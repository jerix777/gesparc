# GesParc

Gestion du parc informatique des institutions publiques de Côte d'Ivoire : inventaire, affectations, demandes de
matériel, pannes et maintenance, pour plusieurs juridictions ou administrations sur un même serveur.

Né du module « Parc informatique et accessoires » de l'application de gestion du personnel du TPI de Bouaké.

## Fonctionnalités (prototype)

- **Inventaire** : fiche complète, n° d'inventaire automatique par institution, recherche, filtres, export Excel (CSV), étiquette imprimable.
- **Affectations et mouvements** : affectation à un agent, un service ou un bureau, transfert, restitution ; historique complet.
- **Demandes de matériel** : dépôt par l'agent, accord ou refus motivé par le Service informatique (cartouches : gestionnaire de stock).
- **Pannes et maintenance** : signalement (y compris par l'agent), mise en maintenance, clôture réparé / irréparable, coût.
- **Comptes et institutions** : 7 rôles, cloisonnement strict par institution, verrouillage après 5 échecs, journal des actions.
- **Tableaux de bord** : par statut, par type, matériel de plus de 5 ans, garanties qui expirent, vue nationale par institution.
- **Reprise** des données de l'application TPI (`gesparc:import-tpi`).

Architecture, modèle de données et droits : [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md).

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

Ouvrez http://localhost:8000. Comptes de démonstration (mot de passe `demo2026`) :

| Compte | Rôle |
| --- | --- |
| national@gesparc.test | Administrateur national |
| audit@gesparc.test | Auditeur |
| admin.bke@gesparc.test | Administrateur du TPI de Bouaké |
| info.bke@gesparc.test | Informaticien du TPI de Bouaké |
| stock.bke@gesparc.test | Gestionnaire de stock |
| president.bke@gesparc.test | Responsable (président) |
| agent.bke@gesparc.test | Agent (greffière) |

Les mêmes comptes existent pour le TPI d'Abidjan-Plateau avec le suffixe `.abj`.

## Installation sur un serveur

1. Serveur Linux avec PHP 8.3 (extensions `pdo_mysql`, `mbstring`, `intl`), MySQL 8 ou MariaDB, Nginx ou Apache pointant sur `public/`.
2. `composer install --no-dev --optimize-autoloader`, puis renseigner `.env` (`APP_ENV=production`, `APP_DEBUG=false`, `APP_URL` en https, accès MySQL).
3. `php artisan key:generate && php artisan migrate --force --seed` (le seeder par défaut ne crée que les types d'équipement).
4. `php artisan gesparc:create-admin admin@exemple.ci` pour créer l'administrateur national, qui crée ensuite les institutions et leurs administrateurs.

Le guide complet (HTTPS, sauvegardes quotidiennes) viendra à l'étape 4.

## Reprendre les données du TPI de Bouaké

Dans l'application TPI, faites une sauvegarde JSON, puis :

```bash
php artisan gesparc:import-tpi sauvegarde.json --institution=TPI-BKE --dry-run   # simulation
php artisan gesparc:import-tpi sauvegarde.json --institution=TPI-BKE
```

Les équipements reçoivent un n° d'inventaire, l'état TPI est converti en statut, et l'affectation d'origine est
rapprochée d'un agent par son nom (sinon conservée en observation). Les doublons de n° de série sont ignorés.

## Tests

```bash
php artisan test
```
