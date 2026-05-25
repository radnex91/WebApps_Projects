<?php
/**
 * Navigation secondaire sous la zone « Facturation ».
 *
 * @param string $currentBase ex. admin-facturation.php
 */
function esadiss_facturation_subnav($currentBase) {
  $links = [
    ['admin-facturation.php', 'Liste des factures'],
    ['admin-facture-edit.php', 'Nouvelle facture'],
  ];
  echo '<nav class="stock-subnav" aria-label="Sections facturation">';
  foreach ($links as $L) {
    $active = ($L[0] === $currentBase) ? ' stock-subnav__link--active' : '';
    echo '<a class="stock-subnav__link' . $active . '" href="' . htmlspecialchars($L[0], ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($L[1], ENT_QUOTES, 'UTF-8') . '</a>';
  }
  echo '</nav>';
}