# Dashboard par rôle — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Remplacer le dashboard unique actuel par 3 dashboards distincts par rôle (caissier, pharmacien, admin) via un routeur + templates.

**Architecture:** `dashboard.php` devient un simple routeur qui inclut le bon template selon `$_SESSION['user_role']`. Trois templates créés dans `includes/` — un par rôle. Chaque template fait ses propres requêtes SQL.

**Tech Stack:** Vanilla PHP 7.4+, MySQL/MariaDB, CSS existant (aucun nouveau style nécessaire), pas de build step.

---

## File Structure

| Action | File | Role |
|---|---|---|
| Modify | `dashboard.php` | Remplacer tout le contenu par un routeur |
| Create | `includes/dashboard-caissier.php` | Dashboard caissier : ventes personnelles |
| Create | `includes/dashboard-pharmacien.php` | Dashboard pharmacien : stock, commandes, tendances |
| Create | `includes/dashboard-admin.php` | Dashboard admin : vue d'ensemble complète |

Les styles (`.stats-grid`, `.stat-card`, `.card`, `.chart-bars`, `.grid-2`, `.grid-3-1`, etc.) existent déjà dans `assets/css/style.css`. Aucun CSS nouveau n'est nécessaire.

---

### Task 1: Rewrite `dashboard.php` as a router

**Files:**
- Modify: `dashboard.php` (tout le contenu remplacé)

- [ ] **Step 1: Replace `dashboard.php` with the router**

Replace the entire content of `dashboard.php`:

```php
<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/config/settings.php';
requireLogin();
requirePermission('dashboard.voir');

$role = $_SESSION['user_role'] ?? '';

// Routeur : inclut le template correspondant au rôle
$templates = [
    'caissier'   => __DIR__ . '/includes/dashboard-caissier.php',
    'pharmacien' => __DIR__ . '/includes/dashboard-pharmacien.php',
    'admin'      => __DIR__ . '/includes/dashboard-admin.php',
];

$template = $templates[$role] ?? $templates['caissier']; // fallback caissier pour rôles custom
require $template;
```

- [ ] **Step 2: Verify — open `http://localhost/pharmacare/dashboard.php` as admin**

Pour l'instant le dashboard admin va casser car le fichier `includes/dashboard-admin.php` n'existe pas encore. C'est normal — on le crée dans la Task 4.

Expected: PHP fatal error or blank page (file not found). This proves the router is working.

---

### Task 2: Create `includes/dashboard-caissier.php`

**Files:**
- Create: `includes/dashboard-caissier.php`

- [ ] **Step 1: Create the caissier dashboard template**

```php
<?php
// includes/dashboard-caissier.php
// Dashboard caissier : focus sur ses ventes personnelles du jour

$db = getDB();
$userId = (int)$_SESSION['user_id'];

// Ventes aujourd'hui (compteur)
$ventes_j = $db->prepare("SELECT COUNT(*) FROM ventes WHERE DATE(created_at)=CURDATE() AND caissier_id=?");
$ventes_j->execute([$userId]);
$ventes_j = $ventes_j->fetchColumn();

// Mon CA du jour
$ca_jour = $db->prepare("SELECT COALESCE(SUM(total),0) FROM ventes WHERE DATE(created_at)=CURDATE() AND caissier_id=?");
$ca_jour->execute([$userId]);
$ca_jour = $ca_jour->fetchColumn();

// Dernières transactions
$dernieres = $db->prepare("SELECT * FROM ventes WHERE caissier_id=? ORDER BY created_at DESC LIMIT 8");
$dernieres->execute([$userId]);
$dernieres = $dernieres->fetchAll();

layout_head('Tableau de bord', 'dashboard');
showFlash();
?>

<div class="stats-grid" style="grid-template-columns:repeat(3,1fr);">
  <div class="stat-card s-teal">
    <div class="stat-icon" style="color:var(--teal2);opacity:.25;"><?= icon('receipt',28) ?></div>
    <div class="stat-label">Ventes aujourd'hui</div>
    <div class="stat-value c-teal"><?= fmtInt((int)$ventes_j) ?></div>
    <div class="stat-sub">transactions effectuées</div>
  </div>
  <div class="stat-card s-gold">
    <div class="stat-icon" style="color:var(--gold);opacity:.25;"><?= icon('money',28) ?></div>
    <div class="stat-label">Mon CA du jour</div>
    <div class="stat-value c-gold"><?= fmtMoney($ca_jour) ?></div>
    <div class="stat-sub">CA personnel</div>
  </div>
  <a href="<?= APP_URL ?>/modules/vente.php" class="stat-card s-blue" style="display:block;text-decoration:none;cursor:pointer;">
    <div class="stat-icon" style="color:var(--blue);opacity:.25;"><?= icon('cart',28) ?></div>
    <div class="stat-label">Point de Vente</div>
    <div class="stat-value c-blue" style="font-size:20px;">Nouvelle vente</div>
    <div class="stat-sub">Ouvrir la caisse</div>
  </a>
</div>

<div class="card">
  <div class="card-header">
    <div class="card-title">Mes dernières transactions</div>
  </div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Référence</th><th>Client</th><th>Total</th><th>Heure</th></tr></thead>
      <tbody>
        <?php if ($dernieres): foreach ($dernieres as $v): ?>
        <tr>
          <td class="td-mono"><?= e($v['reference']) ?></td>
          <td><?= e($v['client_nom'] ?: '—') ?></td>
          <td class="fw-mono c-teal"><?= fmtMoney($v['total']) ?></td>
          <td class="text-sm"><?= date('H:i', strtotime($v['created_at'])) ?></td>
        </tr>
        <?php endforeach; else: ?>
        <tr><td colspan="4" class="empty">Aucune transaction aujourd'hui</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php layout_foot(); ?>
```

