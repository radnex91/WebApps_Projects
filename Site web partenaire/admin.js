var API_BASE = 'api.php';

var modal = document.getElementById('modal');
var form = document.getElementById('form-produit');
var modalTitle = document.getElementById('modal-title');
var groupActif = document.getElementById('group-actif');
var btnAdd = document.getElementById('btn-add');
var cacheStats = {
  produits: [],
  partenaires: [],
  clients: [],
  stockLignes: [],
  stockMouvements: []
};

function renderAnalyse() {
  var wrap = document.getElementById('analyse-wrapper');
  if (!wrap) return;
  var pActifs = (cacheStats.produits || []).filter(function(p) { return Number(p.actif) === 1; }).length;
  var stockTotal = (cacheStats.stockLignes || []).reduce(function(sum, l) { return sum + (Number(l.quantite) || 0); }, 0);
  var ventes = (cacheStats.stockMouvements || []).filter(function(m) { return m.type === 'vente'; }).length;
  wrap.innerHTML =
    '<div class="admin-kpis">' +
      '<article class="kpi-card" style="--card-index:0;"><div class="kpi-label">Produits actifs</div><div class="kpi-value">' + pActifs + '</div></article>' +
      '<article class="kpi-card" style="--card-index:1;"><div class="kpi-label">Partenaires</div><div class="kpi-value">' + (cacheStats.partenaires || []).length + '</div></article>' +
      '<article class="kpi-card" style="--card-index:2;"><div class="kpi-label">Clients</div><div class="kpi-value">' + (cacheStats.clients || []).length + '</div></article>' +
      '<article class="kpi-card" style="--card-index:3;"><div class="kpi-label">Stock total (unités)</div><div class="kpi-value">' + stockTotal + '</div></article>' +
      '<article class="kpi-card" style="--card-index:4;"><div class="kpi-label">Mouvements (50 derniers)</div><div class="kpi-value">' + (cacheStats.stockMouvements || []).length + '</div></article>' +
      '<article class="kpi-card" style="--card-index:5;"><div class="kpi-label">Ventes (50 derniers)</div><div class="kpi-value">' + ventes + '</div></article>' +
    '</div>';
}

function fetchJson(url) {
  return fetch(url, { credentials: 'same-origin' }).then(function(res) {
    if (res.status === 401) {
      window.location.href = 'login.php';
      return null;
    }
    return res.json();
  });
}

function loadDashboardAnalysis() {
  var wrap = document.getElementById('analyse-wrapper');
  if (!wrap) return;
  wrap.innerHTML = '<div class="empty-state">Chargement…</div>';
  Promise.all([
    fetchJson(API_BASE + '?resource=produits&admin=1'),
    fetchJson(API_BASE + '?resource=utilisateurs&role=partenaire'),
    fetchJson(API_BASE + '?resource=utilisateurs&role=client'),
    fetchJson(API_BASE + '?resource=stock')
  ]).then(function(all) {
    var produits = Array.isArray(all[0]) ? all[0] : [];
    var partenaires = Array.isArray(all[1]) ? all[1] : [];
    var clients = Array.isArray(all[2]) ? all[2] : [];
    var stockData = all[3] && !all[3].error ? all[3] : {};
    cacheStats.produits = produits;
    cacheStats.partenaires = partenaires;
    cacheStats.clients = clients;
    cacheStats.stockLignes = Array.isArray(stockData.lignes) ? stockData.lignes : [];
    cacheStats.stockMouvements = Array.isArray(stockData.mouvements) ? stockData.mouvements : [];
    renderAnalyse();
  }).catch(function() {
    wrap.innerHTML = '<div class="empty-state">Erreur chargement analyse.</div>';
  });
}

function openModal(produit) {
  produit = produit || null;
  if (modal) modal.classList.add('open');
  if (produit) {
    modalTitle.textContent = 'Modifier le produit';
    document.getElementById('produit-id').value = produit.id;
    document.getElementById('nom').value = produit.nom;
    document.getElementById('prix_partenaire').value = produit.prix_partenaire;
    document.getElementById('prix_client').value = produit.prix_client;
    document.getElementById('unite').value = produit.unite || '€';
    document.getElementById('actif').checked = produit.actif === 1 || produit.actif === '1';
    groupActif.classList.remove('is-hidden');
  } else {
    modalTitle.textContent = 'Ajouter un produit';
    form.reset();
    document.getElementById('produit-id').value = '';
    document.getElementById('unite').value = '€';
    document.getElementById('actif').checked = true;
    groupActif.classList.add('is-hidden');
  }
}

function closeModal() {
  if (modal) modal.classList.remove('open');
}

var modalClose = document.getElementById('modal-close');
var modalCancel = document.getElementById('modal-cancel');
if (modalClose) modalClose.addEventListener('click', closeModal);
if (modalCancel) modalCancel.addEventListener('click', closeModal);
if (modal) modal.addEventListener('click', function(e) { if (e.target === modal) closeModal(); });

if (btnAdd) btnAdd.addEventListener('click', function() { openModal(); });

if (form) form.addEventListener('submit', function(e) {
  e.preventDefault();
  var id = document.getElementById('produit-id').value;
  var payload = {
    nom: document.getElementById('nom').value.trim(),
    prix_partenaire: parseFloat(document.getElementById('prix_partenaire').value) || 0,
    prix_client: parseFloat(document.getElementById('prix_client').value) || 0,
    unite: document.getElementById('unite').value.trim() || '€'
  };
  if (id) payload.actif = document.getElementById('actif').checked ? 1 : 0;

  var url = id ? (API_BASE + '?resource=produits&id=' + id) : (API_BASE + '?resource=produits');
  var method = id ? 'PUT' : 'POST';

  fetch(url, {
    method: method,
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload),
    credentials: 'same-origin'
  }).then(function(res) {
    if (!res.ok) return res.json().then(function(err) { throw new Error(err.error || 'Erreur'); });
    closeModal();
    loadTable();
  }).catch(function(err) {
    alert(err.message || 'Erreur réseau. Réessayez.');
  });
});

function renderTable(produits) {
  var wrapper = document.getElementById('table-wrapper');
  if (!wrapper) return;
  if (!produits || !Array.isArray(produits)) {
    wrapper.innerHTML = '<div class="empty-state">Erreur.</div>';
    return;
  }
  if (!produits.length) {
    wrapper.innerHTML = '<div class="empty-state">Aucun produit. Cliquez sur « Ajouter un produit ».</div>';
    return;
  }
  cacheStats.produits = produits.slice();
  renderAnalyse();
  wrapper.innerHTML =
    '<table><thead><tr><th>Nom</th><th>Prix partenaire</th><th>Prix client</th><th>Statut</th><th></th></tr></thead><tbody>' +
    produits.map(function(p) {
      return '<tr>' +
        '<td>' + escapeHtml(p.nom) + '</td>' +
        '<td>' + formatPrix(p.prix_partenaire) + ' ' + escapeHtml(p.unite || '€') + '</td>' +
        '<td>' + formatPrix(p.prix_client) + ' ' + escapeHtml(p.unite || '€') + '</td>' +
        '<td><span class="badge ' + (p.actif == 1 ? 'badge-actif' : 'badge-inactif') + '">' + (p.actif == 1 ? 'Actif' : 'Inactif') + '</span></td>' +
        '<td class="actions-cell">' +
          '<button type="button" class="btn btn-secondary edit-btn" data-id="' + p.id + '">Modifier</button> ' +
          '<button type="button" class="btn btn-danger delete-btn" data-id="' + p.id + '">Supprimer</button>' +
        '</td></tr>';
    }).join('') +
    '</tbody></table>';
  wrapper.querySelectorAll('.edit-btn').forEach(function(btn) {
    btn.addEventListener('click', function() { editProduit(Number(btn.getAttribute('data-id'))); });
  });
  wrapper.querySelectorAll('.delete-btn').forEach(function(btn) {
    btn.addEventListener('click', function() { deleteProduit(Number(btn.getAttribute('data-id'))); });
  });
}

function loadTable() {
  var wrapper = document.getElementById('table-wrapper');
  if (!wrapper) return;
  wrapper.innerHTML = '<div class="empty-state">Chargement…</div>';
  fetch(API_BASE + '?resource=produits&admin=1', { credentials: 'same-origin' })
    .then(function(res) {
      if (res.status === 401) { window.location.href = 'login.php'; return null; }
      if (!res.ok) {
        wrapper.innerHTML = '<div class="empty-state">Erreur serveur. Réessayez.</div>';
        return null;
      }
      return res.json();
    })
    .then(function(produits) {
      if (produits === null) return;
      if (!produits || !Array.isArray(produits)) {
        wrapper.innerHTML = '<div class="empty-state">Erreur lors du chargement.</div>';
        return;
      }
      renderTable(produits);
    })
    .catch(function() {
      wrapper.innerHTML = '<div class="empty-state">Erreur réseau. Réessayez.</div>';
    });
}

function editProduit(id) {
  fetch(API_BASE + '?resource=produits&id=' + id, { credentials: 'same-origin' })
    .then(function(res) { return res.json(); })
    .then(function(p) { if (p) openModal(p); })
    .catch(function() { alert('Erreur lors du chargement du produit.'); });
}

function deleteProduit(id) {
  if (!confirm('Supprimer ce produit ? Cette action est irréversible.')) return;
  fetch(API_BASE + '?resource=produits&id=' + id, { method: 'DELETE', credentials: 'same-origin' })
    .then(function(res) {
      if (!res.ok) { alert('Erreur lors de la suppression.'); return; }
      loadTable();
    })
    .catch(function() { alert('Erreur réseau.'); });
}

// ——— Comptes partenaires ———
var modalPartenaire = document.getElementById('modal-partenaire');
var formPartenaire = document.getElementById('form-partenaire');

function openModalPartenaire(role) {
  role = role || 'partenaire';
  var f = document.getElementById('form-partenaire');
  var m = document.getElementById('modal-partenaire');
  var title = document.getElementById('modal-partenaire-title');
  var pr = document.getElementById('p-role');
  if (f) f.reset();
  if (pr) pr.value = role;
  if (title) title.textContent = role === 'client' ? 'Ajouter un client' : 'Ajouter un partenaire';
  if (m) m.classList.add('open');
}

function closeModalPartenaire() {
  var m = document.getElementById('modal-partenaire');
  if (m) m.classList.remove('open');
}

