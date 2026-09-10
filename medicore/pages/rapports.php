<?php
$currentPage = 'rapports';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$s       = get_settings();
$etab    = $s['etablissement'] ?? 'MediCore ERP';
$today   = date('Y-m-d');
$month   = date('Y-m');

//  Paramtres de filtre
$type      = in_whitelist(get_str('type'), ['mensuel','financier','rh','pharmacie','urgences','caisse','patients'], 'mensuel');
$date_from = get_str('date_from') ?: date('Y-m-01');
$date_to   = get_str('date_to')   ?: $today;
$export    = in_whitelist(get_str('export'), ['', 'csv', 'print'], '');

// Valider dates
if (!validate_date($date_from)) $date_from = date('Y-m-01');
if (!validate_date($date_to))   $date_to   = $today;
if ($date_from > $date_to)      [$date_from, $date_to] = [$date_to, $date_from];

//  Charger les donnes selon le type
//  Layout inclus ici  aprs toute logique PHP
require_once __DIR__ . '/../includes/layout.php';
requirePageAccess('rapports');
$data = [];
$title = '';
$cols  = [];

switch ($type) {

    //  RAPPORT MENSUEL
    case 'mensuel':
        $title = 'Rapport d\'activit mensuelle';
        $cols  = ['Indicateur', 'Valeur', 'volution'];
        $admis = (int)db_scalar("SELECT COUNT(*) FROM hospitalisations WHERE DATE(date_admission) BETWEEN ? AND ?", [$date_from, $date_to]);
        $sortis = (int)db_scalar("SELECT COUNT(*) FROM hospitalisations WHERE DATE(date_sortie) BETWEEN ? AND ? AND statut='sorti'", [$date_from, $date_to]);
        $rdv_total = (int)db_scalar("SELECT COUNT(*) FROM rendez_vous WHERE DATE(date_heure) BETWEEN ? AND ?", [$date_from, $date_to]);
        $rdv_ok = (int)db_scalar("SELECT COUNT(*) FROM rendez_vous WHERE DATE(date_heure) BETWEEN ? AND ? AND statut='complete'", [$date_from, $date_to]);
        $rdv_annule = (int)db_scalar("SELECT COUNT(*) FROM rendez_vous WHERE DATE(date_heure) BETWEEN ? AND ? AND statut='annule'", [$date_from, $date_to]);
        $lits_occ = (int)db_scalar("SELECT COUNT(*) FROM lits WHERE statut='occupe'");
        $lits_tot = (int)db_scalar("SELECT COUNT(*) FROM lits");
        $taux_occ = $lits_tot > 0 ? round($lits_occ / $lits_tot * 100, 1) : 0;
        $urgences = (int)db_scalar("SELECT COUNT(*) FROM hospitalisations WHERE DATE(date_admission) BETWEEN ? AND ? AND priorite IN ('urgent','critique')", [$date_from, $date_to]);
        $data = [
            ['Admissions totales',        $admis,      ''],
            ['Sorties',                   $sortis,     ''],
            ['Cas urgents / critiques',   $urgences,   ''],
            ['Rendez-vous programms',    $rdv_total,  ''],
            ['Rendez-vous complétés',     $rdv_ok,     $rdv_total > 0 ? round($rdv_ok/$rdv_total*100,1).'%' : ''],
            ['Rendez-vous annulés',       $rdv_annule, ''],
            ['Lits occupés actuellement', $lits_occ . ' / ' . $lits_tot, $taux_occ . '%'],
        ];
        // Dtail par dpartement
        $depts = db_select("SELECT d.nom, COUNT(h.id) AS nb FROM departements d LEFT JOIN hospitalisations h ON h.departement_id=d.id AND DATE(h.date_admission) BETWEEN ? AND ? GROUP BY d.id ORDER BY nb DESC", [$date_from, $date_to]);
        foreach ($depts as $d) {
            $data[] = [' Admissions à ' . $d['nom'], (int)$d['nb'], ''];
        }
        break;

    //  RAPPORT FINANCIER
    case 'financier':
        $title = 'Rapport financier';
        $cols  = ['Description', 'Montant', 'Statut'];
        $ca    = (float)db_scalar("SELECT COALESCE(SUM(montant_total),0) FROM factures WHERE DATE(date_emission) BETWEEN ? AND ?", [$date_from, $date_to]);
        $regle = (float)db_scalar("SELECT COALESCE(SUM(montant_total),0) FROM factures WHERE DATE(date_emission) BETWEEN ? AND ? AND statut='reglee'", [$date_from, $date_to]);
        $attente = (float)db_scalar("SELECT COALESCE(SUM(montant_patient),0) FROM factures WHERE DATE(date_emission) BETWEEN ? AND ? AND statut='en_attente'", [$date_from, $date_to]);
        $impaye  = (float)db_scalar("SELECT COALESCE(SUM(montant_patient),0) FROM factures WHERE DATE(date_emission) BETWEEN ? AND ? AND statut='impayee'", [$date_from, $date_to]);
        $assu    = (float)db_scalar("SELECT COALESCE(SUM(montant_assurance),0) FROM factures WHERE DATE(date_emission) BETWEEN ? AND ?", [$date_from, $date_to]);
        $nb_fac  = (int)db_scalar("SELECT COUNT(*) FROM factures WHERE DATE(date_emission) BETWEEN ? AND ?", [$date_from, $date_to]);
        $nb_reg  = (int)db_scalar("SELECT COUNT(*) FROM factures WHERE DATE(date_emission) BETWEEN ? AND ? AND statut='reglee'", [$date_from, $date_to]);
        // Caisse pharmacie
        $ca_caisse = (float)db_scalar("SELECT COALESCE(SUM(montant_total),0) FROM caisse_ventes WHERE DATE(date_vente) BETWEEN ? AND ? AND statut='paye'", [$date_from, $date_to]);
        $data = [
            ['Chiffre d\'affaires total',   fmt_money($ca),      $nb_fac . ' factures'],
            ['Montant réglé',               fmt_money($regle),   $nb_reg . ' factures'],
            ['En attente de paiement',      fmt_money($attente), 'à encaisser'],
            ['Impayés',                     fmt_money($impaye),  'à relancer'],
            ['Prise en charge assurances',  fmt_money($assu),    ''],
            ['CA Caisse pharmacie',         fmt_money($ca_caisse), 'Ventes directes'],
            ['Total général',               fmt_money($ca + $ca_caisse), ''],
            ['Taux recouvrement',           $ca > 0 ? round($regle/$ca*100,1).'%' : '', ''],
        ];
        // Dtail par mode
        $modes = db_select("SELECT mode_paiement, COUNT(*) AS nb, SUM(montant_total) AS total FROM caisse_ventes WHERE DATE(date_vente) BETWEEN ? AND ? AND statut='paye' GROUP BY mode_paiement", [$date_from, $date_to]);
        foreach ($modes as $m) {
            $labels = ['especes'=>'Espèces','carte'=>'Carte','cheque'=>'Chèque','virement'=>'Virement','assurance'=>'Assurance','gratuit'=>'Gratuit'];
            $data[] = [' Caisse à ' . ($labels[$m['mode_paiement']] ?? $m['mode_paiement']), fmt_money((float)$m['total']), $m['nb'] . ' tickets'];
        }
        break;

    //  RAPPORT RH
    case 'rh':
        $title = 'Rapport Ressources Humaines';
        $cols  = ['Employé', 'Rôle', 'Spécialité', 'Statut', 'Planning', 'Dernière connexion'];
        $rows  = db_select("SELECT nom, prenom, role, specialite, statut, planning, derniere_connexion FROM utilisateurs ORDER BY role, nom ASC");
        $roleLabels = ['admin'=>'Administrateur','medecin'=>'Médecin','infirmier'=>'Infirmier(ère)','pharmacien'=>'Pharmacien','comptable'=>'Comptable'];
        foreach ($rows as $r) {
            $data[] = [
                $r['prenom'] . ' ' . $r['nom'],
                $roleLabels[$r['role']] ?? $r['role'],
                $r['specialite'] ?? '',
                ucfirst($r['statut']),
                $r['planning'] ?? '',
                $r['derniere_connexion'] ? fmt_date($r['derniere_connexion'], true) : 'Jamais',
            ];
        }
        break;

    //  RAPPORT PHARMACIE
    case 'pharmacie':
        $title = 'Rapport Pharmacie & Stocks';
        $cols  = ['Médicament', 'Catégorie', 'Dosage', 'Stock actuel', 'Seuil min.', 'Fournisseur', 'Prix unit.', 'Statut'];
        $rows  = db_select("SELECT * FROM medicaments ORDER BY statut DESC, nom ASC");
        $statutL = ['normal'=>'Normal','bas'=>'Bas','critique'=>'CRITIQUE','expire'=>'Expir'];
        foreach ($rows as $r) {
            $data[] = [
                $r['nom'],
                $r['categorie'] ?? '',
                $r['dosage'] ?? '',
                $r['stock_actuel'] . ' ' . ($r['unite'] ?? ''),
                $r['stock_minimum'],
                $r['fournisseur'] ?? '',
                fmt_money((float)$r['prix_unitaire']),
                $statutL[$r['statut']] ?? $r['statut'],
            ];
        }
        break;

    //  RAPPORT URGENCES
    case 'urgences':
        $title = 'Rapport Urgences & Hospitalisations';
        $cols  = ['Patient', 'Département', 'Lit', 'Médecin', 'Admission', 'Priorité', 'Statut', 'Motif'];
        $rows  = db_select(
            "SELECT CONCAT(p.prenom,' ',p.nom) AS patient, d.nom AS dept, l.numero AS lit,
                    CONCAT(u.prenom,' ',u.nom) AS medecin, h.date_admission, h.priorite, h.statut, h.motif
             FROM hospitalisations h
             JOIN patients p ON p.id=h.patient_id
             JOIN departements d ON d.id=h.departement_id
             JOIN lits l ON l.id=h.lit_id
             JOIN utilisateurs u ON u.id=h.medecin_id
             WHERE DATE(h.date_admission) BETWEEN ? AND ?
             ORDER BY h.priorite DESC, h.date_admission DESC",
            [$date_from, $date_to]
        );
        foreach ($rows as $r) {
            $data[] = [
                $r['patient'], $r['dept'], $r['lit'], $r['medecin'],
                fmt_date($r['date_admission'], true),
                ucfirst($r['priorite']), ucfirst($r['statut']),
                mb_substr($r['motif'], 0, 60) . (mb_strlen($r['motif']) > 60 ? '' : ''),
            ];
        }
        break;

    //  RAPPORT CAISSE
    case 'caisse':
        $title = 'Rapport Caisse Pharmacie';
        $cols  = ['N° Ticket', 'Date', 'Patient', 'Total', 'Mode paiement', 'Monnaie rendue', 'Caissier', 'Statut'];
        $rows  = db_select(
            "SELECT v.numero_ticket, v.date_vente, CONCAT(p.prenom,' ',p.nom) AS patient,
                    v.montant_total, v.mode_paiement, v.monnaie_rendue,
                    CONCAT(u.prenom,' ',u.nom) AS caissier, v.statut
             FROM caisse_ventes v
             LEFT JOIN patients p ON p.id=v.patient_id
             LEFT JOIN utilisateurs u ON u.id=v.caissier_id
             WHERE DATE(v.date_vente) BETWEEN ? AND ?
             ORDER BY v.date_vente DESC",
            [$date_from, $date_to]
        );
        $modeL = ['especes'=>'Espèces','carte'=>'Carte','cheque'=>'Chèque','virement'=>'Virement','assurance'=>'Assurance','gratuit'=>'Gratuit'];
        $statL = ['ouvert'=>'En attente','paye'=>'Pay','annule'=>'Annul','rembourse'=>'Remboursé'];
        foreach ($rows as $r) {
            $data[] = [
                $r['numero_ticket'],
                fmt_date($r['date_vente'], true),
                $r['patient'] ?: ' Anonyme ',
                fmt_money((float)$r['montant_total']),
                $modeL[$r['mode_paiement']] ?? $r['mode_paiement'],
                $r['monnaie_rendue'] > 0 ? fmt_money((float)$r['monnaie_rendue']) : '',
                $r['caissier'],
                $statL[$r['statut']] ?? $r['statut'],
            ];
        }
        break;

    //  RAPPORT PATIENTS
    case 'patients':
        $title = 'Rapport Patients';
        $cols  = ['N° Dossier', 'Nom', 'Prénom', 'Naissance', 'Sexe', 'Groupe', 'Assurance', 'Tlphone', 'Enregistré le'];
        $rows  = db_select(
            "SELECT numero, nom, prenom, date_naissance, sexe, groupe_sanguin, assurance, telephone, date_creation
             FROM patients WHERE DATE(date_creation) BETWEEN ? AND ? ORDER BY date_creation DESC",
            [$date_from, $date_to]
        );
        foreach ($rows as $r) {
            $data[] = [
                $r['numero'], $r['nom'], $r['prenom'],
                fmt_date($r['date_naissance']),
                $r['sexe'] === 'M' ? 'Masculin' : ($r['sexe'] === 'F' ? 'Féminin' : 'Autre'),
                $r['groupe_sanguin'] ?? '',
                $r['assurance'] ?? '',
                $r['telephone'] ?? '',
                fmt_date($r['date_creation']),
            ];
        }
        break;
}