- [ ] **Step 2: Verify — open `http://localhost/pharmacare/dashboard.php` as caissier**

Login: `caissier` / `password`

Expected:
- 3 stat cards: "Ventes aujourd'hui", "Mon CA du jour", "Point de Vente" (cliquable)
- "Mes dernières transactions" table en dessous
- Ne voit QUE ses propres transactions

- [ ] **Step 3: Verify — the POS card links to `modules/vente.php`**

Click the "Point de Vente" card. It should navigate to the POS page.

---

### Task 3: Create `includes/dashboard-pharmacien.php`

**Files:**
- Create: `includes/dashboard-pharmacien.php`

- [ ] **Step 1: Create the pharmacien dashboard template**

```php
<?php
// includes/dashboard-pharmacien.php
// Dashboard pharmacien : stock, commandes, tendances de vente. Pas de CA.

$db = getDB();

// Alertes stock
$alertes  = $db->query("SELECT COUNT(*) FROM produits WHERE stock <= seuil_alerte AND actif=1")->fetchColumn();
$ruptures = $db->query("SELECT COUNT(*) FROM produits WHERE stock=0 AND actif=1")->fetchColumn();

// Commandes en attente
$cmd_attente = $db->query("SELECT COUNT(*) FROM commandes WHERE statut IN ('en_attente','en_cours')")->fetchColumn();

// Ventes 7j (tendance)
$ventes7 = $db->query("
  SELECT DATE(created_at) AS jour, SUM(total) AS total, COUNT(*) AS nb
  FROM ventes WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
  GROUP BY DATE(created_at) ORDER BY jour
")->fetchAll();

// Stock critique
$critique = $db->query("
  SELECT p.nom, p.stock, p.seuil_alerte, c.nom AS cat
  FROM produits p LEFT JOIN categories c ON p.categorie_id=c.id
  WHERE p.stock <= p.seuil_alerte AND p.actif=1
  ORDER BY p.stock ASC LIMIT 6
")->fetchAll();

// Top 5 produits (30j)
$top = $db->query("
  SELECT vl.produit_nom, SUM(vl.quantite) AS qte, SUM(vl.total_ligne) AS rev
  FROM vente_lignes vl JOIN ventes v ON vl.vente_id=v.id
  WHERE v.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
  GROUP BY vl.produit_nom ORDER BY qte DESC LIMIT 5
")->fetchAll();
$maxQ = $top ? max(array_column($top, 'qte')) : 1;

// Prépare les jours pour le mini graphique
$jours = [];
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $jours[$d] = ['total' => 0, 'nb' => 0, 'label' => date('D', strtotime($d))];
}
$fr = ['Mon' => 'Lun', 'Tue' => 'Mar', 'Wed' => 'Mer', 'Thu' => 'Jeu', 'Fri' => 'Ven', 'Sat' => 'Sam', 'Sun' => 'Dim'];
foreach ($ventes7 as $v) {
    $jours[$v['jour']] = ['total' => $v['total'], 'nb' => $v['nb'], 'label' => $jours[$v['jour']]['label'] ?? ''];
}
$maxV = max(array_column($jours, 'total') ?: [1]);

layout_head('Tableau de bord', 'dashboard');
showFlash();
?>

<div class="stats-grid" style="grid-template-columns:repeat(3,1fr);">
  <div class="stat-card s-red">
    <div class="stat-icon" style="color:var(--red);opacity:.25;"><?= icon('alert',28) ?></div>
    <div class="stat-label">Alertes stock</div>
    <div class="stat-value c-red"><?= $alertes ?></div>
    <div class="stat-sub"><?= $ruptures ?> en rupture totale</div>
  </div>
  <div class="stat-card s-blue">
    <div class="stat-icon" style="color:var(--blue);opacity:.25;"><?= icon('clipboard',28) ?></div>
    <div class="stat-label">Commandes en attente</div>
    <div class="stat-value c-blue"><?= $cmd_attente ?></div>
    <div class="stat-sub">à traiter</div>
  </div>
  <div class="stat-card s-teal">
    <div class="stat-label">Ventes — 7 derniers jours</div>
    <div class="chart-wrap" style="padding:8px 0 0;">
      <div class="chart-bars" style="height:52px;">
        <?php
        $colors = ['var(--teal)', 'var(--teal)', 'var(--teal)', 'var(--teal)', 'var(--teal)', 'var(--teal)', 'var(--teal)'];
        $ci = 0;
        foreach ($jours as $d => $j):
          $pct = $maxV > 0 ? round($j['total'] / $maxV * 44) : 4;
          $lbl = $fr[$j['label']] ?? $j['label'];
        ?>
        <div class="bar-wrap" title="<?= $lbl ?> : <?= fmtMoney($j['total']) ?> (<?= $j['nb'] ?> ventes)">
          <div class="bar" style="height:<?= max($pct, 4) ?>px;background:<?= $colors[$ci++ % 7] ?>;opacity:.8;"></div>
          <div class="bar-label"><?= $lbl ?></div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="stat-sub">tendance hebdomadaire</div>
  </div>
</div>

<div class="grid-2">
  <div class="card">
    <div class="card-header">
      <div class="card-title">Stock critique</div>
      <a href="<?= APP_URL ?>/modules/stock.php" class="btn btn-ghost btn-xs">Voir tout</a>
    </div>
    <?php if ($critique): foreach ($critique as $p): ?>
    <div style="padding:9px 14px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;">
      <div>
        <div style="font-size:13px;font-weight:500;"><?= e($p['nom']) ?></div>
        <div class="text-xs"><?= e($p['cat'] ?? '—') ?> · seuil : <?= $p['seuil_alerte'] ?></div>
      </div>
      <span class="badge <?= $p['stock'] == 0 ? 'badge-red' : 'badge-gold' ?>"><?= $p['stock'] ?> u.</span>
    </div>
    <?php endforeach; else: ?>
    <div class="empty">
      <div style="color:var(--teal2);margin-bottom:8px;"><?= icon('check',32) ?></div>
      <div>Aucune alerte de stock</div>
    </div>
    <?php endif; ?>
  </div>

  <div class="card">
    <div class="card-header"><div class="card-title">Top 5 produits (30j)</div></div>
    <div class="card-pad">
      <?php if ($top): foreach ($top as $p): ?>
      <div style="margin-bottom:13px;">
        <div class="flex-between" style="font-size:13px;margin-bottom:4px;">
          <span><?= e($p['produit_nom']) ?></span>
          <span class="fw-mono c-teal"><?= $p['qte'] ?> ventes</span>
        </div>
        <div class="progress-bar">
          <div class="progress-fill" style="width:<?= round($p['qte'] / $maxQ * 100) ?>%;background:linear-gradient(90deg,var(--teal),var(--blue));"></div>
        </div>
      </div>
      <?php endforeach; else: ?>
      <div class="empty"><div style="color:var(--text3);margin-bottom:8px;"><?= icon('chart',32) ?></div><div>Aucune donnée</div></div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php layout_foot(); ?>
```

