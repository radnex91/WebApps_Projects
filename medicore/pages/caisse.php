<?php
$currentPage = 'caisse';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/comptabilite.php';
requireLogin();

$s = get_settings();

//  Générer numéro ticket
function gen_ticket_num(): string {
    $last = db_scalar("SELECT numero_ticket FROM caisse_ventes ORDER BY id DESC LIMIT 1");
    if ($last && preg_match('/TK-\d{4}-(\d+)/', $last, $m)) {
        $next = (int)$m[1] + 1;
    } else {
        $next = 1;
    }
    return 'TK-' . date('Y') . '-' . str_pad($next, 5, '0', STR_PAD_LEFT);
}

// ══ POST : Ouvrir session de caisse ══
if (can('caisse.session_ouvrir') && $_SERVER['REQUEST_METHOD'] === 'POST' && post_str('action') === 'ouvrir_session') {
    csrf_verify();
    $fond = max(0, post_float('fond_caisse'));

    // Vérifier qu'aucune session ouverte n'existe déjà
    try {
        $existing = db_row("SELECT id FROM caisse_sessions WHERE caissier_id = ? AND statut = 'ouverte'", [$_SESSION['user_id']]);
        if ($existing) {
            $flash = 'Vous avez déjà une session de caisse ouverte.';
        } else {
            db_exec(
                "INSERT INTO caisse_sessions (caissier_id, fond_caisse, statut) VALUES (?, ?, 'ouverte')",
                [$_SESSION['user_id'], $fond]
            );
            $sessionId = db_scalar("SELECT LAST_INSERT_ID()");
            logActivity("Session de caisse ouverte (fond: " . fmt_money($fond) . ")", 'green', 'caisse', (int)$sessionId);
            header('Location: ' . APP_URL . '/caisse.php?session=1');
            exit;
        }
    } catch (Exception $e) {
        $flash = 'Erreur : la table des sessions n\'existe pas encore. Exécutez la mise à jour SQL.';
        _log_error('CAISSE', 'Échec ouverture session', __FILE__, __LINE__, $e);
    }
}

// ══ POST : Fermer session de caisse (clôture) ══
if (can('caisse.session_fermer') && $_SERVER['REQUEST_METHOD'] === 'POST' && post_str('action') === 'fermer_session') {
    csrf_verify();
    $session_id = post_int('session_id');
    $total_reel  = post_float('total_reel');
    $notes       = post_str('notes_fermeture');

    try {
        $session = db_row("SELECT * FROM caisse_sessions WHERE id = ? AND caissier_id = ? AND statut = 'ouverte'", [$session_id, $_SESSION['user_id']]);
    } catch (Exception $e) {
        $session = null;
        $flash = 'Erreur : la table des sessions n\'existe pas encore.';
    }
    if (!$session) {
        if (empty($flash)) $flash = 'Session de caisse introuvable ou déjà fermée.';
    } else {
        // Calculer le total théorique
        $total_ventes = (float)db_scalar(
            "SELECT COALESCE(SUM(montant_total),0) FROM caisse_ventes WHERE session_id = ? AND statut = 'paye'",
            [$session_id]
        );
        $total_theorique = (float)$session['fond_caisse'] + $total_ventes;
        $ecart = round($total_reel - $total_theorique, 2);
        $ventes_count = (int)db_scalar("SELECT COUNT(*) FROM caisse_ventes WHERE session_id = ? AND statut = 'paye'", [$session_id]);

        db_exec(
            "UPDATE caisse_sessions SET fermeture_date = NOW(), statut = 'fermee', total_theorique = ?, total_reel = ?, ecart = ?, ventes_count = ?, notes_fermeture = ? WHERE id = ?",
            [$total_theorique, $total_reel, $ecart, $ventes_count, $notes, $session_id]
        );
        logActivity("Session de caisse fermée — écart: " . fmt_money($ecart), $ecart != 0 ? 'yellow' : 'green', 'caisse', $session_id);
        header('Location: ' . APP_URL . '/caisse.php?cloture=' . $session_id);
        exit;
    }
}