function submitPartenaireForm() {
  var loginEl = document.getElementById('p-login');
  var passEl = document.getElementById('p-password');
  var nomEl = document.getElementById('p-nom');
  var emailEl = document.getElementById('p-email');
  var btn = document.getElementById('btn-create-partenaire');
  if (!loginEl || !passEl) return;
  var login = loginEl.value.trim();
  var password = passEl.value;
  if (!login) {
    alert('Identifiant requis.');
    loginEl.focus();
    return;
  }
  if (!password) {
    alert('Mot de passe requis.');
    passEl.focus();
    return;
  }
  var pr = document.getElementById('p-role');
  var payload = {
    login: login,
    password: password,
    nom: nomEl ? nomEl.value.trim() : '',
    email: emailEl ? emailEl.value.trim() : '',
    role: pr && pr.value ? pr.value : 'partenaire'
  };
  if (btn) btn.disabled = true;
  fetch(API_BASE + '?resource=utilisateurs', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload),
    credentials: 'same-origin'
  }).then(function(res) {
    return res.text().then(function(text) {
      var data;
      try { data = text ? JSON.parse(text) : {}; } catch (e) { data = {}; }
      if (!res.ok) throw new Error(data.error || 'Erreur serveur');
      return data;
    });
  }).then(function() {
    closeModalPartenaire();
    loadPartenaires();
    loadClients();
    var prEl = document.getElementById('p-role');
    var isClient = prEl && prEl.value === 'client';
    alert(isClient ? 'Client créé.' : 'Partenaire créé. Il peut se connecter avec cet identifiant et ce mot de passe.');
  }).catch(function(err) {
    var msg = err.message || 'Erreur. Réessayez ou vérifiez que l\'identifiant n\'est pas déjà utilisé.';
    console.error('Erreur ajout partenaire:', err);
    alert(msg);
  }).then(function() {
    if (btn) btn.disabled = false;
  });
}

if (modalPartenaire) {
  var closeBtn = document.getElementById('modal-partenaire-close');
  var cancelBtn = document.getElementById('modal-partenaire-cancel');
  if (closeBtn) closeBtn.addEventListener('click', closeModalPartenaire);
  if (cancelBtn) cancelBtn.addEventListener('click', closeModalPartenaire);
  modalPartenaire.addEventListener('click', function(e) { if (e.target === modalPartenaire) closeModalPartenaire(); });
}

var btnAddPart = document.getElementById('btn-add-partenaire');
if (btnAddPart) btnAddPart.addEventListener('click', function() { openModalPartenaire('partenaire'); });
var btnAddClient = document.getElementById('btn-add-client');
if (btnAddClient) btnAddClient.addEventListener('click', function() { openModalPartenaire('client'); });

if (formPartenaire) {
  formPartenaire.addEventListener('submit', function(e) {
    e.preventDefault();
    submitPartenaireForm();
  });
}

var btnCreatePartenaire = document.getElementById('btn-create-partenaire');
if (btnCreatePartenaire) {
  btnCreatePartenaire.addEventListener('click', submitPartenaireForm);
}

function changeUserRole(userId, newRole, confirmMsg) {
  if (!confirm(confirmMsg)) return;
  fetch(API_BASE + '?resource=utilisateurs&id=' + userId, {
    method: 'PUT',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ role: newRole }),
    credentials: 'same-origin'
  }).then(function(res) {
    return res.json().then(function(data) {
      if (!res.ok) throw new Error(data.error || 'Erreur');
    });
  }).then(function() {
    loadPartenaires();
    loadClients();
  }).catch(function(err) {
    alert(err.message || 'Erreur');
  });
}

function loadPartenaires() {
  var wrapper = document.getElementById('partenaires-wrapper');
  if (!wrapper) return;
  wrapper.innerHTML = '<div class="empty-state">Chargement…</div>';
  fetch(API_BASE + '?resource=utilisateurs&role=partenaire', { credentials: 'same-origin' })
    .then(function(res) {
      if (res.status === 401) { window.location.href = 'login.php'; return null; }
      return res.json();
    })
    .then(function(list) {
      if (list == null) return;
      if (!Array.isArray(list)) {
        wrapper.innerHTML = '<div class="empty-state">' + (list && list.error ? escapeHtml(list.error) : 'Erreur chargement.') + '</div>';
        return;
      }
      if (!list.length) {
        wrapper.innerHTML = '<div class="empty-state">Aucun compte partenaire. Cliquez sur « Ajouter un partenaire ».</div>';
        return;
      }
      cacheStats.partenaires = list.slice();
      renderAnalyse();
      wrapper.innerHTML = '<div class="table-scroll"><table><thead><tr><th>Identifiant</th><th>Nom</th><th>Email</th><th>Statut</th><th></th></tr></thead><tbody>' +
        list.map(function(p) {
          return '<tr><td>' + escapeHtml(p.login) + '</td><td>' + escapeHtml(p.nom || '—') + '</td><td>' + escapeHtml(p.email || '—') + '</td><td><span class="badge ' + (p.actif == 1 ? 'badge-actif' : 'badge-inactif') + '">' + (p.actif == 1 ? 'Actif' : 'Inactif') + '</span></td>' +
            '<td class="actions-cell"><button type="button" class="btn btn-secondary btn-sm js-role-partenaire" data-user-id="' + p.id + '">Rétrograder en client</button></td></tr>';
        }).join('') +
        '</tbody></table></div>';
      wrapper.querySelectorAll('.js-role-partenaire').forEach(function(btn) {
        btn.addEventListener('click', function() {
          changeUserRole(Number(btn.getAttribute('data-user-id')), 'client', 'Rétrograder ce compte en client ?');
        });
      });
    })
    .catch(function() {
      wrapper.innerHTML = '<div class="empty-state">Erreur chargement partenaires.</div>';
    });
}

function loadClients() {
  var wrapper = document.getElementById('clients-wrapper');
  if (!wrapper) return;
  wrapper.innerHTML = '<div class="empty-state">Chargement…</div>';
  fetch(API_BASE + '?resource=utilisateurs&role=client', { credentials: 'same-origin' })
    .then(function(res) {
      if (res.status === 401) { window.location.href = 'login.php'; return null; }
      return res.json();
    })
    .then(function(list) {
      if (list == null) return;
      if (!Array.isArray(list)) {
        wrapper.innerHTML = '<div class="empty-state">Erreur chargement.</div>';
        return;
      }
      if (!list.length) {
        wrapper.innerHTML = '<div class="empty-state">Aucun compte client. Ils apparaissent après inscription ou via « Ajouter un client ».</div>';
        return;
      }
      cacheStats.clients = list.slice();
      renderAnalyse();
      wrapper.innerHTML = '<div class="table-scroll"><table><thead><tr><th>Identifiant</th><th>Nom</th><th>Email</th><th>Statut</th><th></th></tr></thead><tbody>' +
        list.map(function(p) {
          return '<tr><td>' + escapeHtml(p.login) + '</td><td>' + escapeHtml(p.nom || '—') + '</td><td>' + escapeHtml(p.email || '—') + '</td><td><span class="badge ' + (p.actif == 1 ? 'badge-actif' : 'badge-inactif') + '">' + (p.actif == 1 ? 'Actif' : 'Inactif') + '</span></td>' +
            '<td class="actions-cell"><button type="button" class="btn btn-primary btn-sm js-promote-partenaire" data-user-id="' + p.id + '">Promouvoir partenaire</button></td></tr>';
        }).join('') +
        '</tbody></table></div>';
      wrapper.querySelectorAll('.js-promote-partenaire').forEach(function(btn) {
        btn.addEventListener('click', function() {
          changeUserRole(Number(btn.getAttribute('data-user-id')), 'partenaire', 'Donner à ce compte l’accès aux tarifs partenaires ?');
        });
      });
    })
    .catch(function() {
      wrapper.innerHTML = '<div class="empty-state">Erreur chargement clients.</div>';
    });
}

var TYPE_LABELS = { entree: 'Entrée', sortie: 'Sortie', vente: 'Vente', ajustement: 'Ajustement', transfert: 'Transfert' };

function fillStockFormSelects(data) {
  var selP = document.getElementById('stock-produit');
  var selR = document.getElementById('stock-rayon');
  var selRD = document.getElementById('stock-rayon-dest');
  if (!selP || !selR) return;
  function intVal(x) { return parseInt(x, 10); }

  var prodOptions = [];
  if (data.produits_select && data.produits_select.length) {
    data.produits_select.forEach(function(p) {
      prodOptions.push({ id: intVal(p.id), nom: p.nom });
    });
  } else {
    var seenP = {};
    (data.lignes || []).forEach(function(L) {
      if (!seenP[L.produit_id]) {
        seenP[L.produit_id] = true;
        prodOptions.push({ id: intVal(L.produit_id), nom: L.produit_nom });
      }
    });
  }
  prodOptions.sort(function(a, b) { return String(a.nom).localeCompare(String(b.nom), 'fr'); });
  selP.innerHTML = prodOptions.map(function(p) {
    return '<option value="' + p.id + '">' + escapeHtml(String(p.nom)) + '</option>';
  }).join('');
  if (!prodOptions.length) {
    selP.innerHTML = '<option value="">— Aucun produit géré en stock —</option>';
  }

  var rayOptions = [];
  (data.depots || []).forEach(function(d) {
    (d.rayons || []).forEach(function(r) {
      rayOptions.push({ id: intVal(r.id), label: (d.nom || '') + ' — ' + (r.nom || '') });
    });
  });
  if (!rayOptions.length) {
    var seenR = {};
    (data.lignes || []).forEach(function(L) {
      if (!seenR[L.rayon_id]) {
        seenR[L.rayon_id] = true;
        rayOptions.push({ id: intVal(L.rayon_id), label: L.emplacement });
      }
    });
  }
  rayOptions.sort(function(a, b) { return a.label.localeCompare(b.label, 'fr'); });
  var rayHtml = rayOptions.map(function(r) {
    return '<option value="' + r.id + '">' + escapeHtml(r.label) + '</option>';
  }).join('');
  selR.innerHTML = rayHtml;
  if (selRD) selRD.innerHTML = rayHtml;
  if (!rayOptions.length) {
    var noOpt = '<option value="">— Aucun emplacement —</option>';
    selR.innerHTML = noOpt;
    if (selRD) selRD.innerHTML = noOpt;
  }

  // Stock matrix data for current stock lookup (adjustment page)
  cacheStats.stockLignes = Array.isArray(data.lignes) ? data.lignes.slice() : [];
}

