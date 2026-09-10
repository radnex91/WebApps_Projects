#!/usr/bin/env bash
# PharmaCare — build an UPDATE PATCH (delta) for an existing production install.
# Produces a small Windows .zip + Linux .tar.gz containing ONLY the changed files
# (under pharmacare/) + apply scripts that back up then overwrite. No DB, no reinstall.
#
# Usage: bash tools/build_patch.sh <from_version> <to_version> <files_list>
#   <from_version> e.g. 1.0.7   (only for naming/README)
#   <to_version>   e.g. 1.0.8
#   <files_list>   text file, one path per line, relative to pharmacare/
#                  (e.g. modules/produits.php, config/env.php, assets/css/style.css)
#
# Output: <pharmacare>/dist/pharmacare_patch_<from>_to_<to>.zip and ...tar.gz
set -euo pipefail

FROM="${1:?usage: build_patch.sh <from> <to> <files_list>}"
TO="${2:?usage: build_patch.sh <from> <to> <files_list>}"
LIST="${3:?usage: build_patch.sh <from> <to> <files_list>}"

ROOT="$(cd "$(dirname "$0")/.." && pwd)"          # pharmacare/
PTOOLS="$(cd "$(dirname "$0")" && pwd)/patch"     # tools/patch/
OUTDIR="$ROOT/dist"                               # pharmacare/dist/ (archives livrables)
mkdir -p "$OUTDIR"
NAME="pharmacare_patch_${FROM}_to_${TO}"
STAGING="$(cd "$(dirname "$0")" && pwd)/_patch_staging/${NAME}"

rm -rf "$STAGING"
mkdir -p "$STAGING/pharmacare"

# ── 0. Regenerer le manifeste d'integrite licence (fichiers couverts) ────────
# Toute edition d'un fichier couvert (config/licence.php, config/settings.php,
# modules/vente.php, modules/licence.php, includes/audit.php, includes/auth.php)
# invalide le manifeste. On le regenere systematiquement pour que le patch
# embarque un manifeste coherent avec les fichiers qu'il livre (sinon l'app
# signale « integrite compromise » apres application du patch).
PHP_BIN="/c/xampp/php/php.exe"
if [ -x "$PHP_BIN" ]; then
  echo "[patch] regeneration du manifeste d'integrite licence..."
  "$PHP_BIN" "$ROOT/tools/gen_licence.php" --json --op=integrity >/dev/null \
    || { echo "  [FAIL] regeneration du manifeste echouee (cle privee absente ?)"; exit 1; }
  echo "  [ok] config/licence_integrity.php regenere"
else
  echo "[patch] ATTENTION php absent : manifeste non regenere — VERIFIEZ-LE AVANT LIVRAISON."
fi

# ── 1. Copier les fichiers modifies sous pharmacare/ (chemins relatifs conserves) ──
n=0
while IFS= read -r line || [ -n "$line" ]; do
  line="${line%%#*}"          # strip comments
  line="$(echo "$line" | sed 's/^[[:space:]]*//;s/[[:space:]]*$//')"
  [ -z "$line" ] && continue
  if [ ! -f "$ROOT/$line" ]; then echo "!! fichier absent : $ROOT/$line" >&2; exit 1; fi
  mkdir -p "$STAGING/pharmacare/$(dirname "$line")"
  cp -p "$ROOT/$line" "$STAGING/pharmacare/$line"
  n=$((n+1))
done < "$LIST"
echo "[patch] $n fichier(s) a inclure."

# ── 1b. Inclure systematiquement le manifeste d'integrite regenere ──────────
# Meme un patch code-only doit livrer un manifeste coherent : si un fichier
# couvert a change, l'ancien manifeste de prod ne matchera plus. On l'inclut
# donc dans tous les patchs (inoffensif si aucun fichier couvert n'a change).
if [ -f "$ROOT/config/licence_integrity.php" ]; then
  mkdir -p "$STAGING/pharmacare/config"
  cp -p "$ROOT/config/licence_integrity.php" "$STAGING/pharmacare/config/licence_integrity.php"
  echo "[patch] manifeste d'integrite inclus (config/licence_integrity.php)."
fi