//  EXPORT CSV
if ($export === 'csv' && !empty($data)) {
    // Vider le buffer pour pouvoir envoyer les headers CSV
    if (ob_get_level() > 0) { ob_end_clean(); }
    $filename = 'medicore_' . $type . '_' . $date_from . '_' . $date_to . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    $out = fopen('php://output', 'w');
    // BOM UTF-8 pour Excel
    fputs($out, "\xEF\xBB\xBF");
    // Mta
    fputcsv($out, [$etab . '  ' . $title], ';');
    fputcsv($out, ['Période: ' . fmt_date($date_from) . '  ' . fmt_date($date_to)], ';');
    fputcsv($out, ['Généré le: ' . date('d/m/Y H:i')], ';');
    fputcsv($out, [], ';');
    // En-ttes
    fputcsv($out, $cols, ';');
    // Donnes
    foreach ($data as $row) {
        fputcsv($out, array_map('strip_tags', (array)$row), ';');
    }
    fclose($out);
    exit;
}

//  Libells types de rapports
$typeLabels = [
    'mensuel'    => ['', 'Activit mensuelle'],
    'financier'  => ['', 'Financier'],
    'rh'         => ['', 'Ressources humaines'],
    'pharmacie'  => ['', 'Pharmacie & Stocks'],
    'urgences'   => ['', 'Urgences & Hospitalisations'],
    'caisse'     => ['', 'Caisse pharmacie'],
    'patients'   => ['', 'Patients'],
];
[$typeIcon, $typeName] = $typeLabels[$type];
?>

