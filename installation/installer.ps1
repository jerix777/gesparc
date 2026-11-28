<#
.SYNOPSIS
    Installe ou met à jour SIGACI sous Windows, automatiquement et sans question.

.DESCRIPTION
    Première exécution : installe tout ce qui manque puis SIGACI, de bout en bout.
      - Pile Laragon (si Laragon est installé) : PHP et Apache de Laragon, son MySQL ; extensions PHP activées,
        site Apache dédié.
      - Pile autonome (sinon) : PHP 8.3, MariaDB (service Windows) et le serveur web Caddy, téléchargés depuis
        leurs sites officiels (empreintes SHA-256 vérifiées) dans C:\SIGACI.
      - git et Composer si absents ; base de données, utilisateur, tables, administrateur national ;
        démarrage automatique avec Windows (tâche planifiée « SIGACI ») ; tâches planifiées de Laravel ;
        sauvegarde chaque nuit ; ouverture du pare-feu sur le réseau local.
      Les mots de passe sont générés et notés dans C:\SIGACI\identifiants.txt (lisible par les administrateurs).

    Exécutions suivantes : met seulement SIGACI à jour (sauvegarde, maintenance, code, dépendances,
    nouveaux réglages, migrations, caches, redémarrage). Le .env et les données sont conservés.

    Les droits administrateur sont demandés automatiquement (une confirmation Windows).

.EXAMPLE
    powershell -ExecutionPolicy Bypass -File installer.ps1
    Installe SIGACI (ou le met à jour s'il est déjà installé).
.EXAMPLE
    .\installer.ps1 -Domaine sigaci.tpi-bouake.ci -AdminEmail informatique@tpi-bouake.ci
.EXAMPLE
    .\installer.ps1 -Reprise D:\GESTION_DU_PERSONNEL.html -Institution TPI-BKE
    Installe (ou met à jour) puis reprend le personnel, le matériel et les toners de l'application d'origine.
.EXAMPLE
    .\installer.ps1 -Restaurer D:\sigaci_2026-11-27_0230.zip
    Installe puis remplace la base par la sauvegarde d'un autre serveur SIGACI (.zip Windows ou .sql.gz Linux).
.EXAMPLE
    .\installer.ps1 -Mode demo
    Démonstration (SQLite, comptes de test) sans serveur web ni service.
#>
[CmdletBinding()]
param(
    [ValidateSet('production', 'demo')] [string] $Mode = 'production',
    [ValidateSet('auto', 'laragon', 'autonome')] [string] $Pile = 'auto',
    [string] $Base = 'C:\SIGACI',
    [string] $Dossier,
    [string] $Branche = 'main',
    [string] $Depot = 'https://github.com/jerix777/gesparc.git',
    [string] $Domaine,
    [int]    $PortWeb = 0,
    [string] $AdminEmail = 'admin@sigaci.local',
    [switch] $Https,
    [switch] $Demarrer,
    [switch] $ModeTest,
    [string[]] $Reprise,
    [string] $Institution = 'TPI-BKE',
    [string] $Restaurer,
    [string] $Journal
)

$ErrorActionPreference = 'Stop'
$ProgressPreference = 'SilentlyContinue'   # Invoke-WebRequest est 10 fois plus lent avec la barre de progression
try { [Console]::OutputEncoding = [Text.Encoding]::UTF8 } catch { }
[Net.ServicePointManager]::SecurityProtocol = [Net.ServicePointManager]::SecurityProtocol -bor [Net.SecurityProtocolType]::Tls12

$Production = ($Mode -eq 'production')
if (-not $Production -and -not $PSBoundParameters.ContainsKey('Base')) { $Base = Join-Path $env:USERPROFILE 'SIGACI-demo' }
# Installation faite sous l'ancien nom (GesParc) : elle est reprise et mise à jour à son emplacement.
if (-not $PSBoundParameters.ContainsKey('Base')) {
    $ancienne = if ($Production) { 'C:\GesParc' } else { Join-Path $env:USERPROFILE 'GesParc-demo' }
    if ((Test-Path (Join-Path $ancienne 'installation.json')) -and -not (Test-Path (Join-Path $Base 'installation.json'))) { $Base = $ancienne }
}
$Base = [IO.Path]::GetFullPath($Base)
# Variables de l'ancien nom encore acceptées.
foreach ($v in 'ADMIN_MDP', 'DB_ROOT_MDP') {
    $ancienneVal = [Environment]::GetEnvironmentVariable("GESPARC_$v")
    if ($ancienneVal -and -not [Environment]::GetEnvironmentVariable("SIGACI_$v")) { [Environment]::SetEnvironmentVariable("SIGACI_$v", $ancienneVal) }
}
$App = if ($Dossier) { [IO.Path]::GetFullPath($Dossier) } else { Join-Path $Base 'app' }
$FichierEtat = Join-Path $Base 'installation.json'
$Outils = Join-Path $PSScriptRoot 'outils.php'
$Superviseur = Join-Path $PSScriptRoot 'superviseur.ps1'

# ------------------------------------------------------------------ affichage

$script:NumEtape = 0
function Etape([string] $titre) {
    $script:NumEtape++
    Write-Host ''
    Write-Host ("[{0}] {1}" -f $script:NumEtape, $titre) -ForegroundColor Cyan
}
function Ok([string] $m)        { Write-Host "  [OK] $m" -ForegroundColor Green }
function Info([string] $m)      { Write-Host "  $m" }
function Attention([string] $m) { Write-Host "  [ATTENTION] $m" -ForegroundColor Yellow }
function Echec([string] $m) {
    Write-Host ''
    Write-Host "  [ERREUR] $m" -ForegroundColor Red
    if ($script:Maintenance) { Write-Host "  SIGACI est resté en maintenance. Après correction, relancez l'installateur." -ForegroundColor Red }
    if ($script:Identifiants -and $script:Identifiants.Count) {
        Write-Host "  Identifiants déjà créés (non recréés à la relance) : $($script:FichierIdentifiants)" -ForegroundColor Yellow
    }
    if ($script:Transcription) { try { Stop-Transcript | Out-Null } catch { } }
    exit 1
}

# Fonction simple (sans param) : les arguments comme --no-dev passent tels quels au programme.
function Exec {
    $prog = $args[0]
    $reste = @($args | Select-Object -Skip 1)
    & $prog @reste
    if ($LASTEXITCODE -ne 0) { Echec "Échec de : $(Split-Path $prog -Leaf) $($reste -join ' ')" }
}

function NouveauMotDePasse([int] $n = 24) {
    $car = 'abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789'.ToCharArray()
    $octets = New-Object byte[] $n
    [Security.Cryptography.RandomNumberGenerator]::Create().GetBytes($octets)
    return -join ($octets | ForEach-Object { $car[$_ % $car.Length] })
}

function PortOccupe([int] $port) {
    $c = New-Object Net.Sockets.TcpClient
    try { $c.Connect('127.0.0.1', $port); return $true } catch { return $false } finally { $c.Close() }
}
function PremierPortLibre([int[]] $candidats) {
    foreach ($p in $candidats) { if (-not (PortOccupe $p)) { return $p } }
    Echec "Aucun port libre parmi : $($candidats -join ', ')"
}
function AttendrePort([int] $port, [int] $secondes, [string] $quoi) {
    $fin = (Get-Date).AddSeconds($secondes)
    while (-not (PortOccupe $port)) {
        if ((Get-Date) -gt $fin) { Echec "$quoi ne répond pas sur le port $port (voir $Base\journaux\superviseur.log)." }
        Start-Sleep -Seconds 2
    }
}

function Telecharger([string] $url, [string] $dest, [string] $sha256) {
    Info "Téléchargement : $(Split-Path $url -Leaf)"
    try { Invoke-WebRequest -Uri $url -OutFile $dest -UseBasicParsing }
    catch { Echec "Téléchargement impossible ($url) : $($_.Exception.Message). Vérifiez l'accès à Internet ou le proxy." }
    if ($sha256) {
        $h = (Get-FileHash $dest -Algorithm SHA256).Hash
        if ($h -ne $sha256.ToUpper()) { Remove-Item $dest -Force; Echec "Empreinte SHA-256 incorrecte pour $(Split-Path $url -Leaf) : fichier rejeté." }
    }
}

# Décompresse une archive dans $dest ; si elle contient un seul dossier racine, c'est son contenu qui est copié.
function Extraire([string] $zip, [string] $dest) {
    $tmp = Join-Path ([IO.Path]::GetTempPath()) ('sigaci-' + [Guid]::NewGuid().ToString('N'))
    Expand-Archive $zip -DestinationPath $tmp -Force
    $racine = @(Get-ChildItem $tmp -Force)
    $source = if ($racine.Count -eq 1 -and $racine[0].PSIsContainer) { $racine[0].FullName } else { $tmp }
    New-Item -ItemType Directory -Force $dest | Out-Null
    Get-ChildItem $source -Force | Move-Item -Destination $dest -Force
    Remove-Item $tmp -Recurse -Force
}

function DerniereVersionGitHub([string] $depot, [string] $motif) {
    $r = Invoke-RestMethod "https://api.github.com/repos/$depot/releases/latest" -Headers @{ 'User-Agent' = 'sigaci-installateur' }
    $a = $r.assets | Where-Object { $_.name -match $motif } | Select-Object -First 1
    if (-not $a) { Echec "Fichier introuvable dans la dernière version de $depot." }
    $sha = if ($a.digest -and $a.digest -like 'sha256:*') { $a.digest.Substring(7) } else { $null }
    return @{ url = $a.browser_download_url; sha256 = $sha; nom = $a.name }
}

function Repond([string] $url, [int] $essais) {
    for ($i = 0; $i -lt $essais; $i++) {
        try { if ((Invoke-WebRequest $url -UseBasicParsing -TimeoutSec 20).StatusCode -eq 200) { return $true } } catch { }
        Start-Sleep -Seconds 3
    }
    return $false
}

# Adresses IPv4 du serveur sur le réseau local (cartes actives ; celles qui ont une passerelle d'abord).
function AdressesReseau {
    $virtuelles = 'vEthernet|WSL|Loopback|VirtualBox|VMware|Hyper-V|Docker|Bluetooth'
    $toutes = @(Get-NetIPAddress -AddressFamily IPv4 -AddressState Preferred -ErrorAction SilentlyContinue |
        Where-Object { $_.IPAddress -notmatch '^(127\.|169\.254\.)' -and $_.InterfaceAlias -notmatch $virtuelles })
    $avecPasserelle = @(Get-NetIPConfiguration -ErrorAction SilentlyContinue | Where-Object { $_.IPv4DefaultGateway } |
        ForEach-Object { $_.InterfaceIndex })
    return @($toutes | Sort-Object { if ($_.InterfaceIndex -in $avecPasserelle) { 0 } else { 1 } } |
        Select-Object -ExpandProperty IPAddress -Unique)
}

# Pare-feu : port web ouvert sur les réseaux d'entreprise et privés ; sur un réseau classé « Public »
# (cas fréquent d'une carte réseau neuve), seulement pour les postes du même sous-réseau.
function OuvrirPareFeu([int] $port, [string[]] $programmes) {
    foreach ($n in 'GesParc (web)', 'SIGACI (web)', 'SIGACI (web, réseau public)') {
        Remove-NetFirewallRule -DisplayName $n -ErrorAction SilentlyContinue
    }
    $desc = 'SIGACI : accès au site depuis les postes du réseau local (créé par installer.ps1)'
    New-NetFirewallRule -DisplayName 'SIGACI (web)' -Description $desc -Direction Inbound -Protocol TCP -LocalPort $port `
        -Action Allow -Profile Domain, Private | Out-Null
    New-NetFirewallRule -DisplayName 'SIGACI (web, réseau public)' -Description $desc -Direction Inbound -Protocol TCP -LocalPort $port `
        -Action Allow -Profile Public -RemoteAddress LocalSubnet | Out-Null
    Ok "Pare-feu ouvert pour le réseau local (port $port)"
    # Si quelqu'un a fermé la question « Autoriser l'accès ? » de Windows pour Apache ou Caddy, Windows a créé
    # des règles de blocage, qui l'emportent sur toute autorisation : on les désactive.
    $bloquantes = @(Get-NetFirewallApplicationFilter -ErrorAction SilentlyContinue |
        Where-Object { $_.Program -and ($programmes -contains [Environment]::ExpandEnvironmentVariables($_.Program)) } |
        Get-NetFirewallRule -ErrorAction SilentlyContinue |
        Where-Object { $_.Direction -eq 'Inbound' -and $_.Action -eq 'Block' -and $_.Enabled -eq 'True' })
    foreach ($r in $bloquantes) {
        Disable-NetFirewallRule -Name $r.Name
        Attention "Règle de pare-feu qui bloquait $(Split-Path $programmes[0] -Leaf) désactivée : « $($r.DisplayName) »"
    }
}

function VersionPhp([string] $exe) { return (& $exe -r 'echo PHP_VERSION_ID;') -as [int] }

function EstAdmin {
    $id = [Security.Principal.WindowsIdentity]::GetCurrent()
    return (New-Object Security.Principal.WindowsPrincipal $id).IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)
}

