<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
@php($v = config('vitrine'))
<title>{{ $v['sigle'] }} – Parc informatique · GesParc</title>
<meta name="description" content="Portail du parc informatique du {{ $v['nom'] }} : inventaire, demandes de matériel, pannes et consommables, avec GesParc.">
<link rel="stylesheet" href="{{ asset('css/vitrine.css') }}">
</head>
<body>
<div class="flag" aria-hidden="true"><i></i><i></i><i></i></div>
<div class="tutelle"><div class="wrap">République de Côte d'Ivoire · {{ $v['tutelle'] }}</div></div>
<header class="top">
  <div class="wrap">
    <a class="logo" href="{{ route('home') }}">
      <svg width="38" height="38" viewBox="0 0 38 38" aria-hidden="true"><rect width="38" height="38" rx="10" fill="#102b4e"/><path d="M19 8v20M11 12h16M13 12l-4 9h8zM25 12l-4 9h8zM14 29h10" fill="none" stroke="#f2cf75" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
      <span>{{ $v['sigle'] }}<small>{{ $v['service'] }} · parc informatique</small></span>
    </a>
    <nav class="menu">
      <a href="#chiffres">Chiffres</a>
      <a href="#fonctionnalites">Fonctionnalités</a>
      <a href="#acces">Accès</a>
      <a href="#securite">Sécurité</a>
      <a class="btn navy" href="{{ route('login') }}">Se connecter</a>
    </nav>
  </div>
</header>

<main>
<section class="carousel" aria-roledescription="carrousel" aria-label="Présentation du {{ $v['nom'] }}">
  <div class="slides">
    @foreach ($v['diapositives'] as $i => $d)
    <article class="slide{{ $i === 0 ? ' active' : '' }}" aria-roledescription="diapositive" aria-label="{{ $i + 1 }} sur {{ count($v['diapositives']) }}" @if($i) aria-hidden="true" @endif>
      <div class="wrap slide-in">
        <div class="slide-text">
          <span class="eyebrow">{{ $d['surtitre'] }}</span>
          @if ($i === 0)<h1>{{ $d['titre'] }}</h1>@else<h2 class="h1">{{ $d['titre'] }}</h2>@endif
          <p class="lead">{{ $d['texte'] }}</p>
          <div class="cta">
            <a class="btn gold" href="{{ route('login') }}">Accéder à mon espace</a>
            <a class="btn ghost" href="#acces">Choisir mon profil</a>
          </div>
        </div>
        <div class="slide-art">
          @if (! empty($d['image']) && is_file(public_path('images/vitrine/'.$d['image'])))
            <img src="{{ asset('images/vitrine/'.$d['image']) }}" alt="{{ $d['titre'] }}" loading="{{ $i ? 'lazy' : 'eager' }}">
          @else
            @include('vitrine.illustration', ['name' => $d['illustration']])
          @endif
        </div>
      </div>
    </article>
    @endforeach
  </div>
  <div class="wrap controls">
    <button type="button" class="nav-btn" data-dir="-1" aria-label="Diapositive précédente">‹</button>
    <div class="dots">@foreach ($v['diapositives'] as $i => $d)<button type="button" class="dot{{ $i === 0 ? ' active' : '' }}" data-go="{{ $i }}" aria-label="Aller à la diapositive {{ $i + 1 }}"></button>@endforeach</div>
    <button type="button" class="nav-btn" data-dir="1" aria-label="Diapositive suivante">›</button>
    <button type="button" class="nav-btn pause" aria-label="Mettre en pause le défilement">❚❚</button>
  </div>
</section>

<section id="chiffres" class="stats-section">
  <div class="wrap">
    <div class="head"><span class="k">En chiffres</span><h2>Le parc informatique du {{ $v['sigle'] }} aujourd'hui</h2>
      <p>@if($stats)Chiffres calculés en direct depuis GesParc, mis à jour le {{ $stats['maj'] }}.@else Les chiffres s'afficheront dès que l'inventaire du tribunal sera saisi dans GesParc.@endif</p></div>
    @if ($stats)
    <div class="stats">
      <div class="stat"><b data-count="{{ $stats['equipements'] }}">{{ $stats['equipements'] }}</b><span>équipements en service ou en stock</span></div>
      <div class="stat"><b>{{ $stats['disponibilite'] !== null ? $stats['disponibilite'].' %' : '—' }}</b><span>du matériel disponible (hors panne et maintenance)</span>
        @if($stats['disponibilite'] !== null)<div class="meter"><i style="width:{{ $stats['disponibilite'] }}%"></i></div>@endif</div>
      <div class="stat"><b data-count="{{ $stats['agents_equipes'] }}">{{ $stats['agents_equipes'] }}</b><span>agents équipés sur {{ $stats['agents'] }} comptes actifs</span></div>
      <div class="stat"><b data-count="{{ $stats['services'] }}">{{ $stats['services'] }}</b><span>services du tribunal raccordés</span></div>
      <div class="stat"><b data-count="{{ $stats['demandes_traitees'] }}">{{ $stats['demandes_traitees'] }}</b><span>demandes de matériel traitées</span></div>
      <div class="stat"><b>{{ \App\Support\VitrineStats::formatDelay($stats['delai_heures']) }}</b><span>délai moyen de traitement d'une demande</span></div>
      <div class="stat"><b data-count="{{ $stats['pannes_resolues'] }}">{{ $stats['pannes_resolues'] }}</b><span>pannes résolues et tracées</span></div>
      <div class="stat"><b>24 h/24</b><span>pour déposer une demande ou signaler une panne</span></div>
    </div>
    @endif
  </div>
