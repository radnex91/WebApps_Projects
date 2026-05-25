<?php
$currentPage = 'dossiers';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
require_once __DIR__ . '/../includes/layout.php';
requirePageAccess('dossiers');

$search     = get_str('search');
$patient_id = get_int('patient_id');

// Chargement dossier spcifique
$patient = null;
$hosps = $ordos = $analyses = $factures = [];

if ($patient_id > 0) {
    $patient  = db_row("SELECT * FROM patients WHERE id=?",[$patient_id]);
    if ($patient) {
        db_exec("INSERT INTO audit_acces (utilisateur_id, patient_id, type_acces, entite, entite_id, adresse_ip) VALUES (?,?,?,?,?,?)",
            [currentUser()['id'], $patient_id, 'consultation', 'dossier_medical', $patient_id, $_SERVER['REMOTE_ADDR']??'']);
        $hosps    = db_select("SELECT h.*,d.nom AS dept_nom,l.numero AS lit_num,CONCAT(u.prenom,' ',u.nom) AS medecin FROM hospitalisations h JOIN departements d ON d.id=h.departement_id JOIN lits l ON l.id=h.lit_id JOIN utilisateurs u ON u.id=h.medecin_id WHERE h.patient_id=? ORDER BY h.date_admission DESC",[$patient_id]);
        $ordos    = db_select("SELECT o.*,CONCAT(u.prenom,' ',u.nom) AS medecin FROM ordonnances o JOIN utilisateurs u ON u.id=o.medecin_id WHERE o.patient_id=? ORDER BY o.date_prescription DESC",[$patient_id]);
        foreach ($ordos as &$ord) { $ord['lignes']=db_select("SELECT ol.*,m.nom AS med_nom,m.dosage FROM ordonnance_lignes ol JOIN medicaments m ON m.id=ol.medicament_id WHERE ol.ordonnance_id=?",[$ord['id']]); }
        unset($ord);
        $analyses = db_select("SELECT a.*,CONCAT(u.prenom,' ',u.nom) AS prescripteur FROM analyses a JOIN utilisateurs u ON u.id=a.prescripteur_id WHERE a.patient_id=? ORDER BY a.date_creation DESC",[$patient_id]);
        $factures = db_select("SELECT * FROM factures WHERE patient_id=? ORDER BY date_emission DESC",[$patient_id]);
    }
}

// Recherche patients
$patients = [];
if ($search) {
    $like = "%$search%";
    $patients = db_select("SELECT id,numero,nom,prenom,date_naissance,sexe FROM patients WHERE nom LIKE ? OR prenom LIKE ? OR numero LIKE ? OR telephone LIKE ? ORDER BY nom LIMIT 20",[$like,$like,$like,$like]);
}

$statutBadgeH = ['en_cours'=>'badge-yellow','sorti'=>'badge-green','transfere'=>'badge-blue'];
$statutBadgeO = ['active'=>'badge-green','terminee'=>'badge-blue','annulee'=>'badge-red'];
$statutBadgeA = ['prescrit'=>'badge-blue','en_cours'=>'badge-yellow','disponible'=>'badge-green','archive'=>'badge-gray'];
$statutBadgeF = ['en_attente'=>'badge-yellow','reglee'=>'badge-green','impayee'=>'badge-red','annulee'=>'badge-gray'];
?>

<div class="page-header-row">
  <div><h2> Dossiers médicaux</h2><p>Accès sécurisé aux dossiers patients</p></div>
</div>

<!-- Recherche -->
<form method="GET" style="display:flex;gap:8px;margin-bottom:24px">
  <input type="text" name="search" value="<?= h($search) ?>" autofocus
    placeholder=" Rechercher patient — nom, prénom, numéro de dossier, téléphone..."
    style="flex:1;padding:12px 16px;background:var(--surface);border:1px solid var(--border2);border-radius:10px;color:var(--text);font-family:inherit;font-size:14px;outline:none">
  <button type="submit" class="btn btn-blue" style="padding:12px 20px">Rechercher</button>
  <?php if ($search||$patient_id): ?><a href="dossiers.php" class="btn btn-ghost"> Effacer</a><?php endif; ?>
</form>

