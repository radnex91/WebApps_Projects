@echo off
chcp 65001 >nul
REM ============================================================
REM  PharmaCare — Interface de generation de codes de licence
REM  Double-clic pour lancer. Fermez la fenetre "Serveur Licence"
REM  pour arreter.
REM ============================================================

setlocal
set "TOOLS=%~dp0"
set "ROOT=%TOOLS%..\"

REM Localiser php.exe
set "PHP=C:\xampp\php\php.exe"
if not exist "%PHP%" (
    where php >nul 2>nul && (for /f "delims=" %%i in ('where php') do set "PHP=%%i") || (
        echo [ERREUR] PHP introuvable. Verifiez XAMPP (C:\xampp\php\php.exe).
        pause
        exit /b 1
    )
)

echo Lancement du serveur local sur http://127.0.0.1:8080 ...
start "PharmaCare - Serveur Licence" "%PHP%" -S 127.0.0.1:8080 -t "%TOOLS%"

REM Laisser le serveur demarrer, puis ouvrir le navigateur
timeout /t 2 /nobreak >nul
start "" "http://127.0.0.1:8080/gen_licence_gui.php"

endlocal