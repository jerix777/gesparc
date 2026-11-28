@extends('layouts.app')
@section('title', 'Paramètres')
@section('content')
<div class="pagehead"><div><h1>Paramètres</h1><div class="sub">Réglages communs à toutes les institutions</div></div></div>
<form method="post" action="{{ route('admin.settings.update') }}" class="panel" style="max-width:640px">@csrf @method('put')
  <div class="field"><label>Nom de l'application *</label><input name="sigle" required maxlength="60" value="{{ old('sigle', config('sigaci.sigle')) }}">
    <small>Affiché dans le menu, le titre des pages, la page de connexion, les documents et les courriels.</small></div>
  <div class="field"><label>Description</label><input name="nom" maxlength="190" value="{{ old('nom', $values['nom'] ?? '') }}" placeholder="{{ config('sigaci.nom') }}">
    <small>Affichée sous le nom. Laissez vide pour garder la description par défaut.</small></div>
  <div class="actions"><button>Enregistrer</button></div>
</form>
@endsection
