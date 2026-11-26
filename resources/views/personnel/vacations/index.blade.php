@extends('layouts.app')
@section('title', 'Vacances judiciaires '.$annee)
@section('content')
<div class="pagehead"><div><h1>Vacances judiciaires {{ $annee }}</h1>
  <div class="sub">@if ($campaign)Campagne {{ $campaign->isOpen() ? 'ouverte' : 'clôturée le '.$campaign->closed_at?->format('d/m/Y').' par '.$campaign->closer?->full_name }} · mois proposés : {{ implode(', ', $campaign->mois) }}@else Aucune campagne pour cette année.@endif</div></div>
  <div class="actions">@if ($years->count() > 1)<form method="get"><select name="annee" onchange="this.form.submit()">@foreach ($years as $y)<option @selected($y == $annee)>{{ $y }}</option>@endforeach</select></form>@endif</div></div>

@if (! $campaign)
  @if ($canOpen)
  <form method="post" action="{{ route('personnel.vacations.open') }}" class="panel filters">@csrf
    <div><label class="muted">Année</label><input type="number" name="annee" value="{{ $annee }}" min="2020" max="2100"></div>
    <div><label class="muted">Mois proposés</label><div>@foreach (\App\Models\VacationCampaign::MOIS as $m)<label style="margin-right:12px"><input type="checkbox" style="width:auto" name="mois[]" value="{{ $m }}" checked> {{ $m }}</label>@endforeach</div></div>
    <div class="actions"><button>Ouvrir la campagne</button></div>
  </form>
  @else<div class="panel empty">La campagne {{ $annee }} n’est pas encore ouverte.</div>@endif
@else

@if ($me && $canRequest)
<div class="panel"><h2>Ma demande</h2>
  @if ($mine && $mine->statut !== 'refusee')
    <p><span class="pill {{ $mine->statutColor() }}">{{ $mine->statutLabel() }}</span> Mois demandé : <b>{{ $mine->mois_demande }}</b>
    @if ($mine->mois_retenu) · retenu à titre provisoire : <b>{{ $mine->mois_retenu }}</b>@endif
    @if ($mine->mois_accorde) · <b>accordé : {{ $mine->mois_accorde }}</b> ({{ \App\Models\VacationCampaign::periode($mine->mois_accorde, $annee) }})@endif</p>
    @if ($mine->observation)<p class="muted">Observation : {{ $mine->observation }}</p>@endif
  @else
    @if ($mine)<p><span class="pill red">Demande refusée</span> {{ $mine->motif_refus }}. Vous pouvez choisir à nouveau.</p>@endif
    @if ($campaign->isOpen())
    <form method="post" action="{{ route('personnel.vacations.store', $campaign) }}" class="filters">@csrf
      @foreach ($campaign->mois as $m)
        <div><button name="mois" value="{{ $m }}" @disabled($quota[$m] ?? false) @class(['secondary' => $quota[$m] ?? false])>Choisir {{ $m }}</button>
        @if ($quota[$m] ?? false)<div class="muted" style="max-width:360px">{{ $quota[$m] }}</div>@endif</div>
      @endforeach
    </form>
    <p class="muted">Au plus la moitié de l’effectif de chaque service (arrondie au-dessus) part le même mois.</p>
    @else<p class="muted">La campagne est clôturée.</p>@endif
  @endif
</div>
@endif

@if ($toDecide->isNotEmpty())
<div class="panel" style="border-color:var(--amber)"><h2>À décider ({{ $toDecide->count() }})</h2>
<div class="tablewrap"><table><thead><tr><th>Agent</th><th>Demandé</th><th>Effectif du service</th><th>Décision</th></tr></thead><tbody>
@foreach ($toDecide as $r)@php([$txt, $col] = $staffing($r))@php($final = $r->statut === 'transmise' || \App\Services\Personnel\Approvals::firstLevelIsFinal($r->staff, 'vacances'))
<tr><td><b>{{ $r->staff->full_name }}</b><div class="muted">{{ $r->staff->category?->nom }} · {{ $r->staff->fonction }}</div></td>
<td>{{ $r->mois_demande }}@if ($r->mois_retenu)<div class="muted">retenu : <b>{{ $r->mois_retenu }}</b> ({{ $r->firstDecider?->full_name }}, {{ $r->first_decided_at?->format('d/m') }})</div>@endif</td>
<td><span class="pill {{ $col }}">{{ $txt }}</span></td>
<td style="min-width:330px">
  <form method="post" action="{{ route('personnel.vacations.decide', $r) }}" class="filters" style="margin:0">@csrf
    <div><select name="mois">@foreach ($campaign->mois as $m)<option @selected($m === $r->moisEffectif())>{{ $m }}</option>@endforeach</select></div>
    <div><input name="observation" placeholder="{{ $final ? 'Observation (si autre mois)' : 'Observation (facultatif)' }}" maxlength="500"></div>
    <div class="actions"><button class="success small" name="decision" value="valider">{{ $final ? 'Valider définitivement' : 'Retenir et transmettre' }}</button></div>
  </form>
  <details style="margin-top:6px"><summary class="muted">Refuser</summary>
  <form method="post" action="{{ route('personnel.vacations.decide', $r) }}" class="filters" style="margin:6px 0 0">@csrf
    <div><select name="motif_refus"><option value="">— Motif —</option>@foreach (\App\Models\VacationRequest::MOTIFS_REFUS as $m)<option>{{ $m }}</option>@endforeach</select></div>
    <div><input name="observation" placeholder="Précisions" maxlength="500"></div>
    <div class="actions"><button class="danger small" name="decision" value="refuser">Refuser</button></div>
  </form></details>
