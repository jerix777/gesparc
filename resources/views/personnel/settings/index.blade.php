@extends('layouts.app')
@section('title', 'Paramétrage du personnel')
@section('content')
@php($P = \App\Enums\Position::class)
<div class="pagehead"><div><h1>Paramétrage du personnel</h1><div class="sub">Qui exerce chaque fonction d'autorité, et qui décide pour chaque catégorie d'agents.</div></div>
  <div class="actions"><a class="btn secondary" href="{{ route('personnel.staff.index') }}">← Personnel</a></div></div>

<div class="panel"><h2>Fonctions d'autorité</h2>
<p class="muted">Les droits ne dépendent plus du texte de la fonction : ils suivent ces nominations. Président, Procureur et Greffier en Chef n'ont qu'un titulaire ; une nouvelle nomination remplace l'ancienne.</p>
<div class="tablewrap"><table><thead><tr><th>Fonction</th><th>Titulaire(s)</th></tr></thead><tbody>
@foreach ($P::cases() as $pos)
<tr><td><b>{{ $pos->label() }}</b></td><td>
  @forelse ($holders[$pos->value] ?? [] as $h)
    <form method="post" action="{{ route('personnel.positions.destroy', $h) }}" style="display:inline-flex;gap:6px;align-items:center;margin:0 10px 4px 0">@csrf @method('delete')<span>{{ $h->staff->full_name }}</span><button class="secondary small" title="Retirer">×</button></form>
  @empty<span class="pill amber">Non attribuée</span>@endforelse
</td></tr>
@endforeach
</tbody></table></div>
<form method="post" action="{{ route('personnel.positions.store') }}" class="filters" style="margin-top:12px">@csrf
  <div><label class="muted">Agent</label><select name="staff_id" required><option value="">— Choisir —</option>@foreach ($staff as $s)<option value="{{ $s->id }}">{{ $s->nom }} {{ $s->prenoms }}{{ $s->fonction ? ' – '.$s->fonction : '' }}</option>@endforeach</select></div>
  <div><label class="muted">Fonction</label><select name="position" required>@foreach ($P::cases() as $pos)<option value="{{ $pos->value }}">{{ $pos->label() }}</option>@endforeach</select></div>
  <div class="actions"><button>Attribuer</button></div>
</form></div>

<div class="panel"><h2>Catégories et règles de décision</h2>
@if ($categories->isEmpty())
  <p>Aucune catégorie. Pour une juridiction, partez du modèle du TPI de Bouaké (5 catégories, règles du Président, du Procureur et du Greffier en Chef).</p>
  <form method="post" action="{{ route('personnel.preset') }}">@csrf<button>Appliquer le modèle « juridiction »</button></form>
@else
<p class="muted">« 1er niveau » : qui décide, ou valide à titre provisoire s'il existe une décision définitive. Un agent ne décide jamais de sa propre demande : elle passe alors au niveau supérieur.</p>
@foreach ($categories as $c)
<h3 style="margin:18px 0 6px">{{ $c->nom }} <span class="muted" style="font-weight:400">· {{ $c->staff_count }} agent(s){{ $c->magistrat ? ' · magistrats' : '' }}</span></h3>
<div class="tablewrap"><table><thead><tr><th>Demande</th><th>1er niveau</th><th>Décision définitive</th><th>Copie à</th><th></th></tr></thead><tbody>
@foreach (\App\Models\ApprovalRule::KINDS as $kind => $label)@php($r = $c->rule($kind))
<tr><td colspan="5" style="padding:0;border:0">
<form method="post" action="{{ route('personnel.rules.update', [$c, $kind]) }}" class="rule-row">@csrf @method('put')
  <b>{{ $label }}</b>
  <div>@foreach ($P::cases() as $pos)@if (in_array($pos, [$P::Secretaire, $P::Archiviste, $P::GestionnaireFournitures], true)) @continue @endif<label style="display:block;font-size:13px"><input type="checkbox" style="width:auto" name="first_level[]" value="{{ $pos->value }}" @checked(in_array($pos->value, $r?->first_level ?? [], true))> {{ $pos->label() }}</label>@endforeach</div>
  <select name="final_level"><option value="">— Aucune —</option>@foreach ([$P::Chef, $P::ChefParquet, $P::ChefAdministratif] as $pos)<option value="{{ $pos->value }}" @selected($r?->final_level === $pos)>{{ $pos->label() }}</option>@endforeach</select>
  <div>@foreach ([$P::Chef, $P::ChefParquet, $P::ChefAdministratif] as $pos)<label style="display:block;font-size:13px"><input type="checkbox" style="width:auto" name="copy_to[]" value="{{ $pos->value }}" @checked(in_array($pos->value, $r?->copy_to ?? [], true))> {{ $pos->label() }}</label>@endforeach</div>
  <button class="secondary small">Enregistrer</button>
</form></td></tr>
@endforeach
</tbody></table></div>
@endforeach
@endif
</div>

<div class="panel"><h2>Jours fériés</h2>
<p class="muted">Exclus du décompte des jours ouvrés d’absence. Les fêtes civiles et chrétiennes se calculent ; les fêtes musulmanes (fin du Ramadan, Tabaski, Maouloud, lendemain de la Nuit du destin) se saisissent dès leur annonce officielle.</p>
<div class="filters" style="margin-bottom:12px">
  <form method="post" action="{{ route('personnel.holidays.store') }}" class="filters" style="margin:0">@csrf<div><input type="number" name="annee" value="{{ now()->month >= 10 ? now()->year + 1 : now()->year }}" min="2020" max="2100" style="width:110px"></div><div class="actions"><button class="secondary">Ajouter les jours calculables</button></div></form>
  <form method="post" action="{{ route('personnel.holidays.store') }}" class="filters" style="margin:0">@csrf<div><input type="date" name="date" required></div><div><input name="libelle" required placeholder="Ex. Tabaski"></div><div class="actions"><button>Ajouter</button></div></form>
</div>
<div class="tablewrap"><table><thead><tr><th>Date</th><th>Jour férié</th><th></th></tr></thead><tbody>
@forelse ($holidays as $h)<tr><td>{{ $h->date->translatedFormat('l j F Y') }}</td><td>{{ $h->libelle }}</td><td><form method="post" action="{{ route('personnel.holidays.destroy', $h) }}">@csrf @method('delete')<button class="secondary small">Retirer</button></form></td></tr>
@empty<tr><td colspan="3" class="empty">Aucun jour férié à venir.</td></tr>@endforelse
</tbody></table></div></div>
@endsection
