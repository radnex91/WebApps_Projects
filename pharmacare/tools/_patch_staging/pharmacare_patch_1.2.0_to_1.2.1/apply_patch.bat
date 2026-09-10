@echo off
REM PharmaCare — lanceur du patch (delta). Double-clic pour appliquer.
echo.
echo  PharmaCare - application du patch
echo  -------------------------------
echo.
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0apply_patch.ps1"