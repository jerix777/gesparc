<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>GesParc – Gestion du parc informatique des institutions publiques</title>
<meta name="description" content="GesParc centralise l'inventaire, les affectations, les demandes de matériel, les pannes et les consommables des juridictions et administrations publiques de Côte d'Ivoire.">
<link rel="stylesheet" href="{{ asset('css/vitrine.css') }}">
</head>
<body>
<div class="flag" aria-hidden="true"><i></i><i></i><i></i></div>
<header class="top">
  <div class="wrap">
    <a class="logo" href="{{ route('home') }}">
      <svg width="34" height="34" viewBox="0 0 34 34" aria-hidden="true"><rect width="34" height="34" rx="9" fill="#102b4e"/><rect x="7" y="9" width="20" height="13" rx="2" fill="none" stroke="#f2cf75" stroke-width="2"/><path d="M13 26h8M17 22v4" stroke="#f2cf75" stroke-width="2" stroke-linecap="round"/></svg>
      <span>GesParc<small>Parc informatique public</small></span>
    </a>
    <nav class="menu">
      <a href="#fonctionnalites">Fonctionnalités</a>
      <a href="#fonctionnement">Fonctionnement</a>
      <a href="#acces">Accès</a>
      <a href="#securite">Sécurité</a>
      <a class="btn navy" href="{{ route('login') }}">Se connecter</a>
    </nav>
  </div>
</header>

<main>
<section class="hero">
  <div class="wrap">
    <div>
      <span class="eyebrow">Institutions publiques de Côte d'Ivoire</span>
      <h1>Tout le parc informatique de l'État, <em>suivi au même endroit</em>.</h1>
      <p class="lead">GesParc recense chaque ordinateur, imprimante ou onduleur, sait à qui il est affecté et dans quel état il se trouve. Il traite les demandes de matériel et les pannes, pour chaque juridiction comme au niveau national.</p>
      <div class="cta">
        <a class="btn gold" href="{{ route('login') }}">Accéder à mon espace</a>
        <a class="btn ghost" href="#fonctionnalites">Découvrir les fonctionnalités</a>
      </div>
      <p class="note">Né au Tribunal de première instance de Bouaké, conçu pour plusieurs institutions sur un même serveur.</p>
    </div>
    <div class="mock" aria-hidden="true">
      <svg viewBox="0 0 560 380" font-family="Segoe UI, Arial, sans-serif">
        <rect width="560" height="380" fill="#f3f6fa"/>
        <rect width="120" height="380" fill="#102b4e"/>
        <text x="18" y="34" fill="#fff" font-size="15" font-weight="700">GesParc</text>
        <rect x="12" y="54" width="96" height="24" rx="6" fill="#ffffff22"/>
        <text x="22" y="70" fill="#fff" font-size="10">Tableau de bord</text>
        <text x="22" y="100" fill="#cbd5e1" font-size="10">Inventaire</text>
        <text x="22" y="126" fill="#cbd5e1" font-size="10">Demandes</text>
        <text x="22" y="152" fill="#cbd5e1" font-size="10">Consommables</text>
        <text x="22" y="178" fill="#cbd5e1" font-size="10">Pannes</text>
        <text x="140" y="34" fill="#102b4e" font-size="15" font-weight="700">Tableau de bord</text>
        <g font-size="9" fill="#64748b">
          <rect x="140" y="48" width="96" height="58" rx="8" fill="#fff" stroke="#dbe3ed"/><text x="150" y="78" fill="#102b4e" font-size="20" font-weight="700">248</text><text x="150" y="95">Équipements actifs</text>
          <rect x="244" y="48" width="96" height="58" rx="8" fill="#fff" stroke="#dbe3ed"/><text x="254" y="78" fill="#b83a45" font-size="20" font-weight="700">6</text><text x="254" y="95">En panne</text>
          <rect x="348" y="48" width="96" height="58" rx="8" fill="#fff" stroke="#dbe3ed"/><text x="358" y="78" fill="#102b4e" font-size="20" font-weight="700">41</text><text x="358" y="95">Plus de 5 ans</text>
          <rect x="452" y="48" width="92" height="58" rx="8" fill="#fff" stroke="#dbe3ed"/><text x="462" y="78" fill="#a56b12" font-size="20" font-weight="700">3</text><text x="462" y="95">Demandes</text>
        </g>
        <rect x="140" y="118" width="200" height="246" rx="10" fill="#fff" stroke="#dbe3ed"/>
        <text x="154" y="140" fill="#102b4e" font-size="11" font-weight="700">Parc par type</text>
        <g font-size="9" fill="#475569">
          <text x="154" y="166">Ordinateurs</text><rect x="230" y="158" width="96" height="9" rx="4" fill="#e8eef5"/><rect x="230" y="158" width="90" height="9" rx="4" fill="#173d68"/>
          <text x="154" y="192">Imprimantes</text><rect x="230" y="184" width="96" height="9" rx="4" fill="#e8eef5"/><rect x="230" y="184" width="58" height="9" rx="4" fill="#173d68"/>
          <text x="154" y="218">Écrans</text><rect x="230" y="210" width="96" height="9" rx="4" fill="#e8eef5"/><rect x="230" y="210" width="50" height="9" rx="4" fill="#173d68"/>
          <text x="154" y="244">Onduleurs</text><rect x="230" y="236" width="96" height="9" rx="4" fill="#e8eef5"/><rect x="230" y="236" width="34" height="9" rx="4" fill="#173d68"/>
          <text x="154" y="270">Scanners</text><rect x="230" y="262" width="96" height="9" rx="4" fill="#e8eef5"/><rect x="230" y="262" width="20" height="9" rx="4" fill="#173d68"/>
        </g>
        <rect x="350" y="118" width="194" height="246" rx="10" fill="#fff" stroke="#dbe3ed"/>
        <text x="364" y="140" fill="#102b4e" font-size="11" font-weight="700">Dernières demandes</text>
        <g font-size="9">
          <text x="364" y="166" fill="#1e293b" font-weight="700">DEM-2026-014</text><text x="364" y="179" fill="#64748b">Souris × 1 · Greffe civil</text><rect x="482" y="158" width="50" height="16" rx="8" fill="#e3f5ec"/><text x="489" y="169" fill="#16845b" font-weight="700">Accordée</text>
          <text x="364" y="206" fill="#1e293b" font-weight="700">DEM-2026-015</text><text x="364" y="219" fill="#64748b">Toner HP 59A × 2</text><rect x="482" y="198" width="50" height="16" rx="8" fill="#fdf1dd"/><text x="488" y="209" fill="#a56b12" font-weight="700">En attente</text>
          <text x="364" y="246" fill="#1e293b" font-weight="700">PAN-2026-003</text><text x="364" y="259" fill="#64748b">Imprimante · Parquet</text><rect x="482" y="238" width="50" height="16" rx="8" fill="#fbe7e9"/><text x="490" y="249" fill="#b83a45" font-weight="700">En panne</text>
          <text x="364" y="286" fill="#1e293b" font-weight="700">DEM-2026-016</text><text x="364" y="299" fill="#64748b">Écran × 1 · Présidence</text><rect x="482" y="278" width="50" height="16" rx="8" fill="#fdf1dd"/><text x="488" y="289" fill="#a56b12" font-weight="700">En attente</text>
        </g>
      </svg>
    </div>
  </div>
