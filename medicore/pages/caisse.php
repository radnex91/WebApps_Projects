<?php
$currentPage = 'caisse';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$s = get_settings();

//  GNRER numro ticket
function gen_ticket_num(): string {
    $last = db_scalar("SELECT numero_ticket FROM caisse_ventes ORDER BY id DESC LIMIT 1");
    if ($last && preg_match('/TK-\d{4}-(\d+)/', $last, $m)) {
        $next = (int)$m[1] + 1;
    } else {
        $next = 1;
    }
    return 'TK-' . date('Y') . '-' . str_pad($next, 5, '0', STR_PAD_LEFT);
}

//  CRÉER VENTE
if (can('caisse.create_vente') && $_SERVER['REQUEST_METHOD'] === 'POST' && post_str('action') === 'create_vente') {
    csrf_verify();

    $med_ids   = $_POST['med_id']   ?? [];
    $quantites = $_POST['quantite'] ?? [];
    $remises   = $_POST['remise']   ?? [];

    if (empty($med_ids)) {
        $flash = 'Ajoutez au moins un médicament.';
    } else {
        $mode       = in_whitelist(post_str('mode_paiement'), ['especes','carte','cheque','virement','assurance','gratuit'], 'especes');
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

        $vente_id = db_exec(
            "INSERT INTO caisse_ventes (numero_ticket, patient_id, caissier_id, montant_total, montant_recu, monnaie_rendue, mode_paiement, statut, ordonnance_id, notes)
             VALUES (?,?,?,?,?,?,?,?,?,?)",
            [$num, $patient_id, $_SESSION['user_id'], $total, $montant_recu, $monnaie, $mode, $statut, $linked_ord, $notes]
        );

        foreach ($lignes as [$mid, $qty, $pu, $rem, $tl]) {
            db_exec(
                "INSERT INTO caisse_lignes (vente_id, medicament_id, quantite, prix_unitaire, remise_pct, total_ligne) VALUES (?,?,?,?,?,?)",
                [$vente_id, $mid, $qty, $pu, $rem, $tl]
            );
            //  Décrémenter stock
            db_exec("UPDATE medicaments SET stock_actuel = GREATEST(0, stock_actuel - ?) WHERE id = ?", [$qty, $mid]);
            //  Recalculer statut stock
            db_exec("UPDATE medicaments SET statut = CASE
                WHEN stock_actuel <= 0                       THEN 'critique'
                WHEN stock_actuel <= stock_minimum * 0.5    THEN 'critique'
                WHEN stock_actuel <= stock_minimum          THEN 'bas'
                ELSE 'normal' END WHERE id = ?", [$mid]);
        }

        //  Marquer l'ordonnance comme terminée
        if ($linked_ord) {
            db_exec("UPDATE ordonnances SET statut='terminee' WHERE id=? AND statut='active'", [$linked_ord]);
            logActivity("Ordonnance #$linked_ord soldé via ticket $num", 'green', 'ordonnance', $linked_ord);
        }

        logActivity("Ticket créé  " . fmt_money($total), 'green', 'caisse', $vente_id);
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
        //  Restituer le stock
        $lignes = db_select("SELECT * FROM caisse_lignes WHERE vente_id=?", [$id]);
        foreach ($lignes as $l) {
            db_exec("UPDATE medicaments SET stock_actuel = stock_actuel + ? WHERE id=?",
                    [$l['quantite'], $l['medicament_id']]);
            db_exec("UPDATE medicaments SET statut = CASE
                WHEN stock_actuel <= stock_minimum * 0.5 THEN 'critique'
                WHEN stock_actuel <= stock_minimum       THEN 'bas'
                ELSE 'normal' END WHERE id=?", [$l['medicament_id']]);
        }
        //  Remettre l'ordonnance en active si elle existait
        if ($v['ordonnance_id']) {
            db_exec("UPDATE ordonnances SET statut='active' WHERE id=? AND statut='terminee'", [$v['ordonnance_id']]);
        }
        logActivity("Ticket {$v['numero_ticket']} annulé — stock restitué", 'red', 'caisse', $id);
    }
    header('Location: ' . APP_URL . '/caisse.php?ok=annule');
    exit;
}

//  PRÉREMPLISSAGE depuis pharmacie
//  Layout inclus ici  aprs toute logique PHP/redirects
require_once __DIR__ . '/../includes/layout.php';
requirePageAccess('caisse');
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

//  DONNÉES
$medicaments = db_select("SELECT id, nom, dosage, unite, prix_unitaire, stock_actuel FROM medicaments WHERE stock_actuel > 0 ORDER BY nom ASC");
$patients    = db_select("SELECT id, CONCAT(prenom,' ',nom) AS nom_complet, numero FROM patients ORDER BY nom ASC LIMIT 200");

// Historique   avec jointure ordonnance pour afficher le lien
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
            --  Lien ordonnance
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

// Stats
$ca_jour    = (float)db_scalar("SELECT COALESCE(SUM(montant_total),0) FROM caisse_ventes WHERE DATE(date_vente)=CURDATE() AND statut='paye'");
$nb_tickets = (int)db_scalar("SELECT COUNT(*) FROM caisse_ventes WHERE DATE(date_vente)=CURDATE()");
$nb_ouverts = (int)db_scalar("SELECT COUNT(*) FROM caisse_ventes WHERE statut='ouvert'");
$ca_mois    = (float)db_scalar("SELECT COALESCE(SUM(montant_total),0) FROM caisse_ventes WHERE MONTH(date_vente)=MONTH(CURDATE()) AND statut='paye'");
$nb_ord_att = (int)db_scalar("SELECT COUNT(*) FROM ordonnances WHERE statut='active'");

// Ticket  imprimer
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
            "SELECT cl.*, m.nom AS med_nom, m.dosage, m.unite
             FROM caisse_lignes cl
             JOIN medicaments m ON m.id = cl.medicament_id
             WHERE cl.vente_id = ?", [$ticket_print['id']]
        );
    }
}

