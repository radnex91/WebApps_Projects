<?php
// modules/bons/index.php — Bons des Actionnaires
require_once '../../includes/config.php';
requireLogin(); requirePerm('bons.view');
$pageTitle = 'Bons des Actionnaires';

if (isset($_GET['payer']) && can('bons.manage')) {
    $pdo->prepare("UPDATE bons_actionnaires SET statut='paye' WHERE id=?")->execute([(int)$_GET['payer']]);
    flash('Bon marqué comme payé.');
    redirect(BASE_URL.'modules/bons/');
}
if (isset($_GET['del']) && isSuperAdmin()) {
    $pdo->prepare("DELETE FROM bons_actionnaires WHERE id=?")->execute([(int)$_GET['del']]);
    flash('Supprimé.','warning');
    redirect(BASE_URL.'modules/bons/');
}

$date_d = $_GET['date_d'] ?? date('Y-m-01');
$date_f = $_GET['date_f'] ?? date('Y-m-d');
$grp_f  = (int)($_GET['grp'] ?? 0);
$statut_f = $_GET['statut'] ?? '';

$where = ['b.date_paiement BETWEEN ? AND ?']; $params = [$date_d, $date_f];
if ($grp_f)    { $where[] = 'b.groupe_id=?';  $params[] = $grp_f; }
if ($statut_f) { $where[] = 'b.statut=?';     $params[] = $statut_f; }
$ws = implode(' AND ', $where);

$stmt = $pdo->prepare("SELECT b.*,g.nom as groupe_nom,g.banque,g.num_compte_bancaire,CONCAT(p.prenom,' ',p.nom) as employe_nom,a.nom as agence_nom FROM bons_actionnaires b LEFT JOIN groupes g ON b.groupe_id=g.id LEFT JOIN personnel p ON b.employe_id=p.id LEFT JOIN agences a ON b.agence_id=a.id WHERE $ws ORDER BY b.date_paiement DESC,b.created_at DESC");
$stmt->execute($params); $bons = $stmt->fetchAll();

$tots = $pdo->prepare("SELECT SUM(montant) FROM bons_actionnaires b WHERE $ws"); $tots->execute($params); $totM = $tots->fetchColumn();

$groupes  = $pdo->query("SELECT id,nom FROM groupes ORDER BY nom")->fetchAll();
$agences  = $pdo->query("SELECT id,nom FROM agences WHERE actif=1 ORDER BY nom")->fetchAll();
$personnels = $pdo->query("SELECT id,CONCAT(nom,' ',COALESCE(prenom,'')) as nom FROM personnel WHERE actif=1 ORDER BY nom")->fetchAll();

include '../../includes/header.php';
?>
<div class="breadcrumb"><a href="<?= BASE_URL ?>"><i class="fas fa-home"></i></a><span class="breadcrumb-sep">/</span>Bons Actionnaires</div>

