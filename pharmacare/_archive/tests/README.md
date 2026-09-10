# Tests PharmaCare — PHPUnit

## Prérequis

- PHP ≥ 8.0 (extensions : pdo_mysql, mbstring)
- MySQL/MariaDB local accessible avec les credentials de `config/env.php` (dev : `root` / mot de passe vide)
- Composer

## Installation

```bash
cd C:\xampp\htdocs\pharmacare
composer install
```

## Exécution

```bash
composer test
# ou
vendor/bin/phpunit
```

## Fonctionnement

- `phpunit.xml` force `PHARMACARE_ENV=test`.
- `tests/bootstrap.php` charge la config + helpers et initialise la BDD de test
  `pharmacare_test` (créée + import du schéma `database.sql` si la base est vide,
  avec `SET FOREIGN_KEY_CHECKS=0` pendant l'import pour tolérer l'ordre des tables).
- Les tests ne touchent **jamais** la base `pharmacare` (dev/prod).

## Couverture actuelle

| Fichier | Cible |
|---------|-------|
| `HelpersTest.php` | `e()`, `getParam()`, `fideliteActive()`, `fmtMoney()`, `genRef()` |
| `SecurityTest.php` | `csrf()`, `verifyCsrf()` (token valide), sémantique `hash_equals` |
| `ComptaTest.php` | `ecritureCreate()` — équilibrée (insert) + déséquilibrée (exception) |

## Limites connues (à adresser en Phase 2)

- `verifyCsrf()` appelle `die()` en cas d'échec (non capturable sans mock) →
  convertir en exception/HTTP 419 pour tester le rejet.
- `soldeSession()` est dupliquée dans `modules/caisse.php` et `modules/vente.php`
  (effets de bord au chargement) → extraire vers `includes/helpers.php` pour la tester.
- Pas encore de tests d'intégration sur les flux caisse/vente complets.