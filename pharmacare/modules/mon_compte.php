<?php
declare(strict_types=1);
/**
 * Mon compte — auto-service pour l'utilisateur connecté.
 *
 * Permet à CHACUN de préparer sa propre récupération de mot de passe (sans
 * intervention de l'admin) :
 *   - définir une question secrète + réponse (réponse hachée bcrypt) ;
 *   - générer un code de récupération à usage unique (affiché une seule fois) ;
 *   - changer son mot de passe (mot de passe actuel requis).
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../config/settings.php';
requireLogin();
ensureResetColumns();
$db    = getDB();
$me    = currentUser();

// Menu auto-seedé (section Principal)
try {
    $row = $db->query("SELECT COUNT(*) FROM menus WHERE code = 'mon_compte'")->fetchColumn();
    if (!$row) {
        $max = (int)$db->query("SELECT COALESCE(MAX(position), 0) FROM menus")->fetchColumn();
        $db->exec("UPDATE menus SET position = position + 1 WHERE position >= 2");
        $db->prepare("INSERT INTO menus (code, libelle, actif, position) VALUES ('mon_compte', 'Mon compte', 1, 2)")
           ->execute();
    }
} catch (Throwable $e) { /* non bloquant */ }

$meFull = $db->prepare("SELECT id, login, question_secrete, reponse_secrete, code_recuperation FROM utilisateurs WHERE id = ?");
$meFull->execute([$me['id']]);
$meR = $meFull->fetch();

$aQuestion = !empty($meR['question_secrete']);
$aCode     = !empty($meR['code_recuperation']);
$nouveauCode = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '')) {
    verifyCsrf();
    $act = $_POST['action'];

    // ── Modifier ses informations de profil ──
    if ($act === 'profil') {
        $prenom = trim((string)($_POST['prenom'] ?? ''));
        $nom    = trim((string)($_POST['nom'] ?? ''));
        $email  = trim((string)($_POST['email'] ?? ''));
        if ($prenom === '' || $nom === '') {
            flash('Le prénom et le nom sont requis.', 'error');
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('Adresse email invalide.', 'error');
        } else {
            $stDup = $db->prepare("SELECT COUNT(*) FROM utilisateurs WHERE email = ? AND id <> ?");
            $stDup->execute([$email, $me['id']]);
            if ((int)$stDup->fetchColumn() > 0) {
                flash('Cet email est déjà utilisé par un autre compte.', 'error');
            } else {
                $db->prepare("UPDATE utilisateurs SET prenom = ?, nom = ?, email = ? WHERE id = ?")
                   ->execute([$prenom, $nom, $email, $me['id']]);
                // Rafraîchit la session (nom affiché dans le topbar, etc.)
                $_SESSION['user_prenom'] = $prenom;
                $_SESSION['user_nom']    = $nom;
                $_SESSION['user_email']  = $email;
                auditLog('profil', 'Informations de profil mises à jour');
                flash('Vos informations ont été mises à jour.', 'success');
            }
        }
    }

    // ── Définir / remplacer la question secrète ──
    if ($act === 'question') {
        $q = trim((string)($_POST['question'] ?? ''));
        $r = reset_normalize((string)($_POST['reponse'] ?? ''));
        if (mb_strlen($q) < 5 || mb_strlen($q) > 200) {
            flash('La question doit faire entre 5 et 200 caractères.', 'error');
        } elseif (mb_strlen($r) < 3) {
            flash('La réponse doit contenir au moins 3 caractères.', 'error');
        } else {
            $db->prepare("UPDATE utilisateurs SET question_secrete = ?, reponse_secrete = ? WHERE id = ?")
               ->execute([$q, password_hash($r, PASSWORD_DEFAULT), $me['id']]);
            auditLog('mdp.compte', 'Question secrète définie (Mon compte)');
            flash('Question secrète enregistrée. Elle servira à réinitialiser votre mot de passe en cas d\'oubli.', 'success');
        }
    }

    // ── Générer / régénérer le code de récupération ──
    if ($act === 'code') {
        $code = strtoupper(bin2hex(random_bytes(8)));            // 16 caractères
        $db->prepare("UPDATE utilisateurs SET code_recuperation = ? WHERE id = ?")
           ->execute([password_hash(reset_normalize($code), PASSWORD_DEFAULT), $me['id']]);
        $nouveauCode = $code;                                     // affiché UNE seule fois
        auditLog('mdp.compte', 'Code de récupération (re)généré (Mon compte)');
    }

    // ── Changer son propre mot de passe ──
    if ($act === 'password') {
        $cur = (string)($_POST['current'] ?? '');
        $n1  = (string)($_POST['np1'] ?? '');
        $n2  = (string)($_POST['np2'] ?? '');
        $st  = $db->prepare("SELECT mot_de_passe FROM utilisateurs WHERE id = ?");
        $st->execute([$me['id']]);
        $hash = (string)$st->fetchColumn();
        if (!password_verify($cur, $hash)) {
            flash('Mot de passe actuel incorrect.', 'error');
        } elseif (strlen($n1) < 8) {
            flash('Le nouveau mot de passe doit contenir au moins 8 caractères.', 'error');
        } elseif ($n1 !== $n2) {
            flash('Les deux nouveaux mots de passe ne correspondent pas.', 'error');
        } else {
            $db->prepare("UPDATE utilisateurs SET mot_de_passe = ? WHERE id = ?")
               ->execute([password_hash($n1, PASSWORD_DEFAULT), $me['id']]);
            auditLog('mdp.change', 'Changement de mot de passe depuis Mon compte');
            flash('Mot de passe mis à jour.', 'success');
        }
    }
    header('Location: ' . url('mon_compte') . (!empty($nouveauCode) ? '?code=' . $nouveauCode : ''));
    exit;
}

