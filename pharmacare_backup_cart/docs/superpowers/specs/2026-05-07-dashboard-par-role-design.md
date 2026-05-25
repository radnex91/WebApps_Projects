# Dashboard par rôle — Design Spec

**Date :** 2026-05-07
**Projet :** PharmaCare
**Statut :** Validé

## Objectif

Remplacer le dashboard unique actuel (identique pour tous les rôles) par 3 dashboards distincts, chacun reflétant le métier et les permissions du rôle connecté : caissier, pharmacien, administrateur.

## Architecture

**Approche B : Dashboard routeur + templates par rôle.**

```
dashboard.php                    ← Point d'entrée (routeur)
includes/
  dashboard-caissier.php         ← Dashboard caissier
  dashboard-pharmacien.php       ← Dashboard pharmacien
  dashboard-admin.php            ← Dashboard administrateur
```

### Routage dans `dashboard.php`

1. `requireLogin()` + `requirePermission('dashboard.voir')`
2. Lit `$_SESSION['user_role']`
3. Inclut le template correspondant :
   - `'caissier'`   → `includes/dashboard-caissier.php`
   - `'pharmacien'` → `includes/dashboard-pharmacien.php`
   - `'admin'`      → `includes/dashboard-admin.php`
   - Rôle inconnu/custom → fallback `includes/dashboard-caissier.php` (le plus restrictif)
4. `layout_head()` / `layout_foot()` restent dans `dashboard.php`
5. Le code actuel de `dashboard.php` (requêtes + HTML) est supprimé et remplacé par le routeur

## Contenu par rôle

### Dashboard Caissier

Focus : ventes quotidiennes et transactions personnelles.

| Widget | Requête SQL | Filtre |
|---|---|---|
| Ventes aujourd'hui (compteur) | `COUNT(*) FROM ventes WHERE DATE(created_at)=CURDATE() AND caissier_id=?` | caissier_id = session |
| Mon CA du jour | `COALESCE(SUM(total),0) FROM ventes WHERE DATE(created_at)=CURDATE() AND caissier_id=?` | caissier_id = session |
| Raccourci Point de Vente | Aucune (lien vers `modules/vente.php`) | — |
| Dernières transactions | `SELECT * FROM ventes WHERE caissier_id=? ORDER BY created_at DESC LIMIT 8` | caissier_id = session |

Layout : 3 cartes en ligne (2 stats + 1 raccourci), puis tableau des dernières transactions.

### Dashboard Pharmacien

Focus : stock, commandes, tendances de vente. **Pas de chiffre CA.**

| Widget | Requête SQL |
|---|---|
| Alertes stock (compteur + ruptures) | `COUNT(*) FROM produits WHERE stock <= seuil_alerte AND actif=1` |
| Commandes en attente | `COUNT(*) FROM commandes WHERE statut IN ('en_attente','en_cours')` |
| Ventes 7j (mini barres) | `SELECT DATE(created_at), SUM(total), COUNT(*) FROM ventes WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) GROUP BY DATE(created_at)` |
| Stock critique (liste) | `SELECT p.nom, p.stock, p.seuil_alerte, c.nom AS cat FROM produits p LEFT JOIN categories c ON p.categorie_id=c.id WHERE p.stock <= p.seuil_alerte AND p.actif=1 ORDER BY p.stock ASC LIMIT 6` |
| Top 5 produits (30j) | `SELECT vl.produit_nom, SUM(vl.quantite) AS qte, SUM(vl.total_ligne) AS rev FROM vente_lignes vl JOIN ventes v ON vl.vente_id=v.id WHERE v.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) GROUP BY vl.produit_nom ORDER BY qte DESC LIMIT 5` |

Layout : 3 cartes stats en ligne (alertes, commandes, mini-graph), puis 2 colonnes (stock critique + top 5).

### Dashboard Admin

Focus : vue d'ensemble complète. Reprend quasi tout l'existant + widget activité.

| Widget | Requête SQL |
|---|---|
| CA du mois | `COALESCE(SUM(total),0) FROM ventes WHERE MONTH(created_at)=MONTH(NOW()) AND YEAR(created_at)=YEAR(NOW())` |
| Médicaments en stock | `COUNT(*) FROM produits WHERE actif=1` |
| Alertes stock (compteur + ruptures) | `COUNT(*) FROM produits WHERE stock <= seuil_alerte AND actif=1` + `COUNT(*) FROM produits WHERE stock=0 AND actif=1` |
| Ventes aujourd'hui | `COUNT(*) FROM ventes WHERE DATE(created_at)=CURDATE()` |
| Ventes 7j (graphique) | `SELECT DATE(created_at), SUM(total), COUNT(*) FROM ventes WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) GROUP BY DATE(created_at)` |
| Stock critique (liste) | Idem pharmacien |
| Dernières ventes (tableau) | `SELECT v.*, u.prenom, u.nom FROM ventes v LEFT JOIN utilisateurs u ON v.caissier_id=u.id ORDER BY v.created_at DESC LIMIT 8` |
| Top 5 produits (30j) | Idem pharmacien |
| Activité utilisateurs | `SELECT u.prenom, u.nom, u.derniere_connexion, r.libelle FROM utilisateurs u JOIN roles r ON u.role_id=r.id WHERE u.actif=1 ORDER BY u.derniere_connexion DESC LIMIT 5` |

Layout : 4 cartes stats, puis 2 colonnes (graphique ventes 7j + activité récente), puis 2 colonnes (dernières ventes + top produits + commandes en cours).

## Sécurité

- `requirePermission('dashboard.voir')` requis pour tous
- Toutes les requêtes utilisent des requêtes préparées (`?` placeholders)
- Le caissier ne voit que ses propres données (`caissier_id = ?`)
- Le pharmacien n'a accès à aucun chiffre CA
- Fallback caissier pour les rôles inconnus/custom

## Tests manuels

| Scénario | Résultat attendu |
|---|---|
| Connexion admin | Dashboard admin avec 4 cartes stats + CA visible |
| Connexion pharmacien | Dashboard pharmacien : alertes, commandes, tendances. Pas de CA |
| Connexion caissier | Dashboard caissier : ses ventes, son CA, ses transactions |
| Rôle custom (sans template) | Fallback dashboard caissier |
| Caissier : vérifier transactions | Ne voit que les siennes (pas celles d'autres caissiers) |
| Pharmacien : vérifier pas de CA | Aucun widget CA visible |
