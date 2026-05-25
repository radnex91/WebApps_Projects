<?php
// ButcheryPOS - Login Page
use App\Core\Csrf;
?>
<!DOCTYPE html>
<html lang="<?= e($lang) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($appSettings['company_name'] ?? 'ButcheryPOS') ?> - <?= t('login') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= asset_url('css/app.css') ?>" rel="stylesheet">
</head>
<body>
<div class="login-wrapper">
    <div class="login-card">
        <div class="logo">
            <h1><i class="bi bi-shop"></i> <?= e($appSettings['company_name'] ?? 'ButcheryPOS') ?></h1>
            <p><?= e($appSettings['company_tagline'] ?? '') ?></p>
        </div>

        <?php if (!empty($loginError)): ?>
        <div class="alert alert-danger py-2">
            <i class="bi bi-exclamation-triangle"></i> <?= e($loginError) ?>
        </div>
        <?php endif; ?>

        <form method="POST" action="<?= url('?action=login') ?>">
            <input type="hidden" name="_csrf" value="<?= e(Csrf::getToken()) ?>">

            <div class="mb-3">
                <label class="form-label"><?= t('username') ?></label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-person"></i></span>
                    <input type="text" name="username" class="form-control" required autofocus
                           placeholder="<?= t('username') ?>">
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label"><?= t('password') ?></label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-lock"></i></span>
                    <input type="password" name="password" class="form-control" required
                           placeholder="<?= t('password') ?>">
                </div>
            </div>

            <button type="submit" class="btn btn-danger w-100 py-2 mt-2">
                <i class="bi bi-box-arrow-in-right"></i> <?= t('login') ?>
            </button>
        </form>

        <div class="text-center mt-3">
            <form method="POST" action="<?= url('?action=set_language') ?>" class="d-inline">
                <input type="hidden" name="_csrf" value="<?= e(Csrf::getToken()) ?>">
                <input type="hidden" name="redirect" value="<?= e($_SERVER['REQUEST_URI'] ?? '') ?>">
                <button type="submit" name="lang" value="fr"
                    class="btn btn-sm btn-outline-secondary <?= $lang === 'fr' ? 'active' : '' ?>">FR</button>
                <button type="submit" name="lang" value="en"
                    class="btn btn-sm btn-outline-secondary <?= $lang === 'en' ? 'active' : '' ?>">EN</button>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>