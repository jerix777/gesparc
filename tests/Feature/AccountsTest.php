<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;

class AccountsTest extends GesParcTestCase
{
    public function test_account_locks_after_five_failed_logins(): void
    {
        $u = $this->user(Role::Agent);
        for ($i = 0; $i < 5; $i++) {
            $this->post('/connexion', ['email' => $u->email, 'password' => 'mauvais']);
        }
        $this->assertNotNull($u->refresh()->locked_until);
        $this->post('/connexion', ['email' => $u->email, 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_successful_login(): void
    {
        $u = $this->user(Role::Informaticien);
        $this->post('/connexion', ['email' => $u->email, 'password' => 'password'])->assertRedirect('/');
        $this->assertAuthenticatedAs($u);
    }

    public function test_institution_admin_creates_users_in_own_institution_only(): void
    {
        $admin = $this->user(Role::AdminInstitution, $this->a);
        $payload = ['name' => 'Nouvel agent', 'email' => 'nouvel@test.ci', 'role' => 'agent', 'password' => 'motdepasse1', 'active' => 1];

        $this->actingAs($admin)->post('/administration/utilisateurs', $payload)->assertRedirect();
        $this->assertSame($this->a->id, User::where('email', 'nouvel@test.ci')->value('institution_id'));

        $this->actingAs($admin)->post('/administration/utilisateurs', ['email' => 'x@test.ci', 'role' => 'admin_national'] + $payload)->assertSessionHasErrors('role');
        $this->actingAs($admin)->post('/administration/utilisateurs', ['email' => 'y@test.ci', 'institution_id' => $this->b->id] + $payload)->assertSessionHasErrors('institution_id');
    }

    public function test_institution_admin_cannot_edit_foreign_user(): void
    {
        $foreign = $this->user(Role::Agent, $this->b);
        $this->actingAs($this->user(Role::AdminInstitution, $this->a))->get("/administration/utilisateurs/{$foreign->id}/edit")->assertNotFound();
    }

    public function test_only_national_admin_manages_institutions(): void
    {
        $this->actingAs($this->user(Role::AdminInstitution))->get('/administration/institutions')->assertForbidden();
        $this->actingAs($this->user(Role::AdminNational))
            ->post('/administration/institutions', ['code' => 'cour-bke', 'nom' => "Cour d'appel de Bouaké", 'active' => 1])
            ->assertRedirect();
        $this->assertDatabaseHas('institutions', ['code' => 'COUR-BKE']);
    }
}
