<!doctype html>
<html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Autorisation d’absence {{ $l->numero }}</title>
<style>
body{font-family:Georgia,'Times New Roman',serif;color:#111;background:#eef1f6;margin:0}
.page{background:#fff;max-width:720px;margin:24px auto;padding:48px 56px;box-shadow:0 2px 10px rgba(0,0,0,.08);line-height:1.6}
.head{text-align:center;font-weight:bold;line-height:1.5}.head small{font-weight:normal}
h1{font-size:19px;text-align:center;text-decoration:underline;margin:34px 0 24px;letter-spacing:.5px}
.sign{margin-top:36px;display:flex;justify-content:space-between;align-items:flex-end;gap:20px}
.verif{font-family:Arial,sans-serif;font-size:11px;color:#444;max-width:300px}
.verif svg{width:96px;height:96px;display:block;margin-bottom:4px}
.tools{text-align:center;margin:16px;font-family:Arial,sans-serif}
@media print{body{background:#fff}.page{box-shadow:none;margin:0;padding:24px 32px}.tools{display:none}}
</style></head>
<body>
<div class="tools"><button onclick="window.print()">Imprimer ou enregistrer en PDF</button></div>
<div class="page">
  <div class="head">République de Côte d’Ivoire<br><small>Union – Discipline – Travail</small><br>Ministère de la Justice et des Droits de l’Homme<br>{{ $l->institution->nom }}</div>
  <p style="text-align:right;margin-top:24px">{{ $l->institution->ville ?: '' }}{{ $l->institution->ville ? ', le ' : 'Le ' }}{{ $l->decided_at->translatedFormat('j F Y') }}</p>
  <h1>AUTORISATION D’ABSENCE N° {{ $l->numero }}</h1>
  <p>Par décision du {{ $l->decider_fonction }}, est autorisé(e) :</p>
  <p style="margin-left:24px"><b>{{ $l->staff->full_name }}</b><br>
    @if ($l->staff->matricule)Matricule : {{ $l->staff->matricule }}<br>@endif
    Emploi : {{ $l->staff->fonction ?: 'non renseigné' }}<br>
    Catégorie : {{ $l->staff->category?->nom }}@if ($l->staff->service) · {{ $l->staff->service->nom }}@endif</p>
  <p>à s’absenter du <b>{{ $l->debut->translatedFormat('l j F Y') }}</b> au <b>{{ $l->fin->translatedFormat('l j F Y') }}</b> inclus, soit <b>{{ $l->jours_ouvres }} jour(s) ouvré(s)</b>, pour le motif suivant : {{ $l->motif }}.</p>
  <p>L’intéressé(e) reprendra son service le premier jour ouvré suivant la fin de cette autorisation.</p>
  <div class="sign">
    <div class="verif">{!! $qr !!}Décision électronique enregistrée dans {{ config('sigaci.sigle') }} le {{ $l->decided_at->format('d/m/Y à H:i') }}.<br>Code de vérification : <b>{{ $l->verification_code }}</b></div>
    <div style="text-align:center">{{ $l->signe_po ? 'P.O. ' : '' }}Le {{ $l->decider_fonction }}<br><br><b>{{ $l->decider?->full_name }}</b></div>
  </div>
</div>
</body></html>
