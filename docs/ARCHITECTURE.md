# SIGACI — architecture et modèle de données

Ce document décrit l'étape 2 du projet : comment l'application est construite et comment ses données sont organisées.
Le cahier des charges fonctionnel est tenu à part (Claude Doc « SIGACI – Cahier des charges »).

## Pile technique

| Couche | Choix | Pourquoi |
| --- | --- | --- |
| Langage | PHP 8.3 | Hébergé partout, nombreux développeurs en Côte d'Ivoire |
| Framework | Laravel 13 | Authentification, validation, migrations, tests intégrés |
| Base de données | MySQL 8 / MariaDB 10.6+ (SQLite pour le développement et les tests) | Standard des hébergeurs publics et privés |
| Interface | Pages Blade rendues côté serveur, CSS sans compilation | Légères sur connexion lente, aucun outillage JavaScript à maintenir |

## Multi-institutions (cloisonnement)

Chaque donnée métier porte une colonne `institution_id`. Le trait `App\Models\Concerns\BelongsToInstitution`
ajoute un filtre global à toutes les requêtes : un utilisateur ne lit et ne modifie que les données de son institution.
Seuls l'**administrateur national** et l'**auditeur** (rôles sans institution) voient toutes les institutions.

Conséquences :
- une URL vers un équipement d'une autre institution renvoie « 404 introuvable » ;
- à la création, l'institution de l'utilisateur est renseignée automatiquement ;
- une affectation vers un agent d'une autre institution est refusée.

## Modèle de données

```
institutions ─┬─< services
              ├─< users (role, service, bureau)
              ├─< equipments ─┬─< equipment_movements   (historique complet)
              │               └─< incidents             (pannes et maintenance)
              ├─< material_requests                    (demandes de matériel et de cartouches)
              ├─< consumables ──< consumable_movements (stock de cartouches : entrées, sorties)
              └─< audit_logs                           (journal : qui, quoi, quand, IP)
equipment_types  (référentiel national)
```

