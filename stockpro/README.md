# StockPro — Guide d'installation XAMPP

## 🚀 Installation rapide (5 minutes)

### Étape 1 — Copier les fichiers
Copiez le dossier `stockpro` dans :
```
C:\xampp\htdocs\stockpro\        (Windows)
/opt/lampp/htdocs/stockpro/      (Linux)
/Applications/XAMPP/htdocs/stockpro/  (Mac)
```

### Étape 2 — Créer la base de données
1. Démarrez **Apache** et **MySQL** dans XAMPP Control Panel
2. Ouvrez http://localhost/phpmyadmin
3. Cliquez **"Nouvelle base de données"** → tapez `stockpro` → Créer
4. Cliquez sur `stockpro` → onglet **"Importer"**
5. Choisissez le fichier `setup.sql` → **Exécuter**

### Étape 3 — Accéder à l'application
Ouvrez http://localhost/stockpro

### Identifiants par défaut
| Email | Mot de passe | Rôle |
|-------|-------------|------|
| admin@stockpro.com | password | Super Admin |
| gestionnaire@stockpro.com | password | Gestionnaire |
| caissier@stockpro.com | password | Caissier |

> ⚠️ **Changez les mots de passe** dans Utilisateurs dès la première connexion!

---

## 📋 Fonctionnalités

### Stock
- ✅ Gestion des produits (CRUD complet)
- ✅ Catégories avec couleurs personnalisables
- ✅ Fournisseurs
- ✅ Mouvements : Entrée, Sortie, Ajustement, Retour
- ✅ **Inventaire physique** avec calcul des écarts
- ✅ Alertes stock faible & rupture automatiques

### Analyse
- ✅ Tableau de bord avec **graphique d'activité** (7 jours)
- ✅ Marge potentielle (achat vs vente)
- ✅ Rapports par catégorie, top sorties
- ✅ **Export CSV** (Excel) produits et mouvements
- ✅ **État de stock imprimable** (PDF via navigateur)
- ✅ Rapport des alertes stock

### Administration
- ✅ **5 rôles utilisateurs** avec permissions granulaires
- ✅ Gestion complète des utilisateurs
- ✅ **Profil utilisateur** (modifier infos + changer mot de passe)
- ✅ **Personnalisation entreprise** : logo, couleurs, devise, coordonnées

---

## 👥 Rôles utilisateurs

| Rôle | Accès |
|------|-------|
| **Super Admin** | Tout, y compris la configuration système |
| **Admin** | Gestion complète sauf config système |
| **Gestionnaire** | Produits, catégories, fournisseurs, mouvements, inventaire |
| **Caissier** | Sorties de stock uniquement |
| **Lecteur** | Consultation uniquement |

---

## 🎨 Personnalisation entreprise

Dans **Paramètres** (accessible via la sidebar) :
- **Logo** : Uploadez votre logo (PNG, JPG, SVG)
- **Couleurs** : Choisissez vos couleurs avec le sélecteur ou les préréglages (Indigo, Bleu, Violet, Rose, Vert, Orange, Rouge, Teal)
- **Infos** : Nom, slogan, devise (FCFA, EUR, USD...), coordonnées

---

## 📁 Structure des fichiers

```
stockpro/
├── index.php              # Page de connexion
├── logout.php             # Déconnexion
├── setup.sql              # Installation base de données
├── .htaccess              # Sécurité Apache
├── includes/
│   ├── config.php         # Configuration + fonctions + permissions
│   ├── header.php         # Navigation + layout CSS complet
│   └── footer.php         # Scripts JS
├── pages/
│   ├── dashboard.php      # Tableau de bord avec graphique
│   ├── produits.php       # Gestion produits avec filtres
│   ├── mouvements.php     # Entrées/Sorties/Ajustements
│   ├── inventaire.php     # Inventaire physique ← NOUVEAU
│   ├── categories.php     # Catégories colorées
│   ├── fournisseurs.php   # Fournisseurs
│   ├── rapports.php       # Analyses et statistiques
│   ├── exports.php        # Export CSV + impression ← NOUVEAU
│   ├── utilisateurs.php   # Gestion utilisateurs
│   ├── profil.php         # Profil utilisateur ← NOUVEAU
│   └── parametres.php     # Config entreprise (logo, couleurs)
└── uploads/
    └── logos/             # Logos uploadés
```

