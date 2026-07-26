#!/usr/bin/env bash
# pharmaCare — Installation production LAMP (Ubuntu / Zorin / Debian)
# -----------------------------------------------------------------------------
# Équivalent Linux de install_prod.ps1. À exécuter sur le PC SERVEUR Linux,
# en racine (sudo), depuis le dossier pharmacare/ :
#
#     sudo bash install_prod.sh
#
# Étapes :
#   1. Vérifie la distribution + installe la pile LAMP si manquante (apt)
#   2. Détection de l'IP LAN + nom d'hôte (ex. pharmacare.lan)
#   3. Crée la base `pharmacare` + un user DB dédié `pharmacare` (pas root)
#   4. Importe le schéma (_archive/database.sql)
#   5. Génère config/env.prod.php (APP_URL=http://<hôte>)
#   6. Change les mots de passe des comptes démo (admin, pharmacien, caissier)
#   7. Configure Apache (mod_rewrite, mod_headers, VirtualHost racine <hôte>)
#   8. Permissions www-data (config/.rate_limit, backups, env.prod.php)
#   9. Pare-feu ufw (port 80) si actif
#  10. Résumé + smoke test
#
# L'app est servie à la racine de http://<hôte> (ex. http://pharmacare.lan) via
# un VirtualHost dédié. Le sous-dossier http://<IP>/pharmacare reste accessible
# (fallback). Le serveur se résout lui-même via /etc/hosts ; les postes clients
# doivent résoudre <hôte> -> IP serveur (DNS local / routeur / hosts client).
#
# Prérequis : connexion Internet (si la pile LAMP doit être installée),
# ou LAMP déjà en place (apache2, mysql/mariadb, php 8.1+).
# -----------------------------------------------------------------------------
set -euo pipefail

# ── Variables ────────────────────────────────────────────────
APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SQL_FILE="$APP_DIR/_archive/database.sql"
ENV_FILE="$APP_DIR/config/env.prod.php"
APACHE_CONF="/etc/apache2/conf-available/pharmacare.conf"
APACHE_SITE="/etc/apache2/sites-available/pharmacare.conf"
DB_NAME="pharmacare"
DB_USER="pharmacare"

# ── Couleurs ─────────────────────────────────────────────────
C='\033[36m'; Y='\033[33m'; G='\033[32m'; R='\033[31m'; B='\033[1m'; W='\033[0m'
say()  { echo -e "${C}$1${W}"; }
ok()   { echo -e "  ${G}OK${W} : $1"; }
warn() { echo -e "  ${Y}$1${W}"; }
die()  { echo -e "\n${R}ERREUR${W} : $1\n"; exit 1; }

# ── Bannière ─────────────────────────────────────────────────
echo ""
echo -e "  ${C}============================================${W}"
echo -e "  ${C} ${B}PharmaCare — Installation Production LAMP${W}${C}   ${W}"
echo -e "  ${C}============================================${W}"
echo ""

# ── Root requis ──────────────────────────────────────────────
[ "$(id -u)" -eq 0 ] || die "Exécutez en racine : ${B}sudo bash install_prod.sh${W}"

# ── [1/10] Prérequis + pile LAMP ─────────────────────────────
say "[1/10] Vérification des prérequis..."
. /etc/os-release 2>/dev/null || die "Distribution non reconnue (pas de /etc/os-release). Prévu pour Debian/Ubuntu/Zorin."
echo -e "  Distribution : ${B}$PRETTY_NAME${W}"

need=0
command -v apache2   >/dev/null 2>&1 || need=1
command -v mysql     >/dev/null 2>&1 || need=1
command -v mysqldump >/dev/null 2>&1 || need=1
command -v php       >/dev/null 2>&1 || need=1
# Extensions PHP requises
ext_missing=0
if command -v php >/dev/null 2>&1; then
  for ext in pdo_mysql mbstring openssl xml; do
    php -m 2>/dev/null | grep -qi "^$ext$" || ext_missing=1
  done
fi
[ "$need" -eq 0 ] && [ "$ext_missing" -eq 0 ] || {
  warn "Composants manquants — installation de la pile LAMP via apt..."
  export DEBIAN_FRONTEND=noninteractive
  apt-get update -y
  apt-get install -y \
    apache2 mysql-server \
    php libapache2-mod-php php-mysql php-mbstring php-xml php-intl php-curl php-gd php-zip \
    >/dev/null || die "Échec apt-get install. Vérifiez la connexion Internet et les dépôts."
  ok "Pile LAMP installée."
}

