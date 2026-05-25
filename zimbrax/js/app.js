// js/app.js — ZimbraX Frontend Logic

'use strict';

// ── State ──────────────────────────────────────────────────
let currentFolder = null;
let currentEmail  = null;
let selectedEmails = new Set();
let calDate       = new Date();
let allContacts   = [];
let composeMinimized = false;
let currentEmailData = null; // Store opened email data for reply/forward

// ── Init ───────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
  loadFolder(APP_CONFIG.inboxId, 'Boîte de réception', document.querySelector('.nav-item.active'));
  renderCalendar();
  loadContacts();
  loadTasks();
  // Live search
  let searchTimer;
  document.getElementById('email-search').addEventListener('input', e => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => filterEmailList(e.target.value), 250);
  });
  document.getElementById('global-search').addEventListener('keydown', e => {
    if (e.key === 'Enter' && e.target.value.trim()) globalSearch(e.target.value.trim());
  });
});

// ── API helper ─────────────────────────────────────────────
async function api(url, data = null, formData = null) {
  const opts = {};
  if (formData) {
    opts.method = 'POST';
    opts.body = formData;
  } else if (data) {
    opts.method = 'POST';
    opts.headers = { 'Content-Type': 'application/json' };
    opts.body = JSON.stringify(data);
  }
  const res = await fetch(APP_CONFIG.baseUrl + url, opts);
  return res.json();
}

function showToast(msg, type = 'success') {
  const el = document.createElement('div');
  el.className = `alert alert-${type}`;
  el.style.cssText = 'position:fixed;bottom:30px;right:20px;z-index:9999;min-width:260px;box-shadow:0 4px 20px rgba(0,0,0,.4);';
  el.textContent = (type === 'success' ? '✓ ' : '⚠ ') + msg;
  document.body.appendChild(el);
  setTimeout(() => { el.style.opacity = '0'; el.style.transition = 'opacity .5s'; setTimeout(() => el.remove(), 500); }, 3000);
}

// ── EMAIL LIST ─────────────────────────────────────────────
async function loadFolder(folderId, folderName, navEl) {
  currentFolder = folderId;
  currentEmail  = null;
  currentEmailData = null;
  selectedEmails.clear();
  updateBulkActions();

  // Update nav
  document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));
  if (navEl) navEl.classList.add('active');
  document.getElementById('folder-title').textContent = folderName;

  // Reset email view
  document.getElementById('email-view-panel').innerHTML = `
    <div class="empty-view">
      <div class="empty-icon">📧</div>
      <div style="font-size:15px;font-weight:500;color:var(--text2);">Sélectionnez un e-mail</div>
      <div style="font-size:12px;">Cliquez sur un message pour le lire</div>
    </div>`;

  // Show loader
  document.getElementById('email-items').innerHTML = `
    <div style="padding:24px;text-align:center;color:var(--text3);">
      <div class="loader"></div>
      <p style="margin-top:8px;font-size:12px;">Chargement...</p>
    </div>`;

  const data = await api(`api/emails.php?action=list&folder_id=${folderId}`);
  if (data.success) renderEmailList(data.emails);
}

function renderEmailList(emails) {
  const el = document.getElementById('email-items');
  if (!emails.length) {
    el.innerHTML = `<div class="empty-view" style="padding:40px;"><div class="empty-icon">📭</div><div>Dossier vide</div></div>`;
    return;
  }
  el.innerHTML = emails.map(e => {
    const initStr = ((e.from_name||e.from_email).split(' ').map(w=>w[0]||'').join('').slice(0,2)).toUpperCase();
    const colorHash = strToColor(e.from_email);
    return `<div class="email-item${!e.is_read?' unread':''}${currentEmail==e.id?' selected':''}"
         id="ei-${e.id}" onclick="openEmail(${e.id})" data-id="${e.id}">
      ${!e.is_read ? '<div class="unread-dot"></div>' : ''}
      <div class="ei-row1">
        <input type="checkbox" class="email-cb" onclick="event.stopPropagation();toggleEmailSelect(${e.id},this)"
               style="width:13px;height:13px;cursor:pointer;accent-color:var(--blue);">
        <div class="ei-avatar" style="background:${colorHash};">${initStr}</div>
        <div class="ei-from">${esc(e.from_name || e.from_email)}</div>
        <div class="ei-time">${esc(e.time_ago)}</div>
        <span class="star-icon${e.is_starred?' starred':''}" onclick="event.stopPropagation();toggleStar(${e.id},this)">${e.is_starred?'★':'☆'}</span>
      </div>
      <div class="ei-subject">${esc(e.subject||'(sans objet)')}</div>
      <div class="ei-badges">
        ${e.has_attachment?'<span style="font-size:11px;">📎</span>':''}
        <div class="ei-preview">${esc(e.preview)}</div>
      </div>
    </div>`;
  }).join('');
}

