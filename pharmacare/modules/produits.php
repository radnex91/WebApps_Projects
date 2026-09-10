<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../config/settings.php';
requirePermission('produits.voir');
$db     = getDB();
$action = $_GET['action'] ?? 'list';
$id     = (int)($_GET['id'] ?? 0);

// ═══════════════════════════════════════════════════════════════
//  Import CSV d'articles → table `produits`
// ═══════════════════════════════════════════════════════════════

/** Normalise un en-tête CSV : minuscules, sans accents, ponctuation → espaces. */
function produit_csv_normalize(string $s): string {
    $s = mb_strtolower(trim($s), 'UTF-8');
    $s = strtr($s, [
        'à'=>'a','â'=>'a','ä'=>'a','á'=>'a','ã'=>'a','å'=>'a',
        'é'=>'e','è'=>'e','ê'=>'e','ë'=>'e',
        'í'=>'i','ì'=>'i','î'=>'i','ï'=>'i',
        'ó'=>'o','ò'=>'o','ô'=>'o','ö'=>'o','õ'=>'o',
        'ú'=>'u','ù'=>'u','û'=>'u','ü'=>'u',
        'ç'=>'c','ñ'=>'n',
    ]);
    $s = preg_replace('/[^a-z0-9]+/', ' ', $s); // _ , - . etc. → espace
    $s = preg_replace('/\s+/', ' ', $s);
    return trim($s);
}
/** Nettoie une cellule : trim + conversion CP1252→UTF-8 si besoin (Excel FR). */
function produit_csv_cell(string $s): string {
    $s = trim($s);
    if ($s !== '' && !mb_check_encoding($s, 'UTF-8')) {
        $conv = @mb_convert_encoding($s, 'UTF-8', 'CP1252');
        if ($conv !== false && $conv !== '') $s = $conv;
    }
    return $s;
}
/** Parse un nombre (virgule ou point décimal, espaces retirés). */
function produit_csv_num(string $s): float {
    $s = str_replace([' ', "\xc2\xa0"], '', trim($s));
    $s = str_replace(',', '.', $s);
    return (float)$s;
}
/** Parse une date (jj/mm/aaaa, aaaa-mm-jj, …) → Y-m-d ou null si invalide. */
function produit_csv_parse_date(string $s): ?string {
    $s = trim($s);
    if ($s === '') return null;
    foreach (['d/m/Y', 'Y-m-d', 'd-m-Y', 'd.m.Y'] as $fmt) {
        $d = DateTime::createFromFormat($fmt, $s);
        if ($d instanceof DateTime && $d->format($fmt) === $s) return $d->format('Y-m-d');
    }
    $ts = strtotime($s);
    return $ts ? date('Y-m-d', $ts) : null;
}
/** Carte des colonnes CSV reconnues : champ => alias normalisables. */
$PRODUIT_CSV_FIELDS = [
    'nom'             => ['nom', 'designation', 'design', 'libelle', 'produit', 'medicament', 'name'],
    'reference'       => ['reference', 'ref', 'code', 'codebarres', 'code barres', 'ean', 'cip'],
    'unite'           => ['unite', 'unite de vente', 'conditionnement', 'forme', 'presentation'],
    'categorie'       => ['categorie', 'category', 'rayon', 'famille'],
    'fournisseur'     => ['fournisseur', 'supplier', 'labo', 'laboratoire', 'fabriquant'],
    'stock'           => ['stock', 'quantite', 'qte', 'dispo'],
    'seuil_alerte'    => ['seuil', 'seuil alerte', 'alerte'],
    'prix_achat'      => ['prix achat', 'pa', 'cout', 'prixa'],
    'prix_vente'      => ['prix vente', 'pv', 'prix', 'prixv'],
    'tva'             => ['tva', 'taux tva'],
    'date_expiration' => ['expiration', 'peremption', 'dlc', 'dluo', 'date expiration'],
    'description'     => ['description', 'notes', 'remarque', 'commentaire'],
];

// ── Téléchargement du modèle CSV (GET, avant tout output) ─────
if ($action === 'import_template') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="modele_import_medicaments.csv"');
    header('Cache-Control: no-store');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // BOM UTF-8 pour Excel
    fputcsv($out, ['nom', 'reference', 'unite', 'categorie', 'fournisseur', 'stock', 'seuil_alerte', 'prix_achat', 'prix_vente', 'tva', 'date_expiration', 'description'], ';');
    fputcsv($out, ['Paracétamol 500mg', 'MED-001', 'comprimé', 'Antalgiques', 'PharmaPlus', '100', '10', '150', '200', '9', '31/12/2027', 'Boîte de 16 comprimés'], ';');
    fputcsv($out, ['Amoxicilline 1g', 'MED-002', 'comprimé', 'Antibiotiques', '', '50', '10', '320', '450', '9', '', ''], ';');
    fclose($out);
    exit;
}

