<?php
// modules/export/index.php — Export des données CSV/Excel
require_once '../../includes/config.php';
requireLogin(); requirePerm('export.access');
$pageTitle = 'Export des Données';

$type   = $_GET['type']   ?? '';
$date_d = $_GET['date_d'] ?? date('Y-m-01');
$date_f = $_GET['date_f'] ?? date('Y-m-d');
$ag     = (int)($_GET['ag'] ?? getUserAgenceId() ?? 0);
$grp    = (int)($_GET['grp'] ?? 0);

$agences = $pdo->query("SELECT id,nom FROM agences WHERE actif=1 ORDER BY nom")->fetchAll();
$groupes = $pdo->query("SELECT id,nom FROM groupes WHERE actif=1 ORDER BY nom")->fetchAll();

// ── GÉNÉRATION EXPORT ────────────────────────────────────────
if ($type && isset($_GET['download'])) {
    $wA  = $ag  ? "AND b.agence_depart_id=$ag"  : "";
    $wG  = $grp ? "AND v.groupe_id=$grp" : "";
    $wDep= $ag  ? "AND d.agence_id=$ag"        : "";
    $wVer= $ag  ? "AND ver.agence_id=$ag"       : "";
    $data=[]; $filename='';

    switch($type) {
        case 'bordereaux':
            $data=$pdo->query("SELECT b.num_bordereau 'N° Bordereau',b.code_bordereau 'Code',b.date 'Date',v.immatriculation 'Véhicule',g.nom 'Groupe',ad.nom 'Agence Départ',aa.nom 'Agence Arrivée',b.nb_passagers 'Passagers',b.nb_billets_gratuits 'Billets Gratuits',b.recette_totale 'Recette Brute',b.carburant 'Carburant',b.peage_total 'Péages',b.retenue_agence 'Retenue Agence',b.ration_chauffeur 'Ration Chauffeur',b.autres_depenses 'Autres',b.recette_nette 'Recette Nette' FROM bordereaux b LEFT JOIN vehicules v ON b.vehicule_id=v.id LEFT JOIN groupes g ON v.groupe_id=g.id LEFT JOIN agences ad ON b.agence_depart_id=ad.id LEFT JOIN agences aa ON b.agence_arrivee_id=aa.id WHERE b.date BETWEEN '$date_d' AND '$date_f' $wA $wG ORDER BY b.date,b.num_bordereau")->fetchAll();
            $filename="bordereaux_{$date_d}_{$date_f}";
            break;
        case 'versements':
            $data=$pdo->query("SELECT ver.ref_versement 'Référence',a.nom 'Agence',ver.date 'Date',ver.versement_agence 'Versement',ver.decaissement_agence 'Décaissement',ver.recette_agence 'Recette Agence',ver.quittance 'Quittance',ver.statut 'Statut' FROM versements ver JOIN agences a ON ver.agence_id=a.id WHERE ver.date BETWEEN '$date_d' AND '$date_f' $wVer ORDER BY ver.date")->fetchAll();
            $filename="versements_{$date_d}_{$date_f}";
            break;
        case 'depenses':
            $data=$pdo->query("SELECT a.nom 'Agence',d.date_depense 'Date',d.type_depense 'Type',d.objet 'Objet',d.montant 'Montant',d.mode_paiement 'Mode Paiement',d.statut 'Statut' FROM depenses d JOIN agences a ON d.agence_id=a.id WHERE d.date_depense BETWEEN '$date_d' AND '$date_f' $wDep ORDER BY d.date_depense")->fetchAll();
            $filename="depenses_{$date_d}_{$date_f}";
            break;
        case 'bons':
            $data=$pdo->query("SELECT g.nom 'Groupe',ba.nom_payeur 'Payeur',ba.date_paiement 'Date',ba.montant 'Montant',ba.mode_paiement 'Mode',ba.statut 'Statut',ba.date_expir_delai 'Date Expiration' FROM bons_actionnaires ba JOIN groupes g ON ba.groupe_id=g.id WHERE ba.date_paiement BETWEEN '$date_d' AND '$date_f' ORDER BY ba.date_paiement")->fetchAll();
            $filename="bons_actionnaires_{$date_d}_{$date_f}";
            break;
        case 'agences':
            $data=$pdo->query("SELECT code 'Code',nom 'Nom',ville 'Ville',region 'Région',type_agence 'Type',telephone 'Téléphone',email 'Email',nom_contact 'Responsable' FROM agences ORDER BY nom")->fetchAll();
            $filename="agences";
            break;
        case 'vehicules':
            $data=$pdo->query("SELECT v.immatriculation 'Immatriculation',v.marque 'Marque',v.modele 'Modèle',v.description 'Description',g.nom 'Groupe',v.capacite 'Capacité' FROM vehicules v LEFT JOIN groupes g ON v.groupe_id=g.id ORDER BY v.immatriculation")->fetchAll();
            $filename="vehicules";
            break;
    }

    if (!empty($data)) {
        $fn=preg_replace('/[^a-zA-Z0-9_\-]/','',$filename);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="'.$fn.'.csv"');
        $out=fopen('php://output','w');
        fprintf($out,chr(0xEF).chr(0xBB).chr(0xBF)); // BOM UTF-8
        fputcsv($out,array_keys($data[0]),';');
        foreach($data as $row) fputcsv($out,$row,';');
        fclose($out);
        logAction($pdo,"export_$type",'export',"$date_d à $date_f");
        exit();
    } else { flash('Aucune donnée à exporter.','warning'); }
}