<?php if ($search && !empty($patients)): ?>
<!-- Résultats de recherche -->
<div class="card mb-24">
  <div class="card-header"><h3>Résultats  "<?= h($search) ?>"</h3><span style="font-size:12px;color:var(--text2)"><?= count($patients) ?> patient(s)</span></div>
  <table>
    <thead><tr><th>Dossier</th><th>Nom</th><th>Prénom</th><th>Âge / Sexe</th><th>Action</th></tr></thead>
    <tbody>
    <?php foreach ($patients as $p):
      $age = date_diff(date_create($p['date_naissance']),date_create())->y;
    ?>
    <tr>
      <td><strong><?= h($p['numero']) ?></strong></td>
      <td><?= h($p['nom']) ?></td>
      <td><?= h($p['prenom']) ?></td>
      <td><?= $age ?> ans  <?= $p['sexe']==='M'?'H':'F' ?></td>
      <td><a href="dossiers.php?patient_id=<?= (int)$p['id'] ?>" class="btn btn-sm btn-blue"> Ouvrir le dossier</a></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php elseif ($search): ?>
<div class="alert alert-yellow mb-24"> Aucun patient trouvé pour "<?= h($search) ?>".</div>
<?php endif; ?>

<?php if ($patient): ?>
<!--  BOUTON RETOUR  -->
<div style="margin-bottom:16px">
  <a href="patients.php" class="btn btn-ghost btn-sm" style="display:inline-flex;align-items:center;gap:6px">
     Retour à la liste des patients
  </a>
</div>

<!--  DOSSIER PATIENT  -->
<?php $age = date_diff(date_create($patient['date_naissance']),date_create())->y; ?>
<div class="card mb-24" style="border-color:var(--accent)">
  <div style="padding:20px 24px;display:flex;gap:16px;align-items:flex-start;flex-wrap:wrap">
    <div class="patient-avatar" style="width:60px;height:60px;font-size:20px">
      <?= strtoupper(mb_substr($patient['prenom'],0,1).mb_substr($patient['nom'],0,1)) ?>
    </div>
    <div style="flex:1">
      <div style="font-size:20px;font-weight:700;margin-bottom:4px"><?= h($patient['prenom'].' '.$patient['nom']) ?></div>
      <div style="color:var(--text2);font-size:13px;margin-bottom:8px">
         <?= h($patient['numero']) ?>   <?= $age ?> ans  <?= $patient['sexe']==='M'?'Masculin':'Féminin' ?>
          N(e) le <?= fmt_date($patient['date_naissance']) ?>
      </div>
      <div style="display:flex;gap:8px;flex-wrap:wrap">
        <?php if ($patient['groupe_sanguin']): ?><span class="badge badge-red">Groupe <?= h($patient['groupe_sanguin']) ?></span><?php endif; ?>
        <?php if ($patient['allergies']): ?><span class="badge badge-yellow"> <?= h($patient['allergies']) ?></span><?php endif; ?>
        <span class="badge badge-gray"><?= h($patient['assurance']??'') ?></span>
      </div>
    </div>
    <div style="font-size:12px;color:var(--text2);text-align:right">
      <?php if ($patient['telephone']): ?><div> <?= h($patient['telephone']) ?></div><?php endif; ?>
      <?php if ($patient['contact_urgence_nom']): ?><div style="margin-top:4px"> <?= h($patient['contact_urgence_nom']) ?>  <?= h($patient['contact_urgence_tel']??'') ?></div><?php endif; ?>
    </div>
  </div>
  <?php if ($patient['antecedents']): ?>
  <div style="padding:14px 24px;border-top:1px solid var(--border);background:rgba(239,68,68,.04)">
    <span style="font-size:11px;font-weight:600;color:var(--text3);text-transform:uppercase;letter-spacing:.05em">Antécédents médicaux</span>
    <p style="margin-top:6px;font-size:13px;color:var(--text2)"><?= h($patient['antecedents']) ?></p>
  </div>
  <?php endif; ?>
</div>

