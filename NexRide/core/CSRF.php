<?php
namespace Core;

class CSRF
{
    public function generate(): string
    {
        $token = bin2hex(random_bytes(32));
        $_SESSION['_csrf_token'] = $token;
        return $token;
    }

    public function getToken(): string
    {
        if (empty($_SESSION['_csrf_token'])) {
            return $this->generate();
        }
        return $_SESSION['_csrf_token'];
    }

    public function validate(?string $token): bool
    {
        if (empty($_SESSION['_csrf_token']) || empty($token)) return false;
        return hash_equals($_SESSION['_csrf_token'], $token);
    }

    public function field(): string
    {
        return '<input type="hidden" name="_csrf_token" value="' . $this->getToken() . '">';
    }
}