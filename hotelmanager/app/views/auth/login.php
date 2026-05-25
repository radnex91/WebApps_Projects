<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Connexion — <?= APP_NAME ?></title>
  <link rel="stylesheet" href="<?= APP_URL ?>/public/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <style>
    @import url('https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800&display=swap');
    body {
      font-family: 'Nunito', sans-serif;
      background: linear-gradient(135deg, #0f172a 0%, #1e3a5f 100%);
      min-height: 100vh;
      display: flex; align-items: center; justify-content: center;
    }
    .login-card {
      background: #fff;
      border-radius: 16px;
      box-shadow: 0 20px 60px rgba(0,0,0,.35);
      width: 100%; max-width: 420px;
      overflow: hidden;
    }
    .login-header {
      background: linear-gradient(135deg, #1a56db, #0f3d9e);
      padding: 32px 32px 28px;
      text-align: center; color: #fff;
    }
    .hotel-logo {
      width: 60px; height: 60px;
      background: rgba(255,255,255,.2);
      border-radius: 14px;
      display: inline-flex; align-items: center; justify-content: center;
      font-size: 30px; font-weight: 800; margin-bottom: 12px;
    }
    .login-header h1 { font-size: 22px; font-weight: 800; margin: 0 0 4px; }
    .login-header p  { font-size: 12px; opacity: .8; margin: 0; }
    .login-body { padding: 28px 32px 32px; }
    .form-label { font-size: 13px; font-weight: 700; color: #374151; }
    .form-control {
      border: 2px solid #e5e7eb; border-radius: 10px;
      padding: 10px 14px; font-size: 14px;
      font-family: 'Nunito', sans-serif;
    }
    .form-control:focus {
      border-color: #1a56db;
      box-shadow: 0 0 0 3px rgba(26,86,219,.15);
    }
    .input-icon { position: relative; }
    .input-icon i {
      position: absolute; left: 12px; top: 50%; transform: translateY(-50%);
      color: #9ca3af; font-size: 16px;
    }
    .input-icon .form-control { padding-left: 38px; }
    .btn-login {
      background: #1a56db; color: #fff; border: none;
      border-radius: 10px; padding: 11px; font-size: 15px;
      font-weight: 700; width: 100%; cursor: pointer;
      font-family: 'Nunito', sans-serif;
      transition: background .15s, transform .1s;
    }
    .btn-login:hover  { background: #1240ab; }
    .btn-login:active { transform: scale(.98); }
    .alert { border-radius: 10px; font-size: 13px; }
    .demo-hint {
      background: #f0f9ff; border: 1px solid #bae6fd;
      border-radius: 8px; padding: 10px 14px;
      font-size: 12px; color: #0369a1; margin-top: 16px;
    }
    .demo-hint code { background: #e0f2fe; padding: 2px 6px; border-radius: 4px; }
  </style>
</head>
<body>

<div class="login-card">
  <div class="login-header">
    <div class="hotel-logo">H</div>
    <h1><?= APP_NAME ?></h1>
    <p><?= e(HOTEL_NOM) ?></p>
  </div>

  <div class="login-body">

    <?php if ($error): ?>
    <div class="alert alert-danger d-flex align-items-center gap-2 mb-3" role="alert">
      <i class="bi bi-exclamation-triangle-fill"></i>
      <?= e($error) ?>
    </div>
    <?php endif; ?>

    <?php foreach (get_flash() as $f): ?>
    <div class="alert alert-<?= e($f['type']) ?> mb-3"><?= $f['text'] ?></div>
    <?php endforeach; ?>

    <form method="POST" action="<?= APP_URL ?>/index.php?page=login" novalidate>
      <?= csrf_field() ?>

      <div class="mb-3">
        <label class="form-label" for="email">Adresse email</label>
        <div class="input-icon">
          <i class="bi bi-envelope"></i>
          <input type="email" class="form-control" id="email" name="email"
                 value="<?= e($_POST['email'] ?? '') ?>"
                 placeholder="votre@email.com" required autocomplete="email">
        </div>
      </div>

      <div class="mb-4">
        <label class="form-label" for="password">Mot de passe</label>
        <div class="input-icon">
          <i class="bi bi-lock"></i>
          <input type="password" class="form-control" id="password" name="password"
                 placeholder="••••••••" required autocomplete="current-password">
        </div>
      </div>

      <button type="submit" class="btn-login">
        <i class="bi bi-box-arrow-in-right me-2"></i>Se connecter
      </button>
    </form>

    <div class="demo-hint">
      <strong>Accès démo :</strong><br>
      Email : <code>admin@hotel.cm</code><br>
      MDP : <code>Admin@2025</code>
    </div>
  </div>
</div>

<script src="<?= APP_URL ?>/public/js/bootstrap.bundle.min.js"></script>
</body>
</html>
