<?php
require_once __DIR__ . '/../../includes/functions.php';
requireModuleAccess('ordre_mission');
$pageTitle = 'Ordres de Mission';

$db = getDB();
$userId = $_SESSION['user_id'];
$user = currentUser();

// Handle validation actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'valider') {
        $omId = (int)$_POST['ordre_mission_id'];
        $etape = $_POST['etape'];
        $decision = $_POST['decision'];
        $commentaire = trim($_POST['commentaire'] ?? '');

        $permMap = ['hierarchie' => 'valider_hierarchie', 'daf' => 'valider_daf'];
        if (!hasPermission('ordre_mission', $permMap[$etape] ?? '')) {
            flash('danger', "Vous n'avez pas la permission de valider à cette étape.");
            header('Location: index.php'); exit;
        }

        if ($etape === 'hierarchie') {
            $omR = $db->prepare("SELECT service_id FROM ordres_mission WHERE id=?");
            $omR->execute([$omId]);
            $srvId = $omR->fetchColumn();
            $respR = $db->prepare("SELECT responsable_id FROM services WHERE id=?");
            $respR->execute([$srvId]);
            $respId = $respR->fetchColumn();
            if ($respId && (int)$respId !== $userId && !hasPermission('ordre_mission', 'all') && !hasPermission('all', 'all')) {
                flash('danger', 'Seul le responsable hiérarchique du service peut valider à cette étape.');
                header('Location: index.php'); exit;
            }
        }

        $nextStatut = [
            'hierarchie' => ['approuve' => 'valide_hierarchie', 'rejete' => 'rejete', 'renvoi' => 'renvoye'],
            'daf'        => ['approuve' => 'approuve',           'rejete' => 'rejete', 'renvoi' => 'renvoye'],
        ];

        $newStatut = $nextStatut[$etape][$decision] ?? null;
        if ($newStatut) {
            $db->prepare("UPDATE ordres_mission SET statut=? WHERE id=?")->execute([$newStatut, $omId]);
            $db->prepare("INSERT INTO validations_ordre_mission (ordre_mission_id,etape,valideur_id,action,commentaire) VALUES (?,?,?,?,?)")->execute([$omId, $etape, $userId, $decision, $commentaire]);
            auditLog('valider_ordre_mission', 'ordre_mission', 'ordres_mission', $omId);

            $omInfo = $db->prepare("SELECT numero, objet, demandeur_id, caisse_id FROM ordres_mission WHERE id=?");
            $omInfo->execute([$omId]);
            $om = $omInfo->fetch();
            $omNum = $om['numero'] ?? '';
            $demandeurId = (int)($om['demandeur_id'] ?? 0);

            if ($decision === 'approuve') {
                notify($demandeurId, null, 'valide', 'Ordre de mission validé', "Votre ordre de mission $omNum a été validé au niveau " . ($etape === 'hierarchie' ? 'hiérarchique' : 'DAF') . ".", $omId);
                if ($etape === 'hierarchie') {
                    notifyUsersWithPermission('ordre_mission', 'valider_daf', $omId, 'soumis', 'Ordre de mission à valider', "L'ordre de mission $omNum attend votre validation DAF.", [$userId]);
                } elseif ($etape === 'daf') {
                    $caisseId = $om['caisse_id'] ?? null;
                    if ($caisseId) {
                        $caissR = $db->prepare("SELECT responsable_id FROM caisses WHERE id=?");
                        $caissR->execute([$caisseId]);
                        $caissierId = $caissR->fetchColumn();
                        if ($caissierId) notify((int)$caissierId, null, 'approuve', 'Ordre de mission à exécuter', "L'ordre de mission $omNum est approuvé et prêt pour exécution en caisse.", $omId);
                    }
                    notifyUsersWithPermission('caisse', 'saisir', $omId, 'approuve', 'Ordre de mission à exécuter', "L'ordre de mission $omNum est approuvé.", [$userId]);
                }
            } elseif ($decision === 'rejete') {
                notify($demandeurId, null, 'rejete', 'Ordre de mission rejeté', "Votre ordre de mission $omNum a été rejeté.", $omId);
            } elseif ($decision === 'renvoi') {
                notify($demandeurId, null, 'renvoye', 'Ordre de mission renvoyé', "Votre ordre de mission $omNum a été renvoyé pour révision.", $omId);
            }

            $flashMsg = $decision === 'approuve' ? 'approuvé' : ($decision === 'renvoi' ? 'renvoyé pour révision' : 'rejeté');
            flash('success', "Ordre de mission $flashMsg.");
        }
        $redirect = isset($_POST['from_detail']) ? 'detail.php?id=' . $omId : 'index.php';
        header('Location: ' . $redirect); exit;
    }
}

