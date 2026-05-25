<?php
// modules/bulletins/index.php
require_once '../../includes/config.php';
requireLogin();
$pageTitle = 'Bulletins de notes';

$annee = getAnneeActive($pdo);
$annee_id = $annee['id'] ?? 1;

$classes  = $pdo->query("SELECT c.*, n.nom as niveau_nom FROM classes c JOIN niveaux n ON c.niveau_id=n.id WHERE c.annee_id=$annee_id ORDER BY n.ordre")->fetchAll();
$periodes = $pdo->query("SELECT * FROM periodes WHERE annee_id=$annee_id")->fetchAll();

$sel_classe  = $_GET['classe_id'] ?? '';
$sel_periode = $_GET['periode_id'] ?? '';
$sel_eleve   = $_GET['eleve_id'] ?? '';

$eleves = [];
if ($sel_classe) {
    $stmt = $pdo->prepare("SELECT e.* FROM inscriptions i JOIN eleves e ON i.eleve_id=e.id WHERE i.classe_id=? AND i.annee_id=? ORDER BY e.nom");
    $stmt->execute([$sel_classe, $annee_id]);
    $eleves = $stmt->fetchAll();
}

// --- DONNÉES DU BULLETIN ---
$bulletin = null;
if ($sel_eleve && $sel_periode) {
    // Infos élève
    $stmt = $pdo->prepare("SELECT e.*, CONCAT(p.prenom,' ',p.nom) as parent_nom, p.telephone as parent_tel FROM eleves e LEFT JOIN parents p ON e.parent_id=p.id WHERE e.id=?");
    $stmt->execute([$sel_eleve]);
    $eleve_info = $stmt->fetch();

    // Classe
    $stmt = $pdo->prepare("SELECT cl.nom as classe_nom, n.nom as niveau_nom, n.cycle FROM inscriptions i JOIN classes cl ON i.classe_id=cl.id JOIN niveaux n ON cl.niveau_id=n.id WHERE i.eleve_id=? AND i.annee_id=?");
    $stmt->execute([$sel_eleve, $annee_id]);
    $classe_info = $stmt->fetch();

    // Notes de l'élève
    $stmt = $pdo->prepare("SELECT m.nom as matiere, m.coefficient, n.note, n.note_max, n.type_eval, n.observation,
        CONCAT(ens.prenom,' ',ens.nom) as enseignant
        FROM notes n
        JOIN matieres m ON n.matiere_id=m.id
        LEFT JOIN affectations a ON a.matiere_id=m.id AND a.classe_id=n.classe_id AND a.annee_id=?
        LEFT JOIN enseignants ens ON a.enseignant_id=ens.id
        WHERE n.eleve_id=? AND n.periode_id=? AND n.annee_id=?
        ORDER BY m.nom");
    $stmt->execute([$annee_id, $sel_eleve, $sel_periode, $annee_id]);
    $notes_eleve = $stmt->fetchAll();

    // Calcul moyenne
    $total_pondere = 0; $total_coeff = 0;
    foreach($notes_eleve as $n) {
        if ($n['note'] !== null) {
            $total_pondere += $n['note'] * $n['coefficient'];
            $total_coeff += $n['coefficient'];
        }
    }
    $moyenne_gen = $total_coeff > 0 ? round($total_pondere / $total_coeff, 2) : null;

    // Rang dans la classe
    $rang_stmt = $pdo->prepare("
        SELECT i.eleve_id, ROUND(SUM(n.note*m.coefficient)/NULLIF(SUM(CASE WHEN n.note IS NOT NULL THEN m.coefficient ELSE 0 END),0),2) as moy
        FROM inscriptions i
        LEFT JOIN notes n ON n.eleve_id=i.eleve_id AND n.periode_id=? AND n.annee_id=?
        LEFT JOIN matieres m ON n.matiere_id=m.id
        WHERE i.classe_id=? AND i.annee_id=?
        GROUP BY i.eleve_id ORDER BY moy DESC");
    $rang_stmt->execute([$sel_periode,$annee_id,(int)$sel_classe,$annee_id]);
    $classement = $rang_stmt->fetchAll();
    $total_classe = count($classement);
    $rang = 1;
    foreach($classement as $cl) { if($cl['eleve_id'] == $sel_eleve) break; $rang++; }

    // Absences
    $abs_stmt = $pdo->prepare("SELECT COUNT(*) as total, SUM(justifie) as justif FROM absences WHERE eleve_id=? AND annee_id=?");
    $abs_stmt->execute([$sel_eleve,$annee_id]);
    $absences = $abs_stmt->fetch();

    $periode_info = $pdo->prepare("SELECT * FROM periodes WHERE id=?");
    $periode_info->execute([$sel_periode]);
    $periode_info = $periode_info->fetch();

    $bulletin = compact('eleve_info','classe_info','notes_eleve','moyenne_gen','rang','total_classe','absences','periode_info');
}

include '../../includes/header.php';
?>

<div class="breadcrumb">
  <a href="<?= BASE_URL ?>"><i class="fas fa-home"></i></a>
  <span class="breadcrumb-sep"></span> Bulletins
</div>

<div class="card no-print">
  <div class="card-header">
    <h2><i class="fas fa-file-alt"></i> Génération des bulletins</h2>
    <?php if($bulletin): ?>
    <button onclick="window.print()" class="btn btn-primary btn-sm"><i class="fas fa-print"></i> Imprimer</button>
    <?php endif; ?>
  </div>
  <div class="card-body">
    <form method="GET" class="search-bar">
      <select name="classe_id" class="form-control" onchange="this.form.submit()">
        <option value="">-- Sélectionner une classe --</option>
        <?php foreach($classes as $c): ?>
        <option value="<?= $c['id'] ?>" <?= $sel_classe==$c['id']?'selected':'' ?>><?= sanitize($c['nom'].' ('.$c['niveau_nom'].')') ?></option>
        <?php endforeach; ?>
      </select>
      <select name="periode_id" class="form-control">
        <option value="">-- Période --</option>
        <?php foreach($periodes as $p): ?>
        <option value="<?= $p['id'] ?>" <?= $sel_periode==$p['id']?'selected':'' ?>><?= sanitize($p['nom']) ?></option>
        <?php endforeach; ?>
      </select>
      <?php if(!empty($eleves)): ?>
      <select name="eleve_id" class="form-control">
        <option value="">-- Élève --</option>
        <?php foreach($eleves as $e): ?>
        <option value="<?= $e['id'] ?>" <?= $sel_eleve==$e['id']?'selected':'' ?>><?= sanitize($e['nom'].' '.$e['prenom']) ?></option>
        <?php endforeach; ?>
      </select>
      <?php endif; ?>
      <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Afficher</button>
    </form>
  </div>
</div>

<?php if($bulletin): 
  $b = $bulletin;
  $moy = $b['moyenne_gen'];
  $mention = $moy === null ? '—' : ($moy >= 16 ? 'Très Bien' : ($moy >= 14 ? 'Bien' : ($moy >= 12 ? 'Assez Bien' : ($moy >= 10 ? 'Passable' : 'Insuffisant'))));
?>
<div class="bulletin-print" style="background:#fff;padding:30px;border-radius:8px;box-shadow:var(--shadow);">
  <!-- EN-TÊTE -->
  <table style="width:100%;border:2px solid #333;margin-bottom:16px;">
    <tr>
      <td style="padding:12px;text-align:center;border-right:1px solid #333;">
        <strong style="font-size:16px;"><?= APP_NAME ?></strong><br>
        <small>Année scolaire : <?= sanitize($annee['libelle']??'') ?></small>
      </td>
      <td style="padding:12px;text-align:center;">
        <strong style="font-size:18px;text-transform:uppercase;">BULLETIN DE NOTES</strong><br>
        <span><?= sanitize($b['periode_info']['nom']) ?></span>
      </td>
      <td style="padding:12px;text-align:center;border-left:1px solid #333;">
        <strong>Classe :</strong> <?= sanitize($b['classe_info']['classe_nom']??'') ?><br>
        <strong>Niveau :</strong> <?= sanitize($b['classe_info']['niveau_nom']??'') ?>
      </td>
    </tr>
  </table>

  <!-- INFOS ÉLÈVE -->
  <table style="width:100%;border:1px solid #999;border-collapse:collapse;margin-bottom:16px;font-size:13px;">
    <tr style="background:#f5f5f5;">
      <td style="padding:6px 10px;border:1px solid #999;"><strong>Nom :</strong> <?= sanitize($b['eleve_info']['nom'].' '.$b['eleve_info']['prenom']) ?></td>
      <td style="padding:6px 10px;border:1px solid #999;"><strong>Matricule :</strong> <?= sanitize($b['eleve_info']['matricule']) ?></td>
      <td style="padding:6px 10px;border:1px solid #999;"><strong>Sexe :</strong> <?= $b['eleve_info']['sexe']=='M'?'Masculin':'Féminin' ?></td>
      <td style="padding:6px 10px;border:1px solid #999;"><strong>Né(e) le :</strong> <?= $b['eleve_info']['date_naissance'] ? date('d/m/Y',strtotime($b['eleve_info']['date_naissance'])) : '—' ?></td>
    </tr>
    <tr>
      <td style="padding:6px 10px;border:1px solid #999;" colspan="2"><strong>Parent/Tuteur :</strong> <?= sanitize($b['eleve_info']['parent_nom']??'—') ?></td>
      <td style="padding:6px 10px;border:1px solid #999;" colspan="2"><strong>Tél :</strong> <?= sanitize($b['eleve_info']['parent_tel']??'—') ?></td>
    </tr>
  </table>

  <!-- TABLEAU DES NOTES -->
  <table class="bulletin-table" style="margin-bottom:16px;font-size:13px;">
    <thead>
      <tr style="background:#1e293b;color:#fff;">
        <th style="padding:8px;text-align:left;border:1px solid #333;">Matière</th>
        <th style="padding:8px;text-align:center;border:1px solid #333;">Coeff.</th>
        <th style="padding:8px;text-align:center;border:1px solid #333;">Note /20</th>
        <th style="padding:8px;text-align:center;border:1px solid #333;">Note × Coeff</th>
        <th style="padding:8px;text-align:center;border:1px solid #333;">Appréciation</th>
        <th style="padding:8px;text-align:left;border:1px solid #333;">Enseignant</th>
        <th style="padding:8px;text-align:left;border:1px solid #333;">Observation</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach($b['notes_eleve'] as $n):
      $note_val = $n['note'] !== null ? (float)$n['note'] : null;
      $apprec = $note_val === null ? '—' : ($note_val >= 16 ? 'Très Bien' : ($note_val >= 14 ? 'Bien' : ($note_val >= 12 ? 'Assez Bien' : ($note_val >= 10 ? 'Passable' : 'Insuffisant'))));
      $bg = ($note_val !== null && $note_val < 10) ? '#fff0f0' : '#fff';
    ?>
    <tr style="background:<?= $bg ?>;">
      <td style="padding:6px 10px;border:1px solid #ccc;font-weight:600;"><?= sanitize($n['matiere']) ?></td>
      <td style="padding:6px 10px;border:1px solid #ccc;text-align:center;"><?= $n['coefficient'] ?></td>
      <td style="padding:6px 10px;border:1px solid #ccc;text-align:center;font-weight:700;color:<?= ($note_val!==null&&$note_val<10)?'#c00':'#0a0' ?>;"><?= $n['note'] ?></td>
      <td style="padding:6px 10px;border:1px solid #ccc;text-align:center;"><?= $note_val !== null ? round($note_val*$n['coefficient'],2) : '—' ?></td>
      <td style="padding:6px 10px;border:1px solid #ccc;text-align:center;"><?= $apprec ?></td>
      <td style="padding:6px 10px;border:1px solid #ccc;"><?= sanitize($n['enseignant']??'—') ?></td>
      <td style="padding:6px 10px;border:1px solid #ccc;"><?= sanitize($n['observation']??'') ?></td>
    </tr>
    <?php endforeach; ?>
    <?php if(empty($b['notes_eleve'])): ?>
    <tr><td colspan="7" style="text-align:center;padding:20px;color:#888;">Aucune note enregistrée pour cette période.</td></tr>
    <?php endif; ?>
    </tbody>
    <tfoot>
      <tr style="background:#f0f0f0;font-weight:700;font-size:14px;">
        <td colspan="3" style="padding:8px 10px;border:1px solid #333;text-align:right;">MOYENNE GÉNÉRALE :</td>
        <td colspan="4" style="padding:8px 10px;border:1px solid #333;font-size:18px;color:<?= ($moy!==null&&$moy>=10)?'#0a0':'#c00' ?>;">
          <?= $moy ? $moy.'/20' : '—' ?>
          &nbsp;&nbsp;<span style="font-size:13px;font-weight:normal;"><?= $mention ?></span>
        </td>
      </tr>
    </tfoot>
  </table>

  <!-- RÉSUMÉ -->
  <table style="width:100%;border:1px solid #999;border-collapse:collapse;font-size:13px;margin-bottom:20px;">
    <tr style="background:#f5f5f5;font-weight:700;">
      <td style="padding:8px 12px;border:1px solid #999;">Moyenne : <span style="font-size:16px;color:<?= ($moy!==null&&$moy>=10)?'green':'red' ?>"><?= $moy!==null?$moy:'—' ?>/20</span></td>
      <td style="padding:8px 12px;border:1px solid #999;">Rang : <strong><?= $b['rang'] ?>e / <?= $b['total_classe'] ?></strong></td>
      <td style="padding:8px 12px;border:1px solid #999;">Absences : <?= $b['absences']['total'] ?> (dont <?= $b['absences']['justif']??0 ?> justifiées)</td>
      <td style="padding:8px 12px;border:1px solid #999;">Mention : <strong><?= $mention ?></strong></td>
      <td style="padding:8px 12px;border:1px solid #999;">Décision : <strong><?= ($moy!==null&&$moy>=10)?'ADMIS(E)':'EN ATTENTE' ?></strong></td>
    </tr>
  </table>

  <!-- SIGNATURES -->
  <table style="width:100%;font-size:13px;">
    <tr>
      <td style="text-align:center;padding:20px;">
        <div style="border-top:1px solid #333;padding-top:8px;margin-top:50px;">Signature du Directeur</div>
      </td>
      <td style="text-align:center;padding:20px;">
        <div style="border-top:1px solid #333;padding-top:8px;margin-top:50px;">Signature du Titulaire</div>
      </td>
      <td style="text-align:center;padding:20px;">
        <div style="border-top:1px solid #333;padding-top:8px;margin-top:50px;">Signature du Parent/Tuteur</div>
      </td>
    </tr>
  </table>
</div>
<?php else: ?>
<div class="empty-state card" style="padding:40px;">
  <i class="fas fa-file-alt"></i>
  <p>Sélectionnez une classe, une période et un élève pour afficher le bulletin.</p>
</div>
<?php endif; ?>

<?php include '../../includes/footer.php'; ?>
