# 🚌 TransportManager — Système de Gestion d'Agence de Transport
## Application PHP/MySQL complète sous XAMPP

---

## 📋 Modules développés

| Module | Acteurs | Fonctionnalités |
|--------|---------|----------------|
| 🎟️ **Vente de tickets** | Guichetier | Vente, impression 2 copies, transit |
| 📋 **Bordereaux** | Guichetier, Opérateur | Génération, impression 3 exemplaires |
| 📊 **Rapport journalier agence** | Chef guichet | Synthèse financière, par mode, par guichetier |
| 📈 **Rapport guichetier** | Guichetier, Chef | Détail par guichetier, par mode |
| 📉 **Rapports Direction** | Admin, Directeurs | Stats multi-agences, graphiques, top destinations |
| 🚀 **Voyages/Départs** | Guichetier, Chef | Programmation, gestion statuts |
| 💰 **Dépenses** | Chef guichet, Chef agence | Imputation avec limite, approbation |
| 🏦 **Versements** | Chef guichet | Banque, OM, MOMO, confirmation |
| 📅 **Réservations** | Guichetier | Création, confirmation → ticket, expiration |
| 🔄 **Transits** | Guichetier | Suivi passagers en correspondance |
| 🚌 **Véhicules** | Chef agence | CRUD, alertes assurance/visite |
| 👷 **Personnel** | Chef agence | Chauffeurs, convoyeurs, permis |
| 🏢 **Agences** | Chef agence, Admin | CRUD multi-agences |
| 💲 **Tarifs** | Chef agence | Prix par destination et classe |
| 👥 **Utilisateurs** | Admin, Super Admin | 6 rôles, permissions granulaires |
| 🔔 **Notifications** | Tous | Alertes système |
| ⚙️ **Paramètres** | Admin | Config entreprise, règles métier |

---

## ⚙️ Installation XAMPP

### Étape 1 — Copier les fichiers
```
Dézipper dans :  C:\xampp\htdocs\transport\
```

### Étape 2 — Créer la base de données
1. Démarrer **Apache** et **MySQL** dans XAMPP
2. Ouvrir **http://localhost/phpmyadmin**
3. Créer la base : **`transport_db`** (utf8mb4_unicode_ci)
4. Aller dans **Importer** → sélectionner **`database.sql`** → Exécuter

### Étape 3 — Accéder
```
http://localhost/transport/
```

---

## 🔐 Comptes démo (mot de passe : **password**)

| Compte | Rôle | Accès |
|--------|------|-------|
| `superadmin` | Super Admin | Tout |
| `admin` | Administrateur | Tout sauf debug |
| `chef_yde` | Chef Agence YDE | Agence Yaoundé |
| `chef_guichet1` | Chef de Guichet | Rapports, versements, annulations |
| `guichetier1` | Guichetier | Vente, bordereaux, réservations |
| `operateur1` | Opérateur saisie | Saisie bordereaux, dépenses |

---

## 👥 Rôles & Responsabilités

### Guichetier
- Vente tickets (2 copies : passager + agence)
- Génération bordereaux (3 exemplaires : chauffeur, comptabilité, transit)
- Gestion réservations
- Gestion transits (passagers en correspondance)

### Chef de Guichet
- Rapports journaliers agence & guichetier
- Dépenses ≤ 10 000 FCFA (configurable)
- Annulations tickets et voyages
- Versements bancaires (OM/MOMO/banque)

### Chef d'Agence
- Ajout véhicules, utilisateurs
- Création/modification tarifs
- Toutes dépenses
- Supervision agence

### Opérateur de Saisie
- Saisie bordereaux reçus
- Saisie dépenses
- Saisie reçus de banque

### Administrateur / Directeur
- Gestion profils utilisateurs
- Paramétrage des modules
- Rapports direction multi-agences
- Décisions stratégiques

---

## 🏢 Agences préchargées

| Code | Agence | Ville |
|------|--------|-------|
| YDE | Agence de Yaoundé | Yaoundé |
| DLA | Agence de Douala | Douala |
| BFT | Agence de Bafoussam | Bafoussam |
| NGO | Agence de N'Gaoundéré | N'Gaoundéré |
| BSM | Agence de Bertoua | Bertoua |
| GRB | Agence de Garoua | Garoua |

---

## 📄 Impressions disponibles

- **Ticket de voyage** : 2 exemplaires (passager + agence)
- **Bordereau chauffeur** : liste passagers + synthèse financière
- **Bordereau comptabilité** : idem
- **Bordereau transit** : idem
- **Rapport journalier agence** : synthèse complète avec signatures
- **Rapport journalier guichetier** : détail par ticket

---

## 📂 Structure fichiers

```
transport/
├── index.php               # Dashboard
├── login.php / logout.php
├── database.sql            # Schéma + données démo
├── includes/config.php     # Config, helpers, auth
├── includes/header.php + footer.php
├── css/app.css             # Thème professionnel bleu
├── js/app.js
└── modules/
    ├── tickets/            # vente.php, liste.php, imprimer.php
    ├── bordereaux/         # index.php, generer.php, imprimer.php
    ├── voyages/            # index.php, ajouter.php
    ├── rapports/           # agence.php, guichetier.php, direction.php
    ├── depenses/           # index.php, ajouter.php
    ├── versements/         # index.php, ajouter.php
    ├── reservations/       # index.php
    ├── transits/           # index.php
    ├── vehicules/          # index.php
    ├── agences/            # index.php
    ├── personnel/          # index.php
    ├── tarifs/             # index.php
    ├── users/              # index.php
    ├── parametres/         # index.php
    └── notifications.php
```

---

## 🔧 Configuration

Dans `includes/config.php` :
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'transport_db');
define('BASE_URL', 'http://localhost/transport/');
```

*TransportManager v1.0 — PHP 7.4+ / MySQL 5.7+ / XAMPP — Conforme au CDC fourni*
