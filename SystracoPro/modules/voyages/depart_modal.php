<?php
// modules/voyages/depart_modal.php
// Inclus dans index.php et voir.php
?>
<div class="modal-over" id="modal-depart">
  <div class="modal modal-xl" style="max-width:1000px;">
    <div class="modal-head">
      <h3><i class="fas fa-bus"></i> Départ du voyage — <span id="md-voyage-num">—</span></h3>
      <button class="modal-x" onclick="closeModal('modal-depart')">&times;</button>
    </div>
    <div class="modal-body" style="padding:0;">

      <!-- Stats rapides -->
      <div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin:0;padding:12px 16px;background:var(--bg);gap:10px;">
        <div class="stat-card" style="padding:8px 12px;"><div class="stat-icon" style="background:linear-gradient(135deg,#1d4ed8,#60a5fa)"><i class="fas fa-chair"></i></div><div><div class="stat-val" id="md-stats-places">—</div><div class="stat-lbl">Places</div></div></div>
        <div class="stat-card" style="padding:8px 12px;"><div class="stat-icon" style="background:linear-gradient(135deg,#16a34a,#4ade80)"><i class="fas fa-ticket-alt"></i></div><div><div class="stat-val" id="md-stats-vendus">—</div><div class="stat-lbl">Vendus</div></div></div>
        <div class="stat-card" style="padding:8px 12px;"><div class="stat-icon" style="background:linear-gradient(135deg,#d97706,#fbbf24)"><i class="fas fa-money-bill-wave"></i></div><div><div class="stat-val" style="font-size:14px;" id="md-stats-recette">—</div><div class="stat-lbl">Recette</div></div></div>
        <div class="stat-card" style="padding:8px 12px;"><div class="stat-icon" style="background:linear-gradient(135deg,#7c3aed,#a78bfa)"><i class="fas fa-file-invoice"></i></div><div><div class="stat-val" id="md-stats-brd">—</div><div class="stat-lbl">Bordereaux</div></div></div>
      </div>

      <!-- Tabs -->
      <div class="tabs" style="padding:0 16px;border-bottom:1px solid var(--border);">
        <div class="tab active" data-mdt="tab-info" onclick="mdSwitchTab('tab-info',this)"><i class="fas fa-info-circle"></i> Info voyage</div>
        <div class="tab" data-mdt="tab-vendus" onclick="mdSwitchTab('tab-vendus',this)"><i class="fas fa-ticket-alt"></i> Tickets vendus (<span id="md-count-vendus">0</span>)</div>
        <div class="tab" data-mdt="tab-utilises" onclick="mdSwitchTab('tab-utilises',this)"><i class="fas fa-check-circle"></i> Tickets utilisés (<span id="md-count-utilises">0</span>)</div>
        <div class="tab" data-mdt="tab-finances" onclick="mdSwitchTab('tab-finances',this)"><i class="fas fa-calculator"></i> Finances</div>
      </div>

      <div style="padding:16px;">

        <!-- TAB: Info voyage -->
        <div id="tab-info" class="tab-panel active">
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;font-size:13px;" id="md-info-content"></div>
        </div>

        <!-- TAB: Tickets vendus -->
        <div id="tab-vendus" class="tab-panel" style="display:none;">
          <div style="display:flex;gap:8px;align-items:center;margin-bottom:12px;flex-wrap:wrap;">
            <button type="button" class="btn btn-xs btn-ghost" onclick="mdToggleSel(true)"><i class="fas fa-check-double"></i> Tout cocher</button>
            <button type="button" class="btn btn-xs btn-ghost" onclick="mdToggleSel(false)"><i class="fas fa-times"></i> Tout décocher</button>
            <span style="font-size:12px;color:var(--text2);">Sélectionnés : <strong id="md-sel-count">0</strong></span>
            <span style="font-size:12px;color:var(--success);">Total : <strong id="md-sel-total">0</strong> FCFA</span>
          </div>
          <div class="table-wrap" style="max-height:300px;overflow-y:auto;">
            <table data-no-filter>
              <thead><tr>
                <th style="width:36px;"><input type="checkbox" id="md-check-all" onchange="mdToggleSel(this.checked)"></th>
                <th>N° Ticket</th><th>Passager</th><th>Téléphone</th><th>Siège</th><th>Classe</th><th>Destination</th><th>Montant</th><th>Mode</th><th>Type</th>
              </tr></thead>
              <tbody id="md-tbody-vendus"></tbody>
            </table>
          </div>
        </div>

        <!-- TAB: Tickets utilisés -->
        <div id="tab-utilises" class="tab-panel" style="display:none;">
          <div class="table-wrap" style="max-height:300px;overflow-y:auto;">
            <table data-no-filter>
              <thead><tr>
                <th>N° Ticket</th><th>Passager</th><th>Téléphone</th><th>Siège</th><th>Classe</th><th>Destination</th><th>Montant</th><th>Mode</th><th>Bordereau</th>
              </tr></thead>
              <tbody id="md-tbody-utilises"></tbody>
            </table>
          </div>
        </div>

        <!-- TAB: Finances -->
        <div id="tab-finances" class="tab-panel" style="display:none;">
          <form id="md-form-finances" onsubmit="return false;">
            <div class="form-grid">
              <div class="fg">
                <label class="flbl">Type de bordereau</label>
                <select name="type" id="md-type" class="fc" onchange="mdFiltrerTransit()">
                  <option value="chauffeur">Chauffeur</option>
                  <option value="comptabilite">Comptabilité</option>
                  <option value="transit">Transit</option>
                  <option value="direction">Direction</option>
                </select>
              </div>
              <div class="fg">
                <label class="flbl">Montant carburant (FCFA)</label>
                <input type="number" name="montant_carburant" id="md-carb" class="fc" min="0" step="500" value="0" oninput="mdCalcRecap()">
              </div>
              <div class="fg">
                <label class="flbl">Montant péages (FCFA)</label>
                <input type="number" name="montant_peage" id="md-peage" class="fc" min="0" step="100" value="0" oninput="mdCalcRecap()">
              </div>
              <div class="fg">
                <label class="flbl">Avance chauffeur (FCFA)</label>
                <input type="number" name="avance_chauffeur" id="md-avance" class="fc" min="0" step="500" value="0" oninput="mdCalcRecap()">
              </div>
              <div class="fg">
                <label class="flbl">Autres déductions (FCFA)</label>
                <input type="number" name="autres_deductions" id="md-autres" class="fc" min="0" step="100" value="0" oninput="mdCalcRecap()">
              </div>
              <div class="fg" style="grid-column:1/-1;">
                <label class="flbl">Observations</label>
                <textarea name="observations" id="md-obs" class="fc" rows="2"></textarea>
              </div>
            </div>
          </form>

          <!-- Récapitulatif -->
          <div style="background:var(--bg);border-radius:var(--radius);padding:14px;margin-top:14px;">
            <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid var(--border);font-size:13px;"><span>Tickets sélectionnés</span><strong id="md-recap-nb">0</strong></div>
            <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid var(--border);font-size:13px;"><span>Recette brute</span><strong style="color:var(--success);" id="md-recap-brute">0 FCFA</strong></div>
            <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid var(--border);font-size:13px;"><span>(-) Carburant</span><span id="md-recap-carb">0 FCFA</span></div>
            <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid var(--border);font-size:13px;"><span>(-) Péages</span><span id="md-recap-peage">0 FCFA</span></div>
            <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid var(--border);font-size:13px;"><span>(-) Avance chauffeur</span><span id="md-recap-avance">0 FCFA</span></div>
            <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid var(--border);font-size:13px;"><span>(-) Autres</span><span id="md-recap-autres">0 FCFA</span></div>
            <div style="display:flex;justify-content:space-between;padding:8px 0;font-size:15px;font-weight:800;color:var(--primary);">
              <span>RECETTE NETTE</span><span id="md-recap-nette">0 FCFA</span>
            </div>
          </div>
        </div>

      </div>
    </div>

    <div class="modal-foot" style="display:flex;gap:8px;justify-content:space-between;align-items:center;">
      <div id="md-status-msg" style="font-size:12px;color:var(--text3);"></div>
      <div style="display:flex;gap:8px;">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modal-depart')">Fermer</button>
        <button type="button" class="btn btn-info" id="md-btn-imprimer" style="display:none;" onclick="mdImprimer()"><i class="fas fa-print"></i> Imprimer</button>
        <button type="button" class="btn btn-success btn-lg" id="md-btn-valider" onclick="mdValider()" disabled><i class="fas fa-check"></i> Valider le départ</button>
      </div>
    </div>
  </div>
