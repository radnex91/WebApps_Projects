<?php
// Footer - DanayLedger
$currentUser = $currentUser ?? getCurrentUser();
$role = $_SESSION['user_role'] ?? '';
$roleLabel = getRoleLabel($role);
$userInitials = strtoupper(mb_substr($_SESSION['full_name'] ?? 'U', 0, 1) . mb_substr($_SESSION['full_name'] ?? '', 1, 1));
if (mb_strlen($_SESSION['full_name'] ?? '') < 2) $userInitials = strtoupper(mb_substr($_SESSION['full_name'] ?? 'U', 0, 1));
?>

    </div><!-- /.page-content -->
</div><!-- /.main-content -->
</div><!-- /.wrapper -->

<!-- Toast Container — Style PS5 -->
<div class="toast-container-ps5" id="toastContainer" aria-live="polite" aria-atomic="true"></div>

<!-- Modal de confirmation Style PS5 -->
<div class="modal fade" id="confirmModal" tabindex="-1" aria-labelledby="confirmTitle" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content confirm-modal-content">
            <div class="modal-body confirm-modal-body">
                <div class="confirm-icon">⚠️</div>
                <h6 class="confirm-title" id="confirmTitle">Confirmation</h6>
                <p class="confirm-message" id="confirmMessage">Êtes-vous sûr ?</p>
                <div class="confirm-actions">
                    <button type="button" class="btn btn-light" id="confirmCancel" data-bs-dismiss="modal">Annuler</button>
                    <button type="button" class="btn btn-danger" id="confirmOk">Confirmer</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Main Header -->
<header class="main-header" role="banner" style="display:none;">
    <!-- Header is rendered in the page, not here -->
</header>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
<?php if (!empty($loadChart)): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js" integrity="sha384-NrKB+u6Ts6AtkIhwPixiKTzgSKNblyhlk0Sohlgar9UHUBzai/sgnNNWWd291xqt" crossorigin="anonymous"></script>
<?php endif; ?>
<script src="<?php echo APP_URL; ?>/assets/js/app.js"></script>

<?php
// Capturer les flash messages AVANT qu'ils soient consommés par displayFlashMessages() dans la page
// On lit directement les clés flash_ dans la session
$toastMessages = [];
foreach (['success', 'error', 'warning', 'info'] as $tp) {
    $key = 'flash_' . $tp;
    if (!empty($_SESSION[$key])) {
        $toastMessages[] = ['type' => $tp, 'message' => $_SESSION[$key]];
        unset($_SESSION[$key]); // nettoyer après capture
    }
}
?>

<script>
(function() {
    // Toasts depuis flash messages PHP
    var toasts = <?php echo json_encode($toastMessages, JSON_UNESCAPED_UNICODE); ?>;

    // Empêcher la fermeture des modals au clic extérieur et à la touche Échap
    function initModals() {
        document.querySelectorAll('.modal').forEach(function (modal) {
            modal.addEventListener('show.bs.modal', function () {
                var bsModal = bootstrap.Modal.getOrCreateInstance(modal, {
                    backdrop: 'static',
                    keyboard: false
                });
                bsModal._config.backdrop = 'static';
                bsModal._config.keyboard = false;
            });
        });
    }

    // Afficher les toasts stockés
    function showStoredToasts() {
        if (toasts && toasts.length) {
            toasts.forEach(function(t) {
                showToast(t.message, t.type);
            });
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        initModals();
        // Petit délai pour laisser le DOM respirer
        setTimeout(function() { showStoredToasts(); }, 100);
    });
})();

