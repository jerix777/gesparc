#!/usr/bin/env bash
# ============================================================================
#  SIGACI - installation ou mise à jour automatique (Linux natif ou WSL)
#
#  Première exécution : installe tout, sans question.
#    - production (défaut) : PHP 8.3, MariaDB, Nginx, php-fpm, git, Composer ; base, utilisateur,
#      tables, administrateur national ; site Nginx (HTTPS avec --https) ; tâches planifiées ;
#      sauvegarde chaque nuit. Mots de passe générés, notés dans /etc/sigaci/identifiants.txt.
#    - demo : PHP et SQLite, comptes de test, serveur intégré.
#  Exécutions suivantes : met seulement SIGACI à jour (sauvegarde, maintenance, code, dépendances,
#  nouveaux réglages, migrations, caches, rechargement). Le .env et les données sont conservés.
#
#    sudo bash installer.sh
#    sudo bash installer.sh --domaine sigaci.tpi-bouake.ci --https --admin informatique@tpi-bouake.ci
#    bash installer.sh --mode demo --demarrer
#
#  bash installer.sh --aide   pour toutes les options.
# ============================================================================
set -euo pipefail

MODE="production"
DOSSIER=""
BRANCHE="main"
BRANCHE_DONNEE=""
DEPOT="https://github.com/jerix777/gesparc.git"
DOMAINE=""
HTTPS=""
ADMIN="admin@sigaci.local"
PHPV="8.3"
DEMARRER=""
ETAT_FICHIER="/etc/sigaci/installation.env"

aide() {
    cat <<'FIN'
Usage : [sudo] bash installer.sh [options]

  --mode production|demo   production (défaut, root) : serveur complet ; demo : SQLite + comptes de test
  --dossier CHEMIN         dossier de SIGACI (défaut : /var/www/sigaci, ou ~/sigaci-demo en demo)
  --domaine NOM            nom de domaine du site (défaut : adresse IP du serveur)
  --https                  certificat Let's Encrypt (le domaine doit pointer vers ce serveur)
  --admin COURRIEL         compte administrateur national (défaut : admin@sigaci.local)
  --php 8.3                version de PHP à installer
  --branche NOM            branche git (défaut : main)
  --demarrer               mode demo : lance le serveur intégré à la fin

Sans question. Mots de passe générés, ou fournis par SIGACI_ADMIN_MDP.
Relancer le script met SIGACI à jour.
FIN
}

ARGS_ORIGINE=("$@")
while [ $# -gt 0 ]; do
    case "$1" in
        --mode) MODE="$2"; shift 2 ;;
        --dossier) DOSSIER="$2"; shift 2 ;;
        --branche) BRANCHE="$2"; BRANCHE_DONNEE=1; shift 2 ;;
        --depot) DEPOT="$2"; shift 2 ;;
        --domaine) DOMAINE="$2"; shift 2 ;;
        --https) HTTPS=1; shift ;;
        --admin) ADMIN="$2"; shift 2 ;;
        --php) PHPV="$2"; shift 2 ;;
        --demarrer) DEMARRER=1; shift ;;
        --serveur|--paquets|--oui|-y) shift ;;   # anciennes options : c'est désormais le comportement par défaut
        --aide|-h|--help) aide; exit 0 ;;
        *) echo "Option inconnue : $1"; aide; exit 2 ;;
    esac
done
case "$MODE" in production|demo) ;; *) echo "Mode inconnu : $MODE"; exit 2 ;; esac

# ------------------------------------------------------------------ affichage