</div>

<script>
let mdVoyageId = 0;
let mdVoyageData = null;
let mdTicketsSansBord = [];
let mdTicketsAvecBord = [];
let mdBordereaux = [];
let mdRouteVilles = [];
let mdCanValidate = false;
let mdBordereauId = 0;

function f(n){ return new Intl.NumberFormat('fr-FR').format(Math.round(n)); }

async function ouvrirDepartModal(voyageId) {
    mdVoyageId = voyageId;
    document.getElementById('md-btn-valider').disabled = true;
    document.getElementById('md-btn-valider').style.display = '';
    document.getElementById('md-btn-imprimer').style.display = 'none';
    document.getElementById('md-status-msg').textContent = '';
    document.getElementById('md-tbody-vendus').innerHTML = '<tr><td colspan="10" class="t-empty">Chargement...</td></tr>';
    document.getElementById('md-tbody-utilises').innerHTML = '<tr><td colspan="9" class="t-empty">Chargement...</td></tr>';
    document.getElementById('md-info-content').innerHTML = '<div style="text-align:center;padding:20px;">Chargement...</div>';

    openModal('modal-depart');
    mdSwitchTab('tab-info', document.querySelector('[data-mdt=tab-info]'));

    try {
        var r = await fetch('depart_ajax.php?action=load&voyage_id=' + voyageId);
        var data = await r.json();
        if (data.error) { alert(data.error); closeModal('modal-depart'); return; }

        mdVoyageData = data.voyage;
        mdTicketsSansBord = data.tickets_sans_bord;
        mdTicketsAvecBord = data.tickets_avec_bord;
        mdBordereaux = data.bordereaux;
        mdRouteVilles = data.route_villes || [];
        mdCanValidate = data.can_validate && (mdVoyageData.statut === 'programme');

        mdRenderInfo();
        mdRenderVendus();
        mdRenderUtilises();
        mdUpdateStats();
        mdCalcRecap();

        if (mdVoyageData.statut === 'en_cours' && mdBordereaux.length > 0) {
            mdBordereauId = mdBordereaux[0].id;
            document.getElementById('md-btn-imprimer').style.display = '';
            document.getElementById('md-btn-valider').style.display = 'none';
            document.getElementById('md-status-msg').textContent = 'Voyage en cours — Bordereau: ' + (mdBordereaux[0].numero || '');
        } else if (mdCanValidate) {
            document.getElementById('md-btn-valider').style.display = '';
            document.getElementById('md-btn-imprimer').style.display = 'none';
            document.getElementById('md-btn-valider').disabled = true;
        } else {
            document.getElementById('md-btn-valider').style.display = 'none';
        }
    } catch(e) {
        alert('Erreur de chargement: ' + e.message);
        closeModal('modal-depart');
    }
}

