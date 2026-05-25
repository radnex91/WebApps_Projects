<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requirePermission('settings');

$pageTitle = 'Paramètres';
$settingModel = new Setting();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Requête invalide.');
        redirect(BASE_URL . '/views/settings.php');
    }

    $pairs = [
        'app_name'       => sanitize($_POST['app_name'] ?? ''),
        'app_subtitle'   => sanitize($_POST['app_subtitle'] ?? ''),
        'theme'          => sanitize($_POST['theme'] ?? 'default'),
        'primary_color'  => sanitize($_POST['primary_color'] ?? '#1a4f8a'),
        'sidebar_bg'     => sanitize($_POST['sidebar_bg'] ?? '#0f3060'),
        'accent2_color'  => sanitize($_POST['accent2_color'] ?? '#0ea87e'),
        'heading_font'   => sanitize($_POST['heading_font'] ?? 'Manrope'),
        'body_font'      => sanitize($_POST['body_font'] ?? 'Manrope'),
        'currency_code'  => sanitize($_POST['currency_code'] ?? 'XAF'),
        'currency_symbol'=> sanitize($_POST['currency_symbol'] ?? 'FCFA'),
        'currency_name'  => sanitize($_POST['currency_name'] ?? 'Franc CFA BEAC'),
        'tax_rate'       => sanitize($_POST['tax_rate'] ?? '19.25'),
        'timezone'       => sanitize($_POST['timezone'] ?? 'Africa/Douala'),
        'receipt_footer' => sanitize($_POST['receipt_footer'] ?? ''),
        'invoice_font'   => sanitize($_POST['invoice_font'] ?? 'DM Mono'),
    ];

    // Logo upload
    if (isset($_FILES['app_logo']) && $_FILES['app_logo']['error'] === UPLOAD_ERR_OK) {
        $uploaded = uploadImage($_FILES['app_logo']);
        if ($uploaded) {
            $pairs['app_logo'] = $uploaded;
        } elseif ($uploaded === false) {
            setFlash('error', 'Logo invalide (format ou taille).');
            redirect(BASE_URL . '/views/settings.php');
        }
    }

    // Suppression du logo
    if (isset($_POST['remove_logo']) && $_POST['remove_logo'] === '1') {
        $oldLogo = $appSettings['app_logo'] ?? '';
        if ($oldLogo && file_exists(ROOT_PATH . '/' . $oldLogo)) {
            unlink(ROOT_PATH . '/' . $oldLogo);
        }
        $pairs['app_logo'] = '';
    }

    if (empty($pairs['app_name'])) {
        setFlash('error', 'Le nom de l\'application est requis.');
    } else {
        $settingModel->setMany($pairs);
        // Recharger les settings pour ce rendu
        $appSettings = $settingModel->getAll();
        $GLOBALS['appSettings'] = $appSettings;
        setFlash('success', 'Paramètres enregistrés.');
    }
    redirect(BASE_URL . '/views/settings.php');
}

require_once __DIR__ . '/layout_top.php';

$currencies = [
    'XAF'  => 'FCFA — Franc CFA BEAC (Afrique Centrale)',
    'XOF'  => 'FCFA — Franc CFA BCEAO (Afrique de l\'Ouest)',
    'CDF'  => 'Franc congolais (RDC)',
    'GNF'  => 'Franc guinéen',
    'KMF'  => 'Franc comorien',
    'MGA'  => 'Ariary malgache (Madagascar)',
    'NGN'  => 'Naira (Nigeria)',
    'EGP'  => 'Livre égyptienne',
    'MAD'  => 'Dirham marocain',
    'DZD'  => 'Dinar algérien',
    'TND'  => 'Dinar tunisien',
    'GHS'  => 'Cedi (Ghana)',
    'KES'  => 'Shilling kényan',
    'TZS'  => 'Shilling tanzanien',
    'UGX'  => 'Shilling ougandais',
    'RWF'  => 'Franc rwandais',
    'BIF'  => 'Franc burundais',
    'ZMW'  => 'Kwacha zambien',
    'MWK'  => 'Kwacha malawite',
    'SCR'  => 'Roupie seychelloise',
    'MUR'  => 'Roupie mauricienne',
    'USD'  => 'Dollar américain',
    'EUR'  => 'Euro',
];
$timezones = ['Africa/Douala','Africa/Yaounde','Africa/Bangui','Africa/Brazzaville','Africa/Libreville','Africa/Malabo','Africa/Ndjamena','Africa/Lagos','Africa/Kinshasa','Africa/Cairo','Africa/Casablanca','Africa/Nairobi','Africa/Harare','Africa/Johannesburg','Indian/Antananarivo','Indian/Comoro'];
?>

