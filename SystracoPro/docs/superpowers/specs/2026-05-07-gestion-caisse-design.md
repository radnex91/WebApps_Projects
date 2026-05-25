# Spec — Système de Gestion de Caisse Guichetier

**Date :** 2026-05-07
**Module :** `modules/caisse/`
**Rôle principal :** Guichetier (et supérieurs en supervision)

---

## 1. Résumé

Système de caisse individuelle pour chaque guichetier, avec cycle quotidien complet : ouverture (fond de caisse saisi), suivi en temps réel des ventes tickets, gestion des dépenses et autres recettes, transfert de tickets non embarqués entre guichetiers, et clôture avec rapprochement espèces physique.

---

## 2. Base de données

### 2.1 Nouvelle table : `caisses`

```sql
CREATE TABLE caisses (
    id                      INT AUTO_INCREMENT PRIMARY KEY,
    numero                  VARCHAR(20) NOT NULL UNIQUE,
    guichetier_id           INT NOT NULL,
    agence_id               INT NOT NULL,
    date_ouverture          DATETIME NOT NULL,
    fond_initial            DECIMAL(12,2) NOT NULL DEFAULT 0,
    statut                  ENUM('ouverte','fermee') DEFAULT 'ouverte',
    date_fermeture          DATETIME NULL,
    -- Totaux tickets (figés à la clôture)
    total_tickets_especes   DECIMAL(12,2) DEFAULT 0,
    total_tickets_om        DECIMAL(12,2) DEFAULT 0,
    total_tickets_momo      DECIMAL(12,2) DEFAULT 0,
    total_tickets_carte     DECIMAL(12,2) DEFAULT 0,
    total_tickets_cheque    DECIMAL(12,2) DEFAULT 0,
    nb_tickets_vendus       INT DEFAULT 0,
    nb_tickets_annules      INT DEFAULT 0,
    montant_annulations     DECIMAL(12,2) DEFAULT 0,
    -- Mouvements manuels
    total_depenses          DECIMAL(12,2) DEFAULT 0,
    total_autres_recettes   DECIMAL(12,2) DEFAULT 0,
    -- Transferts
    transfert_emis          DECIMAL(12,2) DEFAULT 0,
    transfert_recu          DECIMAL(12,2) DEFAULT 0,
    -- Rapprochement clôture
    solde_physique          DECIMAL(12,2) DEFAULT 0,
    ecart                   DECIMAL(12,2) DEFAULT 0,
    observations            TEXT,
    created_at              TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at              TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY(guichetier_id) REFERENCES utilisateurs(id),
    FOREIGN KEY(agence_id)     REFERENCES agences(id)
);
```

### 2.2 Nouvelle table : `mouvements_caisse`

Opérations manuelles hors tickets (dépenses, autres recettes).

```sql
CREATE TABLE mouvements_caisse (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    caisse_id       INT NOT NULL,
    type            ENUM('depense','recette') NOT NULL,
    libelle         VARCHAR(300) NOT NULL,
    montant         DECIMAL(12,2) NOT NULL,
    mode_paiement   ENUM('especes','om','momo','carte','cheque') DEFAULT 'especes',
    date_mouvement  DATETIME DEFAULT CURRENT_TIMESTAMP,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(caisse_id) REFERENCES caisses(id) ON DELETE CASCADE
);
```

### 2.3 Nouvelle table : `transferts_caisse`

Traçabilité des transferts de tickets entre caisses.

```sql
CREATE TABLE transferts_caisse (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    caisse_source_id  INT NOT NULL,
    caisse_dest_id    INT NULL,  -- NULL si le destinataire n'a pas encore ouvert sa caisse
    guichetier_source_id INT NOT NULL,
    guichetier_dest_id   INT NOT NULL,
    montant_total     DECIMAL(12,2) NOT NULL,
    nb_tickets        INT DEFAULT 0,
    created_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(caisse_source_id) REFERENCES caisses(id),
    FOREIGN KEY(caisse_dest_id)   REFERENCES caisses(id),
    FOREIGN KEY(guichetier_source_id) REFERENCES utilisateurs(id),
    FOREIGN KEY(guichetier_dest_id)   REFERENCES utilisateurs(id)
);
```

### 2.4 Nouvelle table : `transfert_tickets`

Liaison entre un transfert et les tickets transférés.

