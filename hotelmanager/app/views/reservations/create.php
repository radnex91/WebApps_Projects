<?php $page_title = 'Nouvelle réservation'; ?>

<div class="row justify-content-center">
<div class="col-lg-9">

<div class="card">
  <div class="card-header">
    <h5><i class="bi bi-plus-circle me-2 text-primary"></i>Créer une réservation</h5>
    <a href="<?= APP_URL ?>/index.php?page=reservations" class="btn btn-sm btn-outline-secondary">
      <i class="bi bi-arrow-left me-1"></i>Retour
    </a>
  </div>
  <div class="card-body">

    <form method="POST" action="<?= APP_URL ?>/index.php?page=reservations&action=store" id="resForm">
      <?= csrf_field() ?>

      <div class="row g-3">

        <!-- Client -->
        <div class="col-md-6">
          <label class="form-label" for="client_id">
            <i class="bi bi-person me-1"></i>Client <span class="text-danger">*</span>
          </label>
          <div class="d-flex gap-2">
            <select class="form-select" id="client_id" name="client_id" required>
              <option value="">— Sélectionner un client —</option>
              <?php foreach ($clients as $c): ?>
              <option value="<?= $c['id'] ?>"><?= e($c['nom_complet']) ?> · <?= e($c['telephone']) ?></option>
              <?php endforeach; ?>
            </select>
            <a href="<?= APP_URL ?>/index.php?page=clients&action=create" target="_blank"
               class="btn btn-outline-success btn-sm" title="Nouveau client">
              <i class="bi bi-person-plus"></i>
            </a>
          </div>
        </div>

        <!-- Source réservation -->
        <div class="col-md-6">
          <label class="form-label">Source</label>
          <select class="form-select" name="source">
            <option value="direct">Direct (réception)</option>
            <option value="telephone">Téléphone</option>
            <option value="internet">Internet</option>
            <option value="agence">Agence de voyage</option>
          </select>
        </div>

        <!-- Dates -->
        <div class="col-md-4">
          <label class="form-label" for="date_arrivee">
            <i class="bi bi-calendar-event me-1"></i>Date d'arrivée <span class="text-danger">*</span>
          </label>
          <input type="date" class="form-control" id="date_arrivee" name="date_arrivee"
                 min="<?= date('Y-m-d') ?>" required
                 value="<?= e($_POST['date_arrivee'] ?? date('Y-m-d')) ?>">
        </div>

        <div class="col-md-4">
          <label class="form-label" for="date_depart">
            <i class="bi bi-calendar-x me-1"></i>Date de départ <span class="text-danger">*</span>
          </label>
          <input type="date" class="form-control" id="date_depart" name="date_depart"
                 min="<?= date('Y-m-d', strtotime('+1 day')) ?>" required
                 value="<?= e($_POST['date_depart'] ?? date('Y-m-d', strtotime('+1 day'))) ?>">
        </div>

        <div class="col-md-4">
          <label class="form-label">Durée du séjour</label>
          <div class="form-control bg-light fw-bold text-center" id="dureeDisplay">
            1 nuit
          </div>
        </div>

        <!-- Nombre de personnes -->
        <div class="col-md-3">
          <label class="form-label">Adultes</label>
          <input type="number" class="form-control" name="nb_adultes" min="1" max="10" value="1">
        </div>
        <div class="col-md-3">
          <label class="form-label">Enfants</label>
          <input type="number" class="form-control" name="nb_enfants" min="0" max="10" value="0">
        </div>

        <!-- Chambre -->
        <div class="col-md-6">
          <label class="form-label" for="chambre_id">
            <i class="bi bi-door-open me-1"></i>Chambre <span class="text-danger">*</span>
          </label>
          <select class="form-select" id="chambre_id" name="chambre_id" required>
            <option value="">— Choisir une chambre —</option>
            <?php foreach ($chambres as $c): ?>
            <option value="<?= $c['id'] ?>"
                    data-tarif="<?= $c['tarif_nuit'] ?>"
                    data-type="<?= e($c['type_nom']) ?>">
              N° <?= e($c['numero']) ?> — <?= e($c['type_nom']) ?>
              (<?= format_money($c['tarif_nuit']) ?>/nuit · <?= $c['capacite'] ?> pers.)
            </option>
            <?php endforeach; ?>
          </select>
          <div id="availabilityAlert" class="mt-1" style="display:none;"></div>
        </div>

        <!-- Aperçu tarif -->
        <div class="col-12">
          <div class="card bg-light border-0" id="tarifPreview" style="display:none;">
            <div class="card-body py-3">
              <div class="row text-center">
                <div class="col">
                  <div class="small text-muted">Tarif / nuit</div>
                  <div class="fw-bold" id="tarifNuit">—</div>
                </div>
                <div class="col">
                  <div class="small text-muted">Nombre de nuits</div>
                  <div class="fw-bold" id="nbNuits">—</div>
                </div>
                <div class="col">
                  <div class="small text-muted">HT</div>
                  <div class="fw-bold" id="montantHT">—</div>
                </div>
                <div class="col">
                  <div class="small text-muted">TVA (<?= HOTEL_TVA ?>%)</div>
                  <div class="fw-bold" id="montantTVA">—</div>
                </div>
                <div class="col">
                  <div class="small text-muted">Total TTC</div>
                  <div class="fw-bold text-primary fs-5" id="montantTTC">—</div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Notes -->
        <div class="col-12">
          <label class="form-label">Notes / demandes spéciales</label>
          <textarea class="form-control" name="notes" rows="2"
                    placeholder="Lit bébé, étage élevé, arrivée tardive..."><?= e($_POST['notes'] ?? '') ?></textarea>
        </div>

        <!-- Actions -->
        <div class="col-12 d-flex gap-2 justify-content-end border-top pt-3 mt-1">
          <a href="<?= APP_URL ?>/index.php?page=reservations" class="btn btn-outline-secondary">
            Annuler
          </a>
          <button type="submit" class="btn btn-primary" id="btnSubmit">
            <i class="bi bi-check-circle me-1"></i>Créer la réservation
          </button>
        </div>

      </div>
    </form>

  </div>
