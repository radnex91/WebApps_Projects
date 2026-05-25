<?php
namespace Core;

class Helpers
{
    public static function generateRef(string $prefix): string
    {
        return $prefix . '-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
    }

    public static function formatMoney(float $amount): string
    {
        return number_format($amount, 0, ',', ' ') . ' ' . DEVISE;
    }

    public static function formatDate(?string $date): string
    {
        if (!$date) return '-';
        return date(DATE_FORMAT, strtotime($date));
    }

    public static function formatDateTime(?string $datetime): string
    {
        if (!$datetime) return '-';
        return date(DATETIME_FORMAT, strtotime($datetime));
    }

    public static function sanitize(string $input): string
    {
        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }

    public static function slugify(string $text): string
    {
        $text = preg_replace('~[^\pL\d]+~u', '-', $text);
        $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
        $text = preg_replace('~[^-\w]+~', '', $text);
        $text = trim($text, '-');
        return preg_replace('~-+~', '-', strtolower($text));
    }

    public static function truncate(string $text, int $length = 100): string
    {
        return strlen($text) > $length ? substr($text, 0, $length) . '...' : $text;
    }

    public static function statusBadge(string $status): string
    {
        $colors = [
            'PROGRAMME' => 'blue', 'EN_COURS' => 'yellow', 'TERMINE' => 'green', 'ANNULE' => 'red',
            'CONFIRME' => 'green', 'EN_ATTENTE' => 'yellow', 'REMBOURSE' => 'orange',
            'BROUILLON' => 'gray', 'VALIDE' => 'green', 'CLOTURE' => 'blue',
            'ESPECES' => 'green', 'CARTE' => 'blue', 'MOBILE_MONEY' => 'purple', 'VIREMENT' => 'cyan',
        ];
        $color = $colors[$status] ?? 'gray';
        $labels = [
            'PROGRAMME' => 'Programmé', 'EN_COURS' => 'En cours', 'TERMINE' => 'Terminé', 'ANNULE' => 'Annulé',
            'CONFIRME' => 'Confirmé', 'EN_ATTENTE' => 'En attente', 'REMBOURSE' => 'Remboursé',
            'BROUILLON' => 'Brouillon', 'VALIDE' => 'Validé', 'CLOTURE' => 'Clôturé',
            'ESPECES' => 'Espèces', 'CARTE' => 'Carte', 'MOBILE_MONEY' => 'Mobile Money', 'VIREMENT' => 'Virement',
        ];
        $label = $labels[$status] ?? $status;
        return "<span class=\"badge badge-{$color}\">{$label}</span>";
    }

    public static function isOnline(): bool
    {
        $connected = @fsockopen('www.google.com', 80);
        if ($connected) { fclose($connected); return true; }
        return false;
    }
}