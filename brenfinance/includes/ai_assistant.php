<?php
// Radnex AI Assistant — proactive, memory-enabled, human-like
$user = currentUser();
$role = $user['role_nom'] ?? 'demandeur';
$mod = $currentModule ?? 'dashboard';
$userName = trim(($user['prenom'] ?? '') . ' ' . ($user['nom'] ?? ''));
$userFirstName = trim($user['prenom'] ?? $user['nom'] ?? 'utilisateur');
$userService = '';
$uid = $_SESSION['user_id'] ?? 0;
if ($uid) {
    $radnexDb = getDB();
    $srvQ = $radnexDb->prepare("SELECT s.nom FROM services s JOIN utilisateurs u ON u.service_id=s.id WHERE u.id=?");
    $srvQ->execute([$uid]);
    $userService = $srvQ->fetchColumn() ?: '';
}

// Count pending items for this user's role
$pendingEng = 0; $pendingOM = 0;
try {
    if (in_array($role, ['daf','comptable','valideur_n1','super_admin'])) {
        $st = $role === 'daf' ? 'valide_hierarchie' : ($role === 'comptable' ? 'valide_hierarchie' : 'soumis');
        if ($role === 'daf') {
            $st = 'valide_hierarchie';
            $pendingEng = (int)$radnexDb->query("SELECT COUNT(*) FROM demandes_engagement WHERE statut='valide_comptable'")->fetchColumn();
        } elseif ($role === 'comptable') {
            $st = 'valide_hierarchie';
            $pendingEng = (int)$radnexDb->query("SELECT COUNT(*) FROM demandes_engagement WHERE statut='$st'")->fetchColumn();
        } elseif ($role === 'valideur_n1') {
            $pendingEng = (int)$radnexDb->query("SELECT COUNT(*) FROM demandes_engagement WHERE statut='soumis'")->fetchColumn();
        }
        if ($role === 'daf') {
            $pendingOM = (int)$radnexDb->query("SELECT COUNT(*) FROM ordres_mission WHERE statut='valide_hierarchie'")->fetchColumn();
        } elseif ($role === 'valideur_n1') {
            $pendingOM = (int)$radnexDb->query("SELECT COUNT(*) FROM ordres_mission WHERE statut='soumis'")->fetchColumn();
        }
    }
    if ($role === 'super_admin') {
        $pendingEng = (int)$radnexDb->query("SELECT COUNT(*) FROM demandes_engagement WHERE statut IN ('soumis','valide_hierarchie','valide_comptable')")->fetchColumn();
        $pendingOM = (int)$radnexDb->query("SELECT COUNT(*) FROM ordres_mission WHERE statut IN ('soumis','valide_hierarchie')")->fetchColumn();
    }
} catch(Exception $e) {}

$hour = (int)date('H');

// More natural, warm greetings
if ($hour < 8)       $timeGreet = 'Déjà là, ' . $userFirstName . ' ?';
elseif ($hour < 12)  $timeGreet = 'Bonjour ' . $userFirstName;
elseif ($hour < 14)  $timeGreet = 'Bon appétit... ou presque, ' . $userFirstName . ' !';
elseif ($hour < 18)  $timeGreet = 'Bon après-midi, ' . $userFirstName;
elseif ($hour < 20)  $timeGreet = 'Bonsoir ' . $userFirstName . ' ! Toujours au travail ?';
else                 $timeGreet = 'Bonne soirée, ' . $userFirstName . ' !';

$roleLabels = ['super_admin'=>'Super Administrateur','daf'=>'Directeur Financier','comptable'=>'Comptable','caissier'=>'Caissier','demandeur'=>'Demandeur','valideur_n1'=>'Responsable Hiérarchique'];

