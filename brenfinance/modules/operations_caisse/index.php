<?php
require_once __DIR__ . '/../../includes/functions.php';
requireModuleAccess('operations_caisse');
$pageTitle = 'Opérations de Caisse';

$db = getDB();
$userId = $_SESSION['user_id'];
$user = currentUser();
$isCaissier = ($user['role_nom'] ?? '') === 'caissier';

// Only caissier can perform operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if (!$isCaissier && !hasPermission('operations_caisse', 'all') && !hasPermission('all', 'all')) {
        flash('danger', 'Seul un caissier peut effectuer des opérations de caisse.');
        header('Location: index.php'); exit;
    }

    // Caissier can only operate on their assigned caisse
    if ($isCaissier) {
        $cIdR = $db->prepare("SELECT id FROM caisses WHERE responsable_id=? LIMIT 1");
        $cIdR->execute([$user['id']]);
        $userCaisseId = $cIdR->fetchColumn();
        $postedCaisse = (int)($_POST['caisse_id'] ?? 0);
        if ($userCaisseId && $postedCaisse !== (int)$userCaisseId) {
            flash('danger', 'Vous n\'êtes pas autorisé à opérer sur cette caisse.');
            header('Location: index.php'); exit;
        }
    }

    if ($action === 'annuler_operation') {
        $caisseId = (int)$_POST['caisse_id'];
        $annuleOpId = (int)$_POST['annule_operation_id'];
        $libelle = trim($_POST['libelle'] ?? '');

        if (!$annuleOpId) {
            flash('danger', 'Veuillez sélectionner l\'opération à annuler.');
            header('Location: index.php?caisse='.$caisseId); exit;
        }

        // Verify the operation to cancel exists and belongs to this caisse
        $opR = $db->prepare("SELECT oc.*, to2.sens, to2.code FROM operations_caisse oc JOIN types_operations to2 ON oc.type_operation_id=to2.id WHERE oc.id=? AND oc.caisse_id=? AND oc.annule=0");
        $opR->execute([$annuleOpId, $caisseId]);
        $opToCancel = $opR->fetch();

        if (!$opToCancel) {
            flash('danger', 'Opération introuvable ou déjà annulée.');
            header('Location: index.php?caisse='.$caisseId); exit;
        }

        // Verify open session
        $sessR = $db->prepare("SELECT id FROM sessions_caisse WHERE caisse_id=? AND statut='ouverte' LIMIT 1");
        $sessR->execute([$caisseId]);
        $sess = $sessR->fetch();
        if (!$sess) { flash('danger', 'Ouvrez la caisse avant d\'annuler.'); header('Location: index.php?caisse='.$caisseId); exit; }

        // Get the ANNUL type
        $annulTypeR = $db->prepare("SELECT id, sens FROM types_operations WHERE code='ANNUL' AND statut='actif' LIMIT 1");
        $annulTypeR->execute();
        $annulType = $annulTypeR->fetch();
        if (!$annulType) {
            flash('danger', 'Type d\'opération "Annulation" non configuré.');
            header('Location: index.php?caisse='.$caisseId); exit;
        }

        $montant = (float)$opToCancel['montant'];

        // Get current caisse balance
        $caisseR = $db->prepare("SELECT solde_actuel FROM caisses WHERE id=?");
        $caisseR->execute([$caisseId]);
        $soldeCourant = $caisseR->fetch()['solde_actuel'];

        // Annulation = credit la caisse (balance comptable, on ne modifie pas l'opération d'origine)
        $soldeApres = $soldeCourant + $montant;
        $numPiece = generateNumero('PC');

        $motifAnnulation = $libelle ?: 'Annulation — ' . $opToCancel['numero_piece'];

        // Create the annulation operation (toujours credit, toujours espèces)
        $db->prepare("INSERT INTO operations_caisse (session_id,caisse_id,type_operation_id,beneficiaire_id,beneficiaire_nom,mode_paiement_id,numero_piece,date_operation,heure_operation,libelle,montant,sens,solde_apres,reference_externe,saisi_par) VALUES (?,?,?,?,?,?,?,NOW(),?,?,?,?,?,?,?)")
           ->execute([$sess['id'], $caisseId, $annulType['id'], $opToCancel['beneficiaire_id'], $opToCancel['beneficiaire_nom'], $modeEspecesId, $numPiece, date('Y-m-d'), $motifAnnulation, $montant, 'credit', $soldeApres, $opToCancel['reference_externe'] ?: null, $userId]);

        // Update caisse balance
        $db->prepare("UPDATE caisses SET solde_actuel=? WHERE id=?")->execute([$soldeApres, $caisseId]);

        auditLog('annulation_operation', 'operations_caisse', 'operations_caisse', $annuleOpId, null, ['motif' => $motifAnnulation, 'montant' => $montant]);
        flash('success', 'Annulation enregistrée. Pièce N° ' . $numPiece);
        header('Location: index.php?caisse='.$caisseId); exit;
    }

    if ($action === 'saisir_operation') {
        $caisseId = (int)$_POST['caisse_id'];
        $typeOpId = (int)$_POST['type_operation_id'];
        $groupeProprietaireId = !empty($_POST['groupe_proprietaire_id']) ? (int)$_POST['groupe_proprietaire_id'] : null;
        $montant  = abs((float)$_POST['montant']);
        $libelle  = trim($_POST['libelle']);
        $dateOp   = $_POST['date_operation'];
        $benNom   = trim($_POST['beneficiaire_nom'] ?? '');
        $benId    = !empty($_POST['beneficiaire_id']) ? (int)$_POST['beneficiaire_id'] : null;
        $modeId   = (int)$modeEspecesId;
        $destinationId = !empty($_POST['destination_id']) ? (int)$_POST['destination_id'] : null;
        $ref      = trim($_POST['reference_externe']??'');

        // Get session
        $sessR = $db->prepare("SELECT id FROM sessions_caisse WHERE caisse_id=? AND statut='ouverte' LIMIT 1");
        $sessR->execute([$caisseId]);
        $sess = $sessR->fetch();
        if (!$sess) { flash('danger','Ouvrez la caisse avant de saisir.'); header('Location: index.php?caisse='.$caisseId); exit; }

        // Get type sens
        $typeR = $db->prepare("SELECT sens, code FROM types_operations WHERE id=?");
        $typeR->execute([$typeOpId]);
        $type = $typeR->fetch();
        $sens = $type['sens'];

        // Get current balance
        $caisseR = $db->prepare("SELECT solde_actuel FROM caisses WHERE id=?");
        $caisseR->execute([$caisseId]);
        $caisse = $caisseR->fetch();
        $soldeCourant = $caisse['solde_actuel'];

        if ($sens === 'debit' && $montant > $soldeCourant) {
            flash('danger', 'Solde insuffisant en caisse.');
            header('Location: index.php?caisse='.$caisseId); exit;
        }

        $soldeApres = $sens === 'credit' ? $soldeCourant + $montant : $soldeCourant - $montant;
        $numPiece = generateNumero('PC');

        $db->prepare("INSERT INTO operations_caisse (session_id,caisse_id,type_operation_id,groupe_proprietaire_id,beneficiaire_id,beneficiaire_nom,destination_id,mode_paiement_id,numero_piece,date_operation,heure_operation,libelle,montant,sens,solde_apres,reference_externe,saisi_par) VALUES (?,?,?,?,?,?,?,?,?,NOW(),?,?,?,?,?,?,?)")
           ->execute([$sess['id'],$caisseId,$typeOpId,$groupeProprietaireId,$benId,$benNom,$destinationId,$modeId,$numPiece,$dateOp,$libelle,$montant,$sens,$soldeApres,$ref??null,$userId]);

        $db->prepare("UPDATE caisses SET solde_actuel=? WHERE id=?")->execute([$soldeApres,$caisseId]);

        auditLog('saisie_operation','operations_caisse','operations_caisse',null,null,['montant'=>$montant,'sens'=>$sens]);
        flash('success','Opération enregistrée. Pièce N° ' . $numPiece);
        header('Location: index.php?caisse='.$caisseId); exit;
    }

    if ($action === 'executer_engagement') {
        $engId = (int)$_POST['engagement_id'];
        $caisseId = (int)$_POST['caisse_id'];
        $montantExecution = abs((float)$_POST['montant_execution']);

        // Verify engagement
        $engR = $db->prepare("SELECT de.*, CONCAT(u.nom,' ',u.prenom) as demandeur_nom FROM demandes_engagement de JOIN utilisateurs u ON de.demandeur_id=u.id WHERE de.id=?");
        $engR->execute([$engId]);
        $eng = $engR->fetch();

        if (!$eng || !in_array($eng['statut'], ['approuve', 'execution_partielle'])) {
            flash('danger', 'Engagement non trouvé ou non exécutable.');
            header('Location: index.php?caisse=' . $caisseId); exit;
        }
        if ($eng['caisse_id'] && (int)$eng['caisse_id'] !== $caisseId) {
            flash('danger', 'Cet engagement est affecté à une autre caisse.');
            header('Location: index.php?caisse=' . $caisseId); exit;
        }

        // Calculate remaining amount
        $montantTotal = (float)$eng['montant'];
        $montantDejaExecute = (float)$eng['montant_execute'];
        $montantRestant = $montantTotal - $montantDejaExecute;

        if ($montantExecution <= 0) {
            flash('danger', 'Le montant d\'exécution doit être supérieur à 0.');
            header('Location: index.php?caisse=' . $caisseId); exit;
        }
        if ($montantExecution > $montantRestant) {
            flash('danger', 'Le montant d\'exécution (' . formatMontant($montantExecution) . ') dépasse le montant restant (' . formatMontant($montantRestant) . ').');
            header('Location: index.php?caisse=' . $caisseId); exit;
        }

        // Verify open session
        $sessR = $db->prepare("SELECT id FROM sessions_caisse WHERE caisse_id=? AND statut='ouverte' LIMIT 1");
        $sessR->execute([$caisseId]);
        $sess = $sessR->fetch();
        if (!$sess) { flash('danger', 'Ouvrez la caisse avant d\'exécuter un engagement.'); header('Location: index.php?caisse=' . $caisseId); exit; }

        // Verify balance
        $caisseR = $db->prepare("SELECT solde_actuel FROM caisses WHERE id=?");
        $caisseR->execute([$caisseId]);
        $caisseData = $caisseR->fetch();
        if ($caisseData['solde_actuel'] < $montantExecution) {
            flash('danger', 'Solde insuffisant. Solde: ' . formatMontant($caisseData['solde_actuel']) . ', Requis: ' . formatMontant($montantExecution));
            header('Location: index.php?caisse=' . $caisseId); exit;
        }

        // Use type_operation from engagement, or fall back to SOR_ESP
        if (!empty($eng['type_operation_id'])) {
            $typeOpId = (int)$eng['type_operation_id'];
        } else {
            $typeR = $db->prepare("SELECT id FROM types_operations WHERE code='SOR_ESP' AND statut='actif'");
            $typeR->execute();
            $typeOp = $typeR->fetch();
            $typeOpId = $typeOp ? $typeOp['id'] : null;
        }

        $soldeApres = $caisseData['solde_actuel'] - $montantExecution;
        $numPiece = generateNumero('BC');
        $benefNom = $eng['demandeur_nom'] ?? '';
        $nouveauMontantExecute = $montantDejaExecute + $montantExecution;
        $nouveauMontantRestant = $montantTotal - $nouveauMontantExecute;
        $nouveauStatut = ($nouveauMontantRestant <= 0) ? 'execute' : 'execution_partielle';

        // Build libelle indicating partial or full execution
        $libelle = $eng['objet'];
        if ($nouveauStatut === 'execution_partielle') {
            $pct = round(($nouveauMontantExecute / $montantTotal) * 100);
            $libelle .= ' — Exécution partielle (' . $pct . '%)';
        }

        // Create caisse operation
        $db->prepare("INSERT INTO operations_caisse (session_id,caisse_id,type_operation_id,groupe_proprietaire_id,beneficiaire_id,beneficiaire_nom,destination_id,mode_paiement_id,numero_piece,date_operation,heure_operation,libelle,montant,sens,solde_apres,reference_externe,engagement_id,saisi_par) VALUES (?,?,?,?,?,?,?,?,?,NOW(),?,?,?,?,?,?,?,?)")
           ->execute([$sess['id'], $caisseId, $typeOpId, $eng['groupe_proprietaire_id']??null, $eng['beneficiaire_id'], $benefNom, $eng['destination_id']??null, $modeEspecesId, $numPiece, date('Y-m-d'), $libelle, $montantExecution, 'debit', $soldeApres, $eng['numero'], $engId, $userId]);

        // Update caisse balance
        $db->prepare("UPDATE caisses SET solde_actuel=? WHERE id=?")->execute([$soldeApres, $caisseId]);

        // Update engagement progress
        $db->prepare("UPDATE demandes_engagement SET montant_execute=?, montant_restant=?, statut=? WHERE id=?")
           ->execute([$nouveauMontantExecute, $nouveauMontantRestant, $nouveauStatut, $engId]);

        // Record validation step
        $etapeLabel = $nouveauStatut === 'execute' ? 'execution' : 'execution_partielle';
        $db->prepare("INSERT INTO validations_engagement (engagement_id,etape,valideur_id,action,commentaire) VALUES (?,?,?,?,?)")
           ->execute([$engId, $etapeLabel, $userId, 'approuve', 'Exécution ' . ($nouveauStatut === 'execute' ? 'complète' : 'partielle') . ' — Pièce ' . $numPiece . ' — ' . formatMontant($montantExecution)]);

        auditLog('executer_engagement','operations_caisse','demandes_engagement',$engId,null,['numero'=>$eng['numero'],'montant'=>$montantExecution,'montant_execute'=>$nouveauMontantExecute,'montant_restant'=>$nouveauMontantRestant,'statut'=>$nouveauStatut]);

        // Notify demandeur
        $demR = $db->prepare("SELECT demandeur_id FROM demandes_engagement WHERE id=?");
        $demR->execute([$engId]);
        $demandeurId = (int)$demR->fetchColumn();
        if ($demandeurId) {
            if ($nouveauStatut === 'execute') {
                notify($demandeurId, $engId, 'execute', 'Engagement exécuté', 'Votre engagement ' . $eng['numero'] . ' a été entièrement exécuté. Pièce N° ' . $numPiece . '.');
            } else {
                notify($demandeurId, $engId, 'execute', 'Engagement partiellement exécuté', 'Votre engagement ' . $eng['numero'] . ' a été partiellement exécuté. Montant versé: ' . formatMontant($montantExecution) . '. Restant: ' . formatMontant($nouveauMontantRestant) . '.');
            }
        }

        $msgSuffix = $nouveauStatut === 'execute' ? 'entièrement exécuté' : 'partiellement exécuté (' . formatMontant($nouveauMontantExecute) . ' / ' . formatMontant($montantTotal) . ')';
        flash('success', 'Engagement ' . $eng['numero'] . ' ' . $msgSuffix . '. Bon de caisse N° ' . $numPiece);
        header('Location: index.php?caisse=' . $caisseId . '&bon=' . $db->lastInsertId());
        exit;
    }

    if ($action === 'solder_engagement') {
        $engId = (int)$_POST['engagement_id'];
        $caisseId = (int)$_POST['caisse_id'];
        $motifSolder = trim($_POST['motif_solder'] ?? '');

        if (empty($motifSolder)) {
            flash('danger', 'Le motif de solder est obligatoire.');
            header('Location: index.php?caisse=' . $caisseId); exit;
        }

        $engR = $db->prepare("SELECT de.*, CONCAT(u.nom,' ',u.prenom) as demandeur_nom FROM demandes_engagement de JOIN utilisateurs u ON de.demandeur_id=u.id WHERE de.id=?");
        $engR->execute([$engId]);
        $eng = $engR->fetch();

        if (!$eng || !in_array($eng['statut'], ['approuve', 'execution_partielle'])) {
            flash('danger', 'Engagement non trouvé ou ne peut pas être soldé.');
            header('Location: index.php?caisse=' . $caisseId); exit;
        }

        $montantRestant = (float)$eng['montant'] - (float)$eng['montant_execute'];

        $db->prepare("UPDATE demandes_engagement SET statut='solde', montant_restant=0, motif_solder=?, date_solder=NOW() WHERE id=?")
           ->execute([$motifSolder, $engId]);

        $db->prepare("INSERT INTO validations_engagement (engagement_id,etape,valideur_id,action,commentaire) VALUES (?,?,?,?,?)")
           ->execute([$engId, 'solder', $userId, 'approuve', 'Soldé — Motif: ' . $motifSolder . ' — Montant soldé: ' . formatMontant($montantRestant)]);

        auditLog('solder_engagement','operations_caisse','demandes_engagement',$engId,null,['numero'=>$eng['numero'],'montant_solde'=>$montantRestant,'motif'=>$motifSolder]);

        $demR = $db->prepare("SELECT demandeur_id FROM demandes_engagement WHERE id=?");
        $demR->execute([$engId]);
        $demandeurId = (int)$demR->fetchColumn();
        if ($demandeurId) {
            notify($demandeurId, $engId, 'execute', 'Engagement soldé', 'Votre engagement ' . $eng['numero'] . ' a été soldé. Montant non exécuté: ' . formatMontant($montantRestant) . '. Motif: ' . $motifSolder);
        }

        flash('success', 'Engagement ' . $eng['numero'] . ' soldé. Montant soldé: ' . formatMontant($montantRestant));
        header('Location: index.php?caisse=' . $caisseId);
        exit;
    }
}