```sql
CREATE TABLE transfert_tickets (
    transfert_id    INT NOT NULL,
    ticket_id       INT NOT NULL,
    PRIMARY KEY(transfert_id, ticket_id),
    FOREIGN KEY(transfert_id) REFERENCES transferts_caisse(id) ON DELETE CASCADE,
    FOREIGN KEY(ticket_id)    REFERENCES tickets(id)
);
```

### 2.5 Nouvelle permission

```sql
INSERT INTO permissions (code, module, nom) VALUES ('caisse.manage', 'finances', 'Gérer la caisse');

-- Attribution aux rôles existants (guichetier et supérieurs)
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r, permissions p
WHERE r.code IN ('guichetier','chef_guichet','chef_agence','admin','super_admin')
AND p.code = 'caisse.manage';
```

### 2.6 Tables existantes — pas de modification

- `tickets` : `guichetier_id` et `date_vente` déjà présents, suffisent pour le rattachement automatique.
- `rapports_guichetiers` : inchangé, reste utilisé pour le rapport journalier existant.

---

## 3. Module — Structure

```
modules/caisse/
  index.php      ← page principale (tout le workflow en un seul fichier)
```

Pattern cohérent avec le projet : 1 page = 1 action principale, logique inline PHP + formulaires + modals CSS.

---

## 4. Workflow

### 4.1 Ouverture de caisse

1. Guichetier arrive sur `index.php`, aucune caisse ouverte aujourd'hui
2. Message "Aucune caisse ouverte" + bouton "Ouvrir la caisse"
3. Modal : champ `fond_initial` (montant saisi manuellement)
4. Validation POST : création d'une caisse avec `statut='ouverte'`, `numero` généré via `genNumero()`

**Contrainte :** 1 seule caisse par jour par guichetier — vérification avant création.

### 4.2 Dashboard — Caisse ouverte

Affichage en temps réel (lecture des tickets + mouvements) :

| Zone | Contenu |
|------|---------|
| En-tête | N° caisse, heure ouverture, statut OUVERTE |
| Fond de caisse | Montant saisi à l'ouverture |
| Transfert reçu | Si un autre guichetier a transféré des tickets → montant |
| Tickets vendus | Par mode de paiement (espèces, OM, MoMo, carte, chèque) |
| Tickets annulés | Nombre et montant |
| Dépenses | Total dépenses du jour |
| Autres recettes | Total recettes manuelles |
| Solde théorique espèces | Fond + transfert_recu + ventes espèces + recettes espèces − dépenses espèces |
| Actions | Boutons "+ Dépense", "+ Autre recette", "Clôturer", "Imprimer rapport" |

### 4.3 Ajout de dépense / autre recette

1. Clic sur le bouton → modal
2. Champs : libellé, montant, mode_paiement (défaut espèces)
3. POST → insertion dans `mouvements_caisse`
4. Retour au dashboard mis à jour

### 4.4 Transfert de tickets (étape de clôture)

