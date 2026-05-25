<?php
/**
 * Fonctions utilitaires
 */

function e($string)
{
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

function formatMoney($amount)
{
    return number_format((float)$amount, 2, ',', ' ') . ' MAD';
}

function formatDate($date)
{
    if (!$date) return '';
    $months = [
        1 => 'Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin',
        'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'
    ];
    $d = new DateTime($date);
    return $d->format('d') . ' ' . $months[(int)$d->format('n')] . ' ' . $d->format('Y');
}

function formatDateTime($datetime)
{
    if (!$datetime) return '';
    $d = new DateTime($datetime);
    $months = [
        1 => 'jan', 'fév', 'mar', 'avr', 'mai', 'jun',
        'jul', 'aoû', 'sep', 'oct', 'nov', 'déc'
    ];
    return $d->format('d') . ' ' . $months[(int)$d->format('n')] . ' ' . $d->format('Y H:i');
}

function timeAgo($datetime)
{
    $now = new DateTime();
    $past = new DateTime($datetime);
    $diff = $now->diff($past);

    if ($diff->y > 0) return $diff->y . ' an' . ($diff->y > 1 ? 's' : '');
    if ($diff->m > 0) return $diff->m . ' mois';
    if ($diff->d > 0) return $diff->d . ' jour' . ($diff->d > 1 ? 's' : '');
    if ($diff->h > 0) return $diff->h . ' heure' . ($diff->h > 1 ? 's' : '');
    if ($diff->i > 0) return $diff->i . ' min';
    return 'À l\'instant';
}

function getStatusBadge($status)
{
    $colors = [
        'actif' => 'success', 'inactif' => 'secondary', 'en_congé' => 'info', 'résilié' => 'danger',
        'en_attente' => 'warning', 'approuvé' => 'success', 'refusé' => 'danger', 'annulé' => 'secondary',
        'présent' => 'success', 'absent' => 'danger', 'retard' => 'warning', 'mi-journée' => 'info',
        'brouillon' => 'secondary', 'en_cours' => 'info', 'clôturé' => 'success', 'payé' => 'success', 'validé' => 'success',
        'nouveau' => 'info', 'entretien' => 'warning', 'test' => 'primary', 'offre' => 'success', 'embauché' => 'success',
        'planifié' => 'info', 'terminé' => 'success', 'publié' => 'success',
        'auto_évaluation' => 'warning', 'évaluation_manager' => 'info', 'complété' => 'success',
        'inscrit' => 'info', 'CDI' => 'success', 'CDD' => 'warning', 'Stage' => 'info', 'Freelance' => 'primary', 'Intérim' => 'secondary',
        'congé' => 'info',
    ];
    $color = $colors[$status] ?? 'secondary';
    $labels = [
        'actif' => 'Actif', 'inactif' => 'Inactif', 'en_congé' => 'En congé', 'résilié' => 'Résilié',
        'en_attente' => 'En attente', 'approuvé' => 'Approuvé', 'refusé' => 'Refusé', 'annulé' => 'Annulé',
        'présent' => 'Présent', 'absent' => 'Absent', 'retard' => 'Retard', 'mi-journée' => 'Mi-journée',
        'brouillon' => 'Brouillon', 'en_cours' => 'En cours', 'clôturé' => 'Clôturé', 'payé' => 'Payé', 'validé' => 'Validé',
        'nouveau' => 'Nouveau', 'entretien' => 'Entretien', 'test' => 'Test', 'offre' => 'Offre', 'embauché' => 'Embauché',
        'planifié' => 'Planifié', 'terminé' => 'Terminé', 'publié' => 'Publié',
    ];
    $label = $labels[$status] ?? ucfirst($status);
    return "<span class=\"badge badge-{$color}\">{$label}</span>";
}

function calculateBusinessDays($startDate, $endDate)
{
    $start = new DateTime($startDate);
    $end = new DateTime($endDate);
    $days = 0;
    $current = clone $start;

    while ($current <= $end) {
        if ($current->format('N') <= 5) $days++;
        $current->modify('+1 day');
    }
    return $days;
}

function uploadFile($file, $directory, $allowedTypes = [])
{
    if (!isset($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) {
        return ['error' => 'Erreur lors de l\'upload du fichier.'];
    }

    if ($file['size'] > MAX_UPLOAD_SIZE) {
        return ['error' => 'Le fichier est trop volumineux.'];
    }

    if (!empty($allowedTypes) && !in_array($file['type'], $allowedTypes)) {
        return ['error' => 'Type de fichier non autorisé.'];
    }

    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid() . '_' . time() . '.' . $ext;
    $uploadDir = UPLOADS_PATH . '/' . $directory;

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $destination = $uploadDir . '/' . $filename;
    if (move_uploaded_file($file['tmp_name'], $destination)) {
        return ['success' => $directory . '/' . $filename, 'filename' => $file['name'], 'size' => $file['size']];
    }

    return ['error' => 'Impossible de sauvegarder le fichier.'];
}

function generatePassword($length = 8)
{
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%';
    return substr(str_shuffle($chars), 0, $length);
}

function getMonthName($month)
{
    $months = [
        1 => 'Janvier', 2 => 'Février', 3 => 'Mars', 4 => 'Avril',
        5 => 'Mai', 6 => 'Juin', 7 => 'Juillet', 8 => 'Août',
        9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre'
    ];
    return $months[(int)$month] ?? '';
}