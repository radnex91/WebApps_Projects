<?php
// includes/dashboard-alertes-pharmacies.php
// Bloc d'alertes stock par pharmacie (affiché en haut des dashboards).
// Attend $db en scope. N'affiche rien s'il n'y a qu'une seule pharmacie.

$alertesPharma = $db->query("
    SELECT ph.id, ph.nom,
           COALESCE(SUM(CASE WHEN pp.stock <= pp.seuil_alerte AND p.actif = 1 THEN 1 ELSE 0 END), 0) AS alertes,
           COALESCE(SUM(CASE WHEN pp.stock = 0 AND p.actif = 1 THEN 1 ELSE 0 END), 0)               AS ruptures
    FROM pharmacies ph
    LEFT JOIN produit_pharmacie pp ON pp.pharmacie_id = ph.id
    LEFT JOIN produits p ON p.id = pp.produit_id
    WHERE ph.actif = 1
    GROUP BY ph.id, ph.nom
    ORDER BY ph.id
")->fetchAll();

if (count($alertesPharma) > 1):
?>
<div class="card" style="margin-bottom:20px;">
  <div class="card-header">
    <div class="card-title"><?= icon('alert', 16) ?> Alertes stock par pharmacie</div>
  </div>
  <div class="card-pad" style="display:flex;gap:12px;flex-wrap:wrap;">
    <?php foreach ($alertesPharma as $ap):
      $rupt  = (int)$ap['ruptures'];
      $bas   = (int)$ap['alertes'];
      $niveau = $rupt > 0 ? 'critique' : ($bas > 0 ? 'bas' : 'ok');
      $bord = $niveau === 'critique' ? 'var(--red)' : ($niveau === 'bas' ? 'var(--gold)' : 'var(--teal2)');
      $fond = $niveau === 'critique' ? 'rgba(239,68,68,.08)' : ($niveau === 'bas' ? 'rgba(245,158,11,.08)' : 'rgba(20,184,166,.06)');
      $lien = hasPermission('pharmacies.voir')
        ? url('pharmacies', ['action' => 'stock', 'id' => (int)$ap['id']])
        : null;
    ?>
    <<?= $lien ? 'a href="' . e($lien) . '" style="text-decoration:none;color:inherit;flex:1;min-width:190px;' : 'div style="flex:1;min-width:190px;' ?>
        border:1px solid <?= $bord ?>;border-radius:10px;padding:12px;background:<?= $fond ?>;">
      <div style="font-size:13px;font-weight:600;color:var(--text);display:flex;align-items:center;gap:6px;">
        <?= icon('building', 14) ?> <?= e($ap['nom']) ?>
      </div>
      <div style="display:flex;gap:18px;margin-top:8px;">
        <div>
          <span style="font-size:20px;font-weight:700;color:var(--gold);"><?= $bas ?></span>
          <span class="text-xs" style="color:var(--text3);">stock bas</span>
        </div>
        <div>
          <span style="font-size:20px;font-weight:700;color:var(--red);"><?= $rupt ?></span>
          <span class="text-xs" style="color:var(--text3);">rupture</span>
        </div>
      </div>
    <?= $lien ? '</a>' : '</div>' ?>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>