$modeLabels  = ['especes'=>'Espèces','carte'=>'Carte bancaire','cheque'=>'Chèque','virement'=>'Virement','assurance'=>'Assurance','gratuit'=>'Gratuit'];
$statutBadge = ['ouvert'=>'badge-yellow','paye'=>'badge-green','annule'=>'badge-red','rembourse'=>'badge-blue'];
$statutLabel = ['ouvert'=>'⏳ En attente','paye'=>'✅ Payé','annule'=>'✕ Annulé','rembourse'=>'↩ Remboursé'];
?>

<?php if (!empty($flash)): ?><div class="alert alert-red alert-auto"> <?= h($flash) ?></div><?php endif; ?>
<?php if (get_str('ok') === 'annule'): ?><div class="alert alert-yellow alert-auto"> Ticket annulé — stock restituéé  ordonnance remise en attente.</div><?php endif; ?>

<div class="page-header-row">
  <div>
    <h2> Caisse Pharmacie</h2>
    <p>Point de vente  <?= date('d/m/Y') ?>  <a href="pharmacie.php?tab=ordonnances" style="color:var(--accent2)"> Retour pharmacie</a></p>
  </div>
  <div style="display:flex;gap:8px;align-items:center">
    <?php if ($nb_ord_att > 0): ?>
    <a href="pharmacie.php?tab=ordonnances" class="btn btn-ghost" style="border-color:var(--yellow);color:var(--yellow)">
       <?= $nb_ord_att ?> ordonnance<?= $nb_ord_att > 1 ? 's' : '' ?> en attente
    </a>
    <?php endif; ?>
    <button class="btn btn-blue" <?php if (can('caisse.create_vente')): ?>onclick="openModal('modal-vente')">+ Nouvelle vente</button><?php endif; ?>
  </div>
