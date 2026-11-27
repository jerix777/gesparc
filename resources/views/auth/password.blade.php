@extends('layouts.app')
@section('title', 'Mot de passe')
@section('content')
<div class="pagehead"><div><h1>Changer mon mot de passe</h1>
  <div class="sub">@if (auth()->user()->must_change_password)Votre mot de passe est provisoire : choisissez-en un nouveau pour continuer.@else Au moins 8 caractères.@endif</div></div></div>
<form method="post" action="{{ route('password.update') }}" class="panel" style="max-width:480px">
  @csrf @method('put')
  <div class="field"><label for="current_password">Mot de passe actuel</label><input id="current_password" name="current_password" type="password" required autocomplete="current-password"></div>
  <div class="field"><label for="password">Nouveau mot de passe (8 caractères au moins)</label><input id="password" name="password" type="password" required autocomplete="new-password"></div>
  <div class="field"><label for="password_confirmation">Confirmer le nouveau mot de passe</label><input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password"></div>
  <button>Enregistrer</button>
</form>
@endsection
