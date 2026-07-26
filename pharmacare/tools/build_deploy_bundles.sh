#!/usr/bin/env bash
# -*- coding: utf-8 -*-
# Reconstruit les 2 bundles de deploiement PharmaCare a partir du working dir.
#   - pharmacare_deploy.zip        (Windows : install_prod.bat + install_prod.ps1)
#   - pharmacare_deploy_linux.tar.gz (Linux  : install_prod.sh)
# Inclut data/ (catalogue d'import client). Exclut tout ce qui est dev/sensible
# (voir memoire : deploy-bundle-no-dev-credentials, always-rebuild-deploy-bundles).
set -euo pipefail

SRC="/c/xampp/htdocs/pharmacare"
OUT_DIR="/c/xampp/htdocs"
ZIP_WIN="${OUT_DIR}/pharmacare_deploy.zip"
TAR_LINUX="${OUT_DIR}/pharmacare_deploy_linux.tar.gz"

BASE="/c/Users/RADNEX/AppData/Local/Temp/pc_rebuild"
BASE_WIN='C:\Users\RADNEX\AppData\Local\Temp\pc_rebuild'
STAGING="$BASE/staging/pharmacare"

CSC="/c/Windows/Microsoft.NET/Framework64/v4.0.30319/csc.exe"
FRAME_WIN='C:\Windows\Microsoft.NET\Framework64\v4.0.30319'

# Etape 0 : recompiler install_prod.exe depuis la source (garde binaire == source)
if [ -x "$CSC" ]; then
  echo "[0/7] Recompilation de install_prod.exe (csc .NET Framework)..."
  "$CSC" -nologo -target:winexe -platform:anycpu -optimize+ \
    -win32manifest:"$(cygpath -w "$SRC/tools/install_prod.manifest")" \
    -reference:"$FRAME_WIN\System.Windows.Forms.dll" \
    -reference:"$FRAME_WIN\System.Drawing.dll" \
    -reference:"$FRAME_WIN\System.dll" \
    -out:"$(cygpath -w "$SRC/install_prod.exe")" \
    "$(cygpath -w "$SRC/tools/install_prod_gui.cs")" 2>&1 | grep -vE '^\s*$' || true
  echo "  [ok] install_prod.exe $(stat -c%s "$SRC/install_prod.exe") octets"
else
  echo "[0/7] csc absent : on garde l'install_prod.exe existant."
fi

# Etape 0b : regenerer le manifeste d'integrite licence AVANT le staging.
# Le bundle doit embarquer un manifeste coherent avec les fichiers couverts
# qu'il contient (config/licence.php, config/settings.php, modules/vente.php,
# modules/licence.php, includes/audit.php, includes/auth.php). Sinon l'app
# signale « integrite compromise » des la premiere installation.
PHP_BIN="/c/xampp/php/php.exe"
if [ -x "$PHP_BIN" ]; then
  echo "[0b/7] Regeneration du manifeste d'integrite licence..."
  "$PHP_BIN" "$SRC/tools/gen_licence.php" --json --op=integrity >/dev/null \
    || { echo "  [FAIL] regeneration du manifeste echouee (cle privee absente ?)"; exit 1; }
  echo "  [ok] config/licence_integrity.php regenere"
else
  echo "[0b/7] ATTENTION php absent : manifeste non regenere — VERIFIEZ-LE AVANT LIVRAISON."
fi

echo "[1/7] Nettoyage + preparation staging..."
rm -rf "$BASE"
mkdir -p "$STAGING"

# Copie curatee via tar (gere les exclusions + fichiers caches type .htaccess)
echo "[2/7] Copie curatee (avec exclusions)..."
cd "$SRC"
tar -cf - \
  --exclude='./tools' \
  --exclude='./backups' \
  --exclude='./docs' \
  --exclude='./.claude' \
  --exclude='./.impeccable' \
  --exclude='./.superpowers' \
  --exclude='./.git' \
  --exclude='./config/.rate_limit' \
  --exclude='./config/env.prod.php' \
  --exclude='./_archive' \
  --exclude='*.bak' \
  --exclude='*.log' \
  --exclude='*.xlsx' \
  --exclude='*.pdf' \
  --exclude='*composer*' \
  --exclude='*phpunit*' \
  --exclude='*_mvt_check*' \
  --exclude='*alter_db*' \
  --exclude='*test_import*' \
  --exclude='./licence_privatekey.php' \
  --exclude='./licence_secret.php' \
  --exclude='./licence_ledger.json' \
  --exclude='*licence_instance_id*' \
  --exclude='./PHARMACARE_REBUILD_PROMPT.md' \
  --exclude='./apercu.html' \
  --exclude='./presentation.html' \
  . | tar -xf - -C "$STAGING"

