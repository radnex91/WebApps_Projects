# Gestion de Caisse Décentralisée — Spécification

**Date :** 2026-05-06
**Module :** Caisse
**Inspiration :** SAGE 100 Saisie de Caisse Décentralisée (SCD)

---

## Objectif

Permettre à plusieurs caissiers de gérer leur propre tiroir-caisse de façon indépendante dans la même pharmacie. Chaque caissier peut ouvrir une session sur n'importe quel poste libre, enregistrer des mouvements (ventes, retraits, dépôts, dépenses), consulter l'état de sa caisse (X), et clôturer sa session (Z) avec calcul d'écart.

Un dashboard temps réel permet à l'admin/pharmacien de superviser l'état de toutes les caisses.

---

## Base de données

### Nouvelle table : `caisses`

| Colonne   | Type                    | Description                    |
|-----------|-------------------------|--------------------------------|
| id        | INT PK AUTO_INCREMENT   | Identifiant                    |
| nom       | VARCHAR(100) NOT NULL   | Libellé (ex: "Caisse 1")      |
| actif     | TINYINT(1) DEFAULT 1    | Soft delete                    |
| created_at| DATETIME DEFAULT NOW()  | Date de création               |

### Nouvelle table : `sessions_caisse`

| Colonne         | Type                         | Description                              |
|-----------------|------------------------------|------------------------------------------|
| id              | INT PK AUTO_INCREMENT        | Identifiant                              |
| caisse_id       | INT FK → caisses.id          | Poste utilisé                            |
| caissier_id     | INT FK → utilisateurs.id     | Qui a ouvert la session                  |
| fond_initial    | DECIMAL(10,2) NOT NULL       | Fond de caisse à l'ouverture             |
| date_ouverture  | DATETIME NOT NULL            | Début de session                         |
| date_fermeture  | DATETIME NULL                | Fin de session (NULL si ouverte)         |
| solde_attendu   | DECIMAL(10,2) NULL           | Solde calculé à la fermeture             |
| solde_reel      | DECIMAL(10,2) NULL           | Solde compté physiquement                |
| ecart           | DECIMAL(10,2) NULL           | solde_reel - solde_attendu               |
| statut          | ENUM('ouverte','fermee')     | État de la session                       |

### Nouvelle table : `mouvements_caisse`

| Colonne         | Type                                           | Description                          |
|-----------------|-------------------------------------------------|--------------------------------------|
| id              | INT PK AUTO_INCREMENT                           | Identifiant                          |
| session_id      | INT FK → sessions_caisse.id                     | Session concernée                    |
| type            | ENUM('entree','sortie')                         | Sens du mouvement                    |
| montant         | DECIMAL(10,2) NOT NULL                          | Montant                              |
| motif           | VARCHAR(255) NOT NULL                           | Libellé (vente, retrait, dépôt...)  |
| moyen           | ENUM('especes','carte','cheque','assurance')    | Mode associé (pour filtrage)        |
| reference_vente | VARCHAR(20) NULL                                | FK logique → ventes.reference        |
| created_at      | DATETIME DEFAULT NOW()                          | Horodatage                           |

---

## Permissions

| Code            | Libellé                              | Par défaut                     |
|-----------------|--------------------------------------|--------------------------------|
| `caisse.voir`   | Voir dashboard des caisses          | Admin, Pharmacien              |
| `caisse.gerer`  | Créer postes, forcer fermeture      | Admin                          |
| `caisse.ouvrir` | Ouvrir une session de caisse        | Admin, Pharmacien, Caissier    |

---

## Écrans

Tout est dans un seul fichier `modules/caisse.php` avec 4 vues selon le paramètre `?action=`.

### Vue dashboard (`?action=` par défaut)

Tableau des postes avec leur état :

| Colonne      | Contenu                                                |
|-------------|--------------------------------------------------------|
| Poste       | Nom de la caisse                                       |
| Statut      | Badge vert "Ouverte" ou badge gris "Fermée"           |
| Caissier    | Nom du caissier (ou "—" si fermée)                    |
| Ouvert depuis| Durée écoulée depuis l'ouverture (si ouverte)          |
| Solde       | Solde théorique actuel (fond initial + entrées - sorties) |

Actions disponibles :
- **X de caisse** — consultation (bouton par ligne si session ouverte)
- **Z de caisse** — clôture (bouton par ligne si session ouverte)
- **Historique** — sessions passées, filtrable par poste et date
- Admin : **Forcer fermeture** avec motif obligatoire
- Admin : **+ Nouveau poste** (formulaire nom uniquement)

### Vue ouverture (`?action=ouvrir`)

