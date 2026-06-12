<?php
$currentPage = 'mar';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$flash = null;

// --- POST : Enregistrer une administration ---
if (can('mar.administer') && $_SERVER['REQUEST_METHOD'] === 'POST' && post_str('action') === 'administer') {
    csrf_verify();
    $admin_id  = post_int('admin_id');
    $statut    = in_whitelist(post_str('statut'), ['administre','non_administre','refuse','reporte'], 'administre');
    $motif     = post_str('motif');
    $notes     = post_str('notes');

    if ($admin_id > 0) {
        $sql = "UPDATE administration_medicaments SET statut=?, heure_reelle=NOW(), motif_non_administration=?, notes=? WHERE id=?";
        if ($statut === 'administre') $sql = "UPDATE administration_medicaments SET statut=?, heure_reelle=NOW(), motif_non_administration=NULL, notes=? WHERE id=?";
        db_exec($sql, [$statut, $statut !== 'administre' ? $motif : null, $notes, $admin_id]);

        $mar = db_row("SELECT * FROM administration_medicaments WHERE id=?", [$admin_id]);
        logActivity("MAR: ".$statut." - patient #".($mar['patient_id']??0), $statut==='administre'?'green':'yellow', 'mar');
        $flash = ['green', 'Administration enregistrée.'];
    }
}