</div>

<!-- Stats -->
<div class="stats-grid mb-24">
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
    <div class="stat-label">En attente paiement</div>
  </div>
  <div class="stat-card purple">
    <div class="stat-icon purple">💳</div>
    <div class="stat-value"><?= fmt_money($ca_mois) ?></div>
    <div class="stat-label">CA ce mois</div>
  </div>
</div>

<!-- Bannire ordonnance préremplie -->
<?php if ($prefill_ord_data): ?>
<div class="alert alert-green mb-24" style="margin-bottom:16px">
   <strong>Ordonnance #<?= (int)$prefill_ordonnance ?> charge</strong>
   Patient : <strong><?= h($prefill_ord_data['patient_nom']) ?></strong>
   Mdecin : <?= h($prefill_ord_data['medecin_nom']) ?>
   <?= count($prefill_ord_lignes) ?> médicament(s)
   Total estim : <strong><?= fmt_money(array_sum(array_column($prefill_ord_lignes, 'prix_unitaire'))) ?></strong>
  <br><small style="opacity:.8">La caisse s'ouvre automatiquement avec les médicaments préremplis.</small>
</div>
<?php elseif ($prefill_med_data): ?>
<div class="alert alert-green mb-24" style="margin-bottom:16px">
   Médicament slectionn : <strong><?= h($prefill_med_data['nom']) ?> <?= h($prefill_med_data['dosage'] ?? '') ?></strong>
   Stock : <?= (int)$prefill_med_data['stock_actuel'] ?>  Prix : <?= fmt_money((float)$prefill_med_data['prix_unitaire']) ?>
</div>
<?php endif; ?>

<!-- Historique tickets -->
<div class="card mb-24">
  <div class="card-header">
    <h3> Historique des ventes</h3>
    <form method="GET" style="display:flex;gap:8px;flex-wrap:wrap">
      <input type="date" name="date_filter" value="<?= $show_all ? '' : h($date_tk) ?>"
             style="padding:6px 10px;background:var(--bg);border:1px solid var(--border2);border-radius:6px;color:var(--text);font-size:12px;outline:none"
             onchange="this.form.submit()">
      <a href="caisse.php?date_filter=all<?= $search_tk?'&search='.urlencode($search_tk):'' ?>" class="btn btn-sm <?= $show_all?'btn-blue':'btn-ghost' ?>">Tous</a>
      <input type="text" name="search" value="<?= h($search_tk) ?>"
             placeholder=" N° ticket, patient..."
             style="padding:6px 12px;background:var(--bg);border:1px solid var(--border2);border-radius:6px;color:var(--text);font-family:inherit;font-size:12px;outline:none;width:200px">
      <button type="submit" class="btn btn-sm btn-blue">Chercher</button>
      <?php if ($search_tk): ?><a href="caisse.php" class="btn btn-sm btn-ghost">✕</a><?php endif; ?>
    </form>
  </div>
  <table>
    <thead>
      <tr>
        <th>N° Ticket</th><th>Date</th><th>Patient</th><th>Ordonnance</th>
        <th>Total</th><th>Mode</th><th>Caissier</th><th>Statut</th><th>Actions</th>
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
          <!--  LIEN PHARMACIE  CAISSE visible dans l'historique -->
          <a href="pharmacie.php?tab=ordonnances" style="color:var(--accent2);font-size:12px;display:flex;align-items:center;gap:4px">
            <span></span> Ord. #<?= (int)$t['ord_id'] ?>
            <?php if ($t['ord_medecin']): ?><span style="color:var(--text3)">  <?= h($t['ord_medecin']) ?></span><?php endif; ?>
          </a>
          <?php else: ?>
          <span style="color:var(--text3);font-size:12px"> Vente directe </span>
          <?php endif; ?>
        </td>
        <td><strong style="color:var(--green)"><?= fmt_money((float)$t['montant_total']) ?></strong></td>
        <td style="font-size:12px"><?= h($modeLabels[$t['mode_paiement']] ?? $t['mode_paiement']) ?></td>
        <td style="font-size:12px;color:var(--text2)"><?= h($t['caissier_nom']) ?></td>
        <td><span class="badge <?= $statutBadge[$t['statut']] ?? 'badge-gray' ?>"><?= $statutLabel[$t['statut']] ?? $t['statut'] ?></span></td>
        <td style="display:flex;gap:4px">
          <a href="caisse.php?ticket=<?= (int)$t['id'] ?>" class="btn btn-sm btn-blue">🧾 Voir</a>
          <?php if ($t['statut'] !== 'annule'): ?>
          <a href="caisse.php?action=annuler&id=<?= (int)$t['id'] ?>"
             class="btn btn-sm btn-red"
             onclick="return confirm('Annulér ce ticket ?\nLe stock sera restitu<?= $t['ord_id'] ? ' et l\'ordonnance remise en attente.' : '.' ?>')"></a>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($tickets)): ?>
      <tr><td colspan="9" style="text-align:center;padding:32px;color:var(--text3)">Aucun ticket</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<!--  MODAL NOUVELLE VENTE  -->
