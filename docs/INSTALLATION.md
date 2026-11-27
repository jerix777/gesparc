# Installer SIGACI sur un serveur

Ce guide installe SIGACI sur un serveur Linux Ubuntu 24.04, avec MariaDB, Nginx et HTTPS.
Comptez environ une heure. Les commandes sont à lancer en `root` (ou avec `sudo`).

## 1. Serveur conseillé

| Usage | Processeur | Mémoire | Disque |
| --- | --- | --- | --- |
| Une juridiction (pilote) | 2 cœurs | 2 Go | 20 Go |
| Jusqu'à 50 institutions | 4 cœurs | 8 Go | 100 Go |

Le serveur doit avoir un nom de domaine (ex. `sigaci.justice.gouv.ci`) pointant vers son adresse IP.
Une adresse sur le réseau interne de l'administration convient aussi ; il faut alors un certificat interne.

## 2. Logiciels

```bash
apt update && apt upgrade -y
apt install -y nginx mariadb-server unzip git certbot python3-certbot-nginx \
  php8.3-fpm php8.3-cli php8.3-mysql php8.3-mbstring php8.3-xml php8.3-intl php8.3-zip php8.3-curl
curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
mysql_secure_installation
```

## 3. Base de données

```bash
mysql -e "CREATE DATABASE sigaci CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'sigaci'@'localhost' IDENTIFIED BY 'UN-MOT-DE-PASSE-LONG';
GRANT ALL PRIVILEGES ON sigaci.* TO 'sigaci'@'localhost'; FLUSH PRIVILEGES;"
```

## 4. Application

```bash
git clone https://github.com/jerix777/gesparc.git /var/www/sigaci
cd /var/www/sigaci
composer install --no-dev --optimize-autoloader
cp .env.example .env
php artisan key:generate
```

Dans `.env`, renseignez :

```ini
APP_ENV=production
APP_DEBUG=false
APP_URL=https://sigaci.justice.gouv.ci
DB_PASSWORD=UN-MOT-DE-PASSE-LONG
SESSION_SECURE_COOKIE=true
# Adresse(s) d'où l'application TPI appelle l'API ; * si elle est ouverte comme fichier local
CORS_ALLOWED_ORIGINS=*
```

Pour les courriels automatiques, indiquez le serveur de messagerie de l'administration (SMTP) :

```ini
MAIL_MAILER=smtp
MAIL_HOST=smtp.justice.gouv.ci
MAIL_PORT=587
MAIL_USERNAME=sigaci@justice.gouv.ci
MAIL_PASSWORD=...
MAIL_FROM_ADDRESS="sigaci@justice.gouv.ci"
```

Sans serveur SMTP, laissez `MAIL_MAILER=log` (les courriels sont écrits dans `storage/logs`) ou mettez
`SIGACI_NOTIFICATIONS=false`. Un envoi qui échoue ne bloque jamais l'application.

Puis :

```bash
php artisan migrate --force --seed          # crée les tables et les types d'équipement
php artisan sigaci:create-admin admin@justice.gouv.ci --name="Direction informatique"
php artisan config:cache && php artisan route:cache && php artisan view:cache
chown -R www-data:www-data storage bootstrap/cache
```

## 5. Nginx et HTTPS

```bash
cp deploiement/nginx-sigaci.conf /etc/nginx/sites-available/sigaci
# remplacez sigaci.exemple.ci par votre domaine dans le fichier, puis :
ln -s /etc/nginx/sites-available/sigaci /etc/nginx/sites-enabled/
certbot --nginx -d sigaci.justice.gouv.ci
nginx -t && systemctl reload nginx
```

## 6. Tâches planifiées et sauvegardes

```bash
cp deploiement/sauvegarde.sh /usr/local/bin/sigaci-sauvegarde && chmod 700 /usr/local/bin/sigaci-sauvegarde
crontab -e
```

Ajoutez :

```cron
* * * * *  cd /var/www/sigaci && php artisan schedule:run >> /dev/null 2>&1
30 2 * * * REMOTE=sauvegarde@10.0.0.20:/srv/sauvegardes/sigaci /usr/local/bin/sigaci-sauvegarde >> /var/log/sigaci-sauvegarde.log 2>&1
```

- Une copie de la base et du fichier `.env` est faite chaque nuit à 2 h 30 dans `/var/backups/sigaci`, gardée 30 jours.
- `REMOTE` copie aussi les sauvegardes sur un second serveur (recommandé ; laissez vide sinon).
- **Testez une restauration chaque trimestre** sur un serveur de test : `deploiement/restauration.sh <fichier>`.

## 7. Premiers pas

1. L'administrateur national se connecte et crée les institutions (`Administration → Institutions`), avec un code court (ex. `TPI-BKE`) : il sert de préfixe aux numéros d'inventaire.
2. Il crée un administrateur pour chaque institution (`Administration → Utilisateurs`).
3. Chaque administrateur crée les services et les comptes de son institution.
4. L'informaticien saisit ou importe l'inventaire (voir le README pour l'import depuis l'application TPI).

## 8. Raccorder l'application TPI

Deux possibilités :

- **Fichier prêt à l'emploi** : utilisez `tpi.sigaci.connecte.html`, copie de l'application TPI qui contient déjà le connecteur.
  Pour éviter que chaque agent saisisse l'adresse du serveur, remplacez dans ce fichier `window.SIGACI_URL || ""` par `window.SIGACI_URL || "https://sigaci.justice.gouv.ci"`.
- **Ajout manuel** : juste avant `</body>` de l'application TPI, ajoutez
  ```html
  <script>window.SIGACI_URL = 'https://sigaci.justice.gouv.ci';</script>
  <script src="https://sigaci.justice.gouv.ci/connecteur/sigaci-tpi.js"></script>
  ```

Chaque agent clique ensuite sur « Demande de matériels informatiques » et se connecte une fois avec son compte SIGACI.
Ses demandes partent alors sur le serveur. Sans connexion, l'application TPI fonctionne comme avant.

## 9. Mettre à jour

```bash
cd /var/www/sigaci
/usr/local/bin/sigaci-sauvegarde
php artisan down
git pull && composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan up
```