<!-- Hospitalisations -->
<div class="card mb-16">
  <div class="card-header"><h3> Hospitalisations (<?= count($hosps) ?>)</h3></div>
  <?php if ($hosps): ?>
  <table>
    <thead><tr><th>Département</th><th>Lit</th><th>Médecin</th><th>Admission</th><th>Sortie</th><th>Priorité</th><th>Statut</th><th>Motif</th></tr></thead>
    <tbody>
    <?php foreach ($hosps as $h): ?>
    <tr>
      <td><?= h($h['dept_nom']) ?></td><td><?= h($h['lit_num']) ?></td>
      <td><?= h($h['medecin']) ?></td>
      <td style="font-size:12px"><?= fmt_date($h['date_admission'],true) ?></td>
      <td style="font-size:12px"><?= $h['date_sortie']?fmt_date($h['date_sortie'],true):'En cours' ?></td>
      <td><span class="badge <?= $h['priorite']==='critique'?'badge-red':($h['priorite']==='urgent'?'badge-yellow':'badge-blue') ?>"><?= ucfirst($h['priorite']) ?></span></td>
      <td><span class="badge <?= $statutBadgeH[$h['statut']]??'badge-gray' ?>"><?= ucfirst($h['statut']) ?></span></td>
      <td style="font-size:12px;max-width:200px"><?= h(mb_substr($h['motif'],0,80)) ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php else: ?><div style="padding:20px;color:var(--text3);text-align:center">Aucune hospitalisation</div><?php endif; ?>
</div>

<!-- Ordonnances -->
<div class="card mb-16">
  <div class="card-header"><h3> Ordonnances (<?= count($ordos) ?>)</h3></div>
  <?php foreach ($ordos as $o): ?>
  <div style="padding:14px 20px;border-bottom:1px solid var(--border)">
    <div style="display:flex;justify-content:space-between;margin-bottom:8px">
      <div><strong>Ordonnance #<?= (int)$o['id'] ?></strong>  <?= h($o['medecin']) ?>  <?= fmt_date($o['date_prescription'],true) ?></div>
      <span class="badge <?= $statutBadgeO[$o['statut']]??'badge-gray' ?>"><?= ucfirst($o['statut']) ?></span>
    </div>
    <?php foreach ($o['lignes'] as $l): ?>
    <div style="font-size:12px;color:var(--text2);padding:3px 0">
       <strong><?= h($l['med_nom']) ?></strong> <?= h($l['dosage']??'') ?>  <?= h($l['dosage_prescribed']??$l['frequence']??'') ?>  <?= h($l['duree']??'') ?>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endforeach; ?>
  <?php if (empty($ordos)): ?><div style="padding:20px;color:var(--text3);text-align:center">Aucune ordonnance</div><?php endif; ?>
</div>

<!-- Analyses -->
<div class="card mb-16">
  <div class="card-header"><h3> Analyses (<?= count($analyses) ?>)</h3></div>
  <?php if ($analyses): ?>
  <table>
    <thead><tr><th>N</th><th>Type</th><th>Prescripteur</th><th>Prélevé</th><th>Résultat</th><th>Statut</th></tr></thead>
    <tbody>
    <?php foreach ($analyses as $a): ?>
    <tr>
      <td><strong><?= h($a['numero']) ?></strong></td>
      <td><?= h($a['type_examen']) ?></td>
      <td style="font-size:12px"><?= h($a['prescripteur']) ?></td>
      <td style="font-size:12px"><?= $a['date_prelevement']?fmt_date($a['date_prelevement'],true):'' ?></td>
      <td style="font-size:12px;max-width:200px;color:var(--text2)"><?= $a['resultat']?h(mb_substr($a['resultat'],0,80)):'' ?></td>
      <td><span class="badge <?= $statutBadgeA[$a['statut']]??'badge-gray' ?>"><?= ucfirst($a['statut']) ?></span></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php else: ?><div style="padding:20px;color:var(--text3);text-align:center">Aucune analyse</div><?php endif; ?>
</div>

