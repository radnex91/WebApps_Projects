# Design: Sessions de Caisse (Ouverture/Fermeture)

**Date :** 2026-05-25
**Contexte :** Brenshop POS — La caisse doit être ouverte avant de faire une vente

## Résumé

Ajouter un mécanisme d'ouverture/fermeture de caisse avec suivi des sessions, fond de caisse initial, gestion des retraits/apports, et rapport Z de fermeture.

## Décisions clés

| Décision | Choix |
|---|---|
| Qui ouvre la caisse | Le caissier lui-même (autonome) |
| Solde d'ouverture | Obligatoire, montant saisi |
| Fermeture | Comptage physique + calcul de l'écart + rapport Z |
| Opérations mi-journée | Retraits et apports avec motif obligatoire |
| Qui peut fermer | Caissier + manager/admin |
| Qui est concerné | Tout le monde (admin, manager, caissier) |
| Multi-sessions/jour | Oui, plusieurs ouvertures/fermetures par jour autorisées |

## 1. Schéma base de données

### Nouvelle table : `caisse_sessions`

```sql
CREATE TABLE IF NOT EXISTS `caisse_sessions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `caisse_id` int(10) unsigned NOT NULL,
  `store_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `opening_balance` decimal(15,2) NOT NULL DEFAULT 0.00,
  `opening_time` timestamp NOT NULL DEFAULT current_timestamp(),
  `closing_balance_expected` decimal(15,2) DEFAULT NULL,
  `closing_balance_actual` decimal(15,2) DEFAULT NULL,
  `closing_discrepancy` decimal(15,2) DEFAULT NULL,
  `closed_by` int(10) unsigned DEFAULT NULL,
  `closing_time` timestamp NULL DEFAULT NULL,
  `status` enum('open','closed') NOT NULL DEFAULT 'open',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_caisse_id` (`caisse_id`),
  KEY `idx_store_id` (`store_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_status` (`status`),
  CONSTRAINT `fk_session_caisse` FOREIGN KEY (`caisse_id`) REFERENCES `caisses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_session_store` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`),
  CONSTRAINT `fk_session_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_session_closed_by` FOREIGN KEY (`closed_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
```

### Nouvelle table : `caisse_operations`

```sql
CREATE TABLE IF NOT EXISTS `caisse_operations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `session_id` int(10) unsigned NOT NULL,
  `type` enum('deposit','withdrawal') NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `reason` varchar(255) NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_session_id` (`session_id`),
  CONSTRAINT `fk_operation_session` FOREIGN KEY (`session_id`) REFERENCES `caisse_sessions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_operation_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
```

### Modification table `sales`

```sql
ALTER TABLE `sales`
  ADD COLUMN `session_id` int(10) unsigned DEFAULT NULL AFTER `caisse_id`,
  ADD KEY `idx_sale_session` (`session_id`),
  ADD CONSTRAINT `fk_sale_session` FOREIGN KEY (`session_id`) REFERENCES `caisse_sessions` (`id`) ON DELETE SET NULL;
```

## 2. Modèles

### CaisseSession (extends BaseModel)

- `getActiveSession(int $caisseId): ?array` — retourne la session ouverte pour une caisse
- `getActiveSessionForUser(int $userId, int $storeId): ?array` — session ouverte de l'utilisateur courant
- `open(int $caisseId, int $storeId, int $userId, float $openingBalance): int` — crée une session
- `close(int $sessionId, float $actualBalance, int $closedBy, ?string $notes): bool` — ferme la session (calcule expected + discrepancy)
- `getSessionSummary(int $sessionId): array` — résumé complet pour la fermeture et le rapport Z

### CaisseOperation (extends BaseModel)

- `getBySession(int $sessionId): array` — toutes les opérations d'une session
- `addDeposit(int $sessionId, float $amount, string $reason, int $userId): int`
- `addWithdrawal(int $sessionId, float $amount, string $reason, int $userId): int`

## 3. Contrôleur AJAX

`controllers/caisse_session_ajax.php` :

| Action | Méthode | Description |
|---|---|---|
| `open` | POST | Crée une session (params: caisse_id, opening_balance) |
| `close` | POST | Ferme la session (params: caisse_id, closing_balance_actual, notes?) |
| `status` | GET | Retourne l'état de la session active pour l'utilisateur courant |
| `summary` | GET | Résumé complet pour fermeture (params: session_id) |
| `report` | GET | Données du rapport Z (params: session_id) |
| `add_operation` | POST | Ajoute retrait ou apport (params: session_id, type, amount, reason) |
| `history` | GET | Historique des sessions (params: store_id, date?) |

## 4. Flux utilisateur

### Ouverture
1. Utilisateur arrive sur `views/pos.php`
2. Si aucune session active → écran d'ouverture :
   - Sélecteur de caisse (dropdown des caisses du magasin)
   - Champ "Fond de caisse" (FCFA)
   - Bouton "Ouvrir la caisse"
3. AJAX → `caisse_session_ajax.php?action=open`
4. Session créée → rechargement → POS normal

### Pendant la vente (barre POS)
- Badge affichant : nom caisse, heure ouverture, fond, ventes cash, solde attendu
- Bouton **[Retrait/Apport]** → modale avec type (dépôt/retrait), montant, motif
- Bouton **[Fermer]** → lance l'écran de fermeture

### Fermeture
1. Écran récapitulatif : fond + ventes cash + apports - retraits = solde attendu
2. Champ "Montant compté" (comptage physique)
3. Affichage de l'écart (vert si >= 0, rouge si négatif)
4. Confirmation → AJAX `close` → session marquée closed
5. Rapport Z affiché en modal, imprimable

### Rapport Z
Contenu : nom boutique, nom caisse, caissier, heures ouverture/fermeture, fond, ventes cash, apports, retraits, solde attendu, solde compté, écart, détail par mode de paiement, liste des opérations

## 5. Fichiers impactés

| Fichier | Action |
|---|---|
| `database.sql` | Ajout tables `caisse_sessions`, `caisse_operations` ; FK `session_id` sur `sales` |
| `models/CaisseSession.php` | Nouveau : classes CaisseSession + CaisseOperation |
| `controllers/caisse_session_ajax.php` | Nouveau : endpoints AJAX |
| `views/pos.php` | Écran ouverture + badge session + modales opérations + fermeture + rapport Z |
| `controllers/sale_controller.php` | Ajout `session_id` dans les données de vente |
| `includes/helpers.php` | `getActiveCaisseSession()`, adaptation de `isCaisseRequired()` |
| `includes/bootstrap.php` | Chargement de `CaisseSession.php` |

## 6. Règles métier

- Le fond de caisse minimum est 0 FCFA
- Une seule session ouverte par caisse à la fois
- Si une session est déjà ouverte sur une caisse, l'utilisateur est redirigé vers le POS
- Le motif est obligatoire pour les retraits/apports
- Le solde attendu = opening_balance + ventes cash + deposits - withdrawals
- Seules les ventes en espèces (cash, mobile_money, orange_money, momo) sont comptées dans le solde attendu (pas les ventes à crédit)
- Les retraits/apports sont autorisés uniquement sur une session ouverte
- une caisse déjà ouverte peut etre pausé pour etre repris plutard par le même caissier