1. Guichetier clique "Clôturer" → modal récapitulatif
2. Section "Tickets transférables" : liste des tickets `vendu` ou `reserve` dont le voyage n'est pas encore arrivé
3. Guichetier coche les tickets à transférer → choisit le guichetier destinataire dans un `<select>`
4. Validation → mise à jour `guichetier_id` sur les tickets → création entrée `transferts_caisse` + `transfert_tickets`
5. Mise à jour `transfert_emis` sur la caisse source, et `transfert_recu` sur la caisse destination (si elle existe déjà ; sinon le montant sera appliqué à l'ouverture)

**Règle :** Transfert illimité pour les tickets `vendu` et `reserve`, jusqu'à ce qu'ils passent en `utilise` (embarqué) ou `annule`.

### 4.5 Clôture

1. Récapitulatif complet de la journée
2. **Solde espèces théorique** calculé automatiquement
3. Champ "Espèces comptées physiquement"
4. **Écart** = Physique − Théorique (positif = excédent, négatif = manquant)
5. Champ "Observations"
6. Confirmation → `statut='fermee'`, toutes les colonnes de totaux sont figées
7. Affichage du récapitulatif imprimable (style `@media print`)

---

## 5. Formules

### Solde espèces théorique (temps réel)

```
T = Fond initial
  + Transfert reçu
  + SUM(tickets.montant_total WHERE mode_paiement='especes' AND statut='vendu')
  + SUM(mouvements_caisse WHERE type='recette' AND mode_paiement='especes')
  − SUM(mouvements_caisse WHERE type='depense' AND mode_paiement='especes')
  − SUM(tickets WHERE statut='annule' AND mode_paiement='especes')  [annulés par CE guichetier]
```

### Écart (à la clôture)

```
Écart = Espèces comptées physiquement − Solde théorique
```

---

## 6. Règles métier

| Règle | Détail |
|-------|--------|
| 1 caisse/jour/guichetier | Contrainte UNIQUE (guichetier_id, DATE(date_ouverture)) |
| Vente sans caisse | Bloqué. `vente.php` vérifie la caisse ouverte avant d'autoriser la vente |
| Tickets annulés | Appartiennent à la caisse du guichetier qui annule. Si le ticket original a été vendu par un autre guichetier, l'annulation est tracée dans la caisse de celui qui annule. |
| Fond de caisse = 0 | Autorisé |
| Dépense > solde espèces | Autorisé (solde théorique peut être négatif) |
| Réouverture après clôture | Non autorisé |
| Transfert | Illimité pour tickets `vendu` et `reserve`. Bloqué pour `utilise` et `annule`. |
| Supervision | Chef guichet / chef agence / admin peuvent voir toutes les caisses de leur agence en lecture seule |
| Clôture par un supérieur | Un chef peut clôturer une caisse de guichetier (utile si le guichetier oublie) |
| Garde de déconnexion | Si un guichetier avec caisse ouverte tente de se déconnecter → modal "Pause ou Clôture ?". Si "Pause" → déconnexion autorisée, caisse reste ouverte. Si "Clôture" → refus de déconnexion, redirection vers la page caisse pour clôturer. |
| Déconnexion sans caisse | Si le guichetier n'a pas de caisse ouverte, déconnexion normale sans question. |

---

## 7. Permissions

| Permission | Rôles |
|-----------|-------|
| `caisse.manage` | guichetier, chef_guichet, chef_agence, admin, super_admin |
| Voir/modifier sa propre caisse | `caisse.manage` |
| Voir les caisses de l'agence | `caisse.manage` + rôle >= chef_guichet |
| Clôturer la caisse d'un autre | rôle >= chef_guichet |

---

## 8. Intégration avec l'existant

### Fichiers à modifier

| Fichier | Modification |
|---------|-------------|
| `includes/header.php` | Ajout lien sidebar "Caisse" sous FINANCES (visible si `can('caisse.manage')`) |
| `includes/config.php` | Ajout fonction `caisseOuverte()` et `requireCaisseOuverte()` pour bloquer les ventes sans caisse |
| `modules/tickets/vente.php` | Appel `requireCaisseOuverte()` avant la vente |
| `logout.php` | Intercepter la déconnexion : si guichetier + caisse ouverte → modal pause/clôture. Si "clôture" → redirection vers caisse. |
| `database.sql` | Ajout des 4 nouvelles tables + permission + role_permissions |

### Fichiers créés

| Fichier | Rôle |
|---------|------|
| `modules/caisse/index.php` | Page principale du module caisse |

---

## 9. Cas limites

1. **Guichetier oublie de fermer sa caisse** → Le chef peut la fermer depuis la supervision. La date de fermeture est enregistrée.
2. **Changement de jour (minuit passé)** → Si caisse toujours ouverte, les ventes d'après minuit sont encore rattachées à cette caisse (elle est datée du jour d'ouverture). Le guichetier doit fermer et rouvrir le lendemain.
3. **Ticket vendu un jour, annulé le lendemain** → L'annulation impacte la caisse du jour de l'annulation (celle qui est ouverte ce jour-là), pas la caisse d'origine.
4. **Transfert vers un guichetier sans caisse ouverte** → Le `caisse_dest_id` reste NULL dans `transferts_caisse`. À l'ouverture de sa caisse, le guichetier destinataire voit automatiquement la somme des transferts en attente (SUM des `transferts_caisse.montant_total` où `guichetier_dest_id = lui AND caisse_dest_id IS NULL`). Ces transferts sont alors rattachés à sa nouvelle caisse (`caisse_dest_id` mis à jour). Ce montant est affiché comme "Transfert reçu" et intégré dans le solde théorique.

---

## 10. Interface utilisateur

3 états :

1. **Aucune caisse** — Message + bouton "Ouvrir la caisse"
2. **Caisse ouverte** — Dashboard avec résumé temps réel + boutons d'action
3. **Clôture** — Récapitulatif + saisie espèces physiques + écart + impression

Style cohérent avec le reste de l'application (classes CSS existantes : `.card`, `.btn`, `.modal`, `.filter-bar`).
