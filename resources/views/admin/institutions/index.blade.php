@extends('layouts.app')
@section('title', 'Institutions')
@section('content')
<div class="pagehead"><div><h1>Institutions</h1><div class="sub">Juridictions et administrations raccordées</div></div><a class="btn" href="{{ route('admin.institutions.create') }}">+ Nouvelle institution</a></div>
<div class="panel tablewrap"><table><thead><tr><th>Code</th><th>Nom</th><th>Catégorie</th><th>Ville</th><th>Utilisateurs</th><th>Équipements</th><th>Active</th><th></th></tr></thead><tbody>
@foreach ($institutions as $i)<tr><td><b>{{ $i->code }}</b></td><td>{{ $i->nom }}</td><td>{{ $i->categorie }}</td><td>{{ $i->ville }}</td><td>{{ $i->users_count }}</td><td>{{ $i->equipments_count }}</td><td>{{ $i->active ? 'Oui' : 'Non' }}</td><td><a href="{{ route('admin.institutions.edit', $i) }}">Modifier</a></td></tr>@endforeach
</tbody></table></div>
@endsection
