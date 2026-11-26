<?php

namespace Tests\Feature;

use App\Enums\EquipmentStatus;
use App\Enums\IncidentStatus;
use App\Enums\Role;
use App\Models\Incident;

class LifecycleTest extends GesParcTestCase
{
    public function test_full_cycle_assign_failure_repair(): void
    {
        $info = $this->user(Role::Informaticien);
        $agent = $this->user(Role::Agent);
        $e = $this->equipment();

        $this->actingAs($info)->post("/inventaire/{$e->id}/affecter", ['to_user_id' => $agent->id])->assertSessionHasNoErrors();
        $e->refresh();
        $this->assertSame(EquipmentStatus::Affecte, $e->statut);
        $this->assertSame($agent->id, $e->assigned_user_id);

        // L'agent signale lui-même la panne de son matériel.
        $this->actingAs($agent)->post("/inventaire/{$e->id}/panne", ['description' => 'Écran noir'])->assertSessionHasNoErrors();
        $this->assertSame(EquipmentStatus::EnPanne, $e->refresh()->statut);
        $incident = Incident::withoutGlobalScopes()->first();
        $this->assertSame('PAN-'.now()->year.'-001', $incident->numero);

        $this->actingAs($info)->post("/pannes/{$incident->id}/maintenance", ['prestataire' => 'SOS Info'])->assertSessionHasNoErrors();
        $this->assertSame(EquipmentStatus::EnMaintenance, $e->refresh()->statut);

        $this->actingAs($info)->post("/pannes/{$incident->id}/cloturer", ['issue' => 'repare', 'cout' => 25000])->assertSessionHasNoErrors();
        $this->assertSame(EquipmentStatus::Affecte, $e->refresh()->statut, 'Un équipement réparé retourne à son affectataire.');
        $this->assertSame(IncidentStatus::Resolu, $incident->refresh()->statut);

        $this->actingAs($info)->post("/inventaire/{$e->id}/restituer")->assertSessionHasNoErrors();
        $this->assertSame(EquipmentStatus::EnStock, $e->refresh()->statut);
        $this->assertNull($e->assigned_user_id);

        $this->assertSame(['affectation', 'panne', 'maintenance', 'reparation', 'restitution'],
            $e->movements()->reorder('id')->pluck('type')->all());
    }

    public function test_irreparable_equipment_is_proposed_then_validated_by_responsable(): void
    {
        $info = $this->user(Role::Informaticien);
        $resp = $this->user(Role::Responsable);
        $e = $this->equipment();
        $this->actingAs($info)->post("/inventaire/{$e->id}/panne", ['description' => 'Carte mère grillée']);
        $incident = Incident::withoutGlobalScopes()->first();
        $this->actingAs($info)->post("/pannes/{$incident->id}/cloturer", ['issue' => 'irreparable']);

        $e->refresh();
        $this->assertSame(EquipmentStatus::EnPanne, $e->statut, 'Le statut ne change qu’à la validation.');
        $this->assertTrue($e->hasPendingRetirement());
        $this->assertSame(IncidentStatus::Irreparable, $incident->refresh()->statut);

        // L'informaticien ne valide pas lui-même.
        $this->actingAs($info)->post("/inventaire/{$e->id}/valider-reforme")->assertForbidden();
        $this->actingAs($resp)->get('/tableau-de-bord')->assertSee('Réformes à valider');
        $this->actingAs($resp)->post("/inventaire/{$e->id}/valider-reforme")->assertSessionHasNoErrors();
        $this->assertSame(EquipmentStatus::Reforme, $e->refresh()->statut);
    }

    public function test_responsable_can_reject_a_retirement_proposal(): void
    {
        $info = $this->user(Role::Informaticien);
        $resp = $this->user(Role::Responsable);
        $agent = $this->user(Role::Agent);
        $e = $this->equipment();
        $this->actingAs($info)->post("/inventaire/{$e->id}/affecter", ['to_user_id' => $agent->id]);

        $this->actingAs($info)->post("/inventaire/{$e->id}/proposer-reforme", ['motif' => 'Trop lent'])->assertSessionHasNoErrors();
        $this->actingAs($info)->post("/inventaire/{$e->id}/proposer-reforme", ['motif' => 'Encore'])->assertSessionHasErrors('statut');
        $this->actingAs($resp)->post("/inventaire/{$e->id}/rejeter-reforme")->assertSessionHasErrors('motif');
        $this->actingAs($resp)->post("/inventaire/{$e->id}/rejeter-reforme", ['motif' => 'Encore utilisable'])->assertSessionHasNoErrors();

        $e->refresh();
        $this->assertSame(EquipmentStatus::Affecte, $e->statut);
        $this->assertFalse($e->hasPendingRetirement());
        $this->assertSame($agent->id, $e->assigned_user_id);
        $this->actingAs($resp)->post("/inventaire/{$e->id}/valider-reforme")->assertSessionHasErrors('statut');
    }

    public function test_invalid_transitions_are_refused(): void
    {
        $info = $this->user(Role::Informaticien);
        $e = $this->equipment();

        $this->actingAs($info)->post("/inventaire/{$e->id}/restituer")->assertSessionHasErrors('statut');
        $this->actingAs($info)->post("/inventaire/{$e->id}/proposer-reforme", ['motif' => 'Obsolète']);
        $this->actingAs($this->user(Role::Responsable))->post("/inventaire/{$e->id}/valider-reforme");
        $this->actingAs($info)->post("/inventaire/{$e->id}/affecter", ['bureau' => '3'])->assertSessionHasErrors('statut');
        $this->actingAs($info)->post("/inventaire/{$e->id}/proposer-reforme", ['motif' => 'x'])->assertSessionHasErrors('statut');
        $this->assertSame(EquipmentStatus::Reforme, $e->refresh()->statut);
    }

    public function test_cannot_assign_to_agent_of_another_institution(): void
    {
        $info = $this->user(Role::Informaticien, $this->a);
        $foreign = $this->user(Role::Agent, $this->b);
        $e = $this->equipment($this->a);

        $this->actingAs($info)->post("/inventaire/{$e->id}/affecter", ['to_user_id' => $foreign->id])->assertSessionHasErrors('to_user_id');
        $this->assertSame(EquipmentStatus::EnStock, $e->refresh()->statut);
    }

    public function test_agent_cannot_report_failure_on_equipment_not_assigned_to_them(): void
    {
        $e = $this->equipment();
        $this->actingAs($this->user(Role::Agent))->post("/inventaire/{$e->id}/panne", ['description' => 'x'])->assertForbidden();
    }

    public function test_responsable_validates_but_cannot_edit_or_propose(): void
    {
        $resp = $this->user(Role::Responsable);
        $e = $this->equipment();
        $this->actingAs($resp)->get("/inventaire/{$e->id}/edit")->assertForbidden();
        $this->actingAs($resp)->post("/inventaire/{$e->id}/proposer-reforme", ['motif' => 'Obsolète'])->assertForbidden();
    }
}
