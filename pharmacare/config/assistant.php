<?php
/**
 * config/assistant.php
 *
 * Assistant PharmaCare intégré — moteur déterministe (SANS serveur IA / SANS LLM).
 *
 * Principe : l'assistant classifie l'intention de l'utilisateur par scoring de
 * mots-clés/synonymes FR, exécute un outil en LECTURE SEULE sur la BDD, et renvoie
 * une réponse template + données réelles + suggestions cliquables. Aucune inférence,
 * aucun appel réseau sortant, aucune dépendance externe. Fonctionne hors-ligne.
 *
 * Périmètre (validé par l'utilisateur) : Aide à l'utilisation + requêtes read-only
 * (rechercher un produit, consulter le stock/alertes, statistiques simples, orienter
 * vers le bon menu). AUCUNE mutation, AUCUNE donnée personnelle client.
 *
 * Sécurité :
 *  - Liste blanche codée en dur des outils (5 outils read-only).
 *  - Aucun SELECT n'accède à clients.nom/telephone, ventes.client_nom/telephone/client_id,
 *    utilisateurs.*. Les stats sont des agrégats SUM/COUNT sans projection identifiante.
 *  - Refus poli de toute intention de mutation → orientation vers le menu concerné.
 *  - Désactivable via le paramètre `assistant_active` (Paramètres).
 *
 * Auto-seeding (patch code-only) : assistant_ensure_schema() crée de façon idempotente
 * (INSERT IGNORE) la permission `assistant.utiliser`, son menu et les paramètres par défaut
 * au premier chargement — aucun script SQL manuel à appliquer lors d'une mise à jour prod.
 */

require_once __DIR__ . '/settings.php';

// ──────────────────────────────────────────────────────────────
// Base de connaissance d'aide par module (FR)
// ──────────────────────────────────────────────────────────────

/** @var array<string,array{titre:string,points:string[]}> */
$ASSISTANT_HELP = [
    'vente' => [
        'titre' => 'Point de Vente (POS)',
        'points' => [
            'Scannez ou tapez le nom/code du médicament dans la barre de recherche pour l\'ajouter au panier.',
            'Ajustez la quantité directement dans la ligne du panier.',
            'Pour une remise : saisissez un code de remise valide (validé par un approbateur) — le taux s\'applique sur le HT.',
            'Choisissez le mode de paiement (espèces, carte, chèque, assurance, crédit) puis validez.',
            'Le ticket s\'imprime selon le nombre de copies défini dans les Paramètres.',
        ],
    ],
    'caisse' => [
        'titre' => 'Caisses',
        'points' => [
            'Ouvrez une session de caisse en choisissant la pharmacie à débiter avant de pouvoir vendre.',
            'La colonne « Pharmacie » indique la pharmacie liée à chaque caisse ouverte.',
            'Pour clôturer : depuis le dashboard des caisses, action « Clôturer » sur la session ouverte — le fond et les ventes sont récapitulés.',
            'Une seule session ouverte par caissier à la fois.',
        ],
    ],
    'produits' => [
        'titre' => 'Médicaments',
        'points' => [
            'Recherchez par nom ou référence dans la barre de recherche (filtre en direct).',
            'Pour ajouter un médicament : bouton « Nouveau » — renseignez nom, référence, prix d\'achat/vente, catégorie, seuil d\'alerte.',
            'L\'import CSV ravitaille le MAGASIN (stock_magasin) ; les ventes, elles, prélèvent le stock de la pharmacie.',
            'Archiver un médicament le désactive sans supprimer son historique.',
        ],
    ],
    'stock' => [
        'titre' => 'Stock',
        'points' => [
            'Le stock affiché est celui de votre pharmacie (table produit_pharmacie).',
            'Les badges « Stock bas » / « Rupture » signalent les produits sous le seuil d\'alerte.',
            'Un ajustement de stock est tracé dans les mouvements (audit).',
        ],
    ],
    'magasin' => [
        'titre' => 'Magasin',
        'points' => [
            'Le magasin central (stock_magasin) est le réservoir de réceptions fournisseurs.',
            'Un transfert magasin → pharmacie approvisionne une pharmacie précise.',
            'L\'import CSV est un ravitaillement du MAGASIN, jamais du stock des pharmacies.',
        ],
    ],
    'commandes' => [
        'titre' => 'Commandes',
        'points' => [
            'Créez une commande fournisseur pour préparer une réception de stock.',
            'À la réception, validez les quantités reçues — le stock magasin est mis à jour.',
        ],
    ],
    'clients' => [
        'titre' => 'Clients',
        'points' => [
            'La fiche client (option fidélité) regroupe l\'historique d\'achats et les règlements.',
            'Pour enregistrer un règlement client, ouvrez la fiche puis « Enregistrer un paiement ».',
        ],
    ],
    'comptabilite' => [
        'titre' => 'Comptabilité OHADA',
        'points' => [
            'Les ventes génèrent automatiquement les écritures (compte 7119 — vente de marchandises).',
            'La saisie manuelle d\'écritures est réservée aux utilisateurs avec la permission comptabilite.saisie.',
            'Le plan comptable OHADA est gérable depuis l\'onglet Plan comptable.',
        ],
    ],
    'rapports' => [
        'titre' => 'Rapports',
        'points' => [
            'Générez les rapports ventes, stock, attendance (RH) selon la période choisie.',
            'Les rapports sont filtrables par date et par mode de paiement.',
        ],
    ],
    'parametres' => [
        'titre' => 'Paramètres',
        'points' => [
            'Configurez le nom de la pharmacie, logo, adresse, NIF, taux de TVA, devise.',
            'Le nombre de copies par impression de ticket se règle ici.',
            'Activez ou désactivez l\'assistant intégré depuis cet écran.',
        ],
    ],
];

// FAQ générique (questions transversales)
/** @var array<int,array{q:string[],a:string}> */
$ASSISTANT_FAQ = [
    [
        'q' => ['comment', 'creer', 'vente', 'vendre', 'enregistrer', 'vente'],
        'a' => "Pour enregistrer une vente : ouvrez le Point de Vente, ajoutez les articles au panier, choisissez le mode de paiement puis validez. Le ticket s'imprime automatiquement.",
    ],
    [
        'q' => ['comment', 'cloturer', 'caisse', 'fermer', 'caisse'],
        'a' => "Pour clôturer une caisse : allez sur Caisses, puis action « Clôturer » sur votre session ouverte. Le fond de caisse et les ventes sont récapitulés.",
    ],
    [
        'q' => ['comment', 'inventaire', 'stock', 'ajuster', 'inventaire'],
        'a' => "Pour un inventaire : ouvrez Stock, identifiez les écarts avec le stock réel, puis utilisez l'action d'ajustement (tracé dans les mouvements).",
    ],
    [
        'q' => ['comment', 'importer', 'articles', 'csv', 'import'],
        'a' => "Pour importer des articles : préparez un CSV (nom, référence, prix...) et utilisez l'import — il ravitaille le MAGASIN (stock_magasin), pas le stock des pharmacies.",
    ],
    [
        'q' => ['comment', 'commande', 'commander', 'fournisseur'],
        'a' => "Pour passer une commande : ouvrez Commandes, « Nouvelle commande », sélectionnez le fournisseur et les produits, puis validez.",
    ],
];

