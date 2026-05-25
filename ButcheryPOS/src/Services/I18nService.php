<?php
namespace App\Services;

class I18nService
{
    private string $langDir;

    public function __construct(string $langDir)
    {
        $this->langDir = $langDir;
    }

    /**
     * Get available languages
     */
    public function getAvailableLanguages(): array
    {
        return [
            ['code' => 'fr', 'name' => 'Francais'],
            ['code' => 'en', 'name' => 'English'],
        ];
    }

    /**
     * Load translations for a language
     */
    public function loadTranslations(string $lang): array
    {
        $file = $this->langDir . $lang . '.php';
        if (is_file($file)) {
            return require $file;
        }
        return [];
    }

    /**
     * Get current language from session
     */
    public function getCurrentLanguage(): string
    {
        return $_SESSION['lang'] ?? 'fr';
    }

    /**
     * Set language in session and optionally update user preference
     */
    public function setLanguage(string $lang): void
    {
        $_SESSION['lang'] = $lang;
        if (isset($_SESSION['user'])) {
            $_SESSION['user']['preferred_language'] = $lang;
        }
    }
}