<!--
     EN-TTE
 -->
<div class="page-header-row" style="margin-bottom:20px">
  <div>
    <h2> Rapports</h2>
    <p>Génération, impression et export des données à tous les modules</p>
  </div>
  <div style="display:flex;gap:8px">
    <a href="?type=<?= h($type) ?>&date_from=<?= h($date_from) ?>&date_to=<?= h($date_to) ?>&export=csv"
       class="btn btn-ghost">⬇ Exporter CSV</a>
    <button class="btn btn-ghost btn-sm" onclick="imprimerRapport()">🖨 Imprimer</button>
    <button class="btn btn-blue" onclick="window.print()">🖨 Imprimer</button>
  </div>
</div>

<!--
     FILTRES
 -->
<div class="card mb-24" id="no-print">
  <div style="padding:18px 20px">
    <form method="GET" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end">
      <div class="form-group" style="flex:1;min-width:160px">
        <label>Type de rapport</label>
        <select name="type" onchange="this.form.submit()" style="padding:9px 12px;background:var(--bg);border:1px solid var(--border2);border-radius:7px;color:var(--text);font-family:inherit;font-size:13px;outline:none;width:100%">
          <?php foreach ($typeLabels as $k => [$ic, $lb]): ?>
          <option value="<?= h($k) ?>" <?= $type === $k ? 'selected' : '' ?>><?= $ic ?> <?= h($lb) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label>Du</label>
        <input type="date" name="date_from" value="<?= h($date_from) ?>"
               style="padding:9px 12px;background:var(--bg);border:1px solid var(--border2);border-radius:7px;color:var(--text);font-family:inherit;font-size:13px;outline:none">
      </div>
      <div class="form-group">
        <label>Au</label>
        <input type="date" name="date_to" value="<?= h($date_to) ?>"
               style="padding:9px 12px;background:var(--bg);border:1px solid var(--border2);border-radius:7px;color:var(--text);font-family:inherit;font-size:13px;outline:none">
      </div>
      <button type="submit" class="btn btn-blue"> Générer</button>
      <!-- Raccourcis période -->
      <div style="display:flex;gap:6px;align-items:center">
        <a href="?type=<?= h($type) ?>&date_from=<?= date('Y-m-01') ?>&date_to=<?= $today ?>" class="btn btn-sm btn-ghost">Ce mois</a>
        <a href="?type=<?= h($type) ?>&date_from=<?= date('Y-m-01', strtotime('-1 month')) ?>&date_to=<?= date('Y-m-t', strtotime('-1 month')) ?>" class="btn btn-sm btn-ghost">Mois dernier</a>
        <a href="?type=<?= h($type) ?>&date_from=<?= date('Y-01-01') ?>&date_to=<?= $today ?>" class="btn btn-sm btn-ghost">Cette anne</a>
        <a href="?type=<?= h($type) ?>&date_from=<?= $today ?>&date_to=<?= $today ?>" class="btn btn-sm btn-ghost">Aujourd'hui</a>
      </div>
    </form>
  </div>
