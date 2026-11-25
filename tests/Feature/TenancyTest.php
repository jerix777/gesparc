<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Equipment;
use App\Models\EquipmentType;

class TenancyTest extends GesParcTestCase
{
    public function test_informaticien_only_sees_own_institution(): void
    {
        $mine = $this->equipment($this->a, ['designation' => 'PC du tribunal A']);
        $other = $this->equipment($this->b, ['designation' => 'PC du tribunal B']);
        $info = $this->user(Role::Informaticien, $this->a);

        $this->actingAs($info)->get('/inventaire')->assertOk()->assertSee('PC du tribunal A')->assertDontSee('PC du tribunal B');
        $this->actingAs($info)->get("/inventaire/{$mine->id}")->assertOk();
        $this->actingAs($info)->get("/inventaire/{$other->id}")->assertNotFound();
        $this->actingAs($info)->post("/inventaire/{$other->id}/reformer", ['motif' => 'x'])->assertNotFound();
    }

    public function test_national_admin_and_auditor_see_all_institutions(): void
    {
        $this->equipment($this->a, ['designation' => 'PC du tribunal A']);
        $this->equipment($this->b, ['designation' => 'PC du tribunal B']);

        foreach ([Role::AdminNational, Role::Auditeur] as $role) {
            $this->actingAs($this->user($role))->get('/inventaire')->assertOk()
                ->assertSee('PC du tribunal A')->assertSee('PC du tribunal B');
        }
    }

    public function test_auditor_is_read_only(): void
    {
        $e = $this->equipment();
        $audit = $this->user(Role::Auditeur);
        $this->actingAs($audit)->get('/inventaire/create')->assertForbidden();
        $this->actingAs($audit)->post("/inventaire/{$e->id}/reformer", ['motif' => 'x'])->assertForbidden();
        $this->actingAs($audit)->get('/demandes/nouvelle')->assertForbidden();
    }

    public function test_agent_cannot_open_inventory(): void
    {
        $this->actingAs($this->user(Role::Agent))->get('/inventaire')->assertForbidden();
        $this->actingAs($this->user(Role::Agent))->get('/')->assertRedirect(route('my-space'));
    }

    public function test_created_equipment_gets_user_institution_and_inventory_number(): void
    {
        $info = $this->user(Role::Informaticien, $this->b);
        $typeId = EquipmentType::first()->id;

        $this->actingAs($info)->post('/inventaire', [
            'equipment_type_id' => $typeId, 'designation' => 'Onduleur APC', 'etat' => 'neuf',
        ])->assertRedirect();

        $e = Equipment::withoutGlobalScopes()->where('designation', 'Onduleur APC')->first();
        $this->assertSame($this->b->id, $e->institution_id);
        $this->assertSame('TPI-B-00001', $e->numero_inventaire);
        $this->assertSame('en_stock', $e->statut->value);
        $this->assertSame(1, $e->movements()->count());
    }
}