function mdRenderInfo() {
    var v = mdVoyageData;
    var capBus = v.capacite ? parseInt(v.capacite) : parseInt(v.places_dispo);
    var rows = [
        ['N° Voyage', '<code>'+mdEsc(v.numero)+'</code>'],
        ['Statut', '<span class="tag-statut st-'+mdEsc(v.statut)+'">'+mdEsc(v.statut)+'</span>'],
        ['Trajet', '<strong>'+mdEsc(v.dep)+' → '+mdEsc(v.arr)+'</strong>'],
        ['Date départ', '<strong>'+mdEsc(v.date_depart)+'</strong>'],
        ['Classe', mdEsc(v.classe_voyage || 'cla')],
        ['Véhicule', mdEsc(v.immatriculation || '—') + (v.marque ? ' ('+mdEsc(v.marque)+')' : '')],
        ['Capacité', capBus + ' places'],
        ['Chauffeur', mdEsc(v.chauf_nom || '—')],
        ['Convoyeur', mdEsc(v.convoyeur_nom || '—')],
        ['Chef de piste', mdEsc(v.chef_depiste || '—')],
        ['Agence départ', mdEsc(v.dep_nom || '—')],
        ['Agence arrivée', mdEsc(v.arr_nom || '—')],
        ['Carburant prévu', f(v.montant_carburant || 0) + ' FCFA'],
        ['Péages prévus', f(v.montant_peage || 0) + ' FCFA']
    ];
    var html = '';
    rows.forEach(function(r){
        html += '<div><span style="color:var(--text2);">'+r[0]+' :</span> '+r[1]+'</div>';
    });
    document.getElementById('md-info-content').innerHTML = html;
    document.getElementById('md-voyage-num').textContent = mdVoyageData.numero || '—';
}