$roleConfig = [
    'super_admin' => ['greeting'=>$timeGreet,'subtitle'=>'Vous avez les clés de la maison !','avatar'=>'ph-bold ph-crown','color'=>'#f59e0b',
        'tips'=>['Un coup d\'oeil sur le journal d\'audit de temps en temps, ça rassure tout le monde.','Pensez à vérifier les rôles si quelqu\'un n\'arrive pas à accéder à un module.','Les référentiels sont la base de tout — modes de paiement, destinations, comptes...'],
        'actions'=>[['icon'=>'ph-bold ph-users','label'=>'Gérer les utilisateurs','url'=>BASE_URL.'/modules/admin/index.php'],['icon'=>'ph-bold ph-shield-check','label'=>'Journal d\'audit','url'=>BASE_URL.'/modules/audit/index.php'],['icon'=>'ph-bold ph-database','label'=>'Référentiels','url'=>BASE_URL.'/modules/referentiels/index.php']]],
    'daf' => ['greeting'=>$timeGreet,'subtitle'=>'La vue d\'ensemble vous attend','avatar'=>'ph-bold ph-chart-line-up','color'=>'#6366f1',
        'tips'=>['Des engagements validés par la comptabilité sont prêts pour votre visa.','La trésorerie bouge en continu — un œil sur le tableau de bord et c\'est plus clair.','Les rapports financiers se prêtent bien aux réunions de direction.'],
        'actions'=>[['icon'=>'ph-bold ph-clipboard-text','label'=>'Engagements à valider','url'=>BASE_URL.'/modules/engagements/index.php?statut=valide_comptable'],['icon'=>'ph-bold ph-airplane','label'=>'Missions à valider','url'=>BASE_URL.'/modules/ordre_mission/index.php?statut=valide_hierarchie'],['icon'=>'ph-bold ph-chart-bar','label'=>'Reporting','url'=>BASE_URL.'/modules/reporting/index.php']]],
    'comptable' => ['greeting'=>$timeGreet,'subtitle'=>'La rigueur au quotidien','avatar'=>'ph-bold ph-calculator','color'=>'#0ea5e9',
        'tips'=>['SYSCOHADA demande de la précision — chaque écriture compte.','Des engagements validés par la hiérarchie attendent votre tour.','Le rapprochement caisse/journal, c\'est la tranquillité assurée.'],
        'actions'=>[['icon'=>'ph-bold ph-book-open','label'=>'Saisie comptable','url'=>BASE_URL.'/modules/comptabilite/index.php'],['icon'=>'ph-bold ph-clipboard-text','label'=>'Engagements à valider','url'=>BASE_URL.'/modules/engagements/index.php?statut=valide_hierarchie'],['icon'=>'ph-bold ph-bank','label'=>'Trésorerie','url'=>BASE_URL.'/modules/tresorerie/index.php']]],
    'caissier' => ['greeting'=>$timeGreet,'subtitle'=>'Votre caisse, votre responsabilité','avatar'=>'ph-bold ph-wallet','color'=>'#10b981',
        'tips'=>['Un petit réflexe : vérifier le solde avant de saisir, ça évite les surprises.','Les ordres de mission approuvés sont prêts pour exécution en caisse.','Chaque opération compte — littéralement !'],
        'actions'=>[['icon'=>'ph-bold ph-wallet','label'=>'Ma caisse','url'=>BASE_URL.'/modules/caisse/index.php'],['icon'=>'ph-bold ph-arrows-left-right','label'=>'Opérations','url'=>BASE_URL.'/modules/operations_caisse/index.php'],['icon'=>'ph-bold ph-airplane','label'=>'Missions à exécuter','url'=>BASE_URL.'/modules/ordre_mission/index.php?statut=approuve']]],
    'demandeur' => ['greeting'=>$timeGreet,'subtitle'=>'Créez et suivez vos demandes','avatar'=>'ph-bold ph-user','color'=>'#8b5cf6',
        'tips'=>['Une dépense prévue ? Un engagement est le premier pas.','Un déplacement professionnel ? L\'ordre de mission se prépare à l\'avance.','Vous pouvez suivre l\'avancement de vos demandes en un clin d\'œil.'],
        'actions'=>[['icon'=>'ph-bold ph-clipboard-text','label'=>'Nouvel engagement','url'=>BASE_URL.'/modules/engagements/creer.php'],['icon'=>'ph-bold ph-airplane','label'=>'Nouvel ordre de mission','url'=>BASE_URL.'/modules/ordre_mission/creer.php'],['icon'=>'ph-bold ph-chart-bar','label'=>'Mes demandes','url'=>BASE_URL.'/modules/engagements/index.php']]],
    'valideur_n1' => ['greeting'=>$timeGreet,'subtitle'=>'Votre équipe compte sur vous','avatar'=>'ph-bold ph-user-check','color'=>'#ec4899',
        'tips'=>['Des demandes de votre équipe attendent votre feu vert.','Pas sûr d\'une demande ? Renvoyez-la pour révision, c\'est mieux que de rejeter.','Les missions aussi passent par votre validation avant d\'aller plus loin.'],
        'actions'=>[['icon'=>'ph-bold ph-clipboard-text','label'=>'Engagements à valider','url'=>BASE_URL.'/modules/engagements/index.php?statut=soumis'],['icon'=>'ph-bold ph-airplane','label'=>'Missions à valider','url'=>BASE_URL.'/modules/ordre_mission/index.php?statut=soumis'],['icon'=>'ph-bold ph-chart-bar','label'=>'Tableau de bord','url'=>BASE_URL.'/dashboard.php']]],
];
if (!isset($roleConfig[$role])) $role = 'demandeur';
$cfg = $roleConfig[$role] ?? $roleConfig['demandeur'];

$moduleContext = [
    'dashboard'=>['Tableau de bord','Vos indicateurs en un coup d\'œil','Le reflet en temps réel de la santé financière'],
    'caisse'=>['Caisse','Le pouls de vos espèces','Chaque mouvement est enregistré au fur et à mesure'],
    'operations_caisse'=>['Opérations','Les mouvements au quotidien','Encaissements, décaissements — tout est tracé'],
    'tresorerie'=>['Trésorerie','L\'eau dans les tuyaux financiers','Flux, prévisions et soldes bancaires'],
    'engagements'=>['Engagements','Le circuit des dépenses','Du brouillon jusqu\'à l\'exécution, chaque étape compte'],
    'ordre_mission'=>['Ordres de Mission','Les déplacements autorisés','Création, validation, exécution — tout est fluide'],
    'decharge'=>['Décharges','Le désengagement de responsabilité','Le lien entre mission exécutée et libération'],
    'comptabilite'=>['Comptabilité','Conforme au plan SYSCOHADA','Journaux, balances, écritures — la rigueur au service de la clarté'],
    'budget'=>['Budget','Prévisions vs réalité','Suivez l\'écart entre ce qui était prévu et ce qui est réalisé'],
    'reporting'=>['Reporting','Vos rapports sur mesure','Bilans, comptes de résultat, états de flux — exportables en PDF ou Excel'],
    'audit'=>['Audit','L\'historique de tout','Qui a fait quoi, quand — rien n\'échappe au journal'],
    'admin'=>['Administration','Le centre de contrôle','Utilisateurs, rôles, paramètres — tout se gère d\'ici'],
    'referentiels'=>['Référentiels','Les fondations','Destinations, modes de paiement, types d\'opérations, comptes...'],
];
$currentCtx = $moduleContext[$mod] ?? $moduleContext['dashboard'];

