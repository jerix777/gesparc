# SIGACI

**Système Intégré de Gestion et d'Administration de Côte d'Ivoire** (anciennement GesParc).

Gestion du parc informatique des institutions publiques de Côte d'Ivoire : inventaire, affectations, demandes de
matériel, pannes et maintenance, pour plusieurs juridictions ou administrations sur un même serveur.

Né du module « Parc informatique et accessoires » de l'application de gestion du personnel du TPI de Bouaké.

## Fonctionnalités

- **Inventaire** : fiche complète, n° d'inventaire automatique par institution, recherche, filtres, export Excel (CSV), étiquette imprimable avec QR code (ouvre la fiche au téléphone).
- **Affectations et mouvements** : affectation à un agent, un service ou un bureau, transfert, restitution ; historique complet.
- **Réforme en deux temps** : l'informaticien propose (ou déclare une panne irréparable), le responsable valide ou rejette.
- **Demandes de matériel** : dépôt par l'agent, accord ou refus motivé par le Service informatique (cartouches : gestionnaire de stock).
- **Consommables** : cartouches et toners par institution, entrées et sorties, seuil d'alerte, sortie automatique quand une demande est accordée.
- **API et application TPI** : API JSON par jeton ([docs/API.md](docs/API.md)) et connecteur pour l'application TPI (`public/connecteur/sigaci-tpi.js`).
- **Pannes et maintenance** : signalement (y compris par l'agent), mise en maintenance, clôture réparé / irréparable, coût.
- **Comptes et institutions** : 7 rôles, cloisonnement strict par institution, verrouillage après 5 échecs, journal des actions.
- **Courriels automatiques** : nouvelle demande ou panne au Service informatique, décision au demandeur, réforme à valider au responsable, stock bas.
- **Tableaux de bord** : par statut, par type, matériel de plus de 5 ans, garanties qui expirent, vue nationale par institution.
- **Personnel** : fiches, comptes par matricule, absences validées en ligne (décision avec QR code vérifiable), vacances judiciaires avec quota par service, validation du Président et listes signées.
- **Reprise** des données de l'application TPI (`sigaci:reprise-origine`, `sigaci:import-tpi`, `sigaci:import-personnel`).

Architecture, modèle de données et droits : [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md).

## Interface

L'habillage reprend celui de l'application « Gestion du personnel – TPI de Bouaké » : couleurs du drapeau
(orange `#F77F00`, blanc, vert `#009E60`), menu latéral vert, filet tricolore, cartes et tableaux arrondis
(`public/css/app.css`). Le ministère affiché en haut des pages et le bandeau « Information » se règlent dans
`config/sigaci.php` (variables `SIGACI_TUTELLE` et `SIGACI_INFORMATION` du fichier `.env`).

## Installation automatique

Le dossier [`installation/`](installation/LISEZMOI.md) installe SIGACI et tout ce dont il a besoin (PHP, base de données,
serveur web, démarrage automatique, sauvegardes, accès depuis le réseau local), sans aucune question.
Le relancer met SIGACI à jour.

```text
Windows (double-clic) : installation\installer.cmd      (utilise Laragon s'il est installé, sinon PHP + MariaDB + Caddy)
Linux ou WSL          : sudo bash installation/installer.sh
```

## Démarrer en local

Prérequis : PHP 8.3 et Composer.

```bash
composer install
cp .env.example .env            # puis DB_CONNECTION=sqlite pour un essai rapide
php artisan key:generate
touch database/database.sqlite  # si SQLite
php artisan migrate --seed --seeder=DemoSeeder
php artisan serve
```

Ouvrez http://localhost:8000 : la page d'accueil présente SIGACI et mène à la connexion. Comptes de démonstration (mot de passe `demo2026`) :

| Compte | Rôle |
| --- | --- |
| national@sigaci.test | Administrateur national |
| audit@sigaci.test | Auditeur |
| admin.bke@sigaci.test | Administrateur du TPI de Bouaké |
| info.bke@sigaci.test | Informaticien du TPI de Bouaké |
| stock.bke@sigaci.test | Gestionnaire de stock |
| president.bke@sigaci.test | Responsable (président) |
| agent.bke@sigaci.test | Agent (greffière) |

Les mêmes comptes existent pour le TPI d'Abidjan-Plateau avec le suffixe `.abj`.

## Installation sur un serveur

Guide pas à pas (Ubuntu, MariaDB, Nginx, HTTPS, sauvegardes quotidiennes, raccordement de l'application TPI) :
[docs/INSTALLATION.md](docs/INSTALLATION.md).

## Page d'accueil (vitrine)

La page publique `/` est personnalisée pour le Tribunal de Première Instance de Bouaké : bandeau de tutelle,
carrousel de quatre diapositives et chiffres du parc calculés en direct (totaux uniquement, mis en cache 10 minutes).

- Textes, nom de l'institution, contact et diapositives : `config/vitrine.php`.
- Institution dont les chiffres sont affichés : `VITRINE_INSTITUTION` (code, ex. `TPI-BKE`) ; `VITRINE_STATISTIQUES=false` masque les chiffres.
- Les images sont des illustrations originales (aucun droit à payer). Pour mettre vos propres photos, déposez-les dans
  `public/images/vitrine/` et renseignez la clé `image` de la diapositive (ex. `'image' => 'palais.jpg'`).

## Reprendre les données du TPI de Bouaké

Dans l'application TPI, faites une sauvegarde JSON, puis :

```bash
php artisan sigaci:import-tpi sauvegarde.json --institution=TPI-BKE --dry-run   # simulation
php artisan sigaci:import-tpi sauvegarde.json --institution=TPI-BKE
```

Les équipements reçoivent un n° d'inventaire, l'état TPI est converti en statut, et l'affectation d'origine est
rapprochée d'un agent par son nom (sinon conservée en observation). Les doublons de n° de série sont ignorés.

### Fiches du personnel (module Personnel)

```bash
php artisan sigaci:import-personnel GESTION_DU_PERSONNEL.html sauvegarde.json --institution=TPI-BKE --dry-run
php artisan sigaci:import-personnel GESTION_DU_PERSONNEL.html sauvegarde.json --institution=TPI-BKE
```

Le fichier HTML de l'application fournit la liste d'origine ; la sauvegarde JSON (facultative, `tpi_people`)
apporte les services, bureaux et dates de prise de fonction saisis depuis. La commande peut être relancée sans
créer de doublons. Les fonctions d'autorité (Président, Procureur, Greffier en Chef…) sont proposées d'après
l'intitulé de la fonction, puis se vérifient dans Personnel > Paramétrage. **Ces fichiers contiennent des données
personnelles : ne les ajoutez jamais au dépôt.**

### Reprise complète depuis les fichiers HTML d'origine

Une seule commande reprend tout ce que contiennent les fichiers HTML de l'application (personnel avec services et
bureaux, unités centrales, écrans, imprimantes, scanners, références de toner) :

```bash
# 1. Relire les données mises en correspondance (fichier JSON, rien n'est importé)
php artisan sigaci:reprise-origine GESTION_DU_PERSONNEL.html --exporter=reprise-tpi-bouake.json
# 2. Simuler, puis importer (le JSON relu ou directement le HTML)
php artisan sigaci:reprise-origine reprise-tpi-bouake.json --institution=TPI-BKE --dry-run
php artisan sigaci:reprise-origine reprise-tpi-bouake.json --institution=TPI-BKE
```

`--creer-institution` crée l'institution si elle n'existe pas encore. La commande peut être relancée sans doublons :
une fiche ou un équipement déjà repris est mis à jour (description seulement pour le matériel). Les lignes non
reprises et les points à vérifier sont listés à la fin. Les stocks de toner sont créés à 0, à saisir ensuite.

Les jours fériés civils et chrétiens sont calculés automatiquement ; les fêtes musulmanes (dates annoncées chaque
année) s'ajoutent dans Personnel > Paramétrage.

## Tests

Les tests se lancent en local (pas d'intégration continue sur GitHub) :

```bash
php artisan test
```

Lancez-les avant chaque envoi sur le dépôt.
