<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../config/settings.php';
require_once __DIR__ . '/../config/comptabilite.php';

// Accès : caisse.voir OU caisse.ouvrir
if (!hasPermission('caisse.voir') && !hasPermission('caisse.ouvrir')) {
    header('Location: ' . APP_URL . '/dashboard.php?err=access');
    exit;
}

$db = getDB();
$action = $_GET['action'] ?? '';

// ── Paramètres de fermeture auto ──────────────────────────
$fermetureMode     = getParam('caisse_fermeture_mode', 'manuel');
$fermetureHeure    = getParam('caisse_heure_fermeture', '22:00');
$fermetureAuto     = $fermetureMode === 'auto';
$heureDepassee     = false;
if ($fermetureAuto) {
    $now  = new DateTime('now', new DateTimeZone('Africa/Douala'));
    $limite = DateTime::createFromFormat('H:i', $fermetureHeure, new DateTimeZone('Africa/Douala'));
    if ($limite && $now >= $limite) {
        $heureDepassee = true;
    }
}

// ── Helper : solde théorique d'une session ─────────────────
function soldeSession(PDO $db, int $sessionId): float {
    $stmt = $db->prepare("
        SELECT s.fond_initial,
               COALESCE((SELECT SUM(montant) FROM mouvements_caisse WHERE session_id = s.id AND type = 'entrée'), 0) AS entrees,
               COALESCE((SELECT SUM(montant) FROM mouvements_caisse WHERE session_id = s.id AND type = 'sortie'), 0) AS sorties
        FROM sessions_caisse s WHERE s.id = ?
    ");
    $stmt->execute([$sessionId]);
    $r = $stmt->fetch();
    return (float)$r['fond_initial'] + (float)$r['entrees'] - (float)$r['sorties'];
}

// ── Helper : durée lisible depuis ouverture ─────────────────
function dureeDepuis(string $dateOuverture): string {
    $now = new DateTime();
    $ouv = new DateTime($dateOuverture);
    $diff = $now->diff($ouv);
    $heuresTotales = ($diff->d * 24) + $diff->h;
    $minutes = $diff->i;
    if ($heuresTotales > 0) return $heuresTotales . 'h' . str_pad($minutes, 2, '0', STR_PAD_LEFT);
    return $minutes . 'min';
}

// ── POST : création de poste (admin) ────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_type']) && $_POST['action_type'] === 'creer_poste') {
    verifyCsrf();
    requirePermission('caisse.gerer');
    $nom = trim($_POST['nom'] ?? '');
    if ($nom !== '') {
        $db->prepare("INSERT INTO caisses (nom) VALUES (?)")->execute([$nom]);
        flash('Poste créé avec succès.', 'success');
    }
    header('Location: ' . url('caisse')); exit;
}

// ── POST : clôture de caisse (Z) ────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_type']) && $_POST['action_type'] === 'cloturer') {
    verifyCsrf();
    $sessionId = (int)($_POST['session_id'] ?? 0);
    $soldeReel = (float)($_POST['solde_reel'] ?? 0);

    $stmt = $db->prepare("
        SELECT s.*, c.nom AS caisse_nom
        FROM sessions_caisse s JOIN caisses c ON s.caisse_id = c.id
        WHERE s.id = ?
    ");
    $stmt->execute([$sessionId]);
    $session = $stmt->fetch();

    if (!$session || $session['statut'] !== 'ouverte') {
        flash('Session invalide ou déjà fermée.', 'error');
        header('Location: ' . url('caisse')); exit;
    }

    if ((int)$session['caissier_id'] !== currentUser()['id'] && !hasPermission('caisse.gerer')) {
        flash('Vous ne pouvez pas clôturer cette caisse.', 'error');
        header('Location: ' . url('caisse')); exit;
    }

    // Admin forcé → motif obligatoire
    if ((int)$session['caissier_id'] !== currentUser()['id'] && hasPermission('caisse.gerer')) {
        $motifForce = trim($_POST['motif_force'] ?? '');
        if ($motifForce === '') {
            flash('Motif obligatoire pour une fermeture forcée.', 'error');
            header('Location: ' . url('caisse', ['action'=>'z','id'=>$sessionId])); exit;
        }
        $db->prepare("INSERT INTO mouvements_caisse (session_id, type, montant, motif, moyen) VALUES (?, 'sortie', 0, ?, 'espèces')")
           ->execute([$sessionId, 'Fermeture forcée admin : ' . $motifForce]);
    }

    $soldeAttendu = soldeSession($db, $sessionId);
    $ecart = round($soldeReel - $soldeAttendu, 2);

    $db->prepare("
        UPDATE sessions_caisse
        SET date_fermeture = NOW(), solde_attendu = ?, solde_reel = ?, ecart = ?, statut = 'fermée'
        WHERE id = ?
    ")->execute([$soldeAttendu, $soldeReel, $ecart, $sessionId]);

    $auditDetail = sprintf('Clôture caisse #%d : attendu=%s, réel=%s, écart=%s%s', $sessionId, fmtMoney($soldeAttendu), fmtMoney($soldeReel), fmtMoney($ecart), !empty($estForce) ? ' [FORCÉE]' : '');
    auditLog('caisse.close', $auditDetail, $sessionId);
    flash('Caisse clôturée. Écart : ' . fmtMoney($ecart) . '.', $ecart === 0.0 ? 'success' : 'info');
    header('Location: ' . url('caisse')); exit;
}

// ── POST : ouverture de caisse ──────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_type']) && $_POST['action_type'] === 'ouvrir') {
    verifyCsrf();
    requirePermission('caisse.ouvrir');
    $caisseId    = (int)($_POST['caisse_id'] ?? 0);
    $pharmacieId = (int)($_POST['pharmacie_id'] ?? 0);
    $fond        = max(0, (float)($_POST['fond_initial'] ?? 0));

    $stmt = $db->prepare("SELECT id FROM caisses WHERE id = ? AND actif = 1");
    $stmt->execute([$caisseId]);
    if (!$stmt->fetch()) {
        flash('Poste invalide.', 'error');
        header('Location: ' . url('caisse')); exit;
    }

    // Validation de la pharmacie choisie (active)
    $stmt = $db->prepare("SELECT id, nom FROM pharmacies WHERE id = ? AND actif = 1");
    $stmt->execute([$pharmacieId]);
    $pharmacieChoisie = $stmt->fetch();
    if (!$pharmacieChoisie) {
        flash('Veuillez choisir une pharmacie valide.', 'error');
        header('Location: ' . url('caisse', ['action' => 'ouvrir'])); exit;
    }

    $stmt = $db->prepare("SELECT id FROM sessions_caisse WHERE caisse_id = ? AND statut = 'ouverte'");
    $stmt->execute([$caisseId]);
    if ($stmt->fetch()) {
        flash('Ce poste a déjà une session ouverte.', 'error');
        header('Location: ' . url('caisse')); exit;
    }

    $stmt = $db->prepare("SELECT id FROM sessions_caisse WHERE caissier_id = ? AND statut = 'ouverte'");
    $stmt->execute([currentUser()['id']]);
    if ($stmt->fetch()) {
        flash('Vous avez déjà une session de caisse ouverte.', 'error');
        header('Location: ' . url('caisse')); exit;
    }

    $db->prepare("
        INSERT INTO sessions_caisse (caisse_id, caissier_id, pharmacie_id, fond_initial, date_ouverture, statut)
        VALUES (?, ?, ?, ?, NOW(), 'ouverte')
    ")->execute([$caisseId, currentUser()['id'], $pharmacieId, $fond]);

    $newSessionId = $db->lastInsertId();

    // Écriture comptable du fond de caisse initial (OHADA) :
    // Débit 5711 (caisse) / Crédit 471 (compte d'attente — à régulariser selon origine).
    if ($fond > 0) {
        $db->beginTransaction();
        try {
            $compteCaisse = compteFindOrCreate($db, '5711', 'Caisse principale', 5, 'debit');
            $compteAttente = compteFindOrCreate($db, '471', 'Compte d\'attente', 4, 'credit');
            ecritureCreate($db,
                'Fond de caisse initial — session #' . $newSessionId,
                date('Y-m-d'),
                [
                    [$compteCaisse, round($fond, 2), 0, 'Fond initial session ' . $newSessionId],
                    [$compteAttente, 0, round($fond, 2), 'Fond initial à régulariser'],
                ],
                'caisse', 'FO-' . $newSessionId, currentUser()['id']
            );
            $db->commit();
        } catch (Exception $ex) {
            $db->rollBack();
            flash('Caisse ouverte mais écriture comptable du fond échouée : ' . $ex->getMessage(), 'error');
            header('Location: ' . url('caisse')); exit;
        }
    }

    auditLog('caisse.open', sprintf('Ouverture caisse #%d (%s) : fond %s', (int)$newSessionId, $pharmacieChoisie['nom'], fmtMoney($fond)), (int)$newSessionId);
    flash('Caisse ouverte sur « ' . e($pharmacieChoisie['nom']) . ' » avec un fond initial de ' . fmtMoney($fond) . '.', 'success');
    header('Location: ' . url('caisse')); exit;
}

// ── POST : mouvement manuel ─────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_type']) && $_POST['action_type'] === 'mouvement') {
    verifyCsrf();
    $sessionId = (int)($_POST['session_id'] ?? 0);
    $typeMvt   = $_POST['type_mvt'] ?? 'sortie';
    if (!in_array($typeMvt, ['entrée', 'sortie'])) $typeMvt = 'sortie';
    $montant   = max(0, (float)($_POST['montant'] ?? 0));
    $motif     = trim($_POST['motif'] ?? '');

    if ($montant <= 0 || $motif === '') {
        flash('Montant et motif obligatoires.', 'error');
        header('Location: ' . url('caisse')); exit;
    }

    $stmt = $db->prepare("SELECT id FROM sessions_caisse WHERE id = ? AND statut = 'ouverte' AND caissier_id = ?");
    $stmt->execute([$sessionId, currentUser()['id']]);
    if (!$stmt->fetch()) {
        flash('Session invalide.', 'error');
        header('Location: ' . url('caisse')); exit;
    }

    $labelType = $typeMvt === 'entrée' ? 'Dépôt' : 'Retrait';

    try {
        $db->beginTransaction();
        $db->prepare("INSERT INTO mouvements_caisse (session_id, type, montant, motif, moyen) VALUES (?, ?, ?, ?, 'espèces')")
           ->execute([$sessionId, $typeMvt, $montant, $labelType . ' : ' . $motif]);
        $mvtId = (int)$db->lastInsertId();

        // Écriture comptable du mouvement manuel (OHADA) :
        // Le motif étant libre, la contrepartie transite par 471 (compte d'attente)
        // en attendant reclassification par le comptable (charge, banque, etc.).
        //   entrée (dépôt)   : D 5711 (caisse) / C 471
        //   sortie (retrait) : D 471            / C 5711 (caisse)
        $compteCaisse  = compteFindOrCreate($db, '5711', 'Caisse principale', 5, 'debit');
        $compteAttente = compteFindOrCreate($db, '471', 'Compte d\'attente', 4, 'credit');
        if ($typeMvt === 'entrée') {
            $lignes = [
                [$compteCaisse, round($montant, 2), 0, $labelType . ' : ' . $motif],
                [$compteAttente, 0, round($montant, 2), 'À régulariser : ' . $motif],
            ];
        } else {
            $lignes = [
                [$compteAttente, round($montant, 2), 0, 'À régulariser : ' . $motif],
                [$compteCaisse, 0, round($montant, 2), $labelType . ' : ' . $motif],
            ];
        }
        ecritureCreate($db,
            $labelType . ' caisse — session #' . $sessionId,
            date('Y-m-d'),
            $lignes,
            'caisse', 'MC-' . $mvtId, currentUser()['id']
        );

        $db->commit();
    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        flash('Erreur mouvement caisse : ' . $e->getMessage(), 'error');
        header('Location: ' . url('caisse')); exit;
    }

    auditLog('caisse.mouvement', sprintf('%s : %s (%s)', $labelType, fmtMoney($montant), $motif), $sessionId);
    flash($labelType . ' de ' . fmtMoney($montant) . ' enregistré.', 'success');
    header('Location: ' . url('caisse')); exit;
}

