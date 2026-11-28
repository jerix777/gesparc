<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Setting;

class SettingsTest extends SigaciTestCase
{
    public function test_default_name_is_shown_everywhere(): void
    {
        $this->get('/connexion')->assertOk()->assertSee('<b>SIGACI</b>', false);
        $this->actingAs($this->user(Role::Agent))->get('/mon-espace')->assertOk()
            ->assertSee('– SIGACI</title>', false)->assertSee('Bienvenue sur SIGACI !');
    }

    public function test_national_admin_renames_the_application(): void
    {
        $admin = $this->user(Role::AdminNational);
        $this->actingAs($admin)->get('/administration/parametres')->assertOk()->assertSee('Nom de l');
        $this->actingAs($admin)->put('/administration/parametres', ['sigle' => 'Gestion Du Personnel', 'nom' => ''])
            ->assertRedirect('/administration/parametres');

        $this->assertSame('Gestion Du Personnel', config('sigaci.sigle'));
        $this->assertSame("Système Intégré de Gestion et d'Administration de Côte d'Ivoire", config('sigaci.nom'));
        auth()->logout();
        $this->get('/connexion')->assertOk()->assertSee('<b>Gestion Du Personnel</b>', false);
        $this->actingAs($this->user(Role::Agent))->get('/mon-espace')
            ->assertSee('– Gestion Du Personnel</title>', false)->assertSee('Bienvenue sur Gestion Du Personnel !')
            ->assertDontSee('<b>SIGACI</b>', false);
        $this->assertDatabaseHas('audit_logs', ['action' => 'parametres.modification']);

        // Un nom vide est refusé ; la description vide revient à celle par défaut.
        $this->actingAs($admin)->put('/administration/parametres', ['sigle' => ''])->assertSessionHasErrors('sigle');
        Setting::store(['sigle' => 'SIGACI']);
        $this->assertSame('SIGACI', config('sigaci.sigle'));
    }

    public function test_only_national_admin_can_change_settings(): void
    {
        foreach ([Role::AdminInstitution, Role::Auditeur, Role::Agent] as $role) {
            $u = $this->user($role);
            $this->actingAs($u)->get('/administration/parametres')->assertForbidden();
            $this->actingAs($u)->put('/administration/parametres', ['sigle' => 'X'])->assertForbidden();
        }
        $this->assertSame('SIGACI', config('sigaci.sigle'));
    }
}
