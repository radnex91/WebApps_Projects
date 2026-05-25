<?php
require_once __DIR__ . '/../../includes/functions.php';
requireModuleAccess('engagements');
$pageTitle = 'Gestion des Engagements';

$db = getDB();
$userId = $_SESSION['user_id'];
$user = currentUser();

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'valider') {
        $engId = (int)$_POST['engagement_id'];
        $etape = $_POST['etape'];
        $actionVal = $_POST['decision'];
        $commentaire = trim($_POST['commentaire']??'');

        // Permission checks
        $permMap = [
            'hierarchie' => 'valider_hierarchie',
            'comptable'  => 'valider_comptable',
            'daf'         => 'valider_daf',
        ];

        if (!hasPermission('engagements', $permMap[$etape] ?? '')) {
            flash('danger', 'Vous n\'avez pas la permission de valider à cette étape.');
            header('Location: index.php'); exit;
        }

        // For hierarchie step, also check that validator is the responsable of the demandeur's service
        if ($etape === 'hierarchie') {
            $engR = $db->prepare("SELECT service_id FROM demandes_engagement WHERE id=?");
            $engR->execute([$engId]);
            $engServiceId = $engR->fetchColumn();
            $respR = $db->prepare("SELECT responsable_id FROM services WHERE id=?");
            $respR->execute([$engServiceId]);
            $respId = $respR->fetchColumn();
            if ($respId && (int)$respId !== $userId) {
                // Also allow if user has all permissions on engagements
                if (!hasPermission('engagements', 'all') && !hasPermission('all', 'all')) {
                    flash('danger', 'Seul le responsable hiérarchique du service peut valider à cette étape.');
                    header('Location: index.php'); exit;
                }
            }
        }

        // Map etape to next statut
        $nextStatut = [
            'hierarchie' => ['approuve' => 'valide_hierarchie', 'rejete' => 'rejete', 'renvoi' => 'renvoye'],
            'comptable'  => ['approuve' => 'valide_comptable',  'rejete' => 'rejete', 'renvoi' => 'renvoye'],
            'daf'        => ['approuve' => 'approuve',           'rejete' => 'rejete', 'renvoi' => 'renvoye'],
        ];

        $newStatut = $nextStatut[$etape][$actionVal] ?? null;
        if ($newStatut) {
            $db->prepare("UPDATE demandes_engagement SET statut=? WHERE id=?")->execute([$newStatut,$engId]);
            $db->prepare("INSERT INTO validations_engagement (engagement_id,etape,valideur_id,action,commentaire) VALUES (?,?,?,?,?)")->execute([$engId,$etape,$userId,$actionVal,$commentaire]);
            auditLog('valider_engagement','engagements','demandes_engagement',$engId);

            // Fetch engagement details for notifications
            $engNotif = $db->prepare("SELECT numero, objet, demandeur_id, caisse_id FROM demandes_engagement WHERE id=?");
            $engNotif->execute([$engId]);
            $engInfo = $engNotif->fetch();
            $engNum = $engInfo['numero'] ?? '';
            $engObjet = $engInfo['objet'] ?? '';
            $demandeurId = (int)($engInfo['demandeur_id'] ?? 0);

            if ($actionVal === 'approuve') {
                // Notify demandeur
                notify($demandeurId, $engId, 'valide', 'Engagement validé', 'Votre engagement ' . $engNum . ' a été validé au niveau ' . ($etape === 'hierarchie' ? 'hiérarchique' : ($etape === 'comptable' ? 'comptable' : 'DAF')) . '.');

                if ($etape === 'hierarchie') {
                    // Notify comptables
                    notifyUsersWithPermission('engagements', 'valider_comptable', $engId, 'soumis', 'Engagement à valider', 'L\'engagement ' . $engNum . ' attend votre validation comptable.', [$userId]);
                } elseif ($etape === 'comptable') {
                    // Notify DAF
                    notifyUsersWithPermission('engagements', 'valider_daf', $engId, 'soumis', 'Engagement à valider', 'L\'engagement ' . $engNum . ' attend votre validation DAF.', [$userId]);
                } elseif ($etape === 'daf') {
                    // Notify caissier of assigned caisse
                    $caisseId = $engInfo['caisse_id'] ?? null;
                    if ($caisseId) {
                        $caissR = $db->prepare("SELECT responsable_id FROM caisses WHERE id=?");
                        $caissR->execute([$caisseId]);
                        $caissierId = $caissR->fetchColumn();
                        if ($caissierId) {
                            notify((int)$caissierId, $engId, 'approuve', 'Engagement à exécuter', 'L\'engagement ' . $engNum . ' est approuvé et prêt pour exécution en caisse.');
                        }
                    }
                    // Also notify all caissiers if no specific caisse
                    notifyUsersWithPermission('caisse', 'saisir', $engId, 'approuve', 'Engagement à exécuter', 'L\'engagement ' . $engNum . ' est approuvé.', [$userId]);
                }
            } elseif ($actionVal === 'rejete') {
                notify($demandeurId, $engId, 'rejete', 'Engagement rejeté', 'Votre engagement ' . $engNum . ' a été rejeté.');
            } elseif ($actionVal === 'renvoi') {
                notify($demandeurId, $engId, 'renvoye', 'Engagement renvoyé', 'Votre engagement ' . $engNum . ' a été renvoyé pour révision. Merci de le modifier et le resoumettre.');
            }

            $flashMsg = $actionVal === 'approuve' ? 'approuvé' : ($actionVal === 'renvoi' ? 'renvoyé pour révision' : 'rejeté');
            flash('success', 'Engagement ' . $flashMsg . '.');
        }
        $redirect = isset($_POST['from_detail']) ? 'detail.php?id=' . $engId : 'index.php';
        header('Location: ' . $redirect); exit;
    }
}

