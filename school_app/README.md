# 🏫 COMPLEXE SCOLAIRE — Application Web PHP/MySQL
## Guide d'installation XAMPP

---

## 📋 FONCTIONNALITÉS

| Module | Description |
|--------|-------------|
| 🎓 **Élèves** | Inscription, fiche détaillée, historique scolaire, photo |
| 👩‍🏫 **Enseignants** | Gestion du personnel enseignant, spécialités |
| 📚 **Classes** | De la Maternelle (PS) à la Terminale, tous cycles |
| 👪 **Parents** | Tuteurs, contacts, liaison avec les élèves |
| 📝 **Inscriptions** | Par année scolaire, frais de scolarité |
| ⭐ **Notes** | Saisie par matière/période, moyennes automatiques |
| 📄 **Bulletins** | Génération et impression avec rang, mention, signatures |
| 🗓️ **Emploi du temps** | Grille hebdomadaire par classe, impression |
| 💰 **Paiements** | Suivi des frais, espèces/chèque/mobile money |
| 🕐 **Absences** | Signalement, justification, statistiques |
| 📊 **Rapports** | Statistiques, effectifs, top élèves, paiements par mois |
| ⚙️ **Paramètres** | Années scolaires, affectations, utilisateurs |

---

## ⚙️ INSTALLATION

### Étape 1 — Pré-requis
- XAMPP installé (Apache + MySQL + PHP 7.4+)
- XAMPP démarré (Apache et MySQL en vert)

### Étape 2 — Copier les fichiers
Copiez le dossier **`school_app`** dans :
```
C:\xampp\htdocs\school_app\
```

### Étape 3 — Créer la base de données
1. Ouvrez votre navigateur : **http://localhost/phpmyadmin**
2. Cliquez sur **"Nouvelle base de données"** → tapez `complexe_scolaire` → Créer
3. Cliquez sur l'onglet **"Importer"**
4. Sélectionnez le fichier `database.sql` (dans le dossier school_app)
5. Cliquez **"Exécuter"**

### Étape 4 — Accéder à l'application
Ouvrez : **http://localhost/school_app/**

---

## 🔐 CONNEXION PAR DÉFAUT

| Champ | Valeur |
|-------|--------|
| **Nom d'utilisateur** | `admin` |
| **Mot de passe** | `password` |

> ⚠️ Changez le mot de passe après la première connexion !

---

## 👥 RÔLES UTILISATEURS

| Rôle | Accès |
|------|-------|
| **admin** | Accès total |
| **directeur** | Tout sauf gestion système |
| **secretaire** | Élèves, inscriptions, absences |
| **enseignant** | Notes, emploi du temps, bulletins |
| **comptable** | Paiements, rapports financiers |

---

## 🏫 NIVEAUX SCOLAIRES INCLUS

**Maternelle** : Petite Section (PS) → Grande Section (GS)
**Primaire** : CP → CM2
**Collège** : 6ème → 3ème
**Lycée** : Seconde → Terminale

---

## 📂 STRUCTURE DES FICHIERS

```
school_app/
├── index.php              # Tableau de bord
├── login.php              # Page de connexion
├── logout.php
├── database.sql           # Script SQL complet
├── includes/
│   ├── config.php         # Configuration BDD + fonctions
│   ├── header.php         # En-tête & sidebar
│   └── footer.php
├── css/style.css          # Styles de l'application
├── js/app.js              # JavaScript
└── modules/
    ├── eleves/            # CRUD élèves
    ├── enseignants/       # CRUD enseignants
    ├── classes/           # Gestion des classes
    ├── parents/           # CRUD parents
    ├── inscription/       # Inscriptions annuelles
    ├── notes/             # Saisie des notes
    ├── bulletins/         # Génération bulletins
    ├── emploi_temps/      # Emploi du temps
    ├── absences/          # Suivi absences
    ├── rapports/          # Statistiques
    ├── paiements.php      # Suivi financier
    └── parametres.php     # Configuration
```

---

## 🛠️ PERSONNALISATION

### Modifier les informations de l'école
Dans `includes/config.php` :
```php
define('APP_NAME', 'Votre Complexe Scolaire');
define('BASE_URL',  'http://localhost/school_app/');
```

### Ajouter des matières
Dans phpMyAdmin, table `matieres` :
```sql
INSERT INTO matieres (code, nom, cycle, coefficient) 
VALUES ('INFO', 'Informatique', 'Collège', 2);
```

---

## 💡 UTILISATION RECOMMANDÉE

1. **Commencer par** créer les classes (Paramètres → ou module Classes)
2. **Ajouter les enseignants** et leurs affectations
3. **Enregistrer les élèves** et leurs parents
4. **Inscrire les élèves** dans leurs classes
5. **Saisir les notes** par matière et période
6. **Générer les bulletins** pour chaque élève

---

*Application développée pour XAMPP — PHP 7.4+ / MySQL 5.7+*
