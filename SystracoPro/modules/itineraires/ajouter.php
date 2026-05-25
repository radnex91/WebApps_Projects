<?php
// modules/itineraires/ajouter.php
require_once '../../includes/config.php';
requireLogin(); requirePerm('itineraires.manage');
$pageTitle = 'Nouvel itinéraire';

if ($_SERVER['REQUEST_METHOD']==='POST') {
    $code   = trim($_POST['code'] ?? '');
    $nom    = trim($_POST['nom'] ?? '');
    $dep    = (int)($_POST['agence_depart'] ?? 0);
    $arr    = (int)($_POST['agence_arrivee'] ?? 0);
    $dist   = (int)($_POST['distance_km'] ?? 0);
    $duree  = (int)($_POST['duree_minutes'] ?? 0);
    $escales = $_POST['escales'] ?? []; // [{agence_id, distance_debut, duree_debut}]

    if (!$code || !$nom || !$dep || !$arr) {
        flash('Code, nom et agences départ/arrivée obligatoires.','danger');
    } elseif ($dep === $arr) {
        flash('Les agences de départ et arrivée doivent être différentes.','danger');
    } else {
        try {
            $pdo->beginTransaction();
            $pdo->prepare("INSERT INTO itineraires (code,nom,agence_depart,agence_arrivee,distance_km,duree_minutes) VALUES (?,?,?,?,?,?)")
                ->execute([$code,$nom,$dep,$arr,$dist,$duree]);
            $itId = $pdo->lastInsertId();

            // Escale 1: départ
            $pdo->prepare("INSERT INTO itineraire_escales (itineraire_id,agence_id,ordre,distance_debut,duree_debut) VALUES (?,?,?,?,?)")
                ->execute([$itId,$dep,1,0,0]);

            // Escales intermédiaires
            $ordre = 2;
            foreach ($escales as $esc) {
                $eAgence = (int)($esc['agence_id'] ?? 0);
                $eDist   = (int)($esc['distance_debut'] ?? 0);
                $eDuree  = (int)($esc['duree_debut'] ?? 0);
                if ($eAgence) {
                    $pdo->prepare("INSERT INTO itineraire_escales (itineraire_id,agence_id,ordre,distance_debut,duree_debut) VALUES (?,?,?,?,?)")
                        ->execute([$itId,$eAgence,$ordre,$eDist,$eDuree]);
                    $ordre++;
                }
            }

            // Dernière escale: arrivée
            $pdo->prepare("INSERT INTO itineraire_escales (itineraire_id,agence_id,ordre,distance_debut,duree_debut) VALUES (?,?,?,?,?)")
                ->execute([$itId,$arr,$ordre,$dist,$duree]);

            $pdo->commit();
            logAction($pdo,'create_itineraire','itineraires',"Itinéraire $code créé");
            flash("Itinéraire $code créé avec succès.");
            redirect(BASE_URL.'modules/itineraires/');
        } catch(Exception $e) {
            $pdo->rollBack();
            flash('Erreur : '.$e->getMessage(),'danger');
        }
    }
}

$agences = $pdo->query("SELECT * FROM agences WHERE actif=1 ORDER BY ville")->fetchAll();

include '../../includes/header.php';
?>
<div class="breadcrumb"><a href="<?= BASE_URL ?>"><i class="fas fa-home"></i></a><span class="breadcrumb-sep">/</span><a href="index.php">Itinéraires</a><span class="breadcrumb-sep">/</span>Nouvel itinéraire</div>