// Data
$userCaisseId = null;
if ($isCaissier) {
    $cIdR = $db->prepare("SELECT id FROM caisses WHERE responsable_id=? LIMIT 1");
    $cIdR->execute([$user['id']]);
    $userCaisseId = $cIdR->fetchColumn();
}

if ($isCaissier && $userCaisseId) {
    $caissesR = $db->prepare("SELECT c.*, u.prenom, u.nom, (SELECT id FROM sessions_caisse WHERE caisse_id=c.id AND statut='ouverte' LIMIT 1) as session_id FROM caisses c LEFT JOIN utilisateurs u ON c.responsable_id=u.id WHERE c.id=?");
    $caissesR->execute([$userCaisseId]);
    $caisses = $caissesR->fetchAll();
} else {
    $caisses = $db->query("SELECT c.*, u.prenom, u.nom, (SELECT id FROM sessions_caisse WHERE caisse_id=c.id AND statut='ouverte' LIMIT 1) as session_id FROM caisses c LEFT JOIN utilisateurs u ON c.responsable_id=u.id ORDER BY c.libelle")->fetchAll();
}
$typesOps = $db->query("SELECT * FROM types_operations WHERE statut='actif' AND categorie='caisse' ORDER BY libelle")->fetchAll();
$modesPaiement = $db->query("SELECT * FROM modes_paiement WHERE statut='actif'")->fetchAll();
$modeEspecesId = $db->query("SELECT id FROM modes_paiement WHERE code='ESP' AND statut='actif' LIMIT 1")->fetchColumn() ?: 1;
try { $destinations = $db->query("SELECT * FROM destinations WHERE statut='actif' ORDER BY libelle")->fetchAll(); } catch(PDOException $e) { $destinations = []; }
$groupesProprietaires = $db->query("SELECT * FROM groupes_proprietaires WHERE statut='actif' ORDER BY libelle")->fetchAll();

// Selected caisse — caissier is forced to their assigned caisse
$selectedCaisse = null;
if ($isCaissier && $userCaisseId) {
    $cR = $db->prepare("SELECT c.*, (SELECT id FROM sessions_caisse WHERE caisse_id=c.id AND statut='ouverte' LIMIT 1) as session_id FROM caisses c WHERE c.id=?");
    $cR->execute([$userCaisseId]);
    $selectedCaisse = $cR->fetch();
} elseif (!empty($_GET['caisse'])) {
    $cR = $db->prepare("SELECT c.*, (SELECT id FROM sessions_caisse WHERE caisse_id=c.id AND statut='ouverte' LIMIT 1) as session_id FROM caisses c WHERE c.id=?");
    $cR->execute([(int)$_GET['caisse']]);
    $selectedCaisse = $cR->fetch();
}

// Approved engagements for selected caisse
$engagementsDisponibles = [];
if ($selectedCaisse) {
    $engR = $db->prepare("SELECT de.*, CONCAT(u.nom,' ',u.prenom) as demandeur_nom, mp.libelle as mode_nom FROM demandes_engagement de JOIN utilisateurs u ON de.demandeur_id=u.id LEFT JOIN modes_paiement mp ON de.mode_paiement_id=mp.id WHERE de.statut IN ('approuve','execution_partielle') AND (de.caisse_id=? OR de.caisse_id IS NULL) ORDER BY de.date_besoin ASC");
    $engR->execute([$selectedCaisse['id']]);
    $engagementsDisponibles = $engR->fetchAll();
}

// Bon de caisse view
$bonData = null;
if (!empty($_GET['bon'])) {
    $bonR = $db->prepare("SELECT oc.*, de.numero as eng_numero, de.objet as eng_objet, to2.libelle as type_operation_nom, mp.libelle as mode_nom, c.libelle as caisse_nom, c.code as caisse_code, CONCAT(u.nom,' ',u.prenom) as caissier_nom FROM operations_caisse oc JOIN demandes_engagement de ON oc.engagement_id=de.id LEFT JOIN types_operations to2 ON oc.type_operation_id=to2.id LEFT JOIN modes_paiement mp ON oc.mode_paiement_id=mp.id JOIN caisses c ON oc.caisse_id=c.id JOIN utilisateurs u ON oc.saisi_par=u.id WHERE oc.id=?");
    $bonR->execute([(int)$_GET['bon']]);
    $bonData = $bonR->fetch();
}

