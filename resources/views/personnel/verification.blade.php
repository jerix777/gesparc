<!doctype html>
<html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex">
<title>Vérification d’une décision – {{ config('sigaci.sigle') }}</title><link rel="stylesheet" href="{{ asset('css/app.css') }}"></head>
<body><div class="login"><div class="box">
  <h1>Vérification d’une décision</h1>
  @if ($l)
    <div class="alert ok">Décision authentique, enregistrée dans {{ config('sigaci.sigle') }}.</div>
    <dl class="kv">
      <dt>Document</dt><dd>Autorisation d’absence n° {{ $l->numero }}</dd>
      <dt>Institution</dt><dd>{{ $l->institution->nom }}</dd>
      <dt>Agent</dt><dd>{{ $l->staff->full_name }}</dd>
      <dt>Période</dt><dd>du {{ $l->debut->format('d/m/Y') }} au {{ $l->fin->format('d/m/Y') }}</dd>
      <dt>Décision</dt><dd>{{ $l->decider_fonction }}, le {{ $l->decided_at->format('d/m/Y') }}</dd>
    </dl>
  @else
    <div class="alert err">Aucune décision valide ne correspond à ce code. Le document est peut-être falsifié ou la décision annulée.</div>
  @endif
</div></div></body></html>
