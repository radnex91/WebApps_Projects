<?php
// modules/itineraires/modifier.php
require_once '../../includes/config.php';
requireLogin(); requirePerm('itineraires.manage');
$pageTitle = 'Modifier itinéraire';

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM itineraires WHERE id=?"); $stmt->execute([$id]);
$it = $stmt->fetch();
if (!$it) { flash('Itinéraire introuvable.','danger'); redirect(BASE_URL.'modules/itineraires/'); }

if ($_SERVER['REQUEST_METHOD']==='POST') {
    $nom    = trim($_POST['nom'] ?? '');
    $dist   = (int)($_POST['distance_km'] ?? 0);
    $duree  = (int)($_POST['duree_minutes'] ?? 0);
    $escales = $_POST['escales'] ?? [];

    if (!$nom) { flash('Nom obligatoire.','danger'); }
    else {
        try {
            $pdo->beginTransaction();
            $pdo->prepare("UPDATE itineraires SET nom=?,distance_km=?,duree_minutes=? WHERE id=?")->execute([$nom,$dist,$duree,$id]);

            // Recréer les escales
            $pdo->prepare("DELETE FROM itineraire_escales WHERE itineraire_id=?")->execute([$id]);

            // Départ
            $pdo->prepare("INSERT INTO itineraire_escales (itineraire_id,agence_id,ordre,distance_debut,duree_debut) VALUES (?,?,?,?,?)")
                ->execute([$id,$it['agence_depart'],1,0,0]);

            $ordre = 2;
            foreach ($escales as $esc) {
                $eAgence = (int)($esc['agence_id'] ?? 0);
                $eDist   = (int)($esc['distance_debut'] ?? 0);
                $eDuree  = (int)($esc['duree_debut'] ?? 0);
                if ($eAgence) {
                    $pdo->prepare("INSERT INTO itineraire_escales (itineraire_id,agence_id,ordre,distance_debut,duree_debut) VALUES (?,?,?,?,?)")
                        ->execute([$id,$eAgence,$ordre,$eDist,$eDuree]);
                    $ordre++;
                }
            }

            // Arrivée
            $pdo->prepare("INSERT INTO itineraire_escales (itineraire_id,agence_id,ordre,distance_debut,duree_debut) VALUES (?,?,?,?,?)")
                ->execute([$id,$it['agence_arrivee'],$ordre,$dist,$duree]);

            $pdo->commit();
            logAction($pdo,'modify_itineraire','itineraires',"Itinéraire $id modifié");
            flash('Itinéraire modifié avec succès.');
            redirect(BASE_URL.'modules/itineraires/');
        } catch(Exception $e) {
            $pdo->rollBack();
            flash('Erreur : '.$e->getMessage(),'danger');
        }
    }
}

$current_escales = $pdo->prepare("SELECT e.*, a.ville FROM itineraire_escales e JOIN agences a ON e.agence_id=a.id WHERE e.itineraire_id=? ORDER BY e.ordre");
$current_escales->execute([$id]); $escales = $current_escales->fetchAll();

// Retirer départ et arrivée des escales modifiables
$middle_escales = array_filter($escales, fn($e) => $e['ordre'] > 1 && $e['ordre'] < count($escales));
$agences = $pdo->query("SELECT * FROM agences WHERE actif=1 ORDER BY ville")->fetchAll();

include '../../includes/header.php';
?>
<div class="breadcrumb"><a href="<?= BASE_URL ?>"><i class="fas fa-home"></i></a><span class="breadcrumb-sep">/</span><a href="index.php">Itinéraires</a><span class="breadcrumb-sep">/</span>Modifier</div>