// Print single operation view
$printData = null;
if (!empty($_GET['print']) && !$bonData) {
    $printR = $db->prepare("SELECT oc.*, to2.libelle as type_nom, to2.sens, mp.libelle as mode_nom, c.libelle as caisse_nom, c.code as caisse_code, CONCAT(u.nom,' ',u.prenom) as caissier_nom FROM operations_caisse oc JOIN types_operations to2 ON oc.type_operation_id=to2.id LEFT JOIN modes_paiement mp ON oc.mode_paiement_id=mp.id JOIN caisses c ON oc.caisse_id=c.id JOIN utilisateurs u ON oc.saisi_par=u.id WHERE oc.id=?");
    $printR->execute([(int)$_GET['print']]);
    $printData = $printR->fetch();
}

// Print engagement view
$printEngData = null;
$printEngLignes = [];
if (!empty($_GET['print_eng']) && !$bonData && !$printData) {
    $printEngR = $db->prepare("SELECT de.*, CONCAT(u.nom,' ',u.prenom) as demandeur_nom, s.nom as service_nom, c.libelle as caisse_nom, f.nom as fournisseur_nom, b.nom as beneficiaire_nom, mp.libelle as mode_nom, to2.libelle as type_operation_nom, gp.libelle as groupe_proprietaire_nom, dst.libelle as destination_nom FROM demandes_engagement de JOIN utilisateurs u ON de.demandeur_id=u.id JOIN services s ON de.service_id=s.id LEFT JOIN caisses c ON de.caisse_id=c.id LEFT JOIN fournisseurs f ON de.fournisseur_id=f.id LEFT JOIN beneficiaires b ON de.beneficiaire_id=b.id LEFT JOIN modes_paiement mp ON de.mode_paiement_id=mp.id LEFT JOIN types_operations to2 ON de.type_operation_id=to2.id LEFT JOIN groupes_proprietaires gp ON de.groupe_proprietaire_id=gp.id LEFT JOIN destinations dst ON de.destination_id=dst.id WHERE de.id=?");
    $printEngR->execute([(int)$_GET['print_eng']]);
    $printEngData = $printEngR->fetch();
    if ($printEngData) {
        $lignesPR = $db->prepare("SELECT * FROM lignes_engagement WHERE engagement_id=? ORDER BY ordre");
        $lignesPR->execute([(int)$_GET['print_eng']]);
        $printEngLignes = $lignesPR->fetchAll();

        // Fetch validator names from validations_engagement
        $valSigR = $db->prepare("SELECT ve.etape, CONCAT(u.nom,' ',u.prenom) as valideur_nom FROM validations_engagement ve JOIN utilisateurs u ON ve.valideur_id=u.id WHERE ve.engagement_id=? AND ve.action='approuve' ORDER BY ve.date_validation");
        $valSigR->execute([(int)$_GET['print_eng']]);
        $printEngSignataires = [];
        foreach ($valSigR->fetchAll() as $vs) {
            $printEngSignataires[$vs['etape']] = $vs['valideur_nom'];
        }

        // Responsable hierarchique = responsable of demandeur's service
        $respSigR = $db->prepare("SELECT CONCAT(u.nom,' ',u.prenom) as responsable_nom FROM services s LEFT JOIN utilisateurs u ON s.responsable_id=u.id WHERE s.id=?");
        $respSigR->execute([$printEngData['service_id']]);
        $printEngRespNom = $respSigR->fetchColumn();
    }
}

// Operations history — with date & type filters
$ops = [];
$filterDateFrom = trim($_GET['date_from'] ?? date('Y-m-01'));
$filterDateTo = trim($_GET['date_to'] ?? date('Y-m-t'));
$filterType = (int)($_GET['type_op'] ?? 0);
$filterSens = trim($_GET['sens'] ?? '');

if ($selectedCaisse && !$bonData && !$printData && !$printEngData) {
    $opsWhere = ['oc.caisse_id=?', 'oc.annule=0'];
    $opsParams = [$selectedCaisse['id']];

    if ($filterDateFrom) {
        $opsWhere[] = 'oc.date_operation >= ?';
        $opsParams[] = $filterDateFrom;
    }
    if ($filterDateTo) {
        $opsWhere[] = 'oc.date_operation <= ?';
        $opsParams[] = $filterDateTo;
    }
    if ($filterType) {
        $opsWhere[] = 'oc.type_operation_id = ?';
        $opsParams[] = $filterType;
    }
    if ($filterSens) {
        $opsWhere[] = 'to2.sens = ?';
        $opsParams[] = $filterSens;
    }

    $opsSql = "SELECT oc.*, to2.libelle as type_nom, to2.sens, mp.libelle as mode_nom, dst.libelle as destination_nom, CONCAT(u.nom,' ',u.prenom) as caissier, de.numero as eng_numero, de.objet as eng_objet, de.statut as eng_statut, de.priorite as eng_priorite, de.date_besoin as eng_date_besoin FROM operations_caisse oc JOIN types_operations to2 ON oc.type_operation_id=to2.id LEFT JOIN modes_paiement mp ON oc.mode_paiement_id=mp.id LEFT JOIN destinations dst ON oc.destination_id=dst.id JOIN utilisateurs u ON oc.saisi_par=u.id LEFT JOIN demandes_engagement de ON oc.engagement_id=de.id WHERE " . implode(' AND ', $opsWhere) . " ORDER BY oc.created_at DESC LIMIT 200";
    $opsR = $db->prepare($opsSql);
    $opsR->execute($opsParams);
    $ops = $opsR->fetchAll();
}

// AJAX: search operation by numero_piece for annulation
if (!empty($_GET['ajax_search_op']) && !empty($_GET['q'])) {
    $searchTerm = trim($_GET['q']);
    $caisseIdFilter = (int)($_GET['caisse_id'] ?? 0);
    $opR = $db->prepare("SELECT oc.*, to2.libelle as type_nom, to2.code as type_code, to2.sens, mp.libelle as mode_nom FROM operations_caisse oc JOIN types_operations to2 ON oc.type_operation_id=to2.id LEFT JOIN modes_paiement mp ON oc.mode_paiement_id=mp.id WHERE oc.numero_piece LIKE ? AND oc.annule=0 AND oc.caisse_id=? ORDER BY oc.created_at DESC LIMIT 10");
    $opR->execute(['%' . $searchTerm . '%', $caisseIdFilter]);
    $results = $opR->fetchAll(PDO::FETCH_ASSOC);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($results, JSON_UNESCAPED_UNICODE);
    exit;
}

include __DIR__ . '/../../includes/header.php';
?>

<?php if ($bonData): ?>
<!-- Bon de Caisse — Print View -->
<?php $entBon = getEntreprise(); ?>
<div class="bon-caisse" id="bon-caisse">
  <div class="bon-header">
    <div class="bon-logo">
      <?php if (!empty($entBon['logo'])): ?>
      <img src="<?= htmlspecialchars($entBon['logo']) ?>" alt="<?= htmlspecialchars($entBon['nom']??'') ?>">
      <?php else: ?>
      <?= htmlspecialchars($entBon['sigle'] ?? '₣') ?>
      <?php endif; ?>
    </div>
    <div class="bon-title">
      <strong><?= htmlspecialchars($entBon['nom'] ?? 'BON DE CAISSE') ?></strong><br>
      <?php if (!empty($entBon['registre_commerce']) || !empty($entBon['numero_contribuable'])): ?>
      <span style="font-size:11px;color:#fff;opacity:.75;display:flex;gap:16px">
        <?php if (!empty($entBon['registre_commerce'])): ?><span>RC : <?= sanitize($entBon['registre_commerce']) ?></span><?php endif; ?>
        <?php if (!empty($entBon['numero_contribuable'])): ?><span>N° Contribuable : <?= sanitize($entBon['numero_contribuable']) ?></span><?php endif; ?>
      </span>
      <?php endif; ?>
    </div>
    <div class="bon-logo" style="visibility:hidden"><?= htmlspecialchars($entBon['sigle'] ?? '₣') ?></div>
  </div>
  <div class="bon-body">
    <table class="bon-table">
      <tr><td class="bon-label">N° Bon</td><td class="bon-value"><code><?= sanitize($bonData['numero_piece']) ?></code></td></tr>
      <tr><td class="bon-label">Date</td><td class="bon-value"><?= date('d/m/Y H:i', strtotime($bonData['date_operation'])) ?></td></tr>
      <tr><td class="bon-label">N° Engagement</td><td class="bon-value"><code><?= sanitize($bonData['eng_numero']) ?></code></td></tr>
      <tr><td class="bon-label">Objet</td><td class="bon-value"><?= sanitize($bonData['eng_objet']) ?></td></tr>
      <tr><td class="bon-label">Type d'opération</td><td class="bon-value"><?= sanitize($bonData['type_operation_nom']??'—') ?></td></tr>
      <tr><td class="bon-label"><?php echo ($bonData['sens']??'debit')==='credit' ? 'Remettant' : 'Bénéficiaire' ?></td><td class="bon-value fw-bold"><?= sanitize($bonData['beneficiaire_nom'] ?? '—') ?></td></tr>
      <tr><td class="bon-label">Mode de paiement</td><td class="bon-value"><?= sanitize($bonData['mode_nom']??'—') ?></td></tr>
      <tr class="bon-total-row"><td class="bon-label">Montant en Chiffre</td><td class="bon-value fw-bold" style="font-size:20px"><?= formatMontant($bonData['montant']) ?></td></tr>
      <tr class="bon-total-row"><td class="bon-label">Montant en Lettre</td><td class="bon-value fw-bold" style="font-size:20px"><?= ucfirst(montantEnLettres((float)$bonData['montant'], 'FCFA', 'centimes')) ?></td></tr>
    </table>
  </div>
  <div class="bon-signatures">
    <div class="bon-sig-block">
      <div class="bon-sig-label">Le Caissier</div>
      <div class="bon-sig-line"></div>
      <div class="bon-sig-name"><?= sanitize($bonData['caissier_nom']) ?></div>
    </div>
    <div class="bon-sig-block">
      <div class="bon-sig-label"><?php echo ($bonData['sens']??'debit')==='credit' ? 'Le Remettant' : 'Le Bénéficiaire' ?></div>
      <div class="bon-sig-line"></div>
      <div class="bon-sig-name"><?= sanitize($bonData['beneficiaire_nom'] ?? '') ?></div>
    </div>
  </div>
  <div style="text-align:center;margin-top:16px">
    <div id="qr-bon"></div>
  </div>
