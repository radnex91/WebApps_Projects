// js/app.js

// Auto-close alerts after 5s
document.addEventListener('DOMContentLoaded', () => {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(a => {
        setTimeout(() => {
            a.style.transition = 'opacity .5s';
            a.style.opacity = '0';
            setTimeout(() => a.remove(), 500);
        }, 5000);
    });

    // Active nav link detection
    const links = document.querySelectorAll('.nav-link');
    const current = window.location.pathname;
    links.forEach(link => {
        const href = link.getAttribute('href');
        if (href && current.includes(href.replace(/^.*\/school_app/, '')) && href !== '#') {
            link.classList.add('active');
        }
    });

    // Confirm delete on all danger links with data-confirm
    document.querySelectorAll('[data-confirm]').forEach(el => {
        el.addEventListener('click', e => {
            if (!confirm(el.dataset.confirm)) e.preventDefault();
        });
    });

    // Mobile sidebar toggle
    const overlay = document.createElement('div');
    overlay.style.cssText = 'display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:90;';
    document.body.appendChild(overlay);

    const toggleBtn = document.querySelector('.toggle-btn');
    const sidebar = document.getElementById('sidebar');
    if (toggleBtn && sidebar) {
        toggleBtn.addEventListener('click', () => {
            if (window.innerWidth <= 768) {
                sidebar.classList.toggle('open');
                overlay.style.display = sidebar.classList.contains('open') ? 'block' : 'none';
            } else {
                sidebar.classList.toggle('collapsed');
            }
        });
        overlay.addEventListener('click', () => {
            sidebar.classList.remove('open');
            overlay.style.display = 'none';
        });
    }

    // Note input: color red if < 10
    document.querySelectorAll('input[name^="notes["]').forEach(input => {
        input.addEventListener('input', () => {
            const val = parseFloat(input.value);
            input.style.borderColor = (!isNaN(val) && val < 10) ? 'var(--danger)' : '';
            input.style.background = (!isNaN(val) && val < 10) ? '#fff0f0' : '';
        });
    });

    // Search: submit on Enter
    document.querySelectorAll('.search-bar input').forEach(inp => {
        inp.addEventListener('keydown', e => {
            if (e.key === 'Enter') inp.closest('form')?.submit();
        });
    });
});

// Print helper
function printBulletin() {
    window.print();
}
