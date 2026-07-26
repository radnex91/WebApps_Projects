#!/usr/bin/env bash
# -*- coding: utf-8 -*-
# Recompile tools/gen_licence.exe depuis tools/gen_licence_gui.cs (csc .NET 4).
# Outil DEV-ONLY : reste dans tools/ (jamais dans les bundles de deploiement).
#   bash tools/build_licence_gui.sh
set -euo pipefail

SRC="/c/xampp/htdocs/pharmacare"
CSC="/c/Windows/Microsoft.NET/Framework64/v4.0.30319/csc.exe"
FRAME='C:\Windows\Microsoft.NET\Framework64\v4.0.30319'
OUT="$SRC/tools/gen_licence.exe"

if [ ! -x "$CSC" ]; then
  echo "[ERREUR] csc.exe introuvable : $CSC"
  exit 1
fi

"$CSC" -nologo -target:winexe -platform:anycpu -optimize+ \
  -win32manifest:"$(cygpath -w "$SRC/tools/gen_licence.manifest")" \
  -reference:"$FRAME\System.Windows.Forms.dll" \
  -reference:"$FRAME\System.Drawing.dll" \
  -reference:"$FRAME\System.dll" \
  -reference:"$FRAME\System.Web.Extensions.dll" \
  -out:"$(cygpath -w "$OUT")" \
  "$(cygpath -w "$SRC/tools/gen_licence_gui.cs")" 2>&1 | grep -vE '^\s*$' || true

if [ ! -f "$OUT" ]; then
  echo "[ERREUR] Compilation échouée."
  exit 1
fi
echo "[ok] $OUT $(stat -c%s "$OUT") octets"