- [ ] **Step 2: Verify — open `http://localhost/pharmacare/dashboard.php` as pharmacien**

Login: `pharmacien` / `password`

Expected:
- 3 stat cards: "Alertes stock" (red), "Commandes en attente" (blue), "Ventes 7 derniers jours" (mini chart)
- Below: 2 columns — "Stock critique" (left) + "Top 5 produits 30j" (right)
- **NO CA widget visible anywhere**
- **NO "Dernières ventes" table** (admin only)

---

### Task 4: Create `includes/dashboard-admin.php`

**Files:**
- Create: `includes/dashboard-admin.php`

- [ ] **Step 1: Create the admin dashboard template**

This is the full dashboard — essentially the current `dashboard.php` content + "Activité utilisateurs" widget.

```php
<?php
// includes/dashboard-admin.php
// Dashboard admin : vue d'ensemble complète

$db = getDB();

// CA du mois
$ca_mois  = $db->query("SELECT COALESCE(SUM(total),0) FROM ventes WHERE MONTH(created_at)=MONTH(NOW()) AND YEAR(created_at)=YEAR(NOW())")->fetchColumn();

// Médicaments en stock
$nb_prods = $db->query("SELECT COUNT(*) FROM produits WHERE actif=1")->fetchColumn();

// Alertes stock
$alertes  = $db->query("SELECT COUNT(*) FROM produits WHERE stock <= seuil_alerte AND actif=1")->fetchColumn();
$ruptures = $db->query("SELECT COUNT(*) FROM produits WHERE stock=0 AND actif=1")->fetchColumn();

// Ventes aujourd'hui
$ventes_j = $db->query("SELECT COUNT(*) FROM ventes WHERE DATE(created_at)=CURDATE()")->fetchColumn();
$ca_jour  = $db->query("SELECT COALESCE(SUM(total),0) FROM ventes WHERE DATE(created_at)=CURDATE()")->fetchColumn();

// Ventes 7 derniers jours
$ventes7 = $db->query("
  SELECT DATE(created_at) AS jour, SUM(total) AS total, COUNT(*) AS nb
  FROM ventes WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
  GROUP BY DATE(created_at) ORDER BY jour
")->fetchAll();

// Stock critique
$critique = $db->query("
  SELECT p.nom, p.stock, p.seuil_alerte, c.nom AS cat
  FROM produits p LEFT JOIN categories c ON p.categorie_id=c.id
  WHERE p.stock <= p.seuil_alerte AND p.actif=1
  ORDER BY p.stock ASC LIMIT 6
")->fetchAll();

// Dernières ventes
$dernieres = $db->query("
  SELECT v.*, u.prenom, u.nom AS u_nom
  FROM ventes v LEFT JOIN utilisateurs u ON v.caissier_id=u.id
  ORDER BY v.created_at DESC LIMIT 8
")->fetchAll();

// Top 5 produits (30j)
$top = $db->query("
  SELECT vl.produit_nom, SUM(vl.quantite) AS qte, SUM(vl.total_ligne) AS rev
  FROM vente_lignes vl JOIN ventes v ON vl.vente_id=v.id
  WHERE v.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
  GROUP BY vl.produit_nom ORDER BY qte DESC LIMIT 5
")->fetchAll();
$maxQ = $top ? max(array_column($top, 'qte')) : 1;

// Activité utilisateurs
$activite = $db->query("
  SELECT u.prenom, u.nom, u.derniere_connexion, r.libelle AS role_libelle
  FROM utilisateurs u JOIN roles r ON u.role_id = r.id
  WHERE u.actif = 1 ORDER BY u.derniere_connexion DESC LIMIT 5
")->fetchAll();

// Commandes en cours
$cmd_en_cours = $db->query("SELECT COUNT(*) FROM commandes WHERE statut IN ('en_attente','en_cours')")->fetchColumn();

// Prépare les jours pour le graphique
$jours = [];
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $jours[$d] = ['total' => 0, 'nb' => 0, 'label' => date('D', strtotime($d))];
}
$fr = ['Mon' => 'Lun', 'Tue' => 'Mar', 'Wed' => 'Mer', 'Thu' => 'Jeu', 'Fri' => 'Ven', 'Sat' => 'Sam', 'Sun' => 'Dim'];
foreach ($ventes7 as $v) {
    $jours[$v['jour']] = ['total' => $v['total'], 'nb' => $v['nb'], 'label' => $jours[$v['jour']]['label'] ?? ''];
}
$maxV = max(array_column($jours, 'total') ?: [1]);

layout_head('Tableau de bord', 'dashboard');
showFlash();
?>

<div class="stats-grid">
  <div class="stat-card s-teal">
    <div class="stat-icon" style="color:var(--teal2);opacity:.25;"><?= icon('money',28) ?></div>
    <div class="stat-label">CA du mois</div>
    <div class="stat-value c-teal"><?= fmtMoney($ca_mois) ?></div>
    <div class="stat-sub">Aujourd'hui : <?= fmtMoney($ca_jour) ?></div>
  </div>
  <div class="stat-card s-gold">
    <div class="stat-icon" style="color:var(--gold);opacity:.25;"><?= icon('box',28) ?></div>
    <div class="stat-label">Médicaments en stock</div>
    <div class="stat-value c-gold"><?= fmtInt((int)$nb_prods) ?></div>
    <div class="stat-sub">références actives</div>
  </div>
  <div class="stat-card s-red">
    <div class="stat-icon" style="color:var(--red);opacity:.25;"><?= icon('alert',28) ?></div>
    <div class="stat-label">Alertes stock</div>
    <div class="stat-value c-red"><?= $alertes ?></div>
    <div class="stat-sub"><?= $ruptures ?> en rupture totale</div>
  </div>
  <div class="stat-card s-blue">
    <div class="stat-icon" style="color:var(--blue);opacity:.25;"><?= icon('receipt',28) ?></div>
    <div class="stat-label">Ventes aujourd'hui</div>
    <div class="stat-value c-blue"><?= $ventes_j ?></div>
    <div class="stat-sub">transactions effectuées</div>
  </div>
</div>

<div class="grid-3-1">
  <div class="card">
    <div class="card-header">
      <div class="card-title">Ventes — 7 derniers jours</div>
    </div>
    <div class="chart-wrap">
      <div class="chart-bars">
        <?php
        $colors = ['var(--teal)', 'var(--blue)', 'var(--gold)', 'var(--teal)', 'var(--blue)', 'var(--gold)', 'var(--teal)'];
        $ci = 0;
        foreach ($jours as $d => $j):
          $pct = $maxV > 0 ? round($j['total'] / $maxV * 72) : 4;
          $lbl = $fr[$j['label']] ?? $j['label'];
        ?>
        <div class="bar-wrap" title="<?= $lbl ?> : <?= fmtMoney($j['total']) ?> (<?= $j['nb'] ?> ventes)">
          <div class="bar" style="height:<?= max($pct, 4) ?>px;background:<?= $colors[$ci++ % 7] ?>;opacity:.8;"></div>
          <div class="bar-label"><?= $lbl ?></div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-header">
      <div class="card-title">Activité récente</div>
    </div>
    <?php if ($activite): foreach ($activite as $u): ?>
    <div style="padding:9px 14px;border-bottom:1px solid var(--border);">
      <div style="font-size:13px;font-weight:500;"><?= e($u['prenom'] . ' ' . $u['nom']) ?></div>
      <div style="display:flex;justify-content:space-between;align-items:center;">
        <span class="badge badge-purple" style="font-size:10px;"><?= e($u['role_libelle']) ?></span>
        <span class="text-xs"><?= $u['derniere_connexion'] ? date('d/m/Y H:i', strtotime($u['derniere_connexion'])) : 'Jamais' ?></span>
      </div>
    </div>
    <?php endforeach; else: ?>
    <div class="empty">Aucune activité</div>
    <?php endif; ?>
  </div>
</div>

<div class="grid-2">
  <div class="card">
    <div class="card-header">
      <div class="card-title">Stock critique</div>
      <a href="<?= APP_URL ?>/modules/stock.php" class="btn btn-ghost btn-xs">Voir tout</a>
    </div>
    <?php if ($critique): foreach ($critique as $p): ?>
    <div style="padding:9px 14px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;">
      <div>
        <div style="font-size:13px;font-weight:500;"><?= e($p['nom']) ?></div>
        <div class="text-xs"><?= e($p['cat'] ?? '—') ?> · seuil : <?= $p['seuil_alerte'] ?></div>
      </div>
      <span class="badge <?= $p['stock'] == 0 ? 'badge-red' : 'badge-gold' ?>"><?= $p['stock'] ?> u.</span>
    </div>
    <?php endforeach; else: ?>
    <div class="empty">
      <div style="color:var(--teal2);margin-bottom:8px;"><?= icon('check',32) ?></div>
      <div>Aucune alerte de stock</div>
    </div>
    <?php endif; ?>
  </div>

  <div class="card">
    <div class="card-header">
      <div class="card-title">Dernières ventes</div>
      <a href="<?= APP_URL ?>/modules/ventes_hist.php" class="btn btn-ghost btn-xs">Historique</a>
    </div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Référence</th><th>Client</th><th>Total</th><th>Caissier</th><th>Heure</th></tr></thead>
        <tbody>
          <?php foreach ($dernieres as $v): ?>
          <tr>
            <td class="td-mono"><?= e($v['reference']) ?></td>
            <td><?= e($v['client_nom'] ?: '—') ?></td>
            <td class="fw-mono c-teal"><?= fmtMoney($v['total']) ?></td>
            <td class="text-sm"><?= e($v['prenom'] . ' ' . $v['u_nom']) ?></td>
            <td class="text-sm"><?= date('H:i', strtotime($v['created_at'])) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div class="grid-2">
  <div class="card">
    <div class="card-header"><div class="card-title">Top 5 produits (30j)</div></div>
    <div class="card-pad">
      <?php if ($top): foreach ($top as $p): ?>
      <div style="margin-bottom:13px;">
        <div class="flex-between" style="font-size:13px;margin-bottom:4px;">
          <span><?= e($p['produit_nom']) ?></span>
          <span class="fw-mono c-teal"><?= $p['qte'] ?> ventes</span>
        </div>
        <div class="progress-bar">
          <div class="progress-fill" style="width:<?= round($p['qte'] / $maxQ * 100) ?>%;background:linear-gradient(90deg,var(--teal),var(--blue));"></div>
        </div>
      </div>
      <?php endforeach; else: ?>
      <div class="empty"><div style="color:var(--text3);margin-bottom:8px;"><?= icon('chart',32) ?></div><div>Aucune donnée</div></div>
      <?php endif; ?>
    </div>
  </div>

  <a href="<?= APP_URL ?>/modules/commandes.php" class="card" style="display:block;text-decoration:none;color:inherit;">
    <div class="card-header">
      <div class="card-title">Commandes en cours</div>
      <span class="badge badge-blue"><?= $cmd_en_cours ?></span>
    </div>
    <div class="card-pad" style="text-align:center;padding:44px 20px;">
      <div style="color:var(--blue);margin-bottom:12px;"><?= icon('truck',40) ?></div>
      <div style="font-size:30px;font-weight:700;font-family:var(--font-title);color:var(--blue);"><?= $cmd_en_cours ?></div>
      <div class="text-sm" style="margin-top:4px;">commande(s) en attente ou en cours</div>
    </div>
  </a>
</div>

<?php layout_foot(); ?>
```

