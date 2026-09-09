# PharmaCare — Guide de mise en production

Version applicable : **v1.3.1** (XAMPP LAN ou LAMP Linux).

Deux méthodes d'installation sont disponibles :

| Méthode | Plateforme | Quand l'utiliser |
|---------|------------|------------------|
| **Installation automatisée** (recommandée) | Windows (XAMPP) ou Linux (LAMP) | Nouvelle installation sur le PC serveur |
| **Installation manuelle** | Toutes | Diagnostic, déploiement personnalisé, hébergement mutualisé |

---

## A. Installation automatisée (recommandée)

### A.1. Préparer le PC serveur

- **Windows** : XAMPP installé (`C:\xampp`), Apache et MySQL démarrés.
- **Linux** : Ubuntu / Debian / Zorin (pile LAMP installable automatiquement par le script).
- Droits administrateur requis (le script configure Apache, le pare-feu, et le fichier hosts).

### A.2. Obtenir le bundle de déploiement

Le développeur génère deux bundles via `tools/build_deploy_bundles.sh` :

```
pharmacare_deploy.zip            → Windows (install_prod.exe + install_prod.ps1)
pharmacare_deploy_linux.tar.gz   → Linux   (install_prod.sh)
```

Le bundle est curaté : il **exclut** tous les fichiers de développement et sensibles
(`tools/`, `backups/`, `.git/`, `.claude/`, `config/env.prod.php`, `*.xlsx`, `*.pdf`,
clés privées de licence, etc.) et **inclut** uniquement l'app + le seed SQL
(`_archive/database.sql`) + les données d'import client (`data/`).

### A.3. Installation Windows (XAMPP LAN)

1. Décompresser `pharmacare_deploy.zip` dans `C:\xampp\htdocs\` → dossier `pharmacare/`.
2. **Double-cliquer sur `install_prod.exe`** (GUI) — ou exécuter `install_prod.ps1` en PowerShell admin.

Le script effectue automatiquement (9 étapes) :

1. Vérifie les prérequis (MySQL, PHP, Apache, schema SQL)
2. Détecte l'IP LAN + propose un nom d'hôte (défaut : `pharmacare.lan`)
3. Sécurise le compte root MySQL (mot de passe fort)
4. Crée la base `pharmacare` + importe le schéma (`database.sql`)
5. Génère `config/env.prod.php` avec l'IP/hostname détecté
6. Hash et change les mots de passe des 3 comptes démo (admin, pharmacien, caissier)
7. Configure Apache : `mod_rewrite`, `mod_headers`, VirtualHost racine, `httpd-vhosts.conf`, fichier `hosts`
8. Ouvre le pare-feu Windows (port 80, profil Privé)
9. Résumé + smoke test (HTTP 302/200 sur la racine, 403 sur `config/env.php`)

**Mode non-interactif (GUI)** : les mots de passe transitent via un fichier JSON
temporaire (effacé après lecture), jamais sur la ligne de commande.

### A.4. Installation Linux (LAMP)

1. `tar -xzf pharmacare_deploy_linux.tar.gz` → dossier `pharmacare/`.
2. `cd pharmacare && sudo bash install_prod.sh`

Le script effectue automatiquement (10 étapes) :

1. Vérifie la distribution + installe la pile LAMP si manquante (apt)
2. Détection IP LAN + nom d'hôte
3. Crée la base `pharmacare` + un **user DB dédié** `pharmacare` (pas root) avec mot de passe aléatoire
4. Importe le schéma (`database.sql`)
5. Génère `config/env.prod.php`
6. Change les mots de passe des 3 comptes démo
7. Configure Apache (`mod_rewrite`, `mod_headers`, VirtualHost racine)
8. Permissions `www-data` (`config/.rate_limit`, `backups/`, `env.prod.php` en `600`)
9. Pare-feu ufw (port 80) si actif
10. Résumé + smoke test

### A.5. Résolution du nom d'hôte (postes clients)

Le serveur se résout lui-même via `hosts` (ajouté automatiquement). Les postes
clients doivent résoudre le nom d'hôte → IP du serveur :

- **Recommandé** : configurer le routeur / DNS local (`pharmacare.lan = 192.168.x.x`).
- **Fallback** : ajouter manuellement `192.168.x.x pharmacare.lan` au fichier `hosts` de chaque poste.
- **Fallback direct** : `http://<IP_SERVEUR>/pharmacare` (sans DNS).

---

### A.6. Reprise après coupure de courant (offline-first)