</div>
<script src="<?= BASE_URL ?>/assets/js/qrcode.min.js"></script>
<script>
new QRCode(document.getElementById('qr-bon'), {
  text: "N° : <?= sanitize($bonData['numero_piece']) ?>\nDate : <?= date('d/m/Y H:i', strtotime($bonData['date_operation'])) ?>\nCaissier : <?= sanitize($bonData['caissier_nom']) ?>\n<?= ($bonData['sens']??'debit')==='credit' ? 'Remettant' : 'Bénéficiaire' ?> : <?= sanitize($bonData['beneficiaire_nom'] ?? '') ?>\nMontant : <?= formatMontant($bonData['montant']) ?>",
  width: 90,
  height: 90,
  correctLevel: QRCode.CorrectLevel.M
});
</script>
<div class="bon-actions no-print" style="text-align:center;margin:20px 0">
  <button class="btn btn-primary" onclick="window.print()"><i class="fa-solid fa-print"></i> Imprimer le bon</button>
  <a href="index.php?caisse=<?= $selectedCaisse['id']??'' ?>" class="btn btn-outline" style="margin-left:8px"><i class="fa-solid fa-arrow-left"></i> Retour</a>
</div>

<?php elseif ($printData): ?>
<!-- Piece de Caisse — Print View -->
<?php $entPrint = getEntreprise(); ?>
<div class="bon-caisse" id="bon-caisse">
  <div class="bon-header">
    <div class="bon-logo">
      <?php if (!empty($entPrint['logo'])): ?>
      <img src="<?= htmlspecialchars($entPrint['logo']) ?>" alt="<?= htmlspecialchars($entPrint['nom']??'') ?>">
      <?php else: ?>
      <?= htmlspecialchars($entPrint['sigle'] ?? '₣') ?>
      <?php endif; ?>
    </div>
    <div class="bon-title">
      <strong><?= htmlspecialchars($entPrint['nom'] ?? '') ?></strong><br>
      <?php if (!empty($entPrint['registre_commerce']) || !empty($entPrint['numero_contribuable'])): ?>
      <span style="font-size:11px;color:#fff;opacity:.75;display:flex;gap:16px">
        <?php if (!empty($entPrint['registre_commerce'])): ?><span>RC : <?= sanitize($entPrint['registre_commerce']) ?></span><?php endif; ?>
        <?php if (!empty($entPrint['numero_contribuable'])): ?><span>N° Contribuable : <?= sanitize($entPrint['numero_contribuable']) ?></span><?php endif; ?>
      </span>
      <?php endif; ?>
    </div>
    <div class="bon-logo" style="visibility:hidden"><?= htmlspecialchars($entPrint['sigle'] ?? '₣') ?></div>
  </div>
  <div class="bon-body">
    <table class="bon-table">
      <tr><td class="bon-label">N° Pièce</td><td class="bon-value"><code><?= sanitize($printData['numero_piece']) ?></code></td></tr>
      <tr><td class="bon-label">Date</td><td class="bon-value"><?= date('d/m/Y H:i', strtotime($printData['date_operation'].' '.$printData['heure_operation'])) ?></td></tr>
      <tr><td class="bon-label">Type</td><td class="bon-value"><?= sanitize($printData['type_nom']) ?></td></tr>
      <tr><td class="bon-label">Libellé</td><td class="bon-value"><?= sanitize($printData['libelle']) ?></td></tr>
      <tr><td class="bon-label"><?php echo $printData['sens']==='credit' ? 'Remettant' : 'Bénéficiaire' ?></td><td class="bon-value fw-bold"><?= sanitize($printData['beneficiaire_nom'] ?? '—') ?></td></tr>
      <tr><td class="bon-label">Mode de paiement</td><td class="bon-value"><?= sanitize($printData['mode_nom']??'—') ?></td></tr>
      <tr><td class="bon-label">Sens</td><td class="bon-value"><span class="badge badge-<?= $printData['sens']==='credit'?'success':'danger' ?>"><?= $printData['sens']==='credit'?'Crédit':'Débit' ?></span></td></tr>
      <tr class="bon-total-row"><td class="bon-label">Montant en Chiffre</td><td class="bon-value fw-bold" style="font-size:20px"><?= formatMontant($printData['montant']) ?></td></tr>
      <tr class="bon-total-row"><td class="bon-label">Montant en Lettre</td><td class="bon-value fw-bold" style="font-size:20px"><?= ucfirst(montantEnLettres((float)$printData['montant'], 'FCFA', 'centimes')) ?></td></tr>
    </table>
  </div>
  <div class="bon-signatures">
    <div class="bon-sig-block">
      <div class="bon-sig-label">Le Caissier</div>
      <div class="bon-sig-line"></div>
      <div class="bon-sig-name"><?= sanitize($printData['caissier_nom']) ?></div>
    </div>
    <div class="bon-sig-block">
      <div class="bon-sig-label"><?= $printData['sens']==='credit' ? 'Le Remettant' : 'Le Bénéficiaire' ?></div>
      <div class="bon-sig-line"></div>
      <div class="bon-sig-name"><?= sanitize($printData['beneficiaire_nom'] ?? '') ?></div>
    </div>
  </div>
  <div style="text-align:center;margin-top:16px">
    <div id="qr-piece"></div>
  </div>
</div>
<script src="<?= BASE_URL ?>/assets/js/qrcode.min.js"></script>
<script>
new QRCode(document.getElementById('qr-piece'), {
  text: "N° : <?= sanitize($printData['numero_piece']) ?>\nDate : <?= date('d/m/Y H:i', strtotime($printData['date_operation'].' '.$printData['heure_operation'])) ?>\nCaissier : <?= sanitize($printData['caissier_nom']) ?>\n<?php echo $printData['sens']==='credit' ? 'Remettant' : 'Bénéficiaire' ?> : <?= sanitize($printData['beneficiaire_nom'] ?? '') ?>\nMontant : <?= formatMontant($printData['montant']) ?>",
  width: 90,
  height: 90,
  correctLevel: QRCode.CorrectLevel.M
});
</script>
<div class="bon-actions no-print" style="text-align:center;margin:20px 0">
  <button class="btn btn-primary" onclick="window.print()"><i class="fa-solid fa-print"></i> Imprimer la pièce</button>
  <a href="index.php?caisse=<?= $printData['caisse_id'] ?? '' ?>" class="btn btn-outline" style="margin-left:8px"><i class="fa-solid fa-arrow-left"></i> Retour</a>
</div>

<?php elseif ($printEngData): ?>
<!-- Engagement — Print View -->
<?php $entEng = getEntreprise(); ?>
<div class="bon-caisse" id="bon-caisse">
  <div class="bon-header">
    <div class="bon-logo">
      <?php if (!empty($entEng['logo'])): ?>
      <img src="<?= htmlspecialchars($entEng['logo']) ?>" alt="<?= htmlspecialchars($entEng['nom']??'') ?>">
      <?php else: ?>
      <?= htmlspecialchars($entEng['sigle'] ?? '₣') ?>
      <?php endif; ?>
    </div>
    <div class="bon-title">
      <strong>ENGAGEMENT N° <?= sanitize($printEngData['numero']) ?></strong><br>
      <span style="font-size:12px;opacity:.85"><?= htmlspecialchars($entEng['nom'] ?? '') ?></span><br>
      <?php if (!empty($entEng['registre_commerce']) || !empty($entEng['numero_contribuable'])): ?>
      <span style="font-size:11px;color:#fff;opacity:.75;display:flex;gap:16px">
        <?php if (!empty($entEng['registre_commerce'])): ?><span>RC : <?= sanitize($entEng['registre_commerce']) ?></span><?php endif; ?>
        <?php if (!empty($entEng['numero_contribuable'])): ?><span>N° Contribuable : <?= sanitize($entEng['numero_contribuable']) ?></span><?php endif; ?>
      </span>
      <?php endif; ?>
    </div>
    <div class="bon-logo" style="visibility:hidden"><?= htmlspecialchars($entEng['sigle'] ?? '₣') ?></div>
  </div>
  <div class="bon-body">
    <table class="bon-table">
      <tr><td class="bon-label">N° Engagement</td><td class="bon-value"><code><?= sanitize($printEngData['numero']) ?></code></td><td class="bon-label" style="padding-left:16px">Date de création</td><td class="bon-value"><?= date('d/m/Y H:i', strtotime($printEngData['created_at'])) ?></td></tr>
      <tr><td class="bon-label">Demandeur</td><td class="bon-value"><?= sanitize($printEngData['demandeur_nom']) ?></td><td class="bon-label" style="padding-left:16px">Service</td><td class="bon-value"><?= sanitize($printEngData['service_nom']) ?></td></tr>
      <tr><td class="bon-label">Type d'opération</td><td class="bon-value"><?= sanitize($printEngData['type_operation_nom'] ?? '—') ?></td><td class="bon-label" style="padding-left:16px">Destination</td><td class="bon-value"><?= sanitize($printEngData['destination_nom'] ?? '—') ?></td></tr>
      <tr><td class="bon-label">Bénéficiaire</td><td class="bon-value fw-bold"><?= sanitize($printEngData['beneficiaire_nom'] ?? '—') ?></td><td class="bon-label" style="padding-left:16px">Mode de paiement</td><td class="bon-value"><?= sanitize($printEngData['mode_nom'] ?? '—') ?></td></tr>
    </table>
    <?php if (!empty($printEngLignes)): ?>
    <div style="margin-top:16px">
      <div style="font-weight:700;font-size:14px;margin-bottom:8px;border-bottom:2px solid var(--primary);padding-bottom:4px">Détail des dépenses</div>
      <table style="width:100%;font-size:13px;border-collapse:collapse">
        <thead>
          <tr style="border-bottom:2px solid var(--border)">
            <th style="padding:6px 8px;text-align:center;width:40px">N°</th>
            <th style="padding:6px 8px;text-align:left">Libellé</th>
            <th style="padding:6px 8px;text-align:right;width:80px">Qté</th>
            <th style="padding:6px 8px;text-align:right;width:110px">Coût unitaire</th>
            <th style="padding:6px 8px;text-align:right;width:110px">Montant</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($printEngLignes as $i => $l): ?>
          <tr style="border-bottom:1px solid var(--border)">
            <td style="padding:6px 8px;text-align:center"><?= $i + 1 ?></td>
            <td style="padding:6px 8px"><?= sanitize($l['libelle']) ?></td>
            <td style="padding:6px 8px;text-align:right"><?= number_format($l['quantite'], 2, ',', ' ') ?></td>
            <td style="padding:6px 8px;text-align:right"><?= number_format($l['cout_unitaire'], 0, ',', ' ') ?></td>
            <td style="padding:6px 8px;text-align:right;font-weight:600"><?= number_format($l['montant'], 0, ',', ' ') ?></td>
          </tr>
          <?php endforeach; ?>
          <tr style="border-top:2px solid var(--border)">
            <td colspan="4" style="padding:8px;text-align:right;font-weight:700">Total</td>
            <td style="padding:8px;text-align:right;font-weight:700;font-size:15px"><?= number_format($printEngData['montant'], 0, ',', ' ') ?></td>
          </tr>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
    <table class="bon-table" style="margin-top:12px">
      <tr class="bon-total-row"><td class="bon-label">Montant en Chiffre</td><td class="bon-value fw-bold" style="font-size:20px"><?= formatMontant($printEngData['montant']) ?></td></tr>
      <tr><td class="bon-label">Montant en Lettre</td><td class="bon-value fw-bold" style="font-size:20px"><?= ucfirst(montantEnLettres((float)$printEngData['montant'], 'FCFA', 'centimes')) ?></td></tr>
    </table>
  </div>
  <div style="padding:24px 0;margin-top:24px">
    <div style="display:flex;justify-content:space-between;text-align:center">
      <div style="flex:1;font-size:12px;color:var(--text3)">Le Demandeur</div>
      <div style="flex:1;font-size:12px;color:var(--text3)">Le Responsable Hiérarchique</div>
      <div style="flex:1;font-size:12px;color:var(--text3)">Le DC</div>
      <div style="flex:1;font-size:12px;color:var(--text3)">Le DAF</div>
    </div>
    <div style="border-top:1px solid var(--text3);margin-top:60px"></div>
    <div style="display:flex;justify-content:space-between;text-align:center;margin-top:4px">
      <div style="flex:1;font-size:12px;font-weight:600"><?= sanitize($printEngData['demandeur_nom']) ?></div>
      <div style="flex:1;font-size:12px;font-weight:600"><?= sanitize($printEngRespNom ?: '—') ?></div>
      <div style="flex:1;font-size:12px;font-weight:600"><?= sanitize($printEngSignataires['comptable'] ?? '—') ?></div>
      <div style="flex:1;font-size:12px;font-weight:600"><?= sanitize($printEngSignataires['daf'] ?? '—') ?></div>
    </div>
  </div>
  <div style="text-align:center;margin-top:16px">
    <div id="qr-engagement"></div>
  </div>
