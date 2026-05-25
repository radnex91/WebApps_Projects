<?php
require_once '../../includes/config.php';
requireLogin(); requirePerm('caisse.manage');

$pageTitle = 'Gestion de Caisse';
$aid = getUserAgenceId();
$uid = $_SESSION['user_id'];
$today = date('Y-m-d');

// Recuperer la caisse du jour
$caisse = null;
$s = $pdo->prepare("SELECT * FROM caisses WHERE guichetier_id=? AND DATE(date_ouverture)=? ORDER BY id DESC LIMIT 1");
$s->execute([$uid, $today]);
$caisse = $s->fetch();

// Transferts en attente
$transfPend = null;
if (!$caisse || $caisse['statut'] === 'ouverte') {
    $tp = $pdo->prepare("SELECT COALESCE(SUM(tc.montant_total),0) as total, COUNT(DISTINCT tc.id) as nb FROM transferts_caisse tc WHERE tc.guichetier_dest_id=? AND tc.caisse_dest_id IS NULL");
    $tp->execute([$uid]);
    $transfPend = $tp->fetch();
    if (!$transfPend['total']) $transfPend = null;
}

// Supervision
$vue_guichetier_id = $uid;
$guichetiers = [];
$date_vue = $_GET['date'] ?? $today;
if (isChefGuichet() || isChefAgence() || isAdmin()) {
    $wg = $aid ? "AND u.agence_id=$aid" : "";
    $guichetiers = $pdo->query("SELECT u.id, CONCAT(u.prenom,' ',u.nom) as nom, u.username FROM utilisateurs u JOIN roles r ON u.role_id=r.id WHERE r.code IN ('guichetier','chef_guichet') $wg ORDER BY u.nom")->fetchAll();
    if (isset($_GET['guichetier_id']) && (int)$_GET['guichetier_id'] > 0) {
        $vue_guichetier_id = (int)$_GET['guichetier_id'];
        $s2 = $pdo->prepare("SELECT * FROM caisses WHERE guichetier_id=? AND DATE(date_ouverture)=? ORDER BY id DESC LIMIT 1");
        $s2->execute([$vue_guichetier_id, $date_vue]);
        $caisse = $s2->fetch();
    }
}
$is_own = ($vue_guichetier_id == $uid);

// Stats tickets (temps reel)
$stats = ['especes'=>0,'om'=>0,'momo'=>0,'carte'=>0,'cheque'=>0,'nb_vendu'=>0,'nb_annule'=>0,'annule_m'=>0,'total'=>0];
if ($caisse) {
    $dateCaisse = date('Y-m-d', strtotime($caisse['date_ouverture']));
    $st = $pdo->prepare("SELECT mode_paiement, COUNT(*) as nb, COALESCE(SUM(montant_total),0) as total FROM tickets WHERE guichetier_id=? AND DATE(date_vente)=? AND statut='vendu' GROUP BY mode_paiement");
    $st->execute([$caisse['guichetier_id'], $dateCaisse]);
    foreach ($st->fetchAll() as $row) {
        $m = $row['mode_paiement'];
        if (isset($stats[$m])) $stats[$m] = (float)$row['total'];
        $stats['nb_vendu'] += (int)$row['nb'];
        $stats['total'] += (float)$row['total'];
    }
    $an = $pdo->prepare("SELECT COUNT(*) as nb, COALESCE(SUM(montant_total),0) as total FROM tickets WHERE annule_par=? AND DATE(date_annulation)=? AND statut='annule'");
    $an->execute([$caisse['guichetier_id'], $dateCaisse]);
    $arow = $an->fetch();
    $stats['nb_annule'] = (int)$arow['nb'];
    $stats['annule_m'] = (float)$arow['total'];
}

// Mouvements manuels
$mvmts = ['depenses'=>0,'depenses_esp'=>0,'recettes'=>0,'recettes_esp'=>0];
if ($caisse) {
    $mm = $pdo->prepare("SELECT type, mode_paiement, COALESCE(SUM(montant),0) as total FROM mouvements_caisse WHERE caisse_id=? GROUP BY type, mode_paiement");
    $mm->execute([$caisse['id']]);
    foreach ($mm->fetchAll() as $row) {
        if ($row['type'] === 'depense') {
            $mvmts['depenses'] += (float)$row['total'];
            if ($row['mode_paiement'] === 'especes') $mvmts['depenses_esp'] += (float)$row['total'];
        } else {
            $mvmts['recettes'] += (float)$row['total'];
            if ($row['mode_paiement'] === 'especes') $mvmts['recettes_esp'] += (float)$row['total'];
        }
    }
}

// Solde theorique especes
$solde_theorique = 0;
if ($caisse) {
    $solde_theorique = (float)$caisse['fond_initial']
        + (float)$caisse['transfert_recu']
        + $stats['especes']
        + $mvmts['recettes_esp']
        - $mvmts['depenses_esp'];
}