# ------------------------------------------------------------------ données à reprendre

# Chemins résolus avant la demande de droits, qui relance l'installateur depuis C:\Windows\System32.
# -Reprise accepte plusieurs fichiers : a.html,b.html (séparés par des virgules).
$Reprise = @($Reprise | ForEach-Object { $_ -split '[|,]' } | ForEach-Object { $_.Trim() } | Where-Object { $_ } |
    ForEach-Object { $ExecutionContext.SessionState.Path.GetUnresolvedProviderPathFromPSPath($_) })
if ($Restaurer) { $Restaurer = $ExecutionContext.SessionState.Path.GetUnresolvedProviderPathFromPSPath($Restaurer) }
foreach ($f in @($Reprise) + @($Restaurer)) {
    if ($f -and -not (Test-Path -LiteralPath $f -PathType Leaf)) { Echec "Fichier introuvable : $f" }
}
if ($Reprise.Count) { $PSBoundParameters['Reprise'] = $Reprise -join '|' }
if ($Restaurer) {
    $PSBoundParameters['Restaurer'] = $Restaurer
    if (-not $Production) { Echec '-Restaurer ne concerne que la production (base MariaDB/MySQL).' }
}

# ------------------------------------------------------------------ droits administrateur

if ($Production -and -not $ModeTest -and -not (EstAdmin)) {
    $log = Join-Path ([IO.Path]::GetTempPath()) ("sigaci-installation-{0:yyyyMMdd-HHmmss}.log" -f (Get-Date))
    Write-Host '  Droits administrateur nécessaires : confirmez la demande de Windows.' -ForegroundColor Yellow
    $liste = @('-NoProfile', '-ExecutionPolicy', 'Bypass', '-File', "`"$PSCommandPath`"")
    foreach ($k in $PSBoundParameters.Keys) {
        $v = $PSBoundParameters[$k]
        if ($v -is [Management.Automation.SwitchParameter]) { if ($v.IsPresent) { $liste += "-$k" } }
        else { $liste += "-$k"; $liste += "`"$v`"" }
    }
    $liste += '-Journal'; $liste += "`"$log`""
    try { $p = Start-Process powershell.exe -Verb RunAs -ArgumentList $liste -Wait -PassThru }
    catch { Echec 'Droits administrateur refusés : installation annulée.' }
    if (Test-Path $log) { Get-Content $log -Encoding UTF8 | Where-Object { $_ -notmatch '^\*{10,}|^(Début|Fin|Heure|Nom d|Ordinateur|Application hôte|ID de processus|PSVersion|PSEdition|PSCompatible|BuildVersion|CLRVersion|WSManStack|PSRemoting|SerializationVersion|Username|RunAs|Configuration|Machine|Host Application|Process ID|Start time|End time)' } }
    exit $p.ExitCode
}

New-Item -ItemType Directory -Force (Join-Path $Base 'journaux') | Out-Null
if (-not $Journal) { $Journal = Join-Path $Base ("journaux\installation-{0:yyyyMMdd-HHmmss}.log" -f (Get-Date)) }
try { Start-Transcript -Path $Journal -Force | Out-Null; $script:Transcription = $true } catch { }

# ------------------------------------------------------------------ installation ou mise à jour ?

$Etat = $null
if (Test-Path $FichierEtat) {
    $Etat = Get-Content $FichierEtat -Raw -Encoding UTF8 | ConvertFrom-Json
    $App = $Etat.app
    $Mode = $Etat.mode
    $Production = ($Mode -eq 'production')
}
$patch = $null
$MiseAJour = [bool]$Etat -or ((Test-Path (Join-Path $App 'artisan')) -and (Test-Path (Join-Path $App '.env')))

Write-Host ''
Write-Host '  ================================================' -ForegroundColor Cyan
if ($MiseAJour) { Write-Host '   SIGACI - mise à jour' -ForegroundColor Cyan } else { Write-Host '   SIGACI - installation automatique' -ForegroundColor Cyan }
Write-Host '  ================================================' -ForegroundColor Cyan
Info "Mode : $Mode$(if ($ModeTest) { ' (test : aucun changement système)' })"
Info "Dossier : $App"
if (-not (Test-Path $Outils)) { Echec "outils.php introuvable à côté de installer.ps1." }

# ------------------------------------------------------------------ 1. pile logicielle

Etape 'Pile logicielle'
$LaragonDir = 'C:\laragon'
$cfg = [ordered]@{}
if ($Etat) {
    foreach ($p in $Etat.PSObject.Properties) { $cfg[$p.Name] = $p.Value }
    Ok "Pile $($cfg.pile) (installation existante)"
    # La branche prototype-v1 a été fusionnée dans main, qui porte désormais les nouvelles versions.
    if ($PSBoundParameters.ContainsKey('Branche')) { $cfg.branche = $Branche }
    elseif ($cfg.branche -eq 'prototype-v1') { $cfg.branche = 'main'; Info 'Branche suivie : prototype-v1 -> main (version courante de SIGACI)' }
    if ($cfg.pile -eq 'laragon') {
        $vhostSigaci = Join-Path $LaragonDir 'etc\apache2\sites-enabled\sigaci.conf'
        if ($cfg.vhost -and $cfg.vhost -ne $vhostSigaci) { Remove-Item $cfg.vhost -ErrorAction SilentlyContinue }
        $cfg.vhost = $vhostSigaci
    }
    $cfg.version_installateur = 3
} else {
    $cfg.version_installateur = 3
    $cfg.mode = $Mode
    $cfg.base = $Base
    $cfg.app = $App
    $cfg.branche = $Branche
    $cfg.depot = $Depot
    if ($Production) {
        $choix = $Pile
        $phpLaragon = $null
        $modPhp = Join-Path $LaragonDir 'etc\apache2\mod_php.conf'
        if ($choix -ne 'autonome' -and (Test-Path (Join-Path $LaragonDir 'laragon.exe')) -and (Test-Path $modPhp)) {
            $m = Select-String -Path $modPhp -Pattern 'PHPIniDir\s+"([^"]+)"' | Select-Object -First 1
            if ($m) { $phpLaragon = Join-Path ($m.Matches[0].Groups[1].Value -replace '/', '\') 'php.exe' }
            if ($phpLaragon -and (Test-Path $phpLaragon) -and (VersionPhp $phpLaragon) -ge 80300) {
                $choix = 'laragon'
            } elseif ($choix -eq 'laragon') {
                Echec "Le PHP utilisé par Apache dans Laragon ($phpLaragon) n'est pas en version 8.3+. Changez-le dans Laragon (Menu > PHP > Version) ou utilisez -Pile autonome."
            } else {
                Attention "Laragon présent mais son PHP Apache n'est pas en 8.3+ : pile autonome."
                $choix = 'autonome'
            }
        } elseif ($choix -eq 'laragon') {
            Echec "Laragon introuvable dans $LaragonDir."
        } else {
            $choix = 'autonome'
        }
        $cfg.pile = $choix
        if (-not $Domaine) { $Domaine = $env:COMPUTERNAME.ToLower() }
        $cfg.domaine = $Domaine

        if ($choix -eq 'laragon') {
            $cfg.php = $phpLaragon
            $apache = Get-ChildItem (Join-Path $LaragonDir 'bin\apache') -Directory | Sort-Object Name -Descending | Select-Object -First 1
            $mysql = Get-ChildItem (Join-Path $LaragonDir 'bin\mysql') -Directory | Sort-Object Name -Descending | Select-Object -First 1
            if (-not $apache -or -not $mysql) { Echec 'Apache ou MySQL introuvable dans Laragon.' }
            $cfg.httpd = Join-Path $apache.FullName 'bin\httpd.exe'
            $cfg.mysqld = Join-Path $mysql.FullName 'bin\mysqld.exe'
            $cfg.mysqldump = Join-Path $mysql.FullName 'bin\mysqldump.exe'
            $cfg.myini = Join-Path $mysql.FullName 'my.ini'
            $portIni = Select-String -Path $cfg.myini -Pattern '^\s*port\s*=\s*(\d+)' -ErrorAction SilentlyContinue | Select-Object -First 1
            $cfg.port_bd = if ($portIni) { [int]$portIni.Matches[0].Groups[1].Value } else { 3306 }
            $cfg.port_web = 80
            $cfg.vhost = Join-Path $LaragonDir 'etc\apache2\sites-enabled\sigaci.conf'
            Ok "Laragon : PHP $(& $cfg.php -r 'echo PHP_VERSION;'), $(Split-Path $apache.FullName -Leaf), $(Split-Path $mysql.FullName -Leaf)"
        } else {
            $cfg.php = Join-Path $Base 'php\php.exe'
            $cfg.php_cgi = Join-Path $Base 'php\php-cgi.exe'
            $cfg.mysqld = Join-Path $Base 'mariadb\bin\mysqld.exe'
            $cfg.mysqldump = Join-Path $Base 'mariadb\bin\mariadb-dump.exe'
            $cfg.myini = Join-Path $Base 'data\my.ini'
            $cfg.mysql_service = if ($ModeTest) { $null } else { 'SIGACIMariaDB' }
            $cfg.caddy = Join-Path $Base 'caddy\caddy.exe'
            $cfg.caddyfile = Join-Path $Base 'caddy\Caddyfile'
            $cfg.php_workers = 4
            if ($ModeTest) {
                $cfg.port_web = if ($PortWeb) { $PortWeb } else { PremierPortLibre @(8090, 8091, 8092) }
                $cfg.port_bd = PremierPortLibre @(3317, 3318, 3319)
                $cfg.port_php = PremierPortLibre @(9100, 9110, 9120)
            } else {
                $cfg.port_web = if ($PortWeb) { $PortWeb } else { PremierPortLibre @(80, 8080, 8000) }
                $cfg.port_bd = PremierPortLibre @(3306, 3307, 3308)
                $cfg.port_php = PremierPortLibre @(9000, 9010, 9020)
            }
            Ok "Pile autonome dans $Base (PHP 8.3, MariaDB, Caddy)"
        }
        $suffixe = if ($cfg.port_web -ne 80) { ":$($cfg.port_web)" } else { '' }
        $cfg.url = if ($Https) { "https://$Domaine" } else { "http://$Domaine$suffixe" }
    } else {
        $cfg.pile = 'demo'
        $cfg.url = 'http://localhost:8000'
        Ok 'Démonstration : PHP en ligne de commande, base SQLite'
    }
}

# Laragon : si le port 80 est tenu par un autre programme qu'Apache (redirection netsh portproxy, IIS, Skype...)
# ou si -PortWeb le demande, SIGACI a sa propre instance Apache (programmes de Laragon, configuration dans
# $Base\apache). Laragon et le programme qui tient le port 80 ne sont pas modifiés.
if ($Production -and $cfg.pile -eq 'laragon') {
    $portDedie = 0
    if ($PSBoundParameters.ContainsKey('PortWeb')) { $portDedie = $PortWeb }
    elseif ($cfg.httpd_conf) { $portDedie = [int]$cfg.port_web }
    else {
        $occupant = Get-NetTCPConnection -State Listen -LocalPort 80 -ErrorAction SilentlyContinue | Select-Object -First 1
        if ($occupant) {
            $proc = Get-Process -Id $occupant.OwningProcess -ErrorAction SilentlyContinue
            if (-not $proc -or $proc.ProcessName -ne 'httpd') {
                $nomOccupant = if ($proc) { $proc.ProcessName } else { "processus $($occupant.OwningProcess)" }
                if ($proc -and $proc.ProcessName -eq 'svchost') {
                    $svc = Get-CimInstance Win32_Service -Filter "ProcessId=$($proc.Id)" -ErrorAction SilentlyContinue | Select-Object -First 1
                    if ($svc) { $nomOccupant = "le service Windows « $($svc.DisplayName) »" }
                    if ($svc.Name -eq 'iphlpsvc') { $nomOccupant += ' (redirection de ports netsh portproxy)' }
                }
                $portDedie = PremierPortLibre @(8080, 8000, 8081, 8088)
                Attention "Port 80 occupé par $nomOccupant : SIGACI aura son propre serveur Apache sur le port $portDedie (ni Laragon ni ce programme ne sont modifiés)."
            }
        }
    }
    if ($portDedie -and $portDedie -ne 80) {
        $cfg.port_web = $portDedie
        $cfg.httpd_conf = Join-Path $Base 'apache\httpd.conf'
    } else {
        $cfg.port_web = 80
        $cfg.httpd_conf = $null
    }
    if (-not $Https) { $cfg.url = "http://$($cfg.domaine)$(if ($cfg.port_web -ne 80) { ":$($cfg.port_web)" })" }
}

# ------------------------------------------------------------------ 2. PHP

Etape 'PHP'
function InstallerVcRedist {
    $vc = Get-ItemProperty 'HKLM:\SOFTWARE\Microsoft\VisualStudio\14.0\VC\Runtimes\x64' -ErrorAction SilentlyContinue
    if ($vc -and $vc.Installed -eq 1 -and $vc.Bld -ge 29000) { return }
    $f = Join-Path ([IO.Path]::GetTempPath()) 'vc_redist.x64.exe'
    Telecharger 'https://aka.ms/vs/17/release/vc_redist.x64.exe' $f $null
    $sig = Get-AuthenticodeSignature $f
    if ($sig.Status -ne 'Valid' -or $sig.SignerCertificate.Subject -notmatch 'Microsoft Corporation') { Echec 'Signature du programme Visual C++ invalide : fichier rejeté.' }
    Info 'Installation des bibliothèques Visual C++ (nécessaires à PHP)...'
    $verbe = if (EstAdmin) { 'Open' } else { 'RunAs' }
    $p = Start-Process $f -ArgumentList '/install', '/quiet', '/norestart' -Verb $verbe -Wait -PassThru
    if ($p.ExitCode -notin 0, 1638, 3010) { Echec "Installation de Visual C++ échouée (code $($p.ExitCode))." }
    Ok 'Bibliothèques Visual C++ installées'
}

function InstallerPhpPortable([string] $dest) {
    InstallerVcRedist
    $index = Invoke-RestMethod 'https://windows.php.net/downloads/releases/releases.json'
    $rel = $index.'8.3'.'nts-vs16-x64'.zip
    if (-not $rel) { Echec "Index des versions de PHP illisible (windows.php.net)." }
    $zip = Join-Path ([IO.Path]::GetTempPath()) $rel.path
    Telecharger "https://windows.php.net/downloads/releases/$($rel.path)" $zip $rel.sha256
    Extraire $zip $dest
    Remove-Item $zip
    Ok "PHP $($index.'8.3'.version) installé dans $dest"
}

if ($cfg.pile -eq 'autonome' -and -not (Test-Path $cfg.php)) {
    InstallerPhpPortable (Join-Path $Base 'php')
}
if ($cfg.pile -eq 'demo' -and -not $cfg.php) {
    $candidats = @()
    $surPath = Get-Command php -ErrorAction SilentlyContinue
    if ($surPath) { $candidats += $surPath.Source }
    if (Test-Path (Join-Path $LaragonDir 'bin\php')) {
        $candidats += Get-ChildItem (Join-Path $LaragonDir 'bin\php') -Directory | Sort-Object Name -Descending | ForEach-Object { Join-Path $_.FullName 'php.exe' }
    }
    $candidats += Join-Path $Base 'php\php.exe'
    $cfg.php = $candidats | Where-Object { (Test-Path $_) -and (VersionPhp $_) -ge 80300 } | Select-Object -First 1
    if (-not $cfg.php) {
        InstallerPhpPortable (Join-Path $Base 'php')
        $cfg.php = Join-Path $Base 'php\php.exe'
    }
}
$Php = $cfg.php
$env:Path = (Split-Path $Php) + ';' + $env:Path
if ((VersionPhp $Php) -lt 80300) { Echec "PHP $(& $Php -r 'echo PHP_VERSION;') trop ancien : SIGACI demande PHP 8.3+." }
Ok "PHP $(& $Php -r 'echo PHP_VERSION;') : $Php"

$baseSql = if ($Production) { 'mysql' } else { 'sqlite' }
if ($cfg.pile -in 'laragon', 'autonome') {
    Exec $Php $Outils configurer-php (Join-Path (Split-Path $Php) 'php.ini') $cfg.pile
}
& $Php $Outils verifier $baseSql
if ($LASTEXITCODE -ne 0) {
    Info 'Activation des extensions manquantes...'
    Exec $Php $Outils configurer-php (Join-Path (Split-Path $Php) 'php.ini') laragon
    Exec $Php $Outils verifier $baseSql
}

# ------------------------------------------------------------------ 3. git et Composer

Etape 'git et Composer'
$Git = $null
foreach ($g in @((Join-Path $LaragonDir 'bin\git\cmd\git.exe'), (Join-Path $Base 'git\cmd\git.exe'))) { if (Test-Path $g) { $Git = $g; break } }
if (-not $Git) { $c = Get-Command git -ErrorAction SilentlyContinue; if ($c) { $Git = $c.Source } }
if (-not $Git) {
    $mg = DerniereVersionGitHub 'git-for-windows/git' '^MinGit-[\d.]+-64-bit\.zip$'
    $zip = Join-Path ([IO.Path]::GetTempPath()) $mg.nom
    Telecharger $mg.url $zip $mg.sha256
    Extraire $zip (Join-Path $Base 'git')
    Remove-Item $zip
    $Git = Join-Path $Base 'git\cmd\git.exe'
}
Ok "git : $Git"

$Phar = Join-Path $LaragonDir 'bin\composer\composer.phar'
if (-not (Test-Path $Phar)) {
    $Phar = Join-Path $Base 'outils\composer.phar'
    if (-not (Test-Path $Phar)) {
        New-Item -ItemType Directory -Force (Split-Path $Phar) | Out-Null
        Exec $Php $Outils composer (Split-Path $Phar)
    }
}
Ok "Composer : $Phar"
$env:COMPOSER_NO_INTERACTION = '1'
$env:COMPOSER_ALLOW_SUPERUSER = '1'

function G { & $Git -c safe.directory=* -C $App @args }

# ------------------------------------------------------------------ 4. mise en maintenance (mise à jour)

$script:Maintenance = $false
if ($MiseAJour -and (Test-Path (Join-Path $App 'vendor\autoload.php'))) {
    Etape 'Sauvegarde et mise en maintenance'
    if ($Etat -and $Production) {
        & powershell.exe -NoProfile -ExecutionPolicy Bypass -File $Superviseur -Etat $FichierEtat -UneFois | Out-Null
        AttendrePort $cfg.port_bd 60 'La base de données'
        & powershell.exe -NoProfile -ExecutionPolicy Bypass -File $Superviseur -Etat $FichierEtat -Sauvegarder
        if ($LASTEXITCODE -ne 0) { Echec 'Sauvegarde impossible : mise à jour annulée (rien n''a été modifié).' }
    } elseif (-not $Production) {
        $sqlite = Join-Path $App 'database\database.sqlite'
        if (Test-Path $sqlite) { Copy-Item $sqlite "$sqlite.avant-maj"; Ok 'Copie de la base SQLite : database.sqlite.avant-maj' }
    }
    Push-Location $App
    & $Php artisan down --retry=60 | Out-Null
    Pop-Location
    $script:Maintenance = $true
    Ok 'SIGACI en maintenance'
}

# ------------------------------------------------------------------ 5. code

Etape 'Code de SIGACI'
$sauvegardes = Join-Path $Base 'sauvegardes'
New-Item -ItemType Directory -Force $sauvegardes | Out-Null
$stamp = Get-Date -Format 'yyyy-MM-dd_HHmm'

if (-not (Test-Path (Join-Path $App 'artisan'))) {
    if ((Test-Path $App) -and @(Get-ChildItem $App -Force).Count -gt 0) {
        Echec "Le dossier $App existe, n'est pas vide et ne contient pas SIGACI."
    }
    Info "Clonage de $Depot (branche $($cfg.branche))..."
    Exec $Git -c safe.directory=* clone --quiet --branch $cfg.branche $cfg.depot $App
    Ok 'Code téléchargé'
} else {
    if (-not (Test-Path (Join-Path $App '.git'))) {
        # Installation faite depuis une archive ZIP : on la rattache au dépôt pour les mises à jour.
        $copie = Join-Path $sauvegardes "code-avant-maj_$stamp.zip"
        Get-ChildItem $App -Force | Where-Object { $_.Name -notin 'vendor', 'node_modules', 'storage' } |
            Compress-Archive -DestinationPath $copie -Force
        Info "Installation sans git : copie du code dans $copie, puis rattachement au dépôt."
        G init --quiet
        G remote add origin $cfg.depot
    } else {
        $patch = Join-Path $sauvegardes "modifications-locales_$stamp.patch"
        G diff HEAD --binary "--output=$patch"
        if ((Test-Path $patch) -and (Get-Item $patch).Length -gt 0) {
            Info "Modifications locales mises de côté : $patch"
        } else {
            Remove-Item $patch -ErrorAction SilentlyContinue
            $patch = $null
        }
    }
    G fetch --quiet origin $cfg.branche
    if ($LASTEXITCODE -ne 0) { Echec 'Impossible de joindre le dépôt GitHub (accès Internet ?).' }
    $avant = (G rev-parse --short HEAD 2>$null)
    G checkout --quiet -B $cfg.branche "origin/$($cfg.branche)" --force
    G reset --quiet --hard "origin/$($cfg.branche)"
    if ($patch) {
        G apply --check $patch 2>$null
        if ($LASTEXITCODE -eq 0) { G apply $patch; Ok 'Modifications locales réappliquées' }
        else { Attention "Modifications locales incompatibles avec la nouvelle version : non réappliquées (voir $patch)." }
    }
    $apres = (G rev-parse --short HEAD)
    if ($avant -and $avant -eq $apres) { Ok "Déjà à la dernière version ($apres)" } else { Ok "Code mis à jour : $(if ($avant) { $avant } else { 'archive' }) -> $apres" }
}
$cfg.commit = (G rev-parse --short HEAD)

Etape 'Dépendances PHP (Composer)'
Push-Location $App
try {
    $opts = @('install', '--no-interaction', '--no-progress')
    if ($Production) { $opts += '--no-dev', '--optimize-autoloader' }
    Exec $Php $Phar @opts
    Ok 'Dépendances installées'
} finally { Pop-Location }

# ------------------------------------------------------------------ 6. configuration (.env)

Etape 'Configuration (.env)'
$Identifiants = [ordered]@{}
$FichierIdentifiants = Join-Path $Base 'identifiants.txt'
# Chaque identifiant est écrit dès sa création : un échec plus loin (serveur web, pare-feu...) ne doit pas le perdre,
# les relances suivantes ne le recréant pas.
function NoterIdentifiant([string] $cle, [string] $valeur) {
    $nouveau = -not $Identifiants.Count
    $Identifiants[$cle] = $valeur
    $lignes = @()
    if ($nouveau) { $lignes += @('', "SIGACI - identifiants créés le $(Get-Date -Format 'dd/MM/yyyy HH:mm'). Conservez-les en lieu sûr, puis supprimez ce fichier.") }
    $lignes += "${cle} : $valeur"
    Add-Content -Path $FichierIdentifiants -Value $lignes -Encoding UTF8
    if ($nouveau -and $Production -and -not $ModeTest) {
        # Lecture réservée aux administrateurs et au système (SID : indépendant de la langue de Windows).
        & icacls $FichierIdentifiants /inheritance:r /grant:r '*S-1-5-32-544:F' '*S-1-5-18:F' | Out-Null
    }
}
if (Test-Path (Join-Path $App '.env')) {
    Exec $Php $Outils completer-env $App
} else {
    $env:SIGACI_MODE = $Mode
    $env:SIGACI_URL = $cfg.url
    if ($Production) {
        $env:SIGACI_DB_HOTE = '127.0.0.1'
        $env:SIGACI_DB_PORT = "$($cfg.port_bd)"
        $env:SIGACI_DB_NOM = 'sigaci'
        $env:SIGACI_DB_UTILISATEUR = 'sigaci'
        $env:SIGACI_DB_MDP = NouveauMotDePasse
        NoterIdentifiant 'Base de données SIGACI' "utilisateur sigaci / $($env:SIGACI_DB_MDP) (port $($cfg.port_bd))"
    }
    Exec $Php $Outils preparer-env $App
    Remove-Item Env:SIGACI_DB_MDP -ErrorAction SilentlyContinue
}

# ------------------------------------------------------------------ 7. serveurs (production)

if ($Production) {
    Etape 'Base de données et serveur web'
    $rootMdp = $env:SIGACI_DB_ROOT_MDP
    if ($cfg.pile -eq 'autonome') {
        if (-not (Test-Path $cfg.mysqld)) {
            $index = Invoke-RestMethod 'https://downloads.mariadb.org/rest-api/mariadb/11.4/latest/'
            $rel = ($index.releases.PSObject.Properties | Select-Object -First 1).Value
            $f = $rel.files | Where-Object { $_.file_name -match 'winx64\.zip$' } | Select-Object -First 1
            $zip = Join-Path ([IO.Path]::GetTempPath()) $f.file_name
            Telecharger ($f.file_download_url -replace '^http:', 'https:') $zip $f.checksum.sha256sum
            Extraire $zip (Join-Path $Base 'mariadb')
            Remove-Item $zip
            Ok "MariaDB $($rel.release_id) installé"
        }
        if (-not (Test-Path (Join-Path $Base 'data'))) {
            $rootMdp = NouveauMotDePasse
            $argsInit = @("--datadir=$(Join-Path $Base 'data')", "--password=$rootMdp", "--port=$($cfg.port_bd)")
            if ($cfg.mysql_service) { $argsInit += "--service=$($cfg.mysql_service)" }
            Exec (Join-Path $Base 'mariadb\bin\mariadb-install-db.exe') @argsInit
            NoterIdentifiant 'MariaDB (administrateur)' "root / $rootMdp (port $($cfg.port_bd))"
            Ok 'Base MariaDB initialisée'
        }
        if (-not (Test-Path $cfg.caddy)) {
            $cd = DerniereVersionGitHub 'caddyserver/caddy' 'windows_amd64\.zip$'
            $zip = Join-Path ([IO.Path]::GetTempPath()) $cd.nom
            Telecharger $cd.url $zip $cd.sha256
            Extraire $zip (Join-Path $Base 'caddy')
            Remove-Item $zip
            Ok "Caddy installé ($($cd.nom))"
        }
        # Configuration du serveur web, réécrite à chaque passage.
        $racine = (Join-Path $App 'public') -replace '\\', '/'
        $amont = (0..($cfg.php_workers - 1) | ForEach-Object { "127.0.0.1:$([int]$cfg.port_php + $_)" }) -join ' '
        $site = if ($Https) { $cfg.domaine } else { ":$($cfg.port_web)" }
        $global = if ($Https) { "`tadmin off" } else { "`tadmin off`n`tauto_https off" }
        $caddyfile = @"
# SIGACI - généré par installer.ps1 : réécrit à chaque mise à jour, ne pas modifier.
{
$global
}

$site {
	root * "$racine"
	encode zstd gzip
	php_fastcgi $amont {
		lb_try_duration 5s
	}
	file_server
	header -Server
	header -X-Powered-By
	log {
		output file "$((Join-Path $Base 'journaux\web.log') -replace '\\', '/')" {
			roll_size 10MiB
			roll_keep 5
		}
	}
}
"@
        [IO.File]::WriteAllText($cfg.caddyfile, $caddyfile)
        Ok "Caddy configuré (port $($cfg.port_web))"
    } else {
        $racine = (Join-Path $App 'public') -replace '\\', '/'
        # Accès depuis le réseau local : nom de l'ordinateur et toute adresse IP privée (192.168.x.x, 10.x.x.x,
        # 172.x.x.x), même si elle change. localhost reste le site par défaut de Laragon.
        $pc = $env:COMPUTERNAME.ToLower()
        $alias = @($pc, "$pc.local", "$pc.lan", 'sigaci.test', "$($cfg.domaine).local", '192.168.*', '10.*', '172.*') |
            Where-Object { $_ -ne $cfg.domaine } | Select-Object -Unique
        $vhost = @"
# SIGACI - généré par installer.ps1 : réécrit à chaque mise à jour, ne pas modifier.
<VirtualHost *:80>
    ServerName $($cfg.domaine)
    ServerAlias $($alias -join ' ')
    DocumentRoot "$racine"
    <Directory "$racine">
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
"@
        if (-not $cfg.httpd_conf) {
            [IO.File]::WriteAllText($cfg.vhost, $vhost)
            Ok "Site Apache configuré : $($cfg.vhost)"
        } else {
            # Instance dédiée : le site de SIGACI est retiré de Laragon (il n'y servirait plus).
            if ($cfg.vhost -and (Test-Path $cfg.vhost)) { Remove-Item $cfg.vhost -Force }
            $srv = (Split-Path (Split-Path $cfg.httpd)) -replace '\\', '/'
            $phpDir = (Split-Path $cfg.php) -replace '\\', '/'
            $base = $Base -replace '\\', '/'
            New-Item -ItemType Directory -Force (Join-Path $Base 'apache') | Out-Null
            # Mêmes modules que l'Apache de Laragon (sauf SSL, inutile ici), PHP de SIGACI.
            $modules = Select-String -Path (Join-Path $srv 'conf\httpd.conf') -Pattern '^\s*LoadModule\s+(\S+)\s+(\S+)' |
                Where-Object { $_.Matches[0].Groups[1].Value -notin 'ssl_module', 'socache_shmcb_module' } |
                ForEach-Object { $_.Line.Trim() }
            $dll = Get-ChildItem (Split-Path $cfg.php) -Filter 'php*apache2_4.dll' -ErrorAction SilentlyContinue | Select-Object -First 1
            $fcgid = Join-Path $LaragonDir 'etc\apache2\modules\mod_fcgid.so'
            if ($dll) {
                $moteurPhp = @"
LoadModule php_module "$phpDir/$($dll.Name)"
PHPIniDir "$phpDir"
AddType application/x-httpd-php .php
"@
            } elseif ((Test-Path $fcgid) -and (Test-Path (Join-Path (Split-Path $cfg.php) 'php-cgi.exe'))) {
                $moteurPhp = @"
LoadModule fcgid_module "$($fcgid -replace '\\', '/')"
FcgidInitialEnv PHPRC "$phpDir"
FcgidInitialEnv SystemRoot "C:/Windows"
FcgidInitialEnv TEMP "C:/Windows/Temp"
FcgidInitialEnv PHP_FCGI_MAX_REQUESTS 0
FcgidMaxRequestsPerProcess 0
FcgidIOTimeout 600
FcgidMaxRequestLen 81310720
<Files ~ "\.php$">
    AddHandler fcgid-script .php
    Options +ExecCGI
    FcgidWrapper "$phpDir/php-cgi.exe" .php
</Files>
"@
            } else { Echec "Ni php8apache2_4.dll ni php-cgi.exe dans $phpDir : instance Apache de SIGACI impossible." }
            $conf = @"
# SIGACI - instance Apache dédiée, générée par installer.ps1 : réécrite à chaque mise à jour, ne pas modifier.
# Elle utilise les programmes d'Apache de Laragon sans toucher à sa configuration.
ServerRoot "$srv"
Listen $($cfg.port_web)
$($modules -join "`n")
$moteurPhp
ServerName $($cfg.domaine):$($cfg.port_web)
PidFile "$base/apache/httpd.pid"
ErrorLog "$base/journaux/apache-erreurs.log"
LogLevel warn
LogFormat "%h %l %u %t \"%r\" %>s %b" commun
CustomLog "$base/journaux/apache-acces.log" commun
TypesConfig conf/mime.types
DirectoryIndex index.php index.html
<Directory />
    AllowOverride None
    Require all denied
</Directory>
DocumentRoot "$racine"
<Directory "$racine">
    Options FollowSymLinks
    AllowOverride All
    Require all granted
</Directory>
"@
            [IO.File]::WriteAllText($cfg.httpd_conf, $conf)
            # httpd -t écrit sur la sortie d'erreur même quand tout va bien.
            $eap = $ErrorActionPreference; $ErrorActionPreference = 'Continue'
            $env:Path = "$(Split-Path $cfg.php);$env:Path"
            $verif = (& $cfg.httpd -t -f $cfg.httpd_conf 2>&1 | Out-String).Trim()
            $codeVerif = $LASTEXITCODE
            $ErrorActionPreference = $eap
            if ($codeVerif -ne 0) { Echec "Configuration Apache de SIGACI invalide : $verif" }
            Ok "Instance Apache de SIGACI configurée : port $($cfg.port_web) ($($cfg.httpd_conf))"
        }
    }

    # État et outils, puis démarrage par le superviseur.
    $dossierOutils = Join-Path $Base 'outils'
    New-Item -ItemType Directory -Force $dossierOutils | Out-Null
    foreach ($f in 'installer.ps1', 'installer.cmd', 'superviseur.ps1', 'outils.php', 'LISEZMOI.md') {
        $src = Join-Path $PSScriptRoot $f
        if ((Test-Path $src) -and ((Resolve-Path $src).Path -ne (Join-Path $dossierOutils $f))) { Copy-Item $src $dossierOutils -Force }
    }
    $cfg.installe_le = if ($cfg.installe_le) { $cfg.installe_le } else { (Get-Date).ToString('s') }
    [IO.File]::WriteAllText($FichierEtat, ($cfg | ConvertTo-Json -Depth 4))
    $Superviseur = Join-Path $dossierOutils 'superviseur.ps1'

    if ($ModeTest) {
        & powershell.exe -NoProfile -ExecutionPolicy Bypass -File $Superviseur -Etat $FichierEtat -UneFois
    } else {
        # Arrêt du superviseur en cours (y compris celui de l'ancien nom, « GesParc ») avant de le remplacer.
        foreach ($t in 'GesParc', 'SIGACI') {
            if (Get-ScheduledTask -TaskName $t -ErrorAction SilentlyContinue) {
                Stop-ScheduledTask -TaskName $t -ErrorAction SilentlyContinue
                Unregister-ScheduledTask -TaskName $t -Confirm:$false -ErrorAction SilentlyContinue
            }
        }
        $action = New-ScheduledTaskAction -Execute 'powershell.exe' -Argument "-NoProfile -ExecutionPolicy Bypass -WindowStyle Hidden -File `"$Superviseur`" -Etat `"$FichierEtat`""
        $declencheur = New-ScheduledTaskTrigger -AtStartup
        $compte = New-ScheduledTaskPrincipal -UserId 'S-1-5-18' -LogonType ServiceAccount -RunLevel Highest
        $reglages = New-ScheduledTaskSettingsSet -ExecutionTimeLimit ([TimeSpan]::Zero) -RestartCount 999 -RestartInterval (New-TimeSpan -Minutes 1) `
            -StartWhenAvailable -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries -MultipleInstances IgnoreNew
        Register-ScheduledTask -TaskName 'SIGACI' -Description 'SIGACI : base de données, serveur web, tâches planifiées et sauvegarde quotidienne' `
            -Action $action -Trigger $declencheur -Principal $compte -Settings $reglages | Out-Null
        Start-ScheduledTask -TaskName 'SIGACI'
        Ok 'Démarrage automatique avec Windows (tâche planifiée « SIGACI »)'
    }
    AttendrePort $cfg.port_bd 90 'La base de données'
    Ok "Base de données en marche (port $($cfg.port_bd))"

    if ($rootMdp -or $cfg.pile -eq 'laragon') {
        $env:SIGACI_DB_ROOT_UTILISATEUR = 'root'
        $env:SIGACI_DB_ROOT_MDP = "$rootMdp"
    }
    & $Php $Outils bd-initialisee $App
    if ($LASTEXITCODE -ne 0) {
        & $Php $Outils creer-bd $App
        if ($LASTEXITCODE -ne 0) {
            if ($cfg.pile -eq 'laragon') { Echec 'Création de la base impossible avec le compte root de Laragon. S''il a un mot de passe, lancez depuis une console administrateur : $env:SIGACI_DB_ROOT_MDP=''...'' puis l''installateur.' }
            Echec 'Création de la base impossible.'
        }
    }
    Remove-Item Env:SIGACI_DB_ROOT_MDP, Env:SIGACI_DB_ROOT_UTILISATEUR -ErrorAction SilentlyContinue
}

