<form method="POST" action="<?= BASE_URL ?>/auth/login" class="auth-form">
    <?= $csrf->field() ?>
    <div class="form-group">
        <label for="email">Email</label>
        <input type="email" name="email" id="email" class="form-control" placeholder="admin@nexride.com" required autofocus>
    </div>
    <div class="form-group">
        <label for="password">Mot de passe</label>
        <input type="password" name="password" id="password" class="form-control" placeholder="Mot de passe" required>
    </div>
    <button type="submit" class="btn btn-primary btn-block">Se connecter</button>
</form>
<div class="auth-footer">
    <p>Comptes de démo: admin@nexride.com / caissier@nexride.com / comptable@nexride.com</p>
    <p><small>Mot de passe: <strong>password123</strong></small></p>
</div>