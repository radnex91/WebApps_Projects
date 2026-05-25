<?php
// modules/bordereaux/valider.php — Validation, confirmation escale & ajout passagers
require_once '../../includes/config.php';
requireLogin();
if (!can('bordereaux.validate') && !can('tickets.create')) {
    flash('Permission insuffisante.', 'danger');
    redirect(BASE_URL . 'modules/bordereaux/');
}

$id = (int)($_GET['id'] ?? 0);
if (!$id) redirect(BASE_URL . 'modules/bordereaux/');

$aid = getUserAgenceId();
$uid = (int)$_SESSION['user_id'];

// Charger bordereau
$stmt = $pdo->prepare("SELECT b.*,b.parent_id,b.segment_ordre,ag.nom as agence_nom,ag.ville as agence_ville,v.numero as voy_num,v.date_depart,v.itineraire_id,v.vehicule_id,v.chauffeur_id,CONCAT(u.prenom,' ',u.nom) as saisi_nom,CONCAT(uc.prenom,' ',uc.nom) as cree_nom,CONCAT(uv.prenom,' ',uv.nom) as valide_nom FROM bordereaux b LEFT JOIN agences ag ON b.agence_id=ag.id LEFT JOIN voyages v ON b.voyage_id=v.id LEFT JOIN utilisateurs u ON b.saisi_par=u.id LEFT JOIN utilisateurs uc ON b.created_by=uc.id LEFT JOIN utilisateurs uv ON b.valide_par=uv.id WHERE b.id=?");
$stmt->execute([$id]);
$bordereau = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$bordereau || !is_array($bordereau)) {
    flash('Bordereau introuvable.', 'danger');
    redirect(BASE_URL . 'modules/bordereaux/');
}

// Charger escales du bordereau
$escales = $pdo->prepare("SELECT be.*,a.nom as agence_nom,a.ville as agence_ville,CONCAT(u.prenom,' ',u.nom) as confirme_nom FROM bordereau_escales be LEFT JOIN agences a ON be.agence_id=a.id LEFT JOIN utilisateurs u ON be.confirme_par=u.id WHERE be.bordereau_id=? ORDER BY be.ordre");
$escales->execute([$id]);
$escales = $escales->fetchAll(PDO::FETCH_ASSOC);

// Charger lignes du bordereau
$lignes = $pdo->prepare("SELECT bl.*, t.passager_tel, t.passager_cni, t.mode_paiement, t.escale_montee_id, t.escale_descente_id FROM bordereau_lignes bl LEFT JOIN tickets t ON bl.ticket_id=t.id WHERE bl.bordereau_id=? ORDER BY bl.siege");
$lignes->execute([$id]);
$lignes = $lignes->fetchAll(PDO::FETCH_ASSOC);

// Exclure les passagers arrivés à destination (uniquement pour bordereau cloturé et non segment)
$escaleDestNames = [];
// Pour les segments (parent_id non null) et bordereaux en_cours, on n'applique pas le filtrage
// car les passagers restants ont des destinations qui correspondent aux escales du segment précédent
$isSegment = !empty($bordereau['parent_id']);

if (!$isSegment && $aid && $bordereau['statut'] === 'cloture') {
    $myAg = $pdo->prepare("SELECT nom,ville FROM agences WHERE id=?"); $myAg->execute([$aid]); $myAgData = $myAg->fetch(PDO::FETCH_ASSOC);
    if ($myAgData) $escaleDestNames = [mb_strtolower(trim($myAgData['ville'])), mb_strtolower(trim($myAgData['nom']))];
    foreach ($escales as $esc) {
        if ($esc['statut'] === 'confirme') {
            $escaleDestNames[] = mb_strtolower(trim($esc['agence_ville'] ?? $esc['agence_nom']));
        }
    }
    $escaleDestNames = array_filter(array_unique($escaleDestNames));
}

if (!empty($escaleDestNames)) {
    $lignes = array_filter($lignes, function($l) use ($escaleDestNames) {
        $dest = mb_strtolower(trim($l['destination'] ?? ''));
        if (!$dest) return true;
        foreach ($escaleDestNames as $ev) {
            if ($ev && ($dest === $ev || strpos($ev, $dest) !== false || strpos($dest, $ev) !== false)) return false;
        }
        return true;
    });
    $lignes = array_values($lignes);
}

// Déterminer la prochaine escale à confirmer
$prochaineEscale = null;
foreach ($escales as $esc) {
    if ($esc['statut'] === 'en_attente') {
        $prochaineEscale = $esc;
        break;
    }
}

// AJAX: tarifs pour l'escale courante
if (isset($_GET['ajax']) && $_GET['ajax'] === 'tarifs_escale') {
    header('Content-Type: application/json');
    ob_clean();
    $itinId = (int)($bordereau['itineraire_id'] ?? 0);
    $escalesItin = [];
    $tarifs = [];
    $destinations = [];
    $destTarifs = [];
    if ($itinId) {
        $esq = $pdo->prepare("SELECT ie.*, a.nom as agence_nom, a.ville FROM itineraire_escales ie JOIN agences a ON ie.agence_id=a.id WHERE ie.itineraire_id=? ORDER BY ie.ordre");
        $esq->execute([$itinId]);
        $escalesItin = $esq->fetchAll(PDO::FETCH_ASSOC);
        $tq = $pdo->prepare("SELECT t.*, ag1.ville as esc_dep_ville, ag2.ville as esc_arr_ville FROM tarifs t LEFT JOIN itineraire_escales ie1 ON t.escale_depart_id=ie1.id LEFT JOIN itineraire_escales ie2 ON t.escale_arrivee_id=ie2.id LEFT JOIN agences ag1 ON ie1.agence_id=ag1.id LEFT JOIN agences ag2 ON ie2.agence_id=ag2.id WHERE t.itineraire_id=? AND t.actif=1 ORDER BY ie1.ordre, t.classe");
        $tq->execute([$itinId]);
        $tarifs = $tq->fetchAll(PDO::FETCH_ASSOC);
    }
    if (empty($tarifs)) {
        $destId = $bordereau['destination_id'] ?? null;
        if ($destId) {
            $tq = $pdo->prepare("SELECT t.* FROM tarifs t WHERE t.destination_id=? AND t.actif=1 ORDER BY t.classe");
            $tq->execute([$destId]);
            $tarifs = $tq->fetchAll(PDO::FETCH_ASSOC);
        }
    }
    // Destinations pour vente libre
    $destinations = $pdo->query("SELECT d.id, d.agence_depart, d.agence_arrivee, a1.nom as dep_nom, a2.nom as arr_nom FROM destinations d JOIN agences a1 ON d.agence_depart=a1.id JOIN agences a2 ON d.agence_arrivee=a2.id WHERE d.actif=1 ORDER BY a1.nom, a2.nom")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($destinations as $d) {
        $dt = $pdo->prepare("SELECT t.*, ag1.ville as esc_dep_ville, ag2.ville as esc_arr_ville FROM tarifs t LEFT JOIN itineraire_escales ie1 ON t.escale_depart_id=ie1.id LEFT JOIN itineraire_escales ie2 ON t.escale_arrivee_id=ie2.id LEFT JOIN agences ag1 ON ie1.agence_id=ag1.id LEFT JOIN agences ag2 ON ie2.agence_id=ag2.id WHERE t.destination_id=? AND t.actif=1 ORDER BY t.classe");
        $dt->execute([$d['id']]);
        foreach ($dt->fetchAll(PDO::FETCH_ASSOC) as $t) {
            $destTarifs[] = array_merge($t, ['dest_id' => $d['id']]);
        }
    }
    echo json_encode(['escales' => $escalesItin, 'tarifs' => $tarifs, 'destinations' => $destinations, 'destTarifs' => $destTarifs]);
    exit;
}