# ------------------------------------------------------------------ 8. tables et administrateur

Etape 'Tables de la base'
Push-Location $App
try {
    if ($Restaurer) {
        # Une relance avec la même sauvegarde ne l'applique pas une seconde fois (elle effacerait les saisies faites depuis).
        $empreinte = (Get-FileHash -LiteralPath $Restaurer -Algorithm SHA256).Hash
        if ($cfg.restauration_empreinte -eq $empreinte) {
            Info "Sauvegarde déjà restaurée le $($cfg.restauration_le) : ignorée ($(Split-Path $Restaurer -Leaf))"
        } else {
            Info "Restauration de $Restaurer (remplace les données actuelles$(if ($MiseAJour) { ', sauvegardées à l''étape précédente' }))"
            Exec $Php $Outils restaurer-bd $App $Restaurer
            Info 'Comptes et mots de passe : ceux du serveur d''origine de la sauvegarde.'
            $cfg.restauration_fichier = $Restaurer
            $cfg.restauration_empreinte = $empreinte
            $cfg.restauration_le = (Get-Date).ToString('s')   # noté dans installation.json en fin de passage
        }
    }

    & $Php $Outils bd-initialisee $App
    $nouvelleBase = ($LASTEXITCODE -ne 0)
    Exec $Php artisan config:clear --quiet
    if ($nouvelleBase) {
        if ($Production) { Exec $Php artisan migrate --force --seed } else { Exec $Php artisan migrate --force --seed --seeder=DemoSeeder }
    } else {
        Exec $Php artisan migrate --force
    }
    Ok 'Base de données à jour'

    if ($Production) {
        # Créé seulement s'il n'existe aucun administrateur national (y compris après une installation interrompue).
        $mdpAdmin = if ($env:SIGACI_ADMIN_MDP) { $env:SIGACI_ADMIN_MDP } else { NouveauMotDePasse 16 }
        $env:SIGACI_ADMIN_MDP = $mdpAdmin
        $env:SIGACI_ADMIN_EMAIL = $AdminEmail
        & $Php $Outils creer-admin $App
        $codeAdmin = $LASTEXITCODE
        Remove-Item Env:SIGACI_ADMIN_MDP, Env:SIGACI_ADMIN_EMAIL
        if ($codeAdmin -eq 0) { NoterIdentifiant 'Administrateur national SIGACI' "$AdminEmail / $mdpAdmin" }
        elseif ($codeAdmin -ne 3) { Echec "Création de l'administrateur national impossible." }
    }

    if ($Reprise.Count) {
        # Personnel, matériel et toners des fichiers HTML de l'application d'origine (relançable sans doublon).
        $commandes = (& $Php artisan list --raw 2>$null) -join "`n"
        if ($commandes -notmatch '(?m)^sigaci:reprise-origine\b') {
            Attention 'Cette version de SIGACI ne connaît pas la reprise (sigaci:reprise-origine) : données non importées.'
        } else {
            Info "Reprise des données d'origine dans l'institution $($Institution.ToUpper())"
            Exec $Php artisan sigaci:reprise-origine @Reprise "--institution=$Institution" --creer-institution --no-interaction
            Ok 'Données de l''application d''origine reprises'
        }
    }

    if ($Production) {
        Exec $Php artisan config:cache
        Exec $Php artisan route:cache
        Exec $Php artisan view:cache
        Ok 'Caches de production à jour'
    } else {
        Exec $Php artisan optimize:clear --quiet
    }
    if ($script:Maintenance) { Exec $Php artisan up; $script:Maintenance = $false; Ok 'SIGACI remis en ligne' }
} finally { Pop-Location }

