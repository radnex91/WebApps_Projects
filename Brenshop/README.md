# 🏪 POS System — Guide d'Installation XAMPP

## 📋 Prérequis
- XAMPP (Apache + MySQL/MariaDB) installé
- PHP 8.0 ou supérieur
- Navigateur moderne

---

## 🚀 Installation en 5 étapes

### 1️⃣ Copier les fichiers
Copiez le dossier `pos-system` dans le répertoire XAMPP :
```
C:\xampp\htdocs\pos-system\        (Windows)
/Applications/XAMPP/htdocs/pos-system/  (macOS)
/opt/lampp/htdocs/pos-system/      (Linux)
```

### 2️⃣ Démarrer XAMPP
- Ouvrez le Panneau de contrôle XAMPP
- Démarrez **Apache** et **MySQL**

### 3️⃣ Créer la base de données
Ouvrez votre navigateur → `http://localhost/phpmyadmin`
- Cliquez **Nouvelle base de données**
- Nom : `pos_system`
- Encodage : `utf8mb4_unicode_ci`
- Cliquez **Créer**
- Allez dans l'onglet **Importer**
- Sélectionnez le fichier `database.sql`
- Cliquez **Importer**

### 4️⃣ Configurer la base de données
Ouvrez `config/config.php` et ajustez si nécessaire :
```php
define('DB_HOST', '127.0.0.1');  // ou 'localhost'
define('DB_NAME', 'pos_system');
define('DB_USER', 'root');
define('DB_PASS', '');            // votre mot de passe MySQL si défini
```

### 5️⃣ Accéder à l'application
Ouvrez : **http://localhost/pos-system**

---

## 🔐 Comptes de démo

| Email | Mot de passe | Rôle |
|-------|-------------|------|
| admin@brenshop.sn | password | Administrateur |
| manager.dakar@brenshop.sn | password | Manager |
| caisse.dakar@brenshop.sn | password | Caissier |
| manager.sl@brenshop.sn | password | Manager |
| caisse.sl@brenshop.sn | password | Caissier |

---

## 📁 Structure du projet

```
pos-system/
├── index.php                 # Page de connexion
├── database.sql              # Script SQL complet
├── config/
│   ├── config.php            # Configuration globale
│   └── database.php          # Connexion PDO
├── includes/
│   ├── bootstrap.php         # Chargeur global
│   └── helpers.php           # Fonctions utilitaires
├── models/
│   ├── BaseModel.php         # CRUD générique
│   ├── User.php              # Authentification
│   ├── models.php            # Store, Warehouse, Product, Stock, Customer
│   └── Sale.php              # Sale, Transfer
├── controllers/
│   ├── auth.php              # Login/logout
│   ├── sale_controller.php   # API ventes (JSON)
│   ├── product_ajax.php      # Recherche produits (AJAX)
│   └── stock_ajax.php        # Stock AJAX
├── views/
│   ├── layout_top.php        # Sidebar + topbar
│   ├── layout_bottom.php     # Scripts communs
│   ├── dashboard.php         # Tableau de bord
│   ├── pos.php               # Point de vente
│   ├── products.php          # Gestion produits
│   ├── categories.php        # Catégories
│   ├── stock.php             # Gestion stock
│   ├── transfers.php         # Transferts
│   ├── sales.php             # Historique ventes
│   ├── invoice.php           # Facture / ticket
│   ├── customers.php         # Clients
│   ├── reports.php           # Rapports
│   ├── stores.php            # Boutiques (admin)
│   ├── warehouses.php        # Magasins (admin)
│   └── users.php             # Utilisateurs (admin)
└── assets/
    └── uploads/              # Images produits (auto-créé)
```

---

## 🗄️ Structure Base de Données

| Table | Description |
|-------|-------------|
| `stores` | Boutiques (points de vente) |
| `warehouses` | Magasins / entrepôts |
| `users` | Utilisateurs avec rôles |
| `categories` | Catégories de produits |
| `products` | Catalogue produits |
| `product_prices` | Prix personnalisés par boutique |
| `stock` | Stock actuel par produit/magasin |
| `stock_movements` | Historique tous mouvements |
| `customers` | Base clients |
| `sales` | En-têtes de ventes |
| `sale_items` | Lignes de vente |
| `transfers` | Transferts inter-magasins |
| `transfer_items` | Produits transférés |
| `activity_logs` | Journal d'activité |

---

## ⚡ Fonctionnalités

### POS (Caisse)
- ✅ Recherche produit temps réel (AJAX)
- ✅ Scan code-barres (saisie manuelle ou douchette USB)
- ✅ Panier dynamique avec modification quantités
- ✅ Remise globale en pourcentage
- ✅ Calcul TVA automatique
- ✅ Multi-mode paiement (Cash, Mobile Money, Carte, Crédit)
- ✅ Génération facture A4 + ticket thermique
- ✅ Déduction stock automatique
- ✅ Sélection magasin source

### Stock
- ✅ Stock par produit et par magasin
- ✅ Entrée / Sortie / Ajustement de stock
- ✅ Historique tous mouvements
- ✅ Alertes stock faible en temps réel
- ✅ Inventaire physique (ajustement)

### Transferts
- ✅ Transfert entre magasins (même boutique ou inter-boutiques)
- ✅ Workflow Création → Exécution
- ✅ Mise à jour automatique des stocks à l'exécution
- ✅ Historique complet

### Rapports
- ✅ KPIs temps réel (CA, bénéfice, nb ventes, panier moyen)
- ✅ Graphique revenue mensuel (barres + courbe)
- ✅ Courbe 30 derniers jours
- ✅ Donut méthodes de paiement
- ✅ Top 10 produits avec progression
- ✅ Comparaison par boutique
- ✅ Filtres par période

### Administration
- ✅ CRUD complet boutiques, magasins, produits, catégories, clients, utilisateurs
- ✅ Upload images produits
- ✅ Gestion rôles (admin/manager/caissier)
- ✅ Pagination et recherche

---

## 🔒 Sécurité

- Mots de passe hashés **bcrypt**
- **PDO** avec requêtes préparées (protection SQLi)
- Tokens **CSRF** sur tous les formulaires
- Validation et sanitisation des inputs
- Sessions sécurisées avec expiration automatique (8h)
- Contrôle d'accès par rôle

---

## 🐛 Résolution de problèmes courants

**Erreur de connexion DB :**
- Vérifiez que MySQL est démarré dans XAMPP
- Testez `http://localhost/phpmyadmin`
- Vérifiez `DB_HOST` dans `config/config.php` : essayez `127.0.0.1` si `localhost` échoue

**Page blanche :**
- Activez les erreurs PHP : ajoutez `ini_set('display_errors', 1);` en haut de `index.php`
- Consultez `C:\xampp\php\logs\php_error_log`

**Upload images ne fonctionne pas :**
- Créez le dossier `assets/uploads/products/` manuellement
- Vérifiez les permissions (chmod 755 sur Linux/macOS)

**Session expire trop vite :**
- Modifiez `SESSION_LIFETIME` dans `config/config.php`
