<#
.SYNOPSIS
    Superviseur SIGACI pour Windows : maintient la base de données et le serveur web en marche,
    lance les tâches planifiées de Laravel chaque minute et la sauvegarde quotidienne.

.DESCRIPTION
    Installé par installer.ps1 dans <Base>\outils et lancé au démarrage de Windows par la tâche planifiée
    « SIGACI » (compte SYSTEM) : les services tournent même si personne n'est connecté.
    Il lit sa configuration dans <Base>\installation.json.

      superviseur.ps1                 boucle permanente (utilisée par la tâche planifiée)
      superviseur.ps1 -UneFois        démarre ce qui est arrêté, puis rend la main
      superviseur.ps1 -Redemarrer     arrête le serveur web (PHP compris) et le relance
      superviseur.ps1 -Arreter        arrête le serveur web (la base de données reste en marche)
      superviseur.ps1 -Sauvegarder    sauvegarde immédiate de la base et du fichier .env
#>
param(
    [string] $Etat = (Join-Path (Split-Path $PSScriptRoot -Parent) 'installation.json'),
    [switch] $UneFois,
    [switch] $Redemarrer,
    [switch] $Arreter,
    [switch] $Sauvegarder
)

$ErrorActionPreference = 'Continue'
if (-not (Test-Path $Etat)) { Write-Error "Fichier d'état introuvable : $Etat"; exit 1 }
$cfg = Get-Content $Etat -Raw -Encoding UTF8 | ConvertFrom-Json
$journal = Join-Path $cfg.base 'journaux\superviseur.log'
New-Item -ItemType Directory -Force (Split-Path $journal) | Out-Null

function Ecrire([string] $m) {
    $ligne = '{0:yyyy-MM-dd HH:mm:ss}  {1}' -f (Get-Date), $m
    Add-Content -Path $journal -Value $ligne -Encoding UTF8
    if (-not $script:Boucle) { Write-Host "  $m" }
}

function PortOuvert([int] $port) {
    $c = New-Object Net.Sockets.TcpClient
    try { $c.Connect('127.0.0.1', $port); return $true } catch { return $false } finally { $c.Close() }
}

function ProcessusDe([string] $exe) {
    Get-Process -ErrorAction SilentlyContinue | Where-Object { $_.Path -and $_.Path -ieq $exe }
}

function Lancer([string] $exe, [string] $arguments, [string] $dossier) {
    if (-not $dossier) { $dossier = Split-Path $exe }
    $p = @{ FilePath = $exe; WorkingDirectory = $dossier; WindowStyle = 'Hidden' }
    if ($arguments) { $p.ArgumentList = $arguments }
    Start-Process @p | Out-Null
}

# Les extensions de PHP (openssl, curl, intl...) chargent des DLL placées dans le dossier de PHP :
# il doit être dans le PATH des processus lancés (Apache en particulier).
$phpDir = Split-Path $cfg.php
if (($env:Path -split ';') -notcontains $phpDir) { $env:Path = "$phpDir;$env:Path" }
$env:PHP_FCGI_MAX_REQUESTS = '0'

