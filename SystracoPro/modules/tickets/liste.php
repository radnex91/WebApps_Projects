<?php
// modules/tickets/liste.php
require_once '../../includes/config.php';
requireLogin(); requirePerm('tickets.view');
requireCaisseOuverte();
$pageTitle = 'Liste des Tickets';
$aid = getUserAgenceId();

$search = trim($_GET['q'] ?? ''); $statut = $_GET['statut'] ?? ''; $mode = $_GET['mode'] ?? '';
$date_d = $_GET['date_d'] ?? ''; $date_f = $_GET['date_f'] ?? '';
$page = max(1,(int)($_GET['page']??1)); $perPage=30;

$where=['1=1']; $params=[];
if ($aid) { $where[]="t.agence_id=?"; $params[]=$aid; }
$where[]="t.bordereau_id IS NULL";
if ($search) { $where[]="(t.numero LIKE ? OR t.passager_nom LIKE ? OR t.passager_tel LIKE ?)"; $params=array_merge($params,["%$search%","%$search%","%$search%"]); }
if ($statut) { $where[]="t.statut=?"; $params[]=$statut; }
if ($mode)   { $where[]="t.mode_paiement=?"; $params[]=$mode; }
if ($date_d) { $where[]="DATE(t.date_vente)>=?"; $params[]=$date_d; }
if ($date_f) { $where[]="DATE(t.date_vente)<=?"; $params[]=$date_f; }
$ws=implode(' AND ',$where);
$total=$pdo->prepare("SELECT COUNT(*) FROM tickets t WHERE $ws"); $total->execute($params);
$totalRows=$total->fetchColumn(); $totalPages=ceil($totalRows/$perPage);
$stmt=$pdo->prepare("SELECT t.*,IFNULL(ad.ville,a1.ville) as dep,IFNULL(aa.ville,a2.ville) as arr,v.numero as voy_num,v.date_depart,CONCAT(u.prenom,' ',u.nom) as guichetier,brd.numero as brd_num FROM tickets t LEFT JOIN voyages v ON t.voyage_id=v.id LEFT JOIN itineraires i ON v.itineraire_id=i.id LEFT JOIN destinations d ON v.destination_id=d.id LEFT JOIN agences a1 ON IFNULL(i.agence_depart,d.agence_depart)=a1.id LEFT JOIN agences a2 ON IFNULL(i.agence_arrivee,d.agence_arrivee)=a2.id LEFT JOIN agences ad ON t.agence_depart_id=ad.id LEFT JOIN agences aa ON t.agence_arrivee_id=aa.id LEFT JOIN utilisateurs u ON t.guichetier_id=u.id LEFT JOIN bordereaux brd ON t.bordereau_id=brd.id WHERE $ws ORDER BY t.date_vente DESC LIMIT $perPage OFFSET ".(($page-1)*$perPage));
$stmt->execute($params); $tickets=$stmt->fetchAll();

$tot=$pdo->prepare("SELECT SUM(montant_total) as total,COUNT(*) as nb FROM tickets t WHERE $ws AND t.statut='vendu'"); $tot->execute($params); $totals=$tot->fetch();

include '../../includes/header.php';
?>
<div class="breadcrumb"><a href="<?= BASE_URL ?>"><i class="fas fa-home"></i></a><span class="breadcrumb-sep">/</span>Tickets</div>

<div class="card" style="margin-bottom:16px;">
  <div class="card-body" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;">
    <div style="display:flex;gap:16px;font-size:13px;">
      <span>Total : <strong><?= $totalRows ?> ticket(s)</strong></span>
      <span style="color:var(--success);">Recette : <strong><?= number_format($totals['total']??0,0,',',' ') ?> FCFA</strong></span>
    </div>
    <?php if(can('tickets.create')): ?>
    <div style="display:flex;gap:6px;">
      <button onclick="openModal('modal-vente-libre');resetVenteLibre()" class="btn btn-warning btn-sm"><i class="fas fa-ticket-alt"></i> Vente libre</button>
      <button onclick="openModal('modal-vente-rattachee');resetVenteAssociee()" class="btn btn-success btn-sm"><i class="fas fa-bus"></i> Vente rattachée</button>
    </div>
    <?php endif; ?>
  </div>
</div>

<div class="card">
  <div class="card-header"><h3><i class="fas fa-list"></i> Tickets (<?= $totalRows ?>)</h3></div>
  <div class="card-body" style="padding:0;">
    <div class="table-wrap">
    <table data-no-filter>
      <thead><tr><th>Numéro</th><th>Bordereau</th><th>Passager</th><th>Trajet</th><th>Classe</th><th>Montant</th><th>Mode</th><th>Guichetier</th><th>Date/Heure</th><th>Statut</th></tr></thead>
      <tbody>
        <?php foreach($tickets as $t): ?>
        <tr>
          <td><code style="font-size:11px;"><?= sanitize($t['numero']) ?></code><?= empty($t['voyage_id']) ? '<br><span class="badge badge-amber" style="font-size:9px;">Libre</span>' : '' ?></td>
          <td style="font-size:11px;"><?= $t['brd_num'] ? '<a href="'.BASE_URL.'modules/bordereaux/voir.php?id='.$t['bordereau_id'].'" title="Voir le bordereau"><code>'.sanitize($t['brd_num']).'</code></a>' : '—' ?></td>
          <td><strong><?= sanitize($t['passager_nom']) ?></strong><br><span style="font-size:11px;color:var(--text3);"><?= sanitize($t['passager_tel']??'') ?></span></td>
          <td><?= sanitize($t['dep']??'—') ?> → <?= sanitize($t['arr']??'—') ?></td>
          <td><span class="tag-statut <?= $t['classe']==='vip'?'badge-purple':'badge-blue' ?>"><?= strtoupper($t['classe']) ?></span></td>
          <td style="font-weight:700;color:<?= $t['statut']==='vendu'?'var(--success)':'var(--text2)' ?>;"><?= number_format($t['montant_total'],0,',',' ') ?></td>
          <td style="font-size:11px;"><?= ['especes'=>'💵','om'=>'📱OM','momo'=>'📱MOMO','carte'=>'💳'][$t['mode_paiement']]??$t['mode_paiement'] ?></td>
          <td style="font-size:12px;"><?= sanitize($t['guichetier']??'—') ?></td>
          <td style="font-size:11px;"><?= date('d/m H:i',strtotime($t['date_vente'])) ?></td>
          <td><span class="tag-statut st-<?= $t['statut'] ?>"><?= statutLabel($t['statut']) ?></span></td>
        </tr>
        <?php endforeach; ?>
        <?php if(empty($tickets)): ?><tr><td colspan="10" class="t-empty"><i class="fas fa-ticket-alt"></i>Aucun ticket trouvé</td></tr><?php endif; ?>
      </tbody>
    </table>
    </div>
    <?php if($totalPages>1): ?><div class="pagination"><?php for($i=1;$i<=$totalPages;$i++): ?><a href="?page=<?= $i ?>&q=<?= urlencode($search) ?>&statut=<?= urlencode($statut) ?>&date_d=<?= $date_d ?>&date_f=<?= $date_f ?>" class="page-btn <?= $i==$page?'active':'' ?>"><?= $i ?></a><?php endfor; ?></div><?php endif; ?>
  </div>
