#!/usr/bin/env bash
# -*- coding: utf-8 -*-
# Construit le bundle PORTABLE du gestionnaire de licences :
#   dist/PharmaCare-Licence-Manager/            (dossier)
#   dist/PharmaCare-Licence-Manager-<v>-portable.zip
#
# Bundle utilisable sur toute machine Windows 10/11 x64 SANS XAMPP ni PHP :
# un runtime PHP 8.2 minimal est embarqué dans tools/php/.
#
# ⚠ DEV-ONLY : le bundle contient la clé privée RSA et le secret HMAC.
#   dist/ est git-ignoré — ne jamais livrer aux clients.
#
# Usage : bash tools/build_licence_portable.sh   (depuis la racine du projet)
set -euo pipefail

SRC="/c/xampp/htdocs/pharmacare"
DIST="$SRC/dist/PharmaCare-Licence-Manager"
XAMPP_PHP="/c/xampp/php"
VERSION="1.4.1"

cd "$SRC"

# ── 0) Recompiler l'exe GUI depuis gen_licence_gui.cs ──────────────────────
bash tools/build_licence_gui.sh

# ── 1) Structure : miroir du layout applicatif (licence_lib.php remonte     ──
#      d'un niveau pour trouver config/, modules/, includes/) ────────────────
rm -rf "$DIST"
mkdir -p "$DIST/tools/php/ext" "$DIST/tools/php/extras/openssl" \
         "$DIST/config" "$DIST/modules" "$DIST/includes"

# ── 2) Scripts + clés + ledger (sensibles — dev only) ──────────────────────
cp tools/gen_licence.exe tools/gen_licence.php tools/licence_lib.php \
   tools/licence_privatekey.php tools/licence_secret.php \
   tools/licence_ledger.json "$DIST/tools/"

# ── 3) Fichiers couverts par le manifeste d'intégrité ──────────────────────
cp config/licence.php config/settings.php   "$DIST/config/"
cp modules/vente.php modules/licence.php    "$DIST/modules/"
cp includes/audit.php includes/auth.php     "$DIST/includes/"

# ── 4) Runtime PHP portable minimal (openssl requis, le reste est in-core) ──
cp "$XAMPP_PHP/php.exe" "$XAMPP_PHP/php8ts.dll" \
   "$XAMPP_PHP/libcrypto-3-x64.dll" "$XAMPP_PHP/libssl-3-x64.dll" \
   /c/Windows/System32/vcruntime140.dll /c/Windows/System32/vcruntime140_1.dll \
   "$DIST/tools/php/"
cp "$XAMPP_PHP/ext/php_openssl.dll"            "$DIST/tools/php/ext/"
cp "$XAMPP_PHP/extras/openssl/openssl.cnf"     "$DIST/tools/php/extras/openssl/"