// ── Import CSV (POST) : traitement ────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'import') {
    verifyCsrf();
    if (!hasPermission('produits.ajouter')) {
        flash('Accès refusé.', 'error');
        header('Location: ' . url('produits'));
        exit;
    }
    $mode     = (($_POST['doublons'] ?? 'skip') === 'update') ? 'update' : 'skip';
    $file     = $_FILES['file'] ?? null;
    $ajoutes  = 0; $maj = 0; $ignores = 0; $avert = 0;
    $errors   = [];   // erreurs bloquantes (ligne ignorée / fichier)
    $warnings = [];   // avertissements (ligne importée mais incomplète)

    // Carte alias normalisé => champ canonique
    $aliasMap = [];
    foreach ($PRODUIT_CSV_FIELDS as $field => $aliases) {
        foreach ($aliases as $a) $aliasMap[produit_csv_normalize($a)] = $field;
    }

    $canProcess = false;
    if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'] ?? '')) {
        $errors[] = 'Aucun fichier reçu ou upload échoué.';
    } elseif (strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION)) !== 'csv') {
        $errors[] = 'Le fichier doit avoir l\'extension .csv.';
    } elseif ((int)($file['size'] ?? 0) > 40 * 1024 * 1024) {
        $errors[] = 'Le fichier dépasse 40 Mo.';
    } else {
        $fh = @fopen($file['tmp_name'], 'r');
        if (!$fh) {
            $errors[] = 'Impossible de lire le fichier.';
        } else {
            // Sauter un éventuel BOM UTF-8
            if (fread($fh, 3) !== "\xEF\xBB\xBF") fseek($fh, 0);
            // Détection du délimiteur sur la 1re ligne
            $pos = ftell($fh);
            $firstLine = (string)fgets($fh);
            fseek($fh, $pos);
            $delim = (substr_count($firstLine, ';') > substr_count($firstLine, ',')) ? ';' : ',';
            $header = fgetcsv($fh, 0, $delim);
            if (!$header) {
                $errors[] = 'Fichier vide ou illisible.';
            } else {
                $colMap = [];
                foreach ($header as $i => $h) {
                    $key = produit_csv_normalize(produit_csv_cell((string)$h));
                    if (isset($aliasMap[$key])) $colMap[$aliasMap[$key]] = (int)$i;
                }
                if (!isset($colMap['nom'])) {
                    $errors[] = 'Colonne « nom » manquante. En-têtes détectés : ' . implode(', ', array_map('trim', $header));
                } else {
                    $canProcess = true;
                }
            }
            if ($canProcess) {
                $stmtCat   = $db->prepare("SELECT id FROM categories WHERE LOWER(nom)=LOWER(?) LIMIT 1");
                $stmtFourn = $db->prepare("SELECT id FROM fournisseurs WHERE LOWER(nom)=LOWER(?) ORDER BY actif DESC LIMIT 1");
                $stmtFind  = $db->prepare("SELECT id FROM produits WHERE reference=? LIMIT 1");
                // Palette de couleurs attribuées aux catégories créées à la volée
                // (rotation) — mêmes teintes que le module categories.php.
                $IMPORT_PALETTE = ['#00c9a7','#4895ef','#f0b429','#ef4444','#9b59b6','#e74c3c','#e67e22','#1abc9c','#3498db','#e91e63'];
                $importCatIdx    = 0;
                $importCatsCrees = [];
                // L'import RAVITAILLE LE MAGASIN (dépôt central), pas les pharmacies.
                // stock_magasin = quantité livrée ; produits.stock (miroir pharmacie
                // principale) reste 0 — les pharmacies sont alimentées par transferts.
                $stmtIns   = $db->prepare("INSERT INTO produits (nom,reference,unite,categorie_id,fournisseur_id,description,stock,stock_magasin,seuil_alerte,prix_achat,prix_vente,tva,date_expiration) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)");
                $stmtUpd   = $db->prepare("UPDATE produits SET nom=?,unite=?,categorie_id=?,fournisseur_id=?,description=?,seuil_alerte=?,prix_achat=?,prix_vente=?,tva=?,date_expiration=?,stock_magasin=stock_magasin+? WHERE id=?");
                $stmtMvtMag= $db->prepare("INSERT INTO mouvements_magasin (produit_id,type,quantite,motif,utilisateur_id) VALUES (?,'entrée',?,?,?)");
                $stmtCatIns= $db->prepare("INSERT INTO categories (nom,couleur) VALUES (?,?)");
                $uid = (int)($_SESSION['user_id'] ?? 0);
                $ligne = 1;
                $db->beginTransaction();
                try {
                while (($row = fgetcsv($fh, 0, $delim)) !== false) {
                    $ligne++;
                    // Ligne vide → on saute
                    $nonEmpty = false;
                    foreach ($row as $c) if (trim((string)$c) !== '') { $nonEmpty = true; break; }
                    if (!$nonEmpty) continue;
                    $cell = function (int $i) use ($row): string {
                        return isset($row[$i]) ? produit_csv_cell((string)$row[$i]) : '';
                    };
                    $nom = $cell($colMap['nom']);
                    if ($nom === '') { $errors[] = "Ligne $ligne : nom vide, ligne ignorée."; continue; }
                    $reference = $cell($colMap['reference'] ?? -1);
                    $reference = $reference !== '' ? $reference : null;
                    // Unité de conditionnement (optionnelle, texte libre)
                    $unite = isset($colMap['unite']) ? $cell($colMap['unite']) : '';
                    $unite = $unite !== '' ? $unite : null;
                    // Doublon sur référence (vérifié AVANT résolution cat/fourn/date
                    // pour qu'un doublon ignoré ne génère pas d'avertissements parasites).
                    $existingId = null;
                    if ($reference !== null) {
                        $stmtFind->execute([$reference]); $r = $stmtFind->fetch();
                        if ($r) $existingId = (int)$r['id'];
                    }
                    if ($existingId !== null && $mode === 'skip') { $ignores++; continue; }
                    // Catégorie : trouvée par nom (insensible à la casse), sinon
                    // créée à la volée (couleur de la palette par rotation).
                    $catId = null;
                    if (isset($colMap['categorie'])) {
                        $cn = $cell($colMap['categorie']);
                        if ($cn !== '') {
                            $stmtCat->execute([$cn]); $r = $stmtCat->fetch();
                            if ($r) {
                                $catId = (int)$r['id'];
                            } else {
                                $stmtCatIns->execute([$cn, $IMPORT_PALETTE[$importCatIdx % count($IMPORT_PALETTE)]]);
                                $catId    = (int)$db->lastInsertId();
                                $importCatIdx++;
                                $importCatsCrees[] = $cn;
                            }
                        }
                    }
                    // Fournisseur (par nom, NULL si introuvable)
                    $fournId = null;
                    if (isset($colMap['fournisseur'])) {
                        $fn = $cell($colMap['fournisseur']);
                        if ($fn !== '') {
                            $stmtFourn->execute([$fn]); $r = $stmtFourn->fetch();
                            if ($r) $fournId = (int)$r['id'];
                            else { $avert++; $warnings[] = "Ligne $ligne : fournisseur « $fn » introuvable → fournisseur vide."; }
                        }
                    }
                    $stockCell = isset($colMap['stock']) ? $cell($colMap['stock']) : '';
                    $stock = $stockCell !== '' ? max(0, (int)produit_csv_num($stockCell)) : 0;
                    $seuilCell = isset($colMap['seuil_alerte']) ? $cell($colMap['seuil_alerte']) : '';
                    $seuil = $seuilCell !== '' ? max(0, (int)produit_csv_num($seuilCell)) : 10;
                    $paCell = isset($colMap['prix_achat']) ? $cell($colMap['prix_achat']) : '';
                    $pa = $paCell !== '' ? produit_csv_num($paCell) : 0.0;
                    $pvCell = isset($colMap['prix_vente']) ? $cell($colMap['prix_vente']) : '';
                    $pv = $pvCell !== '' ? produit_csv_num($pvCell) : 0.0;
                    $tvaCell = isset($colMap['tva']) ? $cell($colMap['tva']) : '';
                    $tva = $tvaCell !== '' ? produit_csv_num($tvaCell) : 9.00;
                    $desc = isset($colMap['description']) ? $cell($colMap['description']) : '';
                    $exp = null;
                    if (isset($colMap['date_expiration'])) {
                        $dCell = $cell($colMap['date_expiration']);
                        if ($dCell !== '') {
                            $exp = produit_csv_parse_date($dCell);
                            if ($exp === null) { $avert++; $warnings[] = "Ligne $ligne : date « $dCell » invalide → expiration vide."; }
                        }
                    }
                    if ($existingId !== null) {
                        // mode update : met a jour les infos + ravitaille le magasin
                        // (increment stock_magasin). Stock vide/0 = mise a jour infos seule.
                        $stmtUpd->execute([$nom, $unite, $catId, $fournId, $desc, $seuil, $pa, $pv, $tva, $exp, $stock, $existingId]);
                        if ($stock > 0) $stmtMvtMag->execute([$existingId, $stock, 'Import CSV — ravitaillement magasin', $uid]);
                        $maj++;
                    } else {
                        $stmtIns->execute([$nom, $reference, $unite, $catId, $fournId, $desc, 0, $stock, $seuil, $pa, $pv, $tva, $exp]);
                        if ($stock > 0) {
                            $newId = (int)$db->lastInsertId();
                            $stmtMvtMag->execute([$newId, $stock, 'Import CSV — création + stock initial magasin', $uid]);
                        }
                        $ajoutes++;
                    }
                }
                $db->commit();
                } catch (Throwable $e) {
                    $db->rollBack();
                    if (!defined('IS_PROD') || !IS_PROD) {
                        $errors[] = 'Erreur base de données — import annulé (aucune ligne écrite) : ' . $e->getMessage();
                    } else {
                        error_log('PharmaCare import produits: ' . $e->getMessage());
                        $errors[] = 'Erreur base de données — import annulé (aucune ligne écrite). Contactez un administrateur.';
                    }
                    $ajoutes = 0; $maj = 0;
                }
            }
            fclose($fh);
        }
    }

    auditLog('produits.import', "CSV ($mode) : $ajoutes ajoutés, $maj mis à jour, $ignores ignorés, $avert avert., " . count($importCatsCrees) . " catégories créées, " . count($errors) . " erreurs");

    // ── Vue résultats ──
    layout_head('Résultat de l\'importation', 'produits');
    showFlash();
    ?>
    <div class="card" style="max-width:860px;margin:0 auto;">
      <div class="card-header">
        <div class="card-title">Résultat de l'importation</div>
        <a href="<?= url('produits', ['action' => 'import']) ?>" class="btn btn-ghost btn-sm"><?= icon('chevron-left', 14) ?> Nouvel import</a>
      </div>
      <div class="flex gap-8" style="flex-wrap:wrap;margin:12px 0;">
        <span class="badge badge-green">Ajoutés : <?= $ajoutes ?></span>
        <span class="badge badge-blue">Mis à jour : <?= $maj ?></span>
        <span class="badge badge-gray">Ignorés : <?= $ignores ?></span>
        <span class="badge badge-gold">Avertissements : <?= $avert ?></span>
        <span class="badge badge-purple">Catégories créées : <?= count($importCatsCrees) ?></span>
        <span class="badge badge-red">Erreurs : <?= count($errors) ?></span>
      </div>
      <?php if ($importCatsCrees): ?>
        <div class="table-wrap"><table>
          <thead><tr><th>Catégories créées automatiquement (à partir du CSV)</th></tr></thead>
          <tbody>
          <?php foreach (array_slice(array_unique($importCatsCrees), 0, 50) as $cn): ?>
            <tr><td class="text-sm"><?= e($cn) ?></td></tr>
          <?php endforeach; ?>
          <?php if (count(array_unique($importCatsCrees)) > 50): ?>
            <tr><td class="text-sm" style="color:var(--text3);">… et <?= count(array_unique($importCatsCrees)) - 50 ?> autres.</td></tr>
          <?php endif; ?>
          </tbody>
        </table></div>
      <?php endif; ?>
      <?php if ($errors): ?>
        <div class="table-wrap"><table>
          <thead><tr><th>Erreurs (ligne ignorée ou problème de fichier)</th></tr></thead>
          <tbody>
          <?php foreach (array_slice($errors, 0, 50) as $err): ?>
            <tr><td class="text-sm" style="color:var(--text2);"><?= e($err) ?></td></tr>
          <?php endforeach; ?>
          <?php if (count($errors) > 50): ?>
            <tr><td class="text-sm" style="color:var(--text3);">… et <?= count($errors) - 50 ?> autres.</td></tr>
          <?php endif; ?>
          </tbody>
        </table></div>
      <?php endif; ?>
      <?php if ($warnings): ?>
        <div class="table-wrap" style="margin-top:12px;"><table>
          <thead><tr><th>Avertissements (ligne importée malgré tout)</th></tr></thead>
          <tbody>
          <?php foreach (array_slice($warnings, 0, 50) as $w): ?>
            <tr><td class="text-sm" style="color:var(--text2);"><?= e($w) ?></td></tr>
          <?php endforeach; ?>
          <?php if (count($warnings) > 50): ?>
            <tr><td class="text-sm" style="color:var(--text3);">… et <?= count($warnings) - 50 ?> autres.</td></tr>
          <?php endif; ?>
          </tbody>
        </table></div>
      <?php endif; ?>
      <div class="modal-footer">
        <a href="<?= url('produits') ?>" class="btn btn-ghost">Retour à la liste</a>
        <a href="<?= url('produits', ['action' => 'import']) ?>" class="btn btn-primary"><?= icon('upload', 14) ?> Importer un autre fichier</a>
      </div>
    </div>
    <?php layout_foot();
    exit;
}

