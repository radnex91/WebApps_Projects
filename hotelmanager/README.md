# 🏨 HotelPro Suite — Guide d'installation complet

**Application de gestion hôtelière — PHP/MySQL/Bootstrap**  
Version 1.0.0 | Fonctionne sous XAMPP (offline-first)

---

## 📋 Prérequis

| Composant | Version minimale |
|-----------|-----------------|
| XAMPP     | 8.0+            |
| PHP       | 7.4+ (8.1 recommandé) |
| MySQL / MariaDB | 5.7+ / 10.3+ |
| Apache    | 2.4+            |
| Navigateur | Chrome, Firefox, Edge (récent) |

---

## 🚀 Installation pas à pas

### Étape 1 — Copier le projet

Copiez le dossier `hotelmanager` dans :

```
Windows : C:\xampp\htdocs\hotelmanager\
Linux   : /opt/lampp/htdocs/hotelmanager/
macOS   : /Applications/XAMPP/htdocs/hotelmanager/
```

### Étape 2 — Démarrer XAMPP

Lancez le **Panneau de contrôle XAMPP** et démarrez :
- ✅ **Apache**
- ✅ **MySQL**

### Étape 3 — Créer la base de données

1. Ouvrez **phpMyAdmin** : `http://localhost/phpmyadmin`
2. Cliquez sur **"Nouvelle base de données"**
3. Nom : `hotelmanager` · Encodage : `utf8mb4_unicode_ci`
4. Cliquez **"Importer"** → sélectionnez `database/hotel_schema.sql`
5. Cliquez **"Exécuter"**

### Étape 4 — Générer les mots de passe bcrypt

1. Ouvrez : `http://localhost/hotelmanager/setup.php`
2. Copiez les requêtes SQL affichées
3. Exécutez-les dans phpMyAdmin (onglet SQL)
4. **⚠️ SUPPRIMEZ `setup.php` immédiatement après !**

### Étape 5 — Configurer l'application

Éditez `config/config.php` si nécessaire :

```php
// Base de données
define('DB_HOST', '127.0.0.1');  // ou 'localhost'
define('DB_NAME', 'hotelmanager');
define('DB_USER', 'root');
define('DB_PASS', '');            // Votre mot de passe MySQL

// Votre hôtel
define('HOTEL_NOM',     'Nom de votre hôtel');
define('HOTEL_ADRESSE', 'Votre adresse');
define('HOTEL_TEL',     '+237 XXX XXX XXX');
define('APP_URL',       'http://localhost/hotelmanager');
```

### Étape 6 — Télécharger Bootstrap 5 et Chart.js (mode offline)

Pour un fonctionnement **100% hors ligne**, téléchargez :

**Bootstrap 5.3** → https://getbootstrap.com/docs/5.3/getting-started/download/
- Copier `bootstrap.min.css` → `public/css/`
- Copier `bootstrap.bundle.min.js` → `public/js/`

**Chart.js 4** → https://www.chartjs.org/docs/latest/getting-started/installation.html
- Copier `chart.umd.min.js` → renommer en `chart.min.js` → `public/js/`

> Si vous avez Internet, les CDN sont utilisés automatiquement.

### Étape 7 — Accéder à l'application

Ouvrez : **http://localhost/hotelmanager/**

---

## 🔐 Comptes par défaut

| Email                  | Mot de passe  | Rôle       |
|------------------------|---------------|------------|
| admin@hotel.cm         | Admin@2025    | Admin      |
| manager@hotel.cm       | Manager@2025  | Manager    |
| reception@hotel.cm     | Reception@2025| Réception  |

> ⚠️ **Changez tous ces mots de passe à la première connexion** via Mon Profil → Changer le mot de passe.

---

## 📁 Structure du projet

