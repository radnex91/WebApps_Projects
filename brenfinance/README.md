# BrenFinance Suite — Guide d'installation

## Prérequis
- PHP 7.4+ (recommandé PHP 8.1+)
- MySQL 5.7+ ou MariaDB 10.3+
- Serveur web : Apache (avec mod_rewrite) ou Nginx
- Extension PHP : PDO, pdo_mysql, json, session

---

## Installation

### 1. Copier les fichiers
Placez le dossier `brenfinance/` dans la racine de votre serveur web :
- **XAMPP** : `C:/xampp/htdocs/brenfinance/`
- **WAMP**  : `C:/wamp64/www/brenfinance/`
- **Linux** : `/var/www/html/brenfinance/`

### 2. Créer la base de données
Connectez-vous à MySQL et exécutez :
```sql
SOURCE /chemin/vers/brenfinance/sql/schema.sql;
```
Ou via phpMyAdmin :
- Cliquez "Importer"
- Sélectionnez `sql/schema.sql`
- Cliquez "Exécuter"

### 3. Configurer la connexion
Éditez `config/database.php` :
```php
define('DB_HOST', 'localhost');  // hôte MySQL
define('DB_NAME', 'brenfinance'); // nom base de données
define('DB_USER', 'root');        // utilisateur MySQL
define('DB_PASS', '');            // mot de passe MySQL
```

### 4. Configurer BASE_URL
Dans `includes/functions.php`, ligne 5 :
```php
define('BASE_URL', '/brenfinance'); // chemin depuis la racine web
```
Si installé à la racine : `define('BASE_URL', '');`

### 5. Accéder à l'application
Ouvrez : `http://localhost/brenfinance/`

**Compte administrateur par défaut :**
- Email    : `admin@brenfinance.cm`
- Mot de passe : `Admin@2024`

> ⚠️ Changez le mot de passe administrateur après la première connexion !

---

## Structure des fichiers
```
brenfinance/
├── config/
│   └── database.php          # Configuration BDD
├── includes/
│   ├── functions.php         # Fonctions globales + session
│   ├── header.php            # En-tête / sidebar
│   └── footer.php            # Pied de page
├── assets/
│   ├── css/app.css           # Styles principaux
│   └── js/app.js             # Scripts principaux
├── modules/
│   ├── caisse/index.php      # Module caisse
│   ├── tresorerie/index.php  # Module trésorerie
│   ├── engagements/index.php # Module engagements
│   ├── comptabilite/index.php# Module comptabilité
│   ├── budget/index.php      # Module budget
│   ├── reporting/index.php   # Reporting + graphiques
│   ├── audit/index.php       # Journal d'audit
│   ├── admin/index.php       # Administration
│   └── referentiels/index.php# Référentiels
├── sql/
│   └── schema.sql            # Schéma complet + données initiales
├── index.php                 # Page de connexion
├── dashboard.php             # Tableau de bord
└── logout.php                # Déconnexion
```

---

## Rôles et permissions

| Rôle          | Accès                                      |
|---------------|--------------------------------------------|
| super_admin   | Accès total à tous les modules             |
| daf           | Validation finale + reporting + budget     |
| comptable     | Caisse, banque, comptabilité, val. compta  |
| caissier      | Saisie et consultation caisse uniquement   |
| demandeur     | Création et suivi de ses propres demandes  |
| valideur_n1   | Validation hiérarchique des engagements    |

---

## Workflow d'engagement

```
Demandeur → [Soumis] → Valideur N1 → [Val. Hiérarchie]
         → Comptable → [Val. Comptable]
         → DAF       → [Val. DAF / Approuvé]
         → Caissier  → [Exécuté]
```

---

## Fonctionnalités par module

### Caisse
- Création et gestion de plusieurs caisses
- Ouverture/fermeture de session avec solde
- Saisie des entrées/sorties avec pièce automatique
- Historique des mouvements par caisse
- Contrôle solde insuffisant

### Trésorerie
- Gestion multi-comptes bancaires
- Saisie des opérations bancaires
- Suivi des soldes en temps réel

### Engagements
- Création de demandes avec priorité
- Workflow en 4 étapes de validation
- Vue Kanban par statut
- Détail et suivi de chaque engagement

### Comptabilité
- Journaux comptables (caisse, banque, OD...)
- Saisie d'écritures équilibrées multi-lignes
- Balance générale par exercice
- Grand livre par compte

### Budget
- Définition de budgets par exercice
- Lignes budgétaires avec seuil d'alerte
- Suivi consommation en % avec barre de progression
- Alertes visuelles dépassement

### Reporting
- Tableau de bord KPI temps réel
- Graphique flux caisse 6 mois (Chart.js)
- Répartition dépenses par service
- Synthèse comparative caisses

### Audit
- Journal complet de toutes les actions
- Filtrage par date, module, utilisateur
- Adresse IP et user-agent enregistrés

### Administration
- Gestion des utilisateurs avec rôles
- Paramètres de l'entreprise
- Gestion agences et services

### Référentiels
- Bénéficiaires et fournisseurs
- Types d'opérations personnalisables
- Plan comptable (SYSCOHADA compatible)

---

## Sécurité
- Mots de passe hashés avec bcrypt (PASSWORD_BCRYPT)
- Sessions PHP sécurisées
- Requêtes préparées PDO (protection SQL injection)
- Échappement HTML systématique (XSS)
- Journalisation de toutes les actions sensibles

---

## Support technique
Pour toute question : admin@brenfinance.cm
