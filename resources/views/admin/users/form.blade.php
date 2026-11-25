@extends('layouts.app')
@section('title', 'Compte utilisateur')
@section('content')
<div class="pagehead"><h1>{{ $user->exists ? $user->name : 'Nouveau compte' }}</h1></div>
<form method="post" action="{{ $user->exists ? route('admin.users.update', $user) : route('admin.users.store') }}" class="panel" style="max-width:760px">@csrf @if($user->exists) @method('put') @endif
<div class="grid cols-2">
  <div class="field"><label>Nom complet *</label><input name="name" required value="{{ old('name', $user->name) }}"></div>
  <div class="field"><label>E-mail (identifiant) *</label><input type="email" name="email" required value="{{ old('email', $user->email) }}"></div>
  <div class="field"><label>Matricule</label><input name="matricule" value="{{ old('matricule', $user->matricule) }}"></div>
  <div class="field"><label>Fonction</label><input name="fonction" value="{{ old('fonction', $user->fonction) }}"></div>
  <div class="field"><label>Rôle *</label><select name="role" required>@foreach ($roles as $r)<option value="{{ $r->value }}" @selected(old('role', $user->role?->value) === $r->value)>{{ $r->label() }}</option>@endforeach</select></div>
  @if ($national)
  <div class="field"><label>Institution</label><select name="institution_id"><option value="">— Aucune (rôle national) —</option>@foreach ($institutions as $i)<option value="{{ $i->id }}" @selected(old('institution_id', $user->institution_id) == $i->id)>{{ $i->nom }}</option>@endforeach</select></div>
  @endif
  <div class="field"><label>Service</label><select name="service_id"><option value="">—</option>@foreach ($services as $s)<option value="{{ $s->id }}" @selected(old('service_id', $user->service_id) == $s->id)>{{ $s->nom }}@if($national) ({{ $s->institution->code }})@endif</option>@endforeach</select></div>
  <div class="field"><label>Bureau / n° de porte</label><input name="bureau" value="{{ old('bureau', $user->bureau) }}"></div>
  <div class="field"><label>{{ $user->exists ? 'Nouveau mot de passe (laisser vide pour garder)' : 'Mot de passe *' }}</label><input type="password" name="password" @required(! $user->exists) minlength="8"></div>
  <div class="field"><label><input type="checkbox" name="active" value="1" style="width:auto" @checked(old('active', $user->active))> Compte actif</label></div>
</div>
<div class="actions"><button>Enregistrer</button><a class="btn secondary" href="{{ route('admin.users.index') }}">Annuler</a></div>
</form>
@endsection