// ── Import CSV (GET) : formulaire ─────────────────────────────
if ($action === 'import') {
    if (!hasPermission('produits.ajouter')) {
        flash('Accès refusé.', 'error');
        header('Location: ' . url('produits'));
        exit;
    }
    layout_head('Importer des médicaments', 'produits');
    showFlash();
    ?>
    <div class="card" style="max-width:820px;margin:0 auto;">
      <div class="card-header">
        <div class="card-title">Importer des médicaments (CSV)</div>
        <a href="<?= url('produits') ?>" class="btn btn-ghost btn-sm"><?= icon('chevron-left', 14) ?> Retour</a>
      </div>
      <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="csrf" value="<?= csrf() ?>">
        <input type="hidden" name="action" value="import">
        <div class="form-grid">
          <div class="form-group full">
            <label>Fichier CSV *</label>
            <input type="file" name="file" accept=".csv,text/csv" required>
            <small>UTF-8 de préférence. Délimiteur virgule ou point-virgule (auto). Une ligne d'en-tête est attendue.</small>
          </div>
          <div class="form-group full">
            <label>Doublons (référence déjà existante)</label>
            <label style="display:flex;gap:8px;align-items:center;font-weight:normal;margin:4px 0;">
              <input type="radio" name="doublons" value="skip" checked> Ignorer les doublons (défaut)
            </label>
            <label style="display:flex;gap:8px;align-items:center;font-weight:normal;margin:4px 0;">
              <input type="radio" name="doublons" value="update"> Mettre à jour les articles existants
            </label>
          </div>
          <div class="form-group full">
            <small>
              Colonnes reconnues : <strong>nom</strong>* (requis), reference, unite, categorie, fournisseur,
              stock, seuil_alerte, prix_achat, prix_vente, tva, date_expiration (jj/mm/aaaa), description.
              <strong>stock</strong> = quantité livrée au <strong>magasin</strong> (dépôt central), pas aux pharmacies
              (celles-ci sont ravitaillées par transferts). Mode « mettre à jour » : la quantité s'<em>ajoute</em> au stock magasin existant.
              Catégorie inconnue → <strong>créée automatiquement</strong> (couleur attribuée automatiquement).
              Fournisseur inconnu → laissé vide (avertissement).
            </small>
          </div>
        </div>
        <div class="modal-footer">
          <a href="<?= url('produits', ['action' => 'import_template']) ?>" class="btn btn-ghost"><?= icon('download', 14) ?> Télécharger le modèle</a>
          <button type="submit" class="btn btn-primary"><?= icon('upload', 14) ?> Importer</button>
        </div>
      </form>
    </div>
    <?php layout_foot();
    exit;
}

