  </main>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/feather-icons/4.29.0/feather.min.js"></script>
<script>
// Initialize Feather icons
if (typeof feather !== 'undefined') feather.replace();

// Modal helpers
function openModal(id) {
  var modal = document.getElementById(id);
  modal.classList.add('open');
  modal.setAttribute('aria-hidden', 'false');
  var focusable = modal.querySelector('input, select, textarea, button:not(.modal-close), .modal-close');
  if (focusable) setTimeout(function() { focusable.focus(); }, 100);
}
function closeModal(id) {
  var modal = document.getElementById(id);
  modal.classList.remove('open');
  modal.setAttribute('aria-hidden', 'true');
}
document.querySelectorAll('.modal-bg').forEach(m => {
  m.setAttribute('role', 'dialog');
  m.setAttribute('aria-modal', 'true');
  m.setAttribute('aria-hidden', 'true');
  m.addEventListener('click', e => { if (e.target === m) closeModal(m.id); });
});

// ============================================
// NOTIFICATIONS TOAST — Xbox Series Style
// ============================================
(function() {
  var container = document.createElement('div');
  container.id = 'toast-container';
  container.style.cssText = 'position:fixed;bottom:24px;right:24px;z-index:9999;display:flex;flex-direction:column;align-items:flex-end;gap:10px;pointer-events:none;';
  document.body.appendChild(container);

  if (!document.getElementById('xbox-toast-styles')) {
    var s = document.createElement('style');
    s.id = 'xbox-toast-styles';
    s.textContent = '\
      .xbox-toast {\
        pointer-events:auto; display:flex; align-items:stretch;\
        min-width:320px; max-width:440px; position:relative; overflow:hidden;\
        background:rgba(28,28,38,.94); backdrop-filter:blur(20px) saturate(180%);\
        -webkit-backdrop-filter:blur(20px) saturate(180%);\
        border-radius:12px; border:1px solid rgba(255,255,255,.08);\
        box-shadow:0 8px 32px rgba(0,0,0,.35),0 2px 8px rgba(0,0,0,.18);\
        animation:xboxToastIn .35s cubic-bezier(.16,1,.3,1);\
      }\
      [data-theme="light"] .xbox-toast {\
        background:rgba(255,255,255,.96); border:1px solid rgba(0,0,0,.08);\
        box-shadow:0 8px 32px rgba(0,0,0,.12),0 2px 8px rgba(0,0,0,.05);\
      }\
      .xbox-toast-accent { width:4px; flex-shrink:0; }\
      .xbox-toast-icon { display:flex; align-items:center; justify-content:center; padding:14px 0 14px 14px; flex-shrink:0; }\
      .xbox-toast-msg { flex:1; padding:14px 10px; font-size:14px; font-weight:500; color:#f1f5f9; line-height:1.45; font-family:var(--font-body); }\
      [data-theme="light"] .xbox-toast-msg { color:#1e293b; }\
      .xbox-toast-close { display:flex; align-items:center; justify-content:center; background:none; border:none; color:rgba(255,255,255,.35); cursor:pointer; padding:0 14px 0 6px; flex-shrink:0; transition:color .15s; border-radius:8px; }\
      .xbox-toast-close:hover { color:rgba(255,255,255,.85); }\
      [data-theme="light"] .xbox-toast-close { color:rgba(0,0,0,.25); }\
      [data-theme="light"] .xbox-toast-close:hover { color:rgba(0,0,0,.65); }\
      .xbox-toast-progress { position:absolute; bottom:0; left:0; height:3px; border-radius:0 0 12px 12px; animation:xboxProgress linear forwards; opacity:.55; }\
      .xbox-toast-out { animation:xboxToastOut .28s ease forwards; }\
      @keyframes xboxToastIn { from { transform:translateX(110%); opacity:0; } to { transform:translateX(0); opacity:1; } }\
      @keyframes xboxToastOut { from { transform:translateX(0); opacity:1; } to { transform:translateX(110%); opacity:0; } }\
      @keyframes xboxProgress { from { width:100%; } to { width:0%; } }\
      @media (max-width:500px) {\
        .xbox-toast { min-width:0; max-width:calc(100vw - 48px); }\
      }\
    ';
    document.head.appendChild(s);
  }

  var accentMap = {
    success: { color:'#10b981', icon:'<path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>' },
    error:   { color:'#ef4444', icon:'<circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/>' },
    warning: { color:'#f59e0b', icon:'<path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>' },
    info:    { color:'#3b82f6', icon:'<circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/>' }
  };

  window.showToast = function(message, type, duration) {
    type = type || 'info';
    duration = duration || 5000;
    var cfg = accentMap[type] || accentMap.info;

    var toast = document.createElement('div');
    toast.className = 'xbox-toast';
    toast.innerHTML = '\
      <div class="xbox-toast-accent" style="background:' + cfg.color + '"></div>\
      <div class="xbox-toast-icon" style="color:' + cfg.color + '">\
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' + cfg.icon + '</svg>\
      </div>\
      <div class="xbox-toast-msg">' + message + '</div>\
      <button class="xbox-toast-close" onclick="dismissToast(this.parentElement)">\
        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>\
      </button>\
      <div class="xbox-toast-progress" style="background:' + cfg.color + ';animation-duration:' + duration + 'ms"></div>\
    ';

    container.appendChild(toast);

    var timer = setTimeout(function() { dismissToast(toast); }, duration);

    toast.addEventListener('mouseenter', function() {
      clearTimeout(timer);
      var bar = toast.querySelector('.xbox-toast-progress');
      if (bar) bar.style.animationPlayState = 'paused';
    });
    toast.addEventListener('mouseleave', function() {
      var bar = toast.querySelector('.xbox-toast-progress');
      if (bar) bar.style.animationPlayState = 'running';
      timer = setTimeout(function() { dismissToast(toast); }, 2500);
    });
  };

  window.dismissToast = function(toast) {
    if (!toast || !toast.parentElement) return;
    toast.classList.add('xbox-toast-out');
    setTimeout(function() { toast.remove(); }, 300);
  };
})();

