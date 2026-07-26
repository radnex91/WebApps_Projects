<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../config/settings.php';
requirePermission('menus.voir');
$db = getDB();

// ── POST : activation/désactivation ─────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    requirePermission('menus.gerer');
    $sub = $_POST['action'] ?? '';

    if ($sub === 'toggle') {
        $code = trim($_POST['code'] ?? '');
        if ($code !== '') {
            $db->prepare("UPDATE menus SET actif = 1 - actif WHERE code = ?")->execute([$code]);
            flash('Statut du menu mis à jour.');
        }
        header('Location: ' . url('menus')); exit;
    }

    if ($sub === 'all') {
        $val = ((int)($_POST['valeur'] ?? 1)) === 1 ? 1 : 0;
        $db->prepare("UPDATE menus SET actif = ?")->execute([$val]);
        flash($val ? 'Tous les menus ont été activés.' : 'Tous les menus ont été désactivés.');
        header('Location: ' . url('menus')); exit;
    }

    flash('Action inconnue.', 'error');
    header('Location: ' . url('menus')); exit;
}

// ── Données ─────────────────────────────────────────────────
$menus = $db->query("SELECT code, libelle, actif, position FROM menus ORDER BY position")->fetchAll();

// Regroupement par section (pour l'affichage)
$sections = [
    'Principal'     => ['dashboard', 'vente', 'remise_codes', 'caisse'],
    'Gestion'       => ['stock', 'produits', 'fournisseurs', 'clients', 'commandes', 'retours', 'magasin', 'marketing'],
    'Rapports'      => ['ventes_hist', 'rapports', 'rapports_caissier', 'comptabilite'],
    'Administration'=> ['utilisateurs', 'remise_approbateurs', 'roles', 'categories', 'pharmacies', 'parametres'],
];
$bySection = [];
foreach ($menus as $m) {
    foreach ($sections as $nom => $codes) {
        if (in_array($m['code'], $codes, true)) {
            $bySection[$nom][] = $m;
            break;
        }
    }
}

$peutGerer = hasPermission('menus.gerer');

layout_head('Menus', 'menus');
showFlash();
?>

<div class="card" style="margin-bottom:16px;">
  <div class="card-header">
    <div class="card-title"><?= icon('list',16) ?> Menus du sidebar</div>
    <span class="text-sm">Activez ou désactivez les entrées affichées dans le menu latéral.</span>
  </div>
  <div class="card-pad" style="padding:14px 18px;display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
    <span class="text-sm" style="color:var(--text3);">
      Un menu désactivé est masqué pour <strong>tous</strong> les utilisateurs (admin compris).
    </span>
    <?php if ($peutGerer): ?>
    <div style="margin-left:auto;display:flex;gap:8px;">
      <form method="POST" style="display:inline;" onsubmit="return confirm('Activer tous les menus ?')">
        <input type="hidden" name="csrf" value="<?= csrf() ?>">
        <input type="hidden" name="action" value="all">
        <input type="hidden" name="valeur" value="1">
        <button type="submit" class="btn btn-ghost btn-sm"><?= icon('check',13) ?> Tout activer</button>
      </form>
      <form method="POST" style="display:inline;" onsubmit="return confirm('Désactiver tous les menus ? Le module Menus reste accessible.')">
        <input type="hidden" name="csrf" value="<?= csrf() ?>">
        <input type="hidden" name="action" value="all">
        <input type="hidden" name="valeur" value="0">
        <button type="submit" class="btn btn-ghost btn-sm"><?= icon('x',13) ?> Tout désactiver</button>
      </form>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php foreach ($sections as $nomSection => $_codes): if (empty($bySection[$nomSection])) continue; ?>
<div class="card" style="margin-bottom:16px;">
  <div class="card-header">
    <div class="card-title"><?= e($nomSection) ?></div>
    <span class="text-sm"><?= count($bySection[$nomSection]) ?> entrée(s)</span>
  </div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr><th>Menu</th><th>Code</th><th style="text-align:right;">Statut</th><?php if ($peutGerer): ?><th style="text-align:right;">Action</th><?php endif; ?></tr>
      </thead>
      <tbody>
        <?php foreach ($bySection[$nomSection] as $m):
          $actif = (int)$m['actif'] === 1;
        ?>
        <tr>
          <td class="td-name"><?= e($m['libelle']) ?></td>
          <td class="td-mono text-sm" style="color:var(--text3);"><?= e($m['code']) ?></td>
          <td style="text-align:right;">
            <span class="badge <?= $actif ? 'badge-green' : 'badge-gray' ?>"><?= $actif ? 'Actif' : 'Désactivé' ?></span>
          </td>
          <?php if ($peutGerer): ?>
          <td style="text-align:right;">
            <form method="POST" style="display:inline;">
              <input type="hidden" name="csrf" value="<?= csrf() ?>">
              <input type="hidden" name="action" value="toggle">
              <input type="hidden" name="code" value="<?= e($m['code']) ?>">
              <button type="submit" class="btn btn-sm <?= $actif ? 'btn-ghost' : 'btn-primary' ?>">
                <?= $actif ? (icon('x',13) . ' Désactiver') : (icon('check',13) . ' Activer') ?>
              </button>
            </form>
          </td>
          <?php endif; ?>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endforeach; ?>

<?php layout_foot(); ?>