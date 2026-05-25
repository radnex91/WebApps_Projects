<?php
// modules/tickets/vente.php — API AJAX pour vente de tickets (avec escales + correspondances)
require_once '../../includes/config.php';

// Les endpoints AJAX doivent renvoyer du JSON, pas une redirection
if (isset($_GET['ajax']) || (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')) {
    if (!isLoggedIn()) { http_response_code(401); header('Content-Type: application/json'); echo json_encode(['error'=>'Session expirée']); exit; }
    if (($_GET['ajax'] ?? '') === 'passager' && !can('tickets.create')) { http_response_code(403); header('Content-Type: application/json'); echo json_encode(['error'=>'Permission insuffisante']); exit; }
} else {
    requireLogin(); requirePerm('tickets.create');
    // Redirect to liste.php — vente is now done via modals
    redirect(BASE_URL.'modules/tickets/liste.php');
}

$aid = getUserAgenceId();
$today = date('Y-m-d');

// ── AJAX: destinations + tarifs (pour vente libre) ────────
if (isset($_GET['ajax']) && $_GET['ajax'] === 'destinations_libres') {
    header('Content-Type: application/json'); ob_clean();
    $wAg = $aid ? "AND d.agence_depart=".intval($aid) : "";
    $dests = $pdo->query("SELECT d.id,d.distance_km,d.duree_minutes,d.agence_depart,d.agence_arrivee,a1.ville as dep,a2.ville as arr FROM destinations d JOIN agences a1 ON d.agence_depart=a1.id JOIN agences a2 ON d.agence_arrivee=a2.id WHERE d.actif=1 $wAg ORDER BY a1.ville,a2.ville")->fetchAll(PDO::FETCH_ASSOC);
    $dids = array_column($dests, 'id');
    $tarifs = [];
    if ($dids) {
        $in = implode(',', $dids);
        $ts = $pdo->query("SELECT t.*,a2.ville as arr_ville FROM tarifs t JOIN destinations d ON t.destination_id=d.id JOIN agences a2 ON d.agence_arrivee=a2.id WHERE t.destination_id IN ($in) AND t.actif=1 ORDER BY t.destination_id, t.classe")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($ts as $t) { $tarifs[$t['destination_id']][] = $t; }
    }
    echo json_encode(['destinations' => $dests, 'tarifs' => $tarifs]);
    exit;
}

// ── AJAX: liste des voyages ──────────────────────────────
if (isset($_GET['ajax']) && $_GET['ajax'] === 'voyages') {
    header('Content-Type: application/json'); ob_clean();
    $wA = $aid ? "AND v.agence_id=$aid" : "";
    $voyages = $pdo->query("SELECT v.id,v.numero,v.date_depart,v.statut,v.places_dispo,veh.immatriculation,i.nom as itineraire_nom,i.code as itineraire_code,IFNULL(i.agence_depart,d.agence_depart) as dep_agence,IFNULL(i.agence_arrivee,d.agence_arrivee) as arr_agence,a1.ville as dep,a2.ville as arr,(SELECT COUNT(*) FROM tickets t WHERE t.voyage_id=v.id AND t.statut IN ('vendu','reserve')) as places_prises FROM voyages v LEFT JOIN itineraires i ON v.itineraire_id=i.id LEFT JOIN destinations d ON v.destination_id=d.id JOIN agences a1 ON IFNULL(i.agence_depart,d.agence_depart)=a1.id JOIN agences a2 ON IFNULL(i.agence_arrivee,d.agence_arrivee)=a2.id LEFT JOIN vehicules veh ON v.vehicule_id=veh.id WHERE v.statut='programme' AND v.date_depart>=NOW() $wA ORDER BY v.date_depart LIMIT 30")->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($voyages);
    exit;
}

// ── AJAX: détail voyage + escales + tarifs + correspondances ──
if (isset($_GET['ajax']) && $_GET['ajax'] === 'voyage_detail') {
    header('Content-Type: application/json'); ob_clean();
    $vid = (int)($_GET['voyage_id'] ?? 0);
    if (!$vid) { echo json_encode(['error'=>'voyage_id requis']); exit; }

    $s=$pdo->prepare("SELECT v.*,IFNULL(i.agence_depart,d.agence_depart) as dep_agence_id,IFNULL(i.agence_arrivee,d.agence_arrivee) as arr_agence_id,a1.ville as dep,a1.id as dep_id,a2.ville as arr,a2.id as arr_id,IFNULL(i.id,0) as itineraire_id,veh.immatriculation,CONCAT(p.prenom,' ',p.nom) as chauffeur_nom FROM voyages v LEFT JOIN itineraires i ON v.itineraire_id=i.id LEFT JOIN destinations d ON v.destination_id=d.id JOIN agences a1 ON IFNULL(i.agence_depart,d.agence_depart)=a1.id JOIN agences a2 ON IFNULL(i.agence_arrivee,d.agence_arrivee)=a2.id LEFT JOIN vehicules veh ON v.vehicule_id=veh.id LEFT JOIN personnel p ON v.chauffeur_id=p.id WHERE v.id=?");
    $s->execute([$vid]); $voyage=$s->fetch(PDO::FETCH_ASSOC);

    if (!$voyage) { echo json_encode(['error'=>'Voyage introuvable']); exit; }

    // Escales de l'itinéraire
    $escales = [];
    if ($voyage['itineraire_id']) {
        $es=$pdo->prepare("SELECT e.*, a.ville FROM itineraire_escales e JOIN agences a ON e.agence_id=a.id WHERE e.itineraire_id=? ORDER BY e.ordre");
        $es->execute([$voyage['itineraire_id']]);
        $escales=$es->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $escales = [
            ['id'=>0,'agence_id'=>$voyage['dep_id'],'ville'=>$voyage['dep'],'ordre'=>1,'distance_debut'=>0,'duree_debut'=>0],
            ['id'=>0,'agence_id'=>$voyage['arr_id'],'ville'=>$voyage['arr'],'ordre'=>2,'distance_debut'=>$voyage['distance_km']??0,'duree_debut'=>$voyage['duree_minutes']??0],
        ];
    }

    // Tarifs (tronçons si itinéraire)
    $tarifs = [];
    $itinId = $voyage['itineraire_id'];
    if ($itinId) {
        $ts=$pdo->prepare("SELECT t.*, a1.ville as esc_dep_ville, a2.ville as esc_arr_ville FROM tarifs t LEFT JOIN itineraire_escales a1 ON t.escale_depart_id=a1.id LEFT JOIN itineraire_escales a2 ON t.escale_arrivee_id=a2.id WHERE t.itineraire_id=? AND t.actif=1 ORDER BY a1.ordre, t.classe");
        $ts->execute([$itinId]);
        $tarifs=$ts->fetchAll(PDO::FETCH_ASSOC);
    }
    if (empty($tarifs)) {
        $ts=$pdo->prepare("SELECT t.*,a2.ville as dest_arr FROM tarifs t JOIN destinations d ON t.destination_id=d.id JOIN agences a2 ON d.agence_arrivee=a2.id WHERE d.agence_depart=? AND t.actif=1 ORDER BY t.classe");
        $ts->execute([$voyage['dep_id']]); $tarifs=$ts->fetchAll(PDO::FETCH_ASSOC);
    }

    // Correspondances possibles à chaque escale
    $correspondances = [];
    if ($itinId) {
        $cs=$pdo->prepare("SELECT c.*, i2.nom as depart_nom, i2.code as depart_code, a.ville as agence_ville FROM correspondances c JOIN itineraires i2 ON c.itineraire_depart_id=i2.id JOIN agences a ON c.agence_id=a.id WHERE c.itineraire_arrivee_id=? AND c.actif=1");
        $cs->execute([$itinId]);
        $correspondances=$cs->fetchAll(PDO::FETCH_ASSOC);
    }

    // Places prises
    $pp=$pdo->prepare("SELECT COUNT(*) FROM tickets WHERE voyage_id=? AND statut IN ('vendu','reserve')");
    $pp->execute([$vid]); $voyage['places_prises']=(int)$pp->fetchColumn();

    echo json_encode(['voyage'=>$voyage,'escales'=>$escales,'tarifs'=>$tarifs,'correspondances'=>$correspondances]);
    exit;
}

// ── AJAX: recherche passager par téléphone ou nom ──────────
if (isset($_GET['ajax']) && $_GET['ajax'] === 'passager') {
    header('Content-Type: application/json'); ob_clean();
    $tel = trim($_GET['tel'] ?? '');
    if (!$tel) { echo json_encode(null); exit; }
    $s = $pdo->prepare("SELECT id, nom, prenom, telephone, cni FROM passagers WHERE telephone LIKE ? OR nom LIKE ? OR prenom LIKE ? ORDER BY id DESC LIMIT 10");
    $s->execute(["%$tel%","%$tel%","%$tel%"]);
    $rows = $s->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($rows ?: null);
    exit;
}

// ── Vérifier caisse ouverte (guichetier) ─────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['vendre']) && isGuichetier()) {
    if (!caisseOuverte()) {
        ob_clean(); header('Content-Type: application/json');
        echo json_encode(['success'=>false,'error'=>'Aucune caisse ouverte. Veuillez ouvrir votre caisse avant de vendre.','caisse_required'=>true,'caisse_url'=>BASE_URL.'modules/caisse/index.php']);
        exit;
    }
}

