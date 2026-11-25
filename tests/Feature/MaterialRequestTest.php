<?php

namespace Tests\Feature;

use App\Enums\RequestStatus;
use App\Enums\Role;
use App\Models\MaterialRequest;

class MaterialRequestTest extends GesParcTestCase
{
    public function test_agent_request_is_decided_by_informaticien(): void
    {
        $agent = $this->user(Role::Agent);
        $info = $this->user(Role::Informaticien);

        $this->actingAs($agent)->post('/demandes', ['materiel' => 'Souris', 'quantite' => 2])->assertRedirect('/demandes');
        $req = MaterialRequest::withoutGlobalScopes()->first();
        $this->assertSame('DEM-'.now()->year.'-001', $req->numero);
        $this->assertSame($this->a->id, $req->institution_id);

        $this->actingAs($agent)->post("/demandes/{$req->id}/decision", ['decision' => 'accorder'])->assertForbidden();

        $this->actingAs($info)->post("/demandes/{$req->id}/decision", ['decision' => 'refuser'])->assertSessionHasErrors('motif_refus');
        $this->actingAs($info)->post("/demandes/{$req->id}/decision", ['decision' => 'refuser', 'motif_refus' => 'Stock épuisé'])->assertSessionHasNoErrors();
        $this->assertSame(RequestStatus::Refusee, $req->refresh()->statut);
        $this->assertSame($info->id, $req->decided_by);

        // Une demande clôturée ne peut plus être modifiée.
        $this->actingAs($info)->post("/demandes/{$req->id}/decision", ['decision' => 'accorder'])->assertStatus(422);
    }

    public function test_other_accessories_need_details(): void
    {
        $this->actingAs($this->user(Role::Agent))
            ->post('/demandes', ['materiel' => 'Autres accessoires informatiques', 'quantite' => 1])
            ->assertSessionHasErrors('caracteristiques');
    }

    public function test_informaticien_cannot_decide_own_request(): void
    {
        $info = $this->user(Role::Informaticien);
        $this->actingAs($info)->post('/demandes', ['materiel' => 'Clavier', 'quantite' => 1]);
        $req = MaterialRequest::withoutGlobalScopes()->first();
        $this->actingAs($info)->post("/demandes/{$req->id}/decision", ['decision' => 'accorder'])->assertForbidden();
    }

    public function test_stock_manager_only_decides_cartridges(): void
    {
        $agent = $this->user(Role::Agent);
        $stock = $this->user(Role::GestionnaireStock);
        $this->actingAs($agent)->post('/demandes', ['materiel' => 'Clavier', 'quantite' => 1]);
        $this->actingAs($agent)->post('/demandes', ['materiel' => 'Cartouche d’encre', 'quantite' => 1, 'caracteristiques' => 'HP 59A']);
        [$clavier, $cartouche] = MaterialRequest::withoutGlobalScopes()->orderBy('id')->get()->all();
        $this->assertSame('cartouche', $cartouche->categorie);

        $this->actingAs($stock)->post("/demandes/{$clavier->id}/decision", ['decision' => 'accorder'])->assertForbidden();
        $this->actingAs($stock)->post("/demandes/{$cartouche->id}/decision", ['decision' => 'accorder'])->assertSessionHasNoErrors();
        $this->assertSame(RequestStatus::Accordee, $cartouche->refresh()->statut);
    }

    public function test_informaticien_cannot_see_other_institution_requests(): void
    {
        $agentB = $this->user(Role::Agent, $this->b);
        $this->actingAs($agentB)->post('/demandes', ['materiel' => 'Souris', 'quantite' => 1]);
        $req = MaterialRequest::withoutGlobalScopes()->first();

        $this->actingAs($this->user(Role::Informaticien, $this->a))
            ->post("/demandes/{$req->id}/decision", ['decision' => 'accorder'])->assertNotFound();
    }
}