# Démarrer les services
systemctl enable --now apache2 >/dev/null 2>&1 || true
systemctl enable --now mysql   >/dev/null 2>&1 || systemctl enable --now mariadb >/dev/null 2>&1 || true

# Vérifications finales
command -v apache2   >/dev/null 2>&1 || die "apache2 toujours absent."
command -v mysql     >/dev/null 2>&1 || die "mysql toujours absent."
command -v php       >/dev/null 2>&1 || die "php toujours absent."
for ext in pdo_mysql mbstring openssl xml; do
  php -m 2>/dev/null | grep -qi "^$ext$" || die "Extension PHP '$ext' manquante (apt install php-$ext)."
done
[ -f "$SQL_FILE" ] || die "Schéma introuvable : $SQL_FILE\nExécutez ce script depuis le dossier pharmacare/."
ok "Apache, MySQL, PHP, extensions et schéma SQL détectés."

# ── [2/10] IP LAN + nom d'hôte ───────────────────────────────
say "[2/10] Détection de l'IP LAN + nom d'hôte..."
LAN_IP="$(hostname -I 2>/dev/null | awk '{print $1}')"
[ -n "$LAN_IP" ] || die "Aucune IP LAN détectée (hostname -I vide). Vérifiez le réseau."
ok "IP LAN : $LAN_IP"
echo -e "  ${Y}Nom d'hôte pour l'accès (ex. pharmacare.lan).${W}"
read -rp "  Nom d'hôte souhaité (Entrée = pharmacare.lan) : " HOSTNAME_APP
[ -n "$HOSTNAME_APP" ] || HOSTNAME_APP="pharmacare.lan"
# Validation : lettres/chiffres/tiret/point, pas de schéma ni de slash.
echo "$HOSTNAME_APP" | grep -Eq '^[A-Za-z0-9]([A-Za-z0-9.-]*[A-Za-z0-9])?$' \
  || die "Nom d'hôte invalide : '$HOSTNAME_APP' (lettres, chiffres, '.' et '-' uniquement)."
ok "Hôte : $HOSTNAME_APP"

# ── [3/10] MySQL : base + user dédié ─────────────────────────
say "[3/10] Configuration MySQL (base + user dédié)..."
# Connexion root : socket (Ubuntu par défaut) ou mot de passe demandé.
ROOT_ARGS=()
if mysql -u root -e "SELECT 1;" >/dev/null 2>&1; then
  ROOT_ARGS=(-u root)
  ok "Connexion root via socket (sans mot de passe)."
else
  echo -e "  ${Y}Le root MySQL requiert un mot de passe.${W}"
  read -rsp "  Mot de passe root MySQL : " ROOTPASS; echo
  if ! mysql -u root -p"$ROOTPASS" -e "SELECT 1;" >/dev/null 2>&1; then
    die "Mot de passe root incorrect."
  fi
  ROOT_ARGS=(-u root -p"$ROOTPASS")
  ok "Mot de passe root vérifié."
fi

DB_PASS="$(openssl rand -hex 16 2>/dev/null || head -c 16 /dev/urandom | xxd -p)"
[ -n "$DB_PASS" ] || die "Génération du mot de passe DB impossible."

