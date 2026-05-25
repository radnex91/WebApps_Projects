<?php
require_once __DIR__ . '/../../includes/functions.php';
requireLogin();
$pageTitle = 'Gestion des exercices';

if (!hasPermission('cloture', 'gerer_exercice') && !hasPermission('admin', 'all') && !hasPermission('all', 'all')) {
    flash('danger', 'Accès réservé au DAF ou Super Admin.');
    header('Location: ' . BASE_URL . '/dashboard.php');
    exit;
}

$db = getDB();

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $code = sanitize($_POST['code'] ?? '');
        $dateDebut = $_POST['date_debut'] ?? '';
        $dateFin = $_POST['date_fin'] ?? '';

        if ($code && $dateDebut && $dateFin) {
            try {
                $db->prepare("INSERT INTO exercices (code, date_debut, date_fin, statut) VALUES (?,?,?,'ouvert')")
                   ->execute([$code, $dateDebut, $dateFin]);
                auditLog('create', 'exercices', 'exercices', $db->lastInsertId(), null, ['code' => $code]);
                flash('success', "Exercice $code créé avec succès.");
            } catch (Exception $e) {
                flash('danger', 'Erreur: ' . $e->getMessage());
            }
        }
        header('Location: index.php'); exit;
    }

    if ($action === 'change_status') {
        $id = (int)($_POST['id'] ?? 0);
        $nouveauStatut = $_POST['statut'] ?? '';
        $statutsValides = ['ouvert', 'cloture_provisoire', 'cloture_definitive', 'archive'];
        if ($id > 0 && in_array($nouveauStatut, $statutsValides)) {
            $old = $db->prepare("SELECT * FROM exercices WHERE id=?")->execute([$id]) ? $db->query("SELECT * FROM exercices WHERE id=$id")->fetch() : null;
            if ($nouveauStatut === 'cloture_definitive' || $nouveauStatut === 'archive') {
                $db->prepare("UPDATE exercices SET statut=?, date_cloture_definitive=NOW(), cloture_par=? WHERE id=?")
                   ->execute([$nouveauStatut, $_SESSION['user_id'], $id]);
            } elseif ($nouveauStatut === 'cloture_provisoire') {
                $db->prepare("UPDATE exercices SET statut=?, date_cloture_provisoire=NOW(), cloture_par=? WHERE id=?")
                   ->execute([$nouveauStatut, $_SESSION['user_id'], $id]);
            } else {
                $db->prepare("UPDATE exercices SET statut=?, date_cloture_provisoire=NULL, date_cloture_definitive=NULL WHERE id=?")
                   ->execute([$nouveauStatut, $id]);
            }
            auditLog('update', 'exercices', 'exercices', $id, $old, ['statut' => $nouveauStatut]);
            flash('success', "Statut de l'exercice mis à jour.");
        }
        header('Location: index.php'); exit;
    }
}

$exercices = getExercices();

// Status colors
$statutColors = [
    'ouvert' => 'success',
    'cloture_provisoire' => 'warning',
    'cloture_definitive' => 'danger',
    'archive' => 'gray'
];

include __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-between align-center mb-20">
    <div>
        <h2 style="margin:0"><i class="fa-solid fa-calendar-check"></i> Exercices comptables</h2>
        <p style="color:var(--text3);margin:4px 0 0">Gérez les périodes comptables et leur verrouillage</p>
    </div>
    <button class="btn btn-primary" onclick="openModal('modal-create-exercice')">
        <i class="fa-solid fa-plus"></i> Nouvel exercice
    </button>
</div>

