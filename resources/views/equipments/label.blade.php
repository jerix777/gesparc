<!doctype html>
<html lang="fr"><head><meta charset="utf-8"><title>Étiquette {{ $equipment->numero_inventaire }}</title>
<style>body{font-family:Arial,sans-serif;margin:20px}.label{border:2px solid #102b4e;border-radius:8px;padding:10px 14px;width:380px;display:flex;gap:12px;align-items:center}.label b{font-size:20px;color:#102b4e;display:block}.muted{color:#555;font-size:12px}.qr svg{display:block;width:110px;height:110px}</style></head>
<body onload="window.print()">
<div class="label">
  <div class="qr">{!! $qr !!}</div>
  <div>
    <div class="muted">{{ $equipment->institution->nom }}</div>
    <b>{{ $equipment->numero_inventaire }}</b>
    <div>{{ $equipment->type->nom }} — {{ $equipment->designation }}</div>
    <div class="muted">N° série : {{ $equipment->numero_serie ?: '—' }}</div>
    <div class="muted">Propriété de l'État — ne pas retirer</div>
  </div>
</div>
</body></html>
