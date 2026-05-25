<?php // $page_title défini dans le contrôleur ?>

<div class="row g-3 justify-content-center">

<div class="col-lg-4">
  <!-- Carte profil -->
  <div class="card mb-3">
    <div class="card-body text-center py-4">
      <div class="user-avatar mx-auto mb-3" style="width:72px;height:72px;font-size:26px;">
        <?= strtoupper(substr($user['prenom'],0,1).substr($user['nom'],0,1)) ?>
      </div>
      <h5 class="fw-bold mb-1"><?= e($user['prenom'].' '.$user['nom']) ?></h5>
      <div class="small text-muted mb-2"><?= e($user['email']) ?></div>
      <?php $role_colors = [1=>'danger',2=>'primary',3=>'success',4=>'warning']; ?>
      <span class="badge bg-<?= $role_colors[$user['role_id']] ?? 'secondary' ?>">
        <?= ucfirst(e($user['role_nom'])) ?>
      </span>
    </div>
    <div class="card-body border-top py-3">
      <div class="small text-muted mb-1">Dernière connexion</div>
      <div class="small fw-semibold"><?= $user['last_login'] ? format_datetime($user['last_login']) : 'Première connexion' ?></div>
      <div class="small text-muted mt-2 mb-1">Compte créé le</div>
      <div class="small fw-semibold"><?= format_date($user['created_at']) ?></div>
    </div>
  </div>

  <!-- Activité récente -->
  <div class="card">
    <div class="card-header"><h5 class="small fw-bold text-muted text-uppercase"><i class="bi bi-clock-history me-2"></i>Mes activités récentes</h5></div>
    <div class="card-body p-0" style="max-height:280px;overflow-y:auto;">
      <?php foreach ($recent_logs as $l): ?>
      <div class="d-flex gap-2 px-3 py-2 border-bottom">
        <div class="mt-1"><i class="bi bi-activity text-primary" style="font-size:13px;"></i></div>
        <div>
          <div class="small fw-semibold"><code style="font-size:11px;"><?= e($l['action']) ?></code> — <?= e($l['module']) ?></div>
          <div class="text-muted" style="font-size:11px;"><?= format_datetime($l['created_at']) ?></div>
        </div>
      </div>
      <?php endforeach; ?>
      <?php if (empty($recent_logs)): ?>
      <p class="text-muted text-center small py-3">Aucune activité enregistrée.</p>
      <?php endif; ?>
    </div>
  </div>
</div>

<div class="col-lg-8">

  <!-- Modifier infos -->
  <div class="card mb-3">
    <div class="card-header"><h5><i class="bi bi-person-gear me-2"></i>Mes informations</h5></div>
    <div class="card-body">
      <form method="POST" action="<?= APP_URL ?>/index.php?page=profil&action=updateInfo">
        <?= csrf_field() ?>
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Prénom</label>
            <input type="text" class="form-control" name="prenom" value="<?= e($user['prenom']) ?>" required>
          </div>
          <div class="col-md-6">
            <label class="form-label">Nom</label>
            <input type="text" class="form-control" name="nom" value="<?= e($user['nom']) ?>" required>
          </div>
          <div class="col-md-6">
            <label class="form-label">Email</label>
            <input type="email" class="form-control" value="<?= e($user['email']) ?>" disabled>
            <div class="form-text">L'email ne peut pas être modifié ici.</div>
          </div>
          <div class="col-md-6">
            <label class="form-label">Téléphone</label>
            <input type="text" class="form-control" name="telephone" value="<?= e($user['telephone'] ?? '') ?>">
          </div>
          <div class="col-12 d-flex justify-content-end">
            <button type="submit" class="btn btn-primary btn-sm">
              <i class="bi bi-check me-1"></i>Enregistrer
            </button>
          </div>
        </div>
      </form>
    </div>
  </div>

  <!-- Changer mot de passe -->
  <div class="card">
    <div class="card-header"><h5><i class="bi bi-shield-lock me-2"></i>Changer le mot de passe</h5></div>
    <div class="card-body">
      <form method="POST" action="<?= APP_URL ?>/index.php?page=profil&action=updatePassword">
        <?= csrf_field() ?>
        <div class="row g-3">
          <div class="col-md-12">
            <label class="form-label">Mot de passe actuel</label>
            <input type="password" class="form-control" name="current_password" required autocomplete="current-password">
          </div>
          <div class="col-md-6">
            <label class="form-label">Nouveau mot de passe</label>
            <input type="password" class="form-control" name="new_password" required minlength="8"
                   id="newPass" autocomplete="new-password">
            <div class="form-text">Minimum 8 caractères</div>
          </div>
          <div class="col-md-6">
            <label class="form-label">Confirmer</label>
            <input type="password" class="form-control" name="confirm_password" required
                   id="confirmPass" autocomplete="new-password">
            <div id="matchFeedback" class="form-text"></div>
          </div>
          <div class="col-12 d-flex justify-content-end">
            <button type="submit" class="btn btn-warning btn-sm">
              <i class="bi bi-lock me-1"></i>Mettre à jour le mot de passe
            </button>
          </div>
        </div>
      </form>
    </div>
  </div>

</div>
</div>

<script>
// Vérification en temps réel de la correspondance des mots de passe
document.getElementById('confirmPass').addEventListener('input', function() {
  const np = document.getElementById('newPass').value;
  const fb = document.getElementById('matchFeedback');
  if (this.value === np) {
    fb.textContent = '✓ Les mots de passe correspondent.';
    fb.style.color = '#057a55';
  } else {
    fb.textContent = '✗ Les mots de passe ne correspondent pas.';
    fb.style.color = '#c81e1e';
  }
});
</script>