// --- POST : Planifier les administrations du jour a partir des ordonnances actives ---
if (can('mar.administer') && $_SERVER['REQUEST_METHOD'] === 'POST' && post_str('action') === 'generer_plan') {
    csrf_verify();
    $date_admin = post_str('date_plan');
    if (empty($date_admin)) $date_admin = date('Y-m-d');

    // Recuperer toutes les ordonnances actives
    $ordosActives = db_select("SELECT ol.*, o.patient_id, o.medecin_id, m.nom AS med_nom, m.dosage AS med_dosage
        FROM ordonnance_lignes ol
        JOIN ordonnances o ON o.id=ol.ordonnance_id
        JOIN medicaments m ON m.id=ol.medicament_id
        WHERE o.statut='active'");

    $nbGenere = 0;
    foreach ($ordosActives as $ol) {
        // Verifier si deja planifie pour aujourd'hui
        $exist = (int)db_scalar("SELECT COUNT(*) FROM administration_medicaments WHERE ordonnance_ligne_id=? AND date_administration=?", [$ol['id'], $date_admin]);
        if ($exist == 0) {
            // Deduire les horaires en fonction de la frequence
            $freq = strtolower($ol['frequence'] ?? '');
            $heures = [];
            if (strpos($freq,'x/jour')!==false || strpos($freq,'fois/jour')!==false) {
                preg_match('/(\d+)/', $freq, $m); $nb = (int)($m[1]??3);
                for ($i=0;$i<$nb;$i++) $heures[] = sprintf('%02d:00:00', 8+(int)(12/($nb>1?$nb-1:1))*$i);
            } elseif (strpos($freq,'matin')!==false) $heures=['08:00:00'];
            elseif (strpos($freq,'soir')!==false) $heures=['20:00:00'];
            elseif (strpos($freq,'midi')!==false) $heures=['12:00:00'];
            elseif (strpos($freq,'heures')!==false || strpos($freq,'toutes les')!==false) {
                preg_match('/(\d+)/', $freq, $m); $ecart = (int)($m[1]??8);
                for ($h=8;$h<24;$h+=$ecart) $heures[] = sprintf('%02d:00:00', $h);
            } else $heures = ['08:00:00','12:00:00','20:00:00']; // defaut 3x/jour

            foreach ($heures as $h) {
                db_exec("INSERT INTO administration_medicaments (ordonnance_ligne_id, medicament_id, patient_id, utilisateur_id, date_administration, heure_prevue, dosage_admin) VALUES (?,?,?,?,?,?,?)",
                    [$ol['id'], $ol['medicament_id'], $ol['patient_id'], currentUser()['id'], $date_admin, $h, $ol['dosage']]);
                $nbGenere++;
            }
        }
    }
    $flash = ['green', "Plan generé : $nbGenere administration(s) pour le ".fmt_date($date_admin)];
}

require_once __DIR__ . '/../includes/layout.php';
requirePageAccess('mar');

// --- Donnees ---
$date_mar   = get_str('date_mar') ?: date('Y-m-d');
$patient_id = get_int('patient_id');
$filtre_statut = in_whitelist(get_str('filtre_statut'), ['','planifie','administre','non_administre','refuse','reporte'], '');
$search     = get_str('search');

// --- Liste des administrations planifiees ---
$where  = "a.date_administration=?";
$params = [$date_mar];
if ($patient_id > 0) { $where .= " AND a.patient_id=?"; $params[] = $patient_id; }
if ($filtre_statut) { $where .= " AND a.statut=?"; $params[] = $filtre_statut; }

$administrations = db_select("SELECT a.*, m.nom AS med_nom, CONCAT(p.prenom,' ',p.nom) AS patient_nom, p.numero AS patient_num,
    CONCAT(u.prenom,' ',u.nom) AS inf_nom
    FROM administration_medicaments a
    JOIN patients p ON p.id=a.patient_id
    JOIN medicaments m ON m.id=a.medicament_id
    LEFT JOIN utilisateurs u ON u.id=a.utilisateur_id
    WHERE $where ORDER BY a.heure_prevue ASC, a.statut ASC LIMIT 200", $params);

// --- Stats du jour ---
$nbAdmin    = (int)db_scalar("SELECT COUNT(*) FROM administration_medicaments WHERE date_administration=?", [$date_mar]);
$nbFait     = (int)db_scalar("SELECT COUNT(*) FROM administration_medicaments WHERE date_administration=? AND statut='administre'", [$date_mar]);
$nbRestant  = (int)db_scalar("SELECT COUNT(*) FROM administration_medicaments WHERE date_administration=? AND statut='planifie'", [$date_mar]);
$nbNonAdmin = (int)db_scalar("SELECT COUNT(*) FROM administration_medicaments WHERE date_administration=? AND statut IN ('non_administre','refuse')", [$date_mar]);
$tauxAdmin  = $nbAdmin > 0 ? round($nbFait/$nbAdmin*100) : 0;

// --- Patients avec administrations du jour (pour selecteur) ---
$patientsMar = db_select("SELECT DISTINCT a.patient_id, p.nom, p.prenom, p.numero
    FROM administration_medicaments a
    JOIN patients p ON p.id=a.patient_id
    WHERE a.date_administration=? ORDER BY p.nom", [$date_mar]);

// --- Stats par patient ---
$statsPatients = [];
foreach ($patientsMar as $pm) {
    $statsPatients[$pm['patient_id']] = [
        'total' => (int)db_scalar("SELECT COUNT(*) FROM administration_medicaments WHERE patient_id=? AND date_administration=?", [$pm['patient_id'],$date_mar]),
        'fait'  => (int)db_scalar("SELECT COUNT(*) FROM administration_medicaments WHERE patient_id=? AND date_administration=? AND statut='administre'", [$pm['patient_id'],$date_mar]),
    ];
}
?>

<?php if (!empty($flash)): ?>
<div class="alert alert-<?= $flash[0]==='green'?'green':'red' ?> alert-auto"><?= h($flash[1]) ?></div>
<?php endif; ?>

<!-- Stats header -->
<div class="stats-grid" style="margin-bottom:16px">
  <div class="stat-card">
    <div class="stat-icon" style="background:rgba(var(--accent-rgb),.15)">💉</div>
    <div class="stat-info"><div class="stat-value"><?= $nbAdmin ?></div><div class="stat-label">Total planifié</div></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:rgba(34,197,94,0.15)">✅</div>
    <div class="stat-info"><div class="stat-value" style="color:var(--green)"><?= $nbFait ?></div><div class="stat-label">Administré</div></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:rgba(251,191,36,0.15)">⏳</div>
    <div class="stat-info"><div class="stat-value" style="color:var(--yellow)"><?= $nbRestant ?></div><div class="stat-label">En attente</div></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:rgba(var(--red-rgb),.15)">⚠️</div>
    <div class="stat-info"><div class="stat-value" style="color:var(--red)"><?= $nbNonAdmin ?></div><div class="stat-label">Non administré</div></div>
  </div>
</div>

<!-- Progression -->
<?php if ($nbAdmin > 0): ?>
<div class="card" style="margin-bottom:16px">
  <div style="padding:16px">
    <div style="display:flex;justify-content:space-between;margin-bottom:8px">
      <span>Progression du jour</span><span><strong><?= $tauxAdmin ?>%</strong></span>
    </div>
    <div class="progress-bar"><div class="progress-fill" style="width:<?= $tauxAdmin ?>%;background:var(--green)"></div></div>
  </div>
</div>
<?php endif; ?>

<!-- Controles -->
<div class="card" style="margin-bottom:16px">
  <div style="padding:16px;display:flex;gap:12px;align-items:center;flex-wrap:wrap;border-bottom:1px solid var(--border)">
    <form method="GET" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
      <label>Date :</label>
      <input type="date" name="date_mar" value="<?= h($date_mar) ?>" onchange="this.form.submit()" style="max-width:160px">
      <select name="patient_id" onchange="this.form.submit()">
        <option value="">Tous les patients</option>
        <?php foreach ($patientsMar as $pm): ?>
        <option value="<?= (int)$pm['patient_id'] ?>" <?= $patient_id==$pm['patient_id']?'selected':'' ?>><?= h($pm['prenom'].' '.$pm['nom']) ?></option>
        <?php endforeach; ?>
      </select>
      <select name="filtre_statut" onchange="this.form.submit()">
        <option value="">Tous les statuts</option>
        <option value="planifie" <?= $filtre_statut==='planifie'?'selected':'' ?>>Planifié</option>
        <option value="administre" <?= $filtre_statut==='administre'?'selected':'' ?>>Administré</option>
        <option value="non_administre" <?= $filtre_statut==='non_administre'?'selected':'' ?>>Non administré</option>
        <option value="refuse" <?= $filtre_statut==='refuse'?'selected':'' ?>>Refusé</option>
        <option value="reporte" <?= $filtre_statut==='reporte'?'selected':'' ?>>Reporté</option>
      </select>
    </form>
    <?php if (can('mar.administer')): ?>
    <form method="POST" style="margin-left:auto;display:flex;gap:8px">
      <input type="hidden" name="action" value="generer_plan">
      <input type="hidden" name="date_plan" value="<?= h($date_mar) ?>">
      <?= csrf_field() ?>
      <button type="submit" class="btn btn-sm btn-blue">🔄 Genérer le plan du jour</button>
    </form>
    <?php endif; ?>
  </div>

  <!-- Tableau des administrations -->
  <table>
    <thead><tr><th>Heure</th><th>Patient</th><th>Medicament</th><th>Dosage</th><th>Statut</th><th>Heure réelle</th><th>Par</th><th></th></tr></thead>
    <tbody>
    <?php
    $patientPrec = null;
    foreach ($administrations as $a):
        $stBadge = ['planifie'=>'badge-yellow','administre'=>'badge-green','non_administre'=>'badge-red','refuse'=>'badge-red','reporte'=>'badge-blue'];
        $stLabel = ['planifie'=>'Planifié','administre'=>'Admin.','non_administre'=>'Non donné','refuse'=>'Refusé','reporte'=>'Reporté'];
    ?>
      <tr style="<?= ($patientPrec!==null&&$patientPrec!==$a['patient_id'])?'border-top:2px solid var(--border2)':'' ?>">
        <td><strong><?= h(substr($a['heure_prevue'],0,5)) ?></strong></td>
        <td>
          <a href="observations.php?patient_id=<?= (int)$a['patient_id'] ?>" style="color:var(--text)"><?= h($a['patient_nom']) ?></a>
          <div style="font-size:10px;color:var(--text3)"><?= h($a['patient_num']) ?></div>
        </td>
        <td><?= h($a['med_nom']) ?></td>
        <td><?= h($a['dosage_admin']) ?></td>
        <td><span class="badge <?= $stBadge[$a['statut']] ?>"><?= $stLabel[$a['statut']] ?></span></td>
        <td style="font-size:12px"><?= $a['heure_reelle'] ? substr($a['heure_reelle'],0,5) : '-' ?></td>
        <td style="font-size:12px"><?= h($a['inf_nom'] ?? '-') ?></td>
        <td>
          <?php if ($a['statut'] === 'planifie' && can('mar.administer')): ?>
          <div style="display:flex;gap:4px">
            <form method="POST" style="display:inline"><input type="hidden" name="action" value="administer"><input type="hidden" name="admin_id" value="<?= (int)$a['id'] ?>"><input type="hidden" name="statut" value="administre"><?= csrf_field() ?><button type="submit" class="btn btn-sm btn-green" title="Administré">✅</button></form>
            <button class="btn btn-sm btn-red" title="Non administré" onclick="openNonAdmin(<?= (int)$a['id'] ?>, '<?= h($a['med_nom']) ?>')">❌</button>
          </div>
          <?php endif; ?>
        </td>
      </tr>
      <?php if ($a['motif_non_administration']): ?>
      <tr><td colspan="8" style="font-size:11px;color:var(--red);padding:2px 12px">Motif : <?= h($a['motif_non_administration']) ?></td></tr>
      <?php endif; ?>
    <?php $patientPrec = $a['patient_id']; endforeach; ?>
    <?php if (empty($administrations)): ?>
      <tr><td colspan="8" style="text-align:center;padding:32px;color:var(--text3)">
        Aucune administration planifiée pour cette date.<br>
        <?php if (can('mar.administer')): ?>Cliquez sur "Générer le plan du jour" pour créer le planning a partir des ordonnances actives.<?php endif; ?>
      </td></tr>
    <?php endif; ?>
    </tbody>
  </table>
</div>

<!-- Modal : Non administration -->
<?php if (can('mar.administer')): ?>
<div id="modal-non-admin" class="modal-overlay" role="dialog" aria-modal="true" style="display:none" onclick="if(event.target===this)this.style.display='none'">
  <div style="background:var(--surface);border:1px solid var(--border2);border-radius:16px;width:min(480px,95vw);box-shadow:0 24px 60px rgba(0,0,0,.7)">
    <div style="padding:18px 24px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center">
      <h3>Medicament non administré</h3>
      <button type="button" class="modal-close" onclick="document.getElementById('modal-non-admin').style.display='none'" aria-label="Fermer">✕</button>
    </div>
    <form method="POST" style="padding:24px">
      <input type="hidden" name="action" value="administer">
      <input type="hidden" name="admin_id" id="non-admin-id">
      <input type="hidden" name="statut" value="non_administre">
      <?= csrf_field() ?>
      <div id="non-admin-name" style="font-weight:600;margin-bottom:12px;color:var(--text2)"></div>
      <div class="form-group"><label>Motif de non-administration *</label>
        <select name="motif" required>
          <option value="">-- Sélectionner --</option>
          <option value="Patient absent">Patient absent</option>
          <option value="Patient a jeun">Patient a jeun (pre-op)</option>
          <option value="Refus du patient">Refus du patient</option>
          <option value="Voie orale impossible">Voie orale impossible</option>
          <option value="Contre-indication">Contre-indication médicale</option>
          <option value="Allergie suspectee">Allergie suspectee</option>
          <option value="Rupture de stock">Rupture de stock</option>
          <option value="Erreur de prescription">Erreur de prescription</option>
          <option value="Autre">Autre (preciser en notes)</option>
        </select>
      </div>
      <div class="form-group"><label>Notes</label><textarea name="notes" rows="2" placeholder="Precisions..."></textarea></div>
      <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:20px;padding-top:16px;border-top:1px solid var(--border)">
        <button type="button" class="btn btn-ghost" onclick="document.getElementById('modal-non-admin').style.display='none'">Annuler</button>
        <button type="submit" class="btn btn-red">Confirmer non-administration</button>
      </div>
    </form>
  </div>
</div>
<script>
function openNonAdmin(id, medName){
  document.getElementById('non-admin-id').value=id;
  document.getElementById('non-admin-name').textContent='Medicament : '+medName;
  document.getElementById('modal-non-admin').style.display='flex';
}
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
