<!-- Modal Réservation -->
<div class="modal fade" id="reservationModal" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="reservationModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="reservationModalLabel">
                    <i class="bi bi-calendar-plus"></i> <span>Nouvelle réservation</span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
            </div>
            <form id="reservationForm" novalidate>
                <input type="hidden" name="_mode" id="reservationMode" value="create">
                <input type="hidden" name="_id" id="reservationId" value="">

                <div class="modal-body">
                    <div id="modalErrors" class="alert alert-danger d-none"></div>

                    <!-- Client -->
                    <div id="clientSection" class="mb-3">
                        <label class="form-label" for="modalClient">Client *</label>
                        <select name="client_id" id="modalClient" class="form-select" required>
                            <option value="">— Sélectionner —</option>
                        </select>
                    </div>

                    <!-- Résumé client (edit only) -->
                    <div id="clientSummary" class="mb-3 d-none">
                        <label class="form-label">Client</label>
                        <p class="form-control-plaintext fw-bold" id="clientSummaryText"></p>
                    </div>

                    <!-- Provenance -->
                    <div id="provenanceSection" class="mb-3">
                        <label class="form-label" for="modalProvenance">Provenance</label>
                        <input type="text" name="provenance" id="modalProvenance" class="form-control" placeholder="Ville, pays ou source">
                    </div>

                    <!-- Dates & Nuitées -->
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label class="form-label" for="modalCheckin">Arrivée *</label>
                            <input type="date" name="date_checkin" id="modalCheckin" class="form-control" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="modalNights">Nuitées *</label>
                            <input type="number" name="nights" id="modalNights" class="form-control" value="1" min="1" required>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label" for="modalCheckout">Départ *</label>
                            <input type="date" name="date_checkout" id="modalCheckout" class="form-control" required readonly>
                        </div>
                    </div>

                    <!-- Chambre -->
                    <div id="roomSection" class="mb-3">
                        <label class="form-label" for="modalRoom">Chambre *</label>
                        <select name="room_ids[]" id="modalRoom" class="form-select" required>
                            <option value="">— Sélectionner une chambre —</option>
                        </select>
                    </div>

                    <!-- Statut (edit only) -->
                    <div id="statusSection" class="mb-3 d-none">
                        <label class="form-label" for="modalStatut">Statut</label>
                        <select name="statut" id="modalStatut" class="form-select">
                            <option value="confirmee">Confirmée</option>
                            <option value="en_cours">En cours</option>
                            <option value="terminee">Terminée</option>
                            <option value="annulee">Annulée</option>
                        </select>
                    </div>

                    <!-- Résumé existant (edit only) -->
                    <div id="reservationSummary" class="d-none">
                        <hr>
                        <h6>Chambres réservées</h6>
                        <ul id="existingRoomsList" class="list-unstyled mb-2"></ul>
                        <h6>Services réservés</h6>
                        <ul id="existingServicesList" class="list-unstyled mb-0"></ul>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary" id="modalSubmitBtn">
                        <i class="bi bi-check-lg"></i> Confirmer
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
(function () {
    var modal = document.getElementById('reservationModal');
    var form = document.getElementById('reservationForm');
    var bsModal = null;

    function getModal() {
        if (!bsModal && modal) bsModal = new bootstrap.Modal(modal);
        return bsModal;
    }

    // --- Date auto-calc ---
    var checkinEl = document.getElementById('modalCheckin');
    var nightsEl = document.getElementById('modalNights');
    var checkoutEl = document.getElementById('modalCheckout');

    function calcCheckout() {
        if (checkinEl.value && nightsEl.value) {
            var d = new Date(checkinEl.value + 'T12:00:00');
            d.setDate(d.getDate() + parseInt(nightsEl.value));
            checkoutEl.value = d.toISOString().split('T')[0];
        }
    }

    checkinEl.addEventListener('change', function () {
        calcCheckout();
        loadAvailableRooms();
    });
    nightsEl.addEventListener('change', calcCheckout);

    // --- Load available rooms ---
    async function loadAvailableRooms() {
        var roomSelect = document.getElementById('modalRoom');
        if (!roomSelect || roomSelect.closest('.d-none')) return;

        var checkin = checkinEl.value;
        var checkout = checkoutEl.value;
        if (!checkin || !checkout) return;

        try {
            var params = new URLSearchParams({ checkin: checkin, checkout: checkout });
            var res = await fetch('<?= url("hotel/reservations/availablerooms") ?>?' + params, {
                headers: { 'Accept': 'application/json' }
            });
            var data = await res.json();
            roomSelect.innerHTML = '<option value="">— Sélectionner une chambre —</option>';
            if (data.rooms && data.rooms.length) {
                data.rooms.forEach(function (r) {
                    var opt = document.createElement('option');
                    opt.value = r.id;
                    opt.textContent = 'Ch. ' + escapeHtml(r.numero) + ' — ' + escapeHtml(r.type_nom) + ' (' + formatMoney(r.prix_base) + '/nuit)';
                    roomSelect.appendChild(opt);
                });
            } else {
                roomSelect.innerHTML = '<option value="">Aucune chambre disponible sur ces dates</option>';
            }
        } catch (e) {
            // silent fail — rooms will be loaded on modal open
        }
    }

    // --- Open modal in create mode ---
    window.openCreateModal = async function () {
        document.getElementById('reservationMode').value = 'create';
        document.getElementById('reservationId').value = '';
        document.getElementById('reservationModalLabel').querySelector('span').textContent = 'Nouvelle réservation';
        document.getElementById('reservationModalLabel').querySelector('i').className = 'bi bi-calendar-plus';
        document.getElementById('clientSection').classList.remove('d-none');
        document.getElementById('clientSummary').classList.add('d-none');
        document.getElementById('provenanceSection').classList.remove('d-none');
        document.getElementById('roomSection').classList.remove('d-none');
        document.getElementById('statusSection').classList.add('d-none');
        document.getElementById('reservationSummary').classList.add('d-none');
        document.getElementById('modalErrors').classList.add('d-none');
        form.reset();
        checkinEl.value = '';
        checkoutEl.value = '';
        nightsEl.value = '1';
        document.getElementById('modalProvenance').value = '';
        document.getElementById('modalSubmitBtn').innerHTML = '<i class="bi bi-check-lg"></i> Confirmer la réservation';

        try {
            var res = await fetch('<?= url("hotel/reservations/create") ?>', { headers: { 'Accept': 'application/json' } });
            var data = await res.json();
            populateClients(data.clients);
            // Initial room load if dates already set (unlikely but safe)
            if (checkinEl.value && checkoutEl.value) loadAvailableRooms();
        } catch (e) {
            var errDiv = document.getElementById('modalErrors');
            errDiv.innerHTML = '<i class="bi bi-exclamation-triangle-fill me-2"></i>Erreur lors du chargement des données.';
            errDiv.classList.remove('d-none');
        }

        getModal()?.show();
        setTimeout(function () { document.getElementById('modalClient')?.focus(); }, 200);
    };

    // --- Open modal in edit mode ---
    window.openEditModal = async function (id) {
        document.getElementById('reservationMode').value = 'edit';
        document.getElementById('reservationId').value = id;
        document.getElementById('reservationModalLabel').querySelector('span').textContent = 'Modifier réservation #' + id;
        document.getElementById('reservationModalLabel').querySelector('i').className = 'bi bi-pencil';
        document.getElementById('clientSection').classList.add('d-none');
        document.getElementById('clientSummary').classList.remove('d-none');
        document.getElementById('provenanceSection').classList.add('d-none');
        document.getElementById('roomSection').classList.add('d-none');
        document.getElementById('statusSection').classList.remove('d-none');
        document.getElementById('reservationSummary').classList.remove('d-none');
        document.getElementById('modalErrors').classList.add('d-none');
        form.reset();

        try {
            var res = await fetch('<?= url("hotel/reservations/formdata/") ?>' + id, { headers: { 'Accept': 'application/json' } });
            var data = await res.json();
            if (data.error) {
                var errDiv = document.getElementById('modalErrors');
                errDiv.innerHTML = '<i class="bi bi-exclamation-triangle-fill me-2"></i>' + escapeHtml(data.error);
                errDiv.classList.remove('d-none');
                return;
            }

            document.getElementById('clientSummaryText').textContent =
                (data.reservation.client_prenom || '') + ' ' + (data.reservation.client_nom || '') +
                ' — ' + (data.reservation.client_tel || '');
            checkinEl.value = data.reservation.date_checkin;
            checkoutEl.value = data.reservation.date_checkout;
            document.getElementById('modalStatut').value = data.reservation.statut;
            document.getElementById('modalSubmitBtn').innerHTML = '<i class="bi bi-check-lg"></i> Mettre à jour';

            var roomsHtml = '';
            if (!data.rooms.length) {
                roomsHtml = '<li class="text-muted">Aucune chambre</li>';
            } else {
                data.rooms.forEach(function (r) {
                    roomsHtml += '<li><i class="bi bi-door-open me-1"></i> Ch. ' +
                        escapeHtml(r.numero) + ' — ' + escapeHtml(r.type_nom) +
                        ' (' + formatMoney(r.prix_par_nuit) + '/nuit)</li>';
                });
            }
            document.getElementById('existingRoomsList').innerHTML = roomsHtml;

            var svcHtml = '';
            if (!data.services.length) {
                svcHtml = '<li class="text-muted">Aucun service</li>';
            } else {
                data.services.forEach(function (s) {
                    svcHtml += '<li><i class="bi bi-cup-hot me-1"></i> ' +
                        escapeHtml(s.nom) + ' ×' + s.quantite +
                        ' (' + formatMoney(s.prix_unitaire * s.quantite) + ')</li>';
                });
            }
            document.getElementById('existingServicesList').innerHTML = svcHtml;
        } catch (e) {
            var errDiv2 = document.getElementById('modalErrors');
            errDiv2.innerHTML = '<i class="bi bi-exclamation-triangle-fill me-2"></i>Erreur lors du chargement.';
            errDiv2.classList.remove('d-none');
        }

        getModal()?.show();
        setTimeout(function () { document.getElementById('modalCheckin')?.focus(); }, 200);
    };

    // --- Populate helpers ---
    function populateClients(clients) {
        var sel = document.getElementById('modalClient');
        sel.innerHTML = '<option value="">— Sélectionner —</option>';
        clients.forEach(function (c) {
            var opt = document.createElement('option');
            opt.value = c.id;
            opt.textContent = c.prenom + ' ' + c.nom + ' — ' + (c.telephone || '');
            sel.appendChild(opt);
        });
    }

    // --- Submit handler ---
    form.addEventListener('submit', async function (e) {
        e.preventDefault();
        var errorsDiv = document.getElementById('modalErrors');
        errorsDiv.classList.add('d-none');

        var mode = document.getElementById('reservationMode').value;

        if (mode === 'create') {
            // Validate room selected
            var roomVal = document.getElementById('modalRoom').value;
            if (!roomVal) {
                errorsDiv.innerHTML = '<i class="bi bi-exclamation-triangle-fill me-2"></i>Veuillez sélectionner une chambre.';
                errorsDiv.classList.remove('d-none');
                return;
            }
            // Ensure checkout is calculated
            if (!checkoutEl.value) calcCheckout();
            if (!checkoutEl.value || !checkinEl.value) {
                errorsDiv.innerHTML = '<i class="bi bi-exclamation-triangle-fill me-2"></i>Veuillez renseigner la date d\'arrivée et les nuitées.';
                errorsDiv.classList.remove('d-none');
                return;
            }
        }

        var id = document.getElementById('reservationId').value;
        var url = mode === 'create'
            ? '<?= url("hotel/reservations/store") ?>'
            : '<?= url("hotel/reservations/update/") ?>' + id;

        var formData = new FormData(form);
        if (mode === 'edit') {
            formData.set('statut', document.getElementById('modalStatut').value);
            formData.set('date_checkin', checkinEl.value);
            formData.set('date_checkout', checkoutEl.value);
        }
        // Build notes from provenance in create mode
        if (mode === 'create') {
            var provenance = document.getElementById('modalProvenance').value.trim();
            if (provenance) {
                formData.set('notes', 'Provenance: ' + provenance);
            }
        }

        var submitBtn = document.getElementById('modalSubmitBtn');
        var origHtml = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Enregistrement…';

        try {
            var res = await fetch(url, {
                method: 'POST',
                headers: { 'Accept': 'application/json' },
                body: formData
            });
            var data = await res.json();

            if (data.success) {
                getModal()?.hide();
                window.location.reload();
            } else if (data.errors) {
                errorsDiv.innerHTML = '<i class="bi bi-exclamation-triangle-fill me-2"></i>' +
                    Object.values(data.errors).map(function (e) { return escapeHtml(e); }).join('<br>');
                errorsDiv.classList.remove('d-none');
            } else {
                errorsDiv.innerHTML = '<i class="bi bi-exclamation-triangle-fill me-2"></i>' + escapeHtml(data.message || 'Une erreur est survenue.');
                errorsDiv.classList.remove('d-none');
            }
        } catch (err) {
            errorsDiv.innerHTML = '<i class="bi bi-exclamation-triangle-fill me-2"></i>Erreur réseau. Veuillez réessayer.';
            errorsDiv.classList.remove('d-none');
        } finally {
            submitBtn.disabled = false;
            submitBtn.innerHTML = origHtml;
        }
    });

    // --- Global helpers ---
    window.escapeHtml = function (str) {
        var div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    };
    window.formatMoney = function (amount) {
        return new Intl.NumberFormat('fr-FR', { style: 'decimal', minimumFractionDigits: 0, maximumFractionDigits: 0 }).format(amount) + ' FCFA';
    };
})();
</script>
