@extends('layouts.app')
@section('title', 'Demandes de matériel')
@section('content')
<div class="pagehead"><div><h1>Demandes de matériel</h1><div class="sub">{{ $all ? 'Demandes adressées au Service informatique' : 'Vos demandes' }}</div></div>
  @can('requests.create')<a class="btn" href="{{ route('requests.create') }}">+ Nouvelle demande</a>@endcan</div>

@if ($all || $pending->isNotEmpty())
<div class="panel"><h2>{{ $all ? 'Demandes à traiter' : 'En attente' }} ({{ $pending->count() }})</h2>
@forelse ($pending as $r)
<div style="padding:10px 0;border-bottom:1px solid var(--line);display:flex;gap:16px;justify-content:space-between;flex-wrap:wrap">
  <div><b>{{ $r->numero }}</b> · {{ $r->materiel }} × {{ $r->quantite }}
    <div>{{ $r->requester->name }} <span class="muted">· {{ $r->requester->fonction }} · bureau {{ $r->bureau ?: 'non renseigné' }}</span></div>
    @if($r->caracteristiques || $r->observations)<div class="muted">{{ $r->caracteristiques }} {{ $r->observations }}</div>@endif
    <div class="muted">Déposée le {{ $r->created_at->format('d/m/Y à H:i') }}@if($r->source === 'api') · via l'application TPI @endif</div></div>
  @can('requests.decide')
  @if ($r->requester_id !== auth()->id())
  <form method="post" action="{{ route('requests.decide', $r) }}" class="actions">@csrf
    @if ($r->categorie === 'cartouche' && $consumables->isNotEmpty())
    <select name="consumable_id" style="padding:6px;border:1px solid var(--line);border-radius:8px" title="Déduire du stock"><option value="">Sans sortie de stock</option>@foreach ($consumables as $c)<option value="{{ $c->id }}" @selected($r->caracteristiques && str_contains(mb_strtolower($r->caracteristiques), mb_strtolower($c->reference)))>{{ $c->reference }} (stock {{ $c->stock }})</option>@endforeach</select>
    @endif
    <button name="decision" value="accorder" class="success small">Accorder</button>
    <input name="motif_refus" placeholder="Motif si refus" style="padding:6px;border:1px solid var(--line);border-radius:8px">
    <button name="decision" value="refuser" class="danger small">Refuser</button>
  </form>
  @endif
  @endcan
</div>
@empty<div class="empty">Aucune demande en attente.</div>@endforelse
</div>
@endif

<div class="panel"><h2>Historique</h2><div class="tablewrap"><table><thead><tr><th>N° / date</th>@if($all)<th>Agent</th>@endif<th>Matériel</th><th>Qté</th><th>Statut</th><th>Décision</th></tr></thead><tbody>
@forelse ($history as $r)
<tr><td><b>{{ $r->numero }}</b><div class="muted">{{ $r->created_at->format('d/m/Y') }}</div></td>@if($all)<td>{{ $r->requester->name }}</td>@endif<td>{{ $r->materiel }}<div class="muted">{{ $r->caracteristiques }}</div></td><td>{{ $r->quantite }}</td><td>@include('partials.pill', ['status' => $r->statut])</td><td class="muted">{{ $r->decider?->name }} {{ $r->decided_at?->format('d/m/Y') }}@if($r->motif_refus)<div>Motif : {{ $r->motif_refus }}</div>@endif</td></tr>
@empty<tr><td colspan="6" class="empty">Aucune demande clôturée.</td></tr>@endforelse
</tbody></table>{{ $history->links() }}</div></div>
@endsection
