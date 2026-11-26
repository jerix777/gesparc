<?php

namespace App\Services;

use App\Enums\RequestStatus;
use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\Consumable;
use App\Models\MaterialRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Règles des demandes de matériel, partagées par l'interface web et l'API. */
class MaterialRequests
{
    public function __construct(private ConsumableStock $stock, private Notifier $notifier) {}

    public function validateNew(Request $request): array
    {
        return $request->validate([
            'materiel' => ['required', Rule::in(MaterialRequest::MATERIELS)],
            'quantite' => ['required', 'integer', 'between:1,99'],
            'caracteristiques' => ['nullable', 'string', 'max:500', 'required_if:materiel,Autres accessoires informatiques'],
            'observations' => ['nullable', 'string', 'max:1000'],
        ], ['caracteristiques.required_if' => 'Précisez l’accessoire demandé.']);
    }

    public function create(User $user, array $data, string $source = 'web'): MaterialRequest
    {
        $req = MaterialRequest::create($data + [
            'institution_id' => $user->institution_id,
            'numero' => Numbering::next(MaterialRequest::class, 'DEM', $user->institution_id),
            'requester_id' => $user->id,
            'categorie' => $data['materiel'] === 'Cartouche d’encre' ? 'cartouche' : 'materiel',
            'bureau' => $user->bureau,
            'source' => $source,
            'statut' => RequestStatus::EnAttente,
        ]);
        AuditLog::record('demande.creation', $req, $source === 'api' ? 'via application TPI' : null);
        $this->notifier->newRequest($req->load('requester'));

        return $req;
    }

    /** Vérifie qu'un utilisateur peut traiter la demande ; renvoie un message d'erreur et son code HTTP, ou null. */
    public function denial(User $user, MaterialRequest $req): ?array
    {
        if ($req->statut !== RequestStatus::EnAttente) {
            return [422, 'Cette demande est déjà clôturée.'];
        }
        if ($req->requester_id === $user->id) {
            return [403, 'Vous ne pouvez pas traiter votre propre demande.'];
        }
        if ($user->hasRole(Role::GestionnaireStock) && $req->categorie !== 'cartouche') {
            return [403, 'Le gestionnaire de stock ne traite que les demandes de cartouches.'];
        }

        return null;
    }

    public function validateDecision(Request $request, MaterialRequest $req): array
    {
        return $request->validate([
            'decision' => ['required', Rule::in(['accorder', 'refuser'])],
            'motif_refus' => ['nullable', 'required_if:decision,refuser', 'string', 'max:500'],
            'consumable_id' => ['nullable', Rule::exists('consumables', 'id')->where('institution_id', $req->institution_id)],
        ], ['motif_refus.required_if' => 'Le motif du refus est obligatoire.']);
    }

    /** Accorde ou refuse. Pour une cartouche accordée avec un consommable choisi, le stock est déduit. */
    public function decide(User $user, MaterialRequest $req, array $data): void
    {
        $accorder = $data['decision'] === 'accorder';
        DB::transaction(function () use ($user, $req, $data, $accorder) {
            if ($accorder && ! empty($data['consumable_id'])) {
                $c = Consumable::findOrFail($data['consumable_id']);
                $this->stock->issue($c, $req->quantite, $req->requester_id, $req->id, 'Demande '.$req->numero);
            }
            $req->update([
                'statut' => $accorder ? RequestStatus::Accordee : RequestStatus::Refusee,
                'consumable_id' => $accorder ? ($data['consumable_id'] ?? null) : null,
                'decided_by' => $user->id,
                'decided_at' => now(),
                'motif_refus' => $accorder ? null : $data['motif_refus'],
            ]);
            AuditLog::record('demande.'.($accorder ? 'accord' : 'refus'), $req, $data['motif_refus'] ?? null);
        });
        $this->notifier->requestDecided($req->load(['requester', 'decider']));
    }
}