</div>

</div>
</div>

<script>
const APP_URL = '<?= APP_URL ?>';
const TVA     = <?= HOTEL_TVA ?>;

function formatMoney(v) {
  return new Intl.NumberFormat('fr-FR').format(Math.round(v)) + ' FCFA';
}

function calcNuits() {
  const a = document.getElementById('date_arrivee').value;
  const d = document.getElementById('date_depart').value;
  if (!a || !d) return 0;
  const diff = (new Date(d) - new Date(a)) / 86400000;
  return diff > 0 ? diff : 0;
}

function updatePreview() {
  const sel    = document.getElementById('chambre_id');
  const opt    = sel.options[sel.selectedIndex];
  const tarif  = parseFloat(opt?.dataset?.tarif || 0);
  const nuits  = calcNuits();
  const nbNuitsEl = document.getElementById('nbNuits');

  document.getElementById('dureeDisplay').textContent =
    nuits > 0 ? nuits + ' nuit' + (nuits > 1 ? 's' : '') : '—';

  if (tarif > 0 && nuits > 0) {
    const ht  = tarif * nuits;
    const tva = ht * TVA / 100;
    const ttc = ht + tva;
    document.getElementById('tarifPreview').style.display = '';
    document.getElementById('tarifNuit').textContent  = formatMoney(tarif);
    document.getElementById('nbNuits').textContent    = nuits;
    document.getElementById('montantHT').textContent  = formatMoney(ht);
    document.getElementById('montantTVA').textContent = formatMoney(tva);
    document.getElementById('montantTTC').textContent = formatMoney(ttc);
  } else {
    document.getElementById('tarifPreview').style.display = 'none';
  }
}

let availTimer;
function checkAvailability() {
  clearTimeout(availTimer);
  const chambreId  = document.getElementById('chambre_id').value;
  const dateArrivee = document.getElementById('date_arrivee').value;
  const dateDepart  = document.getElementById('date_depart').value;
  const alertDiv    = document.getElementById('availabilityAlert');

  if (!chambreId || !dateArrivee || !dateDepart) {
    alertDiv.style.display = 'none';
    return;
  }

  availTimer = setTimeout(() => {
    fetch(`${APP_URL}/index.php?page=reservations&action=checkAvailability&chambre_id=${chambreId}&date_arrivee=${dateArrivee}&date_depart=${dateDepart}`)
      .then(r => r.json())
      .then(data => {
        alertDiv.style.display = '';
        if (data.available) {
          alertDiv.innerHTML = '<div class="alert alert-success py-1 mb-0 small"><i class="bi bi-check-circle me-1"></i>Chambre disponible pour ces dates.</div>';
          document.getElementById('btnSubmit').disabled = false;
        } else {
          alertDiv.innerHTML = '<div class="alert alert-danger py-1 mb-0 small"><i class="bi bi-x-circle me-1"></i>Chambre indisponible pour ces dates.</div>';
          document.getElementById('btnSubmit').disabled = true;
        }
      });
  }, 400);
}

['date_arrivee','date_depart','chambre_id'].forEach(id => {
  document.getElementById(id)?.addEventListener('change', () => {
    updatePreview();
    checkAvailability();
  });
});

// Init
updatePreview();
</script>
