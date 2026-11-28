<!doctype html>
<html lang="fr">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Connexion – {{ config('sigaci.sigle') }}</title>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,400;9..40,600;9..40,700;9..40,800&display=swap">
<link rel="stylesheet" href="{{ asset('css/app.css') }}"></head>
<body>
@php($profils = ['agent' => 'Espace agent', 'informatique' => 'Service informatique', 'administration' => 'Responsables et administrateurs'])
<div class="login">
  <div class="intro">
    <div class="brandline">@include('partials.emblem')<div><b>{{ config('sigaci.sigle') }}</b><span>{{ config('sigaci.nom') }}</span></div></div>
    <h2>Une administration au <span class="o">service</span> de chaque agent.</h2>
    <p>Plus besoin de vous déplacer. Faites vos démarches administratives en ligne : matériel, pannes, absences et vacances judiciaires.</p>
    <div class="docs" aria-hidden="true"><i></i><i></i><i></i></div>
  </div>
  <form class="box" method="post" action="{{ route('login') }}">
    @csrf
    <h1>Connexion</h1>
    <div class="sub">@if(isset($profils[request('profil')])){{ $profils[request('profil')] }} · @endif{{ config('sigaci.tutelle') }}</div>
    @if ($errors->any())<div class="alert err">{{ $errors->first() }}</div>@endif
    <div class="field"><label for="email">Adresse e-mail ou matricule professionnel</label><input id="email" name="email" type="text" autocomplete="username" value="{{ old('email') }}" placeholder="Saisir votre e-mail ou votre matricule" required autofocus></div>
    <div class="field"><label for="password">Mot de passe</label><input id="password" name="password" type="password" autocomplete="current-password" placeholder="Mot de passe initial ou personnel" required></div>
    <div class="field"><label style="font-weight:600"><input type="checkbox" name="remember"> Rester connecté</label></div>
    <button>Se connecter</button>
    <a class="back" href="{{ route('home') }}">← Retour à l'accueil</a>
  </form>
</div>
</body>
</html>