<div id="modal-vente" class="modal-overlay" style="display:none;z-index:200;align-items:flex-start;justify-content:center;padding:20px;overflow-y:auto"
     role="dialog" aria-modal="true" onclick="if(event.target===this)closeModal('modal-vente')">
  <div style="background:var(--surface);border:1px solid var(--border2);border-radius:16px;width:min(720px,95vw);box-shadow:0 24px 60px rgba(0,0,0,.7);margin:auto">

    <div style="padding:18px 24px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;background:var(--surface);border-radius:16px 16px 0 0;z-index:1">
      <h3> Nouvelle vente  Caisse pharmacie</h3>
      <button type="button" class="modal-close" onclick="closeModal('modal-vente')" aria-label="Fermer" style="width:28px;height:28px;background:var(--surface2);border-radius:6px;display:flex;align-items:center;justify-content:center;color:var(--text2)"></button>
    </div>

    <form method="POST" id="form-vente" style="padding:24px">
      <input type="hidden" name="action" value="create_vente">
      <!--  Transmet l'ID d'ordonnance si vient de pharmacie -->
      <input type="hidden" name="ordonnance_id" value="<?= (int)($prefill_ordonnance ?? 0) ?>">
      <?= csrf_field() ?>

      <div class="form-group" style="margin-bottom:16px">
        <label for="sel-patient">Patient <span style="color:var(--text3);font-size:10px">(optionnel)</span></label>
        <select name="patient_id" id="sel-patient" style="width:100%;padding:9px 12px;background:var(--bg);border:1px solid var(--border2);border-radius:7px;color:var(--text);font-family:inherit;font-size:13px;outline:none">
          <option value=""> Vente anonyme </option>
          <?php foreach ($patients as $p): ?>
          <option value="<?= (int)$p['id'] ?>"><?= h($p['nom_complet']) ?> (<?= h($p['numero']) ?>)</option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- Articles -->
      <div style="margin-bottom:16px">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px">
          <label style="font-size:11px;font-weight:600;color:var(--text2);text-transform:uppercase;letter-spacing:.04em">Articles</label>
          <button type="button" class="btn btn-sm btn-blue" onclick="addLigne()">+ Article</button>
        </div>
        <div style="display:grid;grid-template-columns:1fr 70px 90px 70px 90px 28px;gap:6px;margin-bottom:6px;padding:0 2px">
          <span style="font-size:10px;color:var(--text3);font-weight:600;text-transform:uppercase">Médicament</span>
          <span style="font-size:10px;color:var(--text3);font-weight:600;text-transform:uppercase">Qt</span>
          <span style="font-size:10px;color:var(--text3);font-weight:600;text-transform:uppercase">Prix unit.</span>
          <span style="font-size:10px;color:var(--text3);font-weight:600;text-transform:uppercase">Remise%</span>
          <span style="font-size:10px;color:var(--text3);font-weight:600;text-transform:uppercase">Total</span>
          <span></span>
        </div>
        <div id="lignes-container"></div>
        <div style="display:flex;justify-content:flex-end;margin-top:12px;padding-top:12px;border-top:2px solid var(--border2)">
          <div style="background:var(--surface2);border-radius:8px;padding:12px 20px;text-align:right">
            <div style="font-size:11px;color:var(--text3);margin-bottom:4px">TOTAL À PAYER</div>
            <div id="total-display" style="font-size:28px;font-weight:700;color:var(--green)"></div>
          </div>
        </div>
      </div>

      <!-- Paiement -->
      <div style="background:var(--surface2);border-radius:10px;padding:16px;margin-bottom:16px">
        <div style="font-size:11px;font-weight:600;color:var(--text2);text-transform:uppercase;letter-spacing:.04em;margin-bottom:12px"> Paiement</div>
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px">
          <div class="form-group">
            <label for="sel-mode">Mode de paiement</label>
            <select name="mode_paiement" id="sel-mode" onchange="updateMonnaie()">
              <option value="especes"> Espèces</option>
              <option value="carte"> Carte bancaire</option>
              <option value="cheque"> Chèque</option>
              <option value="virement"> Virement</option>
              <option value="assurance"> Assurance</option>
              <option value="gratuit"> Gratuit</option>
            </select>
          </div>
          <div class="form-group" id="zone-recu">
            <label for="inp-recu">Montant reçu (<?= h($s['currency_symbol'] ?? '') ?>)</label>
            <input type="number" name="montant_recu" id="inp-recu" step="0.01" min="0" value="0"
                   oninput="updateMonnaie()" style="font-size:16px;font-weight:700;text-align:center">
          </div>
          <div class="form-group">
            <label>Monnaie à rendre</label>
            <div id="monnaie-display" style="padding:9px 12px;background:var(--bg);border:1px solid var(--border2);border-radius:7px;font-size:16px;font-weight:700;color:var(--yellow);text-align:center"></div>
          </div>
        </div>
      </div>

      <div class="form-group" style="margin-bottom:20px">
        <label for="caisse-notes">Notes</label>
        <input type="text" name="notes" id="caisse-notes" maxlength="255" placeholder="ex: Ordonnance fournie / Remise accordée...">
      </div>

      <div style="display:flex;gap:10px;justify-content:flex-end;padding-top:16px;border-top:1px solid var(--border)">
        <button type="button" class="btn btn-ghost" onclick="closeModal('modal-vente')">Annulér</button>
        <button type="submit" id="btn-valider" class="btn btn-blue" disabled style="padding:10px 24px;font-size:14px"> Valider & Générer ticket</button>
      </div>
    </form>
  </div>