# php.ini minimal (chargé automatiquement : il est à côté de php.exe)
cat > "$DIST/tools/php/php.ini" <<'EOF'
; PharmaCare — Gestionnaire de licences : runtime PHP portable minimal.
; Seule l'extension openssl est requise par tools/licence_lib.php
; (signature RSA des codes longs et du manifeste d'intégrité).
; hash, json, SPL, pcre et random sont intégrés au cœur de PHP 8.2.

extension_dir = "ext"
extension=openssl

memory_limit = 256M
max_execution_time = 120
display_errors = Off
log_errors = Off
EOF

# ── 5) LISEZMOI ────────────────────────────────────────────────────────────
cat > "$DIST/LISEZMOI.txt" <<'EOF'
══════════════════════════════════════════════════════════════════════
  PharmaCare — Gestionnaire de licences PORTABLE (v1.4.0)
══════════════════════════════════════════════════════════════════════

DÉMARRAGE
  Double-cliquez :   tools\gen_licence.exe
  Aucune installation : PHP 8.2 est embarqué dans tools\php\ (aucun XAMPP,
  aucun PHP requis). Windows 10/11 x64 uniquement.

CONTENU
  tools\gen_licence.exe         → interface graphique (double-clic)
  tools\gen_licence.php         → moteur CLI (mode --json piloté par l'exe)
  tools\licence_lib.php         → logique crypto (RSA + HMAC + ledger)
  tools\licence_privatekey.php  → CLÉ PRIVÉE RSA (signe les codes longs)
  tools\licence_secret.php      → secret HMAC (signe les codes courts)
  tools\licence_ledger.json     → ledger : cap / counter par client
  tools\php\                    → runtime PHP 8.2 portable
  config\ modules\ includes\    → copies des fichiers « couverts », servant
                                  uniquement à la régénération du manifeste
                                  d'intégrité (ne pas déployer depuis ici)

⚠ CONFIDENTIEL — NE JAMAIS TRANSMETTRE À UN CLIENT
  Ce dossier contient la CLÉ PRIVÉE et le secret HMAC : quiconque le
  possède peut générer des licences valables. Conservez-le sur une
  machine de confiance (idéalement hors ligne). Ne le mettez ni dans un
  dépôt git, ni dans un bundle de déploiement client.

⚠ SYNCHRONISATION DU LEDGER (important si vous utilisez plusieurs machines)
  Chaque copie a son propre tools\licence_ledger.json. Si vous émettez des
  codes depuis deux machines sans synchroniser :
    - le mode « Pack additionnel » sera calculé sur un historique faux ;
    - les codes longs (counter anti-rejeu) peuvent devenir inutilisables
      chez le client (« code déjà utilisé ou obsolète »).
  → Avant de travailler depuis une machine secondaire, recopiez le
    licence_ledger.json de la machine principale ; recopiez-le ensuite
    vers la machine principale après la session.

⚠ PALIER GRATUIT (LICENCE_FREE_CAP)
  Le bouton « Enregistrer » (Palier gratuit) modifie config\licence.php DE
  CE DOSSIER uniquement. Répercuitez la valeur dans le dépôt principal
  (pharmacare\config\licence.php) et régénérez le manifeste d'intégrité
  avant tout déploiement.

MAINTENANCE
  Après toute modification des fichiers couverts ou de licence_lib.php sur
  la machine de développement, reconstruisez ce bundle :
      bash tools/build_licence_portable.sh
  (à lancer depuis la racine du projet, machine de dev)
EOF

# ── 6) Auto-test du bundle (status + génération sur copie temporaire) ──────
TMPB="$(mktemp -d)/bundle"
cp -r "$DIST" "$TMPB"
STATUS="$("$TMPB/tools/php/php.exe" "$TMPB/tools/gen_licence.php" --json --op=status)"
echo "$STATUS" | grep -q '"priv_ok":true' || { echo "[ERREUR] auto-test status: $STATUS"; exit 1; }
GEN="$("$TMPB/tools/php/php.exe" "$TMPB/tools/gen_licence.php" --json --op=generate \
        --instance=BUILD-SELFTEST --format=short --mode=set --value=1234)"
echo "$GEN" | grep -q '"ok":true' || { echo "[ERREUR] auto-test generate: $GEN"; exit 1; }
rm -rf "$(dirname "$TMPB")"
echo "[auto-test] status + génération de code : OK (sur copie temporaire)"

# ── 7) Zip ─────────────────────────────────────────────────────────────────
ZIP="$SRC/dist/PharmaCare-Licence-Manager-$VERSION-portable.zip"
rm -f "$ZIP"
powershell.exe -NoProfile -Command "Compress-Archive -Path 'C:\xampp\htdocs\pharmacare\dist\PharmaCare-Licence-Manager' -DestinationPath 'C:\xampp\htdocs\pharmacare\dist\PharmaCare-Licence-Manager-$VERSION-portable.zip' -Force" >/dev/null 2>&1

echo "[ok] $DIST"
echo "[ok] $ZIP ($(stat -c%s "$ZIP") octets)"