function mdRenderVendus() {
    document.getElementById('md-count-vendus').textContent = mdTicketsSansBord.length;
    if (!mdTicketsSansBord.length) {
        document.getElementById('md-tbody-vendus').innerHTML = '<tr><td colspan="10" class="t-empty">Aucun ticket disponible</td></tr>';
        return;
    }
    var html = '';
    mdTicketsSansBord.forEach(function(t){
        var modeIcons = {especes:'💵',om:'📱OM',momo:'📱MM',carte:'💳'};
        html += '<tr data-dest="'+mdEsc((t.dest_ville||'').toLowerCase())+'">'+
            '<td><input type="checkbox" name="ticket_ids[]" value="'+t.id+'" class="md-cb" data-montant="'+t.montant_total+'" onchange="mdUpdateSel()"></td>'+
            '<td><code style="font-size:11px;">'+mdEsc(t.numero)+'</code></td>'+
            '<td><strong>'+mdEsc(t.passager_nom)+'</strong></td>'+
            '<td style="font-size:12px;">'+mdEsc(t.passager_tel||'—')+'</td>'+
            '<td style="text-align:center;font-weight:700;">'+mdEsc(t.siege||'—')+'</td>'+
            '<td><span class="badge '+(t.classe==='vip'?'badge-purple':(t.classe==='spc'?'badge-teal':'badge-blue'))+'">'+mdEsc(t.classe).toUpperCase()+'</span></td>'+
            '<td>'+mdEsc(t.dest_ville||'—')+'</td>'+
            '<td style="font-weight:600;">'+f(t.montant_total)+'</td>'+
            '<td style="font-size:11px;">'+(modeIcons[t.mode_paiement]||mdEsc(t.mode_paiement||'—'))+'</td>'+
            '<td>'+((t.type_vente||'')==='Libre'?'<span class="badge badge-amber">Libre</span>':'<span class="badge badge-blue">Voyage</span>')+'</td>'+
        '</tr>';
    });
    document.getElementById('md-tbody-vendus').innerHTML = html;
    mdFiltrerTransit();
}

