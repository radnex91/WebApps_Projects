<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - SYSTRACO</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            overflow: hidden;
        }
        .login-header {
            background: #ff6600;
            color: white;
            padding: 30px;
            text-align: center;
        }
        .login-header h1 {
            margin: 0;
            font-size: 28px;
        }
        .login-header i {
            font-size: 48px;
            margin-bottom: 10px;
        }
        .btn-primary {
            background: #ff6600;
            border-color: #ff6600;
        }
        .btn-primary:hover {
            background: #e55a00;
            border-color: #e55a00;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-5">
                <div class="login-card">
                    <div class="login-header">
                        <i class="fas fa-bus"></i>
                        <h1>SYSTRACO</h1>
                        <p class="mb-0">Gestion de Transport</p>
                    </div>
                    <div class="card-body p-4">
                        <?php $flash = getFlash(); ?>
                        <?php if (!empty($flash)): ?>
                            <?php foreach ($flash as $type => $message): ?>
                                <div class="alert alert-<?php echo $type; ?>"><?php echo $message; ?></div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        
                        <form method="POST" action="<?php echo BASE_URL; ?>/login">
                            <div class="mb-3">
                                <label class="form-label">Login</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-user"></i></span>
                                    <input type="text" class="form-control" name="login" required autofocus>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Mot de passe</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                    <input type="password" class="form-control" name="mot_de_passe" required>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary w-100 py-2">
                                <i class="fas fa-sign-in-alt"></i> Connexion
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>