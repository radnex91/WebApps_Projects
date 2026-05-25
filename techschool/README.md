# 🎓 TechSchool — Gestion Établissement Technique
## Application PHP/MySQL complète sous XAMPP

---

## 📋 Fonctionnalités

| Module | Description |
|--------|-------------|
| 🏫 **Tableau de bord** | Statistiques, effectifs, top élèves, activité récente |
| 👩‍🎓 **Élèves** | CRUD complet, matricule auto, fiche détaillée, photo |
| 📝 **Inscriptions** | Inscription par année scolaire, suivi paiements |
| 🏫 **Classes** | Création, affectation matières & coefficients |
| 👨‍🏫 **Enseignants** | CRUD complet avec spécialité, grade, diplôme |
| ⭐ **Notes** | Saisie par matière/période, classement, mentions |
| 📄 **Bulletins** | Génération imprimable complète avec rang, appréciation |
| 🎨 **Config bulletin** | 6 onglets de personnalisation : logo, couleurs, mentions, signatures |
| 💰 **Paiements** | Suivi scolarité, modes de paiement, historique |
| 📊 **Rapports** | Stats, top élèves, taux paiement, répartition filières |
| 👥 **Utilisateurs** | 8 rôles, permissions granulaires par module |
| ⚙️ **Paramètres** | Années scolaires, filières, périodes/séquences |
| 📅 **Absences** | Saisie, justification, cumul par élève |

---

## ⚙️ Installation XAMPP

### Étape 1 — Copier les fichiers
```
Dézipper dans :  C:\xampp\htdocs\techschool\
```

### Étape 2 — Créer la base de données
1. Démarrer **Apache** et **MySQL** dans XAMPP Control Panel
2. Ouvrir **http://localhost/phpmyadmin**
3. Créer une base : **`techschool`** (utf8mb4_unicode_ci)
4. Aller dans l'onglet **Importer**
5. Sélectionner le fichier **`database.sql`**
6. Cliquer **Exécuter**

### Étape 3 — Accéder à l'application
```
http://localhost/techschool/
```

---

## 🔐 Comptes par défaut

| Compte | Rôle | Mot de passe |
|--------|------|-------------|
| **superadmin** | Super Administrateur | `password` |
| **directeur** | Directeur | `password` |
| **prof1** | Enseignant | `password` |
| **secretaire** | Secrétaire | `password` |

> ⚠️ Changez les mots de passe après la première connexion !

---

## 🎓 Structure des niveaux

```
Cycle Secondaire :  1ère Année → 2ème → 3ème → 4ème → Terminale
Cycle BTS :         BTS 1ère Année → BTS 2ème Année
```

## 🛠️ Filières disponibles

- **INFO** — Informatique
- **ELEC** — Électronique
- **MECA** — Mécanique Industrielle
- **GENIE_C** — Génie Civil
- **COMPTA** — Comptabilité & Gestion
- **ELECM** — Électromécanique
- **FROID** — Froid & Climatisation
- **AUTO** — Automobile

> Vous pouvez ajouter/modifier les filières dans **Paramètres → Filières**

---

## 👥 Système de rôles & permissions

| Rôle | Accès principal |
|------|----------------|
| Super Admin | Tout + gestion permissions |
| Admin | Tout sauf système |
| Directeur | Lecture, rapports, supervision |
| Censeur | Pédagogie, discipline |
| Enseignant | Notes, bulletins de ses classes |
| Secrétaire | Élèves, inscriptions, bulletins |
| Comptable | Paiements uniquement |
| Parent | Consultation uniquement |

> Le Super Admin peut activer/désactiver chaque permission pour chaque rôle depuis **Utilisateurs → Permissions**

---

## 🖨️ Personnalisation du bulletin

L'admin peut configurer depuis **Paramètres → Bulletin** :
- **En-tête** : Nom, sous-titre, adresse, téléphone, email, logos gauche/droit
- **Mentions** : Libellés et seuils personnalisables (Très Bien ≥16, Bien ≥14...)
- **Signatures** : 3 blocs de signatures avec titres
- **Style** : Couleurs d'en-tête, accent, lignes, police, filigrane
- **Options** : Rang, absences, appréciations, conseil de classe, décisions

---

## 📂 Structure des fichiers

```
techschool/
├── index.php                    # Dashboard
├── login.php / logout.php
├── database.sql                 # Schéma + données démo
├── includes/
│   ├── config.php               # Config + helpers
│   ├── header.php / footer.php
├── css/app.css                  # Thème professionnel
├── js/app.js
├── modules/
│   ├── eleves/                  # Gestion élèves
│   ├── enseignants/             # Gestion enseignants
│   ├── classes/                 # Classes + matières
│   ├── notes/                   # Saisie notes
│   ├── bulletins/               # Génération bulletins
│   ├── users/                   # Utilisateurs + permissions
│   ├── parametres/              # Config générale + bulletin
│   ├── inscription.php
│   ├── paiements.php
│   ├── rapports.php
│   └── absences.php
└── uploads/
    ├── logos/                   # Logos établissement
    └── photos/                  # Photos élèves
```

---

## 🔧 Configuration

Dans `includes/config.php` :
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');       // Votre user MySQL
define('DB_PASS', '');           // Votre mot de passe
define('DB_NAME', 'techschool');
define('BASE_URL', 'http://localhost/techschool/');
```

---

*TechSchool v1.0 — PHP 7.4+ / MySQL 5.7+ / XAMPP*
