#!/usr/bin/env bash
# PharmaCare — application d'un patch (delta) sur une install existante (Linux/XAMPP/LAMP).
# Sauvegarde les fichiers existants avant de les ecraser. Aucune BDD, aucune reinstall.
# Usage : ./apply_patch.sh [dossier_pharmacare]   (defaut : /opt/lampp/htdocs/pharmacare)
set -euo pipefail

SRC="$(cd "$(dirname "$0")" && pwd)/pharmacare"
PATCHROOT="$(cd "$(dirname "$0")" && pwd)"
LIVE="${1:-/opt/lampp/htdocs/pharmacare}"

confirm_live() { [ -f "$1/config/env.php" ] && [ -f "$1/index.php" ]; }

if ! confirm_live "$LIVE"; then
  echo "Dossier PharmaCare non detecte : $LIVE" >&2
  read -r -p "Chemin du dossier pharmacare : " LIVE
  if ! confirm_live "$LIVE"; then echo "Chemin invalide (config/env.php ou index.php absent). Abandon." >&2; exit 1; fi
fi
if [ ! -d "$SRC" ]; then echo "Source du patch manquante : $SRC" >&2; exit 1; fi

TS="$(date +%Y%m%d_%H%M%S)"
BAK="$LIVE/_patch_backup/$TS"
SUDO=""
[ -w "$LIVE" ] || SUDO=sudo

echo
echo " Installation PharmaCare : $LIVE"
echo " Backup : $BAK"
echo

applied=0; backed=0
cd "$SRC"
while IFS= read -r -d '' f; do
  rel="${f#./}"
  dst="$LIVE/$rel"
  if [ -f "$dst" ]; then
    $SUDO mkdir -p "$BAK/$(dirname "$rel")"
    $SUDO cp -p "$dst" "$BAK/$rel"
    echo "  [backup] $rel"
    backed=$((backed+1))
  fi
  $SUDO mkdir -p "$LIVE/$(dirname "$rel")"
  $SUDO cp -p "$f" "$dst"
  echo "  [apply]  $rel"
  applied=$((applied+1))
done < <(find . -type f -print0)

echo
echo " Termine : $applied fichier(s) applique(s), $backed sauvegarde(s)."
echo " Backup : $BAK"

# ── Migrations BDD idempotentes (migrate_*.php a cote du script) ─────────────
shopt -s nullglob
migs=( "$PATCHROOT"/migrate_*.php )
shopt -u nullglob
if [ "${#migs[@]}" -gt 0 ]; then
  PHP_BIN=""
  if command -v php >/dev/null 2>&1; then PHP_BIN="$(command -v php)"
  elif [ -x /opt/lampp/bin/php ]; then PHP_BIN=/opt/lampp/bin/php
  elif [ -x /usr/bin/php ];   then PHP_BIN=/usr/bin/php
  fi
  if [ -z "$PHP_BIN" ]; then
    echo
    echo " Migrations BDD presentes mais php CLI introuvable."
    echo " Executez manuellement, ex :"
    for m in "${migs[@]}"; do echo "   /opt/lampp/bin/php \"$m\" \"$LIVE\""; done
  else
    echo
    echo " Execution des migrations BDD (index) via $PHP_BIN..."
    for m in "${migs[@]}"; do
      echo "  -> $(basename "$m")"
      $SUDO "$PHP_BIN" "$m" "$LIVE" || echo "  [echec] $(basename "$m")"
    done
  fi
fi

echo " Conseil : videz le cache du navigateur (CSS/JS cache-bustes via APP_VERSION)."