# ------------------------------------------------------------------ 9. redémarrage et vérification (production)

if ($Production) {
    Etape 'Redémarrage et vérification'
    if ($ModeTest) {
        & powershell.exe -NoProfile -ExecutionPolicy Bypass -File $Superviseur -Etat $FichierEtat -Redemarrer
    } else {
        # Arrêt du serveur web : le superviseur (compte SYSTEM) le relance avec le nouveau code et la nouvelle configuration.
        & powershell.exe -NoProfile -ExecutionPolicy Bypass -File $Superviseur -Etat $FichierEtat -Arreter | Out-Null
        $serveurWeb = if ($cfg.pile -eq 'autonome') { @($cfg.caddy) } else { @($cfg.httpd) }
        OuvrirPareFeu $cfg.port_web $serveurWeb
    }
    AttendrePort $cfg.port_web 60 'Le serveur web'
    $test = if ($ModeTest) { "http://127.0.0.1:$($cfg.port_web)/connexion" } else { "$($cfg.url)/connexion" }
    if (-not (Repond $test 10)) { Echec "SIGACI ne répond pas sur $test (voir $Base\journaux et $App\storage\logs)." }
    Ok "SIGACI répond : $test"

    # Accès depuis les autres postes : par l'adresse IP de ce serveur et par son nom.
    $suffixePort = if ($cfg.port_web -ne 80) { ":$($cfg.port_web)" } else { '' }
    $AdressesLan = @(AdressesReseau | ForEach-Object { "http://$_$suffixePort" })
    $AdressesLan += "http://$($env:COMPUTERNAME.ToLower())$suffixePort"
    if ($cfg.url -notin $AdressesLan -and -not $Https) { $AdressesLan = @($cfg.url) + $AdressesLan }
    foreach ($a in ($AdressesLan | Select-Object -First 2)) {
        if (Repond "$a/connexion" 3) { Ok "Accessible depuis le réseau local : $a" }
        else { Attention "SIGACI ne répond pas sur $a : vérifiez le pare-feu ou un autre logiciel de sécurité." }
    }
    $dhcp = Get-NetIPAddress -AddressFamily IPv4 -PrefixOrigin Dhcp -ErrorAction SilentlyContinue |
        Where-Object { "http://$($_.IPAddress)$suffixePort" -in $AdressesLan }
    if ($dhcp) {
        Info "L'adresse IP de ce serveur est attribuée automatiquement (DHCP) et peut changer : pour une adresse stable,"
        Info "faites-la réserver dans le routeur (ou la box), ou utilisez le nom http://$($env:COMPUTERNAME.ToLower())$suffixePort."
    }
}