// ═══ ACTIONS POST ═══════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();

    // OUVRIR CAISSE
    if (isset($_POST['ouvrir_caisse'])) {
        if (!$is_own) { flash('Action non autorisee.', 'danger'); }
        elseif (caisseOuverte()) {
            flash('Vous avez deja une caisse ouverte aujourd\'hui.', 'warning');
        } else {
            $fond = (float)($_POST['fond_initial'] ?? 0);
            $pdo->beginTransaction();
            try {
                $num = genNumero($pdo, 'caisses', 'numero', 'CAISSE', getUserAgenceCode());
                $transf_recu = $transfPend ? (float)$transfPend['total'] : 0;
                $ins = $pdo->prepare("INSERT INTO caisses (numero, guichetier_id, agence_id, date_ouverture, fond_initial, transfert_recu, statut) VALUES (?,?,?,NOW(),?,?,'ouverte')");
                $ins->execute([$num, $uid, $aid ?: currentAgenceId() ?: 1, $fond, $transf_recu]);
                $nc_id = $pdo->lastInsertId();

                if ($transfPend) {
                    $pdo->prepare("UPDATE transferts_caisse SET caisse_dest_id=? WHERE guichetier_dest_id=? AND caisse_dest_id IS NULL")->execute([$nc_id, $uid]);
                }
                $pdo->commit();
                logAction($pdo, 'ouverture_caisse', 'caisse', "Caisse $num ouverte — Fond: ".money($fond));
                flash('Caisse ouverte avec succes !', 'success');
            } catch(Exception $e) {
                $pdo->rollBack();
                flash('Erreur: '.$e->getMessage(), 'danger');
            }
        }
        redirect(BASE_URL.'modules/caisse/index.php');
    }

    // AJOUTER DEPENSE ou RECETTE
    if (isset($_POST['ajout_mouvement'])) {
        if (!$is_own) { flash('Action non autorisee.', 'danger'); }
        elseif (!$caisse) {
            flash('Aucune caisse ouverte.', 'danger');
        } else {
            $type = $_POST['type_mvt'];
            $libelle = mb_strtoupper(trim($_POST['libelle'] ?? ''));
            $montant = (float)($_POST['montant'] ?? 0);
            $mode = $_POST['mode_paiement'] ?? 'especes';
            if ($libelle && $montant > 0) {
                $pdo->prepare("INSERT INTO mouvements_caisse (caisse_id, type, libelle, montant, mode_paiement) VALUES (?,?,?,?,?)")
                    ->execute([$caisse['id'], $type, $libelle, $montant, $mode]);
                $label = $type === 'depense' ? 'Depense' : 'Recette';
                flash("$label enregistree : $libelle — ".money($montant), 'success');
            } else {
                flash('Libelle et montant obligatoires.', 'danger');
            }
        }
        redirect(BASE_URL.'modules/caisse/index.php');
    }

    // CLOTURER
    if (isset($_POST['cloturer'])) {
        if (!$is_own) { flash('Action non autorisee.', 'danger'); }
        elseif (!$caisse || $caisse['statut'] !== 'ouverte') {
            flash('Aucune caisse ouverte a cloturer.', 'danger');
        } else {
            $solde_physique = (float)($_POST['solde_physique'] ?? 0);
            $obs = trim($_POST['observations'] ?? '');
            $transfert_id = (int)($_POST['transfert_to'] ?? 0);
            $tickets_to_transfer = $_POST['tickets_transfer'] ?? [];

            $t_emis_total = 0;
            $nb_transf = 0;

            $pdo->beginTransaction();
            try {
                if ($transfert_id > 0 && !empty($tickets_to_transfer)) {
                    $ids = array_map('intval', $tickets_to_transfer);
                    $in = implode(',', $ids);
                    $tm = $pdo->query("SELECT COALESCE(SUM(montant_total),0) FROM tickets WHERE id IN ($in) AND statut IN ('vendu','reserve')")->fetchColumn();
                    $t_emis_total = (float)$tm;
                    $nb_transf = count($ids);

                    $pdo->prepare("INSERT INTO transferts_caisse (caisse_source_id, caisse_dest_id, guichetier_source_id, guichetier_dest_id, montant_total, nb_tickets) VALUES (?,NULL,?,?,?,?)")
                        ->execute([$caisse['id'], $uid, $transfert_id, $t_emis_total, $nb_transf]);
                    $transfId = $pdo->lastInsertId();

                    $insT = $pdo->prepare("INSERT INTO transfert_tickets (transfert_id, ticket_id) VALUES ($transfId, ?)");
                    foreach ($ids as $tid) {
                        $insT->execute([$tid]);
                        $pdo->prepare("UPDATE tickets SET guichetier_id=? WHERE id=?")->execute([$transfert_id, $tid]);
                    }
                    $pdo->prepare("UPDATE caisses SET transfert_emis=transfert_emis+? WHERE id=?")->execute([$t_emis_total, $caisse['id']]);
                }

                $dateCaisse = date('Y-m-d', strtotime($caisse['date_ouverture']));
                $tt = $pdo->prepare("SELECT mode_paiement, COALESCE(SUM(montant_total),0) as total FROM tickets WHERE guichetier_id=? AND DATE(date_vente)=? AND statut='vendu' GROUP BY mode_paiement");
                $tt->execute([$caisse['guichetier_id'], $dateCaisse]);
                $totaux = ['especes'=>0,'om'=>0,'momo'=>0,'carte'=>0,'cheque'=>0];
                foreach ($tt->fetchAll() as $r) { if (isset($totaux[$r['mode_paiement']])) $totaux[$r['mode_paiement']] = (float)$r['total']; }

                $nbV = $pdo->prepare("SELECT COUNT(*) FROM tickets WHERE guichetier_id=? AND DATE(date_vente)=? AND statut='vendu'");
                $nbV->execute([$caisse['guichetier_id'], $dateCaisse]);
                $nbVendus = (int)$nbV->fetchColumn();

                $nbA = $pdo->prepare("SELECT COUNT(*), COALESCE(SUM(montant_total),0) FROM tickets WHERE annule_par=? AND DATE(date_annulation)=? AND statut='annule'");
                $nbA->execute([$caisse['guichetier_id'], $dateCaisse]);
                $annData = $nbA->fetch();

                $dep = $pdo->prepare("SELECT COALESCE(SUM(montant),0) FROM mouvements_caisse WHERE caisse_id=? AND type='depense'");
                $dep->execute([$caisse['id']]); $totDep = (float)$dep->fetchColumn();

                $rec = $pdo->prepare("SELECT COALESCE(SUM(montant),0) FROM mouvements_caisse WHERE caisse_id=? AND type='recette'");
                $rec->execute([$caisse['id']]); $totRec = (float)$rec->fetchColumn();

                $rec_esp = (float)($pdo->query("SELECT COALESCE(SUM(montant),0) FROM mouvements_caisse WHERE caisse_id={$caisse['id']} AND type='recette' AND mode_paiement='especes'")->fetchColumn());
                $dep_esp = (float)($pdo->query("SELECT COALESCE(SUM(montant),0) FROM mouvements_caisse WHERE caisse_id={$caisse['id']} AND type='depense' AND mode_paiement='especes'")->fetchColumn());
                $st_final = (float)$caisse['fond_initial'] + (float)$caisse['transfert_recu']
                    + $totaux['especes'] + $rec_esp - $dep_esp;

                $ecart = $solde_physique - $st_final;

                $upd = $pdo->prepare("UPDATE caisses SET statut='fermee', date_fermeture=NOW(), total_tickets_especes=?, total_tickets_om=?, total_tickets_momo=?, total_tickets_carte=?, total_tickets_cheque=?, nb_tickets_vendus=?, nb_tickets_annules=?, montant_annulations=?, total_depenses=?, total_autres_recettes=?, solde_physique=?, ecart=?, observations=? WHERE id=?");
                $upd->execute([$totaux['especes'], $totaux['om'], $totaux['momo'], $totaux['carte'], $totaux['cheque'], $nbVendus, (int)$annData[0], (float)$annData[1], $totDep, $totRec, $solde_physique, $ecart, $obs, $caisse['id']]);

                $pdo->commit();
                logAction($pdo, 'cloture_caisse', 'caisse', "Caisse #{$caisse['id']} cloturee — Ecart: ".money($ecart).($nb_transf>0?" — $nb_transf tickets transferes":""));
                flash('Caisse cloturee avec succes !', 'success');
            } catch(Exception $e) {
                $pdo->rollBack();
                flash('Erreur cloture: '.$e->getMessage(), 'danger');
            }
        }
        redirect(BASE_URL.'modules/caisse/index.php');
    }

    // CLOTURE PAR SUPERVISEUR
    if (isset($_POST['cloturer_superviseur']) && (isChefGuichet() || isChefAgence() || isAdmin())) {
        $caisse_id = (int)($_POST['caisse_id'] ?? 0);
        $cs = $pdo->prepare("SELECT * FROM caisses WHERE id=? AND statut='ouverte'");
        $cs->execute([$caisse_id]);
        $c = $cs->fetch();
        if ($c) {
            $pdo->prepare("UPDATE caisses SET statut='fermee', date_fermeture=NOW(), observations=CONCAT(IFNULL(observations,''),' [Cloture forcee par superviseur le ".date('d/m/Y H:i')."]') WHERE id=?")->execute([$c['id']]);
            logAction($pdo, 'cloture_forcee_caisse', 'caisse', "Caisse #{$c['id']} cloturee par superviseur");
            flash('Caisse cloturee (forcee).', 'warning');
        }
        redirect(BASE_URL.'modules/caisse/index.php');
    }
}