// Filters
$statut_filter = $_GET['statut'] ?? 'tous';
$where = '';
$params = [];
if ($statut_filter !== 'tous') {
    $where = 'WHERE om.statut = ?';
    $params[] = $statut_filter;
}

$ordres = $db->prepare("SELECT om.*, CONCAT(u.nom,' ',u.prenom) as demandeur_nom, s.nom as service_nom, d.libelle as destination_nom, c.libelle as caisse_nom
    FROM ordres_mission om
    JOIN utilisateurs u ON om.demandeur_id=u.id
    JOIN services s ON om.service_id=s.id
    LEFT JOIN destinations d ON om.destination_id=d.id
    LEFT JOIN caisses c ON om.caisse_id=c.id
    $where ORDER BY om.created_at DESC LIMIT 100");
$ordres->execute($params);
$ordres = $ordres->fetchAll();

// Stats
$statsQ = $db->query("SELECT statut, COUNT(*) as n, SUM(montant) as total FROM ordres_mission GROUP BY statut");
$statsArr = [];
foreach ($statsQ->fetchAll() as $s) $statsArr[$s['statut']] = $s;

$badges = ['brouillon'=>'gray','soumis'=>'info','valide_hierarchie'=>'purple','approuve'=>'teal','execute'=>'success','rejete'=>'danger','renvoye'=>'warning','annule'=>'danger'];
$labels = ['brouillon'=>'Brouillon','soumis'=>'Soumis','valide_hierarchie'=>'Val. Hiérarchie','approuve'=>'Approuvé','execute'=>'Exécuté','rejete'=>'Rejeté','renvoye'=>'Renvoyé','annule'=>'Annulé'];
$prioriteLabels = ['normale'=>'Normale','urgente'=>'Urgente','tres_urgente'=>'Très urgente'];
$prioriteBadges = ['normale'=>'gray','urgente'=>'warning','tres_urgente'=>'danger'];

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header d-flex justify-between align-center">
  <div>
    <h1>Ordres de Mission</h1>
    <p>Planification et autorisation des missions et déplacements</p>
  </div>
  <?php if (hasPermission('ordre_mission', 'creer')): ?>
  <a href="creer.php" class="btn btn-primary">+ Nouvel ordre</a>
  <?php endif; ?>
</div>

<!-- Stats -->
<div class="stats-grid" style="grid-template-columns:repeat(5,1fr)">
  <?php
  $statsDefs = [
    ['soumis','En attente','info'],
    ['valide_hierarchie','Val. Hiérarchie','purple'],
    ['approuve','Approuvés','teal'],
    ['renvoye','Renvoyés','warning'],
    ['execute','Exécutés','success'],
  ];
  foreach ($statsDefs as [$st,$lbl,$cls]):
    $n = $statsArr[$st]['n'] ?? 0;
    $tot = $statsArr[$st]['total'] ?? 0;
  ?>
  <div class="stat-card <?= $cls ?>">
    <div class="stat-label"><?= $lbl ?></div>
    <div class="stat-value"><?= $n ?></div>
    <div class="stat-sub"><?= formatMontant($tot) ?></div>
  </div>
  <?php endforeach; ?>
</div>

<!-- Filters -->
<div class="card mb-16">
  <div class="card-body" style="padding:12px 16px">
    <div class="d-flex gap-8 align-center" style="flex-wrap:wrap">
      <span style="font-size:13px;font-weight:600;color:var(--text2)">Filtrer :</span>
      <?php foreach (['tous'=>'Tous','brouillon'=>'Brouillons','soumis'=>'Soumis','valide_hierarchie'=>'Att. DAF','approuve'=>'Approuvés','renvoye'=>'Renvoyés','execute'=>'Exécutés','rejete'=>'Rejetés'] as $v => $l): ?>
      <a href="?statut=<?= $v ?>" class="btn btn-sm <?= $statut_filter===$v ? 'btn-primary' : 'btn-outline' ?>"><?= $l ?></a>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<!-- Table -->
<div class="card">
  <div class="card-header">
    <span class="card-title"><?= count($ordres) ?> ordre(s) de mission</span>
    <div style="margin-left:auto">
      <input type="text" id="search-om" class="form-control" placeholder="Rechercher..." style="width:200px;display:inline-block">
    </div>
  </div>
  <div class="table-wrap">
    <table id="tbl-om">
      <thead>
        <tr>
          <th>N° Ordre</th>
          <th>Demandeur</th>
          <th class="hide-mobile">Service</th>
          <th>Objet</th>
          <th class="hide-mobile">Destination</th>
          <th>Dates</th>
          <th>Montant</th>
          <th>Priorité</th>
          <th>Statut</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($ordres)): ?>
        <tr><td colspan="10" class="text-center text-muted" style="padding:32px">Aucun ordre de mission</td></tr>
        <?php else: foreach ($ordres as $o): ?>
        <tr>
          <td><code style="font-size:12px"><?= sanitize($o['numero']) ?></code></td>
          <td><?= sanitize($o['demandeur_nom']) ?></td>
          <td class="hide-mobile"><?= sanitize($o['service_nom']) ?></td>
          <td style="max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?= sanitize($o['objet']) ?>"><?= sanitize($o['objet']) ?></td>
          <td class="hide-mobile"><?= !empty($o['destination_nom']) ? sanitize($o['destination_nom']) : '—' ?></td>
          <td style="white-space:nowrap"><?= date('d/m', strtotime($o['date_depart'])) ?> → <?= date('d/m/Y', strtotime($o['date_retour'])) ?></td>
          <td class="amount fw-bold"><?= formatMontant($o['montant']) ?></td>
          <td><span class="badge badge-<?= $prioriteBadges[$o['priorite']] ?? 'gray' ?>"><?= $prioriteLabels[$o['priorite']] ?? $o['priorite'] ?></span></td>
          <td><span class="badge badge-<?= $badges[$o['statut']] ?? 'gray' ?>"><?= $labels[$o['statut']] ?? $o['statut'] ?></span></td>
          <td>
            <a href="detail.php?id=<?= $o['id'] ?>" class="btn btn-ghost btn-sm" title="Voir le détail"><i class="fa-solid fa-eye"></i></a>
            <?php if (in_array($o['statut'], ['brouillon','renvoye']) && (int)$o['demandeur_id'] === $userId && hasPermission('ordre_mission', 'creer')): ?>
            <a href="modifier.php?id=<?= $o['id'] ?>" class="btn btn-outline btn-sm" title="Modifier"><i class="fa-solid fa-pen"></i></a>
            <?php endif; ?>
            <?php if ($o['statut'] === 'soumis' && hasPermission('ordre_mission', 'valider_hierarchie')):
              $canVal = true;
              $rR = $db->prepare("SELECT responsable_id FROM services WHERE id=?");
              $rR->execute([$o['service_id']]);
              $rId = $rR->fetchColumn();
              if ($rId && (int)$rId !== $userId && !hasPermission('ordre_mission', 'all') && !hasPermission('all', 'all')) $canVal = false;
              if ($canVal): ?>
            <button class="btn btn-outline btn-sm" onclick="ouvrirValidation(<?= $o['id'] ?>,'hierarchie','<?= sanitize($o['numero']) ?>')" title="Valider N1"><i class="fa-solid fa-check"></i></button>
            <?php endif; endif; ?>
            <?php if ($o['statut'] === 'valide_hierarchie' && hasPermission('ordre_mission', 'valider_daf')): ?>
            <button class="btn btn-outline btn-sm" onclick="ouvrirValidation(<?= $o['id'] ?>,'daf','<?= sanitize($o['numero']) ?>')" title="Valider DAF"><i class="fa-solid fa-check"></i></button>
            <?php elseif ($o['statut'] === 'approuve'): ?>
            <a href="<?= BASE_URL ?>/modules/operations_caisse/index.php?caisse=<?= $o['caisse_id'] ?? '' ?>" class="btn btn-primary btn-sm" title="Exécuter en caisse"><i class="fa-solid fa-arrow-right"></i> Caisse</a>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Modal: Validation -->
