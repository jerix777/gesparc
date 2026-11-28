<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class CreateNationalAdminTest extends SigaciTestCase
{
    protected function tearDown(): void
    {
        putenv('SIGACI_ADMIN_MDP');
        parent::tearDown();
    }

    public function test_password_from_environment_creates_admin_without_prompt(): void
    {
        putenv('SIGACI_ADMIN_MDP=Installation-Auto-2026');

        $this->artisan('sigaci:create-admin', ['email' => 'admin@sigaci.local'])->assertSuccessful();

        $admin = User::where('email', 'admin@sigaci.local')->firstOrFail();
        $this->assertSame(Role::AdminNational, $admin->role);
        $this->assertNull($admin->institution_id);
        $this->assertTrue(Hash::check('Installation-Auto-2026', $admin->password));
    }

    public function test_short_password_from_environment_is_refused(): void
    {
        putenv('SIGACI_ADMIN_MDP=court');

        $this->artisan('sigaci:create-admin', ['email' => 'admin@sigaci.local'])->assertFailed();
        $this->assertDatabaseMissing('users', ['email' => 'admin@sigaci.local']);
    }

    public function test_prompts_for_password_when_variable_is_absent(): void
    {
        $this->artisan('sigaci:create-admin', ['email' => 'admin@sigaci.local'])
            ->expectsQuestion('Mot de passe (8 caractères minimum)', 'Saisi-Au-Clavier-1')
            ->assertSuccessful();

        $this->assertTrue(Hash::check('Saisi-Au-Clavier-1', User::where('email', 'admin@sigaci.local')->value('password')));
    }
}
