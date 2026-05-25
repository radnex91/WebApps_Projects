# Plan : Ajout de passagers et confirmation d'escale

## Contexte
Actuellement, quand un bordereau est `en_route` et arrive à une agence escale, cette agence peut uniquement confirmer le passage. L'utilisateur veut que le guichetier (et tout utilisateur avec la permission `tickets.create`) puisse aussi :
1. Vendre des tickets (ajouter des passagers) au bordereau à son escale
2. Confirmer le départ de son escale
3. Imprimer le bordereau mis à jour avec les nouveaux passagers

## Workflow
```
Bordereau en_route arrive à l'escale courante (en_attente)
  → Guichetier voit le bordereau dans la liste avec badge "Votre escale"
  → Clique sur "Gérer l'escale" → page valider.php
  → Voit les passagers existants + formulaire "Ajouter passager"
  → Vend des tickets (montee = escale courante, descente = escale future)
  → Confirme le départ de l'escale
  → Imprime le bordereau mis à jour
```

## Modifications

### 1. `modules/bordereaux/valider.php` — Ajout passager + formulaire de vente

**Accès** : Élargir à `tickets.create` OU `bordereaux.validate` (au lieu de seulement `bordereaux.validate`)

**Nouveau POST handler : `action=ajouter_passager`** (avant les handlers existants)
- Vérifier que le bordereau est `en_route` et que l'escale courante (`en_attente`) appartient à l'agence de l'utilisateur
- Créer un ticket (INSERT dans `tickets`) avec :
  - `voyage_id` = celui du bordereau
  - `escale_montee_id` = l'escale itinéraire correspondant à l'agence courante
  - `escale_descente_id` = l'escale choisie par l'utilisateur
  - `agence_id` = agence de l'utilisateur
  - `guichetier_id` = utilisateur courant
  - Champs passager, montant, classe, mode, etc.
- Créer une ligne dans `bordereau_lignes` avec les données dénormalisées
- Mettre à jour `bordereaux.nb_passagers` et `recette_brute`/`recette_nette`
- Transaction pour atomicité

**Nouveau AJAX handler : `ajax=tarifs_escale`**
- Retourne les tarifs disponibles pour le voyage, filtrés par escale de montée (l'escale courante)
- Format JSON similaire à `vente.php`

**Nouvelles sections HTML** (entre la progression escale et le bouton confirmer) :

**Section "Passagers du bordereau"** : Tableau des `bordereau_lignes` existants avec mise en évidence des passagers ajoutés à cette escale

**Section "Ajouter un passager"** : Formulaire dans un modal avec :
- Classe (cla/vip/spc)
- Escale de montée (auto-remplie = escale courante, lecture seule)
- Escale de descente (select des escales futures)
- Tarif auto-calculé selon montée/descente/classe
- Siège, nom, téléphone, CNI, mode paiement
- Bouton "Vendre le ticket"

**Logique JS** : Réutilise les patterns de `vente.php` (autocomplete passager, calcul reliquat, sélection classe/tarif) mais en version simplifiée sans correspondances ni vente libre

### 2. `modules/bordereaux/voir.php` — Bouton "Ajouter passager"

Ajouter un bouton conditionnel dans la barre d'actions quand :
- `bordereau['statut'] === 'en_route'`
- L'agence de l'utilisateur correspond à l'escale `en_attente`
- L'utilisateur a la permission `tickets.create`

```php
if (can('tickets.create') && $bordereau['statut'] === 'en_route' && $escaleAttente) {
    // Bouton "Ajouter passager" → lien vers valider.php?id=X
}
```

### 3. `modules/bordereaux/index.php` — Badge "Votre escale"

Dans la requête principale, ajouter une colonne calculée pour détecter si l'agence de l'utilisateur a une escale `en_attente` sur ce bordereau :
```sql
(SELECT COUNT(*) FROM bordereau_escales be WHERE be.bordereau_id=b.id AND be.statut='en_attente' AND be.agence_id=?) as escale_attente
```

Dans la colonne Statut du tableau, ajouter un badge quand `escale_attente > 0` :
```php
if ($brd['statut'] === 'en_route' && $brd['escale_attente'] > 0) {
    echo '<span class="badge badge-amber"><i class="fas fa-bell"></i> Votre escale</span>';
}
```

### 4. `database.sql` — Ajouter la permission

Ajouter `bordereaux.validate` dans les INSERT de permissions et l'assigner aux rôles `chef_agence` (3), `chef_guichet` (4), `guichetier` (5).

### 5. Pas de modifications à `imprimer.php`

La page d'impression charge déjà les `bordereau_lignes` dynamiquement, donc les nouveaux passagers apparaîtront automatiquement. Le statut `en_route` n'est pas modifié par l'impression.

## Fichiers à modifier

| Fichier | Action |
|---------|--------|
| `modules/bordereaux/valider.php` | Ajouter POST `ajouter_passager`, AJAX `tarifs_escale`, section passagers + formulaire de vente, élargir accès |
| `modules/bordereaux/voir.php` | Ajouter bouton "Ajouter passager" + "Confirmer escale" conditionnels |
| `modules/bordereaux/index.php` | Ajouter colonne `escale_attente` + badge visuel |
| `database.sql` | Ajouter permission `bordereaux.validate` |

## Vérification

1. Créer un bordereau, le valider, le mettre en route
2. Se connecter en tant que guichetier de l'agence escale suivante
3. Voir le badge "Votre escale" dans la liste des bordereaux
4. Cliquer → voir la page valider avec le formulaire d'ajout de passager
5. Ajouter un passager (montee = escale courante, descente = escale future)
6. Vérifier que le ticket est créé, la ligne bordereau ajoutée, et les montants mis à jour
7. Confirmer le départ de l'escale
8. Imprimer le bordereau → vérifier que les nouveaux passagers apparaissent
9. Vérifier que seuls les utilisateurs avec `tickets.create` ou `bordereaux.validate` peuvent accéder à la page