</div>

<!-- ═══════════════════════════════════════════════════════════ -->
<!-- MODAL VENTE RATTACHÉE                                      -->
<!-- ═══════════════════════════════════════════════════════════ -->
<div class="modal-over" id="modal-vente-rattachee">
  <div class="modal modal-lg">
    <div class="modal-head"><h3><i class="fas fa-bus"></i> Vente rattachée à un voyage</h3><button class="modal-x" onclick="closeModal('modal-vente-rattachee')">✕</button></div>
    <div class="modal-body">
      <!-- Step 1 -->
      <div id="va-step1">
        <div class="fsec">
          <div class="fsec-t"><i class="fas fa-tags"></i> Sélectionnez la classe</div>
          <div class="form-grid">
            <div class="fg full"><label class="flbl">Classe <span class="freq">*</span></label>
              <div id="va-class-btns" style="display:flex;gap:8px;">
                <?php foreach(getTicketClasses() as $c): $code=$c['code']; $label=sanitize($c['nom']); ?>
                <button type="button" class="class-btn" data-class="<?= htmlspecialchars($code) ?>" onclick="selectClassAssociee(this)" style="flex:1;padding:10px 12px;border:2px solid var(--border);border-radius:var(--radius);background:var(--card);cursor:pointer;font-size:13px;font-weight:600;text-align:center;transition:all .15s;"><?= $label ?></button>
                <?php endforeach; ?>
              </div>
            </div>
          </div>
        </div>
        <div id="va-voyage-section" style="display:none;margin-top:16px;">
          <div class="fsec">
            <div class="fsec-t"><i class="fas fa-route"></i> Sélection du voyage</div>
            <div class="form-grid">
              <div class="fg full"><label class="flbl">Voyage disponible <span class="freq">*</span></label><select id="va-voyage" class="fc" onchange="loadVoyageDetailAssociee(this.value)"><option value="">— Sélectionner un voyage —</option></select></div>
            </div>
          </div>
          <div id="va-voyage-info" style="display:none;margin-top:14px;"></div>
        </div>
      </div>

      <!-- Step 2 -->
      <div id="va-step2" style="display:none;">
        <form id="va-form" onsubmit="submitVenteAssociee(event)">
          <?= csrfField() ?>
          <input type="hidden" name="voyage_id" id="va-voyage-id">
          <input type="hidden" name="vendre" value="1">
          <input type="hidden" name="vente_libre" value="0">

          <div class="fsec">
            <div class="fsec-t"><i class="fas fa-calendar-alt"></i> Date et heure</div>
            <div class="form-grid">
              <div class="fg" style="max-width:180px;"><label class="flbl">Date <span class="freq">*</span></label><input type="date" name="date_heure" id="va-date-heure" class="fc" required></div>
              <div class="fg"><label class="flbl">Montée à <span class="freq">*</span></label><select name="escale_montee_id" id="va-escale-montee" class="fc" required onchange="onEscaleChangeAssociee()"></select></div>
              <div class="fg"><label class="flbl">Descente à <span class="freq">*</span></label><select name="escale_descente_id" id="va-escale-descente" class="fc" required onchange="onEscaleChangeAssociee()"></select></div>
            </div>
            <div id="va-escale-visual" style="margin-top:8px;"></div>
          </div>

          <div class="fsec">
            <div class="fsec-t"><i class="fas fa-tags"></i> Tarif & Paiement</div>
            <div class="form-grid">
              <input type="hidden" name="tarif_id" id="va-tarif-sel" value="">
              <input type="hidden" name="classe" id="va-classe" value="">
              <div class="fg"><label class="flbl">Tarif ticket (FCFA)</label><input type="number" name="montant" id="va-montant" class="fc" min="0" step="100" readonly style="background:var(--bg);font-weight:700;font-size:18px;"></div>
              <div class="fg"><label class="flbl">Siège N°</label><input type="text" name="siege" id="va-siege" class="fc" placeholder="A1, B2…" maxlength="5"></div>
              <div class="fg"><label class="flbl">Mode de paiement <span class="freq">*</span></label><select name="mode_paiement" id="va-mode" class="fc" required><option value="especes">💵 Espèces</option><option value="om">📱 Orange Money</option><option value="momo">📱 MTN MoMo</option></select></div>
              <div class="fg"><label class="flbl">Somme perçue (FCFA) <span class="freq">*</span></label><input type="number" name="somme_percu" id="va-somme-percu" class="fc" min="0" step="100" required oninput="calcReliquatAssociee()"></div>
              <div class="fg"><label class="flbl">Reliquat (FCFA)</label><input type="text" id="va-reliquat-display" class="fc" value="0" readonly style="background:var(--bg);font-weight:700;color:var(--success);"></div>
            </div>
          </div>

          <div class="fsec">
            <div class="fsec-t"><i class="fas fa-user"></i> Passager</div>
            <div class="form-grid">
              <div class="fg"><label class="flbl">Type passager <span class="freq">*</span></label><select name="type_passager" id="va-type-passager" class="fc" required><option value="adulte">Adulte</option><option value="enfant">Enfant</option></select></div>
              <div class="fg"><label class="flbl">N° Téléphone <span class="freq">*</span></label><input type="tel" name="passager_tel" id="va-tel" class="fc" placeholder="+237 6XX XXX XXX" required oninput="lookupPassagerAssociee()"></div>
              <div class="fg"><label class="flbl">Nom & Prénom <span class="freq">*</span></label><input type="text" name="passager_nom" id="va-nom" class="fc" placeholder="NOM Prénom" required style="text-transform:uppercase;"></div>
              <div class="fg"><label class="flbl">N° CNI / Passeport <span class="freq">*</span></label><input type="text" name="passager_cni" id="va-cni" class="fc" required></div>
            </div>
            <div id="va-passager-suggest" style="display:none;margin-top:6px;position:relative;"></div>
          </div>

          <div class="fsec">
            <div class="fsec-t"><i class="fas fa-suitcase"></i> Bagages (optionnel)</div>
            <div class="form-grid-3">
              <div class="fg"><label class="flbl">Poids (kg)</label><input type="number" name="bagages_kg" id="va-bag-kg" class="fc" min="0" step="0.5" placeholder="0" oninput="calcTotalAssociee()"></div>
              <div class="fg"><label class="flbl">Montant bagages (FCFA)</label><input type="number" name="bagages_montant" id="va-bag-m" class="fc" min="0" step="100" placeholder="0" oninput="calcTotalAssociee()"></div>
            </div>
          </div>

          <div class="fsec">
            <div class="fsec-t"><i class="fas fa-comment-alt"></i> Observation</div>
            <div class="form-grid">
              <div class="fg full"><label class="flbl">Observation</label><textarea name="observation" id="va-observation" class="fc" rows="2" placeholder="Remarques éventuelles..."></textarea></div>
            </div>
          </div>

          <div class="fsec">
            <div class="fsec-t"><i class="fas fa-exchange-alt"></i> Correspondance (transit)</div>
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px;">
              <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:13px;"><input type="checkbox" name="transit" id="va-transit-cb" onchange="toggleTransitAssociee(this)"> Ce passager continue vers une autre destination</label>
            </div>
            <div id="va-transit-section" style="display:none;">
              <div class="form-grid"><div class="fg full"><label class="flbl">Itinéraire de correspondance</label><select name="itineraire_suite_id" id="va-corr-sel" class="fc"><option value="">— Aucune correspondance définie —</option></select></div></div>
              <div id="va-corr-info" style="display:none;margin-top:8px;padding:8px;background:var(--info-bg);border:1px solid var(--info);border-radius:var(--radius);font-size:12px;color:#164e63;"></div>
            </div>
          </div>

          <div style="display:flex;gap:10px;margin-top:16px;">
            <button type="button" onclick="resetVenteAssocieeStep()" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Changer de voyage</button>
            <button type="submit" id="va-submit-btn" class="btn btn-success btn-lg"><i class="fas fa-check-circle"></i> Confirmer la vente</button>
          </div>
        </form>

        <!-- Succès vente rattachée -->
        <div id="va-success" style="display:none;text-align:center;padding:40px 20px;">
          <div style="font-size:52px;color:var(--success);margin-bottom:12px;"><i class="fas fa-check-circle"></i></div>
          <h2 style="margin-bottom:6px;">Ticket vendu !</h2>
          <p style="color:var(--text2);margin-bottom:4px;">N° <strong id="va-success-num" style="font-family:'JetBrains Mono',monospace;font-size:18px;color:var(--primary);"></strong></p>
          <p style="color:var(--text2);margin-bottom:4px;" id="va-success-troncon"></p>
          <p style="color:var(--text2);margin-bottom:20px;">Montant : <strong id="va-success-montant"></strong></p>
          <div style="display:flex;gap:10px;justify-content:center;">
            <a id="va-print-link" href="#" target="_blank" class="btn btn-primary"><i class="fas fa-print"></i> Imprimer</a>
            <button onclick="newVenteAssociee()" class="btn btn-success"><i class="fas fa-plus"></i> Nouvelle vente</button>
            <button onclick="closeModal('modal-vente-rattachee');location.reload();" class="btn btn-secondary">Fermer</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ═══════════════════════════════════════════════════════════ -->
