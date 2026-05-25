<?php $title = 'Connexion'; ?>
<form method="post" action="<?= url('auth/login') ?>" class="login-form" autocomplete="off" novalidate>
    <div class="login-input-group">
        <label for="email" class="form-label">Adresse email</label>
        <div class="input-icon-wrapper">
            <input type="email" id="email" name="email" class="form-control premium-input" value="<?= e(old('email')) ?>" placeholder="votre@email.com" required autofocus autocomplete="username">
        </div>
        <?php if ($err = formError('email')): ?>
            <span class="field-error"><?= e($err) ?></span>
        <?php endif; ?>
    </div>
    <div class="login-input-group">
        <label for="password" class="form-label">Mot de passe</label>
        <div class="input-icon-wrapper">
            <input type="password" id="password" name="password" class="form-control premium-input" placeholder="••••••••" required autocomplete="current-password">
            <button type="button" class="password-toggle" id="passwordToggle" aria-label="Afficher le mot de passe">
                <i class="bi bi-eye"></i>
            </button>
        </div>
        <?php if ($err = formError('password')): ?>
            <span class="field-error"><?= e($err) ?></span>
        <?php endif; ?>
    </div>
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div class="form-check">
            <input class="form-check-input" type="checkbox" id="remember">
            <label class="form-check-label small" for="remember">Se souvenir de moi</label>
        </div>
        <a href="<?= url('auth/forgot-password') ?>" class="login-forgot-link">Mot de passe oublié ?</a>
    </div>
    <button type="submit" class="btn btn-login w-100" id="loginSubmit">
        <span class="btn-login-label">Se connecter</span>
        <i class="bi bi-arrow-right btn-login-icon"></i>
        <span class="btn-login-spinner">
            <span class="spinner-border spinner-border-sm" aria-hidden="true"></span>
        </span>
    </button>
</form>