async function openEmail(id) {
  currentEmail = id;
  // Highlight selected
  document.querySelectorAll('.email-item').forEach(el => el.classList.remove('selected'));
  const item = document.getElementById(`ei-${id}`);
  if (item) { item.classList.add('selected'); item.classList.remove('unread'); item.querySelector('.unread-dot')?.remove(); }

  // Show loader
  const vp = document.getElementById('email-view-panel');
  vp.innerHTML = `<div class="empty-view"><div class="loader"></div></div>`;

  const data = await api(`api/emails.php?action=read&id=${id}`);
  if (!data.success) { showToast('Erreur de chargement', 'danger'); return; }
  const e = data.email;
  currentEmailData = e; // Store for reply/forward

  // Sanitize body_html to prevent XSS
  const safeBodyHtml = e.body_html ? sanitizeHtml(e.body_html) : '';
  const bodyContent = safeBodyHtml || `<pre>${esc(e.body_text||'')}</pre>`;

  vp.innerHTML = `
    <div class="ev-toolbar">
      <button class="btn btn-sm" onclick="replyEmail(${id})">↩ Répondre</button>
      <button class="btn btn-sm" onclick="replyAllEmail(${id})">↩↩ Répondre à tous</button>
      <button class="btn btn-sm" onclick="forwardEmail(${id})">↪ Transférer</button>
      <button class="btn-icon" onclick="toggleStar(${id},null)" title="Favoris">★</button>
      <button class="btn-icon" onclick="moveEmail(${id})" title="Déplacer">📂</button>
      <button class="btn-icon" style="margin-left:auto;color:var(--red);" onclick="deleteEmail(${id})" title="Supprimer">🗑</button>
    </div>
    <div class="ev-header">
      <div class="ev-subject">${esc(e.subject||'(sans objet)')}</div>
      <div class="ev-meta">
        <div class="ev-sender-av" style="background:${strToColor(e.from_email)};">${((e.from_name||e.from_email).split(' ').map(w=>w[0]||'').join('').slice(0,2)).toUpperCase()}</div>
        <div class="ev-sender-info">
          <div class="ev-from">${esc(e.from_name||'Inconnu')}</div>
          <div class="ev-addr">&lt;${esc(e.from_email)}&gt; → ${esc(e.to_email)}</div>
        </div>
        <div class="ev-date-str">${esc(e.received_at_fmt)}</div>
      </div>
      ${e.attachments?.length ? `<div style="margin-top:10px;display:flex;gap:8px;flex-wrap:wrap;">${e.attachments.map(a=>`<a href="${APP_CONFIG.baseUrl}api/emails.php?action=download&att_id=${a.id}" class="btn btn-sm" download>📎 ${esc(a.original_name)} (${esc(a.size_fmt)})</a>`).join('')}</div>` : ''}
    </div>
    <div class="ev-body">${bodyContent}</div>
    <div class="ev-reply-bar">
      <button class="btn btn-primary" onclick="replyEmail(${id})">↩ Répondre</button>
      <button class="btn" onclick="forwardEmail(${id})">↪ Transférer</button>
    </div>`;

  updateUnreadBadge();
}

