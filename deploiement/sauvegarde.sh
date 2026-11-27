#!/usr/bin/env bash
# Sauvegarde quotidienne de SIGACI : base MySQL/MariaDB + fichier .env.
# Conserve 30 jours en local et copie, si configuré, vers un second serveur (rsync).
#
# Installation (en root) :
#   cp deploiement/sauvegarde.sh /usr/local/bin/sigaci-sauvegarde && chmod 700 /usr/local/bin/sigaci-sauvegarde
#   crontab -e   →   30 2 * * * /usr/local/bin/sigaci-sauvegarde >> /var/log/sigaci-sauvegarde.log 2>&1
set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/sigaci}"
DEST="${DEST:-/var/backups/sigaci}"
KEEP_DAYS="${KEEP_DAYS:-30}"
REMOTE="${REMOTE:-}"   # ex. sauvegarde@10.0.0.20:/srv/sauvegardes/sigaci  (vide = pas de copie distante)

env_value() { grep -E "^$1=" "$APP_DIR/.env" | tail -1 | cut -d= -f2- | sed -e 's/^"//' -e 's/"$//'; }

DB_HOST=$(env_value DB_HOST); DB_PORT=$(env_value DB_PORT); DB_DATABASE=$(env_value DB_DATABASE)
DB_USERNAME=$(env_value DB_USERNAME); DB_PASSWORD=$(env_value DB_PASSWORD)

STAMP=$(date +%Y-%m-%d_%H%M)
mkdir -p "$DEST" && chmod 700 "$DEST"
OUT="$DEST/sigaci_${STAMP}.sql.gz"

# Le mot de passe passe par un fichier temporaire, jamais en ligne de commande.
CNF=$(mktemp); trap 'rm -f "$CNF"' EXIT
printf '[client]\nhost=%s\nport=%s\nuser=%s\npassword=%s\n' "${DB_HOST:-127.0.0.1}" "${DB_PORT:-3306}" "$DB_USERNAME" "$DB_PASSWORD" > "$CNF"

mysqldump --defaults-extra-file="$CNF" --single-transaction --routines --triggers "$DB_DATABASE" | gzip -9 > "$OUT"
cp "$APP_DIR/.env" "$DEST/env_${STAMP}" && chmod 600 "$DEST/env_${STAMP}"

# Vérification minimale : l'archive est lisible et contient la table des équipements.
gunzip -c "$OUT" | grep -q "CREATE TABLE \`equipments\`" || { echo "ÉCHEC : sauvegarde incomplète $OUT"; exit 1; }

find "$DEST" -name 'sigaci_*.sql.gz' -mtime +"$KEEP_DAYS" -delete
find "$DEST" -name 'env_*' -mtime +"$KEEP_DAYS" -delete

if [ -n "$REMOTE" ]; then
  rsync -a "$DEST/" "$REMOTE/"
fi
echo "$(date '+%F %T') sauvegarde OK : $OUT ($(du -h "$OUT" | cut -f1))"
