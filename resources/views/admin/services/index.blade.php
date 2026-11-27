@extends('layouts.app')
@section('title', 'Services')
@section('content')
<div class="pagehead"><div><h1>Services</h1><div class="sub">Organisation interne de l'institution</div></div></div>
@if (auth()->user()->hasRole(\App\Enums\Role::AdminInstitution))
<form method="post" action="{{ route('admin.services.store') }}" class="panel filters">@csrf<div><input name="nom" required placeholder="Nom du service (ex. Greffe correctionnel)"></div><div class="actions"><button>Ajouter</button></div></form>
@endif
<div class="panel tablewrap"><table><thead><tr><th>Service</th><th>Institution</th><th></th></tr></thead><tbody>
@forelse ($services as $s)<tr><td>{{ $s->nom }}</td><td>{{ $s->institution->code }}</td><td>@if (auth()->user()->hasRole(\App\Enums\Role::AdminInstitution))<form method="post" action="{{ route('admin.services.destroy', $s) }}">@csrf @method('delete')<button class="danger small">Supprimer</button></form>@endif</td></tr>
@empty<tr><td colspan="3" class="empty">Aucun service.</td></tr>@endforelse
</tbody></table></div>
@endsection