// Traitement POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ── Ajouter un passager à l'escale ──
    if ($action === 'ajouter_passager' && $bordereau['statut'] === 'en_cours' && can('tickets.create')) {
        if (!$prochaineEscale || $prochaineEscale['agence_id'] != $aid) {
            flash('Votre agence n\'est pas l\'escale courante.', 'danger');
        } else {
            $isLibre = isset($_POST['vente_libre']) && $_POST['vente_libre'] === '1';
            $nom = mb_strtoupper(trim($_POST['passager_nom'] ?? ''));
            $tel = trim($_POST['passager_tel'] ?? '');
            $cni = trim($_POST['passager_cni'] ?? '');
            $siege = trim($_POST['siege'] ?? '');
            $classe = $_POST['classe'] ?? 'cla';
            $tarif_id = (int)($_POST['tarif_id'] ?? 0) ?: null;
            $bag_kg = (float)($_POST['bagages_kg'] ?? 0);
            $bag_m = (float)($_POST['bagages_montant'] ?? 0);
            $mode = $_POST['mode_paiement'] ?? 'especes';
            $somme_percu = (float)($_POST['somme_percu'] ?? 0);

            // Champs spécifiques au type de vente
            $escale_montee = null;
            $escale_descente = null;
            $destination_id = null;
            $agence_depart_id = null;
            $agence_arrivee_id = null;
            $destLabel = '';
            $montant = 0;

            if ($isLibre) {
                // ── VENTE LIBRE ──
                $destination_id = (int)($_POST['destination_id'] ?? 0) ?: null;
                if (!$destination_id) {
                    flash('Destination obligatoire pour vente libre.', 'danger');
                    redirect(BASE_URL . "modules/bordereaux/valider.php?id=$id");
                }
                // Résoudre agences depuis la destination
                $dq = $pdo->prepare("SELECT d.*, a1.nom as dep_nom, a2.nom as arr_nom FROM destinations d JOIN agences a1 ON d.agence_depart=a1.id JOIN agences a2 ON d.agence_arrivee=a2.id WHERE d.id=?");
                $dq->execute([$destination_id]);
                $dest = $dq->fetch(PDO::FETCH_ASSOC);
                if ($dest) {
                    $agence_depart_id = $dest['agence_depart'];
                    $agence_arrivee_id = $dest['agence_arrivee'];
                    $destLabel = ($dest['dep_nom'] ?? '') . ' → ' . ($dest['arr_nom'] ?? '');
                }
                // Récupérer le montant depuis le tarif sélectionné
                if ($tarif_id) {
                    $tq = $pdo->prepare("SELECT prix FROM tarifs WHERE id=? AND actif=1");
                    $tq->execute([$tarif_id]);
                    $tarifPrix = $tq->fetchColumn();
                    $montant = $tarifPrix ? (float)$tarifPrix : (float)($_POST['montant'] ?? 0);
                } else {
                    $montant = (float)($_POST['montant'] ?? 0);
                }
            } else {
                // ── VENTE RATTACHÉE ──
                $escale_montee = (int)($_POST['escale_montee_id'] ?? 0) ?: null;
                $escale_descente = (int)($_POST['escale_descente_id'] ?? 0) ?: null;
                // Destination label depuis escale descente
                if ($escale_descente) {
                    $dq2 = $pdo->prepare("SELECT a.nom FROM itineraire_escales ie JOIN agences a ON ie.agence_id=a.id WHERE ie.id=?");
                    $dq2->execute([$escale_descente]);
                    $destLabel = $dq2->fetchColumn() ?: '';
                }
                if (!$destLabel) {
                    $destLabel = $bordereau['agence_arrivee'] ?? '';
                }
                // Récupérer le montant depuis le tarif sélectionné (obligatoire en rattaché)
                if ($tarif_id) {
                    $tq = $pdo->prepare("SELECT prix FROM tarifs WHERE id=? AND actif=1");
                    $tq->execute([$tarif_id]);
                    $tarifPrix = $tq->fetchColumn();
                    $montant = $tarifPrix ? (float)$tarifPrix : 0;
                }
                if ($montant <= 0) {
                    flash('Tarif obligatoire pour vente rattachée.', 'danger');
                    redirect(BASE_URL . "modules/bordereaux/valider.php?id=$id");
                }
            }

            $total = $montant + $bag_m;
            $reliquat = $somme_percu - $total;

            if (!$nom || $total <= 0) {
                flash('Nom passager et montant obligatoires.', 'danger');
            } else {
                $pdo->beginTransaction();
                try {
                    // 1. Créer ou trouver passager
                    $passager_id = null;
                    if ($tel || $cni) {
                        $ep = $pdo->prepare("SELECT id, telephone, cni FROM passagers WHERE (telephone=? AND telephone != '') OR (cni=? AND cni != '') LIMIT 1");
                        $ep->execute([$tel, $cni]);
                        $existing = $ep->fetch(PDO::FETCH_ASSOC);
                        if ($existing) {
                            $passager_id = $existing['id'];
                            $updates = [];
                            $uParams = [];
                            if ($tel && !$existing['telephone']) {
                                $updates[] = 'telephone=?';
                                $uParams[] = $tel;
                            }
                            if ($cni && !$existing['cni']) {
                                $updates[] = 'cni=?';
                                $uParams[] = $cni;
                            }
                            if ($updates) {
                                $uParams[] = $passager_id;
                                $pdo->prepare("UPDATE passagers SET " . implode(',', $updates) . " WHERE id=?")->execute($uParams);
                            }
                        } else {
                            $parts = preg_split('/\s+/', trim($nom), 2);
                            $pdo->prepare("INSERT INTO passagers (nom,prenom,telephone,cni) VALUES (?,?,?,?)")->execute([$parts[0], $parts[1] ?? '', $tel, $cni]);
                            $passager_id = $pdo->lastInsertId();
                        }
                    }

                    // 2. Créer ticket
                    $num = genNumero($pdo, 'tickets', 'numero', 'T', getUserAgenceCode());
                    $voyage_id_db = $isLibre ? null : $bordereau['voyage_id'];
                    $date_heure = !empty($_POST['date_heure']) ? $_POST['date_heure'] : null;
                    $pdo->prepare("INSERT INTO tickets (numero,voyage_id,bordereau_id,passager_id,passager_nom,passager_tel,passager_cni,siege,classe,tarif_id,montant,bagages_kg,montant_bagages,montant_total,statut,mode_paiement,agence_id,guichetier_id,escale_montee_id,escale_descente_id,agence_depart_id,agence_arrivee_id,type_passager,somme_percu,reliquat,observation,date_heure) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,'utilise',?,?,?,?,?,?,?,?,?,?,?,?)")
                        ->execute([$num, $voyage_id_db, $id, $passager_id, $nom, $tel, $cni, $siege, $classe, $tarif_id, $montant, $bag_kg, $bag_m, $total, $mode, $aid, $uid, $escale_montee, $escale_descente, $agence_depart_id, $agence_arrivee_id, 'adulte', $somme_percu, $reliquat, null, $date_heure]);
                    $ticket_id = $pdo->lastInsertId();

                    // 4. Ajouter à bordereau_lignes
                    $pdo->prepare("INSERT INTO bordereau_lignes (bordereau_id,ticket_id,passager_nom,siege,destination,montant,classe) VALUES (?,?,?,?,?,?,?)")
                        ->execute([$id, $ticket_id, $nom, $siege, $destLabel, $total, $classe]);

                    // 5. Mettre à jour les finances du bordereau
                    $newNb = $bordereau['nb_passagers'] + 1;
                    $newRecette = $bordereau['recette_brute'] + $total;
                    $newNette = $newRecette - ($bordereau['montant_carburant'] ?? 0) - ($bordereau['montant_peage'] ?? 0) - ($bordereau['avance_chauffeur'] ?? 0) - ($bordereau['autres_deductions'] ?? 0);
                    $pdo->prepare("UPDATE bordereaux SET nb_passagers=?, recette_brute=?, recette_nette=? WHERE id=?")
                        ->execute([$newNb, $newRecette, $newNette, $id]);

                    logAction($pdo, 'ajout_passager_escale', 'bordereaux', "Passager $nom ajouté au bordereau {$bordereau['numero']} à l'escale" . ($isLibre ? ' [LIBRE]' : ''));
                    flash("Passager $nom ajouté au bordereau.");
                    $pdo->commit();
                } catch (Exception $e) {
                    $pdo->rollBack();
                    flash('Erreur : ' . $e->getMessage(), 'danger');
                }
                redirect(BASE_URL . "modules/bordereaux/valider.php?id=$id");
            }
        }
    }

    // ── Ajouter des tickets existants au bordereau ──
    if ($action === 'ajouter_tickets_existants' && $bordereau['statut'] === 'en_cours' && (can('tickets.create') || can('bordereaux.validate'))) {
        if (!$prochaineEscale || $prochaineEscale['agence_id'] != $aid) {
            flash('Votre agence n\'est pas l\'escale courante.', 'danger');
        } else {
            $ticket_ids = array_map('intval', $_POST['ticket_ids'] ?? []);
            if (empty($ticket_ids)) {
                flash('Sélectionnez au moins un ticket.', 'danger');
            } else {
                $in = implode(',', $ticket_ids);
                // Vérifier que ces tickets appartiennent à l'agence et ne sont pas déjà dans un bordereau
                $validTickets = $pdo->query("SELECT t.*, IFNULL(aa.nom, '—') as dest_nom FROM tickets t LEFT JOIN agences aa ON t.agence_arrivee_id=aa.id WHERE t.id IN ($in) AND t.agence_id=$aid AND t.statut='vendu' AND (t.voyage_id IS NULL OR t.voyage_id=" . (int)$bordereau['voyage_id'] . ") AND t.id NOT IN (SELECT bl.ticket_id FROM bordereau_lignes bl JOIN bordereaux b ON bl.bordereau_id=b.id WHERE b.statut!='annule' AND bl.ticket_id IS NOT NULL)")->fetchAll(PDO::FETCH_ASSOC);

                if (empty($validTickets)) {
                    flash('Aucun ticket éligible trouvé.', 'danger');
                } else {
                    $pdo->beginTransaction();
                    try {
                        $added = 0;
                        $totalAjout = 0;
                        foreach ($validTickets as $tk) {
                            $destLabel = $tk['dest_nom'] ?? ($bordereau['agence_arrivee'] ?? '');
                            $pdo->prepare("INSERT INTO bordereau_lignes (bordereau_id,ticket_id,passager_nom,siege,destination,montant,classe) VALUES (?,?,?,?,?,?,?)")
                                ->execute([$id, $tk['id'], $tk['passager_nom'], $tk['siege'] ?? '', $destLabel, $tk['montant_total'], $tk['classe']]);
                            // Rattacher le ticket au voyage si vente libre + associer au bordereau
                            if (!$tk['voyage_id']) {
                                $pdo->prepare("UPDATE tickets SET voyage_id=?, bordereau_id=?, statut='utilise' WHERE id=?")->execute([$bordereau['voyage_id'], $id, $tk['id']]);
                            } else {
                                $pdo->prepare("UPDATE tickets SET bordereau_id=?, statut='utilise' WHERE id=?")->execute([$id, $tk['id']]);
                            }
                            $added++;
                            $totalAjout += $tk['montant_total'];
                        }
                        // Mettre à jour les finances du bordereau
                        $newNb = $bordereau['nb_passagers'] + $added;
                        $newRecette = $bordereau['recette_brute'] + $totalAjout;
                        $newNette = $newRecette - ($bordereau['montant_carburant'] ?? 0) - ($bordereau['montant_peage'] ?? 0) - ($bordereau['avance_chauffeur'] ?? 0) - ($bordereau['autres_deductions'] ?? 0);
                        $pdo->prepare("UPDATE bordereaux SET nb_passagers=?, recette_brute=?, recette_nette=? WHERE id=?")
                            ->execute([$newNb, $newRecette, $newNette, $id]);

                        logAction($pdo, 'ajout_tickets_escale', 'bordereaux', "$added tickets ajoutés au bordereau {$bordereau['numero']} à l'escale");
                        flash("$added ticket(s) ajouté(s) au bordereau.");
                        $pdo->commit();
                    } catch (Exception $e) {
                        $pdo->rollBack();
                        flash('Erreur : ' . $e->getMessage(), 'danger');
                    }
                }
            }
            redirect(BASE_URL . "modules/bordereaux/valider.php?id=$id");
        }
    }

    // ── Valider bordereau (genere → en_cours) + mettre voyage en_cours ──
    if ($action === 'valider' && $bordereau['statut'] === 'genere' && can('bordereaux.validate')) {
        if (!$aid || $aid != $bordereau['agence_id']) {
            flash('Seule l\'agence de départ peut valider ce bordereau.', 'danger');
        } else {
            $itin_id = (int)($bordereau['itineraire_id'] ?? 0);
            $pdo->beginTransaction();
            try {
                // Mettre le bordereau en cours
                $pdo->prepare("UPDATE bordereaux SET statut='en_cours',valide_par=?,date_validation=NOW() WHERE id=?")->execute([$uid, $id]);

                // Mettre le voyage en cours si programme
                if ($bordereau['voyage_id']) {
                    $pdo->prepare("UPDATE voyages SET statut='en_cours' WHERE id=? AND statut='programme'")->execute([$bordereau['voyage_id']]);
                }

                // Passer les tickets liés en "utilise" et leur associer le bordereau
                $pdo->prepare("UPDATE tickets SET statut='utilise', bordereau_id=? WHERE id IN (SELECT bl.ticket_id FROM bordereau_lignes bl WHERE bl.bordereau_id=? AND bl.ticket_id IS NOT NULL) AND statut='vendu'")
                    ->execute([$id, $id]);
                // Rattacher les tickets libres au voyage
                if ($bordereau['voyage_id']) {
                    $pdo->prepare("UPDATE tickets SET voyage_id=? WHERE id IN (SELECT bl.ticket_id FROM bordereau_lignes bl WHERE bl.bordereau_id=? AND bl.ticket_id IS NOT NULL) AND voyage_id IS NULL")
                        ->execute([$bordereau['voyage_id'], $id]);
                }

                // Créer les escales depuis l'itinéraire
                $pdo->prepare("DELETE FROM bordereau_escales WHERE bordereau_id=?")->execute([$id]);

                if ($itin_id) {
                    $itEcales = $pdo->prepare("SELECT ie.agence_id,ie.ordre,a.nom FROM itineraire_escales ie JOIN agences a ON ie.agence_id=a.id WHERE ie.itineraire_id=? ORDER BY ie.ordre");
                    $itEcales->execute([$itin_id]);
                    $itEcales = $itEcales->fetchAll(PDO::FETCH_ASSOC);

                    if (empty($itEcales)) {
                        $pdo->prepare("INSERT INTO bordereau_escales (bordereau_id,agence_id,ordre,statut,confirme_par,date_confirmation) VALUES (?,?,?,'confirme',?,NOW())")
                            ->execute([$id, $bordereau['agence_id'], 1, $uid]);
                        $pdo->prepare("UPDATE bordereaux SET statut='cloture' WHERE id=?")->execute([$id]);
                        flash("Bordereau {$bordereau['numero']} validé et clôturé (aucune escale sur l'itinéraire).");
                    } else {
                        foreach ($itEcales as $i => $esc) {
                            if ($i === 0) {
                                $pdo->prepare("INSERT INTO bordereau_escales (bordereau_id,agence_id,ordre,statut,confirme_par,date_confirmation) VALUES (?,?,?,'confirme',?,NOW())")
                                    ->execute([$id, $esc['agence_id'], $esc['ordre'], $uid]);
                            } else {
                                $pdo->prepare("INSERT INTO bordereau_escales (bordereau_id,agence_id,ordre,statut) VALUES (?,?,?,'en_attente')")
                                    ->execute([$id, $esc['agence_id'], $esc['ordre']]);
                            }
                        }
                        $nextEsc = $itEcales[1] ?? null;
                        if ($nextEsc) {
                            addNotif($pdo, null, $nextEsc['agence_id'], 'bordereau', 'Bordereau validé', "Le bordereau {$bordereau['numero']} est en route vers votre agence.", BASE_URL . "modules/bordereaux/valider.php?id=$id");
                        }
                        flash("Bordereau {$bordereau['numero']} validé — " . count($itEcales) . ' escales créées.');
                    }
                } else {
                    // Pas d'itinéraire : bordereau directement clôturé
                    $pdo->prepare("INSERT INTO bordereau_escales (bordereau_id,agence_id,ordre,statut,confirme_par,date_confirmation) VALUES (?,?,?,'confirme',?,NOW())")
                        ->execute([$id, $bordereau['agence_id'], 1, $uid]);
                    $pdo->prepare("UPDATE bordereaux SET statut='cloture' WHERE id=?")->execute([$id]);
                    flash("Bordereau {$bordereau['numero']} validé et clôturé (pas d'itinéraire).");
                }

                $pdo->commit();
                logAction($pdo, 'valider_bordereau', 'bordereaux', "Bordereau {$bordereau['numero']} validé et mis en cours");
                redirect(BASE_URL . "modules/bordereaux/valider.php?id=$id");
            } catch (Exception $e) {
                $pdo->rollBack();
                flash('Erreur lors de la validation : ' . $e->getMessage(), 'danger');
            }
        }
    }

    // ── Confirmer escale → clôturer bordereau + créer nouveau segment ──
    elseif ($action === 'confirmer_escale' && $bordereau['statut'] === 'en_cours' && can('bordereaux.validate')) {
        $esc_id = (int)($_POST['escale_id'] ?? 0);
        $obs = trim($_POST['obs_escale'] ?? '');
        $escCheck = $pdo->prepare("SELECT * FROM bordereau_escales WHERE id=? AND bordereau_id=? AND statut='en_attente' AND agence_id=?");
        $escCheck->execute([$esc_id, $id, $aid]);
        $escData = $escCheck->fetch(PDO::FETCH_ASSOC);
        if (!$escData) {
            flash('Escale invalide ou déjà confirmée.', 'danger');
        } else {
            $prevCheck = $pdo->prepare("SELECT COUNT(*) FROM bordereau_escales WHERE bordereau_id=? AND ordre<? AND statut='en_attente'");
            $prevCheck->execute([$id, $escData['ordre']]);
            if ($prevCheck->fetchColumn() > 0) {
                flash('Vous devez attendre que les escales précédentes confirment.', 'danger');
            } else {
                $pdo->beginTransaction();
                try {
                    // 1. Confirmer l'escale
                    $pdo->prepare("UPDATE bordereau_escales SET statut='confirme',confirme_par=?,date_confirmation=NOW(),observations=? WHERE id=?")
                        ->execute([$uid, $obs, $esc_id]);

                    // 2. Infos de l'agence de l'escale confirmée
                    $escAg = $pdo->prepare("SELECT nom,ville FROM agences WHERE id=?");
                    $escAg->execute([$escData['agence_id']]);
                    $escAgData = $escAg->fetch(PDO::FETCH_ASSOC);
                    $escAgenceLabel = $escAgData ? ($escAgData['ville'] ?? $escAgData['nom']) : '';

                    // 3. Construire la liste des destinations confirmées (pour filtrage passagers)
                    $confirmedEscNames = [];
                    $allEscQ = $pdo->prepare("SELECT be.statut, a.nom as agence_nom, a.ville as agence_ville FROM bordereau_escales be LEFT JOIN agences a ON be.agence_id=a.id WHERE be.bordereau_id=? ORDER BY be.ordre");
                    $allEscQ->execute([$id]);
                    foreach ($allEscQ->fetchAll(PDO::FETCH_ASSOC) as $ae) {
                        if ($ae['statut'] === 'confirme') {
                            $confirmedEscNames[] = mb_strtolower(trim($ae['agence_ville'] ?? $ae['agence_nom']));
                        }
                    }
                    $confirmedEscNames = array_filter(array_unique($confirmedEscNames));

                    // 4. Séparer passagers descendus / restants
                    $allLignes = $pdo->prepare("SELECT bl.*, t.passager_tel, t.passager_cni, t.mode_paiement FROM bordereau_lignes bl LEFT JOIN tickets t ON bl.ticket_id=t.id WHERE bl.bordereau_id=? ORDER BY bl.siege");
                    $allLignes->execute([$id]);
                    $allLignesData = $allLignes->fetchAll(PDO::FETCH_ASSOC);

                    $descendedLignes = [];
                    $remainingLignes = [];
                    foreach ($allLignesData as $l) {
                        $dest = mb_strtolower(trim($l['destination'] ?? ''));
                        $descendu = false;
                        if ($dest) {
                            foreach ($confirmedEscNames as $ev) {
                                if ($ev && ($dest === $ev || strpos($ev, $dest) !== false || strpos($dest, $ev) !== false)) {
                                    $descendu = true;
                                    break;
                                }
                            }
                        }
                        if ($descendu) $descendedLignes[] = $l;
                        else $remainingLignes[] = $l;
                    }

                    // 5. Vérifier s'il reste des escales en_attente
                    $remainingEscQ = $pdo->prepare("SELECT be.*, a.nom as agence_nom, a.ville as agence_ville FROM bordereau_escales be LEFT JOIN agences a ON be.agence_id=a.id WHERE be.bordereau_id=? AND be.statut='en_attente' ORDER BY be.ordre");
                    $remainingEscQ->execute([$id]);
                    $remainingEscales = $remainingEscQ->fetchAll(PDO::FETCH_ASSOC);

                    // 6. Clôturer le bordereau actuel
                    $oldRecette = array_sum(array_column($descendedLignes, 'montant'));
                    $oldNb = count($descendedLignes);
                    $oldNette = $oldRecette - ($bordereau['montant_carburant'] ?? 0) - ($bordereau['montant_peage'] ?? 0) - ($bordereau['avance_chauffeur'] ?? 0) - ($bordereau['autres_deductions'] ?? 0);
                    $originalArrivee = $bordereau['agence_arrivee'];

                    $pdo->prepare("UPDATE bordereaux SET statut='cloture',agence_arrivee=?,nb_passagers=?,recette_brute=?,recette_nette=? WHERE id=?")
                        ->execute([$escAgenceLabel, $oldNb, $oldRecette, $oldNette, $id]);

                    logAction($pdo, 'cloturer_bordereau', 'bordereaux', "Bordereau {$bordereau['numero']} clôturé à l'escale $escAgenceLabel");

                    // 7. S'il reste des escales → créer un nouveau bordereau segment
                    if (!empty($remainingEscales)) {
                        $newNumero = genNumero($pdo, 'bordereaux', 'numero', getParam('prefix_bordereau', 'BRD'));
                        $newRecette = array_sum(array_column($remainingLignes, 'montant'));
                        $newNb = count($remainingLignes);
                        $currentSegment = (int)($bordereau['segment_ordre'] ?? 1);
                        $nextSegment = $currentSegment + 1;

                        $pdo->prepare("INSERT INTO bordereaux (numero,voyage_id,parent_id,segment_ordre,agence_id,type,vehicule_immat,chauffeur_nom,chauffeur_permis,convoyeur_nom,agence_depart,agence_arrivee,date_depart,nb_passagers,recette_brute,montant_carburant,montant_peage,avance_chauffeur,autres_deductions,recette_nette,statut,created_by,observations) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
                            ->execute([
                                $newNumero,
                                $bordereau['voyage_id'],
                                $id,
                                $nextSegment,
                                $escData['agence_id'],
                                $bordereau['type'],
                                $bordereau['vehicule_immat'],
                                $bordereau['chauffeur_nom'],
                                $bordereau['chauffeur_permis'],
                                $bordereau['convoyeur_nom'],
                                $escAgenceLabel,
                                $originalArrivee,
                                $bordereau['date_depart'],
                                $newNb,
                                $newRecette,
                                0, 0, 0, 0,
                                $newRecette,
                                'en_cours',
                                $_SESSION['user_id'],
                                "Suite du bordereau {$bordereau['numero']} — segment $nextSegment"
                            ]);
                        $newBrdId = $pdo->lastInsertId();

                        // Copier les lignes des passagers restants dans le NOUVEAU bordereau
                        foreach ($remainingLignes as $ligne) {
                            $pdo->prepare("INSERT INTO bordereau_lignes (bordereau_id,ticket_id,passager_nom,siege,destination,montant,classe) VALUES (?,?,?,?,?,?,?)")
                                ->execute([$newBrdId, $ligne['ticket_id'], $ligne['passager_nom'], $ligne['siege'], $ligne['destination'], $ligne['montant'], $ligne['classe']]);
                        }

                        // SUPPRIMER les lignes de l'ANCIEN bordereau (pour éviter doublons)
                        $remainingTicketIdsForDelete = array_filter(array_column($remainingLignes, 'ticket_id'));
                        if (!empty($remainingTicketIdsForDelete)) {
                            $ticketInDelete = implode(',', array_map('intval', $remainingTicketIdsForDelete));
                            $pdo->exec("DELETE FROM bordereau_lignes WHERE bordereau_id=$id AND ticket_id IN ($ticketInDelete)");
                        }

                        // Mettre à jour tickets.bordereau_id pour les passagers restants
                        $remainingTicketIds = array_filter(array_column($remainingLignes, 'ticket_id'));
                        if (!empty($remainingTicketIds)) {
                            $ticketIn = implode(',', array_map('intval', $remainingTicketIds));
                            $pdo->exec("UPDATE tickets SET bordereau_id=$newBrdId WHERE id IN ($ticketIn)");
                        }

                        // Copier les escales restantes vers le nouveau bordereau
                        $newOrdre = 1;
                        foreach ($remainingEscales as $re) {
                            if ($newOrdre === 1) {
                                // Première escale = escale courante, auto-confirmée
                                $pdo->prepare("INSERT INTO bordereau_escales (bordereau_id,agence_id,ordre,statut,confirme_par,date_confirmation) VALUES (?,?,?,'confirme',?,NOW())")
                                    ->execute([$newBrdId, $re['agence_id'], $newOrdre, $uid]);
                            } else {
                                $pdo->prepare("INSERT INTO bordereau_escales (bordereau_id,agence_id,ordre,statut) VALUES (?,?,?,'en_attente')")
                                    ->execute([$newBrdId, $re['agence_id'], $newOrdre]);
                            }
                            $newOrdre++;
                        }

                        // Notifier la prochaine escale
                        $nextEscaleAgence = $remainingEscales[1]['agence_id'] ?? null;
                        if ($nextEscaleAgence) {
                            addNotif($pdo, null, $nextEscaleAgence, 'bordereau', 'Nouveau bordereau', "Le bordereau $newNumero (suite de {$bordereau['numero']}) est en route vers votre agence.", BASE_URL . "modules/bordereaux/valider.php?id=$newBrdId");
                        }

                        logAction($pdo, 'creer_bordereau_segment', 'bordereaux', "Nouveau bordereau $newNumero (suite de {$bordereau['numero']}) avec $newNb passager(s)");
                        flash("Escale confirmée. Bordereau {$bordereau['numero']} clôturé. Nouveau bordereau $newNumero créé avec $newNb passager(s) restant(s).");
                        $pdo->commit();
                        redirect(BASE_URL . "modules/bordereaux/valider.php?id=$newBrdId");
                    } else {
                        // Aucune escale restante → voyage terminé, mettre à jour le statut du voyage
                        if ($bordereau['voyage_id']) {
                            $pdo->prepare("UPDATE voyages SET statut='arrive' WHERE id=? AND statut='en_cours'")
                                ->execute([$bordereau['voyage_id']]);
                            logAction($pdo, 'voyage_arrive', 'voyages', "Voyage {$bordereau['voy_num']} arrivé à destination");
                        }
                        flash("Bordereau {$bordereau['numero']} — toutes les escales confirmées. Voyage arrivé à destination.");
                        $pdo->commit();
                        redirect(BASE_URL . "modules/bordereaux/valider.php?id=$id");
                    }
                } catch (Exception $e) {
                    $pdo->rollBack();
                    flash('Erreur : ' . $e->getMessage(), 'danger');
                }
            }
        }
    }
}

