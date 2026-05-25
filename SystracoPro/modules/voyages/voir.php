<?php
// modules/voyages/voir.php
require_once '../../includes/config.php';
requireLogin(); requirePerm('voyages.create');

$id = (int)($_GET['id'] ?? 0);
if (!$id) redirect(BASE_URL.'modules/voyages/index.php');

// ── POST: confirmer le départ du voyage ──
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['depart_voyage']) && can('voyages.create')) {
    requireCsrf();
    $vid = (int)$_POST['depart_voyage'];
    $pdo->prepare("UPDATE voyages SET statut='en_cours' WHERE id=? AND statut='programme'")->execute([$vid]);
    $brds=$pdo->prepare("SELECT b.id, v.itineraire_id FROM bordereaux b JOIN voyages v ON b.voyage_id=v.id WHERE b.voyage_id=? AND b.statut='genere'");
    $brds->execute([$vid]);
    foreach($brds->fetchAll(PDO::FETCH_ASSOC) as $brd){
        $pdo->prepare("UPDATE bordereaux SET statut='en_cours' WHERE id=?")->execute([$brd['id']]);
        $hasEscale=$pdo->prepare("SELECT 1 FROM bordereau_escales WHERE bordereau_id=? LIMIT 1");
        $hasEscale->execute([$brd['id']]);
        if(!$hasEscale->fetchColumn() && $brd['itineraire_id']){
            $es=$pdo->prepare("SELECT agence_id,ordre FROM itineraire_escales WHERE itineraire_id=? ORDER BY ordre");
            $es->execute([$brd['itineraire_id']]);
            foreach($es->fetchAll(PDO::FETCH_ASSOC) as $i=>$esc){
                $pdo->prepare("INSERT INTO bordereau_escales (bordereau_id,agence_id,ordre,statut) VALUES (?,?,?,?)")
                    ->execute([$brd['id'],$esc['agence_id'],$esc['ordre'],$i===0?'confirme':'en_attente']);
            }
        }
    }
    logAction($pdo,'depart_voyage','voyages','Voyage '.$vid.' — départ confirmé');
    flash('Départ confirmé — voyage en cours.','success');
    redirect(BASE_URL."modules/voyages/voir.php?id=$vid");
}
// ── POST: annuler le voyage ──
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['cancel_voyage']) && can('voyages.cancel')) {
    requireCsrf();
    $vid = (int)$_POST['cancel_voyage'];
    $pdo->prepare("UPDATE voyages SET statut='annule' WHERE id=?")->execute([$vid]);
    $pdo->prepare("UPDATE bordereaux SET statut='annule' WHERE voyage_id=? AND statut IN ('genere','en_cours')")->execute([$vid]);
    logAction($pdo,'annulation_voyage','voyages','Voyage '.$vid.' annulé');
    flash('Voyage annulé.','warning');
    redirect(BASE_URL."modules/voyages/index.php");
}

// ── POST: ajouter un ticket directement depuis la fiche voyage ──
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['action']) && $_POST['action']==='ajouter_passager' && can('tickets.create')) {
    requireCsrf();
    $nom      = mb_strtoupper(trim($_POST['passager_nom'] ?? ''));
    $tel      = trim($_POST['passager_tel'] ?? '');
    $cni      = trim($_POST['passager_cni'] ?? '');
    $siege    = trim($_POST['siege'] ?? '');
    $classe   = $_POST['classe'] ?? 'cla';
    $tarif_id = (int)($_POST['tarif_id'] ?? 0);
    $montant  = (float)($_POST['montant'] ?? 0);
    $mode     = $_POST['mode_paiement'] ?? 'especes';
    $type_psg = $_POST['type_passager'] ?? 'adulte';
    $bag_kg   = (float)($_POST['bagages_kg'] ?? 0);
    $bag_m    = (float)($_POST['montant_bagages'] ?? 0);
    $total    = $montant + $bag_m;
    $vid      = $id;
    $aid      = getUserAgenceId();

    if (!$nom || $montant <= 0) {
        flash('Nom passager et montant obligatoires.', 'danger');
    } else {
        try {
            $sv=$pdo->prepare("SELECT agence_id, places_dispo, destination_id FROM voyages WHERE id=?"); $sv->execute([$vid]);
            $voyageRow = $sv->fetch(PDO::FETCH_ASSOC);
            $ticketAgence = $aid ?? $voyageRow['agence_id'];
            $placesDispo = (int)$voyageRow['places_dispo'];

            // Verifier siege unique
            if ($siege) {
                $sc = $pdo->prepare("SELECT id FROM tickets WHERE voyage_id=? AND siege=? AND statut='vendu' LIMIT 1");
                $sc->execute([$vid, $siege]);
                if ($sc->fetchColumn()) {
                    flash("Le siège $siege est déjà occupé.", 'danger');
                    redirect(BASE_URL."modules/voyages/voir.php?id=$vid");
                }
            }

            $pdo->beginTransaction();
            $num = genNumero($pdo, 'tickets', 'numero', getParam('prefix_ticket','TKT'));

            // Passager
            $passager_id = null;
            if ($tel) {
                $ep = $pdo->prepare("SELECT id FROM passagers WHERE telephone=? LIMIT 1");
                $ep->execute([$tel]);
                if ($existing = $ep->fetchColumn()) {
                    $passager_id = $existing;
                } else {
                    $parts = preg_split('/\s+/', trim($nom), 2);
                    $pdo->prepare("INSERT INTO passagers (nom,prenom,telephone,cni) VALUES (?,?,?,?)")->execute([$parts[0],$parts[1]??'',$tel,$cni ?: null]);
                    $passager_id = $pdo->lastInsertId();
                }
            }

            // Récupérer les agences depuis le voyage
            $s_ag=$pdo->prepare("SELECT IFNULL(i.agence_depart,d.agence_depart) as dep,IFNULL(i.agence_arrivee,d.agence_arrivee) as arr FROM voyages v LEFT JOIN itineraires i ON v.itineraire_id=i.id LEFT JOIN destinations d ON v.destination_id=d.id WHERE v.id=?");
            $s_ag->execute([$vid]); $agRow=$s_ag->fetch();

            $pdo->prepare("INSERT INTO tickets (numero,voyage_id,passager_id,passager_nom,passager_tel,passager_cni,siege,classe,tarif_id,montant,bagages_kg,montant_bagages,montant_total,statut,mode_paiement,agence_id,guichetier_id,agence_depart_id,agence_arrivee_id,type_passager) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
                ->execute([$num,$vid,$passager_id,$nom,$tel,$cni?:null,$siege,$classe,$tarif_id?:null,$montant,$bag_kg,$bag_m,$total,'vendu',$mode,$ticketAgence,$_SESSION['user_id'],$agRow['dep']??null,$agRow['arr']??null,$type_psg]);

            $pdo->commit();
            logAction($pdo,'vente_ticket','tickets',"Ticket $num — $nom — ".money($montant).' ('.$type_psg.')');
            flash("Ticket $num créé avec succès.");
        } catch(Exception $e) {
            $pdo->rollBack();
            flash('Erreur : '.sanitize($e->getMessage()), 'danger');
        }
    }
    redirect(BASE_URL."modules/voyages/voir.php?id=$vid");
}

// ── POST: dissocier des tickets du bordereau ──
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['action']) && $_POST['action']==='dissocier' && can('bordereaux.create')) {
    requireCsrf();
    $ticket_ids = array_map('intval', $_POST['ticket_ids'] ?? []);
    if (!empty($ticket_ids)) {
        $in = implode(',', $ticket_ids);
        // Vérifier si un bordereau en_cours est concerné
        $brdEnCours = $pdo->query("SELECT COUNT(*) FROM bordereau_lignes bl JOIN bordereaux b ON bl.bordereau_id=b.id WHERE bl.ticket_id IN ($in) AND b.statut='en_cours'")->fetchColumn();
        if ($brdEnCours > 0 && !can('bordereaux.dissocier')) {
            flash('Impossible de dissocier des tickets d\'un bordereau en cours. Permission insuffisante.', 'danger');
            redirect(BASE_URL."modules/voyages/voir.php?id=$id");
        }
        // Récupérer les bordereaux affectés AVANT suppression
        $affectedBrdIds = $pdo->query("SELECT DISTINCT bordereau_id FROM bordereau_lignes WHERE ticket_id IN ($in)")->fetchAll(PDO::FETCH_COLUMN);
        // Supprimer les lignes de bordereau correspondantes
        $pdo->exec("DELETE FROM bordereau_lignes WHERE ticket_id IN ($in)");
        // Dissocier les tickets
        $pdo->exec("UPDATE tickets SET bordereau_id=NULL WHERE id IN ($in)");
        // Recalculer les bordereaux affectés
        foreach ($affectedBrdIds as $brdId) {
            $pdo->prepare("UPDATE bordereaux SET nb_passagers=(SELECT COUNT(*) FROM bordereau_lignes WHERE bordereau_id=?), recette_brute=(SELECT COALESCE(SUM(montant),0) FROM bordereau_lignes WHERE bordereau_id=?), recette_nette=(SELECT COALESCE(SUM(montant),0) FROM bordereau_lignes WHERE bordereau_id=?)-montant_carburant-montant_peage-avance_chauffeur-autres_deductions WHERE id=?")
                ->execute([$brdId, $brdId, $brdId, $brdId]);
        }
        flash(count($ticket_ids).' ticket(s) dissociés du bordereau.', 'warning');
    }
    redirect(BASE_URL."modules/voyages/voir.php?id=$id");
}