</section>

<section id="fonctionnalites">
  <div class="wrap">
    <div class="head"><span class="k">Fonctionnalités</span><h2>Du bon de réception à la réforme, rien ne se perd</h2><p>Les outils dont le {{ $v['service'] }} du tribunal a besoin chaque jour, et la vue d'ensemble qu'attend la présidence.</p></div>
    <div class="features">
      <div class="feature"><div class="ic"><svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="12" rx="2"/><path d="M8 20h8M12 16v4"/></svg></div><h3>Inventaire</h3><p>Fiche complète par équipement, numéro d'inventaire automatique, recherche, export Excel et étiquette avec QR code.</p></div>
      <div class="feature"><div class="ic"><svg viewBox="0 0 24 24"><circle cx="9" cy="8" r="3"/><path d="M3 20c0-3.3 2.7-6 6-6M15 11l2 2 4-4"/></svg></div><h3>Affectations</h3><p>Affectation à un agent, un service ou un bureau, transferts et restitutions, avec l'historique complet de chaque appareil.</p></div>
      <div class="feature"><div class="ic"><svg viewBox="0 0 24 24"><path d="M4 4h16v12H8l-4 4z"/><path d="M8 9h8M8 12h5"/></svg></div><h3>Demandes de matériel</h3><p>L'agent demande, le Service informatique accorde ou refuse avec un motif. Le demandeur est prévenu par courriel.</p></div>
      <div class="feature"><div class="ic"><svg viewBox="0 0 24 24"><path d="M14.7 6.3a4 4 0 0 0-5.4 5.4L3 18l3 3 6.3-6.3a4 4 0 0 0 5.4-5.4l-2.5 2.5-2.5-.5-.5-2.5z"/></svg></div><h3>Pannes et maintenance</h3><p>Signalement en un clic, suivi de l'intervention et de son coût. Un appareil irréparable part en réforme validée par le responsable.</p></div>
      <div class="feature"><div class="ic"><svg viewBox="0 0 24 24"><path d="M6 3h12v6H6zM4 9h16v8H4zM7 17v4h10v-4"/></svg></div><h3>Consommables</h3><p>Stock de cartouches et de toners par institution, entrées et sorties, alerte automatique quand le seuil est atteint.</p></div>
      <div class="feature"><div class="ic"><svg viewBox="0 0 24 24"><path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/></svg></div><h3>Tableaux de bord</h3><p>Parc par statut et par type, matériel de plus de 5 ans, garanties qui expirent ; vue par institution et vue nationale.</p></div>
    </div>
  </div>
</section>

<section id="fonctionnement" class="alt">
  <div class="wrap">
    <div class="head"><span class="k">Fonctionnement</span><h2>Le cycle de vie de chaque équipement, tracé étape par étape</h2><p>Chaque changement est enregistré avec sa date, son auteur et son motif.</p></div>
    <div class="steps">
      <div class="step"><h4>Réception</h4><p>L'appareil entre en stock avec son numéro d'inventaire.</p></div>
      <div class="step"><h4>Affectation</h4><p>Il est remis à un agent ou installé dans un bureau.</p></div>
      <div class="step"><h4>Panne</h4><p>L'agent la signale ; l'informaticien est prévenu.</p></div>
      <div class="step"><h4>Maintenance</h4><p>Réparé, il retourne à son utilisateur.</p></div>
      <div class="step"><h4>Réforme</h4><p>Proposée par l'informaticien, validée par le responsable.</p></div>
    </div>
  </div>
</section>

<section id="acces">
  <div class="wrap">
    <div class="head"><span class="k">Accès</span><h2>Un espace pour chaque profil</h2><p>Magistrats, greffiers, secrétaires et agents du {{ $v['sigle'] }} : connectez-vous avec l'identifiant fourni par l'administrateur du tribunal.</p></div>
    <div class="access">
      <div class="card">
        <span class="tag">Magistrats et agents</span><h3>Mon espace</h3>
        <ul><li>Voir le matériel qui m'est affecté</li><li>Demander un équipement ou une cartouche</li><li>Signaler une panne</li><li>Suivre mes demandes</li></ul>
        <a class="btn outline" href="{{ route('login', ['profil' => 'agent']) }}">Connexion agent</a>
      </div>
      <div class="card feat">
        <span class="tag">Service informatique</span><h3>Gestion du parc</h3>
        <ul><li>Inventaire, affectations et transferts</li><li>Traitement des demandes</li><li>Pannes, maintenance et réformes</li><li>Stock des consommables</li></ul>
        <a class="btn navy" href="{{ route('login', ['profil' => 'informatique']) }}">Connexion Service informatique</a>
      </div>
      <div class="card">
        <span class="tag">Présidence et administration</span><h3>Pilotage</h3>
        <ul><li>Tableaux de bord du tribunal</li><li>Validation des réformes</li><li>Comptes, services et institutions</li><li>Vue nationale et audit</li></ul>
        <a class="btn outline" href="{{ route('login', ['profil' => 'administration']) }}">Connexion administration</a>
      </div>
    </div>
  </div>
