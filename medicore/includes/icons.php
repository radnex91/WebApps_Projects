<?php
// ============================================================
//  MediCore ERP - Icônes et emojis de l'application
//  Modifiez ici pour personnaliser les icônes partout
// ============================================================

// Icônes de la navigation sidebar
define('ICON_NAV', [
    'dashboard'    => '📊',
    'analytics'    => '📈',
    'patients'     => '👥',
    'appointments' => '📅',
    'urgences'     => '🚨',
    'dossiers'     => '📋',
    'medecins'     => '👨‍⚕️',
    'lits'         => '🛏️',
    'pharmacie'    => '💊',
    'caisse'       => '🧾',
    'laboratoire'  => '🧪',
    'facturation'  => '💰',
    'rh'           => '👤',
    'stocks'       => '📦',
    'rapports'     => '📄',
    'utilisateurs' => '🔑',
    'parametres'   => '⚙️',
    'roles'        => '🛡️',
    'observations' => '🌡️',
    'mar'          => '💉',
    'notes'        => '📝',
    'chirurgie'    => '🔪',
    'imagerie'     => '🩻',
    'assurances'   => '📑',
    'maternite'    => '🤰',
    'deces'        => '🕊️',
    'portail'      => '🌐',
    'notifications'=> '🔔',
    'consentements'=> '📜',
    'audit'        => '🔍',
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
