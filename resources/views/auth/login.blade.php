<!doctype html>
<html lang="fr">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Connexion – GesParc</title>
<link rel="stylesheet" href="{{ asset('css/app.css') }}"></head>
<body>
<div class="login">
  <form class="box" method="post" action="{{ route('login') }}">
    @csrf
    <a href="{{ route('home') }}" class="muted" style="text-decoration:none">← Retour à l'accueil</a>
    <h1 style="margin-top:10px">GesParc</h1>
    @php($profils = ['agent' => 'Espace agent', 'informatique' => 'Service informatique', 'administration' => 'Responsables et administrateurs'])
    <p class="sub">@if(isset($profils[request('profil')]))<b>{{ $profils[request('profil')] }}</b> · @endif Gestion du parc informatique des institutions publiques de Côte d'Ivoire</p>
    @if ($errors->any())<div class="alert err">{{ $errors->first() }}</div>@endif
    <div class="field"><label for="email">Adresse e-mail</label><input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus></div>
    <div class="field"><label for="password">Mot de passe</label><input id="password" name="password" type="password" required></div>
    <div class="field"><label><input type="checkbox" name="remember" style="width:auto"> Rester connecté</label></div>
    <button style="width:100%">Se connecter</button>
  </form>
</div>
</body>
</html>
