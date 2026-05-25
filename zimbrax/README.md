# 📧 ZimbraX — Webmail complet PHP/MySQL
## Clone Zimbra — Installation XAMPP

---

## 🚀 Fonctionnalités

| Module | Fonctionnalités |
|--------|----------------|
| 📧 **Messagerie** | Boîte de réception, envoi, réponse, transfert, brouillons, spam, corbeille, favoris ★, recherche, actions en masse |
| 📅 **Calendrier** | Mini-calendrier, création/modification/suppression d'événements, rappels, couleurs |
| 👥 **Contacts** | CRUD complet, favoris, recherche instantanée, avatars colorés |
| ✅ **Tâches** | Listes multiples, priorités, cases à cocher, ajout rapide |
| 🗂️ **Dossiers** | Personnalisés, drag-déplacer, compteur non-lus |
| ⚙️ **Paramètres** | Profil, mot de passe, signature, couleur avatar, IMAP/SMTP |
| 🔐 **Auth** | Login sécurisé, sessions PHP, multi-utilisateurs |
| 🎨 **UI** | Thème sombre, responsive, animations, raccourcis clavier |

---

## ⚙️ Installation XAMPP

### 1. Copier les fichiers
```
C:\xampp\htdocs\zimbrax\
```

### 2. Créer la base de données
1. Ouvrir **http://localhost/phpmyadmin**
2. Créer une base : `zimbrax`
3. Onglet **Importer** → sélectionner `database.sql`
4. Cliquer **Exécuter**

### 3. Ouvrir l'application
```
http://localhost/zimbrax/
```

---

## 🔐 Comptes par défaut

| Utilisateur | Email | Mot de passe |
|-------------|-------|-------------|
| **admin** | admin@zimbrax.cm | `password` |
| **alice** | alice@zimbrax.cm | `password` |

> Les deux comptes peuvent s'envoyer des emails en interne !

---

## 📂 Structure

```
zimbrax/
├── index.php              # App shell principale
├── login.php              # Authentification
├── logout.php
├── database.sql           # Schéma + données démo
├── includes/
│   └── config.php         # Config BDD + helpers
├── css/app.css            # Thème sombre complet
├── js/app.js              # Logique frontend (AJAX)
├── api/
│   ├── emails.php         # API emails (list/read/send/delete/search)
│   ├── calendar.php       # API calendrier (CRUD)
│   ├── contacts.php       # API contacts (CRUD)
│   ├── tasks.php          # API tâches (CRUD)
│   └── folders.php        # API dossiers
├── modules/
│   └── settings/          # Page paramètres
└── uploads/
    └── attachments/       # Pièces jointes uploadées
```

---

## 🎹 Raccourcis clavier

| Touche | Action |
|--------|--------|
| `Esc` | Fermer compose / modales |
| `Enter` | Dans recherche → lancer |
| `Enter` | Dans champ tâche → ajouter |

---

## 🔧 Configuration IMAP/SMTP externe

Pour connecter un vrai compte Gmail ou Outlook :

1. Aller dans **Paramètres → Serveur Mail**
2. Gmail : `imap.gmail.com:993` / `smtp.gmail.com:587`
3. Utiliser un **mot de passe d'application** (pas le mot de passe principal)
4. Activer IMAP dans les paramètres Gmail

---

## 💡 Personnalisation

### Ajouter un utilisateur
```sql
INSERT INTO users (username, email, password, display_name)
VALUES ('jean', 'jean@zimbrax.cm', '$2y$10$...', 'Jean Dupont');
-- Générer le hash: password_hash('MonMotDePasse', PASSWORD_DEFAULT)
```

### Changer le nom de l'app
Dans `includes/config.php` :
```php
define('APP_NAME', 'MonMail');
define('BASE_URL', 'http://localhost/zimbrax/');
```

---

*ZimbraX v2.0 — PHP 7.4+ / MySQL 5.7+ / XAMPP*