include '../../includes/header.php';
?>
<div class="breadcrumb"><a href="<?= BASE_URL ?>"><i class="fas fa-home"></i></a><span class="breadcrumb-sep">/</span>Export des données</div>

<div class="card" style="max-width:720px;margin:0 auto;">
  <div class="card-header"><h3><i class="fas fa-file-excel"></i> Export CSV / Excel</h3></div>
  <div class="card-body">
    <div class="flash flash-info" style="border-radius:var(--radius);margin-bottom:16px;">
      <i class="fas fa-info-circle"></i>
      Fichiers exportés en <strong>CSV UTF-8</strong> compatible Microsoft Excel. Ouvrir avec le séparateur <strong>point-virgule (;)</strong>.
    </div>
    <form method="GET">
      <div class="form-grid" style="margin-bottom:16px;">
        <div class="fg full">
          <label class="flbl">Type d'export <span class="freq">*</span></label>
          <select name="type" class="fc" required onchange="toggleFilters(this.value)">
            <option value="">— Sélectionner le type —</option>
            <optgroup label="Exploitation">
              <option value="bordereaux" <?= $type==='bordereaux'?'selected':'' ?>>📋 Bordereaux de voyage</option>
              <option value="versements" <?= $type==='versements'?'selected':'' ?>>🏦 Versements bancaires</option>
              <option value="depenses"   <?= $type==='depenses'?'selected':'' ?>>💸 Dépenses</option>
              <option value="bons"       <?= $type==='bons'?'selected':'' ?>>🤝 Bons actionnaires</option>
            </optgroup>
            <optgroup label="Référentiels">
              <option value="agences"   <?= $type==='agences'?'selected':'' ?>>🏢 Liste des agences</option>
              <option value="vehicules" <?= $type==='vehicules'?'selected':'' ?>>🚌 Liste des véhicules</option>
            </optgroup>
          </select>
        </div>
        <div class="fg" id="f-agence">
          <label class="flbl">Agence (filtre)</label>
          <select name="ag" class="fc"><option value="">Toutes</option><?php foreach($agences as $a): ?><option value="<?= $a['id'] ?>" <?= $ag==$a['id']?'selected':'' ?>><?= h($a['nom']) ?></option><?php endforeach; ?></select>
        </div>
        <div class="fg" id="f-groupe">
          <label class="flbl">Groupe (filtre)</label>
          <select name="grp" class="fc"><option value="">Tous</option><?php foreach($groupes as $g): ?><option value="<?= $g['id'] ?>" <?= $grp==$g['id']?'selected':'' ?>><?= h($g['nom']) ?></option><?php endforeach; ?></select>
        </div>
        <div class="fg" id="f-dated">
          <label class="flbl">Date début</label>
          <input type="date" name="date_d" class="fc" value="<?= $date_d ?>">
        </div>
        <div class="fg" id="f-datef">
          <label class="flbl">Date fin</label>
          <input type="date" name="date_f" class="fc" value="<?= $date_f ?>">
        </div>
      </div>
      <div style="display:flex;gap:10px;">
        <button type="submit" name="download" value="1" class="btn btn-success btn-lg"><i class="fas fa-download"></i> Télécharger CSV</button>
        <button type="submit" class="btn btn-secondary"><i class="fas fa-eye"></i> Prévisualiser</button>
      </div>
    </form>

    <!-- APERÇU -->
    <?php if($type && !isset($_GET['download'])):
      $wAp=$ag?"AND agence_depart_id=$ag":""; $nb=0;
      switch($type){
        case 'bordereaux':$nb=$pdo->query("SELECT COUNT(*) FROM bordereaux b WHERE b.date BETWEEN '$date_d' AND '$date_f' $wAp")->fetchColumn();break;
        case 'versements':$nb=$pdo->query("SELECT COUNT(*) FROM versements WHERE date BETWEEN '$date_d' AND '$date_f'")->fetchColumn();break;
        case 'depenses':$nb=$pdo->query("SELECT COUNT(*) FROM depenses WHERE date_depense BETWEEN '$date_d' AND '$date_f'")->fetchColumn();break;
        case 'bons':$nb=$pdo->query("SELECT COUNT(*) FROM bons_actionnaires WHERE date_paiement BETWEEN '$date_d' AND '$date_f'")->fetchColumn();break;
        case 'agences':$nb=$pdo->query("SELECT COUNT(*) FROM agences")->fetchColumn();break;
        case 'vehicules':$nb=$pdo->query("SELECT COUNT(*) FROM vehicules")->fetchColumn();break;
      }
    ?>
    <div style="margin-top:16px;padding:14px;background:var(--bg);border-radius:var(--radius);font-size:13px;">
      <i class="fas fa-info-circle"></i>
      Export <strong><?= h($type) ?></strong> : <strong><?= number_format($nb) ?></strong> ligne(s) à exporter
      <?php if(in_array($type,['bordereaux','versements','depenses','bons'])): ?> | Période : <?= fdate($date_d) ?> → <?= fdate($date_f) ?><?php endif; ?>
    </div>
    <?php endif; ?>
  </div>
</div>

<script>
function toggleFilters(t){
    const refTypes=['agences','vehicules'];
    const show=!refTypes.includes(t);
    ['f-dated','f-datef','f-agence','f-groupe'].forEach(id=>{
        const el=document.getElementById(id);
        if(el) el.style.display=show?'':'none';
    });
}
document.addEventListener('DOMContentLoaded',()=>toggleFilters(document.querySelector('[name=type]')?.value||''));
</script>
<?php include '../../includes/footer.php'; ?>