// ═══════════════════════════════════════════════════════════
// VUE : Ouverture de caisse
// ═══════════════════════════════════════════════════════════
if ($action === 'ouvrir'):
    requirePermission('caisse.ouvrir');

    $dejaOuverte = $db->prepare("SELECT s.id, c.nom FROM sessions_caisse s JOIN caisses c ON s.caisse_id = c.id WHERE s.caissier_id = ? AND s.statut = 'ouverte'");
    $dejaOuverte->execute([currentUser()['id']]);
    if ($maSession = $dejaOuverte->fetch()) {
        flash('Vous avez déjà une session ouverte sur « ' . e($maSession['nom']) . ' ».' , 'info');
        header('Location: ' . url('caisse')); exit;
    }

    $toutesCaisses = $db->query("
        SELECT c.id, c.nom, s.id AS session_id, u.prenom, u.nom AS u_nom
        FROM caisses c
        LEFT JOIN sessions_caisse s ON c.id = s.caisse_id AND s.statut = 'ouverte'
        LEFT JOIN utilisateurs u ON s.caissier_id = u.id
        WHERE c.actif = 1
        ORDER BY c.id
    ")->fetchAll();

    $aucuneDispo = true;
    foreach ($toutesCaisses as $c) {
        if (!$c['session_id']) { $aucuneDispo = false; break; }
    }
    if ($aucuneDispo) {
        flash('Tous les postes de caisse sont actuellement occupés.', 'info');
        header('Location: ' . url('caisse')); exit;
    }

    $pharmacies = $db->query("SELECT id, nom FROM pharmacies WHERE actif = 1 ORDER BY nom")->fetchAll();

    layout_head('Ouverture de Caisse', 'caisse');
    showFlash();
?>
<div class="card" style="max-width:580px;margin:0 auto;">
  <div class="card-header">
    <div class="card-title">Ouvrir une caisse</div>
  </div>
  <div class="card-pad">
    <form method="POST" id="form-ouvrir">
      <input type="hidden" name="csrf" value="<?= csrf() ?>">
      <input type="hidden" name="action_type" value="ouvrir">
      <input type="hidden" name="caisse_id" id="caisse-id" value="" required>
      <div class="form-group" style="margin-bottom:16px;">
        <label style="font-size:13px;font-weight:500;color:var(--text2);">Pharmacie (stock à débiter) *</label>
        <select name="pharmacie_id" id="pharmacie-id" required onchange="majBoutonOuvrir()">
          <option value="">— Sélectionner —</option>
          <?php foreach ($pharmacies as $ph): ?>
          <option value="<?= (int)$ph['id'] ?>"><?= e($ph['nom']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <label style="font-size:13px;font-weight:500;color:var(--text2);margin-bottom:12px;display:block;">Choisissez un poste de caisse</label>
      <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(155px,1fr));gap:10px;margin-bottom:20px;">
        <?php foreach ($toutesCaisses as $c):
          $estOccupee = $c['session_id'] !== null;
        ?>
        <div class="caisse-card <?= $estOccupee ? 'caisse-occupee' : '' ?>"
             <?= !$estOccupee ? 'data-id="' . (int)$c['id'] . '" onclick="selectCaisse(this)" tabindex="0"' : '' ?>
             style="
               border:2px solid var(--border2);
               border-radius:12px;
               padding:14px;
               cursor:<?= $estOccupee ? 'default' : 'pointer' ?>;
               transition:all 0.15s;
               background:<?= $estOccupee ? 'var(--bg2)' : 'var(--bg)' ?>;
               opacity:<?= $estOccupee ? '0.55' : '1' ?>;
               text-align:center;
               outline:none;
             ">
          <div style="font-size:28px;margin-bottom:6px;"><?= $estOccupee ? icon('lock', 24) : icon('building', 24) ?></div>
          <div style="font-weight:600;font-size:14px;"><?= e($c['nom']) ?></div>
          <?php if ($estOccupee): ?>
          <div style="font-size:11px;color:var(--text3);margin-top:4px;">
            Occupée par <?= e($c['prenom'] . ' ' . substr($c['u_nom'], 0, 1) . '.') ?>
          </div>
          <?php else: ?>
          <div style="font-size:11px;color:var(--teal2);margin-top:4px;">Disponible</div>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
      <div class="form-group">
        <label>Fond de caisse initial (FCFA)</label>
        <input type="number" name="fond_initial" value="0" min="0" step="1" required style="font-size:18px;font-weight:600;">
      </div>
      <div style="display:flex;gap:10px;margin-top:20px;">
        <a href="<?= url('caisse') ?>" class="btn btn-ghost" style="flex:1;justify-content:center;">Annuler</a>
        <button type="submit" class="btn btn-primary" style="flex:1;justify-content:center;gap:6px;" id="btn-ouvrir" disabled>
          <?= icon('unlock',14) ?> Ouvrir la caisse
        </button>
      </div>
    </form>
  </div>
</div>
<script>
function majBoutonOuvrir() {
  var caisseOk = document.getElementById('caisse-id').value !== '';
  var phOk     = document.getElementById('pharmacie-id').value !== '';
  document.getElementById('btn-ouvrir').disabled = !(caisseOk && phOk);
}
function selectCaisse(el) {
  document.querySelectorAll('.caisse-card:not(.caisse-occupee)').forEach(function(c){
    c.style.borderColor = 'var(--border2)';
    c.style.background = 'var(--bg)';
    c.classList.remove('selected');
  });
  el.style.borderColor = 'var(--teal2)';
  el.style.background = 'var(--teal-dim)';
  el.classList.add('selected');
  document.getElementById('caisse-id').value = el.dataset.id;
  majBoutonOuvrir();
}
</script>
<?php layout_foot(); return; endif;

// ═══════════════════════════════════════════════════════════
// VUE : X de caisse (interrogation sans clôture)
// ═══════════════════════════════════════════════════════════
if ($action === 'x'):
    $sessionId = (int)($_GET['id'] ?? 0);
    $stmt = $db->prepare("
        SELECT s.*, u.prenom, u.nom AS u_nom, c.nom AS caisse_nom
        FROM sessions_caisse s
        JOIN utilisateurs u ON s.caissier_id = u.id
        JOIN caisses c ON s.caisse_id = c.id
        WHERE s.id = ?
    ");
    $stmt->execute([$sessionId]);
    $session = $stmt->fetch();
    if (!$session) { flash('Session introuvable.', 'error'); header('Location: ' . url('caisse')); exit; }

    $solde = soldeSession($db, $sessionId);

    $ventesEspeces = $db->prepare("SELECT COALESCE(SUM(montant), 0) FROM mouvements_caisse WHERE session_id = ? AND type = 'entrée' AND reference_vente IS NOT NULL");
    $ventesEspeces->execute([$sessionId]);
    $totalVentes = (float)$ventesEspeces->fetchColumn();

    $depots = $db->prepare("SELECT COALESCE(SUM(montant), 0) FROM mouvements_caisse WHERE session_id = ? AND type = 'entrée' AND reference_vente IS NULL");
    $depots->execute([$sessionId]);
    $totalDepots = (float)$depots->fetchColumn();

    $retraits = $db->prepare("SELECT COALESCE(SUM(montant), 0) FROM mouvements_caisse WHERE session_id = ? AND type = 'sortie'");
    $retraits->execute([$sessionId]);
    $totalRetraits = (float)$retraits->fetchColumn();

    $nbTickets = $db->prepare("SELECT COUNT(DISTINCT reference_vente) FROM mouvements_caisse WHERE session_id = ? AND reference_vente IS NOT NULL");
    $nbTickets->execute([$sessionId]);
    $nb = (int)$nbTickets->fetchColumn();

    $repModes = $db->prepare("
        SELECT v.mode_paiement, COUNT(*) AS nb
        FROM ventes v
        WHERE v.reference IN (SELECT reference_vente FROM mouvements_caisse WHERE session_id = ? AND reference_vente IS NOT NULL)
        GROUP BY v.mode_paiement
    ");
    $repModes->execute([$sessionId]);
    $modes = $repModes->fetchAll();

    $modeLabels = ['espèces' => 'Espèces', 'carte' => 'Carte', 'chèque' => 'Chèque', 'assurance' => 'Assurance', 'crédit' => 'Crédit'];

    $ventesX = $db->prepare("
        SELECT v.reference, v.client_nom, v.total, v.mode_paiement, v.created_at,
               COUNT(vl.id) AS nb_articles
        FROM ventes v
        LEFT JOIN vente_lignes vl ON vl.vente_id = v.id
        WHERE v.reference IN (
            SELECT DISTINCT reference_vente FROM mouvements_caisse
            WHERE session_id = ? AND reference_vente IS NOT NULL
        )
        GROUP BY v.id ORDER BY v.created_at
    ");
    $ventesX->execute([$sessionId]);
    $listeVentesX = $ventesX->fetchAll();

    $modeBadges = ['espèces' => 'badge-green', 'carte' => 'badge-blue', 'chèque' => 'badge-gray', 'assurance' => 'badge-purple', 'crédit' => 'badge-gray'];

    layout_head('Relevé de Caisse', 'caisse');
    showFlash();
    if ($fermetureAuto && $heureDepassee): ?>
<div style="max-width:500px;margin:0 auto 16px;padding:14px 18px;border-radius:10px;background:var(--red-dim);border:1px solid var(--red);display:flex;align-items:center;gap:10px;">
  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--red)" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
  <div style="font-size:13px;color:var(--red);font-weight:500;">Il est passé <?= e($fermetureHeure) ?> — Pensez à clôturer cette caisse.</div>
</div>
<?php endif; ?>
<div class="card" style="max-width:500px;margin:0 auto;">
  <div class="card-header">
    <div class="card-title">Relevé de caisse</div>
    <span class="badge badge-gray">Consultation</span>
  </div>
  <div class="card-pad" id="x-content">
    <div style="display:flex;justify-content:space-between;margin-bottom:16px;font-size:13px;color:var(--text2);">
      <span><strong><?= e($session['caisse_nom']) ?></strong> — Caissier : <?= e($session['prenom'] . ' ' . $session['u_nom']) ?></span>
      <span>Ouvert le <?= date('d/m/Y H:i', strtotime($session['date_ouverture'])) ?></span>
    </div>
    <div style="font-size:14px;line-height:2.4;">
      <div style="display:flex;justify-content:space-between;color:var(--text2);">
        <span>Fond initial</span>
        <span style="font-weight:500;"><?= fmtMoney((float)$session['fond_initial']) ?></span>
      </div>
      <div style="display:flex;justify-content:space-between;">
        <span>+ Ventes espèces</span>
        <span style="font-weight:500;"><?= fmtMoney($totalVentes) ?></span>
      </div>
      <div style="display:flex;justify-content:space-between;">
        <span>+ Dépôts</span>
        <span style="font-weight:500;"><?= fmtMoney($totalDepots) ?></span>
      </div>
      <div style="display:flex;justify-content:space-between;">
        <span>- Retraits / dépenses</span>
        <span style="font-weight:500;"><?= fmtMoney($totalRetraits) ?></span>
      </div>
      <div style="border-top:1px dashed var(--border2);margin:10px 0;"></div>
      <div style="display:flex;justify-content:space-between;font-size:20px;font-weight:700;" class="c-teal">
        <span>SOLDE THÉORIQUE</span>
        <span><?= fmtMoney($solde) ?></span>
      </div>
    </div>
    <div style="margin-top:16px;padding-top:12px;border-top:1px solid var(--border);font-size:12px;color:var(--text3);">
      <div>Nb tickets : <strong><?= $nb ?></strong></div>
      <?php if ($modes): ?>
      <div style="margin-top:2px;">
        <?php foreach ($modes as $m): ?>
        <?= e($modeLabels[$m['mode_paiement']] ?? $m['mode_paiement']) ?> : <?= (int)$m['nb'] ?> &nbsp;
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
    <?php if ($listeVentesX): ?>
    <div style="margin-top:16px;padding-top:12px;border-top:1px solid var(--border);">
      <div style="font-size:11px;color:var(--text3);text-transform:uppercase;letter-spacing:1px;margin-bottom:12px;">Ventes de la session</div>
      <table style="font-size:13px;width:100%;">
        <thead><tr><th style="padding:8px 6px;">Réf</th><th style="padding:8px 6px;">Client</th><th style="text-align:right;padding:8px 6px;">Montant</th><th style="padding:8px 6px;">Paiement</th><th style="padding:8px 6px;">Heure</th></tr></thead>
        <tbody>
        <?php foreach ($listeVentesX as $v):
          $badge = $modeBadges[$v['mode_paiement']] ?? 'badge-gray';
          $label = $modeLabels[$v['mode_paiement']] ?? $v['mode_paiement'];
        ?>
        <tr style="border-bottom:1px solid var(--border);">
          <td style="font-family:'DM Mono',monospace;padding:10px 6px;"><?= e($v['reference']) ?></td>
          <td style="padding:10px 6px;"><?= e($v['client_nom'] ?: '—') ?></td>
          <td style="text-align:right;color:var(--teal2);padding:10px 6px;font-family:'DM Mono',monospace;font-weight:500;"><?= fmtMoney((float)$v['total']) ?></td>
          <td style="padding:10px 6px;"><span class="badge <?= $badge ?>"><?= $label ?></span></td>
          <td style="color:var(--text3);padding:10px 6px;"><?= date('H:i', strtotime($v['created_at'])) ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
    <div style="display:flex;gap:10px;margin-top:20px;">
      <a href="<?= url('caisse') ?>" class="btn btn-ghost" style="flex:1;justify-content:center;">Retour</a>
      <a href="<?= url('caisse', ['action'=>'rapport_session','id'=>$sessionId]) ?>" class="btn btn-ghost" style="flex:1;justify-content:center;gap:6px;">
        <?= icon('file-text',14) ?> Rapport A4
      </a>
      <button class="btn btn-ghost btn-sm" onclick="printSection('x-content')" style="gap:6px;">
        <?= icon('receipt',14) ?> Imprimer
      </button>
    </div>
  </div>
</div>
<?php layout_foot(); return; endif;

// ═══════════════════════════════════════════════════════════
// VUE : Z de caisse (clôture)
// ═══════════════════════════════════════════════════════════
if ($action === 'z'):
    $sessionId = (int)($_GET['id'] ?? 0);
    $stmt = $db->prepare("
        SELECT s.*, u.prenom, u.nom AS u_nom, c.nom AS caisse_nom
        FROM sessions_caisse s
        JOIN utilisateurs u ON s.caissier_id = u.id
        JOIN caisses c ON s.caisse_id = c.id
        WHERE s.id = ?
    ");
    $stmt->execute([$sessionId]);
    $session = $stmt->fetch();
    if (!$session || $session['statut'] !== 'ouverte') {
        flash('Session invalide ou déjà fermée.', 'error'); header('Location: ' . url('caisse')); exit;
    }

    $soldeAttendu = soldeSession($db, $sessionId);
    $estForce = (int)$session['caissier_id'] !== currentUser()['id'];

    $mouvements = $db->prepare("SELECT * FROM mouvements_caisse WHERE session_id = ? ORDER BY created_at");
    $mouvements->execute([$sessionId]);
    $mvts = $mouvements->fetchAll();

    $ventesSession = $db->prepare("
        SELECT v.reference, v.client_nom, v.total, v.mode_paiement, v.created_at,
               COUNT(vl.id) AS nb_articles
        FROM ventes v
        LEFT JOIN vente_lignes vl ON vl.vente_id = v.id
        WHERE v.reference IN (
            SELECT DISTINCT reference_vente FROM mouvements_caisse
            WHERE session_id = ? AND reference_vente IS NOT NULL
        )
        GROUP BY v.id ORDER BY v.created_at
    ");
    $ventesSession->execute([$sessionId]);
    $listeVentes = $ventesSession->fetchAll();

    $modeLabels = ['espèces' => 'Espèces', 'carte' => 'Carte bancaire', 'chèque' => 'Chèque', 'assurance' => 'Assurance', 'crédit' => 'Crédit'];
    $modeBadges = ['espèces' => 'badge-green', 'carte' => 'badge-blue', 'chèque' => 'badge-gray', 'assurance' => 'badge-purple', 'crédit' => 'badge-gray'];

    layout_head('Clôture de Caisse', 'caisse');
    showFlash();
    if ($fermetureAuto && $heureDepassee): ?>
<div style="max-width:550px;margin:0 auto 16px;padding:14px 18px;border-radius:10px;background:var(--red-dim);border:1px solid var(--red);display:flex;align-items:center;gap:10px;">
  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--red)" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
  <div style="font-size:13px;color:var(--red);font-weight:500;">Fermeture automatique prévue à <?= e($fermetureHeure) ?> — heure dépassée.</div>
</div>
<?php endif; ?>
<div class="card" style="max-width:550px;margin:0 auto;">
  <div class="card-header">
    <div class="card-title">Clôture de caisse</div>
    <?php if ($estForce): ?>
    <span class="badge" style="background:var(--red-dim);color:var(--red);">Fermeture forcée</span>
    <?php endif; ?>
  </div>
  <div class="card-pad" id="z-content" style="display:flex;flex-direction:column;gap:24px;">
    <!-- Infos session -->
    <div>
      <div style="font-weight:600;font-size:15px;margin-bottom:4px;"><?= e($session['caisse_nom']) ?></div>
      <div style="font-size:13px;color:var(--text2);line-height:1.7;">
        Caissier : <strong><?= e($session['prenom'] . ' ' . $session['u_nom']) ?></strong><br>
        Ouverte le <strong><?= date('d/m/Y à H:i', strtotime($session['date_ouverture'])) ?></strong>
      </div>
    </div>

    <!-- Solde théorique -->
    <div style="background:var(--bg2);border-radius:12px;padding:24px;text-align:center;">
      <div style="font-size:11px;color:var(--text3);text-transform:uppercase;letter-spacing:1px;margin-bottom:8px;">Solde théorique</div>
      <div id="solde-theorique" style="font-size:32px;font-weight:700;color:var(--teal2);line-height:1.2;"
           data-solde="<?= (float)$soldeAttendu ?>"><?= fmtMoney($soldeAttendu) ?></div>
    </div>

    <form method="POST" id="form-z" style="display:flex;flex-direction:column;gap:20px;">
      <input type="hidden" name="csrf" value="<?= csrf() ?>">
      <input type="hidden" name="action_type" value="cloturer">
      <input type="hidden" name="session_id" value="<?= (int)$session['id'] ?>">

      <div class="form-group" style="margin-bottom:0;">
        <label style="margin-bottom:8px;">Solde compté physiquement (FCFA)</label>
        <input type="number" name="solde_reel" id="solde-reel" min="0" step="1" required
               style="font-size:18px;font-weight:600;padding:14px 16px;" placeholder="Comptez et saisissez le montant réel">
      </div>

      <div id="ecart-box" style="text-align:center;padding:16px 20px;border-radius:10px;display:none;">
        <div style="font-size:11px;text-transform:uppercase;letter-spacing:1px;margin-bottom:4px;">Écart</div>
        <div id="ecart-val" style="font-size:24px;font-weight:700;"></div>
      </div>

      <?php if ($estForce && hasPermission('caisse.gerer')): ?>
      <div class="form-group" style="margin-bottom:0;">
        <label style="margin-bottom:8px;">Motif de la fermeture forcée *</label>
        <input type="text" name="motif_force" required placeholder="Raison de la fermeture imposée" style="padding:12px 14px;">
      </div>
      <?php endif; ?>

      <div style="display:flex;gap:10px;">
        <a href="<?= url('caisse') ?>" class="btn btn-ghost" style="flex:1;justify-content:center;padding:10px;">Annuler</a>
        <a href="<?= url('caisse', ['action'=>'rapport_session','id'=>$session['id']]) ?>" class="btn btn-ghost" style="gap:6px;padding:10px 14px;">
          <?= icon('file-text',14) ?> Rapport A4
        </a>
        <button type="button" class="btn btn-ghost" onclick="printSection('z-content')" style="gap:6px;padding:10px 14px;">
          <?= icon('receipt',14) ?> Imprimer
        </button>
        <button type="submit" class="btn btn-primary" style="flex:1;justify-content:center;gap:6px;padding:10px;">
          <?= icon('lock',14) ?> Clôturer
        </button>
      </div>
    </form>

    <?php if ($mvts): ?>
    <div style="border-top:1px solid var(--border);padding-top:20px;">
      <div style="font-size:11px;color:var(--text3);text-transform:uppercase;letter-spacing:1px;margin-bottom:12px;">Détail des mouvements</div>
      <table style="font-size:13px;width:100%;">
        <thead><tr><th style="padding:8px 6px;">Heure</th><th style="padding:8px 6px;">Motif</th><th style="text-align:right;padding:8px 6px;">Entrée</th><th style="text-align:right;padding:8px 6px;">Sortie</th></tr></thead>
        <tbody>
        <?php foreach ($mvts as $m): ?>
        <tr style="border-bottom:1px solid var(--border);">
          <td style="color:var(--text3);padding:10px 6px;"><?= date('H:i', strtotime($m['created_at'])) ?></td>
          <td style="padding:10px 6px;"><?= e($m['motif']) ?></td>
          <td style="text-align:right;color:var(--teal2);padding:10px 6px;font-family:'DM Mono',monospace;"><?= $m['type'] === 'entrée' ? fmtMoney((float)$m['montant']) : '' ?></td>
          <td style="text-align:right;color:var(--red);padding:10px 6px;font-family:'DM Mono',monospace;"><?= $m['type'] === 'sortie' ? fmtMoney((float)$m['montant']) : '' ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>

    <?php if ($listeVentes): ?>
    <div style="border-top:1px solid var(--border);padding-top:20px;">
      <div style="font-size:11px;color:var(--text3);text-transform:uppercase;letter-spacing:1px;margin-bottom:12px;">Ventes de la session</div>
      <table style="font-size:13px;width:100%;">
        <thead><tr><th style="padding:8px 6px;">Réf</th><th style="padding:8px 6px;">Client</th><th style="text-align:right;padding:8px 6px;">Montant</th><th style="padding:8px 6px;">Paiement</th><th style="padding:8px 6px;">Heure</th></tr></thead>
        <tbody>
        <?php foreach ($listeVentes as $v):
          $badge = $modeBadges[$v['mode_paiement']] ?? 'badge-gray';
          $label = $modeLabels[$v['mode_paiement']] ?? $v['mode_paiement'];
        ?>
        <tr style="border-bottom:1px solid var(--border);">
          <td style="font-family:'DM Mono',monospace;padding:10px 6px;"><?= e($v['reference']) ?></td>
          <td style="padding:10px 6px;"><?= e($v['client_nom'] ?: '—') ?></td>
          <td style="text-align:right;color:var(--teal2);padding:10px 6px;font-family:'DM Mono',monospace;font-weight:500;"><?= fmtMoney((float)$v['total']) ?></td>
          <td style="padding:10px 6px;"><span class="badge <?= $badge ?>"><?= $label ?></span></td>
          <td style="color:var(--text3);padding:10px 6px;"><?= date('H:i', strtotime($v['created_at'])) ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>
</div>
<script>
(function(){
  var reel=document.getElementById('solde-reel');
  var box=document.getElementById('ecart-box');
  var val=document.getElementById('ecart-val');
  var theo=parseFloat(document.getElementById('solde-theorique').dataset.solde);
  reel.addEventListener('input',function(){
    var ecart=Math.round((parseFloat(this.value)||0)-theo);
    if(this.value===''){box.style.display='none';return;}
    box.style.display='';
    val.textContent=(ecart>=0?'+':'')+ecart.toLocaleString('fr-FR')+' FCFA';
    var abs=Math.abs(ecart);
    if(abs===0){box.style.background='var(--teal-dim)';val.style.color='var(--teal2)';}
    else if(abs<5000){box.style.background='var(--gold-dim)';val.style.color='var(--gold)';}
    else{box.style.background='var(--red-dim)';val.style.color='var(--red)';}
  });
})();
</script>
<?php layout_foot(); return; endif;

// ═══════════════════════════════════════════════════════════
// VUE : Rapport de Session (Format A4)
// ═══════════════════════════════════════════════════════════
if ($action === 'rapport_session'):
    $sessionId = (int)($_GET['id'] ?? 0);
    $stmt = $db->prepare("
        SELECT s.*, u.prenom, u.nom AS u_nom, c.nom AS caisse_nom
        FROM sessions_caisse s
        JOIN utilisateurs u ON s.caissier_id = u.id
        JOIN caisses c ON s.caisse_id = c.id
        WHERE s.id = ?
    ");
    $stmt->execute([$sessionId]);
    $session = $stmt->fetch();
    if (!$session) { flash('Session introuvable.', 'error'); header('Location: ' . url('caisse')); exit; }

    $soldeAttendu = soldeSession($db, $sessionId);

    $ventesEspeces = $db->prepare("SELECT COALESCE(SUM(montant), 0) FROM mouvements_caisse WHERE session_id = ? AND type = 'entrée' AND reference_vente IS NOT NULL");
    $ventesEspeces->execute([$sessionId]);
    $totalVentes = (float)$ventesEspeces->fetchColumn();

    $depots = $db->prepare("SELECT COALESCE(SUM(montant), 0) FROM mouvements_caisse WHERE session_id = ? AND type = 'entrée' AND reference_vente IS NULL");
    $depots->execute([$sessionId]);
    $totalDepots = (float)$depots->fetchColumn();

    $retraits = $db->prepare("SELECT COALESCE(SUM(montant), 0) FROM mouvements_caisse WHERE session_id = ? AND type = 'sortie'");
    $retraits->execute([$sessionId]);
    $totalRetraits = (float)$retraits->fetchColumn();

    $repModes = $db->prepare("
        SELECT v.mode_paiement, SUM(v.total) as total, COUNT(*) AS nb
        FROM ventes v
        WHERE v.reference IN (SELECT reference_vente FROM mouvements_caisse WHERE session_id = ? AND reference_vente IS NOT NULL)
        GROUP BY v.mode_paiement
    ");
    $repModes->execute([$sessionId]);
    $modes = $repModes->fetchAll();

    $mouvements = $db->prepare("SELECT * FROM mouvements_caisse WHERE session_id = ? ORDER BY created_at");
    $mouvements->execute([$sessionId]);
    $mvts = $mouvements->fetchAll();

    $modeLabels = ['espèces' => 'Espèces', 'carte' => 'Carte', 'chèque' => 'Chèque', 'assurance' => 'Assurance', 'crédit' => 'Crédit'];

    layout_head('Rapport de Session', 'caisse');
    showFlash();
    ?>
    <div class="card" style="max-width:800px;margin:0 auto;" id="rapport-print">
      <div class="card-header" style="border-bottom:2px solid var(--teal);padding-bottom:20px;">
        <div class="flex-between" style="align-items:center;">
          <div style="font-family:var(--font-title);font-size:24px;font-weight:700;color:var(--teal2);">
            <?= getParam('app_nom') ?>
          </div>
          <div style="text-align:right;font-size:13px;color:var(--text2);">
            <strong>Rapport de Session de Caisse</strong><br>
            Généré le <?= date('d/m/Y à H:i') ?>
          </div>
        </div>
      </div>
      <div class="card-pad" style="display:flex;flex-direction:column;gap:30px;">

        <!-- Section 1: Infos Session -->
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;background:var(--bg2);padding:20px;border-radius:12px;font-size:14px;">
          <div>
            <div style="color:var(--text3);font-size:11px;text-transform:uppercase;margin-bottom:4px;">Poste de Caisse</div>
            <div style="font-weight:600;"><?= e($session['caisse_nom']) ?></div>
          </div>
          <div>
            <div style="color:var(--text3);font-size:11px;text-transform:uppercase;margin-bottom:4px;">Caissier</div>
            <div style="font-weight:600;"><?= e($session['prenom'] . ' ' . $session['u_nom']) ?></div>
          </div>
          <div>
            <div style="color:var(--text3);font-size:11px;text-transform:uppercase;margin-bottom:4px;">Ouverture</div>
            <div style="font-weight:500;"><?= date('d/m/Y à H:i', strtotime($session['date_ouverture'])) ?></div>
          </div>
          <div>
            <div style="color:var(--text3);font-size:11px;text-transform:uppercase;margin-bottom:4px;">Fermeture</div>
            <div style="font-weight:500;"><?= $session['date_fermeture'] ? date('d/m/Y à H:i', strtotime($session['date_fermeture'])) : 'Non clôturée' ?></div>
          </div>
        </div>

        <!-- Section 2: Bilan Financier -->
        <div>
          <div style="font-size:15px;font-weight:600;margin-bottom:15px;display:flex;align-items:center;gap:8px;">
            <?= icon('calculator', 18) ?> Bilan Financier (Espèces)
          </div>
          <table style="width:100%;border-collapse:collapse;font-size:14px;">
            <tbody>
              <tr style="border-bottom:1px solid var(--border);">
                <td style="padding:12px 0;color:var(--text2);">Fond de caisse initial</td>
                <td style="text-align:right;padding:12px 0;font-family:'DM Mono',monospace;"><?= fmtMoney((float)$session['fond_initial']) ?></td>
              </tr>
              <tr style="border-bottom:1px solid var(--border);">
                <td style="padding:12px 0;color:var(--text2);">Total Ventes Espèces</td>
                <td style="text-align:right;padding:12px 0;font-family:'DM Mono',monospace;"><?= fmtMoney($totalVentes) ?></td>
              </tr>
              <tr style="border-bottom:1px solid var(--border);">
                <td style="padding:12px 0;color:var(--text2);">Dépôts manuels</td>
                <td style="text-align:right;padding:12px 0;font-family:'DM Mono',monospace;"><?= fmtMoney($totalDepots) ?></td>
              </tr>
              <tr style="border-bottom:1px solid var(--border);">
                <td style="padding:12px 0;color:var(--red);">Retraits / Dépenses</td>
                <td style="text-align:right;padding:12px 0;font-family:'DM Mono',monospace;color:var(--red);">- <?= fmtMoney($totalRetraits) ?></td>
              </tr>
              <tr style="background:var(--teal-dim);font-weight:700;font-size:16px;">
                <td style="padding:12px 15px;border-radius:8px 0 0 8px;">SOLDE THÉORIQUE</td>
                <td style="text-align:right;padding:12px 15px;border-radius:0 8px 8px 0;font-family:'DM Mono',monospace;color:var(--teal2);"><?= fmtMoney($soldeAttendu) ?></td>
              </tr>
              <tr>
                <td style="padding:12px 0;font-weight:600;">Solde Réel (compté)</td>
                <td style="text-align:right;padding:12px 0;font-family:'DM Mono',monospace;font-weight:600;"><?= $session['solde_reel'] !== null ? fmtMoney((float)$session['solde_reel']) : '—' ?></td>
              </tr>
              <tr style="border-top:2px solid var(--border);font-size:18px;font-weight:700;">
                <td style="padding:12px 0;">ÉCART DE CAISSE</td>
                <td style="text-align:right;padding:12px 0;font-family:'DM Mono',monospace;color:<?= ($session['ecart'] ?? 0) === 0.0 ? 'var(--teal2)' : 'var(--red)' ?>;">
                  <?= $session['ecart'] !== null ? fmtMoney((float)$session['ecart']) : '—' ?>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Section 3: Autres Modes de Paiement -->
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:30px;">
          <div>
            <div style="font-size:15px;font-weight:600;margin-bottom:15px;display:flex;align-items:center;gap:8px;">
              <?= icon('credit-card', 18) ?> Autres Paiements
            </div>
            <table style="width:100%;border-collapse:collapse;font-size:13px;">
              <thead>
                <tr style="text-align:left;color:var(--text3);border-bottom:1px solid var(--border);">
                  <th style="padding:8px 0;">Mode</th>
                  <th style="text-align:center;padding:8px 0;">Nb</th>
                  <th style="text-align:right;padding:8px 0;">Total</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($modes as $m):
                  if ($m['mode_paiement'] === 'espèces') continue;
                ?>
                <tr style="border-bottom:1px solid var(--border-dim);">
                  <td style="padding:8px 0;"><?= e($modeLabels[$m['mode_paiement']] ?? $m['mode_paiement']) ?></td>
                  <td style="text-align:center;padding:8px 0;"><?= $m['nb'] ?></td>
                  <td style="text-align:right;padding:8px 0;font-family:'DM Mono',monospace;"><?= fmtMoney((float)$m['total']) ?></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <div>
            <div style="font-size:15px;font-weight:600;margin-bottom:15px;display:flex;align-items:center;gap:8px;">
              <?= icon('info', 18) ?> Statistiques
            </div>
            <div style="background:var(--bg2);padding:15px;border-radius:12px;font-size:13px;line-height:2;">
              <div style="display:flex;justify-content:space-between;">
                <span>Total transactions :</span>
                <span style="font-weight:600;"><?= count($mvts) ?></span>
              </div>
              <div style="display:flex;justify-content:space-between;">
                <span>Ventes espèces :</span>
                <span style="font-weight:600;"><?= fmtMoney($totalVentes) ?></span>
              </div>
            </div>
          </div>
        </div>

        <!-- Section 4: Journal des Mouvements -->
        <div>
          <div style="font-size:15px;font-weight:600;margin-bottom:15px;display:flex;align-items:center;gap:8px;">
            <?= icon('list', 18) ?> Journal des Mouvements
          </div>
          <table style="width:100%;border-collapse:collapse;font-size:12px;">
            <thead>
              <tr style="background:var(--bg2);text-align:left;color:var(--text3);">
                <th style="padding:10px;border-bottom:2px solid var(--border);">Heure</th>
                <th style="padding:10px;border-bottom:2px solid var(--border);">Motif / Référence</th>
                <th style="text-align:right;padding:10px;border-bottom:2px solid var(--border);">Entrée</th>
                <th style="text-align:right;padding:10px;border-bottom:2px solid var(--border);">Sortie</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($mvts as $m): ?>
              <tr style="border-bottom:1px solid var(--border-dim);">
                <td style="padding:10px;color:var(--text2);"><?= date('H:i', strtotime($m['created_at'])) ?></td>
                <td style="padding:10px;"><?= e($m['motif']) ?> <?= $m['reference_vente'] ? '<span class="badge badge-gray" style="font-size:9px;">'.$m['reference_vente'].'</span>' : '' ?></td>
                <td style="text-align:right;padding:10px;font-family:'DM Mono',monospace;color:var(--teal2);"><?= $m['type'] === 'entrée' ? fmtMoney((float)$m['montant']) : '' ?></td>
                <td style="text-align:right;padding:10px;font-family:'DM Mono',monospace;color:var(--red);"><?= $m['type'] === 'sortie' ? fmtMoney((float)$m['montant']) : '' ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <!-- Section 5: Signatures -->
        <div style="margin-top:40px;display:grid;grid-template-columns:1fr 1fr;gap:60px;text-align:center;font-size:13px;">
          <div style="display:flex;flex-direction:column;gap:60px;">
            <div style="font-weight:600;text-decoration:underline;">Signature du Caissier</div>
            <div style="height:80px;border:1px dashed var(--border);border-radius:8px;"></div>
          </div>
          <div style="display:flex;flex-direction:column;gap:60px;">
            <div style="font-weight:600;text-decoration:underline;">Signature du Responsable</div>
            <div style="height:80px;border:1px dashed var(--border);border-radius:8px;"></div>
          </div>
        </div>

      </div>
      <div style="display:flex;gap:10px;margin-top:30px;justify-content:center;">
        <a href="<?= url('caisse') ?>" class="btn btn-ghost" style="padding:10px 20px;">Retour</a>
        <button class="btn btn-primary" onclick="printRapport()" style="gap:6px;padding:10px 20px;">
          <?= icon('print', 14) ?> Imprimer le rapport
        </button>
      </div>
    </div>
    <script>
    function printRapport(){
      if (!rateLimitClick('print.rapportZ', 10, 60000)) { rateLimitWarn('print.rapportZ', 10, 60000); return; }
      var el=document.getElementById('rapport-print');
      var content=el.innerHTML;
      var win=window.open('','_blank','width=900,height=1100');
      win.document.write('<!DOCTYPE html><html><head><title>Rapport de Caisse</title>'+
        '<style>*{margin:0;padding:0;box-sizing:border-box;} body{font-family:\'DM Sans\',sans-serif;padding:40px;color:#333;}'+
        '.flex-between{display:flex;justify-content:space-between;}.card-pad{display:flex;flex-direction:column;gap:30px;}' +
        'table{width:100%;border-collapse:collapse;} .badge{padding:2px 6px;border-radius:4px;font-size:10px;background:#eee;color:#666;}' +
        '.c-teal{color:#00c9a7;}.fw-mono{font-family:\'DM Mono\',monospace;}' +
        'div, span, td, th { font-family: "DM Sans", sans-serif; }' +
        'input, select, button, .btn, form { display:none !important; }' +
        '@media print{@page{margin:1cm;size:A4} body{padding:0;}}' +
        '</style>'+
        '<link href="<?= APP_URL ?>/assets/fonts/fonts.css" rel="stylesheet">'+
        '</head><body>'+content+'<script>window.onload=function(){window.print();}<\/script></body></html>');
      win.document.close();
    }
    </script>
    <?php layout_foot(); return; endif;

    // ═══════════════════════════════════════════════════════════
    // VUE : Historique des sessions
    // ═══════════════════════════════════════════════════════════
    if ($action === 'historique'):
    ?>
    <?php
    $filtreCaisse = $_GET['caisse'] ?? '';
    $filtreDate   = $_GET['date']   ?? '';

    $sql = "
        SELECT s.*, u.prenom, u.nom AS u_nom, c.nom AS caisse_nom
        FROM sessions_caisse s
        JOIN utilisateurs u ON s.caissier_id = u.id
        JOIN caisses c ON s.caisse_id = c.id
        WHERE 1=1
    ";
    $params = [];
    if ($filtreCaisse !== '') { $sql .= " AND s.caisse_id = ?"; $params[] = (int)$filtreCaisse; }
    if ($filtreDate !== '')   { $sql .= " AND DATE(s.date_ouverture) = ?"; $params[] = $filtreDate; }
    $sql .= " ORDER BY s.date_ouverture DESC LIMIT 50";

    $stmtH = $db->prepare($sql);
    $stmtH->execute($params);
    $sessions = $stmtH->fetchAll();

    $allCaisses = $db->query("SELECT id, nom FROM caisses WHERE actif = 1 ORDER BY id")->fetchAll();

    layout_head('Historique Sessions', 'caisse');
    showFlash();
?>
<div class="card">
  <div class="card-header">
    <div class="card-title">Historique des sessions</div>
    <a href="<?= url('caisse') ?>" class="btn btn-ghost btn-sm">← Dashboard</a>
  </div>
  <div class="card-pad" style="padding-bottom:10px;">
    <form method="GET" style="display:flex;gap:10px;align-items:end;flex-wrap:wrap;">
      <input type="hidden" name="action" value="historique">
      <div class="form-group" style="margin:0;min-width:140px;">
        <label>Poste</label>
        <select name="caisse" onchange="this.form.submit()">
          <option value="">Tous</option>
          <?php foreach ($allCaisses as $c): ?>
          <option value="<?= (int)$c['id'] ?>" <?= $filtreCaisse === (string)$c['id'] ? 'selected' : '' ?>><?= e($c['nom']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group" style="margin:0;min-width:140px;">
        <label>Date</label>
        <input type="date" name="date" value="<?= e($filtreDate) ?>" onchange="this.form.submit()">
      </div>
      <?php if ($filtreCaisse !== '' || $filtreDate !== ''): ?>
      <a href="<?= url('caisse', ['action'=>'historique']) ?>" class="btn btn-ghost btn-xs" style="align-self:end;margin-bottom:2px;">Réinitialiser</a>
      <?php endif; ?>
    </form>
  </div>
  <div class="card-pad" style="padding:0;">
    <table>
      <thead>
        <tr>
          <th>Poste</th><th>Caissier</th><th>Ouverture</th><th>Fermeture</th>
          <th style="text-align:right;">Fond init.</th><th style="text-align:right;">Attendu</th>
          <th style="text-align:right;">Réel</th><th style="text-align:right;">Écart</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($sessions as $s):
          $ecartVal = (float)($s['ecart'] ?? 0);
          $ecartColor = $ecartVal === 0.0 ? 'var(--teal2)' : ($ecartVal < 0 ? 'var(--red)' : 'var(--gold)');
        ?>
        <tr>
          <td><?= e($s['caisse_nom']) ?></td>
          <td><?= e($s['prenom'] . ' ' . $s['u_nom']) ?></td>
          <td><?= date('d/m/Y H:i', strtotime($s['date_ouverture'])) ?></td>
          <td><?= $s['date_fermeture'] ? date('d/m/Y H:i', strtotime($s['date_fermeture'])) : '<span class="badge" style="background:var(--teal-dim);color:var(--teal2);">En cours</span>' ?></td>
          <td style="text-align:right;"><?= fmtMoney((float)$s['fond_initial']) ?></td>
          <td style="text-align:right;"><?= $s['solde_attendu'] !== null ? fmtMoney((float)$s['solde_attendu']) : '—' ?></td>
          <td style="text-align:right;"><?= $s['solde_reel'] !== null ? fmtMoney((float)$s['solde_reel']) : '—' ?></td>
          <td style="text-align:right;font-weight:600;color:<?= $ecartColor ?>;"><?= $s['ecart'] !== null ? fmtMoney((float)$s['ecart']) : '—' ?></td>
          <td>
            <?php if ($s['statut'] === 'fermée'): ?>
              <a href="<?= url('caisse', ['action'=>'rapport_session','id'=>$s['id']]) ?>" class="btn btn-ghost btn-xs" style="gap:4px;">
                <?= icon('file-text',12) ?> Rapport
              </a>
            <?php else: ?>
              <span class="text-sm" style="color:var(--text3);">Sess. ouverte</span>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$sessions): ?>
        <tr><td colspan="8" style="text-align:center;color:var(--text3);padding:30px;">Aucune session trouvée.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php layout_foot(); return; endif;

// ═══════════════════════════════════════════════════════════
// VUE : Dashboard (par défaut)
// ═══════════════════════════════════════════════════════════

$caisses = $db->query("SELECT * FROM caisses WHERE actif = 1 ORDER BY id")->fetchAll();

$sessionsOuvertes = $db->query("
    SELECT s.*, u.prenom, u.nom AS u_nom, c.nom AS caisse_nom, ph.nom AS pharmacie_nom
    FROM sessions_caisse s
    JOIN utilisateurs u ON s.caissier_id = u.id
    JOIN caisses c ON s.caisse_id = c.id
    LEFT JOIN pharmacies ph ON s.pharmacie_id = ph.id
    WHERE s.statut = 'ouverte'
")->fetchAll();
$ouvertesParCaisse = array_column($sessionsOuvertes, null, 'caisse_id');

layout_head('Gestion des Caisses', 'caisse');
showFlash();

// ── Alerte fermeture auto si l'heure est dépassée ──────────
if ($fermetureAuto && $heureDepassee && !empty($sessionsOuvertes)):
?>
<div class="card" style="margin-bottom:20px;border:1px solid var(--red);background:var(--red-dim);">
  <div style="padding:18px 22px;display:flex;align-items:center;gap:14px;">
    <div style="flex-shrink:0;width:42px;height:42px;border-radius:50%;background:var(--red);display:flex;align-items:center;justify-content:center;">
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
    </div>
    <div style="flex:1;">
      <div style="font-weight:600;font-size:15px;color:var(--red);">Fermeture automatique à <?= e($fermetureHeure) ?> dépassée</div>
      <div style="font-size:13px;color:var(--text2);margin-top:4px;">
        Il est passé <?= e($fermetureHeure) ?>. Veuillez clôturer les sessions ouvertes.
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<?php if (hasPermission('caisse.gerer') && isset($_GET['new'])): ?>
<div class="card" style="max-width:400px;margin:0 auto 20px;">
  <div class="card-header"><div class="card-title">Nouveau poste</div></div>
  <div class="card-pad">
    <form method="POST">
      <input type="hidden" name="csrf" value="<?= csrf() ?>">
      <input type="hidden" name="action_type" value="creer_poste">
      <div class="form-group">
        <label>Nom du poste</label>
        <input type="text" name="nom" placeholder="ex: Caisse 4" required>
      </div>
      <div style="display:flex;gap:10px;">
        <a href="<?= url('caisse') ?>" class="btn btn-ghost btn-sm" style="flex:1;justify-content:center;">Annuler</a>
        <button type="submit" class="btn btn-primary btn-sm" style="flex:1;justify-content:center;">Créer</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<div class="card">
  <div class="card-header">
    <div class="card-title">Dashboard des caisses</div>
    <div style="display:flex;gap:8px;">
      <?php if (hasPermission('caisse.ouvrir')): ?>
      <a href="<?= url('caisse', ['action'=>'ouvrir']) ?>" class="btn btn-primary btn-sm" style="gap:6px;">
        <?= icon('plus',14) ?> Ouvrir une caisse
      </a>
      <?php endif; ?>
      <a href="<?= url('caisse', ['action'=>'historique']) ?>" class="btn btn-ghost btn-sm" style="gap:6px;">
        <?= icon('history',14) ?> Historique
      </a>
      <?php if (hasPermission('caisse.gerer')): ?>
      <a href="?new=1" class="btn btn-ghost btn-sm" style="gap:6px;">
        <?= icon('plus',14) ?> Nouveau poste
      </a>
      <?php endif; ?>
    </div>
  </div>
  <div class="card-pad" style="padding:0;">
    <table>
      <thead>
        <tr>
          <th>Poste</th><th>Statut</th><th>Caissier</th><th>Pharmacie</th>
          <th>Ouvert depuis</th><th style="text-align:right;">Solde</th><th style="text-align:center;">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($caisses as $c):
          $session = $ouvertesParCaisse[$c['id']] ?? null;
          $estOuverte = $session !== null;
          $solde = $estOuverte ? soldeSession($db, (int)$session['id']) : 0;
        ?>
        <tr>
          <td><strong><?= e($c['nom']) ?></strong></td>
          <td>
            <?php if ($estOuverte): ?>
            <span class="badge" style="background:var(--teal-dim);color:var(--teal2);font-weight:500;">● Ouverte</span>
            <?php if ($fermetureAuto && $heureDepassee): ?>
            <span class="badge" style="background:var(--red-dim);color:var(--red);font-weight:500;margin-left:4px;">⚡ À clôturer</span>
            <?php endif; ?>
            <?php else: ?>
            <span class="badge badge-gray">○ Fermée</span>
            <?php endif; ?>
          </td>
          <td><?= $estOuverte ? e($session['prenom'] . ' ' . $session['u_nom']) : '—' ?></td>
          <td><?= $estOuverte && !empty($session['pharmacie_nom']) ? e($session['pharmacie_nom']) : '—' ?></td>
          <td style="font-size:12px;color:var(--text2);"><?= $estOuverte ? dureeDepuis($session['date_ouverture']) : '—' ?></td>
          <td style="text-align:right;font-weight:600;font-family:'DM Mono',monospace;"><?= $estOuverte ? fmtMoney($solde) : '—' ?></td>
          <td style="text-align:center;">
            <?php if ($estOuverte): ?>
              <a href="<?= url('caisse', ['action'=>'x','id'=>$session['id']]) ?>" class="btn btn-ghost btn-xs" title="Relevé de caisse sans clôture" style="gap:4px;">
                <?= icon('eye',13) ?> Relevé
              </a>
              <?php $peutCloturer = (int)$session['caissier_id'] === currentUser()['id'] || hasPermission('caisse.gerer'); ?>
              <?php if ($peutCloturer): ?>
              <a href="<?= url('caisse', ['action'=>'z','id'=>$session['id']]) ?>" class="btn btn-ghost btn-xs" title="Clôturer la caisse" style="gap:4px;color:var(--red);">
                <?= icon('lock',13) ?> Clôturer
              </a>
              <?php endif; ?>
              <?php if ((int)$session['caissier_id'] === currentUser()['id']): ?>
              <a href="<?= url('caisse', ['action'=>'mouvement','id'=>$session['id']]) ?>" class="btn btn-ghost btn-xs" title="Ajouter un mouvement" style="gap:4px;">
                <?= icon('plus',13) ?> Mvt
              </a>
              <?php endif; ?>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php if ($action === 'mouvement'):
    $mvtSessionId = (int)($_GET['id'] ?? 0);
    $stmtMvt = $db->prepare("SELECT s.*, c.nom AS caisse_nom FROM sessions_caisse s JOIN caisses c ON s.caisse_id = c.id WHERE s.id = ? AND s.caissier_id = ? AND s.statut = 'ouverte'");
    $stmtMvt->execute([$mvtSessionId, currentUser()['id']]);
    $mvtSession = $stmtMvt->fetch();
    if ($mvtSession):
?>
<div class="card" style="max-width:480px;margin:20px auto 0;">
  <div class="card-header"><div class="card-title">Mouvement de caisse — <?= e($mvtSession['caisse_nom']) ?></div></div>
  <div class="card-pad">
    <form method="POST">
      <input type="hidden" name="csrf" value="<?= csrf() ?>">
      <input type="hidden" name="action_type" value="mouvement">
      <input type="hidden" name="session_id" value="<?= $mvtSessionId ?>">
      <div class="form-group">
        <label>Type de mouvement</label>
        <select name="type_mvt" required>
          <option value="sortie">Retrait / Dépense</option>
          <option value="entrée">Dépôt (ajout d'espèces)</option>
        </select>
      </div>
      <div class="form-group">
        <label>Montant (FCFA)</label>
        <input type="number" name="montant" value="0" min="1" step="1" required style="font-size:16px;font-weight:600;">
      </div>
      <div class="form-group">
        <label>Motif</label>
        <input type="text" name="motif" placeholder="ex: Achat fournitures, Retrait banque..." required>
      </div>
      <div style="display:flex;gap:10px;">
        <a href="<?= url('caisse') ?>" class="btn btn-ghost" style="flex:1;justify-content:center;">Annuler</a>
        <button type="submit" class="btn btn-primary" style="flex:1;justify-content:center;gap:6px;">
          <?= icon('save',14) ?> Enregistrer
        </button>
      </div>
    </form>
  </div>
</div>
<?php endif; endif; ?>

<script>
function printSection(id){
  if (!rateLimitClick('print.caisse', 15, 60000)) { rateLimitWarn('print.caisse', 15, 60000); return; }
  var el=document.getElementById(id);
  if(!el)return;
  var content=el.innerHTML;
  var win=window.open('','_blank','width=320,height=600');
  win.document.write('<!DOCTYPE html><html><head><title>Ticket Caisse</title>'+
    '<style>*{margin:0;padding:0;box-sizing:border-box;}body{font-family:\'DM Mono\',monospace;font-size:11px;line-height:1.8;padding:16px;max-width:300px;margin:0 auto;}@media print{@page{margin:0;size:80mm auto;}}input,select,button,.btn,.badge-gray,form{display:none;}</style>'+
    '<link href="<?= APP_URL ?>/assets/fonts/fonts.css" rel="stylesheet">'+
    '</head><body>'+content+'<script>window.onload=function(){window.print();}<\/script></body></html>');
  win.document.close();
}
</script>

<?php layout_foot(); ?>