// Recharger après action
$stmt->execute([$id]);
$bordereau = $stmt->fetch(PDO::FETCH_ASSOC);
$escales = $pdo->prepare("SELECT be.*,a.nom as agence_nom,a.ville as agence_ville,CONCAT(u.prenom,' ',u.nom) as confirme_nom FROM bordereau_escales be LEFT JOIN agences a ON be.agence_id=a.id LEFT JOIN utilisateurs u ON be.confirme_par=u.id WHERE be.bordereau_id=? ORDER BY be.ordre");
$escales->execute([$id]);
$escales = $escales->fetchAll(PDO::FETCH_ASSOC);
$lignes = $pdo->prepare("SELECT bl.*, t.passager_tel, t.passager_cni, t.mode_paiement, t.escale_montee_id, t.escale_descente_id FROM bordereau_lignes bl LEFT JOIN tickets t ON bl.ticket_id=t.id WHERE bl.bordereau_id=? ORDER BY bl.siege");
$lignes->execute([$id]);
$lignes = $lignes->fetchAll(PDO::FETCH_ASSOC);

// Exclure les passagers arrivés à destination (uniquement pour bordereau cloturé et non segment)
$escaleDestNames = [];
// Pour les segments (parent_id non null) et bordereaux en_cours, on n'applique pas le filtrage
// car les passagers restants ont des destinations qui correspondent aux escales du segment précédent
$isSegment = !empty($bordereau['parent_id']);