- [ ] **Step 2: Verify — open `http://localhost/pharmacare/dashboard.php` as admin**

Login: `admin` / `password`

Expected:
- 4 stat cards: "CA du mois", "Médicaments en stock", "Alertes stock", "Ventes aujourd'hui"
- Graphique "Ventes 7j" + "Activité récente" (side by side)
- "Stock critique" liste + "Dernières ventes" table (side by side)
- "Top 5 produits" + "Commandes en cours" (side by side)
- All CA figures visible

---

### Task 5: Final verification — all roles

- [ ] **Step 1: Test as caissier**

Open `http://localhost/pharmacare/dashboard.php` as caissier.

- [ ] Only sees their own transactions
- [ ] "Point de Vente" card navigates to `modules/vente.php`
- [ ] No global CA month visible

- [ ] **Step 2: Test as pharmacien**

Open `http://localhost/pharmacare/dashboard.php` as pharmacien.

- [ ] No CA widget (month or day) anywhere
- [ ] "Alertes stock" card visible
- [ ] "Commandes en attente" card visible
- [ ] "Ventes 7 derniers jours" mini chart visible
- [ ] Stock critique + Top 5 produits visible

- [ ] **Step 3: Test as admin**

Open `http://localhost/pharmacare/dashboard.php` as admin.

- [ ] All 4 stat cards visible including CA
- [ ] All sections visible (chart, activity, stock, sales, top products, orders)
- [ ] "Activité récente" shows user connections with role badges

- [ ] **Step 4: Test custom role fallback (optional — requires creating a custom role)**

If you have a custom role: log in with it. Should see the caissier dashboard as fallback.
