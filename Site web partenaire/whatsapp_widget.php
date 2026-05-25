<?php
/**
 * Bouton flottant WhatsApp.
 * Affiché seulement si WHATSAPP_PHONE est renseigné dans config.php.
 */
function renderWhatsAppFab() {
  $phone = defined('WHATSAPP_PHONE') ? preg_replace('/\D+/', '', (string) WHATSAPP_PHONE) : '';
  if ($phone === '') return;
  $message = rawurlencode('Bonjour, je vous contacte depuis la plateforme ESADISS.');
  $url = 'https://wa.me/' . $phone . '?text=' . $message;
  echo '<a class="whatsapp-fab" href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener noreferrer" aria-label="Contacter sur WhatsApp" title="Contacter sur WhatsApp">';
  echo '<svg width="28" height="28" viewBox="0 0 32 32" aria-hidden="true"><path fill="currentColor" d="M19.11 17.35c-.3-.15-1.77-.87-2.04-.97-.27-.1-.47-.15-.67.15-.2.3-.77.97-.94 1.17-.17.2-.35.22-.65.07-.3-.15-1.26-.46-2.4-1.46-.89-.79-1.49-1.76-1.66-2.06-.17-.3-.02-.46.13-.61.13-.13.3-.35.45-.52.15-.17.2-.3.3-.5.1-.2.05-.37-.03-.52-.07-.15-.67-1.62-.92-2.22-.24-.58-.48-.5-.67-.51h-.57c-.2 0-.52.07-.8.37-.27.3-1.05 1.02-1.05 2.49s1.08 2.89 1.23 3.09c.15.2 2.12 3.24 5.14 4.54.72.31 1.28.5 1.72.64.72.23 1.37.2 1.88.12.57-.09 1.77-.72 2.02-1.42.25-.69.25-1.28.17-1.42-.07-.14-.27-.22-.57-.37Z"/><path fill="currentColor" d="M16.02 3.2c-7.06 0-12.8 5.74-12.8 12.8 0 2.26.59 4.47 1.7 6.42L3 29l6.74-1.76a12.74 12.74 0 0 0 6.28 1.66h.01c7.05 0 12.79-5.74 12.79-12.8 0-3.42-1.33-6.64-3.76-9.06a12.7 12.7 0 0 0-9.04-3.84Zm0 23.47h-.01a10.63 10.63 0 0 1-5.4-1.49l-.39-.23-3.99 1.04 1.07-3.89-.25-.4a10.63 10.63 0 1 1 8.97 4.97Z"/></svg>';
  echo '</a>';
}
