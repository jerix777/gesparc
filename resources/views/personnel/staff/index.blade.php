@extends('layouts.app')
@section('title', 'Personnel')
@section('content')
<div class="pagehead"><div><h1>Personnel</h1><div class="sub">{{ $staff->total() }} fiche(s) · effectif actif : {{ $counts->sum() }}</div></div>
  <div class="actions">@can('personnel.configure')<a class="btn secondary" href="{{ route('personnel.settings') }}">Paramétrage</a>@endcan
  @can('personnel.create')<a class="btn" href="{{ route('personnel.staff.create') }}">+ Nouvelle fiche</a>@endcan</div></div>

<div class="cards">
  @foreach ($categories as $c)<a class="card" style="text-decoration:none" href="{{ route('personnel.staff.index', ['categorie' => $c->id]) }}"><div class="n">{{ $counts[$c->id] ?? 0 }}</div><div class="l">{{ $c->nom }}</div></a>@endforeach
  @if ($incomplete)<a class="card" style="text-decoration:none;border-color:var(--amber)" href="{{ route('personnel.staff.index', ['incomplet' => 1]) }}"><div class="n" style="color:var(--amber)">{{ $incomplete }}</div><div class="l">fiche(s) sans service ou sans catégorie : à régulariser</div></a>@endif
</div>

<form class="panel filters" method="get">
  <div><label class="muted">Recherche</label><input name="q" value="{{ request('q') }}" placeholder="Nom, prénoms, matricule, fonction…"></div>
  <div><label class="muted">Catégorie</label><select name="categorie"><option value="">Toutes</option>@foreach ($categories as $c)<option value="{{ $c->id }}" @selected(request('categorie') == $c->id)>{{ $c->nom }}</option>@endforeach</select></div>
  <div><label class="muted">Service</label><select name="service"><option value="">Tous</option>@foreach ($services as $s)<option value="{{ $s->id }}" @selected(request('service') == $s->id)>{{ $s->nom }}</option>@endforeach</select></div>
  <div><label class="muted">Situation</label><select name="statut"><option value="actif" @selected(request('statut', 'actif') === 'actif')>Dans les effectifs</option><option value="sorti" @selected(request('statut') === 'sorti')>Sortis</option><option value="tous" @selected(request('statut') === 'tous')>Tous</option></select></div>
  <div class="actions"><button>Filtrer</button><a class="btn secondary" href="{{ route('personnel.staff.index') }}">Effacer</a></div>
</form>

<div class="panel tablewrap"><table><thead><tr><th>Agent</th><th>Matricule</th><th>Catégorie</th><th>Service</th><th>Fonction d'autorité</th><th>Compte</th></tr></thead><tbody>
@forelse ($staff as $s)
<tr><td><a href="{{ route('personnel.staff.show', $s) }}"><b>{{ $s->nom }}</b> {{ $s->prenoms }}</a><div class="muted">{{ $s->fonction ?: '—' }}</div></td>
  <td>{{ $s->matricule ?: '—' }}</td><td>{{ $s->category?->nom ?? '—' }}</td>
  <td>@if ($s->service){{ $s->service->nom }}@else<span class="pill amber">À renseigner</span>@endif</td>
  <td>@foreach ($s->positions as $p)<span class="pill blue">{{ $p->position->label() }}</span> @endforeach</td>
  <td>@if (! $s->isActive())<span class="pill grey">Sorti</span>@elseif ($s->user)<span class="pill green">Ouvert</span>@else<span class="muted">—</span>@endif</td></tr>
@empty<tr><td colspan="6" class="empty">Aucune fiche ne correspond. @can('personnel.configure')Importez le personnel avec la commande <code>gesparc:import-personnel</code> (voir la documentation).@endcan</td></tr>@endforelse
</tbody></table>{{ $staff->links() }}</div>
@endsection