| Événement | Comportement |
|---|---|
| **Serveur s'éteint brusquement** | Les postes caisse passent en file d'attente (pill rouge) : les ventes sont conservées dans le navigateur (clé d'idempotence `client_ref`), la caisse continue de vendre |
| **Serveur redémarre** | Apache/MySQL redémarrent automatiquement (services Windows en Automatique — configurés par l'installateur, étape `[+] Services Windows`) ; MySQL rejoue son journal InnoDB : les ventes commitées sont à l'abri, les transactions en vol sont annulées proprement. Dès le retour, la pill repasse au vert et les ventes en attente se retransmettent **sans doublon** (PK UNIQUE `ventes.client_ref`) |
| **Terminal s'éteint brusquement** | La vente est écrite dans la file **avant** l'envoi : elle survit au reboot. Si elle était en vol au moment de la coupure, elle est rejouée à la réouverture — le serveur la dédoublonne si elle avait déjà été enregistrée |
| **MySQL redémarre seul** (mises à jour) | Pill rouge « Base de données indisponible », file d'attente, reprise automatique |

Vérifications conseillées après installation : services `mysql` et `Apache2.4`
présents et en démarrage type **Automatique** (`Get-Service mysql, Apache2.4`),
puis un test réel : arrêt brutal du serveur pendant une vente → constater la
file d'attente sur le poste → redémarrage → reprise et transmission sans doublon.

---

## B. Installation manuelle

### B.1. Préparer la cible

- PHP **≥ 8.0** (8.2 recommandé) avec `pdo_mysql`, `mbstring`, `openssl`, `xml`.
- MySQL/MariaDB dédié.
- Apache 2.4 avec `mod_rewrite`, `mod_headers`.

### B.2. Déployer le code (whitelist)

Ne déployer QUE les fichiers nécessaires. **Exclure** :

```
tools/                  # outils développeur (licence, patches, build)
backups/                # sauvegardes BDD
_archive/               # archives (sauf _archive/database.sql à importer)
.git/ .claude/          # métadonnées de dev
config/env.prod.php     # configuration prod (à créer sur place)
config/.rate_limit/     # state rate-limit (runtime)
*.xlsx *.pdf *.md       # fichiers non applicatifs
*.bak *.log             # fichiers temporaires
licence_privatekey.php  # clé privée de licence (JAMAIS livrée)
licence_secret.php      # secret HMAC (JAMAIS livré)
licence_ledger.json     # journal de licence (JAMAIS livré)
```

Garder (à déployer) :

```
index.php  dashboard.php  *.php (racine applicative)
config/    includes/   modules/   assets/   data/
.htaccess  install_prod.*  (si installation automatisée)
```

Exemple rsync :

```bash
rsync -av --delete \
  --exclude tools/ --exclude backups/ --exclude _archive/ --exclude .git/ --exclude .claude/ \
  --exclude 'config/env.prod.php' --exclude 'config/.rate_limit' \
  --exclude '*.md' --exclude '*.pdf' --exclude '*.xlsx' --exclude '*.bak' --exclude '*.log' \
  --exclude 'licence_privatekey.php' --exclude 'licence_secret.php' --exclude 'licence_ledger.json' \
  ./ user@serveur:/var/www/pharmacare/
```

Après copie :

```bash
chown -R www-data:www-data /var/www/pharmacare
chmod -R 750 /var/www/pharmacare
chmod 700 /var/www/pharmacare/config/.rate_limit
chmod 600 /var/www/pharmacare/config/env.prod.php
```

### B.3. Configuration production

Créer `config/env.prod.php` (non committé, dans `.gitignore`) :

```php
<?php
return [
    'DB_HOST' => '127.0.0.1',
    'DB_NAME' => 'pharmacare',
    'DB_USER' => 'pharmacare_user',     // user dédié, privilèges minimaux
    'DB_PASS' => '<mot de passe fort aléatoire>',
    'APP_URL' => 'http://pharmacare.lan',
];
```

Vérifier : `IS_PROD` devient vrai automatiquement (présence de `env.prod.php`).

### B.4. Base de données

```sql
CREATE DATABASE pharmacare CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'pharmacare_user'@'localhost' IDENTIFIED BY '<pass>';
GRANT ALL PRIVILEGES ON pharmacare.* TO 'pharmacare_user'@'localhost';
FLUSH PRIVILEGES;
```

```bash
mysql -u pharmacare_user -p pharmacare < _archive/database.sql
```

**Changer immédiatement** les mots de passe des 3 comptes seed (admin/pharmacien/caissier),
ou les supprimer et créer les vrais utilisateurs via l'UI.

### B.5. Apache (VirtualHost)

```apache
<VirtualHost *:80>
    ServerName pharmacare.lan
    DocumentRoot "/var/www/pharmacare"
    <Directory "/var/www/pharmacare">
        AllowOverride All
        Require all granted
        DirectoryIndex index.php
    </Directory>
</VirtualHost>
```

Activer `mod_rewrite`, `mod_headers`, puis `systemctl restart apache2`.

---

## C. Système de licence

PharmaCare utilise un système de licence par code d'activation qui plafonne
l'usage à un nombre de lignes de vente (`vente_lignes`).

- **Palier gratuit** : `LICENCE_FREE_CAP` lignes (défaut configurable dans `config/licence.php`).
- Au-delà, le client obtient un code d'activation auprès du développeur.

### Types de codes

| Type | Format | Sécurité | Usage |
|------|--------|----------|-------|
| **Code long (RSA)** | `base64url(payload).base64url(sig)` | Signature RSA-2048/SHA-256, lié à l'instance | Activation officielle |
| **Code court (SMS)** | `LLLL-NNNNNN-CCCCCCCC` | HMAC-SHA256, lié à l'instance | Activation rapide par SMS |

Chaque code est lié à l'`INSTANCE_ID` de la pharmacie (généré paresseusement,
stocké en BDD). Anti-rejeu : compteur strictement croissant par code.

### Intégrité des fichiers

Un manifeste d'intégrité (`config/licence_integrity.php`) signe les empreintes
SHA-256 des fichiers critiques (`config/licence.php`, `config/settings.php`,
`modules/vente.php`, `modules/licence.php`, `includes/audit.php`,
`includes/auth.php`). L'app vérifie la signature (clé publique embarquée) puis
recompare les empreintes. Toute édition est détectée et journalisée.

### Outils développeur (à ne JAMAIS livrer au client)

```
tools/gen_licence_keypair.php   — génère le keypair RSA
tools/gen_licence.php           — génère un code pour une instance + pack
tools/gen_licence_secret.php    — génère le secret HMAC (codes courts)
tools/licence_privatekey.php    — clé privée (gitignorée)
tools/licence_ledger.json       — cap/counter par instance (gitignoré)
```

### Vérification post-installation

Dans l'app : **Administration → Licence** → le palier gratuit doit être actif
et l'intégrité OK.

---

## D. Mises à jour (patches)

Pour mettre à jour une installation existante sans réinstaller :

### D.1. Construire un patch (côté développeur)

```bash
bash tools/build_patch.sh <from_version> <to_version> <files_list>
```

Exemples :
```bash
bash tools/build_patch.sh 1.2.3 1.2.4 tools/patch_files_1.2.3_to_1.2.4.txt
bash tools/build_patch.sh 1.2.4 1.3.1 tools/patch_files_1.2.4_to_1.3.1.txt
```

Liste des fichiers du patch : **delta exact calculé par comparaison de hash**
contre la baseline déployée (staging du dernier patch cumulé, sinon HEAD git).
Outil d'audit des écarts : `php tools/audit_permissions.php` (permissions) ;
pour le périmètre de fichiers, reproduire la comparaison staging-vs-arbre.

Migrations 1.3.1 embarquées (idempotentes, exécutées automatiquement) :
- `migrate_permissions_1.3.1.php` — permissions manquantes (marketing.*,
  suivi_caissiers.*, enligne.voir, rapports_caissier.voir, assistant.utiliser)
  + attributions marketing aux rôles directeur/informaticien
  + paramètre `delai_inactivite_min` = 3 min (politique 1.3.1).
- `migrate_role_magasinier_1.3.1.php` — rôle « Magasinier » (réceptionnaire)
  + son périmètre (magasin, commandes.voir/modifier, stock.voir, produits.voir).
- `migrate_ventes_offline_1.3.1.php` — colonne `ventes.client_ref` + index
  UNIQUE : idempotence des ventes rejouées hors ligne (anti-doublon).

Produit :
- `pharmacare_patch_<from>_to_<to>.zip` (Windows)
- `pharmacare_patch_<from>_to_<to>.tar.gz` (Linux)

Le patch contient **uniquement** les fichiers modifiés + le manifeste
d'intégrité regénéré + d'éventuelles migrations BDD (`migrate_*.php`).

### D.2. Appliquer un patch (côté client)

**Windows** :
1. Décompresser le `.zip`.
2. Double-cliquer sur `apply_patch.bat`.
3. Confirmer le dossier d'installation (`C:\xampp\htdocs\pharmacare`).
4. Les anciens fichiers sont sauvegardés dans `pharmacare/_patch_backup/<horodatage>`.
5. Les migrations BDD sont exécutées automatiquement si PHP CLI est trouvé.

**Linux** :
```bash
tar -xzf pharmacare_patch_<from>_to_<to>.tar.gz
cd pharmacare_patch_<from>_to_<to>
sudo ./apply_patch.sh /var/www/pharmacare
```

**Rollback** : restaurer les fichiers depuis `pharmacare/_patch_backup/<horodatage>`.

---

## E. Vérifications pré-déploiement (smoke test)

- [ ] Accéder à `http://pharmacare.lan/` → page de login (SANS le bloc « Comptes de démonstration », gated `!IS_PROD`).
- [ ] `http://…/config/env.php` → **403** (protégé par `.htaccess`).
- [ ] `http://…/config/.rate_limit/` → **403**.
- [ ] `http://…/backups/` → **403**.
- [ ] `http://…/tools/` → **403**.
- [ ] Headers présents (DevTools Network) : `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`, `Content-Security-Policy`, `Referrer-Policy`, `Permissions-Policy`.
- [ ] Login avec un vrai compte (nouveau mot de passe).
- [ ] Ouvrir une caisse → encaisser une vente → imprimer le ticket (police Manrope self-hostée, hors-ligne OK).
- [ ] Livraison commande → `stock_magasin` augmente ; transfert magasin→pharmacie → `stock` augmente.
- [ ] Consulter la comptabilité → écritures générées.
- [ ] Administration → Licence : palier gratuit actif, intégrité OK.
- [ ] Aucune erreur PHP dans les logs (`/var/log/pharmacare/` ou logs XAMPP).

---

## F. Sauvegarde automatique

```bash
# Linux — /etc/cron.daily/pharmacare-backup
mysqldump --single-transaction --routines -u pharmacare_user -p'<pass>' pharmacare \
  | gzip > /var/backups/pharmacare/$(date +%F).sql.gz
# rotation 7 jours + copie offsite
```

Depuis l'app : **Administration → Sauvegarde** (téléchargement via PHP, jamais
servi directement par Apache — `backups/` bloqué par `.htaccess`).