// ── Sanitize HTML to prevent XSS ─────────────────────────
function sanitizeHtml(html) {
  const doc = new DOMParser().parseFromString(html, 'text/html');
  // Remove script tags
  doc.querySelectorAll('script').forEach(el => el.remove());
  // Remove event handler attributes
  doc.querySelectorAll('*').forEach(el => {
    [...el.attributes].forEach(attr => {
      if (attr.name.startsWith('on')) el.removeAttribute(attr.name);
    });
  });
  // Remove javascript: URLs
  doc.querySelectorAll('a[href^="javascript:"]').forEach(a => a.removeAttribute('href'));
  return doc.body.innerHTML;
}

async function deleteEmail(id) {
  if (!confirm('Supprimer ce message ?')) return;
  const data = await api('api/emails.php', { action: 'delete', id });
  if (data.success) {
    document.getElementById(`ei-${id}`)?.remove();
    document.getElementById('email-view-panel').innerHTML = `<div class="empty-view"><div class="empty-icon">📧</div><div>Message supprimé</div></div>`;
    showToast('Message supprimé');
    updateUnreadBadge();
  }
}

async function toggleStar(id, el) {
  const data = await api('api/emails.php', { action: 'star', id });
  if (data.success && el) {
    el.textContent = data.starred ? '★' : '☆';
    el.classList.toggle('starred', data.starred);
  }
}

function toggleEmailSelect(id, cb) {
  if (cb.checked) selectedEmails.add(id); else selectedEmails.delete(id);
  updateBulkActions();
}

function updateBulkActions() {
  const bar = document.getElementById('bulk-actions');
  if (selectedEmails.size > 0) {
    bar.style.display = 'flex';
    document.getElementById('selected-count').textContent = selectedEmails.size + ' sélectionné(s)';
  } else { bar.style.display = 'none'; }
}

function selectAllEmails() {
  document.querySelectorAll('.email-cb').forEach(cb => { cb.checked = true; const id = parseInt(cb.closest('.email-item').dataset.id); selectedEmails.add(id); });
  updateBulkActions();
}

function clearSelection() {
  document.querySelectorAll('.email-cb').forEach(cb => cb.checked = false);
  selectedEmails.clear(); updateBulkActions();
}

async function bulkAction(action) {
  if (!selectedEmails.size) return;
  const data = await api('api/emails.php', { action: 'bulk', ids: [...selectedEmails], bulk_action: action });
  if (data.success) {
    selectedEmails.forEach(id => document.getElementById(`ei-${id}`)?.remove());
    selectedEmails.clear(); updateBulkActions();
    showToast(`${data.count} message(s) ${action==='read'?'marqué(s) lu':'supprimé(s)'}`);
    updateUnreadBadge();
  }
}

function filterEmailList(q) {
  q = q.toLowerCase();
  document.querySelectorAll('.email-item').forEach(el => {
    const text = el.textContent.toLowerCase();
    el.style.display = text.includes(q) ? '' : 'none';
  });
}

function refreshEmails() { if (currentFolder) loadFolder(currentFolder, document.getElementById('folder-title').textContent, null); }

async function updateUnreadBadge() {
  const data = await api('api/emails.php?action=unread_counts');
  if (data.success) {
    data.counts.forEach(f => {
      const navItem = document.querySelector(`[data-folder="${f.id}"]`);
      if (!navItem) return;
      let badge = navItem.querySelector('.nav-badge');
      if (f.unread > 0) {
        if (!badge) { badge = document.createElement('span'); badge.className = 'nav-badge'; navItem.appendChild(badge); }
        badge.textContent = f.unread;
      } else { badge?.remove(); }
    });
    const total = data.counts.reduce((s, f) => s + f.unread, 0);
    document.getElementById('sb-unread').textContent = total + ' non lu(s)';
  }
}

// ── COMPOSE ────────────────────────────────────────────────
function openCompose(opts = {}) {
  document.getElementById('cm-to').value      = opts.to      || '';
  document.getElementById('cm-subject').value = opts.subject || '';
  document.getElementById('cm-body').value    = opts.body    || '';
  document.getElementById('cm-cc').value      = opts.cc      || '';
  document.getElementById('cm-bcc').value     = opts.bcc     || '';
  document.getElementById('attach-list').innerHTML = '';
  document.getElementById('attach-input').value = '';
  document.getElementById('compose-overlay').classList.add('open');
  composeMinimized = false;
  document.getElementById('compose-body').style.display = '';
  document.getElementById('cm-to').focus();
}

