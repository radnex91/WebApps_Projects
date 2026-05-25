# Module Clients — Spécification

**Date :** 2026-05-14
**Statut :** Validé

## Objectif

Créer un module de gestion des clients dans PharmaCare :
- Fiche client simple (nom, téléphone)
- Relier les clients aux ventes
- Permettre les ventes à crédit avec suivi des dettes
- Enregistrer des règlements partiels avec historique

## Base de données

### Nouvelle table `clients`

| Colonne | Type | Contrainte |
|---|---|---|
| id | INT | PK, AUTO_INCREMENT |
| nom | VARCHAR(150) | NOT NULL |
| telephone | VARCHAR(20) | — |
| actif | TINYINT(1) | DEFAULT 1 |
| created_at | DATETIME | DEFAULT CURRENT_TIMESTAMP |

### Nouvelle table `reglements`

| Colonne | Type | Contrainte |
|---|---|---|
| id | INT | PK, AUTO_INCREMENT |
| client_id | INT | FK → clients.id, NOT NULL |
| vente_id | INT | FK → ventes.id, nullable |
| montant | DECIMAL(10,2) | NOT NULL |
| mode_paiement | ENUM('espèces','carte','chèque','mobile') | NOT NULL |
| note | VARCHAR(255) | — |
| date_reglement | DATETIME | DEFAULT CURRENT_TIMESTAMP |

### Modification `ventes`

- `client_id INT NULL` — FK → clients.id
- `statut_paiement ENUM('payé','en_attente','partiel') DEFAULT 'payé'`
- `mode_paiement` modifié en `ENUM('espèces','carte','chèque','assurance','crédit')`
- Les colonnes existantes `client_nom` et `client_telephone` restent inchangées

### Migration

- Pas de migration des données historiques. On repart de zéro.
- Nouveau fichier `patch_clients.sql` pour créer les tables et altérer `ventes`.

## Permissions (5 nouvelles)

| Code | Libellé |
|---|---|
| `clients.voir` | Voir la liste des clients |
| `clients.ajouter` | Créer un client |
| `clients.modifier` | Modifier une fiche client |
| `clients.supprimer` | Désactiver un client |
| `clients.paiements` | Enregistrer des règlements |

### Attribution par rôle

- **Admin** (rôle 1) : toutes
- **Pharmacien** (rôle 2) : `clients.voir`
- **Caissier** (rôle 3) : `clients.voir`, `clients.ajouter`, `clients.paiements`

## Sidebar

Nouvelle entrée dans la section **Gestion** (entre Fournisseurs et Commandes) :

```php
<?php if(hasPermission('clients.voir')): ?>
<a href="<?= APP_URL ?>/modules/clients.php" class="nav-item <?= $activePage==='clients'?'active':'' ?>">
  <span class="nav-icon i-cyan"><?= icon('users',14) ?></span> Clients
</a>
<?php endif; ?>
```

## Module `modules/clients.php`

### Vue 1 : Liste (défaut)

- Tableau : Nom, Téléphone, Dette restante, Actions
- Dette restante = SUM(ventes à crédit dues) − SUM(règlements)
- Bouton "+ Nouveau client" (si `clients.ajouter`)
- Actions : Voir détail, Modifier, Désactiver (soft delete via `actif=0`)

### Vue 2 : Détail client

- Infos client (nom, téléphone, date création)
- État de la dette : montant restant dû, statut visuel
- Liste des ventes liées (date, référence, montant, statut paiement)
- Historique des règlements (date, montant, mode, note)
- Bouton "Enregistrer un règlement" → mini formulaire

### Vue 3 : Formulaire Ajout/Modification

- Champ Nom (requis)
- Champ Téléphone (optionnel)
- PRG : POST → redirect vers liste ou détail

### Enregistrement d'un règlement

- Formulaire dans la vue Détail
- Champs : Montant, Mode de paiement, Note
- POST → INSERT `reglements` → redirect vers détail client

## Intégration POS (`modules/vente.php`)

- Ajouter un champ de recherche/select client à côté des champs `client_nom` existants
- Si un client est sélectionné ET mode "crédit" choisi → `statut_paiement = 'en_attente'`
- Le mode de paiement "crédit" devient disponible uniquement si un client est sélectionné
- Si pas de client → vente normale (payé direct)

## Fichiers à créer / modifier

| Fichier | Action |
|---|---|
| `patch_clients.sql` | Créer |
| `modules/clients.php` | Créer |
| `includes/layout.php` | Modifier (sidebar + icon) |
| `modules/vente.php` | Modifier (client select + mode crédit) |
| `database.sql` | Modifier (ajout tables + permissions) |
