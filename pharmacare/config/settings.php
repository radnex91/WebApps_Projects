<?php
declare(strict_types=1);
date_default_timezone_set('Africa/Douala');

require_once __DIR__ . '/../includes/cache_file.php';

/**
 * Chargement des paramètres depuis la BDD
 * Utilisé partout via getParam() / getAllParams()
 *
 * Résilience « offline-first » : la lecture des paramètres ne doit JAMAIS
 * tuer l'application quand MySQL est arrêté. C'est la session (délai
 * d'inactivité) et le ping de présence qui en dépendent — ils doivent survivre
 * à une panne pour que les pages déjà ouvertes continuent de fonctionner
 * (ventes mises en file, session conservée, reprise automatique au retour).
 *   - BDD joignable      → lecture + rafraîchissement du cache disque
 *                          (dernier état connu de la pharmacie) ;
 *   - BDD injoignable    → reprise du cache disque, SANS page 503 ;
 *   - ni l'un ni l'autre → valeurs par défaut du code.
 */
function getAllParams(bool $refresh = false): array {
    static $params = null;
    if ($params !== null && !$refresh) {
        return $params;
    }
    if ($refresh) {
        $params = null;
    }
    if ($params === null) {
        $key = 'params_' . (defined('DB_NAME') ? DB_NAME : 'db');
        $GLOBALS['__pc_params_db_ok'] = false;
        try {
            if (!defined('DB_HOST')) throw new Exception('env non chargé');
            // Connexion NON FATALE : contrairement à getDB() (qui affiche la
            // page 503 et interrompt le script), un échec ici est récupérable.
            $pdo = new PDO(
                'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET,
                DB_USER, DB_PASS,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
            );
            $GLOBALS['__pc_params_db_ok'] = true; // MySQL accepte les connexions
            try {
                $rows   = $pdo->query("SELECT cle, valeur FROM parametres")->fetchAll();
                $params = array_column($rows, 'valeur', 'cle');
                // Dernier état connu en cache disque (pour une panne future)
                $found = false;
                if (cache_get($key, 604800, $found) !== $params) {
                    cache_set($key, $params, 604800); // 7 jours ; rafraîchi à chaque lecture réussie
                }
            } catch (Exception $e2) {
                // Table absente (première installation) → valeurs par défaut
                $params = [
                    'devise'            => 'XAF',
                    'devise_symbole'    => 'FCFA',
                    'devise_pos'        => 'after',
                    'tva'               => '19.25',
                    'app_nom'           => 'PharmaCare',
                    'theme'             => 'dark-navy',
                    'police'            => 'Manrope',
                    'police_titre'      => 'Manrope',
                    'pharmacie_adresse' => '',
                    'pharmacie_telephone'=> '',
                    'pharmacie_nif'     => '',
                    'ticket_sous_titre' => 'Gestion Pharmacie',
                    'ticket_pied'       => 'Merci pour votre achat !',
                    'prefix_vente'      => 'VNT',
                    'caisse_fermeture_mode'    => 'manuel',
                    'caisse_heure_fermeture'   => '22:00',
                    'assistant_active'         => '1',
                    'delai_inactivite_min'     => '15',
                ];
            }
        } catch (Exception $e) {
            // MySQL injoignable (XAMPP arrêté, câble débranché…) → NE PAS
            // afficher la page 503 ici : la session doit rester vivante.
            $found  = false;
            $cached = cache_get($key, 604800, $found);
            $params = ($found && is_array($cached) && $cached)
                ? $cached
                : [
                    // Premier démarrage sans cache : valeurs par défaut du code
                    'devise'            => 'XAF',
                    'devise_symbole'    => 'FCFA',
                    'devise_pos'        => 'after',
                    'tva'               => '19.25',
                    'app_nom'           => 'PharmaCare',
                    'theme'             => 'dark-navy',
                    'police'            => 'Manrope',
                    'police_titre'      => 'Manrope',
                    'pharmacie_adresse' => '',
                    'pharmacie_telephone'=> '',
                    'pharmacie_nif'     => '',
                    'ticket_sous_titre' => 'Gestion Pharmacie',
                    'ticket_pied'       => 'Merci pour votre achat !',
                    'prefix_vente'      => 'VNT',
                    'caisse_fermeture_mode'    => 'manuel',
                    'caisse_heure_fermeture'   => '22:00',
                    'assistant_active'         => '1',
                    'delai_inactivite_min'     => '15',
                ];
        }
    }
    return $params;
}

/**
 * MySQL accepte-t-il les connexions en ce moment ?
 * (déterminé par la dernière tentative de getAllParams — appelée au moins
 * une fois par requête via startSession)
 */
function paramsDbReachable(): bool {
    getAllParams();
    return !empty($GLOBALS['__pc_params_db_ok']);
}

function getParam(string $key, string $default = ''): string {
    return getAllParams()[$key] ?? $default;
}