// Proactive messages — more natural, less formal
$proactiveQuestions = [
    'daf_dashboard' => $userFirstName . ', j\'ai repéré <b>' . $pendingEng . ' engagement(s)</b> et <b>' . $pendingOM . ' ordre(s) de mission</b> qui attendent votre signature. On les regarde ensemble ?',
    'daf_engagements' => $userFirstName . ', quelques engagements sont sur votre bureau pour validation DAF. Vous voulez qu\'on fasse le tour ?',
    'daf_ordre_mission' => 'Des ordres de mission attendent votre approbation, ' . $userFirstName . '. Je peux vous aider à les parcourir.',
    'valideur_dashboard' => $userFirstName . ', <b>' . $pendingEng . ' demande(s)</b> de votre équipe attendent votre feu vert. Ça vous dit d\'y jeter un œil ?',
    'demandeur_dashboard' => $userFirstName . ', vous avez besoin de créer une demande d\'engagement ou un ordre de mission ? Je vous guide.',
    'caissier_dashboard' => $userFirstName . ', le solde de votre caisse va bien ce matin ? Un petit contrôle ne fait jamais de mal.',
    'comptable_dashboard' => $userFirstName . ', des écritures à passer aujourd\'hui ? Je peux vous montrer par où commencer.',
    'super_admin_dashboard' => $userFirstName . ', tout roule de mon côté. Vous voulez jeter un œil aux logs ou gérer les accès ?',
    'idle' => $userFirstName . ', vous semblez ailleurs... tout va bien ? Je suis là si vous avez besoin d\'un coup de main sur <b>' . $currentCtx[0] . '</b>.',
];

$proactiveKey = $role . '_' . $mod;
$proactiveQ = $proactiveQuestions[$proactiveKey] ?? null;

$userContext = json_encode([
    'name' => $userName, 'firstName' => $userFirstName, 'role' => $role,
    'roleLabel' => $roleLabels[$role] ?? $role, 'service' => $userService,
    'module' => $mod, 'moduleLabel' => $currentCtx[0], 'hour' => $hour,
    'pendingEng' => $pendingEng, 'pendingOM' => $pendingOM,
    'proactiveQ' => $proactiveQ,
], JSON_UNESCAPED_UNICODE);
?>
<!-- ═══ RADNEX AI ASSISTANT ═══════════════════════════════════════ -->
<div id="ai-fab" title="Radnex — Votre assistant financier">
  <i class="ph-bold ph-sparkle"></i>
  <span id="radnex-badge" class="radnex-badge" style="display:none">!</span>
</div>

<div id="ai-panel" class="ai-panel">
  <div class="ai-header">
    <div class="ai-header-left">
      <div class="ai-avatar" style="background:<?= $cfg['color'] ?>"><i class="<?= $cfg['avatar'] ?>"></i></div>
      <div>
        <div class="ai-title">Radnex</div>
        <div class="ai-subtitle" id="radnex-subtitle"><?= $cfg['greeting'] ?></div>
      </div>
    </div>
    <div class="ai-header-right">
      <button class="ai-voice-btn" id="radnex-voice-btn" onclick="toggleRadnexVoice()" title="Commande vocale : dites « Hey Radnex »">
        <i class="ph-bold ph-microphone"></i>
      </button>
      <button class="ai-close" onclick="toggleRadnex()" aria-label="Fermer"><i class="ph-bold ph-x"></i></button>
    </div>
  </div>

  <div class="ai-body" id="ai-body">
    <div id="radnex-messages"></div>

    <div class="ai-section ai-input-section">
      <form id="ai-chat-form" onsubmit="askRadnex(event)">
        <div class="ai-input-wrap">
          <input type="text" id="ai-chat-input" class="ai-input" placeholder="Dites-moi ce qu'il vous faut..." autocomplete="off">
          <button type="submit" class="ai-send-btn" title="Envoyer"><i class="ph-bold ph-paper-plane-tilt"></i></button>
        </div>
      </form>
      <div id="ai-suggestions" class="ai-suggestions"></div>
    </div>
  </div>
</div>

<script>
/* ═══ RADNEX — Human-like AI with Memory ═══════════════════════════ */
var RADNEX_CTX = <?= $userContext ?>;

/* ─── Helpers ─── */
function radnexPick(arr) { return arr[Math.floor(Math.random() * arr.length)]; }
function radnexTypingDelay() { return 600 + Math.random() * 500; }

function toggleRadnex() {
  var panel = document.getElementById('ai-panel');
  var fab = document.getElementById('ai-fab');
  var isOpen = panel.classList.contains('open');
  if (isOpen) {
    panel.classList.remove('open');
    fab.innerHTML = '<i class="ph-bold ph-sparkle"></i><span id="radnex-badge" class="radnex-badge" style="display:none">!</span>';
  } else {
    panel.classList.add('open');
    fab.innerHTML = '<i class="ph-bold ph-x"></i>';
    var input = document.getElementById('ai-chat-input');
    if (input) setTimeout(function(){ input.focus(); }, 300);
  }
  var badge = document.getElementById('radnex-badge');
  if (badge) badge.style.display = 'none';
}

/* ─── Memory ─── */
function radnexMemory() {
  try {
    var m = JSON.parse(sessionStorage.getItem('radnex_memory') || '{}');
    if (!m.history) m.history = [];
    if (!m.visits) m.visits = 0;
    if (!m.firstName) m.firstName = RADNEX_CTX.firstName;
    if (!m.role) m.role = RADNEX_CTX.role;
    if (!m.seen) m.seen = {};
    if (!m.asked) m.asked = {};
    if (!m.mood) m.mood = 'neutral';
    return m;
  } catch(e) { return { history: [], visits: 0, firstName: RADNEX_CTX.firstName, role: RADNEX_CTX.role, seen: {}, asked: {}, mood: 'neutral' }; }
}
function radnexSave(m) { try { sessionStorage.setItem('radnex_memory', JSON.stringify(m)); } catch(e) {} }
function radnexTrackAction(label) {
  var m = radnexMemory();
  m.history.push({ role: 'user', text: 'Action : ' + label, time: Date.now() });
  if (m.history.length > 30) m.history = m.history.slice(-30);
  radnexSave(m);
}

