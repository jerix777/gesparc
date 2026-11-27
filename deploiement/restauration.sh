#!/usr/bin/env bash
# Restaure une sauvegarde GesParc dans la base configurée dans .env.
# Usage : deploiement/restauration.sh /var/backups/gesparc/gesparc_2026-11-25_0230.sql.gz
# ATTENTION : remplace toutes les données actuelles. Mettre l'application en maintenance avant.
set -euo pipefail
FILE="${1:?Indiquez le fichier .sql.gz à restaurer}"
APP_DIR="${APP_DIR:-/var/www/gesparc}"
env_value() { grep -E "^$1=" "$APP_DIR/.env" | tail -1 | cut -d= -f2- | sed -e 's/^"//' -e 's/"$//'; }
CNF=$(mktemp); trap 'rm -f "$CNF"' EXIT
printf '[client]\nhost=%s\nport=%s\nuser=%s\npassword=%s\n' "$(env_value DB_HOST)" "$(env_value DB_PORT)" "$(env_value DB_USERNAME)" "$(env_value DB_PASSWORD)" > "$CNF"
read -r -p "Remplacer la base $(env_value DB_DATABASE) par $FILE ? Tapez OUI : " ok
[ "$ok" = "OUI" ] || { echo "Annulé."; exit 1; }
php "$APP_DIR/artisan" down
gunzip -c "$FILE" | mysql --defaults-extra-file="$CNF" "$(env_value DB_DATABASE)"
php "$APP_DIR/artisan" migrate --force
php "$APP_DIR/artisan" up
echo "Restauration terminée."