if [ -t 1 ]; then C_B=$'\e[36m'; C_V=$'\e[32m'; C_J=$'\e[33m'; C_R=$'\e[31m'; C_0=$'\e[0m'; else C_B=; C_V=; C_J=; C_R=; C_0=; fi
N=0
MAINTENANCE=""
etape()     { N=$((N + 1)); printf '\n%s[%d] %s%s\n' "$C_B" "$N" "$1" "$C_0"; }
ok()        { printf '  %s[OK]%s %s\n' "$C_V" "$C_0" "$1"; }
info()      { printf '  %s\n' "$1"; }
attention() { printf '  %s[ATTENTION]%s %s\n' "$C_J" "$C_0" "$1"; }
echec() {
    printf '\n  %s[ERREUR]%s %s\n' "$C_R" "$C_0" "$1" >&2
    [ -n "$MAINTENANCE" ] && printf "  SIGACI est resté en maintenance. Après correction, relancez l'installateur.\n" >&2
    exit 1
}
mot_de_passe() { LC_ALL=C tr -dc 'A-HJ-NP-Za-km-z2-9' </dev/urandom | head -c "${1:-24}" || true; }

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
OUTILS="$SCRIPT_DIR/outils.php"
[ -f "$OUTILS" ] || echec "outils.php introuvable : gardez-le à côté de installer.sh."

# ------------------------------------------------------------------ droits

if [ "$MODE" = "production" ] && [ "$(id -u)" -ne 0 ]; then
    command -v sudo >/dev/null 2>&1 || echec "Lancez ce script en root (sudo absent)."
    echo "  Droits root nécessaires : relance avec sudo..."
    exec sudo -E bash "$0" "${ARGS_ORIGINE[@]}"
fi
racine() { if [ "$(id -u)" -eq 0 ]; then "$@"; else sudo "$@"; fi; }
service_actif() {
    if [ -d /run/systemd/system ]; then racine systemctl enable --now "$1" >/dev/null 2>&1 || racine systemctl restart "$1"
    else racine service "$1" start >/dev/null 2>&1 || racine service "$1" restart >/dev/null 2>&1; fi
}
recharger() {
    if [ -d /run/systemd/system ]; then racine systemctl reload "$1" 2>/dev/null || racine systemctl restart "$1"
    else racine service "$1" reload >/dev/null 2>&1 || racine service "$1" restart >/dev/null 2>&1; fi
}
WSL=""; grep -qi microsoft /proc/version 2>/dev/null && WSL=1

# ------------------------------------------------------------------ installation ou mise à jour ?

ETAT=""
# Installation faite sous l'ancien nom (GesParc) : son état est repris.
if [ "$MODE" = "production" ] && [ ! -f "$ETAT_FICHIER" ] && [ -f /etc/gesparc/installation.env ]; then
    mv /etc/gesparc /etc/sigaci
fi
# Variables de l'ancien nom encore acceptées.
[ -n "${GESPARC_ADMIN_MDP:-}" ] && [ -z "${SIGACI_ADMIN_MDP:-}" ] && export SIGACI_ADMIN_MDP="$GESPARC_ADMIN_MDP"
if [ "$MODE" = "production" ] && [ -f "$ETAT_FICHIER" ]; then
    # shellcheck disable=SC1090
    . "$ETAT_FICHIER"; ETAT=1
    APP="$E_APP"; DEPOT="$E_DEPOT"; DOMAINE="$E_DOMAINE"; URL="$E_URL"; PHPV="$E_PHPV"
    # La branche prototype-v1 a été fusionnée dans main, qui porte désormais les nouvelles versions.
    if [ -z "$BRANCHE_DONNEE" ]; then BRANCHE="$E_BRANCHE"; [ "$BRANCHE" = "prototype-v1" ] && BRANCHE="main"; fi
fi
if [ -z "$ETAT" ]; then
    if [ -n "$DOSSIER" ]; then APP="$DOSSIER"
    elif [ "$MODE" = "production" ]; then APP="/var/www/sigaci"
    else APP="$HOME/sigaci-demo"; fi
fi
MISE_A_JOUR=""
if [ -n "$ETAT" ] || { [ -f "$APP/artisan" ] && [ -f "$APP/.env" ]; }; then MISE_A_JOUR=1; fi

