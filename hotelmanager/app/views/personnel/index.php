<?php $page_title = 'Gestion du personnel'; ?>

<div class="section-header mb-3">
  <h2><i class="bi bi-person-badge me-2"></i>Personnel</h2>
  <?php if (has_permission('all')): ?>
  <a href="?page=personnel&action=create" class="btn btn-primary">
    <i class="bi bi-person-plus me-1"></i>Ajouter un compte
  </a>
  <?php endif; ?>
</div>

<div class="card">
  <div class="card-body p-0">
    <table class="table mb-0">
      <thead>
        <tr>
          <th>Nom</th><th>Email</th><th>Rôle</th><th>Téléphone</th>
          <th>Dernière connexion</th><th>Statut</th>
          <?php if (has_permission('all')): ?><th class="text-end">Actions</th><?php endif; ?>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($users as $u): ?>
        <tr>
          <td>
            <div class="d-flex align-items-center gap-2">
              <div class="user-avatar" style="width:30px;height:30px;font-size:11px;
                background:<?= $u['statut']==='actif' ? '#1a56db' : '#9ca3af' ?>;">
                <?= strtoupper(substr($u['prenom'],0,1).substr($u['nom'],0,1)) ?>
              </div>
              <div>
                <div class="fw-semibold"><?= e($u['prenom'].' '.$u['nom']) ?></div>
                <?php if ($u['id'] === (int)$_SESSION['user_id']): ?>
                <span class="badge bg-info" style="font-size:9px;">Vous</span>
                <?php endif; ?>
              </div>
            </div>
          </td>
          <td class="small"><?= e($u['email']) ?></td>
          <td>
            <?php $role_colors = [1=>'danger',2=>'primary',3=>'success',4=>'warning']; ?>
            <span class="badge bg-<?= $role_colors[$u['role_id']] ?? 'secondary' ?>">
              <?= e($u['role_nom']) ?>
            </span>
          </td>
          <td class="small"><?= e($u['telephone'] ?? '—') ?></td>
          <td class="small text-muted"><?= $u['last_login'] ? format_datetime($u['last_login']) : 'Jamais' ?></td>
          <td>
            <?php if ($u['statut'] === 'actif'): ?>
              <span class="badge bg-success">Actif</span>
            <?php elseif ($u['statut'] === 'suspendu'): ?>
              <span class="badge bg-warning text-dark">Suspendu</span>
            <?php else: ?>
              <span class="badge bg-secondary">Inactif</span>
            <?php endif; ?>
          </td>
          <?php if (has_permission('all')): ?>
          <td class="text-end">
            <?php if ($u['id'] !== (int)$_SESSION['user_id']): ?>
            <form method="POST" action="?page=personnel&action=toggleStatut" class="d-inline">
              <?= csrf_field() ?>
              <input type="hidden" name="id" value="<?= $u['id'] ?>">
              <button type="submit" class="btn btn-xs btn-outline-<?= $u['statut']==='actif' ? 'warning' : 'success' ?>"
                      data-confirm="<?= $u['statut']==='actif' ? 'Suspendre ce compte ?' : 'Réactiver ce compte ?' ?>">
                <i class="bi bi-<?= $u['statut']==='actif' ? 'pause-circle' : 'play-circle' ?>"></i>
                <?= $u['statut']==='actif' ? 'Suspendre' : 'Réactiver' ?>
              </button>
            </form>
            <?php endif; ?>
          </td>
          <?php endif; ?>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Rôles et permissions -->
<div class="card mt-4">
  <div class="card-header"><h5><i class="bi bi-shield-check me-2"></i>Rôles et permissions</h5></div>
  <div class="card-body">
    <div class="row g-3">
      <?php foreach ($roles as $r): ?>
      <?php $perms = json_decode($r['permissions'],true); ?>
      <div class="col-md-3">
        <div class="border rounded p-3">
          <div class="fw-bold mb-2"><?= ucfirst(e($r['nom'])) ?></div>
          <ul class="list-unstyled mb-0">
            <?php foreach ($perms as $p): ?>
            <li class="small text-muted">
              <i class="bi bi-check2 text-success me-1"></i>
              <?= $p === 'all' ? '<strong class="text-danger">Accès total</strong>' : e($p) ?>
            </li>
            <?php endforeach; ?>
          </ul>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>
