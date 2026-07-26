# PharmaCare — application d'un patch (delta) sur une install existante.
# Sauvegarde les fichiers existants avant de les ecraser. Aucune BDD, aucune reinstall.
# Lanceur : apply_patch.bat (double-clic). Interactif (console).
param([string]$Live = "C:\xampp\htdocs\pharmacare")
$ErrorActionPreference = 'Stop'
$PatchRoot = Split-Path -Parent $MyInvocation.MyCommand.Path
$Src = Join-Path $PatchRoot 'pharmacare'

function Confirm-Live([string]$p) { return (Test-Path (Join-Path $p 'config\env.php')) -and (Test-Path (Join-Path $p 'index.php')) }

if (-not (Confirm-Live $Live)) {
    Write-Host "Dossier PharmaCare non detecte : $Live" -ForegroundColor Yellow
    $Live = Read-Host "Saisissez le chemin complet du dossier pharmacare (ex: C:\xampp\htdocs\pharmacare)"
    if (-not (Confirm-Live $Live)) { Write-Host "Chemin invalide (config\env.php ou index.php absent). Abandon." -ForegroundColor Red; exit 1 }
}
if (-not (Test-Path $Src)) { Write-Host "Source du patch manquante : $Src" -ForegroundColor Red; exit 1 }

$ts = Get-Date -Format 'yyyyMMdd_HHmmss'
$Bak = Join-Path $Live "_patch_backup\$ts"
Write-Host ""
Write-Host "Installation PharmaCare : $Live" -ForegroundColor Cyan
Write-Host "Backup des fichiers existants : $Bak" -ForegroundColor Cyan
Write-Host ""

$files = Get-ChildItem -Path $Src -Recurse -File
$applied = 0; $backed = 0
foreach ($f in $files) {
    $rel = $f.FullName.Substring($Src.Length + 1)
    $dst = Join-Path $Live $rel
    if (Test-Path $dst) {
        $bdst = Join-Path $Bak $rel
        $null = New-Item -ItemType Directory -Force -Path (Split-Path $bdst)
        Copy-Item $dst $bdst -Force
        Write-Host "  [backup] $rel"
        $backed++
    }
    $null = New-Item -ItemType Directory -Force -Path (Split-Path $dst)
    Copy-Item $f.FullName $dst -Force
    Write-Host "  [apply]  $rel" -ForegroundColor Green
    $applied++
}

Write-Host ""
Write-Host "Termine : $applied fichier(s) applique(s), $backed sauvegarde(s)." -ForegroundColor Green
Write-Host "Backup disponible dans : $Bak" -ForegroundColor Cyan
Write-Host "Conseil : videz le cache du navigateur (CSS/JS sont cache-bustes via APP_VERSION)." -ForegroundColor Yellow
Write-Host ""
pause