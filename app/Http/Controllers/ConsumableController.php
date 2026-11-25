<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Consumable;
use App\Models\ConsumableMovement;
use App\Models\User;
use App\Services\ConsumableStock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ConsumableController extends Controller
{
    public function __construct(private ConsumableStock $stock) {}

    public function index(Request $request)
    {
        Gate::authorize('inventory.view');

        return view('consumables.index', [
            'consumables' => Consumable::with('institution')
                ->when($request->boolean('alerte'), fn ($q) => $q->low())
                ->orderBy('reference')->get(),
            'movements' => ConsumableMovement::with(['consumable', 'author', 'beneficiaire'])->latest('id')->limit(30)->get(),
            'agents' => User::where('institution_id', $request->user()->institution_id)->where('active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        Gate::authorize('consumables.manage');
        $institutionId = $request->user()->institution_id;
        $data = $request->validate([
            'reference' => ['required', 'string', 'max:60', Rule::unique('consumables')->where('institution_id', $institutionId)],
            'designation' => ['required', 'string', 'max:255'],
            'categorie' => ['required', Rule::in(array_keys(Consumable::CATEGORIES))],
            'imprimantes_compatibles' => ['nullable', 'string', 'max:255'],
            'seuil_alerte' => ['required', 'integer', 'min:0', 'max:1000'],
            'stock_initial' => ['nullable', 'integer', 'min:0', 'max:100000'],
        ]);
        $c = Consumable::create(collect($data)->except('stock_initial')->all() + ['institution_id' => $institutionId]);
        if (! empty($data['stock_initial'])) {
            $this->stock->receive($c, $data['stock_initial'], 'Stock initial');
        }
        AuditLog::record('consommable.creation', $c);

        return back()->with('ok', "Consommable {$c->reference} ajouté.");
    }

    public function move(Request $request, Consumable $consumable)
    {
        Gate::authorize('consumables.manage');
        $data = $request->validate([
            'sens' => ['required', Rule::in(['entree', 'sortie'])],
            'quantite' => ['required', 'integer', 'min:1', 'max:100000'],
            'beneficiaire_id' => ['nullable', Rule::exists('users', 'id')->where('institution_id', $consumable->institution_id)],
            'motif' => ['nullable', 'string', 'max:255'],
        ]);
        $data['sens'] === 'entree'
            ? $this->stock->receive($consumable, $data['quantite'], $data['motif'] ?? null)
            : $this->stock->issue($consumable, $data['quantite'], $data['beneficiaire_id'] ?? null, null, $data['motif'] ?? null);

        return back()->with('ok', ($data['sens'] === 'entree' ? 'Entrée' : 'Sortie')." enregistrée. Stock {$consumable->reference} : {$consumable->stock}.");
    }
}