/* ─── Messages ─── */
function radnexAddMessage(who, text, type) {
  var container = document.getElementById('radnex-messages');
  var div = document.createElement('div');
  div.className = 'radnex-msg radnex-msg-' + who;
  if (type === 'welcome') div.className += ' radnex-msg-welcome';
  if (type === 'proactive') div.className += ' radnex-msg-proactive';
  var now = new Date();
  var timeStr = now.getHours().toString().padStart(2,'0') + ':' + now.getMinutes().toString().padStart(2,'0');
  if (who === 'radnex') {
    div.innerHTML = '<div class="radnex-msg-avatar"><div class="radnex-mini-avatar" style="background:' + (RADNEX_CTX.color || '#6366f1') + '"><i class="ph-bold ph-sparkle"></i></div></div>' +
      '<div class="radnex-msg-bubble"><div class="radnex-msg-name">Radnex <span class="radnex-msg-time">' + timeStr + '</span></div><div class="radnex-msg-text">' + text + '</div></div>';
  } else {
    div.innerHTML = '<div class="radnex-msg-bubble radnex-msg-bubble-user"><div class="radnex-msg-name">Vous <span class="radnex-msg-time">' + timeStr + '</span></div><div class="radnex-msg-text">' + text + '</div></div>';
  }
  container.appendChild(div);
  var body = document.getElementById('ai-body');
  body.scrollTop = body.scrollHeight;
}

/* ─── Suggestion chips ─── */
function radnexShowSuggestions(suggestions) {
  var container = document.getElementById('ai-suggestions');
  container.innerHTML = '';
  suggestions.forEach(function(s) {
    var chip = document.createElement('button');
    chip.type = 'button';
    chip.className = 'ai-chip';
    chip.textContent = s;
    chip.onclick = function() {
      document.getElementById('ai-chat-input').value = s;
      askRadnex(new Event('submit'));
      container.innerHTML = '';
    };
    container.appendChild(chip);
  });
}

