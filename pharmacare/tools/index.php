<?php
// Redirige la racine du serveur local de licence vers le GUI.
// (Le serveur PHP local sert tools/ ; sans index.php, / renvoie un 404.)
header('Location: gen_licence_gui.php');
exit;