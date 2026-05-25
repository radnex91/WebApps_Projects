(function() {
  // --- Sidebar toggle ---
  const sidebar = document.getElementById('sidebar');
  const overlay = document.getElementById('sidebarOverlay');
  const toggleBtn = document.getElementById('sidebarToggle');

  if (toggleBtn && sidebar) {
    toggleBtn.addEventListener('click', function(e) {
      e.stopPropagation();
      sidebar.classList.toggle('open');
      if (overlay) overlay.classList.toggle('show');
    });
  }

  if (overlay) {
    overlay.addEventListener('click', function() {
      sidebar.classList.remove('open');
      overlay.classList.remove('show');
    });
  }

  // --- User dropdown toggle ---
  const userToggle = document.getElementById('userDropdownToggle');
  const userDropdown = document.getElementById('userDropdown');

  if (userToggle && userDropdown) {
    userToggle.addEventListener('click', function(e) {
      e.stopPropagation();
      userDropdown.classList.toggle('show');
    });

    document.addEventListener('click', function() {
      userDropdown.classList.remove('show');
    });
  }

  // --- Ripple effect on Material buttons ---
  document.querySelectorAll('.btn-material, .ripple').forEach(function(el) {
    el.addEventListener('click', function(e) {
      const rect = el.getBoundingClientRect();
      const size = Math.max(rect.width, rect.height);
      const x = e.clientX - rect.left - size / 2;
      const y = e.clientY - rect.top - size / 2;

      const ripple = document.createElement('span');
      ripple.style.cssText = [
        'position: absolute',
        'border-radius: 50%',
        'background: rgba(255,255,255,.35)',
        'width: ' + size + 'px',
        'height: ' + size + 'px',
        'top: ' + y + 'px',
        'left: ' + x + 'px',
        'transform: scale(0)',
        'animation: ripple-anim .5s ease-out',
        'pointer-events: none'
      ].join(';');

      el.style.position = 'relative';
      el.style.overflow = 'hidden';
      el.appendChild(ripple);
      setTimeout(function() { ripple.remove(); }, 500);
    });
  });
})();