function closeCompose() { document.getElementById('compose-overlay').classList.remove('open'); }

function closeComposeOnBg(e) { if (e.target === document.getElementById('compose-overlay')) closeCompose(); }

function minimizeCompose() {
  composeMinimized = !composeMinimized;
  document.getElementById('compose-body').style.display = composeMinimized ? 'none' : '';
}
function toggleMinimize() { minimizeCompose(); }

function toggleCcBcc() {
  ['cc-row','bcc-row'].forEach(id => {
    const el = document.getElementById(id);
    el.style.display = el.style.display === 'none' ? '' : 'none';
  });
}

function replyEmail(id) {
  const e = currentEmailData;
  if (!e) { showToast('Ouvrez d\'abord un e-mail', 'danger'); return; }
  const fromEmail = e.from_email || '';
  const subject = e.subject || '';
  openCompose({
    to: fromEmail,
    subject: 'Re: ' + subject.replace(/^Re:\s*/i,''),
    body: '\n\n---\nRéponse à: ' + subject
  });
}

function replyAllEmail(id) {
  const e = currentEmailData;
  if (!e) { showToast('Ouvrez d\'abord un e-mail', 'danger'); return; }
  const fromEmail = e.from_email || '';
  const toEmails = e.to_email || '';
  const ccEmails = e.cc_email || '';
  const subject = e.subject || '';

  // Build "to" list: original sender + other recipients (excluding self)
  const allRecipients = [fromEmail];
  toEmails.split(',').map(s => s.trim()).filter(Boolean).forEach(addr => {
    if (addr !== APP_CONFIG.userEmail && addr !== fromEmail) allRecipients.push(addr);
  });
  const to = allRecipients.join(', ');

  // Build "cc" list from original cc (excluding self)
  let cc = '';
  if (ccEmails) {
    const ccList = ccEmails.split(',').map(s => s.trim()).filter(addr => addr !== APP_CONFIG.userEmail);
    cc = ccList.join(', ');
  }

  openCompose({
    to,
    cc,
    subject: 'Re: ' + subject.replace(/^Re:\s*/i,''),
    body: '\n\n---\nRéponse à: ' + subject
  });
}

function forwardEmail(id) {
  const e = currentEmailData;
  if (!e) { showToast('Ouvrez d\'abord un e-mail', 'danger'); return; }
  const subject = e.subject || '';
  const fwdBody = '\n\n--- Message transféré ---\n'
    + 'De: ' + (e.from_name || '') + ' <' + (e.from_email || '') + '>\n'
    + 'Date: ' + (e.received_at_fmt || '') + '\n'
    + 'Objet: ' + subject + '\n\n'
    + (e.body_text || '');
  openCompose({ subject: 'Fwd: ' + subject.replace(/^Fwd:\s*/i,''), body: fwdBody });
}

function handleAttachments(input) {
  const list = document.getElementById('attach-list');
  list.innerHTML = [...input.files].map(f => `<span>📎 ${esc(f.name)} (${(f.size/1024).toFixed(0)} Ko)</span>`).join(' ');
}

async function sendEmail() {
  const to      = document.getElementById('cm-to').value.trim();
  const subject = document.getElementById('cm-subject').value.trim();
  const body    = document.getElementById('cm-body').value.trim();
  if (!to) { showToast('Destinataire requis', 'danger'); return; }

  const btn = document.querySelector('.cm-footer .btn-primary');
  btn.textContent = '⏳ Envoi...'; btn.disabled = true;

  // Check if we have file attachments
  const fileInput = document.getElementById('attach-input');
  let data;
  if (fileInput.files.length > 0) {
    const formData = new FormData();
    formData.append('action', 'send');
    formData.append('to', to);
    formData.append('subject', subject);
    formData.append('body', body);
    formData.append('cc', document.getElementById('cm-cc').value);
    formData.append('bcc', document.getElementById('cm-bcc').value);
    for (let i = 0; i < fileInput.files.length; i++) {
      formData.append('attachments[]', fileInput.files[i]);
    }
    data = await api('api/emails.php', null, formData);
  } else {
    data = await api('api/emails.php', { action: 'send', to, subject, body,
      cc: document.getElementById('cm-cc').value,
      bcc: document.getElementById('cm-bcc').value });
  }

  btn.textContent = 'Envoyer ↗'; btn.disabled = false;
  if (data.success) { closeCompose(); showToast('Message envoyé !'); }
  else showToast(data.error || 'Erreur envoi', 'danger');
}