if (!$isSegment && $aid && $bordereau['statut'] === 'cloture') {
    $myAg = $pdo->prepare("SELECT nom,ville FROM agences WHERE id=?"); $myAg->execute([$aid]); $myAgData = $myAg->fetch(PDO::FETCH_ASSOC);
    if ($myAgData) $escaleDestNames = [mb_strtolower(trim($myAgData['ville'])), mb_strtolower(trim($myAgData['nom']))];
    foreach ($escales as $esc) {
        if ($esc['statut'] === 'confirme') {
            $escaleDestNames[] = mb_strtolower(trim($esc['agence_ville'] ?? $esc['agence_nom']));
        }
    }
    $escaleDestNames = array_filter(array_unique($escaleDestNames));
}

if (!empty($escaleDestNames)) {
    $lignes = array_filter($lignes, function($l) use ($escaleDestNames) {
        $dest = mb_strtolower(trim($l['destination'] ?? ''));
        if (!$dest) return true;
        foreach ($escaleDestNames as $ev) {
            if ($ev && ($dest === $ev || strpos($ev, $dest) !== false || strpos($dest, $ev) !== false)) return false;
        }
        return true;
    });
    $lignes = array_values($lignes);
}
$prochaineEscale = null;
foreach ($escales as $esc) {
    if ($esc['statut'] === 'en_attente') {
        $prochaineEscale = $esc;
        break;
    }
}