// ── POST: transiter des tickets vers un bordereau transit ──
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['action']) && $_POST['action']==='transiter' && can('bordereaux.create')) {
    requireCsrf();
    $ticket_ids = array_map('intval', $_POST['ticket_ids'] ?? []);
    $vid = $id;
    if (!empty($ticket_ids)) {
        try {
            $pdo->beginTransaction();
            $in = implode(',', $ticket_ids);

            // Récupérer les tickets
            $tkts = $pdo->query("SELECT t.*,aa.ville as dest_ville FROM tickets t LEFT JOIN agences aa ON t.agence_arrivee_id=aa.id WHERE t.id IN ($in) AND statut='vendu'")->fetchAll();
            if (empty($tkts)) throw new Exception('Aucun ticket valide');

            // Récupérer les infos du voyage
            $sv = $pdo->prepare("SELECT v.*,a1.ville as dep,a1.nom as dep_nom,a2.ville as arr,a2.nom as arr_nom,veh.immatriculation,CONCAT(p.prenom,' ',p.nom) as chauf_nom,p.permis as chauf_permis,v.convoyeur_nom FROM voyages v LEFT JOIN itineraires i ON v.itineraire_id=i.id LEFT JOIN destinations d ON v.destination_id=d.id JOIN agences a1 ON IFNULL(i.agence_depart,d.agence_depart)=a1.id JOIN agences a2 ON IFNULL(i.agence_arrivee,d.agence_arrivee)=a2.id LEFT JOIN vehicules veh ON v.vehicule_id=veh.id LEFT JOIN personnel p ON v.chauffeur_id=p.id WHERE v.id=?");
            $sv->execute([$vid]); $vData = $sv->fetch();
            if (!$vData) throw new Exception('Voyage introuvable');

            // Trouver ou créer un bordereau transit pour ce voyage
            $brd = $pdo->prepare("SELECT id FROM bordereaux WHERE voyage_id=? AND type='transit' AND statut='en_cours' LIMIT 1");
            $brd->execute([$vid]); $brdId = $brd->fetchColumn();

            if (!$brdId) {
                $num = genNumero($pdo, 'bordereaux', 'numero', getParam('prefix_bordereau','BRD'));
                $pdo->prepare("INSERT INTO bordereaux (numero,voyage_id,agence_id,type,vehicule_immat,chauffeur_nom,chauffeur_permis,convoyeur_nom,agence_depart,agence_arrivee,date_depart,nb_passagers,recette_brute,recette_nette,created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,0,0,0,?)")
                    ->execute([$num,$vid,$vData['agence_id'],'transit',$vData['immatriculation']??'',$vData['chauf_nom']??'',$vData['chauf_permis']??'',$vData['convoyeur_nom']??'',$vData['dep_nom'],$vData['arr_nom'],$vData['date_depart'],$_SESSION['user_id']]);
                $brdId = $pdo->lastInsertId();

                // Copier les escales
                $escales = [];
                if ($vData['itineraire_id']) {
                    $es = $pdo->prepare("SELECT a.id FROM itineraire_escales e JOIN agences a ON e.agence_id=a.id WHERE e.itineraire_id=? ORDER BY e.ordre");
                    $es->execute([$vData['itineraire_id']]); $escales = $es->fetchAll();
                }
                foreach ($escales as $i => $esc) {
                    $pdo->prepare("INSERT INTO bordereau_escales (bordereau_id,agence_id,ordre,statut) VALUES (?,?,?,'en_attente')")
                        ->execute([$brdId, $esc['id'], $i + 1]);
                }
            }

            // Mettre à jour l'ancien bordereau (recalculer totaux après retrait)
            $oldBrdIds = $pdo->query("SELECT DISTINCT bordereau_id FROM bordereau_lignes WHERE ticket_id IN ($in)")->fetchAll(PDO::FETCH_COLUMN);

            // Supprimer les anciennes lignes de bordereau
            $pdo->exec("DELETE FROM bordereau_lignes WHERE ticket_id IN ($in)");

            // Recalculer les anciens bordereaux
            foreach ($oldBrdIds as $obId) {
                $pdo->prepare("UPDATE bordereaux SET nb_passagers=(SELECT COUNT(*) FROM bordereau_lignes WHERE bordereau_id=?), recette_brute=(SELECT COALESCE(SUM(montant),0) FROM bordereau_lignes WHERE bordereau_id=?), recette_nette=(SELECT COALESCE(SUM(montant),0) FROM bordereau_lignes WHERE bordereau_id=?)-montant_carburant-montant_peage-avance_chauffeur-autres_deductions WHERE id=?")
                    ->execute([$obId, $obId, $obId, $obId]);
            }

            // Insérer dans le bordereau transit + update tickets
            foreach ($tkts as $tk) {
                $pdo->prepare("INSERT INTO bordereau_lignes (bordereau_id,ticket_id,passager_nom,siege,destination,montant,classe) VALUES (?,?,?,?,?,?,?)")
                    ->execute([$brdId, $tk['id'], $tk['passager_nom'], $tk['siege']??'', $tk['dest_ville']??'', $tk['montant_total'], $tk['classe']]);
                $pdo->prepare("UPDATE tickets SET bordereau_id=? WHERE id=?")->execute([$brdId, $tk['id']]);
            }

            // Mettre à jour les totaux du bordereau transit
            $pdo->prepare("UPDATE bordereaux SET nb_passagers=(SELECT COUNT(*) FROM bordereau_lignes WHERE bordereau_id=?), recette_brute=(SELECT COALESCE(SUM(montant),0) FROM bordereau_lignes WHERE bordereau_id=?), recette_nette=(SELECT COALESCE(SUM(montant),0) FROM bordereau_lignes WHERE bordereau_id=?)-montant_carburant-montant_peage-avance_chauffeur-autres_deductions WHERE id=?")
                ->execute([$brdId, $brdId, $brdId, $brdId]);

            $pdo->commit();
            logAction($pdo, 'transiter_tickets', 'tickets', count($ticket_ids).' tickets transités vers bordereau #'.$brdId);
            flash(count($ticket_ids).' ticket(s) transités vers le bordereau transit.', 'success');
        } catch (Exception $e) {
            $pdo->rollBack();
            flash('Erreur : '.sanitize($e->getMessage()), 'danger');
        }
    }
    redirect(BASE_URL."modules/voyages/voir.php?id=$id");
}
$stmt = $pdo->prepare("SELECT v.*,a1.ville as dep,a1.nom as dep_nom,a2.ville as arr,a2.nom as arr_nom,veh.immatriculation,veh.marque,veh.capacite,CONCAT(p.prenom,' ',p.nom) as chauf_nom,p.permis as chauf_permis,CONCAT(u.prenom,' ',u.nom) as cree_par FROM voyages v LEFT JOIN itineraires i ON v.itineraire_id=i.id LEFT JOIN destinations d ON v.destination_id=d.id LEFT JOIN agences a1 ON IFNULL(i.agence_depart,d.agence_depart)=a1.id LEFT JOIN agences a2 ON IFNULL(i.agence_arrivee,d.agence_arrivee)=a2.id LEFT JOIN vehicules veh ON v.vehicule_id=veh.id LEFT JOIN personnel p ON v.chauffeur_id=p.id LEFT JOIN utilisateurs u ON v.created_by=u.id WHERE v.id=?");
$stmt->execute([$id]); $v=$stmt->fetch();
if(!$v){flash('Voyage introuvable.','danger');redirect(BASE_URL.'modules/voyages/index.php');}