# ------------------------------------------------------------------ 10. identifiants et état

$cfg.mis_a_jour_le = (Get-Date).ToString('s')
if (-not $cfg.installe_le) { $cfg.installe_le = $cfg.mis_a_jour_le }
[IO.File]::WriteAllText($FichierEtat, ($cfg | ConvertTo-Json -Depth 4))

if ($Identifiants.Count) {
    # Les identifiants y sont déjà (écrits à leur création) : on ajoute les adresses, connues seulement maintenant.
    $texte = @("Adresse : $($cfg.url)")
    if ($AdressesLan) { $texte += "Depuis les autres postes : $($AdressesLan -join '  ou  ')" }
    Add-Content -Path $FichierIdentifiants -Value $texte -Encoding UTF8
}

Write-Host ''
Write-Host '  ================================================' -ForegroundColor Green
if ($MiseAJour) { Write-Host "   SIGACI est à jour (version $($cfg.commit))" -ForegroundColor Green } else { Write-Host "   SIGACI est installé (version $($cfg.commit))" -ForegroundColor Green }
Write-Host '  ================================================' -ForegroundColor Green
Info "Adresse      : $($cfg.url)"
if ($Production) {
    if ($AdressesLan) { Write-Host "  Réseau local : $($AdressesLan -join '   ')" -ForegroundColor Green }
    Info "Dossier      : $App"
    Info "Pile         : $($cfg.pile)"
    if ($Identifiants.Contains('Administrateur national SIGACI')) {
        Write-Host "  Connexion    : $($Identifiants['Administrateur national SIGACI'])" -ForegroundColor Yellow
        Info '               (un nouveau mot de passe sera demandé à la première connexion)'
    }
    if ($Identifiants.Count) { Info "Identifiants : $FichierIdentifiants (à mettre en lieu sûr)" }
    Info "Sauvegardes  : $Base\sauvegardes (chaque nuit à 2 h 30, 30 jours)"
    Info "Mettre à jour: relancez l'installateur ($Base\outils\installer.cmd)"
    if ($cfg.pile -eq 'laragon') { Info 'Laragon     : inutile de l''ouvrir, la tâche « SIGACI » démarre Apache et MySQL avec Windows.' }
} else {
    Info 'Compte       : national@sigaci.test / demo2026 (autres comptes : README.md)'
    Info "Démarrer     : cd `"$App`" ; php artisan serve"
}
Info "Journal      : $Journal"
if ($script:Transcription) { try { Stop-Transcript | Out-Null } catch { } }

if ($Demarrer -and -not $Production) {
    Push-Location $App
    try { & $Php artisan serve } finally { Pop-Location }
}
