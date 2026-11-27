@extends('layouts.app')
@section('title', $s->exists ? 'Modifier '.$s->full_name : 'Nouvelle fiche')
@section('content')
<div class="pagehead"><div><h1>{{ $s->exists ? 'Modifier la fiche de '.$s->full_name : 'Nouvelle fiche du personnel' }}</h1>
<div class="sub">Seules les catégories qui relèvent de votre autorité sont proposées.</div></div></div>
<form method="post" action="{{ $s->exists ? route('personnel.staff.update', $s) : route('personnel.staff.store') }}" class="panel">
@csrf @if($s->exists) @method('put') @endif
<div class="grid cols-3">
  <div class="field"><label>Nom *</label><input name="nom" required value="{{ old('nom', $s->nom) }}"></div>
  <div class="field"><label>Prénoms</label><input name="prenoms" value="{{ old('prenoms', $s->prenoms) }}"></div>
  <div class="field"><label>Matricule</label><input name="matricule" value="{{ old('matricule', $s->matricule) }}" placeholder="Sert d'identifiant de connexion"></div>
  <div class="field"><label>Catégorie *</label><select name="staff_category_id" required><option value="">— Choisir —</option>@foreach ($categories as $c)<option value="{{ $c->id }}" @selected(old('staff_category_id', $s->staff_category_id) == $c->id)>{{ $c->nom }}</option>@endforeach</select></div>
  <div class="field"><label>Fonction / emploi</label><input name="fonction" value="{{ old('fonction', $s->fonction) }}"></div>
  <div class="field"><label>Service d'affectation</label><select name="service_id"><option value="">— Non renseigné —</option>@foreach ($services as $sv)<option value="{{ $sv->id }}" @selected(old('service_id', $s->service_id) == $sv->id)>{{ $sv->nom }}</option>@endforeach</select></div>
  <div class="field"><label>Bureau / n° de porte</label><input name="bureau" value="{{ old('bureau', $s->bureau) }}"></div>
  <div class="field"><label>Date de prise de service</label><input type="date" name="date_prise_service" value="{{ old('date_prise_service', $s->date_prise_service?->format('Y-m-d')) }}"></div>
  <div class="field"><label>Téléphone</label><input name="telephone" value="{{ old('telephone', $s->telephone) }}"></div>
  <div class="field"><label>E-mail</label><input type="email" name="email" value="{{ old('email', $s->email) }}"></div>
</div>
<div class="actions"><button>Enregistrer</button><a class="btn secondary" href="{{ $s->exists ? route('personnel.staff.show', $s) : route('personnel.staff.index') }}">Annuler</a></div>
</form>
@endsection
