<!doctype html>
<html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>{{ $list->numero }}</title>
<style>
body{font-family:Georgia,'Times New Roman',serif;color:#111;background:#eef1f6;margin:0}
.page{background:#fff;max-width:900px;margin:24px auto;padding:36px 44px;box-shadow:0 2px 10px rgba(0,0,0,.08)}
.head{text-align:center;font-weight:bold;line-height:1.5}
h1{font-size:17px;text-align:center;text-decoration:underline;margin:22px 0 6px}
.sub{text-align:center;margin-bottom:14px;font-size:14px}
table{width:100%;border-collapse:collapse;font-size:12.5px}th,td{border:1px solid #94a3b8;padding:4px 6px;text-align:left;vertical-align:top}
.sign{margin-top:40px;text-align:right;line-height:1.8}
.tools{text-align:center;margin:16px;font-family:Arial,sans-serif}
@media print{body{background:#fff}.page{box-shadow:none;margin:0;padding:10mm}.tools{display:none}tr{page-break-inside:avoid}}
</style></head>
<body>
<div class="tools"><button onclick="window.print()">Imprimer</button></div>
<div class="page">
  <div class="head">Ministère de la Justice et des Droits de l’Homme<br>{{ $list->institution->nom }}</div>
  <h1>LISTE DÉFINITIVE DES AGENTS EN CONGÉ<br>VACANCES JUDICIAIRES {{ $list->campaign->annee }} – {{ mb_strtoupper($list->titre()) }}</h1>
  <div class="sub">Période : <b>{{ \App\Models\VacationCampaign::periode($list->mois, $list->campaign->annee) }}</b><br>
  Greffiers, agents interministériels et agents contractuels · {{ count($list->lignes) }} agent(s)<br>
  Réf. {{ $list->numero }} · éditée le {{ $list->created_at->format('d/m/Y à H:i') }}</div>
  <table><thead><tr><th>N°</th><th>Nom et prénoms</th><th>Matricule</th><th>Catégorie</th><th>Service</th><th>Fonction</th>@unless ($list->mois)<th>Mois</th>@endunless</tr></thead><tbody>
  @forelse ($list->lignes as $i => $r)
    <tr><td>{{ $i + 1 }}</td><td><b>{{ $r['nom'] }}</b></td><td>{{ $r['matricule'] }}</td><td>{{ $r['categorie'] }}</td><td>{{ $r['service'] }}</td><td>{{ $r['fonction'] }}</td>@unless ($list->mois)<td>{{ $r['mois'] }}</td>@endunless</tr>
  @empty<tr><td colspan="7">Aucun agent.</td></tr>@endforelse
  </tbody></table>
  <div class="sign">{{ $list->institution->ville ? $list->institution->ville.', le ' : 'Le ' }}{{ $list->created_at->translatedFormat('j F Y') }}<br>Le Président du Tribunal<br><br><br><b>{{ $list->editor?->full_name }}</b></div>
</div>
</body></html>