</div>

<!--  MODAL TICKET  -->
<?php if ($ticket_print): ?>
<div id="modal-ticket" class="modal-overlay" style="z-index:300;display:flex;align-items:center;justify-content:center"
     role="dialog" aria-modal="true" onclick="if(event.target===this)this.style.display='none'">
  <div style="background:#fff;border-radius:12px;width:min(380px,95vw);max-height:90vh;overflow-y:auto;box-shadow:0 24px 60px rgba(0,0,0,.7)">
    <div style="background:var(--surface);border-radius:12px 12px 0 0;padding:12px 16px;display:flex;gap:8px;justify-content:flex-end">
      <button class="btn btn-blue btn-sm" onclick="printTicket()">🖨 Imprimer</button>
      <a href="pharmacie.php?tab=ordonnances" class="btn btn-ghost btn-sm"> Pharmacie</a>
      <button class="btn btn-ghost btn-sm" onclick="document.getElementById('modal-ticket').style.display='none'">✕ Fermer</button>
    </div>

    <div id="ticket-content" style="font-family:'Courier New',monospace;color:#000;padding:20px;font-size:12px;line-height:1.7">

      <!-- En-tête -->
      <div style="text-align:center;border-bottom:2px dashed #000;padding-bottom:12px;margin-bottom:12px">
        <div style="font-size:16px;font-weight:bold"> <?= h($s['etablissement'] ?? 'MediCore ERP') ?></div>
        <div style="font-size:11px">Pharmacie Hospitalière</div>
      </div>

      <!-- Infos ticket -->
      <div style="border-bottom:1px dashed #aaa;padding-bottom:10px;margin-bottom:10px">
        <?php
        $infos = [
            'Ticket'   => $ticket_print['numero_ticket'],
            'Date'     => fmt_date($ticket_print['date_vente'], true),
            'Caissier' => $ticket_print['caissier_nom'],
        ];
        if ($ticket_print['patient_nom'])  $infos['Patient'] = $ticket_print['patient_nom'];
        if ($ticket_print['patient_num'])  $infos['Dossier'] = $ticket_print['patient_num'];
        //  Afficher la référence ordonnance sur le ticket
        if ($ticket_print['ord_id'])       $infos['Ordonnance'] = '#' . $ticket_print['ord_id'] . '  ' . ($ticket_print['ord_medecin'] ?? '');
        foreach ($infos as $k => $v):
        ?>
        <div style="display:flex;justify-content:space-between;font-size:11px">
          <span><strong><?= $k ?> :</strong></span><span><?= h($v) ?></span>
        </div>
        <?php endforeach; ?>
      </div>

      <!-- Articles -->
      <div style="border-bottom:1px dashed #aaa;padding-bottom:10px;margin-bottom:10px">
        <div style="font-weight:bold;margin-bottom:8px;text-decoration:underline">ARTICLES</div>
        <?php foreach ($lignes_print as $l): ?>
        <div style="margin-bottom:6px">
          <div style="font-weight:bold"><?= h($l['med_nom']) ?> <?= h($l['dosage'] ?? '') ?></div>
          <div style="display:flex;justify-content:space-between;font-size:11px;color:#555">
            <span><?= (int)$l['quantite'] ?> x <?= fmt_money((float)$l['prix_unitaire']) ?><?= $l['remise_pct'] > 0 ? ' (-' . (float)$l['remise_pct'] . '%)' : '' ?></span>
            <strong style="color:#000"><?= fmt_money((float)$l['total_ligne']) ?></strong>
          </div>
        </div>
        <?php endforeach; ?>
      </div>

      <!-- Totaux -->
      <div style="border-bottom:2px dashed #000;padding-bottom:12px;margin-bottom:12px">
        <div style="display:flex;justify-content:space-between;font-size:15px;font-weight:bold">
          <span>TOTAL</span><span><?= fmt_money((float)$ticket_print['montant_total']) ?></span>
        </div>
        <div style="display:flex;justify-content:space-between;font-size:12px;color:#444;margin-top:4px">
          <span>Mode</span><span><?= h($modeLabels[$ticket_print['mode_paiement']] ?? '') ?></span>
        </div>
        <?php if ($ticket_print['mode_paiement'] === 'especes' && $ticket_print['montant_recu'] > 0): ?>
        <div style="display:flex;justify-content:space-between;font-size:12px;color:#444">
          <span>Reu</span><span><?= fmt_money((float)$ticket_print['montant_recu']) ?></span>
        </div>
        <div style="display:flex;justify-content:space-between;font-size:13px;font-weight:bold">
          <span>Monnaie</span><span><?= fmt_money((float)$ticket_print['monnaie_rendue']) ?></span>
        </div>
        <?php endif; ?>
      </div>

      <?php if ($ticket_print['notes']): ?>
      <div style="font-size:11px;color:#555;margin-bottom:10px"> <?= h($ticket_print['notes']) ?></div>
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
const MEDS = <?= json_encode(array_map(fn($m) => [
    'id'    => (int)$m['id'],
    'nom'   => $m['nom'] . ($m['dosage'] ? ' ' . $m['dosage'] : ''),
    'pu'    => (float)$m['prix_unitaire'],
    'stock' => (int)$m['stock_actuel'],
], $medicaments)) ?>;