<div class="modal-overlay" id="modal-validation">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title">Validation — <span id="lbl-om-num"></span></div>
      <button class="modal-close" onclick="closeModal('modal-validation')"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <form method="post">
      <input type="hidden" name="action" value="valider">
      <input type="hidden" name="ordre_mission_id" id="val-om-id">
      <input type="hidden" name="etape" id="val-etape">
      <?php if (isset($_GET['from_detail'])): ?><input type="hidden" name="from_detail" value="1"><?php endif; ?>
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Décision <span class="req">*</span></label>
          <select name="decision" class="form-control" required>
            <option value="approuve">Approuver</option>
            <option value="renvoi">Renvoyer pour révision</option>
            <option value="rejete">Rejeter</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Commentaire</label>
          <textarea name="commentaire" class="form-control" rows="3" placeholder="Motif, conditions..."></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('modal-validation')">Annuler</button>
        <button type="submit" class="btn btn-primary">Confirmer</button>
      </div>
    </form>
  </div>
</div>

<script>
tableSearch('search-om', 'tbl-om');

function ouvrirValidation(id, etape, num) {
  document.getElementById('val-om-id').value = id;
  document.getElementById('val-etape').value = etape;
  document.getElementById('lbl-om-num').textContent = num;
  openModal('modal-validation');
}
</script>
<?php include __DIR__ . '/../../includes/footer.php'; ?>