// Flash messages auto-hide as toast
document.querySelectorAll('.alert').forEach(function(a) {
  var type = a.classList.contains('alert-success') ? 'success' :
             a.classList.contains('alert-danger') ? 'error' :
             a.classList.contains('alert-warning') ? 'warning' : 'info';
  var msg = a.textContent.trim();
  a.remove();
  showToast(msg, type, 5000);
});

// ============================================
// RACCOURCIS CLAVIER
// ============================================
document.addEventListener('keydown', function(e) {
  if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA' || e.target.isContentEditable) {
    if (e.key === 'Escape') {
      document.querySelectorAll('.modal-bg.open').forEach(m => m.classList.remove('open'));
    }
    return;
  }

  if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
    e.preventDefault();
    var searchInput = document.querySelector('input[name="s"]');
    if (searchInput) { searchInput.focus(); searchInput.select(); }
  }

  if (e.key === 'n' || e.key === 'N') {
    var modalProduit = document.getElementById('modal-produit');
    if (modalProduit) openModal('modal-produit');
  }

  if (e.key === 'm' || e.key === 'M') {
    var formMvt = document.querySelector('form[method="POST"]');
    if (formMvt) {
      formMvt.scrollIntoView({ behavior: 'smooth', block: 'center' });
      formMvt.querySelector('select[name="produit_id"]')?.focus();
    }
  }

  if (e.key === 'd' || e.key === 'D') {
    window.location.href = 'dashboard.php';
  }

  if (e.key === 'Escape') {
    document.querySelectorAll('.modal-bg.open').forEach(m => m.classList.remove('open'));
  }
});
</script>
</body>
</html>