// Fetch data
$statut_filter = $_GET['statut'] ?? 'tous';
$where = '';
$params = [];
if ($statut_filter !== 'tous') {
    $where = 'WHERE de.statut = ?';
    $params[] = $statut_filter;
}

$engagements = $db->prepare("SELECT de.*, CONCAT(u.nom,' ',u.prenom) as demandeur_nom, s.nom as service_nom, c.libelle as caisse_nom, to2.libelle as type_operation_nom, gp.libelle as groupe_proprietaire_nom, dst.libelle as destination_nom FROM demandes_engagement de JOIN utilisateurs u ON de.demandeur_id=u.id JOIN services s ON de.service_id=s.id LEFT JOIN caisses c ON de.caisse_id=c.id LEFT JOIN types_operations to2 ON de.type_operation_id=to2.id LEFT JOIN groupes_proprietaires gp ON de.groupe_proprietaire_id=gp.id LEFT JOIN destinations dst ON de.destination_id=dst.id $where ORDER BY de.created_at DESC LIMIT 100");
$engagements->execute($params);
$engagements = $engagements->fetchAll();

// Stats
$statsQ = $db->query("SELECT statut, COUNT(*) as n, SUM(montant) as total FROM demandes_engagement GROUP BY statut");
$statsArr = [];
foreach($statsQ->fetchAll() as $s) $statsArr[$s['statut']] = $s;

$badges = [
  'brouillon'=>'gray','soumis'=>'info','valide_hierarchie'=>'purple',
  'valide_comptable'=>'purple','valide_daf'=>'teal','approuve'=>'teal',
  'execution_partielle'=>'info','execute'=>'success','solde'=>'warning',
  'rejete'=>'danger','renvoye'=>'warning','annule'=>'danger'
];
$labels = [
  'brouillon'=>'Brouillon','soumis'=>'Soumis','valide_hierarchie'=>'Val. Hiérarchie',
  'valide_comptable'=>'Val. Comptable','valide_daf'=>'Val. DAF',
  'approuve'=>'Approuvé','execution_partielle'=>'Exéc. partielle',
  'execute'=>'Exécuté','solde'=>'Soldé','rejete'=>'Rejeté','renvoye'=>'Renvoyé','annule'=>'Annulé'
];
$prioriteLabels = ['normale'=>'Normale','urgente'=>'Urgente','tres_urgente'=>'Très urgente'];
$prioriteBadges = ['normale'=>'gray','urgente'=>'warning','tres_urgente'=>'danger'];

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header d-flex justify-between align-center">
  <div>
    <h1>Engagements Financiers</h1>
    <p>Workflow de validation : Hiérarchie → Comptable → DAF → Caisse</p>
  </div>
  <?php if (hasPermission('engagements', 'creer')): ?>
  <a href="creer.php" class="btn btn-primary">+ Nouvelle demande</a>
  <?php endif; ?>