const SYM = <?= json_encode($s['currency_symbol'] ?? '') ?>;
const POS = <?= json_encode($s['currency_position'] ?? 'after') ?>;
const DS  = <?= json_encode($s['currency_dec_sep'] ?? ',') ?>;
const TS  = <?= json_encode($s['currency_thou_sep'] ?? ' ') ?>;
const DEC = <?= json_encode((int)($s['currency_decimals'] ?? 2)) ?>;

function fmt(n) {
    let [i, d] = Math.abs(n).toFixed(DEC).split('.');
    if (TS) i = i.replace(/\B(?=(\d{3})+(?!\d))/g, TS);
    let s = DEC > 0 ? i + DS + d : i;
    return POS === 'before' ? SYM + s : s + SYM;
}

function openModal(id)  { document.getElementById(id).style.display = 'flex'; }
function closeModal(id) { document.getElementById(id).style.display = 'none'; }

let lineCount = 0;

function addLigne(prefillId = null, prefillQty = 1) {
    const container = document.getElementById('lignes-container');
    const idx = lineCount++;

    let opts = '<option value=""> Choisir </option>';
    MEDS.forEach(m => {
        opts += `<option value="${m.id}" data-pu="${m.pu}" data-stock="${m.stock}" ${prefillId === m.id ? 'selected' : ''}>${m.nom} (stock: ${m.stock})</option>`;
    });

    const div = document.createElement('div');
    div.id = 'ligne-' + idx;
    div.style.cssText = 'display:grid;grid-template-columns:1fr 70px 90px 70px 90px 28px;gap:6px;margin-bottom:6px;align-items:center';

    const fieldStyle = 'padding:8px;background:var(--bg);border:1px solid var(--border2);border-radius:6px;color:var(--text);font-size:12px;outline:none;text-align:center';

    div.innerHTML = `
      <select name="med_id[]" onchange="onMedChange(${idx})" style="padding:8px;background:var(--bg);border:1px solid var(--border2);border-radius:6px;color:var(--text);font-size:12px;outline:none">${opts}</select>
      <input type="number" name="quantite[]" id="qty-${idx}" value="${prefillQty}" min="1" max="9999" oninput="calcLigne(${idx})" style="${fieldStyle}">
      <input type="text" id="pu-${idx}" readonly style="${fieldStyle};background:var(--surface2);border-color:var(--border);color:var(--text2)">
      <input type="number" name="remise[]" id="rem-${idx}" value="0" min="0" max="100" step="0.5" oninput="calcLigne(${idx})" style="${fieldStyle}">
      <input type="text" id="tl-${idx}" readonly style="${fieldStyle};background:var(--surface2);border-color:var(--border);color:var(--green);font-weight:700">
      <button type="button" onclick="removeLigne(${idx})" style="width:44px;height:44px;background:rgba(var(--red-rgb),.15);border:none;border-radius:6px;color:var(--red);cursor:pointer;font-size:14px;display:flex;align-items:center;justify-content:center"></button>
    `;
    container.appendChild(div);

    // Si prérempli, déclencher le calcul
    if (prefillId) {
        setTimeout(() => {
            const sel = div.querySelector('select');
            if (sel) { sel.dispatchEvent(new Event('change')); }
        }, 50);
    }
    recalcTotal();
}

