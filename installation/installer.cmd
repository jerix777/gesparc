@echo off
rem ============================================================================
rem  SIGACI - installation ou mise à jour automatique (Windows)
rem
rem  Double-cliquez sur ce fichier, ou lancez-le depuis l'invite de commandes.
rem  Première exécution : installe tout (PHP, base de données, serveur web, SIGACI).
rem  Exécutions suivantes : met SIGACI à jour.
rem
rem  Il lance installer.ps1 (PowerShell, présent sur tout Windows récent) avec les mêmes options :
rem    installer.cmd -Mode demo
rem    installer.cmd -Domaine sigaci.tpi-bouake.ci -AdminEmail informatique@tpi-bouake.ci
rem ============================================================================
setlocal
chcp 65001 >nul
set "ICI=%~dp0"
set "DOUBLECLIC="
echo %cmdcmdline% | find /i "%~0" >nul && set "DOUBLECLIC=1"

if not exist "%ICI%installer.ps1" (
  echo   [ERREUR] installer.ps1 introuvable : gardez les fichiers de l'installateur ensemble.
  set "CODE=1"
  goto fin
)
where powershell >nul 2>nul || (
  echo   [ERREUR] Windows PowerShell est introuvable sur ce poste.
  set "CODE=1"
  goto fin
)

powershell -NoProfile -ExecutionPolicy Bypass -File "%ICI%installer.ps1" %*
set "CODE=%ERRORLEVEL%"

:fin
if defined DOUBLECLIC (
  echo.
  pause
)
endlocal & exit /b %CODE%