$tickets=$pdo->prepare("SELECT t.*,CONCAT(u.prenom,' ',u.nom) as guichetier, IF(t.voyage_id IS NULL,'Libre','Rattaché') as type_vente,aa.ville as dest_ville FROM tickets t LEFT JOIN utilisateurs u ON t.guichetier_id=u.id LEFT JOIN agences aa ON t.agence_arrivee_id=aa.id WHERE t.voyage_id=? OR (t.voyage_id IS NULL AND t.agence_id=?) ORDER BY t.bordereau_id IS NULL, t.voyage_id IS NULL, t.siege, t.date_vente");
$tickets->execute([$id, $v['agence_id']]); $tickets=$tickets->fetchAll();
// Filtrage par agence : un guichetier ne voit que les tickets de son agence + ceux dans les bordereaux du voyage
$myAid = getUserAgenceId();
if (!isAdmin() && $myAid && $myAid != $v['agence_id']) {
    $bordIdsForFilter = $pdo->prepare("SELECT id FROM bordereaux WHERE voyage_id=?"); $bordIdsForFilter->execute([$id]);
    $bordIdsArr = array_column($bordIdsForFilter->fetchAll(PDO::FETCH_ASSOC), 'id');
    $tickets = array_filter($tickets, function($t) use ($myAid, $bordIdsArr) {
        return $t['agence_id'] == $myAid || ($t['bordereau_id'] && in_array($t['bordereau_id'], $bordIdsArr));
    });
    $tickets = array_values($tickets);
}

// Escales dépassées : exclure les passagers dont la destination est une escale déjà franchie
$escaleDestNames = [];
if ($myAid) {
    $myAg = $pdo->prepare("SELECT nom,ville FROM agences WHERE id=?"); $myAg->execute([$myAid]); $myAgData = $myAg->fetch(PDO::FETCH_ASSOC);
    if ($myAgData) $escaleDestNames = [mb_strtolower(trim($myAgData['ville'])), mb_strtolower(trim($myAgData['nom']))];
    // Escales des bordereaux : on exclut les passagers dont la destination est une escale d'ordre <= l'ordre de l'agence de l'utilisateur (bus déjà passé)
    if (!empty($bords)) {
        $bordIdsEsc = array_map('intval', array_column($bords, 'id'));
        // Trouver l'ordre de l'escale de l'utilisateur
        $myOrder = $pdo->prepare("SELECT MIN(ordre) as ordre FROM bordereau_escales WHERE bordereau_id IN (".implode(',', $bordIdsEsc).") AND agence_id=?");
        $myOrder->execute([$myAid]); $myOrdre = (int)$myOrder->fetchColumn();
        // Escales confirmées (déjà franchies)
        $escQ = $pdo->query("SELECT be.ordre, be.statut, a.ville, a.nom FROM bordereau_escales be JOIN agences a ON be.agence_id=a.id WHERE be.bordereau_id IN (".implode(',', $bordIdsEsc).") ORDER BY be.ordre");
        foreach ($escQ->fetchAll(PDO::FETCH_ASSOC) as $esc) {
            $escOrdre = (int)$esc['ordre'];
            $escVille = mb_strtolower(trim($esc['ville'] ?? $esc['nom']));
            // Exclure si : escale confirmée OU escale d'ordre inférieur à la position de l'utilisateur (bus déjà passé)
            if ($esc['statut'] === 'confirme' || ($myOrdre > 0 && $escOrdre < $myOrdre)) {
                $escaleDestNames[] = $escVille;
                $escaleDestNames[] = mb_strtolower(trim($esc['nom']));
            }
        }
    }
    $escaleDestNames = array_filter(array_unique($escaleDestNames));
}
$sold=array_filter($tickets,fn($t)=>$t['statut']==='vendu'||$t['statut']==='utilise');
$annul=array_filter($tickets,fn($t)=>$t['statut']==='annule');