# Reintegrer uniquement le seed propre (database.sql) dans _archive/
echo "[3/7] Reintegration de _archive/database.sql (seed propre)..."
mkdir -p "$STAGING/_archive"
cp "$SRC/_archive/database.sql" "$STAGING/_archive/database.sql"

# Verifications de curation
echo "[4/7] Verifications de curation..."
assert_absent () { local f="$STAGING/$1"; if [ -e "$f" ]; then echo "  [FAIL] $1 present !"; exit 1; else echo "  [ok] absent: $1"; fi; }
assert_absent "tools"
assert_absent "backups"
assert_absent "docs"
assert_absent ".claude"
assert_absent "_archive/alter_db.php"
assert_absent "_archive/composer.json"
assert_absent "_archive/phpunit.xml"
assert_absent "config/env.prod.php"
if ls "$STAGING"/*.xlsx 2>/dev/null | head -1 | grep -q .; then echo "  [FAIL] xlsx present"; exit 1; fi
echo "  [ok] aucun .xlsx"
if [ ! -f "$STAGING/_archive/database.sql" ]; then echo "  [FAIL] database.sql manquant"; exit 1; fi
echo "  [ok] _archive/database.sql present ($(stat -c%s "$STAGING/_archive/database.sql") octets)"
if [ ! -f "$STAGING/data/import_articles_hopitaux_cliniques_cm.csv" ]; then echo "  [FAIL] data/csv manquant"; exit 1; fi
echo "  [ok] data/import_articles_hopitaux_cliniques_cm.csv present"

# Preparation des 2 arbres (installers differs par plateforme)
echo "[5/7] Arbres par plateforme..."
mkdir -p "$BASE/win" "$BASE/linux"
cp -r "$STAGING" "$BASE/win/pharmacare"
cp -r "$STAGING" "$BASE/linux/pharmacare"
# Windows : install_prod.exe (GUI) + install_prod.ps1 (logique) ; pas de .sh
rm -f "$BASE/win/pharmacare/install_prod.sh"
# Linux : install_prod.sh uniquement ; pas de .bat/.ps1/.exe (binaires Windows inutiles)
rm -f "$BASE/linux/pharmacare/install_prod.bat" \
      "$BASE/linux/pharmacare/install_prod.ps1" \
      "$BASE/linux/pharmacare/install_prod.exe"

# Verif installers par plateforme
echo "  win  : $(ls "$BASE/win/pharmacare" | grep -E '^install_prod' | tr '\n' ' ')"
echo "  linux: $(ls "$BASE/linux/pharmacare" | grep -E '^install_prod' | tr '\n' ' ')"

# Bundle Linux (tar - chemin MSYS obligatoire)
echo "[6/7] Bundle Linux (tar.gz)..."
rm -f "$TAR_LINUX"
cd "$BASE/linux"
tar -czf "$TAR_LINUX" pharmacare
echo "  [ok] $TAR_LINUX ($(du -h "$TAR_LINUX" | cut -f1))"

# Bundle Windows (zip via PowerShell - chemins Windows obligatoires)
echo "[7/7] Bundle Windows (zip)..."
rm -f "$ZIP_WIN"
PS1="$BASE/_zip.ps1"
cat > "$PS1" <<'PSEOF'
Add-Type -AssemblyName System.IO.Compression.FileSystem
$src = 'C:\Users\RADNEX\AppData\Local\Temp\pc_rebuild\win\pharmacare'
$dst = 'C:\xampp\htdocs\pharmacare_deploy.zip'
[System.IO.Compression.ZipFile]::CreateFromDirectory($src, $dst, [System.IO.Compression.CompressionLevel]::Optimal, $true)
Write-Output ("ZIP_OK:" + (Get-Item $dst).Length)
PSEOF
powershell -NoProfile -ExecutionPolicy Bypass -File "$BASE_WIN/_zip.ps1"

echo
echo "=== Bundles regeneres ==="
ls -lh "$ZIP_WIN" "$TAR_LINUX"
echo
echo "Contenu (top-level) Windows zip :"
powershell -NoProfile -Command "Add-Type -AssemblyName System.IO.Compression.FileSystem; \$z=[System.IO.Compression.ZipFile]::OpenRead('C:\xampp\htdocs\pharmacare_deploy.zip'); \$z.Entries | Where-Object { \$_.FullName -notmatch '/' } | ForEach-Object { \$_.FullName }; \$z.Dispose()" 2>/dev/null || true
echo "--- Linux tar (top-level + data) ---"
tar -tzf "$TAR_LINUX" | grep -E '^pharmacare/[^/]*/?$' | head -30
echo "data/ dans tar :"
tar -tzf "$TAR_LINUX" | grep '^pharmacare/data/' | head
echo "Done."