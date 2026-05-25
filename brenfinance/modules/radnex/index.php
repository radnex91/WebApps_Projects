<?php
require_once __DIR__ . '/../../includes/functions.php';
requireModuleAccess('radnex');

// Auto-redirect to admin config if not configured
$configFile = __DIR__ . '/config.php';
$radnexConfig = null;
if (file_exists($configFile)) {
    $radnexConfig = require $configFile;
}
$provider = $radnexConfig['provider'] ?? ($radnexConfig['use_ollama'] ?? false ? 'ollama' : '');
if (!$radnexConfig || (!$provider && empty($radnexConfig['api_key']) && empty($radnexConfig['use_ollama']))) {
    header('Location: '.BASE_URL.'/modules/radnex/setup.php');
    exit;
}

$assistantName = $radnexConfig['assistant_name'] ?? 'RADNEX';
$assistantPrompt = $radnexConfig['system_prompt'] ?? '';

$pageTitle = ($assistantName ?: 'RADNEX') . ' AI Assistant';
$userId = $_SESSION['user_id'];
$user = currentUser();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= APP_NAME ?> — <?= sanitize($assistantName) ?></title>
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/app.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    .chat-container { max-width: 900px; margin: 0 auto; height: calc(100vh - 180px); display: flex; flex-direction: column }
    .chat-messages { flex: 1; overflow-y: auto; padding: 20px; background: var(--bg); border-radius: var(--radius); margin-bottom: 16px }
    .chat-bubble { max-width: 75%; padding: 12px 16px; border-radius: 18px; margin-bottom: 12px; line-height: 1.5 }
    .chat-bubble.user { background: var(--primary); color: #fff; margin-left: auto; border-bottom-right-radius: 4px }
    .chat-bubble.assistant { background: var(--surface); border: 1px solid var(--border); margin-right: auto; border-bottom-left-radius: 4px }
    .chat-bubble .meta { font-size: 11px; opacity: .6; margin-bottom: 4px }
    .chat-input-area { display: flex; gap: 10px; align-items: flex-end }
    .chat-input-area textarea { flex: 1; resize: none; min-height: 44px; max-height: 120px }
    .typing-indicator { display: none; padding: 12px 16px; color: var(--text3); font-style: italic }
    .typing-indicator.active { display: block }
    .source-link { font-size: 11px; color: var(--primary); margin-top: 6px; display: block }
  </style>
</head>
<body>
<?php include __DIR__ . '/../../includes/header.php'; ?>

<div class="page-header">
  <div>
    <h1><i class="fa-solid fa-robot" style="color:var(--primary)"></i> <?= sanitize($assistantName) ?></h1>
    <p>Assistant intelligent avec accès Internet</p>
  </div>
</div>

<div class="chat-container">
  <div class="chat-messages" id="chat-messages">
    <div class="chat-bubble assistant">
      <div class="meta"><?= sanitize($assistantName) ?></div>
      Bonjour <?= sanitize($user['prenom'] ?? 'utilisateur') ?> ! Je suis <strong><?= sanitize($assistantName) ?></strong>, votre assistant IA.<br>
      Je peux rechercher sur Internet et vous aider avec la gestion financière.<br>
      <small>💡 Posez-moi une question ou demandez-moi de chercher des informations en ligne.</small>
    </div>
  </div>
  <div class="typing-indicator" id="typing"><?= sanitize($assistantName) ?> est en train de réfléchir...</div>
  <div class="chat-input-area">
    <textarea id="user-input" class="form-control" placeholder="Tapez votre message... (Shift+Enter pour nouvelle ligne)" rows="1"></textarea>
    <button class="btn btn-primary" id="send-btn" onclick="sendMessage()"><i class="fa-solid fa-paper-plane"></i></button>
  </div>
</div>

<script>
let conversation = [];
const chatMessages = document.getElementById('chat-messages');
const userInput = document.getElementById('user-input');
const typing = document.getElementById('typing');

userInput.addEventListener('keydown', function(e) {
  if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendMessage(); }
});

async function sendMessage() {
  const msg = userInput.value.trim();
  if (!msg) return;
  appendBubble(msg, 'user');
  userInput.value = '';
  typing.classList.add('active');
  conversation.push({role:'user', content:msg});
  try {
    const res = await fetch('chat.php', {
      method:'POST',
      headers:{'Content-Type':'application/json'},
      body: JSON.stringify({message:msg, history:conversation})
    });
    const data = await res.json();
    typing.classList.remove('active');
    if (data.reply) {
      appendBubble(data.reply, 'assistant', data.sources);
      conversation.push({role:'assistant', content:data.reply});
    } else {
      appendBubble('Erreur: ' + (data.error||'Inconnue'), 'assistant');
    }
  } catch(e) {
    typing.classList.remove('active');
    appendBubble('Erreur de connexion: ' + e.message, 'assistant');
  }
}

function appendBubble(text, who, sources) {
  const div = document.createElement('div');
  div.className = 'chat-bubble ' + who;
  div.innerHTML = '<div class="meta">' + (who==='user'?'Vous':'<?= sanitize($assistantName, ENT_QUOTES) ?>') + '</div>' + escapeHtml(text).replace(/\n/g,'<br>') +
    (sources? sources.map(s=>`<a href="${s.url}" target="_blank" class="source-link"><i class="fa-solid fa-link"></i> ${s.title||s.url}</a>`).join('') : '');
  chatMessages.appendChild(div);
  chatMessages.scrollTop = chatMessages.scrollHeight;
}

function escapeHtml(t){return t.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
