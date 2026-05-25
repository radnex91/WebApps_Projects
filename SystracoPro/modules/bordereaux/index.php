<?php
// modules/bordereaux/index.php
require_once '../../includes/config.php';
requireLogin(); requirePerm('bordereaux.view');
$pageTitle = 'Bordereaux de Voyage';
$aid = getUserAgenceId();
$today = date('Y-m-d');

$search = trim($_GET['q'] ?? ''); $statut = $_GET['statut'] ?? ''; $type = $_GET['type'] ?? '';
$date_d = $_GET['date_d'] ?? $today; $date_f = $_GET['date_f'] ?? $today;
$page = max(1,(int)($_GET['page']??1)); $perPage=20;

// ── Vérifier si la table bordereau_escales existe ──
$escaleTableExists = false;
try {
    $pdo->query("SELECT 1 FROM bordereau_escales LIMIT 1");
    $escaleTableExists = true;
} catch (Exception $e) {}

$where=['1=1']; $params=[];
// Exclure les bordereaux sans ticket
$where[] = "EXISTS (SELECT 1 FROM bordereau_lignes bl WHERE bl.bordereau_id=b.id)";
// Visibilité :
// - genere : visible uniquement par l'agence créatrice
// - en_cours/cloture : visibles par toutes les agences (le véhicule passe partout)
// + si table escales existe, on montre aussi les genere où l'agence a une escale
if ($aid) {
    if ($escaleTableExists) {
        $where[] = "(b.agence_id=? OR b.statut IN ('en_cours','cloture') OR EXISTS(SELECT 1 FROM bordereau_escales be WHERE be.bordereau_id=b.id AND be.agence_id=?))";
        $params[] = $aid;
        $params[] = $aid;
    } else {
        $where[] = "(b.agence_id=? OR b.statut IN ('en_cours','cloture'))";
        $params[] = $aid;
    }
}
if ($search) { $where[]="(b.numero LIKE ? OR b.vehicule_immat LIKE ? OR b.chauffeur_nom LIKE ?)"; $params=array_merge($params,["%$search%","%$search%","%$search%"]); }
if ($statut) { $where[]="b.statut=?"; $params[]=$statut; }
if ($type)   { $where[]="b.type=?"; $params[]=$type; }
if ($date_d) { $where[]="DATE(b.created_at)>=?"; $params[]=$date_d; }
if ($date_f) { $where[]="DATE(b.created_at)<=?"; $params[]=$date_f; }
$ws=implode(' AND ',$where);
$total=$pdo->prepare("SELECT COUNT(*) FROM bordereaux b WHERE $ws"); $total->execute($params);
$totalRows=$total->fetchColumn(); $totalPages=ceil($totalRows/$perPage);
$stmt=$pdo->prepare("SELECT b.*,b.parent_id,b.segment_ordre,a1.ville as dep,a2.ville as arr,v.numero as voy_num,v.date_depart,(SELECT COUNT(*) FROM bordereau_lignes bl WHERE bl.bordereau_id=b.id) as nb_passagers_reel,(SELECT IFNULL(SUM(bl.montant),0) FROM bordereau_lignes bl WHERE bl.bordereau_id=b.id) as recette_reelle,(SELECT COUNT(*) FROM bordereaux b2 WHERE b2.parent_id=b.id) as nb_segments".($aid && $escaleTableExists?",(SELECT COUNT(*) FROM bordereau_escales be WHERE be.bordereau_id=b.id AND be.statut='en_attente' AND be.agence_id=$aid) as escale_attente":"")." FROM bordereaux b LEFT JOIN voyages v ON b.voyage_id=v.id LEFT JOIN itineraires i ON v.itineraire_id=i.id LEFT JOIN destinations d ON v.destination_id=d.id LEFT JOIN agences a1 ON IFNULL(i.agence_depart,d.agence_depart)=a1.id LEFT JOIN agences a2 ON IFNULL(i.agence_arrivee,d.agence_arrivee)=a2.id WHERE $ws ORDER BY b.created_at DESC LIMIT $perPage OFFSET ".(($page-1)*$perPage));
$stmt->execute($params); $bordereaux=$stmt->fetchAll(PDO::FETCH_ASSOC);