</div>

<!--
     RAPPORT (visible  l'cran ET  l'impression)
 -->
<div class="card" id="rapport-content">

  <!-- En-tte du rapport -->
  <div style="padding:24px 24px 16px;border-bottom:1px solid var(--border)">
    <?php $__logo = setting('logo_base64',''); ?>
    <?php if ($__logo): ?>
    <div class="print-logo" style="margin-bottom:12px;padding-bottom:12px;border-bottom:1px solid var(--border)">
      <img src="<?= h($__logo) ?>" alt="Logo" style="max-height:60px;max-width:180px;object-fit:contain">
    </div>
    <?php endif; ?>
    <?php $__entete = setting('entete_rapport',''); ?>
    <?php if ($__entete): ?><div style="font-size:12px;color:var(--text3);margin-bottom:12px;white-space:pre-line"><?= h($__entete) ?></div><?php endif; ?>
    <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px">
      <div>
        <div style="font-size:11px;color:var(--text3);text-transform:uppercase;letter-spacing:.06em;margin-bottom:4px">
          <?= $typeIcon ?> Rapport <?= h($typeName) ?>
        </div>
        <h3 style="font-size:20px;font-weight:700;margin-bottom:4px"><?= h($etab) ?></h3>
        <div style="color:var(--text2);font-size:13px">
          Période : <strong><?= fmt_date($date_from) ?></strong>  <strong><?= fmt_date($date_to) ?></strong>
        </div>
      </div>
      <div style="text-align:right;font-size:12px;color:var(--text3)">
        <div>Généré le <?= date('d/m/Y  H:i') ?></div>
        <div>MediCore ERP v<?= APP_VERSION ?></div>
        <div><?= count($data) ?> enregistrement(s)</div>
      </div>
    </div>
  </div>

  <!-- Tableau de donnes -->
  <?php if (!empty($data)): ?>
  <div style="overflow-x:auto">
    <table>
      <thead>
        <tr>
          <?php foreach ($cols as $col): ?>
          <th><?= h($col) ?></th>
          <?php endforeach; ?>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($data as $i => $row):
          $row = (array)$row;
          // Colorier les lignes critiques (pharmacie)
          $rowStyle = '';
          if ($type === 'pharmacie') {
              $last = end($row);
              if ($last === 'CRITIQUE') $rowStyle = 'background:rgba(var(--red-rgb),.06)';
              elseif ($last === 'Bas')  $rowStyle = 'background:rgba(var(--yellow-rgb),.06)';
          }
          if ($type === 'urgences' && isset($row[5])) {
              if (strtolower($row[5]) === 'critique') $rowStyle = 'background:rgba(var(--red-rgb),.06)';
              elseif (strtolower($row[5]) === 'urgent') $rowStyle = 'background:rgba(var(--yellow-rgb),.04)';
          }
        ?>
        <tr style="<?= $rowStyle ?>">
          <?php foreach ($row as $j => $cell): ?>
          <td <?= $j === 0 ? 'style="font-weight:500;color:var(--text)"' : '' ?>>
            <?php
            // Coloration spciale selon contexte
            $cellStr = h((string)$cell);
            if (in_array($cell, ['CRITIQUE', 'Impay'])) {
                echo '<span style="color:var(--red);font-weight:600">' . $cellStr . '</span>';
            } elseif (in_array($cell, ['Bas', 'En attente'])) {
                echo '<span style="color:var(--yellow);font-weight:600">' . $cellStr . '</span>';
            } elseif (in_array($cell, ['Normal', 'Pay', 'Actif', 'complete'])) {
                echo '<span style="color:var(--green)">' . $cellStr . '</span>';
            } elseif (in_array($cell, ['Annul', 'inactif'])) {
                echo '<span style="color:var(--text3)">' . $cellStr . '</span>';
            } else {
                echo $cellStr;
            }
            ?>
          </td>
          <?php endforeach; ?>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <!-- Pied de rapport -->
  <div style="padding:16px 24px;border-top:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;font-size:12px;color:var(--text3)">
    <span><?= count($data) ?> enregistrement(s)  Rapport <?= h($typeName) ?></span>
    <span><?= h($etab) ?>  MediCore ERP v<?= APP_VERSION ?></span>
  </div>

  <?php else: ?>
  <div style="padding:48px;text-align:center;color:var(--text3)">
    <div style="font-size:32px;margin-bottom:12px"></div>
    <div style="font-size:14px">Aucune donnée pour cette période.</div>
    <div style="font-size:12px;margin-top:6px">Essayez d'élargir la plage de dates.</div>
  </div>
  <?php endif; ?>
