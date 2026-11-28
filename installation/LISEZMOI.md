# Installateur SIGACI

L'installateur installe SIGACI **automatiquement, de bout en bout, sans aucune question**, avec tout ce dont il
a besoin. **Le relancer met SIGACI à jour** : il ne réinstalle rien et conserve les réglages et les données.

| Système | Commande |
| --- | --- |
| Windows (double-clic ou invite de commandes) | `installer.cmd` |
| Windows PowerShell | `powershell -ExecutionPolicy Bypass -File installer.ps1` |
| Linux (Debian, Ubuntu, Fedora…) ou WSL | `sudo bash installer.sh` |

Gardez les fichiers ensemble : `installer.ps1`, `installer.cmd`, `superviseur.ps1`, `installer.sh`, `outils.php`.

## Windows

Seule intervention : confirmer la demande de droits administrateur de Windows.

L'installateur choisit la pile logicielle :

- **Laragon installé** : il utilise le PHP chargé par Apache dans Laragon (8.3 ou plus), son Apache et son MySQL.
  Il active les extensions PHP nécessaires (copie de `php.ini` en `php.ini.avant-sigaci`) et ajoute le site
  `C:\laragon\etc\apache2\sites-enabled\sigaci.conf`.
  Si le port 80 est pris par un autre programme qu'Apache (redirection `netsh portproxy` pour WSL ou Docker, IIS…),
  SIGACI reçoit sa **propre instance Apache sur le port 8080** : mêmes programmes que Laragon, configuration dans
  `C:\SIGACI\apache\httpd.conf`. Ni Laragon ni le programme qui tient le port 80 ne sont modifiés.
- **Laragon absent** (pile autonome) : il télécharge depuis les sites officiels, en vérifiant chaque empreinte SHA-256 :
  - PHP 8.3, et au besoin les bibliothèques Visual C++ de Microsoft (signature vérifiée) ;
  - MariaDB 11.4, installé comme service Windows `SIGACIMariaDB` ;
  - le serveur web Caddy, avec 4 processus PHP.

Dans les deux cas, il installe aussi git (MinGit) et Composer s'ils manquent. Ensuite, il :

- télécharge SIGACI ;
- crée la base, son utilisateur, les tables et l'administrateur national ;
- crée la tâche planifiée **« SIGACI »** : elle démarre la base et le serveur web avec Windows (même sans session
  ouverte), les relance s'ils s'arrêtent, exécute les tâches de Laravel chaque minute et fait la sauvegarde de 2 h 30 ;
- rend SIGACI accessible depuis le réseau local (voir ci-dessous) ;
- vérifie que le site répond, sur ce serveur et par son adresse réseau.

Tout est réuni dans `C:\SIGACI` :

| Élément | Contenu |
| --- | --- |
| `app\` | SIGACI |
| `outils\` | l'installateur, pour les mises à jour |
| `sauvegardes\` | base et `.env`, gardés 30 jours |
| `journaux\` | journaux de l'installation et du superviseur |
| `identifiants.txt` | mots de passe générés (lisible par les seuls administrateurs) |
| `installation.json` | état de l'installation |
| `php\`, `mariadb\`, `data\`, `caddy\` | pile autonome seulement |

### Accès depuis le réseau local

Les autres postes (et les téléphones, pour les codes QR des étiquettes) ouvrent SIGACI par l'adresse IP du
serveur ou par son nom. Les deux adresses sont affichées à la fin de l'installation et notées dans
`identifiants.txt`, par exemple `http://192.168.1.7` et `http://nom-du-pc`. L'installateur configure :

- **le serveur web** pour qu'il réponde par le nom de l'ordinateur et par n'importe quelle adresse IP privée
  (`192.168.x.x`, `10.x.x.x`, `172.x.x.x`), même si elle change. Avec Laragon, `localhost` reste le site de Laragon ;
- **le pare-feu de Windows** : port web ouvert sur les réseaux « Domaine » et « Privé » et, sur un réseau classé
  « Public », seulement pour les postes du même sous-réseau. Les règles qui bloquaient Apache ou Caddy (créées
  quand quelqu'un ferme la question « Autoriser l'accès ? » de Windows) sont désactivées.

Si l'adresse IP est attribuée automatiquement (DHCP), l'installateur le signale : faites-la réserver dans le
routeur (ou la box) pour que les adresses imprimées dans les codes QR restent valables.

