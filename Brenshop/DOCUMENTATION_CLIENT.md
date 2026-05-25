# BrenShop Informatique — Guide Utilisateur

---

## 1. Présentation

**BrenShop Informatique** est un logiciel de caisse (POS) et de gestion commerciale conçu pour les magasins d'informatique au Cameroun.

**Fonctionnalités principales :**
- Vente au comptoir avec scan code-barres
- Gestion des stocks et des produits
- Transferts entre magasins
- Suivi des clients et facturation
- Tableaux de bord et rapports

---

## 2. Connexion

Rendez-vous sur l'adresse du logiciel et connectez-vous avec votre email et mot de passe.

**3 niveaux d'accès :**

| Rôle | Accès |
|---|---|
| **Administrateur** | Tout : gestion des boutiques, utilisateurs, paramètres |
| **Manager** | Gestion des produits, stocks, ventes, rapports |
| **Caissier** | Caisse, tableau de bord, historique des ventes |

---

## 3. Tableau de bord

Page d'accueil après connexion. Il affiche en temps réel :

- **Les ventes du jour** avec comparaison par rapport à la veille
- **Le nombre de transactions** effectuées aujourd'hui
- **Le revenu du mois** en cours
- **Les alertes de stock faible** (produits à réapprovisionner)
- **Le graphique des revenus mensuels** pour l'année
- **Le top 5 des produits** les plus vendus
- **Les dernières ventes** enregistrées

Les managers et admins peuvent filtrer les statistiques par caissier.

---

## 4. Point de Vente (Caisse)

Interface principale pour encaisser les clients.

**Comment ça marche :**

1. **Chercher un produit** : tapez le nom ou scannez le code-barres avec une douchette USB
2. **Le produit apparaît** dans le panier à gauche
3. **Ajustez les quantités** si nécessaire (+ / -)
4. **Appliquez une remise** en pourcentage si souhaité
5. **Sélectionnez le client** (optionnel)
6. **Saisissez le montant reçu** — la monnaie à rendre est calculée automatiquement
7. **Choisissez le mode de paiement** :
   - Espèces
   - Mobile Money (Orange Money, MoMo)
   - Crédit client
8. **Confirmez** — le stock est déduit automatiquement et la facture s'affiche

**Astuce** : vous pouvez sélectionner un entrepôt différent en haut de l'écran pour vendre depuis un autre stock.

---

## 5. Gestion des Produits

Permet d'ajouter, modifier ou désactiver des produits.

**Informations pour chaque produit :**
- Nom, description, photo
- Code-barres et référence (SKU)
- Prix d'achat (coût de revient) et prix de vente
- Catégorie et unité (pièce, kg, etc.)
- Seuil d'alerte de stock minimum

Les produits sont liés à une boutique. Un même produit peut exister dans plusieurs boutiques avec des prix différents.

---

## 6. Catégories

Permet de classer les produits par famille (ex: Ordinateurs, Accessoires, Réseau, Périphériques, Stockage).

Chaque catégorie peut avoir une couleur et une icône pour faciliter le repérage.

---

## 7. Gestion des Stocks

**Visualiser le stock** : voir les quantités disponibles par entrepôt.

**Entrée de stock** : quand vous recevez des marchandises, enregistrez l'entrée avec le prix de revient.

**Sortie de stock** : pour les retours fournisseurs ou les pertes.

**Ajustement** : pour corriger un écart après un inventaire physique.

**Alertes** : les produits en dessous du seuil minimum sont signalés en rouge.

**Historique** : tous les mouvements sont tracés (qui, quoi, quand, combien).

---

## 8. Transferts entre Entrepôts

Pour déplacer des produits d'un entrepôt à un autre.

**Comment ça marche :**

1. Créez un transfert : choisissez l'entrepôt source et destination
2. Sélectionnez les produits et quantités à transférer
3. Validez le transfert
4. Exécutez-le : le stock est automatiquement retiré de l'entrepôt source et ajouté à la destination

**Statuts** : En attente → Effectué.

---

## 9. Ventes (Historique)

Liste complète de toutes les ventes effectuées. Vous pouvez :

- Filtrer par période (date de début / date de fin)
- Voir le détail de chaque vente (produits, montants, mode de paiement)
- Accéder à la facture correspondante

---

## 10. Factures

Deux formats d'impression :

- **Facture A4** : format professionnel avec entête, tableau des articles, totaux TVA — pour le client
- **Ticket de caisse** : format compact pour imprimante thermique

Vous pouvez imprimer ou afficher la facture à l'écran.

---

## 11. Clients

Gérez votre fichier clients :

- Ajouter, modifier, supprimer un client
- Informations : nom, téléphone, email, adresse
- Suivi des achats : total cumulé et historique des ventes
- En caisse, associez une vente à un client (obligatoire pour le paiement à crédit)

---

## 12. Rapports

Analysez vos performances :

- **KPIs** : nombre de ventes, chiffre d'affaires, bénéfice estimé, panier moyen
- **Graphique mensuel** : visualisez l'évolution du CA et du nombre de ventes
- **Courbe 30 jours** : suivi quotidien
- **Top 10 produits** : classement des meilleures ventes avec profit
- **Comparaison par boutique** : voyez les performances de chaque magasin

Filtrez par période (aujourd'hui, ce mois, cette année, dates libres).

---

## 13. Administration

Réservé aux administrateurs.

**Boutiques** : ajouter, modifier les points de vente.

**Entrepôts** : gérer les lieux de stockage (dépôt principal, stock boutique, etc.).

**Utilisateurs** : créer des comptes, attribuer des rôles (admin / manager / caissier), choisir les boutiques et entrepôts accessibles.

**Paramètres** : personnaliser l'application :
- Nom, logo, sous-titre
- Couleurs et polices
- Taux de TVA
- Pied de facture / ticket

---

## 14. Commutation de Boutique

Si vous gérez plusieurs magasins, utilisez le sélecteur en haut de l'écran pour changer de boutique active. Toutes les données (stocks, produits, ventes) seront filtrées sur la boutique sélectionnée.

---

*BrenShop Informatique — POS System v1.0.0 — Document généré le 08/05/2026*