```
hotelmanager/
├── index.php                    ← Front controller (point d'entrée)
├── setup.php                    ← Script d'installation (à supprimer)
├── .htaccess                    ← Configuration Apache
│
├── config/
│   ├── config.php               ← Configuration générale
│   └── database.php             ← Connexion PDO Singleton
│
├── app/
│   ├── helpers.php              ← Fonctions utilitaires globales
│   │
│   ├── controllers/
│   │   ├── AuthController.php
│   │   ├── DashboardController.php
│   │   ├── ReservationController.php
│   │   ├── ChambreController.php
│   │   ├── ClientController.php
│   │   ├── FacturationController.php
│   │   ├── PaiementController.php
│   │   ├── PersonnelController.php
│   │   ├── RapportController.php
│   │   └── ProfilController.php
│   │
│   └── views/
│       ├── layouts/
│       │   ├── header.php       ← Sidebar + topbar
│       │   └── footer.php       ← JS + fermeture HTML
│       ├── auth/
│       │   └── login.php
│       ├── dashboard/
│       │   └── index.php
│       ├── reservations/
│       │   ├── index.php        ← Liste + filtres
│       │   ├── create.php       ← Formulaire + vérif. dispo
│       │   └── show.php         ← Détail + check-in/out
│       ├── chambres/
│       │   ├── index.php        ← Grille visuelle
│       │   └── form.php
│       ├── clients/
│       │   ├── index.php
│       │   ├── form.php
│       │   └── show.php         ← Fiche CRM + historique
│       ├── facturation/
│       │   ├── index.php
│       │   ├── show.php         ← Facture + encaissement
│       │   └── print.php        ← Version impression
│       ├── personnel/
│       │   ├── index.php
│       │   ├── form.php
│       │   └── profil.php
│       └── rapports/
│           └── index.php        ← Stats + graphiques + audit
│
├── database/
│   └── hotel_schema.sql         ← Schéma complet + données test
│
├── public/
│   ├── css/
│   │   ├── app.css              ← Styles personnalisés
│   │   └── bootstrap.min.css    ← (à télécharger)
│   └── js/
│       ├── app.js               ← Scripts globaux
│       ├── bootstrap.bundle.min.js ← (à télécharger)
│       └── chart.min.js         ← (à télécharger)
│
├── logs/                        ← Logs PHP (créé automatiquement)
└── uploads/
    └── factures/                ← Exports PDF futurs
```

---

## 🔧 Dépannage

### Erreur "Connexion à la base impossible"
- Vérifiez que MySQL est bien démarré dans XAMPP
- Testez `DB_HOST = '127.0.0.1'` au lieu de `'localhost'`
- Vérifiez les identifiants dans `config/config.php`

### Page blanche ou erreur 500
- Activez les logs : `tail -f C:\xampp\apache\logs\error.log`
- Vérifiez que `mod_rewrite` est activé dans Apache
- Assurez-vous que les dossiers `logs/` et `uploads/` sont accessibles en écriture

### Bootstrap non chargé (mise en page cassée)
- Vérifiez que les fichiers sont dans `public/css/` et `public/js/`
- Ou activez Internet pour charger les CDN

### Sessions expirées trop vite
- Modifiez `SESSION_LIFETIME` dans `config/config.php` (en secondes)

---

## 🛡️ Sécurité

L'application intègre :
- ✅ Mots de passe hashés **bcrypt** (coût 12)
- ✅ Protection **CSRF** sur tous les formulaires POST
- ✅ Requêtes **PDO préparées** (anti SQL injection)
- ✅ Régénération d'ID de session après login
- ✅ **Timeout de session** configurable
- ✅ Système de **rôles et permissions**
- ✅ **Journal d'audit** de toutes les actions
- ✅ En-têtes HTTP de sécurité via `.htaccess`
- ✅ Protection `Options -Indexes` (pas de listing de dossiers)

---

## 📊 Modules disponibles

| Module          | Fonctionnalités                                              |
|-----------------|--------------------------------------------------------------|
| 🛎 Réservations  | CRUD, check-in/out, vérif. dispo temps réel, annulation     |
| 🏨 Chambres      | Grille visuelle, statuts, types, tarification               |
| 👥 Clients (CRM) | Fiches, historique séjours, fidélité, recherche             |
| 💳 Facturation   | Génération auto, TVA, paiements multi-modes, impression     |
| 💰 Paiements     | Espèces, Mobile Money, Carte, Virement                      |
| 📊 Rapports      | CA, taux d'occupation, top clients, évolution, audit trail  |
| 👨‍💼 Personnel     | Comptes, rôles, activation/suspension                       |
| 🔐 Profil        | Infos perso, changement mot de passe                        |

---

## 🔄 Sauvegarde de la base de données

Depuis **phpMyAdmin** :
1. Sélectionnez `hotelmanager`
2. Onglet **Exporter** → Format SQL → Exécuter
3. Sauvegardez le fichier `.sql` quotidiennement

Ou via la ligne de commande XAMPP :
```bash
mysqldump -u root hotelmanager > backup_$(date +%Y%m%d).sql
```

---

## 📞 Support

Pour toute question, consultez la documentation PHP officielle :
- PDO : https://www.php.net/manual/fr/book.pdo.php
- Sessions : https://www.php.net/manual/fr/book.session.php
- Bootstrap 5 : https://getbootstrap.com/docs/5.3/