function renderStockMatrix(lignes, wrapper) {
  if (!wrapper) return;
  if (!lignes || !lignes.length) {
    wrapper.innerHTML = '<div class="empty-state">Aucune ligne de stock.</div>';
    return;
  }
  wrapper.innerHTML = '<div class="table-scroll"><table class="table-stock"><thead><tr><th>Produit</th><th>Emplacement</th><th>Qté</th><th>Seuil min</th></tr></thead><tbody>' +
    lignes.map(function(L) {
      var qty = Number(L.quantite);
      var min = Number(L.stock_min || 0);
      var cls = (min > 0 && qty <= min) ? ' class="stock-low"' : '';
      return '<tr' + cls + '><td>' + escapeHtml(L.produit_nom) + '</td><td>' + escapeHtml(L.emplacement) + '</td><td><strong>' + qty + '</strong></td><td>' + (min || '—') + '</td></tr>';
    }).join('') + '</tbody></table></div>';
}

function renderStockHistory(mouvs, wrapper, filterType) {
  if (!wrapper) return;
  var list = Array.isArray(mouvs) ? mouvs.slice() : [];
  if (filterType === 'entree') {
    list = list.filter(function(m) { return m.type === 'entree'; });
  } else if (filterType === 'sortie') {
    list = list.filter(function(m) { return m.type === 'sortie' || m.type === 'vente'; });
  }
  if (!list.length) {
    wrapper.innerHTML = '<div class="empty-state">Aucun mouvement enregistré pour l’instant.</div>';
    return;
  }
  wrapper.innerHTML = '<div class="table-scroll"><table class="table-stock"><thead><tr><th>Date</th><th>Type</th><th>Qté</th><th>Produit</th><th>Source</th><th>Destination</th><th>Utilisateur</th><th>Note</th></tr></thead><tbody>' +
    list.map(function(m) {
      var dt = m.created_at ? String(m.created_at).replace('T', ' ').slice(0, 19) : '';
      var source = escapeHtml(m.depot_nom || '') + ' \u2014 ' + escapeHtml(m.rayon_nom || '');
      var dest = '\u2014';
      if (m.type === 'transfert' && m.depot_dest_nom && m.rayon_dest_nom) {
        dest = escapeHtml(m.depot_dest_nom) + ' \u2014 ' + escapeHtml(m.rayon_dest_nom);
      }
      return '<tr><td>' + escapeHtml(dt) + '</td><td>' + escapeHtml(TYPE_LABELS[m.type] || m.type) + '</td><td>' + m.quantite + '</td><td>' + escapeHtml(m.produit_nom) + '</td><td>' + source + '</td><td>' + dest + '</td><td>' + escapeHtml(m.user_login || '\u2014') + '</td><td>' + escapeHtml(m.commentaire || '\u2014') + '</td></tr>';
    }).join('') + '</tbody></table></div>';
}

function renderStockStats(stats, wrapper) {
  if (!wrapper) return;
  if (!stats) {
    wrapper.innerHTML = '<div class="empty-state">Aucune statistique.</div>';
    return;
  }
  wrapper.innerHTML =
    '<div class="admin-kpis">' +
      '<article class="kpi-card" style="--card-index:0;"><div class="kpi-label">Articles g\u00e9r\u00e9s en stock</div><div class="kpi-value">' + escapeHtml(String(stats.articles_gestion_stock)) + '</div></article>' +
      '<article class="kpi-card" style="--card-index:1;"><div class="kpi-label">En stock (&gt; 0)</div><div class="kpi-value">' + escapeHtml(String(stats.articles_en_stock)) + '</div></article>' +
      '<article class="kpi-card" style="--card-index:2;"><div class="kpi-label">En rupture</div><div class="kpi-value">' + escapeHtml(String(stats.articles_rupture)) + '</div></article>' +
      '<article class="kpi-card" style="--card-index:3;"><div class="kpi-label">Alerte stock bas</div><div class="kpi-value">' + escapeHtml(String(stats.articles_alerte || 0)) + '</div></article>' +
      '<article class="kpi-card" style="--card-index:4;"><div class="kpi-label">Unit\u00e9s (total)</div><div class="kpi-value">' + escapeHtml(String(stats.unites_total)) + '</div></article>' +
    '</div>';
}

function renderStockRupture(resume, wrapper) {
  if (!wrapper) return;
  var rupture = (resume || []).filter(function(r) { return Number(r.total) <= 0; });
  var alerte = (resume || []).filter(function(r) { return Number(r.total) > 0 && Number(r.stock_min) > 0 && Number(r.total) <= Number(r.stock_min); });
  var hasSeuil = rupture.some(function(r) { return Number(r.stock_min) > 0; });
  var h = '';
  if (rupture.length) {
    var seuilTh = hasSeuil ? '<th>Seuil min</th>' : '';
    h += '<div class="table-scroll"><table class="table-stock"><thead><tr><th>Produit</th><th>Stock total</th>' + seuilTh + '<th>Catalogue public</th><th>Vue partenaire</th></tr></thead><tbody>';
    rupture.forEach(function(r) {
      var pub = Number(r.actif) === 1
        ? '<span class="badge badge-actif">Visible</span>'
        : '<span class="badge badge-inactif">Masqué</span>';
      var part = Number(r.visible_partenaire) === 1
        ? '<span class="badge badge-actif">Visible</span>'
        : '<span class="badge badge-inactif">Masqué (rupture)</span>';
      var seuilTd = hasSeuil ? '<td>' + (Number(r.stock_min) > 0 ? Number(r.stock_min) : '—') + '</td>' : '';
      h += '<tr><td>' + escapeHtml(r.produit_nom) + '</td><td><strong>0</strong></td>' + seuilTd + '<td>' + pub + '</td><td>' + part + '</td></tr>';
    });
    h += '</tbody></table></div>';
  }
  if (alerte.length) {
    h += '<h4 class="stock-subtitle" style="margin-top:1rem;">Stock bas (en dessous du seuil)</h4>';
    h += '<div class="table-scroll"><table class="table-stock"><thead><tr><th>Produit</th><th>Stock total</th><th>Seuil min</th><th>Reste</th></tr></thead><tbody>';
    alerte.forEach(function(r) {
      var reste = Number(r.total) - Number(r.stock_min);
      h += '<tr><td>' + escapeHtml(r.produit_nom) + '</td><td>' + Number(r.total) + '</td><td>' + Number(r.stock_min) + '</td><td><span class="badge badge-inactif">' + reste + '</span></td></tr>';
    });
    h += '</tbody></table></div>';
  }
  if (!rupture.length && !alerte.length) {
    h = '<div class="empty-state">Aucun article en rupture ou en alerte.</div>';
  } else {
    h += '<p class="section-hint section-hint--tight">Les articles en rupture restent sur le <strong>catalogue public</strong> s&#39;ils sont actifs, mais sont retirés de l&#39;<strong>espace partenaire</strong> jusqu&#39;à réapprovisionnement.</p>';
  }
  wrapper.innerHTML = h;
}

function cacheStockFromPayload(data) {
  if (!data || data.error) return;
  cacheStats.stockLignes = Array.isArray(data.lignes) ? data.lignes.slice() : [];
  cacheStats.stockMouvements = Array.isArray(data.mouvements) ? data.mouvements.slice() : [];
  renderAnalyse();
}

function loadStockFormPage(historyFilter) {
  var hw = document.getElementById('stock-history-wrapper');
  if (hw) hw.innerHTML = '<div class="empty-state">Chargement…</div>';
  fetch(API_BASE + '?resource=stock', { credentials: 'same-origin' })
    .then(function(res) {
      if (res.status === 401) { window.location.href = 'login.php'; return null; }
      return res.json();
    })
    .then(function(data) {
      if (data == null) return;
      if (data.error) {
        if (hw) hw.innerHTML = '<div class="empty-state">' + escapeHtml(data.error) + '</div>';
        return;
      }
      fillStockFormSelects(data);
      cacheStockFromPayload(data);
      if (hw) renderStockHistory(data.mouvements, hw, historyFilter);
    })
    .catch(function() {
      if (hw) hw.innerHTML = '<div class="empty-state">Erreur réseau.</div>';
    });
}

function loadStockSituationPage() {
  var sw = document.getElementById('stock-stats-wrapper');
  var rw = document.getElementById('stock-rupture-wrapper');
  var mw = document.getElementById('stock-matrix-wrapper');
  var hw = document.getElementById('stock-history-wrapper');
  if (sw) sw.innerHTML = '<div class="empty-state">Chargement…</div>';
  if (rw) rw.innerHTML = '<div class="empty-state">Chargement…</div>';
  if (mw) mw.innerHTML = '<div class="empty-state">Chargement…</div>';
  if (hw) hw.innerHTML = '<div class="empty-state">Chargement…</div>';
  fetch(API_BASE + '?resource=stock', { credentials: 'same-origin' })
    .then(function(res) {
      if (res.status === 401) { window.location.href = 'login.php'; return null; }
      return res.json();
    })
    .then(function(data) {
      if (data == null) return;
      if (data.error) {
        var msg = '<div class="empty-state">' + escapeHtml(data.error) + '</div>';
        if (sw) sw.innerHTML = msg;
        return;
      }
      cacheStockFromPayload(data);
      renderStockStats(data.statistiques, sw);
      renderStockRupture(data.resume, rw);
      renderStockMatrix(data.lignes, mw);
      renderStockHistory(data.mouvements, hw, null);
    })
    .catch(function() {
      if (mw) mw.innerHTML = '<div class="empty-state">Erreur réseau.</div>';
    });
}

function reloadCurrentStockView() {
  var p = (document.body && document.body.getAttribute('data-admin-page')) || '';
  if (p === 'stock-entree') loadStockFormPage('entree');
  else if (p === 'stock-sortie') loadStockFormPage('sortie');
  else if (p === 'stock-situation') loadStockSituationPage();
  else if (p === 'stock-transfert') loadStockTransfertPage();
  else if (p === 'stock-ajustement') loadStockAjustementPage();
  else if (p === 'stock-lieux') loadStockLieuxPage();
}

