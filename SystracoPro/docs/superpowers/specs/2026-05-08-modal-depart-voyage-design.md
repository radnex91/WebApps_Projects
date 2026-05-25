# Modal Départ Voyage — Design Spec

**Date:** 2026-05-08
**Contexte:** Centraliser la génération de bordereau et la validation de départ dans un modal unique à 4 onglets.

## Objectif

Remplacer le workflow éclaté actuel (generer.php → imprimer.php → valider.php) par un modal unique
accessible depuis la liste des voyages et la fiche voyage. Le modal combine la sélection des tickets,
la saisie des frais, la validation du départ et le déblocage de l'impression en une seule interface.

## Comportement

### Règle métier
- Un seul bouton "Valider le départ" crée le bordereau ET valide le départ en une transaction.
- Les tickets doivent être déjà vendus (statut `vendu`) avant ouverture du modal. Pas de vente express dans le modal.
- Après validation : voyage → `en_cours`, bordereau → `en_cours`, tickets → `utilise`, escales créées depuis l'itinéraire.
- Le bouton Imprimer est débloqué uniquement après validation réussie.

### États du voyage supportés

| État voyage | Comportement modal |
|---|---|
| `programme` sans bordereau | Onglet Tickets Vendus actif avec checkboxes, bouton Valider actif |
| `programme` avec bordereau `genere` | Tickets du bordereau pré-sélectionnés, modifiable, bouton Valider actif |
| `en_cours` ou `arrive` | Modal lecture seule, bouton Imprimer débloqué, pas de validation |

## Interface — 4 onglets

### Onglet 1 : Info Voyage
- Numéro voyage, trajet (départ → arrivée), date/heure départ
- Véhicule (immatriculation), chauffeur, convoyeur, chef de piste
- Places totales, occupation actuelle (X/Y), taux de remplissage
- Lecture seule

### Onglet 2 : Tickets Vendus (sans bordereau)
- Tableau avec checkboxes : N° ticket, passager, téléphone, siège, classe, destination, montant, mode, type (libre/rattaché)
- Actions : Tout cocher / Tout décocher, compteur (sélectionnés + total FCFA)
- Filtre automatique : si type bordereau = "transit", masquer les tickets dont la destination est sur le trajet
- Si un bordereau `genere` existe déjà, ses tickets sont pré-cochés

### Onglet 3 : Tickets Utilisés (avec bordereau)
- Tableau lecture seule des tickets déjà associés à un bordereau pour ce voyage
- Mêmes colonnes que l'onglet 2, sans checkboxes
- Option de dissociation si permission `bordereaux.create`

### Onglet 4 : Infos Financières
- Type de bordereau (chauffeur / comptabilité / transit / direction)
- Champs : carburant (FCFA), péages (FCFA), avance chauffeur (FCFA), autres déductions (FCFA), observations
- Récapitulatif dynamique en bas : nb tickets, recette brute, déductions, **recette nette**
- Les valeurs se mettent à jour en temps réel (JS)

## Actions

- **Valider le départ** : POST AJAX → transaction SQL → succès → Imprimer débloqué, onglet 2 vidé, onglet 3 rempli
- **Imprimer** (débloqué après validation) : ouvre `bordereaux/imprimer.php?id=X` dans nouvel onglet
- **Fermer** : ferme le modal, recharge la page parent si une action a eu lieu

## Architecture technique

### Nouveaux fichiers
- `modules/voyages/depart_modal.php` — contenu HTML/JS du modal, inclus dans les pages qui en ont besoin
- `modules/voyages/depart_ajax.php` — endpoint AJAX :
  - `?action=load&voyage_id=X` → JSON avec données voyage, tickets vendus, tickets utilisés, bordereaux
  - `?action=valider` (POST JSON) → transaction création bordereau + validation départ

### Fichiers modifiés
- `modules/voyages/index.php` — ajouter bouton "Départ" par ligne (voyages `programme` et `en_cours`)
- `modules/voyages/voir.php` — remplacer les boutons dispersés par un bouton unique "Gérer le départ"

### Transaction SQL (action=valider)
1. Créer bordereau (INSERT `bordereaux`, statut=`en_cours`)
2. Créer `bordereau_lignes` pour chaque ticket sélectionné
3. Update `tickets` : `bordereau_id=X`, `statut=utilise`
4. Update `voyages` : `statut=en_cours`
5. Créer `bordereau_escales` depuis `itineraire_escales` (1ère escale = confirme, reste = en_attente)
6. LogAction + notifier prochaine escale
7. Commit → retour JSON `{success: true, bordereau_id: X}`

### Permissions
- Accès modal : `voyages.create`
- Validation départ : `bordereaux.create` + `voyages.create`
- Impression : `bordereaux.print`

## Conventions respectées
- Langue française pour tout le texte UI
- PDO prepared statements obligatoires
- CSRF sur tous les POST
- `sanitize()` pour tout affichage
- `genNumero()` pour génération des numéros
- Monnaie FCFA, formats de nombre français
- Modal CSS existant (`modal-over`, `modal`, classes du projet)