// Recharger l'etat apres POST (caisse fermee affiche recap)
if ($caisse && $caisse['statut'] === 'fermee') {
    $rec_esp = (float)($pdo->query("SELECT COALESCE(SUM(montant),0) FROM mouvements_caisse WHERE caisse_id={$caisse['id']} AND type='recette' AND mode_paiement='especes'")->fetchColumn());
    $dep_esp = (float)($pdo->query("SELECT COALESCE(SUM(montant),0) FROM mouvements_caisse WHERE caisse_id={$caisse['id']} AND type='depense' AND mode_paiement='especes'")->fetchColumn());
    $solde_theorique = (float)$caisse['fond_initial'] + (float)$caisse['transfert_recu']
        + (float)$caisse['total_tickets_especes'] + $rec_esp - $dep_esp;
}

// Autres guichetiers (pour select transfert)
$wAgence = $aid ? "AND u.agence_id=$aid" : "";
$autres_guichetiers = $pdo->query("SELECT u.id, CONCAT(u.prenom,' ',u.nom) as nom, u.username, a.ville FROM utilisateurs u JOIN roles r ON u.role_id=r.id LEFT JOIN agences a ON u.agence_id=a.id WHERE r.code IN ('guichetier','chef_guichet') AND u.id != $uid $wAgence ORDER BY u.nom")->fetchAll();