</section>

<section id="securite" class="alt">
  <div class="wrap split">
    <div>
      <div class="head" style="text-align:left;margin:0 0 20px"><span class="k">Sécurité</span><h2>Des données publiques bien gardées</h2></div>
      <ul class="checks">
        <li><b>Données du tribunal protégées</b> : aucune autre juridiction n'y a accès.</li>
        <li><b>Droits par rôle</b> : chacun n'accède qu'à ce que sa fonction exige.</li>
        <li><b>Comptes protégés</b> : mots de passe chiffrés, verrouillage après 5 essais.</li>
        <li><b>Journal des actions</b> : chaque connexion et chaque modification est tracée.</li>
        <li><b>Sauvegarde chaque nuit</b>, conservée 30 jours, avec copie sur un second serveur.</li>
      </ul>
    </div>
    <div class="panel">
      <h3>Depuis l'application « Gestion du personnel »</h3>
      <p>Les agents du tribunal continuent d'utiliser l'application « Gestion du personnel » : leurs demandes de matériel y sont déposées comme avant et arrivent directement au {{ $v['service'] }}.</p>
      <p>L'inventaire existant est repris automatiquement, sans ressaisie.</p>
      <a class="btn gold" href="{{ route('login') }}">Se connecter</a>
    </div>
  </div>
</section>

<section>
  <div class="wrap">
    <div class="final">
      <h2>Un besoin en matériel ? Une panne ?</h2>
      <p>Connectez-vous et adressez votre demande au {{ $v['service'] }} en quelques clics.</p>
      <a class="btn gold" href="{{ route('login') }}">Accéder à GesParc</a>
    </div>
  </div>
</section>
</main>

<footer>
  <div class="wrap">
    <div><b>{{ $v['nom'] }}</b><br>{{ $v['service'] }} · {{ $v['ville'] }}
      @if($v['contact']['adresse'])<br>{{ $v['contact']['adresse'] }}@endif
      @if($v['contact']['telephone'])<br>Tél. {{ $v['contact']['telephone'] }}@endif
      @if($v['contact']['email'])<br><a href="mailto:{{ $v['contact']['email'] }}">{{ $v['contact']['email'] }}</a>@endif</div>
    <div style="text-align:right">Plateforme <b>GesParc</b> · gestion du parc informatique des institutions publiques<br>Un compte ? Demandez-le à l'administrateur du tribunal.<br><a href="{{ route('login') }}">Se connecter</a></div>
  </div>
</footer>
<script>
(function () {
  var root = document.querySelector('.carousel'); if (!root) return;
  var slides = root.querySelectorAll('.slide'), dots = root.querySelectorAll('.dot'), pauseBtn = root.querySelector('.pause');
  var i = 0, timer = null, paused = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  function show(n) {
    i = (n + slides.length) % slides.length;
    slides.forEach(function (s, k) { s.classList.toggle('active', k === i); s.setAttribute('aria-hidden', k === i ? 'false' : 'true'); });
    dots.forEach(function (d, k) { d.classList.toggle('active', k === i); });
  }
  function start() { stop(); if (!paused) timer = setInterval(function () { show(i + 1); }, 6000); }
  function stop() { if (timer) clearInterval(timer); timer = null; }
  root.querySelectorAll('[data-dir]').forEach(function (b) { b.onclick = function () { show(i + (+b.dataset.dir)); start(); }; });
  dots.forEach(function (d) { d.onclick = function () { show(+d.dataset.go); start(); }; });
  pauseBtn.onclick = function () { paused = !paused; pauseBtn.textContent = paused ? '▶' : '❚❚'; pauseBtn.setAttribute('aria-label', paused ? 'Reprendre le défilement' : 'Mettre en pause le défilement'); paused ? stop() : start(); };
  if (paused) pauseBtn.textContent = '▶';
  root.addEventListener('mouseenter', stop); root.addEventListener('mouseleave', start);
  root.addEventListener('focusin', stop);
  var x0 = null; root.addEventListener('touchstart', function (e) { x0 = e.touches[0].clientX; }, { passive: true });
  root.addEventListener('touchend', function (e) { if (x0 === null) return; var dx = e.changedTouches[0].clientX - x0; if (Math.abs(dx) > 40) { show(i + (dx < 0 ? 1 : -1)); start(); } x0 = null; });
  document.addEventListener('keydown', function (e) { if (!root.contains(document.activeElement)) return; if (e.key === 'ArrowRight') { show(i + 1); start(); } if (e.key === 'ArrowLeft') { show(i - 1); start(); } });
  start();
})();
</script>
</body>
</html>