</section>

<div class="strip">
  <div class="wrap">
    <div><b>1 serveur</b><span>pour toutes les juridictions et administrations</span></div>
    <div><b>7 rôles</b><span>de l'agent à l'administrateur national</span></div>
    <div><b>100 %</b><span>des mouvements historisés : qui, quoi, quand</span></div>
    <div><b>Cloisonné</b><span>chaque institution ne voit que ses données</span></div>
  </div>
</div>

<section id="fonctionnalites">
  <div class="wrap">
    <div class="head"><span class="k">Fonctionnalités</span><h2>Du bon de réception à la réforme, rien ne se perd</h2><p>Les outils dont le Service informatique a besoin chaque jour, et la vue d'ensemble qu'attend la hiérarchie.</p></div>
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
    <div class="head"><span class="k">Accès</span><h2>Un espace pour chaque profil</h2><p>Connectez-vous avec l'identifiant fourni par l'administrateur de votre institution.</p></div>
    <div class="access">
      <div class="card">
        <span class="tag">Agents</span><h3>Mon espace</h3>
        <ul><li>Voir le matériel qui m'est affecté</li><li>Demander un équipement ou une cartouche</li><li>Signaler une panne</li><li>Suivre mes demandes</li></ul>
        <a class="btn outline" href="{{ route('login', ['profil' => 'agent']) }}">Connexion agent</a>
      </div>
      <div class="card feat">
        <span class="tag">Service informatique</span><h3>Gestion du parc</h3>
        <ul><li>Inventaire, affectations et transferts</li><li>Traitement des demandes</li><li>Pannes, maintenance et réformes</li><li>Stock des consommables</li></ul>
        <a class="btn navy" href="{{ route('login', ['profil' => 'informatique']) }}">Connexion Service informatique</a>
      </div>
      <div class="card">
        <span class="tag">Responsables et administrateurs</span><h3>Pilotage</h3>
        <ul><li>Tableaux de bord de l'institution</li><li>Validation des réformes</li><li>Comptes, services et institutions</li><li>Vue nationale et audit</li></ul>
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
        <li><b>Cloisonnement par institution</b> : une juridiction ne voit jamais les données d'une autre.</li>
        <li><b>Droits par rôle</b> : chacun n'accède qu'à ce que sa fonction exige.</li>
        <li><b>Comptes protégés</b> : mots de passe chiffrés, verrouillage après 5 essais.</li>
        <li><b>Journal des actions</b> : chaque connexion et chaque modification est tracée.</li>
        <li><b>Sauvegarde chaque nuit</b>, conservée 30 jours, avec copie sur un second serveur.</li>
      </ul>
    </div>
    <div class="panel">
      <h3>Déjà utilisé au TPI de Bouaké ?</h3>
      <p>L'application « Gestion du personnel » du tribunal se raccorde à GesParc : les agents y déposent leurs demandes de matériel comme avant, et elles arrivent directement sur le serveur central.</p>
      <p>L'inventaire existant est repris automatiquement, sans ressaisie.</p>
      <a class="btn gold" href="{{ route('login') }}">Se connecter</a>
    </div>
  </div>
</section>

<section>
  <div class="wrap">
    <div class="final">
      <h2>Prêt à retrouver votre parc ?</h2>
      <p>Connectez-vous à GesParc avec votre identifiant professionnel.</p>
      <a class="btn gold" href="{{ route('login') }}">Accéder à GesParc</a>
    </div>
  </div>
</section>
</main>

<footer>
  <div class="wrap">
    <div><b>GesParc</b> · Gestion du parc informatique des institutions publiques de Côte d'Ivoire</div>
    <div>Un compte ? Demandez-le à l'administrateur de votre institution.</div>
  </div>
</footer>
</body>
</html>
