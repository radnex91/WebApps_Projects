# RentFlow - Module Rapports & Statistiques

## 🎯 Nouveaux fichiers créés

### 1. Contrôleur : `app/controllers/ReportController.php`
- **index()** : Dashboard statistique avec graphiques mensuels, annuels, répartition par statut
- **export()** : Export CSV et Excel (HTML) avec filtrage par date
- **agency($id)** : Rapport détaillé par agence avec graphique mensuel

### 2. Vues
- **`app/views/reports/index.php`** : Page principale avec :
  - Graphique en barres (paiements mensuels)
  - Graphique circulaire (répartition par statut : Anticipé, À temps, En retard, En attente)
  - Graphique linéaire (comparaison annuelle)
  - Top 10 Bailleurs (CA)
  - Top 10 Agences (CA)
  - Tableaux statistiques par agence et bailleur

- **`app/views/reports/agency.php`** : Rapport spécifique par agence avec graphique mensuel

### 3. Modèle mis à jour : `app/models/Payment.php`
Nouvelles méthodes :
- `getMonthlyStats($year)` : Stats mensuelles pour une année
- `getYearlyStats()` : Comparaison annuelle
- `getPaymentsByStatus()` : Répartition par statut
- `getTopLandlords($limit)` : Top bailleurs par CA
- `getTopAgencies($limit)` : Top agences par CA
- `getPaymentsByMonthForAgency($agencyId, $year)` : Stats mensuelles par agence
- `exportReport($startDate, $endDate)` : Export avec filtrage

### 4. Routes ajoutées dans `routes/web.php`
```php
$router->get('reports', 'ReportController@index');
$router->get('reports/export', 'ReportController@export');
$router->get('reports/agency/{id}', 'ReportController@agency');
```

### 5. Navigation mise à jour : `app/views/layouts/nav.php`
- Nouveau lien "Rapports" dans le menu principal
- Section "Administration" réservée aux admins
- Lien de déconnexion dans le menu utilisateur

## 📊 Graphiques inclus (Chart.js 4.4.0)

1. **Paiements mensuels** (Bar Chart) - Évolution sur l'année
2. **Répartition par statut** (Doughnut Chart) - Pourcentages
3. **Comparaison annuelle** (Line Chart) - Montants et nombre de paiements
4. **Top bailleurs/agences** (Tableaux) - Avec médailles 🥇🥈🥉
5. **Rapport agence** (Bar Chart) - Mensuel par agence

## 📥 Formats d'export

1. **CSV** : Standard avec encodage UTF-8
2. **Excel** : HTML table compatible Excel (.xls)

## 🔗 Accès

- **Rapports généraux** : `http://localhost/RentFlow/public/reports`
- **Export CSV** : `http://localhost/RentFlow/public/reports/export?format=csv&start_date=2026-01-01&end_date=2026-12-31`
- **Export Excel** : `http://localhost/RentFlow/public/reports/export?format=excel`
- **Rapport agence** : `http://localhost/RentFlow/public/reports/agency/1`

## ✅ Bugs corrigés durant cette session

1. **Route ordering** - `routes/web.php` : Routes spécifiques AVANT paramétrées
2. **syncPermissions()** - `app/models/Role.php` : Ordre des colonnes corrigé
3. **calculateStatus()** - `app/models/Payment.php` : Virgule manquante
4. **CSS mal formaté** - `app/views/payments/create.php` et `edit.php`
5. **Tableaux PHP** - `app/controllers/BatchController.php` : Virgules manquantes

## 🎯 Modules manquants restants

1. **Module Quittances** - Génération PDF automatique
2. **Module Notifications SMS** - Twilio/Africa's Talking
3. **Module Contrats** - Gestion des contrats de location
4. **Module Maintenance** - Signalement et suivi des réparations
5. **Module Comptabilité** - Débits/Crédits, bilans
6. **Module Réservation** - Calendrier de disponibilité