// --- Toast System Style PS5 ---
function showToast(message, type, title, duration) {
    type = type || 'info';
    duration = duration || 4000;

    var icons = {
        success: '✓',
        error: '✕',
        warning: '⚠',
        info: 'ℹ'
    };

    var titles = {
        success: title || 'Succès',
        error: title || 'Erreur',
        warning: title || 'Attention',
        info: title || 'Information'
    };

    var container = document.getElementById('toastContainer');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toastContainer';
        container.className = 'toast-container-ps5';
        document.body.appendChild(container);
    }

    // Détection de doublon : même type + même message
    var key = type + '::' + message;
    var existing = document.querySelector('.toast-ps5[data-toast-key="' + CSS.escape(key) + '"]');
    if (existing && !existing.classList.contains('toast-ps5-hiding')) {
        var badge = existing.querySelector('.toast-ps5-count');
        var count = badge ? parseInt(badge.textContent) + 1 : 2;
        if (!badge) {
            badge = document.createElement('span');
            badge.className = 'toast-ps5-count';
            existing.appendChild(badge);
        }
        badge.textContent = count;
        // Reset du timer
        clearTimeout(existing._dismissTimer);
        var bar = existing.querySelector('.toast-ps5-bar');
        bar.style.transition = 'none';
        bar.style.width = '100%';
        requestAnimationFrame(function () {
            bar.style.transition = 'width ' + duration + 'ms linear';
            bar.style.width = '0%';
        });
        existing._dismissTimer = setTimeout(function () { dismissToast(existing); }, duration);
        return;
    }

    var toast = document.createElement('div');
    toast.className = 'toast-ps5 toast-ps5-' + type;
    toast.setAttribute('data-toast-key', key);
    toast.innerHTML = ''
        + '<div class="toast-ps5-icon">' + icons[type] + '</div>'
        + '<div class="toast-ps5-body">'
        + '  <div class="toast-ps5-title">' + titles[type] + '</div>'
        + '  <div class="toast-ps5-message">' + message + '</div>'
        + '</div>'
        + '<button class="toast-ps5-close" onclick="dismissToast(this.parentElement)">×</button>'
        + '<div class="toast-ps5-progress"><div class="toast-ps5-bar"></div></div>';

    container.appendChild(toast);

    // Animation entrée
    requestAnimationFrame(function () {
        toast.classList.add('toast-ps5-show');
    });

    // Barre de progression
    var bar = toast.querySelector('.toast-ps5-bar');
    bar.style.transition = 'width ' + duration + 'ms linear';
    requestAnimationFrame(function () {
        bar.style.width = '0%';
    });

    // Auto-dismiss
    toast._dismissTimer = setTimeout(function () { dismissToast(toast); }, duration);
}

function dismissToast(toast) {
    if (!toast || toast.classList.contains('toast-ps5-hiding')) return;
    toast.classList.add('toast-ps5-hiding');
    toast.classList.remove('toast-ps5-show');
    setTimeout(function () {
        if (toast.parentNode) toast.parentNode.removeChild(toast);
    }, 400);
}

// --- Formatage séparateurs de milliers sur les champs montant ---
(function() {
    // Regex qui capture TOUS les types d'espaces (normaux, insécables, narrow, etc.)
    var ALL_SPACES = /[\s\u00A0\u2000-\u200A\u202F\u205F\u3000]/g;

    function formatNumber(input) {
        var cursorPos = input.selectionStart;
        var raw = input.value.replace(ALL_SPACES, '');
        if (raw === '' || isNaN(raw)) return;
        var beforeLen = input.value.length;
        // Utiliser espace normale au lieu du narrow no-break space
        input.value = Number(raw).toLocaleString('fr-FR').replace(/\s/g, ' ').replace(/\u202F/g, ' ');
        var afterLen = input.value.length;
        var diff = afterLen - beforeLen;
        input.setSelectionRange(cursorPos + diff, cursorPos + diff);
    }

    function bindAmountInput(input) {
        if (input.dataset.amountFormatted) return;
        input.dataset.amountFormatted = '1';
        input.type = 'text';
        input.inputMode = 'numeric';

        // Formatage initial
        if (input.value) formatNumber(input);

        input.addEventListener('input', function() {
            formatNumber(input);
        });

        input.addEventListener('blur', function() {
            formatNumber(input);
        });

        // Nettoyer avant submit du form parent
        var form = input.closest('form');
        if (form && !form.dataset.amountBound) {
            form.dataset.amountBound = '1';
            form.addEventListener('submit', function() {
                form.querySelectorAll('[data-amount-formatted]').forEach(function(inp) {
                    inp.value = inp.value.replace(ALL_SPACES, '');
                });
            });
        }
    }

    // Cibler tous les champs montant existants
    function scanAmountInputs() {
        document.querySelectorAll('input[name="montant"], input[name="montantexpedition"], input[name="montantAccompagnement"], input[name="sommeverse"], input.amount-input').forEach(bindAmountInput);
    }

    // Scan initial
    document.addEventListener('DOMContentLoaded', function() {
        scanAmountInputs();
    });

    // Re-scan quand un modal s'ouvre (Bootstrap)
    document.addEventListener('shown.bs.modal', function() {
        scanAmountInputs();
    });
})();
</script>
</body>
</html>