function DemarrerBase {
    if ($cfg.pile -eq 'autonome' -and $cfg.mysql_service) {
        $s = Get-Service $cfg.mysql_service -ErrorAction SilentlyContinue
        if ($s -and $s.Status -ne 'Running') { Ecrire "Démarrage du service $($cfg.mysql_service)"; Start-Service $cfg.mysql_service }
        if ($s) { return }
    }
    if (-not (PortOuvert $cfg.port_bd)) {
        Ecrire "Démarrage de la base de données (port $($cfg.port_bd))"
        Lancer $cfg.mysqld "--defaults-file=`"$($cfg.myini)`"" ''
    }
}

function DemarrerWeb {
    if ($cfg.pile -eq 'autonome') {
        for ($i = 0; $i -lt $cfg.php_workers; $i++) {
            $p = [int]$cfg.port_php + $i
            if (-not (PortOuvert $p)) {
                Ecrire "Démarrage de PHP (FastCGI, port $p)"
                Lancer $cfg.php_cgi "-b 127.0.0.1:$p" $cfg.app
            }
        }
        if (-not (PortOuvert $cfg.port_web)) {
            Ecrire "Démarrage de Caddy (port $($cfg.port_web))"
            Lancer $cfg.caddy "run --config `"$($cfg.caddyfile)`" --adapter caddyfile" ''
        }
    } else {
        if (-not (PortOuvert $cfg.port_web)) {
            if ($cfg.httpd_conf) {
                # Instance Apache propre à SIGACI (port 80 indisponible) : programmes de Laragon, configuration de SIGACI.
                Ecrire "Démarrage d'Apache (instance SIGACI, port $($cfg.port_web))"
                Lancer $cfg.httpd "-f `"$($cfg.httpd_conf)`"" (Split-Path (Split-Path $cfg.httpd))
            } else {
                Ecrire "Démarrage d'Apache (Laragon, port $($cfg.port_web))"
                Lancer $cfg.httpd '' (Split-Path (Split-Path $cfg.httpd))
            }
        }
    }
}

function ArreterWeb {
    if ($cfg.pile -eq 'laragon' -and $cfg.httpd_conf) {
        # Seulement l'instance de SIGACI (et ses processus fils), jamais l'Apache de Laragon.
        $tous = @(Get-CimInstance Win32_Process -Filter "Name='httpd.exe'" -ErrorAction SilentlyContinue)
        $parents = @($tous | Where-Object { $_.CommandLine -and $_.CommandLine.Contains($cfg.httpd_conf) })
        $ids = @($parents.ProcessId) + @($tous | Where-Object { $_.ParentProcessId -in $parents.ProcessId } | ForEach-Object ProcessId)
        if ($ids.Count) {
            Ecrire 'Arrêt de httpd.exe (instance SIGACI)'
            $ids | Select-Object -Unique | ForEach-Object { Stop-Process -Id $_ -Force -ErrorAction SilentlyContinue }
        }
        Start-Sleep -Seconds 1
        return
    }
    $exes = if ($cfg.pile -eq 'autonome') { @($cfg.php_cgi, $cfg.caddy) } else { @($cfg.httpd) }
    foreach ($e in $exes) {
        $p = ProcessusDe $e
        if ($p) { Ecrire "Arrêt de $(Split-Path $e -Leaf)"; $p | Stop-Process -Force }
    }
    Start-Sleep -Seconds 1
}

function Php([string[]] $arguments) {
    $p = Start-Process -FilePath $cfg.php -ArgumentList $arguments -WorkingDirectory $cfg.app -WindowStyle Hidden -Wait -PassThru
    return $p.ExitCode
}

function ValeurEnv([string] $cle) {
    $outils = Join-Path $PSScriptRoot 'outils.php'
    return (& $cfg.php $outils lire-env $cfg.app $cle)
}

function SauvegarderMaintenant {
    $dest = Join-Path $cfg.base 'sauvegardes'
    New-Item -ItemType Directory -Force $dest | Out-Null
    $stamp = Get-Date -Format 'yyyy-MM-dd_HHmm'
    $sql = Join-Path $dest "sigaci_$stamp.sql"
    $env:MYSQL_PWD = ValeurEnv 'DB_PASSWORD'
    try {
        & $cfg.mysqldump "--host=127.0.0.1" "--port=$($cfg.port_bd)" "--user=$(ValeurEnv 'DB_USERNAME')" `
            --single-transaction --routines --triggers --default-character-set=utf8mb4 "--result-file=$sql" (ValeurEnv 'DB_DATABASE')
        $ok = ($LASTEXITCODE -eq 0)
    } finally { Remove-Item Env:MYSQL_PWD -ErrorAction SilentlyContinue }
    if (-not $ok) { Ecrire 'ERREUR : la sauvegarde de la base a échoué'; return $false }
    Copy-Item (Join-Path $cfg.app '.env') (Join-Path $dest "env_$stamp")
    Compress-Archive -Path $sql, (Join-Path $dest "env_$stamp") -DestinationPath (Join-Path $dest "sigaci_$stamp.zip") -Force
    Remove-Item $sql, (Join-Path $dest "env_$stamp")
    Get-ChildItem $dest -Filter 'sigaci_*.zip' | Where-Object { $_.LastWriteTime -lt (Get-Date).AddDays(-30) } | Remove-Item
    Ecrire "Sauvegarde : $dest\sigaci_$stamp.zip"
    return $true
}

# ------------------------------------------------------------------ commandes ponctuelles

if ($Sauvegarder) { if (SauvegarderMaintenant) { exit 0 } else { exit 1 } }
if ($Arreter) { ArreterWeb; exit 0 }
if ($Redemarrer) { ArreterWeb }
if ($UneFois -or $Redemarrer) { DemarrerBase; DemarrerWeb; exit 0 }

# ------------------------------------------------------------------ boucle permanente

$script:Boucle = $true
Ecrire "Superviseur démarré (pile $($cfg.pile))"
$prochainPlanif = Get-Date
while ($true) {
    try {
        DemarrerBase
        DemarrerWeb
        $maintenant = Get-Date
        if ($maintenant -ge $prochainPlanif) {
            $prochainPlanif = $maintenant.Date.AddHours($maintenant.Hour).AddMinutes($maintenant.Minute + 1)
            # Sans attendre la fin : la surveillance des services ne doit pas être retardée.
            Start-Process -FilePath $cfg.php -ArgumentList 'artisan', 'schedule:run' -WorkingDirectory $cfg.app -WindowStyle Hidden | Out-Null
        }
        # Sauvegarde quotidienne après 2 h 30, une fois par jour.
        $dejaFaite = Get-ChildItem (Join-Path $cfg.base 'sauvegardes') -Filter ("sigaci_{0:yyyy-MM-dd}_*.zip" -f $maintenant) -ErrorAction SilentlyContinue
        if ($maintenant.TimeOfDay -ge [TimeSpan]'02:30:00' -and -not $dejaFaite) { [void](SauvegarderMaintenant) }
    } catch {
        Ecrire "ERREUR : $($_.Exception.Message)"
    }
    Start-Sleep -Seconds 5
}