// Tickets transferables
$tickets_transferables = [];
if ($caisse && $caisse['statut'] === 'ouverte' && $is_own) {
    $ttf = $pdo->prepare("SELECT t.*, v.date_depart, a1.ville as dep, a2.ville as arr FROM tickets t LEFT JOIN voyages v ON t.voyage_id=v.id LEFT JOIN agences a1 ON t.agence_depart_id=a1.id LEFT JOIN agences a2 ON t.agence_arrivee_id=a2.id WHERE t.guichetier_id=? AND t.statut IN ('vendu','reserve') AND t.voyage_id IS NOT NULL AND v.statut IN ('programme','en_cours') ORDER BY t.date_vente");
    $ttf->execute([$uid]);
    $tickets_transferables = $ttf->fetchAll();
}

include '../../includes/header.php';
?>

<div class="breadcrumb no-print"><a href="<?= BASE_URL ?>"><i class="fas fa-home"></i></a><span class="breadcrumb-sep">/</span>Caisse</div>

<?php if (!empty($guichetiers)): ?>
<div class="no-print card" style="margin-bottom:16px;">
  <div class="card-body">
    <form method="GET" class="filter-bar">
      <select name="guichetier_id" class="fc" style="max-width:250px;">
        <option value="">— Ma caisse —</option>
        <?php foreach($guichetiers as $g): ?>
        <option value="<?= $g['id'] ?>" <?= $vue_guichetier_id==$g['id']?'selected':'' ?>><?= sanitize($g['nom']) ?> (@<?= sanitize($g['username']) ?>)</option>
        <?php endforeach; ?>
      </select>
      <input type="date" name="date" class="fc" value="<?= sanitize($date_vue) ?>" style="width:auto;">
      <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-eye"></i> Voir</button>
    </form>
  </div>
</div>
<?php endif; ?>

<?php if (!$caisse): ?>
<!-- ETAT : AUCUNE CAISSE -->
<div class="card">
  <div class="card-body" style="text-align:center;padding:50px;">
    <i class="fas fa-cash-register" style="font-size:60px;color:var(--text3);opacity:.3;"></i>
    <h3 style="color:var(--text2);margin:16px 0 4px;">Aucune caisse ouverte aujourd'hui</h3>
    <p style="color:var(--text3);font-size:13px;margin-bottom:16px;">Vous devez ouvrir votre caisse pour commencer a vendre des tickets.</p>

    <?php if ($transfPend): ?>
    <div style="background:#fef3c7;border:1px solid #fcd34d;border-radius:8px;padding:12px;max-width:400px;margin:0 auto 16px;text-align:left;">
      <strong><i class="fas fa-exchange-alt"></i> Transfert(s) en attente :</strong>
      <span style="font-size:16px;font-weight:700;margin-left:8px;"><?= money((float)$transfPend['total']) ?></span>
      <div style="font-size:11px;color:#92400e;margin-top:4px;">Ce montant sera automatiquement ajoute a votre caisse a l'ouverture.</div>
    </div>
    <?php endif; ?>

    <?php if ($is_own): ?>
    <button onclick="openModal('modal-ouvrir')" class="btn btn-primary btn-lg"><i class="fas fa-door-open"></i> Ouvrir la caisse</button>
    <?php else: ?>
    <p style="color:var(--text3);">Ce guichetier n'a pas encore ouvert sa caisse aujourd'hui.</p>
    <?php endif; ?>
  </div>
</div>