/* ─── Knowledge base — conversational, human-like ─── */
var radnexKnowledge = {
  engagement: {
    keywords: ['engagement','demande','engager','depense','depenser','payer','paiement','fournisseur','facture'],
    answer: function() {
      var name = RADNEX_CTX.firstName;
      if (RADNEX_CTX.role === 'demandeur') return radnexPick([
        name + ', pour lancer un engagement c\'est simple : allez dans <b>Engagements</b>, cliquez "Nouvelle demande", remplissez l\'objet et le fournisseur, ajoutez vos lignes, puis soumettez. Je serai là si vous bloquez quelque part !',
        'Rien de compliqué, ' + name + ' ! <b>Engagements</b> → "Nouvelle demande" → vous remplissez le formulaire → et vous soumettez pour validation. Les valideurs feront le reste.',
        name + ', voici le chemin : <b>Engagements</b> → Nouvelle demande → remplir l\'objet, fournisseur, lignes → soumettre. Ensuite, c\'est le circuit de validation qui prend le relais !'
      ]);
      if (RADNEX_CTX.role === 'daf') return radnexPick([
        name + ', vous êtes le dernier maillon de la chaîne. Vérifiez le budget et la conformité, et votre visa déclenche l\'exécution. Pas de pression !',
        'En tant que DAF, ' + name + ', vous avez le dernier mot sur les engagements. Budget conforme ? Tout est en règle ? Votre approbation et c\'est parti !'
      ]);
      if (RADNEX_CTX.role === 'comptable') return radnexPick([
        name + ', à votre tour : vérifiez l\'imputation budgétaire et la conformité SYSCOHADA. C\'est vous qui garantissez la justesse comptable de l\'engagement.',
        'Votre rôle est crucial, ' + name + ' : imputation budgétaire, conformité SYSCOHADA... Une fois validé au niveau comptable, ça monte au DAF.'
      ]);
      if (RADNEX_CTX.role === 'valideur_n1') return radnexPick([
        name + ', vous êtes le premier filtre. La demande est-elle pertinente ? Le budget le permet ? Si oui, validez, sinon renvoyez pour révision — c\'est mieux qu\'un rejet sec.',
        'À vous de jouer, ' + name + ' ! Première validation : pertinence, budget, conformité. Un doute ? Renvoyez pour révision, votre équipe comprendra.'
      ]);
      return radnexPick([
        'Pour créer un engagement : <b>Engagements</b> → Nouvelle demande → remplir le formulaire → soumettre. Le circuit de validation fait le reste !',
        'C\'est simple : allez dans <b>Engagements</b>, cliquez "Nouvelle demande", remplissez et soumettez. Les valideurs s\'occupent du reste.'
      ]);
    }
  },
  mission: {
    keywords: ['mission','deplacement','voyage','ordre','deplacer','transport','per diem','indemnite','hebergement'],
    answer: function() {
      var name = RADNEX_CTX.firstName;
      if (RADNEX_CTX.role === 'demandeur') return radnexPick([
        name + ', pour un ordre de mission : <b>Ordres de mission</b> → Nouvel ordre → décrivez l\'objet, le lieu, les dates et le budget → soumettez. Ça part en validation et vous suivez l\'avancement.',
        'Un déplacement en vue, ' + name + ' ? Allez dans <b>Ordres de mission</b>, créez un nouvel ordre avec les détails, et soumettez. Le circuit de validation est assez rapide normalement.'
      ]);
      if (RADNEX_CTX.role === 'daf') return radnexPick([
        name + ', les ordres de mission arrivent sur votre bureau après validation hiérarchique. Vérifiez les montants, la conformité, et votre approbation lance l\'exécution.',
        'Dernière étape pour les missions, ' + name + ' ! Votre approbation DAF et l\'ordre peut être exécuté en caisse.'
      ]);
      if (RADNEX_CTX.role === 'valideur_n1') return radnexPick([
        name + ', vous validez les missions au niveau hiérarchique. L\'objectif est-il justifié ? Le budget est-il raisonnable ? Si oui, approuvez !',
        'Premier regard sur les missions, ' + name + '. Objectif clair, budget cohérent ? Votre validation et ça monte au DAF.'
      ]);
      return radnexPick([
        'Pour un ordre de mission : <b>Ordres de mission</b> → Nouvel ordre → remplissez le formulaire → soumettez.',
        'C\'est par là : <b>Ordres de mission</b> → Nouvel ordre. Décrivez le déplacement, les dates, le budget, et soumettez pour validation.'
      ]);
    }
  },
  caisse: {
    keywords: ['caisse','solde','encaissement','decaissement','especes','liquide','cash','argent'],
    answer: function() {
      var name = RADNEX_CTX.firstName;
      return radnexPick([
        name + ', la caisse c\'est le cœur battant de vos finances. Encaissements, décaissements, tout est tracé. Un conseil : toujours vérifier le solde avant de saisir une opération.',
        'La caisse enregistre chaque mouvement, ' + name + '. Réflexe numéro 1 : vérifiez le solde avant toute saisie. Ça évite les mauvaises surprises !',
        name + ', chaque franc qui entre ou sort de la caisse est enregistré. Pensez à vérifier le solde avant de saisir — c\'est un réflexe qui sauve.'
      ]);
    }
  },
  validation: {
    keywords: ['valider','approuver','rejeter','renvoyer','approbation','visa','valide','refuser','hierarchique','comptable'],
    answer: function() {
      var name = RADNEX_CTX.firstName;
      if (RADNEX_CTX.role === 'valideur_n1') return radnexPick([
        name + ', vous êtes le premier rempart. Approuvez si tout est clair, renvoyez pour révision si un détail manque, ou rejetez en dernier recours. Le renvoi, c\'est souvent plus humain que le rejet.',
        'À vous de jouer, ' + name + ' ! Approuvez, renvoyez ou rejetez. Mon conseil : préférez le renvoi au rejet quand c\'est possible — ça garde la dynamique positive.'
      ]);
      if (RADNEX_CTX.role === 'comptable') return radnexPick([
        name + ', votre validation comptable est le garde-fou. Imputation, conformité SYSCOHADA... Une fois validé, ça passe au DAF.',
        'Niveau comptable, ' + name + ' : vérifiez l\'imputation et la conformité. Après vous, c\'est le DAF qui décide.'
      ]);
      if (RADNEX_CTX.role === 'daf') return radnexPick([
        name + ', vous êtes le dernier regard avant l\'exécution. Votre approbation déclenche directement le paiement. Pas de pression, hein !',
        'Dernière étape, ' + name + ' ! Votre visa et l\'engagement passe en exécution. Vérifiez, signez, et c\'est parti.'
      ]);
      return radnexPick([
        'Le circuit est simple : Hiérarchique → Comptable → DAF. Chaque valideur peut approuver, renvoyer ou rejeter. Le renvoi, c\'est souvent le choix le plus constructif.',
        'La validation suit un chemin : Hiérarchique → Comptable → DAF. À chaque étape, on peut approuver, renvoyer ou rejeter. Pensez au renvoi comme à une pause, pas un échec !'
      ]);
    }
  },
  budget: {
    keywords: ['budget','prevision','budgetaire','depassement','execution','credit'],
    answer: function() {
      var name = RADNEX_CTX.firstName;
      return radnexPick([
        name + ', le budget c\'est votre boussole. Créez des prévisions, suivez les écarts entre prévu et réalisé, et surveillez les seuils d\'alerte avant que ça dérape.',
        'Le module Budget vous permet de planifier et de comparer, ' + name + '. Prévisions vs réalisations — c\'est comme ça qu\'on garde le cap.',
        name + ', un budget bien suivi, c\'est des surprises en moins. Créez vos prévisions, et gardez un œil sur le taux de consommation.'
      ]);
    }
  },
  comptabilite: {
    keywords: ['comptable','comptabilite','ecriture','journal','balance','syscohada','compte','bilan'],
    answer: function() {
      var name = RADNEX_CTX.firstName;
      return radnexPick([
        name + ', la comptabilité suit le plan SYSCOHADA. Les écritures sont générées automatiquement à partir des opérations validées — vous n\'avez qu\'à vérifier et valider.',
        'SYSCOHADA, journaux, balances... ' + name + ', la comptabilité est le langage de la transparence financière. Et bonne nouvelle : les écritures se génèrent toutes seules depuis les opérations.',
        name + ', le plan SYSCOHADA est votre repère. Les écritures se créent à partir des opérations validées, vous supervisez et contrôlez.'
      ]);
    }
  },
  decharge: {
    keywords: ['decharge','decharger','liberer','responsabilite','desengagement'],
    answer: function() {
      var name = RADNEX_CTX.firstName;
      return radnexPick([
        name + ', la décharge libère le responsable financier après l\'exécution d\'un ordre de mission. C\'est la dernière étape — une fois signée, le cycle est bouclé.',
        'La décharge, ' + name + ', c\'est la clôture propre d\'une mission. Le responsable est libéré de sa responsabilité financière. Cycle complet !'
      ]);
    }
  },
  rapport: {
    keywords: ['rapport','reporting','stat','statistique','export','pdf','excel','tableau'],
    answer: function() {
      var name = RADNEX_CTX.firstName;
      return radnexPick([
        name + ', le Reporting vous donne bilans, comptes de résultat et états de flux. Le tout exportable en PDF ou Excel — parfait pour les réunions de direction !',
        'Besoin d\'un rapport, ' + name + ' ? Bilans, états de flux, tableaux de bord... Tout est exportable en PDF ou Excel. Idéal pour pitcher au directeur.',
        name + ', le module Reporting est votre allié pour les présentations. Données financières complètes, exports propres. Vos réunions vont être impressionnantes !'
      ]);
    }
  },
  aide: {
    keywords: ['aide','help','comment','guide','tuto','tutorial','expliquer','explique'],
    answer: function() {
      var name = RADNEX_CTX.firstName;
      return radnexPick([
        name + ', je suis là pour ça ! Posez-moi une question, ou dites <b>"Hey Radnex"</b> pour m\'appeler vocalement. Je connais le système par cœur.',
        'C\'est mon métier, ' + name + ' ! Demandez-moi ce que vous voulez, ou utilisez la commande vocale <b>"Hey Radnex"</b>. Je vous guide.',
        name + ', pas de panique ! Je connais chaque recoin de BrenFinance. Posez votre question ou dites <b>"Hey Radnex"</b> et on avance ensemble.'
      ]);
    }
  },
  radnex: {
    keywords: ['radnex','qui es tu','qui es-tu','ton nom','presentation','presente','bonjour','salut','hello','hey','coucou'],
    answer: function() {
      var m = radnexMemory();
      var visits = m.visits + 1;
      var name = RADNEX_CTX.firstName;
      var svc = RADNEX_CTX.service ? ' au service ' + RADNEX_CTX.service : '';
      if (visits === 1) return radnexPick([
        'Enchanté <b>' + name + '</b> ! Je suis <b>Radnex</b>, votre assistant financier. Vous êtes ' + RADNEX_CTX.roleLabel + svc + '. Je suis là pour vous faciliter la vie sur BrenFinance !',
        '<b>' + name + '</b>, ravi de vous rencontrer ! Moi c\'est <b>Radnex</b>, votre compagnon financier. ' + RADNEX_CTX.roleLabel + svc + ' — je me souviendrai de tout ça pour mieux vous aider.'
      ]);
      return radnexPick([
        'Re-salut <b>' + name + '</b> ! On se connaît un peu maintenant. Qu\'est-ce que je peux faire pour vous ?',
        'De retour, <b>' + name + '</b> ? Content de vous retrouver ! On continue où on en était ?',
        '<b>' + name + '</b> ! Toujours au poste. Allez, on reprend — de quoi avez-vous besoin ?'
      ]);
    }
  },
  merci: {
    keywords: ['merci','thanks','merci beaucoup','super','genial','parfait','excellent','bien','ok','d\'accord'],
    answer: function() {
      var name = RADNEX_CTX.firstName;
      return radnexPick([
        'Avec plaisir, ' + name + ' ! Autre chose ?',
        'De rien, ' + name + ' ! Je suis là pour ça.',
        'Tout le plaisir est pour moi, ' + name + ' ! Vous avez besoin d\'autre chose ?',
        'C\'est nothing, ' + name + ' ! N\'hésitez pas si vous avez besoin.',
        name + ', content d\'avoir pu aider ! Autre question ?'
      ]);
    }
  },
  oui: {
    keywords: ['oui','ouais','volontiers','je veux','montre','affiche','aller','vas y','d\'accord'],
    answer: function() {
      var name = RADNEX_CTX.firstName;
      return radnexPick([
        'Super, ' + name + ' ! Cliquez sur l\'action rapide qui correspond ci-dessus, ou dites-moi plus précisément ce qu\'il vous faut.',
        'On y va, ' + name + ' ! Utilisez les raccourcis ci-dessus, ou précisez votre demande et je vous guide.',
        'Parfait, ' + name + ' ! Les actions rapides sont juste au-dessus. Sinon, dites-moi exactement ce que vous cherchez.'
      ]);
    }
  },
  frustration: {
    keywords: ['probleme','bug','erreur','marche pas','ne marche','bloque','perdu','comprends pas','galere','difficile','impossible','relou','nul'],
    answer: function() {
      var name = RADNEX_CTX.firstName;
      return radnexPick([
        name + ', je sens la frustration... Décrivez-moi ce qui bloque, on va trouver une solution ensemble.',
        'Pas de panique, ' + name + '. Dites-moi ce qui se passe, on va décoincer ça.',
        name + ', respirez ! Je suis là. Expliquez-moi le problème et on va le résoudre étape par étape.',
        'Ça arrive à tout le monde, ' + name + '. Racontez-moi ce qui ne fonctionne pas et on voit ça ensemble.'
      ]);
    }
  }
};

