<?php
/**
 * Navigation secondaire sous la zone « Gestion des stocks ».
 *
 * @param string $currentBase ex. admin-stock-entree.php
 */
function esadiss_stock_subnav($currentBase) {
  $links = [
    ['admin-stock-situation.php', 'Situation & statistiques'],
    ['admin-stock-entree.php', 'Entrée en stock'],
    ['admin-stock-sortie.php', 'Sortie de stock'],
    ['admin-stock-transfert.php', 'Transfert'],
    ['admin-stock-ajustement.php', 'Ajustement'],
    ['admin-stock-lieux.php', 'Dépôts & rayons'],
  ];
  echo '<nav class="stock-subnav" aria-label="Sections gestion stock">';
  foreach ($links as $L) {
    $active = ($L[0] === $currentBase) ? ' stock-subnav__link--active' : '';
    echo '<a class="stock-subnav__link' . $active . '" href="' . htmlspecialchars($L[0], ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($L[1], ENT_QUOTES, 'UTF-8') . '</a>';
  }
  echo '</nav>';
}