<div class="card" style="margin-bottom:14px;">
  <div class="card-header">
    <h3><i class="fas fa-handshake"></i> Bons des Actionnaires (<?= count($bons) ?>)</h3>
    <?php if(can('bons.manage')): ?>
    <button class="btn btn-primary btn-sm" onclick="openModal('bm');document.getElementById('bon-id').value=''"><i class="fas fa-plus"></i> Nouveau bon</button>
    <?php endif; ?>
  </div>
  <div class="card-body">
    <form method="GET" class="filter-bar">
      <select name="grp" class="fc" style="width:auto;">
        <option value="">Tous les groupes</option>
        <?php foreach($groupes as $g): ?><option value="<?= $g['id'] ?>" <?= $grp_f==$g['id']?'selected':'' ?>><?= h($g['nom']) ?></option><?php endforeach; ?>
      </select>
      <select name="statut" class="fc" style="width:auto;">
        <option value="">Tous statuts</option>
        <option value="en_attente" <?= $statut_f==='en_attente'?'selected':'' ?>>En attente</option>
        <option value="paye" <?= $statut_f==='paye'?'selected':'' ?>>Payé</option>
        <option value="expire" <?= $statut_f==='expire'?'selected':'' ?>>Expiré</option>
      </select>
      <input type="date" name="date_d" class="fc" value="<?= $date_d ?>" style="width:auto;">
      <input type="date" name="date_f" class="fc" value="<?= $date_f ?>" style="width:auto;">
      <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-search"></i></button>
      <a href="?" class="btn btn-ghost btn-sm"><i class="fas fa-times"></i></a>
    </form>
    <div style="display:flex;gap:16px;font-size:13px;background:var(--bg);padding:10px 14px;border-radius:var(--radius);">
      <span>💰 Total bons : <strong style="color:var(--primary);"><?= moneyRaw($totM??0) ?> FCFA</strong></span>
      <span>📋 Nombre : <strong><?= count($bons) ?></strong></span>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-body" style="padding:0;">
    <div class="table-wrap">
    <table>
      <thead><tr><th>Groupe</th><th>Payeur</th><th>Date paiement</th><th>Montant</th><th>Mode</th><th>Bénéficiaire</th><th>Agence</th><th>Date expir.</th><th>Statut</th><th>Actions</th></tr></thead>
      <tbody>
        <?php foreach($bons as $b): ?>
        <tr style="<?= $b['statut']==='expire'?'opacity:.6;':'' ?>">
          <td><span class="badge b-purple"><?= h($b['groupe_nom']??'—') ?></span></td>
          <td><strong><?= h($b['nom_payeur']??'—') ?></strong></td>
          <td><?= fdate($b['date_paiement']??'') ?></td>
          <td style="font-weight:700;font-size:14px;color:var(--primary);"><?= moneyRaw($b['montant']) ?></td>
          <td><span class="badge b-teal"><?= h($b['mode_paiement']) ?></span></td>
          <td style="font-size:12px;"><?= h($b['employe_nom']??'—') ?></td>
          <td style="font-size:12px;"><?= h($b['agence_nom']??'—') ?></td>
          <td style="font-size:12px;<?= ($b['date_expir_delai']&&$b['date_expir_delai']<date('Y-m-d')&&$b['statut']==='en_attente')?'color:var(--danger);font-weight:600;':'' ?>"><?= fdate($b['date_expir_delai']??'') ?></td>
          <td>
            <span class="st-<?= $b['statut'] ?>"><?= match($b['statut']){
              'paye'=>'✅ Payé',
              'expire'=>'❌ Expiré',
              default=>'⏳ En attente'
            } ?></span>
          </td>
          <td>
            <div style="display:flex;gap:3px;">
              <?php if($b['statut']==='en_attente' && can('bons.manage')): ?>
              <a href="?payer=<?= $b['id'] ?>&date_d=<?= $date_d ?>&date_f=<?= $date_f ?>" class="btn btn-xs btn-success" onclick="return confirm('Marquer ce bon comme payé ?')"><i class="fas fa-check"></i></a>
              <?php endif; ?>
              <?php if(isSuperAdmin()): ?><a href="?del=<?= $b['id'] ?>&date_d=<?= $date_d ?>&date_f=<?= $date_f ?>" class="btn btn-xs btn-danger" onclick="return confirm('Supprimer ?')"><i class="fas fa-trash"></i></a><?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if(empty($bons)): ?><tr><td colspan="10" class="t-empty"><i class="fas fa-handshake"></i>Aucun bon actionnaire</td></tr><?php endif; ?>
      </tbody>
      <?php if(!empty($bons)): ?>
      <tfoot>
        <tr style="background:var(--primary);color:#fff;font-weight:700;">
          <td colspan="3" style="padding:8px 12px;">TOTAL</td>
          <td style="padding:8px 12px;font-size:14px;"><?= moneyRaw($totM??0) ?> FCFA</td>
          <td colspan="6"></td>
        </tr>
      </tfoot>
      <?php endif; ?>
    </table>
    </div>
  </div>
</div>

