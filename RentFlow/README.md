# RentFlow - Système de Gestion Immobilière

Application web complète de gestion immobilière (Landlord Management System) développée en PHP natif avec architecture MVC.

## Architecture

```
RentFlow/
├── app/
│   ├── controllers/    # Contrôleurs (Landlord, Agency, Batch, Payment, Dashboard)
│   ├── models/         # Modèles (Landlord, Agency, Batch, Payment, Reminder)
│   └── views/          # Vues Bootstrap 5
│       ├── layouts/    # Template principal
│       ├── dashboard/
│       ├── landlords/
│       ├── agencies/
│       ├── batches/
│       ├── payments/
│       └── errors/
├── core/               # Cœur du framework MVC
│   ├── Controller.php
│   ├── Model.php
│   ├── Database.php
│   └── Router.php
├── config/             # Configuration
│   └── database.php
├── public/             # Point d'entrée (Front Controller)
│   ├── index.php
│   └── assets/
├── routes/             # Définition des routes
│   └── web.php
└── storage/            # Logs
    └── logs/
```

## Installation sous XAMPP

### 1. Copier le projet
Copiez le dossier `RentFlow` dans `C:\xampp\htdocs\`

### 2. Créer la base de données
1. Démarrez Apache et MySQL depuis le panneau XAMPP
2. Allez sur http://localhost/phpmyadmin
3. Importez le fichier `rentflow.sql` ou exécutez :
```sql
CREATE DATABASE rentflow;
USE rentflow;
-- (voir le contenu de rentflow.sql)
```

### 3. Configuration
Modifiez `config/database.php` si nécessaire :
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'rentflow');
define('DB_USER', 'root');
define('DB_PASS', ''); // Mot de passe XAMPP par défaut est vide
```

### 4. Accès à l'application
Ouvrez : http://localhost/RentFlow/public/

## Fonctionnalités

### Gestion des bailleurs (Landlords)
- CRUD complet avec pagination et recherche
- Stockage des contacts et détails de contrat

### Gestion des agences
- CRUD complet
- Association avec bailleurs et lots

### Gestion des lots (Batches)
- Regroupement des biens par agence et bailleur

### Gestion des paiements
- Enregistrement des loyers
- **Détection automatique du statut** :
  - `EARLY` : payé avant la date d'échéance
  - `ON_TIME` : payé le jour de l'échéance
  - `LATE` : payé après la date d'échéance
- Suivi des retards et rappels automatiques

### Dashboard
- Statistiques en temps réel
- Paiements par agence
- Paiements par bailleur
- Total des loyers
- Alertes de retard

### Reporting
- Export CSV des paiements
- Filtres par statut (retard, anticipé)
- Tableaux dynamiques (DataTables)

## Sécurité

- **PDO** avec requêtes préparées (protection SQL Injection)
- **XSS Protection** via `htmlspecialchars()`
- **Routing** sécurisé (pas d'accès direct aux fichiers)
- **Validation** des données côté serveur

## Technologies

- PHP 8+ (natif, sans framework)
- MySQL 5.7+
- Bootstrap 5 (UI)
- DataTables (tableaux dynamiques)
- JavaScript/jQuery

## Utilisation

### Ajouter un bailleur
1. Menu > Bailleurs > Ajouter
2. Remplir le formulaire
3. Enregistrer

### Enregistrer un paiement
1. Menu > Paiements > Ajouter
2. Sélectionner le lot
3. Saisir montant et dates
4. Le statut est calculé automatiquement

### Voir les retards
Menu > Paiements > Retards (ou via Dashboard)

## Auteur

Développé par un Senior PHP Developer - Architecture MVC native

## Licence

Open Source - Usage libre