<?php elseif ($caisse['statut'] === 'ouverte'): ?>
<!-- ETAT : CAISSE OUVERTE (DASHBOARD) -->
<div class="card" style="margin-bottom:12px;">
  <div class="card-head" style="background:#1e3a8a;color:#fff;padding:10px 16px;border-radius:8px 8px 0 0;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;">
    <div>
      <strong style="font-size:14px;">Caisse <?= sanitize($caisse['numero']) ?></strong>
      <span style="margin-left:12px;font-size:11px;opacity:.85;">Ouverte <?= fdatetime($caisse['date_ouverture']) ?></span>
    </div>
    <span style="background:#16a34a;color:#fff;padding:4px 12px;border-radius:20px;font-size:12px;font-weight:600;">OUVERTE</span>
  </div>
  <div class="card-body">
    <!-- Cartes resume -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(155px,1fr)); gap:10px;margin-bottom:16px;">
      <div style="background:#dbeafe;padding:10px 12px;border-radius:6px;border-left:3px solid #2563eb;">
        <div style="font-size:10px;color:#1e3a8a;text-transform:uppercase;font-weight:600;">Fond de caisse</div>
        <div style="font-size:17px;font-weight:700;"><?= money((float)$caisse['fond_initial']) ?></div>
      </div>

      <?php if((float)$caisse['transfert_recu'] > 0): ?>
      <div style="background:#fef3c7;padding:10px 12px;border-radius:6px;border-left:3px solid #f59e0b;">
        <div style="font-size:10px;color:#92400e;text-transform:uppercase;font-weight:600;">Transfert recu</div>
        <div style="font-size:17px;font-weight:700;"><?= money((float)$caisse['transfert_recu']) ?></div>
      </div>
      <?php endif; ?>

      <?php if((float)$caisse['transfert_emis'] > 0): ?>
      <div style="background:#fef2f2;padding:10px 12px;border-radius:6px;border-left:3px solid #dc2626;">
        <div style="font-size:10px;color:#991b1b;text-transform:uppercase;font-weight:600;">Transfert emis</div>
        <div style="font-size:17px;font-weight:700;"><?= money((float)$caisse['transfert_emis']) ?></div>
      </div>
      <?php endif; ?>

      <div style="background:#dcfce7;padding:10px 12px;border-radius:6px;border-left:3px solid #16a34a;">
        <div style="font-size:10px;color:#166534;text-transform:uppercase;font-weight:600;">Solde theor. especes</div>
        <div style="font-size:17px;font-weight:700;"><?= money($solde_theorique) ?></div>
      </div>
    </div>

    <!-- Tickets vendus -->
    <div style="margin-bottom:12px;">
      <div style="font-weight:600;font-size:12px;margin-bottom:6px;color:#475569;">TICKETS VENDUS</div>
      <table style="width:100%;font-size:12px;border-collapse:collapse;">
        <tr style="border-bottom:1px solid var(--border);"><td style="padding:5px 0;"><i class="fas fa-money-bill-wave" style="color:#16a34a;"></i> Especes</td><td style="text-align:right;font-weight:600;"><?= money($stats['especes']) ?></td></tr>
        <tr style="border-bottom:1px solid var(--border);"><td style="padding:5px 0;"><i class="fas fa-mobile-alt" style="color:#f97316;"></i> Orange Money</td><td style="text-align:right;"><?= money($stats['om']) ?></td></tr>
        <tr style="border-bottom:1px solid var(--border);"><td style="padding:5px 0;"><i class="fas fa-mobile-alt" style="color:#f59e0b;"></i> MTN MoMo</td><td style="text-align:right;"><?= money($stats['momo']) ?></td></tr>
        <tr style="border-bottom:1px solid var(--border);"><td style="padding:5px 0;"><i class="fas fa-credit-card"></i> Carte</td><td style="text-align:right;"><?= money($stats['carte']) ?></td></tr>
        <tr style="border-bottom:1px solid var(--border);"><td style="padding:5px 0;"><i class="fas fa-file-invoice"></i> Cheque</td><td style="text-align:right;"><?= money($stats['cheque']) ?></td></tr>
        <tr style="font-weight:700;background:var(--bg);"><td style="padding:6px 0;">TOTAL (<?= $stats['nb_vendu'] ?> tickets)</td><td style="text-align:right;"><?= money($stats['total']) ?></td></tr>
      </table>
    </div>

    <!-- Mouvements -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
      <div style="background:#fef2f2;border:1px solid #fecaca;border-radius:6px;padding:10px;">
        <div style="display:flex;justify-content:space-between;align-items:center;">
          <span style="font-size:10px;color:#991b1b;text-transform:uppercase;font-weight:600;">DEPENSES</span>
          <span style="font-size:16px;font-weight:700;color:#dc2626;"><?= money($mvmts['depenses']) ?></span>
        </div>
      </div>
      <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:6px;padding:10px;">
        <div style="display:flex;justify-content:space-between;align-items:center;">
          <span style="font-size:10px;color:#166534;text-transform:uppercase;font-weight:600;">AUTRES RECETTES</span>
          <span style="font-size:16px;font-weight:700;color:#16a34a;"><?= money($mvmts['recettes']) ?></span>
        </div>
      </div>
    </div>

    <?php if ($stats['nb_annule'] > 0): ?>
    <div style="margin-top:10px;font-size:11px;color:#dc2626;"><i class="fas fa-ban"></i> <?= $stats['nb_annule'] ?> ticket(s) annule(s) : <?= money($stats['annule_m']) ?></div>
    <?php endif; ?>

    <!-- Boutons d'action -->
    <?php if ($is_own): ?>
    <div style="display:flex;gap:8px;margin-top:16px;flex-wrap:wrap;">
      <button onclick="openModal('modal-depense')" class="btn btn-danger btn-sm"><i class="fas fa-minus-circle"></i> + Depense</button>
      <button onclick="openModal('modal-recette')" class="btn btn-success btn-sm"><i class="fas fa-plus-circle"></i> + Autre recette</button>
      <button onclick="openModal('modal-cloturer')" class="btn btn-warning btn-sm" style="margin-left:auto;"><i class="fas fa-lock"></i> Cloturer la caisse</button>
    </div>
    <?php elseif (isChefGuichet() || isChefAgence() || isAdmin()): ?>
    <div style="margin-top:16px;">
      <form method="POST">
        <?= csrfField() ?>
        <input type="hidden" name="caisse_id" value="<?= $caisse['id'] ?>">
        <button type="submit" name="cloturer_superviseur" value="1" class="btn btn-warning btn-sm" onclick="return confirm('Cloturer cette caisse (forcee) ?')"><i class="fas fa-lock"></i> Cloturer (superviseur)</button>
      </form>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php else: ?>