$peutValider = ($bordereau['statut'] === 'genere' && $aid && $aid == $bordereau['agence_id'] && can('bordereaux.validate'));
$peutConfirmer = ($bordereau['statut'] === 'en_cours' && $prochaineEscale && $aid && $aid == $prochaineEscale['agence_id'] && can('bordereaux.validate'));
$peutAjouter = ($bordereau['statut'] === 'en_cours' && $prochaineEscale && $aid && $aid == $prochaineEscale['agence_id'] && can('tickets.create'));
$peutGererEscale = ($bordereau['statut'] === 'en_cours' && $prochaineEscale && $aid && $aid == $prochaineEscale['agence_id'] && (can('tickets.create') || can('bordereaux.validate')));

// Charger escales de l'itinéraire pour le formulaire d'ajout
$escalesItin = [];
$tarifsItin = [];
$itinId = (int)($bordereau['itineraire_id'] ?? 0);
if ($itinId) {
    $esq = $pdo->prepare("SELECT ie.*, a.nom as agence_nom, a.ville FROM itineraire_escales ie JOIN agences a ON ie.agence_id=a.id WHERE ie.itineraire_id=? ORDER BY ie.ordre");
    $esq->execute([$itinId]);
    $escalesItin = $esq->fetchAll(PDO::FETCH_ASSOC);
    $tq = $pdo->prepare("SELECT t.*, ag1.ville as esc_dep_ville, ag2.ville as esc_arr_ville FROM tarifs t LEFT JOIN itineraire_escales ie1 ON t.escale_depart_id=ie1.id LEFT JOIN itineraire_escales ie2 ON t.escale_arrivee_id=ie2.id LEFT JOIN agences ag1 ON ie1.agence_id=ag1.id LEFT JOIN agences ag2 ON ie2.agence_id=ag2.id WHERE t.itineraire_id=? AND t.actif=1 ORDER BY ie1.ordre, t.classe");
    $tq->execute([$itinId]);
    $tarifsItin = $tq->fetchAll(PDO::FETCH_ASSOC);
}
if (empty($tarifsItin) && !empty($bordereau['destination_id'])) {
    $tq2 = $pdo->prepare("SELECT t.* FROM tarifs t WHERE t.destination_id=? AND t.actif=1 ORDER BY t.classe");
    $tq2->execute([$bordereau['destination_id']]);
    $tarifsItin = $tq2->fetchAll(PDO::FETCH_ASSOC);
}

// Trouver l'escale itineraire correspondant à la prochaine escale du bordereau
$escaleMonteeId = null;
$escaleMonteeOrdre = null;
if ($prochaineEscale && !empty($escalesItin)) {
    foreach ($escalesItin as $ei) {
        if ($ei['agence_id'] == $prochaineEscale['agence_id']) {
            $escaleMonteeId = $ei['id'];
            $escaleMonteeOrdre = $ei['ordre'];
            break;
        }
    }
}

// Escales de descente possibles (après la montée)
$escalesDescente = [];
if ($escaleMonteeOrdre !== null) {
    foreach ($escalesItin as $ei) {
        if ($ei['ordre'] > $escaleMonteeOrdre) {
            $escalesDescente[] = $ei;
        }
    }
}

// Destinations pour vente libre
$destinations = $pdo->query("SELECT d.id, d.agence_depart, d.agence_arrivee, a1.nom as dep_nom, a2.nom as arr_nom FROM destinations d JOIN agences a1 ON d.agence_depart=a1.id JOIN agences a2 ON d.agence_arrivee=a2.id WHERE d.actif=1 ORDER BY a1.nom, a2.nom")->fetchAll(PDO::FETCH_ASSOC);
$destTarifs = [];
foreach ($destinations as $d) {
    $dt = $pdo->prepare("SELECT t.*, ag1.ville as esc_dep_ville, ag2.ville as esc_arr_ville FROM tarifs t LEFT JOIN itineraire_escales ie1 ON t.escale_depart_id=ie1.id LEFT JOIN itineraire_escales ie2 ON t.escale_arrivee_id=ie2.id LEFT JOIN agences ag1 ON ie1.agence_id=ag1.id LEFT JOIN agences ag2 ON ie2.agence_id=ag2.id WHERE t.destination_id=? AND t.actif=1 ORDER BY t.classe");
    $dt->execute([$d['id']]);
    foreach ($dt->fetchAll(PDO::FETCH_ASSOC) as $t) {
        $destTarifs[] = array_merge($t, ['dest_id' => $d['id']]);
    }
}
if (empty($destTarifs)) {
    $dt = $pdo->query("SELECT t.* FROM tarifs t WHERE t.actif=1 ORDER BY t.destination_id, t.classe");
    $destTarifs = $dt->fetchAll(PDO::FETCH_ASSOC);
}

// Tickets existants à l'escale (vendus à l'agence, pas encore dans un bordereau, destination différente de cette escale)
$ticketsExistants = [];
if ($peutGererEscale) {
    $vid = (int)($bordereau['voyage_id'] ?? 0);
    $escaleAgenceId = (int)($prochaineEscale['agence_id'] ?? 0);
    $ticketsExistants = $pdo->prepare("SELECT t.*, IFNULL(aa.nom,'—') as dest_nom, IF(t.voyage_id IS NULL,'Libre','Rattaché') as type_vente FROM tickets t LEFT JOIN agences aa ON t.agence_arrivee_id=aa.id WHERE t.agence_id=? AND t.statut='vendu' AND t.id NOT IN (SELECT bl.ticket_id FROM bordereau_lignes bl JOIN bordereaux b ON bl.bordereau_id=b.id WHERE b.statut!='annule' AND bl.ticket_id IS NOT NULL) AND (t.agence_arrivee_id IS NULL OR t.agence_arrivee_id != ?) ORDER BY t.date_vente DESC LIMIT 100");
    $ticketsExistants->execute([$aid, $escaleAgenceId]);
    $ticketsExistants = $ticketsExistants->fetchAll(PDO::FETCH_ASSOC);
}

$pageTitle = 'Bordereau ' . $bordereau['numero'] . ' — Escale';
include '../../includes/header.php';
?>
<div class="breadcrumb"><a href="<?= BASE_URL ?>"><i class="fas fa-home"></i></a><span class="breadcrumb-sep">/</span><a href=".">Bordereaux</a><span class="breadcrumb-sep">/</span><a href="voir.php?id=<?= $id ?>"><?= sanitize($bordereau['numero']) ?></a><span class="breadcrumb-sep">/</span>Escale</div>

<?php
// Chaîne des bordereaux segments
$parentId = $bordereau['parent_id'] ?? null;
$chainChildren = $pdo->prepare("SELECT id, numero, segment_ordre, agence_depart, agence_arrivee, statut FROM bordereaux WHERE parent_id=? ORDER BY segment_ordre");
$chainChildren->execute([$id]);
$chainChildrenData = $chainChildren->fetchAll(PDO::FETCH_ASSOC);
$chainParent = null;
if ($parentId) {
    $ps = $pdo->prepare("SELECT id, numero, segment_ordre, agence_depart, agence_arrivee, statut FROM bordereaux WHERE id=?");
    $ps->execute([$parentId]);
    $chainParent = $ps->fetch(PDO::FETCH_ASSOC);
}
if ($chainParent || !empty($chainChildrenData)):
?>
<div class="card" style="margin-bottom:16px;border-left:4px solid var(--purple);">
  <div class="card-header"><h3><i class="fas fa-link"></i> Segments du bordereau</h3></div>
  <div class="card-body" style="padding:8px 12px;">
    <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;">
      <?php if ($chainParent): ?>
        <a href="valider.php?id=<?= $chainParent['id'] ?>" class="btn btn-xs btn-secondary"><i class="fas fa-arrow-left"></i> <?= sanitize($chainParent['numero']) ?></a>
        <i class="fas fa-long-arrow-alt-right" style="color:var(--text3);"></i>
      <?php endif; ?>
      <span class="btn btn-xs btn-primary"><strong><?= sanitize($bordereau['numero']) ?></strong></span>
      <?php foreach ($chainChildrenData as $ch): ?>
        <i class="fas fa-long-arrow-alt-right" style="color:var(--text3);"></i>
        <a href="valider.php?id=<?= $ch['id'] ?>" class="btn btn-xs btn-secondary"><?= sanitize($ch['numero']) ?> (<?= sanitize($ch['agence_depart']) ?> → <?= sanitize($ch['agence_arrivee']) ?>)</a>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<?php endif; ?>

