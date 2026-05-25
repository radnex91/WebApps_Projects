    </div><!-- .page-content -->
</div><!-- #main-content -->

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
<script>
function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('open');
    document.getElementById('sidebarOverlay').classList.toggle('show');
}
function closeSidebar() {
    document.getElementById('sidebar').classList.remove('open');
    document.getElementById('sidebarOverlay').classList.remove('show');
}

const TOAST_TITLES = { success: 'Succès', danger: 'Erreur', warning: 'Attention', info: 'Info' };
const TOAST_ICONS  = { success: 'bi-check-circle-fill', danger: 'bi-x-circle-fill', warning: 'bi-exclamation-triangle-fill', info: 'bi-info-circle-fill' };
const TOAST_DURATIONS = { success: 3500, danger: 5000, warning: 4500, info: 3500 };

const toastCounters = {};

function showToast(msg, type = 'success', duration) {
    type = type === 'error' ? 'danger' : type;
    duration = duration || TOAST_DURATIONS[type] || 3500;
    const zone = document.getElementById('toastZone');
    if (!zone) return;

    const key = type + '::' + msg;

    // Si un toast identique existe déjà, incrémenter le compteur
    const escaped = key.replace(/\\/g, '\\\\').replace(/"/g, '\\"');
    const existing = document.querySelector('.toast-item[data-toast-key="' + escaped + '"]');
    if (existing && !existing._dismissed) {
        clearTimeout(existing._timer);
        const badge = existing.querySelector('.toast-counter');
        toastCounters[key] = (toastCounters[key] || 1) + 1;
        badge.textContent = '+' + toastCounters[key];
        badge.style.display = '';
        // Reset progress bar
        const bar = existing.querySelector('.toast-progress-bar');
        bar.style.transition = 'none';
        bar.style.width = '100%';
        requestAnimationFrame(() => {
            bar.style.transition = 'width ' + duration + 'ms linear';
            bar.style.width = '0%';
        });
        existing._timer = setTimeout(() => dismissToast(existing.querySelector('.toast-close')), duration);
        return;
    }

    toastCounters[key] = 1;

    const el = document.createElement('div');
    el.className = 'toast-item';
    el.setAttribute('data-toast-key', key);
    el.innerHTML = '\
        <div class="toast-icon-wrap ' + type + '">\
            <i class="bi ' + (TOAST_ICONS[type] || TOAST_ICONS.info) + ' toast-icon ' + type + '"></i>\
        </div>\
        <div class="toast-body">\
            <div class="toast-title">' + (TOAST_TITLES[type] || type) + '</div>\
            <div class="toast-msg">' + msg + '</div>\
        </div>\
        <div class="toast-counter" style="display:none">+1</div>\
        <button class="toast-close" onclick="dismissToast(this)"><i class="bi bi-x-lg"></i></button>\
        <div class="toast-progress"><div class="toast-progress-bar ' + type + '" style="width:100%"></div></div>\
    ';
    zone.appendChild(el);
    requestAnimationFrame(() => requestAnimationFrame(() => el.classList.add('show')));

    const bar = el.querySelector('.toast-progress-bar');
    bar.style.transitionDuration = duration + 'ms';
    setTimeout(() => { bar.style.width = '0%'; }, 30);

    el._timer = setTimeout(() => dismissToast(el.querySelector('.toast-close')), duration);
}

function dismissToast(btnOrEl) {
    const item = btnOrEl.closest ? btnOrEl.closest('.toast-item') : btnOrEl;
    if (!item || item._dismissed) return;
    item._dismissed = true;
    clearTimeout(item._timer);
    item.classList.remove('show');
    item.classList.add('hide');
    setTimeout(() => item.remove(), 400);
}

// Flash from PHP
if (window._flashToast) {
    const f = window._flashToast;
    const type = f.type === 'error' ? 'danger' : (f.type || 'info');
    setTimeout(() => showToast(f.message, type), 300);
}
</script>
<?php if (isset($extraScript)) echo $extraScript; ?>
</body>
</html>
