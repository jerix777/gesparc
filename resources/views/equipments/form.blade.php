@extends('layouts.app')
@section('title', $equipment->exists ? 'Modifier '.$equipment->numero_inventaire : 'Nouvel équipement')
@section('content')
<div class="pagehead"><div><h1>{{ $equipment->exists ? 'Modifier '.$equipment->numero_inventaire : 'Nouvel équipement' }}</h1>
<div class="sub">{{ $equipment->exists ? 'Le statut et l’affectation se changent depuis la fiche, pour garder l’historique.' : 'L’équipement est enregistré « En stock ». Affectez-le ensuite depuis sa fiche.' }}</div></div></div>
<form method="post" action="{{ $equipment->exists ? route('equipments.update', $equipment) : route('equipments.store') }}" class="panel">
@csrf @if($equipment->exists) @method('put') @endif
<div class="grid cols-3">
  <div class="field"><label>N° d'inventaire</label><input name="numero_inventaire" value="{{ old('numero_inventaire', $equipment->numero_inventaire) }}" placeholder="Laisser vide : attribué automatiquement"></div>
  <div class="field"><label>Type *</label><select name="equipment_type_id" required><option value="">— Choisir —</option>@foreach ($types as $t)<option value="{{ $t->id }}" @selected(old('equipment_type_id', $equipment->equipment_type_id) == $t->id)>{{ $t->nom }}</option>@endforeach</select></div>
  <div class="field"><label>Désignation *</label><input name="designation" required value="{{ old('designation', $equipment->designation) }}"></div>
  <div class="field"><label>Marque</label><input name="marque" value="{{ old('marque', $equipment->marque) }}"></div>
  <div class="field"><label>Modèle</label><input name="modele" value="{{ old('modele', $equipment->modele) }}"></div>
  <div class="field"><label>N° de série</label><input name="numero_serie" value="{{ old('numero_serie', $equipment->numero_serie) }}"></div>
  <div class="field"><label>Fournisseur</label><input name="fournisseur" value="{{ old('fournisseur', $equipment->fournisseur) }}"></div>
  <div class="field"><label>Date d'acquisition</label><input type="date" name="date_acquisition" value="{{ old('date_acquisition', $equipment->date_acquisition?->format('Y-m-d')) }}"></div>
  <div class="field"><label>Prix d'achat (FCFA)</label><input type="number" min="0" name="prix_achat" value="{{ old('prix_achat', $equipment->prix_achat) }}"></div>
  <div class="field"><label>Fin de garantie</label><input type="date" name="fin_garantie" value="{{ old('fin_garantie', $equipment->fin_garantie?->format('Y-m-d')) }}"></div>
  <div class="field"><label>État physique *</label><select name="etat" required>@foreach ($conditions as $c)<option value="{{ $c->value }}" @selected(old('etat', $equipment->etat?->value) === $c->value)>{{ $c->label() }}</option>@endforeach</select></div>
  <div class="field"><label>Service</label><select name="service_id"><option value="">—</option>@foreach ($services as $s)<option value="{{ $s->id }}" @selected(old('service_id', $equipment->service_id) == $s->id)>{{ $s->nom }}</option>@endforeach</select></div>
  <div class="field"><label>Bureau / n° de porte</label><input name="bureau" value="{{ old('bureau', $equipment->bureau) }}"></div>
</div>
<div class="field"><label>Caractéristiques</label><textarea name="caracteristiques" rows="2" placeholder="Processeur, mémoire, disque, système…">{{ old('caracteristiques', $equipment->caracteristiques) }}</textarea></div>
<div class="field"><label>Observations</label><textarea name="observations" rows="2">{{ old('observations', $equipment->observations) }}</textarea></div>
<div class="actions"><button>Enregistrer</button><a class="btn secondary" href="{{ $equipment->exists ? route('equipments.show', $equipment) : route('equipments.index') }}">Annuler</a></div>
</form>
@endsection