---

## ⚙️ Configuration (includes/config.php)

Si votre XAMPP utilise un mot de passe MySQL différent :
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', 'votre_mot_de_passe');  // ← Modifiez ici
define('DB_NAME', 'stockpro');
```

---

## 🔒 Sécurité en production
- Changez tous les mots de passe par défaut
- Le dossier `uploads/logos/` doit être accessible en écriture par Apache
- Activez HTTPS si déployé sur un vrai serveur


## 🚀 Installation rapide (5 minutes)

### Étape 1 — Copier les fichiers
Copiez le dossier `stockpro` dans :
```
C:\xampp\htdocs\stockpro\        (Windows)
/opt/lampp/htdocs/stockpro/      (Linux)
/Applications/XAMPP/htdocs/stockpro/  (Mac)
```

### Étape 2 — Créer la base de données
1. Démarrez **Apache** et **MySQL** dans XAMPP Control Panel
2. Ouvrez http://localhost/phpmyadmin
3. Cliquez **"Nouvelle base de données"** → tapez `stockpro` → Créer
4. Cliquez sur `stockpro` → onglet **"Importer"**
5. Choisissez le fichier `setup.sql` → **Exécuter**

### Étape 3 — Accéder à l'application
Ouvrez http://localhost/stockpro

### Identifiants par défaut
| Email | Mot de passe | Rôle |
|-------|-------------|------|
| admin@stockpro.com | password | Super Admin |

> ⚠️ **Changez le mot de passe** dans Utilisateurs dès la première connexion!

---

## 👥 Rôles utilisateurs

| Rôle | Accès |
|------|-------|
| **Super Admin** | Tout, y compris la configuration système |
| **Admin** | Gestion complète sauf config système |
| **Gestionnaire** | Produits, catégories, fournisseurs, mouvements |
| **Caissier** | Sorties de stock uniquement |
| **Lecteur** | Consultation uniquement |

---

## 🎨 Personnalisation entreprise

Dans **Paramètres** (accessible via la sidebar) :
- **Logo** : Uploadez votre logo (PNG, JPG, SVG)
- **Couleurs** : Choisissez vos couleurs avec le sélecteur ou les préréglages
- **Infos** : Nom, slogan, devise, coordonnées

---

## 📁 Structure des fichiers

```
stockpro/
├── index.php          # Page de connexion
├── logout.php         # Déconnexion
├── setup.sql          # Installation base de données
├── .htaccess          # Sécurité Apache
├── includes/
│   ├── config.php     # Configuration + fonctions
│   ├── header.php     # Navigation + layout
│   └── footer.php     # Scripts + fermeture
├── pages/
│   ├── dashboard.php  # Tableau de bord
│   ├── produits.php   # Gestion produits
│   ├── mouvements.php # Entrées/Sorties
│   ├── categories.php # Catégories
│   ├── fournisseurs.php # Fournisseurs
│   ├── rapports.php   # Rapports et analyses
│   ├── utilisateurs.php # Gestion utilisateurs
│   └── parametres.php # Config entreprise
└── uploads/
    └── logos/         # Logos uploadés
```

---

## ⚙️ Configuration (includes/config.php)

Si votre XAMPP utilise un mot de passe MySQL différent :
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', 'votre_mot_de_passe');  // ← Modifiez ici
define('DB_NAME', 'stockpro');
```

---

## 🔒 Sécurité en production
- Changez tous les mots de passe par défaut
- Le dossier `uploads/logos/` doit être accessible en écriture par Apache
- Activez HTTPS si déployé sur un vrai serveur
