/**
 * Catalogue : sélection produits + lien WhatsApp (accueil public & espace partenaire).
 * Requiert window.__ESADISS_WA__ (chiffres uniquement), défini depuis les pages PHP.
 */
(function () {
  function escapeHtml(s) {
    if (s == null) return '';
    var div = document.createElement('div');
    div.textContent = s;
    return div.innerHTML;
  }

  function formatPrix(n) {
    var x = Number(n);
    if (isNaN(x)) return '';
    return new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0, minimumFractionDigits: 0 }).format(Math.round(x));
  }

  function getWaPhone() {
    return typeof window.__ESADISS_WA__ === 'string' ? window.__ESADISS_WA__.replace(/\D/g, '') : '';
  }

  function buildWaUrl(lines) {
    var phone = getWaPhone();
    if (!phone || !lines || !lines.length) return '';
    var body =
      'Bonjour ESADISS,\n\nJe souhaite des informations sur :\n\n' +
      lines.map(function (l) { return '• ' + l; }).join('\n');
    return 'https://wa.me/' + phone + '?text=' + encodeURIComponent(body);
  }

  function updateToolbar(toolbar, contentRoot, mode) {
    if (!toolbar || !contentRoot) return;
    var phone = getWaPhone();
    var boxes = contentRoot.querySelectorAll('.js-catalog-select:checked');
    var n = boxes.length;
    var countLive = toolbar.querySelector('.catalog-toolbar__count-live');
    var countDesktop = toolbar.querySelector('.catalog-toolbar__count-desktop');
    var countMobile = toolbar.querySelector('.catalog-toolbar__count-mobile');
    var meta = toolbar.querySelector('.catalog-toolbar__meta');
    var btn = toolbar.querySelector('.catalog-toolbar__wa');
    var clearBtn = toolbar.querySelector('.catalog-toolbar__clear');
    var fullLabel =
      n === 0
        ? 'Aucun produit sélectionné'
        : n + ' produit' + (n > 1 ? 's' : '') + ' sélectionné' + (n > 1 ? 's' : '');
    if (countLive) countLive.textContent = fullLabel;
    if (countDesktop) countDesktop.textContent = fullLabel;
    if (countMobile) countMobile.textContent = String(n);
    if (meta) meta.setAttribute('title', fullLabel);
    if (btn) {
      btn.disabled = n === 0 || !phone;
      if (phone && n > 0) {
        var lines = [];
        boxes.forEach(function (cb) {
          var card = cb.closest('.produit-card');
          if (!card) return;
          if (mode === 'partenaire') {
            lines.push(
              card.getAttribute('data-nom') +
                ' — partenaire ' +
                formatPrix(card.getAttribute('data-prix-partenaire')) +
                ' ' +
                (card.getAttribute('data-unite') || '') +
                ' / public ' +
                formatPrix(card.getAttribute('data-prix-client')) +
                ' ' +
                (card.getAttribute('data-unite') || '')
            );
          } else {
            lines.push(
              card.getAttribute('data-nom') +
                ' — prix public indicatif ' +
                formatPrix(card.getAttribute('data-prix-client')) +
                ' ' +
                (card.getAttribute('data-unite') || '')
            );
          }
        });
        btn.onclick = function () {
          window.open(buildWaUrl(lines), '_blank', 'noopener,noreferrer');
        };
      } else {
        btn.onclick = null;
      }
    }
    if (clearBtn) {
      clearBtn.classList.toggle('is-hidden', n === 0);
    }
  }

  function initCatalogToolbar(contentId, mode) {
    var toolbar = document.getElementById('catalog-toolbar');
    var content = document.getElementById(contentId);
    if (!toolbar || !content || !getWaPhone()) return;

    toolbar.hidden = false;
    toolbar.classList.add('catalog-toolbar--visible');

    if (content.dataset.esadissWaBound === '1') {
      updateToolbar(toolbar, content, mode);
      return;
    }
    content.dataset.esadissWaBound = '1';

    var clearBtn = toolbar.querySelector('.catalog-toolbar__clear');
    var allBtn = toolbar.querySelector('.catalog-toolbar__all');

    function refresh() {
      updateToolbar(toolbar, content, mode);
    }

    content.addEventListener('change', function (e) {
      if (e.target && e.target.classList.contains('js-catalog-select')) refresh();
    });

    if (clearBtn) {
      clearBtn.addEventListener('click', function () {
        content.querySelectorAll('.js-catalog-select').forEach(function (cb) {
          cb.checked = false;
        });
        refresh();
      });
    }

    if (allBtn) {
      allBtn.addEventListener('click', function () {
        var list = content.querySelectorAll('.js-catalog-select');
        var allOn = Array.prototype.every.call(list, function (cb) { return cb.checked; });
        list.forEach(function (cb) {
          cb.checked = !allOn;
        });
        refresh();
      });
    }

    refresh();
  }

  window.EsadissCatalog = {
    escapeHtml: escapeHtml,
    formatPrix: formatPrix,
    getWaPhone: getWaPhone,
    initCatalogToolbar: initCatalogToolbar,
    refreshToolbar: function (contentId, mode) {
      var toolbar = document.getElementById('catalog-toolbar');
      var content = document.getElementById(contentId);
      if (toolbar && content) updateToolbar(toolbar, content, mode);
    }
  };
})();
