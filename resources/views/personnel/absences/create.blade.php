@extends('layouts.app')
@section('title', 'Nouvelle demande d’absence')
@section('content')
@php($M = \App\Models\LeaveRequest::MOTIFS)
<div class="pagehead"><div><h1>Demande d’autorisation d’absence</h1><div class="sub">La demande part directement à l’autorité compétente, qui est prévenue par courriel. La durée est calculée en jours ouvrés (week-ends et jours fériés exclus).</div></div></div>
<form method="post" action="{{ route('personnel.absences.store') }}" class="panel" style="max-width:760px">@csrf
  @if ($agents->isNotEmpty())
  <div class="field"><label>Agent concerné</label><select name="staff_id">
    @if ($me)<option value="">Moi-même ({{ $me->full_name }})</option>@endif
    @foreach ($agents as $a)<option value="{{ $a->id }}" @selected(old('staff_id') == $a->id)>{{ $a->nom }} {{ $a->prenoms }} — {{ $a->category?->nom }}</option>@endforeach
  </select><div class="muted">Vous pouvez saisir la demande d’un agent de votre ressort qui n’a pas de compte.</div></div>
  @elseif (! $me)
  <div class="alert err">Votre compte n’est relié à aucune fiche du personnel.</div>
  @else
  <div class="notice">Demandeur : <b>{{ $me->full_name }}</b></div>
  @endif
  <div class="field"><label>Motif</label><select name="motif" required><option value="">— Choisir un motif —</option>
    @foreach ($M as $groupe => $motifs)<optgroup label="{{ $groupe }}">@foreach ($motifs as $m)<option @selected(old('motif') === $m)>{{ $m }}</option>@endforeach</optgroup>@endforeach
  </select></div>
  <div class="field" id="prec"><label>Précisions (obligatoires pour « Autre motif »)</label><textarea name="precision" rows="2" maxlength="250">{{ old('precision') }}</textarea></div>
  <div class="grid cols-2">
    <div class="field"><label>Du</label><input type="date" name="debut" required value="{{ old('debut') }}"></div>
    <div class="field"><label>Au (inclus)</label><input type="date" name="fin" required value="{{ old('fin') }}"></div>
  </div>
  <div class="actions"><button>Transmettre la demande</button><a class="btn secondary" href="{{ route('personnel.absences.index') }}">Annuler</a></div>
</form>
@endsection
