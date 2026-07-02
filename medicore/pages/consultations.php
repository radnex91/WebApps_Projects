<?php
$currentPage = 'consultations';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/accueil.php';
requireLogin();

$flash = null;
$me = (int) currentUser()['id'];

// --- POST : Réorienter vers un confrère ---
if (can('consultations.reorienter') && $_SERVER['REQUEST_METHOD'] === 'POST' && post_str('action') === 'reorienter') {
    csrf_verify();
    $arrivee_id    = post_int('arrivee_id');
    $new_medecin_id = post_int('new_medecin_id');
    if ($arrivee_id <= 0 || $new_medecin_id <= 0) {
        $flash = ['red', 'Arrivée et médecin cible requis.'];
    } else {
        // IDOR : vérifier que l'arrivée appartient bien au médecin connecté.
        $owner = (int) db_scalar("SELECT medecin_id FROM arrivees_patients WHERE id = ? AND statut = 'en_consultation'", [$arrivee_id]);
        if ($owner !== $me) {
            $flash = ['red', 'Arrivée introuvable ou non attribuée à vous.'];
        } else {
            // Cible : médecin actif, confrère (pas soi-même).
            $okCible = (int) db_scalar("SELECT COUNT(*) FROM utilisateurs WHERE id = ? AND role = 'medecin' AND statut = 'actif' AND id <> ?", [$new_medecin_id, $me]);
            if ($okCible === 0) {
                $flash = ['red', 'Médecin cible invalide.'];
            } elseif (reorienter_vers($arrivee_id, $new_medecin_id)) {
                $nomCible = db_scalar("SELECT CONCAT(prenom,' ',nom) FROM utilisateurs WHERE id=?", [$new_medecin_id]);
                logActivity('Consultation : patient réorienté vers Dr ' . ($nomCible ?: '#' . $new_medecin_id) . ' (arrivée ' . $arrivee_id . ')', 'purple', 'rendez_vous', $arrivee_id);
                $flash = ['green', 'Patient réorienté vers Dr ' . ($nomCible ?: 'confrère') . '.'];
            } else {
                $flash = ['red', 'Échec de la réorientation.'];
            }
        }
    }
}

// --- POST : Terminer la consultation ---
if (can('consultations.terminer') && $_SERVER['REQUEST_METHOD'] === 'POST' && post_str('action') === 'terminer') {
    csrf_verify();
    $arrivee_id = post_int('arrivee_id');
    if ($arrivee_id <= 0) {
        $flash = ['red', 'Arrivée requise.'];
    } else {
        // IDOR : vérifier que l'arrivée appartient bien au médecin connecté.
        $owner = (int) db_scalar("SELECT medecin_id FROM arrivees_patients WHERE id = ? AND statut = 'en_consultation'", [$arrivee_id]);
        if ($owner !== $me) {
            $flash = ['red', 'Arrivée introuvable ou non attribuée à vous.'];
        } elseif (terminer_arrivee($arrivee_id)) {
            logActivity('Consultation terminée (arrivée ' . $arrivee_id . ')', 'green', 'rendez_vous', $arrivee_id);
            $flash = ['green', 'Consultation terminée.'];
        } else {
            $flash = ['red', 'Échec de la clôture.'];
        }
    }
}

require_once __DIR__ . '/../includes/layout.php';
requirePageAccess('consultations');

// --- Chargement ---
$consultations = get_mes_consultations($me);
$confreres = db_select("SELECT id, CONCAT(prenom,' ',nom) AS nom_complet, specialite FROM utilisateurs WHERE role='medecin' AND statut='actif' AND id<>? ORDER BY nom", [$me]);

// Helper local : libellé courte d'un type de constante.
$constLabels = [
    'temperature' => 'T', 'ta_systolique' => 'TAS', 'ta_diastolique' => 'TAD',
    'pouls' => 'Pouls', 'spo2' => 'SpO₂', 'glycemie' => 'Glyc', 'poids' => 'Poids', 'bmi' => 'BMI',
];
?>
<?php if (!empty($flash)): ?>
<div class="alert alert-<?= $flash[0]==='green'?'green':'red' ?> alert-auto"><?= h($flash[1]) ?></div>
<?php endif; ?>

<div class="page-header" style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:16px">
  <div>
    <h1 style="margin:0">Mes consultations du jour</h1>
    <div style="font-size:12px;color:var(--text2)"><?= date('d/m/Y') ?> · <?= count($consultations) ?> patient(s) en cours</div>
  </div>
</div>

