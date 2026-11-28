<?php

/**
 * Utilitaire commun aux installateurs SIGACI (PowerShell, CMD, Bash).
 * Toute la logique qui doit se comporter pareil sur Windows et Linux est ici,
 * pour éviter les différences de guillemets entre les trois interpréteurs.
 *
 * Les paramètres sensibles (mots de passe) arrivent par variables d'environnement, jamais par la ligne de commande.
 *
 *   php outils.php verifier <sqlite|mysql>      Version de PHP et extensions
 *   php outils.php composer <dossier>           Télécharge composer.phar (empreinte SHA-256 vérifiée)
 *   php outils.php preparer-env <dossier-app>   Crée ou met à jour .env (variables SIGACI_*)
 *   php outils.php creer-bd <dossier-app>       Crée la base MySQL/MariaDB (et l'utilisateur si accès root)
 *   php outils.php bd-initialisee <dossier-app> Code 0 si les tables existent déjà, 1 sinon
 *   php outils.php lire-env <dossier-app> <CLE> Affiche une valeur de .env
 *   php outils.php completer-env <dossier-app>  Ajoute à .env les clés nouvelles de .env.example (mise à jour)
 *   php outils.php creer-admin <dossier-app>    Crée l'administrateur national s'il n'en existe aucun
 *                                               (SIGACI_ADMIN_EMAIL, SIGACI_ADMIN_MDP) ; code 3 = déjà présent
 *   php outils.php configurer-php <php.ini> <laragon|autonome>
 *                                               Active les extensions requises (et règle PHP pour la pile autonome)
 *   php outils.php restaurer-bd <dossier-app> <fichier>
 *                                               Remplace la base par une sauvegarde (.zip Windows, .sql.gz, .sql)
 */

const PHP_MIN = 80300;

$cmd = $argv[1] ?? '';
$arg = $argv[2] ?? '';

try {
    exit(match ($cmd) {
        'verifier' => verifier($arg ?: 'sqlite'),
        'composer' => telechargerComposer($arg ?: '.'),
        'preparer-env' => preparerEnv($arg),
        'creer-bd' => creerBd($arg),
        'bd-initialisee' => bdInitialisee($arg),
        'lire-env' => lireEnvCli($arg, $argv[3] ?? ''),
        'completer-env' => completerEnv($arg),
        'creer-admin' => creerAdmin($arg),
        'configurer-php' => configurerPhp($arg, $argv[3] ?? 'laragon'),
        'restaurer-bd' => restaurerBd($arg, $argv[3] ?? ''),
        default => usage(),
    });
} catch (Throwable $e) {
    erreur($e->getMessage());
    exit(1);
}

function usage(): int
{
    fwrite(STDERR, "Usage : php outils.php verifier|composer|preparer-env|creer-bd|bd-initialisee|lire-env|restaurer-bd ...\n");

    return 2;
}

function ok(string $m): void
{
    echo "  [OK] $m\n";
}

function dire(string $m): void
{
    echo "  $m\n";
}

function attention(string $m): void
{
    echo "  [ATTENTION] $m\n";
}

function erreur(string $m): void
{
    fwrite(STDERR, "  [ERREUR] $m\n");
}

function envVal(string $nom, string $defaut = ''): string
{
    $v = getenv($nom);

    return $v === false || $v === '' ? $defaut : $v;
}

/* ---------------------------------------------------------------- vérification */