async function saveDraft() {
  const data = await api('api/emails.php', { action: 'draft',
    to: document.getElementById('cm-to').value,
    subject: document.getElementById('cm-subject').value,
    body: document.getElementById('cm-body').value });
  if (data.success) { showToast('Brouillon sauvegardé'); closeCompose(); }
}

async function globalSearch(q) {
  const data = await api(`api/emails.php?action=search&q=${encodeURIComponent(q)}`);
  if (data.success) {
    document.getElementById('folder-title').textContent = `Résultats: "${q}"`;
    renderEmailList(data.emails);
  }
}

// ── MOVE EMAIL ─────────────────────────────────────────────
async function moveEmail(id) {
  // Fetch user's folders to build a selection
  const data = await api('api/emails.php?action=unread_counts');
  if (!data.success) return;
  // Build a simple folder picker prompt
  const folders = data.counts; // Has folder IDs
  const folderNames = document.querySelectorAll('.nav-item[data-folder]');
  let options = [];
  folderNames.forEach(el => {
    const fid = el.dataset.folder;
    if (fid == currentFolder) return; // Skip current folder
    const label = el.querySelector('.nav-label')?.textContent || 'Dossier';
    options.push(`${fid}: ${label}`);
  });
  const choice = prompt(`Déplacer vers (numéro du dossier):\n${options.join('\n')}`);
  if (!choice) return;
  const targetId = parseInt(choice.trim());
  if (isNaN(targetId)) { showToast('Numéro invalide', 'danger'); return; }
  const result = await api('api/emails.php', { action: 'move', id, folder_id: targetId });
  if (result.success) {
    showToast('Message déplacé');
    refreshEmails();
  } else {
    showToast(result.error || 'Erreur', 'danger');
  }
}

// ── CALENDAR ───────────────────────────────────────────────
let calEvents = [];

async function renderCalendar() {
  const y = calDate.getFullYear(), m = calDate.getMonth();
  const months = ['Janvier','Février','Mars','Avril','Mai','Juin','Juillet','Août','Septembre','Octobre','Novembre','Décembre'];
  document.getElementById('cal-month-title').textContent = months[m] + ' ' + y;

  const data = await api(`api/calendar.php?action=list&year=${y}&month=${m+1}`);
  calEvents = data.events || [];

  // Mark days that have events in the current month
  const eventDays = new Set();
  calEvents.forEach(e => {
    const d = new Date(e.start_datetime);
    // Only mark if event starts in the displayed month/year
    if (d.getFullYear() === y && d.getMonth() === m) {
      eventDays.add(d.getDate());
    }
  });

  const grid = document.getElementById('cal-grid-days');
  const today = new Date();
  const firstDay = new Date(y, m, 1).getDay() || 7;
  const daysInMonth = new Date(y, m+1, 0).getDate();
  const daysInPrev  = new Date(y, m, 0).getDate();

  const headers = `<div class="cal-dow">L</div><div class="cal-dow">M</div><div class="cal-dow">M</div><div class="cal-dow">J</div><div class="cal-dow">V</div><div class="cal-dow">S</div><div class="cal-dow">D</div>`;
  let cells = '';
  for (let i = firstDay - 1; i > 0; i--) cells += `<div class="cal-day">${daysInPrev - i + 1}</div>`;
  for (let d = 1; d <= daysInMonth; d++) {
    const isToday = d === today.getDate() && m === today.getMonth() && y === today.getFullYear();
    cells += `<div class="cal-day cur-month${isToday?' today':''}${eventDays.has(d)?' has-event':''}" onclick="selectCalDay(${d}, this)">${d}</div>`;
  }
  grid.innerHTML = headers + cells;
}

