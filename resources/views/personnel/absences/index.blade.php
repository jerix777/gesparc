@extends('layouts.app')
@section('title', 'Autorisations d’absence')
@section('content')
<div class="pagehead"><div><h1>Autorisations d’absence</h1><div class="sub">Demande en ligne, décision électronique : plus d’impression ni de scan.</div></div>
  <div class="actions"><a class="btn" href="{{ route('personnel.absences.create') }}">+ Nouvelle demande</a></div></div>

@if ($toDecide->isNotEmpty())
<div class="panel" style="border-color:var(--amber)"><h2>À décider ({{ $toDecide->count() }})</h2>
<div class="tablewrap"><table><thead><tr><th>N°</th><th>Agent</th><th>Motif</th><th>Période</th><th>Étape</th><th></th></tr></thead><tbody>
@foreach ($toDecide as $l)
<tr><td>{{ $l->numero }}</td><td><b>{{ $l->staff->full_name }}</b><div class="muted">{{ $l->staff->category?->nom }} · {{ $l->staff->service?->nom ?? 'service non renseigné' }}</div></td>
<td>{{ $l->motif }}</td><td>{{ $l->debut->format('d/m/Y') }} → {{ $l->fin->format('d/m/Y') }}<div class="muted">{{ $l->jours_ouvres }} jour(s) ouvré(s)</div></td>
<td><span class="pill {{ $l->statutColor() }}">{{ $l->statutLabel() }}</span></td><td><a class="btn small" href="{{ route('personnel.absences.show', $l) }}">Examiner</a></td></tr>
@endforeach</tbody></table></div></div>
@endif

@if ($me)
<div class="panel"><h2>Mes demandes</h2>
<p class="muted">{{ $granted }} jour(s) ouvré(s) d’absence accordé(s) en {{ now()->year }}.@unless ($canRequest) Votre fonction vous dispense d’autorisation d’absence.@endunless</p>
<div class="tablewrap"><table><thead><tr><th>N°</th><th>Motif</th><th>Période</th><th>Statut</th><th></th></tr></thead><tbody>
@forelse ($mine as $l)
<tr><td>{{ $l->numero }}</td><td>{{ $l->motif }}</td><td>{{ $l->debut->format('d/m/Y') }} → {{ $l->fin->format('d/m/Y') }} <span class="muted">({{ $l->jours_ouvres }} j)</span></td>
<td><span class="pill {{ $l->statutColor() }}">{{ $l->statutLabel() }}</span></td>
<td><a class="btn secondary small" href="{{ route('personnel.absences.show', $l) }}">Ouvrir</a> @if ($l->statut === 'accordee')<a class="btn secondary small" href="{{ route('personnel.absences.document', $l) }}" target="_blank">Décision</a>@endif</td></tr>
@empty<tr><td colspan="5" class="empty">Aucune demande.</td></tr>@endforelse
</tbody></table></div></div>
@endif

@if ($register->isNotEmpty() || request('q'))
<div class="panel"><h2>Registre des décisions</h2>
<form method="get" class="filters" style="margin-bottom:10px"><div><input name="q" value="{{ request('q') }}" placeholder="Nom, matricule ou n° de demande"></div><div class="actions"><button class="secondary">Rechercher</button></div></form>
<div class="tablewrap"><table><thead><tr><th>N°</th><th>Agent</th><th>Période</th><th>Décision</th><th></th></tr></thead><tbody>
@forelse ($register as $l)
<tr><td>{{ $l->numero }}</td><td><b>{{ $l->staff->full_name }}</b><div class="muted">{{ $l->staff->category?->nom }}</div></td><td>{{ $l->debut->format('d/m/Y') }} → {{ $l->fin->format('d/m/Y') }} <span class="muted">({{ $l->jours_ouvres }} j)</span></td>
<td><span class="pill {{ $l->statutColor() }}">{{ $l->statutLabel() }}</span><div class="muted">{{ $l->decider_fonction }}, le {{ $l->decided_at?->format('d/m/Y') }}</div></td>
<td><a class="btn secondary small" href="{{ route('personnel.absences.show', $l) }}">Ouvrir</a></td></tr>
@empty<tr><td colspan="5" class="empty">Aucune décision ne correspond.</td></tr>@endforelse
</tbody></table></div></div>
@endif
@endsection