function onMedChange(idx) {
    const sel = document.querySelector(`#ligne-${idx} select`);
    const opt = sel.options[sel.selectedIndex];
    const pu  = parseFloat(opt.getAttribute('data-pu') || 0);
    document.getElementById('pu-' + idx).value = fmt(pu);
    document.getElementById('qty-' + idx).max  = opt.getAttribute('data-stock') || 9999;
    calcLigne(idx);
}

function calcLigne(idx) {
    const sel = document.querySelector(`#ligne-${idx} select`);
    if (!sel) return;
    const opt = sel.options[sel.selectedIndex];
    const pu  = parseFloat(opt.getAttribute('data-pu') || 0);
    const qty = parseInt(document.getElementById('qty-' + idx).value) || 1;
    const rem = parseFloat(document.getElementById('rem-' + idx).value) || 0;
    const tl  = pu * qty * (1 - rem / 100);
    document.getElementById('tl-' + idx).value = fmt(tl);
    recalcTotal();
}

function removeLigne(idx) {
    const el = document.getElementById('ligne-' + idx);
    if (el) el.remove();
    recalcTotal();
}

function getTotal() {
    let total = 0;
    document.querySelectorAll('[id^="tl-"]').forEach(el => {
        const raw = el.value.replace(/[^\d.,-]/g, '').replace(',', '.');
        total += parseFloat(raw) || 0;
    });
    return Math.round(total * 100) / 100;
}