function calPrevMonth() { calDate.setMonth(calDate.getMonth()-1); renderCalendar(); }
function calNextMonth() { calDate.setMonth(calDate.getMonth()+1); renderCalendar(); }

function selectCalDay(d, el) {
  document.querySelectorAll('.cal-day').forEach(e => e.classList.remove('selected'));
  if (el) el.classList.add('selected');
  const y = calDate.getFullYear(), m = calDate.getMonth();
  const dayEvents = calEvents.filter(e => {
    const dt = new Date(e.start_datetime);
    return dt.getFullYear() === y && dt.getMonth() === m && dt.getDate() === d;
  });
  updateUpcomingEvents(dayEvents);
}

function updateUpcomingEvents(events) {
  const el = document.getElementById('upcoming-events');
  if (!events.length) { el.innerHTML = `<div style="font-size:11px;color:var(--text3);text-align:center;padding:10px;">Aucun événement</div>`; return; }
  el.innerHTML = events.map(e => {
    const t = new Date(e.start_datetime);
    return `<div class="ev-card" onclick="editEvent(${e.id})">
      <div class="ev-stripe" style="background:${esc(e.color)}"></div>
      <div class="ev-card-info">
        <div class="ev-card-title">${esc(e.title)}</div>
        <div class="ev-card-time">${t.toLocaleTimeString('fr-FR',{hour:'2-digit',minute:'2-digit'})}${e.location?' · '+esc(e.location):''}</div>
      </div>
    </div>`;
  }).join('');
}

function openNewEvent() {
  document.getElementById('ev-id').value = '';
  document.getElementById('ev-title').value = '';
  document.getElementById('ev-location').value = '';
  document.getElementById('ev-desc').value = '';
  document.getElementById('ev-color').value = '#4f8ef7';
  document.getElementById('ev-delete-btn').style.display = 'none';
  const now = new Date(); now.setMinutes(0,0,0);
  const end = new Date(now); end.setHours(end.getHours()+1);
  document.getElementById('ev-start').value = toLocalDT(now);
  document.getElementById('ev-end').value   = toLocalDT(end);
  document.getElementById('event-modal-title').textContent = 'Nouvel événement';
  openModal('event-modal');
}

async function editEvent(id) {
  const data = await api(`api/calendar.php?action=get&id=${id}`);
  if (!data.success) return;
  const e = data.event;
  document.getElementById('ev-id').value       = e.id;
  document.getElementById('ev-title').value    = e.title;
  document.getElementById('ev-location').value = e.location||'';
  document.getElementById('ev-desc').value     = e.description||'';
  document.getElementById('ev-color').value    = e.color;
  document.getElementById('ev-start').value    = e.start_datetime.replace(' ','T').slice(0,16);
  document.getElementById('ev-end').value      = e.end_datetime.replace(' ','T').slice(0,16);
  document.getElementById('ev-delete-btn').style.display = '';
  document.getElementById('event-modal-title').textContent = 'Modifier l\'événement';
  openModal('event-modal');
}

async function saveEvent() {
  const id    = document.getElementById('ev-id').value;
  const title = document.getElementById('ev-title').value.trim();
  if (!title) { showToast('Titre requis','danger'); return; }
  const data = await api('api/calendar.php', {
    action: id ? 'update' : 'create', id,
    title, location: document.getElementById('ev-location').value,
    description: document.getElementById('ev-desc').value,
    start_datetime: document.getElementById('ev-start').value.replace('T',' '),
    end_datetime:   document.getElementById('ev-end').value.replace('T',' '),
    color: document.getElementById('ev-color').value,
    category: document.getElementById('ev-category').value
  });
  if (data.success) { closeModal('event-modal'); renderCalendar(); showToast(id?'Événement modifié':'Événement créé'); }
  else showToast(data.error||'Erreur','danger');
}

