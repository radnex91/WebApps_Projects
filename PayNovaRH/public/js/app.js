// PayNovaRH - Application JavaScript

$(document).ready(function() {
    // Initialize tooltips
    $('[data-toggle="tooltip"]').tooltip();

    // Delete confirmation
    $(document).on('click', '.btn-delete', function(e) {
        e.preventDefault();
        var form = $(this).closest('form');
        var itemName = $(this).data('name') || 'cet élément';

        Swal.fire({
            title: 'Êtes-vous sûr ?',
            text: 'Vous allez supprimer ' + itemName + '. Cette action est irréversible.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Oui, supprimer',
            cancelButtonText: 'Annuler'
        }).then((result) => {
            if (result.isConfirmed && form.length) {
                form.submit();
            }
        });
    });

    // Select2 initialization for dynamic selects
    if ($.fn.select2) {
        $('.select2').select2({
            theme: 'bootstrap4',
            language: {
                noResults: function() { return 'Aucun résultat trouvé'; },
                searching: function() { return 'Recherche...'; }
            }
        });
    }

    // Auto-calculate leave days
    $('#start_date, #end_date').on('change', function() {
        var start = $('#start_date').val();
        var end = $('#end_date').val();
        if (start && end) {
            // Simple calculation (server will do business days)
            var startDate = new Date(start);
            var endDate = new Date(end);
            var days = Math.ceil((endDate - startDate) / (1000 * 60 * 60 * 24)) + 1;
            $('#total_days_display').text(days + ' jour(s)');
            $('#total_days').val(days);
        }
    });

    // Clock in/out buttons
    $('#btn-clock-in').on('click', function() {
        var btn = $(this);
        btn.prop('disabled', true);
        $.post(APP_URL + '/attendance/clock-in', function(data) {
            if (data.success) {
                Toast.fire({ icon: 'success', title: 'Pointage d\'entrée enregistré à ' + data.time });
                btn.removeClass('btn-success').addClass('btn-secondary').text('Pointé ' + data.time);
            }
        }, 'json').fail(function() {
            btn.prop('disabled', false);
            Toast.fire({ icon: 'error', title: 'Erreur lors du pointage' });
        });
    });

    $('#btn-clock-out').on('click', function() {
        var btn = $(this);
        btn.prop('disabled', true);
        $.post(APP_URL + '/attendance/clock-out', function(data) {
            if (data.success) {
                Toast.fire({ icon: 'success', title: 'Pointage de sortie enregistré à ' + data.time });
                btn.removeClass('btn-warning').addClass('btn-secondary').text('Pointé ' + data.time);
            }
        }, 'json').fail(function() {
            btn.prop('disabled', false);
            Toast.fire({ icon: 'error', title: 'Erreur lors du pointage' });
        });
    });

    // Print payslip
    $('#btn-print-payslip').on('click', function() {
        window.print();
    });

    // Leave approve/reject
    $(document).on('click', '.btn-approve-leave', function(e) {
        e.preventDefault();
        var form = $(this).closest('form');
        Swal.fire({
            title: 'Approuver ce congé ?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#28a745',
            confirmButtonText: 'Oui, approuver',
            cancelButtonText: 'Annuler'
        }).then((result) => {
            if (result.isConfirmed) form.submit();
        });
    });

    $(document).on('click', '.btn-reject-leave', function(e) {
        e.preventDefault();
        var form = $(this).closest('form');
        Swal.fire({
            title: 'Motif du refus',
            input: 'textarea',
            inputPlaceholder: 'Entrez le motif du refus...',
            showCancelButton: true,
            confirmButtonText: 'Refuser',
            confirmButtonColor: '#d33',
            cancelButtonText: 'Annuler'
        }).then((result) => {
            if (result.isConfirmed) {
                form.find('input[name="rejection_reason"]').val(result.value);
                form.submit();
            }
        });
    });

    // Payroll calculate
    $(document).on('click', '.btn-calculate-payroll', function(e) {
        e.preventDefault();
        var form = $(this).closest('form');
        Swal.fire({
            title: 'Calculer la paie ?',
            text: 'Les déductions seront calculées selon la législation marocaine.',
            icon: 'info',
            showCancelButton: true,
            confirmButtonText: 'Calculer',
            cancelButtonText: 'Annuler'
        }).then((result) => {
            if (result.isConfirmed) form.submit();
        });
    });

    // Application status update
    $('.application-status-select').on('change', function() {
        var select = $(this);
        var form = select.closest('form');
        var newStatus = select.val();

        if (newStatus === 'embauché') {
            Swal.fire({
                title: 'Confirmer l\'embauche ?',
                text: 'Un profil employé sera créé automatiquement.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Confirmer',
                cancelButtonText: 'Annuler'
            }).then((result) => {
                if (result.isConfirmed) {
                    form.submit();
                } else {
                    select.val(select.data('original'));
                }
            });
        } else {
            form.submit();
        }
    });
});

// APP_URL constant for JS
var APP_URL = '<?php echo APP_URL; ?>';

// Toast helper
const Toast = Swal.mixin({
    toast: true,
    position: 'top-end',
    showConfirmButton: false,
    timer: 3000,
    timerProgressBar: true,
    didOpen: (toast) => {
        toast.addEventListener('mouseenter', Swal.stopTimer);
        toast.addEventListener('mouseleave', Swal.resumeTimer);
    }
});