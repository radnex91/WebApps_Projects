<?php $title = 'Paramètres'; ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4><i class="bi bi-gear"></i> Paramètres</h4>
</div>

<ul class="nav nav-tabs mb-3" id="settingsTabs" role="tablist">
    <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-general">Général</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-currency">Devise</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-taxes">TVA / Taxes</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-roles">Rôles & Permissions</button></li>
</ul>

<div class="tab-content">
    <!-- === GÉNÉRAL === -->
    <div class="tab-pane fade show active" id="tab-general">
        <div class="card"><div class="card-header"><h6 class="mb-0"><i class="bi bi-building"></i> Informations de l'hôtel</h6></div>
        <div class="card-body">
            <form method="post" action="<?= url('settings/update') ?>">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Nom de l'hôtel</label>
                        <input type="text" name="hotel_nom" class="form-control" value="<?= e($settings['hotel_nom'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Email</label>
                        <input type="email" name="hotel_email" class="form-control" value="<?= e($settings['hotel_email'] ?? '') ?>">
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Téléphone</label>
                        <input type="text" name="hotel_telephone" class="form-control" value="<?= e($settings['hotel_telephone'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Adresse</label>
                        <input type="text" name="hotel_adresse" class="form-control" value="<?= e($settings['hotel_adresse'] ?? '') ?>">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Thème par défaut</label>
                    <p class="text-muted small mb-2">Ce thème sera appliqué à tous les nouveaux utilisateurs. Chaque utilisateur peut ensuite changer son thème via le sélecteur dans la barre latérale.</p>
                    <div class="row g-3">
                        <?php
                        $themes = [
                            'dark'      => ['Dark Pro', 'Interface sombre professionnelle, accent violet/indigo', '#4f8cf7', '#0d1117'],
                            'corporate' => ['Corporate Luxe', 'Bleu marine profond, accents dorés', '#c8a44e', '#0f1929'],
                            'minimal'   => ['Minimal', 'Vert sauge épuré, tons neutres chauds', '#8ab89a', '#151917'],
                        ];
                        $current = $settings['theme_defaut'] ?? 'dark';
                        foreach ($themes as $key => [$name, $desc, $accent, $bg]):
                        ?>
                        <div class="col-md-4">
                            <label class="theme-card-option" style="cursor:pointer; display:block;">
                                <input type="radio" name="theme_defaut" value="<?= $key ?>" <?= $current === $key ? 'checked' : '' ?> class="d-none theme-radio">
                                <div class="card p-3 theme-preview-card <?= $current === $key ? 'border-accent' : '' ?>" style="background:<?= $bg ?>; border:2px solid <?= $current === $key ? $accent : 'var(--border-color)' ?>; border-radius:var(--radius); transition:0.2s;">
                                    <div class="d-flex align-items-center gap-2 mb-2">
                                        <div class="rounded-circle" style="width:18px;height:18px;background:<?= $accent ?>;"></div>
                                        <strong style="color:#e2e4e9"><?= $name ?></strong>
                                    </div>
                                    <small class="d-block" style="color:#8b8f98"><?= $desc ?></small>
                                    <div class="d-flex gap-1 mt-2">
                                        <span style="width:40px;height:6px;border-radius:3px;background:<?= $accent ?>;opacity:0.6"></span>
                                        <span style="width:25px;height:6px;border-radius:3px;background:#555;opacity:0.5"></span>
                                        <span style="width:30px;height:6px;border-radius:3px;background:#555;opacity:0.3"></span>
                                    </div>
                                </div>
                            </label>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Enregistrer</button>
                    <a href="<?= url('auth/theme/' . $current) ?>" class="btn btn-outline-secondary">Appliquer le thème par défaut maintenant</a>
                </div>
            </form>
        </div></div>
    </div>

    <!-- === DEVISE === -->
    <div class="tab-pane fade" id="tab-currency">
        <div class="card"><div class="card-header"><h6 class="mb-0"><i class="bi bi-cash-stack"></i> Configuration de la devise</h6></div>
        <div class="card-body">
            <form method="post" action="<?= url('settings/update') ?>">
                <div class="alert alert-info">
                    <i class="bi bi-info-circle me-2"></i> La devise est configurée en <strong>XAF (Franc CFA d'Afrique Centrale)</strong>, monnaie officielle du Cameroun.
                </div>
                <div class="row mb-3">
                    <div class="col-md-4">
                        <label class="form-label">Code devise (ISO)</label>
                        <input type="text" name="devise_code" class="form-control" value="<?= e($settings['devise_code'] ?? 'XAF') ?>" readonly>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Symbole</label>
                        <input type="text" name="devise_symbole" class="form-control" value="<?= e($settings['devise_symbole'] ?? 'FCFA') ?>">
                    </div>
                </div>
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Enregistrer</button>
            </form>
        </div></div>
    </div>

    <!-- === TVA / TAXES === -->
    <div class="tab-pane fade" id="tab-taxes">
        <div class="card"><div class="card-header"><h6 class="mb-0"><i class="bi bi-percent"></i> Taux de TVA par défaut</h6></div>
        <div class="card-body">
            <form method="post" action="<?= url('settings/update') ?>" class="mb-4">
                <div class="row mb-3">
                    <div class="col-md-4">
                        <label class="form-label">TVA par défaut (%)</label>
                        <div class="input-group">
                            <input type="number" name="tva_defaut" class="form-control" step="0.01" value="<?= e($settings['tva_defaut'] ?? '19.25') ?>">
                            <span class="input-group-text">%</span>
                        </div>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Enregistrer</button>
            </form>
            <hr>
            <p class="text-muted small">Pour gérer l'ensemble des taxes (TVA Standard, TVA Réduite, IS, etc.), utilisez le module Comptabilité → <a href="<?= url('accounting/taxes') ?>">Taxes</a>.</p>
        </div></div>
    </div>

    <!-- === RÔLES & PERMISSIONS === -->
    <div class="tab-pane fade" id="tab-roles">
        <div class="row">
            <div class="col-md-6">
                <div class="card mb-3"><div class="card-header"><h6 class="mb-0"><i class="bi bi-shield-lock"></i> Permissions par rôle</h6></div>
                <div class="card-body">
                    <?php foreach ($roles as $role): ?>
                    <form method="post" action="<?= url('settings/updatePermissions') ?>" class="mb-3 p-3 border rounded">
                        <input type="hidden" name="role" value="<?= e($role) ?>">
                        <h6 class="text-capitalize mb-2">
                            <span class="badge bg-<?= $role==='admin'?'primary':($role==='manager'?'warning':'secondary') ?>"><?= e($role) ?></span>
                        </h6>
                        <div class="d-flex flex-wrap gap-2 mb-2">
                            <?php
                            $modules = ['hotel' => 'Hôtellerie', 'hr' => 'Ressources Humaines', 'accounting' => 'Comptabilité', 'admin' => 'Administration'];
                            $currentPerms = $rolePermissions[$role] ?? [];
                            foreach ($modules as $modKey => $modLabel):
                                $checked = in_array($modKey, $currentPerms) ? 'checked' : '';
                            ?>
                            <label class="form-check form-check-inline">
                                <input class="form-check-input" type="checkbox" name="modules[]" value="<?= $modKey ?>" <?= $checked ?>>
                                <span class="form-check-label small"><?= $modLabel ?></span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                        <button type="submit" class="btn btn-sm btn-outline-primary">Appliquer</button>
                    </form>
                    <?php endforeach; ?>
                </div></div>
            </div>

            <div class="col-md-6">
                <div class="card"><div class="card-header"><h6 class="mb-0"><i class="bi bi-person-badge"></i> Changer le rôle d'un utilisateur</h6></div>
                <div class="card-body">
                    <form method="post" action="<?= url('settings/updateRole') ?>">
                        <div class="mb-3">
                            <label class="form-label">Utilisateur</label>
                            <select name="user_id" class="form-select">
                                <option value="">— Sélectionner —</option>
                                <?php foreach ($users as $u): ?>
                                <option value="<?= $u['id'] ?>"><?= e($u['prenom'].' '.$u['nom']) ?> (<?= e($u['role']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Nouveau rôle</label>
                            <select name="role" class="form-select">
                                <?php foreach ($roles as $r): ?>
                                <option value="<?= e($r) ?>"><?= ucfirst(e($r)) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary">Changer le rôle</button>
                    </form>
                </div></div>
            </div>
        </div>
    </div>

</div>