function radnexFindAnswer(question) {
  var q = question.toLowerCase();
  for (var key in radnexKnowledge) {
    var entry = radnexKnowledge[key];
    for (var i = 0; i < entry.keywords.length; i++) {
      if (q.indexOf(entry.keywords[i]) !== -1) return entry.answer();
    }
  }
  var name = RADNEX_CTX.firstName;
  return radnexPick([
    name + ', je ne suis pas sûr de comprendre. Essayez de me demander quelque chose comme :<br>• "Comment créer un engagement ?"<br>• "Comment valider une mission ?"<br>• "C\'est quoi la caisse ?"<br><br>Ou dites <b>"Hey Radnex"</b> pour m\'appeler vocalement !',
    'Hmm, ' + name + ', je ne trouve pas de réponse à ça. Reformulez ou essayez :<br>• "Aide-moi avec un engagement"<br>• "Comment marche la validation ?"<br>• "Montre-moi la caisse"<br><br>Et n\'oubliez pas : <b>"Hey Radnex"</b> pour la voix !',
    name + ', je ne suis pas tombé sur la bonne réponse. Pouvez-vous reformuler ? Ou essayez :<br>• "Créer un engagement"<br>• "Valider une mission"<br>• "La caisse"<br><br>Vous pouvez aussi m\'appeler avec <b>"Hey Radnex"</b> !'
  ]);
}

