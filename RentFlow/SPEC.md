# Application de Gestion des Loyers - RentFlow

## 1. Présentation du Projet

**Nom**: RentFlow - Gestion des Loyers
**Type**: Application Web PHP/MySQL
**Résumé**: Application de gestion locative permettant aux bailleurs de suivre leurs lots, agences, et paiements (avance/retard)
**Utilisateurs cibles**: Bailleurs, gestionnaires d'agences immobilières

## 2. Structure des Données

### Tables MySQL

#### `agences`
- `id` INT PRIMARY KEY AUTO_INCREMENT
- `nom` VARCHAR(100)
- `adresse` VARCHAR(255)
- `telephone` VARCHAR(20)
- `email` VARCHAR(100)
- `date_creation` DATETIME

#### `bailleurs`
- `id` INT PRIMARY KEY AUTO_INCREMENT
- `nom` VARCHAR(100)
- `prenom` VARCHAR(100)
- `email` VARCHAR(100)
- `telephone` VARCHAR(20)
- `date_creation` DATETIME

#### `lots`
- `id` INT PRIMARY KEY AUTO_INCREMENT
- `bailleur_id` INT FOREIGN KEY -> bailleurs.id
- `agence_id` INT FOREIGN KEY -> agences.id
- `adresse` VARCHAR(255)
- `ville` VARCHAR(100)
- `loyer_mensuel` DECIMAL(10,2)
- `type` ENUM('appartement','maison','local')
- `date_creation` DATETIME

#### `paiements`
- `id` INT PRIMARY KEY AUTO_INCREMENT
- `lot_id` INT FOREIGN KEY -> lots.id
- `mois` VARCHAR(7) -- format YYYY-MM
- `montant` DECIMAL(10,2)
- `date_paiement` DATE
- `statut` ENUM('avance','normal','retard')
- `date_creation` DATETIME

## 3. Fonctionnalités

### Dashboard
- Résumé des lots par bailleur
- Statistiques des paiements (avance/normal/retard)
- Graphiques de synthèse

### Gestion des Bailleurs
- Lister/Ajouter/Modifier/Supprimer des bailleurs

### Gestion des Agences
- Lister/Ajouter/Modifier/Supprimer des agences

### Gestion des Lots
- Lister/Ajouter/Modifier/Supprimer des lots
- Association bailleur-agence

### Gestion des Paiements
- Enregistrer les paiements par lot/mois
- Statut automatique basé sur la date:
  - AVANCE: paiement avant le 5 du mois
  - NORMAL: paiement entre le 5 et le 10
  - RETARD: paiement après le 10

### Rapports
-État des lieux par bailleur
-Historique des retards
-Paiements en avance

## 4. Technologies

- PHP 7.4+
- MySQL 5.7+
- Bootstrap 5 (front-end)
- Chart.js (graphiques)
- XAMPP (serveur local)

## 5. Structure des Fichiers

```
RentFlow/
├── index.php
├── db.php
├── README.md
├── assets/
│   └── css/
│       └── style.css
├── includes/
│   ├── header.php
│   ├── footer.php
│   └── db.php
├── pages/
│   ├── dashboard.php
│   ├── bailleurs.php
│   ├── agences.php
│   ├── lots.php
│   └── paiements.php
└── sql/
    └── database.sql
```