function verifier(string $base): int
{
    $probleme = false;
    if (PHP_VERSION_ID < PHP_MIN) {
        erreur('PHP '.PHP_VERSION.' détecté : SIGACI demande PHP 8.3 ou plus récent.');
        $probleme = true;
    } else {
        ok('PHP '.PHP_VERSION);
    }

    $requises = ['ctype', 'filter', 'hash', 'mbstring', 'openssl', 'session', 'tokenizer', 'fileinfo', 'dom', 'xml', 'pdo',
        $base === 'mysql' ? 'pdo_mysql' : 'pdo_sqlite'];
    $conseillees = ['intl', 'zip', 'curl'];

    $manquantes = array_values(array_filter($requises, fn ($x) => ! extension_loaded($x)));
    $absentesConseil = array_values(array_filter($conseillees, fn ($x) => ! extension_loaded($x)));

    if ($manquantes) {
        erreur('Extensions PHP manquantes : '.implode(', ', $manquantes));
        $probleme = true;
    } else {
        ok('Extensions requises présentes');
    }
    if ($absentesConseil) {
        attention('Extensions conseillées absentes : '.implode(', ', $absentesConseil));
    }

    if ($manquantes || $absentesConseil) {
        $ini = php_ini_loaded_file();
        if (PHP_OS_FAMILY === 'Windows') {
            dire($ini
                ? "Activez-les dans $ini (retirez le « ; » devant extension=...)."
                : 'Aucun php.ini chargé : copiez php.ini-development en php.ini dans le dossier de PHP, puis activez les extensions.');
        } else {
            $v = PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION;
            $paquets = array_map(fn ($x) => 'php'.$v.'-'.str_replace(['pdo_', 'dom'], ['', 'xml'], $x), array_merge($manquantes, $absentesConseil));
            dire('Debian/Ubuntu : sudo apt install '.implode(' ', array_unique($paquets)));
        }
    }

    return $probleme ? 1 : 0;
}

/* ---------------------------------------------------------------- composer */

function telechargerComposer(string $dossier): int
{
    $cible = rtrim($dossier, '/\\').DIRECTORY_SEPARATOR.'composer.phar';
    $url = 'https://getcomposer.org/download/latest-stable/composer.phar';
    dire('Téléchargement de Composer...');
    $phar = @file_get_contents($url);
    $somme = @file_get_contents($url.'.sha256sum');
    if ($phar === false || $somme === false) {
        throw new RuntimeException("Téléchargement de Composer impossible ($url). Vérifiez la connexion Internet ou le proxy.");
    }
    $attendu = strtolower(trim(explode(' ', trim($somme))[0]));
    if (! hash_equals($attendu, hash('sha256', $phar))) {
        throw new RuntimeException('Empreinte SHA-256 de composer.phar incorrecte : fichier rejeté.');
    }
    file_put_contents($cible, $phar);
    ok("Composer installé : $cible");

    return 0;
}

/* ---------------------------------------------------------------- .env */

/** Remplace (ou ajoute) une clé ; $valeur null = commente la ligne. */
function definir(string $contenu, string $cle, ?string $valeur): string
{
    $motif = '/^[#\s]*'.preg_quote($cle, '/').'=.*$/m';
    if ($valeur === null) {
        return preg_replace('/^'.preg_quote($cle, '/').'=/m', '# '.$cle.'=', $contenu);
    }
    if ($valeur !== '' && preg_match('/[\s#"\'$\\\\]/', $valeur)) {
        // Guillemets simples : pris littéralement par phpdotenv (pas d'interpolation de $).
        $valeur = ! str_contains($valeur, "'")
            ? "'".$valeur."'"
            : '"'.str_replace(['\\', '"'], ['\\\\', '\\"'], $valeur).'"';
    }
    $ligne = $cle.'='.$valeur;
    if (preg_match($motif, $contenu)) {
        return preg_replace($motif, str_replace(['\\', '$'], ['\\\\', '\\$'], $ligne), $contenu, 1);
    }

    return rtrim($contenu)."\n".$ligne."\n";
}

function lireEnv(string $app): array
{
    $f = $app.'/.env';
    $vals = [];
    if (! is_file($f)) {
        return $vals;
    }
    foreach (file($f, FILE_IGNORE_NEW_LINES) as $l) {
        if (preg_match('/^\s*([A-Z0-9_]+)\s*=\s*(.*)$/', $l, $m)) {
            $v = trim($m[2]);
            if (strlen($v) >= 2 && $v[0] === '"' && str_ends_with($v, '"')) {
                $v = str_replace(['\\"', '\\\\'], ['"', '\\'], substr($v, 1, -1));
            } elseif (strlen($v) >= 2 && $v[0] === "'" && str_ends_with($v, "'")) {
                $v = substr($v, 1, -1);
            }
            $vals[$m[1]] = $v;
        }
    }

    return $vals;
}

