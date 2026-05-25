<?php
require_once __DIR__ . '/config/database.php';
http_response_code(404);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Page introuvable — PharmaCare</title>
<style>
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:sans-serif;min-height:100vh;display:flex;align-items:center;justify-content:center;background:#0F172A;color:#E2E8F0;}
.container{text-align:center;padding:40px;}
h1{font-size:72px;color:#0D9488;margin-bottom:16px;}
p{color:#64748B;margin-bottom:24px;}
a{color:#5EEAD4;text-decoration:none;font-weight:600;}
</style>
</head>
<body>
<div class="container">
<h1>404</h1>
<p>La page que vous cherchez n'existe pas.</p>
<a href="<?= APP_URL ?>/dashboard.php">Retour au tableau de bord</a>
</div>
</body>
</html>