</div>

<!-- Stats -->
<div class="stats-grid" style="grid-template-columns:repeat(auto-fill,minmax(140px,1fr))">
  <?php
  $statsDefs = [
    ['soumis','En attente','info'],
    ['valide_hierarchie','Val. N1','purple'],
    ['valide_comptable','Val. Compta','purple'],
    ['approuve','Approuvés','teal'],
    ['execution_partielle','Exéc. partielle','info'],
    ['execute','Exécutés','success'],
    ['solde','Soldés','warning'],
    ['renvoye','Renvoyés','warning'],
  ];
  foreach($statsDefs as [$st,$lbl,$cls]):
    $n = $statsArr[$st]['n']??0;
    $tot = $statsArr[$st]['total']??0;
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
      <?php foreach(['tous'=>'Tous', 'brouillon'=>'Brouillons', 'soumis'=>'Soumis', 'valide_hierarchie'=>'Att. Comptable', 'valide_comptable'=>'Att. DAF', 'approuve'=>'Approuvés', 'execution_partielle'=>'Exéc. partielle', 'execute'=>'Exécutés', 'solde'=>'Soldés', 'renvoye'=>'Renvoyés', 'rejete'=>'Rejetés'] as $v=>$l): ?>
      <a href="?statut=<?= $v ?>" class="btn btn-sm <?= $statut_filter===$v?'btn-primary':'btn-outline' ?>"><?= $l ?></a>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<!-- Table -->
