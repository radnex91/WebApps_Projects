<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-gear-fill"></i> Paramètres de l'application</h2>
</div>

<div class="row">
    <div class="col-md-8">
        <div class="card shadow-sm">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="bi bi-sliders"></i> Configuration générale</h5>
            </div>
            <div class="card-body">
                <form action="<?= BASE_URL ?>/settings" method="POST" enctype="multipart/form-data">
                    <?= Csrf::field() ?>
                    
                    <!-- Section: Informations -->
                    <div class="mb-4">
                        <h6 class="border-bottom pb-2"><i class="bi bi-info-circle"></i> Informations</h6>

                        <div class="mb-3">
                            <label class="form-label">Nom de l'application</label>
                            <input type="text" name="app_name" class="form-control"
                                   value="<?= htmlspecialchars($settings['app_name'] ?? 'RentFlow - Gestion Immobilière') ?>">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Logo</label>
                            <input type="file" name="logo" class="form-control" accept="image/*">
                            <?php if (!empty($settings['logo'])): ?>
                                <div class="mt-2">
                                    <img src="<?= BASE_URL ?>/<?= htmlspecialchars($settings['logo']) ?>"
                                         alt="Logo" style="max-height: 50px;">
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Section: Régional -->
                    <div class="mb-4">
                        <h6 class="border-bottom pb-2"><i class="bi bi-globe"></i> Paramètres régionaux</h6>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Devise</label>
                                <select name="currency" class="form-select">
                                    <option value="XAF" <?= ($settings['currency'] ?? 'XAF') === 'XAF' ? 'selected' : '' ?>>FCFA (XAF)</option>
                                    <option value="EUR" <?= ($settings['currency'] ?? 'XAF') === 'EUR' ? 'selected' : '' ?>>Euro (EUR)</option>
                                    <option value="USD" <?= ($settings['currency'] ?? 'XAF') === 'USD' ? 'selected' : '' ?>>Dollar (USD)</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Fuseau horaire</label>
                                <select name="timezone" class="form-select">
                                    <option value="Africa/Douala" <?= ($settings['timezone'] ?? 'Africa/Douala') === 'Africa/Douala' ? 'selected' : '' ?>>WAT (Douala)</option>
                                    <option value="Africa/Yaounde" <?= ($settings['timezone'] ?? 'Africa/Douala') === 'Africa/Yaounde' ? 'selected' : '' ?>>WAT (Yaoundé)</option>
                                    <option value="Africa/Abidjan" <?= ($settings['timezone'] ?? 'Africa/Douala') === 'Africa/Abidjan' ? 'selected' : '' ?>>GMT (Abidjan)</option>
                                    <option value="Europe/Paris" <?= ($settings['timezone'] ?? 'Africa/Douala') === 'Europe/Paris' ? 'selected' : '' ?>>CET (Paris)</option>
                                </select>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Langue</label>
                                <select name="language" class="form-select">
                                    <option value="fr" <?= ($settings['language'] ?? 'fr') === 'fr' ? 'selected' : '' ?>>Français</option>
                                    <option value="en" <?= ($settings['language'] ?? 'fr') === 'en' ? 'selected' : '' ?>>English</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Format de date</label>
                                <select name="date_format" class="form-select">
                                    <option value="d/m/Y" <?= ($settings['date_format'] ?? 'd/m/Y') === 'd/m/Y' ? 'selected' : '' ?>>JJ/MM/AAAA</option>
                                    <option value="m/d/Y" <?= ($settings['date_format'] ?? 'd/m/Y') === 'm/d/Y' ? 'selected' : '' ?>>MM/JJ/AAAA</option>
                                    <option value="Y-m-d" <?= ($settings['date_format'] ?? 'd/m/Y') === 'Y-m-d' ? 'selected' : '' ?>>AAAA-MM-JJ</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Section: Notifications -->
                    <div class="mb-4">
                        <h6 class="border-bottom pb-2"><i class="bi bi-bell"></i> Notifications</h6>

                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" name="notifications_enabled"
                                   id="notifications_enabled"
                                   <?= ($settings['notifications_enabled'] ?? '1') === '1' ? 'checked' : '' ?>>
                            <label class="form-check-label" for="notifications_enabled">
                                Activer les notifications
                            </label>
                        </div>

                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" name="email_notifications"
                                   id="email_notifications"
                                   <?= ($settings['email_notifications'] ?? '1') === '1' ? 'checked' : '' ?>>
                            <label class="form-check-label" for="email_notifications">
                                Notifications par email
                            </label>
                        </div>
                    </div>

                    <!-- Section: Affichage -->
                    <div class="mb-4">
                        <h6 class="border-bottom pb-2"><i class="bi bi-display"></i> Affichage</h6>

                        <div class="mb-3">
                            <label class="form-label">Éléments par page</label>
                            <select name="items_per_page" class="form-select">
                                <option value="5" <?= ($settings['items_per_page'] ?? '10') === '5' ? 'selected' : '' ?>>5</option>
                                <option value="10" <?= ($settings['items_per_page'] ?? '10') === '10' ? 'selected' : '' ?>>10</option>
                                <option value="25" <?= ($settings['items_per_page'] ?? '10') === '25' ? 'selected' : '' ?>>25</option>
                                <option value="50" <?= ($settings['items_per_page'] ?? '10') === '50' ? 'selected' : '' ?>>50</option>
                                <option value="100" <?= ($settings['items_per_page'] ?? '10') === '100' ? 'selected' : '' ?>>100</option>
                            </select>
                        </div>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg"></i> Enregistrer
                        </button>
                        <a href="<?= BASE_URL ?>/dashboard" class="btn btn-secondary">
                            <i class="bi bi-x-lg"></i> Annuler
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card shadow-sm mb-3">
            <div class="card-header bg-info text-white">
                <h6 class="mb-0"><i class="bi bi-lightbulb"></i> Le saviez-vous ?</h6>
            </div>
            <div class="card-body">
                <p class="mb-0 small">
                    Les paramètres vous permettent de personnaliser l'application selon vos besoins.
                    Les modifications sont appliquées immédiatement.
                </p>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-header bg-success text-white">
                <h6 class="mb-0"><i class="bi bi-shield-check"></i> Système</h6>
            </div>
            <div class="card-body">
                <div class="d-flex justify-content-between mb-2">
                    <span>Version:</span>
                    <strong>1.0.0</strong>
                </div>
            </div>
        </div>
    </div>
</div>