/* ─── Suggestions — mix of task and conversational ─── */
function radnexGetSuggestions() {
  var taskSuggestions = {
    demandeur: ['Créer un engagement', 'Nouvel ordre de mission', 'Où en sont mes demandes ?'],
    valideur_n1: ['Engagements à valider', 'Missions à valider', 'Comment renvoyer une demande ?'],
    daf: ['Engagements en attente DAF', 'Missions à approuver', 'Voir la trésorerie'],
    comptable: ['Saisie comptable', 'Engagements à valider', 'Validation comptable ?'],
    caissier: ['Solde de ma caisse', 'Missions à exécuter', 'Saisir une opération'],
    super_admin: ['Gérer les utilisateurs', 'Journal d\'audit', 'Configurer les référentiels']
  };
  var base = taskSuggestions[RADNEX_CTX.role] || ['Créer un engagement', 'Nouvel ordre de mission', 'Aide'];
  // Add a conversational suggestion randomly
  var convos = ['Comment tu vas, Radnex ?', 'Qu\'est-ce que tu sais faire ?', 'Raconte-moi une blague'];
  if (Math.random() < 0.3) base[2] = radnexPick(convos);
  return base;
}

function askRadnex(e) {
  e.preventDefault();
  var input = document.getElementById('ai-chat-input');
  var question = input.value.trim();
  if (!question) return;
  input.value = '';
  document.getElementById('ai-suggestions').innerHTML = '';

  radnexAddMessage('user', question.replace(/</g, '&lt;'));

  var container = document.getElementById('radnex-messages');
  var typing = document.createElement('div');
  typing.className = 'radnex-msg radnex-msg-radnex';
  typing.id = 'radnex-typing';
  typing.innerHTML = '<div class="radnex-msg-avatar"><div class="radnex-mini-avatar" style="background:#6366f1"><i class="ph-bold ph-sparkle"></i></div></div>' +
    '<div class="radnex-msg-bubble"><div class="radnex-msg-name">Radnex</div><div class="radnex-typing-dots"><span></span><span></span><span></span></div></div>';
  container.appendChild(typing);
  document.getElementById('ai-body').scrollTop = 99999;

  var answer = radnexFindAnswer(question);
  var m = radnexMemory();
  m.history.push({ role: 'user', text: question, time: Date.now() });
  m.history.push({ role: 'radnex', text: answer.replace(/<[^>]*>/g, ''), time: Date.now() });
  if (m.history.length > 30) m.history = m.history.slice(-30);
  radnexSave(m);

  // Variable delay to feel more natural (longer answers = longer "thinking")
  var delay = radnexTypingDelay() + (answer.length > 200 ? 400 : 0);
  setTimeout(function() {
    var t = document.getElementById('radnex-typing');
    if (t) t.remove();
    radnexAddMessage('radnex', answer);
    radnexShowSuggestions(radnexGetSuggestions());
  }, delay);
}

/* ─── Init + Welcome ─── */
function radnexInit() {
  var m = radnexMemory();
  m.visits++;
  var isFirst = m.visits === 1;
  if (!m.seen[RADNEX_CTX.module]) m.seen[RADNEX_CTX.module] = 0;
  m.seen[RADNEX_CTX.module]++;
  radnexSave(m);

  var name = RADNEX_CTX.firstName;
  var service = RADNEX_CTX.service ? ' au service ' + RADNEX_CTX.service : '';
  var moduleLabel = RADNEX_CTX.moduleLabel;
  var hour = RADNEX_CTX.hour;

  if (isFirst) {
    var welcomes = [
      'Ravi de vous rencontrer, <b>' + name + '</b> ! Moi c\'est <b>Radnex</b> — votre assistant financier.<br><br>Vous êtes <b>' + RADNEX_CTX.roleLabel + '</b>' + service + ', et vous êtes sur <b>' + moduleLabel + '</b>.<br><br>Dites-moi ce dont vous avez besoin, je connais la maison par cœur !',
      '<b>' + name + '</b>, bienvenue ! Je suis <b>Radnex</b>, votre compagnon sur BrenFinance.<br><br>' + RADNEX_CTX.roleLabel + service + ' — c\'est noté ! Vous êtes sur <b>' + moduleLabel + '</b>.<br><br>Une question ? Un blocage ? Je suis là.'
    ];
    radnexAddMessage('radnex', radnexPick(welcomes), 'welcome');
  } else {
    var pagesVisited = Object.keys(m.seen).length;
    var totalInteractions = m.history.filter(function(h) { return h.role === 'user'; }).length;
    var returns = [
      'Re-bonjour <b>' + name + '</b> ! Content de vous retrouver.<br>On a déjà échangé <b>' + totalInteractions + '</b> fois ensemble. Vous êtes sur <b>' + moduleLabel + '</b> — on continue ?',
      '<b>' + name + '</b> ! Toujours dans le coin !<br>Vous avez exploré <b>' + pagesVisited + '</b> module' + (pagesVisited > 1 ? 's' : '') + '. Aujourd\'hui, c\'est <b>' + moduleLabel + '</b>. Qu\'est-ce que je peux faire pour vous ?',
      'Coucou <b>' + name + '</b> ! On se retrouve sur <b>' + moduleLabel + '</b>.<br>Je me souviens de nos <b>' + totalInteractions + '</b> échanges. Un nouveau sujet ou on reprend ?'
    ];
    radnexAddMessage('radnex', radnexPick(returns), 'welcome');
  }

  // Proactive — contextual nudge
  var pq = RADNEX_CTX.proactiveQ;
  if (pq && !m.asked[RADNEX_CTX.module + '_' + RADNEX_CTX.role]) {
    m.asked[RADNEX_CTX.module + '_' + RADNEX_CTX.role] = true;
    radnexSave(m);
    setTimeout(function() {
      radnexAddMessage('radnex', pq, 'proactive');
      radnexShowSuggestions(radnexGetSuggestions());
    }, 1500);
  } else {
    radnexShowSuggestions(radnexGetSuggestions());
  }
}

