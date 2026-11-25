@extends('layouts.app')
@section('title', 'Tableau de bord')
@section('content')
@php($statuses = \App\Enums\EquipmentStatus::cases())
<div class="pagehead"><div><h1>Tableau de bord</h1><div class="sub">{{ auth()->user()->institution?->nom ?? 'Vue nationale, toutes institutions' }}</div></div>
  @can('inventory.manage')<a class="btn" href="{{ route('equipments.create') }}">+ Nouvel équipement</a>@endcan</div>

<div class="cards">
  <div class="card"><div class="n">{{ $stats['total'] }}</div><div class="l">Équipements en service ou en stock</div></div>
  <div class="card"><div class="n">{{ ($byStatus['en_panne'] ?? 0) + ($byStatus['en_maintenance'] ?? 0) }}</div><div class="l">En panne ou en maintenance</div></div>
  <div class="card"><div class="n">{{ $stats['old'] }}</div><div class="l">Équipements de plus de 5 ans</div></div>
  <div class="card"><div class="n">{{ $stats['warranty'] }}</div><div class="l">Garanties qui expirent sous 90 jours</div></div>
  <div class="card"><div class="n">{{ $stats['pending_requests'] }}</div><div class="l">Demandes en attente</div></div>
</div>

<div class="grid cols-2">
  <div class="panel"><h2>Répartition par statut</h2>
    @php($max = max(1, $byStatus->max() ?? 1))
    <table>@foreach ($statuses as $s)<tr><td style="width:150px">@include('partials.pill', ['status' => $s])</td><td><div class="bar"><span style="width:{{ round(($byStatus[$s->value] ?? 0) / $max * 100) }}%"></span></div></td><td style="width:50px;text-align:right"><a href="{{ route('equipments.index', ['statut' => $s->value]) }}">{{ $byStatus[$s->value] ?? 0 }}</a></td></tr>@endforeach</table>
  </div>
  <div class="panel"><h2>Parc actif par type</h2>
    @php($maxT = max(1, $byType->max() ?? 1))
    <table>@forelse ($byType as $nom => $n)<tr><td style="width:170px">{{ $nom }}</td><td><div class="bar"><span style="width:{{ round($n / $maxT * 100) }}%"></span></div></td><td style="width:50px;text-align:right">{{ $n }}</td></tr>@empty<tr><td class="empty">Aucun équipement.</td></tr>@endforelse</table>
  </div>
</div>

@if ($perInstitution->isNotEmpty())
<div class="panel"><h2>Par institution</h2><div class="tablewrap"><table><thead><tr><th>Institution</th><th>Ville</th><th>Équipements actifs</th><th>En panne / maintenance</th></tr></thead><tbody>
@foreach ($perInstitution as $i)<tr><td><a href="{{ route('equipments.index', ['institution' => $i->id]) }}">{{ $i->nom }}</a></td><td>{{ $i->ville }}</td><td>{{ $i->actifs_count }}</td><td>{{ $i->pannes_count }}</td></tr>@endforeach
</tbody></table></div></div>
@endif

<div class="grid cols-2">
  <div class="panel"><h2>Dernières demandes</h2>
    @forelse ($latestRequests as $r)<div style="padding:6px 0;border-bottom:1px solid var(--line)"><b>{{ $r->numero }}</b> · {{ $r->materiel }} × {{ $r->quantite }} <span class="muted">— {{ $r->requester->name }}</span> @include('partials.pill', ['status' => $r->statut])</div>@empty<div class="empty">Aucune demande.</div>@endforelse
    <p><a href="{{ route('requests.index') }}">Toutes les demandes →</a></p>
  </div>
  <div class="panel"><h2>Pannes en cours</h2>
    @forelse ($latestIncidents as $i)<div style="padding:6px 0;border-bottom:1px solid var(--line)"><b>{{ $i->numero }}</b> · <a href="{{ route('equipments.show', $i->equipment) }}">{{ $i->equipment->designation }}</a> @include('partials.pill', ['status' => $i->statut])<div class="muted">{{ \Illuminate\Support\Str::limit($i->description, 90) }}</div></div>@empty<div class="empty">Aucune panne en cours.</div>@endforelse
    <p><a href="{{ route('incidents.index') }}">Toutes les pannes →</a></p>
  </div>
</div>
@endsection