<!-- ETAT : CAISSE FERMEE (RECAPITULATIF) -->
<div id="rpt-zone">
<div class="card">
  <div class="card-head" style="background:#16a34a;color:#fff;padding:10px 16px;border-radius:8px 8px 0 0;display:flex;justify-content:space-between;align-items:center;">
    <strong>Caisse <?= sanitize($caisse['numero']) ?> — CLOTUREE</strong>
    <span style="font-size:11px;opacity:.9;">Fermee le <?= fdatetime($caisse['date_fermeture'] ?? $caisse['updated_at']) ?></span>
  </div>
  <div class="card-body">
    <table style="width:100%;border-collapse:collapse;font-size:12px;">
      <tr style="background:var(--bg);"><td style="padding:6px 10px;font-weight:600;">Fond de caisse</td><td style="text-align:right;"><?= money((float)$caisse['fond_initial']) ?></td></tr>
      <?php if((float)$caisse['transfert_recu'] > 0): ?><tr><td style="padding:6px 10px;font-weight:600;">Transfert recu</td><td style="text-align:right;"><?= money((float)$caisse['transfert_recu']) ?></td></tr><?php endif; ?>
      <?php if((float)$caisse['transfert_emis'] > 0): ?><tr><td style="padding:6px 10px;font-weight:600;color:#dc2626;">Transfert emis</td><td style="text-align:right;color:#dc2626;"><?= money((float)$caisse['transfert_emis']) ?></td></tr><?php endif; ?>
      <tr><td style="padding:6px 10px;font-weight:600;">Tickets (<?= $caisse['nb_tickets_vendus'] ?> vendus)</td><td style="text-align:right;"><?= money((float)$caisse['total_tickets_especes'] + (float)$caisse['total_tickets_om'] + (float)$caisse['total_tickets_momo'] + (float)$caisse['total_tickets_carte'] + (float)$caisse['total_tickets_cheque']) ?></td></tr>
      <?php if((int)$caisse['nb_tickets_annules'] > 0): ?><tr><td style="padding:6px 10px;font-weight:600;color:#dc2626;">Tickets annules (<?= $caisse['nb_tickets_annules'] ?>)</td><td style="text-align:right;color:#dc2626;"><?= money((float)$caisse['montant_annulations']) ?></td></tr><?php endif; ?>
      <tr><td style="padding:6px 10px;font-weight:600;">Depenses</td><td style="text-align:right;color:#dc2626;"><?= money((float)$caisse['total_depenses']) ?></td></tr>
      <tr><td style="padding:6px 10px;font-weight:600;">Autres recettes</td><td style="text-align:right;color:#16a34a;"><?= money((float)$caisse['total_autres_recettes']) ?></td></tr>
      <tr><td colspan="2"><hr></td></tr>
      <tr style="background:#eff6ff;font-weight:700;"><td style="padding:6px 10px;">Solde especes theorique</td><td style="text-align:right;"><?= money($solde_theorique) ?></td></tr>
      <tr style="background:#fff;font-weight:700;"><td style="padding:6px 10px;">Especes comptees</td><td style="text-align:right;"><?= money((float)$caisse['solde_physique']) ?></td></tr>
      <tr style="background:<?= $caisse['ecart']==0?'#f0fdf4':($caisse['ecart']>0?'#fffbeb':'#fef2f2') ?>;font-weight:700;font-size:14px;">
        <td style="padding:8px 10px;">Ecart <?= $caisse['ecart']==0?'':($caisse['ecart']>0?'(Excedent)':'(Manquant)') ?></td>
        <td style="text-align:right;color:<?= $caisse['ecart']==0?'#16a34a':($caisse['ecart']>0?'#d97706':'#dc2626') ?>;"><?= money((float)$caisse['ecart']) ?></td>
      </tr>
    </table>
    <?php if($caisse['observations']): ?><div style="margin-top:10px;font-size:11px;color:var(--text2);font-style:italic;">Obs: <?= sanitize($caisse['observations']) ?></div><?php endif; ?>

    <div class="no-print" style="margin-top:16px;">
      <button onclick="window.print()" class="btn btn-info btn-sm"><i class="fas fa-print"></i> Imprimer</button>
    </div>
  </div>
