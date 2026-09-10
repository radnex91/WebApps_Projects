<?php
// ============================================================
//  MediCore ERP - Backfill comptable idempotent
//  Remonte l'historique des flux existants en écritures compta.
//  Idempotent : skip les sources déjà générées.
//  Usage : php sql/backfill_compta.php  (ou via navigateur)
// ============================================================
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/comptabilite.php';

if (!compta_enabled()) { echo "Compta désactivée (migration v9 requise).\n"; exit(1); }

$stats = ['caisse' => 0, 'factures' => 0, 'stocks' => 0, 'skip' => 0];

// 1. Caisse : tickets payés
$ventes = db_select("SELECT * FROM caisse_ventes WHERE statut='paye' ORDER BY id ASC");
foreach ($ventes as $v) {
    $exists = db_scalar("SELECT 1 FROM compta_ecritures WHERE source_table='caisse_ventes' AND source_id=? AND libelle NOT LIKE 'ANNULATION%' LIMIT 1", [$v['id']]);
    if ($exists) { $stats['skip']++; continue; }
    compta_on_vente_caisse($v);
    $stats['caisse']++;
}

// 1b. Caisse : tickets annulés -> écriture originale + miroir (à la date de vente)
$annuls = db_select("SELECT * FROM caisse_ventes WHERE statut='annule' ORDER BY id ASC");
foreach ($annuls as $v) {
    $existsOrig = db_scalar("SELECT 1 FROM compta_ecritures WHERE source_table='caisse_ventes' AND source_id=? AND libelle NOT LIKE 'ANNULATION%' LIMIT 1", [$v['id']]);
    if (!$existsOrig) {
        compta_on_vente_caisse($v);
        $stats['caisse']++;
    } else { $stats['skip']++; }
    $existsMirror = db_scalar("SELECT 1 FROM compta_ecritures WHERE source_table='caisse_ventes' AND source_id=? AND libelle LIKE 'ANNULATION%' LIMIT 1", [$v['id']]);
    if (!$existsMirror) {
        compta_on_annulation_vente($v);
        $stats['caisse']++;
    }
}

// 2. Factures : création + règlement
$facts = db_select("SELECT * FROM factures ORDER BY id ASC");
foreach ($facts as $f) {
    $existsCrea = db_scalar("SELECT 1 FROM compta_ecritures WHERE source_table='factures' AND source_id=? AND libelle LIKE '%création%' LIMIT 1", [$f['id']]);
    if (!$existsCrea) { compta_on_facture($f, 'creation'); $stats['factures']++; }
    else { $stats['skip']++; }
    $statutNorm = str_replace(['é','è'], ['e','e'], strtolower(trim($f['statut'])));
    if ($statutNorm === 'reglee') {
        $existsRegl = db_scalar("SELECT 1 FROM compta_ecritures WHERE source_table='factures' AND source_id=? AND libelle LIKE 'Encaissement%' LIMIT 1", [$f['id']]);
        if (!$existsRegl) {
            compta_on_facture($f + ['mode_paiement' => 'especes', 'regle_par' => null], 'reglement');
            $stats['factures']++;
        }
    }
}

// 3. Stocks : entrées validées
$entries = db_select("SELECT * FROM stock_entries WHERE statut='validee' ORDER BY id ASC");
foreach ($entries as $e) {
    $exists = db_scalar("SELECT 1 FROM compta_ecritures WHERE source_table='stock_entries' AND source_id=? LIMIT 1", [$e['id']]);
    if ($exists) { $stats['skip']++; continue; }
    compta_on_achat_stock($e);
    $stats['stocks']++;
}

$td = (float)db_scalar("SELECT SUM(debit) FROM compta_ecriture_lignes");
$tc = (float)db_scalar("SELECT SUM(credit) FROM compta_ecriture_lignes");
echo "Backfill termine : caisse={$stats['caisse']} factures={$stats['factures']} stocks={$stats['stocks']} skips={$stats['skip']}\n";
echo "Total debit=$td Total credit=$tc " . (abs($td-$tc)<0.01?'(equilibre OK)':'(DESEQUILIBRE!)') . "\n";