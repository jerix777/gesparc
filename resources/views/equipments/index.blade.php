@extends('layouts.app')
@section('title', 'Inventaire')
@section('content')
<div class="pagehead"><div><h1>Inventaire du parc</h1><div class="sub">{{ $equipments->total() }} équipement(s)</div></div>
  <div class="actions"><a class="btn secondary" href="{{ route('equipments.export', request()->query()) }}">Exporter (Excel)</a>
  @can('inventory.manage')<a class="btn" href="{{ route('equipments.create') }}">+ Nouvel équipement</a>@endcan</div></div>
<form class="panel filters" method="get">
  <div><label class="muted">Recherche</label><input name="q" value="{{ request('q') }}" placeholder="Désignation, n° inventaire, n° série, bureau…"></div>
  <div><label class="muted">Statut</label><select name="statut"><option value="">Tous</option>@foreach (\App\Enums\EquipmentStatus::cases() as $s)<option value="{{ $s->value }}" @selected(request('statut') === $s->value)>{{ $s->label() }}</option>@endforeach</select></div>
  <div><label class="muted">Type</label><select name="type"><option value="">Tous</option>@foreach ($types as $t)<option value="{{ $t->id }}" @selected(request('type') == $t->id)>{{ $t->nom }}</option>@endforeach</select></div>
  @if ($institutions->isNotEmpty())
  <div><label class="muted">Institution</label><select name="institution"><option value="">Toutes</option>@foreach ($institutions as $i)<option value="{{ $i->id }}" @selected(request('institution') == $i->id)>{{ $i->nom }}</option>@endforeach</select></div>
  @else
  <div><label class="muted">Service</label><select name="service"><option value="">Tous</option>@foreach ($services as $s)<option value="{{ $s->id }}" @selected(request('service') == $s->id)>{{ $s->nom }}</option>@endforeach</select></div>
  @endif
  <div class="actions"><button>Filtrer</button><a class="btn secondary" href="{{ route('equipments.index') }}">Effacer</a></div>
</form>
<div class="panel tablewrap"><table><thead><tr><th>N° inventaire</th><th>Équipement</th>@if($institutions->isNotEmpty())<th>Institution</th>@endif<th>N° de série</th><th>Localisation</th><th>Affecté à</th><th>Statut</th></tr></thead><tbody>
@forelse ($equipments as $e)
<tr><td><a href="{{ route('equipments.show', $e) }}"><b>{{ $e->numero_inventaire }}</b></a></td><td>{{ $e->designation }}<div class="muted">{{ $e->type->nom }} · {{ $e->marque }} {{ $e->modele }}</div></td>@if($institutions->isNotEmpty())<td>{{ $e->institution->code }}</td>@endif<td>{{ $e->numero_serie ?: '—' }}</td><td>{{ $e->location() }}</td><td>{{ $e->assignedUser?->name ?? '—' }}</td><td>@include('partials.pill', ['status' => $e->statut])</td></tr>
@empty<tr><td colspan="7" class="empty">Aucun équipement ne correspond.</td></tr>@endforelse
</tbody></table>{{ $equipments->links() }}</div>
@endsection
