@extends('layouts.app')
@section('title', 'Utilisateurs')
@section('content')
<div class="pagehead"><div><h1>Utilisateurs</h1><div class="sub">Comptes et rôles</div></div><a class="btn" href="{{ route('admin.users.create') }}">+ Nouveau compte</a></div>
<form class="panel filters" method="get"><div><input name="q" value="{{ request('q') }}" placeholder="Nom ou e-mail"></div><div class="actions"><button>Rechercher</button></div></form>
<div class="panel tablewrap"><table><thead><tr><th>Nom</th><th>E-mail</th><th>Rôle</th><th>Institution / service</th><th>Actif</th><th></th></tr></thead><tbody>
@foreach ($users as $u)<tr><td><b>{{ $u->name }}</b><div class="muted">{{ $u->fonction }}</div></td><td>{{ $u->email }}</td><td>{{ $u->role->label() }}</td><td>{{ $u->institution?->code ?? 'National' }}<div class="muted">{{ $u->service?->nom }}</div></td><td>{{ $u->active ? 'Oui' : 'Non' }}@if($u->locked_until?->isFuture())<div class="pill red">verrouillé</div>@endif</td>
<td class="actions"><a href="{{ route('admin.users.edit', $u) }}">Modifier</a><form method="post" action="{{ route('admin.users.reset', $u) }}">@csrf<button class="secondary small">Réinitialiser le mot de passe</button></form></td></tr>@endforeach
</tbody></table>{{ $users->links() }}</div>
@endsection