</div>
<script src="<?= BASE_URL ?>/assets/js/qrcode.min.js"></script>
<script>
new QRCode(document.getElementById('qr-engagement'), {
  text: "Engagement N° : <?= sanitize($printEngData['numero']) ?>\nDate : <?= date('d/m/Y', strtotime($printEngData['created_at'])) ?>\nDemandeur : <?= sanitize($printEngData['demandeur_nom']) ?>\nObjet : <?= sanitize($printEngData['objet']) ?>\nMontant : <?= formatMontant($printEngData['montant']) ?>",
  width: 90,
  height: 90,
  correctLevel: QRCode.CorrectLevel.M
});
</script>
<div class="bon-actions no-print" style="text-align:center;margin:20px 0">
  <button class="btn btn-primary" onclick="window.print()"><i class="fa-solid fa-print"></i> Imprimer l'engagement</button>
  <a href="index.php?caisse=<?= $selectedCaisse['id'] ?? '' ?>" class="btn btn-outline" style="margin-left:8px"><i class="fa-solid fa-arrow-left"></i> Retour</a>
</div>

<?php else: ?>

<div class="page-header d-flex justify-between align-center">
  <div>
    <h1>Opérations de Caisse</h1>
    <p>Saisie, historique et impression des opérations</p>
  </div>
  <?php if ($isCaissier && $selectedCaisse && $selectedCaisse['statut'] === 'ouverte'): ?>
  <div style="display:flex;gap:10px">
    <button class="btn btn-primary" onclick="openModal('modal-operation')">+ Nouvelle opération</button>
    <button class="btn btn-outline" onclick="openModal('modal-libre')">Saisie libre</button>
    <button class="btn" style="background:#f59e0b;border-color:#f59e0b;color:#fff" onclick="openModal('modal-annulation')">Annulation</button>
  </div>
  <?php endif; ?>
</div>

<!-- Caisse selector -->
<?php if (!$selectedCaisse): ?>
<div class="card">
  <div class="card-header"><span class="card-title">Sélectionnez une caisse</span></div>
  <div class="card-body">
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:12px">
      <?php foreach($caisses as $c): ?>
      <?php $isOpen = $c['statut'] === 'ouverte'; ?>
      <a href="?caisse=<?= $c['id'] ?>" class="card" style="text-decoration:none;border-top:3px solid <?= $isOpen?'var(--success)':'var(--border)' ?>">
        <div class="card-body">
          <div style="font-weight:600;margin-bottom:4px"><?= sanitize($c['libelle']) ?></div>
          <div style="font-size:12px;color:var(--text3)"><?= sanitize($c['code']) ?> — <span class="badge <?= $isOpen?'badge-success':'badge-gray' ?>" style="font-size:11px"><?= $isOpen?'Ouverte':'Fermée' ?></span></div>
          <div style="font-size:18px;font-weight:700;margin-top:8px"><?= formatMontant($c['solde_actuel']) ?></div>
        </div>
      </a>
      <?php endforeach; ?>
      <?php if (empty($caisses)): ?>
      <p class="text-muted" style="padding:24px">Aucune caisse configurée. <a href="<?= BASE_URL ?>/modules/caisse/index.php">Créer une caisse</a></p>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php else: ?>

<!-- Selected caisse info bar -->
<div class="card mb-16" style="padding:0">
  <div class="card-body d-flex justify-between align-center" style="padding:12px 16px">
    <div>
      <strong><?= sanitize($selectedCaisse['libelle']) ?></strong>
      <span style="color:var(--text3);font-size:12px;margin-left:8px"><?= sanitize($selectedCaisse['code']) ?></span>
      <span class="badge <?= $selectedCaisse['statut']==='ouverte'?'badge-success':'badge-gray' ?>" style="margin-left:8px"><?= $selectedCaisse['statut']==='ouverte'?'Ouverte':'Fermée' ?></span>
    </div>
    <div style="font-size:18px;font-weight:700"><?= formatMontant($selectedCaisse['solde_actuel']) ?></div>
    <div>
      <?php if (hasPermission('admin', 'all') || hasPermission('all', 'all')): ?>
      <a href="?caisse=" class="btn btn-ghost btn-sm">Changer de caisse</a>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php if ($selectedCaisse['statut'] !== 'ouverte'): ?>
<div class="alert alert-warning" style="margin-bottom:16px">
  Cette caisse est fermée. <a href="<?= BASE_URL ?>/modules/caisse/index.php">Ouvrir la caisse</a> pour saisir des opérations.
</div>
<?php endif; ?>

<!-- Engagements disponibles -->
<?php if (!empty($engagementsDisponibles)): ?>
<div class="card mb-24">
  <div class="card-header">
    <div class="card-title">Engagements à exécuter</div>
    <span class="badge badge-teal"><?= count($engagementsDisponibles) ?></span>
  </div>
  <div style="padding:16px;display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:14px">
    <?php foreach($engagementsDisponibles as $eng): ?>
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:16px;display:flex;flex-direction:column;gap:8px;border-left:4px solid var(--primary)">
      <div style="display:flex;justify-content:space-between;align-items:center">
        <code style="font-size:12px;font-weight:600;color:var(--primary)"><?= sanitize($eng['numero']) ?></code>
        <span class="badge badge-teal" style="font-size:11px"><?= sanitize($eng['mode_nom']??'—') ?></span>
      </div>
      <div style="font-weight:600;font-size:14px;line-height:1.3;min-height:20px"><?= sanitize($eng['objet']) ?></div>
      <?php if ($eng['statut'] === 'execution_partielle'): ?>
      <?php $pctExec = $eng['montant'] > 0 ? round(($eng['montant_execute'] / $eng['montant']) * 100) : 0; ?>
      <div style="margin-top:2px">
        <div style="display:flex;justify-content:space-between;font-size:11px;color:var(--text3)">
          <span>Exécuté: <?= formatMontant($eng['montant_execute']) ?></span>
          <span>Restant: <?= formatMontant($eng['montant'] - $eng['montant_execute']) ?></span>
        </div>
        <div style="background:var(--border);border-radius:4px;height:6px;margin-top:3px;overflow:hidden">
          <div style="background:var(--primary);height:100%;border-radius:4px;width:<?= $pctExec ?>%"></div>
        </div>
      </div>
      <?php endif; ?>
      <div style="font-size:12px;color:var(--text3)">
        <span><?= sanitize($eng['demandeur_nom']??'—') ?></span>
        <?php if (!empty($eng['date_besoin'])): ?>
        <span style="margin-left:8px">• Besoin: <?= date('d/m/Y', strtotime($eng['date_besoin'])) ?></span>
        <?php endif; ?>
      </div>
      <div style="display:flex;justify-content:space-between;align-items:center;margin-top:auto;padding-top:8px;border-top:1px solid var(--border)">
        <div class="amount fw-bold" style="font-size:17px"><?= formatMontant($eng['montant']) ?></div>
        <div style="display:flex;gap:6px">
          <a href="?caisse=<?= $selectedCaisse['id'] ?>&print_eng=<?= $eng['id'] ?>" class="btn btn-ghost btn-sm" title="Imprimer l'engagement"><i class="fa-solid fa-print"></i></a>
          <?php if ($isCaissier && $selectedCaisse['statut'] === 'ouverte'): ?>
          <button class="btn btn-primary btn-sm" onclick="confirmerExecution(<?= $eng['id'] ?>, '<?= sanitize($eng['numero']) ?>', '<?= sanitize(addslashes($eng['objet'])) ?>', <?= (float)$eng['montant'] ?>, <?= (float)$eng['montant_execute'] ?>, '<?= sanitize($eng['demandeur_nom']??'') ?>')">Exécuter</button>
          <button class="btn btn-outline btn-sm" style="color:var(--danger);border-color:var(--danger)" onclick="ouvrirSolder(<?= $eng['id'] ?>, '<?= sanitize($eng['numero']) ?>', '<?= sanitize(addslashes($eng['objet'])) ?>', <?= (float)$eng['montant'] ?>, <?= (float)$eng['montant_execute'] ?>)">Solder</button>
          <?php elseif (!$isCaissier): ?>
          <span class="text-muted" style="font-size:12px">Réservé au caissier</span>
          <?php else: ?>
          <span class="text-muted" style="font-size:12px">Caisse fermée</span>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<!-- Operations history -->
