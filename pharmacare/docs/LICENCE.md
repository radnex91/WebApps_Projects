# Système de licence — Code d'activation

PharmaCare est plafonné à un nombre de **lignes de vente** (`vente_lignes`). Le palier
**gratuit** est de **10 000 lignes**. Au-delà, les nouvelles ventes sont bloquées ; le client
doit obtenir un **code d'activation** auprès du développeur pour acheter des **packs de
lignes supplémentaires**, au prix fixé par le développeur.

Ce document décrit :
- [A. Principe & sécurité](#a-principe--sécurité)
- [B. Préparation développeur (une fois)](#b-préparation-développeur-une-fois)
- [C. Déploiement chez un nouveau client](#c-déploiement-chez-un-nouveau-client)
- [D. Exploitation — émettre un code d'activation](#d-exploitation--émettre-un-code-dactivation)
- [E. Réglages](#e-réglages)
- [F. Dépannage](#f-dépannage)

Il existe **deux formats** de code, au choix selon le canal :
- **Code long** (RSA) — sécurisé, non forgeable par le client. À transmettre par e-mail /
  copier-coller. Recommandé par défaut.
- **Code court** (HMAC) — `LLLL-NNNNNN-CCCCCCCC` (ex : `733D-15000-KEKABDBU`), **SMS-friendly**.
  Sécurité **réduite** (le secret de vérification est embarqué dans l'app livrée) ;
  pratique à dicter ou envoyer par SMS. Voir §A.3.

---

## A. Principe & sécurité

### Comment ça marche
- Un **identifiant d'instance** (`licence_instance_id`) est généré automatiquement par
  pharmacie, au premier usage, et stocké dans la table `parametres`.
- Le **plafond effectif** est un **enregistrement de licence signé** stocké dans
  `parametres.licence_record`.
- À chaque vente (POS), un garde vérifie `COUNT(vente_lignes) >= plafond`. Si atteint →
  la vente est bloquée (flash + `audit_log`), le reste de l'app reste accessible.
- Le client applique un code sur la page **Menu → Licence** ; le code, s'il est valide,
  devient le nouvel enregistrement de licence → le plafond augmente.

### Format d'un code long (RSA)
```
base64url( JSON{i,c,n,e} ) . base64url( signature_RSA_SHA256(payload) )
```
- `i` = instance_id (32 hex) — le code n'est valable que pour CETTE pharmacie.
- `c` = plafond absolu (lignes) — le développeur calcule `ancien_cap + pack`.
- `n` = compteur (anti-rejeu, strictement croissant par client).
- `e` = expiration (timestamp Unix, `0` = sans limite de durée).

### A.3 Format d'un code court (HMAC, SMS-friendly)
```
LLLL-NNNNNN-CCCCCCCC        ex : 733D-15000-KEKABDBU
```
- `LLLL` = 4 caractères alphanumériques (préfixe cosmétique, **non vérifié**).
- `NNNNNN` = plafond absolu (chiffres, ≥ 4).
- `CCCCCCCC` = jeton de contrôle = 8 symboles dérivés de `HMAC-SHA256(secret, "instance:cap")`,
  mappés sur un alphabet de 32 symboles sans ambigüité (`ABCDEFGHJKLMNPQRSTUVWXYZ23456789`).
L'app recalcule ce jeton avec **son** `instance_id` et le **secret embarqué**, et le compare
(temps constant). Le code n'est valable que pour l'instance liée.

**Sécurité réduite (à comprendre) :** contrairement au code long (RSA), le secret de
vérification du code court **voyage dans l'app livrée au client** (fichier
`config/licence_secret.php`, livraison bundle uniquement — absent du dépôt git ; l'app
le lit via `licence_hmac_secret()`). Un client qui lit le code source peut donc **forger** des codes
courts pour sa propre instance. C'est un compromis délibéré pour la praticité du SMS.
Le code long (RSA) reste disponible quand la sécurité importe plus que la commodité.
L'anti-rejeu des codes courts repose sur le **plafond absolu** : un code n'est appliqué
que si son `cap` est **strictement supérieur** au cap courant (rejouer ou rétrograder = rejet).

### Sécurité (on-premise — à lire en entier)
Le client a le code source **et** la base de données. Aucune solution n'est 100 %
inviolable (il peut patcher le code de vérification). On rend le contournement pénible :

| Mesure | Effet |
|---|---|
| Signature asymétrique RSA-2048/SHA-256 (code long) | Le client ne peut pas **forger** un code : il n'a pas la clé privée. |
| HMAC-SHA256 lié à l'instance (code court) | Le client ne peut pas **deviner** un code pour une autre instance ; mais peut forger le sien s'il lit le source (sécurité réduite). |
| Liaison à l'instance | Un code ne marche que pour la pharmacie ciblée — **pas de revente** entre clients. |
| Plafond signé / HMAC | Modifier la valeur en BDD **casse la vérification** → repli sur le palier gratuit. Le client n'a aucun intérêt à trafiquer. |
| Anti-rejeu | Code long : compteur strictement croissant. Code court : plafond absolu strictement supérieur. |
| Repli sûr | Vérification invalide/absente/expirée → **palier gratuit 10 000**. Le client ne perd **jamais** ses données. |
| Manifeste d'intégrité signé | Empreintes SHA-256 des fichiers critiques, signées RSA. L'app détecte toute édition d'un fichier couvert (y compris `config/licence.php` qui porte la vérification) → avertissement + `audit_log`. Le client ne peut pas re-signer un manifeste modifié (pas de clé privée). Dissuasion, pas garantie absolue. |

**La protection réelle repose sur un point opérationnel : ne JAMAIS livrer le dossier
`tools/` (ni la clé privée, ni le secret HMAC) au client.** Voir §C.

---

## B. Préparation développeur (une fois)

> Déjà effectuée sur cette machine : `config/licence.php` contient la clé publique,
> `tools/licence_privatekey.php` contient la clé privée (gitignorée) et
> `tools/licence_secret.php` contient le secret HMAC (gitignoré). Vous pouvez
> passer directement au §C si vous réutilisez keypair et secret existants.

### B.1 Générer (ou régénérer) le keypair (codes longs)
```bash
php tools/gen_licence_keypair.php
```
- Écrit la **clé privée** dans `tools/licence_privatekey.php` (gitignorée — à garder secrète).
- Injecte la **clé publique** dans `config/licence.php` (constante `LICENCE_PUBKEY`).
- **Refuse** de s'exécuter si une clé est déjà en place — sauf `--force` :
  ```bash
  php tools/gen_licence_keypair.php --force   # écrase et invalide TOUS les codes longs déjà émis
  ```

### B.2 Générer le secret HMAC (codes courts — une fois)
```bash
php tools/gen_licence_secret.php
```
- Écrit le **secret** dans `tools/licence_secret.php` (gitignoré — à garder secret).
- Écrit le même secret dans `config/licence_secret.php` (app cliente — gitignoré, mais
  **livré dans les bundles deploy** ; livré manuellement vers les installs existantes).
- **Refuse** de s'exécuter si un secret est déjà en place — sauf `--force` :
  ```bash
  php tools/gen_licence_secret.php --force   # écrase et invalide TOUS les codes courts déjà émis
  ```

### B.3 Vérifier le ledger (état connu des clients)
```bash
php tools/gen_licence.php --list
```
Affiche, par `instance_id`, le cap et le compteur actuels (mémorisés dans
`tools/licence_ledger.json`, gitignoré).

> ⚠️ **Sauvegardez** `tools/licence_privatekey.php`, `tools/licence_secret.php` et
> `tools/licence_ledger.json` hors du dépôt (clé USB / coffre). Perdre la clé privée ou le
> secret = impossibilité d'émettre de nouveaux codes pour les clients existants.

### B.4 Générer le manifeste d'intégrité (avant chaque déploiement)
```bash
php tools/gen_licence_integrity.php
```
Calcule l'empreinte SHA-256 des fichiers critiques (`config/licence.php`,
`config/settings.php`, `modules/vente.php`, `modules/licence.php`,
`includes/audit.php`, `includes/auth.php`), **signe** la liste avec la clé privée RSA,
et écrit `config/licence_integrity.php` (livré au client — public, mais non forgeable
sans la clé privée).

L'app vérifie ce manifeste sur la page **Licence** : signature RSA (clé publique
embarquée) + comparaison des empreintes. Si un fichier couvert a été modifié chez le
client → avertissement visible + entrée `licence.integrity` dans `audit_log`.

> ⚠️ **Régénérez ce manifeste après TOUTE modification d'un fichier couvert** — y compris
> un changement de `LICENCE_FREE_CAP` ou une mise à jour de l'app — **sinon l'app
> signalera une intégrité compromise** chez le client au prochain déploiement. Le
> manifeste doit être le **dernier** fichier régénéré avant de livrer.
>
> **Auto-régénération depuis la GUI** : la GUI régénère automatiquement le manifeste quand
> tu (a) changes le palier gratuit (bouton « Enregistrer ») et (b) génères un code
> d'activation (bouton « Générer le code »). Tu n'as donc pas à y penser dans ces deux cas.
> Pour toute **autre** édition manuelle d'un fichier couvert, relance
> `php tools/gen_licence_integrity.php`.

---

## C. Déploiement chez un nouveau client

L'installateur `install_prod.ps1` s'exécute **en place** dans le dossier `pharmacare/`
déjà copié sur le serveur client. La licence est transparente : `config/licence.php`
(avec la clé publique) voyage avec le dossier, l'instance_id s'auto-génère, le palier
gratuit 10 000 est actif immédiatement. **Aucun seed SQL, aucune modification de
l'installateur.**

### C.1 Copier le dossier vers le serveur client — en EXCLUANT `tools/`

```powershell
# Depuis votre PC, vers le serveur client (adapter le chemin UNC / lecteur réseau)
robocopy C:\xampp\htdocs\pharmacare \\<SERVEUR>\C$\xampp\htdocs\pharmacare `
  /E /XD .git tools .claude /XF *.log
```

| À **exclure** (`/XD` / `/XF`) | Pourquoi |
|---|---|
| `tools/` | Contient les générateurs. Livrés au client, ils pourraient re-créer une clé (`--force`), le secret HMAC, et signer leurs propres codes. |
| `tools/licence_privatekey.php` | Clé privée RSA — déjà gitignorée, mais à ne **jamais** copier. |
| `tools/licence_secret.php` | Secret HMAC (codes courts) — déjà gitignoré, à ne **jamais** copier. |
| `tools/licence_ledger.json` | Ledger des clients — secret commercial. |
| `.git`, `.claude` | Métadonnées de dev. |
| `*.log` | Artefacts runtime. |

> À **conserver** : `_archive/` (contient `database.sql` requis par l'installateur),
> `config/licence.php` (clé publique), tout le reste de l'app.

### C.2 Installer sur le serveur client
Sur le PC serveur (PowerShell **en administrateur**) :
```powershell
cd C:\xampp\htdocs\pharmacare
.\install_prod.ps1
```
L'installateur : crée la BDD `pharmacare`, importe `database.sql`, génère
`config/env.prod.php`, change les mots de passe des comptes démo, configure Apache +
pare-feu.

### C.3 Vérifier la licence côté client
1. Se connecter en **admin**.
2. Menu **Administration → Licence**.
3. Vérifier : « Palafond de lignes » = 10 000, « Palier gratuit — enregistrement non
   actif ». L'**Identifiant d'instance** est visible (c'est celui que le client vous
   communiquera plus tard).

---

## D. Exploitation — émettre un code d'activation

Quand un client atteint (ou va atteindre) 10 000 lignes :

1. **Le client vous communique son Identifiant d'instance** (page Licence, champ lecture
   seule — il peut le copier d'un clic).
2. **Sur votre machine**, générez le code — soit via l'**interface graphique**
   (recommandé), soit en **ligne de commande**.
3. **Le client colle le code** sur sa page Licence → « Activer la licence ». Le plafond
   augmente ; les ventes redeviennent possibles.

> Si votre ledger et le cap réel du client sont désynchronisés (ex. BDD restaurée),
> demandez au client le « Plafond » affiché sur sa page Licence et utilisez le mode
> **Cap absolu** (`--set` ou bouton « Cap absolu »).

### D.1 Interface graphique (recommandé)

Double-cliquez **`tools/licence_gui.bat`** : il lance un serveur web local
(`http://127.0.0.1:8080`) et ouvre le navigateur. **Aucune ligne de commande.**

- **Passphrase** : `ramadan061091@1433` (à changer dans la constante `GUI_PASS` de
  `tools/gen_licence_gui.php`).
- **Format** : choisissez **Court** (SMS, `LLLL-NNNNNN-CCCCCCCC`) ou **Long** (RSA).
  Le champ « Expiration » n'apparaît que pour le format long (les codes courts n'expirent pas).
- Dans le formulaire : collez l'**identifiant d'instance** du client, choisissez le mode
  (**Pack additionnel** = +N lignes, ou **Cap absolu**), saisissez la valeur, cliquez
  **« Générer le code »**.
- Le code apparaît dans une zone copiable (bouton **« Copier le code »**).
- Le tableau **« Clients enregistrés »** liste le ledger (cap, counter, expiration par
  instance) avec deux actions : **↺** réinitialiser le ledger du client,
  **✕** retirer le client du ledger.

> L'interface n'est accessible **que depuis localhost** et est protégée par passphrase.
> Elle n'est **pas** servie par Apache (`.htaccess` bloque `tools/`) — seul le serveur
> PHP local lancé par le `.bat` l'atteint. Ne jamais livrer `tools/` aux clients.
> Fermez la fenêtre « PharmaCare - Serveur Licence » pour arrêter le serveur.

### D.2 Ligne de commande (alternative)

```bash
# Code COURT (SMS) — pack additionnel : +N lignes par rapport au cap actuel du client
php tools/gen_licence.php --instance=<ID> --pack=5000 --short

# Code COURT — cap absolu
php tools/gen_licence.php --instance=<ID> --set=25000 --short

# Code LONG (RSA) — pack additionnel
php tools/gen_licence.php --instance=<ID> --pack=5000

# Code LONG (RSA) — avec expiration (timestamp Unix), optionnel
php tools/gen_licence.php --instance=<ID> --pack=5000 --expire=1735689600

# Lister le ledger
php tools/gen_licence.php --list
```

Le ledger (`tools/licence_ledger.json`) retient le cap du client ; `--pack` calcule
automatiquement `nouveau_cap = ancien_cap + pack`. Les codes longs incrémentent en outre
le compteur anti-rejeu.

### D.3 Version portable — émettre des codes depuis une autre machine

Le bundle **`dist/PharmaCare-Licence-Manager-<version>-portable.zip`** contient
l'exe + un runtime PHP 8.2 embarqué (`tools/php/`) : il fonctionne sur toute
machine Windows 10/11 x64 **sans XAMPP ni PHP**. Décompressez-le où vous voulez
et double-cliquez `tools\gen_licence.exe`.

- **Contenu sensible** : clé privée RSA (`licence_privatekey.php`), secret HMAC
  (`licence_secret.php`) et ledger (`licence_ledger.json`). **Ne jamais transmettre
  à un client** ni committer (dist/ est git-ignoré).
- **Ledger par copie** : chaque machine a son propre `licence_ledger.json`.
  Avant/après une session sur une machine secondaire, synchronisez ce fichier
  avec la machine principale — sinon le mode « Pack additionnel » calcule sur un
  historique faux et les counters des codes longs entrent en conflit.
- **Palier gratuit** : modifié dans la copie du bundle uniquement ; répercutez la
  valeur dans le dépôt principal avant déploiement.
- **Rebuild** (après modification de `licence_lib.php`, de l'exe ou des fichiers
  couverts) : `bash tools/build_licence_portable.sh` sur la machine de dev —
  recompile l'exe, assemble le bundle, auto-teste (status + génération sur copie
  temporaire) et produit le zip.

### Exemples de tarification (à votre guise)
| Vous vendez | Commande |
|---|---|
| +5 000 lignes | `--pack=5000` |
| +10 000 lignes | `--pack=10000` |
| Licence illimité (cap très haut) | `--set=1000000000` |

---

## E. Réglages

### Changer le palier gratuit
Le palier gratuit (nombre de lignes offertes à un nouveau client, et repli quand
aucune licence n'est active) est **configurable** sans toucher au code.

**Via la GUI** (recommandé) : dans la section « Palier gratuit », saisissez la valeur et
cliquez « Enregistrer ». La constante `LICENCE_FREE_CAP` de `config/licence.php` est
mise à jour.

**En CLI / éditeur** : éditez `config/licence.php` :
```php
const LICENCE_FREE_CAP = 10000;   // ← palier gratuit (ex: 5000, 20000…)
```

Le générateur lit cette même valeur comme cap de départ d'un nouveau client (c'est le
même fichier qui sert de source de vérité, côté dev et côté app).

> ⚠️ **Effet sur les clients déjà déployés** : le palier gratuit est le repli quand
> aucune licence n'est active. Si vous changez cette valeur et redéployez chez un client
> qui n'a **pas** de licence active, son repli passera à la nouvelle valeur. Les
> licences **déjà activées** (code long ou court appliqué) gardent leur plafond — seule
> la valeur de repli change. Réfléchissez avant de baisser la valeur sur un parc déjà
> installé.

### Permission d'accès à la page Licence
La page `modules/licence.php` et le menu « Licence » requièrent la permission
`parametres.gerer` (rôle admin par défaut). Affectez-la via le module Rôles si besoin.

### Où sont stockées les données licence
Dans la table `parametres` (clé/valeur) :
- `licence_instance_id` — identifiant de la pharmacie.
- `licence_record` — enregistrement signé actif (source du plafond).
- `licence_applied_codes` — journal des codes appliqués (audit interne).

---

## F. Dépannage

### « Clé publique de licence non configurée sur ce serveur »
`config/licence.php` a `LICENCE_PUBKEY` vide. Sur le **serveur dev** : relancez
`php tools/gen_licence_keypair.php`. Sur un **serveur client** : le dossier `config/`
n'a pas été copié correctement — reprenez la copie (§C.1).

### Un code est refusé : « non lié à cette instance »
Le code a été généré pour un autre `instance_id`. Régénérez-le avec le **bon** identifiant
(affiché sur la page Licence du client).

### Un code est refusé : « déjà utilisé ou obsolète »
Le compteur du code est ≤ au compteur courant. Générez un **nouveau** code (le compteur
incrémente à chaque émission).

### Un code court est refusé : « Code court invalide ou non lié à cette instance »
Soit le code a été généré pour une autre instance, soit le secret HMAC diffère entre le
poste dev (`tools/licence_secret.php`) et le serveur client (`config/licence_secret.php`).
Vérifiez qu'ils sont identiques. À noter : les codes courts
n'expirent pas et n'acceptent que des plafonds strictement supérieurs au cap courant
(rejouer un même code, ou un cap inférieur/égal, est refusé).

### Le client a trafiqué `parametres.licence_record`
Signature cassée → l'app retombe automatiquement sur le palier gratuit 10 000. Le client
doit appliquer un code valide pour retrouver son plafond acheté.

### `openssl_pkey_new a échoué` (générateur, XAMPP)
Le générateur auto-configure `OPENSSL_CONF`. Si l'erreur persiste, vérifiez la présence de
`C:\xampp\php\extras\openssl\openssl.cnf` et que l'extension `openssl` est activée
(`php -m | grep openssl`).

### La page Licence affiche « Intégrité des fichiers licence compromise »
Un fichier couvert (`config/licence.php`, `config/settings.php`, `modules/vente.php`,
`modules/licence.php`, `includes/audit.php`, `includes/auth.php`) diffère de la version
livrée. Soit le client a édité un fichier (alors tracé dans `audit_log` sous
`licence.integrity`), soit **vous avez modifié un fichier côté dev sans régénérer le
manifeste** (§B.4) avant de déployer. Dans ce dernier cas, régénérez
`config/licence_integrity.php` et redéployez-le.

### La page Licence affiche « Manifeste d'intégrité invalide »
`config/licence_integrity.php` est absent, corrompu, ou sa signature RSA ne vérifie plus
(clé publique modifiée / manifeste falsifié). Redéployez le `config/` complet depuis le
poste dev.

### Vérifier l'état licence en CLI (debug)
Créez un mini-script temporaire :
```php
<?php
require __DIR__ . '/config/env.php';
require __DIR__ . '/config/database.php';
require __DIR__ . '/config/settings.php';
require __DIR__ . '/config/licence.php';
var_dump(licence_instance_id(), licence_current(), licence_usage(), licence_remaining());
```

---

## Références code
- `config/licence.php` — cœur (clé publique, secret HMAC, vérification, application de code, intégrité).
- `config/licence_integrity.php` — manifeste d'intégrité signé (généré par le dev, livré au client).
- `modules/licence.php` — page d'activation (admin) + vérification d'intégrité.
- `modules/vente.php` — garde pré-transaction (blocage des ventes).
- `includes/layout.php` — entrée de menu « Licence ».
- `tools/gen_licence_keypair.php` — génère le keypair RSA (codes longs).
- `tools/gen_licence_secret.php` — génère le secret HMAC (codes courts).
- `tools/gen_licence_integrity.php` — génère le manifeste d'intégrité signé.
- `tools/gen_licence.php`, `tools/gen_licence_gui.php`, `tools/licence_gui.bat`,
  `tools/licence_lib.php` — outils développeur (ne pas livrer).
- `config/settings.php` — `paramCacheClear()` (cohérence du cache `parametres`).