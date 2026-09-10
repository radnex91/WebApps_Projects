@echo off
chcp 65001 >nul
setlocal enableextensions enabledelayedexpansion

REM ============================================================
REM  PharmaCare - Installation locale (XAMPP / Windows)
REM  - importe database.sql
REM  - verifie la connexion PHP -> MySQL
REM  - affiche les comptes de demo et l'URL
REM  A executer depuis le dossier pharmacare (C:\xampp\htdocs\pharmacare)
REM ============================================================

echo.
echo ====================================================
echo   PharmaCare - Installation locale (XAMPP)
echo ====================================================
echo.

REM --- 0) Verifier qu'on est dans le dossier pharmacare ---
if not exist "config\env.php" (
  echo [ERREUR] Ce script doit etre execute depuis le dossier "pharmacare"
  echo          (celui qui contient index.php, config\, modules\, database.sql).
  echo          Place-toi dans C:\xampp\htdocs\pharmacare et relance install_local.bat
  echo.
  pause
  exit /b 1
)

if not exist "database.sql" (
  echo [ERREUR] database.sql introuvable dans le dossier courant.
  echo.
  pause
  exit /b 1
)

REM --- 1) Localiser mysql.exe ---
set "MYSQL=C:\xampp\mysql\bin\mysql.exe"
set "PHP=C:\xampp\php\php.exe"

if not exist "%MYSQL%" (
  echo [INFO] mysql.exe non trouve au chemin par defaut : %MYSQL%
  set /p "MYSQL=Indique le chemin complet vers mysql.exe (ou Entrée pour reessayer) : "
  if "!MYSQL!"=="" set "MYSQL=C:\xampp\mysql\bin\mysql.exe"
)
if not exist "%MYSQL%" (
  echo [ERREUR] mysql.exe introuvable : !MYSQL!
  echo          Verifie que MySQL/XAMPP est installe et demarre.
  pause
  exit /b 1
)

if not exist "%PHP%" (
  echo [INFO] php.exe non trouve au chemin par defaut : %PHP%
  set /p "PHP=Indique le chemin complet vers php.exe (ou Entrée pour reessayer) : "
  if "!PHP!"=="" set "PHP=C:\xampp\php\php.exe"
)
if not exist "%PHP%" (
  echo [ERREUR] php.exe introuvable : !PHP!
  pause
  exit /b 1
)

echo [OK] mysql  : %MYSQL%
echo [OK] php    : %PHP%
echo.

REM --- 2) Mot de passe root MySQL (vide par defaut sur XAMPP) ---
set "MYSQLPASS="
set /p "MYSQLPASS=Mot de passe root MySQL (vide par defaut sur XAMPP, taper Entrée) : "

REM Construire les args de connexion
set "MYSQLAUTH=-u root"
if not "!MYSQLPASS!"=="" set "MYSQLAUTH=-u root -p!MYSQLPASS!"

REM --- 3) Tester la connexion MySQL ---
echo.
echo [..] Test de la connexion MySQL...
"%MYSQL%" %MYSQLAUTH% -e "SELECT 1;" >nul 2>&1
if errorlevel 1 (
  echo [ERREUR] Connexion MySQL impossible.
  echo          - Verifie que MySQL est demarre dans le panneau XAMPP.
  echo          - Verifie le mot de passe root.
  pause
  exit /b 1
)
echo [OK] Connexion MySQL reussie.

REM --- 4) Importer database.sql ---
echo.
echo [..] Import de database.sql (creation de la base + tables + donnees de demo)...
"%MYSQL%" %MYSQLAUTH% < database.sql
if errorlevel 1 (
  echo [ERREUR] L'import de database.sql a echoue.
  echo          Si la base existait deja avec un schema different, supprime-la
  echo          dans phpMyAdmin puis relance ce script.
  pause
  exit /b 1
)
echo [OK] Import termine.

REM --- 5) Verification PHP -> MySQL (getDB + compte des tables) ---
echo.
echo [..] Verification de la connexion PHP -> MySQL...

"%PHP%" install_verify.php > "%TEMP%\pc_verify_out.txt" 2>&1
set /p "VOUT=" < "%TEMP%\pc_verify_out.txt"

echo %VOUT% | findstr /C:"FAIL" >nul
if errorlevel 1 (
  echo [OK] Verification PHP reussie : %VOUT%
) else (
  echo [ERREUR] Verification PHP echouee : %VOUT%
  echo          Verifie config/env.php ^(DB_HOST/DB_USER/DB_PASS^) et que MySQL tourne.
  pause
  exit /b 1
)

REM --- 6) Verifier que le dossier est bien dans htdocs ---
echo.
set "CUR=%CD%"
echo %CUR% | findstr /I /C:"htdocs" >nul
if errorlevel 1 (
  echo [ATTENTION] Le dossier courant ne semble pas etre sous "htdocs" :
  echo              %CUR%
  echo              Pour que http://localhost/pharmacare fonctionne, le dossier
  echo              pharmacare doit etre dans C:\xampp\htdocs\
  echo              Tu peux quand meme tester en pointant Apache sur ce dossier.
) else (
  echo [OK] Dossier situe sous htdocs : %CUR%
)

REM --- 7) Recapitulatif ---
echo.
echo ====================================================
echo   INSTALLATION TERMINEE
echo ====================================================
echo.
echo   1) Demarre Apache ET MySQL dans le panneau XAMPP
echo      ^(si ce n'est pas deja fait^).
echo.
echo   2) Ouvre ton navigateur sur :
echo        http://localhost/pharmacare
echo.
echo   3) Connecte-toi avec un compte de demo :
echo        admin      / password
echo        pharmacien / password
echo        caissier   / password
echo.
echo   4) Flux de test : ouvrir une caisse -> encaisser une
echo      vente -> livrer une commande -> transferer Magasin
echo      vers Pharmacie -> consulter la Comptabilite.
echo.
echo   Note : mode DEV ^(comptes de demo affiches sur la page de
echo   login, erreurs PHP visibles^). Ne pas exposer sur Internet.
echo   Pour une mise en prod, voir deploy\DEPLOIEMENT.md
echo.
pause
endlocal