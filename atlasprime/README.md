# 🚚 Atlas Prime Logistics — Guide d'installation

## Prérequis
- XAMPP (Apache + MySQL/MariaDB + PHP 8.0+)
- Navigateur moderne

---

## Installation rapide

### Option A — Assistant d'installation (recommandé)
1. Copiez le dossier `atlas_prime/` dans `C:/xampp/htdocs/`
2. Démarrez Apache et MySQL dans XAMPP Control Panel
3. Ouvrez : **http://localhost/atlas_prime/setup.php**
4. Suivez les 3 étapes de l'assistant
5. **Supprimez `setup.php`** après installation

### Option B — Installation manuelle
1. Copiez le dossier dans `htdocs/atlas_prime/`
2. Ouvrez **phpMyAdmin** (http://localhost/phpmyadmin)
3. Importez le fichier `database.sql`
4. Vérifiez `includes/config.php` :
   ```php
   define('DB_HOST', '127.0.0.1');  // Important: 127.0.0.1 pas localhost
   define('DB_NAME', 'atlas_prime_logistics');
   define('DB_USER', 'root');
   define('DB_PASS', '');
   ```
5. Accédez à : **http://localhost/atlas_prime/login.php**

---

## Comptes de démonstration
| Rôle | Identifiant | Mot de passe |
|------|-------------|--------------|
| Admin | `admin` | `password` |
| Opérateur | `marie.d` | `password` |
| Superviseur | `jean.m` | `password` |

> **Note :** Le mot de passe `password` correspond au hash bcrypt par défaut de Laravel/PHP.  
> Si ça ne fonctionne pas, utilisez le setup.php pour redéfinir le mot de passe admin.

---

## Structure des fichiers
```
atlas_prime/
├── setup.php              ← Installer (à supprimer après)
├── login.php              ← Page de connexion
├── index.php              ← Tableau de bord
├── database.sql           ← Schéma + données initiales
├── includes/
│   ├── config.php         ← Configuration DB et app
│   ├── database.php       ← Classe PDO
│   ├── auth.php           ← Gestion sessions/auth
│   ├── functions.php      ← Fonctions utilitaires
│   ├── header.php         ← Template header + sidebar
│   ├── footer.php         ← Template footer
│   └── logout.php         ← Script déconnexion
├── pages/
│   ├── colis.php          ← Liste des colis
│   ├── nouveau_colis.php  ← Enregistrement colis
│   ├── detail_colis.php   ← Fiche détail + suivi
│   ├── edit_colis.php     ← Modification colis
│   ├── suivi.php          ← Suivi par numéro
│   ├── voyages.php        ← Liste des voyages
│   ├── nouveau_voyage.php ← Création voyage
│   ├── detail_voyage.php  ← Détail voyage + manifeste
│   ├── bordereau_print.php← Impression bordereaux
│   ├── bordereaux.php     ← Sélection bordereaux groupés
│   ├── rapports.php       ← Rapports & statistiques
│   ├── agences.php        ← Gestion agences (admin)
│   └── utilisateurs.php   ← Gestion utilisateurs (admin)
└── assets/
    ├── css/main.css       ← Styles principaux
    └── js/main.js         ← JavaScript
```

---

## Fonctionnalités

### Expéditions
- Enregistrement colis **normal** (par transporteur)
- Enregistrement colis **accompagné** (dans un voyage)
- Calcul automatique tarif/remise/total
- Gestion paiement (espèces, mobile money, virement, à la livraison)
- Mise à jour statut en un clic depuis la liste
- Historique complet de suivi avec localisation

### Voyages
- Création de voyages (transporteur, véhicule, chauffeur)
- Association automatique des colis accompagnés
- Changement de statut qui marque les colis livrés
- Manifeste de voyage imprimable

### Bordereaux & Impression
- **Bordereau individuel** (A4, format professionnel)
- **Bordereau groupé** (multi-colis en un seul document)
- **Manifeste de voyage** (tableau récapitulatif + signatures)
- Sélection par date, agence, ou voyage

### Rapports
- KPIs configurables par période
- Graphiques évolution + répartition paiements
- Performance par agence et par opérateur
- Top expéditeurs et destinataires

### Sécurité & Multi-utilisateurs
- Sessions concurrentes sécurisées (bcrypt)
- 3 niveaux : Admin, Superviseur, Opérateur
- Audit log de toutes les actions
- Timeout de session configurable (8h par défaut)

---

## Personnalisation
Modifier `includes/config.php` pour ajuster :
- `SESSION_LIFETIME` : durée de session
- `APP_NAME` : nom de l'application
- `APP_SLOGAN` : slogan

---

## Support
Atlas Prime Logistics v1.0 — PHP/MySQL — XAMPP