<!-- Date & type filter block -->
<form method="get" id="ops-filter-form" style="margin-bottom:16px">
  <input type="hidden" name="caisse" value="<?= $selectedCaisse['id'] ?>">
  <div class="card" style="padding:0">
    <div style="padding:12px 16px;display:flex;align-items:center;gap:12px;flex-wrap:wrap;border-bottom:1px solid var(--border)">
      <div style="display:flex;align-items:center;gap:6px;font-weight:600;color:var(--primary)">
        <i class="fa-solid fa-filter" style="font-size:14px"></i>
        <span>Filtres</span>
      </div>
      <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;flex:1;min-width:0">
        <div style="position:relative;display:flex;align-items:center">
          <i class="fa-solid fa-magnifying-glass" style="position:absolute;left:10px;font-size:12px;color:var(--text3);pointer-events:none"></i>
          <input type="text" id="search-ops" class="form-control" placeholder="Rechercher..." style="width:170px;font-size:13px;padding-left:30px">
        </div>
        <div style="display:flex;align-items:center;gap:6px">
          <label style="font-size:12.5px;color:var(--text3);white-space:nowrap">Du</label>
          <input type="date" name="date_from" value="<?= htmlspecialchars($filterDateFrom) ?>" class="form-control" style="width:150px;font-size:13px" id="filter-date-from">
        </div>
        <div style="display:flex;align-items:center;gap:6px">
          <label style="font-size:12.5px;color:var(--text3);white-space:nowrap">Au</label>
          <input type="date" name="date_to" value="<?= htmlspecialchars($filterDateTo) ?>" class="form-control" style="width:150px;font-size:13px" id="filter-date-to">
        </div>
        <select name="type_op" class="form-control" style="width:auto;min-width:160px;font-size:13px">
          <option value="">Tous les types</option>
          <?php foreach($typesOps as $t): ?>
          <option value="<?= $t['id'] ?>" <?= $filterType === (int)$t['id'] ? 'selected' : '' ?>><?= sanitize($t['libelle']) ?></option>
          <?php endforeach; ?>
        </select>
        <select name="sens" class="form-control" style="width:auto;min-width:130px;font-size:13px">
          <option value="">Tous les sens</option>
          <option value="debit" <?= $filterSens==='debit'?'selected':'' ?>>Débit (sortie)</option>
          <option value="credit" <?= $filterSens==='credit'?'selected':'' ?>>Crédit (entrée)</option>
        </select>
      </div>
      <div style="display:flex;gap:6px">
        <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-search" style="font-size:12px"></i> Filtrer</button>
        <?php if ($filterDateFrom || $filterDateTo || $filterType || $filterSens): ?>
        <a href="?caisse=<?= $selectedCaisse['id'] ?>" class="btn btn-ghost btn-sm">Réinitialiser</a>
        <?php endif; ?>
      </div>
    </div>
    <?php if ($filterDateFrom || $filterDateTo || $filterType || $filterSens): ?>
    <div style="padding:6px 16px 8px;display:flex;align-items:center;gap:8px;flex-wrap:wrap;font-size:12.5px;color:var(--text3)">
      <span><?= count($ops) ?> résultat(s)</span>
      <?php if ($filterDateFrom): ?><span class="badge badge-info" style="font-size:10px;cursor:pointer" onclick="document.getElementById('filter-date-from').value='';document.getElementById('ops-filter-form').submit()">Du <?= date('d/m/Y', strtotime($filterDateFrom)) ?> ×</span><?php endif; ?>
      <?php if ($filterDateTo): ?><span class="badge badge-info" style="font-size:10px;cursor:pointer" onclick="document.getElementById('filter-date-to').value='';document.getElementById('ops-filter-form').submit()">Au <?= date('d/m/Y', strtotime($filterDateTo)) ?> ×</span><?php endif; ?>
    </div>
    <?php endif; ?>
  </div>
</form>

<div class="card mb-24">
  <div class="card-header">
    <div class="card-title">Historique des opérations</div>
  </div>
  <div class="table-wrap" id="ops-table-wrap">
    <table id="tbl-ops">
      <thead>
        <tr>
          <th style="white-space:nowrap">N° Pièce</th>
          <th>Date</th>
          <th>Type</th>
          <th>Destination</th>
          <th>Libellé</th>
          <th>Engagement</th>
          <th>Bénéficiaire / Remettant</th>
          <th style="text-align:right">Débit</th>
          <th style="text-align:right">Crédit</th>
          <th style="text-align:right">Solde</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($ops)): ?>
        <tr><td colspan="11" class="text-center text-muted" style="padding:24px">Aucune opération</td></tr>
        <?php else: foreach($ops as $op): ?>
        <tr>
          <td style="white-space:nowrap"><code style="font-weight:600;font-size:13px"><?= sanitize($op['numero_piece']) ?></code></td>
          <td style="white-space:nowrap"><?= date('d/m/Y', strtotime($op['date_operation'])) ?></td>
          <td><?= sanitize($op['type_nom']) ?></td>
          <td><?= !empty($op['destination_nom']) ? sanitize($op['destination_nom']) : '—' ?></td>
          <td style="max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?= sanitize($op['libelle']) ?>"><?= sanitize($op['libelle']) ?></td>
          <td>
            <?php if (!empty($op['eng_numero'])): ?>
            <a href="<?= BASE_URL ?>/modules/engagements/detail.php?id=<?= $op['engagement_id'] ?>" style="color:var(--primary);text-decoration:none" title="<?= sanitize($op['eng_objet']) ?>">
              <code style="font-size:11px"><?= sanitize($op['eng_numero']) ?></code>
            </a>
            <?php else: ?>—<?php endif; ?>
          </td>
          <td><span style="font-size:11px;color:var(--text3)"><?= $op['sens']==='credit' ? 'Remettant' : 'Bénéficiaire' ?></span><br><?= sanitize($op['beneficiaire_nom'] ?? '—') ?></td>
          <td class="amount amount-debit" style="text-align:right"><?= $op['sens']==='debit' ? number_format($op['montant'],0,',',' ') : '' ?></td>
          <td class="amount amount-credit" style="text-align:right"><?= $op['sens']==='credit' ? number_format($op['montant'],0,',',' ') : '' ?></td>
          <td class="amount" style="text-align:right"><?= number_format($op['solde_apres'],0,',',' ') ?></td>
          <td>
            <a href="?caisse=<?= $selectedCaisse['id'] ?>&print=<?= $op['id'] ?>" class="btn btn-ghost btn-sm" title="Imprimer"><i class="fa-solid fa-print"></i></a>
          </td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php endif; /* selected caisse */ ?>

<?php if ($isCaissier): ?>
<!-- Modal: Nouvelle opération — Exécuter un engagement -->
<div class="modal-overlay" id="modal-operation">
  <div class="modal" style="max-width:600px">
    <div class="modal-header">
      <div class="modal-title">Nouvelle opération — Engagement</div>
      <button class="modal-close" onclick="closeModal('modal-operation')"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <form method="post">
      <input type="hidden" name="action" value="executer_engagement">
      <input type="hidden" name="caisse_id" value="<?= $selectedCaisse['id']??'' ?>">
      <input type="hidden" name="engagement_id" id="op-engagement-id" value="">
      <div class="modal-body">
        <?php if (empty($engagementsDisponibles)): ?>
        <div class="alert alert-info" style="margin:0">Aucun engagement approuvé en attente d'exécution.</div>
        <?php else: ?>
        <div class="form-group">
          <label class="form-label">Engagement approuvé <span class="req">*</span></label>
          <select id="op-engagement-select" class="form-control">
            <option value="">-- Sélectionner un engagement --</option>
            <?php foreach($engagementsDisponibles as $eng): ?>
            <option value="<?= $eng['id'] ?>" data-numero="<?= sanitize($eng['numero']) ?>" data-objet="<?= sanitize($eng['objet']) ?>" data-montant="<?= $eng['montant'] ?>" data-montant-execute="<?= (float)$eng['montant_execute'] ?>" data-benef="<?= sanitize($eng['demandeur_nom']??'') ?>" data-mode-id="<?= $eng['mode_paiement_id']??'' ?>">
              <?= sanitize($eng['numero']) ?> — <?= sanitize($eng['objet']) ?> — <?= formatMontant($eng['montant']) ?><?= $eng['statut']==='execution_partielle' ? ' (partiel)' : '' ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div id="eng-recap" style="display:none;padding:16px;background:var(--surface);border-radius:var(--radius);border:1px solid var(--border)">
          <div style="font-weight:600;margin-bottom:10px;color:var(--primary)">Récapitulatif de l'engagement</div>
          <table style="width:100%;font-size:13.5px">
            <tr><td style="color:var(--text3);padding:5px 0;width:120px">N° Engagement</td><td id="eng-recap-num" class="fw-bold"></td></tr>
            <tr><td style="color:var(--text3);padding:5px 0">Objet</td><td id="eng-recap-objet"></td></tr>
            <tr><td style="color:var(--text3);padding:5px 0">Bénéficiaire</td><td id="eng-recap-benef"></td></tr>
            <tr><td style="color:var(--text3);padding:5px 0">Mode</td><td id="eng-recap-mode"></td></tr>
            <tr><td style="color:var(--text3);padding:5px 0">Montant total</td><td id="eng-recap-montant" class="fw-bold" style="font-size:16px;color:var(--danger)"></td></tr>
            <tr id="eng-recap-deja-row" style="display:none"><td style="color:var(--text3);padding:5px 0">Déjà exécuté</td><td id="eng-recap-deja" style="color:var(--success)"></td></tr>
            <tr id="eng-recap-restant-row" style="display:none"><td style="color:var(--text3);padding:5px 0">Restant</td><td id="eng-recap-restant" style="color:var(--danger);font-weight:700"></td></tr>
          </table>
          <div style="margin-top:10px;padding:10px;background:var(--bg);border-radius:var(--radius);font-size:13px;color:var(--text2)">
            Solde caisse : <strong><?= formatMontant($selectedCaisse['solde_actuel']??0) ?></strong>
          </div>
          <div class="alert alert-danger" style="margin-top:10px;font-size:13px;padding:8px 12px">
            <strong>Attention :</strong> Cette action va débiter la caisse et est irréversible.
          </div>
        </div>
        <?php endif; ?>
      </div>
      <?php if (!empty($engagementsDisponibles)): ?>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('modal-operation')">Annuler</button>
        <button type="submit" class="btn btn-danger" id="btn-submit-eng" disabled style="opacity:0.5;cursor:not-allowed">Exécuter l'engagement</button>
      </div>
      <?php endif; ?>
    </form>
  </div>
</div>