printf '\n  %s================================================%s\n' "$C_B" "$C_0"
if [ -n "$MISE_A_JOUR" ]; then printf '  %s SIGACI - mise à jour%s\n' "$C_B" "$C_0"; else printf '  %s SIGACI - installation automatique%s%s\n' "$C_B" "${WSL:+ (WSL)}" "$C_0"; fi
printf '  %s================================================%s\n' "$C_B" "$C_0"
info "Mode : $MODE"
info "Dossier : $APP"
[ -n "$WSL" ] && [[ "$APP" == /mnt/* ]] && attention "Dossier sur le disque Windows : SIGACI y sera lent. Préférez --dossier ~/sigaci-demo."

# ------------------------------------------------------------------ 1. paquets

paquets_requis() {
    local base=pdo_sqlite; [ "$MODE" = "production" ] && base=pdo_mysql
    command -v php >/dev/null 2>&1 && php -r 'exit(PHP_VERSION_ID >= 80300 ? 0 : 1);' 2>/dev/null \
        && php "$OUTILS" verifier "$base" >/dev/null 2>&1 && command -v git >/dev/null 2>&1 && command -v unzip >/dev/null 2>&1 \
        && { [ "$MODE" = "demo" ] || { command -v nginx >/dev/null 2>&1 && command -v mariadb >/dev/null 2>&1 && ls /run/php/php*-fpm.sock /usr/sbin/php-fpm* >/dev/null 2>&1; }; }
}

if [ -z "$MISE_A_JOUR" ] || ! paquets_requis; then
    etape "Logiciels nécessaires (PHP $PHPV, git$( [ "$MODE" = production ] && echo ', MariaDB, Nginx'))"
    if paquets_requis; then
        ok "Déjà installés"
    elif command -v apt-get >/dev/null 2>&1; then
        export DEBIAN_FRONTEND=noninteractive
        . /etc/os-release
        racine apt-get update -qq
        racine apt-get install -y -qq ca-certificates curl git unzip lsb-release gnupg >/dev/null
        if ! apt-cache show "php$PHPV-cli" >/dev/null 2>&1; then
            info "PHP $PHPV absent des dépôts de $PRETTY_NAME : ajout du dépôt de Ondřej Surý."
            if [[ "$ID ${ID_LIKE:-}" == *ubuntu* ]]; then
                racine apt-get install -y -qq software-properties-common >/dev/null
                racine add-apt-repository -y ppa:ondrej/php >/dev/null
            else
                racine curl -fsSL -o /usr/share/keyrings/sury-php.gpg https://packages.sury.org/php/apt.gpg
                echo "deb [signed-by=/usr/share/keyrings/sury-php.gpg] https://packages.sury.org/php/ $(lsb_release -sc) main" \
                    | racine tee /etc/apt/sources.list.d/sury-php.list >/dev/null
            fi
            racine apt-get update -qq
        fi
        LISTE="php$PHPV-cli php$PHPV-mbstring php$PHPV-xml php$PHPV-intl php$PHPV-zip php$PHPV-curl php$PHPV-sqlite3 php$PHPV-mysql"
        if [ "$MODE" = "production" ]; then
            LISTE="$LISTE php$PHPV-fpm nginx mariadb-server cron"
            [ -n "$HTTPS" ] && LISTE="$LISTE certbot python3-certbot-nginx"
        fi
        # shellcheck disable=SC2086
        racine apt-get install -y -qq $LISTE >/dev/null
        [ -x "/usr/bin/php$PHPV" ] && racine update-alternatives --set php "/usr/bin/php$PHPV" >/dev/null 2>&1 || true
        ok "Logiciels installés"
    elif command -v dnf >/dev/null 2>&1; then
        LISTE="git unzip curl php-cli php-mbstring php-xml php-intl php-zip php-pdo php-mysqlnd php-process"
        [ "$MODE" = "production" ] && LISTE="$LISTE php-fpm nginx mariadb-server cronie"
        [ "$MODE" = "production" ] && [ -n "$HTTPS" ] && LISTE="$LISTE certbot python3-certbot-nginx"
        # shellcheck disable=SC2086
        racine dnf install -y -q $LISTE
        ok "Logiciels installés"
    else
        echec "Gestionnaire de paquets non pris en charge (ni apt ni dnf) : installez PHP $PHPV, git et unzip, puis relancez."
    fi
fi
php -r 'exit(PHP_VERSION_ID >= 80300 ? 0 : 1);' || echec "PHP $(php -r 'echo PHP_VERSION;') trop ancien : SIGACI demande PHP 8.3+."
BASE_SQL=sqlite; [ "$MODE" = "production" ] && BASE_SQL=mysql
php "$OUTILS" verifier "$BASE_SQL" >/dev/null || { php "$OUTILS" verifier "$BASE_SQL"; echec "Extensions PHP manquantes."; }
ok "PHP $(php -r 'echo PHP_VERSION;')"

export COMPOSER_ALLOW_SUPERUSER=1 COMPOSER_NO_INTERACTION=1
if command -v composer >/dev/null 2>&1; then
    COMPOSER_CMD=(composer)
else
    if [ "$(id -u)" -eq 0 ]; then DC="/usr/local/lib/sigaci"; else DC="${XDG_CACHE_HOME:-$HOME/.cache}/sigaci"; fi
    mkdir -p "$DC"
    [ -f "$DC/composer.phar" ] || php "$OUTILS" composer "$DC" >/dev/null || echec "Installation de Composer impossible."
    COMPOSER_CMD=(php "$DC/composer.phar")
fi
ok "Composer prêt"

if [ "$MODE" = "production" ]; then
    service_actif mariadb
    service_actif "php$PHPV-fpm" || service_actif php-fpm || true
    service_actif nginx
    ok "MariaDB, php-fpm et Nginx en marche"
fi

# ------------------------------------------------------------------ 2. sauvegarde et maintenance (mise à jour)

SAUVEGARDES="/var/backups/sigaci"; [ "$MODE" = "demo" ] && SAUVEGARDES="$APP/../sigaci-sauvegardes"
STAMP="$(date +%Y-%m-%d_%H%M)"
if [ -n "$MISE_A_JOUR" ] && [ -f "$APP/vendor/autoload.php" ]; then
    etape "Sauvegarde et mise en maintenance"
    mkdir -p "$SAUVEGARDES"
    if [ "$MODE" = "production" ]; then
        APP_DIR="$APP" DEST="$SAUVEGARDES" bash "$APP/deploiement/sauvegarde.sh" >/dev/null || echec "Sauvegarde impossible : mise à jour annulée (rien n'a été modifié)."
        ok "Sauvegarde faite dans $SAUVEGARDES"
    elif [ -f "$APP/database/database.sqlite" ]; then
        cp "$APP/database/database.sqlite" "$SAUVEGARDES/database_$STAMP.sqlite"
        ok "Copie de la base SQLite dans $SAUVEGARDES"
    fi
    (cd "$APP" && php artisan down --retry=60 >/dev/null) && MAINTENANCE=1 && ok "SIGACI en maintenance"
fi

# ------------------------------------------------------------------ 3. code

etape "Code de SIGACI"
G() { git -c safe.directory='*' -C "$APP" "$@"; }
PATCH=""
if [ ! -f "$APP/artisan" ]; then
    if [ -d "$APP" ] && [ -n "$(ls -A "$APP")" ]; then echec "Le dossier $APP existe, n'est pas vide et ne contient pas SIGACI."; fi
    mkdir -p "$(dirname "$APP")"
    git -c safe.directory='*' clone --quiet --branch "$BRANCHE" "$DEPOT" "$APP" || echec "Clonage impossible (accès Internet ?)."
    ok "Code téléchargé"
else
    mkdir -p "$SAUVEGARDES"
    if [ ! -d "$APP/.git" ]; then
        tar -czf "$SAUVEGARDES/code-avant-maj_$STAMP.tar.gz" -C "$APP" --exclude=./vendor --exclude=./storage --exclude=./node_modules .
        info "Installation sans git : copie du code dans $SAUVEGARDES, puis rattachement au dépôt."
        G init --quiet; G remote add origin "$DEPOT"
    else
        PATCH="$SAUVEGARDES/modifications-locales_$STAMP.patch"
        G diff HEAD --binary --output="$PATCH"
        if [ -s "$PATCH" ]; then info "Modifications locales mises de côté : $PATCH"; else rm -f "$PATCH"; PATCH=""; fi
    fi
    AVANT="$(G rev-parse --short HEAD 2>/dev/null || echo archive)"
    G fetch --quiet origin "$BRANCHE" || echec "Impossible de joindre le dépôt (accès Internet ?)."
    G checkout --quiet --force -B "$BRANCHE" "origin/$BRANCHE"
    G reset --quiet --hard "origin/$BRANCHE"
    if [ -n "$PATCH" ]; then
        if G apply --check "$PATCH" 2>/dev/null; then G apply "$PATCH"; ok "Modifications locales réappliquées"
        else attention "Modifications locales incompatibles avec la nouvelle version : non réappliquées ($PATCH)."; fi
    fi
    APRES="$(G rev-parse --short HEAD)"
    if [ "$AVANT" = "$APRES" ]; then ok "Déjà à la dernière version ($APRES)"; else ok "Code mis à jour : $AVANT -> $APRES"; fi
fi
COMMIT="$(G rev-parse --short HEAD)"

etape "Dépendances PHP (Composer)"
cd "$APP"
OPTS=(install --no-interaction --no-progress)
[ "$MODE" = "production" ] && OPTS+=(--no-dev --optimize-autoloader)
"${COMPOSER_CMD[@]}" "${OPTS[@]}" || echec "composer install a échoué."
ok "Dépendances installées"

# ------------------------------------------------------------------ 4. configuration et base

etape "Configuration et base de données"
IDENTIFIANTS=()
if [ -z "$ETAT" ] && [ "$MODE" = "production" ]; then
    if [ -n "$DOMAINE" ]; then URL="http${HTTPS:+s}://$DOMAINE"
    else URL="http://$(hostname -I 2>/dev/null | awk '{print $1}')"; fi
fi
[ "$MODE" = "demo" ] && URL="http://localhost:8000"

if [ -f "$APP/.env" ]; then
    php "$OUTILS" completer-env "$APP"
else
    export SIGACI_MODE="$MODE" SIGACI_URL="$URL"
    if [ "$MODE" = "production" ]; then
        DB_MDP="$(mot_de_passe)"
        export SIGACI_DB_HOTE=localhost SIGACI_DB_PORT=3306 SIGACI_DB_NOM=sigaci SIGACI_DB_UTILISATEUR=sigaci SIGACI_DB_MDP="$DB_MDP"
        IDENTIFIANTS+=("Base de données SIGACI : utilisateur sigaci / $DB_MDP")
    fi
    php "$OUTILS" preparer-env "$APP" || echec "Configuration de .env impossible."
    unset SIGACI_DB_MDP DB_MDP
fi

NOUVELLE_BASE=""
php "$OUTILS" bd-initialisee "$APP" || NOUVELLE_BASE=1
if [ -n "$NOUVELLE_BASE" ] && [ "$MODE" = "production" ]; then
    # En root, MariaDB accepte root par le socket local sans mot de passe.
    SIGACI_DB_ROOT_UTILISATEUR=root php "$OUTILS" creer-bd "$APP" || echec "Préparation de la base MariaDB impossible."
fi
php artisan config:clear --quiet
if [ -n "$NOUVELLE_BASE" ]; then
    if [ "$MODE" = "production" ]; then php artisan migrate --force --seed || echec "Les migrations ont échoué."
    else php artisan migrate --force --seed --seeder=DemoSeeder || echec "Les migrations ont échoué."; fi
else
    php artisan migrate --force || echec "Les migrations ont échoué."
fi
ok "Base de données à jour"

if [ "$MODE" = "production" ]; then
    # Créé seulement s'il n'existe aucun administrateur national (y compris après une installation interrompue).
    ADMIN_MDP="${SIGACI_ADMIN_MDP:-$(mot_de_passe 16)}"
    CODE_ADMIN=0
    SIGACI_ADMIN_MDP="$ADMIN_MDP" SIGACI_ADMIN_EMAIL="$ADMIN" php "$OUTILS" creer-admin "$APP" || CODE_ADMIN=$?
    case "$CODE_ADMIN" in
        0) IDENTIFIANTS+=("Administrateur national SIGACI : $ADMIN / $ADMIN_MDP") ;;
        3) ;;
        *) echec "Création de l'administrateur national impossible." ;;
    esac
    unset ADMIN_MDP
fi

mkdir -p tests/Unit
if [ "$MODE" = "production" ]; then
    php artisan config:cache && php artisan route:cache && php artisan view:cache || echec "Mise en cache impossible."
    ok "Caches de production à jour"
else
    php artisan optimize:clear --quiet
fi
if [ -n "$MAINTENANCE" ]; then php artisan up >/dev/null; MAINTENANCE=""; ok "SIGACI remis en ligne"; fi

# ------------------------------------------------------------------ 5. serveur web, tâches, sauvegardes (production)

if [ "$MODE" = "production" ]; then
    etape "Serveur web, tâches planifiées et sauvegardes"
    WEB_USER="www-data"; id "$WEB_USER" >/dev/null 2>&1 || WEB_USER="nginx"
    chown -R "$WEB_USER:$WEB_USER" "$APP/storage" "$APP/bootstrap/cache"
    chown "root:$WEB_USER" "$APP/.env"; chmod 640 "$APP/.env"

    FPM_SOCK="$(ls /run/php/php*-fpm.sock /run/php-fpm/www.sock 2>/dev/null | sort -V | tail -1 || true)"
    [ -n "$FPM_SOCK" ] || FPM_SOCK="/run/php/php$PHPV-fpm.sock"
    if [ -d /etc/nginx/sites-available ]; then CONF=/etc/nginx/sites-available/sigaci; else CONF=/etc/nginx/conf.d/sigaci.conf; fi
    # Ancien nom : la configuration (et le HTTPS de certbot qu'elle contient) est reprise sous le nouveau nom.
    for ANCIEN in /etc/nginx/sites-available/gesparc /etc/nginx/conf.d/gesparc.conf; do
        if [ -f "$ANCIEN" ]; then [ -f "$CONF" ] || mv "$ANCIEN" "$CONF"; rm -f "$ANCIEN"; fi
    done
    rm -f /etc/nginx/sites-enabled/gesparc /etc/cron.d/gesparc /usr/local/bin/gesparc-sauvegarde \
        /usr/local/bin/gesparc-restauration /usr/local/sbin/gesparc-installer

    # Accès depuis le réseau local : SIGACI est le site par défaut, il répond donc aussi par l'adresse IP
    # du serveur (http://192.168.x.x) et par son nom, sauf si un autre site est déjà le site par défaut.
    NOM_PC="$(hostname -s 2>/dev/null || hostname)"
    NOM="$(echo "${DOMAINE:-} $NOM_PC $NOM_PC.local" | xargs)"
    AUTRE_DEFAUT=""
    for f in /etc/nginx/nginx.conf /etc/nginx/sites-enabled/* /etc/nginx/conf.d/*.conf; do
        [ -e "$f" ] || continue
        case "$(readlink -f "$f")" in "$(readlink -f "$CONF" 2>/dev/null || echo "$CONF")"|/etc/nginx/sites-available/default) continue ;; esac
        grep -qE '^[^#]*listen[^;]*default_server' "$f" && AUTRE_DEFAUT="$f"
    done
    if [ -z "$AUTRE_DEFAUT" ]; then
        LISTEN="80 default_server"
        [ -L /etc/nginx/sites-enabled/default ] && rm -f /etc/nginx/sites-enabled/default   # page d'accueil de Nginx
    else
        LISTEN="80"
        attention "$AUTRE_DEFAUT est déjà le site par défaut : SIGACI ne répondra que par son nom ($NOM)."
    fi
    # Le bloc HTTPS ajouté par certbot est conservé : on ne réécrit la configuration que si elle n'en a pas.
    if ! grep -q 'managed by Certbot' "$CONF" 2>/dev/null; then
        cat >"$CONF" <<NGINX
# SIGACI - généré par installation/installer.sh, réécrit à chaque mise à jour.
server {
    listen $LISTEN;
    server_name $NOM;
    root $APP/public;
    index index.php;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";
    client_max_body_size 10m;
    charset utf-8;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \.php\$ {
        fastcgi_pass unix:$FPM_SOCK;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
NGINX
    fi
    [ -d /etc/nginx/sites-enabled ] && ln -sf "$CONF" /etc/nginx/sites-enabled/sigaci
    nginx -t >/dev/null 2>&1 || { nginx -t; echec "Configuration Nginx invalide : $CONF"; }
    recharger nginx
    recharger "php$PHPV-fpm" || recharger php-fpm || true   # vide le cache OPcache : nouveau code pris en compte
    ok "Nginx configuré : $CONF"

    if [ -n "$HTTPS" ] && [ -n "$DOMAINE" ] && ! grep -q 'managed by Certbot' "$CONF"; then
        if certbot --nginx -d "$DOMAINE" --redirect --non-interactive --agree-tos -m "$ADMIN"; then ok "HTTPS actif pour $DOMAINE"
        else attention "Certificat non obtenu (le domaine pointe-t-il vers ce serveur ?). Relancez l'installateur plus tard."; fi
    fi

    install -m 700 "$APP/deploiement/sauvegarde.sh" /usr/local/bin/sigaci-sauvegarde
    install -m 700 "$APP/deploiement/restauration.sh" /usr/local/bin/sigaci-restauration
    install -m 700 "$SCRIPT_DIR/installer.sh" /usr/local/sbin/sigaci-installer 2>/dev/null || true
    mkdir -p /usr/local/lib/sigaci && install -m 644 "$OUTILS" /usr/local/lib/sigaci/outils.php 2>/dev/null || true
    cat >/etc/cron.d/sigaci <<CRON
# SIGACI - tâches planifiées (généré par installer.sh)
* * * * *  $WEB_USER  cd $APP && php artisan schedule:run >> /dev/null 2>&1
30 2 * * * root  APP_DIR=$APP DEST=$SAUVEGARDES /usr/local/bin/sigaci-sauvegarde >> /var/log/sigaci-sauvegarde.log 2>&1
CRON
    chmod 644 /etc/cron.d/sigaci
    service_actif cron 2>/dev/null || service_actif crond 2>/dev/null || true
    chown -R "$WEB_USER:$WEB_USER" "$APP/storage" "$APP/bootstrap/cache"
    ok "Tâches planifiées et sauvegarde quotidienne (2 h 30, $SAUVEGARDES)"

    HOTE="${DOMAINE:-localhost}"
    if curl -fsS -o /dev/null -H "Host: $HOTE" "http://127.0.0.1/connexion"; then ok "SIGACI répond : $URL"
    else echec "SIGACI ne répond pas (voir /var/log/nginx/error.log et $APP/storage/logs)."; fi

    # Pare-feu : ouverture du port web s'il y en a un d'actif (ufw sur Debian/Ubuntu, firewalld sur Fedora/RHEL).
    if command -v ufw >/dev/null 2>&1 && ufw status 2>/dev/null | grep -q '^Status: active'; then
        ufw allow 80/tcp >/dev/null; [ -n "$HTTPS" ] && ufw allow 443/tcp >/dev/null
        ok "Pare-feu (ufw) ouvert pour le web"
    elif command -v firewall-cmd >/dev/null 2>&1 && firewall-cmd --state >/dev/null 2>&1; then
        firewall-cmd --permanent --add-service=http >/dev/null; [ -n "$HTTPS" ] && firewall-cmd --permanent --add-service=https >/dev/null
        firewall-cmd --reload >/dev/null
        ok "Pare-feu (firewalld) ouvert pour le web"
    fi

    # Adresses à utiliser depuis les autres postes du réseau local.
    ADRESSES_LAN=()
    for ip in $(hostname -I 2>/dev/null); do
        case "$ip" in *:*|127.*|169.254.*|172.17.*) continue ;; esac   # IPv6, boucle locale, Docker
        ADRESSES_LAN+=("http://$ip")
    done
    ADRESSES_LAN+=("http://$NOM_PC.local")
    if [ -n "$AUTRE_DEFAUT" ]; then ADRESSES_LAN=("http://$NOM_PC.local"); fi
    if [ ${#ADRESSES_LAN[@]} -gt 1 ]; then
        if curl -fsS -o /dev/null --max-time 10 "${ADRESSES_LAN[0]}/connexion"; then ok "Accessible depuis le réseau local : ${ADRESSES_LAN[0]}"
        else attention "SIGACI ne répond pas sur ${ADRESSES_LAN[0]} : vérifiez le pare-feu."; fi
    fi
    [ -n "$WSL" ] && attention "WSL : pour les autres postes, activez networkingMode=mirrored dans .wslconfig, ou utilisez installer.cmd sous Windows."

    mkdir -p /etc/sigaci && chmod 700 /etc/sigaci
    INSTALLE_LE="${E_INSTALLE_LE:-$(date -Iseconds)}"
    cat >"$ETAT_FICHIER" <<ETAT
# SIGACI - état de l'installation (utilisé par l'installateur pour les mises à jour)
E_APP='$APP'
E_BRANCHE='$BRANCHE'
E_DEPOT='$DEPOT'
E_DOMAINE='$DOMAINE'
E_URL='$URL'
E_PHPV='$PHPV'
E_COMMIT='$COMMIT'
E_INSTALLE_LE='$INSTALLE_LE'
E_MIS_A_JOUR_LE='$(date -Iseconds)'
ETAT
    chmod 600 "$ETAT_FICHIER"
    if [ ${#IDENTIFIANTS[@]} -gt 0 ]; then
        {
            echo "SIGACI - identifiants créés le $(date '+%d/%m/%Y %H:%M'). Conservez-les en lieu sûr, puis supprimez ce fichier."
            echo "Adresse : $URL"
            echo "Depuis les autres postes : ${ADRESSES_LAN[*]}"
            printf '%s\n' "${IDENTIFIANTS[@]}"
            echo
        } >>/etc/sigaci/identifiants.txt
        chmod 600 /etc/sigaci/identifiants.txt
    fi
fi

# ------------------------------------------------------------------ résumé

printf '\n  %s================================================%s\n' "$C_V" "$C_0"
if [ -n "$MISE_A_JOUR" ]; then printf '  %s SIGACI est à jour (version %s)%s\n' "$C_V" "$COMMIT" "$C_0"; else printf '  %s SIGACI est installé (version %s)%s\n' "$C_V" "$COMMIT" "$C_0"; fi
printf '  %s================================================%s\n' "$C_V" "$C_0"
info "Adresse      : $URL"
info "Dossier      : $APP"
if [ "$MODE" = "production" ]; then
    printf '  %sRéseau local : %s%s\n' "$C_V" "${ADRESSES_LAN[*]}" "$C_0"
    for l in "${IDENTIFIANTS[@]}"; do [[ "$l" == Administrateur* ]] && printf '  %sConnexion    : %s%s\n' "$C_J" "${l#*: }" "$C_0"; done
    [ ${#IDENTIFIANTS[@]} -gt 0 ] && info "               (un nouveau mot de passe sera demandé à la première connexion)"
    [ ${#IDENTIFIANTS[@]} -gt 0 ] && info "Identifiants : /etc/sigaci/identifiants.txt (à mettre en lieu sûr)"
    info "Sauvegardes  : $SAUVEGARDES (chaque nuit à 2 h 30, 30 jours)"
    info "Mettre à jour: sudo sigaci-installer"
else
    info "Compte       : national@sigaci.test / demo2026 (autres comptes : README.md)"
    info "Démarrer     : cd \"$APP\" && php artisan serve"
    if [ -n "$DEMARRER" ]; then exec php artisan serve; fi
fi
