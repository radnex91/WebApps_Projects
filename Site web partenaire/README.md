# Plateforme tarifs partenaires (PHP + MySQL)

Site pour mettre à disposition de vos partenaires **deux prix** par produit : **prix partenaire** et **prix client**. Conçu pour un **hébergement mutualisé** (PHP + MySQL), sans Node.js.

## Technologie

- **PHP** (version 7.4 ou 8.x selon l’hébergeur)
- **MySQL** (ou MariaDB)
- HTML, CSS, JavaScript (vanilla) — pas de build, pas de dépendances front

Compatible avec les offres mutualisées classiques (OVH, o2switch, Hostinger, etc.).

## Installation sur l’hébergement

### 1. Créer la base MySQL

Dans le panel de votre hébergeur (cPanel, phpMyAdmin, etc.) :

- Créer une **base de données** MySQL.
- Créer un **utilisateur** MySQL et lui donner tous les droits sur cette base.
- Noter : nom de la base, utilisateur, mot de passe. L’hôte est en général `localhost`.

### 2. Configurer le projet

Éditer **`config.php`** et renseigner :

```php
define('DB_HOST', 'localhost');      // souvent "localhost" en mutualisé
define('DB_NAME', 'votre_base');     // nom de la base créée
define('DB_USER', 'votre_utilisateur');
define('DB_PASS', 'votre_mot_de_passe');
define('DB_CHARSET', 'utf8mb4');
```

### 3. Créer les tables et le premier compte

- **Option A** : Ouvrir **`install.php`** dans le navigateur. Le fichier **`database.sql`** est appliqué (structure uniquement, sans les INSERT d’exemple), et un compte **admin** est ajouté (identifiant : `admin`, mot de passe : `Admin123!`). À modifier après première connexion. Puis supprimer ou protéger `install.php`.
- **Option B** : Dans phpMyAdmin, exécuter tout le fichier **`database.sql`** (structure + 30 produits d’exemple). Pour créer le premier admin sans passer par `install.php`, exécuter une fois **`seed_admin.php`** dans le navigateur.

### 4. Mise en ligne

Envoyer tous les fichiers du projet. Accès :

- **Connexion** : `https://votresite.com/login.php` (obligatoire pour partenaires et admin)
- Vue partenaire : `https://votresite.com/index.php` (après connexion partenaire ou admin)
- Administration : `https://votresite.com/admin.php` (réservé au rôle admin)

Si le site est dans un sous-dossier (ex. `https://votresite.com/partenaires/`), les liens relatifs (`api.php`, `styles.css`, etc.) continuent de fonctionner.

## Sécurité

- Le fichier **`config.php`** contient le mot de passe MySQL. Le `.htaccess` fourni interdit son accès direct en HTTP. Si possible, placez `config.php` **au-dessus de la racine web** et adaptez les `require` dans `db.php` et `api.php`.
- Après installation, supprimez ou protégez **`install.php`** pour éviter de recréer la base par erreur.

## Fonctionnalités

- **Connexion obligatoire** : partenaires et administrateur doivent se connecter (`login.php`). Déconnexion via le lien en haut à droite.
- **Vue partenaire** (`index.php`) : liste des produits (prix partenaire et prix client) avec **recherche** par nom, référence ou description.
- **Administration** (`admin.php`) : réservée au rôle admin. Gestion des produits (CRUD) avec **recherche** ; section **Comptes partenaires** pour créer les identifiants des partenaires (login, mot de passe, nom, email).
- **Base MySQL** : tables `produits` et `utilisateurs`. Modification possible via phpMyAdmin.

## Fichiers principaux

| Fichier      | Rôle |
|-------------|------|
| `config.php` | Identifiants MySQL (à configurer) |
| `auth.php`   | Session et contrôle d’accès (login, rôle) |
| `login.php`  | Page de connexion |
| `logout.php` | Déconnexion |
| `db.php`     | Connexion PDO et requêtes |
| `api.php`    | API REST (authentification requise ; produits + comptes partenaires) |
| `index.php`  | Page partenaires (accès après connexion) |
| `admin.php`  | Page administration (réservée admin) |
| `database.sql` | Schéma MySQL complet + données d’exemple (`install.php` n’exécute pas les INSERT) |
| `install.php`| Création des tables + premier compte admin (à supprimer après usage) |
| `seed_admin.php` | Création du premier admin si la base existait déjà (optionnel) |

## Structure table `produits`

| Colonne           | Type         | Description              |
|-------------------|--------------|--------------------------|
| id                | INT          | Clé primaire             |
| nom               | VARCHAR(255) | Nom du produit           |
| reference         | VARCHAR(100) | Référence                |
| description       | TEXT         | Description              |
| prix_partenaire   | DECIMAL(12,2)| Prix partenaire          |
| prix_client       | DECIMAL(12,2)| Prix client              |
| unite             | VARCHAR(10)  | Unité (€, $, etc.)       |
| actif             | TINYINT(1)   | 1 = visible, 0 = masqué  |
| created_at        | DATETIME     | Date de création         |
| updated_at        | DATETIME     | Dernière modification    |

## Ancienne version Node.js / SQLite

Les fichiers `server.js`, `db.js` et `package.json` ont été retirés. Le projet tourne uniquement en PHP + MySQL pour l’hébergement mutualisé.