</div>
</div>
<?php endif; ?>

<!-- ════ MODALS ════════════════════════════════════════════════ -->

<!-- Modal OUVRIR -->
<div class="modal-over" id="modal-ouvrir">
  <div class="modal modal-sm">
    <div class="modal-head"><h3><i class="fas fa-door-open"></i> Ouvrir la caisse</h3><button class="modal-x" onclick="closeModal('modal-ouvrir')">&times;</button></div>
    <form method="POST">
      <?= csrfField() ?>
      <div class="modal-body">
        <?php if ($transfPend): ?>
        <div style="background:#fef3c7;border:1px solid #fcd34d;border-radius:6px;padding:8px;margin-bottom:12px;font-size:12px;">
          <i class="fas fa-exchange-alt"></i> Transfert recu en attente : <strong><?= money((float)$transfPend['total']) ?></strong> (ajoute automatiquement)
        </div>
        <?php endif; ?>
        <div class="fg">
          <label class="fl">Fond de caisse (FCFA)</label>
          <input type="number" name="fond_initial" class="fc" value="0" required min="0" style="font-size:20px;font-weight:700;text-align:center;" placeholder="0">
          <small style="color:var(--text3);">Montant en especes que vous avez en caisse au demarrage.</small>
        </div>
      </div>
      <div class="modal-foot">
        <button type="button" class="btn btn-ghost btn-sm" onclick="closeModal('modal-ouvrir')">Annuler</button>
        <button type="submit" name="ouvrir_caisse" value="1" class="btn btn-primary btn-sm"><i class="fas fa-check"></i> Ouvrir</button>
      </div>
    </form>
  </div>
</div>

<?php if ($caisse && $caisse['statut']==='ouverte' && $is_own): ?>

<!-- Modal DEPENSE -->
<div class="modal-over" id="modal-depense">
  <div class="modal modal-sm">
    <div class="modal-head"><h3><i class="fas fa-minus-circle" style="color:#dc2626;"></i> Nouvelle depense</h3><button class="modal-x" onclick="closeModal('modal-depense')">&times;</button></div>
    <form method="POST">
      <?= csrfField() ?>
      <input type="hidden" name="type_mvt" value="depense">
      <div class="modal-body">
        <div class="fg"><label class="fl">Libelle</label><input type="text" name="libelle" class="fc" required placeholder="Ex: Peage, Carburant, Remboursement..."></div>
        <div class="fg"><label class="fl">Montant (FCFA)</label><input type="number" name="montant" class="fc" required min="1" value="0"></div>
        <div class="fg"><label class="fl">Mode</label><select name="mode_paiement" class="fc"><option value="especes">Especes</option><option value="om">Orange Money</option><option value="momo">MTN MoMo</option><option value="carte">Carte</option><option value="cheque">Cheque</option></select></div>
      </div>
      <div class="modal-foot">
        <button type="button" class="btn btn-ghost btn-sm" onclick="closeModal('modal-depense')">Annuler</button>
        <button type="submit" name="ajout_mouvement" value="1" class="btn btn-danger btn-sm">Enregistrer</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal AUTRE RECETTE -->
<div class="modal-over" id="modal-recette">
  <div class="modal modal-sm">
    <div class="modal-head"><h3><i class="fas fa-plus-circle" style="color:#16a34a;"></i> Autre recette</h3><button class="modal-x" onclick="closeModal('modal-recette')">&times;</button></div>
    <form method="POST">
      <?= csrfField() ?>
      <input type="hidden" name="type_mvt" value="recette">
      <div class="modal-body">
        <div class="fg"><label class="fl">Libelle</label><input type="text" name="libelle" class="fc" required placeholder="Ex: Vente colis, Frais reservation..."></div>
        <div class="fg"><label class="fl">Montant (FCFA)</label><input type="number" name="montant" class="fc" required min="1" value="0"></div>
        <div class="fg"><label class="fl">Mode</label><select name="mode_paiement" class="fc"><option value="especes">Especes</option><option value="om">Orange Money</option><option value="momo">MTN MoMo</option><option value="carte">Carte</option><option value="cheque">Cheque</option></select></div>
      </div>
      <div class="modal-foot">
        <button type="button" class="btn btn-ghost btn-sm" onclick="closeModal('modal-recette')">Annuler</button>
        <button type="submit" name="ajout_mouvement" value="1" class="btn btn-success btn-sm">Enregistrer</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal CLOTURER -->