var formStock = document.getElementById('form-stock');
if (formStock) {
  formStock.addEventListener('submit', function(e) {
    e.preventDefault();
    var pid = document.getElementById('stock-produit');
    var rid = document.getElementById('stock-rayon');
    var typ = document.getElementById('stock-type');
    var qty = document.getElementById('stock-qty');
    var com = document.getElementById('stock-comment');
    if (!pid || !rid || !typ || !qty) return;
    var payload = {
      produit_id: parseInt(pid.value, 10),
      rayon_id: parseInt(rid.value, 10),
      type: typ.value,
      quantite: parseInt(qty.value, 10),
      commentaire: com ? com.value.trim() : ''
    };
    if (!payload.produit_id || !payload.rayon_id) {
      alert('Choisissez un produit et un emplacement.');
      return;
    }
    fetch(API_BASE + '?resource=stock', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
      credentials: 'same-origin'
    }).then(function(res) {
      return res.json().then(function(data) {
        if (!res.ok) throw new Error(data.error || 'Erreur');
      });
    }).then(function() {
      reloadCurrentStockView();
      if (qty) qty.value = '1';
      if (com) com.value = '';
    }).catch(function(err) {
      alert(err.message || 'Erreur');
    });
  });
}

function formatPrix(n) {
  var x = Number(n);
  if (isNaN(x)) return '—';
  var entier = Math.round(x);
  return new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0, minimumFractionDigits: 0 }).format(entier);
}

function escapeHtml(s) {
  var div = document.createElement('div');
  div.textContent = s;
  return div.innerHTML;
}

// ——— Stock alert badge ———
function loadStockAlertBadge() {
  fetch(API_BASE + '?resource=stock', { credentials: 'same-origin' })
    .then(function(res) { return res.json(); })
    .then(function(data) {
      var badge = document.getElementById('stock-alert-badge');
      if (!badge || !data.alertes) return;
      var n = data.alertes.length;
      if (n > 0) {
        badge.textContent = n;
        badge.style.display = '';
      } else {
        badge.style.display = 'none';
      }
    })
    .catch(function() {});
}
loadStockAlertBadge();

// ——— Stock lieux (depots & rayons) ———
function loadStockLieuxPage() {
  var wrap = document.getElementById('lieux-wrapper');
  if (!wrap) return;
  wrap.innerHTML = '<div class="empty-state">Chargement&#8230;</div>';
  fetch(API_BASE + '?resource=stock', { credentials: 'same-origin' })
    .then(function(res) { if (res.status === 401) { window.location.href = 'login.php'; return null; } return res.json(); })
    .then(function(data) {
      if (!data || data.error) { wrap.innerHTML = '<div class="empty-state">' + escapeHtml((data && data.error) || 'Erreur') + '</div>'; return; }
      renderLieuxUI(data.depots, wrap);
    })
    .catch(function() { wrap.innerHTML = '<div class="empty-state">Erreur réseau.</div>'; });
}

