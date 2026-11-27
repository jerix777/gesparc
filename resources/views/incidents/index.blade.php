@extends('layouts.app')
@section('title', 'Pannes et maintenance')
@section('content')
<div class="pagehead"><div><h1>Pannes et maintenance</h1><div class="sub">Signalements et interventions</div></div></div>
<form class="panel filters" method="get"><div><label class="muted">Statut</label><select name="statut"><option value="">Tous</option>@foreach (\App\Enums\IncidentStatus::cases() as $s)<option value="{{ $s->value }}" @selected(request('statut') === $s->value)>{{ $s->label() }}</option>@endforeach</select></div><div class="actions"><button>Filtrer</button></div></form>
<div class="panel tablewrap"><table><thead><tr><th>N°</th><th>Équipement</th><th>Description</th><th>Signalée par</th><th>Prestataire / coût</th><th>Statut</th></tr></thead><tbody>
@forelse ($incidents as $i)
<tr><td><b>{{ $i->numero }}</b><div class="muted">{{ $i->created_at->format('d/m/Y') }}</div></td><td><a href="{{ route('equipments.show', $i->equipment) }}">{{ $i->equipment->numero_inventaire }}</a><div class="muted">{{ $i->equipment->designation }}</div></td><td>{{ $i->description }}@if($i->resolution)<div class="muted">Intervention : {{ $i->resolution }}</div>@endif</td><td>{{ $i->reporter?->name }}</td><td>{{ $i->prestataire ?: '—' }}@if($i->cout)<div class="muted">{{ number_format($i->cout, 0, ',', ' ') }} FCFA</div>@endif</td><td>@include('partials.pill', ['status' => $i->statut])@if($i->resolved_at)<div class="muted">{{ $i->resolved_at->format('d/m/Y') }}</div>@endif</td></tr>
@empty<tr><td colspan="6" class="empty">Aucune panne.</td></tr>@endforelse
</tbody></table>{{ $incidents->links() }}</div>
@endsection