mysql "${ROOT_ARGS[@]}" <<SQL
CREATE DATABASE IF NOT EXISTS \`$DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
DROP USER IF EXISTS '$DB_USER'@'localhost';
CREATE USER '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASS';
GRANT ALL PRIVILEGES ON \`$DB_NAME\`.* TO '$DB_USER'@'localhost';
GRANT PROCESS ON *.* TO '$DB_USER'@'localhost';
FLUSH PRIVILEGES;
SQL
ok "Base '$DB_NAME' + user '$DB_USER' créés (mot de passe généré, écrit dans env.prod.php)."

# ── [4/10] Import du schéma ──────────────────────────────────
say "[4/10] Import du schéma (database.sql)..."
if ! mysql -u "$DB_USER" -p"$DB_PASS" --default-character-set=utf8mb4 "$DB_NAME" < "$SQL_FILE"; then
  die "Échec import du schéma SQL."
fi
TABLES="$(mysql -u "$DB_USER" -p"$DB_PASS" -N -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$DB_NAME';")"
ok "$TABLES tables importées."

# ── [5/10] env.prod.php ──────────────────────────────────────
say "[5/10] Génération de config/env.prod.php..."
APP_URL="http://$HOSTNAME_APP"
cat > "$ENV_FILE" <<PHP
<?php
return [
    'DB_HOST' => '127.0.0.1',
    'DB_NAME' => '$DB_NAME',
    'DB_USER' => '$DB_USER',
    'DB_PASS' => '$DB_PASS',
    'APP_URL' => '$APP_URL',
];
PHP
ok "$ENV_FILE créé (APP_URL=$APP_URL)"

# ── [6/10] Mots de passe des comptes démo ────────────────────
say "[6/10] Changement des mots de passe des comptes démo..."
for LOGIN in admin pharmacien caissier; do
  echo ""
  echo -e "  ${B}Compte : $LOGIN${W}"
  while true; do
    read -rsp "    Nouveau mot de passe (8+ car.) : " P1; echo
    read -rsp "    Confirmer                       : " P2; echo
    [ "$P1" = "$P2" ] || { warn "Les mots de passe ne correspondent pas."; continue; }
    [ "${#P1}" -ge 8 ] || { warn "Minimum 8 caractères."; continue; }
    break
  done
  HASH="$(printf '%s' "$P1" | php -r 'echo password_hash(trim(fgets(STDIN)), PASSWORD_DEFAULT);')"
  [ -n "$HASH" ] || die "Échec génération hash pour $LOGIN."
  mysql -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" \
    -e "UPDATE utilisateurs SET mot_de_passe='$HASH' WHERE login='$LOGIN';" \
    || die "Échec mise à jour mot de passe $LOGIN."
  ok "Mot de passe de '$LOGIN' changé."
done

# ── [7/10] Apache ────────────────────────────────────────────
say "[7/10] Configuration Apache (mod_rewrite, mod_headers, VirtualHost racine)..."
a2enmod rewrite >/dev/null 2>&1 || true
a2enmod headers >/dev/null 2>&1 || true

# (a) Directory global : autorise + AllowOverride All pour le dossier app.
#     Sert aussi le fallback sous-dossier http://<IP>/pharmacare via le site par défaut.
cat > "$APACHE_CONF" <<CONF
<Directory "$APP_DIR">
    AllowOverride All
    Require all granted
</Directory>
CONF
a2enconf pharmacare >/dev/null 2>&1 || true

# (b) VirtualHost dédié : sert l'app à la racine de http://<hôte>.
#     Idempotent : on réécrit le fichier à chaque exécution (pas de doublon).
SHORT_HOST="$(echo "$HOSTNAME_APP" | awk -F. '{print $1}')"
cat > "$APACHE_SITE" <<CONF
# >>> PharmaCare VirtualHost (install_prod.sh) >>>
<VirtualHost *:80>
    ServerName $HOSTNAME_APP
    ServerAlias $SHORT_HOST pharmacare.local pharmacare
    DocumentRoot "$APP_DIR"
    <Directory "$APP_DIR">
        AllowOverride All
        Require all granted
        DirectoryIndex index.php
    </Directory>
    ErrorLog  \${APACHE_LOG_DIR}/pharmacare_error.log
    CustomLog \${APACHE_LOG_DIR}/pharmacare_access.log combined
</VirtualHost>
# <<< PharmaCare VirtualHost <<<
CONF
a2ensite pharmacare >/dev/null 2>&1 || true

# (c) Résolution locale sur le serveur lui-même (127.0.0.1 <hôte>).
HOSTS_FILE="/etc/hosts"
if ! grep -Eq "[[:space:]]$HOSTNAME_APP([[:space:]]|\$)" "$HOSTS_FILE"; then
  printf '127.0.0.1 %s\n' "$HOSTNAME_APP" >> "$HOSTS_FILE"
  ok "Entrée /etc/hosts ajoutée : 127.0.0.1 $HOSTNAME_APP"
else
  ok "/etc/hosts contient déjà $HOSTNAME_APP."
fi

ok "mod_rewrite + mod_headers activés, VirtualHost $HOSTNAME_APP -> $APP_DIR."
systemctl restart apache2 || die "Échec redémarrage Apache."
ok "Apache redémarré."

# ── [8/10] Permissions www-data ──────────────────────────────
say "[8/10] Permissions d'écriture (www-data)..."
mkdir -p "$APP_DIR/config/.rate_limit" "$APP_DIR/backups"
# .htaccess anti-listing dans backups/
[ -f "$APP_DIR/backups/.htaccess" ] || printf 'Require all denied\nDeny from all\nOptions -Indexes\n' > "$APP_DIR/backups/.htaccess"
chown -R www-data:www-data "$APP_DIR/config/.rate_limit" "$APP_DIR/backups"
chmod 700 "$APP_DIR/config/.rate_limit" "$APP_DIR/backups"
# env.prod.php contient le mot de passe DB → lecture www-data uniquement.
chown www-data:www-data "$ENV_FILE" 2>/dev/null || true
chmod 600 "$ENV_FILE" 2>/dev/null || true
ok "config/.rate_limit, backups et env.prod.php protégés (www-data)."

# ── [9/10] Pare-feu ufw ──────────────────────────────────────
say "[9/10] Pare-feu..."
if command -v ufw >/dev/null 2>&1 && ufw status 2>/dev/null | grep -qi "Status: active"; then
  ufw allow 80/tcp >/dev/null 2>&1 || true
  ok "ufw : port 80/tcp autorisé."
else
  warn "ufw inactif ou absent — port 80 laissé ouvert (vérifiez votre pare-feu)."
fi

# ── [10/10] Résumé + smoke test ──────────────────────────────
say "[10/10] Résumé + smoke test..."
echo ""
echo -e "  ${G}============================================${W}"
echo -e "  ${G} ${B}INSTALLATION TERMINÉE${W}"
echo -e "  ${G}============================================${W}"
echo ""
echo -e "  URL (ce PC)   : ${B}$APP_URL${W}"
echo -e "  URL (LAN)     : ${B}$APP_URL${W}  (depuis tout poste qui résout $HOSTNAME_APP)"
echo -e "  Fallback      : http://$LAN_IP/pharmacare  (sans DNS configuré)"
echo ""
echo -e "  ${B}Résolution du nom (postes clients) :${W}"
echo "   Configurez votre routeur / DNS local pour :"
echo -e "     ${B}$HOSTNAME_APP  ->  $LAN_IP${W}"
echo "   (ou une réservation DHCP IP fixe + entrée DNS locale)."
echo "   En attendant, chaque poste peut ajouter à son fichier hosts :"
echo "     $LAN_IP  $HOSTNAME_APP"
echo ""
echo -e "  ${B}Prochaines étapes :${W}"
echo "   1. Ouvrez $APP_URL dans un navigateur"
echo "   2. Connectez-vous avec admin / <votre mot de passe>"
echo "   3. Vérifiez que le bloc « Comptes de démonstration » a disparu"
echo "   4. Testez une vente, une caisse, un transfert de stock"
echo "   5. Administration → Licence : palier gratuit 10 000, intégrité OK"
echo ""
# Smoke test : on teste le VirtualHost via l'en-tête Host (le serveur se résout
# lui-même via /etc/hosts ajouté à l'étape 7).
if command -v curl >/dev/null 2>&1; then
  CODE_HOME="$(curl -s -o /dev/null -w '%{http_code}' -H "Host: $HOSTNAME_APP" "http://127.0.0.1/" 2>/dev/null || echo 000)"
  CODE_ENV="$(curl -s -o /dev/null -w '%{http_code}' -H "Host: $HOSTNAME_APP" "http://127.0.0.1/config/env.php" 2>/dev/null || echo 000)"
  echo -e "  ${B}Smoke test :${W}"
  echo "   - $APP_URL → HTTP $CODE_HOME (302/200 = OK)"
  echo "   - .../config/env.php → HTTP $CODE_ENV (403 = attendu)"
fi
echo ""
echo -e "  ${B}Sauvegarde automatique (cron) :${W}"
echo "   0 2 * * *  mysqldump -u $DB_USER -p\"<pass>\" $DB_NAME > /var/backups/pharmacare_\$(date +\%Y\%m\%d).sql"
echo "   (mot de passe dans config/env.prod.php — depuis l'app : Administration → Sauvegarde)"
echo ""