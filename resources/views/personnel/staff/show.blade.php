@extends('layouts.app')
@section('title', $s->full_name)
@section('content')
<div class="pagehead"><div><h1>{{ $s->full_name }}</h1><div class="sub">{{ $s->fonction ?: 'Fonction non renseignée' }} · {{ $s->category?->nom ?? 'Catégorie à renseigner' }}</div></div>
  <div class="actions">@if ($s->isActive())<span class="pill green">Dans les effectifs</span>@else<span class="pill grey">Sorti le {{ $s->date_sortie?->format('d/m/Y') }}</span>@endif
  @if ($canManage)<a class="btn secondary" href="{{ route('personnel.staff.edit', $s) }}">Modifier la fiche</a>@endif</div></div>

<div class="grid cols-2">
<div class="panel"><h2>Fiche</h2><dl class="kv">
  <dt>Matricule</dt><dd>{{ $s->matricule ?: '—' }}</dd>
  <dt>Catégorie</dt><dd>{{ $s->category?->nom ?? '—' }}</dd>
  <dt>Service</dt><dd>{!! $s->service ? e($s->service->nom) : '<span class="pill amber">À renseigner</span> <span class="muted">indispensable pour les vacances judiciaires</span>' !!}</dd>
  <dt>Bureau / porte</dt><dd>{{ $s->bureau ?: '—' }}</dd>
  <dt>Prise de service</dt><dd>{{ $s->date_prise_service?->format('d/m/Y') ?? '—' }}</dd>
  <dt>Téléphone</dt><dd>{{ $s->telephone ?: '—' }}</dd>
  <dt>E-mail</dt><dd>{{ $s->email ?: '—' }}</dd>
  @if (! $s->isActive())<dt>Motif de sortie</dt><dd>{{ $s->motif_sortie }}</dd>@endif
</dl></div>

<div>
<div class="panel"><h2>Qui décide pour cet agent</h2>
  @if ($s->category && $s->category->rules->isNotEmpty())
  <dl class="kv">@foreach (\App\Models\ApprovalRule::KINDS as $kind => $label)@php($r = $s->category->rule($kind))<dt>{{ $label }}</dt><dd>{{ $r?->describe() ?? 'Non défini' }}</dd>@endforeach</dl>
  @else<div class="muted">Aucune règle : choisissez une catégorie, puis réglez les autorités dans le paramétrage.</div>@endif
  @if ($s->positions->isNotEmpty())<p style="margin-top:12px">Fonction(s) d'autorité : @foreach ($s->positions as $p)<span class="pill blue">{{ $p->position->label() }}</span> @endforeach</p>@endif
</div>

<div class="panel"><h2>Compte {{ config('sigaci.sigle') }}</h2>
  @if ($s->user)
    <p>Identifiant : <b>{{ $s->user->matricule ?: $s->user->email }}</b> · {{ $s->user->active ? 'actif' : 'désactivé' }}@if($s->user->must_change_password) · <span class="pill amber">mot de passe provisoire non changé</span>@endif</p>
  @elseif ($canManage && $s->isActive())
    <p class="muted">L'agent n'a pas encore de compte. Un mot de passe provisoire sera affiché une seule fois ; l'agent le changera à sa première connexion.</p>
    <form method="post" action="{{ route('personnel.staff.account', $s) }}">@csrf<button>Ouvrir le compte</button></form>
  @else<p class="muted">Pas de compte.</p>@endif
</div>

@if ($canManage)
<div class="panel"><h2>{{ $s->isActive() ? 'Sortie des effectifs' : 'Réintégration' }}</h2>
  @if ($s->isActive())
  <form method="post" action="{{ route('personnel.staff.leave', $s) }}" class="grid cols-2">@csrf
    <div class="field"><label>Date de sortie</label><input type="date" name="date_sortie" required value="{{ now()->toDateString() }}"></div>
    <div class="field"><label>Motif</label><input name="motif_sortie" required placeholder="Mutation, retraite, fin de contrat…"></div>
    <div class="actions"><button class="danger">Enregistrer la sortie</button></div>
  </form><p class="muted">La fiche et son historique sont conservés ; le compte est désactivé.</p>
  @else
  <form method="post" action="{{ route('personnel.staff.reinstate', $s) }}">@csrf<button class="secondary">Réintégrer dans les effectifs</button></form>
  @endif
</div>
@endif
</div>
</div>
@endsection
