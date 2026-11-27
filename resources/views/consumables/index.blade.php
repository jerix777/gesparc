@extends('layouts.app')
@section('title', 'Consommables')
@section('content')
<div class="pagehead"><div><h1>Consommables</h1><div class="sub">Cartouches, toners et accessoires suivis en quantité</div></div>
  <div class="actions"><a class="btn secondary" href="{{ route('consumables.index', request()->boolean('alerte') ? [] : ['alerte' => 1]) }}">{{ request()->boolean('alerte') ? 'Tout afficher' : 'Stocks en alerte seulement' }}</a></div></div>

@can('consumables.manage')
<details class="panel"><summary>Ajouter une référence</summary>
<form method="post" action="{{ route('consumables.store') }}" style="margin-top:12px">@csrf
<div class="grid cols-3">
  <div class="field"><label>Référence *</label><input name="reference" required placeholder="HP 59A" value="{{ old('reference') }}"></div>
  <div class="field"><label>Désignation *</label><input name="designation" required placeholder="Toner noir HP 59A" value="{{ old('designation') }}"></div>
  <div class="field"><label>Catégorie *</label><select name="categorie">@foreach (\App\Models\Consumable::CATEGORIES as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></div>
  <div class="field"><label>Imprimantes compatibles</label><input name="imprimantes_compatibles" placeholder="HP LaserJet Pro M404dn" value="{{ old('imprimantes_compatibles') }}"></div>
  <div class="field"><label>Seuil d'alerte *</label><input type="number" min="0" name="seuil_alerte" value="{{ old('seuil_alerte', 2) }}" required></div>
  <div class="field"><label>Stock initial</label><input type="number" min="0" name="stock_initial" value="{{ old('stock_initial', 0) }}"></div>
</div><button>Ajouter</button></form></details>
@endcan

<div class="panel tablewrap"><table><thead><tr><th>Référence</th><th>Désignation</th>@if(auth()->user()->role->isCrossInstitution())<th>Institution</th>@endif<th>Compatibilité</th><th>Stock</th>@can('consumables.manage')<th>Mouvement</th>@endcan</tr></thead><tbody>
@forelse ($consumables as $c)
<tr><td><b>{{ $c->reference }}</b><div class="muted">{{ \App\Models\Consumable::CATEGORIES[$c->categorie] ?? $c->categorie }}</div></td><td>{{ $c->designation }}</td>@if(auth()->user()->role->isCrossInstitution())<td>{{ $c->institution->code }}</td>@endif<td>{{ $c->imprimantes_compatibles ?: '—' }}</td>
<td><b>{{ $c->stock }}</b> @if($c->isLow())<span class="pill red">sous le seuil ({{ $c->seuil_alerte }})</span>@endif</td>
@can('consumables.manage')<td><form method="post" action="{{ route('consumables.move', $c) }}" class="actions">@csrf
  <select name="sens" style="padding:6px;border:1px solid var(--line);border-radius:8px"><option value="entree">Entrée</option><option value="sortie">Sortie</option></select>
  <input type="number" name="quantite" min="1" value="1" style="width:70px;padding:6px;border:1px solid var(--line);border-radius:8px">
  <select name="beneficiaire_id" style="padding:6px;border:1px solid var(--line);border-radius:8px;max-width:170px"><option value="">Bénéficiaire (sortie)</option>@foreach ($agents as $a)<option value="{{ $a->id }}">{{ $a->name }}</option>@endforeach</select>
  <input name="motif" placeholder="Bon de livraison, motif…" style="padding:6px;border:1px solid var(--line);border-radius:8px;width:150px">
  <button class="small">Valider</button></form></td>@endcan</tr>
@empty<tr><td colspan="6" class="empty">Aucun consommable.</td></tr>@endforelse
</tbody></table></div>

<div class="panel"><h2>Derniers mouvements</h2><div class="tablewrap"><table><thead><tr><th>Date</th><th>Référence</th><th>Mouvement</th><th>Stock après</th><th>Bénéficiaire / motif</th><th>Par</th></tr></thead><tbody>
@forelse ($movements as $m)<tr><td>{{ $m->created_at?->format('d/m/Y H:i') }}</td><td>{{ $m->consumable->reference }}</td><td><span class="pill {{ $m->sens === 'entree' ? 'green' : 'amber' }}">{{ $m->sens === 'entree' ? '+' : '−' }}{{ $m->quantite }}</span></td><td>{{ $m->stock_apres }}</td><td>{{ $m->beneficiaire?->name }} <span class="muted">{{ $m->motif }}</span></td><td class="muted">{{ $m->author?->name }}</td></tr>
@empty<tr><td colspan="6" class="empty">Aucun mouvement.</td></tr>@endforelse
</tbody></table></div></div>
@endsection