// ── Suppression (POST + CSRF) ───────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete' && hasPermission('produits.archiver')) {
    verifyCsrf();
    $delId = (int)($_POST['id'] ?? 0);
    if ($delId) {
        $db->prepare("UPDATE produits SET actif=0 WHERE id=?")->execute([$delId]);
        flash('Médicament archivé.');
    }
    header('Location: ' . url('produits')); exit;
}

// ── Sauvegarde (ajout / modification) ───────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $pid        = (int)($_POST['id'] ?? 0);
    if (!hasPermission($pid ? 'produits.modifier' : 'produits.ajouter')) {
        flash('Accès refusé.', 'error');
        header('Location: ' . url('produits')); exit;
    }
    $nom        = trim($_POST['nom'] ?? '');
    $reference  = trim($_POST['reference'] ?? '');
    $reference  = $reference !== '' ? $reference : null; // vide -> NULL (UNIQUE n'accepte pas plusieurs '')
    $unite      = trim($_POST['unite'] ?? '');
    $unite      = $unite !== '' ? $unite : null; // vide -> NULL (unité de conditionnement optionnelle)
    $cat_id     = ($_POST['categorie_id'] ?? '') !== '' ? (int)$_POST['categorie_id'] : null; // 0 violerait la FK
    $fourn_id   = ($_POST['fournisseur_id'] ?? '') !== '' ? (int)$_POST['fournisseur_id'] : null;
    $description= trim($_POST['description'] ?? '');
    $stock      = (int)($_POST['stock'] ?? 0);
    $seuil      = (int)($_POST['seuil_alerte'] ?? 10);
    $prix_achat = (float)str_replace(',', '.', $_POST['prix_achat'] ?? 0);
    $prix_vente = (float)str_replace(',', '.', $_POST['prix_vente'] ?? 0);
    $expiry     = $_POST['date_expiration'] ?: null;

    if ($nom === '') {
        flash('Le nom du médicament est requis.', 'error');
        header('Location: ' . ($pid ? url('produits', ['action'=>'edit','id'=>$pid]) : url('produits', ['action'=>'add']))); exit;
    }

    try {
        if ($pid) {
            $db->prepare("UPDATE produits SET nom=?,reference=?,unite=?,categorie_id=?,fournisseur_id=?,description=?,stock=?,seuil_alerte=?,prix_achat=?,prix_vente=?,date_expiration=? WHERE id=?")
               ->execute([$nom,$reference,$unite,$cat_id,$fourn_id,$description,$stock,$seuil,$prix_achat,$prix_vente,$expiry,$pid]);
            flash('Médicament mis à jour.');
        } else {
            $db->prepare("INSERT INTO produits (nom,reference,unite,categorie_id,fournisseur_id,description,stock,seuil_alerte,prix_achat,prix_vente,date_expiration) VALUES (?,?,?,?,?,?,?,?,?,?,?)")
               ->execute([$nom,$reference,$unite,$cat_id,$fourn_id,$description,$stock,$seuil,$prix_achat,$prix_vente,$expiry]);
            flash('Médicament ajouté avec succès.');
        }
    } catch (PDOException $e) {
        // Contrainte UNIQUE (référence déjà utilisée) ou FK (catégorie/fournisseur).
        flashError($e, 'enregistrement produit');
        header('Location: ' . ($pid ? url('produits', ['action'=>'edit','id'=>$pid]) : url('produits', ['action'=>'add']))); exit;
    }
    header('Location: ' . url('produits')); exit;
}

