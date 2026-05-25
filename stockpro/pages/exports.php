<?php
$page_title = 'Exports';
$page_id = 'exports';
require_once '../includes/header.php';
requireAuth();
$db = getDB();
$ent = getEntreprise();

// === EXPORT CSV PRODUITS ===
if (isset($_GET['export']) && $_GET['export'] === 'produits_csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="produits_'.date('Y-m-d').'.csv"');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
    fputcsv($out, ['Référence','Nom','Catégorie','Fournisseur','P.Achat','P.Vente','Stock','Unité','Stock Min','Valeur Stock'], ';');
    $rows = $db->query("SELECT p.*,c.nom as cat,f.nom as four FROM produits p LEFT JOIN categories c ON p.categorie_id=c.id LEFT JOIN fournisseurs f ON p.fournisseur_id=f.id WHERE p.actif=1 ORDER BY p.nom")->fetchAll();
    foreach ($rows as $r) {
        fputcsv($out, [$r['reference'],$r['nom'],$r['cat']??'',$r['four']??'',$r['prix_achat'],$r['prix_vente'],$r['quantite'],$r['unite'],$r['quantite_min'],$r['quantite']*$r['prix_achat']], ';');
    }
    fclose($out); exit;
}

// === EXPORT CSV MOUVEMENTS ===
$msg_mouvements_vide = false;
if (isset($_GET['export']) && $_GET['export'] === 'mouvements_csv') {
    $date_debut = $_GET['date_debut'] ?? date('Y-m-01');
    $date_fin   = $_GET['date_fin']   ?? date('Y-m-d');
    $countStmt = $db->prepare("SELECT COUNT(*) FROM mouvements WHERE DATE(created_at) BETWEEN ? AND ?");
    $countStmt->execute([$date_debut, $date_fin]);
    $nb = (int)$countStmt->fetchColumn();
    if ($nb === 0) {
        $msg_mouvements_vide = true;
    } else {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="mouvements_'.$date_debut.'_'.$date_fin.'.csv"');
        $out = fopen('php://output', 'w');
        fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
        fputcsv($out, ['Date','Produit','Référence','Type','Quantité','Avant','Après','Prix Unit.','Motif','Utilisateur'], ';');
        $rows = $db->prepare("SELECT m.*,p.nom as prod,p.reference as ref,CONCAT(u.prenom,' ',u.nom) as user FROM mouvements m JOIN produits p ON m.produit_id=p.id JOIN utilisateurs u ON m.utilisateur_id=u.id WHERE DATE(m.created_at) BETWEEN ? AND ? ORDER BY m.created_at DESC");
        $rows->execute([$date_debut, $date_fin]);
        foreach ($rows->fetchAll() as $r) {
            fputcsv($out, [date('d/m/Y H:i',strtotime($r['created_at'])),$r['prod'],$r['ref'],$r['type'],$r['quantite'],$r['quantite_avant'],$r['quantite_apres'],$r['prix_unitaire']??'',$r['motif']??'',$r['user']], ';');
        }
        fclose($out); exit;
    }
}