/**
 * Logo de la PHARMACIE (propre au client, uploadé via Paramètres).
 * Distinct du logo de l'application PharmaCare (qui est propriétaire et fixe).
 * Retourne l'URL publique (cache-bustée) si un logo est défini et présent sur disque,
 * sinon chaîne vide. Utilisé sur les tickets imprimés et les bons.
 */
function pharmacieLogoUrl(): string {
    $rel = getParam('pharmacie_logo', '');
    if ($rel === '') return '';
    $file = __DIR__ . '/../' . $rel;
    if (!is_file($file)) return '';
    $base = defined('APP_URL') ? APP_URL : '';
    $v    = defined('APP_VERSION') ? APP_VERSION : '1';
    return $base . '/' . $rel . '?v=' . rawurlencode($v);
}

/**
 * Chemin absolu du logo pharmacie sur disque (pour validation upload / suppression).
 */
function pharmacieLogoPath(): string {
    $rel = getParam('pharmacie_logo', '');
    return $rel === '' ? '' : __DIR__ . '/../' . $rel;
}

/**
 * Invalide le cache statique de getAllParams().
 * À appeler après une écriture directe dans parametres (ex: licence_apply_code)
 * pour que getParam() reflète la nouvelle valeur dans la même requête.
 */
function paramCacheClear(): void {
    getAllParams(true);
}

/**
 * Module fidélité / « client fidèle » — activé via le paramètre 'fidelite_active'.
 * Désactivé par défaut. Pour réactiver partout dans l'app :
 *   INSERT INTO parametres (cle, valeur) VALUES ('fidelite_active', '1')
 *     ON DUPLICATE KEY UPDATE valeur='1';
 */
function fideliteActive(): bool {
    return getParam('fidelite_active', '0') === '1';
}

/**
 * Vente à crédit / dettes clients — activée via le paramètre 'credit_active'.
 * Désactivée par défaut (les dettes sont suspendues pour l'instant). Bloque
 * la création de nouvelles ventes à crédit au POS ; l'existant (dettes déjà
 * enregistrées, règlements, compta OHADA) reste visible et intact.
 * Pour réactiver :
 *   INSERT INTO parametres (cle, valeur) VALUES ('credit_active', '1')
 *     ON DUPLICATE KEY UPDATE valeur='1';
 */
function creditActive(): bool {
    return getParam('credit_active', '0') === '1';
}

/**
 * Formatage monétaire selon la devise configurée
 */
function fmtMoney(float $n): string {
    $p   = getAllParams();
    $sym = $p['devise_symbole'] ?? 'FCFA';
    $pos = $p['devise_pos']     ?? 'after';
    $formatted = number_format($n, 0, ',', ' ');   // FCFA = 0 décimales
    return $pos === 'before' ? "$sym $formatted" : "$formatted $sym";
}

/**
 * Thèmes disponibles : [id => [label, --teal, --gold, --red, --blue, --bg]]
 */
function getThemes(): array {
    return [
        'dark-navy'    => ['Bleu Marine',   '#06d6a0','#f59e0b','#ef4444','#818cf8','#080c15'],
        'dark-rose'    => ['Rose Poudré',    '#cc6f7f','#a1ae9d','#ef4444','#d4a574','#0f0c0a'],
        'light-clair'  => ['Clair — Azur',   '#0d9488','#a16207','#dc2626','#4a7dc4','#f4f5f2','#eceee9','#e3e8e0','#fcfcfa'],
        'light-brainy' => ['Brainy ERP',     '#0ea87e','#e8a800','#d63547','#1a4f8a','#f0f2f5','#e6e9ee','#dde2ea','#ffffff'],
    ];
}

/**
 * Polices disponibles
 */
function getPolices(): array {
    return [
        'Manrope' => 'Manrope',
    ];
}

function getPolicesTitres(): array {
    return [
        'Manrope' => 'Manrope',
    ];
}

/**
 * Devises Afrique Centrale et courantes
 */
function getDevises(): array {
    return [
        // Afrique Centrale & CEMAC
        'XAF' => ['FCFA',  'after',  'Franc CFA BEAC (FCFA) — Cameroun, Gabon, Congo, RCA, Tchad, Guinée Équatoriale'],
        'CDF' => ['FC',    'after',  'Franc Congolais (FC) — RD Congo'],
        'AOA' => ['Kz',    'after',  'Kwanza (Kz) — Angola'],
        'XOF' => ['FCFA',  'after',  'Franc CFA BCEAO (FCFA) — Sénégal, Côte d\'Ivoire, Mali…'],
        'GNF' => ['FG',    'after',  'Franc Guinéen (FG)'],
        // Autres courantes
        'DZD' => ['DA',    'after',  'Dinar Algérien (DA)'],
        'MAD' => ['DH',    'after',  'Dirham Marocain (DH)'],
        'TND' => ['DT',    'after',  'Dinar Tunisien (DT)'],
        'EUR' => ['€',     'after',  'Euro (€)'],
        'USD' => ['$',     'before', 'Dollar US ($)'],
    ];
}