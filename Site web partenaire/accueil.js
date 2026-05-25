var API_BASE = 'api.php';

function ec() {
  return window.EsadissCatalog || {};
}

function attrEsc(s) {
  return String(s)
    .replace(/&/g, '&amp;')
    .replace(/"/g, '&quot;')
    .replace(/</g, '&lt;');
}

function resolveImageUrl(image) {
  if (!image) return '';
  var raw = String(image).trim();
  if (!raw) return '';
  if (/^(https?:)?\/\//i.test(raw) || raw.indexOf('data:image/') === 0 || raw.charAt(0) === '/') {
    return raw;
  }
  if (raw.indexOf('uploads/') === 0 || raw.indexOf('images/') === 0 || raw.indexOf('assets/') === 0) {
    return raw;
  }
  return 'uploads/' + raw;
}

function imageCandidates(image) {
  if (!image) return [];
  var raw = String(image).trim();
  if (!raw) return [];
  if (/^(https?:)?\/\//i.test(raw) || raw.indexOf('data:image/') === 0 || raw.charAt(0) === '/') {
    return [raw];
  }
  var list = [raw];
  if (raw.indexOf('uploads/') !== 0) list.push('uploads/' + raw);
  if (raw.indexOf('uploads/produits/') !== 0) list.push('uploads/produits/' + raw);
  if (raw.indexOf('images/') !== 0) list.push('images/' + raw);
  if (raw.indexOf('assets/') !== 0) list.push('assets/' + raw);
  return list.filter(function (v, i, a) { return v && a.indexOf(v) === i; });
}

function bindImageFallbacks(content) {
  if (!content) return;
  content.querySelectorAll('.js-produit-img').forEach(function (img) {
    var data = img.getAttribute('data-candidates') || '';
    var candidates = data ? data.split('|') : [];
    var idx = parseInt(img.getAttribute('data-idx') || '0', 10);
    img.addEventListener('error', function () {
      idx += 1;
      if (idx < candidates.length) {
        img.setAttribute('data-idx', String(idx));
        img.src = candidates[idx];
        return;
      }
      var media = img.parentNode;
      if (media) {
        media.classList.add('is-broken');
      }
      img.remove();
    });
  });
}

function renderProduits(produits, content) {
  if (!content) return;
  var E = ec();
  var escapeHtml = E.escapeHtml || function (s) { return s; };
  var formatPrix = E.formatPrix || function (n) { return String(n); };
  var selectable = typeof E.getWaPhone === 'function' && E.getWaPhone() !== '';

  if (!produits || !Array.isArray(produits)) {
    content.innerHTML = '<div class="empty-state">Erreur lors du chargement.</div>';
    return;
  }
  if (produits.length === 0) {
    content.innerHTML = '<div class="empty-state">Aucun produit pour cette recherche.</div>';
    return;
  }

  content.innerHTML =
    '<div class="produits-grid">' +
    produits
      .map(function (p, cardIndex) {
        var unite = p.unite || '';
        var nomEsc = escapeHtml(p.nom);
        var cands = imageCandidates(p.image);
        var imageUrl = cands.length ? cands[0] : resolveImageUrl(p.image);
        var candsAttr = attrEsc(cands.join('|'));
        var imgAttrs =
          ' class="js-produit-img" decoding="async" src="' +
          attrEsc(imageUrl) +
          '" data-candidates="' +
          candsAttr +
          '" data-idx="0" alt="' +
          attrEsc(p.nom) +
          '"';
        if (cardIndex === 0) {
          imgAttrs += ' loading="eager" fetchpriority="high"';
        } else {
          imgAttrs += ' loading="lazy" fetchpriority="low"';
        }
        var check =
          '<label class="produit-card__check">' +
          '<input type="checkbox" class="js-catalog-select visually-hidden" aria-label="Sélectionner pour WhatsApp : ' +
          nomEsc +
          '">' +
          '<span class="produit-card__check-ui" aria-hidden="true"></span>' +
          '</label>';

        var head =
          '<div class="produit-card__head">' +
          (selectable ? check : '') +
          '<h3 class="produit-card__title">' +
          nomEsc +
          '</h3></div>';

        var imageBlock = imageUrl
          ? '<div class="produit-card__media"><img' + imgAttrs + '></div>'
          : '<div class="produit-card__media is-broken"><div class="produit-card__media-ph" aria-hidden="true">Aucune image</div></div>';

        var prices =
          '<div class="produit-card__prices produit-card__prices--single">' +
          '<div class="prix-cell prix-cell--accent">' +
          '<span class="prix-cell__label">Prix public indicatif</span>' +
          '<span class="prix-cell__value">' +
          formatPrix(p.prix_client) +
          ' ' +
          escapeHtml(unite) +
          '</span></div></div>';

        var foot =
          '<p class="produit-card__hint">Tarif partenaire après <a href="login.php">connexion</a></p>';

        var dataAttrs =
          ' data-nom="' +
          attrEsc(p.nom) +
          '" data-prix-client="' +
          attrEsc(String(p.prix_client)) +
          '" data-unite="' +
          attrEsc(unite) +
          '"';

        var klass = 'produit-card' + (selectable ? ' produit-card--selectable' : '');
        return (
          '<article class="' +
          klass +
          '"' +
          dataAttrs +
          ' style="--card-index:' + cardIndex + ';"' +
          '>' +
          head +
          imageBlock +
          prices +
          foot +
          '</article>'
        );
      })
      .join('') +
    '</div>';
  bindImageFallbacks(content);
}

var searchTimer;
function chargerProduits(q) {
  var content = document.getElementById('content');
  if (!content) return;
  content.innerHTML = '<div class="empty-state">Chargement…</div>';
  var url = API_BASE + '?resource=produits&public=1';
  if (q && q.trim() !== '') {
    url += '&q=' + encodeURIComponent(q.trim());
  }
  fetch(url, { credentials: 'same-origin' })
    .then(function (res) {
      return res.text().then(function (text) {
        var body = null;
        try {
          body = text ? JSON.parse(text) : null;
        } catch (e) {
          body = null;
        }
        if (!res.ok) {
          var err =
            body && body.error
              ? body.error
              : 'Erreur ' + res.status + (text && text.length < 200 ? ' : ' + text : '') + '.';
          content.innerHTML =
            '<div class="empty-state empty-state--error">' + ec().escapeHtml(err) + '</div>';
          return null;
        }
        if (!body) {
          content.innerHTML =
            '<div class="empty-state">Réponse invalide du serveur.</div>';
          return null;
        }
        return body;
      });
    })
    .then(function (data) {
      if (data === null) return;
      if (data.error) {
        content.innerHTML =
          '<div class="empty-state empty-state--error">' + ec().escapeHtml(data.error) + '</div>';
        return;
      }
      renderProduits(data, content);
      if (window.EsadissCatalog && EsadissCatalog.initCatalogToolbar) {
        EsadissCatalog.initCatalogToolbar('content', 'public');
      }
    })
    .catch(function () {
      content.innerHTML = '<div class="empty-state">Erreur réseau. Réessayez.</div>';
    });
}

document.addEventListener('DOMContentLoaded', function () {
  chargerProduits('');
  var inp = document.getElementById('search-public');
  if (inp) {
    inp.addEventListener('input', function () {
      clearTimeout(searchTimer);
      var v = inp.value;
      searchTimer = setTimeout(function () {
        chargerProduits(v);
      }, 280);
    });
  }
});