// ──────────────────────────────────────────────────────────────
// Auto-seeding (idempotent, patch code-only)
// ──────────────────────────────────────────────────────────────

/**
 * Crée, de façon idempotente, la permission `assistant.utiliser`, son menu et les
 * paramètres par défaut. Exécuté une seule fois (garde via `assistant_seeded`).
 */
function assistant_ensure_schema(): void
{
    if (getParam('assistant_seeded', '0') === '1') {
        return;
    }
    $db = getDB();
    try {
        // Permission
        $db->exec("INSERT IGNORE INTO permissions (code, libelle, module) "
            . "VALUES ('assistant.utiliser', 'Utiliser l''assistant intégré', 'assistant')");
        $permId = (int)$db->query("SELECT id FROM permissions WHERE code='assistant.utiliser'")->fetchColumn();

        // Grant à tous les rôles existants (l'assistant est une aide universelle)
        if ($permId > 0) {
            $db->exec("INSERT IGNORE INTO role_permissions (role_id, permission_id) "
                . "SELECT r.id, $permId FROM roles r WHERE r.id NOT IN "
                . "(SELECT role_id FROM role_permissions WHERE permission_id=$permId)");
        }

        // Menu (pour menuActif('assistant') si une page pleine est un jour rendue)
        $db->exec("INSERT IGNORE INTO menus (code, libelle, actif, position) "
            . "VALUES ('assistant', 'Assistant', 1, 23)");

        // Paramètres par défaut
        $db->exec("INSERT IGNORE INTO parametres (cle, valeur) VALUES ('assistant_active', '1')");
    } catch (Throwable $ex) {
        // Ne jamais casser une page à cause du seed ; on retentera au prochain chargement.
        if (defined('IS_PROD') && IS_PROD) {
            error_log('assistant_ensure_schema: ' . $ex->getMessage());
        }
        return;
    }
    // Upsert du marqueur (inline, comme le reste du projet — pas de setParam())
    $db->exec("INSERT INTO parametres (cle, valeur) VALUES ('assistant_seeded','1') "
        . "ON DUPLICATE KEY UPDATE valeur='1'");
    paramCacheClear();
}

/** L'assistant est-il actif pour l'utilisateur courant ? */
function assistantActive(): bool
{
    return getParam('assistant_active', '1') === '1' && hasPermission('assistant.utiliser');
}

// ──────────────────────────────────────────────────────────────
// Widget (FAB + slide-over) — rendu par layout_foot()
// ──────────────────────────────────────────────────────────────