// Filtrer les passagers arrivés à une escale confirmée
if (!empty($escaleDestNames)) {
    $sold = array_filter($sold, function($t) use ($escaleDestNames) {
        $dest = mb_strtolower(trim($t['dest_ville'] ?? ''));
        if (!$dest) return true;
        foreach ($escaleDestNames as $ev) {
            if ($ev && ($dest === $ev || strpos($ev, $dest) !== false || strpos($dest, $ev) !== false)) return false;
        }
        return true;
    });
    $sold = array_values($sold);
}

$soldSansBord=array_filter($sold,fn($t)=>!$t['bordereau_id']); // tickets sans bordereau
$soldAvecBord=array_filter($sold,fn($t)=>$t['bordereau_id']);   // tickets avec bordereau
$soldVoyage=array_filter($sold,fn($t)=>$t['voyage_id']==$id); // tickets liés au voyage (pour l'occupation)
$recette=array_sum(array_column(array_values($soldVoyage),'montant_total'));

$bords=$pdo->prepare("SELECT * FROM bordereaux WHERE voyage_id=? ORDER BY type");
$bords->execute([$id]); $bords=$bords->fetchAll();
$hasBrdEnCours = !empty(array_filter($bords, fn($b) => $b['statut'] === 'en_cours'));
$canDissocier = $hasBrdEnCours ? can('bordereaux.dissocier') : can('bordereaux.create');

// Occupation du bus : tickets du voyage + tickets des bordereaux associés
$capaciteBus = !empty($v['capacite']) ? (int)$v['capacite'] : (int)$v['places_dispo'];
$bordTicketIds = [];
if (!empty($bords)) {
    $bordIds = array_map('intval', array_column($bords, 'id'));
    $bordTicketIds = $pdo->query("SELECT id FROM tickets WHERE bordereau_id IN (".implode(',',$bordIds).") AND statut='vendu'")->fetchAll(PDO::FETCH_COLUMN);
}
$soldOccupation = array_filter($sold, function($t) use ($id, $bordTicketIds) {
    return ($t['voyage_id'] ?? null) == $id || in_array($t['id'], $bordTicketIds);
});
$recetteOccupation = array_sum(array_column(array_values($soldOccupation), 'montant_total'));

// Escales du voyage (pour filtrage transit)
$escales = [];
$routeVilles = [];
if ($v['itineraire_id']) {
    $es = $pdo->prepare("SELECT a.ville,a.nom FROM itineraire_escales e JOIN agences a ON e.agence_id=a.id WHERE e.itineraire_id=? ORDER BY e.ordre");
    $es->execute([$v['itineraire_id']]); $escales = $es->fetchAll();
}
$routeVilles = array_unique(array_filter(array_merge(
    [mb_strtolower(trim($v['dep']??'')), mb_strtolower(trim($v['arr']??''))],
    array_map(function($e){ return mb_strtolower(trim($e['ville']??$e['nom']??'')); }, $escales)
)));
// Tickets sans bordereau éligibles au transit (destination hors route/escales)
$soldTransit = array_filter($soldSansBord, function($t) use ($routeVilles) {
    $dest = mb_strtolower(trim($t['dest_ville']??''));
    if (!$dest) return false;
    foreach ($routeVilles as $rv) {
        if ($dest === $rv || strpos($rv, $dest) !== false || strpos($dest, $rv) !== false) return false;
    }
    return true;
});

// Tarifs disponibles pour ce voyage
$tarifsDispo=[];
if ($v['itineraire_id']) {
    $ts=$pdo->prepare("SELECT * FROM tarifs WHERE itineraire_id=? AND actif=1 ORDER BY classe");
    $ts->execute([$v['itineraire_id']]);
    $tarifsDispo=$ts->fetchAll();
}
if (empty($tarifsDispo) && $v['destination_id']) {
    $ts=$pdo->prepare("SELECT * FROM tarifs WHERE destination_id=? AND actif=1 ORDER BY classe");
    $ts->execute([$v['destination_id']]);
    $tarifsDispo=$ts->fetchAll();
}

// Sieges occupes
$siegesOccupes = array_filter(array_column($sold, 'siege'));