$categories   = $db->query("SELECT * FROM categories ORDER BY nom")->fetchAll();
$fournisseurs = $db->query("SELECT * FROM fournisseurs WHERE actif=1 ORDER BY nom")->fetchAll();

// ── Vue formulaire ───────────────────────────────────────────
if (in_array($action, ['add','edit'])) {
    $p = ['nom'=>'','reference'=>'','unite'=>'','categorie_id'=>'','fournisseur_id'=>'','description'=>'',
          'stock'=>0,'seuil_alerte'=>10,'prix_achat'=>0,'prix_vente'=>0,'date_expiration'=>''];
    if ($id) {
        $stmt = $db->prepare("SELECT * FROM produits WHERE id=?");
        $stmt->execute([$id]);
        $fetched = $stmt->fetch();
        if ($fetched) $p = $fetched;
    }
    layout_head(($id ? 'Modifier' : 'Ajouter') . ' un médicament', 'produits');
    showFlash();
    ?>
    <div class="card" style="max-width:820px;margin:0 auto;">
      <div class="card-header">
        <div class="card-title"><?= $id ? 'Modifier médicament' : 'Nouveau médicament' ?></div>
        <a href="<?= url('produits') ?>" class="btn btn-ghost btn-sm">
          <?= icon('chevron-left',14) ?> Retour
        </a>
      </div>
      <form method="POST">
        <input type="hidden" name="csrf" value="<?= csrf() ?>">
        <input type="hidden" name="id" value="<?= $id ?>">
        <div class="form-grid">
          <div class="form-group full">
            <label>Nom du médicament *</label>
            <input type="text" name="nom" value="<?= e($p['nom']) ?>" required placeholder="ex: Paracétamol 500mg">
          </div>
          <div class="form-group">
            <label>Référence / Code-barres</label>
            <input type="text" name="reference" value="<?= e($p['reference']) ?>" placeholder="Ex: MED-001 ou 3760000000000" style="font-family:'DM Mono',monospace;">
          </div>
          <div class="form-group">
            <label>Unité</label>
            <input type="text" name="unite" value="<?= e($p['unite'] ?? '') ?>" list="dl-unite" placeholder="boîte, comprimé, plaquette…" maxlength="30">
            <datalist id="dl-unite">
              <option value="boîte"></option>
              <option value="comprimé"></option>
              <option value="plaquette"></option>
              <option value="flacon"></option>
              <option value="ampoule"></option>
              <option value="tube"></option>
              <option value="sachet"></option>
              <option value="gélule"></option>
              <option value="injection"></option>
            </datalist>
          </div>
          <div class="form-group">
            <label>Catégorie</label>
            <select name="categorie_id">
              <option value="">— Sélectionner —</option>
              <?php foreach ($categories as $c): ?>
              <option value="<?= $c['id'] ?>" <?= $p['categorie_id']==$c['id']?'selected':'' ?>><?= e($c['nom']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Fournisseur</label>
            <select name="fournisseur_id">
              <option value="">— Sélectionner —</option>
              <?php foreach ($fournisseurs as $f): ?>
              <option value="<?= $f['id'] ?>" <?= $p['fournisseur_id']==$f['id']?'selected':'' ?>><?= e($f['nom']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Stock actuel</label>
            <input type="number" name="stock" value="<?= (int)$p['stock'] ?>" min="0" required>
          </div>
          <div class="form-group">
            <label>Seuil d'alerte</label>
            <input type="number" name="seuil_alerte" value="<?= (int)$p['seuil_alerte'] ?>" min="1" required>
          </div>
          <div class="form-group">
            <label>Prix d'achat</label>
            <input type="number" name="prix_achat" value="<?= (float)$p['prix_achat'] ?>" step="1" min="0" required>
          </div>
          <div class="form-group">
            <label>Prix de vente</label>
            <input type="number" name="prix_vente" value="<?= (float)$p['prix_vente'] ?>" step="1" min="0" required>
          </div>
          <div class="form-group">
            <label>Date d'expiration</label>
            <input type="date" name="date_expiration" value="<?= e($p['date_expiration'] ?? '') ?>">
          </div>
          <div class="form-group full">
            <label>Description / Notes</label>
            <textarea name="description"><?= e($p['description']) ?></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <a href="<?= url('produits') ?>" class="btn btn-ghost">Annuler</a>
          <button type="submit" class="btn btn-primary">
            <?= icon('save',14) ?> Enregistrer
          </button>
        </div>
      </form>
    </div>
    <?php layout_foot(); exit;
}

// ── Vue liste ────────────────────────────────────────────────
require_once __DIR__ . '/../includes/pagination.php';
$q     = trim($_GET['q'] ?? '');
$where = "p.actif=1";
$params = [];
if ($q !== '') {
    $qEsc = str_replace(['\\','%','_'], ['\\\\','\%','\_'], $q);
    $where .= " AND (p.nom LIKE ? ESCAPE '\\\\' OR p.reference LIKE ? ESCAPE '\\\\')";
    $params[] = "%$qEsc%";
    $params[] = "%$qEsc%";
}

// ── Export Excel (mêmes filtres que la liste) ────────────────
if ($action === 'export') {
    require_once __DIR__ . '/../includes/export_xlsx.php';
    $st = $db->prepare("SELECT p.reference, p.nom, c.nom AS cat, f.nom AS fourn, p.unite,
                               p.stock, p.seuil_alerte, p.prix_achat, p.prix_vente, p.tva,
                               p.date_expiration, p.created_at
                        FROM produits p
                        LEFT JOIN categories c ON p.categorie_id=c.id
                        LEFT JOIN fournisseurs f ON p.fournisseur_id=f.id
                        WHERE $where
                        ORDER BY p.nom");
    $st->execute($params);
    $rows = [];
    foreach ($st->fetchAll() as $p) {
        $rows[] = [
            $p['reference'], $p['nom'], $p['cat'], $p['fourn'], $p['unite'],
            (int)$p['stock'], (int)$p['seuil_alerte'],
            (float)$p['prix_achat'], (float)$p['prix_vente'],
            ($p['tva'] === null || $p['tva'] === '') ? null : (float)$p['tva'],
            $p['date_expiration'] ? date('d/m/Y', strtotime($p['date_expiration'])) : '',
            date('d/m/Y H:i', strtotime($p['created_at'])),
        ];
    }
    export_xlsx_send('medicaments_' . date('Y-m-d'), 'Médicaments',
        ['Référence', 'Nom', 'Catégorie', 'Fournisseur', 'Unité', 'Stock', 'Seuil alerte',
         'Prix achat', 'Prix vente', 'TVA %', 'Expiration', 'Créé le'], $rows);
}

$perPage = 25;
$page    = max(1, (int)($_GET['page'] ?? 1));
$cntStmt = $db->prepare("SELECT COUNT(*) FROM produits p WHERE $where");
$cntStmt->execute($params);
$totalProduits = (int)$cntStmt->fetchColumn();
$offset  = paginateOffset($page, $perPage);

$query = "SELECT p.id, p.nom, p.reference, p.unite, p.stock, p.seuil_alerte,
                 p.prix_achat, p.prix_vente, p.date_expiration,
                 c.nom AS cat, f.nom AS fourn
          FROM produits p
          LEFT JOIN categories c ON p.categorie_id=c.id
          LEFT JOIN fournisseurs f ON p.fournisseur_id=f.id
          WHERE $where
          ORDER BY p.nom
          LIMIT $perPage OFFSET $offset";
$stmt   = $db->prepare($query);
$stmt->execute($params);
$produits = $stmt->fetchAll();

layout_head('Médicaments', 'produits');
showFlash();
?>
<div class="card">
  <div class="card-header">
    <div class="card-title">Liste des médicaments</div>
    <div class="flex gap-8">
      <form method="GET" style="display:flex;">
        <div class="search-box" style="min-width:420px;flex:1;max-width:640px;">
          <span style="color:var(--text3);display:flex;"><?= icon('search',14) ?></span>
          <input type="text" name="q" placeholder="Rechercher..." value="<?= e($q) ?>">
        </div>
      </form>
      <?php if (hasPermission('produits.ajouter')): ?>
      <a href="<?= url('produits', ['action'=>'import']) ?>" class="btn btn-ghost btn-sm"><?= icon('upload',14) ?> Importer</a>
      <a href="<?= url('produits', ['action'=>'export']) ?>" class="btn btn-ghost btn-sm" title="Exporter la liste au format Excel (.xlsx)"><?= icon('download',14) ?> Exporter</a>
      <a href="<?= url('produits', ['action'=>'add']) ?>" class="btn btn-primary btn-sm"><?= icon('plus',14) ?> Ajouter</a>
      <?php endif; ?>
    </div>
  </div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>N°</th><th>Médicament</th><th>Réf.</th><th>Unité</th><th>Catégorie</th>
          <th>Stock</th><th>P. Achat</th><th>P. Vente</th><th>Expiration</th>
          <th>Fournisseur</th><th>Statut</th>
          <?php if (hasPermission('produits.modifier') || hasPermission('produits.archiver')): ?><th>Actions</th><?php endif; ?>
        </tr>
      </thead>
      <tbody id="produits-tbody">
        <?php foreach ($produits as $i => $p):
          $ordre = $offset + $i + 1;
          if     ($p['stock'] == 0)                   { $b='badge-red';   $t='Rupture';    }
          elseif ($p['stock'] <= $p['seuil_alerte'])  { $b='badge-gold';  $t='Stock bas';  }
          else                                         { $b='badge-green'; $t='Disponible'; }
        ?>
        <tr>
          <td class="text-sm" style="color:var(--text3);"><?= $ordre ?></td>
          <td class="td-name"><?= e($p['nom']) ?></td>
          <td class="td-mono"><?= e($p['reference'] ?? '—') ?></td>
          <td class="text-sm"><?= e($p['unite'] ?? '—') ?></td>
          <td><span class="badge badge-gray"><?= e($p['cat'] ?? '—') ?></span></td>
          <td><strong><?= $p['stock'] ?></strong></td>
          <td class="fw-mono" style="color:var(--text3);"><?= fmtMoney((float)$p['prix_achat']) ?></td>
          <td class="fw-mono c-teal"><?= fmtMoney((float)$p['prix_vente']) ?></td>
          <td class="text-sm"><?= $p['date_expiration'] ? date('d/m/Y', strtotime($p['date_expiration'])) : '—' ?></td>
          <td class="text-sm"><?= e($p['fourn'] ?? '—') ?></td>
          <td><span class="badge <?= $b ?>"><?= $t ?></span></td>
          <?php if (hasPermission('produits.modifier') || hasPermission('produits.archiver')): ?>
          <td>
            <div class="flex gap-8">
              <a href="<?= url('produits', ['action'=>'edit','id'=>$p['id']], $p['nom'] ?? null) ?>" class="btn btn-ghost btn-xs"><?= icon('edit',13) ?></a>
              <button class="btn btn-danger btn-xs"
                onclick="confirmDeletePost('delete','<?= (int)$p['id'] ?>','Archiver ce médicament ?')">
                <?= icon('trash',13) ?>
              </button>
            </div>
          </td>
          <?php endif; ?>
        </tr>
        <?php endforeach; ?>
        <?php if (!$produits): ?>
        <tr><td colspan="11">
          <div class="empty">
            <div style="color:var(--text3);margin-bottom:8px;"><?= icon('pill',36) ?></div>
            <div>Aucun médicament trouvé</div>
          </div>
        </td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  <?= renderPagination($page, $perPage, $totalProduits, ['q'=>$q]) ?>
</div>
<?php layout_foot(); ?>