function assistant_widget(): string
{
    // Données pour le panneau : base de connaissance sérialisée pour quick-replies
    $helpTopics = [];
    foreach ($GLOBALS['ASSISTANT_HELP'] as $key => $h) {
        $helpTopics[$key] = $h['titre'];
    }
    $topicsJson = json_encode($helpTopics, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP);
    $brand = defined('APP_NAME') ? APP_NAME : 'PharmaCare';
    $brandJson = json_encode($brand, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP);

    // Message d'accueil + suggestions adaptés au rôle de l'utilisateur
    $c = assistant_capabilities();
    $can = [];
    if ($c['produits'] || $c['vente'] || $c['stock']) $can[] = 'trouver un médicament';
    if ($c['stock']) $can[] = 'consulter le stock';
    if ($c['compta'] || $c['admin'] || $c['stock']) $can[] = 'voir le chiffre du jour';
    elseif ($c['vente']) $can[] = 'voir vos ventes du jour';
    $can[] = 'vous guider dans les menus';
    $welcome = "Bonjour 👋 Je suis l'assistant intégré de PharmaCare. Je peux vous aider à "
        . implode(', ', $can) . ". Choisissez une suggestion ci-dessous ou posez votre question.";

    $icons = [
        'Chercher un médicament' => '🔍', 'Stock en rupture' => '⚠️',
        'Chiffre du jour' => '💰', 'Mes ventes du jour' => '💰',
        'Comment clôturer une caisse' => '🧾', 'Comment faire une vente' => '🛒',
        'Comment faire un réassort' => '📦',
    ];
    $chipsHtml = '';
    foreach (assistant_quick_replies() as $qr) {
        $ic = $icons[$qr] ?? '💬';
        $qj = htmlspecialchars($qr, ENT_QUOTES, 'UTF-8');
        $chipsHtml .= '<button class="assistant-chip" data-q="' . $qj . '">' . $ic . ' ' . $qj . '</button>';
    }

    return <<<HTML
<style>
/* Assistant PharmaCare — FAB + slide-over (styles isolés, pas de fichier CSS dédié) */
.assistant-fab{position:fixed;right:22px;bottom:22px;width:54px;height:54px;border-radius:50%;
  background:linear-gradient(135deg,#0d9488,#06d6a0);color:#fff;display:flex;align-items:center;
  justify-content:center;cursor:pointer;box-shadow:0 8px 24px rgba(13,148,136,.45);
  border:none;z-index:9000;transition:transform .15s ease,box-shadow .15s ease;}
.assistant-fab:hover{transform:translateY(-2px) scale(1.05);box-shadow:0 12px 30px rgba(13,148,136,.55);}
.assistant-fab:active{transform:scale(.97);}
.assistant-panel{position:fixed;top:0;right:0;height:100vh;width:380px;max-width:92vw;
  background:#0d1220;color:#e2e8f0;box-shadow:-12px 0 40px rgba(0,0,0,.45);
  display:flex;flex-direction:column;z-index:9001;transform:translateX(110%);
  transition:transform .25s cubic-bezier(.4,0,.2,1);border-left:1px solid #1e293b;}
.assistant-panel.open{transform:translateX(0);}
.assistant-head{display:flex;align-items:center;justify-content:space-between;padding:14px 16px;
  border-bottom:1px solid #1e293b;background:#080c15;}
.assistant-title{display:flex;align-items:center;gap:8px;font-weight:600;font-size:15px;color:#fff;}
.assistant-title svg{color:#06d6a0;}
.assistant-brand{color:#06d6a0;font-weight:700;}
.assistant-close{background:none;border:none;color:#94a3b8;font-size:18px;cursor:pointer;
  width:30px;height:30px;border-radius:6px;display:flex;align-items:center;justify-content:center;}
.assistant-close:hover{background:#1e293b;color:#e2e8f0;}
.assistant-body{flex:1;overflow-y:auto;padding:16px;display:flex;flex-direction:column;gap:10px;}
.assistant-bubble{max-width:88%;padding:10px 13px;border-radius:12px;font-size:13.5px;line-height:1.5;white-space:normal;word-wrap:break-word;}
.assistant-bubble-bot{background:#172033;color:#e2e8f0;border:1px solid #233047;align-self:flex-start;border-bottom-left-radius:3px;}
.assistant-bubble-user{background:linear-gradient(135deg,#0d9488,#06d6a0);color:#042f2e;align-self:flex-end;border-bottom-right-radius:3px;font-weight:500;}
.assistant-quick{display:flex;flex-wrap:wrap;gap:7px;padding:0 16px 8px;}
.assistant-chip{background:#172033;border:1px solid #2a3a52;color:#cbd5e1;padding:6px 11px;border-radius:16px;
  font-size:12.5px;cursor:pointer;transition:all .15s;}
.assistant-chip:hover{background:#1e293b;border-color:#0d9488;color:#fff;}
.assistant-links{display:flex;flex-wrap:wrap;gap:7px;padding:0 0 2px;}
.assistant-link{background:#0d9488;color:#042f2e;text-decoration:none;padding:6px 12px;border-radius:8px;
  font-size:12.5px;font-weight:600;display:inline-flex;align-items:center;gap:5px;}
.assistant-link:hover{background:#06d6a0;}
.assistant-input{display:flex;gap:8px;padding:12px 16px;border-top:1px solid #1e293b;background:#080c15;}
.assistant-input input{flex:1;background:#172033;border:1px solid #2a3a52;color:#e2e8f0;
  border-radius:9px;padding:10px 12px;font-size:13.5px;outline:none;}
.assistant-input input:focus{border-color:#0d9488;}
.assistant-send{background:#0d9488;border:none;color:#fff;width:42px;border-radius:9px;cursor:pointer;
  display:flex;align-items:center;justify-content:center;flex-shrink:0;}
.assistant-send:hover{background:#06d6a0;}
.assistant-typing{display:flex;gap:4px;align-items:center;}
.assistant-dot{width:7px;height:7px;border-radius:50%;background:#06d6a0;opacity:.5;animation:assistantbounce 1.2s infinite ease-in-out;}
.assistant-dot:nth-child(2){animation-delay:.15s;} .assistant-dot:nth-child(3){animation-delay:.3s;}
@keyframes assistantbounce{0%,80%,100%{transform:scale(.6);opacity:.4;}40%{transform:scale(1);opacity:1;}}
@media (max-width:480px){.assistant-panel{width:100vw;}.assistant-fab{right:16px;bottom:16px;}}
</style>
<!-- Assistant PharmaCare (déterministe, sans serveur IA) -->
<div id="assistant-fab" class="assistant-fab" title="Assistant PharmaCare" role="button" tabindex="0" aria-label="Ouvrir l'assistant">
  <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2a3 3 0 0 0-3 3v1a3 3 0 0 0-3 3v1a3 3 0 0 0 0 6 3 3 0 0 0 3 3 3 3 0 0 0 6 0 3 3 0 0 0 3-3 3 3 0 0 0 0-6V9a3 3 0 0 0-3-3V5a3 3 0 0 0-3-3Z"/><path d="M8 14h.01"/><path d="M16 14h.01"/><path d="M9 18h6"/></svg>
</div>
<aside id="assistant-panel" class="assistant-panel" aria-hidden="true">
  <div class="assistant-head">
    <div class="assistant-title">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2a3 3 0 0 0-3 3v1a3 3 0 0 0-3 3v1a3 3 0 0 0 0 6 3 3 0 0 0 3 3 3 3 0 0 0 6 0 3 3 0 0 0 3-3 3 3 0 0 0 0-6V9a3 3 0 0 0-3-3V5a3 3 0 0 0-3-3Z"/></svg>
      <span>Assistant <span class="assistant-brand" id="assistant-brand"></span></span>
    </div>
    <button class="assistant-close" id="assistant-close" aria-label="Fermer l'assistant">✕</button>
  </div>
  <div id="assistant-body" class="assistant-body">
    <div class="assistant-bubble assistant-bubble-bot">
      {$welcome}
    </div>
    <div class="assistant-quick" id="assistant-quick">
{$chipsHtml}
    </div>
  </div>
  <form class="assistant-input" id="assistant-form" autocomplete="off">
    <input type="text" id="assistant-text" placeholder="Posez votre question…" maxlength="200" aria-label="Question à l'assistant">
    <button type="submit" class="assistant-send" aria-label="Envoyer">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
    </button>
  </form>
</aside>
<script>
window.PHARMA_ASSISTANT_TOPICS = {$topicsJson};
window.PHARMA_ASSISTANT_BRAND = {$brandJson};
document.getElementById('assistant-brand').textContent = window.PHARMA_ASSISTANT_BRAND;
</script>
HTML;
}

// ──────────────────────────────────────────────────────────────
// Détection d'intention (scoring de mots-clés/synonymes FR)
// ──────────────────────────────────────────────────────────────

/** @return array<string,string[]> */
function assistant_keyword_map(): array
{
    return [
        'search_product' => ['chercher', 'rechercher', 'trouver', 'ou est', 'ou est', 'lors de', 'paracetamol', 'produit', 'medicament', 'molecule', 'reference', 'code', 'avoir', 'avons', 'disponibilite', 'disponible', 'prix de', 'combien coute', 'coute combien', 'combien', 'quantite', 'quantité', 'nombre', 'reste', 'restant', 'en stock', 'on a', 'avons nous', 'avons-nous'],
        'stock_alerts'   => ['rupture', 'ruptures', 'stock bas', 'seuil', 'alerte', 'alertes', 'epuise', 'epuisé', 'rassurer', 'manque', 'penurie', 'faible', 'reassort'],
        'stats'          => ['chiffre', 'ca', 'vente', 'ventes', 'recette', 'recettes', 'total', 'combien de ventes', 'aujourd', 'jour', 'journee', 'statistique', 'statistiques', 'performance'],
        'navigate'       => ['aller', 'ouvrir', 'menu', 'page', 'acceder', 'acces', 'va a', 'va sur', 'afficher', 'montrer', 'voir le', 'affiche'],
        'help'           => ['comment', 'aide', 'aider', 'expliquer', 'explique', 'guide', 'tutoriel', 'que faire', 'comprendre', 'documentation', 'faq'],
        'greeting'       => ['bonjour', 'salut', 'bonsoir', 'hello', 'coucou', 'hey', 'merci', 'super', 'ok'],
    ];
}

/** Mots-clés signalant une intention de MUTATION → refus poli. */
function assistant_mutation_keywords(): array
{
    return ['supprimer', 'suppr', 'effacer', 'efface', 'supprime', 'modifier', 'modifie', 'change', 'changer',
        'rembourser', 'remboursement', 'ajuster', 'ajuste', 'importer', 'import', 'cloturer', 'cloture',
        'creer un utilisateur', 'ajouter un utilisateur', 'changer prix', 'changer le prix', 'modifier prix',
        'augmenter', 'baisser', 'vendre', 'faire une vente', 'encaisser', 'saisir', 'saisie', 'generer', 'genere'];
}

function assistant_intent(string $text): string
{
    $t = ' ' . strtolower(trim($text)) . ' ';
    if ($t === '  ') {
        return 'unknown';
    }
    // 1) Question d'aide (« comment… », « aide », « expliquer »…) en priorité :
    //    « comment clôturer une caisse » est une demande d'aide, pas une mutation.
    $helpMarkers = ['comment', 'aide', 'aider', 'expliquer', 'explique', 'guide', 'tutoriel', 'que faire', 'comprendre', 'documentation', 'faq', 'aide-moi'];
    foreach ($helpMarkers as $m) {
        if (strpos($t, $m) !== false) {
            return 'help';
        }
    }
    // 2) Détection mutation (refus poli) — seulement pour des demandes d'action,
    //    pas pour des questions « comment ».
    foreach (assistant_mutation_keywords() as $kw) {
        if (preg_match('/\b' . preg_quote($kw, '/') . '\b/u', $t)) {
            return 'mutation';
        }
    }
    $scores = [];
    // Tokenisation (mots) pour le matching des mots-clés d'un seul mot ;
    // évite les faux positifs par sous-chaîne (ex. « ca » dans « caisse », « jour » dans « bonjour »).
    $tokens = preg_split('/[^a-zà-ÿ0-9]+/u', $t, -1, PREG_SPLIT_NO_EMPTY);
    $tokenSet = array_flip($tokens);
    foreach (assistant_keyword_map() as $intent => $kws) {
        $scores[$intent] = 0;
        foreach ($kws as $kw) {
            if (strpos($kw, ' ') !== false) {
                // Expression multi-mots : matching par sous-chaîne (phrase)
                if (strpos($t, $kw) !== false) {
                    $scores[$intent]++;
                }
            } else {
                // Mot unique : matching par token exact
                if (isset($tokenSet[$kw])) {
                    $scores[$intent]++;
                }
            }
        }
    }
    arsort($scores);
    $best = array_key_first($scores);
    if (!$best || $scores[$best] === 0) {
        return 'unknown';
    }
    return $best;
}

// ──────────────────────────────────────────────────────────────
// Contexte pharmacie de l'utilisateur (session caisse ouverte, fallback 1)
// ──────────────────────────────────────────────────────────────

/** @return array{int,string} */
function assistant_user_pharmacie(): array
{
    $db = getDB();
    $uid = (int)currentUser()['id'];
    if ($uid > 0) {
        $st = $db->prepare("SELECT s.pharmacie_id FROM sessions_caisse s
                            WHERE s.caissier_id=? AND s.statut='ouverte' LIMIT 1");
        $st->execute([$uid]);
        $pid = (int)$st->fetchColumn();
        if ($pid > 0) {
            $nom = (string)$db->query("SELECT nom FROM pharmacies WHERE id=" . $pid)->fetchColumn();
            return [$pid, $nom];
        }
    }
    // Fallback pharmacie principale
    $pid = 1;
    $nom = (string)$db->query("SELECT nom FROM pharmacies WHERE id=1")->fetchColumn() ?: 'Pharmacie principale';
    return [$pid, $nom];
}

// ──────────────────────────────────────────────────────────────
// Capacités de l'utilisateur (déduites des permissions réelles, pas du libellé
// de rôle — robuste pour les 8 rôles). L'assistant agit selon ces capacités.
// ──────────────────────────────────────────────────────────────

function assistant_capabilities(): array
{
    return [
        'admin'       => hasPermission('utilisateurs.gerer'),
        'compta'      => hasPermission('comptabilite.voir'),
        'stock'       => hasPermission('stock.voir'),
        'produits'    => hasPermission('produits.voir'),
        'vente'       => hasPermission('vente.creer'),
        'caisse'      => hasPermission('caisse.ouvrir') || hasPermission('caisse.voir'),
        'clients'     => hasPermission('clients.voir'),
        'magasin'     => hasPermission('magasin.voir'),
        'commandes'   => hasPermission('commandes.voir'),
        'rapports'    => hasPermission('rapports.voir'),
        'ventes_hist' => hasPermission('ventes_hist.voir'),
        'dashboard'   => hasPermission('dashboard.voir'),
        'parametres'  => hasPermission('parametres.voir'),
    ];
}

/** Suggestions cliquables par défaut, adaptées au rôle de l'utilisateur. */
function assistant_quick_replies(): array
{
    $c = assistant_capabilities();
    $q = [];
    if ($c['produits'] || $c['vente'] || $c['stock']) $q[] = 'Chercher un médicament';
    if ($c['stock']) $q[] = 'Stock en rupture';
    if ($c['compta'] || $c['admin']) $q[] = 'Chiffre du jour';
    elseif ($c['vente']) $q[] = 'Mes ventes du jour';
    elseif ($c['stock']) $q[] = 'Chiffre du jour';
    if ($c['caisse']) $q[] = 'Comment clôturer une caisse';
    elseif ($c['vente']) $q[] = 'Comment faire une vente';
    if (count($q) < 4 && $c['stock']) $q[] = 'Comment faire un réassort';
    if (count($q) < 2) $q = ['Chercher un médicament', 'Comment faire une vente'];
    return array_values(array_slice($q, 0, 4));
}

/** Liens vers les menus accessibles à l'utilisateur (filtrés par permission). */
function assistant_accessible_menu_links(): array
{
    $c = assistant_capabilities();
    $links = [];
    if ($c['dashboard']) $links[] = ['label' => 'Tableau de bord', 'url' => url('dashboard')];
    if ($c['vente'])     $links[] = ['label' => 'Point de Vente', 'url' => url('vente')];
    if ($c['caisse'])    $links[] = ['label' => 'Caisses', 'url' => url('caisse')];
    if ($c['stock'])     $links[] = ['label' => 'Stock', 'url' => url('stock')];
    if ($c['produits'])  $links[] = ['label' => 'Médicaments', 'url' => url('produits')];
    if ($c['commandes']) $links[] = ['label' => 'Commandes', 'url' => url('commandes')];
    if ($c['magasin'])   $links[] = ['label' => 'Magasin', 'url' => url('magasin')];
    if ($c['clients'])   $links[] = ['label' => 'Clients', 'url' => url('clients')];
    if ($c['compta'])    $links[] = ['label' => 'Comptabilité', 'url' => url('comptabilite')];
    if ($c['rapports'])  $links[] = ['label' => 'Rapports', 'url' => url('rapports')];
    return $links;
}

// ──────────────────────────────────────────────────────────────
// Outils en LECTURE SEULE (liste blanche)
// ──────────────────────────────────────────────────────────────

/**
 * Recherche produits (LIKE sur nom/référence). Top 8. Aucune donnée personnelle.
 *
 * Périmètre adapté au rôle :
 *  - Administrateur (utilisateurs.gerer) : DÉTAIL COMPLET — stock magasin + ventilation
 *    par pharmacie (toutes les pharmacies), pour chaque produit trouvé.
 *  - Pharmacien (stock.voir) : nom + quantité dans SA pharmacie (session caisse).
 *  - Caissier (vente.creer, sans stock.voir) : nom + quantité dans SA pharmacie
 *    (session caisse) — il doit pouvoir répondre « avons-nous X et combien ? ».
 *    La quantité affichée est celle de la pharmacie correspondante uniquement
 *    (jamais la vue agrégée ni le magasin central), donc sans fuite business.
 *
 * @return array{reply:string,quick:string[],links:array<int,array{label:string,url:string}>}
 */
function tool_search_products(string $q): array
{
    // Accès : catalogue (produits.voir), vente au POS (vente.creer) ou gestion stock (stock.voir)
    if (!(hasPermission('produits.voir') || hasPermission('vente.creer') || hasPermission('stock.voir'))) {
        return ['reply' => "Vous n'avez pas accès au catalogue de médicaments. Adressez-vous à un pharmacien ou un administrateur.",
            'quick' => assistant_quick_replies(), 'links' => []];
    }
    $canStock = hasPermission('stock.voir');
    $isAdmin  = hasPermission('utilisateurs.gerer');
    $q = trim($q);
    if ($q === '') {
        return ['reply' => "Donnez-moi un nom ou une référence à rechercher, par exemple « chercher paracétamol ».",
            'quick' => ['Chercher amoxicilline', 'Chercher aspirine'], 'links' => []];
    }
    // Extraire le terme de recherche (on retire les mots-outils)
    $stop = ['chercher', 'rechercher', 'trouver', 'ou', 'est', 'le', 'la', 'les', 'un', 'une', 'de', 'du', 'du produit', 'produit', 'medicament', 'medicaments', 'voir', 'afficher', 'montre', 'moi'];
    $words = array_values(array_filter(explode(' ', $q), function ($w) use ($stop) {
        return $w !== '' && !in_array($w, $stop);
    }));
    $term = implode(' ', $words);
    if ($term === '') {
        $term = $q;
    }
    $db = getDB();
    $esc = str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $term);
    $like = "%$esc%";

    // Lien contextuel selon le droit d'accès au catalogue
    $catalogLink = hasPermission('produits.voir')
        ? ['label' => 'Voir dans le catalogue', 'url' => url('produits', ['q' => $term])]
        : (hasPermission('vente.creer') ? ['label' => 'Ouvrir le Point de Vente', 'url' => url('vente')] : null);

    // ── Administrateur : détail complet (magasin + ventilation toutes pharmacies) ──
    if ($isAdmin) {
        $st = $db->prepare("SELECT p.id, p.nom, p.reference, p.prix_vente, COALESCE(p.stock_magasin,0) AS stock_magasin
                            FROM produits p
                            WHERE p.actif=1 AND (p.nom LIKE ? ESCAPE '\\\\' OR p.reference LIKE ? ESCAPE '\\\\')
                            ORDER BY p.nom LIMIT 8");
        $st->execute([$like, $like]);
        $prods = $st->fetchAll();
        if (!$prods) {
            return ['reply' => "Aucun médicament trouvé pour « " . $term . " » (toutes pharmacies).",
                'quick' => ['Chercher un autre médicament', 'Stock en rupture'],
                'links' => $catalogLink ? [$catalogLink] : []];
        }
        // Ventilation par pharmacie pour les produits trouvés (une 2e requête bornée)
        $ids = array_map('intval', array_column($prods, 'id'));
        $in = implode(',', array_fill(0, count($ids), '?'));
        $st2 = $db->prepare("SELECT pp.produit_id, ph.nom AS pnom, COALESCE(pp.stock,0) AS pstock
                             FROM produit_pharmacie pp
                             JOIN pharmacies ph ON ph.id = pp.pharmacie_id
                             WHERE pp.produit_id IN ($in)
                             ORDER BY pp.produit_id, ph.nom");
        $st2->execute($ids);
        $byProd = [];
        foreach ($st2->fetchAll() as $r) {
            $byProd[(int)$r['produit_id']][] = $r['pnom'] . ' : ' . (int)$r['pstock'];
        }
        $lines = [];
        foreach ($prods as $p) {
            $detail = $byProd[(int)$p['id']] ?? [];
            $pharmTxt = $detail ? implode(' | ', $detail) : 'aucune pharmacie';
            $lines[] = "• " . $p['nom'] . " — réf. " . $p['reference']
                . " — " . fmtMoney((float)$p['prix_vente'])
                . "\n    Magasin : " . (int)$p['stock_magasin'] . " | Pharmacies : " . $pharmTxt;
        }
        $reply = "J'ai trouvé " . count($prods) . " médicament(s) pour « " . $term . " » (détail complet) :\n" . implode("\n", $lines);
        return ['reply' => $reply, 'quick' => ['Stock en rupture', 'Chiffre du jour'],
            'links' => $catalogLink ? [$catalogLink] : []];
    }

    // ── Pharmacien / Caissier : nom + quantité dans SA pharmacie correspondante ──
    [$pid, $pnom] = assistant_user_pharmacie();
    $st = $db->prepare("SELECT p.id, p.nom, p.reference, p.prix_vente, COALESCE(pp.stock,0) AS stock
                        FROM produits p
                        LEFT JOIN produit_pharmacie pp ON pp.produit_id=p.id AND pp.pharmacie_id=?
                        WHERE p.actif=1 AND (p.nom LIKE ? ESCAPE '\\\\' OR p.reference LIKE ? ESCAPE '\\\\')
                        ORDER BY p.nom LIMIT 8");
    $st->execute([$pid, $like, $like]);
    $rows = $st->fetchAll();

    if (!$rows) {
        return ['reply' => "Aucun médicament trouvé pour « " . $term . " » dans " . $pnom . ".",
            'quick' => ['Chercher un autre médicament'],
            'links' => $catalogLink ? [$catalogLink] : []];
    }
    $lines = [];
    foreach ($rows as $r) {
        // Quantité réelle dans la pharmacie correspondante (le caissier a besoin
        // de savoir « combien reste-t-il dans MA pharmacie » pour vendre).
        $stockTxt = (int)$r['stock'] > 0
            ? ((int)$r['stock'] . ' en stock')
            : 'rupture';
        $lines[] = "• " . $r['nom'] . " — réf. " . $r['reference']
            . " — " . fmtMoney((float)$r['prix_vente']) . " (" . $stockTxt . " @ " . $pnom . ")";
    }
    $reply = "J'ai trouvé " . count($rows) . " médicament(s) pour « " . $term . " » @ " . $pnom . " :\n" . implode("\n", $lines);
    $quick = $canStock ? ['Stock en rupture', 'Chiffre du jour'] : (hasPermission('vente.creer') ? ['Mes ventes du jour', 'Comment faire une vente'] : ['Chercher un autre médicament']);
    return ['reply' => $reply, 'quick' => $quick,
        'links' => $catalogLink ? [$catalogLink] : []];
}

/**
 * Produits sous le seuil d'alerte. Scoped à la pharmacie de l'utilisateur ;
 * vue agrégée (toutes pharmacies) pour un admin sans session de caisse ouverte.
 */
function tool_stock_alerts(): array
{
    if (!hasPermission('stock.voir')) {
        return ['reply' => "Vous n'avez pas accès aux alertes de stock. Adressez-vous à un pharmacien ou un administrateur.",
            'quick' => assistant_quick_replies(), 'links' => []];
    }
    $db = getDB();
    $isAdmin = hasPermission('utilisateurs.gerer');
    $uid = (int)currentUser()['id'];
    $sessPid = 0;
    if ($uid > 0) {
        $st = $db->prepare("SELECT pharmacie_id FROM sessions_caisse WHERE caissier_id=? AND statut='ouverte' LIMIT 1");
        $st->execute([$uid]);
        $sessPid = (int)$st->fetchColumn();
    }
    $stockLink = ['label' => 'Voir le stock', 'url' => url('stock', ['filtre' => 'alerte'])];

    // Admin sans session de caisse : vue agrégée toutes pharmacies confondues
    if ($isAdmin && $sessPid <= 0) {
        $rows = $db->query("SELECT p.nom, p.reference, ph.nom AS pnom, pp.stock, pp.seuil_alerte
                            FROM produit_pharmacie pp
                            JOIN produits p ON p.id=pp.produit_id
                            JOIN pharmacies ph ON ph.id=pp.pharmacie_id
                            WHERE p.actif=1 AND pp.stock <= pp.seuil_alerte
                            ORDER BY pp.stock ASC LIMIT 15")->fetchAll();
        if (!$rows) {
            return ['reply' => "Aucun produit sous le seuil d'alerte (toutes pharmacies) 👍",
                'quick' => ['Chiffre du jour', 'Chercher un médicament'], 'links' => [$stockLink]];
        }
        $lines = [];
        $ruptures = 0;
        foreach ($rows as $r) {
            $s = (int)$r['stock'];
            if ($s === 0) { $ruptures++; $lines[] = "• " . $r['nom'] . " — RUPTURE @ " . $r['pnom']; }
            else { $lines[] = "• " . $r['nom'] . " — " . $s . " restant(s) @ " . $r['pnom']; }
        }
        $head = $ruptures > 0 ? "$ruptures rupture(s) + " : "";
        $reply = $head . count($rows) . " produit(s) sous le seuil (toutes pharmacies, top 15) :\n" . implode("\n", $lines);
        return ['reply' => $reply, 'quick' => ['Chiffre du jour', 'Chercher un médicament'], 'links' => [$stockLink]];
    }

    // Vue par pharmacie (pharmacien / caissier avec session / admin avec session)
    [$pid, $pnom] = assistant_user_pharmacie();
    $st = $db->prepare("SELECT p.nom, p.reference, pp.stock, pp.seuil_alerte
                        FROM produit_pharmacie pp
                        JOIN produits p ON p.id=pp.produit_id
                        WHERE pp.pharmacie_id=? AND p.actif=1 AND pp.stock <= pp.seuil_alerte
                        ORDER BY pp.stock ASC LIMIT 15");
    $st->execute([$pid]);
    $rows = $st->fetchAll();

    if (!$rows) {
        return ['reply' => "Aucun produit sous le seuil d'alerte pour " . $pnom . " 👍",
            'quick' => ['Chiffre du jour', 'Chercher un médicament'], 'links' => [$stockLink]];
    }
    $lines = [];
    $ruptures = 0;
    foreach ($rows as $r) {
        $s = (int)$r['stock'];
        if ($s === 0) {
            $ruptures++;
            $lines[] = "• " . $r['nom'] . " — RUPTURE (seuil " . (int)$r['seuil_alerte'] . ")";
        } else {
            $lines[] = "• " . $r['nom'] . " — " . $s . " restant(s) (seuil " . (int)$r['seuil_alerte'] . ")";
        }
    }
    $head = $ruptures > 0 ? "$ruptures rupture(s) + " : "";
    $reply = $head . count($rows) . " produit(s) sous le seuil pour " . $pnom . " :\n" . implode("\n", $lines);
    return ['reply' => $reply, 'quick' => ['Comment faire un réassort', 'Chercher un médicament'],
        'links' => [$stockLink]];
}

/**
 * Statistiques du jour, adaptées au rôle :
 *  - admin/compta : vue globale (CA, nb ventes, valeur stock, ruptures).
 *  - pharmacien (stock.voir, pas compta) : CA global + valeur stock & ruptures de SA pharmacie.
 *  - caissier (vente.creer, pas stock ni compta) : SES ventes du jour uniquement (pas de données stock).
 * Agrégats sans colonnes identifiantes.
 */
function tool_stats(): array
{
    $db = getDB();
    $c = assistant_capabilities();
    $links = [];
    if ($c['dashboard']) $links[] = ['label' => 'Tableau de bord', 'url' => url('dashboard')];

    if ($c['compta'] || $c['admin']) {
        // Vue globale (administrateur / comptabilité / direction)
        $caJour   = (float)$db->query("SELECT COALESCE(SUM(total),0) FROM ventes WHERE created_at >= CURDATE()")->fetchColumn();
        $nbJour   = (int)$db->query("SELECT COUNT(*) FROM ventes WHERE created_at >= CURDATE()")->fetchColumn();
        $valStock = (float)$db->query("SELECT COALESCE(SUM(stock * prix_vente),0) FROM produits WHERE actif=1")->fetchColumn();
        $ruptures = (int)$db->query("SELECT COUNT(*) FROM produits WHERE stock=0 AND actif=1")->fetchColumn();
        $reply = "Aperçu global du jour :\n"
            . "• Chiffre d'affaires (aujourd'hui) : " . fmtMoney($caJour) . "\n"
            . "• Ventes aujourd'hui : " . fmtInt($nbJour) . "\n"
            . "• Valeur du stock (actif) : " . fmtMoney($valStock) . "\n"
            . "• Ruptures : " . fmtInt($ruptures);
        return ['reply' => $reply, 'quick' => ['Stock en rupture', 'Comment clôturer une caisse'], 'links' => $links];
    }

    if ($c['stock']) {
        // Pharmacien : CA global (visible via ventes_hist) + stock de sa pharmacie
        [$pid, $pnom] = assistant_user_pharmacie();
        $caJour = (float)$db->query("SELECT COALESCE(SUM(total),0) FROM ventes WHERE created_at >= CURDATE()")->fetchColumn();
        $nbJour = (int)$db->query("SELECT COUNT(*) FROM ventes WHERE created_at >= CURDATE()")->fetchColumn();
        $st = $db->prepare("SELECT COALESCE(SUM(pp.stock * p.prix_vente),0) AS val,
                                   SUM(CASE WHEN pp.stock = 0 THEN 1 ELSE 0 END) AS rup
                            FROM produit_pharmacie pp
                            JOIN produits p ON p.id = pp.produit_id
                            WHERE pp.pharmacie_id = ? AND p.actif = 1");
        $st->execute([$pid]);
        $row = $st->fetch();
        $reply = "Aperçu du jour — " . $pnom . " :\n"
            . "• Chiffre d'affaires (aujourd'hui) : " . fmtMoney($caJour) . "\n"
            . "• Ventes aujourd'hui : " . fmtInt($nbJour) . "\n"
            . "• Valeur du stock (pharmacie) : " . fmtMoney((float)$row['val']) . "\n"
            . "• Ruptures (pharmacie) : " . fmtInt((int)$row['rup']);
        return ['reply' => $reply, 'quick' => ['Stock en rupture', 'Chercher un médicament'], 'links' => $links];
    }

    // Caissier : ses propres ventes du jour (aucune donnée stock/business globale)
    $uid = (int)currentUser()['id'];
    $st = $db->prepare("SELECT COALESCE(SUM(total),0) AS ca, COUNT(*) AS nb
                        FROM ventes WHERE caissier_id = ? AND created_at >= CURDATE()");
    $st->execute([$uid]);
    $row = $st->fetch();
    $reply = "Vos ventes du jour :\n"
        . "• Ventes réalisées : " . fmtInt((int)$row['nb']) . "\n"
        . "• Votre chiffre d'affaires aujourd'hui : " . fmtMoney((float)$row['ca']);
    if ($c['ventes_hist']) $links[] = ['label' => 'Mon historique', 'url' => url('ventes_hist')];
    return ['reply' => $reply, 'quick' => ['Comment faire une vente', 'Comment clôturer une caisse'], 'links' => $links];
}

/**
 * Aide contextuelle : si $page correspond à un module connu, on renvoie son aide ;
 * sinon on renvoie une aide générique + suggestions de sujets.
 */
function tool_help(string $page): array
{
    $help = $GLOBALS['ASSISTANT_HELP'];
    $page = trim($page);
    if ($page !== '' && isset($help[$page])) {
        $h = $help[$page];
        $reply = $h['titre'] . " :\n" . implode("\n", array_map(function ($p) {
            return "• " . $p;
        }, $h['points']));
        return ['reply' => $reply, 'quick' => ['Chercher un médicament', 'Chiffre du jour'],
            'links' => []];
    }
    // Aide générique : tenter la FAQ
    return ['reply' => "Je peux vous aider à :\n• rechercher un médicament (ex. « chercher paracétamol »)\n• voir le stock en rupture\n• consulter le chiffre du jour\n• vous guider vers un menu.\nPosez votre question ou utilisez les suggestions ci-dessous.",
        'quick' => ['Chercher un médicament', 'Stock en rupture', 'Chiffre du jour', 'Comment clôturer une caisse'],
        'links' => []];
}

/** Orientation menu : renvoie une URL selon l'intention, filtrée par permission. */
function tool_nav(string $intent): array
{
    $map = [
        'search_product' => ['produits', 'Médicaments', 'produits.voir'],
        'stock_alerts'   => ['stock', 'Stock', 'stock.voir'],
        'stats'          => ['dashboard', 'Tableau de bord', 'dashboard.voir'],
        'vente'          => ['vente', 'Point de Vente', 'vente.creer'],
        'caisse'         => ['caisse', 'Caisses', 'caisse.voir'],
        'produits'       => ['produits', 'Médicaments', 'produits.voir'],
        'stock'          => ['stock', 'Stock', 'stock.voir'],
        'magasin'        => ['magasin', 'Magasin', 'magasin.voir'],
        'commandes'      => ['commandes', 'Commandes', 'commandes.voir'],
        'clients'        => ['clients', 'Clients', 'clients.voir'],
        'comptabilite'   => ['comptabilite', 'Comptabilité', 'comptabilite.voir'],
        'rapports'       => ['rapports', 'Rapports', 'rapports.voir'],
        'parametres'     => ['parametres', 'Paramètres', 'parametres.voir'],
        'dashboard'      => ['dashboard', 'Tableau de bord', 'dashboard.voir'],
    ];
    if (isset($map[$intent])) {
        [$route, $label, $perm] = $map[$intent];
        if (hasPermission($perm)) {
            return ['reply' => "Voici le menu « " . $label . " » :", 'quick' => [],
                'links' => [['label' => 'Aller à ' . $label, 'url' => url($route)]]];
        }
        // Pas le droit : on oriente vers les menus accessibles
        $access = assistant_accessible_menu_links();
        if ($access) {
            return ['reply' => "Vous n'avez pas accès au menu « " . $label . " » avec votre rôle. Voici les menus accessibles :",
                'quick' => [], 'links' => $access];
        }
        return ['reply' => "Vous n'avez pas accès au menu « " . $label . " » avec votre rôle.",
            'quick' => [], 'links' => []];
    }
    $access = assistant_accessible_menu_links();
    return ['reply' => "Quel menu cherchez-vous ? Voici ceux accessibles avec votre rôle :",
        'quick' => [], 'links' => $access];
}

// ──────────────────────────────────────────────────────────────
// Point d'entrée
// ──────────────────────────────────────────────────────────────

/**
 * Traite un message et renvoie une réponse.
 * @param array{message?:string,page?:string,role?:string} $msg
 * @return array{reply:string,quick:string[],links:array<int,array{label:string,url:string}>}
 */
function assistant_handle(array $msg, array $ctx): array
{
    $text = trim($msg['message'] ?? '');
    $page = trim($ctx['page'] ?? '');
    $c = assistant_capabilities();

    if ($text === '') {
        return ['reply' => "Je n'ai pas reçu de question. Posez-moi par exemple « chercher paracétamol ».",
            'quick' => assistant_quick_replies(), 'links' => []];
    }

    $intent = assistant_intent($text);

    switch ($intent) {
        case 'mutation':
            // Orientation vers les menus accessibles (filtrés par rôle)
            return [
                'reply' => "Je ne peux pas effectuer d'action sensible (créer, modifier, supprimer, rembourser, ajuster, importer, clôturer) — c'est une sécurité. Ouvrez le menu correspondant pour le faire vous-même :",
                'quick' => ['Comment clôturer une caisse', 'Comment faire un inventaire'],
                'links' => array_slice(assistant_accessible_menu_links(), 0, 4),
            ];
        case 'greeting':
            return ['reply' => "Bonjour 👋 Comment puis-je vous aider ?",
                'quick' => assistant_quick_replies(), 'links' => []];
        case 'search_product':
            return tool_search_products($text);
        case 'stock_alerts':
            return tool_stock_alerts();
        case 'stats':
            return tool_stats();
        case 'help':
            // Aide contextuelle prioritaire : si la question porte sur le module
            // de la page courante, on renvoie l'aide de ce module.
            if ($page !== '' && isset($GLOBALS['ASSISTANT_HELP'][$page]) && assistant_text_mentions($text, $page)) {
                return tool_help($page);
            }
            // Puis la FAQ (questions « comment faire X »)
            $faq = assistant_faq_match($text);
            if ($faq !== null) {
                return ['reply' => $faq, 'quick' => assistant_quick_replies(), 'links' => []];
            }
            // Sinon aide du module courant s'il y en a une, sinon aide générique
            return tool_help($page);
        case 'navigate':
            // Extraire la cible
            $target = assistant_nav_target($text);
            return tool_nav($target);
        case 'unknown':
        default:
            // En fallback, si la page courante a une aide, la proposer
            if ($page !== '' && isset($GLOBALS['ASSISTANT_HELP'][$page])) {
                return tool_help($page);
            }
            // Liste des capacités réelles de l'utilisateur
            $can = [];
            if ($c['produits'] || $c['vente'] || $c['stock']) $can[] = 'rechercher un médicament';
            if ($c['stock']) $can[] = 'voir le stock en rupture';
            if ($c['compta'] || $c['admin'] || $c['stock']) $can[] = 'consulter le chiffre du jour';
            elseif ($c['vente']) $can[] = 'voir vos ventes du jour';
            $can[] = 'vous guider vers un menu';
            return ['reply' => "Je n'ai pas bien compris la demande. Je peux : " . implode(', ', $can) . ".",
                'quick' => assistant_quick_replies(), 'links' => []];
    }
}

/** Tente de matcher une question FAQ (mots-clés cumulés). */
function assistant_faq_match(string $text): ?string
{
    $t = ' ' . strtolower($text) . ' ';
    foreach ($GLOBALS['ASSISTANT_FAQ'] as $item) {
        $hits = 0;
        foreach ($item['q'] as $kw) {
            if (strpos($t, $kw) !== false) {
                $hits++;
            }
        }
        // Au moins la moitié des mots-clés + un minimum de 2
        if ($hits >= 2 && $hits >= ceil(count($item['q']) / 2)) {
            return $item['a'];
        }
    }
    return null;
}

/** Le texte mentionne-t-il le domaine du module donné ? */
function assistant_text_mentions(string $text, string $page): bool
{
    $t = ' ' . strtolower($text) . ' ';
    $map = [
        'vente'    => ['vente', 'pos', 'panier', 'vendre'],
        'caisse'   => ['caisse', 'caisses', 'fond', 'session', 'cloturer', 'cloture', 'fermer'],
        'produits' => ['medicament', 'médicament', 'produit', 'catalogue', 'article'],
        'stock'    => ['stock', 'inventaire', 'rupture', 'seuil'],
        'magasin'  => ['magasin', 'transfert', 'reception'],
        'commandes' => ['commande', 'commandes', 'fournisseur'],
        'clients'  => ['client', 'clients', 'fidelite'],
        'comptabilite' => ['comptabil', 'compta', 'ecriture', 'ohada'],
        'rapports' => ['rapport', 'rapports', 'statistique'],
        'parametres' => ['parametre', 'parametres', 'reglage', 'config'],
    ];
    $kws = $map[$page] ?? [$page];
    foreach ($kws as $kw) {
        if (strpos($t, $kw) !== false) {
            return true;
        }
    }
    return false;
}

/** Extrait une cible de navigation depuis le texte. */
function assistant_nav_target(string $text): string
{
    $t = strtolower($text);
    $map = [
        'vente'    => ['vente', 'pos', 'point de vente', 'vendre', 'panier'],
        'caisse'   => ['caisse', 'caisses', 'fond', 'session'],
        'produits' => ['medicament', 'médicament', 'produit', 'catalogue', 'article'],
        'stock'    => ['stock', 'inventaire', 'rupture', 'seuil'],
        'magasin'  => ['magasin', 'transfert', 'reception'],
        'commandes' => ['commande', 'commandes', 'fournisseur'],
        'clients'  => ['client', 'clients', 'fidelite'],
        'comptabilite' => ['comptabil', 'compta', 'ecriture', 'ohada', 'plan comptable'],
        'rapports' => ['rapport', 'rapports', 'statistique', 'reporting'],
        'parametres' => ['parametre', 'parametres', 'reglage', 'config'],
        'dashboard' => ['dashboard', 'tableau de bord', 'accueil'],
    ];
    foreach ($map as $target => $kws) {
        foreach ($kws as $kw) {
            if (strpos($t, $kw) !== false) {
                return $target;
            }
        }
    }
    return '';
}