<!-- MODAL NOUVEAU BON -->
<div class="modal-over" id="bm">
  <div class="modal modal-sm">
    <div class="modal-head"><h3><i class="fas fa-handshake"></i> Nouveau bon actionnaire</h3><button class="modal-x" onclick="closeModal('bm')">✕</button></div>
    <form method="POST">
      <input type="hidden" name="action" value="create">
      <input type="hidden" name="id" id="bon-id">
      <div class="modal-body">
        <div class="form-grid">
          <div class="fg full">
            <label class="flbl">Groupe actionnaire <span class="freq">*</span></label>
            <select name="groupe_id" class="fc" required>
              <option value="">— Sélectionner —</option>
              <?php foreach($groupes as $g): ?><option value="<?= $g['id'] ?>"><?= h($g['nom']) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="fg">
            <label class="flbl">Nom du payeur</label>
            <input type="text" name="nom_payeur" class="fc" placeholder="Nom de la personne payante">
          </div>
          <div class="fg">
            <label class="flbl">Date de paiement</label>
            <input type="date" name="date_paiement" class="fc" value="<?= date('Y-m-d') ?>">
          </div>
          <div class="fg">
            <label class="flbl">Montant (FCFA) <span class="freq">*</span></label>
            <input type="number" name="montant" class="fc" required min="1" step="100">
          </div>
          <div class="fg">
            <label class="flbl">Mode de paiement</label>
            <select name="mode_paiement" class="fc">
              <option value="Virement">Virement</option>
              <option value="Espèces">Espèces</option>
              <option value="Chèque">Chèque</option>
              <option value="Mobile Money">Mobile Money</option>
            </select>
          </div>
          <div class="fg">
            <label class="flbl">Bénéficiaire (employé)</label>
            <select name="employe_id" class="fc">
              <option value="">— Aucun —</option>
              <?php foreach($personnels as $p): ?><option value="<?= $p['id'] ?>"><?= h($p['nom']) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="fg">
            <label class="flbl">Agence</label>
            <select name="agence_id" class="fc">
              <option value="">— Aucune —</option>
              <?php foreach($agences as $a): ?><option value="<?= $a['id'] ?>"><?= h($a['nom']) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="fg">
            <label class="flbl">Date d'expiration du délai</label>
            <input type="date" name="date_expir_delai" class="fc" value="<?= date('Y-m-d', strtotime('+30 days')) ?>">
          </div>
        </div>
      </div>
      <div class="modal-foot">
        <button type="button" class="btn btn-secondary" onclick="closeModal('bm')">Annuler</button>
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Enregistrer</button>
      </div>
    </form>
  </div>
</div>

<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action']??'') === 'create') {
    $grp  = (int)($_POST['groupe_id'] ?? 0);
    $payeur = trim($_POST['nom_payeur'] ?? '');
    $date_p = $_POST['date_paiement'] ?? date('Y-m-d');
    $montant = (float)($_POST['montant'] ?? 0);
    $mode  = $_POST['mode_paiement'] ?? 'Virement';
    $emp   = (int)($_POST['employe_id'] ?? 0) ?: null;
    $ag    = (int)($_POST['agence_id'] ?? 0) ?: null;
    $expir = $_POST['date_expir_delai'] ?? null ?: null;
    if ($grp && $montant > 0) {
        $pdo->prepare("INSERT INTO bons_actionnaires (groupe_id,nom_payeur,date_paiement,montant,mode_paiement,employe_id,agence_id,date_expir_delai,saisie_par) VALUES (?,?,?,?,?,?,?,?,?)")
            ->execute([$grp,$payeur,$date_p,$montant,$mode,$emp,$ag,$expir,$_SESSION['user_id']]);
        logAction($pdo,'create_bon','bons',"Groupe $grp — ".money($montant));
        flash('Bon actionnaire créé.');
    } else { flash('Groupe et montant obligatoires.','danger'); }
    redirect(BASE_URL.'modules/bons/');
}
include '../../includes/footer.php';
?>