<div class="card" style="margin-bottom:16px;">
  <div class="card-header">
    <h3><i class="fas fa-check-circle"></i> Bordereau <?= sanitize($bordereau['numero']) ?></h3>
    <div style="display:flex;gap:6px;">
      <span class="tag-statut st-<?= $bordereau['statut'] ?>"><?= statutLabel($bordereau['statut']) ?></span>
      <a href="voir.php?id=<?= $id ?>" class="btn btn-xs btn-secondary"><i class="fas fa-eye"></i> Voir</a>
      <a href="imprimer.php?id=<?= $id ?>" class="btn btn-xs btn-info" target="_blank"><i class="fas fa-print"></i> Imprimer</a>
    </div>
  </div>
  <div class="card-body">
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;font-size:13px;">
      <div><span style="color:var(--text3);">Voyage :</span> <strong><?= sanitize($bordereau['voy_num'] ?? '—') ?></strong></div>
      <div><span style="color:var(--text3);">Trajet :</span> <strong><?= sanitize($bordereau['agence_depart'] ?? '—') ?> → <?= sanitize($bordereau['agence_arrivee'] ?? '—') ?></strong></div>
      <div><span style="color:var(--text3);">Véhicule :</span> <strong><?= sanitize($bordereau['vehicule_immat'] ?? '—') ?></strong></div>
      <div><span style="color:var(--text3);">Chauffeur :</span> <strong><?= sanitize($bordereau['chauffeur_nom'] ?? '—') ?></strong></div>
      <div><span style="color:var(--text3);">Recette brute :</span> <strong style="color:var(--success);"><?= number_format($bordereau['recette_brute'], 0, ',', ' ') ?> FCFA</strong></div>
      <div><span style="color:var(--text3);">Passagers :</span> <strong><?= $bordereau['nb_passagers'] ?></strong></div>
      <?php if ($bordereau['valide_nom']): ?>
      <div><span style="color:var(--text3);">Validé par :</span> <strong><?= sanitize($bordereau['valide_nom']) ?></strong></div>
      <div><span style="color:var(--text3);">Date validation :</span> <strong><?= fdatetime($bordereau['date_validation'] ?? '') ?></strong></div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php if ($bordereau['statut'] === 'genere' && can('bordereaux.validate')): ?>
<div class="card" style="margin-bottom:16px;border-left:4px solid var(--success);">
  <div class="card-header"><h3><i class="fas fa-clipboard-check"></i> Validation du bordereau</h3></div>
  <div class="card-body">
    <?php if ($peutValider): ?>
    <p>Le bordereau est prêt. Validez-le pour le mettre en cours et démarrer le suivi par escale.</p>
    <?php if ($itinId):
        $itEcales = $pdo->prepare("SELECT ie.*,a.nom as agence_nom,a.ville FROM itineraire_escales ie JOIN agences a ON ie.agence_id=a.id WHERE ie.itineraire_id=? ORDER BY ie.ordre");
        $itEcales->execute([$itinId]); $itEcales = $itEcales->fetchAll(PDO::FETCH_ASSOC);
    ?>
    <div style="background:var(--bg);border-radius:var(--radius);padding:14px;margin:12px 0;">
      <div style="font-size:12px;font-weight:600;margin-bottom:8px;color:var(--text2);">ESCALLES PRÉVUES SUR LE TRAJET</div>
      <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;">
        <?php foreach ($itEcales as $i => $esc): ?>
        <span style="background:<?= $i === 0 ? 'var(--success)' : ($i === count($itEcales) - 1 ? 'var(--danger)' : 'var(--warning)') ?>;color:#fff;padding:4px 12px;border-radius:20px;font-size:11px;font-weight:600;"><?= sanitize($esc['ville'] ?? $esc['agence_nom']) ?></span>
        <?php if ($i < count($itEcales) - 1): ?><i class="fas fa-long-arrow-alt-right" style="color:var(--text3);font-size:11px;"></i><?php endif; ?>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>
    <form method="POST">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="valider">
      <button type="submit" class="btn btn-success btn-lg"><i class="fas fa-check"></i> Valider le bordereau</button>
    </form>
    <?php else: ?>
    <p style="color:var(--text3);">Seule l'agence de départ peut valider ce bordereau.</p>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>

<?php if ($bordereau['statut'] === 'en_cours' && !empty($escales)): ?>
<div class="card" style="margin-bottom:16px;border-left:4px solid var(--info);">
  <div class="card-header"><h3><i class="fas fa-map-marked-alt"></i> Progression des escales</h3></div>
  <div class="card-body">
    <div style="display:flex;align-items:flex-start;gap:0;flex-wrap:wrap;padding:10px 0;">
      <?php foreach ($escales as $i => $esc): ?>
      <div style="display:flex;flex-direction:column;align-items:center;min-width:90px;position:relative;">
        <div style="width:36px;height:36px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:14px;font-weight:700;
          <?= $esc['statut'] === 'confirme' ? 'background:var(--success);color:#fff;' : ($esc['statut'] === 'refuse' ? 'background:var(--danger);color:#fff;' : 'background:var(--bg);border:2px solid var(--text3);color:var(--text3);') ?>">
          <?php if ($esc['statut'] === 'confirme'): ?><i class="fas fa-check"></i>
          <?php elseif ($esc['statut'] === 'refuse'): ?><i class="fas fa-times"></i>
          <?php else: ?><?= $esc['ordre'] ?><?php endif; ?>
        </div>
        <div style="margin-top:6px;font-size:11px;font-weight:600;text-align:center;<?= $esc['statut'] === 'en_attente' && $prochaineEscale && $prochaineEscale['id'] == $esc['id'] ? 'color:var(--primary);' : '' ?>"><?= sanitize($esc['agence_ville'] ?? $esc['agence_nom']) ?></div>
        <div style="font-size:9px;color:var(--text3);text-align:center;"><?= $esc['statut'] === 'confirme' ? 'Confirmé' . ($esc['confirme_nom'] ? ' par ' . sanitize($esc['confirme_nom']) : '') : ($esc['statut'] === 'en_attente' ? 'En attente' : 'Refusé') ?></div>
      </div>
      <?php if ($i < count($escales) - 1): ?><div style="flex:1;min-width:30px;height:2px;background:<?= $esc['statut'] === 'confirme' ? 'var(--success)' : 'var(--border)' ?>;margin-top:18px;align-self:flex-start;"></div><?php endif; ?>
      <?php endforeach; ?>
    </div>

    <?php if ($peutConfirmer): ?>
    <div style="margin-top:16px;padding:14px;background:var(--primary-bg);border-radius:var(--radius);border:1px solid var(--primary);">
      <div style="font-weight:600;margin-bottom:8px;"><i class="fas fa-bell" style="color:var(--primary);"></i> Votre agence est la prochaine escale</div>
      <p style="font-size:13px;margin-bottom:12px;">Confirmez le passage du bordereau/véhicule à votre agence.</p>
      <form method="POST">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="confirmer_escale">
        <input type="hidden" name="escale_id" value="<?= $prochaineEscale['id'] ?>">
        <div class="fg" style="margin-bottom:10px;">
          <label class="flbl">Observations (optionnel)</label>
          <textarea name="obs_escale" class="fc" rows="2" placeholder="Remarques éventuelles..."></textarea>
        </div>
        <button type="submit" class="btn btn-success"><i class="fas fa-check"></i> Confirmer le passage</button>
      </form>
    </div>
    <?php elseif ($bordereau['statut'] === 'en_cours' && $prochaineEscale): ?>
    <div style="margin-top:12px;font-size:12px;color:var(--text3);"><i class="fas fa-clock"></i> En attente de confirmation par <strong><?= sanitize($prochaineEscale['agence_ville'] ?? $prochaineEscale['agence_nom']) ?></strong></div>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>

<?php if ($bordereau['statut'] === 'cloture'): ?>
<div class="card" style="margin-bottom:16px;border-left:4px solid var(--success);">
  <div class="card-header"><h3><i class="fas fa-check-double"></i> Bordereau clôturé</h3></div>
  <div class="card-body">
    <p style="color:var(--success);font-weight:600;">Toutes les escales ont confirmé le passage. Ce bordereau est clôturé.</p>
    <?php if (!empty($chainChildrenData)): ?>
    <p style="margin-top:8px;font-size:13px;"><i class="fas fa-link" style="color:var(--purple);"></i> Suite : <?php foreach ($chainChildrenData as $i => $ch): ?><?php if ($i > 0) echo ' → '; ?><a href="valider.php?id=<?= $ch['id'] ?>"><?= sanitize($ch['numero']) ?></a><?php endforeach; ?></p>
    <?php endif; ?>
    <?php if (!empty($escales)): ?>
    <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;margin-top:12px;">
      <?php foreach ($escales as $i => $esc): ?>
      <span style="background:var(--success);color:#fff;padding:4px 12px;border-radius:20px;font-size:11px;font-weight:600;"><i class="fas fa-check" style="margin-right:4px;"></i><?= sanitize($esc['agence_ville'] ?? $esc['agence_nom']) ?></span>
      <?php if ($i < count($escales) - 1): ?><i class="fas fa-long-arrow-alt-right" style="color:var(--success);font-size:11px;"></i><?php endif; ?>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>

