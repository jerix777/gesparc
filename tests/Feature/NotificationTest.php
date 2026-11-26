<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Consumable;
use App\Models\MaterialRequest;
use App\Notifications\GesParcMessage;
use Illuminate\Support\Facades\Notification;

class NotificationTest extends GesParcTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
    }

    public function test_new_request_notifies_institution_it_staff_and_decision_notifies_requester(): void
    {
        $agent = $this->user(Role::Agent);
        $info = $this->user(Role::Informaticien);
        $stock = $this->user(Role::GestionnaireStock);
        $foreignInfo = $this->user(Role::Informaticien, $this->b);

        $this->actingAs($agent)->post('/demandes', ['materiel' => 'Souris', 'quantite' => 1]);
        Notification::assertSentTo($info, GesParcMessage::class, fn ($n) => str_contains($n->subject, 'Nouvelle demande'));
        Notification::assertNotSentTo([$stock, $foreignInfo, $agent], GesParcMessage::class);

        $req = MaterialRequest::withoutGlobalScopes()->first();
        $this->actingAs($info)->post("/demandes/{$req->id}/decision", ['decision' => 'refuser', 'motif_refus' => 'Stock épuisé']);
        Notification::assertSentTo($agent, GesParcMessage::class, fn ($n) => str_contains($n->subject, 'refusée') && in_array('Motif : Stock épuisé', $n->lines, true));
    }

    public function test_cartridge_request_also_notifies_stock_manager(): void
    {
        $stock = $this->user(Role::GestionnaireStock);
        $this->actingAs($this->user(Role::Agent))->post('/demandes', ['materiel' => 'Cartouche d’encre', 'quantite' => 1, 'caracteristiques' => 'HP 59A']);
        Notification::assertSentTo($stock, GesParcMessage::class);
    }

    public function test_failure_and_retirement_notify_the_right_people(): void
    {
        $info = $this->user(Role::Informaticien);
        $resp = $this->user(Role::Responsable);
        $e = $this->equipment();

        $this->actingAs($this->user(Role::Informaticien))->post("/inventaire/{$e->id}/panne", ['description' => 'Écran noir']);
        Notification::assertSentTo($info, GesParcMessage::class, fn ($n) => str_contains($n->subject, 'Panne'));

        $this->actingAs($info)->post("/inventaire/{$e->id}/proposer-reforme", ['motif' => 'Obsolète']);
        Notification::assertSentTo($resp, GesParcMessage::class, fn ($n) => str_contains($n->subject, 'Réforme à valider'));
    }

    public function test_low_stock_alert_only_when_crossing_threshold(): void
    {
        $info = $this->user(Role::Informaticien);
        $stock = $this->user(Role::GestionnaireStock);
        $c = Consumable::create(['institution_id' => $this->a->id, 'reference' => 'HP 59A', 'designation' => 'Toner', 'stock' => 4, 'seuil_alerte' => 2]);

        $this->actingAs($stock)->post("/consommables/{$c->id}/mouvement", ['sens' => 'sortie', 'quantite' => 1]); // 3 : au-dessus
        Notification::assertNothingSent();
        $this->actingAs($stock)->post("/consommables/{$c->id}/mouvement", ['sens' => 'sortie', 'quantite' => 1]); // 2 : seuil atteint
        Notification::assertSentToTimes($info, GesParcMessage::class, 1);
        $this->actingAs($stock)->post("/consommables/{$c->id}/mouvement", ['sens' => 'sortie', 'quantite' => 1]); // 1 : déjà en alerte
        Notification::assertSentToTimes($info, GesParcMessage::class, 1);
    }

    public function test_notifications_can_be_disabled(): void
    {
        config(['gesparc.notifications' => false]);
        $this->user(Role::Informaticien);
        $this->actingAs($this->user(Role::Agent))->post('/demandes', ['materiel' => 'Souris', 'quantite' => 1]);
        Notification::assertNothingSent();
    }

    public function test_mail_renders_in_french(): void
    {
        $info = $this->user(Role::Informaticien);
        $mail = (new GesParcMessage('Sujet', ['Ligne 1'], 'Ouvrir', 'https://x.test'))->toMail($info);
        $this->assertSame('[GesParc] Sujet', $mail->subject);
        $this->assertStringContainsString('Bonjour', $mail->greeting);
    }
}
