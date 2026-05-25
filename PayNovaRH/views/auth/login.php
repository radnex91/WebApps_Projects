<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Connexion | PayNovaRH</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <!-- AdminLTE -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <!-- Google Font: Source Sans Pro -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=swap">
    <style>
        .login-page {
            background: linear-gradient(135deg, #001f3f 0%, #003366 50%, #004080 100%);
            min-height: 100vh;
        }
        .login-card-body {
            border-radius: 0 0 8px 8px;
        }
        .login-logo a {
            color: #fff;
            text-decoration: none;
        }
        .login-logo .brand-icon {
            font-size: 2.5rem;
            color: #ffc107;
        }
        .login-logo .brand-text {
            font-size: 2rem;
            font-weight: 700;
            letter-spacing: 0.05em;
        }
        .card-outline-navy {
            border-top: 3px solid #001f3f;
        }
        .btn-navy-gradient {
            background: linear-gradient(135deg, #001f3f 0%, #004080 100%);
            border: none;
            color: #fff;
            font-weight: 600;
            letter-spacing: 0.03em;
            transition: all 0.3s ease;
        }
        .btn-navy-gradient:hover {
            background: linear-gradient(135deg, #003366 0%, #0059b3 100%);
            color: #fff;
            box-shadow: 0 4px 15px rgba(0, 31, 63, 0.4);
        }
        .login-box {
            margin-top: 8vh;
        }
        .input-group-text {
            background-color: #f8f9fa;
            border-right: none;
        }
        .form-control-border-right {
            border-left: none;
        }
        .alert-login {
            border-radius: 6px;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body class="hold-transition login-page">
<div class="login-box">
    <!-- Logo -->
    <div class="login-logo mb-2">
        <a href="<?php echo APP_URL; ?>/">
            <i class="brand-icon fas fa-building"></i>
            <span class="brand-text">PayNovaRH</span>
        </a>
    </div>

    <!-- Flash error message -->
    <?php if (isset($error) && $error): ?>
        <div class="alert alert-danger alert-dismissible alert-login mx-3" role="alert">
            <i class="icon fas fa-exclamation-triangle mr-2"></i>
            <?php echo e($error); ?>
            <button type="button" class="close" data-dismiss="alert" aria-label="Fermer">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    <?php endif; ?>

    <?php $flashError = Flash::get('error'); ?>
    <?php if ($flashError): ?>
        <div class="alert alert-danger alert-dismissible alert-login mx-3" role="alert">
            <i class="icon fas fa-exclamation-triangle mr-2"></i>
            <?php echo e($flashError); ?>
            <button type="button" class="close" data-dismiss="alert" aria-label="Fermer">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    <?php endif; ?>

    <!-- Login Card -->
    <div class="card card-outline card-navy">
        <div class="card-header text-center">
            <h5 class="card-title mb-0">
                <i class="fas fa-sign-in-alt mr-1"></i> Connexion
            </h5>
        </div>
        <div class="card-body login-card-body">
            <p class="login-box-msg text-muted">Veuillez vous authentifier pour accéder à l'application</p>

            <form action="<?php echo APP_URL; ?>/login" method="post" autocomplete="on">
                <!-- CSRF Token -->
                <?php echo Session::csrfField(); ?>

                <!-- Username / Email -->
                <div class="input-group mb-3">
                    <input type="text"
                           name="username"
                           id="username"
                           class="form-control"
                           placeholder="Nom d'utilisateur ou e-mail"
                           value="<?php echo e($old['username'] ?? ''); ?>"
                           required
                           autofocus
                           autocomplete="username">
                    <div class="input-group-append">
                        <span class="input-group-text"><i class="fas fa-user"></i></span>
                    </div>
                </div>

                <!-- Password -->
                <div class="input-group mb-3">
                    <input type="password"
                           name="password"
                           id="password"
                           class="form-control"
                           placeholder="Mot de passe"
                           required
                           autocomplete="current-password">
                    <div class="input-group-append">
                        <span class="input-group-text"><i class="fas fa-lock"></i></span>
                    </div>
                </div>

                <div class="row">
                    <!-- Remember Me -->
                    <div class="col-8">
                        <div class="icheck-primary">
                            <input type="checkbox"
                                   name="remember"
                                   id="remember"
                                   value="1">
                            <label for="remember">
                                Se souvenir de moi
                            </label>
                        </div>
                    </div>
                    <!-- Login Button -->
                    <div class="col-4">
                        <button type="submit" class="btn btn-navy-gradient btn-block">
                            <i class="fas fa-sign-in-alt mr-1"></i> Entrer
                        </button>
                    </div>
                </div>
            </form>

            <div class="text-center mt-3">
                <a href="<?php echo APP_URL; ?>/forgot-password" class="text-sm text-navy">
                    <i class="fas fa-question-circle mr-1"></i> Mot de passe oublié ?
                </a>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <div class="text-center mt-3 text-white-50 small">
        <strong>&copy; <?php echo date('Y'); ?> PayNovaRH.</strong> Tous droits réservés.
    </div>
</div>

<!-- jQuery -->
<script src="https://cdn.jsdelivr.net/npm/jquery@3.6.4/dist/jquery.min.js"></script>
<!-- Bootstrap 4 -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<!-- AdminLTE -->
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>

<script>
$(function () {
    // Auto-focus username field
    $('#username').focus();

    // Toggle password visibility on icon click
    $('.input-group-append').on('click', function () {
        var input = $(this).siblings('input');
        if (input.attr('type') === 'password') {
            input.attr('type', 'text');
            $(this).find('i').removeClass('fa-lock').addClass('fa-unlock');
        } else {
            input.attr('type', 'password');
            $(this).find('i').removeClass('fa-unlock').addClass('fa-lock');
        }
    });

    // Auto-dismiss alerts
    setTimeout(function () {
        $('.alert-dismissible').fadeOut('slow', function () {
            $(this).remove();
        });
    }, 5000);
});
</script>
</body>
</html>