<div class="modal-over" id="modal-cloturer">
  <div class="modal modal-lg">
    <div class="modal-head"><h3><i class="fas fa-lock"></i> Cloturer la caisse</h3><button class="modal-x" onclick="closeModal('modal-cloturer')">&times;</button></div>
    <form method="POST">
      <?= csrfField() ?>
      <div class="modal-body">
        <div style="background:#f0f7ff;border:1px solid #bfdbfe;border-radius:8px;padding:12px;margin-bottom:12px;">
          <strong>Solde especes theorique :</strong>
          <span style="font-size:20px;font-weight:700;margin-left:8px;"><?= money($solde_theorique) ?></span>
          <div style="font-size:10px;color:var(--text3);margin-top:4px;">
            = <?= money((float)$caisse['fond_initial']) ?> (fond) + <?= money((float)$caisse['transfert_recu']) ?> (transf) + <?= money($stats['especes']) ?> (ventes) + <?= money($mvmts['recettes_esp']) ?> (recettes) - <?= money($mvmts['depenses_esp']) ?> (depenses)
          </div>
        </div>

        <?php if (!empty($tickets_transferables)): ?>
        <div style="margin-bottom:14px;">
          <div style="font-weight:600;font-size:12px;color:#7c3aed;margin-bottom:6px;"><i class="fas fa-exchange-alt"></i> TRANSFERT DE TICKETS (optionnel)</div>
          <table style="width:100%;font-size:11px;border-collapse:collapse;">
            <thead><tr style="background:var(--bg);"><th style="padding:4px 6px;text-align:left;">Sel.</th><th>No Ticket</th><th>Passager</th><th>Trajet</th><th style="text-align:right;">Montant</th></tr></thead>
            <tbody>
            <?php foreach($tickets_transferables as $tk): ?>
            <tr style="border-bottom:1px solid var(--border);">
              <td><input type="checkbox" name="tickets_transfer[]" value="<?= $tk['id'] ?>" style="width:16px;height:16px;"></td>
              <td style="font-family:monospace;font-size:10px;"><?= sanitize($tk['numero']) ?></td>
              <td><?= sanitize($tk['passager_nom']) ?></td>
              <td><?= sanitize(($tk['dep']??'—').'→'.($tk['arr']??'—')) ?></td>
              <td style="text-align:right;"><?= money((float)$tk['montant_total']) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
          <select name="transfert_to" class="fc" style="max-width:280px;margin-top:8px;">
            <option value="0">— Choisir guichetier destinataire —</option>
            <?php foreach($autres_guichetiers as $g): ?><option value="<?= $g['id'] ?>"><?= sanitize($g['nom']) ?> (<?= sanitize($g['ville']??'—') ?>)</option><?php endforeach; ?>
          </select>
        </div>
        <?php endif; ?>

        <div class="fg">
          <label class="fl" style="font-weight:600;">Especes comptees physiquement (FCFA)</label>
          <input type="number" name="solde_physique" class="fc" id="solde-physique" required min="0" value="<?= $solde_theorique ?>" style="font-size:22px;font-weight:700;text-align:center;border:2px solid #2563eb;" oninput="calcEcart()">
        </div>

        <div id="ecart-display" style="text-align:center;padding:10px;border-radius:8px;font-size:14px;font-weight:700;margin-top:8px;"></div>

        <div class="fg"><label class="fl">Observations</label><textarea name="observations" class="fc" rows="2" placeholder="Note eventuelle..."></textarea></div>
      </div>
      <div class="modal-foot">
        <button type="button" class="btn btn-ghost btn-sm" onclick="closeModal('modal-cloturer')">Annuler</button>
        <button type="submit" name="cloturer" value="1" class="btn btn-warning btn-sm"><i class="fas fa-check-circle"></i> Confirmer la cloture</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<script>
function calcEcart() {
  var physique = parseFloat(document.getElementById('solde-physique').value) || 0;
  var theorique = <?= $solde_theorique ?>;
  var ecart = physique - theorique;
  var el = document.getElementById('ecart-display');
  if (ecart === 0) {
    el.textContent = 'Ecart : 0 FCFA — Caisse juste';
    el.style.background = '#f0fdf4'; el.style.color = '#16a34a';
  } else if (ecart > 0) {
    el.textContent = 'Ecart : +' + format(ecart) + ' FCFA (Excedent)';
    el.style.background = '#fffbeb'; el.style.color = '#d97706';
  } else {
    el.textContent = 'Ecart : ' + format(ecart) + ' FCFA (Manquant)';
    el.style.background = '#fef2f2'; el.style.color = '#dc2626';
  }
}
function format(n) { return n.toFixed(0).replace(/\B(?=(\d{3})+(?!\d))/g, ' '); }
document.addEventListener('DOMContentLoaded', calcEcart);
</script>

<?php include '../../includes/footer.php'; ?>