<!-- MODAL VENTE LIBRE                                          -->
<!-- ═══════════════════════════════════════════════════════════ -->
<div class="modal-over" id="modal-vente-libre">
  <div class="modal modal-lg">
    <div class="modal-head"><h3><i class="fas fa-ticket-alt"></i> Vente libre (sans voyage)</h3><button class="modal-x" onclick="closeModal('modal-vente-libre')">✕</button></div>
    <div class="modal-body">
      <form id="vl-form" onsubmit="submitVenteLibre(event)">
        <?= csrfField() ?>
        <input type="hidden" name="vendre" value="1">
        <input type="hidden" name="vente_libre" value="1">
        <input type="hidden" name="voyage_id" value="0">
        <input type="hidden" name="classe" id="vl-classe-hidden" value="">

        <div class="fsec">
          <div class="fsec-t"><i class="fas fa-calendar-alt"></i> Date et heure</div>
          <div class="form-grid">
            <div class="fg" style="max-width:180px;"><label class="flbl">Date <span class="freq">*</span></label><input type="date" name="date_heure" id="vl-date-heure" class="fc" required></div>
            <div class="fg"><label class="flbl">Classe <span class="freq">*</span></label>
              <div id="vl-class-btns" style="display:flex;gap:8px;">
                <?php foreach(getTicketClasses() as $c): $code=$c['code']; $label=sanitize($c['nom']); ?>
                <button type="button" class="class-btn" data-class="<?= htmlspecialchars($code) ?>" onclick="selectClassLibre(this)" style="flex:1;padding:10px 12px;border:2px solid var(--border);border-radius:var(--radius);background:var(--card);cursor:pointer;font-size:13px;font-weight:600;text-align:center;transition:all .15s;"><?= $label ?></button>
                <?php endforeach; ?>
              </div>
            </div>
          </div>
        </div>

        <div class="fsec">
          <div class="fsec-t"><i class="fas fa-map-marker-alt"></i> Trajet & Tarif</div>
          <div class="form-grid">
            <div class="fg"><label class="flbl">Destination <span class="freq">*</span></label><select name="destination_id" id="vl-destination" class="fc" required onchange="onDestLibreChange()"><option value="">— Choisir d'abord une classe —</option></select></div>
            <input type="hidden" name="tarif_id" id="vl-tarif" value="">
            <div class="fg"><label class="flbl">Tarif ticket (FCFA)</label><input type="number" name="montant" id="vl-montant" class="fc" min="0" step="100" readonly style="background:var(--bg);font-weight:700;font-size:18px;"></div>
          </div>
          <div id="vl-trajet-visual" style="margin-top:8px;font-size:12px;color:var(--text2);"></div>
        </div>

        <div class="fsec">
          <div class="fsec-t"><i class="fas fa-coins"></i> Paiement</div>
          <div class="form-grid">
            <div class="fg"><label class="flbl">Mode de paiement <span class="freq">*</span></label><select name="mode_paiement" id="vl-mode" class="fc" required><option value="especes">💵 Espèces</option><option value="om">📱 Orange Money</option><option value="momo">📱 MTN MoMo</option></select></div>
            <div class="fg"><label class="flbl">Somme perçue (FCFA) <span class="freq">*</span></label><input type="number" name="somme_percu" id="vl-somme-percu" class="fc" min="0" step="100" required oninput="calcReliquatLibre()"></div>
            <div class="fg"><label class="flbl">Reliquat (FCFA)</label><input type="text" id="vl-reliquat-display" class="fc" value="0" readonly style="background:var(--bg);font-weight:700;color:var(--success);"></div>
          </div>
        </div>

        <div class="fsec">
          <div class="fsec-t"><i class="fas fa-user"></i> Passager</div>
          <div class="form-grid">
            <div class="fg"><label class="flbl">Type passager <span class="freq">*</span></label><select name="type_passager" id="vl-type-passager" class="fc" required><option value="adulte">Adulte</option><option value="enfant">Enfant</option></select></div>
            <div class="fg"><label class="flbl">N° Téléphone <span class="freq">*</span></label><input type="tel" name="passager_tel" id="vl-tel" class="fc" placeholder="+237 6XX XXX XXX" required oninput="lookupPassagerLibre()"></div>
            <div class="fg"><label class="flbl">Nom & Prénom <span class="freq">*</span></label><input type="text" name="passager_nom" id="vl-nom" class="fc" placeholder="NOM Prénom" required style="text-transform:uppercase;"></div>
            <div class="fg"><label class="flbl">N° CNI / Passeport <span class="freq">*</span></label><input type="text" name="passager_cni" id="vl-cni" class="fc" required></div>
          </div>
          <div id="vl-passager-suggest" style="display:none;margin-top:6px;position:relative;"></div>
        </div>

        <div class="fsec">
          <div class="fsec-t"><i class="fas fa-suitcase"></i> Bagages & Observation</div>
          <div class="form-grid">
            <div class="fg"><label class="flbl">Poids (kg)</label><input type="number" name="bagages_kg" id="vl-bag-kg" class="fc" min="0" step="0.5" placeholder="0" oninput="calcTotalLibre()"></div>
            <div class="fg"><label class="flbl">Montant bagages (FCFA)</label><input type="number" name="bagages_montant" id="vl-bag-m" class="fc" min="0" step="100" placeholder="0" oninput="calcTotalLibre()"></div>
            <div class="fg full"><label class="flbl">Observation</label><textarea name="observation" id="vl-observation" class="fc" rows="2" placeholder="Remarques éventuelles..."></textarea></div>
          </div>
        </div>

        <div style="display:flex;gap:10px;margin-top:16px;">
          <button type="submit" id="vl-submit-btn" class="btn btn-warning btn-lg"><i class="fas fa-check-circle"></i> Confirmer la vente libre</button>
        </div>
      </form>

      <!-- Succès vente libre -->
      <div id="vl-success" style="display:none;text-align:center;padding:40px 20px;">
        <div style="font-size:52px;color:var(--success);margin-bottom:12px;"><i class="fas fa-check-circle"></i></div>
        <h2 style="margin-bottom:6px;">Ticket libre vendu !</h2>
        <p style="color:var(--text2);margin-bottom:4px;">N° <strong id="vl-success-num" style="font-family:'JetBrains Mono',monospace;font-size:18px;color:var(--primary);"></strong></p>
        <p style="color:var(--text2);margin-bottom:4px;" id="vl-success-troncon"></p>
        <p style="color:var(--text2);margin-bottom:20px;">Montant : <strong id="vl-success-montant"></strong></p>
        <div style="display:flex;gap:10px;justify-content:center;">
          <a id="vl-print-link" href="#" target="_blank" class="btn btn-primary"><i class="fas fa-print"></i> Imprimer</a>
          <button onclick="newVenteLibre()" class="btn btn-warning"><i class="fas fa-plus"></i> Nouvelle vente libre</button>
          <button onclick="closeModal('modal-vente-libre');location.reload();" class="btn btn-secondary">Fermer</button>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
