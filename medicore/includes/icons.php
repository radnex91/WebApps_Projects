<?php
// ============================================================
//  MediCore ERP - Icônes et emojis de l'application
//  Modifiez ici pour personnaliser les icônes partout
// ============================================================

// Icônes de la navigation sidebar (Bootstrap Icons — modernes et intuitives)
define('ICON_NAV', [
    'dashboard'    => '<i class="bi bi-speedometer2"></i>',
    'analytics'    => '<i class="bi bi-graph-up-arrow"></i>',
    'patients'     => '<i class="bi bi-people-fill"></i>',
    'accueil'      => '<i class="bi bi-door-open-fill"></i>',
    'appointments' => '<i class="bi bi-calendar-event-fill"></i>',
    'consultations' => '<i class="bi bi-clipboard2-pulse-fill"></i>',
    'urgences'     => '<i class="bi bi-exclamation-triangle-fill"></i>',
    'dossiers'     => '<i class="bi bi-folder-fill"></i>',
    'medecins'     => '<i class="bi bi-person-badge-fill"></i>',
    'lits'         => '<i class="bi bi-house-heart-fill"></i>',
    'pharmacie'    => '<i class="bi bi-capsule"></i>',
    'caisse'       => '<i class="bi bi-receipt"></i>',
    'laboratoire'  => '<i class="bi bi-droplet-half"></i>',
    'facturation'  => '<i class="bi bi-cash-stack"></i>',
    'comptabilite' => '<i class="bi bi-journal-text"></i>',
    'rh'           => '<i class="bi bi-person-vcard-fill"></i>',
    'stocks'       => '<i class="bi bi-box-seam"></i>',
    'rapports'     => '<i class="bi bi-journal-text"></i>',
    'utilisateurs' => '<i class="bi bi-key-fill"></i>',
    'parametres'   => '<i class="bi bi-gear-fill"></i>',
    'roles'        => '<i class="bi bi-shield-check"></i>',
    'observations' => '<i class="bi bi-thermometer-half"></i>',
    'mar'          => '<i class="bi bi-activity"></i>',
    'notes'        => '<i class="bi bi-pencil-square"></i>',
    'chirurgie'    => '<i class="bi bi-scissors"></i>',
    'imagerie'     => '<i class="bi bi-image-fill"></i>',
    'assurances'   => '<i class="bi bi-umbrella-fill"></i>',
    'maternite'    => '<i class="bi bi-gender-female"></i>',
    'deces'        => '<i class="bi bi-x-circle-fill"></i>',
    'portail'      => '<i class="bi bi-globe"></i>',
    'notifications'=> '<i class="bi bi-bell-fill"></i>',
    'consentements'=> '<i class="bi bi-file-earmark-check-fill"></i>',
    'audit'        => '<i class="bi bi-shield-lock-fill"></i>',
]);

// Icônes des actions (boutons)
define('ICON_BTN', [
    'add'      => '+',
    'edit'     => '✏️',
    'delete'   => '🗑',
    'save'     => '💾',
    'print'    => '🖨',
    'export'   => '⬇',
    'import'   => '⬆',
    'close'    => '✕',
    'back'     => '←',
    'search'   => '🔍',
    'filter'   => '🔽',
    'refresh'  => '🔄',
    'view'     => '👁',
    'send'     => '📤',
    'dossier'  => '📋',
    'caisse'   => '🧾',
    'urgence'  => '🚨',
    'admit'    => '🏥',
    'ticket'   => '🧾',
    'password' => '🔑',
    'toggle'   => '⏸',
    'confirm'  => '✓',
    'ok'       => '✅',
    'cancel'   => '🚫',
    'warn'     => '⚠️',
    'star'     => '⭐',
    'check'    => '✅',
    'cross'    => '✗',
]);

// Icônes des statuts
define('ICON_STATUT', [
    'actif'       => '✅',
    'inactif'     => '🚫',
    'conge'       => '🏖️',
    'libre'       => '🟢',
    'occupe'      => '🔴',
    'nettoyage'   => '🟡',
    'hors_service'=> '⚫',
    'normal'      => '🟢',
    'bas'         => '🟡',
    'critique'    => '🔴',
    'planifie'    => '📅',
    'confirme'    => '✅',
    'complete'    => '✔️',
    'annule'      => '✕',
    'absent'      => '❌',
    'en_attente'  => '⏳',
    'reglee'      => '✅',
    'impayee'     => '❌',
    'disponible'  => '✅',
    'en_cours'    => '🔬',
    'urgent'      => '🚨',
    'active'      => '💊',
]);

// Icônes des priorités (hospitalisations)
define('ICON_PRIORITE', [
    'critique' => '🔴',
    'urgent'   => '🟠',
    'normal'   => '🟢',
]);

// Icônes des alertes (journal activité)
define('ICON_ALERT', [
    'green'  => '✅',
    'blue'   => '📋',
    'yellow' => '⚠️',
    'red'    => '🚨',
    'purple' => '📌',
]);

// Helper pour récupérer une icône avec fallback
function icon(string $type, string $key, string $fallback = '•'): string {
    $maps = [
        'nav'     => ICON_NAV,
        'btn'     => ICON_BTN,
        'statut'  => ICON_STATUT,
        'priorite'=> ICON_PRIORITE,
        'alert'   => ICON_ALERT,
    ];
    return $maps[$type][$key] ?? $fallback;
}