function mdRenderUtilises() {
    document.getElementById('md-count-utilises').textContent = mdTicketsAvecBord.length;
    if (!mdTicketsAvecBord.length) {
        document.getElementById('md-tbody-utilises').innerHTML = '<tr><td colspan="9" class="t-empty">Aucun ticket dans un bordereau</td></tr>';
        return;
    }
    var html = '';
    mdTicketsAvecBord.forEach(function(t){
        var modeIcons = {especes:'💵',om:'📱OM',momo:'📱MM',carte:'💳'};
        html += '<tr>'+
            '<td><code style="font-size:11px;">'+mdEsc(t.numero)+'</code></td>'+
            '<td><strong>'+mdEsc(t.passager_nom)+'</strong></td>'+
            '<td style="font-size:12px;">'+mdEsc(t.passager_tel||'—')+'</td>'+
            '<td style="text-align:center;font-weight:700;">'+mdEsc(t.siege||'—')+'</td>'+
            '<td><span class="badge '+(t.classe==='vip'?'badge-purple':(t.classe==='spc'?'badge-teal':'badge-blue'))+'">'+mdEsc(t.classe).toUpperCase()+'</span></td>'+
            '<td>'+mdEsc(t.dest_ville||'—')+'</td>'+
            '<td style="font-weight:600;">'+f(t.montant_total)+'</td>'+
            '<td style="font-size:11px;">'+(modeIcons[t.mode_paiement]||mdEsc(t.mode_paiement||'—'))+'</td>'+
            '<td><span class="tag-statut st-'+mdEsc(t.brd_statut||'genere')+'">'+mdEsc(t.brd_numero||'—')+'</span></td>'+
        '</tr>';
    });
    document.getElementById('md-tbody-utilises').innerHTML = html;
}

function mdUpdateStats() {
    var capBus = mdVoyageData.capacite ? parseInt(mdVoyageData.capacite) : parseInt(mdVoyageData.places_dispo);
    var nbTks = parseInt(mdVoyageData.nb_tks) || 0;
    document.getElementById('md-stats-places').textContent = capBus;
    document.getElementById('md-stats-vendus').textContent = nbTks + '/' + capBus;
    document.getElementById('md-stats-recette').textContent = f(mdTicketsAvecBord.reduce(function(s,t){return s+parseFloat(t.montant_total||0);},0)) + ' F';
    document.getElementById('md-stats-brd').textContent = mdBordereaux.length;
}

function mdUpdateSel() {
    var cbs = document.querySelectorAll('.md-cb:checked');
    var count = cbs.length, total = 0;
    cbs.forEach(function(cb){ total += parseFloat(cb.dataset.montant||0); });
    document.getElementById('md-sel-count').textContent = count;
    document.getElementById('md-sel-total').textContent = f(total);
    var allCbs = document.querySelectorAll('.md-cb');
    document.getElementById('md-check-all').checked = allCbs.length > 0 && count === allCbs.length;
    document.getElementById('md-btn-valider').disabled = count === 0;
    mdCalcRecap();
}

function mdToggleSel(state) {
    document.querySelectorAll('.md-cb').forEach(function(cb){ cb.checked = state; });
    mdUpdateSel();
}

function mdFiltrerTransit() {
    var type = document.getElementById('md-type').value;
    document.querySelectorAll('#md-tbody-vendus tr[data-dest]').forEach(function(tr){
        var dest = (tr.dataset.dest || '').trim();
        if (type === 'transit' && dest && mdRouteVilles.length) {
            var surTrajet = mdRouteVilles.some(function(v){ return dest === v || dest.indexOf(v) !== -1 || v.indexOf(dest) !== -1; });
            tr.style.display = surTrajet ? 'none' : '';
            if (surTrajet) {
                var cb = tr.querySelector('.md-cb');
                if (cb) cb.checked = false;
            }
        } else {
            tr.style.display = '';
        }
    });
    mdUpdateSel();
}

