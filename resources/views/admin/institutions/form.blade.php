@extends('layouts.app')
@section('title', 'Institution')
@section('content')
<div class="pagehead"><h1>{{ $institution->exists ? $institution->nom : 'Nouvelle institution' }}</h1></div>
<form method="post" action="{{ $institution->exists ? route('admin.institutions.update', $institution) : route('admin.institutions.store') }}" class="panel" style="max-width:640px">@csrf @if($institution->exists) @method('put') @endif
  <div class="field"><label>Code (préfixe des n° d'inventaire) *</label><input name="code" required value="{{ old('code', $institution->code) }}" placeholder="TPI-BKE"></div>
  <div class="field"><label>Nom *</label><input name="nom" required value="{{ old('nom', $institution->nom) }}"></div>
  <div class="field"><label>Catégorie</label><input name="categorie" value="{{ old('categorie', $institution->categorie) }}" placeholder="Juridiction, ministère, direction…"></div>
  <div class="field"><label>Ville</label><input name="ville" value="{{ old('ville', $institution->ville) }}"></div>
  <div class="field"><label>Téléphone</label><input name="telephone" value="{{ old('telephone', $institution->telephone) }}"></div>
  <div class="field"><label><input type="checkbox" name="active" value="1" style="width:auto" @checked(old('active', $institution->active))> Active</label></div>
  <div class="actions"><button>Enregistrer</button><a class="btn secondary" href="{{ route('admin.institutions.index') }}">Annuler</a></div>
</form>
@endsection
