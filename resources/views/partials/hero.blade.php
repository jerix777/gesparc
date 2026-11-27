{{-- Bandeau d'accueil : $title (HTML autorisé, déjà échappé par l'appelant), $lead, $chips = [libellé => url] --}}
<section class="hero">
  <div class="text">
    <div class="brandline">@include('partials.emblem')<span class="eyebrow">{{ auth()->user()->institution?->nom ?? 'SIGACI · Niveau national' }}</span></div>
    <h2>{!! $title !!}</h2>
    <p>{{ $lead }}</p>
    @if (! empty($chips))<div class="chips">@foreach ($chips as $label => $url)<a href="{{ $url }}">{{ $label }}</a>@endforeach</div>@endif
  </div>
  <div class="docs" aria-hidden="true"><i></i><i></i><i></i></div>
</section>
