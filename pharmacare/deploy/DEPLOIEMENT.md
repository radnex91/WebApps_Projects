# PharmaCare — Guide de mise en production

Checklist consolidée (Phase 1 hardening déjà appliqué dans le code). Suivre dans l'ordre.

## 1. Préparer la cible (serveur)

- [ ] PHP **≥ 8.0** (8.2 recommandé) avec `pdo_mysql`, `mbstring`, `openssl`.
- [ ] MySQL/MariaDB dédié.
- [ ] Apache 2.4 avec `mod_rewrite`, `mod_headers`, `mod_ssl`.
- [ ] **DocumentRoot dédié** pour pharmacare (vhost isolé — voir `deploy/pharmacare.vhost.conf`).
      Ne JAMAIS cohabiter avec les autres projets du dépôt (medicore, app, atlasprime,
      pharmacare_backup_cart) : créer `/var/www/pharmacare` dédié.
- [ ] Créer le dossier de logs : `mkdir -p /var/log/pharmacare && chown www-data: /var/log/pharmacare`.

## 2. Déployer le code (whitelist — ne pas tout copier)

Ne déployer QUE les fichiers nécessaires. **Exclure** :

```
tests/                  # suite de tests
deploy/                 # vhost example + ce guide
sql/                    # migrations (jouées à part)
database.sql            # schéma + seeds (ne pas exposer)
*.md                    # docs
*.pdf                   # présentations
alter_db.php            # (déjà supprimé, ne pas réintégrer)
_mvt_check.js           # stray
{config,includes,assets # dossier typo (nettoyer)
pharmacare_backup_cart/ # sauvegarde (ne pas déployer)
```

Garder (à déployer) :

```
index.php  dashboard.php  *.php (racine applicative)
config/    includes/   modules/   assets/
.htaccess
```

Exemple rsync :

```bash
rsync -av --delete \
  --exclude tests/ --exclude deploy/ --exclude sql/ --exclude '*.md' --exclude '*.pdf' \
  --exclude 'database.sql' --exclude '_mvt_check.js' --exclude 'pharmacare_backup_cart/' \
  --exclude 'vendor/' --exclude '.phpunit.cache/' \
  --exclude 'config/env.prod.php' \
  ./ user@serveur:/var/www/pharmacare/
```

Après copie :

```bash
chown -R www-data:www-data /var/www/pharmacare
chmod -R 750 /var/www/pharmacare
chmod 700 /var/www/pharmacare/config/.rate_limit
```

## 3. Configuration production

- [ ] Créer `config/env.prod.php` (NON committé, dans `.gitignore`) :

```php
<?php
return [
    'DB_HOST' => '127.0.0.1',
    'DB_NAME' => 'pharmacare',
    'DB_USER' => 'pharmacare_user',     // user dédié, privilèges minimaux sur pharmacare seulement
    'DB_PASS' => '<mot de passe fort aléatoire>',
    'APP_URL' => 'https://pharmacare.exemple.cm',
];
```

