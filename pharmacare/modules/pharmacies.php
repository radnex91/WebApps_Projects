<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/pagination.php';
require_once __DIR__ . '/../config/settings.php';

// Lecture (liste) : pharmacies.voir ; modifications : pharmacies.gerer
if (!hasPermission('pharmacies.voir')) {
    requirePermission('pharmacies.voir'); // redirige proprement
}
$db     = getDB();
$uid    = (int)currentUser()['id'];
$action = $_GET['action'] ?? 'list';
$id     = (int)($_GET['id'] ?? 0);

// ═══════════════════════════════════════════════════════════════
//  Import CSV : stock de produits DANS une pharmacie
//  (gabarit complet de l'import des médicaments : les produits
//   introuvables sont créés avec toutes leurs colonnes ; les
//   existants ne voient que leur stock/seuil pharmacie modifiés)
// ═══════════════════════════════════════════════════════════════

/** Normalise un en-tête CSV : minuscules, sans accents, ponctuation → espaces. */
function pharmacie_csv_normalize(string $s): string {
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
function pharmacie_csv_cell(string $s): string {
    $s = trim($s);
    if ($s !== '' && !mb_check_encoding($s, 'UTF-8')) {
        $conv = @mb_convert_encoding($s, 'UTF-8', 'CP1252');
        if ($conv !== false && $conv !== '') $s = $conv;
    }
    return $s;
}
/** Parse un nombre (virgule ou point décimal, espaces retirés). */
function pharmacie_csv_num(string $s): float {
    $s = str_replace([' ', "\xc2\xa0"], '', trim($s));
    $s = str_replace(',', '.', $s);
    return (float)$s;
}
/** Parse une date (jj/mm/aaaa, aaaa-mm-jj, …) → Y-m-d ou null si invalide. */
function pharmacie_csv_parse_date(string $s): ?string {
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
$PHARMACIE_CSV_FIELDS = [
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
    header('Content-Disposition: attachment; filename="modele_import_stock_pharmacie.csv"');
    header('Cache-Control: no-store');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // BOM UTF-8 pour Excel
    fputcsv($out, ['nom', 'reference', 'unite', 'categorie', 'fournisseur', 'stock', 'seuil_alerte', 'prix_achat', 'prix_vente', 'tva', 'date_expiration', 'description'], ';');
    fputcsv($out, ['Paracétamol 500mg', 'MED-001', 'comprimé', 'Antalgiques', 'PharmaPlus', '120', '10', '150', '200', '9', '31/12/2027', 'Boîte de 16 comprimés'], ';');
    fputcsv($out, ['Amoxicilline 1g', 'MED-002', 'comprimé', 'Antibiotiques', '', '50', '10', '320', '450', '9', '', ''], ';');
    fclose($out);
    exit;
}

// ── POST ─────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $sub = $_POST['action'] ?? '';

    // ── Import CSV : stock de produits dans une pharmacie ──
    if ($sub === 'import') {
        requirePermission('pharmacies.gerer');
        $pid = (int)($_POST['pharmacie_id'] ?? 0);
        $file     = $_FILES['file'] ?? null;
        $ajoutes = 0; $maj = 0; $ignores = 0; $avert = 0;
        $errors   = [];   // erreurs bloquantes (ligne ignorée / fichier)
        $warnings = [];   // avertissements (ligne importée malgré tout)
        $catsCrees = [];  // catégories créées à la volée (noms)

        // La pharmacie cible doit exister et être active
        $stmtPh = $db->prepare("SELECT id, nom FROM pharmacies WHERE id=? AND actif=1");
        $stmtPh->execute([$pid]);
        $pharmacie = $stmtPh->fetch();
        if (!$pharmacie) {
            flash('Pharmacie cible introuvable ou inactive.', 'error');
            header('Location: ' . url('pharmacies', ['action' => 'import'])); exit;
        }
        // Création des produits absents (toutes colonnes) : exige produits.ajouter ;
        // sans elle, seuls les produits déjà connus peuvent être importés.
        $canCreate = hasPermission('produits.ajouter');

        // Carte alias normalisé => champ canonique
        $aliasMap = [];
        foreach ($PHARMACIE_CSV_FIELDS as $field => $aliases) {
            foreach ($aliases as $a) $aliasMap[pharmacie_csv_normalize($a)] = $field;
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
                        $key = pharmacie_csv_normalize(pharmacie_csv_cell((string)$h));
                        if (isset($aliasMap[$key])) $colMap[$aliasMap[$key]] = (int)$i;
                    }
                    if (!isset($colMap['stock'])) {
                        $errors[] = 'Colonne « stock » manquante. En-têtes détectés : ' . implode(', ', array_map('trim', $header));
                    } elseif (!isset($colMap['reference']) && !isset($colMap['nom'])) {
                        $errors[] = 'Colonne « reference » ou « nom » requise pour identifier le médicament. En-têtes détectés : ' . implode(', ', array_map('trim', $header));
                    } else {
                        $canProcess = true;
                    }
                }
                if ($canProcess) {
                    // Produits actifs : recherche par référence (exacte) d'abord,
                    // fallback par nom (insensible à la casse).
                    $stmtByRef  = $db->prepare("SELECT id FROM produits WHERE reference=? AND actif=1 LIMIT 1");
                    $stmtByName = $db->prepare("SELECT id FROM produits WHERE LOWER(nom)=LOWER(?) AND actif=1 LIMIT 1");
                    // Référence portée par un produit archivé : ni reprise (ventes
                    // interdites), ni création (UNIQUE(reference)) → ligne ignorée.
                    $stmtArchRef = $db->prepare("SELECT id, actif FROM produits WHERE reference=? LIMIT 1");
                    // Écriture : même sémantique que la page « Stock par pharmacie »
                    $stmtUp = $db->prepare("
                        INSERT INTO produit_pharmacie (produit_id, pharmacie_id, stock, seuil_alerte)
                        VALUES (?, ?, ?, ?)
                        ON DUPLICATE KEY UPDATE stock = VALUES(stock), seuil_alerte = VALUES(seuil_alerte)
                    ");
                    // Création des produits absents, toutes colonnes (gabarit de
                    // l'import médicaments). La quantité du CSV va dans la pharmacie
                    // cible, pas dans le magasin ; le stock global (miroir pharmacie
                    // principale) reste 0 — il est resynchronisé plus bas si pid=1.
                    $stmtInsPdt = $db->prepare("
                        INSERT INTO produits (nom, reference, unite, categorie_id, fournisseur_id, description,
                                             stock, stock_magasin, seuil_alerte, prix_achat, prix_vente, tva, date_expiration)
                        VALUES (?,?,?,?,?,?,0,0,?,?,?,?,?)
                    ");
                    $stmtCat    = $db->prepare("SELECT id FROM categories WHERE LOWER(nom)=LOWER(?) LIMIT 1");
                    $stmtFourn  = $db->prepare("SELECT id FROM fournisseurs WHERE LOWER(nom)=LOWER(?) ORDER BY actif DESC LIMIT 1");
                    $stmtCatIns = $db->prepare("INSERT INTO categories (nom,couleur) VALUES (?,?)");
                    $stmtSeuil  = $db->prepare("SELECT seuil_alerte FROM produit_pharmacie WHERE produit_id=? AND pharmacie_id=?");
                    // Palette de couleurs des catégories créées à la volée (mêmes
                    // teintes que categories.php / l'import médicaments).
                    $IMPORT_PALETTE = ['#00c9a7','#4895ef','#f0b429','#ef4444','#9b59b6','#e74c3c','#e67e22','#1abc9c','#3498db','#e91e63'];
                    $importCatIdx = 0;
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
                            return isset($row[$i]) ? pharmacie_csv_cell((string)$row[$i]) : '';
                        };
                        // Stock pharmacie : colonne requise — validée AVANT toute
                        // création de produit pour ne pas laisser de fiche sans stock
                        $stockCell = $cell($colMap['stock']);
                        if ($stockCell === '') { $ignores++; $errors[] = "Ligne $ligne : stock vide, ligne ignorée."; continue; }
                        // Identification du produit : référence exacte d'abord, nom ensuite
                        $reference = isset($colMap['reference']) ? $cell($colMap['reference']) : '';
                        $nom = isset($colMap['nom']) ? $cell($colMap['nom']) : '';
                        $produitId = null;
                        if ($reference !== '') {
                            $stmtByRef->execute([$reference]); $r = $stmtByRef->fetch();
                            if ($r) $produitId = (int)$r['id'];
                        }
                        if ($produitId === null && $nom !== '') {
                            $stmtByName->execute([$nom]); $r = $stmtByName->fetch();
                            if ($r) $produitId = (int)$r['id'];
                        }
                        // Produit introuvable → création avec toutes ses colonnes
                        if ($produitId === null) {
                            // Référence déjà portée par un produit archivé : ni reprise
                            // (ventes interdites), ni création (UNIQUE(reference)) → ligne
                            // ignorée. Test AVANT les autres garde-fous : les CSV de
                            // comptage sont souvent « référence + stock » sans nom.
                            if ($reference !== '') {
                                $stmtArchRef->execute([$reference]); $arch = $stmtArchRef->fetch();
                                if ($arch && !(int)$arch['actif']) {
                                    $ignores++;
                                    $errors[] = "Ligne $ligne : référence « $reference » déjà utilisée par un médicament archivé, ligne ignorée.";
                                    continue;
                                }
                            }
                            if (!$canCreate) {
                                $ignores++;
                                $errors[] = "Ligne $ligne : produit introuvable et création non autorisée (permission « Ajouter des médicaments » requise), ligne ignorée.";
                                continue;
                            }
                            if ($nom === '') {
                                $ignores++;
                                $errors[] = "Ligne $ligne : nom vide, produit introuvable, ligne ignorée.";
                                continue;
                            }
                            // Catégorie : trouvée par nom, sinon créée à la volée
                            $catId = null;
                            if (isset($colMap['categorie'])) {
                                $cn = $cell($colMap['categorie']);
                                if ($cn !== '') {
                                    $stmtCat->execute([$cn]); $r = $stmtCat->fetch();
                                    if ($r) {
                                        $catId = (int)$r['id'];
                                    } else {
                                        $stmtCatIns->execute([$cn, $IMPORT_PALETTE[$importCatIdx % count($IMPORT_PALETTE)]]);
                                        $catId = (int)$db->lastInsertId();
                                        $importCatIdx++;
                                        $catsCrees[] = $cn;
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
                            $unite = isset($colMap['unite']) ? $cell($colMap['unite']) : '';
                            $unite = $unite !== '' ? $unite : null;
                            $desc = isset($colMap['description']) ? $cell($colMap['description']) : '';
                            $paCell = isset($colMap['prix_achat']) ? $cell($colMap['prix_achat']) : '';
                            $pa = $paCell !== '' ? pharmacie_csv_num($paCell) : 0.0;
                            $pvCell = isset($colMap['prix_vente']) ? $cell($colMap['prix_vente']) : '';
                            $pv = $pvCell !== '' ? pharmacie_csv_num($pvCell) : 0.0;
                            $tvaCell = isset($colMap['tva']) ? $cell($colMap['tva']) : '';
                            $tva = $tvaCell !== '' ? pharmacie_csv_num($tvaCell) : 9.00;
                            $exp = null;
                            if (isset($colMap['date_expiration'])) {
                                $dCell = $cell($colMap['date_expiration']);
                                if ($dCell !== '') {
                                    $exp = pharmacie_csv_parse_date($dCell);
                                    if ($exp === null) { $avert++; $warnings[] = "Ligne $ligne : date « $dCell » invalide → expiration vide."; }
                                }
                            }
                            $seuilCell = isset($colMap['seuil_alerte']) ? $cell($colMap['seuil_alerte']) : '';
                            $seuil = $seuilCell !== '' ? max(0, (int)pharmacie_csv_num($seuilCell)) : 10;
                            $stmtInsPdt->execute([$nom, $reference !== '' ? $reference : null, $unite, $catId, $fournId,
                                                   $desc, $seuil, $pa, $pv, $tva, $exp]);
                            $produitId = (int)$db->lastInsertId();
                            $ajoutes++;
                        }
                        // Stock pharmacie : remplace la valeur actuelle
                        $stock = max(0, (int)pharmacie_csv_num($stockCell));
                        // Seuil d'alerte pharmacie : colonne absente du CSV → la ligne
                        // pharmacie garde son seuil actuel (10 par défaut à la création)
                        $seuilUpdCell = isset($colMap['seuil_alerte']) ? $cell($colMap['seuil_alerte']) : '';
                        if ($seuilUpdCell !== '') {
                            $seuilUpd = max(0, (int)pharmacie_csv_num($seuilUpdCell));
                        } else {
                            $stmtSeuil->execute([$produitId, $pid]);
                            $existingSeuil = $stmtSeuil->fetchColumn();
                            $seuilUpd = ($existingSeuil !== false) ? (int)$existingSeuil : 10;
                        }
                        $stmtUp->execute([$produitId, $pid, $stock, $seuilUpd]);
                        $maj++;
                    }
                    $db->commit();
                    } catch (Throwable $e) {
                        $db->rollBack();
                        if (!defined('IS_PROD') || !IS_PROD) {
                            $errors[] = 'Erreur base de données — import annulé (aucune ligne écrite) : ' . $e->getMessage();
                        } else {
                            error_log('PharmaCare import stock pharmacie: ' . $e->getMessage());
                            $errors[] = 'Erreur base de données — import annulé (aucune ligne écrite). Contactez un administrateur.';
                        }
                        $ajoutes = 0; $maj = 0;
                    }
                    // Pour la pharmacie principale, on resynchronise produits.stock
                    // (même règle que la page « Stock par pharmacie »)
                    if ($pid === 1 && $maj > 0) {
                        $db->prepare("UPDATE produits p
                            JOIN produit_pharmacie pp ON pp.produit_id = p.id AND pp.pharmacie_id = 1
                            SET p.stock = pp.stock")->execute();
                    }
                }
                fclose($fh);
            }
        }

        auditLog('pharmacies.import_stock', "CSV stock « {$pharmacie['nom']} » : $ajoutes produits créés, $maj lignes stock, $ignores ignorés, $avert avert., " . count($catsCrees) . " catég. créées, " . count($errors) . " erreurs");

        // ── Vue résultats ──
        layout_head('Résultat de l\'importation', 'pharmacies');
        showFlash();
        ?>
        <div class="card" style="max-width:860px;margin:0 auto;">
          <div class="card-header">
            <div class="card-title">Résultat de l'importation — stock <?= e($pharmacie['nom']) ?></div>
            <a href="<?= url('pharmacies', ['action' => 'import']) ?>" class="btn btn-ghost btn-sm"><?= icon('chevron-left', 14) ?> Nouvel import</a>
          </div>
          <div class="flex gap-8" style="flex-wrap:wrap;margin:12px 0;">
            <span class="badge badge-green">Produits créés : <?= $ajoutes ?></span>
            <span class="badge badge-blue">Lignes stock importées : <?= $maj ?></span>
            <span class="badge badge-gray">Ignorées : <?= $ignores ?></span>
            <span class="badge badge-gold">Avertissements : <?= $avert ?></span>
            <?php if (count($catsCrees) > 0): ?><span class="badge badge-purple">Catégories créées : <?= count($catsCrees) ?></span><?php endif; ?>
            <span class="badge badge-red">Erreurs : <?= count($errors) ?></span>
          </div>
          <?php if ($catsCrees): ?>
            <div class="table-wrap"><table>
              <thead><tr><th>Catégories créées automatiquement (à partir du CSV)</th></tr></thead>
              <tbody>
              <?php foreach (array_slice(array_unique($catsCrees), 0, 50) as $cn): ?>
                <tr><td class="text-sm"><?= e($cn) ?></td></tr>
              <?php endforeach; ?>
              <?php if (count(array_unique($catsCrees)) > 50): ?>
                <tr><td class="text-sm" style="color:var(--text3);">… et <?= count(array_unique($catsCrees)) - 50 ?> autres.</td></tr>
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
            <a href="<?= url('pharmacies', ['action' => 'stock', 'id' => $pid]) ?>" class="btn btn-ghost"><?= icon('boxes', 14) ?> Voir le stock de la pharmacie</a>
            <a href="<?= url('pharmacies', ['action' => 'import']) ?>" class="btn btn-primary"><?= icon('upload', 14) ?> Importer un autre fichier</a>
          </div>
          <small style="display:block;padding:0 16px 14px;color:var(--text3);">
            L'import <strong>remplace</strong> le stock des produits listés (il ne s'additionne pas). Les produits absents du CSV gardent leur stock actuel.
            Les produits introuvables sont <strong>créés</strong> avec toutes leurs colonnes (prix, catégorie, fournisseur…) ; pour un produit existant, seule la fiche de stock de la pharmacie est modifiée.
          </small>
        </div>
        <?php layout_foot();
        exit;
    }

    // ── Toggle actif/désactivé ──
    if ($sub === 'toggle') {
        requirePermission('pharmacies.gerer');
        $tid = (int)($_POST['id'] ?? 0);
        if ($tid) {
            // On ne permet pas de désactiver la pharmacie principale (id=1)
            $db->prepare("UPDATE pharmacies SET actif = 1-actif WHERE id=? AND id <> 1")->execute([$tid]);
            flash('Statut de la pharmacie mis à jour.');
        }
        header('Location: ' . url('pharmacies')); exit;
    }

    // ── Mise à jour du stock par pharmacie ──
    if ($sub === 'update_stock') {
        requirePermission('pharmacies.gerer');
        $pid = (int)($_POST['pharmacie_id'] ?? 0);
        $stocks = $_POST['stock'] ?? [];
        if ($pid) {
            $stmtUp = $db->prepare("
                INSERT INTO produit_pharmacie (produit_id, pharmacie_id, stock, seuil_alerte)
                VALUES (?, ?, ?, 10)
                ON DUPLICATE KEY UPDATE stock = VALUES(stock)
            ");
            foreach ($stocks as $produitId => $qte) {
                $stmtUp->execute([(int)$produitId, $pid, max(0, (int)$qte)]);
            }
            // Pour la pharmacie principale, on resynchronise produits.stock
            if ($pid === 1) {
                $db->prepare("UPDATE produits p
                    JOIN produit_pharmacie pp ON pp.produit_id = p.id AND pp.pharmacie_id = 1
                    SET p.stock = pp.stock")->execute();
            }
            flash('Stock de la pharmacie mis à jour.');
        }
        $redirPage = max(1, (int)($_POST['page'] ?? 1));
        header('Location: ' . url('pharmacies', ['action' => 'stock', 'id' => $pid, 'page' => $redirPage])); exit;
    }

    // ── Transfert inter-pharmacies (1 étape : débit source, crédit destination) ──
    if ($sub === 'transfert_ph') {
        requirePermission('pharmacies.gerer');
        $sourceId = (int)($_POST['pharmacie_source'] ?? 0);
        $destId   = (int)($_POST['pharmacie_dest'] ?? 0);
        $note     = trim($_POST['note'] ?? '');

        // Les deux pharmacies doivent exister, être actives et distinctes
        $stSrc = $db->prepare("SELECT id, nom FROM pharmacies WHERE id=? AND actif=1");
        $stSrc->execute([$sourceId]);
        $source = $stSrc->fetch();
        $stDst = $db->prepare("SELECT id, nom FROM pharmacies WHERE id=? AND actif=1");
        $stDst->execute([$destId]);
        $dest = $stDst->fetch();
        if (!$source || !$dest) {
            flash('Pharmacie source ou destination introuvable/inactive.', 'error');
            header('Location: ' . url('pharmacies', ['action' => 'transfert'])); exit;
        }
        if ($sourceId === $destId) {
            flash('La pharmacie source et la destination doivent être différentes.', 'error');
            header('Location: ' . url('pharmacies', ['action' => 'transfert'])); exit;
        }

        // Lignes valides (produit_id + quantité > 0)
        $produits_ids = $_POST['produit_id'] ?? [];
        $quantites    = $_POST['quantite']    ?? [];
        $lignesValides = [];
        for ($i = 0; $i < count($produits_ids); $i++) {
            $pid = (int)$produits_ids[$i];
            $qte = (int)$quantites[$i];
            if ($pid > 0 && $qte > 0) $lignesValides[] = [$pid, $qte];
        }
        if (!$lignesValides) {
            flash('Aucune ligne valide pour le transfert.', 'error');
            header('Location: ' . url('pharmacies', ['action' => 'transfert'])); exit;
        }

        try {
            $db->beginTransaction();

            // Disponibilité du stock source (message d'erreur détaillé par produit)
            $stStock = $db->prepare("SELECT p.nom, COALESCE(pp.stock, 0) AS stock
                                     FROM produits p
                                     LEFT JOIN produit_pharmacie pp ON pp.produit_id = p.id AND pp.pharmacie_id = ?
                                     WHERE p.id = ?");
            $insuffisants = [];
            foreach ($lignesValides as [$pid, $qte]) {
                $stStock->execute([$sourceId, $pid]);
                $p = $stStock->fetch();
                if (!$p) {
                    $insuffisants[] = "Produit #$pid introuvable";
                } elseif ((int)$p['stock'] < $qte) {
                    $insuffisants[] = e($p['nom']) . " (dispo: {$p['stock']}, demandé: $qte)";
                }
            }
            if ($insuffisants) {
                $db->rollBack();
                flash('Stock insuffisant à la pharmacie « ' . $source['nom'] . ' » : ' . implode(' ; ', $insuffisants), 'error');
                header('Location: ' . url('pharmacies', ['action' => 'transfert', 'source' => $sourceId, 'dest' => $destId])); exit;
            }

            // Entête de transfert
            $ref = genRef('TVP');
            $db->prepare("INSERT INTO transferts_pharmacies (reference, utilisateur_id, pharmacie_source_id, pharmacie_dest_id, note)
                          VALUES (?,?,?,?,?)")
               ->execute([$ref, $uid, $sourceId, $destId, $note !== '' ? $note : null]);
            $transfertId = (int)$db->lastInsertId();

            $stmtNom    = $db->prepare("SELECT nom FROM produits WHERE id=?");
            // Garde atomique : le débit source ne passe que si le stock suffit
            // (protège contre une vente simultanée sur la pharmacie source).
            $stmtDecSrc = $db->prepare("UPDATE produit_pharmacie SET stock = stock - ?
                                        WHERE produit_id = ? AND pharmacie_id = ? AND stock >= ?");
            // Crédit destination (crée la ligne si nécessaire, seuil par défaut 10)
            $stmtIncDst = $db->prepare("
                INSERT INTO produit_pharmacie (produit_id, pharmacie_id, stock, seuil_alerte)
                VALUES (?, ?, ?, 10)
                ON DUPLICATE KEY UPDATE stock = stock + VALUES(stock)
            ");
            // Sync produits.stock si la pharmacie principale (1) est impliquée
            $syncMain = fn(int $phId): ?PDOStatement =>
                $phId === 1
                    ? $db->prepare("UPDATE produits p
                                    JOIN produit_pharmacie pp ON pp.produit_id = p.id AND pp.pharmacie_id = 1
                                    SET p.stock = pp.stock WHERE p.id = ?")
                    : null;
            $stmtSyncSrc = $syncMain($sourceId);
            $stmtSyncDst = $syncMain($destId);
            $stmtLigne   = $db->prepare("INSERT INTO transfert_pharmacie_lignes (transfert_id, produit_id, produit_nom, quantite) VALUES (?,?,?,?)");
            $stmtMvtOut  = $db->prepare("INSERT INTO mouvements_stock (produit_id, type, quantite, motif, utilisateur_id) VALUES (?,'sortie',?,?,?)");
            $stmtMvtIn   = $db->prepare("INSERT INTO mouvements_stock (produit_id, type, quantite, motif, utilisateur_id) VALUES (?,'entrée',?,?,?)");

            foreach ($lignesValides as [$pid, $qte]) {
                $stmtNom->execute([$pid]);
                $nom = $stmtNom->fetchColumn() ?: ('Produit #' . $pid);

                // Débit source (garde) puis crédit destination
                $stmtDecSrc->execute([$qte, $pid, $sourceId, $qte]);
                if ($stmtDecSrc->rowCount() === 0) {
                    throw new Exception('Stock de « ' . $source['nom'] . ' » devenu insuffisant pour ' . $nom . ' (demandé : ' . $qte . ').');
                }
                $stmtIncDst->execute([$pid, $destId, $qte]);
                if ($stmtSyncSrc) $stmtSyncSrc->execute([$pid]);
                if ($stmtSyncDst) $stmtSyncDst->execute([$pid]);

                $stmtLigne->execute([$transfertId, $pid, $nom, $qte]);
                $stmtMvtOut->execute([$pid, $qte, 'Transfert ' . $ref . ' de « ' . $source['nom'] . ' » vers « ' . $dest['nom'] . ' »', $uid]);
                $stmtMvtIn->execute([$pid, $qte, 'Transfert ' . $ref . ' reçu de « ' . $source['nom'] . ' »', $uid]);
            }

            $db->commit();
            auditLog('pharmacies.transfert', sprintf('Transfert %s : %d ligne(s) de « %s » vers « %s »', $ref, count($lignesValides), $source['nom'], $dest['nom']));
            flash('Transfert ' . $ref . ' effectué — « ' . $dest['nom'] . ' » approvisionnée depuis « ' . $source['nom'] . ' ».');
            header('Location: ' . url('pharmacies', ['action' => 'bon', 'id' => $transfertId])); exit;
        } catch (Exception $e) {
            $db->rollBack();
            flashError($e, 'transfert inter-pharmacies');
            header('Location: ' . url('pharmacies', ['action' => 'transfert', 'source' => $sourceId, 'dest' => $destId])); exit;
        }
    }

    // ── Création / édition ──
    requirePermission('pharmacies.gerer');
    $nom       = trim($_POST['nom'] ?? '');
    $adresse   = trim($_POST['adresse'] ?? '');
    $telephone = trim($_POST['telephone'] ?? '');

    if ($nom === '') {
        flash('Le nom de la pharmacie est obligatoire.', 'error');
        header('Location: ' . ($id ? url('pharmacies', ['action' => 'edit', 'id' => $id])
                                   : url('pharmacies', ['action' => 'add']))); exit;
    }

    if ($id) {
        $db->prepare("UPDATE pharmacies SET nom=?, adresse=?, telephone=? WHERE id=?")
           ->execute([$nom, $adresse, $telephone, $id]);
        flash('Pharmacie mise à jour.');
    } else {
        $db->prepare("INSERT INTO pharmacies (nom, adresse, telephone, actif) VALUES (?,?,?,1)")
           ->execute([$nom, $adresse, $telephone]);
        $newId = (int)$db->lastInsertId();
        // Initialise une ligne de stock à 0 pour chaque produit actif (facilite la saisie)
        $db->prepare("INSERT IGNORE INTO produit_pharmacie (produit_id, pharmacie_id, stock, seuil_alerte)
                      SELECT p.id, ?, 0, 10 FROM produits p WHERE p.actif = 1")->execute([$newId]);
        flash('Pharmacie créée. Pensez à régler son stock initial via l\'onglet Stock.');
    }
    header('Location: ' . url('pharmacies')); exit;
}

// ── Vue : import CSV (formulaire) ────────────────────────────
if ($action === 'import') {
    requirePermission('pharmacies.gerer');
    $pharmaciesList = $db->query("SELECT id, nom FROM pharmacies WHERE actif=1 ORDER BY id")->fetchAll();
    $pidSel = (int)($_GET['pharmacie_id'] ?? 0);
    layout_head('Importer un stock', 'pharmacies');
    showFlash();
    ?>
    <div class="card" style="max-width:820px;margin:0 auto;">
      <div class="card-header">
        <div class="card-title">Importer le stock d'une pharmacie (CSV)</div>
        <a href="<?= url('pharmacies') ?>" class="btn btn-ghost btn-sm"><?= icon('chevron-left', 14) ?> Retour</a>
      </div>
      <?php if (!$pharmaciesList): ?>
      <div class="card-pad"><p>Aucune pharmacie active. Créez d'abord une pharmacie.</p></div>
      <?php else: ?>
      <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="csrf" value="<?= csrf() ?>">
        <input type="hidden" name="action" value="import">
        <div class="form-grid">
          <div class="form-group full">
            <label>Pharmacie cible *</label>
            <select name="pharmacie_id" required>
              <?php foreach ($pharmaciesList as $ph): ?>
                <option value="<?= (int)$ph['id'] ?>" <?= $pidSel === (int)$ph['id'] ? 'selected' : '' ?>><?= e($ph['nom']) ?></option>
              <?php endforeach; ?>
            </select>
            <small>Le stock du CSV sera écrit pour cette pharmacie.</small>
          </div>
          <div class="form-group full">
            <label>Fichier CSV *</label>
            <input type="file" name="file" accept=".csv,text/csv" required>
            <small>UTF-8 de préférence. Délimiteur virgule ou point-virgule (auto). Une ligne d'en-tête est attendue.</small>
          </div>
          <div class="form-group full">
            <small>
              Colonnes reconnues : <strong>reference</strong> ou <strong>nom</strong> (pour identifier le médicament),
              <strong>stock</strong>* (quantité, requis), seuil_alerte, unite, categorie, fournisseur,
              prix_achat, prix_vente, tva, date_expiration (jj/mm/aaaa), description.
              Produit introuvable → <strong>créé</strong> avec toutes ses colonnes (catégorie créée à la volée,
              fournisseur inconnu → laissé vide avec avertissement) ; la quantité du CSV alimente le stock
              de la pharmacie cible. Produit déjà connu (référence exacte ou nom) → seule sa ligne de stock
              pharmacie est remplacée, sa fiche n'est pas modifiée.
              L'import <strong>remplace</strong> le stock actuel des produits listés — les produits absents du CSV
              gardent leur stock. Pour la pharmacie principale, le stock global est resynchronisé automatiquement.
              <?php if (!hasPermission('produits.ajouter')): ?>
              <br><strong style="color:var(--text2);">Sans la permission « Ajouter des médicaments », seuls les produits déjà connus peuvent être importés.</strong>
              <?php endif; ?>
            </small>
          </div>
        </div>
        <div class="modal-footer">
          <a href="<?= url('pharmacies', ['action' => 'import_template']) ?>" class="btn btn-ghost"><?= icon('download', 14) ?> Télécharger le modèle</a>
          <button type="submit" class="btn btn-primary"><?= icon('upload', 14) ?> Importer</button>
        </div>
      </form>
      <?php endif; ?>
    </div>
    <?php layout_foot();
    exit;
}

// ── Vues : ajout / édition ────────────────────────────────────
if (in_array($action, ['add', 'edit'])) {
    requirePermission('pharmacies.gerer');
    $p = ['nom' => '', 'adresse' => '', 'telephone' => ''];
    if ($id) {
        $stmt = $db->prepare("SELECT * FROM pharmacies WHERE id=?");
        $stmt->execute([$id]);
        if ($r = $stmt->fetch()) $p = $r;
    }
    layout_head(($id ? 'Modifier' : 'Ajouter') . ' pharmacie', 'pharmacies');
    showFlash();
    ?>
    <div class="card" style="max-width:620px;margin:0 auto;">
      <div class="card-header">
        <div class="card-title"><?= $id ? 'Modifier la pharmacie' : 'Nouvelle pharmacie' ?></div>
        <a href="<?= url('pharmacies') ?>" class="btn btn-ghost btn-sm"><?= icon('chevron-left', 14) ?> Retour</a>
      </div>
      <form method="POST">
        <input type="hidden" name="csrf" value="<?= csrf() ?>">
        <input type="hidden" name="id" value="<?= $id ?>">
        <div class="form-grid">
          <div class="form-group"><label>Nom *</label>
            <input type="text" name="nom" value="<?= e($p['nom']) ?>" required></div>
          <div class="form-group"><label>Téléphone</label>
            <input type="text" name="telephone" value="<?= e($p['telephone']) ?>"></div>
          <div class="form-group" style="grid-column:1/-1;"><label>Adresse</label>
            <input type="text" name="adresse" value="<?= e($p['adresse']) ?>"></div>
        </div>
        <div class="modal-footer">
          <a href="<?= url('pharmacies') ?>" class="btn btn-ghost">Annuler</a>
          <button type="submit" class="btn btn-primary"><?= icon('save', 14) ?> Enregistrer</button>
        </div>
      </form>
    </div>
    <?php layout_foot(); exit;
}

// ── Vue : stock par pharmacie ─────────────────────────────────
if ($action === 'stock') {
    requirePermission('pharmacies.gerer');
    $pharmacies = $db->query("SELECT id, nom FROM pharmacies WHERE actif=1 ORDER BY id")->fetchAll();
    $pid = $id ?: (int)($_GET['pharmacie_id'] ?? ($pharmacies[0]['id'] ?? 0));

    $produits = [];
    $total = 0;
    if ($pid) {
        $stC = $db->prepare("SELECT COUNT(*) FROM produits WHERE actif = 1");
        $stC->execute();
        $total = (int)$stC->fetchColumn();

        $perPage = 50;
        $page    = max(1, (int)($_GET['page'] ?? 1));
        $offset  = paginateOffset($page, $perPage);

        $stmt = $db->prepare("
            SELECT p.id, p.nom, p.reference, COALESCE(pp.stock, 0) AS stock
            FROM produits p
            LEFT JOIN produit_pharmacie pp ON pp.produit_id = p.id AND pp.pharmacie_id = ?
            WHERE p.actif = 1
            ORDER BY p.nom
            LIMIT $perPage OFFSET $offset
        ");
        $stmt->execute([$pid]);
        $produits = $stmt->fetchAll();
    }

    layout_head('Stock par pharmacie', 'pharmacies');
    showFlash();
    ?>
    <div class="card">
      <div class="card-header">
        <div class="card-title">Stock par pharmacie</div>
        <a href="<?= url('pharmacies') ?>" class="btn btn-ghost btn-sm"><?= icon('chevron-left', 14) ?> Retour</a>
      </div>
      <div class="card-pad" style="padding-bottom:0;">
        <div class="form-group" style="max-width:360px;">
          <label>Pharmacie</label>
          <select onchange="if(this.value) window.location='<?= url('pharmacies', ['action' => 'stock']) ?>?id='+this.value">
            <?php foreach ($pharmacies as $ph): ?>
              <option value="<?= (int)$ph['id'] ?>" <?= $pid === (int)$ph['id'] ? 'selected' : '' ?>><?= e($ph['nom']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <?php if ($pid): ?>
      <form method="POST">
        <input type="hidden" name="csrf" value="<?= csrf() ?>">
        <input type="hidden" name="action" value="update_stock">
        <input type="hidden" name="pharmacie_id" value="<?= $pid ?>">
        <div class="table-wrap">
          <table>
            <thead><tr><th style="width:44px;">N°</th><th>Médicament</th><th>Référence</th><th style="width:160px;">Stock</th></tr></thead>
            <tbody>
              <?php $num = $offset + 1; foreach ($produits as $pr): ?>
              <tr>
                <td class="text-sm" style="color:var(--text3);"><?= $num++ ?></td>
                <td class="td-name"><?= e($pr['nom']) ?></td>
                <td class="td-mono"><?= e($pr['reference']) ?></td>
                <td><input type="number" name="stock[<?= (int)$pr['id'] ?>]" value="<?= (int)$pr['stock'] ?>" min="0" step="1" style="width:110px;"></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <input type="hidden" name="page" value="<?= $page ?>">
        <div class="modal-footer">
          <div style="flex:1;"><?= renderPagination($page, $perPage, $total, ['action'=>'stock', 'id'=>$pid]) ?></div>
          <button type="submit" class="btn btn-primary"><?= icon('save', 14) ?> Enregistrer le stock</button>
        </div>
      </form>
      <?php else: ?>
      <div class="card-pad"><p>Aucune pharmacie active.</p></div>
      <?php endif; ?>
    </div>
    <?php layout_foot(); exit;
}

// ── Ajax : produits filtrés pour la modale de transfert ─────
// Renvoie le stock du produit pour CHAQUE pharmacie active, afin que la
// modale affiche la dispo de la pharmacie source sélectionnée dynamiquement.
if (($_GET['ajax'] ?? '') === 'produits' && hasPermission('pharmacies.gerer')) {
    $aq = trim($_GET['q'] ?? '');
    $aWhere  = "p.actif = 1";
    $aParams = [];
    if ($aq !== '') {
        $qEsc = str_replace(['\\','%','_'], ['\\\\','\%','\_'], $aq);
        $aWhere .= " AND (p.nom LIKE ? ESCAPE '\\\\' OR p.reference LIKE ? ESCAPE '\\\\')";
        $aParams[] = "%$qEsc%"; $aParams[] = "%$qEsc%";
    }
    $st = $db->prepare("SELECT p.id, p.nom, p.reference
                        FROM produits p WHERE $aWhere ORDER BY p.nom LIMIT 50");
    $st->execute($aParams);
    $rows = $st->fetchAll();
    $app = [];
    $aIds = array_map(function($r){ return (int)$r['id']; }, $rows);
    if ($aIds) {
        $phL = implode(',', array_fill(0, count($aIds), '?'));
        $stpp = $db->prepare("SELECT produit_id, pharmacie_id, stock FROM produit_pharmacie WHERE produit_id IN ($phL)");
        $stpp->execute($aIds);
        foreach ($stpp->fetchAll() as $r) {
            $app[(int)$r['produit_id']][(int)$r['pharmacie_id']] = (int)$r['stock'];
        }
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['produits' => $rows, 'pp' => $app], JSON_UNESCAPED_UNICODE);
    exit;
}

// ── Vue : transfert inter-pharmacies (formulaire) ────────────
if ($action === 'transfert') {
    requirePermission('pharmacies.gerer');
    $pharmacies = $db->query("SELECT id, nom FROM pharmacies WHERE actif=1 ORDER BY id")->fetchAll();
    $srcSel = (int)($_GET['source'] ?? ($pharmacies[0]['id'] ?? 0));
    $dstSel = (int)($_GET['dest'] ?? ($pharmacies[1]['id'] ?? 0));

    layout_head('Transfert inter-pharmacies', 'pharmacies');
    showFlash();
    ?>
    <div class="card" style="max-width:920px;margin:0 auto;">
      <div class="card-header">
        <div class="card-title"><?= icon('truck', 16) ?> Transfert entre pharmacies</div>
        <a href="<?= url('pharmacies') ?>" class="btn btn-ghost btn-sm"><?= icon('chevron-left', 14) ?> Retour</a>
      </div>
      <?php if (count($pharmacies) < 2): ?>
      <div class="card-pad"><p>Il faut au moins deux pharmacies actives pour effectuer un transfert.</p></div>
      <?php else: ?>
      <form method="POST" id="trf-form" onsubmit="return submitTransfertPh(event)">
        <input type="hidden" name="csrf" value="<?= csrf() ?>">
        <input type="hidden" name="action" value="transfert_ph">
        <div class="card-pad">
          <div class="form-grid">
            <div class="form-group">
              <label>Pharmacie source *</label>
              <select name="pharmacie_source" id="trf-source" required onchange="trfReloadLines()">
                <?php foreach ($pharmacies as $ph): ?>
                  <option value="<?= (int)$ph['id'] ?>" <?= $srcSel === (int)$ph['id'] ? 'selected' : '' ?>><?= e($ph['nom']) ?></option>
                <?php endforeach; ?>
              </select>
              <small>Le stock est débité immédiatement.</small>
            </div>
            <div class="form-group">
              <label>Pharmacie destination *</label>
              <select name="pharmacie_dest" id="trf-dest" required onchange="trfValidateSel()">
                <?php foreach ($pharmacies as $ph): ?>
                  <option value="<?= (int)$ph['id'] ?>" <?= $dstSel === (int)$ph['id'] ? 'selected' : '' ?>><?= e($ph['nom']) ?></option>
                <?php endforeach; ?>
              </select>
              <small>Approvisionnée immédiatement.</small>
            </div>
          </div>
          <div class="form-group full">
            <label>Produits à transférer</label>
            <div style="display:flex;gap:8px;align-items:center;margin-bottom:8px;">
              <input type="text" id="trf-search" placeholder="Rechercher un médicament (nom ou référence)…" oninput="trfSearchDebounce()" style="flex:1;">
              <?= icon('search', 14) ?>
            </div>
            <div class="table-wrap" style="max-height:340px;overflow:auto;">
              <table id="trf-lines">
                <thead><tr><th style="width:44px;">N°</th><th>Médicament</th><th>Référence</th><th style="width:110px;">Dispo source</th><th style="width:130px;">Quantité</th></tr></thead>
                <tbody><tr><td colspan="5" class="text-center text-sm" style="padding:18px;">Chargement des produits…</td></tr></tbody>
              </table>
            </div>
          </div>
          <div class="form-group full">
            <label>Note / motif</label>
            <input type="text" name="note" placeholder="Motif du transfert (ex. dépannage rupture, rééquilibrage…)">
          </div>
        </div>
        <div class="modal-footer">
          <a href="<?= url('pharmacies', ['action' => 'transferts']) ?>" class="btn btn-ghost"><?= icon('history', 14) ?> Historique</a>
          <button type="submit" class="btn btn-primary"><?= icon('truck', 14) ?> Valider le transfert</button>
        </div>
      </form>
      <?php endif; ?>
    </div>
    <script>
    var TRF_PH = {
      url: '<?= url('pharmacies', ['action' => 'transfert']) ?>',
      ajaxUrl: '<?= url('pharmacies') ?>?ajax=produits&q=',
      ph: <?= json_encode(array_column($pharmacies, 'nom', 'id'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>,
      picked: {},        // pid -> {nom, ref, qte}
      timer: null
    };
    function trfValidateSel() {
      var s = document.getElementById('trf-source'), d = document.getElementById('trf-dest');
      if (s.value === d.value) {
        // choisit automatiquement une destination différente
        for (var i = 0; i < d.options.length; i++) {
          if (d.options[i].value !== s.value) { d.selectedIndex = i; break; }
        }
        alert('La source et la destination doivent être différentes.');
      }
      trfReloadLines();
    }
    function trfReloadLines() {
      var q = document.getElementById('trf-search').value.trim();
      if (q === '') { trfRender(); return; }
      trfFetch(q);
    }
    function trfSearchDebounce() {
      clearTimeout(TRF_PH.timer);
      TRF_PH.timer = setTimeout(trfReloadLines, 250);
    }
    function trfFetch(q) {
      fetch(TRF_PH.ajaxUrl + encodeURIComponent(q)).then(function(r){ return r.json(); }).then(function(data){
        TRF_PH.last = data;
        trfRender();
      })['catch'](function(){
        var tb = document.querySelector('#trf-lines tbody');
        if (tb) { tb.innerHTML = '<tr><td colspan="5" class="text-center text-sm" style="padding:18px;">Erreur réseau — impossible de charger les produits.</td></tr>'; }
      });
    }
    function trfDispo(pid) {
      var srcId = parseInt(document.getElementById('trf-source').value, 10);
      var d = TRF_PH.last && TRF_PH.last.pp && TRF_PH.last.pp[pid];
      return d ? (d[srcId] || 0) : 0;
    }
    function trfRender() {
      var tb = document.querySelector('#trf-lines tbody');
      if (!TRF_PH.last || !TRF_PH.last.produits || !TRF_PH.last.produits.length) {
        tb.innerHTML = '<tr><td colspan="5" class="text-center text-sm" style="padding:18px;">Aucun produit trouvé pour cette recherche.</td></tr>';
        return;
      }
      var srcId = parseInt(document.getElementById('trf-source').value, 10);
      var html = '';
      var n = 0;
      TRF_PH.last.produits.forEach(function(p){
        var dispo = trfDispo(p.id);
        var picked = TRF_PH.picked[p.id];
        n++;
        html += '<tr>'
             +  '<td class="text-sm" style="color:var(--text3);">' + n + '</td>'
             +  '<td class="td-name">' + p.nom.replace(/</g,'&lt;') + '</td>'
             +  '<td class="td-mono">' + (p.reference || '—') + '</td>'
             +  '<td class="text-right ' + (dispo <= 0 ? 'c-red' : '') + '">' + dispo + '</td>'
             +  '<td><input type="number" min="1" step="1" data-pid="' + p.id + '" data-nom="' + p.nom.replace(/"/g,'&quot;') + '" data-ref="' + (p.reference || '') + '" value="' + (picked ? picked.qte : '') + '" placeholder="0" style="width:90px;" onchange="trfPick(this)"></td>'
             +  '</tr>';
      });
      tb.innerHTML = html;
    }
    function trfPick(input) {
      var pid = input.dataset.pid;
      var qte = parseInt(input.value, 10);
      if (qte > 0) {
        TRF_PH.picked[pid] = { qte: qte, nom: input.dataset.nom, ref: input.dataset.ref };
      } else {
        delete TRF_PH.picked[pid];
      }
    }
    function submitTransfertPh(e) {
      var src = document.getElementById('trf-source'), dst = document.getElementById('trf-dest');
      if (src.value === dst.value) { alert('Source et destination identiques.'); return false; }
      var picks = TRF_PH.picked, n = 0;
      for (var k in picks) { if (picks[k].qte > 0) n++; }
      if (n === 0) { alert('Aucun produit à transférer — saisissez au moins une quantité.'); return false; }
      // Lignes cachées produit_id[] / quantite[]
      var form = e.target;
      Object.keys(picks).forEach(function(k){
        if (picks[k].qte > 0) {
          var a = document.createElement('input'); a.type='hidden'; a.name='produit_id[]'; a.value=k; form.appendChild(a);
          var b = document.createElement('input'); b.type='hidden'; b.name='quantite[]'; b.value=picks[k].qte; form.appendChild(b);
        }
      });
      if (!confirm('Confirmer le transfert de ' + n + ' produit(s) de « ' + TRF_PH.ph[src.value] + ' » vers « ' + TRF_PH.ph[dst.value] + ' » ?')) {
        var ins = form.querySelectorAll('input[name="produit_id[]"],input[name="quantite[]"]');
        for (var j = 0; j < ins.length; j++) { ins[j].remove(); }
        return false;
      }
      return true;
    }
    // Pré-sélection initiale : s'assure que source ≠ destination
    (function(){
      var s = document.getElementById('trf-source'), d = document.getElementById('trf-dest');
      if (s && d && s.value === d.value) { trfValidateSel(); }
      // Charge la liste des produits dès l'ouverture (50 premiers) : la
      // recherche affine ensuite. Sans ça, la table reste vide et on croit
      // que la sélection de produits ne fonctionne pas.
      trfFetch('');
    })();
    </script>
    <?php layout_foot(); exit;
}

// ── Vue : historique des transferts inter-pharmacies ────────
if ($action === 'transferts') {
    requirePermission('pharmacies.gerer');
    $pharmacies = $db->query("SELECT id, nom FROM pharmacies ORDER BY id")->fetchAll();
    $fSrc = (int)($_GET['source'] ?? 0);
    $fDst = (int)($_GET['dest'] ?? 0);
    $perPage = 25;
    $page    = max(1, (int)($_GET['page'] ?? 1));

    $where = "1=1"; $params = [];
    if ($fSrc) { $where .= " AND t.pharmacie_source_id = ?"; $params[] = $fSrc; }
    if ($fDst) { $where .= " AND t.pharmacie_dest_id = ?"; $params[] = $fDst; }

    $stT = $db->prepare("SELECT COUNT(*) FROM transferts_pharmacies t WHERE $where");
    $stT->execute($params);
    $total = (int)$stT->fetchColumn();

    $stL = $db->prepare("
        SELECT t.*, u.prenom, u.nom AS u_nom,
               src.nom AS source_nom, dst.nom AS dest_nom,
               (SELECT COUNT(*) FROM transfert_pharmacie_lignes tl WHERE tl.transfert_id = t.id) AS nb_lignes,
               (SELECT COALESCE(SUM(tl.quantite),0) FROM transfert_pharmacie_lignes tl WHERE tl.transfert_id = t.id) AS total_qte
        FROM transferts_pharmacies t
        LEFT JOIN utilisateurs u ON u.id = t.utilisateur_id
        LEFT JOIN pharmacies src ON src.id = t.pharmacie_source_id
        LEFT JOIN pharmacies dst ON dst.id = t.pharmacie_dest_id
        WHERE $where
        ORDER BY t.created_at DESC
        LIMIT $perPage OFFSET " . paginateOffset($page, $perPage));
    $stL->execute($params);
    $transferts = $stL->fetchAll();

    // Lignes des transferts affichés (détail dépliable)
    $trfLignes = [];
    $trfIds = array_column($transferts, 'id');
    if ($trfIds) {
        $phL = implode(',', array_fill(0, count($trfIds), '?'));
        $stLines = $db->prepare("SELECT transfert_id, produit_nom, quantite FROM transfert_pharmacie_lignes
                                 WHERE transfert_id IN ($phL) ORDER BY id");
        $stLines->execute($trfIds);
        foreach ($stLines->fetchAll() as $l) $trfLignes[$l['transfert_id']][] = $l;
    }

    layout_head('Historique transferts', 'pharmacies');
    showFlash();
    ?>
    <div class="card">
      <div class="card-header">
        <div class="card-title"><?= icon('history', 16) ?> Historique des transferts inter-pharmacies</div>
        <div class="flex gap-8">
          <a href="<?= url('pharmacies', ['action' => 'transfert']) ?>" class="btn btn-primary btn-sm"><?= icon('truck', 14) ?> Nouveau transfert</a>
          <a href="<?= url('pharmacies') ?>" class="btn btn-ghost btn-sm"><?= icon('chevron-left', 14) ?> Retour</a>
        </div>
      </div>
      <div class="card-pad" style="padding-bottom:0;">
        <form method="GET" action="<?= url('pharmacies') ?>" class="form-grid" style="grid-template-columns:1fr 1fr auto;max-width:720px;">
          <input type="hidden" name="action" value="transferts">
          <div class="form-group">
            <label>Source</label>
            <select name="source" onchange="this.form.submit()">
              <option value="0">— Toutes —</option>
              <?php foreach ($pharmacies as $ph): ?>
                <option value="<?= (int)$ph['id'] ?>" <?= $fSrc === (int)$ph['id'] ? 'selected' : '' ?>><?= e($ph['nom']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Destination</label>
            <select name="dest" onchange="this.form.submit()">
              <option value="0">— Toutes —</option>
              <?php foreach ($pharmacies as $ph): ?>
                <option value="<?= (int)$ph['id'] ?>" <?= $fDst === (int)$ph['id'] ? 'selected' : '' ?>><?= e($ph['nom']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group" style="align-self:end;">
            <button type="submit" class="btn btn-ghost btn-sm"><?= icon('filter', 14) ?> Filtrer</button>
          </div>
        </form>
      </div>
      <div class="table-wrap">
        <table>
          <thead><tr><th>Référence</th><th>Date</th><th>Source</th><th>Destination</th><th>Lignes</th><th>Qté totale</th><th>Opérateur</th><th>Note</th><th></th></tr></thead>
          <tbody>
            <?php if (!$transferts): ?>
            <tr><td colspan="9" style="text-align:center;padding:22px;color:var(--text3);">Aucun transfert.</td></tr>
            <?php endif; ?>
            <?php foreach ($transferts as $t): ?>
            <tr>
              <td class="td-mono"><?= e($t['reference']) ?></td>
              <td class="text-sm"><?= date('d/m/Y H:i', strtotime($t['created_at'])) ?></td>
              <td class="td-name"><?= e($t['source_nom']) ?></td>
              <td class="td-name"><?= e($t['dest_nom']) ?></td>
              <td class="text-right"><?= (int)$t['nb_lignes'] ?></td>
              <td class="text-right"><?= fmtInt((int)$t['total_qte']) ?></td>
              <td class="text-sm"><?= e(trim($t['prenom'] . ' ' . $t['u_nom'])) ?: '—' ?></td>
              <td class="text-sm"><?= e($t['note'] ?: '—') ?></td>
              <td class="text-right"><a href="<?= url('pharmacies', ['action' => 'bon', 'id' => $t['id']]) ?>" class="btn btn-ghost btn-xs" title="Bon de transfert imprimable"><?= icon('print', 12) ?> Bon</a></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?= renderPagination($page, $perPage, $total, ['action'=>'transferts', 'source'=>$fSrc, 'dest'=>$fDst]) ?>
    </div>
    <?php layout_foot(); exit;
}

// ── Vue : bon de transfert inter-pharmacies (imprimable) ────
if ($action === 'bon' && $id) {
    requirePermission('pharmacies.gerer');
    $stT = $db->prepare("SELECT t.*, u.prenom, u.nom AS u_nom,
                                src.nom AS source_nom, dst.nom AS dest_nom
                         FROM transferts_pharmacies t
                         LEFT JOIN utilisateurs u ON u.id = t.utilisateur_id
                         LEFT JOIN pharmacies src ON src.id = t.pharmacie_source_id
                         LEFT JOIN pharmacies dst ON dst.id = t.pharmacie_dest_id
                         WHERE t.id = ?");
    $stT->execute([$id]);
    $bonT = $stT->fetch();
    if (!$bonT) {
        http_response_code(404);
        exit('Transfert introuvable.');
    }
    $stL = $db->prepare("SELECT tl.*, p.reference, p.prix_vente
                         FROM transfert_pharmacie_lignes tl
                         LEFT JOIN produits p ON p.id = tl.produit_id
                         WHERE tl.transfert_id = ? ORDER BY tl.id");
    $stL->execute([$id]);
    $bonLignes = $stL->fetchAll();

    $bonEtsNom = getParam('app_nom', 'PharmaCare');
    $bonEtsAdr = getParam('pharmacie_adresse', '');
    $bonEtsTel = getParam('pharmacie_telephone', '');
    $bonEtsNif = getParam('pharmacie_nif', '');
    $bonTotalQte  = 0;
    $bonTotalMont = 0.0;
    foreach ($bonLignes as $bl) {
        $bonTotalQte  += (int)$bl['quantite'];
        $bonTotalMont += (float)$bl['prix_vente'] * (int)$bl['quantite'];
    }
    $bonDate  = date('d/m/Y', strtotime($bonT['created_at']));
    $bonHeure = date('H:i', strtotime($bonT['created_at']));

    ?><!DOCTYPE html>
<html lang="fr"><head><meta charset="UTF-8">
<title>Bon de transfert <?= e($bonT['reference']) ?></title>
<style>
  @page { size: A4; margin: 14mm; }
  * { box-sizing: border-box; }
  body { font-family: 'Segoe UI', Arial, sans-serif; color: #1e293b; font-size: 12px; margin: 0; }
  .entete { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #0f172a; padding-bottom: 10px; }
  .pays { font-weight: 700; font-size: 13px; }
  .devise { font-style: italic; font-size: 11px; }
  .ministere { font-weight: 600; font-size: 12px; }
  .etablissement { font-weight: 700; font-size: 15px; }
  .titre-doc { text-align: center; font-size: 20px; font-weight: 700; margin: 22px 0 2px; text-transform: uppercase; letter-spacing: 2px; }
  .ref-doc { text-align: center; font-size: 12px; color: #475569; margin-bottom: 16px; }
  .infos { display: grid; grid-template-columns: 1fr 1fr; gap: 6px 24px; margin-bottom: 14px; font-size: 12px; }
  .infos .lbl { color: #64748b; display: inline-block; min-width: 130px; }
  table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
  th, td { border: 1px solid #94a3b8; padding: 6px 7px; vertical-align: top; }
  th { background: #0f172a; color: #fff; font-size: 10px; text-transform: uppercase; letter-spacing: .5px; }
  td.right, th.right { text-align: right; }
  td.center, th.center { text-align: center; }
  tfoot td { font-weight: 700; background: #f1f5f9; }
  .signatures { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; margin-top: 46px; text-align: center; }
  .signatures .role { font-weight: 600; font-size: 11px; margin-bottom: 26px; }
  .signatures .sig { border-top: 1px solid #475569; padding-top: 5px; font-size: 10px; color: #64748b; }
  .pied { margin-top: 26px; text-align: center; font-size: 10px; color: #94a3b8; border-top: 1px solid #e2e8f0; padding-top: 8px; }
  .toolbar { text-align: center; margin-bottom: 10px; }
  .toolbar button { padding: 8px 18px; font-size: 13px; cursor: pointer; border: 1px solid #0f172a; background: #0f172a; color: #fff; border-radius: 6px; }
  @media print { .toolbar { display: none; } body { font-size: 11px; } }
</style></head>
<body>
  <div class="toolbar"><button onclick="window.print()">🖨 Imprimer le bon</button></div>

  <div class="entete">
    <div>
      <div class="pays">RÉPUBLIQUE DU CAMEROUN</div>
      <div class="devise">Paix – Travail – Patrie</div>
      <div class="ministere">MINISTÈRE DE LA SANTÉ PUBLIQUE</div>
      <div style="font-size:11px;color:#64748b;margin-top:2px;">Établissement pharmaceutique</div>
    </div>
    <div style="text-align:right;">
      <div class="etablissement"><?= e($bonEtsNom) ?></div>
      <?php if ($bonEtsAdr): ?><div style="font-size:11px;"><?= e($bonEtsAdr) ?></div><?php endif; ?>
      <?php if ($bonEtsTel): ?><div style="font-size:11px;">Tél : <?= e($bonEtsTel) ?></div><?php endif; ?>
      <?php if ($bonEtsNif): ?><div style="font-size:11px;">NIF : <?= e($bonEtsNif) ?></div><?php endif; ?>
    </div>
  </div>

  <div class="titre-doc">Bon de Transfert Inter-Pharmacies</div>
  <div class="ref-doc">Réf. <?= e($bonT['reference']) ?> &mdash; émis le <?= $bonDate ?> à <?= $bonHeure ?></div>

  <div class="infos">
    <div><span class="lbl">Pharmacie émettrice :</span> <strong><?= e($bonT['source_nom'] ?: '—') ?></strong></div>
    <div><span class="lbl">Pharmacie destinataire :</span> <strong><?= e($bonT['dest_nom'] ?: '—') ?></strong></div>
    <div><span class="lbl">Opérateur :</span> <?= e(trim($bonT['prenom'] . ' ' . $bonT['u_nom'])) ?: '—' ?></div>
    <div><span class="lbl">Date du transfert :</span> <?= $bonDate ?> à <?= $bonHeure ?></div>
    <?php if (!empty($bonT['note'])): ?><div style="grid-column:1/-1;"><span class="lbl">Motif / Observation :</span> <?= e($bonT['note']) ?></div><?php endif; ?>
  </div>

  <table>
    <thead>
      <tr>
        <th class="center" style="width:5%;">N°</th>
        <th>Désignation</th>
        <th style="width:11%;">Réf. produit</th>
        <th class="center" style="width:11%;">Qté transférée</th>
        <th class="right" style="width:14%;">Valeur (PV)</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($bonLignes as $i => $bl): ?>
      <tr>
        <td class="center"><?= $i + 1 ?></td>
        <td><?= e($bl['produit_nom']) ?></td>
        <td><?= e($bl['reference'] ?: '—') ?></td>
        <td class="center"><?= (int)$bl['quantite'] ?></td>
        <td class="right"><?= fmt((float)$bl['prix_vente'] * (int)$bl['quantite']) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
    <tfoot>
      <tr>
        <td colspan="3" class="right">Total</td>
        <td class="center"><?= $bonTotalQte ?></td>
        <td class="right"><?= fmt($bonTotalMont) ?></td>
      </tr>
    </tfoot>
  </table>

  <div class="signatures">
    <div><div class="role">Pharmacien / Émetteur</div><div class="sig"><?= e(trim($bonT['prenom'] . ' ' . $bonT['u_nom'])) ?: '&nbsp;' ?></div></div>
    <div><div class="role">Responsable site destinataire</div><div class="sig">&nbsp;</div></div>
  </div>

  <div class="pied">
    <?= e($bonEtsNom) ?> — Bon de transfert inter-pharmacies <?= e($bonT['reference']) ?> — Généré par PharmaCare
  </div>
</body></html>
    <?php exit;
}

// ── Vue : liste ───────────────────────────────────────────────
// ── Export Excel ───────────────────────────────────────────
if (($_GET['export'] ?? '') === '1') {
    require_once __DIR__ . '/../includes/export_xlsx.php';
    $rowsX = [];
    foreach ($db->query("
        SELECT ph.nom, ph.adresse, ph.telephone, ph.actif, ph.created_at,
               (SELECT COUNT(*) FROM produit_pharmacie pp WHERE pp.pharmacie_id = ph.id AND pp.stock > 0) AS nb_prods_stock,
               (SELECT COUNT(*) FROM sessions_caisse s WHERE s.pharmacie_id = ph.id AND s.statut = 'ouverte') AS nb_sessions_ouvertes
        FROM pharmacies ph
        ORDER BY ph.id
    ")->fetchAll() as $ph) {
        $rowsX[] = [$ph['nom'], $ph['adresse'], $ph['telephone'],
                    (int)$ph['actif'] ? 'Active' : 'Désactivée',
                    (int)$ph['nb_prods_stock'], (int)$ph['nb_sessions_ouvertes'],
                    date('d/m/Y', strtotime($ph['created_at']))];
    }
    export_xlsx_send('pharmacies_' . date('Y-m-d'), 'Pharmacies',
        ['Nom', 'Adresse', 'Téléphone', 'Statut', 'Nb produits en stock', 'Sessions ouvertes', 'Créée le'], $rowsX);
}

$pharmacies = $db->query("
    SELECT ph.*,
           (SELECT COUNT(*) FROM produit_pharmacie pp WHERE pp.pharmacie_id = ph.id AND pp.stock > 0) AS nb_prods_stock,
           (SELECT COUNT(*) FROM sessions_caisse s WHERE s.pharmacie_id = ph.id AND s.statut = 'ouverte') AS nb_sessions_ouvertes
    FROM pharmacies ph
    ORDER BY ph.id
")->fetchAll();

$canGerer = hasPermission('pharmacies.gerer');

layout_head('Pharmacies', 'pharmacies');
showFlash();
?>
<div class="card">
  <div class="card-header">
    <div class="card-title">Pharmacies</div>
    <div class="flex gap-8">
      <a href="<?= url('pharmacies', ['export'=>'1']) ?>" class="btn btn-ghost btn-sm" title="Exporter au format Excel (.xlsx)"><?= icon('download', 14) ?> Exporter</a>
      <?php if ($canGerer): ?>
      <a href="<?= url('pharmacies', ['action' => 'import']) ?>" class="btn btn-ghost btn-sm"><?= icon('upload', 14) ?> Importer</a>
      <a href="<?= url('pharmacies', ['action' => 'transfert']) ?>" class="btn btn-ghost btn-sm"><?= icon('truck', 14) ?> Transfert</a>
      <a href="<?= url('pharmacies', ['action' => 'transferts']) ?>" class="btn btn-ghost btn-sm"><?= icon('history', 14) ?> Transferts</a>
      <a href="<?= url('pharmacies', ['action' => 'stock']) ?>" class="btn btn-ghost btn-sm"><?= icon('boxes', 14) ?> Stock par pharmacie</a>
      <a href="<?= url('pharmacies', ['action' => 'add']) ?>" class="btn btn-primary btn-sm"><?= icon('plus', 14) ?> Ajouter</a>
      <?php endif; ?>
    </div>
  </div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr><th>Nom</th><th>Adresse</th><th>Téléphone</th><th>Produits en stock</th><th>Sessions ouvertes</th><th>Statut</th><th>Actions</th></tr>
      </thead>
      <tbody>
        <?php foreach ($pharmacies as $ph): ?>
        <tr>
          <td class="td-name"><?= e($ph['nom']) ?><?php if ((int)$ph['id'] === 1): ?> <span class="badge badge-gray">principale</span><?php endif; ?></td>
          <td class="text-sm"><?= e($ph['adresse'] ?: '—') ?></td>
          <td class="text-sm"><?= e($ph['telephone'] ?: '—') ?></td>
          <td><span class="badge badge-blue"><?= (int)$ph['nb_prods_stock'] ?></span></td>
          <td><span class="badge badge-gold"><?= (int)$ph['nb_sessions_ouvertes'] ?></span></td>
          <td><span class="badge <?= $ph['actif'] ? 'badge-green' : 'badge-red' ?>"><?= $ph['actif'] ? 'Active' : 'Inactive' ?></span></td>
          <td>
            <div class="flex gap-8">
              <?php if ($canGerer): ?>
              <a href="<?= url('pharmacies', ['action' => 'edit', 'id' => $ph['id']], $ph['nom']) ?>" class="btn btn-ghost btn-xs"><?= icon('edit', 13) ?> Modifier</a>
              <?php if ((int)$ph['id'] !== 1): ?>
              <button type="button" class="btn <?= $ph['actif'] ? 'btn-danger' : 'btn-gold' ?> btn-xs"
                onclick="confirmDeletePost('toggle','<?= (int)$ph['id'] ?>','<?= $ph['actif'] ? 'Désactiver cette pharmacie ?' : 'Activer cette pharmacie ?' ?>')">
                <?= $ph['actif'] ? icon('lock', 13) . ' Désactiver' : icon('unlock', 13) . ' Activer' ?>
              </button>
              <?php endif; ?>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php layout_foot(); ?>