$pageTitle='Voyage '.$v['numero'];
include '../../includes/header.php';
?>
<div class="breadcrumb"><a href="<?= BASE_URL ?>"><i class="fas fa-home"></i></a><span class="breadcrumb-sep">/</span><a href="index.php">Voyages</a><span class="breadcrumb-sep">/</span><?= sanitize($v['numero']) ?></div>
<style>
.tab-link{display:inline-block;padding:6px 14px;font-size:12px;font-weight:600;cursor:pointer;border-radius:6px;color:var(--text2);transition:all .15s;}
.tab-link:hover{background:var(--bg);color:var(--text1);}
.tab-link.active{background:var(--primary);color:#fff;}
.tab-panel{display:none;}
.tab-panel.active{display:block;}
</style>

<div class="no-print" style="display:flex;gap:8px;margin-bottom:14px;flex-wrap:wrap;">
  <?php if($v['statut']==='programme' && can('tickets.create')): ?>
  <button class="btn btn-primary" onclick="openModal('modalPassager')"><i class="fas fa-user-plus"></i> Ajouter un passager</button>
  <?php endif; ?>

  <?php if(can('bordereaux.create') && can('voyages.create') && in_array($v['statut'],['programme','en_cours'])): ?>
  <button type="button" class="btn btn-success" onclick="ouvrirDepartModal(<?= $id ?>)"><i class="fas fa-bus"></i> Gérer le départ</button>
  <?php endif; ?>

  <?php if($v['statut']==='programme' && can('voyages.cancel')): ?><form method="POST" action="index.php" style="display:inline" onsubmit="return confirm('Annuler ce voyage ?')"><input type="hidden" name="_csrf" value="<?= csrfToken() ?>"><input type="hidden" name="cancel_voyage" value="<?= $id ?>"><button type="submit" class="btn btn-danger"><i class="fas fa-times"></i> Annuler</button></form><?php endif; ?>
  <a href="index.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Retour</a>
</div>

<!-- STATS RAPIDES -->
<div class="stats-grid" style="grid-template-columns:repeat(5,1fr);margin-bottom:18px;">
  <div class="stat-card"><div class="stat-icon" style="background:linear-gradient(135deg,#1d4ed8,#60a5fa)"><i class="fas fa-chair"></i></div><div><div class="stat-val"><?= $capaciteBus ?></div><div class="stat-lbl">Places bus</div></div></div>
  <div class="stat-card"><div class="stat-icon" style="background:linear-gradient(135deg,#16a34a,#4ade80)"><i class="fas fa-ticket-alt"></i></div><div><div class="stat-val"><?= count($soldOccupation) ?></div><div class="stat-lbl">Tickets vendus</div></div></div>
  <div class="stat-card"><div class="stat-icon" style="background:linear-gradient(135deg,#dc2626,#f87171)"><i class="fas fa-times"></i></div><div><div class="stat-val"><?= count($annul) ?></div><div class="stat-lbl">Annulés</div></div></div>
  <div class="stat-card"><div class="stat-icon" style="background:linear-gradient(135deg,#d97706,#fbbf24)"><i class="fas fa-money-bill-wave"></i></div><div><div class="stat-val" style="font-size:14px;"><?= number_format($recetteOccupation,0,',',' ') ?></div><div class="stat-lbl">Recette (FCFA)</div></div></div>
  <div class="stat-card"><div class="stat-icon" style="background:linear-gradient(135deg,#7c3aed,#a78bfa)"><i class="fas fa-file-invoice"></i></div><div><div class="stat-val"><?= count($bords) ?></div><div class="stat-lbl">Bordereaux</div></div></div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:18px;margin-bottom:18px;">
<!-- INFO VOYAGE -->
<div class="card">
  <div class="card-header"><h3><i class="fas fa-route"></i> Informations du voyage</h3><span class="tag-statut st-<?= $v['statut'] ?>"><?= statutLabel($v['statut']) ?></span></div>
  <div class="card-body">
    <table data-no-filter style="width:100%;font-size:13px;border-collapse:collapse;">
      <?php $rows=[['N° Voyage',$v['numero']],['Trajet',$v['dep'].' → '.$v['arr']],['Date départ',fdatetime($v['date_depart'])],['Classe',statutLabel($v['classe_voyage'])],['Véhicule',$v['immatriculation']??'—'],['Chauffeur',$v['chauf_nom']??'—'],['Convoyeur',$v['convoyeur_nom']??'—'],['Chef de piste',$v['chef_depiste']??'—'],['Carburant alloué',number_format($v['montant_carburant'],0,',',' ').' FCFA'],['Péages',number_format($v['montant_peage'],0,',',' ').' FCFA'],['Créé par',$v['cree_par']??'—']];
      foreach($rows as [$k,$val]): ?>
      <tr><td style="padding:6px 10px;border-bottom:1px solid var(--border);color:var(--text2);font-weight:500;width:140px;"><?= $k ?></td><td style="padding:6px 10px;border-bottom:1px solid var(--border);"><?= sanitize($val) ?></td></tr>
      <?php endforeach; ?>
    </table>
  </div>
</div>

<!-- PLAN DE CHARGEMENT -->
<div class="card">
  <div class="card-header"><h3><i class="fas fa-chair"></i> Occupation (<?= count($soldOccupation) ?>/<?= $capaciteBus ?>)</h3></div>
  <div class="card-body">
    <?php
    $pct = $capaciteBus>0 ? round(count($soldOccupation)/$capaciteBus*100) : 0;
    $col = $pct>=90?'var(--danger)':($pct>=70?'var(--warning)':'var(--success)');
    ?>
    <div style="margin-bottom:12px;">
      <div style="display:flex;justify-content:space-between;margin-bottom:4px;font-size:13px;">
        <span>Taux de remplissage</span><strong style="color:<?= $col ?>"><?= $pct ?>%</strong>
      </div>
      <div class="progress" style="height:14px;"><div class="progress-bar" style="width:<?= $pct ?>%;background:<?= $col ?>"></div></div>
    </div>
    <!-- Répartition par classe -->
    <?php
    $byClasse=['cla'=>0,'vip'=>0,'spc'=>0];
    foreach($soldOccupation as $t) $byClasse[$t['classe']]++;
    foreach($byClasse as $cl=>$n): if($n>0):
    ?>
    <div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:4px;">
      <span><span class="badge <?= $cl==='vip'?'badge-purple':($cl==='spc'?'badge-teal':'badge-blue') ?>"><?= strtoupper($cl) ?></span></span>
      <span><?= $n ?> passager(s)</span>
    </div>
    <?php endif; endforeach; ?>
    <!-- Recette par mode -->
    <?php
    $byMode=[];
    foreach($soldOccupation as $t) $byMode[$t['mode_paiement']]=($byMode[$t['mode_paiement']]??0)+$t['montant_total'];
    ?>
    <div class="divider"></div>
    <div style="font-size:11px;font-weight:600;color:var(--text2);margin-bottom:6px;">RECETTES PAR MODE</div>
    <?php foreach(['especes'=>'💵','om'=>'📱OM','momo'=>'📱MM','carte'=>'💳'] as $m=>$icon): if(isset($byMode[$m])): ?>
    <div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:3px;"><span><?= $icon ?></span><strong><?= number_format($byMode[$m],0,',',' ') ?> FCFA</strong></div>
    <?php endif; endforeach; ?>
  </div>
</div>
</div>

<!-- LISTE TICKETS VENDUS -->
<div class="card" style="margin-bottom:18px;">
  <div class="card-header" style="flex-wrap:wrap;gap:8px;">
    <h3><i class="fas fa-ticket-alt"></i> Tickets vendus (<?= count($sold) ?>)</h3>
    <div style="display:flex;flex-wrap:wrap;align-items:center;gap:8px;">
      <div style="display:flex;gap:6px;">
        <span class="tab-link active" data-tab="tab-sans" onclick="switchTab('tab-sans',this)">Sans bordereau (<?= count($soldSansBord) ?>)</span>
        <span class="tab-link" data-tab="tab-avec" onclick="switchTab('tab-avec',this)">Avec bordereau (<?= count($soldAvecBord) ?>)</span>
        <span class="tab-link" data-tab="tab-transit" onclick="switchTab('tab-transit',this)">Transit (<?= count($soldTransit) ?>)</span>
      </div>
      <!-- Actions: tab Sans bordereau -->
      <?php if(can('bordereaux.create')): ?>
      <div id="act-sans" style="display:flex;gap:6px;align-items:center;">
        <span style="width:1px;height:18px;background:var(--border);display:inline-block;"></span>
        <button type="button" class="btn btn-xs btn-ghost" onclick="toggleSel(true)"><i class="fas fa-check-double"></i> Tout</button>
        <button type="button" class="btn btn-xs btn-ghost" onclick="toggleSel(false)"><i class="fas fa-times"></i> Rien</button>
        <span style="font-size:12px;color:var(--text2);">Sélection : <strong id="sel-count">0</strong></span>
        <span style="font-size:12px;color:var(--success);">Total : <strong id="sel-total">0</strong> FCFA</span>
        <button type="button" class="btn btn-success btn-sm" id="brd-btn" onclick="genererBordereau()" disabled><i class="fas fa-file-invoice"></i> Associer au bordereau</button>
      </div>
      <!-- Actions: tab Avec bordereau -->
      <div id="act-avec" style="display:none;gap:6px;align-items:center;">
        <?php if($canDissocier): ?>
        <span style="width:1px;height:18px;background:var(--border);display:inline-block;"></span>
        <button type="button" class="btn btn-xs btn-ghost" onclick="toggleDissocier(true)"><i class="fas fa-check-double"></i> Tout</button>
        <button type="button" class="btn btn-xs btn-ghost" onclick="toggleDissocier(false)"><i class="fas fa-times"></i> Rien</button>
        <span style="font-size:12px;color:var(--text2);">Sélection : <strong id="dissocier-count">0</strong></span>
        <button type="button" class="btn btn-warning btn-sm" id="dissocier-btn" onclick="dissocierTickets()" disabled><i class="fas fa-unlink"></i> Dissocier</button>
        <button type="button" class="btn btn-info btn-sm" id="transiter-btn" onclick="transiterTickets()" disabled><i class="fas fa-truck"></i> Transiter</button>
        <?php elseif($hasBrdEnCours): ?>
        <span style="font-size:12px;color:var(--text3);"><i class="fas fa-lock"></i> Dissociation bloquée — bordereau en cours</span>
        <?php endif; ?>
      </div>
      <!-- Actions: tab Transit -->
      <div id="act-transit" style="display:none;gap:6px;align-items:center;">
        <span style="width:1px;height:18px;background:var(--border);display:inline-block;"></span>
        <button type="button" class="btn btn-xs btn-ghost" onclick="toggleTransitSel(true)"><i class="fas fa-check-double"></i> Tout</button>
        <button type="button" class="btn btn-xs btn-ghost" onclick="toggleTransitSel(false)"><i class="fas fa-times"></i> Rien</button>
        <span style="font-size:12px;color:var(--text2);">Sélection : <strong id="transit-count">0</strong></span>
        <span style="font-size:12px;color:var(--success);">Total : <strong id="transit-total">0</strong> FCFA</span>
        <button type="button" class="btn btn-info btn-sm" id="transit-btn" onclick="genererTransitBordereau()" disabled><i class="fas fa-file-invoice"></i> Bordereau transit</button>
      </div>
      <?php endif; ?>
    </div>
  </div>
  <div class="card-body" style="padding:0;">
    <form id="brd-form" method="POST" action="../bordereaux/generer.php">
      <?= csrfField() ?>
      <input type="hidden" name="voyage_id" value="<?= $id ?>">
      <input type="hidden" name="type" value="chauffeur">
      <input type="hidden" name="montant_carburant" value="0">
      <input type="hidden" name="montant_peage" value="0">
      <input type="hidden" name="avance_chauffeur" value="0">
      <input type="hidden" name="autres_deductions" value="0">

      <!-- Tab: Sans bordereau -->
      <div id="tab-sans" class="tab-panel active">
      <div class="table-wrap">
      <table data-no-filter>
        <thead><tr>
          <th style="width:36px;"><input type="checkbox" id="sel-all" onchange="toggleSel(this.checked)"></th>
          <th>N° Ticket</th><th>Passager</th><th>Téléphone</th><th>Siège</th><th>Classe</th><th>Montant</th><th>Destination</th><th>Guichetier</th><th>Heure</th><th>Type</th>
        </tr></thead>
        <tbody>
          <?php foreach($soldSansBord as $t): ?>
          <tr>
            <td><input type="checkbox" name="ticket_ids[]" value="<?= $t['id'] ?>" class="sel-cb" data-montant="<?= $t['montant_total'] ?>" onchange="updateSel()"></td>
            <td><code style="font-size:11px;"><?= sanitize($t['numero']) ?></code></td>
            <td><strong><?= sanitize($t['passager_nom']) ?></strong></td>
            <td style="font-size:12px;"><?= sanitize($t['passager_tel']??'—') ?></td>
            <td style="text-align:center;font-weight:700;"><?= sanitize($t['siege']??'—') ?></td>
            <td><span class="badge <?= $t['classe']==='vip'?'badge-purple':'badge-blue' ?>"><?= strtoupper($t['classe']) ?></span></td>
            <td style="font-weight:600;"><?= number_format($t['montant_total'],0,',',' ') ?></td>
            <td><strong><?= sanitize($t['dest_ville']??'—') ?></strong></td>
            <td style="font-size:12px;"><?= sanitize($t['guichetier']??'—') ?></td>
            <td style="font-size:11px;"><?= date('H:i',strtotime($t['date_vente'])) ?></td>
            <td><?= ($t['type_vente']??'')==='Libre'?'<span class="badge badge-amber">Libre</span>':'<span class="badge badge-blue">Voyage</span>' ?></td>
          </tr>
          <?php endforeach; ?>
          <?php if(empty($soldSansBord)): ?><tr><td colspan="11" class="t-empty">Aucun ticket sans bordereau</td></tr><?php endif; ?>
        </tbody>
      </table>
      </div>
      </div>

      <!-- Tab: Transit -->
      <div id="tab-transit" class="tab-panel">
      <div class="table-wrap">
      <table data-no-filter>
        <thead><tr>
          <th style="width:36px;"><input type="checkbox" id="transit-all" onchange="toggleTransitSel(this.checked)"></th>
          <th>N° Ticket</th><th>Passager</th><th>Téléphone</th><th>Siège</th><th>Classe</th><th>Destination</th><th>Montant</th><th>Mode</th>
        </tr></thead>
        <tbody>
          <?php foreach($soldTransit as $t): ?>
          <tr>
            <td><input type="checkbox" name="ticket_ids[]" value="<?= $t['id'] ?>" class="transit-cb" data-montant="<?= $t['montant_total'] ?>" onchange="updateTransitSel()"></td>
            <td><code style="font-size:11px;"><?= sanitize($t['numero']) ?></code></td>
            <td><strong><?= sanitize($t['passager_nom']) ?></strong></td>
            <td style="font-size:12px;"><?= sanitize($t['passager_tel']??'—') ?></td>
            <td style="text-align:center;font-weight:700;"><?= sanitize($t['siege']??'—') ?></td>
            <td><span class="badge <?= $t['classe']==='vip'?'badge-purple':'badge-blue' ?>"><?= strtoupper($t['classe']) ?></span></td>
            <td><strong><?= sanitize($t['dest_ville']??'—') ?></strong></td>
            <td style="font-weight:600;"><?= number_format($t['montant_total'],0,',',' ') ?></td>
            <td style="font-size:11px;"><?= ['especes'=>'💵','om'=>'📱','momo'=>'📱','carte'=>'💳'][$t['mode_paiement']]??'' ?></td>
          </tr>
          <?php endforeach; ?>
          <?php if(empty($soldTransit)): ?><tr><td colspan="9" class="t-empty">Aucun ticket en transit</td></tr><?php endif; ?>
        </tbody>
      </table>
      </div>
      </div>
    </form>

    <form id="dissocier-form" method="POST">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="dissocier">
      <!-- Tab: Avec bordereau -->
      <div id="tab-avec" class="tab-panel">
      <div class="table-wrap">
      <table data-no-filter>
        <thead><tr>
          <th style="width:36px;"><input type="checkbox" id="dissocier-all" onchange="toggleDissocier(this.checked)"></th>
          <th>N° Ticket</th><th>Passager</th><th>Téléphone</th><th>Siège</th><th>Classe</th><th>Montant</th><th>Destination</th><th>Guichetier</th><th>Heure</th><th>Type</th><th>Transit</th>
        </tr></thead>
        <tbody>
          <?php foreach($soldAvecBord as $t):
            $estTransit = false;
            $dest = mb_strtolower(trim($t['dest_ville']??''));
            if ($dest && !empty($routeVilles)) {
                $estTransit = true;
                foreach ($routeVilles as $rv) {
                    if ($dest === $rv || strpos($rv, $dest) !== false || strpos($dest, $rv) !== false) { $estTransit = false; break; }
                }
            }
          ?>
          <tr>
            <td><input type="checkbox" name="ticket_ids[]" value="<?= $t['id'] ?>" class="dissocier-cb" onchange="updateDissocier()"></td>
            <td><code style="font-size:11px;"><?= sanitize($t['numero']) ?></code></td>
            <td><strong><?= sanitize($t['passager_nom']) ?></strong></td>
            <td style="font-size:12px;"><?= sanitize($t['passager_tel']??'—') ?></td>
            <td style="text-align:center;font-weight:700;"><?= sanitize($t['siege']??'—') ?></td>
            <td><span class="badge <?= $t['classe']==='vip'?'badge-purple':'badge-blue' ?>"><?= strtoupper($t['classe']) ?></span></td>
            <td style="font-weight:600;"><?= number_format($t['montant_total'],0,',',' ') ?></td>
            <td><strong><?= sanitize($t['dest_ville']??'—') ?></strong></td>
            <td style="font-size:12px;"><?= sanitize($t['guichetier']??'—') ?></td>
            <td style="font-size:11px;"><?= date('H:i',strtotime($t['date_vente'])) ?></td>
            <td><?= ($t['type_vente']??'')==='Libre'?'<span class="badge badge-amber">Libre</span>':'<span class="badge badge-blue">Voyage</span>' ?></td>
            <td><?= $estTransit ? '<span class="badge badge-purple" style="font-size:10px;">Transit</span>' : '<span style="color:var(--text3);font-size:10px;">—</span>' ?></td>
          </tr>
          <?php endforeach; ?>
          <?php if(empty($soldAvecBord)): ?><tr><td colspan="12" class="t-empty">Aucun ticket avec bordereau</td></tr><?php endif; ?>
        </tbody>
      </table>
      </div>
      </div>
    </form>
  </div>
</div>

<?php if(!empty($annul)): ?>
<!-- TICKETS ANNULÉS -->
<div class="card" style="margin-bottom:18px;opacity:.65;">
  <div class="card-header"><h3><i class="fas fa-times-circle"></i> Tickets annulés (<?= count($annul) ?>)</h3></div>
  <div class="card-body" style="padding:0;">
    <table data-no-filter>
      <thead><tr><th>N° Ticket</th><th>Passager</th><th>Siège</th><th>Classe</th><th>Montant</th><th>Guichetier</th><th>Heure</th></tr></thead>
      <tbody>
        <?php foreach($annul as $t): ?>
        <tr style="text-decoration:line-through;opacity:.6;">
          <td><code style="font-size:11px;"><?= sanitize($t['numero']) ?></code></td>
          <td><strong><?= sanitize($t['passager_nom']) ?></strong></td>
          <td style="text-align:center;"><?= sanitize($t['siege']??'—') ?></td>
          <td><span class="badge badge-gray"><?= strtoupper($t['classe']) ?></span></td>
          <td style="font-weight:600;"><?= number_format($t['montant_total'],0,',',' ') ?></td>
          <td style="font-size:12px;"><?= sanitize($t['guichetier']??'—') ?></td>
          <td style="font-size:11px;"><?= date('H:i',strtotime($t['date_vente'])) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<!-- BORDEREAUX -->
<?php if(!empty($bords)): ?>
<div class="card">
  <div class="card-header"><h3><i class="fas fa-file-invoice"></i> Bordereaux associés</h3></div>
  <div class="card-body" style="padding:0;">
    <table data-no-filter>
      <thead><tr><th>Numéro</th><th>Type</th><th>Recette nette</th><th>Statut</th><th>Actions</th></tr></thead>
      <tbody>
        <?php foreach($bords as $brd): ?>
        <tr>
          <td><code style="font-size:11px;"><?= sanitize($brd['numero']) ?></code></td>
          <td><span class="badge badge-blue"><?= sanitize($brd['type']) ?></span></td>
          <td style="font-weight:700;color:var(--success);"><?= number_format($brd['recette_nette'],0,',',' ') ?></td>
          <td><span class="tag-statut st-<?= $brd['statut'] ?>"><?= statutLabel($brd['statut']) ?></span></td>
          <td><div style="display:flex;gap:3px;"><a href="../bordereaux/voir.php?id=<?= $brd['id'] ?>" class="btn btn-xs btn-primary"><i class="fas fa-eye"></i></a><a href="../bordereaux/imprimer.php?id=<?= $brd['id'] ?>" class="btn btn-xs btn-info"><i class="fas fa-print"></i></a></div></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<!-- MODAL AJOUTER PASSAGER -->
<?php if($v['statut']==='programme' && can('tickets.create')): ?>
<div class="modal-over" id="modalPassager"><div class="modal modal-sm">
  <div class="modal-head"><h3><i class="fas fa-user-plus"></i> Ajouter un passager</h3><button class="modal-x" onclick="closeModal('modalPassager')">✕</button></div>
  <form method="POST">
    <?= csrfField() ?>
    <input type="hidden" name="action" value="ajouter_passager">
    <div class="modal-body">
      <!-- Trajet (lecture seule) -->
      <div style="background:var(--bg);border-radius:var(--radius);padding:10px 12px;margin-bottom:14px;font-size:13px;">
        <div style="font-weight:600;margin-bottom:4px;"><i class="fas fa-route"></i> <?= sanitize($v['dep']) ?> → <?= sanitize($v['arr']) ?></div>
        <div style="font-size:11px;color:var(--text3);">Voyage n° <?= sanitize($v['numero']) ?></div>
      </div>
      <div class="form-grid">
        <div class="fg" style="grid-column:1 / -1;">
          <label class="flbl">Nom du passager <span class="freq">*</span></label>
          <input type="text" name="passager_nom" class="fc" placeholder="NOM Prénom" required>
        </div>
        <div class="fg">
          <label class="flbl">Type <span class="freq">*</span></label>
          <select name="type_passager" class="fc">
            <option value="adulte">Adulte</option>
            <option value="enfant">Enfant</option>
          </select>
        </div>
        <div class="fg">
          <label class="flbl">Siège</label>
          <select name="siege" class="fc">
            <option value="">— Auto —</option>
            <?php for($s=1;$s<=$v['places_dispo'];$s++): if(!in_array((string)$s,$siegesOccupes)): ?>
            <option value="<?= $s ?>"><?= $s ?></option>
            <?php endif; endfor; ?>
          </select>
        </div>
        <div class="fg">
          <label class="flbl">Téléphone</label>
          <input type="tel" name="passager_tel" class="fc" placeholder="6XX XXX XXX">
        </div>
        <div class="fg">
          <label class="flbl">CNI / NIN</label>
          <input type="text" name="passager_cni" class="fc" placeholder="Numéro pièce">
        </div>
        <div class="fg">
          <label class="flbl">Classe <span class="freq">*</span></label>
          <select name="classe" class="fc" id="classe-sel" onchange="updateMontant()" required>
            <?php foreach($tarifsDispo as $tr): ?>
            <option value="<?= $tr['classe'] ?>" data-montant="<?= (int)$tr['prix'] ?>" data-tarif="<?= $tr['id'] ?>"><?= strtoupper($tr['classe']) ?> — <?= money($tr['prix']) ?></option>
            <?php endforeach; ?>
            <?php if(empty($tarifsDispo)): ?>
            <option value="cla" data-montant="3000" data-tarif="0">CLASSIQUE — 3 000 FCFA</option>
            <option value="vip" data-montant="5000" data-tarif="0">VIP — 5 000 FCFA</option>
            <?php endif; ?>
          </select>
          <input type="hidden" name="tarif_id" id="tarif-id" value="<?= $tarifsDispo[0]['id'] ?? 0 ?>">
        </div>
        <div class="fg">
          <label class="flbl">Montant (FCFA) <span class="freq">*</span></label>
          <input type="number" name="montant" id="montant-inp" class="fc" min="0" step="100" value="<?= (int)($tarifsDispo[0]['prix'] ?? 3000) ?>" required>
        </div>
        <div class="fg">
          <label class="flbl">Bagages (kg)</label>
          <input type="number" name="bagages_kg" class="fc" min="0" step="1" value="0" placeholder="0">
        </div>
        <div class="fg">
          <label class="flbl">Montant bagages</label>
          <input type="number" name="montant_bagages" class="fc" min="0" step="100" value="0" placeholder="0">
        </div>
        <div class="fg">
          <label class="flbl">Mode de paiement <span class="freq">*</span></label>
          <select name="mode_paiement" class="fc" required>
            <option value="especes">Espèces</option>
            <option value="om">Orange Money</option>
            <option value="momo">MTN MoMo</option>
            <option value="carte">Carte</option>
          </select>
        </div>
      </div>
    </div>
    <div class="modal-foot">
      <button type="button" class="btn btn-secondary" onclick="closeModal('modalPassager')">Annuler</button>
      <button type="submit" class="btn btn-success"><i class="fas fa-check"></i> Créer le ticket</button>
    </div>
  </form>
</div></div>
<?php endif; ?>

<script>
function switchTab(tabId, el) {
  document.querySelectorAll('.tab-panel').forEach(function(p){ p.classList.remove('active'); });
  document.querySelectorAll('.tab-link').forEach(function(l){ l.classList.remove('active'); });
  document.getElementById(tabId).classList.add('active');
  if (el) el.classList.add('active');
  document.getElementById('act-sans').style.display = tabId === 'tab-sans' ? 'flex' : 'none';
  document.getElementById('act-avec').style.display = tabId === 'tab-avec' ? 'flex' : 'none';
  document.getElementById('act-transit').style.display = tabId === 'tab-transit' ? 'flex' : 'none';
}

<?php if($v['statut']==='programme' && can('tickets.create')): ?>
function updateMontant() {
  var sel = document.getElementById('classe-sel');
  var opt = sel.options[sel.selectedIndex];
  document.getElementById('montant-inp').value = opt.dataset.montant || 3000;
  document.getElementById('tarif-id').value = opt.dataset.tarif || 0;
}
<?php endif; ?>

function updateSel() {
  var cbs = document.querySelectorAll('.sel-cb');
  var count = 0, total = 0;
  cbs.forEach(function(cb){ if(cb.checked){ count++; total += parseFloat(cb.dataset.montant||0); }});
  document.getElementById('sel-count').textContent = count;
  document.getElementById('sel-total').textContent = new Intl.NumberFormat('fr-FR').format(total);
  document.getElementById('sel-all').checked = cbs.length > 0 && count === cbs.length;
  document.getElementById('brd-btn').disabled = count === 0;
}

function toggleSel(state) {
  document.querySelectorAll('.sel-cb').forEach(function(cb){ cb.checked = state; });
  updateSel();
}

function genererBordereau() {
  var cbs = document.querySelectorAll('.sel-cb:checked');
  if (!cbs.length) return;
  document.getElementById('brd-form').submit();
}

function updateDissocier() {
  var cbs = document.querySelectorAll('.dissocier-cb');
  var count = 0;
  cbs.forEach(function(cb){ if(cb.checked) count++; });
  document.getElementById('dissocier-count').textContent = count;
  document.getElementById('dissocier-all').checked = cbs.length > 0 && count === cbs.length;
  document.getElementById('dissocier-btn').disabled = count === 0;
  document.getElementById('transiter-btn').disabled = count === 0;
}

function toggleDissocier(state) {
  document.querySelectorAll('.dissocier-cb').forEach(function(cb){ cb.checked = state; });
  updateDissocier();
}

function dissocierTickets() {
  var cbs = document.querySelectorAll('.dissocier-cb:checked');
  if (!cbs.length) return;
  if (!confirm('Dissocier les tickets sélectionnés du bordereau ?')) return;
  document.getElementById('dissocier-form').submit();
}

function transiterTickets() {
  var cbs = document.querySelectorAll('.dissocier-cb:checked');
  if (!cbs.length) return;
  if (!confirm('Transférer les tickets sélectionnés vers le bordereau transit ?')) return;
  document.querySelector('#dissocier-form [name=action]').value = 'transiter';
  document.getElementById('dissocier-form').submit();
}

function updateTransitSel() {
  var cbs = document.querySelectorAll('.transit-cb');
  var count = 0, total = 0;
  cbs.forEach(function(cb){ if(cb.checked){ count++; total += parseFloat(cb.dataset.montant||0); }});
  document.getElementById('transit-count').textContent = count;
  document.getElementById('transit-total').textContent = new Intl.NumberFormat('fr-FR').format(total);
  document.getElementById('transit-all').checked = cbs.length > 0 && count === cbs.length;
  document.getElementById('transit-btn').disabled = count === 0;
}

function toggleTransitSel(state) {
  document.querySelectorAll('.transit-cb').forEach(function(cb){ cb.checked = state; });
  updateTransitSel();
}

function genererTransitBordereau() {
  var cbs = document.querySelectorAll('.transit-cb:checked');
  if (!cbs.length) return;
  document.querySelector('#brd-form [name=type]').value = 'transit';
  document.getElementById('brd-form').submit();
}
</script>

<?php include 'depart_modal.php'; ?>
<?php include '../../includes/footer.php'; ?>