---

## G. HTTPS (optionnel, recommandé pour WAN)

- [ ] Activer HTTPS (certificat TLS — Let's Encrypt ou auto-signé en LAN).
- [ ] Dans `.htaccess` : décommenter la redirection HTTP → HTTPS + HSTS.
- [ ] `session.cookie_secure` suit `IS_PROD` automatiquement.

---

## Récapitulatif du hardening en place

| Domaine | Mesure |
|---------|--------|
| Accès | `.htaccess` : `config/`, `sql/`, `_archive/`, `tools/`, `backups/`, `.git` → 403 |
| Headers | CSP, X-Frame-Options DENY, nosniff, Referrer-Policy, Permissions-Policy (HSTS à activer) |
| Erreurs | `display_errors=Off` forcé en prod + filet de sécurité (hôte non-localhost) |
| CSRF | Actions destructives en POST + CSRF token |
| Anti-escalade | Un utilisateur ne peut pas modifier son propre rôle |
| XSS | `json_encode` au lieu d'`e()` dans `roles.php` |
| Rate-limit | `.htaccess` Apache 2.4 (`Require all denied`) |
| Sessions | `use_strict_mode` ; `cookie_secure` suit `IS_PROD` |
| Assets | Cache-busting `?v=APP_VERSION` ; polices self-hostées (woff2 locaux) |
| Perf | Pagination serveur sur ventes_hist, rapports, produits |
| Licence | Codes signés RSA-2048 + manifeste d'intégrité des fichiers critiques |
| Clean URLs | `.htaccess` : `/<segment>` → `modules/<file>.php` (fonctionne dev + prod) |
| Pages d'erreur | 403.php, 404.php, 500.php brandées (ErrorDocument en prod) |

---

## Reste à faire (Phase 2 — non couvert ici)

- XSS DOM résiduel (`innerHTML` non échappé) → `escHtml` central + `json_encode` flags.
- `verifyCsrf()` : `hash_equals` + HTTP 419 au lieu de `die()`.
- Index BDD manquants + prédicats `DATE(created_at)` non sargables.
- N+1 (commandes, comptabilité, marketing, utilisateurs).
- Transactions manquantes (clôture caisse, rôles, suppression commande, update utilisateur).
- Script `sql/clean_demo.sql`.