function recalcTotal() {
    const total = getTotal();
    document.getElementById('total-display').textContent = total > 0 ? fmt(total) : '';
    document.getElementById('btn-valider').disabled = total <= 0;
    updateMonnaie();
}

function updateMonnaie() {
    const mode = document.getElementById('sel-mode').value;
    const zone = document.getElementById('zone-recu');
    const recu = parseFloat(document.getElementById('inp-recu').value) || 0;
    const total = getTotal();

    if (mode === 'especes') {
        zone.style.display = '';
        const monnaie = Math.max(0, recu - total);
        const el = document.getElementById('monnaie-display');
        el.textContent = fmt(monnaie);
        el.style.color = monnaie >= 0 ? 'var(--yellow)' : 'var(--red)';
    } else {
        zone.style.display = 'none';
        document.getElementById('monnaie-display').textContent = '';
    }
}

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

//  Préremplissage depuis pharmacie
<?php if ($prefill_ord_data && !empty($prefill_ord_lignes)): ?>
const PREFILL = {
    patient_id: <?= (int)$prefill_ord_data['patient_id_val'] ?>,
    lignes: <?= json_encode(array_map(fn($l) => [
        'med_id' => (int)$l['medicament_id'],
        'qty'    => 1,
    ], $prefill_ord_lignes)) ?>
};
<?php elseif ($prefill_med_data): ?>
const PREFILL = { med_id: <?= (int)$prefill_med_data['id'] ?>, qty: 1 };
<?php else: ?>
const PREFILL = null;
<?php endif; ?>

document.addEventListener('DOMContentLoaded', () => {
    if (PREFILL && PREFILL.lignes) {
        // Ordonnance complte
        openModal('modal-vente');
        const selPat = document.getElementById('sel-patient');
        if (selPat && PREFILL.patient_id) selPat.value = PREFILL.patient_id;
        PREFILL.lignes.forEach(l => addLigne(l.med_id, l.qty));
    } else if (PREFILL && PREFILL.med_id) {
        // Médicament unique
        openModal('modal-vente');
        addLigne(PREFILL.med_id, PREFILL.qty);
    } else {
        addLigne(); // ligne vide par défaut
    }

    // Auto-ouvrir ticket si redirect après création
    <?php if (get_int('ticket') > 0 && isset($_GET['print'])): ?>
    const modalTicket = document.getElementById('modal-ticket');
    if (modalTicket) { modalTicket.style.display = 'flex'; setTimeout(printTicket, 700); }
    <?php endif; ?>
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php';
