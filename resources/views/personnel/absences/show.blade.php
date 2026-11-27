@extends('layouts.app')
@section('title', 'Absence '.$l->numero)
@section('content')
@php($ACTIONS = ['depot' => 'Demande déposée', 'transmission' => 'Avis favorable, transmise', 'accord' => 'Absence accordée', 'refus' => 'Demande refusée', 'annulation' => 'Demande annulée'])
<div class="pagehead"><div><h1>Demande d’absence {{ $l->numero }}</h1><div class="sub">{{ $l->staff->full_name }} · {{ $l->staff->category?->nom }} · {{ $l->staff->service?->nom ?? 'service non renseigné' }}</div></div>
  <div class="actions"><span class="pill {{ $l->statutColor() }}">{{ $l->statutLabel() }}</span>
  @if ($l->statut === 'accordee')<a class="btn" href="{{ route('personnel.absences.document', $l) }}" target="_blank">Imprimer la décision</a>@endif</div></div>

<div class="grid cols-2">
<div class="panel"><h2>Demande</h2><dl class="kv">
  <dt>Motif</dt><dd>{{ $l->motif }}@if ($l->precision)<div class="muted">{{ $l->precision }}</div>@endif</dd>
  <dt>Période</dt><dd>du {{ $l->debut->translatedFormat('l j F Y') }} au {{ $l->fin->translatedFormat('l j F Y') }}</dd>
  <dt>Durée</dt><dd><b>{{ $l->jours_ouvres }} jour(s) ouvré(s)</b> <span class="muted">({{ $l->jours_calendaires }} jour(s) calendaire(s))</span></dd>
  <dt>Déjà accordé en {{ $l->debut->year }}</dt><dd>{{ $granted }} jour(s) ouvré(s)</dd>
  <dt>Fonction</dt><dd>{{ $l->staff->fonction ?: '—' }}</dd>
  @if ($l->statut === 'refusee')<dt>Motif du refus</dt><dd>{{ $l->motif_refus }}</dd>@endif
  @if ($nextLevel)<dt>Doit décider</dt><dd>{{ $nextLevel }}</dd>@endif
</dl>
@if ($l->isOpen() && auth()->user()->staff?->id === $l->staff_id)
  <form method="post" action="{{ route('personnel.absences.cancel', $l) }}" style="margin-top:12px">@csrf<button class="secondary small">Annuler ma demande</button></form>
@endif
</div>

<div>
@if ($canDecide)
<div class="panel" style="border-color:var(--amber)"><h2>Votre décision</h2>
  <form method="post" action="{{ route('personnel.absences.decide', $l) }}">@csrf
    <input type="hidden" name="decision" value="accorder">
    <button class="success">{{ $willTransmit ? 'Avis favorable : transmettre' : 'Accorder l’absence' }}</button>
    <span class="muted">{{ $willTransmit ? 'La décision définitive reviendra au niveau supérieur.' : 'La décision est enregistrée à votre nom, datée et numérotée.' }}</span>
  </form>
  <hr style="border:0;border-top:1px solid var(--line);margin:14px 0">
  <form method="post" action="{{ route('personnel.absences.decide', $l) }}">@csrf
    <input type="hidden" name="decision" value="refuser">
    <div class="field"><label>Motif du refus</label><select name="motif_refus"><option value="">— Choisir —</option>@foreach (\App\Models\LeaveRequest::MOTIFS_REFUS as $m)<option>{{ $m }}</option>@endforeach</select></div>
    <div class="field"><label>Précisions pour l’agent (ou autre motif)</label><input name="precision_refus" maxlength="250"></div>
    <button class="danger">Refuser</button>
  </form>
</div>
@endif

<div class="panel"><h2>Historique</h2><ul class="timeline">
@foreach ($l->events as $e)
  <li><b>{{ $ACTIONS[$e->action] ?? $e->action }}</b> <span class="muted">· {{ $e->created_at->format('d/m/Y à H:i') }}</span><div>{{ $e->auteur }}</div>@if ($e->details)<div class="muted">{{ $e->details }}</div>@endif</li>
@endforeach
</ul></div>
</div>
</div>
@endsection
