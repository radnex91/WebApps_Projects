<?php
// modules/correspondances/ajouter.php
require_once '../../includes/config.php';
requireLogin(); requirePerm('correspondances.manage');
$pageTitle = 'Nouvelle correspondance';

if ($_SERVER['REQUEST_METHOD']==='POST') {
    $agence  = (int)($_POST['agence_id'] ?? 0);
    $itArr   = (int)($_POST['itineraire_arrivee_id'] ?? 0);
    $itDep   = (int)($_POST['itineraire_depart_id'] ?? 0);
    $dMin    = (int)($_POST['delai_min'] ?? 30);
    $dMax    = (int)($_POST['delai_max'] ?? 240);

    if (!$agence || !$itArr || !$itDep) {
        flash('Tous les champs sont obligatoires.','danger');
    } elseif ($itArr === $itDep) {
        flash('Les itinéraires doivent être différents.','danger');
    } else {
        try {
            $pdo->prepare("INSERT INTO correspondances (agence_id,itineraire_arrivee_id,itineraire_depart_id,delai_min,delai_max) VALUES (?,?,?,?,?)")
                ->execute([$agence,$itArr,$itDep,$dMin,$dMax]);
            logAction($pdo,'create_correspondance','correspondances',"Correspondance créée à agence $agence");
            flash('Correspondance créée avec succès.');
            redirect(BASE_URL.'modules/correspondances/');
        } catch(Exception $e) {
            flash('Erreur : '.$e->getMessage(),'danger');
        }
    }
}

$agences = $pdo->query("SELECT * FROM agences WHERE actif=1 ORDER BY ville")->fetchAll();
$itineraires = $pdo->query("SELECT i.*, a1.ville as dep, a2.ville as arr FROM itineraires i JOIN agences a1 ON i.agence_depart=a1.id JOIN agences a2 ON i.agence_arrivee=a2.id WHERE i.actif=1 ORDER BY i.nom")->fetchAll();

include '../../includes/header.php';
?>
<div class="breadcrumb"><a href="<?= BASE_URL ?>"><i class="fas fa-home"></i></a><span class="breadcrumb-sep">/</span><a href="index.php">Correspondances</a><span class="breadcrumb-sep">/</span>Nouvelle</div>

<div class="card">
  <div class="card-header"><h3><i class="fas fa-plus"></i> Nouvelle correspondance</h3></div>
  <div class="card-body">
    <div style="background:var(--info-bg);border:1px solid var(--info);border-radius:var(--radius);padding:12px;margin-bottom:18px;font-size:13px;color:#164e63;">
      <i class="fas fa-info-circle"></i> Une correspondance définit qu'à une agence donnée, un passager arrivant via un itinéraire peut prendre un autre itinéraire en correspondance (transit).
    </div>
    <form method="POST">
      <?= csrfField() ?>
      <div class="form-grid">
        <div class="fg">
          <label class="flbl">Agence de correspondance <span class="freq">*</span></label>
          <select name="agence_id" class="fc" required>
            <option value="">— Où se fait la correspondance ? —</option>
            <?php foreach($agences as $a): ?><option value="<?= $a['id'] ?>"><?= sanitize($a['ville']) ?> (<?= $a['code'] ?>)</option><?php endforeach; ?>
          </select>
        </div>
        <div class="fg">
          <label class="flbl">Itinéraire d'arrivée <span class="freq">*</span></label>
          <select name="itineraire_arrivee_id" class="fc" required>
            <option value="">— Le passager arrive via… —</option>
            <?php foreach($itineraires as $i): ?><option value="<?= $i['id'] ?>"><?= sanitize($i['nom']) ?> (<?= $i['code'] ?>)</option><?php endforeach; ?>
          </select>
        </div>
        <div class="fg">
          <label class="flbl">Itinéraire de départ <span class="freq">*</span></label>
          <select name="itineraire_depart_id" class="fc" required>
            <option value="">— Le passager repart via… —</option>
            <?php foreach($itineraires as $i): ?><option value="<?= $i['id'] ?>"><?= sanitize($i['nom']) ?> (<?= $i['code'] ?>)</option><?php endforeach; ?>
          </select>
        </div>
        <div class="fg">
          <label class="flbl">Délai minimum (min)</label>
          <input type="number" name="delai_min" class="fc" min="0" value="30" placeholder="30">
        </div>
        <div class="fg">
          <label class="flbl">Délai maximum (min)</label>
          <input type="number" name="delai_max" class="fc" min="0" value="240" placeholder="240">
        </div>
      </div>
      <button type="submit" class="btn btn-success btn-lg btn-block" style="margin-top:18px;"><i class="fas fa-check-circle"></i> Créer la correspondance</button>
    </form>
  </div>
</div>
<?php include '../../includes/footer.php'; ?>