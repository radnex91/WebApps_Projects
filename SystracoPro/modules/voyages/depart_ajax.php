<?php
// modules/voyages/depart_ajax.php
require_once '../../includes/config.php';
requireLogin();

header('Content-Type: application/json; charset=utf-8');
ob_clean();

$action = $_GET['action'] ?? '';
$aid = getUserAgenceId();

// ── ACTION: Charger les données du voyage ──
if ($action === 'load' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $vid = (int)($_GET['voyage_id'] ?? 0);
    if (!$vid) { echo json_encode(['error' => 'Voyage requis']); exit; }

    // Voyage
    $sv = $pdo->prepare("SELECT v.*,a1.ville as dep,a1.nom as dep_nom,a2.ville as arr,a2.nom as arr_nom,
        veh.immatriculation,veh.marque,veh.capacite,
        CONCAT(p.prenom,' ',p.nom) as chauf_nom,p.permis as chauf_permis,
        v.convoyeur_nom,v.chef_depiste,
        (SELECT COUNT(DISTINCT t.id) FROM tickets t WHERE (t.voyage_id=v.id OR t.bordereau_id IN (SELECT id FROM bordereaux WHERE voyage_id=v.id)) AND t.statut IN ('vendu','utilise')) as nb_tks
        FROM voyages v
        LEFT JOIN itineraires i ON v.itineraire_id=i.id
        LEFT JOIN destinations d ON v.destination_id=d.id
        LEFT JOIN agences a1 ON IFNULL(i.agence_depart,d.agence_depart)=a1.id
        LEFT JOIN agences a2 ON IFNULL(i.agence_arrivee,d.agence_arrivee)=a2.id
        LEFT JOIN vehicules veh ON v.vehicule_id=veh.id
        LEFT JOIN personnel p ON v.chauffeur_id=p.id
        WHERE v.id=?");
    $sv->execute([$vid]);
    $voyage = $sv->fetch();
    if (!$voyage) { echo json_encode(['error' => 'Voyage introuvable']); exit; }

    $agId = $aid ?? $voyage['agence_id'];

    // Tickets vendus SANS bordereau
    $sansBord = $pdo->prepare("SELECT t.*,aa.ville as dest_ville,
        IF(t.voyage_id IS NULL,'Libre','Rattaché') as type_vente,
        CONCAT(u.prenom,' ',u.nom) as guichetier
        FROM tickets t
        LEFT JOIN agences aa ON t.agence_arrivee_id=aa.id
        LEFT JOIN utilisateurs u ON t.guichetier_id=u.id
        WHERE t.statut='vendu'
        AND t.bordereau_id IS NULL
        AND (t.voyage_id=? OR (t.voyage_id IS NULL AND t.agence_id=?))
        ORDER BY t.voyage_id IS NULL, t.siege");
    $sansBord->execute([$vid, $agId]);
    $ticketsSansBord = $sansBord->fetchAll(PDO::FETCH_ASSOC);

    // Tickets AVEC bordereau (pour ce voyage)
    $avecBord = $pdo->prepare("SELECT t.*,aa.ville as dest_ville,b.numero as brd_numero,b.statut as brd_statut,
        IF(t.voyage_id IS NULL,'Libre','Rattaché') as type_vente,
        CONCAT(u.prenom,' ',u.nom) as guichetier
        FROM tickets t
        LEFT JOIN agences aa ON t.agence_arrivee_id=aa.id
        LEFT JOIN utilisateurs u ON t.guichetier_id=u.id
        LEFT JOIN bordereaux b ON t.bordereau_id=b.id
        WHERE t.statut IN ('vendu','utilise')
        AND t.bordereau_id IS NOT NULL
        AND t.bordereau_id IN (SELECT id FROM bordereaux WHERE voyage_id=?)
        ORDER BY t.siege");
    $avecBord->execute([$vid]);
    $ticketsAvecBord = $avecBord->fetchAll(PDO::FETCH_ASSOC);

    // Bordereaux existants
    $bordereaux = $pdo->prepare("SELECT * FROM bordereaux WHERE voyage_id=? ORDER BY created_at DESC");
    $bordereaux->execute([$vid]);
    $bordereaux = $bordereaux->fetchAll(PDO::FETCH_ASSOC);

    // Escales du trajet
    $escales = [];
    $routeVilles = [];
    if ($voyage['itineraire_id']) {
        $es = $pdo->prepare("SELECT a.id,a.ville,a.nom FROM itineraire_escales e JOIN agences a ON e.agence_id=a.id WHERE e.itineraire_id=? ORDER BY e.ordre");
        $es->execute([$voyage['itineraire_id']]);
        $escales = $es->fetchAll();
    }
    $routeVilles = array_unique(array_filter(array_merge(
        [mb_strtolower(trim($voyage['dep']??'')), mb_strtolower(trim($voyage['arr']??''))],
        array_map(function($e){ return mb_strtolower(trim($e['ville']??$e['nom']??'')); }, $escales)
    )));

    echo json_encode([
        'success' => true,
        'voyage' => $voyage,
        'tickets_sans_bord' => $ticketsSansBord,
        'tickets_avec_bord' => $ticketsAvecBord,
        'bordereaux' => $bordereaux,
        'escales' => $escales,
        'route_villes' => array_values($routeVilles),
        'can_validate' => can('bordereaux.create') && can('voyages.create'),
        'can_print' => can('bordereaux.print'),
        'can_dissocier' => can('bordereaux.create')
    ]);
    exit;
}

// ── ACTION: Valider le départ ──
if ($action === 'valider' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF check via header (config.php auto-check is skipped for JSON)
    if (!csrfCheck()) {
        echo json_encode(['error' => 'Session expirée. Veuillez réessayer.']); exit;
    }

    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (!$data) { echo json_encode(['error' => 'Données JSON invalides']); exit; }

    $vid = (int)($data['voyage_id'] ?? 0);
    $type = $data['type'] ?? 'chauffeur';
    $carb = (float)($data['montant_carburant'] ?? 0);
    $peage = (float)($data['montant_peage'] ?? 0);
    $avance = (float)($data['avance_chauffeur'] ?? 0);
    $autres = (float)($data['autres_deductions'] ?? 0);
    $obs = trim($data['observations'] ?? '');
    $ticket_ids = array_map('intval', $data['ticket_ids'] ?? []);

    if (empty($ticket_ids)) { echo json_encode(['error' => 'Sélectionnez au moins un ticket.']); exit; }
    if (!can('bordereaux.create') || !can('voyages.create')) {
        echo json_encode(['error' => 'Permission insuffisante.']); exit;
    }

    // Charger le voyage
    $sv = $pdo->prepare("SELECT v.*,a1.ville as dep,a1.nom as dep_nom,a2.ville as arr,a2.nom as arr_nom,
        veh.immatriculation,CONCAT(p.prenom,' ',p.nom) as chauf_full,p.permis as chauf_permis,
        v.convoyeur_nom
        FROM voyages v
        LEFT JOIN itineraires i ON v.itineraire_id=i.id
        LEFT JOIN destinations d ON v.destination_id=d.id
        JOIN agences a1 ON IFNULL(i.agence_depart,d.agence_depart)=a1.id
        JOIN agences a2 ON IFNULL(i.agence_arrivee,d.agence_arrivee)=a2.id
        LEFT JOIN vehicules veh ON v.vehicule_id=veh.id
        LEFT JOIN personnel p ON v.chauffeur_id=p.id
        WHERE v.id=?");
    $sv->execute([$vid]);
    $svData = $sv->fetch();
    if (!$svData) { echo json_encode(['error' => 'Voyage introuvable.']); exit; }

    // Vérifier que le voyage n'est pas déjà en_cours
    if (!in_array($svData['statut'], ['programme'])) {
        echo json_encode(['error' => 'Ce voyage ne peut pas être validé (statut: '.$svData['statut'].').']); exit;
    }

    // Valider les tickets sélectionnés
    $in = implode(',', $ticket_ids);
    $ts = $pdo->query("SELECT SUM(montant_total) as r, COUNT(*) as nb FROM tickets WHERE id IN ($in) AND statut='vendu' AND bordereau_id IS NULL")->fetch();
    $nb_pass = (int)($ts['nb'] ?? 0);
    $recette = (float)($ts['r'] ?? 0);

    if ($nb_pass !== count($ticket_ids)) {
        echo json_encode(['error' => 'Certains tickets ne sont plus disponibles.']); exit;
    }

    $nette = $recette - $carb - $peage - $avance - $autres;
    $agenceId = $aid ?? $svData['agence_id'];
    $itin_id = (int)($svData['itineraire_id'] ?? 0);

    $pdo->beginTransaction();
    try {
        // 1. Créer le bordereau directement en_cours
        $num = genNumero($pdo, 'bordereaux', 'numero', getParam('prefix_bordereau', 'BRD'));
        $pdo->prepare("INSERT INTO bordereaux (numero,voyage_id,agence_id,type,vehicule_immat,chauffeur_nom,chauffeur_permis,convoyeur_nom,agence_depart,agence_arrivee,date_depart,nb_passagers,recette_brute,montant_carburant,montant_peage,avance_chauffeur,autres_deductions,recette_nette,statut,created_by,valide_par,date_validation,observations) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),?)")
            ->execute([$num,$vid,$agenceId,$type,$svData['immatriculation'],$svData['chauf_full'],$svData['chauf_permis'],$svData['convoyeur_nom']??'',$svData['dep_nom'],$svData['arr_nom'],$svData['date_depart'],$nb_pass,$recette,$carb,$peage,$avance,$autres,$nette,'en_cours',$_SESSION['user_id'],$_SESSION['user_id'],$obs]);
        $brd_id = $pdo->lastInsertId();

        // 2. Ajouter les lignes du bordereau + update tickets
        $tkts = $pdo->query("SELECT t.*,IFNULL(aa.ville,a2.ville) as dest FROM tickets t LEFT JOIN voyages vv ON t.voyage_id=vv.id LEFT JOIN itineraires i ON vv.itineraire_id=i.id LEFT JOIN destinations d ON vv.destination_id=d.id LEFT JOIN agences a2 ON IFNULL(i.agence_arrivee,d.agence_arrivee)=a2.id LEFT JOIN agences aa ON t.agence_arrivee_id=aa.id WHERE t.id IN ($in) ORDER BY t.siege");
        foreach ($tkts->fetchAll() as $tk) {
            $pdo->prepare("INSERT INTO bordereau_lignes (bordereau_id,ticket_id,passager_nom,siege,destination,montant,classe) VALUES (?,?,?,?,?,?,?)")
                ->execute([$brd_id,$tk['id'],$tk['passager_nom'],$tk['siege'],$tk['dest'],$tk['montant_total'],$tk['classe']]);
            $pdo->prepare("UPDATE tickets SET bordereau_id=?, statut='utilise' WHERE id=?")
                ->execute([$brd_id,$tk['id']]);
        }

        // 3. Mettre le voyage en_cours
        $pdo->prepare("UPDATE voyages SET statut='en_cours' WHERE id=? AND statut='programme'")->execute([$vid]);

        // 4. Créer les escales depuis l'itinéraire
        if ($itin_id) {
            $itEscales = $pdo->prepare("SELECT ie.*,a.nom,a.ville FROM itineraire_escales ie JOIN agences a ON ie.agence_id=a.id WHERE ie.itineraire_id=? ORDER BY ie.ordre");
            $itEscales->execute([$itin_id]);
            $itEscales = $itEscales->fetchAll(PDO::FETCH_ASSOC);

            if (empty($itEscales)) {
                $pdo->prepare("INSERT INTO bordereau_escales (bordereau_id,agence_id,ordre,statut,confirme_par,date_confirmation) VALUES (?,?,?,'confirme',?,NOW())")
                    ->execute([$brd_id, $agenceId, 1, $_SESSION['user_id']]);
                $pdo->prepare("UPDATE bordereaux SET statut='cloture' WHERE id=?")->execute([$brd_id]);
            } else {
                foreach ($itEscales as $i => $esc) {
                    if ($i === 0) {
                        $pdo->prepare("INSERT INTO bordereau_escales (bordereau_id,agence_id,ordre,statut,confirme_par,date_confirmation) VALUES (?,?,?,'confirme',?,NOW())")
                            ->execute([$brd_id, $esc['agence_id'], $esc['ordre'], $_SESSION['user_id']]);
                    } else {
                        $pdo->prepare("INSERT INTO bordereau_escales (bordereau_id,agence_id,ordre,statut) VALUES (?,?,?,'en_attente')")
                            ->execute([$brd_id, $esc['agence_id'], $esc['ordre']]);
                    }
                }
                $nextEsc = $itEscales[1] ?? null;
                if ($nextEsc) {
                    addNotif($pdo, null, $nextEsc['agence_id'], 'bordereau', 'Bordereau validé', "Le bordereau $num est en route vers votre agence.", BASE_URL."modules/bordereaux/valider.php?id=$brd_id");
                }
            }
        } else {
            $pdo->prepare("INSERT INTO bordereau_escales (bordereau_id,agence_id,ordre,statut,confirme_par,date_confirmation) VALUES (?,?,?,'confirme',?,NOW())")
                ->execute([$brd_id, $agenceId, 1, $_SESSION['user_id']]);
            $pdo->prepare("UPDATE bordereaux SET statut='cloture' WHERE id=?")->execute([$brd_id]);
        }

        // 5. Nettoyer les bordereaux genere restants (s'il y en avait) : les cloturer
        $pdo->prepare("UPDATE bordereaux SET statut='cloture', observations=CONCAT(COALESCE(observations,''),' | Remplacé par ',?) WHERE voyage_id=? AND statut='genere' AND id!=?")->execute([$num, $vid, $brd_id]);

        logAction($pdo, 'valider_depart', 'voyages', "Départ voyage {$svData['numero']} — Bordereau $num — $nb_pass tickets");
        $pdo->commit();

        echo json_encode([
            'success' => true,
            'bordereau_id' => $brd_id,
            'bordereau_numero' => $num,
            'message' => "Départ validé — Bordereau $num créé avec $nb_pass passagers."
        ]);
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log('Erreur valider_depart: ' . $e->getMessage());
        echo json_encode(['error' => 'Une erreur est survenue lors de la validation du départ. Veuillez réessayer.']);
    }
    exit;
}

// Action inconnue
echo json_encode(['error' => 'Action inconnue']);