<div class="card">
  <div class="card-header"><h3>Patients orientés vers moi</h3><span style="font-size:12px;color:var(--text2)"><?= count($consultations) ?> en cours</span></div>
  <table class="tbl-actions">
    <thead><tr><th>Patient</th><th>Arrivée / Prise en charge</th><th>Dernières constantes</th><th class="col-actions">Actions</th></tr></thead>
    <tbody>
    <?php foreach ($consultations as $a):
        $age = !empty($a['date_naissance']) ? (int)((time()-strtotime($a['date_naissance']))/31536000) : '?';
        $dossierUrl = secure_url('dossiers.php', (int)$a['patient_id'], 'dossier', ['patient_id' => (int)$a['patient_id']]);
        $prescUrl = 'pharmacie.php?tab=ordonnances&patient_id=' . (int)$a['patient_id'] . '&medecin_id=' . $me . '&new_ord=1';
        $c = get_dernieres_constantes((int)$a['patient_id']);
    ?>
      <tr>
        <td>
          <a href="<?= h($dossierUrl) ?>" style="text-decoration:none"><strong><?= h($a['prenom'].' '.$a['nom']) ?></strong></a>
          <div style="font-size:11px;color:var(--text3)"><?= h($a['numero']) ?> · <?= $age ?> ans · <?= h($a['sexe']) ?></div>
          <?php if (!empty($a['motif'])): ?><div style="font-size:11px;color:var(--text3);margin-top:2px">Motif : <?= h($a['motif']) ?></div><?php endif; ?>
        </td>
        <td>
          <div style="font-size:12px">Arrivée : <?= date('H:i', strtotime($a['date_arrivee'])) ?></div>
          <div style="font-size:12px;color:var(--text2)">PEC : <?= !empty($a['date_prise_en_charge']) ? date('H:i', strtotime($a['date_prise_en_charge'])) : '—' ?></div>
        </td>
        <td style="font-size:12px;line-height:1.6">
          <?php
          $parts = [];
          foreach (['temperature','ta_systolique','ta_diastolique','pouls','spo2','poids'] as $t) {
              if (!empty($c[$t])) {
                  $seuil = isset(ACCUEIL_SEUILS[$t]) ? ACCUEIL_SEUILS[$t] : null;
                  $oos = $seuil && ($c[$t]['v'] < $seuil['min'] || $c[$t]['v'] > $seuil['max']);
                  $val = h(($constLabels[$t] ?? $t) . ' ' . $c[$t]['v'] . $c[$t]['u']);
                  $parts[] = $oos ? '<span style="color:var(--accent2);font-weight:600">⚠ '.$val.'</span>' : $val;
              }
          }
          echo $parts ? implode(' · ', $parts) : '<span style="color:var(--text3)">Aucune constante</span>';
          ?>
        </td>
        <td><div class="row-actions"><span class="row-hint" aria-hidden="true">⋯</span>
          <?php if (can('consultations.prescrire')): ?>
          <a class="btn btn-sm btn-ghost" href="<?= h($prescUrl) ?>">💊 Prescrire</a>
          <?php endif; ?>
          <?php if (can('consultations.reorienter') && !empty($confreres)): ?>
          <button class="btn btn-sm btn-ghost" data-reorienter="<?= (int)$a['id'] ?>" data-nom="<?= h($a['prenom'].' '.$a['nom']) ?>">↺ Réorienter</button>
          <?php endif; ?>
          <?php if (can('consultations.terminer')): ?>
          <form method="POST" style="display:inline" onsubmit="return confirm('Terminer la consultation de ce patient ?')">
            <input type="hidden" name="action" value="terminer">
            <input type="hidden" name="arrivee_id" value="<?= (int)$a['id'] ?>">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-sm btn-green" style="font-size:11px">✓</button>
          </form>
          <?php endif; ?>
        </div></td>
      </tr>
    <?php endforeach; ?>
    <?php if (empty($consultations)): ?>
      <tr><td colspan="4" style="text-align:center;padding:32px;color:var(--text3)">Aucun patient orienté vers vous aujourd'hui.</td></tr>
    <?php endif; ?>
    </tbody>
  </table>
</div>

<?php if (can('consultations.reorienter') && !empty($confreres)): ?>
<div id="modal-reorienter" class="modal-overlay" role="dialog" aria-modal="true" style="display:none;z-index:200;align-items:center;justify-content:center;padding:20px" onclick="if(event.target===this)this.style.display='none'">
  <div style="background:var(--surface);border:1px solid var(--border2);border-radius:16px;width:min(460px,95vw);box-shadow:0 24px 60px rgba(0,0,0,.7)">
    <div style="padding:16px 20px;border-bottom:1px solid var(--border2);display:flex;align-items:center;justify-content:space-between">
      <h3 style="margin:0;font-size:16px">Réorienter le patient</h3>
      <button type="button" class="modal-close" onclick="document.getElementById('modal-reorienter').style.display='none'" aria-label="Fermer" style="font-size:18px;color:var(--text2)">✕</button>
    </div>
    <form method="POST" style="padding:20px">
      <input type="hidden" name="action" value="reorienter"><?= csrf_field() ?>
      <input type="hidden" name="arrivee_id" id="or-arrivee_id" value="0">
      <p style="margin:0 0 12px;font-size:13px;color:var(--text2)">Patient : <strong id="or-nom" style="color:var(--text)">—</strong></p>
      <label for="or-medecin" style="font-size:12px;color:var(--text2)">Médecin cible *</label>
      <select name="new_medecin_id" id="or-medecin" required style="padding:9px 12px;background:var(--bg);border:1px solid var(--border2);border-radius:7px;color:var(--text);font-family:inherit;font-size:13px;outline:none;width:100%;margin-top:6px">
        <?php foreach ($confreres as $m): ?>
          <option value="<?= (int)$m['id'] ?>">Dr. <?= h($m['nom_complet']) ?><?= !empty($m['specialite']) ? ' — ' . h($m['specialite']) : '' ?></option>
        <?php endforeach; ?>
      </select>
      <div style="margin-top:18px;display:flex;gap:8px;justify-content:flex-end">
        <button type="button" class="btn btn-ghost" onclick="document.getElementById('modal-reorienter').style.display='none'">Annuler</button>
        <button type="submit" class="btn btn-blue">Réorienter</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<script>
document.querySelectorAll('[data-reorienter]').forEach(function(btn){
  btn.addEventListener('click', function(){
    var m = document.getElementById('modal-reorienter');
    if(!m) return;
    document.getElementById('or-arrivee_id').value = btn.getAttribute('data-reorienter');
    document.getElementById('or-nom').textContent = btn.getAttribute('data-nom');
    m.style.display='flex';
  });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php';