// Ajuster le nombre de passagers et la recette pour l'agence escale (exclure les passagers descendus)
if ($aid && $escaleTableExists && !isAdmin()) {
    $brdIds = array_column($bordereaux, 'id');
    if (!empty($brdIds)) {
        // Escales confirmées ou dépassées pour les bordereaux visibles
        $escData = $pdo->query("SELECT be.bordereau_id, be.ordre, be.statut, be.agence_id, a.ville, a.nom FROM bordereau_escales be JOIN agences a ON be.agence_id=a.id WHERE be.bordereau_id IN (".implode(',', array_map('intval', $brdIds)).") ORDER BY be.bordereau_id, be.ordre")->fetchAll(PDO::FETCH_ASSOC);
        // Pour chaque bordereau, trouver l'ordre de l'utilisateur et les escales dépassées
        $escByBrd = [];
        foreach ($escData as $e) { $escByBrd[$e['bordereau_id']][] = $e; }
        // Lignes avec destination pour filtrage
        $lignesData = $pdo->query("SELECT bl.bordereau_id, bl.destination, bl.montant FROM bordereau_lignes bl WHERE bl.bordereau_id IN (".implode(',', array_map('intval', $brdIds)).")")->fetchAll(PDO::FETCH_ASSOC);
        $lignesByBrd = [];
        foreach ($lignesData as $l) { $lignesByBrd[$l['bordereau_id']][] = $l; }
        // Agence de l'utilisateur
        $myAgData = $pdo->prepare("SELECT nom,ville FROM agences WHERE id=?"); $myAgData->execute([$aid]); $myAg = $myAgData->fetch(PDO::FETCH_ASSOC);
        foreach ($bordereaux as $i => $brd) {
            $bid = $brd['id'];
            $escales = $escByBrd[$bid] ?? [];
            // Trouver l'ordre de l'utilisateur
            $myOrdre = 0;
            foreach ($escales as $esc) {
                if ((int)$esc['agence_id'] === (int)$aid) { $myOrdre = (int)$esc['ordre']; break; }
            }
            // Collecter les noms/villes des escales dépassées
            $excludeDests = [];
            if ($myAg) { $excludeDests[] = mb_strtolower(trim($myAg['ville'])); $excludeDests[] = mb_strtolower(trim($myAg['nom'])); }
            foreach ($escales as $esc) {
                if ($esc['statut'] === 'confirme' || ($myOrdre > 0 && (int)$esc['ordre'] < $myOrdre)) {
                    $excludeDests[] = mb_strtolower(trim($esc['ville'] ?? $esc['nom']));
                    $excludeDests[] = mb_strtolower(trim($esc['nom']));
                }
            }
            $excludeDests = array_filter(array_unique($excludeDests));
            if (!empty($excludeDests) && !empty($lignesByBrd[$bid])) {
                $visibleLignes = array_filter($lignesByBrd[$bid], function($l) use ($excludeDests) {
                    $dest = mb_strtolower(trim($l['destination'] ?? ''));
                    if (!$dest) return true;
                    foreach ($excludeDests as $ev) {
                        if ($ev && ($dest === $ev || strpos($ev, $dest) !== false || strpos($dest, $ev) !== false)) return false;
                    }
                    return true;
                });
                $bordereaux[$i]['nb_passagers_reel'] = count($visibleLignes);
                $bordereaux[$i]['recette_reelle'] = array_sum(array_column($visibleLignes, 'montant'));
            }
        }
    }
}
// ── Escales en attente pour l'agence de l'utilisateur (ou toutes pour admin) ──
$mesEscales = [];