### Options (toutes facultatives)

| Option | Effet |
| --- | --- |
| `-Domaine sigaci.tpi-bouake.ci` | nom du site (défaut : nom de l'ordinateur) |
| `-AdminEmail informatique@tpi-bouake.ci` | compte administrateur national (défaut : `admin@sigaci.local`) |
| `-Pile autonome` | ignore Laragon (`-Pile laragon` : exige Laragon) |
| `-Base D:\SIGACI` | autre dossier d'installation |
| `-PortWeb 8080` | autre port web (avec Laragon : instance Apache propre à SIGACI ; `-PortWeb 80` revient au site Laragon) |
| `-Https` | HTTPS automatique avec Caddy (domaine public joignable depuis Internet) |
| `-Mode demo` | démonstration : SQLite, comptes de test, sans service (`-Demarrer` lance le serveur) |
| `-Reprise D:\GESTION_DU_PERSONNEL.html` | importe les données de l'application d'origine (voir plus bas) |
| `-Institution TPI-BKE` | institution qui reçoit la reprise (défaut : `TPI-BKE`, créée si besoin) |
| `-Restaurer D:\sigaci_2026-11-27_0230.zip` | remplace la base par la sauvegarde d'un autre serveur SIGACI (voir plus bas) |

Mots de passe imposés au lieu de mots de passe générés : variables `SIGACI_ADMIN_MDP` et, pour un MySQL de
Laragon protégé, `SIGACI_DB_ROOT_MDP`. Définissez-les dans une **console administrateur**, car la demande de
droits de Windows ne transmet pas les variables.

## Linux et WSL

```bash
sudo bash installer.sh
sudo bash installer.sh --domaine sigaci.tpi-bouake.ci --https --admin informatique@tpi-bouake.ci
bash installer.sh --mode demo --demarrer        # démonstration, sans root
```

En production, l'installateur :

- installe PHP 8.3 et ses extensions (en ajoutant le dépôt d'Ondřej Surý si la distribution est trop ancienne),
  MariaDB, Nginx, php-fpm, git, Composer, cron et, avec `--https`, certbot ;
- installe SIGACI dans `/var/www/sigaci` ;
- crée la base, l'utilisateur, les tables et l'administrateur ;
- configure Nginx, les droits de `www-data`, les tâches planifiées et la sauvegarde de 2 h 30 (`/var/backups/sigaci`) ;
- rend SIGACI accessible depuis le réseau local : site Nginx par défaut (il répond par l'adresse IP du serveur et
  par son nom), port web ouvert dans ufw ou firewalld s'ils sont actifs ;
- vérifie que le site répond et affiche les adresses à utiliser depuis les autres postes.

Sous WSL, les autres postes ne voient pas Linux directement : activez `networkingMode=mirrored` dans
`%USERPROFILE%\.wslconfig`, ou utilisez plutôt `installer.cmd` sous Windows.

Les mots de passe générés sont dans `/etc/sigaci/identifiants.txt` (lisible par root seulement).
Mise à jour : `sudo sigaci-installer`, ou relancer `installer.sh`.

## Ce que fait une relance (mise à jour)

1. Sauvegarde de la base et du `.env`.
2. Mise en maintenance : les visiteurs voient « Service indisponible ».
3. Récupération de la nouvelle version. Les modifications faites à la main dans le code sont mises de côté
   (`sauvegardes\modifications-locales_*.patch`), puis réappliquées si elles restent compatibles.
   Une installation faite depuis une archive ZIP est rattachée à git, après une copie du code.
4. Dépendances, nouveaux réglages ajoutés au `.env` sans toucher aux existants, migrations, caches.
5. Remise en ligne, redémarrage du serveur web, vérification.

Si une étape échoue, SIGACI reste en maintenance avec la sauvegarde à disposition. Corrigez la cause et relancez :
l'installateur reprend où il s'était arrêté. Il en va de même après une installation interrompue.

**Les logiciels déjà installés ne sont pas modifiés lors d'une mise à jour** : versions de PHP, MariaDB et Caddy,
Laragon. Seul SIGACI change.

L'installateur suit la branche `main` du dépôt (`-Branche` / `--branche` pour une autre).

### Installation faite sous l'ancien nom (GesParc)

L'installateur la reconnaît (`C:\GesParc` sous Windows, `/etc/gesparc` sous Linux) et la met à jour **sur place** :
même dossier, même base de données, mêmes mots de passe. Il passe de la branche `prototype-v1` à `main`, remplace la
tâche planifiée « GesParc » par « SIGACI », le site Apache `gesparc.conf` par `sigaci.conf` et les règles de pare-feu.
Une installation faite à la main ou par une ancienne version de l'installateur hors de `C:\GesParc` est reprise
avec `installer.cmd -Dossier "C:\chemin\vers\gesparc"`.

Le compte administrateur national créé par l'installateur doit choisir un nouveau mot de passe à sa première
connexion (le mot de passe généré n'est noté que dans `identifiants.txt`).

## Reprendre les données de l'application d'origine

L'installateur n'importe aucune donnée par lui-même : une installation neuve est vide (types d'équipement et
administrateur national seulement). Sous Windows, deux options le font pendant l'installation ou une mise à jour :

```bat
installer.cmd -Reprise D:\GESTION_DU_PERSONNEL.html
installer.cmd -Restaurer D:\sigaci_2026-11-27_0230.zip
```

- **`-Reprise`** : personnel (services et bureaux), unités centrales, écrans, imprimantes, scanners et références de
  toner des fichiers HTML de l'ancienne application, dans l'institution `-Institution` (défaut `TPI-BKE`, créée si
  besoin). Plusieurs fichiers : `-Reprise a.html,b.html`. Relancer ne crée pas de doublons. Les lignes non reprises
  sont listées dans le journal.
- **`-Restaurer`** : transfère les données d'un autre serveur SIGACI. Indiquez sa sauvegarde : `sigaci_*.zip` de
  `C:\SIGACI\sauvegardes` (Windows) ou `sigaci_*.sql.gz` de `/var/backups/sigaci` (Linux). Le fichier est vérifié,
  puis **remplace toutes les données** (une mise à jour sauvegarde d'abord la base actuelle) ; les migrations
  l'amènent ensuite à la version installée. Comptes et mots de passe deviennent ceux du serveur d'origine. Une
  relance avec la même sauvegarde ne la réapplique pas.

Les deux options peuvent être combinées (restauration d'abord, reprise ensuite). Copiez les fichiers sur le serveur
en dehors de `C:\SIGACI\app` : ils contiennent des données personnelles.

La reprise peut aussi se lancer à la main, depuis le dossier de l'application :

```powershell
cd C:\SIGACI\app
# Pile autonome : C:\SIGACI\php\php.exe ; Laragon : le php.exe de Laragon
C:\SIGACI\php\php.exe artisan sigaci:reprise-origine C:\chemin\GESTION_DU_PERSONNEL.html --exporter=reprise.json
C:\SIGACI\php\php.exe artisan sigaci:reprise-origine reprise.json --institution=TPI-BKE --dry-run
C:\SIGACI\php\php.exe artisan sigaci:reprise-origine reprise.json --institution=TPI-BKE
```

Sous Linux : `cd /var/www/sigaci && sudo -u www-data php artisan sigaci:reprise-origine ...`. La commande peut être
relancée sans créer de doublons. Ces fichiers contiennent des données personnelles : ne les laissez pas dans `app\`.

## En cas de problème

| Message | Que faire |
| --- | --- |
| Droits administrateur refusés | Relancez et acceptez la demande de Windows |
| Téléchargement impossible | Vérifiez l'accès à Internet (proxy éventuel), puis relancez |
| Création de la base impossible avec le compte root de Laragon | Le root de Laragon a un mot de passe : console administrateur, `$env:SIGACI_DB_ROOT_MDP='...'`, puis l'installateur |
| SIGACI ne répond pas | Consultez `C:\SIGACI\journaux\superviseur.log` et `C:\SIGACI\app\storage\logs` |
| Le port 80 est déjà pris (IIS, redirection de ports…) | Le port 8080 est choisi automatiquement (Laragon : instance Apache propre à SIGACI) ; ou `-PortWeb` |
| Les autres postes n'atteignent pas SIGACI | Essayez l'adresse IP affichée plutôt que le nom ; vérifiez qu'un antivirus avec pare-feu (Kaspersky, ESET…) ne bloque pas le port web |

Le journal complet de chaque passage est dans `C:\SIGACI\journaux\installation-*.log`.