function lireEnvCli(string $app, string $cle): int
{
    echo lireEnv($app)[$cle] ?? '';

    return 0;
}

function preparerEnv(string $app): int
{
    $app = rtrim($app, '/\\');
    $env = $app.'/.env';
    $exemple = $app.'/.env.example';
    if (! is_file($exemple)) {
        throw new RuntimeException("$exemple introuvable : le dossier n'est pas une installation SIGACI.");
    }

    if (is_file($env)) {
        $copie = $env.'.sauvegarde-'.date('Ymd-His');
        copy($env, $copie);
        dire('.env existant conservé (copie : '.basename($copie).')');
        $c = file_get_contents($env);
    } else {
        $c = file_get_contents($exemple);
        dire('.env créé à partir de .env.example');
    }

    $mode = envVal('SIGACI_MODE', 'demo');
    $port = envVal('SIGACI_PORT', '8000');
    $url = envVal('SIGACI_URL', 'http://localhost:'.$port);

    $c = definir($c, 'APP_URL', $url);

    if ($mode === 'production') {
        $c = definir($c, 'APP_ENV', 'production');
        $c = definir($c, 'APP_DEBUG', 'false');
        $c = definir($c, 'LOG_LEVEL', 'warning');
        $c = definir($c, 'DB_CONNECTION', 'mysql');
        $c = definir($c, 'DB_HOST', envVal('SIGACI_DB_HOTE', '127.0.0.1'));
        $c = definir($c, 'DB_PORT', envVal('SIGACI_DB_PORT', '3306'));
        $c = definir($c, 'DB_DATABASE', envVal('SIGACI_DB_NOM', 'sigaci'));
        $c = definir($c, 'DB_USERNAME', envVal('SIGACI_DB_UTILISATEUR', 'sigaci'));
        $c = definir($c, 'DB_PASSWORD', envVal('SIGACI_DB_MDP'));
        if (str_starts_with($url, 'https://')) {
            $c = definir($c, 'SESSION_SECURE_COOKIE', 'true');
        }
    } else {
        $c = definir($c, 'APP_ENV', 'local');
        $c = definir($c, 'APP_DEBUG', 'true');
        $c = definir($c, 'DB_CONNECTION', 'sqlite');
        foreach (['DB_HOST', 'DB_PORT', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD'] as $k) {
            $c = definir($c, $k, null);
        }
        $sqlite = $app.'/database/database.sqlite';
        if (! is_file($sqlite)) {
            touch($sqlite);
            ok('Base SQLite créée : database/database.sqlite');
        }
    }

    if (! preg_match('/^APP_KEY=\S+/m', $c)) {
        $c = definir($c, 'APP_KEY', 'base64:'.base64_encode(random_bytes(32)));
        ok("Clé d'application générée");
    }

    file_put_contents($env, $c);
    if (PHP_OS_FAMILY !== 'Windows') {
        @chmod($env, 0640);
    }
    ok(".env configuré (mode $mode)");

    return 0;
}

/** Mise à jour : ajoute les clés présentes dans .env.example mais absentes de .env, sans rien modifier d'autre. */
function completerEnv(string $app): int
{
    $app = rtrim($app, '/\\');
    $existantes = lireEnv($app);
    $ajouts = [];
    foreach (file($app.'/.env.example', FILE_IGNORE_NEW_LINES) as $l) {
        if (preg_match('/^([A-Z0-9_]+)=/', $l, $m) && ! array_key_exists($m[1], $existantes)
            && ! preg_match('/^#\s*'.$m[1].'=/m', (string) file_get_contents($app.'/.env'))) {
            $ajouts[] = $l;
        }
    }
    if ($ajouts) {
        file_put_contents($app.'/.env', "\n# Ajouté par la mise à jour du ".date('d/m/Y')."\n".implode("\n", $ajouts)."\n", FILE_APPEND);
        ok('Nouveaux réglages ajoutés à .env : '.implode(', ', array_map(fn ($l) => strstr($l, '=', true), $ajouts)));
    } else {
        ok('.env conservé (aucun réglage nouveau)');
    }

    return 0;
}

/* ---------------------------------------------------------------- administrateur */

function creerAdmin(string $app): int
{
    $app = rtrim($app, '/\\');
    $email = envVal('SIGACI_ADMIN_EMAIL');
    $mdp = envVal('SIGACI_ADMIN_MDP');
    if ($email === '' || strlen($mdp) < 8) {
        throw new RuntimeException('SIGACI_ADMIN_EMAIL et SIGACI_ADMIN_MDP (8 caractères minimum) sont requis.');
    }
    require $app.'/vendor/autoload.php';
    $laravel = require $app.'/bootstrap/app.php';
    $console = $laravel->make(Illuminate\Contracts\Console\Kernel::class);
    $console->bootstrap();

    // Idempotent : rien à faire s'il existe déjà un administrateur national (code 3, distinct d'une erreur).
    if (App\Models\User::where('role', App\Enums\Role::AdminNational)->exists()) {
        ok('Administrateur national déjà présent : conservé');

        return 3;
    }

    // Versions récentes : la commande lit SIGACI_ADMIN_MDP. Versions antérieures (y compris sous le nom
    // gesparc:create-admin) : mêmes champs que la commande, écrits directement.
    $commande = null;
    foreach (['sigaci:create-admin', 'gesparc:create-admin'] as $nom) {
        $c = $console->all()[$nom] ?? null;
        if ($c && str_contains($c->getDescription(), 'SIGACI_ADMIN_MDP')) {
            $commande = $nom;
            break;
        }
    }
    if ($commande) {
        $code = $console->call($commande, ['email' => $email]);
        if ($code !== 0) {
            throw new RuntimeException(trim($console->output()) ?: 'Création de l\'administrateur impossible.');
        }
    } else {
        App\Models\User::updateOrCreate(['email' => $email], [
            'name' => 'Administrateur national',
            'password' => $mdp,
            'role' => App\Enums\Role::AdminNational,
            'institution_id' => null,
            'active' => true,
        ]);
    }
    // Mot de passe généré, noté dans un fichier : l'administrateur choisit le sien à la première connexion.
    if (Illuminate\Support\Facades\Schema::hasColumn('users', 'must_change_password')) {
        App\Models\User::where('email', $email)->update(['must_change_password' => true]);
    }
    ok("Administrateur national créé : $email");

    return 0;
}

/* ---------------------------------------------------------------- php.ini */

function configurerPhp(string $ini, string $profil): int
{
    $dir = dirname($ini);
    if (! is_file($ini)) {
        $modele = is_file("$dir/php.ini-production") ? "$dir/php.ini-production" : "$dir/php.ini-development";
        if (! is_file($modele)) {
            throw new RuntimeException("Ni php.ini ni modèle dans $dir.");
        }
        copy($modele, $ini);
        dire("php.ini créé à partir de ".basename($modele));
    } else {
        $copie = $ini.'.avant-sigaci';
        if (! is_file($copie) && ! is_file($ini.'.avant-gesparc')) { // copie de l'original, faite une seule fois
            copy($ini, $copie);
        }
    }
    $c = file_get_contents($ini);
    $avant = $c;

    $extensions = ['curl', 'fileinfo', 'intl', 'mbstring', 'openssl', 'pdo_mysql', 'pdo_sqlite', 'sodium', 'zip'];
    foreach ($extensions as $x) {
        if (! is_file("$dir/ext/php_$x.dll") && PHP_OS_FAMILY === 'Windows') {
            continue; // extension compilée en dur ou absente de cette distribution
        }
        if (preg_match('/^\s*extension\s*=\s*"?(php_)?'.$x.'(\.dll)?"?\s*$/mi', $c)) {
            continue;
        }
        $n = 0;
        $c = preg_replace('/^\s*;\s*extension\s*=\s*"?(php_)?'.$x.'(\.dll)?"?\s*$/mi', 'extension='.$x, $c, 1, $n);
        if (! $n) {
            $c = rtrim($c)."\nextension=$x\n";
        }
    }

    // Barres obliques : entre guillemets, php.ini interprète « \ » comme un échappement (« \66 » = octal).
    $reglages = ['extension_dir' => '"'.str_replace('\\', '/', $dir).'/ext"'];
    if ($profil === 'autonome') {
        $reglages += [
            'date.timezone' => 'Africa/Abidjan',
            'memory_limit' => '256M',
            'upload_max_filesize' => '10M',
            'post_max_size' => '12M',
            'max_execution_time' => '120',
            'opcache.enable' => '1',
            'opcache.enable_cli' => '0',
            'opcache.memory_consumption' => '128',
            'expose_php' => 'Off',
        ];
        if (! preg_match('/^\s*zend_extension\s*=\s*"?(php_)?opcache/mi', $c)) {
            $c = preg_replace('/^\s*;\s*zend_extension\s*=\s*"?(php_)?opcache(\.dll)?"?\s*$/mi', 'zend_extension=opcache', $c, 1, $n);
            if (! $n) {
                $c = rtrim($c)."\nzend_extension=opcache\n";
            }
        }
    }
    foreach ($reglages as $k => $v) {
        $motif = '/^\s*;?\s*'.preg_quote($k, '/').'\s*=.*$/m';
        $c = preg_match($motif, $c) ? preg_replace($motif, "$k = $v", $c, 1) : rtrim($c)."\n$k = $v\n";
    }

    if ($c !== $avant) {
        file_put_contents($ini, $c);
        ok("php.ini configuré : $ini");
    } else {
        ok('php.ini déjà configuré');
    }

    return 0;
}

/* ---------------------------------------------------------------- base de données */

function pdoMysql(string $hote, string $port, string $user, string $mdp, ?string $base = null): PDO
{
    $dsn = $hote === 'localhost' && PHP_OS_FAMILY !== 'Windows'
        ? 'mysql:unix_socket='.(envVal('SIGACI_DB_SOCKET') ?: trouverSocket())
        : "mysql:host=$hote;port=$port";
    if ($base) {
        $dsn .= ";dbname=$base";
    }

    return new PDO($dsn, $user, $mdp, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]);
}