/* ─── Badge notification on idle (no auto-open) ─── */
var radnexIdleTimer = null;
function radnexStartIdleTimer() {
  clearTimeout(radnexIdleTimer);
  radnexIdleTimer = setTimeout(function() {
    var panel = document.getElementById('ai-panel');
    if (!panel.classList.contains('open')) {
      var fab = document.getElementById('ai-fab');
      var badge = fab.querySelector('.radnex-badge') || document.getElementById('radnex-badge');
      if (badge) badge.style.display = 'flex';
    }
  }, 120000);
}
document.addEventListener('mousemove', function() { radnexStartIdleTimer(); });
document.addEventListener('keydown', function() { radnexStartIdleTimer(); });
radnexStartIdleTimer();

/* ─── Voice ─── */
var radnexVoiceActive = false;
var radnexRecognition = null;

function toggleRadnexVoice() {
  var btn = document.getElementById('radnex-voice-btn');
  if (radnexVoiceActive) {
    radnexVoiceActive = false;
    if (radnexRecognition) radnexRecognition.stop();
    btn.classList.remove('listening');
    return;
  }
  var SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
  if (!SpeechRecognition) { alert('La commande vocale ne marche pas ici. Essayez Chrome ou Edge.'); return; }

  radnexRecognition = new SpeechRecognition();
  radnexRecognition.lang = 'fr-FR';
  radnexRecognition.continuous = true;
  radnexRecognition.interimResults = false;

  radnexRecognition.onresult = function(event) {
    for (var i = event.resultIndex; i < event.results.length; i++) {
      var transcript = event.results[i][0].transcript.toLowerCase().trim();
      if (transcript.indexOf('radnex') !== -1) {
        var panel = document.getElementById('ai-panel');
        if (!panel.classList.contains('open')) toggleRadnex();
        var cmd = transcript.replace(/hey\s*radnex/i, '').replace(/radnex/i, '').trim();
        if (cmd.length > 2) {
          document.getElementById('ai-chat-input').value = cmd;
          askRadnex(new Event('submit'));
        } else {
          var name = RADNEX_CTX.firstName;
          radnexAddMessage('radnex', radnexPick([
            'Oui ' + name + ' ? Je vous écoute !',
            name + ' ? À vos ordres !',
            'Présent, ' + name + ' ! Qu\'est-ce qu\'il vous faut ?',
            'Ouais, ' + name + ' ? Dites-moi tout !'
          ]), 'proactive');
          radnexShowSuggestions(radnexGetSuggestions());
        }
        btn.classList.add('flash');
        setTimeout(function(){ btn.classList.remove('flash'); }, 600);
      }
    }
  };
  radnexRecognition.onerror = function(e) { if (e.error !== 'no-speech' && e.error !== 'aborted') console.warn('Radnex Voice:', e.error); };
  radnexRecognition.onend = function() { if (radnexVoiceActive) { try { radnexRecognition.start(); } catch(e) {} } };

  try { radnexRecognition.start(); radnexVoiceActive = true; btn.classList.add('listening'); } catch(e) { console.warn('Radnex Voice start error:', e); }
}

/* ─── Draggable FAB ─── */
(function() {
  var fab = document.getElementById('ai-fab');
  if (!fab) return;
  var dragging = false, moved = false;
  var startX, startY, fabX, fabY;

  function onDown(e) {
    var ev = e.touches ? e.touches[0] : e;
    dragging = true; moved = false;
    startX = ev.clientX; startY = ev.clientY;
    var rect = fab.getBoundingClientRect();
    fabX = rect.left; fabY = rect.top;
    fab.classList.add('dragging');
    e.preventDefault();
  }
  function onMove(e) {
    if (!dragging) return;
    var ev = e.touches ? e.touches[0] : e;
    var dx = ev.clientX - startX, dy = ev.clientY - startY;
    if (Math.abs(dx) > 3 || Math.abs(dy) > 3) moved = true;
    var newX = fabX + dx, newY = fabY + dy;
    var maxX = window.innerWidth - fab.offsetWidth;
    var maxY = window.innerHeight - fab.offsetHeight;
    newX = Math.max(0, Math.min(newX, maxX));
    newY = Math.max(0, Math.min(newY, maxY));
    fab.style.left = newX + 'px';
    fab.style.top = newY + 'px';
    fab.style.right = 'auto';
    fab.style.bottom = 'auto';
  }
  function onUp() {
    if (!dragging) return;
    dragging = false;
    fab.classList.remove('dragging');
    var rect = fab.getBoundingClientRect();
    var midX = rect.left + rect.width / 2;
    var snapX = midX < window.innerWidth / 2 ? 16 : window.innerWidth - rect.width - 16;
    fab.style.left = snapX + 'px';
    fab.style.top = rect.top + 'px';
    fab.style.right = 'auto';
    fab.style.bottom = 'auto';
  }
  fab.addEventListener('mousedown', onDown);
  document.addEventListener('mousemove', onMove);
  document.addEventListener('mouseup', onUp);
  fab.addEventListener('touchstart', onDown, {passive: false});
  document.addEventListener('touchmove', onMove, {passive: false});
  document.addEventListener('touchend', onUp);
  var origClick = fab.onclick;
  fab.onclick = null;
  fab.addEventListener('click', function(e) {
    if (moved) { e.preventDefault(); e.stopPropagation(); return; }
    toggleRadnex();
  });
})();

/* ─── First-open hook ─── */
var radnexInitialized = false;
var origToggle = toggleRadnex;
toggleRadnex = function() {
  origToggle();
  if (!radnexInitialized) { radnexInitialized = true; radnexInit(); }
};
</script>