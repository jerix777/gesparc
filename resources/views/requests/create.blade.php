@extends('layouts.app')
@section('title', 'Nouvelle demande')
@section('content')
<div class="pagehead"><div><h1>Demande de matériel informatique</h1><div class="sub">Bureau : {{ auth()->user()->bureau ?: 'non renseigné sur votre fiche' }}</div></div></div>
<form method="post" action="{{ route('requests.store') }}" class="panel" style="max-width:640px">@csrf
  <div class="field"><label>Matériel souhaité *</label><select name="materiel" required><option value="">— Sélectionner —</option>@foreach ($materiels as $m)<option @selected(old('materiel') === $m)>{{ $m }}</option>@endforeach</select></div>
  <div class="field"><label>Quantité *</label><input type="number" name="quantite" min="1" max="99" value="{{ old('quantite', 1) }}" required></div>
  <div class="field"><label>Caractéristiques / référence</label><input name="caracteristiques" value="{{ old('caracteristiques') }}" placeholder="Obligatoire pour « Autres accessoires » ; référence de cartouche…"></div>
  <div class="field"><label>Observation</label><textarea name="observations" rows="3">{{ old('observations') }}</textarea></div>
  <div class="actions"><button>Soumettre la demande</button><a class="btn secondary" href="{{ route('requests.index') }}">Annuler</a></div>
</form>
@endsection
