<?php
require_once "../../includes/config.php"; requireLogin(); requirePerm("depenses.view");
$pageTitle="Dépenses"; $aid=getUserAgenceId();
if(isset($_GET["approve"])&&can("depenses.approve")){
    $pdo->prepare("UPDATE depenses SET statut=?,approuve_par=? WHERE id=?")->execute(["approuve",$_SESSION["user_id"],(int)$_GET["approve"]]);
    flash("Dépense approuvée."); redirect(BASE_URL."modules/depenses/");
}
$date_d=$_GET["date_d"]??date("Y-m-01"); $date_f=$_GET["date_f"]??date("Y-m-d"); $type_f=$_GET["type"]??""; $ag=(int)($_GET["ag"]??0);
$where=["d.date_depense BETWEEN ? AND ?"]; $params=[$date_d,$date_f];
if($aid){$where[]="d.agence_id=?";$params[]=$aid;} elseif($ag){$where[]="d.agence_id=?";$params[]=$ag;}
if($type_f){$where[]="d.type_depense=?";$params[]=$type_f;}
$ws=implode(" AND ",$where);
$stmt=$pdo->prepare("SELECT d.*,a.nom as ag,CONCAT(p.prenom," ",p.nom) as emp FROM depenses d JOIN agences a ON d.agence_id=a.id LEFT JOIN personnel p ON d.employe_id=p.id WHERE $ws ORDER BY d.date_depense DESC");
$stmt->execute($params); $deps=$stmt->fetchAll();
$agences=$pdo->query("SELECT id,nom FROM agences WHERE actif=1 ORDER BY nom")->fetchAll();
include "../../includes/header.php";
?>
<div class="breadcrumb"><a href="<?= BASE_URL ?>"><i class="fas fa-home"></i></a><span class="breadcrumb-sep">/</span>Dépenses</div>
<div class="card" style="margin-bottom:14px;">
  <div class="card-header"><h3><i class="fas fa-money-bill-wave"></i> Dépenses (<?= count($deps) ?>)</h3><?php if(can("depenses.create")): ?><a href="ajouter.php" class="btn btn-warning btn-sm"><i class="fas fa-plus"></i> Nouvelle</a><?php endif; ?></div>
  <div class="card-body">
    <form method="GET" class="filter-bar">
      <input type="date" name="date_d" class="fc" value="<?= $date_d ?>" style="width:auto;">
      <input type="date" name="date_f" class="fc" value="<?= $date_f ?>" style="width:auto;">
      <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-search"></i></button>
    </form>
  </div>
</div>
<div class="card"><div class="card-body" style="padding:0;"><div class="table-wrap">
<table>
  <thead><tr><th>Agence</th><th>Date</th><th>Type</th><th>Objet</th><th>Ordonnateur</th><th>Mode</th><th>Montant</th><th>Statut</th><th>Actions</th></tr></thead>
  <tbody>
    <?php foreach($deps as $d): ?>
    <tr>
      <td style="font-size:12px;"><?= h($d["ag"]) ?></td>
      <td><?= fdate($d["date_depense"]) ?></td>
      <td><span class="badge b-blue" style="font-size:10px;"><?= h($d["type_depense"]) ?></span></td>
      <td><?= h($d["objet"]) ?></td>
      <td style="font-size:12px;"><?= h($d["emp"]??"—") ?></td>
      <td style="font-size:12px;"><?= h($d["mode_paiement"]) ?></td>
      <td style="font-weight:700;color:var(--danger);"><?= moneyRaw($d["montant"]) ?></td>
      <td><span class="st-<?= $d["statut"] ?>"><?= h($d["statut"]) ?></span></td>
      <td><?php if($d["statut"]==="en_attente"&&can("depenses.approve")): ?><a href="?approve=<?= $d["id"] ?>" class="btn btn-xs btn-success"><i class="fas fa-check"></i></a><?php endif; ?></td>
    </tr>
    <?php endforeach; ?>
    <?php if(empty($deps)): ?><tr><td colspan="9" class="t-empty"><i class="fas fa-money-bill-wave"></i>Aucune dépense</td></tr><?php endif; ?>
  </tbody>
</table></div></div></div>
<?php include "../../includes/footer.php"; ?>