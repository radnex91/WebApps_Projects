<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
Auth::requirePermission('bordereaux_imprimer');
$user = Auth::currentUser() ?: ['nom' => 'Système'];

$colisId  = isset($_GET['id'])     ? intval($_GET['id'])     : 0;
$voyageId = isset($_GET['voyage']) ? intval($_GET['voyage']) : 0;
$multi    = isset($_GET['multi'])  ? array_map('intval', explode(',', $_GET['multi'])) : [];

if ($colisId) {
    $colis = [Database::fetchOne(
        "SELECT c.*, ad.nom agd, ad.nom vd, ad.adresse aad, ad.telephone td,
                aa.nom aga, aa.nom va, aa.adresse aaa, aa.telephone ta,
                v.numero_voyage, v.transporteur, v.date_depart, v.matricule_vehicule, v.chauffeur,
                CONCAT(u.prenom,' ',u.nom) AS operateur
         FROM colis c JOIN agences ad ON c.agence_depart_id=ad.id JOIN agences aa ON c.agence_arrivee_id=aa.id
         LEFT JOIN voyages v ON c.voyage_id=v.id JOIN utilisateurs u ON c.cree_par=u.id
         WHERE c.id=?", [$colisId]
    )];
    $title = 'Bordereau de transport — ' . ($colis[0]['numero_colis'] ?? '');
} elseif ($voyageId) {
    $voyage = Database::fetchOne(
        "SELECT v.*, ad.nom agd, ad.nom vd, aa.nom aga, aa.nom va,
                CONCAT(u.prenom,' ',u.nom) AS operateur
         FROM voyages v JOIN agences ad ON v.agence_depart_id=ad.id JOIN agences aa ON v.agence_arrivee_id=aa.id
         JOIN utilisateurs u ON v.cree_par=u.id WHERE v.id=?", [$voyageId]
    );
    $colis = Database::fetchAll(
        "SELECT c.*, ad.nom vd, aa.nom va FROM colis c
         JOIN agences ad ON c.agence_depart_id=ad.id JOIN agences aa ON c.agence_arrivee_id=aa.id
         WHERE c.voyage_id=? ORDER BY c.created_at", [$voyageId]
    );
    $title = 'Manifeste voyage — ' . ($voyage['numero_voyage'] ?? '');
} elseif ($multi) {
    $in = implode(',', $multi);
    $colis = Database::fetchAll(
        "SELECT c.*, ad.nom agd, ad.nom vd, aa.nom aga, aa.nom va FROM colis c
         JOIN agences ad ON c.agence_depart_id=ad.id JOIN agences aa ON c.agence_arrivee_id=aa.id
         WHERE c.id IN ($in) ORDER BY c.created_at"
    );
    $title = 'Bordereau groupé (' . count($colis) . ' colis)';
} else {
    die('Paramètre manquant');
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title><?= htmlspecialchars($title) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;600;700&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Sora',sans-serif;font-size:11px;color:#1a1a1a;background:white}
.page{width:210mm;min-height:297mm;padding:12mm;margin:0 auto;background:white}
.header{display:flex;justify-content:space-between;align-items:flex-start;
    border-bottom:3px solid #E8500A;padding-bottom:10px;margin-bottom:10px}
.logo-area{display:flex;align-items:center;gap:10px}
.logo-box{width:40px;height:40px;background:linear-gradient(135deg,#E8500A,#F5A623);
    border-radius:8px;display:flex;align-items:center;justify-content:center;
    font-size:18px;color:white;font-weight:900;flex-shrink:0}
.logo-name{font-size:14px;font-weight:800;color:#1a1a1a}
.logo-sub{font-size:8px;color:#E8500A;text-transform:uppercase;letter-spacing:2px;display:block}
.doc-info{text-align:right}
.doc-num{font-family:'Space Mono',monospace;font-size:14px;font-weight:700;color:#E8500A}
.doc-date{font-size:9px;color:#666;margin-top:2px}
.doc-title{font-size:10px;font-weight:700;text-transform:uppercase;color:#333;margin-top:2px}

/* SINGLE COLIS LAYOUT */
.bordereau-box{border:1px solid #e0e0e0;border-radius:6px;overflow:hidden;margin-bottom:10px}
.bordereau-header{background:#E8500A;color:white;padding:6px 12px;
    display:flex;justify-content:space-between;align-items:center}
.bordereau-header-title{font-size:10px;font-weight:700;text-transform:uppercase}
.bordereau-header-num{font-family:'Space Mono',monospace;font-size:11px;font-weight:700}
.bordereau-body{display:grid;grid-template-columns:1fr 1fr;gap:0}
.col-box{padding:10px 12px;border-right:1px solid #e0e0e0}
.col-box:last-child{border-right:none}
.col-label{font-size:8px;font-weight:700;color:#E8500A;text-transform:uppercase;letter-spacing:1px;margin-bottom:4px}
.col-name{font-size:12px;font-weight:700;margin-bottom:2px}
.col-phone{font-size:10px;color:#444;font-family:'Space Mono',monospace}
.col-addr{font-size:9px;color:#666;margin-top:2px}

.details-grid{display:grid;grid-template-columns:repeat(4,1fr);border-top:1px solid #e0e0e0}
.detail-cell{padding:6px 10px;border-right:1px solid #e0e0e0;text-align:center}
.detail-cell:last-child{border-right:none}
.detail-cell-label{font-size:8px;color:#666;text-transform:uppercase;letter-spacing:.5px}
.detail-cell-val{font-size:11px;font-weight:700;margin-top:1px}

.desc-row{padding:6px 12px;border-top:1px solid #e0e0e0;background:#fafafa}
.desc-label{font-size:8px;font-weight:700;color:#E8500A;text-transform:uppercase;display:block;margin-bottom:2px}
.desc-text{font-size:10px;color:#333}

.payment-bar{background:#1A1D2E;color:white;padding:6px 12px;
    display:flex;justify-content:space-between;align-items:center;border-top:1px solid #e0e0e0}
.payment-bar span{font-size:9px;opacity:.7}
.payment-bar strong{font-family:'Space Mono',monospace;font-size:13px;color:#F5A623}

.signatures{display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-top:10px}
.sig-box{border:1px solid #e0e0e0;border-radius:4px;padding:8px;min-height:55px}
.sig-label{font-size:8px;font-weight:700;text-transform:uppercase;color:#666;margin-bottom:20px;display:block}

.conditions{background:#f9f9f9;border:1px solid #e0e0e0;border-radius:4px;padding:8px;margin-top:8px;font-size:8px;color:#666}
.conditions strong{color:#E8500A;text-transform:uppercase;display:block;margin-bottom:4px;font-size:8px}

.badge{display:inline-block;padding:2px 8px;border-radius:10px;font-size:8px;font-weight:700;text-transform:uppercase}
.b-normal{background:#DBEAFE;color:#1D4ED8}
.b-accompagne{background:#EDE9FE;color:#7C3AED}
.b-paye{background:#DCFCE7;color:#16A34A}
.b-attente{background:#FEF9C3;color:#CA8A04}

/* MANIFESTE VOYAGE */
.manifeste-header{background:#1A1D2E;color:white;padding:10px 14px;border-radius:6px;margin-bottom:10px}
.manifeste-route{font-size:16px;font-weight:800;margin-bottom:4px}
.manifeste-meta{font-size:9px;opacity:.7;display:flex;gap:20px;flex-wrap:wrap}
.manifeste-meta span{display:flex;align-items:center;gap:4px}

table.manifest-table{width:100%;border-collapse:collapse;font-size:9px;margin-bottom:10px}
.manifest-table thead th{background:#E8500A;color:white;padding:5px 8px;text-align:left;
    font-size:8px;text-transform:uppercase;letter-spacing:.5px}
.manifest-table tbody tr:nth-child(even){background:#f5f5f5}
.manifest-table td{padding:5px 8px;border-bottom:1px solid #e8e8e8;vertical-align:top}
.manifest-table tfoot td{background:#1A1D2E;color:white;padding:6px 8px;font-weight:700}
.mono{font-family:'Space Mono',monospace}

.print-btn{position:fixed;top:16px;right:16px;display:flex;gap:8px}
.btn-p{padding:8px 16px;border-radius:6px;cursor:pointer;border:none;font-family:'Sora',sans-serif;font-size:12px;font-weight:600}
.btn-print{background:#E8500A;color:white}
.btn-close{background:#1a1a1a;color:white}
@media print{.print-btn{display:none!important}body{background:white}}
@page{margin:10mm;size:A4}
</style>
</head>
<body>
<div class="print-btn">
    <button class="btn-p btn-print" onclick="window.print()">🖨️ Imprimer</button>
    <button class="btn-p btn-close" onclick="window.close()">✕ Fermer</button>
</div>

<div class="page">
    <!-- HEADER -->
    <div class="header">
        <div class="logo-area">
            <div class="logo-box">🚚</div>
            <div>
                <div class="logo-name">Atlas Prime Logistics</div>
                <span class="logo-sub">Votre colis, notre priorité</span>
                <div style="font-size:8px;color:#666;margin-top:1px">contact@atlasprime.cm | +237 222 00 00 00</div>
            </div>
        </div>
        <div class="doc-info">
            <?php if ($voyageId && isset($voyage)): ?>
            <div class="doc-num"><?= $voyage['numero_voyage'] ?></div>
            <div class="doc-title">Manifeste de voyage</div>
            <?php elseif ($colis): ?>
            <div class="doc-num"><?= $colis[0]['numero_colis'] ?></div>
            <div class="doc-title">Bordereau de transport</div>
            <?php endif; ?>
            <div class="doc-date">Édité le <?= date('d/m/Y à H:i') ?> par <?= htmlspecialchars($user['nom']) ?></div>
        </div>
    </div>

    <?php if ($voyageId && isset($voyage)): ?>
    <!-- MANIFESTE VOYAGE -->
    <div class="manifeste-header">
        <div class="manifeste-route">✈ <?= htmlspecialchars($voyage['vd']) ?> → <?= htmlspecialchars($voyage['va']) ?></div>
        <div class="manifeste-meta">
            <span>🚛 <?= htmlspecialchars($voyage['transporteur']) ?></span>
            <?php if ($voyage['matricule_vehicule']): ?>
            <span>🔢 <?= htmlspecialchars($voyage['matricule_vehicule']) ?></span>
            <?php endif; ?>
            <?php if ($voyage['chauffeur']): ?>
            <span>👤 <?= htmlspecialchars($voyage['chauffeur']) ?></span>
            <?php endif; ?>
            <span>📅 Départ: <?= formatDate($voyage['date_depart'],'d/m/Y H:i') ?></span>
            <span>📦 <?= count($colis) ?> colis</span>
        </div>
    </div>

    <table class="manifest-table">
        <thead><tr>
            <th>#</th><th>N° Colis</th><th>Expéditeur</th><th>Destinataire</th>
            <th>Description</th><th>Poids</th><th>Pièces</th><th>Montant</th><th>Statut</th>
        </tr></thead>
        <tbody>
        <?php
        $totalMontant = 0;
        $totalPoids = 0;
        $totalPieces = 0;
        foreach ($colis as $i => $c):
            $totalMontant += $c['montant_total'];
            $totalPoids   += $c['poids'];
            $totalPieces  += $c['nombre_pieces'];
        ?>
        <tr>
            <td><?= $i+1 ?></td>
            <td class="mono" style="font-weight:700;color:#E8500A"><?= $c['numero_colis'] ?></td>
            <td><strong><?= htmlspecialchars($c['expediteur_nom']) ?></strong><br><?= htmlspecialchars($c['expediteur_telephone']) ?></td>
            <td><strong><?= htmlspecialchars($c['destinataire_nom']) ?></strong><br><?= htmlspecialchars($c['destinataire_telephone']) ?></td>
            <td style="max-width:120px"><?= htmlspecialchars(substr($c['description'],0,60)) ?><?= strlen($c['description'])>60?'...':'' ?></td>
            <td class="mono"><?= $c['poids'] ?>kg</td>
            <td class="mono"><?= $c['nombre_pieces'] ?></td>
            <td class="mono" style="font-weight:700"><?= number_format($c['montant_total'],0,',',' ') ?> F</td>
            <td><?= ucfirst(str_replace('_',' ',$c['statut'])) ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot><tr>
            <td colspan="5" style="text-align:right">TOTAUX :</td>
            <td class="mono"><?= number_format($totalPoids,2) ?>kg</td>
            <td class="mono"><?= $totalPieces ?></td>
            <td class="mono" colspan="2"><?= number_format($totalMontant,0,',',' ') ?> FCFA</td>
        </tr></tfoot>
    </table>

    <div class="signatures">
        <div class="sig-box"><span class="sig-label">Signature Responsable Départ</span><br><div style="margin-top:5px;border-top:1px solid #ccc;padding-top:5px;font-size:9px;color:#999">Date: ________________</div></div>
        <div class="sig-box"><span class="sig-label">Signature Chauffeur</span><br><div style="margin-top:5px;border-top:1px solid #ccc;padding-top:5px;font-size:9px;color:#999">Date: ________________</div></div>
    </div>

    <?php else: ?>
    <!-- BORDEREAUX INDIVIDUELS — CLIENT + COMPTABILITÉ -->
    <?php foreach ($colis as $c):
        $details = Database::fetchAll("SELECT * FROM colis_details WHERE colis_id=? ORDER BY id", [$c['id']]);
    ?>

    <?php foreach (['client' => 'CLIENT', 'comptabilite' => 'COMPTABILITÉ'] as $partieKey => $partieLabel): ?>
    <div class="bordereau-box">
        <div class="bordereau-header">
            <div>
                <div class="bordereau-header-title">
Bordereau de transport
                    <span class="badge <?= $c['type_expedition']==='accompagne'?'b-accompagne':'b-normal' ?>"
                          style="margin-left:6px"><?= $c['type_expedition']==='accompagne'?'Accompagné':'Expédition' ?></span>
                </div>
            </div>
            <div style="text-align:right">
                <div class="bordereau-header-num"><?= $c['numero_colis'] ?></div>
                <div style="font-size:9px;font-weight:700;letter-spacing:1px;margin-top:2px;color:<?= $partieKey==='client' ? '#E8500A' : '#7C3AED' ?>">
                    ◆ PARTIE <?= $partieLabel ?>
                </div>
            </div>
        </div>
        <div class="bordereau-body">
            <div class="col-box">
                <div class="col-label">▶ Expéditeur</div>
                <div class="col-name"><?= htmlspecialchars($c['expediteur_nom']) ?></div>
                <div class="col-phone"><?= htmlspecialchars($c['expediteur_telephone']) ?></div>
                <?php if ($c['expediteur_ville']): ?>
                <div class="col-addr"><?= htmlspecialchars($c['expediteur_ville']) ?><?= $c['expediteur_adresse'] ? ', '.$c['expediteur_adresse'] : '' ?></div>
                <?php endif; ?>
                <div style="margin-top:6px;padding-top:6px;border-top:1px dashed #ddd;font-size:8px;color:#E8500A">
                    <strong>Agence départ:</strong> <?= htmlspecialchars($c['agd']) ?> — <?= htmlspecialchars($c['vd']) ?>
                </div>
            </div>
            <div class="col-box" style="border-right:none">
                <div class="col-label">▶ Destinataire</div>
                <div class="col-name"><?= htmlspecialchars($c['destinataire_nom']) ?></div>
                <div class="col-phone"><?= htmlspecialchars($c['destinataire_telephone']) ?></div>
                <?php if ($c['destinataire_ville']): ?>
                <div class="col-addr"><?= htmlspecialchars($c['destinataire_ville']) ?><?= $c['destinataire_adresse'] ? ', '.$c['destinataire_adresse'] : '' ?></div>
                <?php endif; ?>
                <div style="margin-top:6px;padding-top:6px;border-top:1px dashed #ddd;font-size:8px;color:#E8500A">
                    <strong>Agence arrivée:</strong> <?= htmlspecialchars($c['aga'] ?? $c['aga']) ?> — <?= htmlspecialchars($c['va']) ?>
                </div>
            </div>
        </div>

        <?php if (!empty($details)): ?>
        <table style="width:100%;border-collapse:collapse;border-top:1px solid #e0e0e0;font-size:9px">
            <thead><tr style="background:#fafafa">
                <th style="padding:4px 8px;text-align:left;font-size:7px;color:#999;text-transform:uppercase;border-bottom:1px solid #e0e0e0">Description</th>
                <th style="padding:4px 8px;text-align:right;font-size:7px;color:#999;text-transform:uppercase;border-bottom:1px solid #e0e0e0;width:60px">Poids</th>
                <th style="padding:4px 8px;text-align:right;font-size:7px;color:#999;text-transform:uppercase;border-bottom:1px solid #e0e0e0;width:50px">Pièces</th>
                <th style="padding:4px 8px;text-align:right;font-size:7px;color:#999;text-transform:uppercase;border-bottom:1px solid #e0e0e0;width:70px">Valeur</th>
            </tr></thead>
            <tbody>
            <?php foreach ($details as $d): ?>
            <tr>
                <td style="padding:3px 8px;border-bottom:1px solid #f0f0f0"><?= htmlspecialchars($d['description']) ?></td>
                <td style="padding:3px 8px;text-align:right;border-bottom:1px solid #f0f0f0;font-family:'Space Mono',monospace"><?= $d['poids'] > 0 ? number_format($d['poids'],2,',',' ').' kg' : '—' ?></td>
                <td style="padding:3px 8px;text-align:right;border-bottom:1px solid #f0f0f0;font-family:'Space Mono',monospace"><?= intval($d['nombre_pieces']) ?></td>
                <td style="padding:3px 8px;text-align:right;border-bottom:1px solid #f0f0f0;font-family:'Space Mono',monospace"><?= $d['valeur_declaree'] > 0 ? number_format($d['valeur_declaree'],0,',',' ').' F' : '—' ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot><tr style="background:#fafafa;font-weight:700">
                <td style="padding:4px 8px">Total</td>
                <td style="padding:4px 8px;text-align:right;font-family:'Space Mono',monospace"><?= number_format($c['poids'],2,',',' ') ?> kg</td>
                <td style="padding:4px 8px;text-align:right;font-family:'Space Mono',monospace"><?= intval($c['nombre_pieces']) ?></td>
                <td style="padding:4px 8px;text-align:right;font-family:'Space Mono',monospace"><?= number_format((float)$c['valeur_declaree'],0,',',' ') ?> F</td>
            </tr></tfoot>
        </table>
        <?php else: ?>
        <div class="desc-row">
            <span class="desc-label">Description du contenu</span>
            <span class="desc-text"><?= htmlspecialchars($c['description']) ?></span>
        </div>
        <div class="details-grid">
            <div class="detail-cell">
                <div class="detail-cell-label">Poids</div>
                <div class="detail-cell-val mono"><?= $c['poids'] ?: '—' ?> kg</div>
            </div>
            <div class="detail-cell">
                <div class="detail-cell-label">Pièces</div>
                <div class="detail-cell-val mono"><?= $c['nombre_pieces'] ?></div>
            </div>
            <div class="detail-cell">
                <div class="detail-cell-label">Date expédition</div>
                <div class="detail-cell-val"><?= formatDate($c['date_expedition'],'d/m/Y') ?></div>
            </div>
            <div class="detail-cell">
                <div class="detail-cell-label">Paiement</div>
                <div class="detail-cell-val">
                    <span class="badge <?= $c['statut_paiement']==='paye'?'b-paye':'b-attente' ?>"><?= ucfirst(str_replace('_',' ',$c['statut_paiement'])) ?></span>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($c['numero_voyage'])): ?>
        <div class="desc-row" style="background:#EDE9FE">
            <span class="desc-label" style="color:#7C3AED">Voyage associé</span>
            <span class="desc-text"><?= htmlspecialchars($c['numero_voyage']) ?> — <?= htmlspecialchars($c['transporteur']??'') ?>
            <?php if (!empty($c['date_depart'])): ?> — Départ: <?= formatDate($c['date_depart'],'d/m/Y H:i') ?><?php endif; ?>
            </span>
        </div>
        <?php endif; ?>
        <div class="payment-bar">
            <span>Mode: <?= ucfirst(str_replace('_',' ',$c['mode_paiement'])) ?></span>
            <span>Tarif: <strong><?= number_format($c['tarif'],0,',',' ') ?> F</strong></span>
            <?php if ($c['remise'] > 0): ?>
            <span>Remise: -<?= number_format($c['remise'],0,',',' ') ?> F</span>
            <?php endif; ?>
            <strong><?= number_format($c['montant_total'],0,',',' ') ?> FCFA</strong>
        </div>
    </div>

    <?php if ($partieKey === 'client'): ?>
    <div class="signatures">
        <div class="sig-box"><span class="sig-label">Signature Expéditeur</span></div>
        <div class="sig-box"><span class="sig-label">Cachet Atlas Prime</span></div>
    </div>
    <!-- LIGNE DE COUPE -->
    <div style="border-top:2px dashed #E8500A;margin:14px 0;text-align:center;position:relative">
        <span style="position:relative;top:-8px;background:white;padding:0 12px;font-size:7px;color:#E8500A;font-weight:700;letter-spacing:2px">✂ COUPER ICI ✂</span>
    </div>
    <?php else: ?>
    <div class="signatures">
        <div class="sig-box"><span class="sig-label">Signature Opérateur</span></div>
        <div class="sig-box"><span class="sig-label">Visa Comptabilité</span></div>
    </div>
    <?php endif; ?>

    <?php endforeach; ?>

    <!-- LIGNE DE COUPE AVANT TALON -->
    <div style="border-top:2px dashed #E8500A;margin:14px 0;text-align:center;position:relative">
        <span style="position:relative;top:-8px;background:white;padding:0 12px;font-size:7px;color:#E8500A;font-weight:700;letter-spacing:2px">✂ COUPER ICI ✂</span>
    </div>

    <!-- TALONS ÉTIQUETTES — 1 par pièce -->
    <?php $nbPieces = max(1, intval($c['nombre_pieces'])); ?>
    <?php for ($pieceIdx = 1; $pieceIdx <= $nbPieces; $pieceIdx++): ?>
    <?php if ($pieceIdx > 1): ?>
    <div style="border-top:2px dashed #E8500A;margin:8px 0;text-align:center;position:relative">
        <span style="position:relative;top:-6px;background:white;padding:0 8px;font-size:7px;color:#E8500A;font-weight:700;letter-spacing:2px">✂ COUPER ICI ✂</span>
    </div>
    <?php endif; ?>
    <div style="border:2px solid #1A1D2E;border-radius:8px;overflow:hidden">
        <div style="background:#1A1D2E;color:white;padding:6px 12px;display:flex;justify-content:space-between;align-items:center">
            <div style="font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:1px">🏷️ Étiquette<?= $nbPieces > 1 ? ' pièce '.$pieceIdx.'/'.$nbPieces : '' ?></div>
            <div style="font-family:'Space Mono',monospace;font-size:14px;font-weight:700;color:#F5A623"><?= $c['numero_colis'] ?></div>
        </div>
        <div style="display:grid;grid-template-columns:1fr auto 1fr;gap:0;font-size:10px">
            <div style="padding:8px 10px">
                <div style="font-size:7px;font-weight:700;color:#E8500A;text-transform:uppercase;letter-spacing:1px;margin-bottom:3px">Expéditeur</div>
                <div style="font-weight:700"><?= htmlspecialchars($c['expediteur_nom']) ?></div>
                <div style="font-family:'Space Mono',monospace;font-size:9px;color:#444"><?= htmlspecialchars($c['expediteur_telephone']) ?></div>
                <?php if ($c['expediteur_ville']): ?>
                <div style="font-size:8px;color:#666"><?= htmlspecialchars($c['expediteur_ville']) ?></div>
                <?php endif; ?>
            </div>
            <div style="display:flex;align-items:center;padding:0 6px">
                <div style="font-size:18px;color:#E8500A">→</div>
            </div>
            <div style="padding:8px 10px;border-left:1px solid #e0e0e0">
                <div style="font-size:7px;font-weight:700;color:#16A34A;text-transform:uppercase;letter-spacing:1px;margin-bottom:3px">Destinataire</div>
                <div style="font-weight:700"><?= htmlspecialchars($c['destinataire_nom']) ?></div>
                <div style="font-family:'Space Mono',monospace;font-size:9px;color:#444"><?= htmlspecialchars($c['destinataire_telephone']) ?></div>
                <?php if ($c['destinataire_ville']): ?>
                <div style="font-size:8px;color:#666"><?= htmlspecialchars($c['destinataire_ville']) ?></div>
                <?php endif; ?>
            </div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr;border-top:1px solid #e0e0e0;font-size:9px;text-align:center">
            <div style="padding:5px 6px;border-right:1px solid #e0e0e0">
                <div style="font-size:7px;color:#999;text-transform:uppercase">Départ</div>
                <div style="font-weight:600;font-size:9px"><?= htmlspecialchars($c['vd']) ?></div>
            </div>
            <div style="padding:5px 6px;border-right:1px solid #e0e0e0">
                <div style="font-size:7px;color:#999;text-transform:uppercase">Arrivée</div>
                <div style="font-weight:600;font-size:9px"><?= htmlspecialchars($c['va']) ?></div>
            </div>
            <div style="padding:5px 6px;border-right:1px solid #e0e0e0">
                <div style="font-size:7px;color:#999;text-transform:uppercase">Poids</div>
                <div style="font-family:'Space Mono',monospace;font-weight:700"><?= number_format((float)$c['poids'],2,',',' ') ?> kg</div>
            </div>
            <div style="padding:5px 6px">
                <div style="font-size:7px;color:#999;text-transform:uppercase">Pièce</div>
                <div style="font-family:'Space Mono',monospace;font-weight:700"><?= $pieceIdx ?>/<?= $nbPieces ?></div>
            </div>
        </div>
        <?php if (!empty($details)): ?>
        <div style="border-top:1px solid #e0e0e0;padding:4px 10px;font-size:8px;color:#555">
            <?= htmlspecialchars(implode(' · ', array_map(fn($d) => $d['description'], $details))) ?>
        </div>
        <?php elseif ($c['description']): ?>
        <div style="border-top:1px solid #e0e0e0;padding:4px 10px;font-size:8px;color:#555">
            <?= htmlspecialchars(mb_strimwidth($c['description'], 0, 80, '...')) ?>
        </div>
        <?php endif; ?>
        <div style="background:#fafafa;border-top:1px solid #e0e0e0;padding:4px 10px;display:flex;justify-content:space-between;align-items:center;font-size:8px;color:#999">
            <span><?= formatDate($c['date_expedition'],'d/m/Y') ?></span>
            <span style="text-transform:uppercase;letter-spacing:1px">
                <span class="badge <?= $c['type_expedition']==='accompagne'?'b-accompagne':'b-normal' ?>"><?= $c['type_expedition']==='accompagne'?'Accompagné':'Expédition' ?></span>
                <span class="badge <?= $c['statut']==='livre'?'b-paye':'b-attente' ?>" style="margin-left:4px"><?= ucfirst(str_replace('_',' ',$c['statut'])) ?></span>
            </span>
        </div>
    </div>
    <?php endfor; ?>

    <?php if (!end($colis) || $c !== end($colis)): ?>
    <div style="page-break-after:always;margin:10px 0;border-top:1px dashed #ddd"></div>
    <?php endif; ?>
    <?php endforeach; ?>
    <?php endif; ?>

    <div class="conditions">
        <strong>Conditions d'expédition — Atlas Prime Logistics</strong>
        L'expéditeur déclare que le contenu de ce colis est licite et correctement déclaré. Atlas Prime Logistics décline toute
        responsabilité en cas de contenu non déclaré ou illicite. En cas de dommage, la réclamation doit être présentée dans un délai
        de 48h à compter de la réception. La valeur de remboursement ne peut excéder la valeur déclarée.
        <br><br>
        <strong>Atlas Prime Logistics</strong> — contact@atlasprime.cm — +237 222 00 00 00 — www.atlasprime.cm
    </div>
</div>
</body>
</html>