const VENTE_URL = '<?= BASE_URL ?>modules/tickets/vente.php';
const CSRF_TOKEN = document.getElementById('csrf-token')?.dataset?.token || '';

// ═══ HELPERS ═══
const CLASS_COLORS = {cla:'#2563eb', vip:'#7c3aed', spc:'#d97706'};
function highlightClassBtn(container, btn) {
    container.querySelectorAll('.class-btn').forEach(b => {
        b.style.borderColor = 'var(--border)'; b.style.background = 'var(--card)'; b.style.color = 'var(--text2)';
    });
    btn.style.borderColor = CLASS_COLORS[btn.dataset.class] || 'var(--primary)';
    btn.style.background = (CLASS_COLORS[btn.dataset.class]||'#2563eb') + '15';
    btn.style.color = CLASS_COLORS[btn.dataset.class] || 'var(--primary)';
}
function setNowDatetime(id) {
    const now = new Date(); const pad = n => String(n).padStart(2,'0');
    document.getElementById(id).value = now.getFullYear()+'-'+pad(now.getMonth()+1)+'-'+pad(now.getDate())+'T'+pad(now.getHours())+':'+pad(now.getMinutes());
}
function setTodayDate(id) {
    const now = new Date(); const pad = n => String(n).padStart(2,'0');
    document.getElementById(id).value = now.getFullYear()+'-'+pad(now.getMonth()+1)+'-'+pad(now.getDate());
}

// ═══ VENTE RATTACHÉE ═══
let vaData = null, vaSelectedClass = '';

