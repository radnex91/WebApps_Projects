<?php
/**
 * Messages flash
 */
class Flash
{
    public static function set($type, $message)
    {
        Session::setFlash($type, $message);
    }

    public static function success($message)
    {
        self::set('success', $message);
    }

    public static function error($message)
    {
        self::set('error', $message);
    }

    public static function warning($message)
    {
        self::set('warning', $message);
    }

    public static function info($message)
    {
        self::set('info', $message);
    }

    public static function get($type = null)
    {
        return Session::getFlash($type);
    }

    public static function has($type = null)
    {
        return Session::hasFlash($type);
    }

    public static function render()
    {
        $messages = Session::getFlash();
        if (empty($messages)) return '';

        $html = '';
        foreach ($messages as $type => $message) {
            $icon = match($type) {
                'success' => 'fas fa-check-circle',
                'error' => 'fas fa-exclamation-circle',
                'warning' => 'fas fa-exclamation-triangle',
                'info' => 'fas fa-info-circle',
                default => 'fas fa-info-circle'
            };
            $html .= "<div class=\"alert alert-{$type} alert-dismissible fade show\" role=\"alert\">
                <i class=\"icon {$icon} mr-2\"></i>{$message}
                <button type=\"button\" class=\"close\" data-dismiss=\"alert\" aria-label=\"Fermer\">
                    <span aria-hidden=\"true\">&times;</span>
                </button>
            </div>";
        }
        return $html;
    }
}