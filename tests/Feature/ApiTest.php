<?php

namespace Tests\Feature;

use App\Enums\EquipmentStatus;
use App\Enums\Role;
use App\Models\Consumable;
use App\Models\MaterialRequest;
use Laravel\Sanctum\Sanctum;

class ApiTest extends SigaciTestCase
{
    public function test_token_login_and_me(): void
    {
        $u = $this->user(Role::Agent);
        $token = $this->postJson('/api/v1/jetons', ['email' => $u->email, 'password' => 'password', 'appareil' => 'Poste greffe'])
            ->assertCreated()->assertJsonPath('utilisateur.role', 'agent')->json('jeton');

        $this->withToken($token)->getJson('/api/v1/moi')->assertOk()
            ->assertJsonPath('utilisateur.institution.code', 'TPI-A')
            ->assertJsonPath('droits.deposer_demande', true)
            ->assertJsonPath('droits.voir_inventaire', false);
    }

    public function test_bad_credentials_and_lockout_apply_to_api(): void
    {
        $u = $this->user(Role::Agent);
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/jetons', ['email' => $u->email, 'password' => 'faux'])->assertUnprocessable();
        }
        $this->postJson('/api/v1/jetons', ['email' => $u->email, 'password' => 'password'])
            ->assertUnprocessable()->assertJsonPath('errors.email.0', fn ($m) => str_contains($m, 'verrouillé'));
    }

    public function test_requires_token(): void
    {
        $this->getJson('/api/v1/moi')->assertUnauthorized();
    }

    public function test_inventory_is_scoped_to_institution(): void
    {
        $this->equipment($this->a, ['designation' => 'PC A']);
        $other = $this->equipment($this->b, ['designation' => 'PC B']);
        Sanctum::actingAs($this->user(Role::Informaticien, $this->a));

        $this->getJson('/api/v1/equipements')->assertOk()
            ->assertJsonCount(1, 'donnees')->assertJsonPath('donnees.0.designation', 'PC A');
        $this->getJson("/api/v1/equipements/{$other->id}")->assertNotFound();
    }

    public function test_agent_flow_request_and_failure_from_tpi(): void
    {
        $agent = $this->user(Role::Agent);
        $e = $this->equipment($this->a, ['statut' => EquipmentStatus::Affecte, 'assigned_user_id' => $agent->id]);
        Sanctum::actingAs($agent);

        $this->getJson('/api/v1/mes-equipements')->assertOk()->assertJsonPath('donnees.0.id', $e->id);
        $this->getJson('/api/v1/equipements')->assertForbidden();

        $this->postJson('/api/v1/demandes', ['materiel' => 'Souris', 'quantite' => 1])
            ->assertCreated()->assertJsonPath('donnees.statut', 'en_attente');
        $this->assertSame('api', MaterialRequest::withoutGlobalScopes()->value('source'));
        $this->postJson('/api/v1/demandes', ['materiel' => 'Licorne', 'quantite' => 1])->assertUnprocessable();

        $this->postJson("/api/v1/equipements/{$e->id}/pannes", ['description' => 'Ne démarre plus'])
            ->assertCreated()->assertJsonPath('donnees.statut', 'ouvert');
        $this->assertSame(EquipmentStatus::EnPanne, $e->refresh()->statut);
    }

    public function test_informaticien_decides_cartridge_request_and_stock_is_deducted(): void
    {
        $agent = $this->user(Role::Agent);
        $stock = $this->user(Role::GestionnaireStock);
        $c = Consumable::create(['institution_id' => $this->a->id, 'reference' => 'HP 59A', 'designation' => 'Toner', 'stock' => 3, 'seuil_alerte' => 1]);
        $req = MaterialRequest::create(['institution_id' => $this->a->id, 'numero' => 'DEM-X-001', 'requester_id' => $agent->id, 'categorie' => 'cartouche', 'materiel' => 'Cartouche d’encre', 'quantite' => 2]);

        Sanctum::actingAs($agent);
        $this->postJson("/api/v1/demandes/{$req->id}/decision", ['decision' => 'accorder'])->assertForbidden();

        Sanctum::actingAs($stock);
        $this->getJson('/api/v1/demandes?statut=en_attente')->assertOk()->assertJsonCount(1, 'donnees');
        $this->postJson("/api/v1/demandes/{$req->id}/decision", ['decision' => 'accorder', 'consumable_id' => $c->id])
            ->assertOk()->assertJsonPath('donnees.statut', 'accordee');
        $this->assertSame(1, $c->refresh()->stock);
        $this->postJson("/api/v1/demandes/{$req->id}/decision", ['decision' => 'accorder'])->assertStatus(422);
        $this->getJson('/api/v1/consommables')->assertOk()->assertJsonPath('donnees.0.en_alerte', true);
    }

    public function test_logout_revokes_token(): void
    {
        $u = $this->user(Role::Agent);
        $token = $this->postJson('/api/v1/jetons', ['email' => $u->email, 'password' => 'password'])->json('jeton');
        $this->withToken($token)->deleteJson('/api/v1/jetons/courant')->assertNoContent();
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/moi')->assertUnauthorized();
    }
}