function selectClassAssociee(btn) {
    vaSelectedClass = btn.dataset.class;
    document.getElementById('va-classe').value = vaSelectedClass;
    highlightClassBtn(document.getElementById('va-class-btns'), btn);
    document.getElementById('va-voyage-section').style.display = '';
    loadVoyagesAssociee();
    if (vaData) filterTarifsAssociee();
}
function resetVenteAssociee() {
    vaSelectedClass = ''; vaData = null;
    document.getElementById('va-step1').style.display = '';
    document.getElementById('va-step2').style.display = 'none';
    document.getElementById('va-success').style.display = 'none';
    document.getElementById('va-success').dataset.sold = '';
    document.getElementById('va-voyage-section').style.display = 'none';
    document.getElementById('va-voyage').value = '';
    document.getElementById('va-voyage-info').style.display = 'none';
    document.getElementById('va-form')?.reset();
    document.getElementById('va-classe').value = '';
    document.getElementById('va-somme-percu').value = '';
    document.getElementById('va-reliquat-display').value = '0';
    document.getElementById('va-observation').value = '';
    document.getElementById('va-class-btns').querySelectorAll('.class-btn').forEach(b => {
        b.style.borderColor='var(--border)'; b.style.background='var(--card)'; b.style.color='var(--text2)';
    });
    setTodayDate('va-date-heure');
}
function resetVenteAssocieeStep() {
    document.getElementById('va-step1').style.display = '';
    document.getElementById('va-step2').style.display = 'none';
    document.getElementById('va-voyage').value = '';
}
function newVenteAssociee() { resetVenteAssociee(); }

async function loadVoyagesAssociee() {
    const sel = document.getElementById('va-voyage');
    sel.innerHTML = '<option value="">— Chargement… —</option>';
    try {
        const r = await fetch(VENTE_URL + '?ajax=voyages');
        if (!r.ok) {
            if (r.status === 401) { sel.innerHTML = '<option value="">Session expirée — rechargez la page</option>'; return; }
            throw new Error('HTTP ' + r.status);
        }
        const text = await r.text();
        let data;
        try { data = JSON.parse(text); } catch(e) { console.error('Réponse non-JSON:', text.substring(0,200)); throw e; }
        if (data && data.error) { sel.innerHTML = '<option value="">'+data.error+'</option>'; return; }
        sel.innerHTML = '<option value="">— Sélectionner un voyage —</option>';
        if (!data || !data.length) { sel.innerHTML += '<option value="" disabled>Aucun voyage programmé</option>'; return; }
        data.forEach(v => {
            const dispo = v.places_dispo - v.places_prises;
            const d = new Date(v.date_depart);
            sel.innerHTML += `<option value="${v.id}">${v.dep} → ${v.arr} | ${d.toLocaleDateString('fr-FR')} ${d.toLocaleTimeString('fr-FR',{hour:'2-digit',minute:'2-digit'})} | ${v.immatriculation||'—'} (${dispo} places)</option>`;
        });
    } catch(e) { console.error('loadVoyagesAssociee:', e); sel.innerHTML = '<option value="">Erreur de chargement</option>'; }
}

async function loadVoyageDetailAssociee(id) {
    if (!id) { document.getElementById('va-voyage-info').style.display='none'; return; }
    const info = document.getElementById('va-voyage-info'); info.style.display = '';
    info.innerHTML = '<div style="padding:12px;text-align:center;color:var(--text3);"><i class="fas fa-spinner fa-spin"></i> Chargement…</div>';
    try {
        const r = await fetch(VENTE_URL + '?ajax=voyage_detail&voyage_id=' + id);
        if (!r.ok) throw new Error('HTTP ' + r.status);
        const text = await r.text();
        let data;
        try { data = JSON.parse(text); } catch(e) { console.error('Réponse non-JSON:', text.substring(0,200)); throw e; }
        if (data.error) { info.innerHTML = `<div style="color:var(--danger);">${data.error}</div>`; return; }
        vaData = data; const v = data.voyage;
        const dispo = v.places_dispo - v.places_prises;
        let escHtml = '';
        if (data.escales.length > 2) {
            escHtml = '<div style="margin-top:8px;font-size:12px;">Escale(s) : ';
            data.escales.forEach((e,i) => {
                const bg = i===0?'var(--success)':i===data.escales.length-1?'var(--danger)':'var(--warning)';
                escHtml += `<span style="background:${bg};color:#fff;padding:2px 8px;border-radius:12px;font-size:11px;margin:0 2px;">${e.ville}</span>`;
                if (i<data.escales.length-1) escHtml += '<i class="fas fa-arrow-right" style="font-size:9px;color:var(--text3);margin:0 2px;"></i>';
            });
            escHtml += '</div>';
        }
        info.innerHTML = `<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:10px;background:var(--bg);border-radius:var(--radius);padding:12px;">
            <div><div style="font-size:10px;color:var(--text3);">Trajet</div><strong>${v.dep} → ${v.arr}</strong></div>
            <div><div style="font-size:10px;color:var(--text3);">Départ</div><strong>${new Date(v.date_depart).toLocaleDateString('fr-FR')} ${new Date(v.date_depart).toLocaleTimeString('fr-FR',{hour:'2-digit',minute:'2-digit'})}</strong></div>
            <div><div style="font-size:10px;color:var(--text3);">Véhicule</div><strong>${v.immatriculation||'—'}</strong></div>
            <div><div style="font-size:10px;color:var(--text3);">Places</div><strong>${dispo}</strong></div>
        </div>${escHtml}`;
        document.getElementById('va-voyage-id').value = id;
        const monteeSel = document.getElementById('va-escale-montee');
        const descenteSel = document.getElementById('va-escale-descente');
        monteeSel.innerHTML = ''; descenteSel.innerHTML = '';
        data.escales.forEach(e => {
            monteeSel.innerHTML += `<option value="${e.id}" data-ville="${e.ville}" data-ordre="${e.ordre}">${e.ville}</option>`;
            descenteSel.innerHTML += `<option value="${e.id}" data-ville="${e.ville}" data-ordre="${e.ordre}">${e.ville}</option>`;
        });
        if (monteeSel.options.length) monteeSel.selectedIndex = 0;
        if (descenteSel.options.length) descenteSel.selectedIndex = descenteSel.options.length - 1;
        window._vaAllTarifs = data.tarifs;
        filterTarifsAssociee();
        const corrSel = document.getElementById('va-corr-sel');
        corrSel.innerHTML = '<option value="">— Aucune correspondance définie —</option>';
        if (data.correspondances && data.correspondances.length) {
            data.correspondances.forEach(c => {
                corrSel.innerHTML += `<option value="${c.itineraire_depart_id}" data-nom="${c.depart_nom}" data-ville="${c.agence_ville}" data-min="${c.delai_min}" data-max="${c.delai_max}">${c.depart_nom} (depuis ${c.agence_ville}, délai ${c.delai_min}-${c.delai_max} min)</option>`;
            });
        }
        document.getElementById('va-step1').style.display = 'none';
        document.getElementById('va-step2').style.display = '';
        setTodayDate('va-date-heure');
        onEscaleChangeAssociee();
        document.getElementById('va-nom').focus();
    } catch(e) { info.innerHTML = '<div style="color:var(--danger);">Erreur de chargement</div>'; }
}

