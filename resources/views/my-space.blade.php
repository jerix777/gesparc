@extends('layouts.app')
@section('title', 'Mon espace')
@section('content')
@include('partials.hero', ['title' => 'Vos démarches, <span class="o">simplifiées</span>.', 'lead' => 'Retrouvez votre matériel, vos demandes et vos démarches administratives dans un espace clair, pensé pour faciliter votre quotidien.', 'chips' => array_filter(['Demander du matériel' => \Illuminate\Support\Facades\Gate::allows('requests.create') ? route('requests.create') : null, 'Mes demandes' => route('requests.index'), 'Mes absences' => auth()->user()->staff ? route('personnel.absences.index') : null, 'Mes vacances' => auth()->user()->staff ? route('personnel.vacations.index') : null])])
<div class="pagehead"><div><h1>Mon espace</h1><div class="sub">{{ auth()->user()->fonction }} @if(auth()->user()->bureau)· Bureau {{ auth()->user()->bureau }}@endif</div></div>
  @can('requests.create')<a class="btn" href="{{ route('requests.create') }}">+ Demander du matériel</a>@endcan</div>
<div class="panel"><h2>Matériel qui m'est affecté</h2>
@if ($equipments->isEmpty())<div class="empty">Aucun équipement ne vous est affecté.</div>@else
<div class="tablewrap"><table><thead><tr><th>N° inventaire</th><th>Équipement</th><th>N° de série</th><th>Statut</th><th>Signaler une panne</th></tr></thead><tbody>
@foreach ($equipments as $e)<tr><td><b>{{ $e->numero_inventaire }}</b></td><td>{{ $e->designation }}<div class="muted">{{ $e->type->nom }} · {{ $e->marque }} {{ $e->modele }}</div></td><td>{{ $e->numero_serie ?: '—' }}</td><td>@include('partials.pill', ['status' => $e->statut])</td>
<td>@if ($e->statut === \App\Enums\EquipmentStatus::Affecte)<details><summary>Signaler</summary><form method="post" action="{{ route('incidents.store', $e) }}">@csrf<div class="field"><textarea name="description" rows="2" required placeholder="Décrivez le problème"></textarea></div><button class="danger small">Envoyer</button></form></details>@else<span class="muted">—</span>@endif</td></tr>@endforeach
</tbody></table></div>@endif
</div>
<div class="panel"><h2>Mes dernières demandes</h2>
@forelse ($requests as $r)<div style="padding:6px 0;border-bottom:1px solid var(--line)"><b>{{ $r->numero }}</b> · {{ $r->materiel }} × {{ $r->quantite }} @include('partials.pill', ['status' => $r->statut]) <span class="muted">{{ $r->created_at->format('d/m/Y') }}</span>@if($r->motif_refus)<div class="muted">Motif : {{ $r->motif_refus }}</div>@endif</div>@empty<div class="empty">Aucune demande.</div>@endforelse
</div>
@endsection
