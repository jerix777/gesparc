<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Consumable;
use App\Models\ConsumableMovement;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Entrées et sorties de stock des consommables ; le stock ne devient jamais négatif. */
class ConsumableStock
{
    public function receive(Consumable $c, int $quantite, ?string $motif = null): ConsumableMovement
    {
        return $this->move($c, 'entree', $quantite, ['motif' => $motif]);
    }

    public function issue(Consumable $c, int $quantite, ?int $beneficiaireId = null, ?int $requestId = null, ?string $motif = null): ConsumableMovement
    {
        return $this->move($c, 'sortie', $quantite, [
            'beneficiaire_id' => $beneficiaireId,
            'material_request_id' => $requestId,
            'motif' => $motif,
        ]);
    }

    private function move(Consumable $c, string $sens, int $quantite, array $extra): ConsumableMovement
    {
        if ($quantite < 1) {
            throw ValidationException::withMessages(['quantite' => 'La quantité doit être au moins 1.']);
        }

        return DB::transaction(function () use ($c, $sens, $quantite, $extra) {
            $locked = Consumable::whereKey($c->id)->lockForUpdate()->firstOrFail();
            if ($sens === 'sortie' && $locked->stock < $quantite) {
                throw ValidationException::withMessages([
                    'quantite' => "Stock insuffisant pour {$locked->reference} : {$locked->stock} disponible(s), {$quantite} demandé(s).",
                ]);
            }
            $locked->stock += $sens === 'entree' ? $quantite : -$quantite;
            $locked->save();
            $c->setRawAttributes($locked->getAttributes(), true);

            $m = ConsumableMovement::create($extra + [
                'institution_id' => $locked->institution_id,
                'consumable_id' => $locked->id,
                'sens' => $sens,
                'quantite' => $quantite,
                'stock_apres' => $locked->stock,
                'author_id' => Auth::id(),
            ]);
            AuditLog::record('consommable.'.$sens, $locked, "{$quantite} × {$locked->reference}");

            return $m;
        });
    }
}