| Table | Rôle | Champs clés |
| --- | --- | --- |
| `institutions` | Juridiction ou administration | `code` (préfixe des n° d'inventaire), `nom`, `categorie`, `ville`, `active` |
| `services` | Service interne d'une institution | `institution_id`, `nom` |
| `users` | Comptes | `institution_id` (vide pour les rôles nationaux), `service_id`, `role`, `fonction`, `bureau`, `failed_logins`, `locked_until` |
| `equipment_types` | Types (ordinateur, imprimante…) | `nom`, `categorie` |
| `equipments` | Fiche équipement | `numero_inventaire` (unique par institution), `statut`, `etat`, `reforme_motif` / `reforme_proposee_par` / `reforme_proposee_le` (réforme en attente), `numero_serie`, `date_acquisition`, `prix_achat` (FCFA), `fin_garantie`, `service_id`, `bureau`, `assigned_user_id`, `source_ref` (identifiant d'origine d'un équipement repris, unique par institution) |
| `equipment_movements` | Historique | `type`, `statut_avant`, `statut_apres`, `from_user_id`, `to_user_id`, `motif`, `author_id` |
| `incidents` | Pannes | `numero` (PAN-AAAA-NNN), `statut`, `prestataire`, `cout`, `resolution` |
| `material_requests` | Demandes | `numero` (DEM-AAAA-NNN), `categorie` (materiel / cartouche), `materiel`, `quantite`, `statut`, `motif_refus` |
| `consumables` | Consommables suivis en quantité | `reference` (unique par institution), `categorie`, `imprimantes_compatibles`, `stock`, `seuil_alerte` |
| `consumable_movements` | Entrées et sorties de stock | `sens`, `quantite`, `stock_apres`, `material_request_id`, `beneficiaire_id`, `motif` |
| `personal_access_tokens` | Jetons de l'API (Sanctum) | expirent après 30 jours, purgés chaque nuit |
| `audit_logs` | Journal | `action`, `subject_type`, `subject_id`, `details`, `ip` |

## Cycle de vie d'un équipement

Toutes les transitions passent par `App\Services\EquipmentLifecycle`, qui vérifie la transition puis écrit l'historique.

| Depuis | Action | Vers |
| --- | --- | --- |
| (création) | Réception | En stock |
| En stock | Affectation | Affecté |
| Affecté | Transfert | Affecté |
| Affecté | Restitution | En stock |
| En stock, Affecté | Panne signalée (crée un incident) | En panne |
| En panne | Mise en maintenance | En maintenance |
| En panne, En maintenance | Réparé | Affecté (s'il avait un affectataire), sinon En stock |
| En panne, En maintenance | Irréparable | inchangé, réforme proposée |
| tout sauf Réformé | Proposition de réforme (informaticien, motif obligatoire) | inchangé |
| réforme proposée | Validation (responsable) | Réformé |
| réforme proposée | Rejet (responsable, motif obligatoire) | inchangé, proposition effacée |

## Droits par rôle

Définis dans `App\Providers\AppServiceProvider` (Gates).

| Droit | Rôles |
| --- | --- |
| Voir l'inventaire, les pannes, le tableau de bord | informaticien, gestionnaire de stock, responsable, admin d'institution, admin national, auditeur |
| Créer / modifier un équipement, affecter, gérer les pannes | informaticien |
| Proposer une réforme | informaticien |
| Valider ou rejeter une réforme | responsable |
| Déposer une demande | tout utilisateur rattaché à une institution |
| Traiter une demande | informaticien (toutes), gestionnaire de stock (cartouches seulement) ; jamais sa propre demande |
| Gérer les consommables (entrées, sorties, références) | informaticien, gestionnaire de stock |
| Gérer les comptes | admin d'institution (son institution), admin national (tous) |
| Gérer les institutions | admin national |
| Paramètres de l'application (nom affiché, description) | admin national |

Un agent voit le matériel qui lui est affecté et peut signaler une panne dessus.

## Module Personnel

Tables : `staff` (fiches, reliées à `users` quand l'agent a un compte), `staff_categories`, `staff_positions`
(fonctions d'autorité attribuées nommément), `approval_rules` (qui décide, par catégorie et type de demande :
`first_level`, `final_level`, `copy_to`). Le modèle « juridiction » (`PersonnelSetup::applyJuridiction`) reprend
les règles du TPI de Bouaké ; chaque institution peut les modifier dans Personnel > Paramétrage.

Les droits combinent le rôle SIGACI et les fonctions d'autorité (`App\Services\Personnel\Authority`) :

| Droit | Qui |
| --- | --- |
| Voir les fiches (`personnel.view`) | admin d'institution, admin national, auditeur, Président, Procureur, Greffier en Chef et adjoints, secrétaires |
| Créer une fiche (`personnel.create`) | admin d'institution, Président, Procureur, Greffier en Chef |
| Modifier une fiche, ouvrir le compte, sortie (`personnel.manage`) | admin d'institution et Président : toutes ; Procureur : magistrats du parquet sauf lui-même ; Greffier en Chef : personnel non magistrat |
| Fonctions d'autorité et règles (`personnel.configure`) | admin d'institution, admin national |

Comptes : identifiant = matricule (espaces et points ignorés) ou e-mail ; mot de passe provisoire affiché une
fois, à changer à la première connexion (`must_change_password`, middleware `EnsurePasswordChanged`).

### Circuits de validation (`App\Services\Personnel\Approvals`)

Le circuit se lit dans `approval_rules` : `first_level` (fonctions qui donnent le premier avis), `final_level`
(décision définitive), `copy_to` (information). Un agent qui détient lui-même une fonction du premier niveau passe
directement au niveau final. Le Président et le Greffier en Chef sont dispensés de demande d'absence ; le Président
est dispensé de vacances. Chaque étape est tracée dans `workflow_events` (trait `HasWorkflowEvents`).

**Absences** (`leave_requests`, `LeaveService`) : validation électronique. Statuts `en_attente` → `transmise`
(si deux niveaux) → `accordee` / `refusee`, ou `annulee` par l'agent tant que la demande est ouverte. Contrôles au
dépôt : chevauchement, dispense, au moins un jour ouvré. Les jours ouvrés excluent week-ends et jours fériés
(`public_holidays`, `WorkingDays` : fêtes civiles et chrétiennes calculées, fêtes musulmanes saisies dans
Paramétrage). La décision accordée est numérotée (`AA-AAAA-NNN`), porte un code de vérification et un QR code ;
« P.O. » quand un adjoint du Greffier en Chef signe. Page publique `/verification/{code}` (30 requêtes/minute).

**Vacances judiciaires** (`vacation_campaigns`, `vacation_requests`, `vacation_lists`, `VacationService`) :
campagne annuelle ouverte par l'admin, le Président ou le Greffier en Chef (mois août/septembre). L'agent choisit
son mois ; quota par service : au plus ⌈n/2⌉ départs le même mois. Le premier niveau retient le mois à titre
provisoire (`transmise`), le Président valide (observation obligatoire s'il change le mois). À la clôture, le
Président édite les listes définitives `LD-AAAA-AOUT`, `-SEPT`, `-GEN` (suffixe `-Vn` après réouverture ; magistrats
exclus), qui s'impriment, se signent à la main et dont le scan est archivé.

## Sécurité

- Mots de passe hachés (bcrypt) ; verrouillage 15 min après 5 échecs ; limitation à 20 tentatives/min par IP.
- Protection CSRF sur tous les formulaires ; sessions régénérées à la connexion.
- Journal `audit_logs` sur connexions, créations, modifications, transitions et décisions.
- En production : HTTPS obligatoire, `APP_DEBUG=false`.

## Consommables

Le stock ne devient jamais négatif (`App\Services\ConsumableStock`, verrou de ligne pendant la mise à jour).
Quand une demande de cartouche est accordée en choisissant la référence, la quantité sort du stock dans la même
transaction ; si le stock est insuffisant, rien n'est enregistré et la demande reste en attente.

## Courriels

`App\Services\Notifier` envoie un courriel (classe `SigaciMessage`) à chaque événement :

| Événement | Destinataires (même institution, comptes actifs, sauf l'auteur) |
| --- | --- |
| Nouvelle demande | informaticiens ; gestionnaires de stock aussi pour une cartouche |
| Demande accordée ou refusée | le demandeur |
| Panne signalée | informaticiens |
| Réforme proposée | responsables |
| Consommable passé sous le seuil | informaticiens et gestionnaires de stock |

Un échec d'envoi est journalisé sans bloquer l'action. `SIGACI_NOTIFICATIONS=false` coupe tout.

## API et application TPI

- API JSON v1 sous `/api/v1`, jetons Sanctum : voir [API.md](API.md). Les contrôleurs web et API partagent
  les mêmes services (`MaterialRequests`, `EquipmentLifecycle`, `LoginGuard`), donc les mêmes règles.
- Le connecteur `public/connecteur/sigaci-tpi.js` se charge dans l'application TPI. Une fois l'agent connecté,
  les demandes de matériel passent par l'API et l'inventaire du serveur s'affiche dans « Parc informatique ».
  Le jeton est gardé dans le navigateur (localStorage), par agent TPI.
- Installation, sauvegardes et raccordement : [INSTALLATION.md](INSTALLATION.md).

## Reprise des données de l'application d'origine

`php artisan sigaci:reprise-origine` lit les fichiers HTML autonomes de l'application « Gestion du personnel –
TPI de Bouaké » (`App\Services\Reprise\TpiHtmlExtractor`) : liste du personnel (`initialPeople`), services et
bureaux de novembre 2026 (`TPI_SERVICE_UPDATE_NOV2026*`) et inventaire du Pack informatique (bloc base64 `#pcSrc`).

- Personnel : `App\Services\Personnel\StaffImport`, partagé avec `sigaci:import-personnel`, retrouve la fiche par
  `staff.source_ref` et propose les fonctions d'autorité d'après l'intitulé de la fonction.
- Équipements : retrouvés par `equipments.source_ref` (`pc:<ligne>`, `ecran:<ligne>`, `imp:<id>`, `scan:<ligne>`).
  À la création : n° d'inventaire, mouvement `import`, puis affectation au service et au bureau d'origine par
  `EquipmentLifecycle::assign()`. À une nouvelle reprise, seule la description est actualisée.
- Consommables : une référence par toner rencontré, stock à 0 ; une référence existante n'est pas modifiée.
- `--exporter=fichier.json` écrit les données mises en correspondance (format `sigaci-reprise`), relisibles
  puis importables par la même commande. Ce fichier contient des données personnelles : hors du dépôt.
