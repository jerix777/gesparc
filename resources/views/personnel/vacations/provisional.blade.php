<!doctype html>
<html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Validations provisoires {{ $campaign->annee }}</title>
<style>
body{font-family:Georgia,'Times New Roman',serif;color:#111;background:#eef1f6;margin:0}
.page{background:#fff;max-width:960px;margin:24px auto;padding:36px 44px}
.head{text-align:center;font-weight:bold;line-height:1.5}h1{font-size:17px;text-align:center;text-decoration:underline;margin:22px 0 6px}
table{width:100%;border-collapse:collapse;font-size:12.5px;margin-top:14px}th,td{border:1px solid #94a3b8;padding:4px 6px;text-align:left}
.tools{text-align:center;margin:16px;font-family:Arial,sans-serif}@media print{body{background:#fff}.page{margin:0;padding:10mm}.tools{display:none}}
</style></head><body>
<div class="tools"><button onclick="window.print()">Imprimer</button></div>
<div class="page">
  <div class="head">Ministère de la Justice et des Droits de l’Homme<br>{{ $campaign->institution->nom }}</div>
  <h1>ÉTAT DES VALIDATIONS PROVISOIRES<br>VACANCES JUDICIAIRES {{ $campaign->annee }}</h1>
  <p style="text-align:center">Examinées au 1er niveau et transmises au Président du Tribunal · {{ $rows->count() }} demande(s) · édité le {{ now()->format('d/m/Y à H:i') }}</p>
  <table><thead><tr><th>N°</th><th>Agent</th><th>Catégorie</th><th>Service</th><th>Mois demandé</th><th>Mois retenu</th><th>Par, le</th><th>Situation</th></tr></thead><tbody>
  @forelse ($rows as $i => $r)
    <tr><td>{{ $i + 1 }}</td><td><b>{{ $r->staff->full_name }}</b></td><td>{{ $r->staff->category?->nom }}</td><td>{{ $r->staff->service?->nom ?? 'Non renseigné' }}</td><td>{{ $r->mois_demande }}</td><td>{{ $r->mois_retenu }}</td><td>{{ $r->firstDecider?->full_name }}, {{ $r->first_decided_at->format('d/m/Y') }}</td><td>{{ $r->statutLabel() }}</td></tr>
  @empty<tr><td colspan="8">Aucune validation provisoire.</td></tr>@endforelse
  </tbody></table>
</div></body></html>