- [ ] Vérifier : `IS_PROD` devient vrai automatiquement (présence de `env.prod.php`).
- [ ] Activer HTTPS (certificat TLS — Let's Encrypt). **Obligatoire** :
      `session.cookie_secure=IS_PROD` sinon les cookies ne sont pas envoyés → boucle de login.
- [ ] Vhost : `SetEnv PHARMACARE_ENV prod` + directives `php_admin_value` (voir `pharmacare.vhost.conf`).
- [ ] Dans `pharmacare/.htaccess` : décommenter la redirection HTTPS + HSTS une fois TLS actif.

## 4. Base de données

- [ ] Créer la base + un user dédié : `CREATE DATABASE pharmacare CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;`
- [ ] Importer le schéma : `mysql -u pharmacare_user -p pharmacare < database.sql` (depuis le poste admin, pas sur le serveur web).
- [ ] **Changer immédiatement les mots de passe des 3 comptes seed** (admin/pharmacien/caissier),
      ou les supprimer et créer les vrais utilisateurs via l'UI. Ne JAMAIS laisser `password`.
- [ ] (Optionnel) Nettoyer les données démo : voir `sql/` pour un script `clean_demo.sql` à créer
      (DELETE sur ventes/vente_lignes/commandes/produits démo ; garder plan_comptable, exercices, rôles, permissions, caisses, paramètres).
- [ ] **Sauvegarde automatique** (CRITICAL) :
      ```bash
      # /etc/cron.daily/pharmacare-backup
      mysqldump --single-transaction --routines -u pharmacare_user -p'<pass>' pharmacare | gzip > /var/backups/pharmacare/$(date +%F).sql.gz
      # rotation 7 jours + copie offsite
      ```

## 5. Vérifications pré-déploiement (smoke test)

- [ ] Accéder à `https://pharmacare.exemple.cm/` → page de login (SANS le bloc "Comptes de démonstration", gated `!IS_PROD`).
- [ ] `https://…/config/env.php` → **403** (protégé par `.htaccess`).
- [ ] `https://…/database.sql` → **403**.
- [ ] `https://…/config/.rate_limit/` → **403**.
- [ ] Headers présents (DevTools Network) : `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`, `Content-Security-Policy`, `Referrer-Policy`, HSTS.
- [ ] Login avec un vrai compte (nouveau mot de passe).
- [ ] Ouvrir une caisse → encaisser une vente → imprimer le ticket (police Manrope/DM Mono rendue — self-hostée, hors-ligne OK).
- [ ] Livraison commande → `stock_magasin` augmente ; transfert magasin→pharmacie → `stock` augmente.
- [ ] Consulter la comptabilité → écritures générées.
- [ ] Aucune erreur PHP dans `/var/log/pharmacare/php_errors.log`.

## 6. Post-déploiement

- [ ] Surveiller les logs (erreurs, CSRF refusés, rate-limit).
- [ ] Vérifier la rotation des logs (logrotate).
- [ ] Planifier un test de restauration de sauvegarde.

---

## Récapitulatif du hardening déjà en place (Phase 1 + 3)

| Domaine | Mesure |
|---------|--------|
| Accès | `.htaccess` pharmacare : deny `config/`, `sql/`, `*.sql/log/md/bak/env`, `alter_db`, `_mvt_check` |
| Headers | CSP, X-Frame-Options DENY, nosniff, Referrer-Policy, Permissions-Policy (HSTS à activer) |
| Erreurs | `display_errors=Off` forcé au plus tôt dans `config/env.php` |
| CSRF | Actions destructives (produits/commandes/catégories/rôles/utilisateurs) en POST + CSRF |
| Anti-escalade | Un utilisateur ne peut plus modifier son propre rôle |
| XSS | `roles.php` onclick → `json_encode` au lieu de `e()` |
| Rate-limit | `.htaccess` Apache 2.4 (`Require all denied`) |
| Sessions | `use_strict_mode` (vhost) ; `cookie_secure` suit IS_PROD |
| Assets | Cache-busting `?v=APP_VERSION` ; polices self-hostées (woff2 locaux) |
| Perf | Pagination serveur sur ventes_hist, rapports mouvements, produits |
| Robustesse | `declare(strict_types=1)` sur config ; `VALUES(valeur)` → lié ; `?:`→`??` |
| Tests | PHPUnit (`tests/`) — `composer install && composer test` |
| Isolation | Vhost dédié (example) — ne pas cohabiter avec les autres projets |

## Reste à faire (Phase 2 — non couvert ici)

- XSS DOM résiduel (`innerHTML` non échappé dans magasin/ventes_hist/vente/caisse) → `escHtml` central + `json_encode` flags.
- `verifyCsrf()` : `hash_equals` + HTTP 419 au lieu de `die()`.
- Index BDD manquants + prédicats `DATE(created_at)` non sargables.
- N+1 (commandes, comptabilité, marketing, utilisateurs).
- Transactions manquantes (clôture caisse, rôles, suppression commande, update utilisateur).
- ENUM `mode_paiement` incohérents ; FK `ventes.caissier_id` ; colonne morte `est_annulee`.
- Helper `soldeSession` dupliqué → extraire vers `includes/helpers.php`.
- Script `sql/clean_demo.sql`.