Formulaire :
- Sélecteur du poste (ne liste que les postes sans session ouverte)
- Champ `fond_initial` (obligatoire, accepte 0, minimum 0)
- Bouton « Ouvrir la caisse »

POST → crée la `sessions_caisse` (statut = ouverte) → redirect vers le dashboard.

Règles :
- Un poste = une seule session ouverte à la fois
- Un caissier = une seule session ouverte à la fois
- Pas de vente sans caisse ouverte (bloqué au niveau du POS)

### Vue X de caisse (`?action=x&id=<session_id>`)

Fiche d'interrogation (lecture seule, n'affecte rien) :

- En-tête : Poste, Caissier, Date/heure ouverture
- Lignes détaillées :
  - Fond initial
  - + Ventes espèces (regroupées)
  - + Dépôts (entrées manuelles)
  - - Retraits (sorties manuelles)
  - - Dépenses (sorties manuelles)
- **SOLDE THÉORIQUE** (mis en évidence)
- Compteurs : nb tickets, répartition par mode de paiement
- Bouton [Imprimer X] pour ticket thermique

### Vue Z de caisse (`?action=z&id=<session_id>`)

Formulaire de clôture :

- Rappel du **SOLDE THÉORIQUE** calculé
- Champ `solde_reel` à saisir par le caissier (obligatoire)
- Calcul automatique en JS : `écart = solde_reel - solde_théorique`
- Affichage de l'écart avec couleur (vert si 0, orange si léger, rouge si important)
- Bouton [Imprimer Z] — ticket thermique avec toutes les lignes
- Bouton [Clôturer la session] — enregistre solde_reel + écart + date_fermeture → statut = fermée

Règle : seul le caissier propriétaire peut faire son Z. L'admin peut forcer via motif obligatoire.

---

## Modifications du POS (`modules/vente.php`)

### Au chargement de la page

```php
// Vérifier si le caissier a une session ouverte
$sessionActive = $db->prepare("
    SELECT s.*, c.nom AS caisse_nom
    FROM sessions_caisse s
    JOIN caisses c ON s.caisse_id = c.id
    WHERE s.caissier_id = ? AND s.statut = 'ouverte'
")->execute([currentUser()['id']])->fetch();

if (!$sessionActive) {
    flash('Vous devez ouvrir une caisse avant de pouvoir vendre.', 'error');
    header('Location: ' . APP_URL . '/modules/caisse.php?action=ouvrir');
    exit;
}
```

Affichage dans le bandeau du panier : « Caisse : Caisse 1 — Solde : 245 000 FCFA »

### À la validation d'une vente

Si `mode_paiement = 'especes'` → insertion automatique dans `mouvements_caisse` :

```sql
INSERT INTO mouvements_caisse (session_id, type, montant, motif, moyen, reference_vente)
VALUES (?, 'entree', ?, ?, 'especes', ?)
```

Motif = `"Vente $ref"`. Les autres modes (carte, chèque, assurance) n'alimentent PAS la caisse.

---

## Règles métier

1. **1 poste = 1 session ouverte max** — pas d'ouverture multiple sur un même poste
2. **1 caissier = 1 session ouverte max** — pas de sessions parallèles pour un même utilisateur
3. **Vente interdite sans caisse ouverte** — redirect avec message
4. **Mouvements post-clôture interdits** — une session fermée est immutable
5. **X de caisse illimité** — aucun impact sur les données
6. **Propriétaire = seul à pouvoir clôturer** — sauf admin (fermeture forcée avec motif)
7. **Fond initial >= 0** — une caisse peut démarrer sans espèces

---

## Fichiers à créer

| Fichier              | Contenu                                              |
|----------------------|------------------------------------------------------|
| `modules/caisse.php` | Page unique : dashboard, ouverture, X, Z, historique |
| `patch_caisse.sql`   | Création 3 tables + 3 permissions + données démo     |

## Fichiers à modifier

| Fichier                | Modification                                            |
|------------------------|--------------------------------------------------------|
| `modules/vente.php`    | Vérification session ouverte + mouvement auto à la vente |
| `includes/layout.php`  | Lien « Caisses » dans le menu latéral (section Ventes)  |

## Données de démo (patch_caisse.sql)

- 3 postes : Caisse 1, Caisse 2, Caisse 3 (tous actifs)
- 1 session simulée fermée (Yasmine sur Caisse 1, hier, avec quelques mouvements)

---

## Menu latéral

Ajout dans la section « Ventes » du sidebar :

```
📊 Caisses
```

Visible si `hasPermission('caisse.voir')` OU `hasPermission('caisse.ouvrir')`.
