<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Consumable;
use App\Models\MaterialRequest;

class ConsumableTest extends SigaciTestCase
{
    public function test_stock_manager_adds_reference_receives_and_issues(): void
    {
        $stock = $this->user(Role::GestionnaireStock);
        $agent = $this->user(Role::Agent);

        $this->actingAs($stock)->post('/consommables', [
            'reference' => 'HP 59A', 'designation' => 'Toner noir', 'categorie' => 'toner', 'seuil_alerte' => 2, 'stock_initial' => 5,
        ])->assertSessionHasNoErrors();
        $c = Consumable::withoutGlobalScopes()->first();
        $this->assertSame(5, $c->stock);
        $this->assertSame($this->a->id, $c->institution_id);

        $this->actingAs($stock)->post("/consommables/{$c->id}/mouvement", ['sens' => 'sortie', 'quantite' => 4, 'beneficiaire_id' => $agent->id])->assertSessionHasNoErrors();
        $this->assertSame(1, $c->refresh()->stock);
        $this->assertTrue($c->isLow());

        $this->actingAs($stock)->post("/consommables/{$c->id}/mouvement", ['sens' => 'sortie', 'quantite' => 2])->assertSessionHasErrors('quantite');
        $this->assertSame(1, $c->refresh()->stock, 'Le stock ne devient jamais négatif.');

        $this->actingAs($stock)->post("/consommables/{$c->id}/mouvement", ['sens' => 'entree', 'quantite' => 10, 'motif' => 'BL 2026-114'])->assertSessionHasNoErrors();
        $this->assertSame(11, $c->refresh()->stock);
        $this->assertSame(3, $c->movements()->count());
    }

    public function test_agent_and_other_institution_cannot_move_stock(): void
    {
        $c = Consumable::create(['institution_id' => $this->a->id, 'reference' => 'X1', 'designation' => 'X', 'stock' => 5]);
        $this->actingAs($this->user(Role::Agent))->post("/consommables/{$c->id}/mouvement", ['sens' => 'sortie', 'quantite' => 1])->assertForbidden();
        $this->actingAs($this->user(Role::GestionnaireStock, $this->b))->post("/consommables/{$c->id}/mouvement", ['sens' => 'sortie', 'quantite' => 1])->assertNotFound();
        $this->assertSame(5, $c->refresh()->stock);
    }

    public function test_granting_cartridge_with_insufficient_stock_is_refused_and_request_stays_pending(): void
    {
        $agent = $this->user(Role::Agent);
        $info = $this->user(Role::Informaticien);
        $c = Consumable::create(['institution_id' => $this->a->id, 'reference' => 'HP 59A', 'designation' => 'Toner', 'stock' => 1]);
        $this->actingAs($agent)->post('/demandes', ['materiel' => 'Cartouche d’encre', 'quantite' => 3, 'caracteristiques' => 'HP 59A']);
        $req = MaterialRequest::withoutGlobalScopes()->first();

        $this->actingAs($info)->post("/demandes/{$req->id}/decision", ['decision' => 'accorder', 'consumable_id' => $c->id])->assertSessionHasErrors('quantite');
        $this->assertSame('en_attente', $req->refresh()->statut->value);
        $this->assertSame(1, $c->refresh()->stock);
    }
}
