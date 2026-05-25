<?php
// modules/agences/import.php — Import CSV des agences
require_once '../../includes/config.php';
requireLogin(); requirePerm('agences.manage');
$pageTitle = 'Importer des agences';
$import_result = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    $file = $_FILES['csv_file']['tmp_name'];
    $sep = trim($_POST['separator'] ?? ';');

    if (!$file || !is_uploaded_file($_FILES['csv_file']['tmp_name'])) {
        flash('Veuillez sélectionner un fichier CSV.', 'danger');
    } else {
        $handle = fopen($file, 'r');
        if (!$handle) {
            flash('Impossible de lire le fichier.', 'danger');
        } else {
            // Skip header row
            $header = fgetcsv($handle, 0, $sep);
            $created = 0; $updated = 0; $errors = 0; $lines = [];
            $row_num = 1;

            while (($row = fgetcsv($handle, 0, $sep)) !== false) {
                $row_num++;
                // Pad row to at least 3 columns
                while (count($row) < 3) $row[] = '';
                $code = strtoupper(trim($row[0] ?? ''));
                $nom = trim($row[1] ?? '');
                $responsable = trim($row[2] ?? '');

                if (!$code || !$nom) {
                    $lines[] = ['row' => $row_num, 'code' => $code, 'nom' => $nom, 'status' => 'erreur', 'msg' => 'Code ou nom manquant'];
                    $errors++;
                    continue;
                }

                try {
                    // Check if code already exists
                    $existing = $pdo->prepare("SELECT id FROM agences WHERE code=?");
                    $existing->execute([$code]);
                    $ex = $existing->fetchColumn();

                    if ($ex) {
                        $pdo->prepare("UPDATE agences SET nom=?,responsable=? WHERE code=?")->execute([$nom, $responsable, $code]);
                        $lines[] = ['row' => $row_num, 'code' => $code, 'nom' => $nom, 'status' => 'updated', 'msg' => 'Mis à jour'];
                        $updated++;
                    } else {
                        $pdo->prepare("INSERT INTO agences (code,nom,responsable) VALUES (?,?,?)")->execute([$code, $nom, $responsable]);
                        $lines[] = ['row' => $row_num, 'code' => $code, 'nom' => $nom, 'status' => 'created', 'msg' => 'Créé'];
                        $created++;
                    }
                } catch (Exception $e) {
                    $lines[] = ['row' => $row_num, 'code' => $code, 'nom' => $nom, 'status' => 'erreur', 'msg' => $e->getMessage()];
                    $errors++;
                }
            }
            fclose($handle);
            $import_result = ['created' => $created, 'updated' => $updated, 'errors' => $errors, 'lines' => $lines];
            logAction($pdo, 'import_agences', 'agences', "Import CSV: $created créés, $updated mis à jour, $errors erreurs");
        }
    }
}

include '../../includes/header.php';
?>
<div class="breadcrumb"><a href="<?= BASE_URL ?>"><i class="fas fa-home"></i></a><span class="breadcrumb-sep">/</span><a href="./">Agences</a><span class="breadcrumb-sep">/</span>Importer</div>

<div class="card" style="max-width:700px;margin:0 auto;">
  <div class="card-header"><h3><i class="fas fa-file-import"></i> Importer des agences</h3></div>
  <div class="card-body">
    <div style="background:var(--bg);border-radius:var(--radius);padding:14px;margin-bottom:16px;font-size:13px;">
      <strong>Format du fichier CSV :</strong>
      <div style="margin-top:8px;">
        <code style="font-size:12px;background:#e5e7eb;padding:2px 6px;border-radius:3px;">code;nom;responsable</code>
      </div>
      <div style="margin-top:6px;color:var(--text2);">
        Colonnes : <strong>code*</strong> (ex: YDE), <strong>nom*</strong> (ex: Gare Routière), <strong>responsable</strong> (ex: Jean Dupont)<br>
        * = obligatoire. Si un code existe déjà, l'agence sera mise à jour.
      </div>
      <div style="margin-top:10px;">
        <a href="?template=1" class="btn btn-xs btn-ghost"><i class="fas fa-download"></i> Télécharger le template CSV</a>
      </div>
    </div>

    <form method="POST" enctype="multipart/form-data">
      <?= csrfField() ?>
      <div class="form-grid">
        <div class="fg full">
          <label class="flbl">Fichier CSV <span class="freq">*</span></label>
          <input type="file" name="csv_file" accept=".csv" class="fc" required>
        </div>
        <div class="fg">
          <label class="flbl">Séparateur</label>
          <select name="separator" class="fc">
            <option value=";">Point-virgule (;)</option>
            <option value=",">Virgule (,)</option>
            <option value="\t">Tabulation</option>
          </select>
        </div>
      </div>
      <div style="margin-top:14px;display:flex;gap:10px;justify-content:flex-end;">
        <a href="./" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Retour</a>
        <button type="submit" class="btn btn-success"><i class="fas fa-upload"></i> Importer</button>
      </div>
    </form>

    <?php if ($import_result): ?>
    <div style="margin-top:20px;">
      <h4 style="margin-bottom:10px;">Résultat de l'import</h4>
      <div style="display:flex;gap:14px;margin-bottom:12px;">
        <div style="padding:10px 16px;background:#dcfce7;border-radius:var(--radius);font-weight:600;color:#14532d;"><?= $import_result['created'] ?> créés</div>
        <div style="padding:10px 16px;background:#dbeafe;border-radius:var(--radius);font-weight:600;color:#1e3a8a;"><?= $import_result['updated'] ?> mis à jour</div>
        <?php if ($import_result['errors'] > 0): ?>
        <div style="padding:10px 16px;background:#fee2e2;border-radius:var(--radius);font-weight:600;color:#7f1d1d;"><?= $import_result['errors'] ?> erreurs</div>
        <?php endif; ?>
      </div>
      <table data-no-filter style="width:100%;font-size:12px;">
        <thead><tr><th>Ligne</th><th>Code</th><th>Nom</th><th>Résultat</th></tr></thead>
        <tbody>
          <?php foreach ($import_result['lines'] as $line): ?>
          <tr>
            <td><?= $line['row'] ?></td>
            <td><code><?= sanitize($line['code']) ?></code></td>
            <td><?= sanitize($line['nom']) ?></td>
            <td><span class="badge <?= $line['status']==='created'?'badge-green':($line['status']==='updated'?'badge-blue':'badge-red') ?>"><?= $line['msg'] ?></span></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>
</div>
<?php include '../../includes/footer.php'; ?>