<div class="card">
  <div class="card-header"><h3><i class="fas fa-plus"></i> Nouvel itinéraire</h3></div>
  <div class="card-body">
    <form method="POST" id="itin-form">
      <?= csrfField() ?>
      <div class="fsec">
        <div class="fsec-t"><i class="fas fa-info-circle"></i> Informations</div>
        <div class="form-grid">
          <div class="fg"><label class="flbl">Code <span class="freq">*</span></label><input type="text" name="code" class="fc" placeholder="YDE-DLA" required style="text-transform:uppercase;"></div>
          <div class="fg"><label class="flbl">Nom <span class="freq">*</span></label><input type="text" name="nom" class="fc" placeholder="Yaoundé → Douala" required></div>
          <div class="fg"><label class="flbl">Agence départ <span class="freq">*</span></label>
            <select name="agence_depart" id="sel-dep" class="fc" required onchange="updatePreview()">
              <option value="">— Sélectionner —</option>
              <?php foreach($agences as $a): ?><option value="<?= $a['id'] ?>"><?= sanitize($a['ville']) ?> (<?= $a['code'] ?>)</option><?php endforeach; ?>
            </select>
          </div>
          <div class="fg"><label class="flbl">Agence arrivée <span class="freq">*</span></label>
            <select name="agence_arrivee" id="sel-arr" class="fc" required onchange="updatePreview()">
              <option value="">— Sélectionner —</option>
              <?php foreach($agences as $a): ?><option value="<?= $a['id'] ?>"><?= sanitize($a['ville']) ?> (<?= $a['code'] ?>)</option><?php endforeach; ?>
            </select>
          </div>
          <div class="fg"><label class="flbl">Distance (km)</label><input type="number" name="distance_km" id="inp-dist" class="fc" min="0" placeholder="0" oninput="updatePreview()"></div>
          <div class="fg"><label class="flbl">Durée (minutes)</label><input type="number" name="duree_minutes" id="inp-duree" class="fc" min="0" placeholder="0" oninput="updatePreview()"></div>
        </div>
      </div>

      <div class="fsec">
        <div class="fsec-t"><i class="fas fa-map-marker-alt"></i> Escales intermédiaires</div>
        <p style="font-size:12px;color:var(--text3);margin-bottom:10px;">Ajoutez les arrêts entre le départ et l'arrivée. L'agence de départ et d'arrivée sont ajoutées automatiquement.</p>
        <div id="escales-container"></div>
        <button type="button" onclick="addEscale()" class="btn btn-secondary btn-sm" style="margin-top:8px;"><i class="fas fa-plus"></i> Ajouter une escale</button>
      </div>

      <!-- Aperçu -->
      <div class="fsec">
        <div class="fsec-t"><i class="fas fa-eye"></i> Aperçu de l'itinéraire</div>
        <div id="itin-preview" style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;font-size:14px;padding:8px 0;">
          <span style="color:var(--text3);">Sélectionnez les agences pour voir l'aperçu</span>
        </div>
      </div>

      <button type="submit" class="btn btn-success btn-lg btn-block"><i class="fas fa-check-circle"></i> Créer l'itinéraire</button>
    </form>
  </div>
</div>

<script>
const agences = <?= json_encode(array_column($agences,'ville','id')) ?>;

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
        row.querySelectorAll('input,select').forEach(el => {
            el.name = el.name.replace(/escales\[\d+\]/, `escales[${i}]`);
        });
    });
}

function updatePreview() {
    const dep = document.getElementById('sel-dep');
    const arr = document.getElementById('sel-arr');
    const preview = document.getElementById('itin-preview');
    if (!dep.value || !arr.value) { preview.innerHTML = '<span style="color:var(--text3);">Sélectionnez les agences pour voir l\'aperçu</span>'; return; }

    const depName = dep.options[dep.selectedIndex]?.text?.split(' (')[0] || '';
    const arrName = arr.options[arr.selectedIndex]?.text?.split(' (')[0] || '';
    const totalDist = parseInt(document.getElementById('inp-dist').value)||0;
    const totalDuree = parseInt(document.getElementById('inp-duree').value)||0;

    let html = `<span style="background:var(--success);color:#fff;padding:4px 10px;border-radius:20px;font-size:12px;font-weight:600;">${depName}</span>`;
    html += `<i class="fas fa-long-arrow-alt-right" style="color:var(--text3);"></i>`;

    document.querySelectorAll('.escale-row').forEach(row => {
        const sel = row.querySelector('.escale-agence');
        if (sel && sel.value) {
            const name = sel.options[sel.selectedIndex]?.text || '';
            html += `<span style="background:var(--warning);color:#fff;padding:4px 10px;border-radius:20px;font-size:12px;font-weight:600;">${name}</span>`;
            html += `<i class="fas fa-long-arrow-alt-right" style="color:var(--text3);"></i>`;
        }
    });

    html += `<span style="background:var(--danger);color:#fff;padding:4px 10px;border-radius:20px;font-size:12px;font-weight:600;">${arrName}</span>`;
    if (totalDist || totalDuree) {
        html += `<span style="margin-left:8px;color:var(--text3);font-size:12px;">${totalDist} km — ${totalDuree} min</span>`;
    }
    preview.innerHTML = html;
}
</script>
<?php include '../../includes/footer.php'; ?>