// Relecture après redirection pour afficher l'état à jour
$meFull->execute([$me['id']]);
$meR = $meFull->fetch();
$aQuestion = !empty($meR['question_secrete']);
$aCode     = !empty($meR['code_recuperation']);
$codeAffiche = (isset($_GET['code']) && preg_match('/^[A-F0-9]{16}$/', $_GET['code'])) ? $_GET['code'] : null;

layout_head('Mon compte', 'mon_compte');
showFlash();
?>
<div class="page-header">
  <h1>👤 Mon compte</h1>
  <span class="text-sm" style="color:var(--text3);"><?= e($me['login']) ?></span>
</div>

<?php if ($codeAffiche): ?>
<div class="card" style="border-color:var(--teal2);">
  <div class="card-header"><div class="card-title">🔑 Votre code de récupération</div></div>
  <div class="card-pad">
    <div class="alert alert-success" style="position:static;display:block;">
      <strong>Notez ce code maintenant — il ne sera plus jamais affiché.</strong><br>
      Il permet de réinitialiser votre mot de passe depuis l'écran de connexion
      (« Mot de passe oublié ? ») sans intervention de l'administrateur.
    </div>
    <div style="font-family:'DM Mono',monospace;font-size:24px;letter-spacing:3px;text-align:center;padding:14px;background:var(--bg2);border-radius:10px;"><?= e($codeAffiche) ?></div>
    <p class="text-sm" style="color:var(--text3);margin-top:10px;">Conservez-le en lieu sûr (papier, gestionnaire de mots de passe…). Il est à usage unique : après utilisation, générez-en un nouveau depuis cette page.</p>
  </div>
</div>
<?php endif; ?>

<div class="cards-grid" style="display:grid;grid-template-columns:1fr;gap:16px;">
<div class="card">
  <div class="card-header">
    <div class="card-title">Mes informations</div>
    <span class="text-sm" style="color:var(--text3);">vos données personnelles — modifiables par vous seul</span>
  </div>
  <form method="POST" class="card-pad" style="display:grid;gap:10px;">
    <input type="hidden" name="csrf" value="<?= csrf() ?>">
    <input type="hidden" name="action" value="profil">
    <div style="display:grid;grid-template-columns:1fr 1fr 1.4fr;gap:10px;">
      <div class="form-group">
        <label>Prénom *</label>
        <input type="text" name="prenom" required maxlength="60" style="width:100%;" value="<?= e($meR['prenom'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label>Nom *</label>
        <input type="text" name="nom" required maxlength="60" style="width:100%;" value="<?= e($meR['nom'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label>Email *</label>
        <input type="email" name="email" required style="width:100%;" value="<?= e($meR['email'] ?? '') ?>">
      </div>
    </div>
    <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;">
      <button type="submit" class="btn btn-primary"><?= icon('save', 14) ?> Enregistrer mes informations</button>
      <span class="text-sm" style="color:var(--text3);">Identifiant de connexion : <strong><?= e($meR['login']) ?></strong> (non modifiable)</span>
    </div>
  </form>
