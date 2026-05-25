# StockMTW — Application de Gestion de Stock
## Projet MOUTOURWA - MAROUA | Code Projet: 30

---

## INSTALLATION SUR XAMPP

### 1. Copier le dossier
Copier tout le dossier `stock_app/` dans :
```
C:\xampp\htdocs\stock_moutourwa\
```
*(ou sur Linux/Mac: `/opt/lampp/htdocs/stock_moutourwa/`)*

### 2. Créer la base de données
- Ouvrir **phpMyAdmin** → `http://localhost/phpmyadmin`
- Cliquer **"Importer"**
- Sélectionner le fichier `database.sql`
- Cliquer **"Exécuter"**

### 3. Configurer la connexion BD
Editer `config.php` si nécessaire :
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'stock_moutourwa');
define('DB_USER', 'root');      // votre utilisateur MySQL
define('DB_PASS', '');          // votre mot de passe MySQL
```

### 4. Accéder à l'application
Ouvrir un navigateur sur :
```
http://localhost/stock_moutourwa/
```

### 5. Connexion initiale
| Rôle | Login | Mot de passe |
|------|-------|-------------|
| Administrateur | `admin` | `password` |
| Gestionnaire | `gestionnaire` | `password` |

**⚠ Changer ces mots de passe immédiatement dans "Paramètres" !**

---

## FONCTIONNALITÉS

### 📦 Gestion des Articles
- Créer et gérer les matériaux (Fil d'attache, Ciment, Fer 10, Fer 8, Pointe 60/150/TOC, Bois Chevron/Planche, Carburant)
- Prix unitaire et unité de mesure
- Seuil d'alerte stock

### 📋 Fiches Mensuelles (Suivi de Consommation)
- Exactement comme les fichiers Excel : tableau avec ENTRÉES, SORTIES, STOCK, CMUPACE
- Navigation par article et par mois
- Report automatique du mois précédent
- Zone de signatures (Assistant Gestionnaire, Gestionnaire des Stocks, RAF, Directeur des Travaux)
- Impression native

### ↓ Entrées en Stock
- Réceptions fournisseur (DA/BC + BCL/Facture)
- Approvisionnement via caisse
- Calcul automatique du CMUPACE

### ↑ Sorties (BSM)
- Bon de Sortie Matériel (BSM)
- Affectation par chantier/fournisseur (BAKIDJA, RAMADAN, NGB, HYPOLITE, GENIE CIVIL...)
- Numéro BSM obligatoire

### 📊 Analyse Croisée Dynamique
- Vue annuelle par article
- Tableau quantités (Entrées / Sorties / Stocks)
- Tableau valorisation (Coûts / Marge)
- Graphique interactif multi-axes

### 🔐 Gestion des utilisateurs
- Rôles: Admin, Gestionnaire, Assistant, Directeur, RAF
- Gestion des mots de passe

---

## MÉTHODE CMUPACE

L'application implémente le **Coût Moyen Pondéré Actualisé à Chaque Entrée** :

```
Nouveau CMUPACE = (Stock en valeur + Quantité entrée × PU entrée) / Nouveau stock en quantité
```

Le CMUPACE est recalculé automatiquement à chaque entrée en stock.
Les sorties sont valorisées au dernier CMUPACE connu.

---

## MATÉRIAUX PRÉ-CONFIGURÉS

| Code | Désignation | Unité | PU référence |
|------|-------------|-------|--------------|
| FIL_ATTACHE | Fil d'attache | Kg | 1 000 FCFA |
| CIMENT | Ciment | Sac | 5 300 FCFA |
| FER_10 | Fer de 10 | Barre | 856 FCFA |
| FER_8 | Fer de 8 | Barre | 856 FCFA |
| POINTE_150 | Pointe de 150 | Kg | 5 000 FCFA |
| POINTE_60 | Pointe de 60 | Kg | 1 800 FCFA |
| POINTE_TOC_70 | Pointe TOC 70 | Kg | 1 800 FCFA |
| BOIS_CHEVRON | Bois Chevron | Pce | 8 500 FCFA |
| BOIS_PLANCHE | Bois Planche | Pce | 7 800 FCFA |
| CARBURANT | Carburant | L | 856 FCFA |

---

## STRUCTURE DES FICHIERS

```
stock_moutourwa/
├── config.php          → Configuration BD et app
├── index.php           → Tableau de bord
├── login.php           → Page connexion
├── logout.php          → Déconnexion
├── database.sql        → Schéma SQL complet
├── .htaccess           → Configuration Apache
├── css/
│   └── style.css       → Styles complets (thème sombre industriel)
├── js/
│   └── app.js          → JavaScript applicatif
├── includes/
│   ├── db.php          → Connexion PDO
│   ├── auth.php        → Authentification
│   ├── functions.php   → Fonctions métier (CMUPACE, etc.)
│   ├── layout_top.php  → En-tête HTML (sidebar, topbar)
│   └── layout_bottom.php → Pied HTML
├── pages/
│   ├── fiche_mensuelle.php  → Suivi mensuel (cœur de l'app)
│   ├── mouvement_form.php   → Formulaire entrée/sortie
│   ├── analyse_croisee.php  → Analyse croisée dynamique
│   ├── articles.php         → Gestion articles
│   ├── stock.php            → État du stock
│   ├── mouvements.php       → Historique mouvements
│   ├── fournisseurs.php     → Gestion fournisseurs
│   ├── utilisateurs.php     → Gestion utilisateurs
│   ├── parametres.php       → Paramètres compte
│   ├── entrees.php          → Raccourci entrée
│   └── sorties.php          → Raccourci sortie
└── ajax/
    └── delete_mouvement.php → API suppression
```

---

## SUPPORT TECHNIQUE

Application développée sur la base des fichiers Excel de suivi des matériaux du projet MOUTOURWA - MAROUA.
Structure fidèle aux fiches originales avec amélioration UX/UI et automatisation des calculs CMUPACE.