<!-- Modal: Saisie libre -->
<div class="modal-overlay" id="modal-libre">
  <div class="modal" style="max-width:600px">
    <div class="modal-header">
      <div class="modal-title">Saisie libre</div>
      <button class="modal-close" onclick="closeModal('modal-libre')"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <form method="post">
      <input type="hidden" name="action" value="saisir_operation">
      <input type="hidden" name="caisse_id" value="<?= $selectedCaisse['id']??'' ?>">
      <div class="modal-body">
        <div class="form-row-2">
          <div class="form-group">
            <label class="form-label">Type d'opération <span class="req">*</span></label>
            <select name="type_operation_id" id="libre-type-operation" class="form-control" required onchange="toggleGroupeProprietaire('libre-type-operation','libre-groupe-proprietaire-div','libre-groupe-proprietaire')">
              <option value="" data-code="">-- Choisir --</option>
              <?php foreach($typesOps as $t): ?>
              <?php if ($t['code'] !== 'ANNUL'): ?>
              <option value="<?= $t['id'] ?>" data-code="<?= sanitize($t['code']) ?>" data-sens="<?= $t['sens'] ?>">[<?= $t['sens']==='credit'?'+':'-' ?>] <?= sanitize($t['libelle']) ?></option>
              <?php endif; ?>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Date <span class="req">*</span></label>
            <input type="date" name="date_operation" class="form-control" value="<?= date('Y-m-d') ?>" required>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label" id="libre-benef-label">Bénéficiaire</label>
          <input type="text" name="beneficiaire_nom" class="form-control" placeholder="Nom du bénéficiaire">
        </div>
        <div class="form-group" id="libre-groupe-proprietaire-div" style="display:none">
          <label class="form-label">Groupe propriétaire <span class="req">*</span></label>
          <select name="groupe_proprietaire_id" id="libre-groupe-proprietaire" class="form-control">
            <option value="">-- Choisir --</option>
            <?php foreach($groupesProprietaires as $gp): ?>
            <option value="<?= $gp['id'] ?>"><?= sanitize($gp['libelle']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Destination</label>
          <select name="destination_id" class="form-control">
            <option value="">-- Choisir --</option>
            <?php foreach($destinations as $d): ?>
            <option value="<?= $d['id'] ?>"><?= sanitize($d['libelle']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Libellé <span class="req">*</span></label>
          <input type="text" name="libelle" class="form-control" placeholder="Objet de l'opération" required>
        </div>
        <div class="form-row-2">
          <div class="form-group">
            <label class="form-label">Montant (FCFA) <span class="req">*</span></label>
            <input type="number" name="montant" class="form-control amount-input" step="1" min="1" required>
          </div>
          <div class="form-group">
            <label class="form-label">Mode de paiement</label>
            <input type="hidden" name="mode_paiement_id" value="<?= $modeEspecesId ?>">
            <input type="text" class="form-control" value="Espèces" disabled style="background:var(--surface2)">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Référence externe</label>
          <input type="text" name="reference_externe" class="form-control" placeholder="N° chèque, reçu...">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('modal-libre')">Annuler</button>
        <button type="submit" class="btn btn-primary">Enregistrer</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal: Annulation d'opération -->
<div class="modal-overlay" id="modal-annulation">
  <div class="modal" style="max-width:600px">
    <div class="modal-header" style="background:#f59e0b;color:#fff">
      <div class="modal-title">Annulation d'opération</div>
      <button class="modal-close" onclick="closeModal('modal-annulation')" style="color:#fff"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <form method="post" id="form-annulation">
      <input type="hidden" name="action" value="annuler_operation">
      <input type="hidden" name="caisse_id" value="<?= $selectedCaisse['id']??'' ?>">
      <input type="hidden" name="annule_operation_id" id="annul-op-id" value="">
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Rechercher l'opération à annuler <span class="req">*</span></label>
          <input type="text" id="annul-search-input" class="form-control" placeholder="Saisir le N° de pièce (ex: PC-...)">
          <div id="annul-search-results" style="margin-top:8px"></div>
        </div>
        <div id="annul-op-details" style="display:none;margin-bottom:12px">
          <div style="padding:14px;background:#fff8e1;border:1px solid #f59e0b;border-radius:var(--radius);margin-bottom:12px">
            <div style="font-weight:600;margin-bottom:8px;color:#92400e">Opération à annuler</div>
            <table style="width:100%;font-size:13.5px">
              <tr><td style="color:var(--text3);padding:4px 0;width:130px">Pièce</td><td id="annul-detail-piece" class="fw-bold"></td></tr>
              <tr><td style="color:var(--text3);padding:4px 0">Type</td><td id="annul-detail-type"></td></tr>
              <tr><td style="color:var(--text3);padding:4px 0" id="annul-detail-benef-label">Bénéficiaire</td><td id="annul-detail-benef"></td></tr>
              <tr><td style="color:var(--text3);padding:4px 0">Montant</td><td id="annul-detail-montant" class="fw-bold" style="color:var(--danger)"></td></tr>
              <tr><td style="color:var(--text3);padding:4px 0">Mode</td><td id="annul-detail-mode"></td></tr>
              <tr><td style="color:var(--text3);padding:4px 0">Date</td><td id="annul-detail-date"></td></tr>
            </table>
          </div>
          <div class="form-group">
            <label class="form-label">Motif / Libellé d'annulation</label>
            <input type="text" name="libelle" id="annul-libelle" class="form-control" placeholder="Motif de l'annulation (optionnel)">
          </div>
          <div class="alert alert-danger" style="margin-top:8px;font-size:13px">
            <strong>Attention :</strong> Cette action va créditer la caisse du même montant (balance comptable). L'opération d'origine reste enregistrée.
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('modal-annulation')">Annuler</button>
        <button type="submit" class="btn btn-danger" id="btn-confirm-annul" disabled style="opacity:0.5;cursor:not-allowed">Confirmer l'annulation</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal: Exécuter engagement (partiel) -->
<div class="modal-overlay" id="modal-executer">
  <div class="modal" style="max-width:560px">
    <div class="modal-header">
      <div class="modal-title">Exécuter l'engagement</div>
      <button class="modal-close" onclick="closeModal('modal-executer')"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <form method="post">
      <input type="hidden" name="action" value="executer_engagement">
      <input type="hidden" name="engagement_id" id="exec-eng-id">
      <input type="hidden" name="caisse_id" value="<?= $selectedCaisse['id']??'' ?>">
      <div class="modal-body">
        <div class="alert alert-info" style="margin-bottom:16px">
          <strong>Confirmation</strong> — Vous allez débiter la caisse pour honorer cet engagement.
        </div>
        <table style="width:100%;font-size:13.5px;margin-bottom:16px">
          <tr><td style="color:var(--text3);padding:6px 0;width:140px">N° Engagement</td><td id="exec-eng-num" class="fw-bold"></td></tr>
          <tr><td style="color:var(--text3);padding:6px 0">Objet</td><td id="exec-eng-objet"></td></tr>
          <tr><td style="color:var(--text3);padding:6px 0">Bénéficiaire</td><td id="exec-eng-benef"></td></tr>
          <tr><td style="color:var(--text3);padding:6px 0">Montant total</td><td id="exec-eng-montant-total" class="fw-bold" style="font-size:16px"></td></tr>
          <tr id="exec-row-deja" style="display:none"><td style="color:var(--text3);padding:6px 0">Déjà exécuté</td><td id="exec-eng-deja" class="fw-bold" style="color:var(--success)"></td></tr>
          <tr id="exec-row-restant" style="display:none"><td style="color:var(--text3);padding:6px 0">Restant</td><td id="exec-eng-restant" class="fw-bold" style="color:var(--danger)"></td></tr>
        </table>
        <div id="exec-progress-area" style="display:none;margin-bottom:16px">
          <div style="background:var(--border);border-radius:4px;height:10px;overflow:hidden">
            <div id="exec-progress-bar" style="background:var(--primary);height:100%;border-radius:4px;width:0%;transition:width .3s"></div>
          </div>
          <div style="display:flex;justify-content:space-between;font-size:12px;color:var(--text3);margin-top:4px">
            <span id="exec-progress-pct"></span>
            <span id="exec-progress-label"></span>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Montant à exécuter (FCFA) <span class="req">*</span></label>
          <div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:8px">
            <button type="button" class="btn btn-outline btn-sm pct-btn" data-pct="70" onclick="setPctAmount(70)">70%</button>
            <button type="button" class="btn btn-outline btn-sm pct-btn" data-pct="80" onclick="setPctAmount(80)">80%</button>
            <button type="button" class="btn btn-outline btn-sm pct-btn" data-pct="100" onclick="setPctAmount(100)">Total</button>
          </div>
          <input type="number" name="montant_execution" id="exec-montant" class="form-control amount-input" step="1" min="1" required placeholder="Ou saisir un montant personnalisé">
        </div>
        <div style="padding:10px;background:var(--surface);border-radius:var(--radius);font-size:13px;color:var(--text2)">
          Solde caisse : <strong><?= formatMontant($selectedCaisse['solde_actuel']??0) ?></strong>
        </div>
        <div class="alert alert-danger" style="margin-top:10px;font-size:13px;padding:8px 12px">
          <strong>Attention :</strong> Cette action va débiter la caisse et est irréversible.
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('modal-executer')">Annuler</button>
        <button type="submit" class="btn btn-danger">Exécuter</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal: Solder engagement -->
<div class="modal-overlay" id="modal-solder">
  <div class="modal" style="max-width:500px">
    <div class="modal-header" style="background:#ef4444;color:#fff">
      <div class="modal-title">Solder l'engagement</div>
      <button class="modal-close" onclick="closeModal('modal-solder')" style="color:#fff"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <form method="post">
      <input type="hidden" name="action" value="solder_engagement">
      <input type="hidden" name="engagement_id" id="solder-eng-id">
      <input type="hidden" name="caisse_id" value="<?= $selectedCaisse['id']??'' ?>">
      <div class="modal-body">
        <div class="alert alert-danger" style="margin-bottom:16px">
          <strong>Attention</strong> — Solder un engagement signifie renoncer au montant restant. Le montant ne sera pas versé et l'engagement sera clôturé.
        </div>
        <table style="width:100%;font-size:13.5px;margin-bottom:16px">
          <tr><td style="color:var(--text3);padding:6px 0;width:140px">N° Engagement</td><td id="solder-eng-num" class="fw-bold"></td></tr>
          <tr><td style="color:var(--text3);padding:6px 0">Objet</td><td id="solder-eng-objet"></td></tr>
          <tr><td style="color:var(--text3);padding:6px 0">Montant total</td><td id="solder-eng-total" class="fw-bold"></td></tr>
          <tr><td style="color:var(--text3);padding:6px 0">Déjà exécuté</td><td id="solder-eng-deja" style="color:var(--success)"></td></tr>
          <tr style="border-top:2px solid var(--border)"><td style="color:var(--text3);padding:6px 0;font-weight:700">Montant à solder</td><td id="solder-eng-restant" class="fw-bold" style="color:var(--danger);font-size:16px"></td></tr>
        </table>
        <div class="form-group">
          <label class="form-label">Motif du solder <span class="req">*</span></label>
          <textarea name="motif_solder" class="form-control" rows="3" required placeholder="Justification obligatoire pour le solder..."></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('modal-solder')">Annuler</button>
        <button type="submit" class="btn btn-danger">Confirmer le solder</button>
      </div>
    </form>
  </div>
</div>
<?php endif; /* isCaissier for modals */ ?>

<?php endif; /* bon/print/normal view */ ?>

<script>
function toggleGroupeProprietaire(selectId, divId, fieldId) {
  const sel = document.getElementById(selectId);
  if (!sel) return;
  const opt = sel.options[sel.selectedIndex];
  const show = opt && opt.getAttribute('data-code') === 'BON_PROPRIETAIRE';
  document.getElementById(divId).style.display = show ? '' : 'none';
  if (!show) document.getElementById(fieldId).value = '';
  // Update beneficiaire/remettant label based on sens
  var benefLabel = document.getElementById('libre-benef-label');
  var benefInput = document.querySelector('#modal-libre input[name="beneficiaire_nom"]');
  if (benefLabel && opt && opt.value) {
    var sens = opt.getAttribute('data-sens');
    if (sens === 'credit') {
      benefLabel.textContent = 'Remettant';
      if (benefInput) benefInput.placeholder = 'Nom du remettant';
    } else {
      benefLabel.textContent = 'Bénéficiaire';
      if (benefInput) benefInput.placeholder = 'Nom du bénéficiaire';
    }
  }
}
// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
  toggleGroupeProprietaire('libre-type-operation','libre-groupe-proprietaire-div','libre-groupe-proprietaire');
});

