<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>@yield('title', 'GesParc') – GesParc</title>
<link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
@php($u = auth()->user())
<div class="app">
  <aside class="side">
    <div class="brand"><b>GesParc</b><small>Parc informatique des institutions publiques</small></div>
    <nav class="nav">
      @can('dashboard.view')<a href="{{ route('dashboard') }}" @class(['active' => request()->routeIs('dashboard')])>Tableau de bord</a>@endcan
      <a href="{{ route('my-space') }}" @class(['active' => request()->routeIs('my-space')])>Mon espace</a>
      <div class="group">Parc</div>
      @can('inventory.view')<a href="{{ route('equipments.index') }}" @class(['active' => request()->routeIs('equipments.*')])>Inventaire</a>@endcan
      <a href="{{ route('requests.index') }}" @class(['active' => request()->routeIs('requests.*')])>Demandes de matériel</a>
      @can('inventory.view')<a href="{{ route('consumables.index') }}" @class(['active' => request()->routeIs('consumables.*')])>Consommables</a>@endcan
      @can('inventory.view')<a href="{{ route('incidents.index') }}" @class(['active' => request()->routeIs('incidents.*')])>Pannes et maintenance</a>@endcan
      @can('users.manage')
        <div class="group">Administration</div>
        @can('institutions.manage')<a href="{{ route('admin.institutions.index') }}" @class(['active' => request()->routeIs('admin.institutions.*')])>Institutions</a>@endcan
        <a href="{{ route('admin.users.index') }}" @class(['active' => request()->routeIs('admin.users.*')])>Utilisateurs</a>
        <a href="{{ route('admin.services.index') }}" @class(['active' => request()->routeIs('admin.services.*')])>Services</a>
      @endcan
    </nav>
  </aside>
  <main>
    <div class="top">
      <div><strong>{{ $u->institution?->nom ?? 'Niveau national' }}</strong></div>
      <div class="actions">
        <span>{{ $u->name }} <span class="muted">· {{ $u->role->label() }}</span></span>
        <form method="post" action="{{ route('logout') }}">@csrf<button class="secondary small">Déconnexion</button></form>
      </div>
    </div>
    <div class="content">
      @if (session('ok'))<div class="alert ok">{{ session('ok') }}</div>@endif
      @if ($errors->any())<div class="alert err">@foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>@endif
      @yield('content')
    </div>
  </main>
</div>
</body>
</html>
