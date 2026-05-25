# DanaySuite Mail

Application web PHP/MySQL inspiree de Zimbra, pensee pour tourner sur XAMPP avec une interface moderne.

## Fonctionnalites

- Authentification utilisateur avec sessions PHP
- Installation locale via `setup.php`
- Boite mail persistante dans MySQL
- Multi-utilisateurs avec roles `Administrator`, `Manager`, `User`
- Envoi simule entre utilisateurs connus de l'application
- Pieces jointes locales
- Recherche globale sur messages, contacts, agenda et taches
- Module d'administration pour creer des comptes et modifier les roles
- Contacts persistants
- Calendrier persistant
- Taches persistantes

## Installation sur XAMPP

1. Place le dossier `DanaySuite` dans `C:\xampp\htdocs\`.
2. Demarre `Apache` et `MySQL` dans XAMPP.
3. Ouvre `http://localhost/DanaySuite/setup.php`.
4. Clique sur `Installer l'application`.
5. Connecte-toi ensuite sur `http://localhost/DanaySuite/login.php`.

## Parametres par defaut

- Base de donnees: `danaysuite_mail`
- Hote MySQL: `127.0.0.1`
- Port MySQL: `3306`
- Utilisateur MySQL: `root`
- Mot de passe MySQL: vide

Tu peux modifier ces valeurs dans `config.php` si ton environnement XAMPP est different.

## Comptes de demonstration

- Email: `admin@danaysuite.local`
- Mot de passe: `admin123`
- Email: `amina.okoro@danaysuite.local`
- Mot de passe: `amina123`
- Email: `kevin.mendy@danaysuite.local`
- Mot de passe: `kevin123`

## Structure principale

- `config.php` : configuration application et MySQL
- `setup.php` : installation automatique de la base
- `login.php` : connexion utilisateur
- `logout.php` : deconnexion
- `index.php` : dashboard principal
- `includes/` : bootstrap, auth, PDO et logique metier
- `database/schema.sql` : schema SQL

## Notes

- Les messages sont simules localement: si un destinataire correspond a un utilisateur connu, il recoit une copie dans sa boite `inbox`.
- Les pieces jointes sont stockees dans `uploads/attachments/`.
- Si tu avais deja installe une ancienne version, les nouvelles tables sont creees automatiquement au chargement.
