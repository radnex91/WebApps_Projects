<?php
namespace App\Services;

use App\Models\Setting;
use App\Core\Permission;
use App\Core\Auth;

class SettingsService
{
    private Setting $settingModel;

    public function __construct(Setting $settingModel)
    {
        $this->settingModel = $settingModel;
    }

    /**
     * Get all settings (filtered by sensitivity for non-admins)
     */
    public function getAll(): array
    {
        $isAdmin = Auth::check() && Permission::canManageSensitiveSettings(Auth::get('role_name'));
        $settings = $this->settingModel->getAllKeyed();

        if (!$isAdmin) {
            // Remove sensitive settings values for non-admins
            $sensitiveKeys = $this->settingModel->getRepo()->query(
                "SELECT setting_key FROM app_settings WHERE is_sensitive = 1"
            );
            foreach ($sensitiveKeys as $row) {
                if (isset($settings[$row['setting_key']])) {
                    $settings[$row['setting_key']] = '********';
                }
            }
        }

        return $settings;
    }

    /**
     * Get a setting value by key
     */
    public function get(string $key, $default = null): ?string
    {
        $value = $this->settingModel->getByKey($key);
        return $value ?? $default;
    }

    /**
     * Update multiple settings
     */
    public function updateMany(array $settings): void
    {
        $isAdmin = Auth::check() && Permission::canManageSensitiveSettings(Auth::get('role_name'));

        foreach ($settings as $key => $value) {
            // Check if this is a sensitive setting
            $row = $this->settingModel->getRepo()->findBy('app_settings', 'setting_key', $key);
            if ($row && $row['is_sensitive'] && !$isAdmin) {
                continue; // Skip sensitive settings for non-admins
            }
            $this->settingModel->setByKey($key, $value);
        }
    }
}