async function deleteEvent() {
  if (!confirm('Supprimer cet événement ?')) return;
  const id = document.getElementById('ev-id').value;
  const data = await api('api/calendar.php', { action: 'delete', id });
  if (data.success) { closeModal('event-modal'); renderCalendar(); showToast('Événement supprimé'); }
}

// ── CONTACTS ───────────────────────────────────────────────
async function loadContacts() {
  const data = await api('api/contacts.php?action=list');
  allContacts = data.contacts || [];
  renderContacts(allContacts);
}

function renderContacts(contacts) {
  const el = document.getElementById('contacts-list');
  if (!contacts.length) { el.innerHTML = `<div style="padding:16px;text-align:center;font-size:12px;color:var(--text3);">Aucun contact</div>`; return; }
  el.innerHTML = contacts.slice(0,30).map(c => `
    <div class="contact-item" onclick="editContact(${c.id})">
      <div class="c-av" style="background:${c.avatar_color}">${((c.first_name[0]||'')+(c.last_name?.[0]||'')).toUpperCase()}</div>
      <div style="min-width:0;flex:1;">
        <div class="c-name">${esc(c.first_name+' '+(c.last_name||''))}</div>
        <div class="c-email-sm">${esc(c.email||c.company||'')}</div>
      </div>
      ${c.is_favorite ? '<span style="color:var(--amber);font-size:12px;">★</span>' : ''}
    </div>`).join('');
}

function searchContacts(q) {
  q = q.toLowerCase();
  renderContacts(q ? allContacts.filter(c =>
    (c.first_name+' '+c.last_name+c.email+c.company).toLowerCase().includes(q)
  ) : allContacts);
}

function openNewContact() {
  ['ct-id','ct-firstname','ct-lastname','ct-email','ct-phone','ct-company','ct-job','ct-notes'].forEach(id => {
    const el = document.getElementById(id); if (el) el.value = '';
  });
  document.getElementById('ct-delete-btn').style.display = 'none';
  document.getElementById('contact-modal-title').textContent = 'Nouveau contact';
  openModal('contact-modal');
}

async function editContact(id) {
  const data = await api(`api/contacts.php?action=get&id=${id}`);
  if (!data.success) return;
  const c = data.contact;
  document.getElementById('ct-id').value        = c.id;
  document.getElementById('ct-firstname').value = c.first_name;
  document.getElementById('ct-lastname').value  = c.last_name||'';
  document.getElementById('ct-email').value     = c.email||'';
  document.getElementById('ct-phone').value     = c.phone||'';
  document.getElementById('ct-company').value   = c.company||'';
  document.getElementById('ct-job').value       = c.job_title||'';
  document.getElementById('ct-notes').value     = c.notes||'';
  document.getElementById('ct-delete-btn').style.display = '';
  document.getElementById('contact-modal-title').textContent = 'Modifier le contact';
  openModal('contact-modal');
}

async function saveContact() {
  const fn = document.getElementById('ct-firstname').value.trim();
  if (!fn) { showToast('Prénom requis','danger'); return; }
  const id = document.getElementById('ct-id').value;
  const data = await api('api/contacts.php', {
    action: id ? 'update' : 'create', id,
    first_name: fn,
    last_name:  document.getElementById('ct-lastname').value,
    email:      document.getElementById('ct-email').value,
    phone:      document.getElementById('ct-phone').value,
    company:    document.getElementById('ct-company').value,
    job_title:  document.getElementById('ct-job').value,
    notes:      document.getElementById('ct-notes').value
  });
  if (data.success) { closeModal('contact-modal'); loadContacts(); showToast(id?'Contact modifié':'Contact créé'); }
  else showToast(data.error||'Erreur','danger');
}

async function deleteContact() {
  if (!confirm('Supprimer ce contact ?')) return;
  const id = document.getElementById('ct-id').value;
  const data = await api('api/contacts.php', { action: 'delete', id });
  if (data.success) { closeModal('contact-modal'); loadContacts(); showToast('Contact supprimé'); }
}

// ── TASKS ──────────────────────────────────────────────────
async function loadTasks() {
  const list = document.getElementById('task-list-filter')?.value || '';
  const data = await api(`api/tasks.php?action=list${list?'&list='+encodeURIComponent(list):''}`);
  renderTasks(data.tasks || []);
}