</td></tr>
@endforeach</tbody></table></div></div>
@endif

@if ($isChef)
<div class="panel"><h2>Clôture de la programmation</h2>
  @if ($campaign->isOpen())
    <p class="muted">La clôture fige les listes définitives (août, septembre et liste générale : greffiers, interministériels, contractuels) et bloque toute modification. Elle est impossible tant qu’une demande attend votre décision.</p>
    <form method="post" action="{{ route('personnel.vacations.close', $campaign) }}" onsubmit="return confirm('Clôturer définitivement la programmation {{ $annee }} ?')">@csrf<button>Clôturer la programmation {{ $annee }}</button></form>
  @else
    <form method="post" action="{{ route('personnel.vacations.reopen', $campaign) }}" class="filters">@csrf<div><input name="motif" required placeholder="Motif de la réouverture exceptionnelle"></div><div class="actions"><button class="secondary">Rouvrir</button></div></form>
  @endif
  @if ($campaign->reouvertures)<p class="muted" style="white-space:pre-line">Réouvertures : {{ $campaign->reouvertures }}</p>@endif
</div>
@endif

@if ($lists->isNotEmpty())
<div class="panel"><h2>Listes définitives</h2>
<p class="muted">À imprimer, signer à la main par le Président, puis numériser : le scan signé est archivé ici.</p>
<div class="tablewrap"><table><thead><tr><th>Référence</th><th>Liste</th><th>Agents</th><th>Liste signée</th><th></th></tr></thead><tbody>
@foreach ($lists as $l)
<tr><td>{{ $l->numero }}</td><td>{{ $l->titre() }}<div class="muted">éditée le {{ $l->created_at->format('d/m/Y') }}</div></td><td>{{ count($l->lignes) }}</td>
<td>@if ($l->scan_path)<a href="{{ route('personnel.vacations.lists.scan', $l) }}">Télécharger le scan</a><div class="muted">{{ $l->scan_at->format('d/m/Y') }}</div>@else<span class="pill amber">À numériser</span>@endif</td>
<td><a class="btn secondary small" href="{{ route('personnel.vacations.lists.show', $l) }}" target="_blank">Imprimer</a>
@if ($canOpen)<form method="post" action="{{ route('personnel.vacations.lists.upload', $l) }}" enctype="multipart/form-data" style="display:inline-flex;gap:6px;margin-top:6px">@csrf<input type="file" name="scan" accept=".pdf,image/*" required style="max-width:190px"><button class="small">Téléverser</button></form>@endif</td></tr>
@endforeach</tbody></table></div></div>
@endif

@if ($stats->isNotEmpty())
<div class="panel"><h2>Répartition par service</h2>
<p class="muted">Règle : au plus la moitié de l’effectif d’un service, arrondie au-dessus, part le même mois (2 agents : 1 ; 3 agents : 2 ; 4 agents : 2…). <a href="{{ route('personnel.vacations.provisional', $campaign) }}" target="_blank">Imprimer l’état des validations provisoires</a></p>
<div class="tablewrap"><table><thead><tr><th>Service</th><th>Effectif</th><th>Départs max. / mois</th>@foreach ($campaign->mois as $m)<th>{{ $m }}</th>@endforeach<th>Sans choix</th><th>Contrôle</th></tr></thead><tbody>
@foreach ($stats as $s)
<tr><td>@if ($s->service)<b>{{ $s->service }}</b>@else<span class="pill red">Service non renseigné</span>@endif</td><td>{{ $s->effectif }}</td><td>{{ $s->service ? $s->max : '—' }}</td>@foreach ($campaign->mois as $m)<td>{{ $s->mois[$m] }}</td>@endforeach<td>{{ $s->sans_choix }}</td>
<td>@if (! $s->service)<span class="pill red">À régulariser</span>@elseif ($s->conforme)<span class="pill green">Conforme</span>@else<span class="pill amber">Dépassement</span>@endif</td></tr>
@endforeach</tbody></table></div></div>
@endif

@if ($all->isNotEmpty())
<div class="panel"><h2>Toutes les demandes ({{ $all->count() }})</h2>
<div class="tablewrap"><table><thead><tr><th>Agent</th><th>Service</th><th>Demandé</th><th>Retenu</th><th>Accordé</th><th>Statut</th></tr></thead><tbody>
@foreach ($all as $r)
<tr><td>{{ $r->staff->full_name }}<div class="muted">{{ $r->staff->category?->nom }}</div></td><td>{{ $r->staff->service?->nom ?? '—' }}</td><td>{{ $r->mois_demande }}</td><td>{{ $r->mois_retenu ?? '—' }}</td><td>{{ $r->mois_accorde ?? '—' }}</td>
<td><span class="pill {{ $r->statutColor() }}">{{ $r->statutLabel() }}</span>@if ($r->motif_refus)<div class="muted">{{ $r->motif_refus }}</div>@endif</td></tr>
@endforeach</tbody></table></div></div>
@endif
@endif
@endsection
