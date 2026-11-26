@extends('layouts.app')
@section('title', $equipment->numero_inventaire)
@section('content')
@php($S = \App\Enums\EquipmentStatus::class)
@php($open = $equipment->incidents->first(fn ($i) => ! $i->statut->isClosed()))
<div class="pagehead"><div><h1>{{ $equipment->designation }}</h1><div class="sub">{{ $equipment->numero_inventaire }} · {{ $equipment->type->nom }} · {{ $equipment->institution->nom }}</div></div>
  <div class="actions">@include('partials.pill', ['status' => $equipment->statut])
  <a class="btn secondary" href="{{ route('equipments.label', $equipment) }}" target="_blank">Étiquette</a>
  @can('inventory.manage')<a class="btn secondary" href="{{ route('equipments.edit', $equipment) }}">Modifier la fiche</a>@endcan</div></div>

<div class="grid cols-2">
<div class="panel"><h2>Fiche</h2><dl class="kv">
  <dt>Marque / modèle</dt><dd>{{ trim($equipment->marque.' '.$equipment->modele) ?: '—' }}</dd>
  <dt>N° de série</dt><dd>{{ $equipment->numero_serie ?: '—' }}</dd>
  <dt>Caractéristiques</dt><dd>{{ $equipment->caracteristiques ?: '—' }}</dd>
  <dt>État physique</dt><dd>{{ $equipment->etat->label() }}</dd>
  <dt>Localisation</dt><dd>{{ $equipment->location() }}</dd>
  <dt>Affecté à</dt><dd>{{ $equipment->assignedUser?->name ?? '—' }}</dd>
  <dt>Fournisseur</dt><dd>{{ $equipment->fournisseur ?: '—' }}</dd>
  <dt>Acquisition</dt><dd>{{ $equipment->date_acquisition?->format('d/m/Y') ?? '—' }} @if($equipment->ageInYears() !== null)<span class="muted">({{ $equipment->ageInYears() }} an(s))</span>@endif</dd>
  <dt>Prix d'achat</dt><dd>{{ $equipment->prix_achat ? number_format($equipment->prix_achat, 0, ',', ' ').' FCFA' : '—' }}</dd>
  <dt>Fin de garantie</dt><dd>{{ $equipment->fin_garantie?->format('d/m/Y') ?? '—' }} @if($equipment->warrantyExpiresSoon())<span class="pill amber">bientôt</span>@endif</dd>
  <dt>Observations</dt><dd>{{ $equipment->observations ?: '—' }}</dd>
</dl></div>

