<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Equipment;
use App\Models\Institution;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

abstract class SigaciTestCase extends TestCase
{
    use RefreshDatabase;

    protected Institution $a;

    protected Institution $b;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->a = Institution::factory()->create(['code' => 'TPI-A']);
        $this->b = Institution::factory()->create(['code' => 'TPI-B']);
    }

    protected function user(Role $role, ?Institution $inst = null): User
    {
        return User::factory()->role($role)->create(
            $role->isCrossInstitution() ? [] : ['institution_id' => ($inst ?? $this->a)->id]
        );
    }

    protected function equipment(?Institution $inst = null, array $attrs = []): Equipment
    {
        return Equipment::factory()->create(['institution_id' => ($inst ?? $this->a)->id] + $attrs);
    }
}
