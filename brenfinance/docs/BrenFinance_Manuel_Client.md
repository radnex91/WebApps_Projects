# BrenFinance Suite

## Manuel de Présentation de l'Application

### Gestion Financière pour les Organisations Camerounaises

---

**Version du document :** 1.0  
**Date :** Mai 2026  
**Confidentialité :** Document client

---

## Table des Matières

1. [Présentation Générale](#1-présentation-générale)
2. [Fonctionnalités Principales](#2-fonctionnalités-principales)
3. [Gestion des Caisse et Opérations](#3-gestion-des-caisses-et-opérations)
4. [Gestion des Engagements](#4-gestion-des-engagements)
5. [Trésorerie et Banque](#5-trésorerie-et-banque)
6. [Comptabilité](#6-comptabilité)
7. [Budget](#7-budget)
8. [Reporting et Analyse Financière](#8-reporting-et-analyse-financière)
9. [Paie et Ressources Humaines](#9-paie-et-ressources-humaines)
10. [Bons de Commande](#10-bons-de-commande)
11. [Ordres de Mission](#11-ordres-de-mission)
12. [Comptabilité Analytique](#12-comptabilité-analytique)
13. [Clôture d'Exercice](#13-clôture-dexercice)
14. [Administration et Sécurité](#14-administration-et-sécurité)
15. [Audit et Traçabilité](#15-audit-et-traçabilité)
16. [Assistant IA RADNEX](#16-assistant-ia-radnex)
17. [Rôles et Permissions](#17-rôles-et-permissions)
18. [Spécifications Techniques](#18-spécifications-techniques)

---

## 1. Présentation Générale

### Qu'est-ce que BrenFinance Suite ?

BrenFinance Suite est une application web complète de gestion financière, conçue spécifiquement pour les organisations camerounaises. Elle centralise l'ensemble des opérations financières d'une structure au sein d'une plateforme unique, sécurisée et intuitive.

L'application couvre l'intégralité du cycle financier : de la gestion quotidienne des caisses et des opérations bancaires, en passant par les engagements de dépenses avec leur circuit de validation multi-niveaux, jusqu'à la comptabilité conforme au SYSCOHADA, la gestion budgétaire, la paie et le reporting financier avancé.

### Objectifs

- **Centraliser** toutes les opérations financières dans un seul outil
- **Sécuriser** les processus de validation grâce à un circuit d'approbation multi-niveaux
- **Automatiser** la génération des numéros de pièces, les calculs de paie et les écritures comptables
- **Contrôler** les dépenses grâce au suivi budgétaire en temps réel
- **Auditer** l'ensemble des actions effectuées dans le système
- **Décider** grâce à des tableaux de bord et indicateurs financiers pertinents

### Valeurs Ajoutées

| Avantage | Description |
|---|---|
| Conformité SYSCOHADA | Plan comptable intégré conforme aux normes OHADA |
| Fiscalité camerounaise | Calcul de l'IRPP selon les barèmes en vigueur au Cameroun |
| Multi-agences | Gestion de plusieurs agences et services avec des responsables dédiés |
| Multi-caisses | Possibilité de gérer plusieurs caisses simultanément |
| Multi-devises | Support des devises avec le FCFA comme devise principale |
| Traçabilité totale | Chaque action est enregistrée dans le journal d'audit |

---

## 2. Fonctionnalités Principales

BrenFinance Suite s'articule autour de **19 modules** interconnectés :

| Module | Fonction |
|---|---|
| Caisse | Gestion des caisses et sessions de caisse |
| Opérations de Caisse | Enregistrement des entrées et sorties d'argent |
| Trésorerie / Banque | Gestion des comptes bancaires et opérations bancaires |
| Engagements | Demandes de dépenses avec circuit de validation |
| Ordres de Mission | Gestion des ordres de mission et déplacements |
| Comptabilité | Écritures comptables, journaux, grand livre, balance |
| Budget | Création et suivi des budgets par exercice |
| Reporting | Tableaux de bord, SIG, ratios financiers |
| Paie | Gestion de la paie et bulletins de salaire |
| Ressources Humaines | Gestion des employés et contrats |
| Bons de Commande | Commandes fournisseurs liées aux engagements |
| Comptabilité Analytique | Axes analytiques et répartition des coûts |
| Clôture d'Exercice | Inventaire, amortissements, clôture annuelle |
| Référentiels | Bénéficiaires, fournisseurs, plan comptable |
| Audit | Journal complet de toutes les actions |
| Administration | Utilisateurs, rôles, paramètres entreprise |
| Exercices | Gestion des exercices comptables |
| Décharge | Module de décharge (en développement) |
| RADNEX AI | Assistant financier intelligent |

---

## 3. Gestion des Caisses et Opérations

### 3.1 Gestion des Caisses

Le module Caisse permet de créer et gérer plusieurs caisses au sein de votre organisation. Chaque caisse est caractérisée par :

- Un **code** et un **libellé** unique
- Une **devise** de fonctionnement
- Un **solde initial** et un **solde actuel** mis à jour en temps réel
- Un **responsable** (caissier) assigné
- Un **statut** : ouverte, fermée ou en session

Les caisses fonctionnent par **sessions** : chaque session de caisse est ouverte avec un fond de caisse initial et fermée avec un solde de clôture. Le système calcule automatiquement l'écart entre le solde théorique et le solde physique.

### 3.2 Opérations de Caisse

Le module Opérations de Caisse permet d'enregistrer toutes les entrées et sorties d'argent :

- **Entrées (crédit)** : encaissements, reversements, exécutions d'engagements
- **Sorties (débit)** : paiements, avances, remboursements

**Fonctionnalités clés :**

- Génération automatique du numéro de pièce (ex: `PC-2025-00001`)
- Blocage des sorties insuffisantes (impossible de débiter plus que le solde disponible)
- Exécution partielle ou totale des engagements approuvés
- Possibilité de solder un engagement avec motif
- Annulation d'opérations avec écriture de contre-passation
- Impression du **Bon de Caisse** avec code QR et montant en lettres
- Recherche et filtrage des opérations

### 3.3 Transferts entre Caisses

Le système permet d'effectuer des transferts de fonds entre caisses avec suivi du statut (en cours, validé, annulé).

---

## 4. Gestion des Engagements

### 4.1 Présentation

Un engagement est une demande de dépense soumise à un circuit de validation multi-niveaux. C'est le cœur du contrôle des dépenses dans BrenFinance Suite.

Chaque engagement comprend :

- Un **numéro** généré automatiquement (ex: `ENG-2025-00001`)
- Le **demandeur** et son **service**
- Le **fournisseur** et le **bénéficiaire**
- L'**objet** de la dépense
- Un **montant total** calculé à partir des lignes de détail
- Une **priorité** : normale, urgente ou très urgente
- Des **fichiers joints** (devis, factures proforma, etc.)
- Une **imputation budgétaire** (compte comptable et ligne budgétaire)

### 4.2 Circuit de Validation

Le processus de validation suit **5 étapes** :

```
Étape 1 : Le demandeur crée l'engagement → Statut : Brouillon
                ↓
Étape 2 : Le demandeur soumet l'engagement → Statut : Soumis
                ↓
Étape 3 : Le validateur hiérarchique (N+1) examine
          → Valide → Statut : Validé hiérarchie
          → Rejette → Statut : Rejeté
          → Renvoie → Statut : Renvoyé (pour modification)
                ↓
Étape 4 : Le comptable vérifie l'imputation budgétaire
          → Valide → Statut : Validé comptable
          → Rejette → Statut : Rejeté
                ↓
Étape 5 : Le DAF approuve la dépense
          → Approuve → Statut : Approuvé
          → Rejette → Statut : Rejeté
                ↓
Étape 6 : Le caissier exécute le paiement
          → Exécution totale → Statut : Exécuté
          → Exécution partielle → Statut : Exécution partielle
                ↓
Étape 7 (optionnel) : Le caissier solde l'engagement avec motif
          → Statut : Soldé
```

Chaque étape est **traçable** : le système enregistre l'identité du validateur, la date, l'action et le commentaire associé.

### 4.3 Suivi et Statistiques

Un tableau de bord intégré affiche en temps réel :

- Le nombre et le montant total des engagements par statut
- Le taux d'exécution des engagements approuvés
- Une barre de progression pour les engagements partiellement exécutés
- Des filtres par statut, service, agence et exercice

---

## 5. Trésorerie et Banque

### 5.1 Gestion des Comptes Bancaires

Le module Trésorerie permet de gérer l'ensemble de vos comptes bancaires :

- Création de comptes avec banque, agence, numéro de compte et RIB
- Suivi du **solde en temps réel** par compte et global
- Enregistrement des opérations bancaires avec **date de valeur**
- **Réconciliation bancaire** : marquage des opérations rapprochées
- Import de relevés bancaires

### 5.2 Prévisions de Trésorerie

Le système intègre un module de prévisions de trésorerie permettant de :

- Planifier les encaissements et décaissements futurs
- Comparer le prévu au réalisé
- Anticiper les besoins de financement

---

## 6. Comptabilité

### 6.1 Conformité SYSCOHADA

BrenFinance Suite intègre un **plan comptable SYSCOHADA** complet avec les 7 classes :

| Classe | Intitulé |
|---|---|
| Classe 1 | Financement permanent |
| Classe 2 | Actif immobilisé |
| Classe 3 | Actif circulant |
| Classe 4 | Tiers |
| Classe 5 | Trésorerie |
| Classe 6 | Charges |
| Classe 7 | Produits |

### 6.2 Écritures Comptables

Le module Comptabilité offre :

- **Multi-journaux** : caisse, banque, ventes, achats, opérations diverses, ouverture, clôture
- Saisie d'écritures multi-lignes avec **vérification automatique** de l'équilibre débit/crédit
- Numérotation automatique des pièces comptables
- Lettrage des écritures
- Filtrage par journal et par exercice

### 6.3 États Comptables

- **Balance générale** : balance des comptes par exercice avec soldes début et fin
- **Grand livre** : détail des mouvements par compte avec solde cumulé
- Consultation des écritures par compte, période ou journal

---

## 7. Budget

### 7.1 Création des Budgets

Le module Budget permet de définir et suivre les budgets de votre organisation :

- Budgets par **exercice**, **agence** et **service**
- Trois **types de budget** : prévisionnel, révisé, supplémentaire
- Lignes budgétaires avec compte comptable, libellé et montant prévu

### 7.2 Suivi de l'Exécution Budgétaire

Pour chaque ligne budgétaire, le système affiche :

- Le **montant prévu**
- Le **montant engagé** (issu des engagements validés)
- Le **montant réalisé** (issu des opérations de caisse et bancaires)
- Le **taux de consommation** avec barre de progression visuelle
- Des **alertes visuelles** lorsque les seuils sont dépassés

Un seuil d'alerte configurable permet de recevoir un avertissement avant l'épuisement du budget.

---

## 8. Reporting et Analyse Financière

### 8.1 Tableau de Bord

Le module Reporting offre une vue d'ensemble de la situation financière :

- **Indicateurs clés** : total caisse, total banque, trésorerie globale, engagements en attente
- **Graphique des flux de trésorerie** sur 6 mois (diagramme en barres)
- **Répartition des dépenses** par service (diagramme circulaire)
- **Synthèse des caisses** avec variation des soldes

### 8.2 Soldes Intermédiaires de Gestion (SIG)

Le calcul automatique des SIG comprend **10 lignes** :

| Ligne SIG | Description |
|---|---|
| Marge commerciale | Ventes de marchandises - Coût d'achat des marchandises vendues |
| Production | Production vendue + stockée + immobilisée |
| Consommation de l'exercice | Achats consommés + services extérieurs |
| Valeur ajoutée | Marge + Production - Consommation |
| Excédent Brut d'Exploitation (EBE) | VA + Subventions - Impôts - Charges de personnel |
| Résultat d'exploitation | EBE + Autres produits/charges d'exploitation |
| Résultat financier | Produits financiers - Charges financières |
| Résultat ordinaire | Résultat d'exploitation + Résultat financier |
| Résultat hors activités ordinaires | Produits HAO - Charges HAO |
| Résultat net | Résultat ordinaire + Résultat HAO - Impôts |

### 8.3 Ratios Financiers

| Ratio | Formule | Utilité |
|---|---|---|
| Marge nette | Résultat net / Chiffre d'affaires | Rentabilité globale |
| Taux de marge brute | EBE / Valeur ajoutée | Performance opérationnelle |
| ROE | Résultat net / Capitaux propres | Rentabilité des fonds propres |
| Capacité de remboursement | Dettes financières / EBE | Endettement |
| Marge opérationnelle | Résultat d'exploitation / Chiffre d'affaires | Performance du cœur de métier |

### 8.4 Comparaison Annuelle

Le reporting permet une comparaison **année N vs année N-1** pour analyser les tendances d'évolution.

---

## 9. Paie et Ressources Humaines

### 9.1 Gestion de la Paie

Le module Paie automatise le traitement de la rémunération :

- **Périodes de paie** par mois et par exercice
- **Génération automatique** des bulletins pour tous les employés actifs
- **Calcul des composantes** :
  - Salaire de base
  - Indemnités et avantages
  - Déductions et retenues
  - **IRPP** (Impôt sur le Revenu des Personnes Physiques) calculé selon les **8 tranches du barème camerounais** (0% à 40%)
  - Cotisations patronales
- **Net à payer** calculé automatiquement
- Validation des périodes et enregistrement des paiements
- Support des **déclarations CNPS et DGI**

### 9.2 Gestion des Ressources Humaines

Le module RH permet de gérer :

- Les **fiches employés** avec informations personnelles et professionnelles
- Les **contrats** : type (CDD, CDI, stage, etc.), date de début, salaire de base
- Les **statistiques RH** : effectifs actifs, masse salariale, répartition par type de contrat et par agence
- L'**import en masse** des employés via fichier CSV
- Le **rattachement** d'un compte utilisateur à un employé

---

## 10. Bons de Commande

Le module Bons de Commande permet de gérer les commandes fournisseurs :

- Création de bons de commande liés à un **engagement** et un **fournisseur**
- Circuit de **statuts** : brouillon → émis → reçu partiel → reçu total → facturé → annulé
- Suivi des **réceptions partielles ou totales**
- Marquage comme **facturé** pour le suivi comptable
- **Export** en CSV/Excel
- Annulation avec traçabilité

---

## 11. Ordres de Mission

Le module Ordres de Mission gère les déplacements professionnels :

- Création avec destination, dates, moyens de transport, contacts d'urgence
- Plafonds d'hébergement et de restauration
- Circuit de validation en **2 étapes** : validateur hiérarchique (N+1) puis DAF
- Exécution du paiement en caisse après approbation
- Impression de l'ordre de mission
- Statistiques et suivi par statut

---

## 12. Comptabilité Analytique

La comptabilité analytique permet d'analyser les coûts par centre de responsabilité :

### 12.1 Axes Analytiques

- **Centres de coût**, **projets**, **activités**, **produits**, **régions**, **clients internes**
- Structure **hiérarchique** (parent/enfant) pour une analyse multi-niveaux

### 12.2 Clés de Répartition

- Création de **sections analytiques** avec des clés de répartition
- Types de clés : **pourcentage**, **montant fixe**, **unité d'œuvre**
- Affectation des coûts depuis les écritures comptables, opérations de caisse, opérations bancaires, lignes de paie et lignes d'engagements

### 12.3 Répartition des Coûts

Le module de répartition permet de distribuer les coûts communs selon les clés définies, offrant une vision précise du coût de revient par axe analytique.

---

## 13. Clôture d'Exercice

Le module Clôture guide l'utilisateur à travers un **processus en 4 étapes** :

### Étape 1 : Écritures d'Inventaire

Enregistrement des écritures de régularisation :

- Amortissements et provisions
- Charges à répartir
- Variations de stock
- Régularisations diverses

### Étape 2 : Amortissements

- **Fichier des immobilisations** avec code, libellé, valeur d'acquisition, amortissements cumulés
- Calcul automatique des **dotations annuelles**
- Deux méthodes supportées : **linéaire** et **dégressif** (avec coefficients)
- Calcul de la **Valeur Nette Comptable (VNC)**
- Génération automatique des écritures d'amortissement

### Étape 3 : Balance et Résultat

- Affichage de la **balance générale** de clôture
- Calcul des **SIG** et du **résultat net** de l'exercice

### Étape 4 : Clôture Définitive

- Clôture **irréversible** de l'exercice
- Archivage des données
- Blocage de toute saisie sur l'exercice clos

---

## 14. Administration et Sécurité

### 14.1 Gestion des Utilisateurs

L'administrateur peut :

- Créer, modifier et supprimer des comptes utilisateurs
- Assigner des **rôles** avec des permissions granulaires
- Assigner un **caissier** à une caisse spécifique
- Activer ou désactiver des comptes
- Réinitialiser les mots de passe

### 14.2 Paramètres de l'Entreprise

Configuration centralisée des informations de l'entreprise :

- Raison sociale, adresse, téléphone, email
- Numéro de Registre du Commerce (RC)
- Numéro de contribuable
- Logo de l'entreprise
- **Thème visuel** (9 thèmes disponibles)
- Police de caractères
- Devise et exercice comptable par défaut

### 14.3 Gestion des Rôles et Permissions

Création de rôles personnalisés avec une **matrice de permissions** visuelle couvrant **16 modules** et plusieurs actions par module. Les permissions supportent :

- Accès total : `{"all": true}`
- Accès complet à un module : `{"module": {"all": true}}`
- Accès à une action spécifique : `{"module": {"action": true}}`

### 14.4 Agences et Services

- Création et gestion des **agences** (siège, antennes)
- Création et gestion des **services** au sein de chaque agence
- Assignation d'un **responsable hiérarchique** (validateur N+1) par service

### 14.5 Sécurité

| Mesure | Description |
|---|---|
| Chiffrement des mots de passe | Algorithme bcrypt avec coût 12 |
| Protection SQL | Requêtes préparées PDO (anti-injection SQL) |
| Protection XSS | Échappement HTML systématique |
| Authentification par session | Sessions sécurisées avec tokens "Se souvenir de moi" |
| Rotation des tokens | Renouvellement automatique des tokens de connexion persistante |
| Verrouillage des exercices | Impossibilité de modifier les données d'un exercice clos |
| Contrôle d'accès | Vérification des permissions à chaque action |

---

## 15. Audit et Traçabilité

### 15.1 Journal d'Audit

Chaque action effectuée dans le système est enregistrée dans le journal d'audit avec :

- L'**utilisateur** ayant effectué l'action
- Le **type d'action** (connexion, déconnexion, création, modification, suppression, validation, rejet, etc.)
- Le **module** concerné
- La **table** cible et l'**identifiant** de l'enregistrement
- Les **anciennes et nouvelles valeurs** (au format JSON)
- L'**adresse IP** et le **navigateur** (user agent) de l'utilisateur

### 15.2 Consultation et Filtrage

Le journal d'audit est consultable avec des filtres par :

- **Période** (date de début et date de fin)
- **Module** concerné
- **Utilisateur** ayant effectué l'action

Les actions courantes sont identifiées par des **icônes** pour une lecture rapide.

---

## 16. Assistant IA RADNEX

BrenFinance Suite intègre un **assistant financier intelligent** propulsé par l'intelligence artificielle.

### 16.1 Fonctionnalités

- **Chat interactif** pour poser des questions sur vos données financières
- **Support multi-fournisseurs** : Ollama (local), OpenAI, OpenRouter, API personnalisée
- **Recherche web** activable pour des réponses enrichies
- **Prompt système** configurable pour adapter le comportement de l'assistant
- **Nom de l'assistant** personnalisable

### 16.2 Cas d'Utilisation

- Analyse de la situation financière
- Explication des indicateurs et ratios
- Aide à la saisie comptable
- Conseils sur la gestion budgétaire
- Interprétation des rapports financiers

---

## 17. Rôles et Permissions

### 17.1 Rôles Prédéfinis

| Rôle | Description | Responsabilités |
|---|---|---|
| **Super Admin** | Administrateur système | Accès complet à toutes les fonctionnalités |
| **DAF** | Directeur Administratif et Financier | Validation des engagements et ordres de mission (niveau DAF), consultation budgets, reporting, trésorerie, comptabilité, audit |
| **Comptable** | Responsable comptable | Gestion caisse et opérations, trésorerie, comptabilité complète, validation comptable des engagements, référentiels |
| **Caissier** | Responsable de caisse | Gestion de sa caisse, enregistrement des opérations, exécution des engagements approuvés |
| **Demandeur** | Collaborateur | Création et suivi de ses propres engagements et ordres de mission |
| **Valideur N+1** | Responsable hiérarchique | Validation hiérarchique des engagements et ordres de mission de son service |

### 17.2 Matrice des Permissions

Chaque rôle dispose de permissions spécifiques par module. Par exemple :

- Le **caissier** ne peut opérer que sur la caisse qui lui est assignée
- Le **demandeur** ne voit que ses propres engagements
- Le **validateur N+1** ne peut valider que les engagements de son service
- Le **comptable** a accès à la comptabilité complète mais ne peut pas valider au niveau DAF

---

## 18. Spécifications Techniques

### 18.1 Environnement Requis

| Composant | Version Minimale | Version Recommandée |
|---|---|---|
| PHP | 7.4 | 8.1+ |
| MySQL | 5.7 | 8.0+ |
| MariaDB | 10.3 | 10.6+ |
| Extensions PHP | PDO, pdo_mysql, json, session | idem |

### 18.2 Architecture

- **Backend** : PHP procédural avec PDO pour les accès base de données
- **Base de données** : MySQL/MariaDB avec requêtes préparées
- **Frontend** : HTML, CSS (variables CSS personnalisées), JavaScript vanilla
- **Graphiques** : Chart.js pour les visualisations
- **Responsive** : Design adaptatif avec 5 breakpoints (xs, sm, md, lg, xl)

### 18.3 Installation

1. Démarrer Apache et MySQL via le panneau de contrôle XAMPP
2. Importer la base de données : `mysql -u root < sql/schema.sql`
3. Accéder à l'application : `http://localhost/brenfinance/`
4. Exécuter `setup.php` une seule fois pour créer le compte administrateur
5. Supprimer `setup.php` après l'installation

### 18.4 Monnaie et Langue

- **Monnaie** : FCFA (Franc CFA)
- **Langue de l'interface** : Français
- **Format des montants** : `1 234 567 FCFA` (espace comme séparateur de milliers)

---

## Glossaire

| Terme | Définition |
|---|---|
| **Engagement** | Demande de dépense soumise à validation |
| **Imputation** | Attribution d'une dépense à un compte budgétaire ou comptable |
| **SYSCOHADA** | Système comptable OHADA, norme comptable en vigueur dans les pays africains francophones |
| **SIG** | Soldes Intermédiaires de Gestion, indicateurs de performance financière |
| **IRPP** | Impôt sur le Revenu des Personnes Physiques (barème camerounais) |
| **VNC** | Valeur Nette Comptable (valeur d'un bien après amortissements) |
| **RIB** | Relevé d'Identité Bancaire |
| **CNPS** | Caisse Nationale de Prévoyance Sociale (Cameroun) |
| **DGI** | Direction Générale des Impôts (Cameroun) |
| **OHADA** | Organisation pour l'Harmonisation en Afrique du Droit des Affaires |

---

## Contact et Support

Pour toute question ou demande d'assistance concernant BrenFinance Suite, veuillez contacter votre administrateur système ou le support technique.

---

*Document rédigé en Mai 2026 — BrenFinance Suite v1.0*