<div class="card">
  <div class="card-header"><h3><i class="fas fa-edit"></i> Modifier l'itinéraire <?= sanitize($it['code']) ?></h3></div>
  <div class="card-body">
    <form method="POST" id="itin-form">
      <?= csrfField() ?>
      <div class="fsec">
        <div class="fsec-t"><i class="fas fa-info-circle"></i> Informations</div>
        <div class="form-grid">
          <div class="fg"><label class="flbl">Code</label><input type="text" class="fc" value="<?= sanitize($it['code']) ?>" disabled></div>
          <div class="fg"><label class="flbl">Nom <span class="freq">*</span></label><input type="text" name="nom" class="fc" value="<?= sanitize($it['nom']) ?>" required></div>
          <div class="fg"><label class="flbl">Départ</label><input type="text" class="fc" value="<?= sanitize($escales[0]['ville'] ?? '') ?>" disabled></div>
          <div class="fg"><label class="flbl">Arrivée</label><input type="text" class="fc" value="<?= sanitize($escales[count($escales)-1]['ville'] ?? '') ?>" disabled></div>
          <div class="fg"><label class="flbl">Distance (km)</label><input type="number" name="distance_km" id="inp-dist" class="fc" min="0" value="<?= $it['distance_km'] ?>" oninput="updatePreview()"></div>
          <div class="fg"><label class="flbl">Durée (minutes)</label><input type="number" name="duree_minutes" id="inp-duree" class="fc" min="0" value="<?= $it['duree_minutes'] ?>" oninput="updatePreview()"></div>
        </div>
      </div>

      <div class="fsec">
        <div class="fsec-t"><i class="fas fa-map-marker-alt"></i> Escales intermédiaires</div>
        <div id="escales-container">
          <?php foreach($middle_escales as $k => $esc): ?>
          <div class="escale-row" style="display:grid;grid-template-columns:40px 1fr 100px 100px 40px;gap:8px;align-items:end;margin-bottom:8px;padding:8px;background:var(--bg);border-radius:var(--radius);">
            <div style="text-align:center;font-weight:700;color:var(--text3);padding-top:8px;"><?= $esc['ordre'] ?></div>
            <div class="fg"><label class="flbl">Agence</label>
              <select name="escales[<?= $k ?>][agence_id]" class="fc escale-agence" onchange="updatePreview()">
                <option value="">— Sélectionner —</option>
                <?php foreach($agences as $a): ?><option value="<?= $a['id'] ?>" <?= $a['id']==$esc['agence_id']?'selected':'' ?>><?= sanitize($a['ville']) ?></option><?php endforeach; ?>
              </select>
            </div>
            <div class="fg"><label class="flbl">Distance (km)</label><input type="number" name="escales[<?= $k ?>][distance_debut]" class="fc escale-dist" min="0" value="<?= $esc['distance_debut'] ?>" oninput="updatePreview()"></div>
            <div class="fg"><label class="flbl">Durée (min)</label><input type="number" name="escales[<?= $k ?>][duree_debut]" class="fc escale-duree" min="0" value="<?= $esc['duree_debut'] ?>" oninput="updatePreview()"></div>
            <button type="button" onclick="this.parentElement.remove();reorderEscales();updatePreview()" style="background:none;border:none;color:var(--danger);cursor:pointer;font-size:16px;padding-top:6px;"><i class="fas fa-trash"></i></button>
          </div>
          <?php endforeach; ?>
        </div>
        <button type="button" onclick="addEscale()" class="btn btn-secondary btn-sm" style="margin-top:8px;"><i class="fas fa-plus"></i> Ajouter une escale</button>
      </div>

      <div class="fsec">
        <div class="fsec-t"><i class="fas fa-eye"></i> Aperçu</div>
        <div id="itin-preview" style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;font-size:14px;padding:8px 0;"></div>
      </div>

      <button type="submit" class="btn btn-success btn-lg btn-block"><i class="fas fa-check-circle"></i> Enregistrer</button>
    </form>
  </div>
</div>

<script>
const depName = '<?= sanitize($escales[0]['ville'] ?? '') ?>';
const arrName = '<?= sanitize($escales[count($escales)-1]['ville'] ?? '') ?>';

function addEscale() {
    const c = document.getElementById('escales-container');
    const n = c.children.length;
    const div = document.createElement('div');
    div.className = 'escale-row';
    div.style.cssText = 'display:grid;grid-template-columns:40px 1fr 100px 100px 40px;gap:8px;align-items:end;margin-bottom:8px;padding:8px;background:var(--bg);border-radius:var(--radius);';
    div.innerHTML = `
      <div style="text-align:center;font-weight:700;color:var(--text3);padding-top:8px;">${n+2}</div>
      <div class="fg"><label class="flbl">Agence</label><select name="escales[${n}][agence_id]" class="fc escale-agence" onchange="updatePreview()">
        <option value="">— Sélectionner —</option>
        <?php foreach($agences as $a): ?><option value="<?= $a['id'] ?>"><?= sanitize($a['ville']) ?></option><?php endforeach; ?>
      </select></div>
      <div class="fg"><label class="flbl">Distance (km)</label><input type="number" name="escales[${n}][distance_debut]" class="fc escale-dist" min="0" placeholder="0" oninput="updatePreview()"></div>
      <div class="fg"><label class="flbl">Durée (min)</label><input type="number" name="escales[${n}][duree_debut]" class="fc escale-duree" min="0" placeholder="0" oninput="updatePreview()"></div>
      <button type="button" onclick="this.parentElement.remove();reorderEscales();updatePreview()" style="background:none;border:none;color:var(--danger);cursor:pointer;font-size:16px;padding-top:6px;"><i class="fas fa-trash"></i></button>
    `;
    c.appendChild(div);
    updatePreview();
}
function reorderEscales() {
    document.querySelectorAll('#escales-container .escale-row').forEach((row,i) => {
        row.querySelector('div').textContent = i+2;
        row.querySelectorAll('input,select').forEach(el => { el.name = el.name.replace(/escales\[\d+\]/, `escales[${i}]`); });
    });
}
function updatePreview() {
    const totalDist = parseInt(document.getElementById('inp-dist').value)||0;
    const totalDuree = parseInt(document.getElementById('inp-duree').value)||0;
    let html = `<span style="background:var(--success);color:#fff;padding:4px 10px;border-radius:20px;font-size:12px;font-weight:600;">${depName}</span><i class="fas fa-long-arrow-alt-right" style="color:var(--text3);"></i>`;
    document.querySelectorAll('.escale-row').forEach(row => {
        const sel = row.querySelector('.escale-agence');
        if (sel && sel.value) {
            html += `<span style="background:var(--warning);color:#fff;padding:4px 10px;border-radius:20px;font-size:12px;font-weight:600;">${sel.options[sel.selectedIndex].text}</span><i class="fas fa-long-arrow-alt-right" style="color:var(--text3);"></i>`;
        }
    });
    html += `<span style="background:var(--danger);color:#fff;padding:4px 10px;border-radius:20px;font-size:12px;font-weight:600;">${arrName}</span>`;
    if (totalDist||totalDuree) html += `<span style="margin-left:8px;color:var(--text3);font-size:12px;">${totalDist} km — ${totalDuree} min</span>`;
    document.getElementById('itin-preview').innerHTML = html;
}
updatePreview();
</script>
<?php include '../../includes/footer.php'; ?>