# ── 2. Scripts d'application (Windows + Linux) ──
cp -p "$PTOOLS/apply_patch.bat" "$STAGING/"
cp -p "$PTOOLS/apply_patch.ps1" "$STAGING/"
cp -p "$PTOOLS/apply_patch.sh"  "$STAGING/"
# GUI Windows : apply_patch.exe (assistant graphique generique, lit le nom du
# patch et APP_VERSION du payload ; meme style que install_prod.exe). Le .bat
# reste en secours (et pour les postes sans .NET Framework).
if [ -f "$PTOOLS/apply_patch.exe" ]; then
  cp -p "$PTOOLS/apply_patch.exe" "$STAGING/"
  echo "[patch] apply_patch.exe (GUI Windows) inclus."
fi

# ── 2b. Migrations BDD eventuelles (tools/patch/migrate_*.php) ────────────────
# Tout fichier tools/patch/migrate_*.php est inclus au patch et exécuté
# automatiquement (en idempotent) par apply_patch après copie des fichiers.
# Inoffensif si aucun fichier de migration n'est présent.
migcount=0
for mig in "$PTOOLS"/migrate_*.php; do
  [ -f "$mig" ] || continue
  cp -p "$mig" "$STAGING/"
  migcount=$((migcount+1))
done
if [ "$migcount" -gt 0 ]; then
  echo "[patch] $migcount migration(s) BDD incluse(s)."
fi

# ── 3. README (liste + instructions) ──
{
  echo "PharmaCare — patch de mise a jour ${FROM} -> ${TO}"
  echo "=================================================="
  echo
  echo "Ce patch contient UNIQUEMENT les fichiers modifies. Il ne touche pas a la"
  echo "base de donnees ni a votre configuration de production (env.prod.php)."
  echo
  echo "Fichiers inclus ($n) :"
  while IFS= read -r line || [ -n "$line" ]; do
    line="$(echo "${line%%#*}" | sed 's/^[[:space:]]*//;s/[[:space:]]*$//')"
    [ -z "$line" ] && continue
    echo "  - pharmacare/$line"
  done < "$LIST"
  echo "  - pharmacare/config/licence_integrity.php  (manifeste d'integrite regenere)"
  if [ "$migcount" -gt 0 ]; then
    echo "  + $migcount script(s) de migration BDD (migrate_*.php, idempotents)"
  fi
  echo
  echo "APPLICATION (Windows, XAMPP) :"
  echo "  1. Decompressez l'archive .zip ou vous voulez (ex: Bureau)."
  echo "  2. Double-cliquez sur apply_patch.exe (assistant graphique ; il detecte"
  echo "     l'installation, verifie la version, sauvegarde et applique)."
  echo "     Alternative sans GUI : double-cliquez sur apply_patch.bat."
  echo "  3. Confirmez le dossier d'installation (defaut C:\\xampp\\htdocs\\pharmacare)."
  echo "  4. Les anciens fichiers sont sauvegardes dans pharmacare\\_patch_backup\\<horodatage>."
  echo "  5. Les migrations BDD (index) sont executees automatiquement si php CLI est trouve."
  echo
  echo "APPLICATION (Linux, LAMPP) :"
  echo "  1. tar -xzf pharmacare_patch_${FROM}_to_${TO}.tar.gz"
  echo "  2. cd pharmacare_patch_${FROM}_to_${TO}"
  echo "  3. ./apply_patch.sh [/opt/lampp/htdocs/pharmacare]   (sudo si necessaire)"
  echo "  4. Les migrations BDD (index) sont executees automatiquement si php CLI est trouve."
  echo
  echo "Apres application : videz le cache du navigateur (le CSS/JS est cache-buste"
  echo "via APP_VERSION, desormais ${TO})."
  echo
  echo "ROLLBACK : restaurer les fichiers depuis pharmacare/_patch_backup/<horodatage>."
} > "$STAGING/PATCH_README.txt"

# ── 4. Archiver (Windows zip + Linux tar) ──
WIN_STAGING=$(cygpath -w "$STAGING" 2>/dev/null || echo "$STAGING")
WIN_OUT=$(cygpath -w "$OUTDIR" 2>/dev/null || echo "$OUTDIR")
ZIP="$OUTDIR/${NAME}.zip"
TAR="$OUTDIR/${NAME}.tar.gz"

rm -f "$ZIP" "$TAR"
powershell.exe -NoProfile -Command \
  "Compress-Archive -Path '$WIN_STAGING\\*' -DestinationPath '$(cygpath -w "$ZIP")' -Force"
tar -czf "$TAR" -C "$(dirname "$STAGING")" "$NAME"

echo "[patch] OK -> $ZIP"
echo "[patch] OK -> $TAR"