function filterTarifsAssociee() {
    const tarifInput = document.getElementById('va-tarif-sel');
    const allTarifs = window._vaAllTarifs || [];
    const filtered = vaSelectedClass ? allTarifs.filter(t => t.classe === vaSelectedClass) : allTarifs;
    tarifInput.value = ''; document.getElementById('va-montant').value = '';
    window._vaFilteredTarifs = filtered;
}

function onEscaleChangeAssociee() {
    const mSel = document.getElementById('va-escale-montee');
    const dSel = document.getElementById('va-escale-descente');
    const mOpt = mSel.options[mSel.selectedIndex];
    const dOpt = dSel.options[dSel.selectedIndex];
    if (!mOpt || !dOpt) return;
    const mVille = mOpt.dataset.ville, dVille = dOpt.dataset.ville;
    const vis = document.getElementById('va-escale-visual');
    if (vaData && vaData.escales.length > 0) {
        let html = '<div style="display:flex;align-items:center;gap:4px;flex-wrap:wrap;font-size:12px;">';
        vaData.escales.forEach(e => {
            const isM = e.ville===mVille, isD = e.ville===dVille;
            const bg = isM?'var(--success)':isD?'var(--danger)':'var(--border2)';
            const col = (isM||isD)?'#fff':'var(--text3)';
            html += `<span style="background:${bg};color:${col};padding:2px 8px;border-radius:12px;${(isM||isD)?'font-weight:700;':''}">${e.ville}</span>`;
            html += '<i class="fas fa-long-arrow-alt-right" style="color:var(--text3);font-size:9px;"></i>';
        }); html += '</div>'; vis.innerHTML = html;
    }
    const filtered = window._vaFilteredTarifs || [];
    const matched = filtered.find(t => t.escale_depart_id == mSel.value && t.escale_arrivee_id == dSel.value);
    if (matched) {
        document.getElementById('va-tarif-sel').value = matched.id;
        document.getElementById('va-montant').value = matched.prix;
        document.getElementById('va-classe').value = matched.classe;
        calcTotalAssociee();
    }
}

function calcTotalAssociee() {
    calcReliquatAssociee();
}
function calcReliquatAssociee() {
    const t = parseFloat(document.getElementById('va-montant')?.value||0);
    const b = parseFloat(document.getElementById('va-bag-m')?.value||0);
    const total = t + b;
    const percu = parseFloat(document.getElementById('va-somme-percu')?.value||0);
    const reliquat = percu - total;
    const fmt = new Intl.NumberFormat('fr-FR').format;
    const reliqEl = document.getElementById('va-reliquat-display');
    if (reliqEl) {
        reliqEl.value = fmt(Math.abs(reliquat)) + ' FCFA';
        reliqEl.style.color = reliquat >= 0 ? 'var(--success)' : 'var(--danger)';
    }
}
function toggleTransitAssociee(cb) {
    const sec = document.getElementById('va-transit-section');
    sec.style.display = cb.checked ? '' : 'none';
    if (cb.checked) {
        const corrSel = document.getElementById('va-corr-sel');
        corrSel.onchange = function() {
            const opt = this.options[this.selectedIndex];
            const infoDiv = document.getElementById('va-corr-info');
            if (opt && opt.value) {
                infoDiv.innerHTML = `<i class="fas fa-info-circle"></i> Correspondance à <strong>${opt.dataset.ville}</strong> : ${opt.dataset.nom}<br>Délai : ${opt.dataset.min}-${opt.dataset.max} min`;
                infoDiv.style.display = '';
            } else { infoDiv.style.display = 'none'; }
        };
    } else { document.getElementById('va-corr-info').style.display = 'none'; }
}

async function submitVenteAssociee(e) {
    e.preventDefault();
    const btn = document.getElementById('va-submit-btn');
    btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Enregistrement…';
    const fd = new FormData(document.getElementById('va-form'));
    try {
        const r = await fetch(VENTE_URL, { method:'POST', headers:{'X-Requested-With':'XMLHttpRequest'}, body:fd });
        if (!r.ok) {
            if (r.status === 401) { alert('Session expirée. Reconnectez-vous.'); window.location.reload(); return; }
            throw new Error('HTTP ' + r.status);
        }
        const text = await r.text();
        let data;
        try { data = JSON.parse(text); } catch(e) { console.error('Réponse non-JSON:', text.substring(0,300)); throw new Error('Réponse serveur invalide'); }
        if (data.success) {
            document.getElementById('va-step2').style.display = 'none';
            document.getElementById('va-success').style.display = '';
            document.getElementById('va-success').dataset.sold = '1';
            document.getElementById('va-success-num').textContent = data.numero;
            const mSel = document.getElementById('va-escale-montee');
            const dSel = document.getElementById('va-escale-descente');
            if (mSel.selectedIndex>=0 && dSel.selectedIndex>=0) {
                document.getElementById('va-success-troncon').textContent = mSel.options[mSel.selectedIndex].dataset.ville + ' → ' + dSel.options[dSel.selectedIndex].dataset.ville;
            }
            document.getElementById('va-success-montant').textContent = new Intl.NumberFormat('fr-FR').format(data.montant_total) + ' FCFA';
            document.getElementById('va-print-link').href = data.print_url;
        } else {
            alert(data.error || 'Erreur lors de la vente');
            btn.disabled = false; btn.innerHTML = '<i class="fas fa-check-circle"></i> Confirmer la vente';
        }
    } catch(err) { alert('Erreur réseau'); btn.disabled = false; btn.innerHTML = '<i class="fas fa-check-circle"></i> Confirmer la vente'; }
}