// ============================================
// RAPPORT IMPRIMÉ : Inventaire de stock (sans prix)
// ============================================
if (isset($_GET['export']) && $_GET['export'] === 'inventaire_print') {
    $produits = $db->query("SELECT p.*,c.nom as cat,c.couleur as cat_couleur FROM produits p LEFT JOIN categories c ON p.categorie_id=c.id WHERE p.actif=1 ORDER BY c.nom, p.nom")->fetchAll();
    $nb_total = count($produits);
    $nb_rupture = 0; $nb_faible = 0; $nb_ok = 0;
    foreach ($produits as $p) {
        if ($p['quantite'] == 0) $nb_rupture++;
        elseif ($p['quantite'] <= $p['quantite_min']) $nb_faible++;
        else $nb_ok++;
    }
    ?>
    <!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><title>Inventaire de stock — <?= htmlspecialchars($ent['nom']) ?></title>
    <style>
    body{font-family:Arial,sans-serif;font-size:11px;color:#000;margin:20px}
    h1{font-size:18px;margin-bottom:2px}
    .sub{color:#555;font-size:11px;margin-bottom:6px}
    .summary{display:flex;gap:20px;margin-bottom:18px;padding:12px 16px;background:#f7f7f7;border:1px solid #ddd;border-radius:6px}
    .summary-item{text-align:center}
    .summary-val{font-size:20px;font-weight:800}
    .summary-label{font-size:10px;color:#666}
    table{width:100%;border-collapse:collapse;margin-bottom:16px}
    th{background:#f0f0f0;padding:6px 8px;text-align:left;border:1px solid #ccc;font-size:10px;text-transform:uppercase;letter-spacing:.3px}
    td{padding:5px 8px;border:1px solid #ddd;font-size:10px}
    tr:nth-child(even) td{background:#fafafa}
    .rupture{color:#c00;font-weight:bold}
    .faible{color:#c60;font-weight:bold}
    .ok{color:#060;font-weight:bold}
    .cat-header td{background:#e8e8e8;font-weight:700;font-size:11px;padding:8px}
    .footer{margin-top:16px;font-size:10px;color:#888;border-top:1px solid #eee;padding-top:8px;display:flex;justify-content:space-between}
    .signature{margin-top:40px;display:flex;justify-content:space-between}
    .signature-box{width:200px;text-align:center;font-size:11px}
    .signature-line{border-top:1px solid #999;margin-top:50px;padding-top:4px}
    @media print{@page{size:A4 portrait;margin:12mm}}
    </style></head><body>
    <h1>📋 Inventaire de stock</h1>
    <div class="sub"><?= htmlspecialchars($ent['nom']) ?><?php if (!empty($ent['slogan'])): ?> — <?= htmlspecialchars($ent['slogan']) ?><?php endif; ?></div>
    <div class="sub">Fait le <?= date('d/m/Y à H:i') ?> · <?= $nb_total ?> articles référencés</div>

    <div class="summary">
        <div class="summary-item"><div class="summary-val"><?= $nb_total ?></div><div class="summary-label">Total articles</div></div>
        <div class="summary-item"><div class="summary-val" style="color:#060"><?= $nb_ok ?></div><div class="summary-label">Stock OK</div></div>
        <div class="summary-item"><div class="summary-val" style="color:#c60"><?= $nb_faible ?></div><div class="summary-label">Stock faible</div></div>
        <div class="summary-item"><div class="summary-val" style="color:#c00"><?= $nb_rupture ?></div><div class="summary-label">Rupture</div></div>
    </div>

    <table>
        <tr><th>#</th><th>Référence</th><th>Désignation</th><th>Catégorie</th><th>Stock</th><th>Seuil min</th><th>Unité</th><th>Statut</th></tr>
        <?php
        $i = 0;
        $current_cat = null;
        foreach ($produits as $p):
            if ($current_cat !== $p['cat']):
                $current_cat = $p['cat'];
        ?>
        <tr class="cat-header"><td colspan="8"><?= htmlspecialchars($p['cat'] ?? 'Non classé') ?></td></tr>
        <?php endif; $i++; ?>
        <tr>
            <td style="text-align:center;color:#999"><?= $i ?></td>
            <td><?= htmlspecialchars($p['reference']) ?></td>
            <td><?= htmlspecialchars($p['nom']) ?></td>
            <td><?= htmlspecialchars($p['cat'] ?? '—') ?></td>
            <td style="font-weight:bold" class="<?= $p['quantite']==0?'rupture':($p['quantite']<=$p['quantite_min']?'faible':'ok') ?>"><?= $p['quantite'] ?></td>
            <td style="color:#888"><?= $p['quantite_min'] ?></td>
            <td><?= $p['unite'] ?></td>
            <td class="<?= $p['quantite']==0?'rupture':($p['quantite']<=$p['quantite_min']?'faible':'ok') ?>"><?= $p['quantite']==0?'RUPTURE':($p['quantite']<=$p['quantite_min']?'FAIBLE':'OK') ?></td>
        </tr>
        <?php endforeach; ?>
    </table>

    <div class="footer">
        <span><?= htmlspecialchars($ent['nom']) ?> · <?= htmlspecialchars($ent['adresse'] ?? '') ?> · <?= htmlspecialchars($ent['telephone'] ?? '') ?></span>
        <span>Document sans valeur financière — Usage interne</span>
    </div>

    <div class="signature">
        <div class="signature-box"><div class="signature-line">Responsable stock</div></div>
        <div class="signature-box"><div class="signature-line">Vérificateur</div></div>
        <div class="signature-box"><div class="signature-line">Direction</div></div>
    </div>

    <script>window.print()</script>
    </body></html>
    <?php exit;
}

// ============================================
// RAPPORT IMPRIMÉ : Financier
// ============================================
if (isset($_GET['export']) && $_GET['export'] === 'financier_print') {
    $produits = $db->query("SELECT p.*,c.nom as cat FROM produits p LEFT JOIN categories c ON p.categorie_id=c.id WHERE p.actif=1 ORDER BY c.nom, p.nom")->fetchAll();

    // Calculs globaux
    $val_achat_total = 0; $val_vente_total = 0; $marge_total = 0;
    foreach ($produits as $p) {
        $va = $p['quantite'] * $p['prix_achat'];
        $vv = $p['quantite'] * $p['prix_vente'];
        $val_achat_total += $va;
        $val_vente_total += $vv;
        $marge_total += ($vv - $va);
    }

    // Mouvements du mois
    $mvt_mois = $db->query("
        SELECT type,
               SUM(quantite * COALESCE(prix_unitaire,0)) as montant,
               SUM(quantite) as quantite_totale
        FROM mouvements
        WHERE MONTH(created_at)=MONTH(NOW()) AND YEAR(created_at)=YEAR(NOW())
        GROUP BY type
    ")->fetchAll(PDO::FETCH_ASSOC);
    $achats_mois = 0; $ventes_mois = 0;
    foreach ($mvt_mois as $m) {
        if ($m['type'] === 'entree') $achats_mois = (float)$m['montant'];
        if ($m['type'] === 'sortie') $ventes_mois = (float)$m['montant'];
    }

    // Répartition par catégorie
    $cats = $db->query("
        SELECT c.nom, c.couleur,
               COUNT(p.id) as nb,
               SUM(p.quantite * p.prix_achat) as val_achat,
               SUM(p.quantite * p.prix_vente) as val_vente,
               SUM(p.quantite * p.prix_vente - p.quantite * p.prix_achat) as marge
        FROM categories c
        LEFT JOIN produits p ON p.categorie_id=c.id AND p.actif=1
        GROUP BY c.id ORDER BY val_vente DESC
    ")->fetchAll();
    ?>
    <!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><title>Rapport financier — <?= htmlspecialchars($ent['nom']) ?></title>
    <style>
    body{font-family:Arial,sans-serif;font-size:11px;color:#000;margin:20px}
    h1{font-size:18px;margin-bottom:2px}
    .sub{color:#555;font-size:11px;margin-bottom:16px}
    .kpi{display:flex;gap:16px;margin-bottom:20px}
    .kpi-box{flex:1;padding:14px 16px;border:1px solid #ddd;border-radius:8px;text-align:center}
    .kpi-val{font-size:20px;font-weight:800;margin-bottom:2px}
    .kpi-label{font-size:10px;color:#666;text-transform:uppercase;letter-spacing:.3px}
    .green{color:#060} .red{color:#c00} .blue{color:#06c} .orange{color:#c60}
    table{width:100%;border-collapse:collapse;margin-bottom:16px}
    th{background:#f0f0f0;padding:6px 8px;text-align:left;border:1px solid #ccc;font-size:10px;text-transform:uppercase;letter-spacing:.3px}
    th.right{text-align:right}
    td{padding:5px 8px;border:1px solid #ddd;font-size:10px}
    td.right{text-align:right}
    tr:nth-child(even) td{background:#fafafa}
    .cat-header td{background:#e8e8e8;font-weight:700;font-size:11px;padding:8px}
    .total-row td{background:#f0f0f0;font-weight:700;font-size:11px;padding:8px}
    .footer{margin-top:16px;font-size:10px;color:#888;border-top:1px solid #eee;padding-top:8px;display:flex;justify-content:space-between}
    .signature{margin-top:40px;display:flex;justify-content:space-between}
    .signature-box{width:200px;text-align:center;font-size:11px}
    .signature-line{border-top:1px solid #999;margin-top:50px;padding-top:4px}
    .confidential{display:inline-block;background:#c00;color:white;padding:2px 8px;border-radius:3px;font-size:9px;font-weight:700;letter-spacing:.5px;margin-left:8px}
    @media print{@page{size:A4 portrait;margin:12mm}}
    </style></head><body>
    <h1>💰 Rapport financier<span class="confidential">CONFIDENTIEL</span></h1>
    <div class="sub"><?= htmlspecialchars($ent['nom']) ?><?php if (!empty($ent['slogan'])): ?> — <?= htmlspecialchars($ent['slogan']) ?><?php endif; ?></div>
    <div class="sub">Édité le <?= date('d/m/Y à H:i') ?> · <?= count($produits) ?> articles en stock</div>

    <!-- KPI -->
    <div class="kpi">
        <div class="kpi-box">
            <div class="kpi-val blue"><?= number_format($val_achat_total,0,',',' ') ?> <?= $ent['devise'] ?></div>
            <div class="kpi-label">Valeur stock (achat)</div>
        </div>
        <div class="kpi-box">
            <div class="kpi-val green"><?= number_format($val_vente_total,0,',',' ') ?> <?= $ent['devise'] ?></div>
            <div class="kpi-label">Valeur stock (vente)</div>
        </div>
        <div class="kpi-box">
            <div class="kpi-val <?= $marge_total>=0?'green':'red' ?>"><?= number_format($marge_total,0,',',' ') ?> <?= $ent['devise'] ?></div>
            <div class="kpi-label">Marge potentielle</div>
        </div>
        <div class="kpi-box">
            <div class="kpi-val orange"><?= number_format($achats_mois,0,',',' ') ?> <?= $ent['devise'] ?></div>
            <div class="kpi-label">Achats du mois</div>
        </div>
        <div class="kpi-box">
            <div class="kpi-val blue"><?= number_format($ventes_mois,0,',',' ') ?> <?= $ent['devise'] ?></div>
            <div class="kpi-label">Ventes du mois</div>
        </div>
    </div>

    <!-- Répartition par catégorie -->
    <h2 style="font-size:13px;margin-bottom:8px">Répartition par catégorie</h2>
    <table>
        <tr><th>Catégorie</th><th class="right">Nb articles</th><th class="right">Valeur achat</th><th class="right">Valeur vente</th><th class="right">Marge</th><th class="right">Taux marge</th></tr>
        <?php foreach ($cats as $c):
            $va = (float)($c['val_achat'] ?? 0);
            $vv = (float)($c['val_vente'] ?? 0);
            $mg = (float)($c['marge'] ?? 0);
            $tx = $va > 0 ? round($mg / $va * 100, 1) : 0;
        ?>
        <tr>
            <td style="font-weight:600"><?= htmlspecialchars($c['nom']) ?></td>
            <td class="right"><?= $c['nb'] ?? 0 ?></td>
            <td class="right"><?= number_format($va,0,',',' ') ?></td>
            <td class="right"><?= number_format($vv,0,',',' ') ?></td>
            <td class="right <?= $mg>=0?'green':'red' ?>"><?= number_format($mg,0,',',' ') ?></td>
            <td class="right"><?= $tx ?>%</td>
        </tr>
        <?php endforeach; ?>
        <tr class="total-row">
            <td>TOTAL</td>
            <td class="right"><?= count($produits) ?></td>
            <td class="right"><?= number_format($val_achat_total,0,',',' ') ?></td>
            <td class="right"><?= number_format($val_vente_total,0,',',' ') ?></td>
            <td class="right <?= $marge_total>=0?'green':'red' ?>"><?= number_format($marge_total,0,',',' ') ?></td>
            <td class="right"><?= $val_achat_total>0 ? round($marge_total/$val_achat_total*100,1) : 0 ?>%</td>
        </tr>
    </table>

    <!-- Détail par produit -->
    <h2 style="font-size:13px;margin-bottom:8px">Détail par produit</h2>
    <table>
        <tr><th>Référence</th><th>Désignation</th><th>Catégorie</th><th class="right">Stock</th><th class="right">P.U. Achat</th><th class="right">P.U. Vente</th><th class="right">Val. Stock Achat</th><th class="right">Val. Stock Vente</th><th class="right">Marge</th></tr>
        <?php
        $current_cat = null;
        foreach ($produits as $p):
            $va = $p['quantite'] * $p['prix_achat'];
            $vv = $p['quantite'] * $p['prix_vente'];
            $mg = $vv - $va;
            if ($current_cat !== $p['cat']):
                $current_cat = $p['cat'];
        ?>
        <tr class="cat-header"><td colspan="9"><?= htmlspecialchars($p['cat'] ?? 'Non classé') ?></td></tr>
        <?php endif; ?>
        <tr>
            <td><?= htmlspecialchars($p['reference']) ?></td>
            <td><?= htmlspecialchars($p['nom']) ?></td>
            <td><?= htmlspecialchars($p['cat'] ?? '—') ?></td>
            <td class="right" style="font-weight:600"><?= $p['quantite'] ?> <?= $p['unite'] ?></td>
            <td class="right"><?= number_format($p['prix_achat'],0,',',' ') ?></td>
            <td class="right"><?= number_format($p['prix_vente'],0,',',' ') ?></td>
            <td class="right"><?= number_format($va,0,',',' ') ?></td>
            <td class="right"><?= number_format($vv,0,',',' ') ?></td>
            <td class="right <?= $mg>=0?'green':'red' ?>"><?= number_format($mg,0,',',' ') ?></td>
        </tr>
        <?php endforeach; ?>
        <tr class="total-row">
            <td colspan="6">TOTAL</td>
            <td class="right"><?= number_format($val_achat_total,0,',',' ') ?></td>
            <td class="right"><?= number_format($val_vente_total,0,',',' ') ?></td>
            <td class="right <?= $marge_total>=0?'green':'red' ?>"><?= number_format($marge_total,0,',',' ') ?></td>
        </tr>
    </table>

    <div class="footer">
        <span><?= htmlspecialchars($ent['nom']) ?> · <?= htmlspecialchars($ent['adresse'] ?? '') ?> · <?= htmlspecialchars($ent['telephone'] ?? '') ?></span>
        <span>Document confidentiel — Accès restreint</span>
    </div>

    <div class="signature">
        <div class="signature-box"><div class="signature-line">Responsable financier</div></div>
        <div class="signature-box"><div class="signature-line">Comptable</div></div>
        <div class="signature-box"><div class="signature-line">Direction</div></div>
    </div>

    <script>window.print()</script>
    </body></html>
    <?php exit;
}

// ============================================
// PAGE EXPORTS
// ============================================
$date_debut = date('Y-m-01');
$date_fin   = date('Y-m-d');
$nb_produits = $db->query("SELECT COUNT(*) FROM produits WHERE actif=1")->fetchColumn();
$nb_mouvements = $db->query("SELECT COUNT(*) FROM mouvements WHERE DATE(created_at) BETWEEN '".date('Y-m-01')."' AND '".date('Y-m-d')."'")->fetchColumn();
?>

<div style="max-width:800px">
    <div style="background:linear-gradient(135deg,var(--primary),color-mix(in srgb,var(--primary) 60%,#000));border-radius:var(--radius);padding:28px;color:white;margin-bottom:24px">
        <div style="font-family:'Manrope',sans-serif;font-size:20px;font-weight:800;margin-bottom:6px">📤 Centre d'exports</div>
        <p style="opacity:.8;font-size:14px">Exportez vos données en CSV (Excel) ou générez des états imprimables.</p>
    </div>

    <!-- Rapports imprimés -->
    <div class="card" style="margin-bottom:16px">
        <div class="card-header">
            <div class="card-title">🖨️ Rapports imprimés</div>
        </div>
        <div class="card-body">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
                <!-- Inventaire -->
                <div style="padding:20px;border-radius:14px;border:2px solid var(--success);background:color-mix(in srgb,var(--success) 6%,transparent);position:relative">
                    <div style="display:flex;align-items:center;gap:10px;margin-bottom:12px">
                        <span style="font-size:32px">📋</span>
                        <div>
                            <div style="font-family:var(--font-heading);font-weight:700;font-size:16px;color:var(--text)">Inventaire de stock</div>
                            <div style="font-size:12px;color:var(--muted)">Sans prix — Usage interne</div>
                        </div>
                    </div>
                    <div style="font-size:13px;color:var(--text-2);margin-bottom:16px;line-height:1.5">
                        État complet des quantités en stock par catégorie. Statuts OK / Faible / Rupture. Zone de signature pour validation physique.
                    </div>
                    <a href="?export=inventaire_print" target="_blank" class="btn btn-primary" style="width:100%;justify-content:center">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                        Imprimer l'inventaire
                    </a>
                </div>
                <!-- Financier -->
                <div style="padding:20px;border-radius:14px;border:2px solid var(--warning);background:color-mix(in srgb,var(--warning) 6%,transparent);position:relative">
                    <div style="position:absolute;top:12px;right:12px"><span class="badge badge-red" style="font-size:10px">CONFIDENTIEL</span></div>
                    <div style="display:flex;align-items:center;gap:10px;margin-bottom:12px">
                        <span style="font-size:32px">💰</span>
                        <div>
                            <div style="font-family:var(--font-heading);font-weight:700;font-size:16px;color:var(--text)">Rapport financier</div>
                            <div style="font-size:12px;color:var(--muted)">Accès restreint</div>
                        </div>
                    </div>
                    <div style="font-size:13px;color:var(--text-2);margin-bottom:16px;line-height:1.5">
                        Valeurs d'achat/vente, marges par catégorie et par produit. KPIs financiers. Achats et ventes du mois.
                    </div>
                    <a href="?export=financier_print" target="_blank" class="btn btn-primary" style="width:100%;justify-content:center;background:var(--warning);box-shadow:0 2px 10px color-mix(in srgb,var(--warning) 30%,transparent)">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                        Imprimer le financier
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Export CSV Produits -->
    <div class="card" style="margin-bottom:16px">
        <div class="card-header">
            <div class="card-title">📦 Produits & Stock</div>
            <span class="badge badge-blue"><?= $nb_produits ?> produits</span>
        </div>
        <div class="card-body" style="display:flex;gap:12px;flex-wrap:wrap">
            <a href="?export=produits_csv" class="btn btn-primary">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                Télécharger CSV (Excel)
            </a>
        </div>
    </div>

    <!-- Export Mouvements -->
    <div class="card" style="margin-bottom:16px">
        <div class="card-header">
            <div class="card-title">🔄 Mouvements de stock</div>
            <span class="badge badge-green"><?= $nb_mouvements ?> ce mois</span>
        </div>
        <div class="card-body">
            <?php if ($msg_mouvements_vide): ?>
            <div class="alert alert-warning">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                Aucun mouvement enregistré pour cette période. Le fichier CSV serait vide.
            </div>
            <?php endif; ?>
            <form method="GET" style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap">
                <input type="hidden" name="export" value="mouvements_csv">
                <div class="form-group" style="margin:0">
                    <label class="form-label">Du</label>
                    <input type="date" class="form-control" name="date_debut" value="<?= date('Y-m-01') ?>" style="width:160px">
                </div>
                <div class="form-group" style="margin:0">
                    <label class="form-label">Au</label>
                    <input type="date" class="form-control" name="date_fin" value="<?= date('Y-m-d') ?>" style="width:160px">
                </div>
                <button type="submit" class="btn btn-primary">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    Télécharger CSV
                </button>
            </form>
        </div>
    </div>

    <!-- Export Alertes -->
    <div class="card">
        <div class="card-header"><div class="card-title">⚠️ Rapport stock faible</div></div>
        <div class="card-body">
            <?php
            $alertes = $db->query("SELECT p.*,c.nom as cat FROM produits p LEFT JOIN categories c ON p.categorie_id=c.id WHERE p.quantite <= p.quantite_min AND p.actif=1 ORDER BY p.quantite ASC")->fetchAll();
            ?>
            <?php if (empty($alertes)): ?>
                <div style="color:var(--success);font-weight:600">✅ Aucun produit en alerte stock actuellement.</div>
            <?php else: ?>
                <div class="alert alert-warning" style="margin-bottom:16px">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/></svg>
                    <?= count($alertes) ?> produit(s) en alerte ou rupture de stock
                </div>
                <table>
                    <thead><tr><th>Produit</th><th>Catégorie</th><th>Stock</th><th>Minimum</th><th>Statut</th></tr></thead>
                    <tbody>
                    <?php foreach ($alertes as $p): ?>
                    <tr>
                        <td>
                            <div style="font-weight:600;font-size:13px"><?= htmlspecialchars($p['nom']) ?></div>
                            <div style="font-size:11px;color:var(--muted)"><?= $p['reference'] ?></div>
                        </td>
                        <td style="font-size:13px"><?= htmlspecialchars($p['cat']??'—') ?></td>
                        <td style="font-weight:700;color:<?= $p['quantite']==0?'var(--danger)':'var(--warning)' ?>"><?= $p['quantite'] ?> <?= $p['unite'] ?></td>
                        <td style="color:var(--muted)"><?= $p['quantite_min'] ?></td>
                        <td><span class="badge <?= $p['quantite']==0?'badge-red':'badge-orange' ?>"><?= $p['quantite']==0?'Rupture':'Stock faible' ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>