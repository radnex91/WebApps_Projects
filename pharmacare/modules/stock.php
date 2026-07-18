<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../config/settings.php';
requirePermission('stock.voir');
$db = getDB();

$filtre = $_GET['filtre'] ?? 'tous';
$cat    = $_GET['cat']    ?? '';

$where = "p.actif = 1";
if ($filtre === 'alerte')  $where .= " AND p.stock <= p.seuil_alerte AND p.stock > 0";
if ($filtre === 'rupture') $where .= " AND p.stock = 0";
if ($filtre === 'ok')      $where .= " AND p.stock > p.seuil_alerte";
if ($cat !== '')           $where .= " AND c.nom = " . $db->quote($cat);

$produits = $db->query("
    SELECT p.*, c.nom AS cat, f.nom AS fournisseur,
           COALESCE((SELECT MAX(ms.created_at) FROM mouvements_stock ms WHERE ms.produit_id = p.id AND ms.type = 'entrée'), p.created_at) AS derniere_entree
    FROM produits p
    LEFT JOIN categories c ON p.categorie_id = c.id
    LEFT JOIN fournisseurs f ON p.fournisseur_id = f.id
    WHERE $where ORDER BY derniere_entree DESC, p.nom
")->fetchAll();

$categories = $db->query("SELECT nom FROM categories ORDER BY nom")->fetchAll(PDO::FETCH_COLUMN);

$stats = [
    'total'   => $db->query("SELECT COUNT(*) FROM produits WHERE actif=1")->fetchColumn(),
    'alerte'  => $db->query("SELECT COUNT(*) FROM produits WHERE stock<=seuil_alerte AND stock>0 AND actif=1")->fetchColumn(),
    'rupture' => $db->query("SELECT COUNT(*) FROM produits WHERE stock=0 AND actif=1")->fetchColumn(),
    'valeur'  => $db->query("SELECT COALESCE(SUM(stock*prix_achat),0) FROM produits WHERE actif=1")->fetchColumn(),
];

layout_head('Gestion du stock', 'stock');
showFlash();
?>
<div class="stats-grid" style="grid-template-columns:repeat(4,1fr);">
  <div class="stat-card s-blue">
    <div class="stat-icon" style="color:var(--blue);opacity:.25;"><?= icon('box',28) ?></div>
    <div class="stat-label">Total références</div>
    <div class="stat-value c-blue"><?= $stats['total'] ?></div>
  </div>
  <div class="stat-card s-teal">
    <div class="stat-icon" style="color:var(--teal2);opacity:.25;"><?= icon('money',28) ?></div>
    <div class="stat-label">Valeur d'achat du stock</div>
    <div class="stat-value c-teal" style="font-size:20px;"><?= fmtMoney((float)$stats['valeur']) ?></div>
  </div>
  <div class="stat-card s-gold">
    <div class="stat-icon" style="color:var(--gold);opacity:.25;"><?= icon('alert',28) ?></div>
    <div class="stat-label">Stock bas</div>
    <div class="stat-value c-gold"><?= $stats['alerte'] ?></div>
  </div>
  <div class="stat-card s-red">
    <div class="stat-icon" style="color:var(--red);opacity:.25;"><?= icon('alert',28) ?></div>
    <div class="stat-label">Ruptures</div>
    <div class="stat-value c-red"><?= $stats['rupture'] ?></div>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <div class="card-title">Inventaire des médicaments</div>
    <div class="flex gap-8" style="flex-wrap:wrap;">
      <div class="search-box" style="flex:2;min-width:420px;max-width:640px;">
        <span style="color:var(--text3);display:flex;"><?= icon('search',14) ?></span>
        <input type="text" id="search-stock" placeholder="Rechercher un médicament...">
      </div>
      <select onchange="location.href='?filtre='+this.value+'&cat=<?= urlencode($cat) ?>'" style="padding:6px 12px;font-size:12px;width:auto;">
        <option value="tous"    <?= $filtre==='tous'   ?'selected':'' ?>>Tous</option>
        <option value="alerte"  <?= $filtre==='alerte' ?'selected':'' ?>>Stock bas</option>
        <option value="rupture" <?= $filtre==='rupture'?'selected':'' ?>>Rupture</option>
        <option value="ok"      <?= $filtre==='ok'     ?'selected':'' ?>>Disponible</option>
      </select>
      <select onchange="location.href='?filtre=<?= urlencode($filtre) ?>&cat='+encodeURIComponent(this.value)" style="padding:6px 12px;font-size:12px;width:auto;">
        <option value="">Toutes catégories</option>
        <?php foreach ($categories as $c): ?>
        <option value="<?= e($c) ?>" <?= $cat===$c?'selected':'' ?>><?= e($c) ?></option>
        <?php endforeach; ?>
      </select>
      <?php if (hasPermission('produits.ajouter')): ?>
      <a href="<?= APP_URL ?>/modules/produits.php?action=add" class="btn btn-primary btn-sm">
        <?= icon('plus',14) ?> Médicament
      </a>
      <?php endif; ?>
    </div>
  </div>
  <div class="table-wrap">
    <table id="stock-table">
      <thead>
        <tr>
          <th class="sortable" data-col="0" data-type="text">Médicament <span class="sort-arrow"></span></th>
          <th class="sortable" data-col="1" data-type="text">Réf. <span class="sort-arrow"></span></th>
          <th class="sortable" data-col="2" data-type="text">Catégorie <span class="sort-arrow"></span></th>
          <th class="sortable" data-col="3" data-type="num">Stock <span class="sort-arrow"></span></th>
          <th class="sortable" data-col="4" data-type="num">Seuil <span class="sort-arrow"></span></th>
          <th class="sortable" data-col="5" data-type="num">P. Achat <span class="sort-arrow"></span></th>
          <th class="sortable" data-col="6" data-type="num">P. Vente <span class="sort-arrow"></span></th>
          <th class="sortable" data-col="7" data-type="text">Expiration <span class="sort-arrow"></span></th>
          <th class="sortable" data-col="8" data-type="text">Fournisseur <span class="sort-arrow"></span></th>
          <th class="sortable" data-col="9" data-type="text">Statut <span class="sort-arrow"></span></th>
          <?php if (hasPermission('stock.ajuster') || hasPermission('produits.modifier')): ?><th>Actions</th><?php endif; ?>
        </tr>
      </thead>
      <tbody id="stock-tbody">
        <?php foreach ($produits as $p):
          $exp     = $p['date_expiration'] ? date('m/Y', strtotime($p['date_expiration'])) : '—';
          $expSoon = $p['date_expiration'] && strtotime($p['date_expiration']) < strtotime('+3 months');
          if     ($p['stock'] == 0)                    { $badge='badge-red';  $txt='Rupture';    }
          elseif ($p['stock'] <= $p['seuil_alerte'])   { $badge='badge-gold'; $txt='Stock bas';  }
          else                                          { $badge='badge-green';$txt='Disponible'; }
        ?>
        <tr>
          <td class="td-name"><?= e($p['nom']) ?></td>
          <td class="td-mono"><?= e($p['reference'] ?? '—') ?></td>
          <td><span class="badge badge-gray"><?= e($p['cat'] ?? '—') ?></span></td>
          <td><strong><?= $p['stock'] ?></strong></td>
          <td class="text-sm"><?= $p['seuil_alerte'] ?></td>
          <td class="fw-mono text-sm"><?= fmtMoney((float)$p['prix_achat']) ?></td>
          <td class="fw-mono c-teal"><?= fmtMoney((float)$p['prix_vente']) ?></td>
          <td class="text-sm <?= $expSoon?'c-red':'' ?>"><?= $exp ?><?= $expSoon ? ' ⚠' : '' ?></td>
          <td class="text-sm"><?= e($p['fournisseur'] ?? '—') ?></td>
          <td><span class="badge <?= $badge ?>"><?= $txt ?></span></td>
          <?php if (hasPermission('stock.ajuster') || hasPermission('produits.modifier')): ?>
          <td>
            <div class="flex gap-8">
              <a href="<?= APP_URL ?>/modules/produits.php?action=edit&id=<?= $p['id'] ?>" class="btn btn-ghost btn-xs">
                <?= icon('edit',13) ?>
              </a>
              <a href="<?= APP_URL ?>/modules/stock_ajust.php?id=<?= $p['id'] ?>" class="btn btn-gold btn-xs">
                <?= icon('refresh',13) ?> Ajuster
              </a>
            </div>
          </td>
          <?php endif; ?>
        </tr>
        <?php endforeach; ?>
        <?php if (!$produits): ?>
        <tr><td colspan="11">
          <div class="empty">
            <div style="color:var(--text3);margin-bottom:8px;"><?= icon('box',36) ?></div>
            <div>Aucun produit trouvé</div>
          </div>
        </td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<style>
th.sortable{cursor:pointer;user-select:none;white-space:nowrap;}
th.sortable:hover{color:var(--teal2);}
.sort-arrow{font-size:10px;margin-left:3px;opacity:.3;transition:opacity .15s;}
th.sortable.asc .sort-arrow::after{content:'▲';opacity:1;}
th.sortable.desc .sort-arrow::after{content:'▼';opacity:1;}
th.sortable.asc,th.sortable.desc{color:var(--teal2);}
</style>
<script>
(function(){
  var table=document.getElementById('stock-table');
  if(!table) return;
  var tbody=table.querySelector('tbody');
  var headers=table.querySelectorAll('thead th.sortable');
  var lastCol=null, lastDir='asc';
  headers.forEach(function(th){
    th.addEventListener('click',function(){
      var col=parseInt(th.getAttribute('data-col'));
      var type=th.getAttribute('data-type');
      if(lastCol===col) lastDir=lastDir==='asc'?'desc':'asc';
      else lastDir='asc';
      lastCol=col;
      headers.forEach(function(h){h.classList.remove('asc','desc');});
      th.classList.add(lastDir);
      var rows=Array.from(tbody.querySelectorAll('tr'));
      rows.sort(function(a,b){
        var va=a.children[col]?a.children[col].textContent.trim():'';
        var vb=b.children[col]?b.children[col].textContent.trim():'';
        if(type==='num'){
          va=parseFloat(va.replace(/[^\d.,]/g,'').replace(',','.'))||0;
          vb=parseFloat(vb.replace(/[^\d.,]/g,'').replace(',','.'))||0;
          return lastDir==='asc'?va-vb:vb-va;
        }
        return lastDir==='asc'?va.localeCompare(vb,'fr'):-va.localeCompare(vb,'fr');
      });
      rows.forEach(function(r){tbody.appendChild(r);});
    });
  });
})();
</script>
<?php layout_foot(); ?>