// Global functions (needed by onclick attributes in HTML)
function resetOpModal() {
  const sel = document.getElementById('op-engagement-select');
  if (sel) sel.value = '';
  const recap = document.getElementById('eng-recap');
  if (recap) recap.style.display = 'none';
  document.getElementById('op-engagement-id').value = '';
  const btn = document.getElementById('btn-submit-eng');
  if (btn) { btn.disabled = true; btn.style.opacity = '0.5'; btn.style.cursor = 'not-allowed'; }
}

function selectAnnulOp(op) {
  document.getElementById('annul-op-id').value = op.id;
  document.getElementById('annul-search-input').value = op.numero_piece;
  document.getElementById('annul-search-results').innerHTML = '';
  document.getElementById('annul-op-details').style.display = 'block';
  document.getElementById('annul-detail-piece').textContent = op.numero_piece;
  document.getElementById('annul-detail-type').textContent = op.type_nom;
  document.getElementById('annul-detail-benef').textContent = op.beneficiaire_nom || '—';
  document.getElementById('annul-detail-benef-label').textContent = op.sens === 'credit' ? 'Remettant' : 'Bénéficiaire';
  document.getElementById('annul-detail-montant').textContent = new Intl.NumberFormat('fr-CM').format(op.montant) + ' FCFA';
  document.getElementById('annul-detail-mode').textContent = op.mode_nom || '—';
  document.getElementById('annul-detail-date').textContent = op.date_operation;
  document.getElementById('annul-libelle').value = 'Annulation — ' + op.numero_piece;
  // Enable confirm button
  const btn = document.getElementById('btn-confirm-annul');
  btn.disabled = false;
  btn.style.opacity = '1';
  btn.style.cursor = 'pointer';
}

function resetAnnulModal() {
  document.getElementById('annul-op-id').value = '';
  document.getElementById('annul-search-input').value = '';
  document.getElementById('annul-search-results').innerHTML = '';
  document.getElementById('annul-op-details').style.display = 'none';
  document.getElementById('annul-libelle').value = '';
  const btn = document.getElementById('btn-confirm-annul');
  btn.disabled = true;
  btn.style.opacity = '0.5';
  btn.style.cursor = 'not-allowed';
}

function confirmerExecution(id, num, objet, montantTotal, dejaExecute, benef) {
  document.getElementById('exec-eng-id').value = id;
  document.getElementById('exec-eng-num').textContent = num;
  document.getElementById('exec-eng-objet').textContent = objet;
  document.getElementById('exec-eng-benef').textContent = benef || '—';
  document.getElementById('exec-eng-montant-total').textContent = new Intl.NumberFormat('fr-CM').format(montantTotal) + ' FCFA';

  var restant = montantTotal - dejaExecute;

  if (dejaExecute > 0) {
    document.getElementById('exec-row-deja').style.display = '';
    document.getElementById('exec-row-restant').style.display = '';
    document.getElementById('exec-eng-deja').textContent = new Intl.NumberFormat('fr-CM').format(dejaExecute) + ' FCFA';
    document.getElementById('exec-eng-restant').textContent = new Intl.NumberFormat('fr-CM').format(restant) + ' FCFA';
    document.getElementById('exec-progress-area').style.display = '';
    var pct = Math.round((dejaExecute / montantTotal) * 100);
    document.getElementById('exec-progress-bar').style.width = pct + '%';
    document.getElementById('exec-progress-pct').textContent = pct + '% exécuté';
    document.getElementById('exec-progress-label').textContent = new Intl.NumberFormat('fr-CM').format(restant) + ' FCFA restant';
  } else {
    document.getElementById('exec-row-deja').style.display = 'none';
    document.getElementById('exec-row-restant').style.display = 'none';
    document.getElementById('exec-progress-area').style.display = 'none';
  }

  document.getElementById('exec-montant').value = restant;
  document.getElementById('exec-montant').max = restant;
  // Reset pct buttons
  document.querySelectorAll('.pct-btn').forEach(function(btn) {
    btn.classList.remove('btn-primary');
    btn.classList.add('btn-outline');
  });
  openModal('modal-executer');
}

function setPctAmount(pct) {
  var montantTotal = parseFloat(document.getElementById('exec-eng-montant-total').textContent.replace(/[^\d,-]/g, '').replace(/\s/g, '').replace(',', '.')) || 0;
  var dejaExecute = 0;
  if (document.getElementById('exec-row-deja').style.display !== 'none') {
    dejaExecute = parseFloat(document.getElementById('exec-eng-deja').textContent.replace(/[^\d,-]/g, '').replace(/\s/g, '').replace(',', '.')) || 0;
  }
  var restant = montantTotal - dejaExecute;
  var amount = Math.round(restant * pct / 100);
  if (amount < 1) amount = 1;
  if (amount > restant) amount = restant;
  document.getElementById('exec-montant').value = amount;
  document.querySelectorAll('.pct-btn').forEach(function(btn) {
    btn.classList.toggle('btn-primary', parseInt(btn.dataset.pct) === pct);
    btn.classList.toggle('btn-outline', parseInt(btn.dataset.pct) !== pct);
  });
}

function ouvrirSolder(id, num, objet, montantTotal, dejaExecute) {
  document.getElementById('solder-eng-id').value = id;
  document.getElementById('solder-eng-num').textContent = num;
  document.getElementById('solder-eng-objet').textContent = objet;
  document.getElementById('solder-eng-total').textContent = new Intl.NumberFormat('fr-CM').format(montantTotal) + ' FCFA';
  document.getElementById('solder-eng-deja').textContent = new Intl.NumberFormat('fr-CM').format(dejaExecute) + ' FCFA';
  var restant = montantTotal - dejaExecute;
  document.getElementById('solder-eng-restant').textContent = new Intl.NumberFormat('fr-CM').format(restant) + ' FCFA';
  openModal('modal-solder');
}

// Defer everything that depends on app.js (loaded in footer after this script)
document.addEventListener('DOMContentLoaded', function() {
  tableSearch('search-ops', 'tbl-ops');

  // Engagement selection in Nouvelle opération modal
  const engSelect = document.getElementById('op-engagement-select');
  if (engSelect) {
    engSelect.addEventListener('change', function() {
      const opt = this.options[this.selectedIndex];
      const engId = this.value;
      const recap = document.getElementById('eng-recap');
      const submitBtn = document.getElementById('btn-submit-eng');

      if (!engId) {
        recap.style.display = 'none';
        document.getElementById('op-engagement-id').value = '';
        submitBtn.disabled = true;
        submitBtn.style.opacity = '0.5';
        submitBtn.style.cursor = 'not-allowed';
        return;
      }

      document.getElementById('op-engagement-id').value = engId;
      document.getElementById('eng-recap-num').textContent = opt.dataset.numero;
      document.getElementById('eng-recap-objet').textContent = opt.dataset.objet;
      document.getElementById('eng-recap-benef').textContent = opt.dataset.benef || '—';

      // Mode — always Espèces in caisse
      document.getElementById('eng-recap-mode').textContent = 'Espèces';

      var montantTotal = parseFloat(opt.dataset.montant);
      var dejaExecute = parseFloat(opt.dataset.montantExecute) || 0;
      var restant = montantTotal - dejaExecute;
      document.getElementById('eng-recap-montant').textContent = new Intl.NumberFormat('fr-CM').format(montantTotal) + ' FCFA';

      if (dejaExecute > 0) {
        document.getElementById('eng-recap-deja-row').style.display = '';
        document.getElementById('eng-recap-restant-row').style.display = '';
        document.getElementById('eng-recap-deja').textContent = new Intl.NumberFormat('fr-CM').format(dejaExecute) + ' FCFA';
        document.getElementById('eng-recap-restant').textContent = new Intl.NumberFormat('fr-CM').format(restant) + ' FCFA';
      } else {
        document.getElementById('eng-recap-deja-row').style.display = 'none';
        document.getElementById('eng-recap-restant-row').style.display = 'none';
      }
      recap.style.display = 'block';
      submitBtn.disabled = false;
      submitBtn.style.opacity = '1';
      submitBtn.style.cursor = 'pointer';
    });
  }

  // Hook closeModal to reset modals on close
  const origClose = window.closeModal;
  window.closeModal = function(id) {
    origClose(id);
    if (id === 'modal-operation') resetOpModal();
    if (id === 'modal-annulation') resetAnnulModal();
  };

  // Annulation modal: search operations by numero_piece
  let annulSearchTimer = null;
  document.getElementById('annul-search-input').addEventListener('input', function() {
    clearTimeout(annulSearchTimer);
    const q = this.value.trim();
    if (q.length < 2) {
      document.getElementById('annul-search-results').innerHTML = '';
      return;
    }
    annulSearchTimer = setTimeout(() => {
      const caisseId = document.querySelector('#form-annulation input[name="caisse_id"]').value;
      fetch('?ajax_search_op=1&q=' + encodeURIComponent(q) + '&caisse_id=' + caisseId)
        .then(r => r.json())
        .then(data => {
          const container = document.getElementById('annul-search-results');
          if (!data.length) {
            container.innerHTML = '<div style="padding:8px;font-size:13px;color:var(--text3)">Aucune opération trouvée.</div>';
            return;
          }
          container.innerHTML = data.map(op =>
            '<div style="padding:8px 12px;margin-bottom:4px;background:var(--surface);border-radius:var(--radius);cursor:pointer;font-size:13px;border-left:3px solid #f59e0b" onclick="selectAnnulOp(' + JSON.stringify(op).replace(/"/g, '&quot;') + ')">' +
            '<strong>' + op.numero_piece + '</strong> — ' + op.type_nom +
            ' — <span class="amount">' + new Intl.NumberFormat('fr-CM').format(op.montant) + ' FCFA</span>' +
            '<br><span style="color:var(--text3)">' + (op.beneficiaire_nom || '—') + ' | ' + op.date_operation + '</span>' +
            '</div>'
          ).join('');
        });
    }, 300);
  });
});
</script>
<?php include __DIR__ . '/../../includes/footer.php'; ?>