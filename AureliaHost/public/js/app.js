// AureliaHost — Premium Sidebar Interactions
document.addEventListener('DOMContentLoaded', () => {
    const sidebar    = document.getElementById('sidebar');
    const toggleBtn  = document.getElementById('sidebarToggle');
    const mobileBtn  = document.getElementById('mobileSidebarToggle');
    const overlay    = document.getElementById('sidebarOverlay');

    // --- Desktop collapse toggle ---
    if (toggleBtn) {
        toggleBtn.addEventListener('click', () => {
            sidebar.classList.toggle('collapsed');
            localStorage.setItem('sidebar_collapsed', sidebar.classList.contains('collapsed'));
        });
    }

    // Restore sidebar state
    if (localStorage.getItem('sidebar_collapsed') === 'true') {
        sidebar?.classList.add('collapsed');
    }

    // --- Mobile toggle ---
    if (mobileBtn) {
        mobileBtn.addEventListener('click', () => {
            sidebar.classList.toggle('mobile-open');
            overlay?.classList.toggle('show');
            if (overlay) {
                var isVisible = overlay.classList.contains('show');
                overlay.setAttribute('aria-hidden', isVisible ? 'false' : 'true');
            }
        });
    }

    if (overlay) {
        overlay.addEventListener('click', () => {
            sidebar.classList.remove('mobile-open');
            overlay.classList.remove('show');
            overlay.setAttribute('aria-hidden', 'true');
        });

        // Escape key closes mobile sidebar
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && overlay.classList.contains('show')) {
                sidebar.classList.remove('mobile-open');
                overlay.classList.remove('show');
                overlay.setAttribute('aria-hidden', 'true');
            }
        });
    }

    // --- Submenu toggles ---
    document.querySelectorAll('.sidebar-link.has-submenu').forEach(btn => {
        btn.addEventListener('click', (e) => {
            if (sidebar.classList.contains('collapsed')) {
                e.preventDefault();
                sidebar.classList.remove('collapsed');
                localStorage.setItem('sidebar_collapsed', 'false');
                setTimeout(() => {
                    const submenuId = btn.dataset.submenu;
                    const submenu = document.getElementById(submenuId);
                    if (submenu) { submenu.classList.add('open'); btn.classList.add('open'); }
                }, 300);
                return;
            }

            const submenuId = btn.dataset.submenu;
            const submenu = document.getElementById(submenuId);
            if (submenu) {
                submenu.classList.toggle('open');
                btn.classList.toggle('open');
            }
        });
    });

    // --- Auto-hide alerts ---
    document.querySelectorAll('.alert-dismissible').forEach(alert => {
        setTimeout(() => {
            const bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
            if (bsAlert) bsAlert.close();
        }, 5000);
    });

    // --- Password toggle ---
    const passwordToggle = document.getElementById('passwordToggle');
    if (passwordToggle) {
        passwordToggle.addEventListener('click', () => {
            const passwordInput = document.getElementById('password');
            const icon = passwordToggle.querySelector('i');
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                icon.classList.remove('bi-eye');
                icon.classList.add('bi-eye-slash');
                passwordToggle.setAttribute('aria-label', 'Masquer le mot de passe');
            } else {
                passwordInput.type = 'password';
                icon.classList.remove('bi-eye-slash');
                icon.classList.add('bi-eye');
                passwordToggle.setAttribute('aria-label', 'Afficher le mot de passe');
            }
        });
    }

    // --- Login form loading state ---
    const loginForm = document.querySelector('.login-form');
    if (loginForm) {
        loginForm.addEventListener('submit', function (e) {
            const emailInput = document.getElementById('email');
            const passwordInput = document.getElementById('password');
            let hasError = false;

            // Clear previous errors
            document.querySelectorAll('.field-error').forEach(el => el.remove());
            document.querySelectorAll('.premium-input.has-error').forEach(el => el.classList.remove('has-error'));

            // Validate email
            if (emailInput && !emailInput.value.trim()) {
                showFieldError(emailInput, 'L\'adresse email est requise');
                hasError = true;
            } else if (emailInput && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailInput.value.trim())) {
                showFieldError(emailInput, 'Format d\'email invalide');
                hasError = true;
            }

            // Validate password
            if (passwordInput && !passwordInput.value) {
                showFieldError(passwordInput, 'Le mot de passe est requis');
                hasError = true;
            }

            if (hasError) {
                e.preventDefault();
                return;
            }

            // Show loading state
            const submitBtn = document.getElementById('loginSubmit');
            if (submitBtn) {
                submitBtn.classList.add('loading');
                submitBtn.setAttribute('aria-disabled', 'true');
            }
        });
    }

    function showFieldError(input, message) {
        const group = input.closest('.login-input-group');
        if (!group) return;
        const errorEl = document.createElement('span');
        errorEl.className = 'field-error';
        errorEl.textContent = message;
        group.appendChild(errorEl);
        input.classList.add('has-error');
        input.addEventListener('input', () => {
            input.classList.remove('has-error');
            const err = group.querySelector('.field-error');
            if (err) err.remove();
        }, { once: true });
    }
});