<!-- Factures -->
<div class="card mb-16">
  <div class="card-header"><h3> Factures (<?= count($factures) ?>)</h3></div>
  <?php if ($factures): ?>
  <table>
    <thead><tr><th>N</th><th>Montant total</th><th>Part assurance</th><th>Part patient</th><th>Mode</th><th>Date</th><th>Statut</th></tr></thead>
    <tbody>
    <?php foreach ($factures as $f): ?>
    <tr>
      <td><strong><?= h($f['numero']) ?></strong></td>
      <td><?= fmt_money((float)$f['montant_total']) ?></td>
      <td><?= fmt_money((float)$f['montant_assurance']) ?></td>
      <td><?= fmt_money((float)$f['montant_patient']) ?></td>
      <td style="font-size:12px"><?= h($f['assurance_type']??'') ?></td>
      <td style="font-size:12px"><?= fmt_date($f['date_emission'],true) ?></td>
      <td><span class="badge <?= $statutBadgeF[$f['statut']]??'badge-gray' ?>"><?= ucfirst(str_replace('_',' ',$f['statut'])) ?></span></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php else: ?><div style="padding:20px;color:var(--text3);text-align:center">Aucune facture</div><?php endif; ?>
</div>

<?php elseif (!$search): ?>
<!-- Page d'accueil dossiers  Patients récents -->
<?php
$recents = db_select(
    "SELECT p.id, p.numero, p.nom, p.prenom, p.date_naissance, p.sexe,
            h.priorite, d.nom AS dept_nom
     FROM patients p
     LEFT JOIN hospitalisations h ON h.patient_id=p.id AND h.statut='en_cours'
     LEFT JOIN departements d ON d.id=h.departement_id
     ORDER BY p.date_creation DESC LIMIT 12"
);
?>
<div style="text-align:center;padding:32px 0 20px;color:var(--text3)">
  <div style="font-size:32px;margin-bottom:8px"></div>
  <div style="font-size:14px;font-weight:600;color:var(--text2)">Rechercher ou sélectionner un patient</div>
</div>
<?php if (!empty($recents)): ?>
<div class="card">
  <div class="card-header">
    <h3>Patients récents</h3>
    <a href="patients.php" class="btn btn-sm btn-ghost">Tous les patients </a>
  </div>
  <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:0">
    <?php foreach ($recents as $r):
      $age   = $r['date_naissance'] ? date_diff(date_create($r['date_naissance']), date_create())->y : '?';
      $init  = strtoupper(mb_substr($r['prenom'],0,1).mb_substr($r['nom'],0,1));
      $hosp  = !empty($r['dept_nom']);
    ?>
    <a href="dossiers.php?patient_id=<?= (int)$r['id'] ?>" style="display:flex;align-items:center;gap:10px;padding:12px 16px;border-right:1px solid var(--border);border-bottom:1px solid var(--border);text-decoration:none;transition:background .1s" onmouseover="this.style.background='var(--surface2)'" onmouseout="this.style.background=''">
      <div style="width:36px;height:36px;border-radius:9px;background:<?= $hosp?'rgba(239,68,68,.15)':'rgba(59,130,246,.12)' ?>;color:<?= $hosp?'var(--red)':'var(--accent2)' ?>;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;flex-shrink:0"><?= $init ?></div>
      <div style="min-width:0">
        <div style="font-size:13px;font-weight:600;color:var(--text);overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= h($r['prenom'].' '.$r['nom']) ?></div>
        <div style="font-size:11px;color:var(--text3)"><?= $age ?> ans  <?= h($r['numero']) ?><?= $hosp?'  '.h($r['dept_nom']):'' ?></div>
      </div>
    </a>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>
<?php endif; ?>

<style>
@media print {
    .sidebar, nav, header, .pill-tabs, .btn, form, .alert,
    .page-header-row > div:last-child, .recents-grid { display: none !important; }
    .card { box-shadow: none; border: 1px solid #ddd; break-inside: avoid; margin-bottom: 12px; }
    body, .app-wrapper, .main-content, .content-area { background: white !important; color: black !important; }
    table { width: 100%; border-collapse: collapse; font-size: 11px; }
    th, td { border: 1px solid #ddd; padding: 4px 8px; }
    th { background: #f5f5f5 !important; }
    .badge { border: 1px solid #999; background: #eee !important; color: #333 !important; }
    h2, h3 { color: black !important; }
}
</style>
<?php require_once __DIR__ . '/../includes/footer.php';