function trouverSocket(): string
{
    foreach (['/run/mysqld/mysqld.sock', '/var/run/mysqld/mysqld.sock', '/var/lib/mysql/mysql.sock', '/tmp/mysql.sock'] as $s) {
        if (file_exists($s)) {
            return $s;
        }
    }

    return '/run/mysqld/mysqld.sock';
}

function creerBd(string $app): int
{
    $env = lireEnv(rtrim($app, '/\\'));
    $hote = $env['DB_HOST'] ?? '127.0.0.1';
    $port = $env['DB_PORT'] ?? '3306';
    $base = $env['DB_DATABASE'] ?? 'sigaci';
    $user = $env['DB_USERNAME'] ?? 'sigaci';
    $mdp = $env['DB_PASSWORD'] ?? '';
    if (! preg_match('/^[A-Za-z0-9_]+$/', $base) || ! preg_match('/^[A-Za-z0-9_.-]+$/', $user)) {
        throw new RuntimeException('Nom de base ou d\'utilisateur invalide (lettres, chiffres, _ uniquement).');
    }
    if (! extension_loaded('pdo_mysql')) {
        throw new RuntimeException('Extension pdo_mysql absente.');
    }

    $rootUser = envVal('SIGACI_DB_ROOT_UTILISATEUR');
    if ($rootUser !== '') {
        // Accès administrateur : crée la base, l'utilisateur et ses droits.
        $pdo = pdoMysql($hote, $port, $rootUser, envVal('SIGACI_DB_ROOT_MDP'));
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `$base` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $q = $pdo->quote($mdp);
        $origine = in_array($hote, ['127.0.0.1', 'localhost'], true) ? ['localhost', '127.0.0.1'] : ['%'];
        foreach ($origine as $o) {
            $pdo->exec("CREATE USER IF NOT EXISTS '$user'@'$o' IDENTIFIED BY $q");
            $pdo->exec("ALTER USER '$user'@'$o' IDENTIFIED BY $q");
            $pdo->exec("GRANT ALL PRIVILEGES ON `$base`.* TO '$user'@'$o'");
        }
        $pdo->exec('FLUSH PRIVILEGES');
        ok("Base « $base » et utilisateur « $user » prêts");

        return 0;
    }

    // Sans accès root : on tente avec le compte de l'application.
    try {
        pdoMysql($hote, $port, $user, $mdp, $base);
        ok("Connexion à la base « $base » réussie");

        return 0;
    } catch (PDOException $e) {
        if (! str_contains($e->getMessage(), 'Unknown database') && ! str_contains($e->getMessage(), '1049')) {
            throw new RuntimeException("Connexion MySQL impossible ($user@$hote:$port) : ".$e->getMessage());
        }
    }
    $pdo = pdoMysql($hote, $port, $user, $mdp);
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$base` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    ok("Base « $base » créée");

    return 0;
}

function bdInitialisee(string $app): int
{
    $app = rtrim($app, '/\\');
    $env = lireEnv($app);
    try {
        if (($env['DB_CONNECTION'] ?? 'sqlite') === 'sqlite') {
            $f = $env['DB_DATABASE'] ?? '';
            $f = $f !== '' ? $f : $app.'/database/database.sqlite';
            if (! is_file($f) || filesize($f) === 0) {
                return 1;
            }
            $pdo = new PDO('sqlite:'.$f);
        } else {
            $pdo = pdoMysql($env['DB_HOST'] ?? '127.0.0.1', $env['DB_PORT'] ?? '3306', $env['DB_USERNAME'] ?? '', $env['DB_PASSWORD'] ?? '', $env['DB_DATABASE'] ?? '');
        }
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $n = (int) $pdo->query('SELECT COUNT(*) FROM migrations')->fetchColumn();

        return $n > 0 ? 0 : 1;
    } catch (Throwable) {
        return 1;
    }
}

/**
 * Remplace le contenu de la base par une sauvegarde : .zip de l'installateur Windows (sigaci_*.zip),
 * .sql.gz de sigaci-sauvegarde (Linux) ou .sql de mysqldump. Le fichier est vérifié avant de toucher à la base.
 */
function restaurerBd(string $app, string $fichier): int
{
    $app = rtrim($app, '/\\');
    $env = lireEnv($app);
    if (($env['DB_CONNECTION'] ?? 'sqlite') !== 'mysql' && ($env['DB_CONNECTION'] ?? '') !== 'mariadb') {
        erreur('La restauration ne concerne que les bases MySQL/MariaDB (installation de production).');

        return 1;
    }
    if (! is_file($fichier) || ! is_readable($fichier)) {
        erreur("Sauvegarde introuvable : $fichier");

        return 1;
    }

    $temp = null;
    try {
        $sql = fichierSql($fichier, $temp);
        // Vérification : c'est bien une sauvegarde SIGACI (tables des migrations et des équipements).
        $tables = [];
        $h = ouvrirSql($sql);
        while (($l = fgets($h)) !== false) {
            if (preg_match('/^CREATE TABLE `(migrations|equipments)`/', $l, $m)) {
                $tables[$m[1]] = true;
            }
        }
        fclose($h);
        if (count($tables) < 2) {
            erreur("$fichier n'est pas une sauvegarde de SIGACI (tables migrations et equipments absentes) : base inchangée.");

            return 1;
        }

        $pdo = pdoMysql($env['DB_HOST'] ?? '127.0.0.1', $env['DB_PORT'] ?? '3306', $env['DB_USERNAME'] ?? '', $env['DB_PASSWORD'] ?? '', $env['DB_DATABASE'] ?? '');
        $pdo->exec('SET NAMES utf8mb4');
        $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
        // Tables créées par une version plus récente que la sauvegarde : supprimées, les migrations les recréent.
        foreach ($pdo->query('SHOW FULL TABLES')->fetchAll(PDO::FETCH_NUM) as [$nom, $type]) {
            $pdo->exec(($type === 'VIEW' ? 'DROP VIEW' : 'DROP TABLE').' IF EXISTS `'.str_replace('`', '``', $nom).'`');
        }

        $h = ouvrirSql($sql);
        $delim = ';';
        $tampon = '';
        $n = 0;
        while (($l = fgets($h)) !== false) {
            $t = rtrim($l, "\r\n");
            if ($tampon === '') {
                // Commentaires, lignes vides et ligne « sandbox » propre au client de MariaDB 11.
                if (trim($t) === '' || str_starts_with($t, '--') || str_starts_with($t, '/*M!999999')) {
                    continue;
                }
                if (preg_match('/^DELIMITER\s+(\S+)/i', $t, $m)) {
                    $delim = $m[1];

                    continue;
                }
            }
            $tampon .= $l;
            if (str_ends_with(rtrim($t), $delim)) {
                $instr = substr(rtrim($tampon), 0, -strlen($delim));
                if (trim($instr) !== '') {
                    $pdo->exec($instr);
                    $n++;
                }
                $tampon = '';
            }
        }
        fclose($h);
        if (trim($tampon) !== '') {
            $pdo->exec($tampon);
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
        $nb = (int) $pdo->query('SELECT COUNT(*) FROM equipments')->fetchColumn();
        ok("Sauvegarde restaurée : $n instructions, $nb équipement(s)");

        return 0;
    } finally {
        if ($temp && is_file($temp)) {
            unlink($temp);
        }
    }
}

