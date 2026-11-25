<!doctype html>
<html lang="fr"><head><meta charset="utf-8"><title>Étiquette {{ $equipment->numero_inventaire }}</title>
<style>body{font-family:Arial,sans-serif;margin:20px}.label{border:2px solid #102b4e;border-radius:8px;padding:12px 16px;width:320px}.label b{font-size:20px;color:#102b4e}.muted{color:#555;font-size:12px}</style></head>
<body onload="window.print()">
<div class="label">
  <div class="muted">{{ $equipment->institution->nom }}</div>
  <b>{{ $equipment->numero_inventaire }}</b>
  <div>{{ $equipment->type->nom }} — {{ $equipment->designation }}</div>
  <div class="muted">N° série : {{ $equipment->numero_serie ?: '—' }}</div>
  <div class="muted">Propriété de l'État — ne pas retirer</div>
</div>
</body></html>
