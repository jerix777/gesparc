<?php

namespace Tests\Feature;

use App\Enums\Position;
use App\Enums\Role;
use App\Models\Institution;
use App\Models\LeaveRequest;
use App\Models\PublicHoliday;
use App\Models\Service;
use App\Models\Staff;
use App\Models\StaffCategory;
use App\Models\StaffPosition;
use App\Models\User;
use App\Models\VacationCampaign;
use App\Models\VacationList;
use App\Models\VacationRequest;
use App\Services\Personnel\PersonnelSetup;
use App\Services\Personnel\WorkingDays;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/** Circuits du lot 1 : absences (validation électronique) et vacances judiciaires (validation du Président). */
class PersonnelCircuitsTest extends GesParcTestCase
{
    private Service $greffe;

    private User $president;

    private User $procureur;

    private User $gc;

    private User $adjoint;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-11-26 10:00'); // dates des scénarios fixes (décembre 2026, campagne 2027)
        PersonnelSetup::applyJuridiction($this->a);
        $this->greffe = Service::withoutGlobalScopes()->create(['institution_id' => $this->a->id, 'nom' => 'Greffe civil']);
        $this->president = $this->agent('magistrats_siege', [Position::Chef]);
        $this->procureur = $this->agent('magistrats_parquet', [Position::ChefParquet]);
        $this->gc = $this->agent('greffes', [Position::ChefAdministratif]);
        $this->adjoint = $this->agent('greffes', [Position::AdjointAdministratif]);
    }

    /** Agent avec compte et fiche (et éventuellement des fonctions d'autorité). */
    private function agent(string $cat, array $positions = [], ?int $serviceId = null, ?Institution $inst = null): User
    {
        $inst ??= $this->a;
        $user = $this->user(Role::Agent, $inst);
        $staff = Staff::withoutGlobalScopes()->create([
            'institution_id' => $inst->id, 'user_id' => $user->id, 'nom' => mb_strtoupper(fake()->lastName()), 'prenoms' => fake()->firstName(),
            'staff_category_id' => StaffCategory::withoutGlobalScopes()->where('institution_id', $inst->id)->where('code', $cat)->value('id'),
            'service_id' => $serviceId,
        ]);
        foreach ($positions as $p) {
            StaffPosition::withoutGlobalScopes()->create(['institution_id' => $inst->id, 'staff_id' => $staff->id, 'position' => $p]);
        }

        return $user->fresh();
    }

    private function leave(User $user, string $debut = '2026-12-07', string $fin = '2026-12-11'): LeaveRequest
    {
        $this->actingAs($user)->post(route('personnel.absences.store'), ['motif' => 'Maladie', 'debut' => $debut, 'fin' => $fin])->assertRedirect();

        return LeaveRequest::withoutGlobalScopes()->latest('id')->firstOrFail();
    }

    public function test_working_days_exclude_weekends_and_holidays(): void
    {
        $this->assertSame('2026-04-05', WorkingDays::easter(2026)->toDateString());
        $this->assertArrayHasKey('2026-04-06', WorkingDays::computed(2026)); // lundi de Pâques
        $this->assertArrayHasKey('2026-05-14', WorkingDays::computed(2026)); // Ascension
        PublicHoliday::create(['date' => '2026-12-25', 'libelle' => 'Noël']);
        // du lundi 21 au dimanche 27 décembre 2026 : 5 jours ouvrés moins Noël (vendredi)
        $this->assertSame(4, WorkingDays::count(now()->setDate(2026, 12, 21), now()->setDate(2026, 12, 27)));
    }

    public function test_greffier_absence_is_decided_electronically_by_greffier_en_chef(): void
    {
        $agent = $this->agent('greffes', [], $this->greffe->id);
        $l = $this->leave($agent);
        $this->assertSame('en_attente', $l->statut);
        $this->assertSame(5, $l->jours_ouvres);

        // Ni le Procureur ni l'agent lui-même ne peuvent décider ; l'adjoint non plus pour un greffier.
        $this->actingAs($this->procureur)->get(route('personnel.absences.show', $l))->assertForbidden();
        $this->actingAs($agent)->post(route('personnel.absences.decide', $l), ['decision' => 'accorder'])->assertForbidden();
        $this->actingAs($this->adjoint)->post(route('personnel.absences.decide', $l), ['decision' => 'accorder'])->assertForbidden();

        $this->actingAs($this->gc)->post(route('personnel.absences.decide', $l), ['decision' => 'accorder'])->assertRedirect();
        $l->refresh();
        $this->assertSame('accordee', $l->statut);
        $this->assertSame('Greffier en Chef', $l->decider_fonction);
        $this->assertNotNull($l->verification_code);
        $this->assertSame(['depot', 'accord'], $l->events()->pluck('action')->all());

        // Décision imprimable par l'agent, vérifiable publiquement par son code.
        $this->actingAs($agent)->get(route('personnel.absences.document', $l))->assertOk()->assertSee($l->numero)->assertSee('<svg', false);
        auth()->logout();
        $this->get(route('verification', $l->verification_code))->assertOk()->assertSee('authentique');
        $this->get(route('verification', 'FAUXCODE1234'))->assertNotFound();
    }

    public function test_interministerial_absence_can_be_signed_po_by_adjoint_and_refusal_needs_motive(): void
    {
        $agent = $this->agent('interministeriel', [], $this->greffe->id);
        $l = $this->leave($agent);
        $this->actingAs($this->adjoint)->post(route('personnel.absences.decide', $l), ['decision' => 'refuser'])->assertSessionHasErrors('motif_refus');
        $this->actingAs($this->adjoint)->post(route('personnel.absences.decide', $l), ['decision' => 'accorder'])->assertRedirect();
        $this->assertTrue($l->fresh()->signe_po);

        $l2 = $this->leave($agent, '2026-12-14', '2026-12-15');
        $this->actingAs($this->gc)->post(route('personnel.absences.decide', $l2), ['decision' => 'refuser', 'motif_refus' => LeaveRequest::MOTIFS_REFUS[1]])->assertRedirect();
        $this->assertSame('refusee', $l2->fresh()->statut);
        $this->assertSame(LeaveRequest::MOTIFS_REFUS[1], $l2->fresh()->motif_refus);
    }

    public function test_overlap_exemption_and_procureur_own_request_goes_to_president(): void
    {
        $agent = $this->agent('greffes', [], $this->greffe->id);
        $this->leave($agent);
        $this->actingAs($agent)->post(route('personnel.absences.store'), ['motif' => 'Maladie', 'debut' => '2026-12-10', 'fin' => '2026-12-12'])->assertSessionHasErrors('debut');
        $this->actingAs($agent)->post(route('personnel.absences.store'), ['motif' => 'Autre motif', 'debut' => '2026-12-21', 'fin' => '2026-12-21'])->assertSessionHasErrors('precision');
        $this->actingAs($this->president)->post(route('personnel.absences.store'), ['motif' => 'Maladie', 'debut' => '2026-12-21', 'fin' => '2026-12-22'])->assertSessionHasErrors('staff_id');

        // Le Procureur ne décide pas de sa propre demande : elle va au Président.
        $l = $this->leave($this->procureur);
        $this->assertSame('transmise', $l->statut);
        $this->actingAs($this->procureur)->post(route('personnel.absences.decide', $l), ['decision' => 'accorder'])->assertForbidden();
        $this->actingAs($this->president)->post(route('personnel.absences.decide', $l), ['decision' => 'accorder'])->assertRedirect();
        $this->assertSame('Président du Tribunal', $l->fresh()->decider_fonction);

        // Un substitut : décision du Procureur, copie au Président (qui peut consulter).
        $substitut = $this->agent('magistrats_parquet');
        $l2 = $this->leave($substitut);
        $this->actingAs($this->procureur)->post(route('personnel.absences.decide', $l2), ['decision' => 'accorder'])->assertRedirect();
        $this->actingAs($this->president)->get(route('personnel.absences.show', $l2))->assertOk();
        $this->actingAs($this->gc)->get(route('personnel.absences.show', $l2))->assertForbidden();
    }

    public function test_authority_can_file_request_for_agent_without_account_and_agent_can_cancel(): void
    {
        $sansCompte = Staff::withoutGlobalScopes()->create(['institution_id' => $this->a->id, 'nom' => 'SANS', 'prenoms' => 'Compte',
            'staff_category_id' => StaffCategory::withoutGlobalScopes()->where('institution_id', $this->a->id)->where('code', 'contractuels')->value('id')]);
        $this->actingAs($this->gc)->post(route('personnel.absences.store'), ['staff_id' => $sansCompte->id, 'motif' => 'Maladie', 'debut' => '2026-12-07', 'fin' => '2026-12-08'])->assertRedirect();
        $this->assertSame(1, LeaveRequest::where('staff_id', $sansCompte->id)->count());
        $juge = Staff::withoutGlobalScopes()->create(['institution_id' => $this->a->id, 'nom' => 'JUGE', 'staff_category_id' => $this->president->staff->staff_category_id]);
        $this->actingAs($this->gc)->post(route('personnel.absences.store'), ['staff_id' => $juge->id, 'motif' => 'Maladie', 'debut' => '2026-12-07', 'fin' => '2026-12-08'])->assertForbidden();

        $agent = $this->agent('greffes');
        $l = $this->leave($agent);
        $this->actingAs($agent)->post(route('personnel.absences.cancel', $l))->assertRedirect();
        $this->assertSame('annulee', $l->fresh()->statut);
    }

    public function test_vacation_two_level_validation_with_service_quota(): void
    {
        $agents = collect(range(1, 3))->map(fn () => $this->agent('greffes', [], $this->greffe->id)); // + aucun autre membre : effectif 3 → 2 départs max
        $this->actingAs($this->gc)->post(route('personnel.vacations.open'), ['annee' => 2027, 'mois' => ['Août', 'Septembre']])->assertRedirect();
        $campaign = VacationCampaign::where('annee', 2027)->firstOrFail();

        $this->actingAs($agents[0])->post(route('personnel.vacations.store', $campaign), ['mois' => 'Août'])->assertSessionHasNoErrors();
        $this->actingAs($agents[1])->post(route('personnel.vacations.store', $campaign), ['mois' => 'Août'])->assertSessionHasNoErrors();
        $this->actingAs($agents[2])->post(route('personnel.vacations.store', $campaign), ['mois' => 'Août'])->assertSessionHasErrors('mois'); // quota
        $this->actingAs($agents[2])->post(route('personnel.vacations.store', $campaign), ['mois' => 'Septembre'])->assertSessionHasNoErrors();
        $this->actingAs($agents[0])->post(route('personnel.vacations.store', $campaign), ['mois' => 'Septembre'])->assertSessionHasErrors('mois'); // déjà une demande

        // Un agent sans service est bloqué.
        $sansService = $this->agent('greffes');
        $this->actingAs($sansService)->post(route('personnel.vacations.store', $campaign), ['mois' => 'Août'])->assertSessionHasErrors('mois');

        $r0 = VacationRequest::where('staff_id', $agents[0]->staff->id)->first();
        $this->assertSame('en_attente', $r0->statut);
        $this->actingAs($this->president)->post(route('personnel.vacations.decide', $r0), ['decision' => 'valider'])->assertForbidden(); // pas encore transmise
        $this->actingAs($this->gc)->post(route('personnel.vacations.decide', $r0), ['decision' => 'valider', 'mois' => 'Août'])->assertRedirect();
        $this->assertSame('transmise', $r0->fresh()->statut);
        $this->assertSame('Août', $r0->fresh()->mois_retenu);

        // Le Président change le mois : observation obligatoire ; Septembre déjà à 1/2 → possible.
        $this->actingAs($this->president)->post(route('personnel.vacations.decide', $r0), ['decision' => 'valider', 'mois' => 'Septembre'])->assertSessionHasErrors('observation');
        $this->actingAs($this->president)->post(route('personnel.vacations.decide', $r0), ['decision' => 'valider', 'mois' => 'Septembre', 'observation' => 'Audiences en août'])->assertRedirect();
        $r0->refresh();
        $this->assertSame('validee', $r0->statut);
        $this->assertSame('Septembre', $r0->mois_accorde);

        // Septembre est maintenant à 2/2 : le GC ne peut plus y retenir un troisième agent.
        $r1 = VacationRequest::where('staff_id', $agents[1]->staff->id)->first();
        $this->actingAs($this->gc)->post(route('personnel.vacations.decide', $r1), ['decision' => 'valider', 'mois' => 'Septembre'])->assertSessionHasErrors('mois');
        $this->actingAs($this->gc)->post(route('personnel.vacations.decide', $r1), ['decision' => 'refuser', 'motif_refus' => VacationRequest::MOTIFS_REFUS[0]])->assertRedirect();
        $this->assertSame('refusee', $r1->fresh()->statut);

        $this->actingAs($this->gc)->get(route('personnel.vacations.index', ['annee' => 2027]))->assertOk()->assertSee('Répartition par service')->assertSee('Greffe civil');
    }

    public function test_magistrat_vacation_goes_directly_to_president_and_closure_freezes_lists(): void
    {
        Storage::fake();
        $juge = $this->agent('magistrats_siege', [], $this->greffe->id);
        $greffier = $this->agent('greffes', [], $this->greffe->id);
        $campaign = VacationCampaign::create(['institution_id' => $this->a->id, 'annee' => 2027, 'mois' => ['Août', 'Septembre']]);

        $this->actingAs($juge)->post(route('personnel.vacations.store', $campaign), ['mois' => 'Août'])->assertSessionHasNoErrors();
        $rj = VacationRequest::where('staff_id', $juge->staff->id)->first();
        $this->assertSame('transmise', $rj->statut);
        $this->actingAs($greffier)->post(route('personnel.vacations.store', $campaign), ['mois' => 'Septembre'])->assertSessionHasNoErrors();
        $rg = VacationRequest::where('staff_id', $greffier->staff->id)->first();
        $this->actingAs($this->gc)->post(route('personnel.vacations.decide', $rg), ['decision' => 'valider'])->assertRedirect();

        // Clôture refusée tant que des demandes attendent le Président, et réservée au Président.
        $this->actingAs($this->gc)->post(route('personnel.vacations.close', $campaign))->assertForbidden();
        $this->actingAs($this->president)->post(route('personnel.vacations.close', $campaign))->assertSessionHasErrors('campagne');
        $this->actingAs($this->president)->post(route('personnel.vacations.decide', $rj), ['decision' => 'valider'])->assertRedirect();
        $this->actingAs($this->president)->post(route('personnel.vacations.decide', $rg), ['decision' => 'valider'])->assertRedirect();
        $this->actingAs($this->president)->post(route('personnel.vacations.close', $campaign))->assertSessionHasNoErrors();

        $campaign->refresh();
        $this->assertSame('cloturee', $campaign->statut);
        $lists = VacationList::where('vacation_campaign_id', $campaign->id)->get()->keyBy('numero');
        $this->assertEqualsCanonicalizing(['LD-2027-AOUT', 'LD-2027-SEPT', 'LD-2027-GEN'], $lists->keys()->all());
        $this->assertCount(0, $lists['LD-2027-AOUT']->lignes); // le juge (magistrat) n'y figure pas
        $this->assertSame($greffier->staff->full_name, $lists['LD-2027-SEPT']->lignes[0]['nom']);

        // Plus aucune demande après clôture ; liste imprimable, scan signé archivé.
        $autre = $this->agent('greffes', [], $this->greffe->id);
        $this->actingAs($autre)->post(route('personnel.vacations.store', $campaign), ['mois' => 'Août'])->assertSessionHasErrors('campagne');
        $this->actingAs($this->president)->get(route('personnel.vacations.lists.show', $lists['LD-2027-GEN']))->assertOk()->assertSee('LISTE DÉFINITIVE');
        $this->actingAs($this->gc)->post(route('personnel.vacations.lists.upload', $lists['LD-2027-GEN']), ['scan' => UploadedFile::fake()->create('liste.pdf', 200, 'application/pdf')])->assertRedirect();
        $this->assertNotNull($lists['LD-2027-GEN']->fresh()->scan_path);
        Storage::assertExists($lists['LD-2027-GEN']->fresh()->scan_path);

        // Réouverture avec motif, puis nouvelle clôture : listes en version 2.
        $this->actingAs($this->president)->post(route('personnel.vacations.reopen', $campaign), ['motif' => 'Oubli'])->assertRedirect();
        $this->actingAs($this->president)->post(route('personnel.vacations.close', $campaign))->assertSessionHasNoErrors();
        $this->assertTrue(VacationList::where('numero', 'LD-2027-GEN-V2')->exists());
    }
}