/** Chemin du SQL à lire : extrait d'un .zip si besoin (dans $temp, à supprimer ensuite). */
function fichierSql(string $fichier, ?string &$temp): string
{
    $debut = (string) file_get_contents($fichier, false, null, 0, 4);
    if (! str_starts_with($debut, "PK\x03\x04")) {
        return $fichier;
    }
    if (! class_exists(ZipArchive::class)) {
        throw new RuntimeException("Extension PHP zip absente : décompressez la sauvegarde et indiquez le fichier .sql.");
    }
    $zip = new ZipArchive;
    if ($zip->open($fichier) !== true) {
        throw new RuntimeException("Archive illisible : $fichier");
    }
    try {
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $nom = (string) $zip->getNameIndex($i);
            if (preg_match('/\.sql(\.gz)?$/i', $nom)) {
                $temp = tempnam(sys_get_temp_dir(), 'sigaci-restauration-');
                if (file_put_contents($temp, $zip->getStream($nom)) === false) {
                    throw new RuntimeException("Extraction impossible de $nom");
                }

                return $temp;
            }
        }
    } finally {
        $zip->close();
    }
    throw new RuntimeException("Aucun fichier .sql dans l'archive $fichier");
}

/** @return resource flux texte, décompressé à la volée pour un .gz */
function ouvrirSql(string $fichier)
{
    $gz = (string) file_get_contents($fichier, false, null, 0, 2) === "\x1f\x8b";
    $h = fopen(($gz ? 'compress.zlib://' : '').$fichier, 'rb');
    if (! $h) {
        throw new RuntimeException("Lecture impossible : $fichier");
    }

    return $h;
}
