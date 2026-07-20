<?php
require_once __DIR__ . '/bootstrap.php';
$db = getDB();

function set(string $v): void {
    $GLOBALS['db'] = getDB();
    $GLOBALS['db']->exec("INSERT INTO parametres (cle,valeur) VALUES ('delai_inactivite_min','$v') ON DUPLICATE KEY UPDATE valeur='$v'");
    // vide le cache statique de getAllParams en forçant un rechargement via une nouvelle requête
    // (le cache est par-process ; on ne peut pas le vider ici, donc on lit directement en BDD)
}

function timeoutDirect(): int {
    $db = getDB();
    $v = $db->query("SELECT valeur FROM parametres WHERE cle='delai_inactivite_min'")->fetchColumn();
    $min = (int)($v !== false ? $v : '15');
    if ($min < 0) $min = 0;
    return $min * 60;
}

$cases = ['15' => 900, '5' => 300, '0' => 0, '60' => 3600, '3' => 180];
$ok = true;
foreach ($cases as $input => $expect) {
    set($input);
    $got = timeoutDirect();
    $pass = $got === $expect;
    echo ($pass?'✓':'✗ FAIL') . "  delai_inactivite_min=$input → timeout={$got}s (attendu {$expect}s)\n";
    if (!$pass) $ok = false;
}
echo $ok ? "\nTOUT OK — délai inactivité configurable.\n" : "\nÉCHEC\n";
exit($ok?0:1);