<div class="card">
  <div class="card-header">
    <span class="card-title"><?= count($engagements) ?> demande(s) d'engagement</span>
    <div style="margin-left:auto">
      <input type="text" id="search-eng" class="form-control" placeholder="Rechercher..." style="width:200px;display:inline-block">
    </div>
  </div>
  <div class="table-wrap">
    <table id="tbl-eng">
      <thead>
        <tr>
          <th>N° Engagement</th>
          <th>Demandeur</th>
          <th class="hide-mobile">Service</th>
          <th>Objet</th>
          <th class="hide-mobile">Type d'opération</th>
          <th>Destination</th>
          <th>Priorité</th>
          <th>Montant</th>
          <th class="hide-mobile">Date besoin</th>
          <th>Statut</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($engagements)): ?>
        <tr><td colspan="10" class="text-center text-muted" style="padding:32px">Aucune demande d'engagement</td></tr>
        <?php else: foreach($engagements as $e): ?>
        <tr>
          <td><code style="font-size:12px"><?= sanitize($e['numero']) ?></code></td>
          <td><?= sanitize($e['demandeur_nom']) ?></td>
          <td><?= sanitize($e['service_nom']) ?></td>
          <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?= sanitize($e['objet']) ?>"><?= sanitize($e['objet']) ?></td>
          <td class="hide-mobile"><?= sanitize($e['type_operation_nom'] ?? '—') ?></td>
          <td><?= !empty($e['destination_nom']) ? sanitize($e['destination_nom']) : '—' ?></td>
          <td><span class="badge badge-<?= $prioriteBadges[$e['priorite']]??'gray' ?>"><?= $prioriteLabels[$e['priorite']]??$e['priorite'] ?></span></td>
          <td>
            <span class="amount fw-bold"><?= formatMontant($e['montant']) ?></span>
            <?php if ($e['statut'] === 'execution_partielle'): ?>
            <?php $pctE = $e['montant'] > 0 ? round(($e['montant_execute'] / $e['montant']) * 100) : 0; ?>
            <div style="background:var(--border);border-radius:3px;height:4px;margin-top:3px;overflow:hidden">
              <div style="background:var(--primary);height:100%;width:<?= $pctE ?>%"></div>
            </div>
            <span style="font-size:11px;color:var(--text3)"><?= $pctE ?>% — Reste <?= formatMontant($e['montant'] - $e['montant_execute']) ?></span>
            <?php endif; ?>
          </td>
          <td><?= $e['date_besoin'] ? date('d/m/Y', strtotime($e['date_besoin'])) : '—' ?></td>
          <td><span class="badge badge-<?= $badges[$e['statut']]??'gray' ?>"><?= $labels[$e['statut']]??$e['statut'] ?></span></td>
          <td>
            <a href="detail.php?id=<?= $e['id'] ?>" class="btn btn-ghost btn-sm" title="Voir le détail"><i class="fa-solid fa-eye"></i></a>
            <?php if (in_array($e['statut'], ['brouillon','renvoye']) && (int)$e['demandeur_id']===$userId && hasPermission('engagements', 'creer')): ?>
            <a href="modifier.php?id=<?= $e['id'] ?>" class="btn btn-outline btn-sm" title="Modifier"><i class="fa-solid fa-pen"></i></a>
            <?php endif; ?>
            <?php if ($e['statut']==='soumis' && hasPermission('engagements', 'valider_hierarchie')): ?>
              <?php
                // Check if user is the responsable of the demandeur's service
                $canValidateN1 = true;
                $respR = $db->prepare("SELECT responsable_id FROM services WHERE id=?");
                $respR->execute([$e['service_id']]);
                $respId = $respR->fetchColumn();
                if ($respId && (int)$respId !== $userId && !hasPermission('engagements', 'all') && !hasPermission('all', 'all')) {
                    $canValidateN1 = false;
                }
              ?>
              <?php if ($canValidateN1): ?>
            <button class="btn btn-outline btn-sm" onclick="ouvrirValidation(<?= $e['id'] ?>,'hierarchie','<?= sanitize($e['numero']) ?>')" title="Valider N1"><i class="fa-solid fa-check"></i></button>
              <?php endif; ?>
            <?php elseif ($e['statut']==='valide_hierarchie' && hasPermission('engagements', 'valider_comptable')): ?>
            <button class="btn btn-outline btn-sm" onclick="ouvrirValidation(<?= $e['id'] ?>,'comptable','<?= sanitize($e['numero']) ?>')" title="Valider Compta"><i class="fa-solid fa-check"></i></button>
            <?php elseif ($e['statut']==='valide_comptable' && hasPermission('engagements', 'valider_daf')): ?>
            <button class="btn btn-outline btn-sm" onclick="ouvrirValidation(<?= $e['id'] ?>,'daf','<?= sanitize($e['numero']) ?>')" title="Valider DAF"><i class="fa-solid fa-check"></i></button>
            <?php elseif (in_array($e['statut'], ['approuve', 'execution_partielle']) && ($user['role_nom'] ?? '') === 'caissier'): ?>
            <a href="<?= BASE_URL ?>/modules/operations_caisse/index.php?caisse=<?= $e['caisse_id'] ?? '' ?>" class="btn btn-primary btn-sm" title="Exécuter en caisse"><i class="fa-solid fa-arrow-right"></i> Caisse</a>
            <?php endif; ?>
            <?php if ($e['statut'] === 'execute' || $e['statut'] === 'solde'): ?>
            <a href="detail.php?id=<?= $e['id'] ?>&print=1" class="btn btn-outline btn-sm no-print" title="Imprimer"><i class="fa-solid fa-print"></i></a>
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
      <div class="modal-title">Validation — <span id="lbl-eng-num"></span></div>
      <button class="modal-close" onclick="closeModal('modal-validation')"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <form method="post">
      <input type="hidden" name="action" value="valider">
      <input type="hidden" name="engagement_id" id="val-eng-id">
      <input type="hidden" name="etape" id="val-etape">
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
tableSearch('search-eng', 'tbl-eng');

function ouvrirValidation(id, etape, num) {
  document.getElementById('val-eng-id').value = id;
  document.getElementById('val-etape').value = etape;
  document.getElementById('lbl-eng-num').textContent = num;
  openModal('modal-validation');
}
</script>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