function renderLieuxUI(depots, wrapper) {
  if (!depots || !depots.length) {
    wrapper.innerHTML = '<div class="empty-state">Aucun dépôt configuré.</div>';
    return;
  }
  var h = '<div class="lieux-grid">';
  h += '<div class="lieux-col"><h3 class="stock-subtitle">Dépôts</h3>';
  h += '<div id="depot-list">';
  depots.forEach(function(d) {
    h += '<div class="lieux-card" data-depot-id="' + d.id + '">';
    h += '<div class="lieux-card__header"><strong>' + escapeHtml(d.nom) + '</strong>';
    if (d.code) h += ' <span class="badge badge-code">' + escapeHtml(d.code) + '</span>';
    h += '</div>';
    h += '<div class="lieux-card__actions">';
    h += '<button class="btn btn-secondary btn-sm" onclick="editDepot(' + d.id + ',\'' + escapeHtml(d.nom).replace(/'/g, "\\'") + '\',\'' + escapeHtml(d.code || '').replace(/'/g, "\\'") + '\',' + d.ordre + ')">Modifier</button>';
    h += '<button class="btn btn-danger btn-sm" onclick="deleteDepotConfirm(' + d.id + ',\'' + escapeHtml(d.nom).replace(/'/g, "\\'") + '\')">Supprimer</button>';
    h += '</div></div>';
  });
  h += '</div>';
  h += '<div class="lieux-add"><form id="form-add-depot" class="form-inline"><input type="text" id="depot-nom" placeholder="Nom du dépôt" required> <input type="text" id="depot-code" placeholder="Code"> <button type="submit" class="btn btn-primary btn-sm">Ajouter</button></form></div>';
  h += '</div>';

  h += '<div class="lieux-col"><h3 class="stock-subtitle">Rayons</h3>';
  h += '<div class="form-group"><label for="lieux-depot-filter">Filtrer par dépôt</label><select id="lieux-depot-filter">';
  depots.forEach(function(d) { h += '<option value="' + d.id + '">' + escapeHtml(d.nom) + '</option>'; });
  h += '</select></div>';
  h += '<div id="rayon-list">';
  var firstDepot = depots[0];
  (firstDepot.rayons || []).forEach(function(r) {
    h += renderRayonCard(r, firstDepot.nom);
  });
  h += '</div>';
  h += '<div class="lieux-add"><form id="form-add-rayon" class="form-inline"><input type="hidden" id="rayon-depot-id" value="' + firstDepot.id + '"> <input type="text" id="rayon-nom" placeholder="Nom du rayon" required> <input type="text" id="rayon-code" placeholder="Code"> <button type="submit" class="btn btn-primary btn-sm">Ajouter</button></form></div>';
  h += '</div></div>';

  wrapper.innerHTML = h;

  // Bind add depot form
  var fDepot = document.getElementById('form-add-depot');
  if (fDepot) fDepot.addEventListener('submit', function(e) {
    e.preventDefault();
    var nom = document.getElementById('depot-nom').value.trim();
    var code = document.getElementById('depot-code').value.trim();
    if (!nom) return;
    fetch(API_BASE + '?resource=stock', { method: 'POST', headers: { 'Content-Type': 'application/json' }, credentials: 'same-origin', body: JSON.stringify({ action: 'create-depot', nom: nom, code: code, ordre: 0 }) })
      .then(function(r) { return r.json().then(function(d) { if (!r.ok) throw new Error(d.error || 'Erreur'); }); })
      .then(function() { loadStockLieuxPage(); })
      .catch(function(err) { alert(err.message); });
  });

  // Bind depot filter
  var filter = document.getElementById('lieux-depot-filter');
  if (filter) filter.addEventListener('change', function() {
    var did = parseInt(this.value, 10);
    var dep = (depots || []).find(function(d) { return d.id === did; });
    var rl = document.getElementById('rayon-list');
    var di = document.getElementById('rayon-depot-id');
    if (di) di.value = did;
    if (rl && dep) {
      rl.innerHTML = '';
      (dep.rayons || []).forEach(function(r) { rl.innerHTML += renderRayonCard(r, dep.nom); });
    }
  });

  // Bind add rayon form
  var fRayon = document.getElementById('form-add-rayon');
  if (fRayon) fRayon.addEventListener('submit', function(e) {
    e.preventDefault();
    var did = document.getElementById('rayon-depot-id').value;
    var nom = document.getElementById('rayon-nom').value.trim();
    var code = document.getElementById('rayon-code').value.trim();
    if (!nom) return;
    fetch(API_BASE + '?resource=stock', { method: 'POST', headers: { 'Content-Type': 'application/json' }, credentials: 'same-origin', body: JSON.stringify({ action: 'create-rayon', depot_id: parseInt(did, 10), nom: nom, code: code, ordre: 0 }) })
      .then(function(r) { return r.json().then(function(d) { if (!r.ok) throw new Error(d.error || 'Erreur'); }); })
      .then(function() { loadStockLieuxPage(); })
      .catch(function(err) { alert(err.message); });
  });
}

function renderRayonCard(r, depotNom) {
  var h = '<div class="lieux-card">';
  h += '<div class="lieux-card__header"><strong>' + escapeHtml(r.nom) + '</strong>';
  if (r.code) h += ' <span class="badge badge-code">' + escapeHtml(r.code) + '</span>';
  h += ' <span class="lieux-card__depot">' + escapeHtml(depotNom) + '</span></div>';
  h += '<div class="lieux-card__actions">';
  h += '<button class="btn btn-secondary btn-sm" onclick="editRayon(' + r.id + ',' + r.depot_id + ',\'' + escapeHtml(r.nom).replace(/'/g, "\\'") + '\',\'' + escapeHtml(r.code || '').replace(/'/g, "\\'") + '\',' + r.ordre + ')">Modifier</button>';
  h += '<button class="btn btn-danger btn-sm" onclick="deleteRayonConfirm(' + r.id + ',\'' + escapeHtml(r.nom).replace(/'/g, "\\'") + '\')">Supprimer</button>';
  h += '</div></div>';
  return h;
}

function editDepot(id, nom, code, ordre) {
  var newNom = prompt('Nom du dépôt :', nom);
  if (newNom === null) return;
  var newCode = prompt('Code :', code || '');
  fetch(API_BASE + '?resource=stock', { method: 'POST', headers: { 'Content-Type': 'application/json' }, credentials: 'same-origin', body: JSON.stringify({ action: 'update-depot', id: id, nom: newNom, code: newCode || '', ordre: ordre }) })
    .then(function(r) { return r.json().then(function(d) { if (!r.ok) throw new Error(d.error || 'Erreur'); }); })
    .then(function() { loadStockLieuxPage(); })
    .catch(function(err) { alert(err.message); });
}

function deleteDepotConfirm(id, nom) {
  if (!confirm('Supprimer le dépôt « ' + nom + ' » et tous ses rayons ?')) return;
  fetch(API_BASE + '?resource=stock', { method: 'POST', headers: { 'Content-Type': 'application/json' }, credentials: 'same-origin', body: JSON.stringify({ action: 'delete-depot', id: id }) })
    .then(function(r) { return r.json().then(function(d) { if (!r.ok) throw new Error(d.error || 'Erreur'); }); })
    .then(function() { loadStockLieuxPage(); })
    .catch(function(err) { alert(err.message); });
}

function editRayon(id, depotId, nom, code, ordre) {
  var newNom = prompt('Nom du rayon :', nom);
  if (newNom === null) return;
  var newCode = prompt('Code :', code || '');
  fetch(API_BASE + '?resource=stock', { method: 'POST', headers: { 'Content-Type': 'application/json' }, credentials: 'same-origin', body: JSON.stringify({ action: 'update-rayon', id: id, depot_id: depotId, nom: newNom, code: newCode || '', ordre: ordre }) })
    .then(function(r) { return r.json().then(function(d) { if (!r.ok) throw new Error(d.error || 'Erreur'); }); })
    .then(function() { loadStockLieuxPage(); })
    .catch(function(err) { alert(err.message); });
}

function deleteRayonConfirm(id, nom) {
  if (!confirm('Supprimer le rayon « ' + nom + ' » ?')) return;
  fetch(API_BASE + '?resource=stock', { method: 'POST', headers: { 'Content-Type': 'application/json' }, credentials: 'same-origin', body: JSON.stringify({ action: 'delete-rayon', id: id }) })
    .then(function(r) { return r.json().then(function(d) { if (!r.ok) throw new Error(d.error || 'Erreur'); }); })
    .then(function() { loadStockLieuxPage(); })
    .catch(function(err) { alert(err.message); });
}

// ——— Stock transfert ———
function loadStockTransfertPage() {
  var hw = document.getElementById('stock-history-wrapper');
  if (hw) hw.innerHTML = '<div class="empty-state">Chargement&#8230;</div>';
  fetch(API_BASE + '?resource=stock', { credentials: 'same-origin' })
    .then(function(res) { if (res.status === 401) { window.location.href = 'login.php'; return null; } return res.json(); })
    .then(function(data) {
      if (!data) return;
      if (data.error) { if (hw) hw.innerHTML = '<div class="empty-state">' + escapeHtml(data.error) + '</div>'; return; }
      fillStockFormSelects(data);
      var mouvs = (data.mouvements || []).filter(function(m) { return m.type === 'transfert'; });
      if (hw) renderStockHistory(mouvs, hw, null);
    })
    .catch(function() { if (hw) hw.innerHTML = '<div class="empty-state">Erreur réseau.</div>'; });

  // Override form submit for transfert
  var fs = document.getElementById('form-stock');
  if (fs) fs.onsubmit = function(e) {
    e.preventDefault();
    var pid = document.getElementById('stock-produit');
    var srcR = document.getElementById('stock-rayon');
    var dstR = document.getElementById('stock-rayon-dest');
    var qty = document.getElementById('stock-qty');
    var com = document.getElementById('stock-comment');
    if (!pid || !srcR || !dstR || !qty) return;
    var p = { action: 'transfer', produit_id: parseInt(pid.value, 10), rayon_id: parseInt(srcR.value, 10), rayon_dest_id: parseInt(dstR.value, 10), quantite: parseInt(qty.value, 10), commentaire: com ? com.value.trim() : '' };
    if (!p.produit_id || !p.rayon_id || !p.rayon_dest_id) { alert('Remplissez tous les champs.'); return; }
    if (p.rayon_id === p.rayon_dest_id) { alert('Les rayons source et destination doivent être différents.'); return; }
    fetch(API_BASE + '?resource=stock', { method: 'POST', headers: { 'Content-Type': 'application/json' }, credentials: 'same-origin', body: JSON.stringify(p) })
      .then(function(r) { return r.json().then(function(d) { if (!r.ok) throw new Error(d.error || 'Erreur'); }); })
      .then(function() { loadStockTransfertPage(); })
      .catch(function(err) { alert(err.message); });
  };
}

// ——— Stock ajustement ———
function loadStockAjustementPage() {
  var hw = document.getElementById('stock-history-wrapper');
  if (hw) hw.innerHTML = '<div class="empty-state">Chargement&#8230;</div>';
  fetch(API_BASE + '?resource=stock', { credentials: 'same-origin' })
    .then(function(res) { if (res.status === 401) { window.location.href = 'login.php'; return null; } return res.json(); })
    .then(function(data) {
      if (!data) return;
      if (data.error) { if (hw) hw.innerHTML = '<div class="empty-state">' + escapeHtml(data.error) + '</div>'; return; }
      fillStockFormSelects(data);
      var mouvs = (data.mouvements || []).filter(function(m) { return m.type === 'ajustement'; });
      if (hw) renderStockHistory(mouvs, hw, null);

      // Show current stock when product/rayon changes
      updateCurrentStockDisplay();
    })
    .catch(function() { if (hw) hw.innerHTML = '<div class="empty-state">Erreur réseau.</div>'; });

  var selP = document.getElementById('stock-produit');
  var selR = document.getElementById('stock-rayon');
  if (selP) selP.addEventListener('change', updateCurrentStockDisplay);
  if (selR) selR.addEventListener('change', updateCurrentStockDisplay);

  // Override form submit for ajustement
  var fs = document.getElementById('form-stock');
  if (fs) fs.onsubmit = function(e) {
    e.preventDefault();
    var pid = document.getElementById('stock-produit');
    var rid = document.getElementById('stock-rayon');
    var qty = document.getElementById('stock-qty');
    var com = document.getElementById('stock-comment');
    if (!pid || !rid || !qty) return;
    var p = { action: 'adjust', produit_id: parseInt(pid.value, 10), rayon_id: parseInt(rid.value, 10), quantite: parseInt(qty.value, 10), commentaire: com ? com.value.trim() : '' };
    if (!p.produit_id || !p.rayon_id) { alert('Choisissez un produit et un emplacement.'); return; }
    fetch(API_BASE + '?resource=stock', { method: 'POST', headers: { 'Content-Type': 'application/json' }, credentials: 'same-origin', body: JSON.stringify(p) })
      .then(function(r) { return r.json().then(function(d) { if (!r.ok) throw new Error(d.error || 'Erreur'); }); })
      .then(function() { loadStockAjustementPage(); })
      .catch(function(err) { alert(err.message); });
  };
}

function updateCurrentStockDisplay() {
  var pid = document.getElementById('stock-produit');
  var rid = document.getElementById('stock-rayon');
  var cur = document.getElementById('stock-current');
  if (!pid || !rid || !cur) return;
  var pVal = parseInt(pid.value, 10);
  var rVal = parseInt(rid.value, 10);
  var found = (cacheStats.stockLignes || []).find(function(l) {
    return parseInt(l.produit_id, 10) === pVal && parseInt(l.rayon_id, 10) === rVal;
  });
  cur.value = found ? found.quantite : '0';
}

// ——— Paramètres ———
function loadParametresPage() {
  var wrap = document.getElementById('settings-wrapper');
  if (!wrap) return;
  wrap.innerHTML = '<div class="empty-state">Chargement&#8230;</div>';
  fetch(API_BASE + '?resource=parametres', { credentials: 'same-origin' })
    .then(function(res) { if (res.status === 401) { window.location.href = 'login.php'; return null; } return res.json(); })
    .then(function(data) {
      if (!data) return;
      renderParametresUI(data, wrap);
    })
    .catch(function() { wrap.innerHTML = '<div class="empty-state">Erreur réseau.</div>'; });
}

function renderParametresUI(params, wrapper) {
  var logoSrc = params.entreprise_logo || '';
  var h = '<div class="settings-tabs">';
  h += '<button class="settings-tab settings-tab--active" data-tab="entreprise">Entreprise</button>';
  h += '<button class="settings-tab" data-tab="facturation">Facturation</button>';
  h += '<button class="settings-tab" data-tab="stock">Stock</button>';
  h += '</div>';

  // Entreprise tab
  h += '<div class="settings-section" id="tab-entreprise">';
  h += '<div class="logo-upload"><label>Logo de l&#8217;entreprise</label>';
  if (logoSrc) h += '<div class="logo-preview"><img src="' + escapeHtml(logoSrc) + '" alt="Logo"></div>';
  h += '<form id="form-logo" enctype="multipart/form-data"><input type="file" name="logo" accept="image/*"> <button type="submit" class="btn btn-secondary btn-sm">Uploader</button></form></div>';
  h += '<div class="form-group"><label for="p-entreprise_nom">Nom</label><input type="text" id="p-entreprise_nom" value="' + escapeHtml(params.entreprise_nom || '') + '"></div>';
  h += '<div class="form-group"><label for="p-entreprise_adresse">Adresse</label><input type="text" id="p-entreprise_adresse" value="' + escapeHtml(params.entreprise_adresse || '') + '"></div>';
  h += '<div class="form-group"><label for="p-entreprise_telephone">Téléphone</label><input type="text" id="p-entreprise_telephone" value="' + escapeHtml(params.entreprise_telephone || '') + '"></div>';
  h += '<div class="form-group"><label for="p-entreprise_email">Email</label><input type="email" id="p-entreprise_email" value="' + escapeHtml(params.entreprise_email || '') + '"></div>';
  h += '</div>';

  // Facturation tab
  h += '<div class="settings-section is-hidden" id="tab-facturation">';
  h += '<div class="form-group"><label for="p-facture_prefixe">Préfixe facture</label><input type="text" id="p-facture_prefixe" value="' + escapeHtml(params.facture_prefixe || 'FAC') + '"></div>';
  h += '<div class="form-group"><label for="p-facture_prochain_num">Prochain numéro</label><input type="number" id="p-facture_prochain_num" value="' + escapeHtml(params.facture_prochain_num || '1') + '" min="1"></div>';
  h += '<div class="form-group"><label for="p-facture_tva_defaut">TVA par défaut (%)</label><input type="number" id="p-facture_tva_defaut" value="' + escapeHtml(params.facture_tva_defaut || '0') + '" min="0" max="100" step="0.1"></div>';
  h += '<div class="form-group"><label for="p-devise">Devise</label><input type="text" id="p-devise" value="' + escapeHtml(params.devise || 'FCFA') + '"></div>';
  h += '</div>';

  // Stock tab
  h += '<div class="settings-section is-hidden" id="tab-stock">';
  h += '<div class="form-group"><label for="p-stock_alerte_actif">Alertes stock bas</label><select id="p-stock_alerte_actif"><option value="1"' + (params.stock_alerte_actif === '1' ? ' selected' : '') + '>Activées</option><option value="0"' + (params.stock_alerte_actif !== '1' ? ' selected' : '') + '>Désactivées</option></select></div>';
  h += '<div class="form-group"><label for="p-stock_seuil_defaut">Seuil par défaut</label><input type="number" id="p-stock_seuil_defaut" value="' + escapeHtml(params.stock_seuil_defaut || '5') + '" min="0"></div>';
  h += '</div>';

  h += '<button class="btn btn-primary" id="btn-save-settings">Enregistrer les paramètres</button>';
  wrapper.innerHTML = h;

  // Tab switching
  wrapper.querySelectorAll('.settings-tab').forEach(function(tab) {
    tab.addEventListener('click', function() {
      wrapper.querySelectorAll('.settings-tab').forEach(function(t) { t.classList.remove('settings-tab--active'); });
      wrapper.querySelectorAll('.settings-section').forEach(function(s) { s.classList.add('is-hidden'); });
      tab.classList.add('settings-tab--active');
      var target = document.getElementById('tab-' + tab.getAttribute('data-tab'));
      if (target) target.classList.remove('is-hidden');
    });
  });

  // Logo upload form
  var fLogo = document.getElementById('form-logo');
  if (fLogo) fLogo.addEventListener('submit', function(e) {
    e.preventDefault();
    var fd = new FormData(fLogo);
    fetch(API_BASE + '?resource=parametres', { method: 'POST', credentials: 'same-origin', body: fd })
      .then(function(r) { return r.json().then(function(d) { if (!r.ok) throw new Error(d.error || 'Erreur'); }); })
      .then(function() { loadParametresPage(); })
      .catch(function(err) { alert(err.message); });
  });

  // Save all settings
  var btnSave = document.getElementById('btn-save-settings');
  if (btnSave) btnSave.addEventListener('click', function() {
    var pairs = {
      entreprise_nom: (document.getElementById('p-entreprise_nom') || {}).value || '',
      entreprise_adresse: (document.getElementById('p-entreprise_adresse') || {}).value || '',
      entreprise_telephone: (document.getElementById('p-entreprise_telephone') || {}).value || '',
      entreprise_email: (document.getElementById('p-entreprise_email') || {}).value || '',
      facture_prefixe: (document.getElementById('p-facture_prefixe') || {}).value || 'FAC',
      facture_prochain_num: (document.getElementById('p-facture_prochain_num') || {}).value || '1',
      facture_tva_defaut: (document.getElementById('p-facture_tva_defaut') || {}).value || '0',
      devise: (document.getElementById('p-devise') || {}).value || 'FCFA',
      stock_alerte_actif: (document.getElementById('p-stock_alerte_actif') || {}).value || '1',
      stock_seuil_defaut: (document.getElementById('p-stock_seuil_defaut') || {}).value || '5',
      site_nom: params.site_nom || 'ESADISS Partenaire'
    };
    fetch(API_BASE + '?resource=parametres', { method: 'POST', headers: { 'Content-Type': 'application/json' }, credentials: 'same-origin', body: JSON.stringify(pairs) })
      .then(function(r) { return r.json().then(function(d) { if (!r.ok) throw new Error(d.error || 'Erreur'); }); })
      .then(function() { alert('Paramètres enregistrés'); })
      .catch(function(err) { alert(err.message); });
  });
}

// ——— Facturation ———
function loadFacturationPage() {
  var sw = document.getElementById('facture-stats-wrapper');
  var lw = document.getElementById('facture-list-wrapper');
  var ow = document.getElementById('facture-overdue-wrapper');
  if (sw) sw.innerHTML = '<div class="empty-state">Chargement&#8230;</div>';
  if (lw) lw.innerHTML = '<div class="empty-state">Chargement&#8230;</div>';

  // Load stats
  fetch(API_BASE + '?resource=factures&action=stats', { credentials: 'same-origin' })
    .then(function(r) { return r.json(); })
    .then(function(stats) {
      if (stats && !stats.error) renderFactureStats(stats, sw);
    })
    .catch(function() {});

  // Load overdue
  fetch(API_BASE + '?resource=factures&action=overdue', { credentials: 'same-origin' })
    .then(function(r) { return r.json(); })
    .then(function(overdue) {
      if (overdue && overdue.length && ow) {
        ow.innerHTML = '<div class="stock-alert"><strong>Impayés en retard</strong> : ' + overdue.length + ' facture(s) dont l&#8217;échéance est dépassée.</div>';
      }
    })
    .catch(function() {});

  // Load factures list
  fetch(API_BASE + '?resource=factures', { credentials: 'same-origin' })
    .then(function(r) { return r.json(); })
    .then(function(factures) {
      if (!factures || factures.error) { if (lw) lw.innerHTML = '<div class="empty-state">' + escapeHtml((factures && factures.error) || 'Erreur') + '</div>'; return; }
      renderFacturesList(factures, lw);
    })
    .catch(function() { if (lw) lw.innerHTML = '<div class="empty-state">Erreur réseau.</div>'; });
}

function renderFactureStats(stats, wrapper) {
  if (!wrapper) return;
  wrapper.innerHTML =
    '<div class="admin-kpis">' +
    '<article class="kpi-card" style="--card-index:0;"><div class="kpi-label">Factures du mois</div><div class="kpi-value">' + stats.factures_mois + '</div></article>' +
    '<article class="kpi-card" style="--card-index:1;"><div class="kpi-label">Montant facturé</div><div class="kpi-value">' + formatPrix(stats.montant_mois) + '</div></article>' +
    '<article class="kpi-card" style="--card-index:2;"><div class="kpi-label">Encaissé</div><div class="kpi-value">' + formatPrix(stats.paye) + '</div></article>' +
    '<article class="kpi-card" style="--card-index:3;"><div class="kpi-label">Reste à payer</div><div class="kpi-value">' + formatPrix(stats.reste) + '</div></article>' +
    '<article class="kpi-card" style="--card-index:4;"><div class="kpi-label">Impayés</div><div class="kpi-value">' + formatPrix(stats.impaye) + '</div></article>' +
    '</div>';
}

var STATUT_LABELS = { brouillon: 'Brouillon', envoyee: 'Envoyée', payee_partiellement: 'Payée partiellement', payee: 'Payée', annulee: 'Annulée' };
var STATUT_CLASSES = { brouillon: 'statut-brouillon', envoyee: 'statut-envoyee', payee_partiellement: 'statut-partiel', payee: 'statut-payee', annulee: 'statut-annulee' };

function renderFacturesList(factures, wrapper) {
  if (!wrapper) return;
  if (!factures || !factures.length) {
    wrapper.innerHTML = '<div class="empty-state">Aucune facture. Cliquez sur « Nouvelle facture » pour commencer.</div>';
    return;
  }
  var h = '<div class="table-scroll"><table class="table-stock"><thead><tr><th>Numéro</th><th>Client / Partenaire</th><th>Date</th><th>Échéance</th><th>Total TTC</th><th>Payé</th><th>Statut</th><th>Actions</th></tr></thead><tbody>';
  factures.forEach(function(f) {
    var sClass = STATUT_CLASSES[f.statut] || '';
    h += '<tr>';
    h += '<td><strong>' + escapeHtml(f.numero) + '</strong></td>';
    h += '<td>' + escapeHtml(f.client_nom || f.client_login || '—') + '</td>';
    h += '<td>' + escapeHtml(f.date_facture || '') + '</td>';
    h += '<td>' + escapeHtml(f.echeance || '—') + '</td>';
    h += '<td>' + formatPrix(f.montant_ttc) + '</td>';
    h += '<td>' + formatPrix(f.montant_paye) + '</td>';
    h += '<td><span class="facture-statut ' + sClass + '">' + escapeHtml(STATUT_LABELS[f.statut] || f.statut) + '</span></td>';
    h += '<td><a href="admin-facture-view.php?id=' + f.id + '" class="btn btn-secondary btn-sm">Voir</a> <a href="facture-pdf.php?id=' + f.id + '" class="btn btn-secondary btn-sm" target="_blank">PDF</a></td>';
    h += '</tr>';
  });
  h += '</tbody></table></div>';
  wrapper.innerHTML = h;
}

// ——— Facture edit ———
function loadFactureEditPage() {
  var rawId = (document.getElementById('facture-id') || {}).value || '';
  var editId = (rawId && parseInt(rawId, 10) > 0) ? rawId : '';
  var clients = [];
  var produits = [];

  // Load clients and partenaires
  Promise.all([
    fetch(API_BASE + '?resource=utilisateurs&role=client', { credentials: 'same-origin' }).then(function(r) { return r.json(); }),
    fetch(API_BASE + '?resource=utilisateurs&role=partenaire', { credentials: 'same-origin' }).then(function(r) { return r.json(); })
  ])
    .then(function(results) {
      var clientsList = Array.isArray(results[0]) ? results[0] : [];
      var partenairesList = Array.isArray(results[1]) ? results[1] : [];
      clients = clientsList.concat(partenairesList);
      // Sort by name
      clients.sort(function(a, b) { return (a.nom || a.login || '').localeCompare(b.nom || b.login || '', 'fr'); });
      var sel = document.getElementById('facture-client');
      if (sel) {
        sel.innerHTML = '<option value="">— Sélectionner —</option>' +
          clients.map(function(c) {
            var label = escapeHtml(c.nom || c.login) + (c.role === 'partenaire' ? ' (Partenaire)' : '');
            return '<option value="' + c.id + '" data-role="' + c.role + '">' + label + '</option>';
          }).join('');
        // When client/partner changes, refresh all line prices based on role
        sel.addEventListener('change', function() {
          refreshAllLignePrices();
        });
      }
      if (editId) loadFactureForEdit(editId);
    })
    .catch(function() {});

  // Load products for line item auto-fill
  fetch(API_BASE + '?resource=produits&admin=1', { credentials: 'same-origin' })
    .then(function(r) { return r.json(); })
    .then(function(data) {
      produits = Array.isArray(data) ? data : [];
    })
    .catch(function() {});

  // Load next numero
  if (!editId) {
    fetch(API_BASE + '?resource=factures&action=next-numero', { credentials: 'same-origin' })
      .then(function(r) { return r.json(); })
      .then(function(d) {
        var numField = document.getElementById('facture-numero');
        if (numField && d.numero) numField.value = d.numero;
      })
      .catch(function() {});
    var dateField = document.getElementById('facture-date');
    if (dateField) dateField.value = new Date().toISOString().slice(0, 10);
  }

  // Load default TVA
  fetch(API_BASE + '?resource=parametres', { credentials: 'same-origin' })
    .then(function(r) { return r.json(); })
    .then(function(p) {
      var tvaField = document.getElementById('facture-taux-tva');
      if (tvaField && p.facture_tva_defaut) tvaField.value = p.facture_tva_defaut;
    })
    .catch(function() {});

  // Add line button
  var btnAddLigne = document.getElementById('btn-add-ligne');
  if (btnAddLigne) btnAddLigne.addEventListener('click', function() { addFactureLigne(produits); });

  // Add first line
  setTimeout(function() { addFactureLigne(produits); }, 300);

  // TVA change handler
  var tvaInput = document.getElementById('facture-taux-tva');
  if (tvaInput) tvaInput.addEventListener('input', computeFactureTotals);

  // Form submit
  var fFacture = document.getElementById('form-facture');
  if (fFacture) fFacture.addEventListener('submit', function(e) {
    e.preventDefault();
    var currentEditId = editId;
    saveFacture(currentEditId, produits);
  });
}

function addFactureLigne(produits) {
  var wrap = document.getElementById('facture-lignes-wrapper');
  if (!wrap) return;
  var idx = wrap.children.length;
  var div = document.createElement('div');
  div.className = 'facture-ligne';
  div.setAttribute('data-ligne-idx', idx);

  var prodSelect = '<select class="ligne-produit" onchange="onLigneProduitChange(this)"><option value="">— S\u00e9lectionner un produit —</option>';
  (produits || []).forEach(function(p) {
    var epuise = (Number(p.gestion_stock) === 1 && Number(p.stock_total) <= 0);
    var label = escapeHtml(p.nom) + (epuise ? ' \u26a0 \u00c9puis\u00e9' : '');
    var disabled = epuise ? ' disabled' : '';
    prodSelect += '<option value="' + p.id + '" data-prix="' + (p.prix_client || 0) + '" data-prix-partenaire="' + (p.prix_partenaire || 0) + '" data-nom="' + escapeHtml(p.nom).replace(/"/g, '&quot;') + '"' + disabled + '>' + label + '</option>';
  });
  prodSelect += '</select>';

  div.innerHTML = '<div class="facture-ligne__fields">' +
    '<div class="form-group">' + prodSelect + '</div>' +
    '<div class="form-group"><input type="text" class="ligne-designation" placeholder="Désignation"></div>' +
    '<div class="form-group"><input type="number" class="ligne-qty" min="0" step="1" value="1" onchange="computeFactureTotals()"></div>' +
    '<div class="form-group"><input type="number" class="ligne-prix" min="0" step="0.01" value="0" onchange="computeFactureTotals()"></div>' +
    '<div class="form-group ligne-total">0</div>' +
    '<button type="button" class="btn btn-danger btn-sm" onclick="this.closest(\'.facture-ligne\').remove();computeFactureTotals();">✕</button>' +
    '</div>';
  wrap.appendChild(div);
}

function onLigneProduitChange(sel) {
  var ligne = sel.closest('.facture-ligne');
  if (!ligne) return;
  var opt = sel.options[sel.selectedIndex];
  if (opt && opt.value) {
    ligne.querySelector('.ligne-designation').value = opt.getAttribute('data-nom') || '';
    var role = getSelectedClientRole();
    var prixAttr = (role === 'partenaire') ? 'data-prix-partenaire' : 'data-prix';
    ligne.querySelector('.ligne-prix').value = opt.getAttribute(prixAttr) || '0';
  }
  computeFactureTotals();
}

function getSelectedClientRole() {
  var sel = document.getElementById('facture-client');
  if (!sel) return 'client';
  var opt = sel.options[sel.selectedIndex];
  return (opt && opt.getAttribute('data-role')) || 'client';
}

function refreshAllLignePrices() {
  var role = getSelectedClientRole();
  var lignes = document.querySelectorAll('.facture-ligne');
  lignes.forEach(function(ligne) {
    var prodSel = ligne.querySelector('.ligne-produit');
    if (!prodSel || !prodSel.value) return;
    var opt = prodSel.options[prodSel.selectedIndex];
    if (!opt || !opt.value) return;
    var prixAttr = (role === 'partenaire') ? 'data-prix-partenaire' : 'data-prix';
    ligne.querySelector('.ligne-prix').value = opt.getAttribute(prixAttr) || '0';
  });
  computeFactureTotals();
}

function computeFactureTotals() {
  var lignes = document.querySelectorAll('.facture-ligne');
  var totalHt = 0;
  lignes.forEach(function(l) {
    var qty = parseFloat((l.querySelector('.ligne-qty') || {}).value) || 0;
    var prix = parseFloat((l.querySelector('.ligne-prix') || {}).value) || 0;
    var lineTotal = qty * prix;
    totalHt += lineTotal;
    var totalCell = l.querySelector('.ligne-total');
    if (totalCell) totalCell.textContent = formatPrix(lineTotal);
  });
  var tvaPct = parseFloat((document.getElementById('facture-taux-tva') || {}).value) || 0;
  var tvaAmount = totalHt * tvaPct / 100;
  var totalTtc = totalHt + tvaAmount;

  var elHt = document.getElementById('facture-total-ht');
  var elTva = document.getElementById('facture-total-tva');
  var elTtc = document.getElementById('facture-total-ttc');
  var elTvaPct = document.getElementById('facture-tva-pct');
  if (elHt) elHt.textContent = formatPrix(totalHt);
  if (elTva) elTva.textContent = formatPrix(tvaAmount);
  if (elTtc) elTtc.textContent = formatPrix(totalTtc);
  if (elTvaPct) elTvaPct.textContent = tvaPct;
}

function saveFacture(editId, produits) {
  var lignes = [];
  document.querySelectorAll('.facture-ligne').forEach(function(l) {
    var prodSel = l.querySelector('.ligne-produit');
    var prodId = prodSel ? prodSel.value : '';
    lignes.push({
      produit_id: prodId ? parseInt(prodId, 10) : null,
      designation: (l.querySelector('.ligne-designation') || {}).value || '',
      quantite: parseFloat((l.querySelector('.ligne-qty') || {}).value) || 1,
      prix_unitaire: parseFloat((l.querySelector('.ligne-prix') || {}).value) || 0,
      total_ligne: (parseFloat((l.querySelector('.ligne-qty') || {}).value) || 1) * (parseFloat((l.querySelector('.ligne-prix') || {}).value) || 0)
    });
  });

  var clientId = (document.getElementById('facture-client') || {}).value;
  var dateF = (document.getElementById('facture-date') || {}).value;
  var echeance = (document.getElementById('facture-echeance') || {}).value;
  var statut = (document.getElementById('facture-statut') || {}).value;
  var tvaPct = parseFloat((document.getElementById('facture-taux-tva') || {}).value) || 0;
  var montantPaye = parseFloat((document.getElementById('facture-montant-paye') || {}).value) || 0;
  var notes = (document.getElementById('facture-notes') || {}).value || '';

  var totalHt = lignes.reduce(function(s, l) { return s + (l.total_ligne || 0); }, 0);
  var tvaAmount = totalHt * tvaPct / 100;
  var totalTtc = totalHt + tvaAmount;

  var payload = {
    client_id: parseInt(clientId, 10),
    date_facture: dateF,
    echeance: echeance || null,
    statut: statut,
    montant_ht: totalHt,
    tva: tvaPct,
    montant_ttc: totalTtc,
    montant_paye: montantPaye,
    notes: notes,
    lignes: lignes
  };

  // Client-side check: prevent out-of-stock products
  for (var i = 0; i < lignes.length; i++) {
    var lid = lignes[i].produit_id;
    if (lid) {
      var match = (produits || []).find(function(p) { return Number(p.id) === Number(lid); });
      if (match && Number(match.gestion_stock) === 1 && Number(match.stock_total || 0) <= 0) {
        alert('Le produit "' + (match.nom || '') + '" est en rupture de stock et ne peut pas \u00eatre factur\u00e9.');
        return;
      }
    }
  }

  var url = API_BASE + '?resource=factures';
  var method = 'POST';
  if (editId) { url += '&id=' + editId; method = 'PUT'; }

  fetch(url, { method: method, headers: { 'Content-Type': 'application/json' }, credentials: 'same-origin', body: JSON.stringify(payload) })
    .then(function(r) { return r.json().then(function(d) { if (!r.ok) throw new Error(d.error || 'Erreur'); return d; }); })
    .then(function(d) {
      alert(editId ? 'Facture mise à jour' : 'Facture créée');
      window.location.href = 'admin-facturation.php';
    })
    .catch(function(err) { alert(err.message); });
}

function loadFactureForEdit(id) {
  fetch(API_BASE + '?resource=factures&id=' + id, { credentials: 'same-origin' })
    .then(function(r) { return r.json(); })
    .then(function(f) {
      if (!f || f.error) return;
      var numField = document.getElementById('facture-numero');
      var clientField = document.getElementById('facture-client');
      var dateField = document.getElementById('facture-date');
      var echeanceField = document.getElementById('facture-echeance');
      var statutField = document.getElementById('facture-statut');
      var tvaField = document.getElementById('facture-taux-tva');
      var payeField = document.getElementById('facture-montant-paye');
      var notesField = document.getElementById('facture-notes');
      if (numField) numField.value = f.numero || '';
      if (clientField) clientField.value = f.client_id || '';
      if (dateField) dateField.value = f.date_facture || '';
      if (echeanceField) echeanceField.value = f.echeance || '';
      if (statutField) statutField.value = f.statut || 'brouillon';
      if (tvaField) tvaField.value = f.tva || '0';
      if (payeField) payeField.value = f.montant_paye || '0';
      if (notesField) notesField.value = f.notes || '';

      // Load lignes
      var wrap = document.getElementById('facture-lignes-wrapper');
      if (wrap) wrap.innerHTML = '';
      (f.lignes || []).forEach(function(l) {
        addFactureLigne([]);
        var lastLigne = wrap.lastChild;
        if (lastLigne) {
          var prodSel = lastLigne.querySelector('.ligne-produit');
          if (prodSel && l.produit_id) prodSel.value = l.produit_id;
          var des = lastLigne.querySelector('.ligne-designation');
          if (des) des.value = l.designation || '';
          var qty = lastLigne.querySelector('.ligne-qty');
          if (qty) qty.value = l.quantite || 1;
          var prix = lastLigne.querySelector('.ligne-prix');
          if (prix) prix.value = l.prix_unitaire || 0;
        }
      });
      computeFactureTotals();
    })
    .catch(function() {});
}

// ——— Facture view ———
function loadFactureViewPage() {
  var wrap = document.getElementById('facture-detail-wrapper');
  if (!wrap) return;
  var id = new URLSearchParams(window.location.search).get('id');
  if (!id) { window.location.href = 'admin-facturation.php'; return; }

  fetch(API_BASE + '?resource=factures&id=' + id, { credentials: 'same-origin' })
    .then(function(r) { return r.json(); })
    .then(function(f) {
      if (!f || f.error) { wrap.innerHTML = '<div class="empty-state">' + escapeHtml((f && f.error) || 'Facture introuvable') + '</div>'; return; }
      renderFactureDetail(f, wrap);
    })
    .catch(function() { wrap.innerHTML = '<div class="empty-state">Erreur réseau.</div>'; });
}

function renderFactureDetail(f, wrapper) {
  var reste = f.montant_ttc - f.montant_paye;
  var sClass = STATUT_CLASSES[f.statut] || '';

  var h = '<div class="facture-detail">';
  h += '<div class="facture-detail__header">';
  h += '<div><h2 class="admin-section__title">Facture ' + escapeHtml(f.numero) + '</h2>';
  h += '<span class="facture-statut ' + sClass + '">' + escapeHtml(STATUT_LABELS[f.statut] || f.statut) + '</span></div>';
  h += '<div class="facture-detail__actions">';
  h += '<a href="admin-facture-edit.php?id=' + f.id + '" class="btn btn-secondary btn-sm">Modifier</a> ';
  h += '<a href="facture-pdf.php?id=' + f.id + '" class="btn btn-primary btn-sm" target="_blank">Télécharger PDF</a> ';
  h += '<button class="btn btn-danger btn-sm" onclick="deleteFactureConfirm(' + f.id + ')">Supprimer</button>';
  h += '</div></div>';

  h += '<div class="facture-detail__info"><div class="facture-detail__col"><strong>Client / Partenaire :</strong> ' + escapeHtml(f.client_nom || f.client_login) + '</div>';
  h += '<div class="facture-detail__col"><strong>Date :</strong> ' + escapeHtml(f.date_facture) + '</div>';
  h += '<div class="facture-detail__col"><strong>Échéance :</strong> ' + escapeHtml(f.echeance || '—') + '</div></div>';

  // Lignes
  h += '<h3 class="stock-subtitle">Lignes</h3>';
  h += '<div class="table-scroll"><table class="table-stock"><thead><tr><th>Désignation</th><th>Qté</th><th>Prix unitaire</th><th>Total</th></tr></thead><tbody>';
  (f.lignes || []).forEach(function(l) {
    h += '<tr><td>' + escapeHtml(l.designation) + '</td><td>' + l.quantite + '</td><td>' + formatPrix(l.prix_unitaire) + '</td><td>' + formatPrix(l.total_ligne) + '</td></tr>';
  });
  h += '</tbody></table></div>';

  // Totals
  h += '<div class="facture-totals">';
  h += '<div class="facture-total-row"><span>Total HT</span><span>' + formatPrix(f.montant_ht) + '</span></div>';
  h += '<div class="facture-total-row"><span>TVA (' + f.tva + '%)</span><span>' + formatPrix(f.montant_ttc - f.montant_ht) + '</span></div>';
  h += '<div class="facture-total-row facture-total-ttc"><span>Total TTC</span><span>' + formatPrix(f.montant_ttc) + '</span></div>';
  h += '<div class="facture-total-row"><span>Payé</span><span>' + formatPrix(f.montant_paye) + '</span></div>';
  h += '<div class="facture-total-row' + (reste > 0 ? ' facture-total-reste' : '') + '"><span>Reste à payer</span><span>' + formatPrix(reste) + '</span></div>';
  h += '</div>';

  // Paiements
  h += '<h3 class="stock-subtitle">Paiements</h3>';
  if (f.paiements && f.paiements.length) {
    h += '<div class="table-scroll"><table class="table-stock"><thead><tr><th>Date</th><th>Montant</th><th>Mode</th><th>Référence</th></tr></thead><tbody>';
    f.paiements.forEach(function(p) {
      h += '<tr><td>' + escapeHtml(p.date_paiement) + '</td><td>' + formatPrix(p.montant) + '</td><td>' + escapeHtml(p.mode) + '</td><td>' + escapeHtml(p.reference || '—') + '</td></tr>';
    });
    h += '</tbody></table></div>';
  } else {
    h += '<div class="empty-state">Aucun paiement enregistré.</div>';
  }

  // Record payment form
  h += '<div class="card-admin" style="margin-top:1rem;">';
  h += '<h4 style="margin:0 0 0.5rem;">Enregistrer un paiement</h4>';
  h += '<div class="form-row form-row-stock">';
  h += '<div class="form-group"><label>Montant</label><input type="number" id="pay-montant" min="0" step="0.01" value="' + reste.toFixed(2) + '"></div>';
  h += '<div class="form-group"><label>Date</label><input type="date" id="pay-date" value="' + new Date().toISOString().slice(0, 10) + '"></div>';
  h += '<div class="form-group"><label>Mode</label><select id="pay-mode"><option value="especes">Espèces</option><option value="cheque">Chèque</option><option value="virement">Virement</option><option value="carte">Carte</option><option value="autre">Autre</option></select></div>';
  h += '<div class="form-group"><label>Référence</label><input type="text" id="pay-ref" placeholder="N° chèque, réf. virement&#8230;"></div>';
  h += '</div>';
  h += '<button class="btn btn-primary btn-sm" onclick="recordPayment(' + f.id + ')">Enregistrer</button>';
  h += '</div>';

  // Relances
  h += '<h3 class="stock-subtitle" style="margin-top:1.5rem;">Relances</h3>';
  if (f.relances && f.relances.length) {
    h += '<div class="table-scroll"><table class="table-stock"><thead><tr><th>Date</th><th>Type</th><th>Commentaire</th></tr></thead><tbody>';
    f.relances.forEach(function(r) {
      h += '<tr><td>' + escapeHtml(r.date_relance) + '</td><td>' + escapeHtml(r.type) + '</td><td>' + escapeHtml(r.commentaire || '—') + '</td></tr>';
    });
    h += '</tbody></table></div>';
  } else {
    h += '<div class="empty-state">Aucune relance.</div>';
  }

  // Add relance form
  h += '<div class="card-admin" style="margin-top:1rem;">';
  h += '<h4 style="margin:0 0 0.5rem;">Ajouter une relance</h4>';
  h += '<div class="form-row form-row-stock">';
  h += '<div class="form-group"><label>Type</label><select id="relance-type"><option value="rappel">Rappel</option><option value="relance">Relance</option><option value="mise_en_demeure">Mise en demeure</option></select></div>';
  h += '<div class="form-group"><label>Date</label><input type="date" id="relance-date" value="' + new Date().toISOString().slice(0, 10) + '"></div>';
  h += '<div class="form-group"><label>Commentaire</label><input type="text" id="relance-comment" placeholder="Motif&#8230;"></div>';
  h += '</div>';
  h += '<button class="btn btn-secondary btn-sm" onclick="addRelance(' + f.id + ')">Ajouter</button>';
  h += '</div>';

  if (f.notes) {
    h += '<div class="card-admin" style="margin-top:1.5rem;"><strong>Notes :</strong> ' + escapeHtml(f.notes) + '</div>';
  }

  h += '</div>';
  wrapper.innerHTML = h;
}

function recordPayment(factureId) {
  var montant = parseFloat((document.getElementById('pay-montant') || {}).value) || 0;
  var dateP = (document.getElementById('pay-date') || {}).value || '';
  var mode = (document.getElementById('pay-mode') || {}).value || 'especes';
  var ref = (document.getElementById('pay-ref') || {}).value || '';
  if (montant <= 0) { alert('Montant invalide.'); return; }
  fetch(API_BASE + '?resource=factures', {
    method: 'POST', headers: { 'Content-Type': 'application/json' }, credentials: 'same-origin',
    body: JSON.stringify({ action: 'record-payment', facture_id: factureId, montant: montant, date_paiement: dateP, mode: mode, reference: ref })
  })
    .then(function(r) { return r.json().then(function(d) { if (!r.ok) throw new Error(d.error || 'Erreur'); }); })
    .then(function() { loadFactureViewPage(); })
    .catch(function(err) { alert(err.message); });
}

function addRelance(factureId) {
  var type = (document.getElementById('relance-type') || {}).value || 'rappel';
  var dateR = (document.getElementById('relance-date') || {}).value || '';
  var comment = (document.getElementById('relance-comment') || {}).value || '';
  fetch(API_BASE + '?resource=factures', {
    method: 'POST', headers: { 'Content-Type': 'application/json' }, credentials: 'same-origin',
    body: JSON.stringify({ action: 'add-relance', facture_id: factureId, type: type, date_relance: dateR, commentaire: comment })
  })
    .then(function(r) { return r.json().then(function(d) { if (!r.ok) throw new Error(d.error || 'Erreur'); }); })
    .then(function() { loadFactureViewPage(); })
    .catch(function(err) { alert(err.message); });
}

function deleteFactureConfirm(id) {
  if (!confirm('Supprimer cette facture ?')) return;
  fetch(API_BASE + '?resource=factures&id=' + id, { method: 'DELETE', credentials: 'same-origin' })
    .then(function(r) { return r.json().then(function(d) { if (!r.ok) throw new Error(d.error || 'Erreur'); }); })
    .then(function() { window.location.href = 'admin-facturation.php'; })
    .catch(function(err) { alert(err.message); });
}

var currentAdminPage = (document.body && document.body.getAttribute('data-admin-page')) || '';
if (currentAdminPage === 'produits') {
  loadTable();
} else if (currentAdminPage === 'partenaires') {
  loadPartenaires();
} else if (currentAdminPage === 'clients') {
  loadClients();
} else if (currentAdminPage === 'stock-entree') {
  loadStockFormPage('entree');
} else if (currentAdminPage === 'stock-sortie') {
  loadStockFormPage('sortie');
} else if (currentAdminPage === 'stock-situation') {
  loadStockSituationPage();
} else if (currentAdminPage === 'stock-transfert') {
  loadStockTransfertPage();
} else if (currentAdminPage === 'stock-ajustement') {
  loadStockAjustementPage();
} else if (currentAdminPage === 'stock-lieux') {
  loadStockLieuxPage();
} else if (currentAdminPage === 'parametres') {
  loadParametresPage();
} else if (currentAdminPage === 'facturation') {
  loadFacturationPage();
} else if (currentAdminPage === 'facture-edit') {
  loadFactureEditPage();
} else if (currentAdminPage === 'facture-view') {
  loadFactureViewPage();
} else if (currentAdminPage === 'dashboard') {
  loadDashboardAnalysis();
}