<div>
@can('inventory.manage')
  @if (in_array($equipment->statut, [$S::EnStock, $S::Affecte], true))
  <div class="panel"><h2>{{ $equipment->statut === $S::Affecte ? 'Transférer' : 'Affecter' }}</h2>
  <form method="post" action="{{ route('equipments.assign', $equipment) }}">@csrf
    <div class="grid cols-2">
    <div class="field"><label>Agent</label><select name="to_user_id"><option value="">— Aucun (bureau ou service) —</option>@foreach ($agents as $a)<option value="{{ $a->id }}">{{ $a->name }}{{ $a->bureau ? ' · bureau '.$a->bureau : '' }}</option>@endforeach</select></div>
    <div class="field"><label>Service</label><select name="service_id"><option value="">— Celui de l'agent —</option>@foreach ($services as $s)<option value="{{ $s->id }}">{{ $s->nom }}</option>@endforeach</select></div>
    <div class="field"><label>Bureau</label><input name="bureau" placeholder="Celui de l'agent"></div>
    <div class="field"><label>Motif</label><input name="motif"></div>
    </div><button>Enregistrer l'affectation</button>
  </form>
  @if ($equipment->statut === $S::Affecte)
  <form method="post" action="{{ route('equipments.release', $equipment) }}" style="margin-top:10px">@csrf<input type="hidden" name="motif" value="Restitution"><button class="secondary">Restituer et remettre en stock</button></form>
  @endif
  </div>
  <div class="panel"><h2>Signaler une panne</h2><form method="post" action="{{ route('incidents.store', $equipment) }}">@csrf<div class="field"><textarea name="description" rows="2" required placeholder="Description du problème"></textarea></div><button class="danger">Signaler</button></form></div>
  @endif

  @if ($open)
  <div class="panel"><h2>Panne {{ $open->numero }} @include('partials.pill', ['status' => $open->statut])</h2><p>{{ $open->description }}</p>
    @if ($equipment->statut === $S::EnPanne)
    <form method="post" action="{{ route('incidents.maintenance', $open) }}" class="actions" style="margin-bottom:10px">@csrf<input name="prestataire" placeholder="Prestataire (facultatif)" style="flex:1;padding:8px;border:1px solid var(--line);border-radius:8px"><button class="gold">Mettre en maintenance</button></form>
    @endif
    <form method="post" action="{{ route('incidents.close', $open) }}">@csrf
      <div class="grid cols-2"><div class="field"><label>Issue</label><select name="issue"><option value="repare">Réparé</option><option value="irreparable">Irréparable (réforme)</option></select></div>
      <div class="field"><label>Coût (FCFA)</label><input type="number" min="0" name="cout"></div></div>
      <div class="field"><label>Intervention réalisée</label><textarea name="resolution" rows="2"></textarea></div><button class="success">Clôturer la panne</button></form>
  </div>
  @endif
@endcan

@if ($equipment->hasPendingRetirement())
  <div class="panel" style="border-left:5px solid var(--amber)"><h2>Réforme proposée</h2>
    <p>{{ $equipment->reforme_motif }}</p>
    <p class="muted">Proposée par {{ $equipment->retirementProposer?->name ?? '—' }} le {{ $equipment->reforme_proposee_le->format('d/m/Y') }}. En attente de validation par le responsable.</p>
    @can('inventory.retire')
    <form method="post" action="{{ route('equipments.retire.approve', $equipment) }}" style="margin-bottom:10px">@csrf<div class="field"><input name="commentaire" placeholder="Commentaire (facultatif)" style="width:100%;padding:8px;border:1px solid var(--line);border-radius:8px"></div><button class="danger">Valider la réforme</button></form>
    <form method="post" action="{{ route('equipments.retire.reject', $equipment) }}" class="actions">@csrf<input name="motif" required placeholder="Motif du rejet" style="flex:1;padding:8px;border:1px solid var(--line);border-radius:8px"><button class="secondary">Rejeter</button></form>
    @endcan
  </div>
@elseif ($equipment->statut !== $S::Reforme)
  @can('inventory.manage')
  <div class="panel"><details><summary>Proposer la réforme de cet équipement</summary><form method="post" action="{{ route('equipments.retire.propose', $equipment) }}" style="margin-top:10px">@csrf<div class="field"><label>Motif (obsolescence, vol, perte…) *</label><input name="motif" required></div><button class="danger">Proposer au responsable</button></form></details></div>
  @endcan
@endif
</div>
</div>

<div class="panel"><h2>Historique</h2>
<ul class="timeline">
@forelse ($equipment->movements as $m)
<li><b>{{ $m->typeLabel() }}</b> <span class="muted">— {{ $m->created_at?->format('d/m/Y H:i') }} par {{ $m->author?->name ?? 'système' }}</span>
<div>@if($m->statut_avant){{ $m->statut_avant->label() }} → @endif{{ $m->statut_apres->label() }}@if($m->toUser) · {{ $m->toUser->name }}@endif @if($m->to_bureau) · bureau {{ $m->to_bureau }}@endif</div>
@if ($m->motif)<div class="muted">{{ $m->motif }}</div>@endif</li>
@empty<li class="muted">Aucun mouvement.</li>@endforelse
</ul></div>
@endsection