</div>

<!--  Raccourcis rapports rapides  -->
<div style="margin-top:24px" id="no-print">
  <div style="font-size:11px;color:var(--text3);text-transform:uppercase;letter-spacing:.06em;margin-bottom:12px"> Accs rapide</div>
  <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:10px">
    <?php foreach ($typeLabels as $k => [$ic, $lb]): ?>
    <a href="?type=<?= h($k) ?>&date_from=<?= date('Y-m-01') ?>&date_to=<?= $today ?>"
       style="display:flex;align-items:center;gap:10px;padding:14px;background:var(--surface);border:1px solid <?= $k === $type ? 'var(--accent)' : 'var(--border)' ?>;border-radius:10px;transition:border-color .15s;text-decoration:none;color:inherit">
      <span style="font-size:20px"><?= $ic ?></span>
      <span style="font-size:12px;font-weight:500;color:<?= $k === $type ? 'var(--accent2)' : 'var(--text2)' ?>"><?= h($lb) ?></span>
    </a>
    <?php endforeach; ?>
  </div>
</div>

<!--
     CSS IMPRESSION
 -->
<style>
@media print {
  /* Masquer tout sauf le rapport */
  #no-print, .sidebar, .header, .page-header-row > div:last-child,
  .card:not(#rapport-content), [id="no-print"] { display: none !important; }

  /* Reset couleurs pour impression */
  body, #app, .main, .content { background: #fff !important; color: #000 !important; margin: 0 !important; padding: 0 !important; }
  .main { margin-left: 0 !important; }
  #rapport-content { border: 1px solid #ccc !important; border-radius: 0 !important; box-shadow: none !important; }

  /* En-tte rapport */
  #rapport-content h3 { color: #000 !important; }
  #rapport-content div { color: #333; }

  /* Table */
  table { width: 100% !important; font-size: 10px !important; }
  th { background: #f0f0f0 !important; color: #000 !important; border: 1px solid #ccc !important; padding: 6px !important; }
  td { border: 1px solid #ddd !important; padding: 5px !important; color: #000 !important; }
  tr:nth-child(even) td { background: #fafafa !important; }

  /* Badges couleurs  texte simple */
  span[style*="color:var(--red)"]    { color: #c00 !important; }
  span[style*="color:var(--green)"]  { color: #060 !important; }
  span[style*="color:var(--yellow)"] { color: #a60 !important; }

  /* Page break */
  @page { margin: 1.5cm; size: A4 landscape; }
  thead { display: table-header-group; }
  tr { page-break-inside: avoid; }
}
</style>

<?php require_once __DIR__ . '/../includes/footer.php';