// ══ POST : Créer vente ══
if (can('caisse.create_vente') && $_SERVER['REQUEST_METHOD'] === 'POST' && post_str('action') === 'create_vente') {
    csrf_verify();

    // Vérifier qu'une session de caisse est ouverte
    $session_ouverte = null;
    try {
        $session_ouverte = db_row("SELECT id FROM caisse_sessions WHERE caissier_id = ? AND statut = 'ouverte'", [$_SESSION['user_id']]);
    } catch (Exception $e) {
        // Table absente — on continue sans session (rétro-compatibilité)
        _log_error('CAISSE', 'Table caisse_sessions absente, vente sans session', __FILE__, __LINE__, $e);
    }

    $med_ids   = $_POST['med_id'] ?? [];
    $quantites = $_POST['quantite'] ?? [];
    $remises   = $_POST['remise'] ?? [];

    if (empty($med_ids)) {
        $flash = 'Ajoutez au moins un médicament.';
    } else {
        $mode       = in_whitelist(post_str('mode_paiement'), ['especes','carte','cheque','virement','assurance','mobile_money','gratuit'], 'especes');
        $patient_id = post_int('patient_id') ?: null;
        $linked_ord = post_int('ordonnance_id') ?: null;
        $montant_recu = post_float('montant_recu');
        $notes      = post_str('notes');
        $num        = gen_ticket_num();
        $total      = 0.0;
        $lignes     = [];

        foreach ($med_ids as $i => $mid) {
            $mid = (int)$mid;
            $qty = max(1, (int)($quantites[$i] ?? 1));
            $rem = max(0.0, min(100.0, (float)($remises[$i] ?? 0)));
            $med = db_row("SELECT * FROM medicaments WHERE id = ?", [$mid]);
            if (!$med) continue;
            $pu      = (float)$med['prix_unitaire'];
            $tl      = round($pu * $qty * (1 - $rem / 100), 2);
            $total  += $tl;
            $lignes[] = [$mid, $qty, $pu, $rem, $tl];
        }

        $total    = round($total, 2);
        $monnaie  = $mode === 'especes' ? max(0, round($montant_recu - $total, 2)) : 0.0;
        $statut   = in_array($mode, ['carte','cheque','virement','assurance','gratuit']) || $montant_recu >= $total
                    ? 'paye' : 'ouvert';

        $session_id_val = $session_ouverte ? (int)$session_ouverte['id'] : null;

        $vente_id = db_exec(
            "INSERT INTO caisse_ventes (numero_ticket, patient_id, caissier_id, session_id, montant_total, montant_recu, monnaie_rendue, mode_paiement, statut, ordonnance_id, notes)
             VALUES (?,?,?,?,?,?,?,?,?,?,?)",
            [$num, $patient_id, $_SESSION['user_id'], $session_id_val, $total, $montant_recu, $monnaie, $mode, $statut, $linked_ord, $notes]
        );

        foreach ($lignes as [$mid, $qty, $pu, $rem, $tl]) {
            db_exec(
                "INSERT INTO caisse_lignes (vente_id, medicament_id, quantite, prix_unitaire, remise_pct, total_ligne) VALUES (?,?,?,?,?,?)",
                [$vente_id, $mid, $qty, $pu, $rem, $tl]
            );
            //  Décrémenter stock
            db_exec("UPDATE medicaments SET stock_actuel = GREATEST(0, stock_actuel - ?) WHERE id = ?", [$qty, $mid]);
            db_exec("UPDATE medicaments SET statut = CASE
                WHEN stock_actuel <= 0                       THEN 'critique'
                WHEN stock_actuel <= stock_minimum * 0.5    THEN 'critique'
                WHEN stock_actuel <= stock_minimum          THEN 'bas'
                ELSE 'normal' END WHERE id = ?", [$mid]);
        }

        //  Marquer l'ordonnance comme terminée
        if ($linked_ord) {
            db_exec("UPDATE ordonnances SET statut='terminee' WHERE id=? AND statut='active'", [$linked_ord]);
            logActivity("Ordonnance #$linked_ord soldée via ticket $num", 'green', 'ordonnance', $linked_ord);
        }

        //  Comptabilité : écriture si ticket payé
        if ($statut === 'paye') {
            compta_on_vente_caisse([
                'id' => (int)$vente_id, 'numero_ticket' => $num, 'type_vente' => 'medicament',
                'montant_total' => $total, 'mode_paiement' => $mode, 'date_vente' => date('Y-m-d H:i:s'),
                'caissier_id' => $_SESSION['user_id'],
            ]);
        }

        logActivity("Ticket créé " . fmt_money($total), 'green', 'caisse', $vente_id);
        header('Location: ' . APP_URL . '/caisse.php?ticket=' . $vente_id . '&print=1');
        exit;
    }
}

// ══ POST : Ticket de service (création de dossier / droit de consultation) ══
if (can('caisse.ticket_service') && $_SERVER['REQUEST_METHOD'] === 'POST' && post_str('action') === 'create_ticket_service') {
    csrf_verify();
    require_once __DIR__ . '/../includes/dossiers.php';

    $type       = in_whitelist(post_str('ticket_type'), ['creation_dossier','consultation'], '');
    $patient_id = post_int('patient_id');
    $mode       = in_whitelist(post_str('mode_paiement'), ['especes','carte','cheque','virement','assurance','mobile_money','gratuit'], 'especes');
    $medecin_id = post_int('medecin_id') ?: null;
    $rdv_id     = post_int('rdv_id') ?: null;
    $montant_recu = post_float('montant_recu');
    $notes      = post_str('notes');

    if ($patient_id <= 0) {
        $flash = 'Un patient est obligatoire pour un ticket de service.';
    } elseif ($type === '') {
        $flash = 'Type de ticket invalide.';
    } elseif ($type === 'creation_dossier' && patient_has_dossier($patient_id)) {
        $flash = 'Ce patient possède déjà un dossier médical.';
    } else {
        // Session de caisse (rétro-compatible si table absente)
        $session_ouverte = null;
        try {
            $session_ouverte = db_row("SELECT id FROM caisse_sessions WHERE caissier_id = ? AND statut = 'ouverte'", [$_SESSION['user_id']]);
        } catch (Exception $e) {
            _log_error('CAISSE', 'Table caisse_sessions absente, ticket service sans session', __FILE__, __LINE__, $e);
        }

        $tarif       = (float) setting($type === 'creation_dossier' ? 'tarif_ticket_dossier' : 'tarif_consultation', '0');
        $designation = $type === 'creation_dossier'
            ? 'Ticket de création de dossier médical'
            : 'Droit de consultation';
        $total   = round($tarif, 2);
        $monnaie = $mode === 'especes' ? max(0, round($montant_recu - $total, 2)) : 0.0;
        $statut  = (in_array($mode, ['carte','cheque','virement','assurance','gratuit']) || $montant_recu >= $total)
                    ? 'paye' : 'ouvert';
        $session_id_val = $session_ouverte ? (int) $session_ouverte['id'] : null;
        $num = gen_ticket_num();

        $vente_id = db_exec(
            "INSERT INTO caisse_ventes
             (numero_ticket, type_vente, patient_id, caissier_id, session_id, montant_total, montant_recu, monnaie_rendue, mode_paiement, statut, ordonnance_id, rdv_id, medecin_id, notes)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
            [$num, $type, $patient_id, $_SESSION['user_id'], $session_id_val, $total, $montant_recu, $monnaie, $mode, $statut, null, $rdv_id, $medecin_id, $notes]
        );

        // Ligne service : pas de médicament, libellé libre
        db_exec(
            "INSERT INTO caisse_lignes (vente_id, medicament_id, designation, quantite, prix_unitaire, remise_pct, total_ligne)
             VALUES (?, NULL, ?, 1, ?, 0, ?)",
            [$vente_id, $designation, $total, $total]
        );

        // Effets de bord uniquement si le ticket est payé
        if ($statut === 'paye') {
            if ($type === 'creation_dossier') {
                $dossier_id = create_dossier_from_ticket($patient_id, $vente_id, (int) $_SESSION['user_id']);
                if ($dossier_id) {
                    logActivity("Dossier médical créé (patient #$patient_id) via ticket $num", 'green', 'dossier', $dossier_id);
                }
            }
            if ($type === 'consultation' && $rdv_id) {
                db_exec("UPDATE rendez_vous SET caisse_vente_id = ? WHERE id = ?", [$vente_id, $rdv_id]);
            }
        }

        //  Comptabilité : écriture si ticket service payé
        if ($statut === 'paye') {
            compta_on_vente_caisse([
                'id' => (int)$vente_id, 'numero_ticket' => $num, 'type_vente' => $type,
                'montant_total' => $total, 'mode_paiement' => $mode, 'date_vente' => date('Y-m-d H:i:s'),
                'caissier_id' => $_SESSION['user_id'],
            ]);
        }

        logActivity("Ticket " . ($type === 'creation_dossier' ? 'création dossier' : 'consultation') . " " . fmt_money($total) . " — $num", 'green', 'caisse', $vente_id);
        header('Location: ' . APP_URL . '/caisse.php?ticket=' . $vente_id . '&print=1');
        exit;
    }
}

//  ANNULER vente
if (get_str('action') === 'annuler' && get_int('id') > 0) {
    $id = get_int('id');
    $v  = assert_owns('caisse_ventes', $id);
    if ($v['statut'] !== 'annule') {
        db_exec("UPDATE caisse_ventes SET statut='annule' WHERE id=?", [$id]);
        $lignes = db_select("SELECT * FROM caisse_lignes WHERE vente_id=?", [$id]);
        foreach ($lignes as $l) {
            db_exec("UPDATE medicaments SET stock_actuel = stock_actuel + ? WHERE id=?",
                    [$l['quantite'], $l['medicament_id']]);
            db_exec("UPDATE medicaments SET statut = CASE
                WHEN stock_actuel <= stock_minimum * 0.5 THEN 'critique'
                WHEN stock_actuel <= stock_minimum       THEN 'bas'
                ELSE 'normal' END WHERE id=?", [$l['medicament_id']]);
        }
        if ($v['ordonnance_id']) {
            db_exec("UPDATE ordonnances SET statut='active' WHERE id=? AND statut='terminee'", [$v['ordonnance_id']]);
        }
        compta_on_annulation_vente(['id' => (int)$id, 'caissier_id' => $_SESSION['user_id']]);
        logActivity("Ticket {$v['numero_ticket']} annulé — stock restitué", 'red', 'caisse', $id);
    }
    header('Location: ' . APP_URL . '/caisse.php?ok=annule');
    exit;
}

//  Layout inclus ici — après toute logique PHP/redirects
require_once __DIR__ . '/../includes/layout.php';
requirePageAccess('caisse');

//  PRÉREMPLISSAGE depuis pharmacie
$prefill_med = get_int('prefill_med');
$prefill_med_data = $prefill_med > 0
    ? db_row("SELECT id, nom, dosage, unite, prix_unitaire, stock_actuel FROM medicaments WHERE id=? AND stock_actuel>0", [$prefill_med])
    : null;

$prefill_ordonnance = get_int('ordonnance_id');
$prefill_ord_data   = null;
$prefill_ord_lignes = [];
if ($prefill_ordonnance > 0) {
    $prefill_ord_data = db_row(
        "SELECT o.*, CONCAT(p.prenom,' ',p.nom) AS patient_nom, p.id AS patient_id_val,
                p.numero AS patient_num_val,
                CONCAT(u.prenom,' ',u.nom) AS medecin_nom
         FROM ordonnances o
         JOIN patients p    ON p.id = o.patient_id
         JOIN utilisateurs u ON u.id = o.medecin_id
         WHERE o.id = ? AND o.statut = 'active'", [$prefill_ordonnance]
    );
    if ($prefill_ord_data) {
        $prefill_ord_lignes = db_select(
            "SELECT ol.*, m.nom AS med_nom, m.dosage AS med_dosage, m.prix_unitaire, m.stock_actuel, m.unite
             FROM ordonnance_lignes ol
             JOIN medicaments m ON m.id = ol.medicament_id
             WHERE ol.ordonnance_id = ?", [$prefill_ordonnance]
        );
    }
}

//  PRÉREMPLISSAGE ticket de service (depuis patients.php / appointments.php)
$prefill_ticket_type = in_whitelist(get_str('ticket_type'), ['creation_dossier','consultation'], '');
$prefill_ticket_pid  = get_int('patient_id');
$prefill_ticket_rdv  = get_int('rdv_id');
$prefill_ticket_med  = get_int('medecin_id');
$tarif_ticket_dossier = (float) setting('tarif_ticket_dossier', '0');
$tarif_consultation   = (float) setting('tarif_consultation', '0');

// Médecins actifs (pour le ticket de consultation)
$medecins_caisse = db_select("SELECT id, CONCAT(prenom,' ',nom) AS nom_complet FROM utilisateurs WHERE role = 'medecin' AND statut = 'actif' ORDER BY nom ASC");

// ══ Données POS ══

// Session de caisse active (résilient si la table n'existe pas encore)
$session_active = null;
$session_stats = null;
$cloture_data = null;
$can_open_session = can('caisse.session_ouvrir');
$can_close_session = can('caisse.session_fermer');

try {
    $session_active = db_row("SELECT s.*, CONCAT(u.prenom,' ',u.nom) AS caissier_nom FROM caisse_sessions s JOIN utilisateurs u ON u.id = s.caissier_id WHERE s.caissier_id = ? AND s.statut = 'ouverte'", [$_SESSION['user_id']]);
    if ($session_active) {
        $session_stats = [
            'ventes_payees' => (int)db_scalar("SELECT COUNT(*) FROM caisse_ventes WHERE session_id = ? AND statut = 'paye'", [$session_active['id']]),
            'total_ventes'  => (float)db_scalar("SELECT COALESCE(SUM(montant_total),0) FROM caisse_ventes WHERE session_id = ? AND statut = 'paye'", [$session_active['id']]),
        ];
    }
    if (get_int('cloture') > 0) {
        $cloture_data = db_row("SELECT s.*, CONCAT(u.prenom,' ',u.nom) AS caissier_nom FROM caisse_sessions s JOIN utilisateurs u ON u.id = s.caissier_id WHERE s.id = ?", [get_int('cloture')]);
    }
} catch (Exception $e) {
    // Table caisse_sessions inexistante — mode sans session
    _log_error('CAISSE', 'Table caisse_sessions absente, mode sans session', __FILE__, __LINE__, $e);
}

// Médicaments avec catégories
$medicaments = db_select("SELECT id, nom, categorie, dosage, unite, prix_unitaire, stock_actuel, stock_minimum FROM medicaments WHERE stock_actuel > 0 ORDER BY nom ASC");
$categories = array_unique(array_filter(array_column($medicaments, 'categorie')));
sort($categories);

// Patients (pool pour la recherche — limité côté client)
$patients = db_select("SELECT id, CONCAT(prenom,' ',nom) AS nom_complet, numero FROM patients ORDER BY nom ASC LIMIT 2000");

// S'assurer que le patient d'une ordonnance préremplie est dans le pool de recherche
if ($prefill_ord_data && !empty($prefill_ord_data['patient_id_val'])) {
    $pid = (int)$prefill_ord_data['patient_id_val'];
    $found = false;
    foreach ($patients as $p) { if ((int)$p['id'] === $pid) { $found = true; break; } }
    if (!$found) {
        $patients[] = ['id' => $pid, 'nom_complet' => $prefill_ord_data['patient_nom'], 'numero' => $prefill_ord_data['patient_num_val'] ?? ''];
    }
}

// Historique tickets
$search_tk  = get_str('search');
$date_tk    = get_str('date_filter') ?: date('Y-m-d');
$show_all   = get_str('date_filter') === 'all';
$where_tk   = $show_all ? 'WHERE 1=1' : "WHERE DATE(v.date_vente)=?";
$params_tk  = $show_all ? [] : [$date_tk];
if ($search_tk) {
    $like      = '%' . $search_tk . '%';
    $where_tk .= " AND (v.numero_ticket LIKE ? OR CONCAT(p.prenom,' ',p.nom) LIKE ?)";
    $params_tk = array_merge($params_tk, [$like, $like]);
}
$tickets = db_select(
    "SELECT v.*,
            CONCAT(p.prenom,' ',p.nom)    AS patient_nom,
            CONCAT(u.prenom,' ',u.nom)    AS caissier_nom,
            o.id                          AS ord_id,
            CONCAT(pm.prenom,' ',pm.nom)  AS ord_medecin
     FROM caisse_ventes v
     LEFT JOIN patients    p  ON p.id  = v.patient_id
     LEFT JOIN utilisateurs u ON u.id  = v.caissier_id
     LEFT JOIN ordonnances  o  ON o.id  = v.ordonnance_id
     LEFT JOIN utilisateurs pm ON pm.id = o.medecin_id
     $where_tk
     ORDER BY v.date_vente DESC LIMIT 60",
    $params_tk
);

// Stats globales
$ca_jour    = (float)db_scalar("SELECT COALESCE(SUM(montant_total),0) FROM caisse_ventes WHERE DATE(date_vente)=CURDATE() AND statut='paye'");
$nb_tickets = (int)db_scalar("SELECT COUNT(*) FROM caisse_ventes WHERE DATE(date_vente)=CURDATE()");
$nb_ouverts = (int)db_scalar("SELECT COUNT(*) FROM caisse_ventes WHERE statut='ouvert'");
$ca_mois    = (float)db_scalar("SELECT COALESCE(SUM(montant_total),0) FROM caisse_ventes WHERE MONTH(date_vente)=MONTH(CURDATE()) AND statut='paye'");
$nb_ord_att = (int)db_scalar("SELECT COUNT(*) FROM ordonnances WHERE statut='active'");

// Ticket à imprimer
$ticket_print = null;
$lignes_print = [];
if (get_int('ticket') > 0) {
    $ticket_print = db_row(
        "SELECT v.*, CONCAT(p.prenom,' ',p.nom) AS patient_nom, p.numero AS patient_num,
                CONCAT(u.prenom,' ',u.nom) AS caissier_nom,
                o.id AS ord_id, CONCAT(pm.prenom,' ',pm.nom) AS ord_medecin
         FROM caisse_ventes v
         LEFT JOIN patients     p  ON p.id  = v.patient_id
         LEFT JOIN utilisateurs u  ON u.id  = v.caissier_id
         LEFT JOIN ordonnances   o  ON o.id  = v.ordonnance_id
         LEFT JOIN utilisateurs pm ON pm.id  = o.medecin_id
         WHERE v.id = ?", [get_int('ticket')]
    );
    if ($ticket_print) {
        $lignes_print = db_select(
            "SELECT cl.*, m.nom AS med_nom, m.dosage, m.unite, cl.designation
             FROM caisse_lignes cl
             LEFT JOIN medicaments m ON m.id = cl.medicament_id
             WHERE cl.vente_id = ?", [$ticket_print['id']]
        );
    }
}

$modeLabels  = ['especes'=>'Espèces','carte'=>'Carte bancaire','cheque'=>'Chèque','virement'=>'Virement','assurance'=>'Assurance','mobile_money'=>'Mobile Money','gratuit'=>'Gratuit'];
$statutBadge = ['ouvert'=>'badge-yellow','paye'=>'badge-green','annule'=>'badge-red','rembourse'=>'badge-blue'];
$statutLabel = ['ouvert'=>'⏳ En attente','paye'=>'✅ Payé','annule'=>'✕ Annulé','rembourse'=>'↩ Remboursé'];
?>

<?php if (!$cloture_data && $session_active): ?>
<?php if (!empty($flash)): ?><div class="alert alert-red alert-auto">⚠️ <?= h($flash) ?></div><?php endif; ?>
<?php if (get_str('ok') === 'annule'): ?><div class="alert alert-yellow alert-auto">✓ Ticket annulé — stock restitué et ordonnance remise en attente.</div><?php endif; ?>
<?php if (get_str('session') === '1'): ?><div class="alert alert-green alert-auto">✓ Session de caisse ouverte</div><?php endif; ?>
<?php endif; ?>

<?php if ($cloture_data): ?>
<!-- ══ RÉSUMÉ DE CLÔTURE ══ -->
<div class="pos-cloture-card">
    <h3>📋 Clôture de session</h3>
    <div class="pos-cloture-grid">
    <div class="pos-cloture-row"><span>Caissier</span><strong><?= h($cloture_data['caissier_nom']) ?></strong></div>
    <div class="pos-cloture-row"><span>Ouverture</span><strong><?= fmt_date($cloture_data['ouverture_date'], true) ?></strong></div>
    <div class="pos-cloture-row"><span>Fermeture</span><strong><?= fmt_date($cloture_data['fermeture_date'], true) ?></strong></div>
    <div class="pos-cloture-row"><span>Fond de caisse</span><strong><?= fmt_money((float)$cloture_data['fond_caisse']) ?></strong></div>
    <div class="pos-cloture-row"><span>Nombre de ventes</span><strong><?= (int)$cloture_data['ventes_count'] ?></strong></div>
    <div class="pos-cloture-row"><span>Total des ventes</span><strong><?= fmt_money((float)$cloture_data['total_theorique'] - (float)$cloture_data['fond_caisse']) ?></strong></div>
    <div class="pos-cloture-row"><span>Total théorique</span><strong><?= fmt_money((float)$cloture_data['total_theorique']) ?></strong></div>
    <div class="pos-cloture-row"><span>Total réel</span><strong><?= fmt_money((float)$cloture_data['total_reel']) ?></strong></div>
    <div class="pos-cloture-row full <?= $cloture_data['ecart'] > 0 ? 'ecart-pos' : ($cloture_data['ecart'] < 0 ? 'ecart-neg' : '') ?>">
        <span>Écart</span><strong><?= fmt_money(abs((float)$cloture_data['ecart'])) ?> <?= $cloture_data['ecart'] > 0 ? '(excédent)' : ($cloture_data['ecart'] < 0 ? '(manquant)' : '') ?></strong>
    </div>
    <?php if ($cloture_data['notes_fermeture']): ?>
    <div class="pos-cloture-row full"><span>Observations</span><em><?= h($cloture_data['notes_fermeture']) ?></em></div>
    <?php endif; ?>
    </div>
    <div style="margin-top:24px;text-align:center">
        <a href="caisse.php" class="btn btn-blue">Retour à la caisse</a>
    </div>
</div>
<?php elseif ($session_active): ?>
<!-- ══ POS LAYOUT (session active) ══ -->
<div class="pos-layout">
    <!-- PANIER (gauche) -->
    <aside class="pos-cart-panel">
        <div class="pos-cart-header">
            <h3>🛒 Panier</h3>
            <span class="pos-session-badge">Session #<?= (int)$session_active['id'] ?></span>
        </div>

        <!-- Sélecteur patient (avec recherche) -->
        <div class="pos-cart-patient">
            <div class="pos-patient-combobox" id="pos-patient-combobox">
                <input type="text" id="pos-patient-search" class="pos-patient-search"
                       placeholder="🔎 Rechercher un patient…"
                       autocomplete="off"
                       oninput="POSSession.searchPatients(this.value)"
                       onfocus="POSSession.searchPatients('')">
                <input type="hidden" id="pos-patient-id" value="">
                <button type="button" id="pos-patient-clear" class="pos-patient-clear" style="display:none" onclick="POSSession.clearPatient()" title="Vente anonyme">✕</button>
                <div class="pos-patient-dropdown" id="pos-patient-dropdown"></div>
            </div>
        </div>

        <!-- Articles du panier -->
        <div id="pos-cart-items" class="pos-cart-items">
            <div class="pos-cart-empty">🛒 Ajoutez des articles</div>
        </div>

        <!-- Totaux -->
        <div class="pos-cart-totals">
            <div class="pos-total-row">
                <span>Sous-total</span>
                <span id="pos-subtotal">0</span>
            </div>
            <div class="pos-total-row pos-grand-total">
                <span>TOTAL</span>
                <span id="pos-total-display">0</span>
            </div>
        </div>

        <!-- Bouton encaisser -->
        <?php if (can('caisse.create_vente')): ?>
        <button id="pos-pay-btn" class="pos-pay-btn" disabled onclick="POSSession.openPaymentModal()">Valider</button>
        <?php endif; ?>

        <!-- Contrôle session -->
        <?php if ($can_close_session): ?>
        <div class="pos-session-controls">
            <button type="button" class="btn btn-ghost btn-sm" onclick="POSSession.openClotureModal()" style="color:var(--yellow)">🔒 Fermer la session</button>
        </div>
        <?php endif; ?>
    </aside>

    <!-- PRODUITS (droite) -->
    <main class="pos-product-panel">
        <!-- Tickets de service (dossier / consultation) -->
        <?php if (can('caisse.ticket_service')): ?>
        <div class="pos-service-bar">
            <button type="button" class="pos-service-btn" onclick="openTicketService('creation_dossier')">
                <span class="pos-service-ico">📂</span>
                <span class="pos-service-txt">
                    <strong>Création de dossier</strong>
                    <small><?= fmt_money($tarif_ticket_dossier) ?></small>
                </span>
            </button>
            <button type="button" class="pos-service-btn" onclick="openTicketService('consultation')">
                <span class="pos-service-ico">🩺</span>
                <span class="pos-service-txt">
                    <strong>Droit de consultation</strong>
                    <small><?= fmt_money($tarif_consultation) ?></small>
                </span>
            </button>
        </div>
        <?php endif; ?>

        <!-- Barre de recherche -->
        <div class="pos-search-bar">
            <input type="text" id="pos-search" placeholder="🔍 Rechercher un médicament..." autocomplete="off" autofocus>
        </div>

        <!-- Onglets catégories -->
        <div class="pos-category-tabs" id="pos-category-tabs">
            <button class="pos-cat-tab active" data-cat="all" onclick="POSSession.filterByCategory('all')">Tous</button>
            <?php foreach ($categories as $cat): ?>
            <button class="pos-cat-tab" data-cat="<?= h($cat) ?>" onclick="POSSession.filterByCategory('<?= h($cat) ?>')"><?= h(ucfirst($cat)) ?></button>
            <?php endforeach; ?>
        </div>

        <!-- Grille produits -->
        <div id="pos-product-grid" class="pos-product-grid">
            <!-- Rempli par JS -->
        </div>
    </main>
</div>

<!-- ══ MODAL PAIEMENT ══ -->
<div id="modal-payment" class="modal-overlay" style="display:none;z-index:300;align-items:center;justify-content:center" role="dialog" aria-modal="true" onclick="if(event.target===this)closeModal('modal-payment')">
    <div style="background:var(--surface);border:1px solid var(--border2);border-radius:16px;width:min(520px,95vw);max-height:90vh;overflow-y:auto;box-shadow:0 24px 60px rgba(0,0,0,.7)">

        <div style="padding:18px 24px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;background:var(--surface);border-radius:16px 16px 0 0;z-index:1">
            <h3 style="font-size:16px;font-weight:700">💳 Paiement</h3>
            <button type="button" onclick="closeModal('modal-payment')" style="width:30px;height:30px;background:var(--surface2);border:1px solid var(--border2);border-radius:8px;color:var(--text2);cursor:pointer;font-size:14px;display:flex;align-items:center;justify-content:center">✕</button>
        </div>

        <form method="POST" id="pos-payment-form" action="caisse.php" style="padding:24px">
            <input type="hidden" name="action" value="create_vente">
            <?= csrf_field() ?>
            <input type="hidden" name="patient_id" id="pos-form-patient" value="">
            <input type="hidden" name="ordonnance_id" id="pos-form-ordonnance" value="<?= (int)($prefill_ordonnance ?? 0) ?>">

            <div id="pos-form-lines-container">
                <!-- Inputs cachés pour chaque ligne du panier — remplis par JS -->
            </div>

            <!-- Résumé panier -->
            <div style="font-size:11px;font-weight:600;color:var(--text2);text-transform:uppercase;letter-spacing:.04em;margin-bottom:8px">Résumé</div>
            <div id="pos-pay-summary-items" class="pos-pay-summary">
                <!-- Rempli par JS -->
            </div>

            <!-- Mode de paiement -->
            <div style="font-size:11px;font-weight:600;color:var(--text2);text-transform:uppercase;letter-spacing:.04em;margin:16px 0 8px">Mode de paiement</div>
            <div class="pos-pay-methods" id="pos-pay-methods">
                <label class="pos-pay-method active" data-mode="especes" onclick="POSSession.selectPayMethod('especes')">
                    <input type="radio" name="mode_paiement" value="especes" checked style="display:none"> 💵 Espèces
                </label>
                <label class="pos-pay-method" data-mode="carte" onclick="POSSession.selectPayMethod('carte')">
                    <input type="radio" name="mode_paiement" value="carte" style="display:none"> 💳 Carte
                </label>
                <label class="pos-pay-method" data-mode="mobile_money" onclick="POSSession.selectPayMethod('mobile_money')">
                    <input type="radio" name="mode_paiement" value="mobile_money" style="display:none"> 📱 Mobile
                </label>
                <label class="pos-pay-method" data-mode="cheque" onclick="POSSession.selectPayMethod('cheque')">
                    <input type="radio" name="mode_paiement" value="cheque" style="display:none"> 📄 Chèque
                </label>
                <label class="pos-pay-method" data-mode="assurance" onclick="POSSession.selectPayMethod('assurance')">
                    <input type="radio" name="mode_paiement" value="assurance" style="display:none"> 🏥 Assurance
                </label>
                <label class="pos-pay-method" data-mode="gratuit" onclick="POSSession.selectPayMethod('gratuit')">
                    <input type="radio" name="mode_paiement" value="gratuit" style="display:none"> 🎁 Gratuit
                </label>
            </div>

            <!-- Montant reçu (espèces) -->
            <div id="pos-zone-recu" class="form-group" style="margin:16px 0">
                <label>Montant reçu (<?= h($s['currency_symbol'] ?? '') ?>)</label>
                <input type="number" name="montant_recu" id="pos-montant-recu" step="0.01" min="0" value="" oninput="POSSession.calculateChange()" style="font-size:20px;font-weight:700;text-align:center;padding:12px">
            </div>

            <!-- Monnaie à rendre -->
            <div id="pos-zone-monnaie" class="form-group" style="margin:16px 0">
                <label>Monnaie à rendre</label>
                <div id="pos-monnaie-display" class="pos-change-display">0</div>
            </div>

            <!-- Notes -->
            <div class="form-group" style="margin:16px 0">
                <label>Notes</label>
                <input type="text" name="notes" id="pos-form-notes" maxlength="255" placeholder="Notes optionnelles..." style="padding:10px 12px">
            </div>

            <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:20px;padding-top:16px;border-top:1px solid var(--border)">
                <button type="button" class="btn btn-ghost" onclick="closeModal('modal-payment')">Annuler</button>
                <button type="submit" class="btn btn-blue" style="padding:12px 24px;font-size:14px;font-weight:700">✓ Valider & Générer ticket</button>
            </div>
        </form>
    </div>
</div>

<!-- ══ MODAL TICKET DE SERVICE (dossier / consultation) ══ -->
<?php if (can('caisse.ticket_service') && $session_active): ?>
<div id="modal-ticket-service" class="modal-overlay" style="display:none;z-index:300;align-items:center;justify-content:center" role="dialog" aria-modal="true" onclick="if(event.target===this)closeModal('modal-ticket-service')">
    <div style="background:var(--surface);border:1px solid var(--border2);border-radius:16px;width:min(520px,95vw);max-height:90vh;overflow-y:auto;box-shadow:0 24px 60px rgba(0,0,0,.7)">

        <div style="padding:18px 24px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;background:var(--surface);border-radius:16px 16px 0 0;z-index:1">
            <h3 id="svc-title" style="font-size:16px;font-weight:700">🩺 Ticket de service</h3>
            <button type="button" onclick="closeModal('modal-ticket-service')" style="width:30px;height:30px;background:var(--surface2);border:1px solid var(--border2);border-radius:8px;color:var(--text2);cursor:pointer;font-size:14px;display:flex;align-items:center;justify-content:center">✕</button>
        </div>

        <form method="POST" action="caisse.php" style="padding:24px" id="svc-form">
            <input type="hidden" name="action" value="create_ticket_service">
            <?= csrf_field() ?>
            <input type="hidden" name="ticket_type" id="svc-type" value="">
            <input type="hidden" name="rdv_id" id="svc-rdv" value="<?= (int) $prefill_ticket_rdv ?>">

            <!-- Patient (requis) -->
            <div class="form-group" style="margin-bottom:16px">
                <label>Patient <span style="color:var(--red)">*</span></label>
                <div class="pos-patient-combobox" style="position:relative">
                    <input type="text" id="svc-patient-search" class="pos-patient-search"
                           placeholder="🔎 Rechercher un patient…"
                           autocomplete="off"
                           oninput="SvcTicket.searchPatients(this.value)"
                           onfocus="SvcTicket.searchPatients('')">
                    <input type="hidden" name="patient_id" id="svc-patient-id" value="">
                    <button type="button" id="svc-patient-clear" class="pos-patient-clear" style="display:none" onclick="SvcTicket.clearPatient()" title="Effacer">✕</button>
                    <div class="pos-patient-dropdown" id="svc-patient-dropdown"></div>
                </div>
            </div>

            <!-- Médecin (optionnel, consultation seulement) -->
            <div class="form-group" id="svc-medecin-group" style="margin-bottom:16px;display:none">
                <label>Médecin (optionnel)</label>
                <select name="medecin_id" id="svc-medecin">
                    <option value="">— Non spécifié —</option>
                    <?php foreach ($medecins_caisse as $m): ?>
                    <option value="<?= (int) $m['id'] ?>" <?= $prefill_ticket_med === (int) $m['id'] ? 'selected' : '' ?>><?= h($m['nom_complet']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Montant (lecture seule, depuis les paramètres) -->
            <div class="form-group" style="margin-bottom:16px">
                <label>Montant à encaisser</label>
                <div id="svc-montant" style="font-size:24px;font-weight:700;text-align:center;padding:14px;background:var(--bg);border:1px solid var(--border2);border-radius:8px">—</div>
            </div>

            <!-- Mode de paiement -->
            <div style="font-size:11px;font-weight:600;color:var(--text2);text-transform:uppercase;letter-spacing:.04em;margin:16px 0 8px">Mode de paiement</div>
            <div class="pos-pay-methods" id="svc-pay-methods">
                <label class="pos-pay-method active" data-mode="especes" onclick="SvcTicket.selectPayMethod('especes')">
                    <input type="radio" name="mode_paiement" value="especes" checked style="display:none"> 💵 Espèces
                </label>
                <label class="pos-pay-method" data-mode="carte" onclick="SvcTicket.selectPayMethod('carte')">
                    <input type="radio" name="mode_paiement" value="carte" style="display:none"> 💳 Carte
                </label>
                <label class="pos-pay-method" data-mode="mobile_money" onclick="SvcTicket.selectPayMethod('mobile_money')">
                    <input type="radio" name="mode_paiement" value="mobile_money" style="display:none"> 📱 Mobile
                </label>
                <label class="pos-pay-method" data-mode="cheque" onclick="SvcTicket.selectPayMethod('cheque')">
                    <input type="radio" name="mode_paiement" value="cheque" style="display:none"> 📄 Chèque
                </label>
                <label class="pos-pay-method" data-mode="assurance" onclick="SvcTicket.selectPayMethod('assurance')">
                    <input type="radio" name="mode_paiement" value="assurance" style="display:none"> 🏥 Assurance
                </label>
                <label class="pos-pay-method" data-mode="gratuit" onclick="SvcTicket.selectPayMethod('gratuit')">
                    <input type="radio" name="mode_paiement" value="gratuit" style="display:none"> 🎁 Gratuit
                </label>
            </div>

            <!-- Montant reçu (espèces) -->
            <div id="svc-zone-recu" class="form-group" style="margin:16px 0">
                <label>Montant reçu (<?= h($s['currency_symbol'] ?? '') ?>)</label>
                <input type="number" name="montant_recu" id="svc-montant-recu" step="0.01" min="0" value="" oninput="SvcTicket.calculateChange()" style="font-size:20px;font-weight:700;text-align:center;padding:12px">
            </div>

            <!-- Monnaie à rendre -->
            <div id="svc-zone-monnaie" class="form-group" style="margin:16px 0">
                <label>Monnaie à rendre</label>
                <div id="svc-monnaie-display" class="pos-change-display">0</div>
            </div>

            <!-- Notes -->
            <div class="form-group" style="margin:16px 0">
                <label>Notes</label>
                <input type="text" name="notes" id="svc-notes" maxlength="255" placeholder="Notes optionnelles..." style="padding:10px 12px">
            </div>

            <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:20px;padding-top:16px;border-top:1px solid var(--border)">
                <button type="button" class="btn btn-ghost" onclick="closeModal('modal-ticket-service')">Annuler</button>
                <button type="submit" class="btn btn-blue" style="padding:12px 24px;font-size:14px;font-weight:700">✓ Valider le ticket</button>
            </div>
        </form>
    </div>
</div>

<script>
const SVC_TARIFS = {
    creation_dossier: <?= json_encode($tarif_ticket_dossier) ?>,
    consultation:      <?= json_encode($tarif_consultation) ?>
};
const SVC_PATIENTS = <?= json_encode(array_map(fn($p) => ['id' => (int) $p['id'], 'nom' => $p['nom_complet'], 'numero' => $p['numero'] ?? ''], $patients)) ?>;
const SVC_PREFILL = { type: <?= json_encode($prefill_ticket_type) ?>, patient_id: <?= (int) $prefill_ticket_pid ?>, rdv_id: <?= (int) $prefill_ticket_rdv ?>, medecin_id: <?= (int) $prefill_ticket_med ?> };

const SvcTicket = {
    mode: 'especes',
    montant: 0,
    searchTimer: null,
    open(type) {
        document.getElementById('svc-type').value = type;
        const isCons = type === 'consultation';
        document.getElementById('svc-medecin-group').style.display = isCons ? '' : 'none';
        document.getElementById('svc-title').textContent = isCons ? '🩺 Droit de consultation' : '📂 Création de dossier médical';
        this.montant = parseFloat(SVC_TARIFS[type] || 0);
        document.getElementById('svc-montant').textContent = fmtMoneySvc(this.montant);
        // Reset
        this.clearPatient();
        document.getElementById('svc-montant-recu').value = '';
        document.getElementById('svc-monnaie-display').textContent = '0';
        document.querySelector('#svc-pay-methods .pos-pay-method.active')?.classList.remove('active');
        const first = document.querySelector('#svc-pay-methods .pos-pay-method[data-mode="especes"]');
        if (first) first.classList.add('active');
        this.mode = 'especes';
        document.querySelector('#svc-pay-methods input[value="especes"]').checked = true;
        // Pré-remplissage patient (depuis patients.php / appointments.php)
        if (SVC_PREFILL.patient_id > 0) {
            const p = SVC_PATIENTS.find(x => x.id === SVC_PREFILL.patient_id);
            if (p) this.selectPatient(p.id, p.nom + (p.numero ? ' (' + p.numero + ')' : ''));
        }
        if (SVC_PREFILL.medecin_id > 0 && isCons) document.getElementById('svc-medecin').value = String(SVC_PREFILL.medecin_id);
        openModal('modal-ticket-service');
    },
    selectPatient(id, label) {
        document.getElementById('svc-patient-id').value = id;
        document.getElementById('svc-patient-search').value = label;
        document.getElementById('svc-patient-dropdown').style.display = 'none';
        document.getElementById('svc-patient-clear').style.display = '';
    },
    clearPatient() {
        document.getElementById('svc-patient-id').value = '';
        document.getElementById('svc-patient-search').value = '';
        document.getElementById('svc-patient-dropdown').style.display = 'none';
        document.getElementById('svc-patient-clear').style.display = 'none';
    },
    searchPatients(q) {
        clearTimeout(this.searchTimer);
        this.searchTimer = setTimeout(() => {
            const dd = document.getElementById('svc-patient-dropdown');
            q = (q || '').toLowerCase().trim();
            const res = SVC_PATIENTS.filter(p => !q || p.nom.toLowerCase().includes(q) || (p.numero || '').toLowerCase().includes(q)).slice(0, 12);
            if (!res.length) { dd.style.display = 'none'; return; }
            dd.innerHTML = res.map(p => `<div class="pos-patient-option" onclick="SvcTicket.selectPatient(${p.id}, ${JSON.stringify(p.nom + (p.numero ? ' (' + p.numero + ')' : ''))})">${p.nom}${p.numero ? ' <span style="color:var(--text3);font-size:11px">' + p.numero + '</span>' : ''}</div>`).join('');
            dd.style.display = 'block';
        }, 120);
    },
    selectPayMethod(mode) {
        this.mode = mode;
        document.querySelectorAll('#svc-pay-methods .pos-pay-method').forEach(el => el.classList.toggle('active', el.dataset.mode === mode));
        document.querySelectorAll('#svc-pay-methods input').forEach(i => i.checked = (i.value === mode));
        this.calculateChange();
    },
    calculateChange() {
        const recu = parseFloat(document.getElementById('svc-montant-recu').value || 0);
        const monnaie = this.mode === 'especes' ? Math.max(0, recu - this.montant) : 0;
        document.getElementById('svc-monnaie-display').textContent = fmtMoneySvc(monnaie);
    }
};
function fmtMoneySvc(v) {
    // Formatage léger (le PHP fmt_money reste la référence pour l'affichage serveur)
    return new Intl.NumberFormat('fr-FR', { minimumFractionDigits: 0, maximumFractionDigits: 2 }).format(v) + ' <?= h($s['currency_symbol'] ?? '') ?>';
}
function openTicketService(type) { SvcTicket.open(type); }
// Auto-ouverture si un ticket_type est pré-rempli dans l'URL
<?php if ($prefill_ticket_type !== '' && $session_active): ?>
document.addEventListener('DOMContentLoaded', () => SvcTicket.open(<?= json_encode($prefill_ticket_type) ?>));
<?php endif; ?>
</script>
<?php endif; ?>

<!-- ══ MODAL CLÔTURE ══ -->
<?php if ($can_close_session && $session_active): ?>
<div id="modal-cloture" class="modal-overlay" style="display:none;z-index:300;align-items:center;justify-content:center" role="dialog" aria-modal="true" onclick="if(event.target===this)closeModal('modal-cloture')">
    <div style="background:var(--surface);border:1px solid var(--border2);border-radius:16px;width:min(460px,95vw);box-shadow:0 24px 60px rgba(0,0,0,.7)">

        <div style="padding:18px 24px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between">
            <h3 style="font-size:16px;font-weight:700">🔒 Clôture de session</h3>
            <button type="button" onclick="closeModal('modal-cloture')" style="width:30px;height:30px;background:var(--surface2);border:1px solid var(--border2);border-radius:8px;color:var(--text2);cursor:pointer;font-size:14px;display:flex;align-items:center;justify-content:center">✕</button>
        </div>

        <form method="POST" action="caisse.php" style="padding:24px">
            <input type="hidden" name="action" value="fermer_session">
            <input type="hidden" name="session_id" value="<?= (int)$session_active['id'] ?>">
            <?= csrf_field() ?>

            <div style="background:var(--surface2);border-radius:10px;padding:16px;margin-bottom:20px">
                <div class="pos-cloture-row" style="border:none"><span>Fond de caisse</span><strong><?= fmt_money((float)$session_active['fond_caisse']) ?></strong></div>
                <div class="pos-cloture-row" style="border:none"><span>Ventes (<?= $session_stats['ventes_payees'] ?>)</span><strong><?= fmt_money($session_stats['total_ventes']) ?></strong></div>
                <div class="pos-cloture-row" style="border:none;padding-bottom:12px;border-bottom:1px solid var(--border)"><span>Total théorique</span><strong id="pos-cloture-theorique" data-value="<?= $session_active['fond_caisse'] + $session_stats['total_ventes'] ?>"><?= fmt_money((float)$session_active['fond_caisse'] + $session_stats['total_ventes']) ?></strong></div>
            </div>

            <div class="form-group" style="margin-bottom:16px">
                <label>Total réel en caisse (<?= h($s['currency_symbol'] ?? '') ?>)</label>
                <input type="number" name="total_reel" id="pos-cloture-reel" step="0.01" min="0" value="" required oninput="POSSession.calculateEcart()" style="font-size:18px;font-weight:700;text-align:center;padding:12px">
            </div>

            <div class="form-group" style="margin-bottom:16px">
                <label>Écart</label>
                <div style="display:flex;align-items:center;gap:8px">
                    <span id="pos-cloture-ecart-label" style="font-size:12px;color:var(--text3)">—</span>
                    <span id="pos-cloture-ecart" style="font-size:20px;font-weight:700;color:var(--yellow)">0</span>
                </div>
            </div>

            <div class="form-group" style="margin-bottom:20px">
                <label>Notes de clôture (optionnel)</label>
                <input type="text" name="notes_fermeture" maxlength="500" placeholder="Observations...">
            </div>

            <div style="display:flex;gap:10px;justify-content:flex-end">
                <button type="button" class="btn btn-ghost" onclick="closeModal('modal-cloture')">Annuler</button>
                <button type="submit" class="btn btn-red" style="font-weight:700">🔒 Fermer la session</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php else: ?>
<!-- ══ PAS DE SESSION — OUVERTURE ══ -->
<div class="pos-session-open-card">
    <h3>🔓 Ouvrir une session de caisse</h3>
    <p style="color:var(--text3);font-size:13px;margin-bottom:24px">Vous devez ouvrir une session avant d'enregistrer des ventes.</p>

    <?php if ($can_open_session): ?>
    <form method="POST" action="caisse.php">
        <input type="hidden" name="action" value="ouvrir_session">
        <?= csrf_field() ?>
        <div class="form-group" style="margin-bottom:20px">
            <label>Fond de caisse (<?= h($s['currency_symbol'] ?? '') ?>)</label>
            <input type="number" name="fond_caisse" step="0.01" min="0" value="0" required style="font-size:20px;font-weight:700;text-align:center;padding:12px">
        </div>
        <button type="submit" class="btn btn-blue" style="width:100%;padding:14px;font-size:15px;font-weight:700">Ouvrir la session</button>
    </form>
    <?php else: ?>
    <div class="alert alert-red" style="margin-top:16px">Vous n'avez pas la permission d'ouvrir une session de caisse.</div>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php if (!$cloture_data && $session_active): ?>
<!-- ══ STATS ══ -->
<div class="stats-grid mb-24" style="<?= $session_active ? 'display:none' : '' ?>">
    <div class="stat-card green">
        <div class="stat-icon green">💰</div>
        <div class="stat-value"><?= fmt_money($ca_jour) ?></div>
        <div class="stat-label">CA aujourd'hui</div>
    </div>
    <div class="stat-card blue">
        <div class="stat-icon blue">🧾</div>
        <div class="stat-value"><?= $nb_tickets ?></div>
        <div class="stat-label">Tickets du jour</div>
    </div>
    <div class="stat-card yellow">
        <div class="stat-icon yellow">⏳</div>
        <div class="stat-value"><?= $nb_ouverts ?></div>
        <div class="stat-label">En attente</div>
    </div>
    <div class="stat-card purple">
        <div class="stat-icon purple">💳</div>
        <div class="stat-value"><?= fmt_money($ca_mois) ?></div>
        <div class="stat-label">CA ce mois</div>
    </div>
</div>
<?php endif; ?>

<!-- Bannière ordonnance préremplie -->
<?php if ($prefill_ord_data && !$cloture_data && $session_active): ?>
<div class="alert alert-green mb-24" style="margin-bottom:16px">
    <strong>Ordonnance #<?= (int)$prefill_ordonnance ?> chargée</strong>
    Patient : <strong><?= h($prefill_ord_data['patient_nom']) ?></strong>
    Médecin : <?= h($prefill_ord_data['medecin_nom']) ?>
    <?= count($prefill_ord_lignes) ?> médicament(s)
    <br><small style="opacity:.8">Les médicaments seront ajoutés au panier automatiquement.</small>
</div>
<?php elseif ($prefill_med_data && !$cloture_data && $session_active): ?>
<div class="alert alert-green mb-24" style="margin-bottom:16px">
    Médicament sélectionné : <strong><?= h($prefill_med_data['nom']) ?> <?= h($prefill_med_data['dosage'] ?? '') ?></strong>
    Stock : <?= (int)$prefill_med_data['stock_actuel'] ?> — Prix : <?= fmt_money((float)$prefill_med_data['prix_unitaire']) ?>
</div>
<?php endif; ?>

<?php if (!$cloture_data && $session_active): ?>
<!-- ══ HISTORIQUE TICKETS ══ -->
<div class="card mb-24" style="<?= $session_active ? 'display:none' : '' ?>" id="ticket-history">
    <div class="card-header">
        <h3>🧾 Historique des ventes</h3>
        <form method="GET" style="display:flex;gap:8px;flex-wrap:wrap">
            <input type="date" name="date_filter" value="<?= $show_all ? '' : h($date_tk) ?>"
                   style="padding:6px 10px;background:var(--bg);border:1px solid var(--border2);border-radius:6px;color:var(--text);font-size:12px;outline:none"
                   onchange="this.form.submit()">
            <a href="caisse.php?date_filter=all<?= $search_tk?'&search='.urlencode($search_tk):'' ?>" class="btn btn-sm <?= $show_all?'btn-blue':'btn-ghost' ?>">Tous</a>
            <input type="text" name="search" value="<?= h($search_tk) ?>"
                   placeholder="N° ticket, patient..."
                   style="padding:6px 12px;background:var(--bg);border:1px solid var(--border2);border-radius:6px;color:var(--text);font-family:inherit;font-size:12px;outline:none;width:200px">
            <button type="submit" class="btn btn-sm btn-blue">Chercher</button>
            <?php if ($search_tk): ?><a href="caisse.php" class="btn btn-sm btn-ghost">✕</a><?php endif; ?>
        </form>
    </div>
    <table class="tbl-actions">
        <thead>
            <tr>
                <th>N° Ticket</th><th>Date</th><th>Patient</th><th>Ordonnance</th>
                <th>Total</th><th>Mode</th><th>Caissier</th><th>Statut</th><th class="col-actions">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($tickets as $t): ?>
            <tr>
                <td><strong><?= h($t['numero_ticket']) ?></strong></td>
                <td style="font-size:12px"><?= fmt_date($t['date_vente'], true) ?></td>
                <td><?= $t['patient_nom'] ? h($t['patient_nom']) : '<span style="color:var(--text3)">Anonyme</span>' ?></td>
                <td>
                    <?php if ($t['ord_id']): ?>
                    <a href="pharmacie.php?tab=ordonnances" style="color:var(--accent2);font-size:12px;display:flex;align-items:center;gap:4px">
                        📋 Ord. #<?= (int)$t['ord_id'] ?>
                        <?php if ($t['ord_medecin']): ?><span style="color:var(--text3)">— <?= h($t['ord_medecin']) ?></span><?php endif; ?>
                    </a>
                    <?php else: ?>
                    <span style="color:var(--text3);font-size:12px">Vente directe</span>
                    <?php endif; ?>
                </td>
                <td><strong style="color:var(--green)"><?= fmt_money((float)$t['montant_total']) ?></strong></td>
                <td style="font-size:12px"><?= h($modeLabels[$t['mode_paiement']] ?? $t['mode_paiement']) ?></td>
                <td style="font-size:12px;color:var(--text2)"><?= h($t['caissier_nom']) ?></td>
                <td><span class="badge <?= $statutBadge[$t['statut']] ?? 'badge-gray' ?>"><?= $statutLabel[$t['statut']] ?? $t['statut'] ?></span></td>
                <td>
                    <div class="row-actions">
                        <span class="row-hint" aria-hidden="true">⋯</span>
                        <a href="caisse.php?ticket=<?= (int)$t['id'] ?>" class="btn btn-sm btn-blue">🧾 Voir</a>
                        <?php if ($t['statut'] !== 'annule'): ?>
                        <a href="caisse.php?action=annuler&id=<?= (int)$t['id'] ?>"
                           class="btn btn-sm btn-red"
                           onclick="return confirm('Annuler ce ticket ?\nLe stock sera restitué<?= $t['ord_id'] ? " et l'ordonnance remise en attente." : "." ?>')"></a>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($tickets)): ?>
            <tr><td colspan="9" style="text-align:center;padding:32px;color:var(--text3)">Aucun ticket</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<!-- ══ MODAL TICKET ══ -->
<?php if ($ticket_print): ?>
<div id="modal-ticket" class="modal-overlay" style="z-index:300;display:flex;align-items:center;justify-content:center" role="dialog" aria-modal="true" onclick="if(event.target===this)this.style.display='none'">
    <div style="background:#fff;border-radius:12px;width:min(380px,95vw);max-height:90vh;overflow-y:auto;box-shadow:0 24px 60px rgba(0,0,0,.7)">
        <div style="background:var(--surface);border-radius:12px 12px 0 0;padding:12px 16px;display:flex;gap:8px;justify-content:flex-end">
            <button class="btn btn-blue btn-sm" onclick="printTicket()">🖨️ Imprimer</button>
            <a href="pharmacie.php?tab=ordonnances" class="btn btn-ghost btn-sm">💊 Pharmacie</a>
            <button class="btn btn-ghost btn-sm" onclick="document.getElementById('modal-ticket').style.display='none'">✕ Fermer</button>
        </div>

        <div id="ticket-content" style="font-family:'Courier New',monospace;color:#000;padding:20px;font-size:12px;line-height:1.7">
            <div style="text-align:center;border-bottom:2px dashed #000;padding-bottom:12px;margin-bottom:12px">
                <div style="font-size:16px;font-weight:bold">🏥 <?= h($s['etablissement'] ?? 'MediCore ERP') ?></div>
                <div style="font-size:11px">Pharmacie Hospitalière</div>
            </div>

            <div style="border-bottom:1px dashed #aaa;padding-bottom:10px;margin-bottom:10px">
                <?php
                $infos = [
                    'Ticket'   => $ticket_print['numero_ticket'],
                    'Date'     => fmt_date($ticket_print['date_vente'], true),
                    'Caissier' => $ticket_print['caissier_nom'],
                ];
                if ($ticket_print['patient_nom'])  $infos['Patient'] = $ticket_print['patient_nom'];
                if ($ticket_print['patient_num'])  $infos['Dossier'] = $ticket_print['patient_num'];
                if ($ticket_print['ord_id'])       $infos['Ordonnance'] = '#' . $ticket_print['ord_id'] . '  ' . ($ticket_print['ord_medecin'] ?? '');
                foreach ($infos as $k => $v):
                ?>
                <div style="display:flex;justify-content:space-between;font-size:11px">
                    <span><strong><?= $k ?> :</strong></span><span><?= h($v) ?></span>
                </div>
                <?php endforeach; ?>
            </div>

            <div style="border-bottom:1px dashed #aaa;padding-bottom:10px;margin-bottom:10px">
                <div style="font-weight:bold;margin-bottom:8px;text-decoration:underline">ARTICLES</div>
                <?php foreach ($lignes_print as $l):
                    $libelle = $l['designation'] !== null ? $l['designation'] : trim(($l['med_nom'] ?? '') . ' ' . ($l['dosage'] ?? ''));
                ?>
                <div style="margin-bottom:6px">
                    <div style="font-weight:bold"><?= h($libelle) ?></div>
                    <div style="display:flex;justify-content:space-between;font-size:11px;color:#555">
                        <span><?= (int)$l['quantite'] ?> x <?= fmt_money((float)$l['prix_unitaire']) ?><?= $l['remise_pct'] > 0 ? ' (-' . (float)$l['remise_pct'] . '%)' : '' ?></span>
                        <strong style="color:#000"><?= fmt_money((float)$l['total_ligne']) ?></strong>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <div style="border-bottom:2px dashed #000;padding-bottom:12px;margin-bottom:12px">
                <div style="display:flex;justify-content:space-between;font-size:15px;font-weight:bold">
                    <span>TOTAL</span><span><?= fmt_money((float)$ticket_print['montant_total']) ?></span>
                </div>
                <div style="display:flex;justify-content:space-between;font-size:12px;color:#444;margin-top:4px">
                    <span>Mode</span><span><?= h($modeLabels[$ticket_print['mode_paiement']] ?? '') ?></span>
                </div>
                <?php if ($ticket_print['mode_paiement'] === 'especes' && $ticket_print['montant_recu'] > 0): ?>
                <div style="display:flex;justify-content:space-between;font-size:12px;color:#444">
                    <span>Reçu</span><span><?= fmt_money((float)$ticket_print['montant_recu']) ?></span>
                </div>
                <div style="display:flex;justify-content:space-between;font-size:13px;font-weight:bold">
                    <span>Monnaie</span><span><?= fmt_money((float)$ticket_print['monnaie_rendue']) ?></span>
                </div>
                <?php endif; ?>
            </div>

            <?php if ($ticket_print['notes']): ?>
            <div style="font-size:11px;color:#555;margin-bottom:10px">📝 <?= h($ticket_print['notes']) ?></div>
            <?php endif; ?>

            <div style="text-align:center;font-size:11px;color:#666">
                <div>Merci de votre confiance</div>
                <div style="font-family:monospace;font-size:20px;letter-spacing:2px;margin-top:8px">
                    ||||||| <?= h($ticket_print['numero_ticket']) ?> |||||||
                </div>
                <div style="font-size:9px;margin-top:4px">MediCore ERP v<?= APP_VERSION ?></div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
// Données PHP → JS
const MEDS = <?= json_encode(array_map(fn($m) => [
    'id'         => (int)$m['id'],
    'nom'        => $m['nom'] . ($m['dosage'] ? ' ' . $m['dosage'] : ''),
    'categorie'  => $m['categorie'] ?? '',
    'dosage'     => $m['dosage'] ?? '',
    'pu'         => (float)$m['prix_unitaire'],
    'stock'      => (int)$m['stock_actuel'],
    'stock_min'  => (int)($m['stock_minimum'] ?? 5),
], $medicaments)) ?>;

const CATEGORIES = <?= json_encode(array_values($categories)) ?>;

const CURRENCY = {
    symbol:   <?= json_encode($s['currency_symbol'] ?? '') ?>,
    position: <?= json_encode($s['currency_position'] ?? 'after') ?>,
    decSep:   <?= json_encode($s['currency_dec_sep'] ?? ',') ?>,
    thouSep:  <?= json_encode($s['currency_thou_sep'] ?? ' ') ?>,
    decimals:  <?= (int)($s['currency_decimals'] ?? 2) ?>
};

// Patients pour la recherche côté client
const PATIENTS = <?= json_encode(array_map(fn($p) => [
    'id'     => (int)$p['id'],
    'nom'    => $p['nom_complet'],
    'numero' => $p['numero'] ?? ''
], $patients)) ?>;

// Modal helpers (utilisés aussi par pos.js)
function openModal(id) { document.getElementById(id).style.display = 'flex'; }
function closeModal(id) { document.getElementById(id).style.display = 'none'; }

// Préremplissage depuis pharmacie
const PREFILL_DATA = <?php
    if ($prefill_ord_data && !empty($prefill_ord_lignes)):
        echo json_encode([
            'type'       => 'ordonnance',
            'patient_id' => (int)$prefill_ord_data['patient_id_val'],
            'lignes'     => array_map(fn($l) => ['med_id' => (int)$l['medicament_id'], 'qty' => 1], $prefill_ord_lignes)
        ]);
    elseif ($prefill_med_data):
        echo json_encode([
            'type'   => 'medicament',
            'med_id' => (int)$prefill_med_data['id']
        ]);
    else:
        echo 'null';
    endif;
?>;

// Impression ticket
function printTicket() {
    const content = document.getElementById('ticket-content').innerHTML;
    const win = window.open('', '_blank', 'width=420,height=680');
    win.document.write(`<!DOCTYPE html><html><head>
    <title>Ticket <?= h($ticket_print['numero_ticket'] ?? '') ?></title>
    <style>body{font-family:'Courier New',monospace;font-size:12px;margin:0;padding:10px;color:#000}
    @media print{body{margin:0}}</style></head>
    <body>${content}
    <script>window.onload=()=>{window.print();setTimeout(()=>window.close(),500)}<\/script>
    </body></html>`);
    win.document.close();
}

// Auto-ouverture ticket après création
<?php if (get_int('ticket') > 0 && isset($_GET['print'])): ?>
(function() {
    // Une vente vient d'être enregistrée : on vide le panier persisté
    // pour qu'il ne réapparaisse pas au prochain chargement.
    try { localStorage.removeItem('medicore_pos_cart'); } catch (e) {}
    const modalTicket = document.getElementById('modal-ticket');
    if (modalTicket) { modalTicket.style.display = 'flex'; setTimeout(printTicket, 700); }
})();
<?php endif; ?>
</script>

<?php if ($session_active): ?>
<script src="<?= APP_URL ?>/assets/js/pos.js"></script>
<script>
// Préremplissage panier POS (après chargement de pos.js, POSSession disponible)
(function() {
    if (typeof POSSession === 'undefined' || !PREFILL_DATA) return;
    // Arrivée depuis la pharmacie : on repart d'un panier vierge (comportement
    // d'origine) pour ne pas mélanger avec un éventuel panier persisté.
    POSSession.clearCart();
    if (PREFILL_DATA.type === 'ordonnance') {
        if (PREFILL_DATA.patient_id) POSSession.selectPatient(PREFILL_DATA.patient_id);
        PREFILL_DATA.lignes.forEach(l => POSSession.addToCart(l.med_id));
    } else if (PREFILL_DATA.type === 'medicament') {
        POSSession.addToCart(PREFILL_DATA.med_id);
    }
})();
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php';