function renderTasks(tasks) {
  const el = document.getElementById('tasks-list');
  const pri = { urgent:'var(--red)', high:'var(--amber)', normal:'var(--blue)', low:'var(--text3)' };
  el.innerHTML = tasks.map(t => `
    <div class="task-item" id="task-${t.id}">
      <input type="checkbox" class="task-cb" ${t.status==='done'?'checked':''} onchange="toggleTask(${t.id},this)">
      <div class="task-text${t.status==='done'?' done':''}" title="${esc(t.title)}">${esc(t.title)}</div>
      ${t.due_date?`<span style="font-size:10px;color:var(--text3);">${formatDate(t.due_date)}</span>`:''}
      <div class="task-pri" style="background:${pri[t.priority]||'var(--blue)'}"></div>
      <button class="btn-icon" style="font-size:11px;padding:2px;" onclick="deleteTask(${t.id})">✕</button>
    </div>`).join('');
}

async function toggleTask(id, cb) {
  const status = cb.checked ? 'done' : 'todo';
  await api('api/tasks.php', { action: 'update', id, status });
  const el = document.querySelector(`#task-${id} .task-text`);
  if (el) el.classList.toggle('done', cb.checked);
}

async function addTask() {
  const inp = document.getElementById('new-task-input');
  const title = inp.value.trim();
  if (!title) return;
  const list = document.getElementById('task-list-filter')?.value || 'Tâches';
  const data = await api('api/tasks.php', { action: 'create', title, list_name: list });
  if (data.success) { inp.value = ''; loadTasks(); }
}

async function deleteTask(id) {
  await api('api/tasks.php', { action: 'delete', id });
  document.getElementById(`task-${id}`)?.remove();
}

// ── PANEL SWITCHING ────────────────────────────────────────
function switchTab(tab, el) {
  document.querySelectorAll('.p-tab').forEach(t => t.classList.remove('active'));
  el.classList.add('active');
  ['calendar','contacts','tasks'].forEach(t => {
    const d = document.getElementById('tab-'+t);
    if (d) d.style.display = t === tab ? '' : 'none';
  });
}

function switchApp(app, navEl) {
  document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));
  navEl.classList.add('active');
  // Focus right panel tab
  const tabMap = { calendar:'calendar', contacts:'contacts', tasks:'tasks' };
  if (tabMap[app]) {
    const tabEl = document.querySelector(`.p-tab:nth-child(${['calendar','contacts','tasks'].indexOf(app)+1})`);
    if (tabEl) switchTab(app, tabEl);
  }
}

// ── MODAL helpers ──────────────────────────────────────────
function openModal(id) { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }
document.addEventListener('keydown', e => { if (e.key === 'Escape') { closeCompose(); document.querySelectorAll('.modal-backdrop.open').forEach(m => m.classList.remove('open')); }});

// ── UTILS ──────────────────────────────────────────────────
function esc(s) { return (s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;'); }

function strToColor(str) {
  const colors = ['#4f8ef7','#3dd68c','#f75f5f','#f7a84f','#a78bfa','#2dd4bf','#ec4899'];
  let hash = 0; for (let c of (str||'')) hash = c.charCodeAt(0) + ((hash<<5)-hash);
  return colors[Math.abs(hash) % colors.length];
}

function toLocalDT(d) {
  const pad = n => String(n).padStart(2,'0');
  return `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

function formatDate(dateStr) {
  if (!dateStr) return '';
  const d = new Date(dateStr);
  const today = new Date(); today.setHours(0,0,0,0);
  const diff = Math.round((d-today)/86400000);
  if (diff === 0) return 'Auj.';
  if (diff === 1) return 'Dem.';
  const months = ['Jan','Fév','Mar','Avr','Mai','Jun','Jul','Aoû','Sep','Oct','Nov','Déc'];
  return `${d.getDate()} ${months[d.getMonth()]}`;
}

function toggleUserMenu() { showToast('Profil — '+APP_CONFIG.userName); }