// ═══ VENTE LIBRE ═══
let vlData = null, vlSelectedClass = '';

function selectClassLibre(btn) {
    vlSelectedClass = btn.dataset.class;
    document.getElementById('vl-classe-hidden').value = vlSelectedClass;
    highlightClassBtn(document.getElementById('vl-class-btns'), btn);
    loadDestinationsLibres();
}
function resetVenteLibre() {
    vlSelectedClass = ''; vlData = null;
    document.getElementById('vl-form')?.reset();
    document.getElementById('vl-classe-hidden').value = '';
    document.getElementById('vl-somme-percu').value = '';
    document.getElementById('vl-reliquat-display').value = '0';
    document.getElementById('vl-observation').value = '';
    document.getElementById('vl-success').style.display = 'none';
    document.getElementById('vl-success').dataset.sold = '';
    document.getElementById('vl-submit-btn').disabled = false;
    document.getElementById('vl-submit-btn').innerHTML = '<i class="fas fa-check-circle"></i> Confirmer la vente libre';
    document.getElementById('vl-trajet-visual').innerHTML = '';
    document.getElementById('vl-destination').innerHTML = '<option value="">— Choisir d\'abord une classe —</option>';
    document.getElementById('vl-tarif').value = '';
    document.getElementById('vl-montant').value = '';
    document.getElementById('vl-class-btns').querySelectorAll('.class-btn').forEach(b => {
        b.style.borderColor='var(--border)'; b.style.background='var(--card)'; b.style.color='var(--text2)';
    });
    document.getElementById('vl-form').style.display = '';
    setTodayDate('vl-date-heure');
}
function newVenteLibre() { resetVenteLibre(); }

async function loadDestinationsLibres() {
    if (!vlSelectedClass) return;
    const destSel = document.getElementById('vl-destination');
    destSel.innerHTML = '<option value="">— Chargement… —</option>';
    try {
        const r = await fetch(VENTE_URL + '?ajax=destinations_libres');
        vlData = await r.json();
        destSel.innerHTML = '<option value="">— Sélectionner une destination —</option>';
        vlData.destinations.forEach(d => {
            const tarifs = vlData.tarifs[d.id] || [];
            const hasClassTarif = tarifs.some(t => t.classe === vlSelectedClass);
            if (hasClassTarif) destSel.innerHTML += `<option value="${d.id}" data-dep="${d.dep}" data-arr="${d.arr}">${d.dep} → ${d.arr}</option>`;
        });
    } catch(e) {}
}

function onDestLibreChange() {
    const destSel = document.getElementById('vl-destination');
    const opt = destSel.options[destSel.selectedIndex];
    const tarifInput = document.getElementById('vl-tarif');
    if (!opt || !opt.value) {
        tarifInput.value = ''; document.getElementById('vl-montant').value = '';
        document.getElementById('vl-trajet-visual').innerHTML = '';
        calcTotalLibre(); return;
    }
    const depVille = opt.dataset.dep, arrVille = opt.dataset.arr;
    document.getElementById('vl-trajet-visual').innerHTML = `<i class="fas fa-map-marker-alt" style="color:var(--success);"></i> ${depVille} <i class="fas fa-long-arrow-alt-right" style="color:var(--text3);margin:0 4px;"></i> <i class="fas fa-map-marker-alt" style="color:var(--danger);"></i> ${arrVille}`;
    const destId = destSel.value;
    const allTarifs = (vlData && vlData.tarifs && vlData.tarifs[destId]) ? vlData.tarifs[destId] : [];
    const tarifs = vlSelectedClass ? allTarifs.filter(t => t.classe === vlSelectedClass) : allTarifs;
    if (tarifs.length >= 1) {
        const t = tarifs[0];
        tarifInput.value = t.id;
        document.getElementById('vl-montant').value = t.prix;
        document.getElementById('vl-classe-hidden').value = t.classe;
        calcTotalLibre();
    } else {
        tarifInput.value = ''; document.getElementById('vl-montant').value = '';
        calcTotalLibre();
    }
}

function calcTotalLibre() {
    calcReliquatLibre();
}
function calcReliquatLibre() {
    const t = parseFloat(document.getElementById('vl-montant')?.value||0);
    const b = parseFloat(document.getElementById('vl-bag-m')?.value||0);
    const total = t + b;
    const percu = parseFloat(document.getElementById('vl-somme-percu')?.value||0);
    const reliquat = percu - total;
    const fmt = new Intl.NumberFormat('fr-FR').format;
    const reliqEl = document.getElementById('vl-reliquat-display');
    if (reliqEl) {
        reliqEl.value = fmt(Math.abs(reliquat)) + ' FCFA';
        reliqEl.style.color = reliquat >= 0 ? 'var(--success)' : 'var(--danger)';
    }
}

async function submitVenteLibre(e) {
    e.preventDefault();
    const btn = document.getElementById('vl-submit-btn');
    btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Enregistrement…';
    const fd = new FormData(document.getElementById('vl-form'));
    try {
        const r = await fetch(VENTE_URL, { method:'POST', headers:{'X-Requested-With':'XMLHttpRequest'}, body:fd });
        if (!r.ok) {
            if (r.status === 401) { alert('Session expirée. Reconnectez-vous.'); window.location.reload(); return; }
            throw new Error('HTTP ' + r.status);
        }
        const text = await r.text();
        let data;
        try { data = JSON.parse(text); } catch(e) { console.error('Réponse non-JSON:', text.substring(0,300)); throw new Error('Réponse serveur invalide'); }
        if (data.success) {
            document.getElementById('vl-form').style.display = 'none';
            document.getElementById('vl-success').style.display = '';
            document.getElementById('vl-success').dataset.sold = '1';
            document.getElementById('vl-success-num').textContent = data.numero;
            const destSel = document.getElementById('vl-destination');
            const dOpt = destSel.options[destSel.selectedIndex];
            document.getElementById('vl-success-troncon').textContent = dOpt && dOpt.value ? dOpt.dataset.dep + ' → ' + dOpt.dataset.arr : '';
            document.getElementById('vl-success-montant').textContent = new Intl.NumberFormat('fr-FR').format(data.montant_total) + ' FCFA';
            document.getElementById('vl-print-link').href = data.print_url;
        } else {
            alert(data.error || 'Erreur lors de la vente');
            btn.disabled = false; btn.innerHTML = '<i class="fas fa-check-circle"></i> Confirmer la vente libre';
        }
    } catch(err) { alert('Erreur réseau'); btn.disabled = false; btn.innerHTML = '<i class="fas fa-check-circle"></i> Confirmer la vente libre'; }
}

