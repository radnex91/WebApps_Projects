<?php
$hash = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';
foreach (['admin123','password','admin','secret','test','123456','root','Medicore123!','admin@medicore'] as $p) {
    echo "$p: " . (password_verify($p, $hash) ? 'YES' : 'NO') . "\n";
}
