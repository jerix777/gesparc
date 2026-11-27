<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>@yield('title', 'Accueil') – SIGACI</title>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,400;9..40,600;9..40,700;9..40,800&display=swap">
<link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
@php($u = auth()->user())
@php($nav = fn (string $route, string $icon, string $label, string $pattern) => '<a href="'.route($route).'"'.(request()->routeIs($pattern) ? ' class="active" aria-current="page"' : '').'><span class="ic" aria-hidden="true">'.$icon.'</span><span>'.e($label).'</span></a>')
<div class="app">
  <aside class="side">
    <a class="brand" href="{{ route('home') }}" title="{{ config('sigaci.nom') }}">
      @include('partials.emblem')
      <span><b>SIGACI</b><small>{{ $u->institution?->nom ?? 'Niveau national · toutes institutions' }}</small></span>
    </a>
    <nav class="nav" aria-label="Menu principal">
      <div class="group">Général</div>
      @can('dashboard.view'){!! $nav('dashboard', '▦', 'Tableau de bord', 'dashboard') !!}@endcan
      {!! $nav('my-space', '◉', 'Mon espace personnel', 'my-space') !!}
      <div class="group">Parc informatique</div>
      @can('inventory.view'){!! $nav('equipments.index', '▣', 'Inventaire', 'equipments.*') !!}@endcan
      {!! $nav('requests.index', '▤', 'Demandes de matériels informatiques', 'requests.*') !!}
      @can('inventory.view'){!! $nav('consumables.index', '▥', 'Consommables', 'consumables.*') !!}@endcan
      @can('inventory.view'){!! $nav('incidents.index', '⚠', 'Pannes et maintenance', 'incidents.*') !!}@endcan
      @if ($u->staff || \Illuminate\Support\Facades\Gate::allows('personnel.view'))
        <div class="group">Personnel</div>
        {!! $nav('personnel.absences.index', '▣', 'Autorisations d’absence', 'personnel.absences.*') !!}
        {!! $nav('personnel.vacations.index', '▦', 'Vacances judiciaires – Planification des congés', 'personnel.vacations.*') !!}
      @endif
      @can('personnel.view')
        {!! $nav('personnel.staff.index', '▤', 'Répertoire du personnel', 'personnel.staff.*') !!}
        @can('personnel.configure'){!! $nav('personnel.settings', '⚙', 'Paramétrage', 'personnel.settings') !!}@endcan
      @endcan
      @can('users.manage')
        <div class="group">Administration</div>
        @can('institutions.manage'){!! $nav('admin.institutions.index', '▥', 'Institutions', 'admin.institutions.*') !!}@endcan
        {!! $nav('admin.users.index', '⚿', 'Comptes et mots de passe', 'admin.users.*') !!}
        {!! $nav('admin.services.index', '▧', 'Services', 'admin.services.*') !!}
      @endcan
    </nav>
    <div class="sidefoot"><b>SIGACI</b><br>{{ config('sigaci.nom') }}</div>
  </aside>
  <main>
    <div class="top">
      <strong>{{ config('sigaci.tutelle') }}</strong>
      <div class="actions">
        <span class="who">{{ $u->name }} <span class="muted">· {{ $u->role->label() }}</span></span>
        <a class="btn secondary small" href="{{ route('password.edit') }}">Mot de passe</a>
        <form method="post" action="{{ route('logout') }}">@csrf<button class="secondary">Déconnexion</button></form>
      </div>
    </div>
    <div class="content">
      @if (config('sigaci.information'))
        <div class="ticker no-print"><span class="label">INFORMATION</span><span class="track">{{ config('sigaci.information') }}</span></div>
      @endif
      @if (session('ok'))<div class="alert ok">{{ session('ok') }}</div>@endif
      @if ($errors->any())<div class="alert err">@foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>@endif
      @yield('content')
    </div>
    <div class="footer">SIGACI · {{ config('sigaci.nom') }}</div>
  </main>
</div>
</body>
</html>