// ── VENTE (POST) — renvoie JSON ─────────────────────────────
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['vendre'])) {
    ob_start();
    $vid     = (int)($_POST['voyage_id'] ?? 0);
    $isLibre = isset($_POST['vente_libre']) && $_POST['vente_libre'] === '1';
    $nom     = mb_strtoupper(trim($_POST['passager_nom'] ?? ''));
    $tel     = trim($_POST['passager_tel'] ?? '');
    $cni     = trim($_POST['passager_cni'] ?? '');
    $siege   = $isLibre ? '' : trim($_POST['siege'] ?? '');
    $classe  = $_POST['classe'] ?? 'cla';
    $tarif_id= $isLibre ? null : ((int)($_POST['tarif_id'] ?? 0) ?: null);
    $montant = (float)($_POST['montant'] ?? 0);
    $bag_kg  = (float)($_POST['bagages_kg'] ?? 0);
    $bag_m   = (float)($_POST['bagages_montant'] ?? 0);
    $total   = $montant + $bag_m;
    $mode    = $_POST['mode_paiement'] ?? 'especes';
    $type_passager = $_POST['type_passager'] ?? 'adulte';
    $somme_percu  = (float)($_POST['somme_percu'] ?? 0);
    $reliquat     = $somme_percu - $total;
    $observation  = trim($_POST['observation'] ?? '');
    $date_heure   = !empty($_POST['date_heure']) ? $_POST['date_heure'] : null;
    $transit = $isLibre ? 0 : (isset($_POST['transit']) ? 1 : 0);
    $escale_montee  = $isLibre ? null : ((int)($_POST['escale_montee_id'] ?? 0) ?: null);
    $escale_descente = $isLibre ? null : ((int)($_POST['escale_descente_id'] ?? 0) ?: null);
    $itineraire_suite = $isLibre ? null : ((int)($_POST['itineraire_suite_id'] ?? 0) ?: null);
    $corr_statut = ($itineraire_suite && !$isLibre) ? 'en_attente' : null;

    // Vente libre : destination + tarif
    $destination_id = $isLibre ? (int)($_POST['destination_id'] ?? 0) : 0;
    $tarif_id = $isLibre ? ((int)($_POST['tarif_id'] ?? 0) ?: null) : $tarif_id;
    $agence_depart_id = 0; $agence_arrivee_id = 0;

    if ($isLibre && $destination_id) {
        $dd = $pdo->prepare("SELECT agence_depart,agence_arrivee FROM destinations WHERE id=?");
        $dd->execute([$destination_id]);
        if ($dRow = $dd->fetch()) {
            $agence_depart_id = $dRow['agence_depart'];
            $agence_arrivee_id = $dRow['agence_arrivee'];
        }
    }

    if (!$nom || $montant <= 0) {
        ob_clean(); header('Content-Type: application/json'); echo json_encode(['success'=>false,'error'=>'Nom passager et montant obligatoires.']); exit;
    }
    if (!$isLibre && !$vid) {
        ob_clean(); header('Content-Type: application/json'); echo json_encode(['success'=>false,'error'=>'Voyage requis pour vente associée.']); exit;
    }
    if ($isLibre && !$destination_id) {
        ob_clean(); header('Content-Type: application/json'); echo json_encode(['success'=>false,'error'=>'Destination requise.']); exit;
    }
    if ($isLibre && !$agence_depart_id) {
        ob_clean(); header('Content-Type: application/json'); echo json_encode(['success'=>false,'error'=>'Destination introuvable.']); exit;
    }

    try {
        $vAgence = null;
        if (!$isLibre) {
            $sv=$pdo->prepare("SELECT agence_id FROM voyages WHERE id=?"); $sv->execute([$vid]); $vAgence=$sv->fetchColumn();
        }
        $ticketAgence = $isLibre ? $agence_depart_id : ($aid ?? $vAgence);

        $pdo->beginTransaction();
        $num = genNumero($pdo, 'tickets', 'numero', 'T', getUserAgenceCode());

        $passager_id = null;
        if ($tel || $cni) {
            $ep = $pdo->prepare("SELECT id, telephone, cni FROM passagers WHERE (telephone=? AND telephone != '') OR (cni=? AND cni != '') LIMIT 1");
            $ep->execute([$tel,$cni]);
            $existing = $ep->fetch(PDO::FETCH_ASSOC);
            if ($existing) {
                $passager_id = $existing['id'];
                $updates = []; $uParams = [];
                if ($tel && !$existing['telephone']) { $updates[] = 'telephone=?'; $uParams[] = $tel; }
                if ($cni && !$existing['cni']) { $updates[] = 'cni=?'; $uParams[] = $cni; }
                if ($updates) { $uParams[] = $passager_id; $pdo->prepare("UPDATE passagers SET ".implode(',',$updates)." WHERE id=?")->execute($uParams); }
            } else {
                $parts = preg_split('/\s+/', trim($nom), 2);
                $pdo->prepare("INSERT INTO passagers (nom,prenom,telephone,cni) VALUES (?,?,?,?)")->execute([$parts[0],$parts[1]??'',$tel,$cni]);
                $passager_id = $pdo->lastInsertId();
            }
        }

        $voyage_id_db = $isLibre ? null : $vid;
        $pdo->prepare("INSERT INTO tickets (numero,voyage_id,passager_id,passager_nom,passager_tel,passager_cni,siege,classe,tarif_id,montant,bagages_kg,montant_bagages,montant_total,statut,mode_paiement,agence_id,guichetier_id,transit,transit_destination,escale_montee_id,escale_descente_id,itineraire_suite_id,correspondance_statut,agence_depart_id,agence_arrivee_id,type_passager,somme_percu,reliquat,observation,date_heure) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,'vendu',?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
            ->execute([$num,$voyage_id_db,$passager_id,$nom,$tel,$cni,$siege,$classe,$tarif_id,$montant,$bag_kg,$bag_m,$total,$mode,$ticketAgence,$_SESSION['user_id'],$transit,$transit_destination=null,$escale_montee,$escale_descente,$itineraire_suite,$corr_statut,$agence_depart_id?:null,$agence_arrivee_id?:null,$type_passager,$somme_percu,$reliquat,$observation,$date_heure]);

        $ticket_id = $pdo->lastInsertId();
        $pdo->commit();
        $logType = $isLibre ? 'vente_ticket_libre' : 'vente_ticket';
        logAction($pdo,$logType,'tickets',"Ticket $num — $nom — ".money($total).($isLibre?' [LIBRE]':'').($transit?' [TRANSIT]':''));

        ob_clean(); header('Content-Type: application/json');
        echo json_encode(['success'=>true,'ticket_id'=>$ticket_id,'numero'=>$num,'montant_total'=>$total,'transit'=>$transit,'vente_libre'=>$isLibre,'print_url'=>BASE_URL."modules/tickets/imprimer.php?id=$ticket_id"]);
        exit;
    } catch(Exception $e) {
        $pdo->rollBack();
        ob_clean(); header('Content-Type: application/json'); echo json_encode(['success'=>false,'error'=>$e->getMessage()]); exit;
    }
}

// If we get here without matching any endpoint, redirect to liste
redirect(BASE_URL.'modules/tickets/liste.php');