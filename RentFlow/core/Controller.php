<?php
class Controller {
    protected function view($view, $data = []) {
        extract($data);
        $viewFile = __DIR__ . '/../app/views/' . $view . '.php';
        if (file_exists($viewFile)) {
            require $viewFile;
        } else {
            die("Vue $view introuvable");
        }
    }

    protected function redirect($url) {
        header('Location: ' . BASE_URL . '/' . $url);
        exit;
    }

    protected function sanitize($input) {
        if (is_array($input)) {
            return array_map([$this, 'sanitize'], $input);
        }
        return htmlspecialchars(trim($input ?? ''), ENT_QUOTES, 'UTF-8');
    }

    protected function validate($data, $rules) {
        $errors = [];
        foreach ($rules as $field => $rule) {
            if ($rule === 'required' && empty($data[$field])) {
                $errors[$field] = "Le champ $field est requis";
            }
        }
        return $errors;
    }

    protected function requireAuth() {
        Auth::requireAuth();
    }

    protected function validateCsrf() {
        $token = $_POST['csrf_token'] ?? '';
        if (!Csrf::validateToken($token)) {
            http_response_code(403);
            die("Erreur de sécurité : jeton CSRF invalide. <a href='javascript:history.back()'>Retour</a>");
        }
    }

    protected function e($string) {
        return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
    }
}