<!-- Liste des exercices -->
<div class="card">
    <div class="card-header"><span class="card-title">Tous les exercices</span></div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Date début</th>
                    <th>Date fin</th>
                    <th>Statut</th>
                    <th>Clôture</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($exercices)): ?>
                <tr><td colspan="6" style="text-align:center;color:var(--text3);padding:40px">Aucun exercice défini.</td></tr>
                <?php else: foreach ($exercices as $ex): ?>
                <tr>
                    <td style="font-weight:700"><?= htmlspecialchars($ex['code']) ?></td>
                    <td><?= date('d/m/Y', strtotime($ex['date_debut'])) ?></td>
                    <td><?= date('d/m/Y', strtotime($ex['date_fin'])) ?></td>
                    <td><span class="badge badge-<?= $statutColors[$ex['statut']] ?? 'gray' ?>"><?= htmlspecialchars(str_replace('_', ' ', ucfirst($ex['statut']))) ?></span></td>
                    <td style="font-size:12px;color:var(--text3)">
                        <?php if ($ex['date_cloture_definitive']): ?>
                            Définitive: <?= date('d/m/Y H:i', strtotime($ex['date_cloture_definitive'])) ?>
                        <?php elseif ($ex['date_cloture_provisoire']): ?>
                            Provisoire: <?= date('d/m/Y H:i', strtotime($ex['date_cloture_provisoire'])) ?>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="btn-group btn-group-sm">
                            <?php if ($ex['statut'] === 'ouvert'): ?>
                            <button class="btn btn-warning btn-sm" onclick="changeStatus(<?= $ex['id'] ?>, 'cloture_provisoire')" title="Clôture provisoire">
                                <i class="fa-solid fa-lock"></i> Prov.
                            </button>
                            <button class="btn btn-danger btn-sm" onclick="if(confirm('Fermer définitivement cet exercice ?')) changeStatus(<?= $ex['id'] ?>, 'cloture_definitive')" title="Clôture définitive">
                                <i class="fa-solid fa-lock"></i> Définitive
                            </button>
                            <?php elseif ($ex['statut'] === 'cloture_provisoire'): ?>
                            <button class="btn btn-outline btn-sm" onclick="changeStatus(<?= $ex['id'] ?>, 'ouvert')" title="Rouvrir">
                                <i class="fa-solid fa-unlock"></i> Rouvrir
                            </button>
                            <button class="btn btn-danger btn-sm" onclick="if(confirm('Fermer définitivement ?')) changeStatus(<?= $ex['id'] ?>, 'cloture_definitive')">
                                <i class="fa-solid fa-lock"></i> Définitive
                            </button>
                            <?php elseif ($ex['statut'] === 'cloture_definitive'): ?>
                            <button class="btn btn-outline btn-sm" onclick="changeStatus(<?= $ex['id'] ?>, 'archive')" title="Archiver">
                                <i class="fa-solid fa-box-archive"></i> Archiver
                            </button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Note verrouillage -->
<div class="card mt-20">
    <div class="card-header"><span class="card-title"><i class="fa-solid fa-circle-info"></i> Comportement du verrouillage</span></div>
    <div class="card-body" style="font-size:13px;color:var(--text2);line-height:1.7">
        <p><strong>Ouvert :</strong> Toutes les saisies sont autorisées (caisse, banque, compta, engagements).</p>
        <p><strong>Clôture provisoire :</strong> Les saisies sont bloquées sur cet exercice mais les écritures d'inventaire restent possibles (amortissements, CCA, provisions).</p>
        <p><strong>Clôture définitive :</strong> Aucune écriture n'est plus possible. L'exercice est figé.</p>
        <p><strong>Archivé :</strong> Identique à la clôture définitive, masqué des sélecteurs par défaut.</p>
    </div>
</div>

<!-- Modal Create -->
<div class="modal-overlay" id="modal-create-exercice">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title">Nouvel exercice</span>
            <button class="modal-close" onclick="closeModal('modal-create-exercice')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="create">
            <div class="modal-body">
                <div class="form-group">
                    <label>Code</label>
                    <input type="text" name="code" class="form-control" required placeholder="ex: 2027" pattern="[0-9]{4}" maxlength="4">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Date début</label>
                        <input type="date" name="date_debut" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Date fin</label>
                        <input type="date" name="date_fin" class="form-control" required>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('modal-create-exercice')">Annuler</button>
                <button type="submit" class="btn btn-primary">Créer</button>
            </div>
        </form>
    </div>
</div>

<form id="form-change-status" method="POST" style="display:none">
    <input type="hidden" name="action" value="change_status">
    <input type="hidden" name="id" id="change-id">
    <input type="hidden" name="statut" id="change-statut">
</form>

<script>
function changeStatus(id, statut) {
    document.getElementById('change-id').value = id;
    document.getElementById('change-statut').value = statut;
    document.getElementById('form-change-status').submit();
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