</div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;align-items:start;">
  <div class="card">
    <div class="card-header">
      <div class="card-title">Question secrète</div>
      <span class="badge <?= $aQuestion ? 'badge-green' : 'badge-gray' ?>"><?= $aQuestion ? 'Configurée' : 'Non configurée' ?></span>
    </div>
    <form method="POST" class="card-pad" style="display:grid;gap:10px;">
      <input type="hidden" name="csrf" value="<?= csrf() ?>">
      <input type="hidden" name="action" value="question">
      <div class="form-group">
        <label>Question (affichée lors du « mot de passe oublié ») *</label>
        <input type="text" name="question" required minlength="5" maxlength="200" style="width:100%;"
               value="<?= e($aQuestion ? $meR['question_secrete'] : '') ?>"
               placeholder="Ex. : Quel est le nom de mon premier animal ?">
      </div>
      <div class="form-group">
        <label>Réponse secrète * <?= $aQuestion ? '(laisser vide pour conserver)' : '' ?></label>
        <input type="text" name="reponse" <?= $aQuestion ? '' : 'required' ?> minlength="3" style="width:100%;"
               placeholder="La casse et les espaces multiples sont ignorés">
      </div>
      <button type="submit" class="btn btn-primary"><?= icon('save', 14) ?> Enregistrer</button>
      <p class="text-sm" style="color:var(--text3);margin:0;">La réponse est stockée chiffrée (hachage bcrypt) — même l'administrateur ne peut pas la lire.</p>
    </form>
  </div>

  <div class="card">
    <div class="card-header">
      <div class="card-title">Code de récupération</div>
      <span class="badge <?= $aCode ? 'badge-green' : 'badge-gray' ?>"><?= $aCode ? 'Configuré' : 'Non configuré' ?></span>
    </div>
    <div class="card-pad">
      <p class="text-sm" style="color:var(--text2);line-height:1.6;">
        Un code à <strong>usage unique</strong> : entrez-le sur l'écran « Mot de passe oublié ? »
        pour définir un nouveau mot de passe. Il est remplacé à chaque génération.
      </p>
      <form method="POST">
        <input type="hidden" name="csrf" value="<?= csrf() ?>">
        <input type="hidden" name="action" value="code">
        <button type="submit" class="btn btn-primary" style="margin-top:10px;"
                onclick="return confirm('<?= $aCode ? 'Générer un NOUVEAU code ? L\u0027ancien sera invalidé.' : 'Générer un code de récupération ?' ?>')">
          <?= icon('key', 14) ?> <?= $aCode ? 'Régénérer le code' : 'Générer un code' ?>
        </button>
      </form>
      <?php if ($codeAffiche): ?>
      <p class="text-sm" style="color:var(--teal2);margin-top:10px;font-weight:600;">✓ Code généré — affiché ci-dessus, à copier maintenant.</p>
      <?php endif; ?>
    </div>
  </div>
</div>

<div class="card" style="max-width:620px;">
  <div class="card-header"><div class="card-title">Changer mon mot de passe</div></div>
  <form method="POST" class="card-pad" style="display:grid;gap:10px;">
    <input type="hidden" name="csrf" value="<?= csrf() ?>">
    <input type="hidden" name="action" value="password">
    <div class="form-group">
      <label>Mot de passe actuel *</label>
      <input type="password" name="current" required autocomplete="current-password" style="width:100%;">
    </div>
    <div class="form-group">
      <label>Nouveau mot de passe * (8 caractères min.)</label>
      <input type="password" name="np1" required minlength="8" autocomplete="new-password" style="width:100%;">
    </div>
    <div class="form-group">
      <label>Confirmer *</label>
      <input type="password" name="np2" required minlength="8" autocomplete="new-password" style="width:100%;">
    </div>
    <button type="submit" class="btn btn-primary"><?= icon('save', 14) ?> Mettre à jour</button>
  </form>
</div>
<?php layout_foot();