<?php if ($peutGererEscale): ?>
<div class="card" style="margin-bottom:16px;border-left:4px solid var(--warning);">
  <div class="card-header">
    <h3><i class="fas fa-users"></i> Passagers du bordereau (<?= count($lignes) ?>)</h3>
    <?php if ($peutAjouter): ?><button class="btn btn-warning btn-sm" onclick="openModal('modal-ajout-passager')"><i class="fas fa-plus"></i> Ajouter un passager</button><?php endif; ?>
  </div>
  <div class="card-body" style="padding:0;">
    <div class="table-wrap">
      <table data-no-filter>
        <thead><tr><th>#</th><th>Siège</th><th>Passager</th><th>Tél.</th><th>Destination</th><th>Classe</th><th>Mode</th><th>Montant</th></tr></thead>
        <tbody>
          <?php foreach ($lignes as $i => $l): ?>
          <tr>
            <td style="text-align:center;"><?= $i + 1 ?></td>
            <td><?= sanitize($l['siege'] ?? '—') ?></td>
            <td><strong><?= sanitize($l['passager_nom']) ?></strong></td>
            <td style="font-size:12px;"><?= sanitize($l['passager_tel'] ?? '—') ?></td>
            <td><?= sanitize($l['destination'] ?? '—') ?></td>
            <td><span class="badge <?= $l['classe'] === 'vip' ? 'badge-purple' : ($l['classe'] === 'spc' ? 'badge-teal' : 'badge-blue') ?>"><?= strtoupper($l['classe']) ?></span></td>
            <td style="font-size:12px;"><?= $l['mode_paiement'] ?? '—' ?></td>
            <td style="font-weight:700;"><?= number_format($l['montant'], 0, ',', ' ') ?></td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($lignes)): ?>
          <tr><td colspan="8" class="t-empty"><i class="fas fa-users"></i> Aucun passager</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
    <?php if (!empty($lignes)): ?>
    <div style="padding:10px 12px;text-align:right;font-weight:700;font-size:14px;color:var(--success);border-top:1px solid var(--border);">
      Total : <?= number_format(array_sum(array_column($lignes, 'montant')), 0, ',', ' ') ?> FCFA
    </div>
    <?php endif; ?>
  </div>
</div>

<?php if ($peutGererEscale && !empty($ticketsExistants)): ?>
<div class="card" style="margin-bottom:16px;border-left:4px solid var(--teal);">
  <div class="card-header">
    <h3><i class="fas fa-ticket-alt"></i> Tickets existants à votre agence</h3>
  </div>
  <div class="card-body">
    <div style="background:var(--info-bg);border-radius:var(--radius);padding:10px;margin-bottom:12px;font-size:12px;">
      <i class="fas fa-info-circle" style="color:var(--info);"></i> Ces tickets ont déjà été vendus à votre agence et ne sont pas encore dans un bordereau. Sélectionnez ceux à ajouter.
    </div>
    <form method="POST" id="form-tickets-existants">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="ajouter_tickets_existants">
      <div class="table-wrap">
        <table data-no-filter>
          <thead>
            <tr>
              <th style="width:30px;"><input type="checkbox" id="chk-all-tickets" onchange="toggleAllTickets(this)"></th>
              <th>N°</th>
              <th>Passager</th>
              <th>Destination</th>
              <th>Classe</th>
              <th>Montant</th>
              <th>Type</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($ticketsExistants as $tk): ?>
            <tr>
              <td><input type="checkbox" name="ticket_ids[]" value="<?= $tk['id'] ?>" class="chk-ticket" onchange="updateTicketCount()"></td>
              <td><code style="font-size:11px;"><?= sanitize($tk['numero']) ?></code></td>
              <td><strong><?= sanitize($tk['passager_nom']) ?></strong></td>
              <td><?= sanitize($tk['dest_nom'] ?? '—') ?></td>
              <td><span class="badge <?= $tk['classe'] === 'vip' ? 'badge-purple' : ($tk['classe'] === 'spc' ? 'badge-teal' : 'badge-blue') ?>"><?= strtoupper($tk['classe'] ?? 'cla') ?></span></td>
              <td style="font-weight:700;"><?= number_format($tk['montant_total'] ?? $tk['montant'], 0, ',', ' ') ?> FCFA</td>
              <td><span class="badge <?= $tk['type_vente'] === 'Libre' ? 'badge-amber' : 'badge-green' ?>"><?= $tk['type_vente'] ?></span></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div style="margin-top:12px;display:flex;gap:8px;align-items:center;">
        <span id="ticket-count" style="font-size:12px;color:var(--text3);">0 ticket(s) sélectionné(s)</span>
        <button type="submit" class="btn btn-success" id="btn-add-tickets" disabled><i class="fas fa-plus-circle"></i> Ajouter les tickets sélectionnés au bordereau</button>
      </div>
    </form>
  </div>
</div>
<script>
function toggleAllTickets(master) {
  document.querySelectorAll('.chk-ticket').forEach(cb => cb.checked = master.checked);
  updateTicketCount();
}
function updateTicketCount() {
  const checked = document.querySelectorAll('.chk-ticket:checked').length;
  document.getElementById('ticket-count').textContent = checked + ' ticket(s) sélectionné(s)';
  document.getElementById('btn-add-tickets').disabled = checked === 0;
}
</script>
<?php endif; ?>

