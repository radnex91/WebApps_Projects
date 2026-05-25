<?php
// modules/vehicules/import.php — Import CSV des véhicules
require_once '../../includes/config.php'; requireLogin(); requirePerm('vehicules.manage');
$pageTitle = 'Importer des véhicules';
$aid = getUserAgenceId();
$import_result = null;

// Template download
if(isset($_GET['template'])){header('Content-Type:text/csv;charset=utf-8');header('Content-Disposition:attachment;filename=vehicules_template.csv');echo "immatriculation;marque;concessionnaire;modele;capacite;date_achat;groupe;statut\nLT-1234AB;Mercedes;SOTRA;Tourismo;55;2024-01-15;Groupe Nord;actif\nLT-5678CD;Toyota;;Hiace;16;2023-06-20;;actif\n";exit;}

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
            fgetcsv($handle, 0, $sep); // skip header
            $created = 0; $updated = 0; $errors = 0; $lines = [];
            $row_num = 1;

            while (($row = fgetcsv($handle, 0, $sep)) !== false) {
                $row_num++;
                while (count($row) < 8) $row[] = '';
                $immat = strtoupper(trim($row[0] ?? ''));
                $marque = trim($row[1] ?? '');
                $concessionnaireNom = trim($row[2] ?? '');
                $modele = trim($row[3] ?? '');
                $cap = (int)($row[4] ?? 0);
                $dateAchat = trim($row[5] ?? '') ?: null;
                $groupeNom = trim($row[6] ?? '');
                $statut = strtolower(trim($row[7] ?? 'actif'));

                if (!$immat) {
                    $lines[] = ['row' => $row_num, 'immat' => $immat, 'marque' => $marque, 'status' => 'erreur', 'msg' => 'Immatriculation manquante'];
                    $errors++; continue;
                }

                // Validate statut
                $validStatuts = ['actif','panne','maintenance','hors_service'];
                if (!in_array($statut, $validStatuts)) $statut = 'actif';

                // Resolve concessionnaire_id by name
                $cid = null;
                if ($concessionnaireNom) {
                    $cq = $pdo->prepare("SELECT id FROM concessionnaires WHERE nom=? AND actif=1 LIMIT 1");
                    $cq->execute([$concessionnaireNom]);
                    $cid = $cq->fetchColumn() ?: null;
                }

                // Resolve groupe_id
                $gid = null;
                if ($groupeNom) {
                    $gq = $pdo->prepare("SELECT id FROM groupes WHERE nom=? AND actif=1 LIMIT 1");
                    $gq->execute([$groupeNom]);
                    $gid = $gq->fetchColumn() ?: null;
                }

                try {
                    $existing = $pdo->prepare("SELECT id FROM vehicules WHERE immatriculation=?");
                    $existing->execute([$immat]);
                    $ex = $existing->fetchColumn();

                    if ($ex) {
                        $pdo->prepare("UPDATE vehicules SET marque=?,concessionnaire_id=?,modele=?,capacite=?,date_achat=?,groupe_id=?,statut=? WHERE immatriculation=?")
                            ->execute([$marque,$cid,$modele,$cap,$dateAchat,$gid,$statut,$immat]);
                        $lines[] = ['row' => $row_num, 'immat' => $immat, 'marque' => $marque, 'status' => 'updated', 'msg' => 'Mis à jour'];
                        $updated++;
                    } else {
                        $pdo->prepare("INSERT INTO vehicules (immatriculation,marque,concessionnaire_id,modele,capacite,date_achat,groupe_id,statut) VALUES (?,?,?,?,?,?,?,?)")
                            ->execute([$immat,$marque,$cid,$modele,$cap,$dateAchat,$gid,$statut]);
                        $lines[] = ['row' => $row_num, 'immat' => $immat, 'marque' => $marque, 'status' => 'created', 'msg' => 'Créé'];
                        $created++;
                    }
                } catch (Exception $e) {
                    $lines[] = ['row' => $row_num, 'immat' => $immat, 'marque' => $marque, 'status' => 'erreur', 'msg' => $e->getMessage()];
                    $errors++;
                }
            }
            fclose($handle);
            $import_result = ['created' => $created, 'updated' => $updated, 'errors' => $errors, 'lines' => $lines];
            logAction($pdo, 'import_vehicules', 'vehicules', "Import CSV: $created créés, $updated mis à jour, $errors erreurs");
        }
    }
}

include '../../includes/header.php';
?>
<div class="breadcrumb"><a href="<?= BASE_URL ?>"><i class="fas fa-home"></i></a><span class="breadcrumb-sep">/</span><a href="./">Véhicules</a><span class="breadcrumb-sep">/</span>Importer</div>

<div class="card" style="max-width:700px;margin:0 auto;">
  <div class="card-header"><h3><i class="fas fa-file-import"></i> Importer des véhicules</h3></div>
  <div class="card-body">
    <div style="background:var(--bg);border-radius:var(--radius);padding:14px;margin-bottom:16px;font-size:13px;">
      <strong>Format du fichier CSV :</strong>
      <div style="margin-top:8px;">
        <code style="font-size:11px;background:#e5e7eb;padding:2px 6px;border-radius:3px;">immatriculation;marque;concessionnaire;modele;capacite;date_achat;groupe;statut</code>
      </div>
      <div style="margin-top:6px;color:var(--text2);">
        Colonnes : <strong>immatriculation*</strong>, <strong>marque</strong>, concessionnaire (nom existant dans la table concessionnaires), modele, capacite (nombre), date_achat (AAAA-MM-JJ), groupe (nom du groupe existant), statut (actif/panne/maintenance/hors_service)<br>
        * = obligatoire. Si l'immatriculation existe, le véhicule sera mis à jour. Concessionnaire et groupe sont résolus par nom.
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
        <thead><tr><th>Ligne</th><th>Immatriculation</th><th>Marque</th><th>Résultat</th></tr></thead>
        <tbody>
          <?php foreach ($import_result['lines'] as $line): ?>
          <tr>
            <td><?= $line['row'] ?></td>
            <td><code><?= sanitize($line['immat']) ?></code></td>
            <td><?= sanitize($line['marque']) ?></td>
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