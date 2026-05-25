# 💊 PharmaCare — Application de Gestion de Pharmacie
**Version 1.0 | PHP 7.4+ / MySQL 5.7+ / XAMPP**

---

## 📁 Structure du projet

```
pharmacare/
├── index.php               ← Page de connexion
├── logout.php
├── dashboard.php           ← Tableau de bord
├── database.sql            ← Script SQL (importer en PREMIER)
├── config/
│   └── database.php        ← ⚙️ Configuration BDD
├── includes/
│   ├── auth.php            ← Authentification & sessions
│   └── layout.php          ← En-tête, sidebar, pied de page
├── assets/
│   ├── css/style.css       ← Styles principaux
│   └── js/app.js           ← JavaScript
└── modules/
    ├── vente.php           ← Point de Vente (caisse)
    ├── stock.php           ← Gestion du stock
    ├── produits.php        ← CRUD médicaments
    ├── fournisseurs.php    ← CRUD fournisseurs
    ├── commandes.php       ← Commandes fournisseurs
    ├── ventes_hist.php     ← Historique des ventes
    ├── rapports.php        ← Rapports & graphiques
    ├── utilisateurs.php    ← Gestion utilisateurs (admin)
    ├── categories.php      ← Gestion catégories (admin)
    └── stock_ajust.php     ← Ajustement de stock
```

---

## 🚀 Installation (XAMPP)

### Étape 1 — Copier les fichiers
```
Copier le dossier "pharmacare" dans :
C:\xampp\htdocs\pharmacare\       (Windows)
/opt/lampp/htdocs/pharmacare/     (Linux)
/Applications/XAMPP/htdocs/pharmacare/ (Mac)
```

### Étape 2 — Démarrer XAMPP
- Ouvrir XAMPP Control Panel
- Démarrer **Apache** et **MySQL**

### Étape 3 — Créer la base de données
Option A — Via phpMyAdmin :
1. Ouvrir http://localhost/phpmyadmin
2. Cliquer "Nouvelle base de données" → pharmacare → Créer
3. Onglet "Importer" → Choisir `database.sql` → Exécuter

Option B — Via terminal :
```bash
mysql -u root -p < C:\xampp\htdocs\pharmacare\database.sql
```

### Étape 4 — Configurer la connexion (si nécessaire)
Modifier `config/database.php` :
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'pharmacare');
define('DB_USER', 'root');
define('DB_PASS', '');        // ← Votre mot de passe MySQL si défini
define('APP_URL', 'http://localhost/pharmacare');
```

### Étape 5 — Accéder à l'application
Ouvrir : **http://localhost/pharmacare**

---

## 🔐 Comptes de démonstration

| Login        | Mot de passe | Rôle           | Droits                                    |
|--------------|-------------|----------------|-------------------------------------------|
| `admin`      | `password`  | Administrateur | Accès complet + gestion utilisateurs      |
| `pharmacien` | `password`  | Pharmacien     | Stock, produits, commandes, ventes        |
| `caissier`   | `password`  | Caissier       | Point de vente + consultation stock       |

---

## 📋 Fonctionnalités

### 🛒 Point de Vente
- Catalogue cliquable avec filtre par catégorie et nom
- Panier dynamique avec calcul TVA (9%) en temps réel
- Gestion de la monnaie à rendre
- 4 modes de paiement : Espèces, Carte, Chèque, Assurance
- Déduction automatique du stock lors de chaque vente

### 📦 Gestion du Stock
- Vue complète avec filtres (stock bas, rupture, catégorie)
- Alertes visuelles (vert / orange / rouge)
- Détection produits périmés dans les 3 mois
- Ajustement manuel du stock avec historique des mouvements

### 💊 Médicaments
- CRUD complet avec toutes les informations
- Prix d'achat et de vente, TVA, date d'expiration
- Référence unique, catégorie, fournisseur associé

### 🏭 Fournisseurs
- Gestion complète des fournisseurs
- Suivi du nombre de produits par fournisseur

### 📋 Commandes
- Création et suivi des commandes fournisseurs
- Statuts : En attente → En cours → Livrée / Annulée

### 📈 Historique & Rapports
- Historique filtrable (date, mode de paiement)
- Détail de chaque vente en popup
- Rapport annuel : CA mensuel, top catégories, modes de paiement

### 👥 Utilisateurs (Admin)
- Création / modification / activation-désactivation
- 3 rôles avec permissions différenciées
- Suivi de la dernière connexion

---

## 🔧 Personnalisation

### Changer le nom de la pharmacie
Dans `includes/layout.php`, modifier :
```php
<div class="logo-mark">💊 PharmaCare</div>
```

### Changer le taux de TVA
Dans `config/database.php` :
```php
define('TVA_DEFAULT', 19.00);  // ex: 19%
```
Et dans `assets/js/app.js` :
```javascript
const TVA_RATE = 0.19;
```

### Changer la devise
Dans `includes/auth.php`, modifier la fonction `fmt()` et dans `assets/js/app.js` modifier `fmtDA()`.

---

## 🛡️ Sécurité
- Mots de passe hashés avec `password_hash()` (bcrypt)
- Sessions PHP sécurisées avec régénération d'ID
- Échappement HTML systématique (`htmlspecialchars`)
- Requêtes préparées PDO (protection SQL injection)
- Vérification des rôles sur chaque page sensible

---

## 📞 Support
Application développée avec PHP natif + MySQL.
Compatible XAMPP 8.x, 7.x.