<?php if ($peutAjouter): ?>
<!-- Modal ajout passager -->
<div class="modal-over" id="modal-ajout-passager">
  <div class="modal modal-lg">
    <div class="modal-head"><h3><i class="fas fa-user-plus"></i> Ajouter un passager à l'escale</h3><button class="modal-x" onclick="closeModal('modal-ajout-passager')">✕</button></div>
    <form method="POST" id="form-ajout-passager">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="ajouter_passager">
      <input type="hidden" name="vente_libre" id="ap-vente-libre" value="0">
      <div class="modal-body">
        <div style="background:var(--primary-bg);border-radius:var(--radius);padding:10px;margin-bottom:14px;font-size:12px;">
          <i class="fas fa-info-circle" style="color:var(--primary);"></i> Le passager sera ajouté au bordereau <strong><?= sanitize($bordereau['numero']) ?></strong> avec montée à <strong><?= sanitize($prochaineEscale['agence_ville'] ?? $prochaineEscale['agence_nom']) ?></strong>.
        </div>
        <div style="margin-bottom:14px;">
          <div class="tabs" style="margin-bottom:0;">
            <div class="tab active" id="tab-rattachee" onclick="apSetType('rattachee')"><i class="fas fa-bus"></i> Rattachée au voyage</div>
            <div class="tab" id="tab-libre" onclick="apSetType('libre')"><i class="fas fa-ticket-alt"></i> Vente libre</div>
          </div>
        </div>
        <div class="form-grid">
          <div class="fg">
            <label class="flbl">Classe <span class="freq">*</span></label>
            <select name="classe" id="ap-classe" class="fc" onchange="apCalcTarif()">
              <option value="cla">Classique</option>
              <option value="vip">VIP</option>
              <option value="spc">Spécial</option>
            </select>
          </div>

          <!-- Champs vente rattachée -->
          <div id="ap-rattachee-fields">
            <div class="fg">
              <label class="flbl">Montée</label>
              <select name="escale_montee_id" id="ap-montee" class="fc" onchange="apCalcTarif()">
                <option value="<?= $escaleMonteeId ?>"><?= sanitize($prochaineEscale['agence_ville'] ?? $prochaineEscale['agence_nom']) ?> (cette escale)</option>
              </select>
            </div>
            <div class="fg">
              <label class="flbl">Descente <span class="freq">*</span></label>
              <select name="escale_descente_id" id="ap-descente" class="fc" onchange="apCalcTarif()">
                <option value="">— Choisir —</option>
                <?php foreach ($escalesDescente as $ed): ?>
                <option value="<?= $ed['id'] ?>"><?= sanitize($ed['ville'] ?? $ed['agence_nom']) ?></option>
                <?php endforeach; ?>
                <?php if (empty($escalesDescente)): ?>
                <option value="">Aucune escale disponible</option>
                <?php endif; ?>
              </select>
            </div>
          </div>

          <!-- Champs vente libre -->
          <div id="ap-libre-fields" style="display:none;">
            <div class="fg">
              <label class="flbl">Destination <span class="freq">*</span></label>
              <select name="destination_id" id="ap-destination" class="fc" onchange="apCalcTarif()">
                <option value="">— Choisir —</option>
                <?php foreach ($destinations as $d): ?>
                <option value="<?= $d['id'] ?>"><?= sanitize($d['dep_nom']) ?> → <?= sanitize($d['arr_nom']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <input type="hidden" name="tarif_id" id="ap-tarif" value="">
          <div class="fg">
            <label class="flbl">Nom passager <span class="freq">*</span></label>
            <input type="text" name="passager_nom" id="ap-nom" class="fc" required placeholder="Nom Prénom" oninput="apAutoPassager()">
          </div>
          <div class="fg">
            <label class="flbl">Téléphone</label>
            <input type="tel" name="passager_tel" id="ap-tel" class="fc" placeholder="6XXXXXXXX">
          </div>
          <div class="fg">
            <label class="flbl">CNI</label>
            <input type="text" name="passager_cni" id="ap-cni" class="fc" placeholder="Numéro CNI">
          </div>
          <div class="fg">
            <label class="flbl">Siège</label>
            <input type="text" name="siege" id="ap-siege" class="fc" placeholder="Ex: A1">
          </div>
          <div class="fg">
            <label class="flbl">Montant (FCFA) <span class="freq">*</span> <span id="ap-montant-lock" style="display:none;font-size:10px;color:var(--text3);">(auto)</span></label>
            <input type="number" name="montant" id="ap-montant" class="fc" min="0" step="1" required onchange="apCalcTotal()">
          </div>
          <div class="fg">
            <label class="flbl">Type passager</label>
            <select name="type_passager" id="ap-type-passager" class="fc">
              <option value="adulte">Adulte</option>
              <option value="enfant">Enfant</option>
              <option value="bebe">Bébé</option>
            </select>
          </div>
          <div class="fg">
            <label class="flbl">Mode paiement</label>
            <select name="mode_paiement" id="ap-mode" class="fc">
              <option value="especes">Espèces</option>
              <option value="om">Orange Money</option>
              <option value="momo">MTN MoMo</option>
              <option value="carte">Carte</option>
            </select>
          </div>
          <div class="fg">
            <label class="flbl">Bagages (kg)</label>
            <input type="number" name="bagages_kg" id="ap-bagkg" class="fc" min="0" step="0.5" value="0">
          </div>
          <div class="fg">
            <label class="flbl">Montant bagages</label>
            <input type="number" name="bagages_montant" id="ap-bagmt" class="fc" min="0" step="1" value="0" onchange="apCalcTotal()">
          </div>
          <div class="fg">
            <label class="flbl">Somme perçue</label>
            <input type="number" name="somme_percu" id="ap-percu" class="fc" min="0" step="1" value="0" oninput="apCalcReliquat()">
          </div>
          <div class="fg" style="font-size:14px;font-weight:700;color:var(--success);display:flex;align-items:center;">
            Total : <span id="ap-total" style="margin-left:4px;">0</span> FCFA
            <span id="ap-reliquat" style="margin-left:12px;color:var(--text3);font-weight:400;font-size:12px;"></span>
          </div>
        </div>
      </div>
      <div class="modal-foot">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modal-ajout-passager')">Annuler</button>
        <button type="submit" class="btn btn-success"><i class="fas fa-plus"></i> Vendre le ticket</button>
      </div>
    </form>
  </div>
</div>
<script>
const apTarifs = <?= json_encode($tarifsItin) ?>;
const apDestTarifs = <?= json_encode($destTarifs) ?>;
const apEscales = <?= json_encode($escalesItin) ?>;
const apEscaleMontee = <?= $escaleMonteeId ?? 'null' ?>;
let apType = 'rattachee';

function apSetType(type) {
  apType = type;
  document.getElementById('tab-rattachee').classList.toggle('active', type === 'rattachee');
  document.getElementById('tab-libre').classList.toggle('active', type === 'libre');
  document.getElementById('ap-rattachee-fields').style.display = type === 'rattachee' ? '' : 'none';
  document.getElementById('ap-libre-fields').style.display = type === 'libre' ? '' : 'none';
  document.getElementById('ap-vente-libre').value = type === 'libre' ? '1' : '0';
  // Reset descent/destination
  document.getElementById('ap-descente').value = '';
  document.getElementById('ap-destination').value = '';
  // Lock/unlock montant based on type
  const mtInput = document.getElementById('ap-montant');
  const mtLock = document.getElementById('ap-montant-lock');
  if (type === 'rattachee') {
    mtInput.readOnly = true;
    mtInput.style.background = 'var(--bg)';
    mtLock.style.display = '';
  } else {
    mtInput.readOnly = false;
    mtInput.style.background = '';
    mtLock.style.display = 'none';
  }
  apCalcTarif();
}

function apCalcTarif() {
  const classe = document.getElementById('ap-classe').value;
  const tarifInput = document.getElementById('ap-tarif');
  const mtInput = document.getElementById('ap-montant');
  const mtLock = document.getElementById('ap-montant-lock');

  if (apType === 'libre') {
    // VENTE LIBRE : tarif basé sur destination + classe
    const destId = parseInt(document.getElementById('ap-destination').value) || 0;
    if (!destId) {
      tarifInput.value = '';
      mtInput.value = '';
      mtInput.readOnly = false;
      mtInput.style.background = '';
      mtLock.style.display = 'none';
      return;
    }
    const filtered = apDestTarifs.filter(t => {
      if (t.dest_id) return t.dest_id == destId && t.classe === classe;
      if (t.destination_id) return t.destination_id == destId && t.classe === classe;
      return false;
    });
    if (filtered.length > 0) {
      tarifInput.value = filtered[0].id;
      mtInput.value = filtered[0].prix;
      // En vente libre, laisser modifiable mais suggérer le prix
      mtInput.readOnly = false;
      mtInput.style.background = '';
      mtLock.style.display = 'none';
    } else {
      tarifInput.value = '';
      mtInput.value = '';
    }
  } else {
    // VENTE RATTACHÉE : tarif basé sur escale montée + descente + classe
    const montee = parseInt(document.getElementById('ap-montee').value) || 0;
    const descente = parseInt(document.getElementById('ap-descente').value) || 0;

    if (!descente) {
      tarifInput.value = '';
      mtInput.value = '';
      mtInput.readOnly = true;
      mtInput.style.background = 'var(--bg)';
      mtLock.style.display = '';
      return;
    }

    let filtered = apTarifs.filter(t => {
      if (t.escale_depart_id && t.escale_arrivee_id) {
        return t.escale_depart_id == montee && t.escale_arrivee_id == descente && t.classe === classe;
      }
      return false;
    });

    if (filtered.length === 0) {
      // Fallback: tarifs sans escale mais avec destination_id
      filtered = apTarifs.filter(t => t.destination_id && t.classe === classe);
    }

    if (filtered.length > 0) {
      tarifInput.value = filtered[0].id;
      mtInput.value = filtered[0].prix;
      // Verrouiller le montant en mode rattaché
      mtInput.readOnly = true;
      mtInput.style.background = 'var(--bg)';
      mtLock.style.display = '';
    } else {
      tarifInput.value = '';
      mtInput.value = '';
    }
  }
  apCalcTotal();
}

function apCalcTotal() {
  const mt = parseFloat(document.getElementById('ap-montant').value) || 0;
  const bagmt = parseFloat(document.getElementById('ap-bagmt').value) || 0;
  document.getElementById('ap-total').textContent = (mt + bagmt).toLocaleString('fr-FR');
  apCalcReliquat();
}

function apCalcReliquat() {
  const mt = parseFloat(document.getElementById('ap-montant').value) || 0;
  const bagmt = parseFloat(document.getElementById('ap-bagmt').value) || 0;
  const total = mt + bagmt;
  const percu = parseFloat(document.getElementById('ap-percu').value) || 0;
  const reliq = percu - total;
  const el = document.getElementById('ap-reliquat');
  if (percu > 0) {
    el.textContent = 'Reliquat : ' + reliq.toLocaleString('fr-FR') + ' FCFA';
    el.style.color = reliq >= 0 ? 'var(--success)' : 'var(--danger)';
  } else {
    el.textContent = '';
  }
}

function apAutoPassager() {
  const tel = document.getElementById('ap-tel').value.trim();
  const cni = document.getElementById('ap-cni').value.trim();
  if (!tel && !cni) return;
  fetch('<?= BASE_URL ?>modules/tickets/vente.php?ajax=passager&tel=' + encodeURIComponent(tel || cni))
    .then(r => r.json())
    .then(data => {
      if (data && data.id) {
        if (!document.getElementById('ap-nom').value) document.getElementById('ap-nom').value = (data.prenom ? data.prenom + ' ' : '') + data.nom;
        if (!document.getElementById('ap-tel').value && data.telephone) document.getElementById('ap-tel').value = data.telephone;
        if (!document.getElementById('ap-cni').value && data.cni) document.getElementById('ap-cni').value = data.cni;
      }
    }).catch(() => {});
}

// Initialisation : mode rattaché par défaut, calculer le tarif
apSetType('rattachee');
</script>
<?php endif; ?>
<?php endif; /* peutGererEscale */ ?>

<?php if ($bordereau['statut'] === 'en_cours' && !$peutGererEscale && !$peutConfirmer): ?>
<div class="card" style="margin-bottom:16px;border-left:4px solid var(--text3);">
  <div class="card-header"><h3><i class="fas fa-users"></i> Passagers du bordereau (<?= count($lignes) ?>)</h3></div>
  <div class="card-body" style="padding:0;">
    <div class="table-wrap">
      <table data-no-filter>
        <thead><tr><th>#</th><th>Siège</th><th>Passager</th><th>Destination</th><th>Classe</th><th>Montant</th></tr></thead>
        <tbody>
          <?php foreach ($lignes as $i => $l): ?>
          <tr>
            <td style="text-align:center;"><?= $i + 1 ?></td>
            <td><?= sanitize($l['siege'] ?? '—') ?></td>
            <td><?= sanitize($l['passager_nom']) ?></td>
            <td><?= sanitize($l['destination'] ?? '—') ?></td>
            <td><span class="badge <?= $l['classe'] === 'vip' ? 'badge-purple' : ($l['classe'] === 'spc' ? 'badge-teal' : 'badge-blue') ?>"><?= strtoupper($l['classe']) ?></span></td>
            <td style="font-weight:700;"><?= number_format($l['montant'], 0, ',', ' ') ?></td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($lignes)): ?>
          <tr><td colspan="6" class="t-empty"><i class="fas fa-users"></i> Aucun passager</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php endif; ?>

<div style="display:flex;gap:8px;">
  <a href="voir.php?id=<?= $id ?>" class="btn btn-secondary"><i class="fas fa-eye"></i> Voir le bordereau</a>
  <a href="." class="btn btn-ghost"><i class="fas fa-arrow-left"></i> Retour</a>
</div>

<?php include '../../includes/footer.php'; ?>