function mdCalcRecap() {
    var cbs = document.querySelectorAll('.md-cb:checked');
    var count = cbs.length, brute = 0;
    cbs.forEach(function(cb){ brute += parseFloat(cb.dataset.montant||0); });
    var carb = parseFloat(document.getElementById('md-carb')?.value||0);
    var peage = parseFloat(document.getElementById('md-peage')?.value||0);
    var avance = parseFloat(document.getElementById('md-avance')?.value||0);
    var autres = parseFloat(document.getElementById('md-autres')?.value||0);
    var nette = brute - carb - peage - avance - autres;

    document.getElementById('md-recap-nb').textContent = count;
    document.getElementById('md-recap-brute').textContent = f(brute) + ' FCFA';
    document.getElementById('md-recap-carb').textContent = f(carb) + ' FCFA';
    document.getElementById('md-recap-peage').textContent = f(peage) + ' FCFA';
    document.getElementById('md-recap-avance').textContent = f(avance) + ' FCFA';
    document.getElementById('md-recap-autres').textContent = f(autres) + ' FCFA';
    document.getElementById('md-recap-nette').textContent = f(nette) + ' FCFA';
}

function mdSwitchTab(tabId, el) {
    document.querySelectorAll('#modal-depart .tab-panel').forEach(function(p){ p.style.display='none'; });
    document.querySelectorAll('#modal-depart .tab').forEach(function(t){ t.classList.remove('active'); });
    var panel = document.getElementById(tabId);
    if (panel) panel.style.display = '';
    if (el) el.classList.add('active');
}

async function mdValider() {
    var cbs = document.querySelectorAll('.md-cb:checked');
    if (!cbs.length) { alert('Sélectionnez au moins un ticket.'); return; }

    var ticketIds = [];
    cbs.forEach(function(cb){ ticketIds.push(parseInt(cb.value)); });

    var body = {
        voyage_id: mdVoyageId,
        type: document.getElementById('md-type').value,
        montant_carburant: parseFloat(document.getElementById('md-carb')?.value||0),
        montant_peage: parseFloat(document.getElementById('md-peage')?.value||0),
        avance_chauffeur: parseFloat(document.getElementById('md-avance')?.value||0),
        autres_deductions: parseFloat(document.getElementById('md-autres')?.value||0),
        observations: document.getElementById('md-obs')?.value||'',
        ticket_ids: ticketIds
    };

    document.getElementById('md-btn-valider').disabled = true;
    document.getElementById('md-btn-valider').innerHTML = '<i class="fas fa-spinner fa-spin"></i> Validation...';
    document.getElementById('md-status-msg').textContent = '';

    try {
        var r = await fetch('depart_ajax.php?action=valider', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': '<?= csrfToken() ?>'
            },
            body: JSON.stringify(body)
        });
        var data = await r.json();
        if (data.error) {
            alert(data.error);
            document.getElementById('md-btn-valider').disabled = false;
            document.getElementById('md-btn-valider').innerHTML = '<i class="fas fa-check"></i> Valider le départ';
            return;
        }

        mdBordereauId = data.bordereau_id;
        document.getElementById('md-btn-valider').style.display = 'none';
        document.getElementById('md-btn-imprimer').style.display = '';
        document.getElementById('md-status-msg').innerHTML = '<span style="color:var(--success);"><i class="fas fa-check-circle"></i> '+mdEsc(data.message)+'</span>';

        setTimeout(async function(){
            try {
                var r2 = await fetch('depart_ajax.php?action=load&voyage_id=' + mdVoyageId);
                var d2 = await r2.json();
                if (!d2.error) {
                    mdVoyageData = d2.voyage;
                    mdTicketsSansBord = d2.tickets_sans_bord;
                    mdTicketsAvecBord = d2.tickets_avec_bord;
                    mdBordereaux = d2.bordereaux;
                    mdRenderInfo();
                    mdRenderVendus();
                    mdRenderUtilises();
                    mdUpdateStats();
                }
            } catch(e) {}
        }, 800);

    } catch(e) {
        alert('Erreur: ' + e.message);
        document.getElementById('md-btn-valider').disabled = false;
        document.getElementById('md-btn-valider').innerHTML = '<i class="fas fa-check"></i> Valider le départ';
    }
}

function mdImprimer() {
    if (mdBordereauId) {
        window.open('../bordereaux/imprimer.php?id=' + mdBordereauId, '_blank');
    }
}

function mdEsc(s) {
    if (!s) return '';
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
</script>