// ═══ AUTOCOMPLETION PASSAGER ═══
let _vaLookupTimer = null, _vlLookupTimer = null;

function buildSuggestDropdown(data, box, fillFn) {
    if (!data || !data.length) { box.style.display='none'; return; }
    let html = '<div style="font-size:12px;color:var(--text2);margin-bottom:6px;"><i class="fas fa-user-check"></i> '+data.length+' passager(s) trouvé(s) — cliquez pour remplir</div>';
    data.forEach(p => {
        const name = ((p.nom||'') + ' ' + (p.prenom||'')).trim();
        const phone = p.telephone || '';
        const cni = p.cni || '';
        const safeName = name.replace(/'/g,"\\'").replace(/"/g,'&quot;');
        const safeCni = cni.replace(/'/g,"\\'").replace(/"/g,'&quot;');
        const safeTel = phone.replace(/'/g,"\\'").replace(/"/g,'&quot;');
        html += `<div onclick="${fillFn}('${safeName}','${safeCni}','${safeTel}')" style="padding:8px 12px;background:var(--card);border:1px solid var(--border);border-radius:var(--radius);margin-bottom:4px;cursor:pointer;font-size:13px;display:flex;align-items:center;gap:10px;" onmouseover="this.style.background='var(--primary)15';this.style.borderColor='var(--primary)'" onmouseout="this.style.background='var(--card)';this.style.borderColor='var(--border)'">
            <i class="fas fa-user" style="color:var(--primary);"></i>
            <div><strong>${name}</strong>${phone ? ' <span style="color:var(--text3);font-size:11px;">📱'+phone+'</span>' : ''}${cni ? ' <span style="color:var(--text3);font-size:11px;">🪪'+cni+'</span>' : ''}</div>
        </div>`;
    });
    box.innerHTML = html;
    box.style.cssText = 'display:block;position:absolute;z-index:100;background:var(--card);border:1px solid var(--border);border-radius:var(--radius);padding:8px;box-shadow:0 4px 16px rgba(0,0,0,.12);max-height:250px;overflow-y:auto;width:100%;';
}

async function lookupPassagerAssociee() {
    clearTimeout(_vaLookupTimer);
    const tel = document.getElementById('va-tel').value.trim();
    if (tel.length < 3) { document.getElementById('va-passager-suggest').style.display='none'; return; }
    _vaLookupTimer = setTimeout(async () => {
        try {
            const r = await fetch(VENTE_URL + '?ajax=passager&tel=' + encodeURIComponent(tel));
            if (!r.ok) { console.warn('Passager lookup failed:', r.status); return; }
            const data = await r.json();
            if (data && data.error) { console.warn('Passager lookup error:', data.error); return; }
            const box = document.getElementById('va-passager-suggest');
            if (!data || !data.length) { box.style.display='none'; return; }
            if (data.length === 1) {
                const p = data[0];
                document.getElementById('va-nom').value = ((p.nom||'') + ' ' + (p.prenom||'')).trim().toUpperCase();
                document.getElementById('va-cni').value = p.cni || '';
                if (p.telephone) document.getElementById('va-tel').value = p.telephone;
                box.style.display = 'none';
            } else {
                buildSuggestDropdown(data, box, 'fillPassagerAssociee');
            }
        } catch(e) { console.error('lookupPassagerAssociee error:', e); }
    }, 300);
}
function fillPassagerAssociee(nom, cni, tel) {
    document.getElementById('va-nom').value = nom.toUpperCase();
    document.getElementById('va-cni').value = cni;
    if (tel) document.getElementById('va-tel').value = tel;
    document.getElementById('va-passager-suggest').style.display = 'none';
}

async function lookupPassagerLibre() {
    clearTimeout(_vlLookupTimer);
    const tel = document.getElementById('vl-tel').value.trim();
    if (tel.length < 3) { document.getElementById('vl-passager-suggest').style.display='none'; return; }
    _vlLookupTimer = setTimeout(async () => {
        try {
            const r = await fetch(VENTE_URL + '?ajax=passager&tel=' + encodeURIComponent(tel));
            if (!r.ok) { console.warn('Passager lookup failed:', r.status); return; }
            const data = await r.json();
            if (data && data.error) { console.warn('Passager lookup error:', data.error); return; }
            const box = document.getElementById('vl-passager-suggest');
            if (!data || !data.length) { box.style.display='none'; return; }
            if (data.length === 1) {
                const p = data[0];
                document.getElementById('vl-nom').value = ((p.nom||'') + ' ' + (p.prenom||'')).trim().toUpperCase();
                document.getElementById('vl-cni').value = p.cni || '';
                if (p.telephone) document.getElementById('vl-tel').value = p.telephone;
                box.style.display = 'none';
            } else {
                buildSuggestDropdown(data, box, 'fillPassagerLibre');
            }
        } catch(e) { console.error('lookupPassagerLibre error:', e); }
    }, 300);
}
function fillPassagerLibre(nom, cni, tel) {
    document.getElementById('vl-nom').value = nom.toUpperCase();
    document.getElementById('vl-cni').value = cni;
    if (tel) document.getElementById('vl-tel').value = tel;
    document.getElementById('vl-passager-suggest').style.display = 'none';
}

// Fermer suggestions au clic extérieur
document.addEventListener('click', function(e) {
    if (!e.target.closest('#va-passager-suggest') && !e.target.closest('#va-tel'))
        document.getElementById('va-passager-suggest').style.display='none';
    if (!e.target.closest('#vl-passager-suggest') && !e.target.closest('#vl-tel'))
        document.getElementById('vl-passager-suggest').style.display='none';
});
</script>
<?php include '../../includes/footer.php'; ?>