<style>
.settings-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem; }
@media (max-width: 900px) { .settings-grid { grid-template-columns: 1fr; } }
.settings-card { background: var(--card-bg); border: 1px solid var(--border); border-radius: 14px; overflow: hidden; }
.settings-card-header { padding: 1rem 1.25rem; border-bottom: 1px solid var(--border); display: flex; align-items: center; gap: 0.6rem; }
.settings-card-header i { font-size: 1.1rem; color: var(--accent); }
.settings-card-header h6 { margin: 0; font-family: 'Manrope', sans-serif; font-weight: 700; font-size: 0.95rem; }
.settings-card-body { padding: 1.25rem; }

.form-group { margin-bottom: 1rem; }
.form-group:last-child { margin-bottom: 0; }
.form-group label { display: block; font-size: 0.82rem; font-weight: 600; color: var(--text-muted); margin-bottom: 0.35rem; }
.form-group label .hint { font-weight: 400; font-size: 0.75rem; opacity: 0.7; }

.logo-preview { display: flex; align-items: center; gap: 1rem; margin-bottom: 0.75rem; }
.logo-preview img { height: 48px; border-radius: 8px; border: 1px solid var(--border); padding: 4px; background: #fff; }
.logo-preview .no-logo { height: 48px; width: 48px; border-radius: 8px; border: 1px dashed var(--border); display: flex; align-items: center; justify-content: center; color: var(--text-muted); font-size: 1.2rem; }
.btn-save-all { background: var(--accent); border: none; border-radius: 8px; color: #fff; font-family: 'Manrope', sans-serif; font-weight: 600; font-size: 0.95rem; padding: 0.7rem 2rem; cursor: pointer; transition: all 0.15s; box-shadow: 0 2px 8px rgba(25,118,210,0.25); }
.btn-save-all:hover { transform: translateY(-1px); box-shadow: 0 4px 16px rgba(25,118,210,0.35); }

</style>

<form method="POST" enctype="multipart/form-data">
<input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">

<div class="d-flex justify-content-between align-items-center mb-3">
    <p class="text-muted mb-0 small">Configurez l'apparence, la devise et les paramètres généraux.</p>
    <button type="submit" class="btn-save-all"><i class="bi bi-check-circle me-2"></i>Enregistrer tout</button>
</div>

<div class="settings-grid">

    <!-- GENERAL -->
    <div class="settings-card">
        <div class="settings-card-header"><i class="bi bi-building"></i><h6>Général</h6></div>
        <div class="settings-card-body">
            <div class="form-group">
                <label>Nom de l'application</label>
                <input type="text" name="app_name" class="form-control form-control-sm" style="border-radius:8px" value="<?= e($appSettings['app_name'] ?? 'POS System') ?>" required>
            </div>
            <div class="form-group">
                <label>Sous-titre</label>
                <input type="text" name="app_subtitle" class="form-control form-control-sm" style="border-radius:8px" value="<?= e($appSettings['app_subtitle'] ?? 'Gestion Commerciale') ?>">
            </div>
            <div class="form-group">
                <label>Logo <span class="hint">(PNG, JPG, max 2MB)</span></label>
                <?php if (!empty($appSettings['app_logo'])): ?>
                <div class="logo-preview">
                    <img src="<?= BASE_URL ?>/<?= e($appSettings['app_logo']) ?>" alt="Logo">
                    <label class="btn btn-sm btn-outline-danger" style="border-radius:6px;font-size:.75rem">
                        <input type="checkbox" name="remove_logo" value="1" style="display:none" onchange="this.parentElement.classList.toggle('active', this.checked)">
                        <i class="bi bi-trash me-1"></i>Supprimer
                    </label>
                </div>
                <?php else: ?>
                <div class="logo-preview">
                    <div class="no-logo"><i class="bi bi-image"></i></div>
                </div>
                <?php endif; ?>
                <input type="file" name="app_logo" accept="image/*" class="form-control form-control-sm" style="border-radius:8px">
            </div>
            <div class="form-group">
                <label>Pied de ticket / reçu</label>
                <input type="text" name="receipt_footer" class="form-control form-control-sm" style="border-radius:8px" value="<?= e($appSettings['receipt_footer'] ?? '') ?>" placeholder="Merci pour votre achat !">
            </div>
            <div class="form-group">
                <label>Police des impressions <span class="hint">(factures &amp; tickets)</span></label>
                <select name="invoice_font" class="form-select form-select-sm" style="border-radius:8px">
                    <?php
                    $invoiceFonts = ['DM Mono','DM Sans','Inter','Poppins','Montserrat','Nunito','Rubik','Outfit','Space Grotesk','Manrope','Figtree','Courier Prime','Fira Code','JetBrains Mono','Source Code Pro'];
                    $currentInvoiceFont = $appSettings['invoice_font'] ?? 'DM Mono';
                    foreach ($invoiceFonts as $f): ?>
                    <option value="<?= $f ?>" <?= $currentInvoiceFont === $f ? 'selected' : '' ?>><?= $f ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>

    <!-- APPARENCE -->
    <div class="settings-card">
        <div class="settings-card-header"><i class="bi bi-palette"></i><h6>Apparence</h6></div>
        <div class="settings-card-body">
            <div class="form-group">
                <label>Thème</label>
                <select name="theme" class="form-select form-select-sm" style="border-radius:8px" onchange="applyTheme(this.value)">
                    <?php
                    $themes = [
                        'default'    => 'BrenFinance Bleu',
                        'wallstreet' => 'Wall Street',
                        'cyberpunk'  => 'Cyberpunk',
                        'aurora'     => 'Aurora Boréale',
                        'executive'  => 'Executive Dark',
                        'solar'      => 'Solar Flare',
                        'ocean'      => 'Ocean Depth',
                        'royal'      => 'Royal Purple',
                        'emerald'    => 'Emerald Luxe',
                    ];
                    $cur = $appSettings['theme'] ?? 'default';
                    foreach ($themes as $key => $label): ?>
                    <option value="<?= $key ?>" <?= $cur === $key ? 'selected' : '' ?>><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <input type="hidden" name="heading_font" value="Manrope">
            <input type="hidden" name="body_font" value="Manrope">
        </div>
    </div>

    <!-- DEVISE & FISCAL -->
    <div class="settings-card">
        <div class="settings-card-header"><i class="bi bi-cash-coin"></i><h6>Devise & Fiscal</h6></div>
        <div class="settings-card-body">
            <div class="form-group">
                <label>Devise</label>
                <select name="currency_code" id="currency_code" class="form-select form-select-sm" style="border-radius:8px" onchange="autoFillCurrency(this.value)">
                    <?php foreach ($currencies as $code => $label): ?>
                    <option value="<?= $code ?>" <?= ($appSettings['currency_code'] ?? 'XAF') === $code ? 'selected' : '' ?>><?= $code ?> — <?= $label ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="row g-2">
                <div class="col-4">
                    <div class="form-group">
                        <label>Symbole</label>
                        <input type="text" name="currency_symbol" id="currency_symbol" class="form-control form-control-sm" style="border-radius:8px" value="<?= e($appSettings['currency_symbol'] ?? 'FCFA') ?>">
                    </div>
                </div>
                <div class="col-8">
                    <div class="form-group">
                        <label>Nom complet</label>
                        <input type="text" name="currency_name" id="currency_name" class="form-control form-control-sm" style="border-radius:8px" value="<?= e($appSettings['currency_name'] ?? 'Franc CFA BEAC') ?>">
                    </div>
                </div>
            </div>
            <div class="form-group">
                <label>Taux TVA (%)</label>
                <input type="number" name="tax_rate" min="0" max="100" step="0.01" class="form-control form-control-sm" style="border-radius:8px" value="<?= e($appSettings['tax_rate'] ?? '19.25') ?>">
            </div>
            <div class="form-group">
                <label>Fuseau horaire</label>
                <select name="timezone" class="form-select form-select-sm" style="border-radius:8px">
                    <?php foreach ($timezones as $tz): ?>
                    <option value="<?= $tz ?>" <?= ($appSettings['timezone'] ?? 'Africa/Douala') === $tz ? 'selected' : '' ?>><?= str_replace('_', ' ', $tz) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>

</div>
</form>

<?php require_once __DIR__ . '/layout_bottom.php'; ?>

<script>
const currencyMap = {
    XAF:  { symbol: 'FCFA', name: 'Franc CFA BEAC' },
};

function autoFillCurrency(code) {
    const m = currencyMap[code];
    if (m) {
        document.getElementById('currency_symbol').value = m.symbol;
        document.getElementById('currency_name').value = m.name;
    }
}

function applyTheme(name) {
    const themes = {
        default:    { body: '#f0f2f5', card: '#ffffff', text: '#1a2236' },
        wallstreet: { body: '#0f0f14', card: '#16161e', text: '#d8d8e0' },
        cyberpunk:  { body: '#1a0a2a', card: '#22103a', text: '#e0d6f0' },
        aurora:     { body: '#0a0a20', card: '#121230', text: '#c8d0f0' },
        executive:  { body: '#0f0f14', card: '#16161e', text: '#d0d0d8' },
        solar:      { body: '#1a140a', card: '#221c10', text: '#e8d8c8' },
        ocean:      { body: '#0a0f2a', card: '#121a30', text: '#b8c8e0' },
        royal:      { body: '#1a0a2a', card: '#22103a', text: '#d8c8e0' },
        emerald:    { body: '#0a1a14', card: '#121f18', text: '#c8e0d0' },
    };
    const t = themes[name] || themes.default;
    document.documentElement.style.setProperty('--body-bg', t.body);
    document.documentElement.style.setProperty('--card-bg', t.card);
    document.documentElement.style.setProperty('--text', t.text);
}
</script>