if ($escaleTableExists && ($aid || isAdmin())) {
    try {
        $sql = "SELECT b.id, b.numero, b.vehicule_immat, b.chauffeur_nom, (SELECT COUNT(*) FROM bordereau_lignes bl WHERE bl.bordereau_id=b.id) as nb_passagers_reel, (SELECT IFNULL(SUM(bl.montant),0) FROM bordereau_lignes bl WHERE bl.bordereau_id=b.id) as recette_reelle, b.statut, b.date_depart, b.agence_depart, b.agence_arrivee, v.numero as voy_num, be.id as escale_id, be.ordre as escale_ordre, be.agence_id as escale_agence_id, ae.nom as escale_agence_nom, ae.ville as escale_agence_ville FROM bordereaux b LEFT JOIN voyages v ON b.voyage_id=v.id JOIN bordereau_escales be ON be.bordereau_id=b.id AND be.statut='en_attente' JOIN agences ae ON be.agence_id=ae.id WHERE b.statut='en_cours' AND EXISTS (SELECT 1 FROM bordereau_lignes bl WHERE bl.bordereau_id=b.id)";
        $params2 = [];
        if (!isAdmin() && $aid) {
            $sql .= " AND be.agence_id=?";
            $params2[] = $aid;
        }
        $sql .= " ORDER BY be.ordre ASC, b.date_depart DESC";
        $me = $pdo->prepare($sql);
        $me->execute($params2);
        $mesEscales = $me->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {}
}

// ── Fallback: pas de table escales → on montre les bordereaux en_cours visibles par l'agence ──
if (empty($mesEscales) && !$escaleTableExists) {
    try {
        $fbSql = "SELECT b.id, b.numero, b.vehicule_immat, b.chauffeur_nom, (SELECT COUNT(*) FROM bordereau_lignes bl WHERE bl.bordereau_id=b.id) as nb_passagers_reel, (SELECT IFNULL(SUM(bl.montant),0) FROM bordereau_lignes bl WHERE bl.bordereau_id=b.id) as recette_reelle, b.statut, b.date_depart, b.agence_depart, b.agence_arrivee, v.numero as voy_num FROM bordereaux b LEFT JOIN voyages v ON b.voyage_id=v.id WHERE b.statut='en_cours' AND EXISTS (SELECT 1 FROM bordereau_lignes bl WHERE bl.bordereau_id=b.id)";
        $fbParams = [];
        if ($aid) {
            $fbSql .= " AND b.agence_id!=?";
            $fbParams[] = $aid;
        }
        $fbSql .= " ORDER BY b.date_depart DESC LIMIT 20";
        $fb = $pdo->prepare($fbSql);
        $fb->execute($fbParams);
        $mesEscales = $fb->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {}
}

// Ajuster le nombre de passagers et la recette pour $mesEscales (escales en attente)
if ($aid && $escaleTableExists && !isAdmin() && !empty($mesEscales)) {
    $meBrdIds = array_column($mesEscales, 'id');
    $meEscData = $pdo->query("SELECT be.bordereau_id, be.ordre, be.statut, be.agence_id, a.ville, a.nom FROM bordereau_escales be JOIN agences a ON be.agence_id=a.id WHERE be.bordereau_id IN (".implode(',', array_map('intval', $meBrdIds)).") ORDER BY be.bordereau_id, be.ordre")->fetchAll(PDO::FETCH_ASSOC);
    $meEscByBrd = [];
    foreach ($meEscData as $e) { $meEscByBrd[$e['bordereau_id']][] = $e; }
    $meLignes = $pdo->query("SELECT bl.bordereau_id, bl.destination, bl.montant FROM bordereau_lignes bl WHERE bl.bordereau_id IN (".implode(',', array_map('intval', $meBrdIds)).")")->fetchAll(PDO::FETCH_ASSOC);
    $meLignesByBrd = [];
    foreach ($meLignes as $l) { $meLignesByBrd[$l['bordereau_id']][] = $l; }
    $myAg2 = $pdo->prepare("SELECT nom,ville FROM agences WHERE id=?"); $myAg2->execute([$aid]); $myAgData2 = $myAg2->fetch(PDO::FETCH_ASSOC);
    foreach ($mesEscales as $i => $me) {
        $bid = $me['id'];
        $escales = $meEscByBrd[$bid] ?? [];
        $myOrdre = 0;
        foreach ($escales as $esc) {
            if ((int)$esc['agence_id'] === (int)$aid) { $myOrdre = (int)$esc['ordre']; break; }
        }
        $excludeDests = [];
        if ($myAgData2) { $excludeDests[] = mb_strtolower(trim($myAgData2['ville'])); $excludeDests[] = mb_strtolower(trim($myAgData2['nom'])); }
        foreach ($escales as $esc) {
            if ($esc['statut'] === 'confirme' || ($myOrdre > 0 && (int)$esc['ordre'] < $myOrdre)) {
                $excludeDests[] = mb_strtolower(trim($esc['ville'] ?? $esc['nom']));
                $excludeDests[] = mb_strtolower(trim($esc['nom']));
            }
        }
        $excludeDests = array_filter(array_unique($excludeDests));
        if (!empty($excludeDests) && !empty($meLignesByBrd[$bid])) {
            $visibleLignes = array_filter($meLignesByBrd[$bid], function($l) use ($excludeDests) {
                $dest = mb_strtolower(trim($l['destination'] ?? ''));
                if (!$dest) return true;
                foreach ($excludeDests as $ev) {
                    if ($ev && ($dest === $ev || strpos($ev, $dest) !== false || strpos($dest, $ev) !== false)) return false;
                }
                return true;
            });
            $mesEscales[$i]['nb_passagers_reel'] = count($visibleLignes);
            $mesEscales[$i]['recette_reelle'] = array_sum(array_column($visibleLignes, 'montant'));
        }
    }
}

include '../../includes/header.php';
?>
<div class="breadcrumb"><a href="<?= BASE_URL ?>"><i class="fas fa-home"></i></a><span class="breadcrumb-sep">/</span>Bordereaux</div>

<div class="card" style="margin-bottom:16px;border-left:4px solid var(--warning);">
  <div class="card-header" style="background:var(--warning-bg,#fffbeb);">
    <h3 style="color:var(--warning,#d97706);"><i class="fas fa-bell"></i> <?= isAdmin() ? 'Escales en attente' : 'Escales en attente — Votre agence' ?></h3>
    <span style="font-size:12px;background:var(--warning,#d97706);color:#fff;padding:2px 10px;border-radius:12px;font-weight:700;"><?= count($mesEscales) ?></span>
  </div>
  <div class="card-body" style="padding:0;">
    <?php if (empty($mesEscales)): ?>
    <div style="padding:24px;text-align:center;color:var(--text3);">
      <i class="fas fa-check-circle" style="font-size:24px;color:var(--success);"></i>
      <p style="margin-top:8px;">Aucune escale en attente pour <?= isAdmin() ? 'le moment' : 'votre agence' ?>.</p>
    </div>
    <?php else: ?>
    <div class="table-wrap">
    <table data-no-filter>
      <thead><tr><th>Bordereau</th><th>Voyage</th><th>Trajet</th><th>Escale</th><th>Véhicule</th><th>Passagers</th><th>Recette</th><th>Actions</th></tr></thead>
      <tbody>
        <?php foreach ($mesEscales as $me): ?>
        <tr>
          <td><code style="font-size:11px;"><?= sanitize($me['numero']) ?></code></td>
          <td><code style="font-size:11px;color:var(--text3);"><?= sanitize($me['voy_num']) ?></code></td>
          <td><strong><?= sanitize($me['agence_depart'] ?? '—') ?> → <?= sanitize($me['agence_arrivee'] ?? '—') ?></strong></td>
          <td><?php if (!empty($me['escale_agence_ville'])): ?><span class="badge badge-amber"><i class="fas fa-map-pin"></i> <?= sanitize($me['escale_agence_ville'] ?? $me['escale_agence_nom'] ?? '—') ?></span><?php else: ?><span class="badge badge-blue"><i class="fas fa-road"></i> En transit</span><?php endif; ?></td>
          <td><?= sanitize($me['vehicule_immat'] ?? '—') ?></td>
          <td style="text-align:center;font-weight:600;"><?= $me['nb_passagers_reel'] ?></td>
          <td style="font-weight:700;color:var(--success);"><?= number_format($me['recette_reelle'], 0, ',', ' ') ?> FCFA</td>
          <td>
            <a href="valider.php?id=<?= $me['id'] ?>" class="btn btn-xs btn-warning"><i class="fas fa-check-circle"></i> Gérer escale</a>
            <a href="voir.php?id=<?= $me['id'] ?>" class="btn btn-xs btn-secondary"><i class="fas fa-eye"></i></a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    </div>
    <?php endif; ?>
  </div>
</div>

<div class="card" style="margin-bottom:16px;">
  <div class="card-header">
    <h3><i class="fas fa-file-invoice"></i> Bordereaux (<?= $totalRows ?>)</h3>
    <?php if(can('bordereaux.create')): ?><a href="generer.php" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Générer</a><?php endif; ?>
  </div>
  <div class="card-body">
    <form method="GET" class="filter-bar">
      <input type="text" name="q" class="fc" placeholder="🔍 Numéro, véhicule..." value="<?= sanitize($search) ?>" style="max-width:240px;">
      <input type="date" name="date_d" class="fc" value="<?= sanitize($date_d) ?>" style="width:auto;" title="Date début">
      <input type="date" name="date_f" class="fc" value="<?= sanitize($date_f) ?>" style="width:auto;" title="Date fin">
      <select name="type" class="fc" style="width:auto;"><option value="">Tous types</option><option value="chauffeur">Chauffeur</option><option value="comptabilite">Comptabilité</option><option value="transit">Transit</option></select>
      <select name="statut" class="fc" style="width:auto;"><option value="">Tous statuts</option><?php foreach(['genere','en_cours','cloture'] as $s): ?><option value="<?= $s ?>" <?= $statut===$s?'selected':'' ?>><?= statutLabel($s) ?></option><?php endforeach; ?></select>
      <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-search"></i></button>
      <a href="?" class="btn btn-ghost btn-sm"><i class="fas fa-times"></i></a>
    </form>
    <div class="table-wrap">
    <table data-no-filter>
      <thead><tr><th>Numéro</th><th>Voyage</th><th>Trajet</th><th>Véhicule</th><th>Chauffeur</th><th>Départ</th><th>Passagers</th><th>Recette brute</th><th>Type</th><th>Statut</th><th>Actions</th></tr></thead>
      <tbody>
        <?php foreach($bordereaux as $brd): ?>
        <tr>
          <td><code style="font-size:11px;"><?= sanitize($brd['numero']) ?></code><?php if(!empty($brd['parent_id'])||!empty($brd['nb_segments'])): ?> <span class="badge badge-purple" style="font-size:9px;margin-left:4px;" title="Bordereau segmentaire"><i class="fas fa-link"></i><?php if(!empty($brd['nb_segments'])): ?> +<?= (int)$brd['nb_segments'] ?><?php endif; ?></span><?php endif; ?></td>
          <td><code style="font-size:11px;color:var(--text3);"><?= sanitize($brd['voy_num']) ?></code></td>
          <td><?= sanitize($brd['dep']??'') ?> → <?= sanitize($brd['arr']??'') ?></td>
          <td><?= sanitize($brd['vehicule_immat']??'—') ?></td>
          <td><?= sanitize($brd['chauffeur_nom']??'—') ?></td>
          <td style="font-size:11px;"><?= fdatetime($brd['date_depart']??'') ?></td>
          <td style="text-align:center;font-weight:600;"><?= $brd['nb_passagers_reel'] ?></td>
          <td style="font-weight:700;color:var(--success);"><?= number_format($brd['recette_reelle'],0,',',' ') ?></td>
          <td><span class="badge badge-blue"><?= sanitize($brd['type']) ?></span></td>
          <td><span class="tag-statut st-<?= $brd['statut'] ?>"><?= statutLabel($brd['statut']) ?></span><?php if($brd['statut']==='en_cours' && !empty($brd['escale_attente'])): ?><br><span class="badge badge-amber" style="font-size:9px;margin-top:2px;"><i class="fas fa-bell"></i> Votre escale</span><?php endif; ?></td>
          <td>
            <div style="display:flex;gap:3px;">
              <a href="voir.php?id=<?= $brd['id'] ?>" class="btn btn-xs btn-primary"><i class="fas fa-eye"></i></a>
              <a href="imprimer.php?id=<?= $brd['id'] ?>" class="btn btn-xs btn-info"><i class="fas fa-print"></i></a>
              <?php if(can('bordereaux.validate') && $brd['statut']==='genere'): ?><a href="valider.php?id=<?= $brd['id'] ?>" class="btn btn-xs btn-success" title="Valider le bordereau"><i class="fas fa-check"></i></a><?php endif; ?>
              <?php if(can('bordereaux.validate') && $brd['statut']==='en_cours'): ?><a href="valider.php?id=<?= $brd['id'] ?>" class="btn btn-xs btn-warning" title="Validation escale"><i class="fas fa-check-circle"></i></a><?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if(empty($bordereaux)): ?><tr><td colspan="11" class="t-empty"><i class="fas fa-file-invoice"></i>Aucun bordereau</td></tr><?php endif; ?>
      </tbody>
    </table>
    </div>
    <?php if($totalPages>1): ?><div class="pagination"><?php for($i=1;$i<=$totalPages;$i++): ?><a href="?page=<?= $i ?>&q=<?= urlencode($search) ?>&statut=<?= urlencode($statut) ?>&type=<?= urlencode($type) ?>&date_d=<?= urlencode($date_d) ?>&date_f=<?= urlencode($date_f) ?>" class="page-btn <?= $i==$page?'active':'' ?>"><?= $i ?></a><?php endfor; ?></div><?php endif; ?>
  </div>
</div>
<?php include '../../includes/footer.php'; ?>