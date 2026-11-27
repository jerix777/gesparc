<?php

namespace Tests\Feature;

use App\Enums\Position;
use App\Enums\Role;
use App\Models\ApprovalRule;
use App\Models\Institution;
use App\Models\Staff;
use App\Models\StaffCategory;
use App\Models\StaffPosition;
use App\Models\User;
use App\Services\Personnel\PersonnelSetup;

/** Module Personnel, lot 1 : fiches, fonctions d'autorité, règles, import, comptes. */
class PersonnelTest extends GesParcTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        PersonnelSetup::applyJuridiction($this->a);
        PersonnelSetup::applyJuridiction($this->b);
    }

    private function cat(string $code, ?Institution $inst = null): StaffCategory
    {
        return StaffCategory::withoutGlobalScopes()->where('institution_id', ($inst ?? $this->a)->id)->where('code', $code)->firstOrFail();
    }

    private function staff(string $cat, array $attrs = [], ?Institution $inst = null): Staff
    {
        $inst ??= $this->a;

        return Staff::withoutGlobalScopes()->create($attrs + ['institution_id' => $inst->id, 'staff_category_id' => $this->cat($cat, $inst)->id, 'nom' => 'AGENT', 'prenoms' => fake()->firstName()]);
    }

    /** Utilisateur dont la fiche porte une fonction d'autorité. */
    private function holder(Position $position, string $cat): User
    {
        $user = $this->user(Role::Agent);
        $staff = $this->staff($cat, ['user_id' => $user->id]);
        StaffPosition::withoutGlobalScopes()->create(['institution_id' => $this->a->id, 'staff_id' => $staff->id, 'position' => $position]);

        return $user;
    }

    public function test_juridiction_preset_creates_categories_and_rules(): void
    {
        $this->assertSame(5, StaffCategory::withoutGlobalScopes()->where('institution_id', $this->a->id)->count());
        $vac = $this->cat('greffes')->rules()->where('kind', 'vacances')->first();
        $this->assertSame([Position::ChefAdministratif], $vac->firstLevelPositions());
        $this->assertSame(Position::Chef, $vac->final_level);
        $abs = $this->cat('magistrats_parquet')->rules()->where('kind', 'absence')->first();
        $this->assertSame([Position::Chef], $abs->copyPositions());

        PersonnelSetup::applyJuridiction($this->a); // relancer ne duplique rien
        $this->assertSame(15, ApprovalRule::withoutGlobalScopes()->where('institution_id', $this->a->id)->count());
    }

    public function test_imports_personnel_from_application_file_and_is_idempotent(): void
    {
        // Données fictives au format du fichier HTML de l'application d'origine.
        $people = [
            ['id' => '0-1', 'nom' => 'KONAN', 'prenoms' => 'Ama', 'categorie' => 'Magistrats du siège', 'fonction' => 'Président du tribunal', 'matricule' => '100 001-A', 'telephone' => '0102030405', 'email' => 'pas-un-email', 'statut' => 'Actif'],
            ['id' => '0-2', 'nom' => 'KOUAME', 'prenoms' => 'Yao', 'categorie' => 'Personnel des greffes', 'fonction' => 'Greffier en Chef', 'matricule' => '100 002-B', 'statut' => 'Actif'],
            ['id' => '0-3', 'nom' => 'DIALLO', 'prenoms' => 'Awa', 'categorie' => 'Personnel des greffes', 'fonction' => 'Secrétaire des Greffes et Parquets', 'matricule' => '', 'statut' => 'Actif'],
            ['id' => '0-4', 'nom' => 'BROU', 'prenoms' => 'Eric', 'categorie' => 'Personnel interministériel', 'fonction' => 'Secrétaire de Direction', 'matricule' => '100 004-D', 'statut' => 'Actif'],
            ['id' => '0-5', 'nom' => 'TANO', 'prenoms' => 'Luc', 'categorie' => 'Contractuels du tribunal', 'fonction' => 'Chauffeur', 'matricule' => '', 'statut' => 'Actif'],
        ];
        $html = tempnam(sys_get_temp_dir(), 'gp').'.html';
        file_put_contents($html, '<script>const initialPeople='.json_encode($people, JSON_UNESCAPED_UNICODE).';function x(){}</script>');
        // Sauvegarde plus récente : service, bureau et date de prise de fonction renseignés par l'agent.
        $backup = tempnam(sys_get_temp_dir(), 'gp').'.json';
        file_put_contents($backup, json_encode(['donnees' => ['tpi_people' => json_encode([
            ['id' => '0-2', 'serviceAffectation' => 'Greffe civil', 'numeroPorte' => '12', 'datePriseFonction' => '2021-09-01'],
        ])]]));

        $this->artisan('gesparc:import-personnel', ['fichiers' => [$html, $backup], '--institution' => 'tpi-a'])->assertSuccessful();
        $this->artisan('gesparc:import-personnel', ['fichiers' => [$html, $backup], '--institution' => 'tpi-a'])->assertSuccessful();

        $all = Staff::withoutGlobalScopes()->where('institution_id', $this->a->id)->with('positions', 'service', 'category')->get()->keyBy('source_ref');
        $this->assertCount(5, $all);
        $this->assertNull($all['0-1']->email);
        $this->assertNull($all['0-3']->matricule);
        $this->assertSame('contractuels', $all['0-5']->category->code);
        $this->assertSame('Greffe civil', $all['0-2']->service->nom);
        $this->assertSame('12', $all['0-2']->bureau);
        $this->assertSame('2021-09-01', $all['0-2']->date_prise_service->toDateString());
        $this->assertTrue($all['0-1']->holds(Position::Chef));
        $this->assertTrue($all['0-2']->holds(Position::ChefAdministratif));
        $this->assertTrue($all['0-4']->holds(Position::Secretaire));
        $this->assertFalse($all['0-3']->holds(Position::Secretaire)); // grade des greffes, pas un poste de secrétariat
    }

    public function test_authorities_only_manage_their_categories(): void
    {
        $gc = $this->holder(Position::ChefAdministratif, 'greffes');
        $proc = $this->holder(Position::ChefParquet, 'magistrats_parquet');
        $president = $this->holder(Position::Chef, 'magistrats_siege');
        $greffier = $this->staff('greffes');
        $juge = $this->staff('magistrats_siege');
        $substitut = $this->staff('magistrats_parquet');
        $ailleurs = $this->staff('greffes', [], $this->b);

        $this->actingAs($gc)->get(route('personnel.staff.edit', $greffier))->assertOk();
        $this->actingAs($gc)->get(route('personnel.staff.edit', $juge))->assertForbidden();
        $this->actingAs($gc)->get(route('personnel.staff.show', $ailleurs))->assertNotFound();
        $this->actingAs($proc)->get(route('personnel.staff.edit', $substitut))->assertOk();
        $this->actingAs($proc)->get(route('personnel.staff.edit', $greffier))->assertForbidden();
        $this->actingAs($proc)->get(route('personnel.staff.edit', $proc->staff))->assertForbidden(); // pas sa propre fiche
        $this->actingAs($president)->get(route('personnel.staff.edit', $juge))->assertOk();

        // Le Greffier en Chef ne peut pas classer un agent parmi les magistrats.
        $this->actingAs($gc)->put(route('personnel.staff.update', $greffier), ['nom' => 'X', 'staff_category_id' => $this->cat('magistrats_siege')->id])->assertForbidden();
        $this->actingAs($this->user(Role::Agent))->get(route('personnel.staff.index'))->assertForbidden();
        $this->actingAs($gc)->get(route('personnel.staff.index'))->assertOk()->assertSee($greffier->prenoms)->assertDontSee($ailleurs->prenoms);
    }

    public function test_settings_assign_unique_position_and_update_rule(): void
    {
        $admin = $this->user(Role::AdminInstitution);
        $first = $this->staff('magistrats_siege');
        $second = $this->staff('magistrats_siege');

        $this->actingAs($admin)->post(route('personnel.positions.store'), ['staff_id' => $first->id, 'position' => 'chef'])->assertRedirect();
        $this->actingAs($admin)->post(route('personnel.positions.store'), ['staff_id' => $second->id, 'position' => 'chef'])->assertRedirect();
        $this->assertSame([$second->id], StaffPosition::withoutGlobalScopes()->where('position', 'chef')->where('institution_id', $this->a->id)->pluck('staff_id')->all());

        $cat = $this->cat('contractuels');
        $this->actingAs($admin)->put(route('personnel.rules.update', [$cat, 'absence']), ['first_level' => ['chef_administratif'], 'final_level' => 'chef'])->assertRedirect();
        $rule = $cat->rules()->where('kind', 'absence')->first();
        $this->assertSame(['chef_administratif'], $rule->first_level);
        $this->assertSame(Position::Chef, $rule->final_level);
        $this->actingAs($admin)->put(route('personnel.rules.update', [$cat, 'absence']), [])->assertSessionHasErrors('first_level');

        $this->actingAs($this->holder(Position::Chef, 'magistrats_siege'))->get(route('personnel.settings'))->assertForbidden();
        $this->actingAs($admin)->get(route('personnel.settings'))->assertOk()->assertSee('Greffier en Chef');
    }

    public function test_account_opening_matricule_login_and_forced_password_change(): void
    {
        $gc = $this->holder(Position::ChefAdministratif, 'greffes');
        $agent = $this->staff('greffes', ['matricule' => '123 456-X', 'nom' => 'AMON']);

        $res = $this->actingAs($gc)->post(route('personnel.staff.account', $agent))->assertRedirect();
        preg_match('/provisoire : (\w+)\./', session('ok'), $m);
        $this->assertNotEmpty($m[1] ?? null);
        $user = $agent->fresh()->user;
        $this->assertTrue($user->must_change_password);
        $this->assertSame(Role::Agent, $user->role);
        auth()->logout();

        // Connexion avec le matricule saisi sans espace ni tiret identique à la casse près.
        $this->post('/connexion', ['email' => '123456-x', 'password' => $m[1]])->assertRedirect();
        $this->assertAuthenticatedAs($user);
        $this->get(route('my-space'))->assertRedirect(route('password.edit'));
        $this->put(route('password.update'), ['current_password' => $m[1], 'password' => $m[1], 'password_confirmation' => $m[1]])->assertSessionHasErrors('password');
        $this->put(route('password.update'), ['current_password' => $m[1], 'password' => 'Nouveau-2026', 'password_confirmation' => 'Nouveau-2026'])->assertRedirect();
        $this->assertFalse($user->fresh()->must_change_password);
        $this->get(route('my-space'))->assertOk();
    }

    public function test_leaving_staff_deactivates_account(): void
    {
        $gc = $this->holder(Position::ChefAdministratif, 'greffes');
        $user = $this->user(Role::Agent);
        $agent = $this->staff('greffes', ['user_id' => $user->id]);

        $this->actingAs($gc)->post(route('personnel.staff.leave', $agent), ['date_sortie' => '2026-11-30', 'motif_sortie' => 'Mutation'])->assertRedirect();
        $this->assertSame('sorti', $agent->fresh()->statut);
        $this->assertFalse($user->fresh()->active);
        // (le message de confirmation cite le nom : on vérifie la liste via le lien vers la fiche)
        $this->actingAs($gc)->get(route('personnel.staff.index'))->assertDontSee(route('personnel.staff.show', $agent).'"', false);
        $this->actingAs($gc)->get(route('personnel.staff.index', ['statut' => 'sorti']))->assertSee(route('personnel.staff.show', $agent).'"', false);
    }
}
