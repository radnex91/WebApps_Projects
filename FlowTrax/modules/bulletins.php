<?php
requireLogin();

$db = getDB();
$action = $_GET['action'] ?? 'list';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

switch ($action) {
    case 'delete':
        requirePermission('bulletins.delete');
        $db->prepare("DELETE FROM bulletins WHERE id = ?")->execute([$id]);
        $_SESSION['flash']['success'] = 'Bulletin supprimé avec succès.';
        redirect('index.php?page=bulletins');
        break;

    case 'view':
        requirePermission('bulletins.view');
        $stmt = $db->prepare("
            SELECT b.*, v.immatriculation, v.marque, v.nb_places, v.proprietaire, v.type_vehicule,
                   a1.nom as agence_dep, a2.nom as agence_dest, a3.nom as agence_comp,
                   c.nom as chauffeur_nom, u.prenom as creator_prenom, u.nom as creator_nom
            FROM bulletins b
            LEFT JOIN vehicules v ON b.vehicule_id = v.id
            LEFT JOIN agences a1 ON b.agence_depart_id = a1.id
            LEFT JOIN agences a2 ON b.agence_destination_id = a2.id
            LEFT JOIN agences a3 ON b.agence_complement_id = a3.id
            LEFT JOIN chauffeurs c ON b.chauffeur_id = c.id
            LEFT JOIN users u ON b.created_by = u.id
            WHERE b.id = ?
        ");
        $stmt->execute([$id]);
        $b = $stmt->fetch();
        if (!$b) { $_SESSION['flash']['error'] = 'Bulletin introuvable.'; redirect('index.php?page=bulletins'); }

        $etapes = $db->prepare("SELECT * FROM etapes_passagers WHERE bulletin_id = ? ORDER BY id");
        $etapes->execute([$id]);
        $etapes = $etapes->fetchAll();
        ?>
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">📋 Bulletin #<?= $b['id'] ?></h3>
                <a href="index.php?page=bulletins" class="btn btn-sm btn-ghost">← Retour</a>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                <div><strong>Date :</strong> <?= formatDate($b['date_voyage']) ?></div>
                <div><strong>N° Ordre :</strong> <?= e($b['numero_ordre']) ?></div>
                <div><strong>Véhicule :</strong> <?= e($b['immatriculation']) ?> (<?= e($b['marque']) ?>, <?= (int)$b['nb_places'] ?> places)</div>
                <div><strong>Propriétaire :</strong> <?= e($b['proprietaire']) ?></div>
                <div><strong>Chauffeur :</strong> <?= e($b['chauffeur_nom']) ?></div>
                <div><strong>N° Bordereau :</strong> <?= e($b['numero_bordereau']) ?></div>
                <div><strong>Départ :</strong> <?= e($b['agence_dep']) ?> <?= $b['heure_depart'] ? 'à ' . $b['heure_depart'] : '' ?></div>
                <div><strong>Destination :</strong> <?= e($b['agence_dest']) ?> <?= $b['heure_arrivee'] ? 'à ' . $b['heure_arrivee'] : '' ?></div>
                <?php if ($b['agence_comp']): ?><div><strong>Agence complément :</strong> <?= e($b['agence_comp']) ?></div><?php endif; ?>
                <div><strong>Total passagers :</strong> <?= (int)$b['total_passagers'] ?></div>
                <div><strong>Taux remplissage :</strong> <?= $b['taux_remplissage'] ?>%</div>
            </div>

            <?php if (!empty($etapes)): ?>
            <h4 style="margin:20px 0 12px;">Détail par étape</h4>
            <div class="table-container">
                <table>
                    <thead><tr><th>#</th><th>Ville</th><th>Passagers montés</th></tr></thead>
                    <tbody>
                        <?php $i=1; foreach ($etapes as $e): ?>
                        <tr>
                            <td><?= $i++ ?></td>
                            <td><?= e($e['ville']) ?></td>
                            <td><strong><?= (int)$e['passagers_montees'] ?></strong></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>

            <?php if ($b['observation']): ?>
            <div style="margin-top:16px;">
                <strong>Observation :</strong><br>
                <?= nl2br(e($b['observation'])) ?>
            </div>
            <?php endif; ?>

            <div style="margin-top:16px;font-size:12px;color:var(--gray-400);">
                Créé par <?= e($b['creator_prenom']) ?> <?= e($b['creator_nom']) ?> le <?= formatDatetime($b['created_at']) ?>
            </div>
        </div>
        <?php
        break;

    case 'create':
    case 'edit':
        requirePermission($action === 'create' ? 'bulletins.create' : 'bulletins.edit');
        $b = [
            'numero_ordre'=>'', 'date_voyage'=>date('Y-m-d'), 'vehicule_id'=>'', 'chauffeur_id'=>'',
            'agence_depart_id'=>'', 'agence_destination_id'=>'', 'agence_complement_id'=>'',
            'total_passagers'=>0, 'taux_remplissage'=>0, 'heure_depart'=>'', 'heure_arrivee'=>'',
            'numero_bordereau'=>'', 'observation'=>''
        ];
        $etapes = [];

        if ($action === 'edit' && $id) {
            $stmt = $db->prepare("SELECT * FROM bulletins WHERE id = ?");
            $stmt->execute([$id]); $b = $stmt->fetch();
            if (!$b) { $_SESSION['flash']['error'] = 'Bulletin introuvable.'; redirect('index.php?page=bulletins'); }
            $stmt = $db->prepare("SELECT * FROM etapes_passagers WHERE bulletin_id = ? ORDER BY id");
            $stmt->execute([$id]); $etapes = $stmt->fetchAll();
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = [
                'numero_ordre' => $_POST['numero_ordre'],
                'date_voyage' => $_POST['date_voyage'],
                'vehicule_id' => $_POST['vehicule_id'] ?: null,
                'chauffeur_id' => $_POST['chauffeur_id'] ?: null,
                'agence_depart_id' => $_POST['agence_depart_id'] ?: null,
                'agence_destination_id' => $_POST['agence_destination_id'] ?: null,
                'agence_complement_id' => $_POST['agence_complement_id'] ?: null,
                'total_passagers' => (int)$_POST['total_passagers'],
                'taux_remplissage' => (float)$_POST['taux_remplissage'],
                'heure_depart' => $_POST['heure_depart'] ?: null,
                'heure_arrivee' => $_POST['heure_arrivee'] ?: null,
                'numero_bordereau' => $_POST['numero_bordereau'],
                'observation' => $_POST['observation'],
            ];

            if ($action === 'create') {
                $data['created_by'] = $_SESSION['user_id'];
                $stmt = $db->prepare("INSERT INTO bulletins (numero_ordre, date_voyage, vehicule_id, chauffeur_id, agence_depart_id, agence_destination_id, agence_complement_id, total_passagers, taux_remplissage, heure_depart, heure_arrivee, numero_bordereau, observation, created_by) VALUES (:numero_ordre, :date_voyage, :vehicule_id, :chauffeur_id, :agence_depart_id, :agence_destination_id, :agence_complement_id, :total_passagers, :taux_remplissage, :heure_depart, :heure_arrivee, :numero_bordereau, :observation, :created_by)");
                $stmt->execute($data);
                $bulletinId = $db->lastInsertId();
            } else {
                $stmt = $db->prepare("UPDATE bulletins SET numero_ordre=:numero_ordre, date_voyage=:date_voyage, vehicule_id=:vehicule_id, chauffeur_id=:chauffeur_id, agence_depart_id=:agence_depart_id, agence_destination_id=:agence_destination_id, agence_complement_id=:agence_complement_id, total_passagers=:total_passagers, taux_remplissage=:taux_remplissage, heure_depart=:heure_depart, heure_arrivee=:heure_arrivee, numero_bordereau=:numero_bordereau, observation=:observation WHERE id=$id");
                $stmt->execute($data);
                $bulletinId = $id;
                $db->prepare("DELETE FROM etapes_passagers WHERE bulletin_id = ?")->execute([$bulletinId]);
            }

            // Save etapes
            $villes = $_POST['ville'] ?? [];
            $passagers = $_POST['passagers'] ?? [];
            $insertEtape = $db->prepare("INSERT INTO etapes_passagers (bulletin_id, ville, passagers_montees) VALUES (?, ?, ?)");
            foreach ($villes as $i => $ville) {
                if (!empty(trim($ville))) {
                    $insertEtape->execute([$bulletinId, $ville, (int)($passagers[$i] ?? 0)]);
                }
            }

            $_SESSION['flash']['success'] = 'Bulletin enregistré avec succès.';
            redirect('index.php?page=bulletins');
        }

        $vehicules = $db->query("SELECT * FROM vehicules WHERE active = 1 ORDER BY immatriculation")->fetchAll();
        $chauffeurs = $db->query("SELECT * FROM chauffeurs WHERE active = 1 ORDER BY nom")->fetchAll();
        $agences = $db->query("SELECT * FROM agences WHERE active = 1 ORDER BY nom")->fetchAll();

        // Default etapes from the original Excel (Cameroon route cities)
        $defaultEtapes = ['Yaoundé', 'Abong-mbang', 'Bertoua', 'Ndokayo', 'Garoua-boulai', 'Meiganga', 'Ngaoundéré', 'Mbé', 'Ngong', 'Garoua', 'Kalfou', 'Guidiguis', 'Kaélé', 'Maroua'];
        ?>
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><?= $action === 'create' ? '📋 Nouveau bulletin' : '✏️ Modifier le bulletin' ?></h3>
                <a href="index.php?page=bulletins" class="btn btn-sm btn-ghost">← Retour</a>
            </div>
            <form method="POST">
                <div class="form-row">
                    <div class="form-group">
                        <label>N° Ordre</label>
                        <input type="text" name="numero_ordre" class="form-control" value="<?= e($b['numero_ordre']) ?>">
                    </div>
                    <div class="form-group">
                        <label>Date voyage *</label>
                        <input type="date" name="date_voyage" class="form-control" value="<?= e($b['date_voyage']) ?>" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>🚌 Véhicule *</label>
                        <select name="vehicule_id" id="vehicule_id" class="form-control" required>
                            <option value="">— Sélectionner —</option>
                            <?php foreach ($vehicules as $v): ?>
                            <option value="<?= $v['id'] ?>" data-places="<?= $v['nb_places'] ?>" data-marque="<?= e($v['marque']) ?>" data-proprietaire="<?= e($v['proprietaire']) ?>" <?= $b['vehicule_id'] == $v['id'] ? 'selected' : '' ?>>
                                <?= e($v['immatriculation']) ?> — <?= e($v['marque']) ?> (<?= $v['nb_places'] ?> pl.)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>👨‍✈️ Chauffeur *</label>
                        <select name="chauffeur_id" class="form-control" required>
                            <option value="">— Sélectionner —</option>
                            <?php foreach ($chauffeurs as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= $b['chauffeur_id'] == $c['id'] ? 'selected' : '' ?>><?= e($c['nom']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div style="display:none;">
                    <input type="text" id="nb_places" name="nb_places_display" value="<?= $b['vehicule_id'] ? ($vehicules[array_search($b['vehicule_id'], array_column($vehicules, 'id'))]['nb_places'] ?? '') : '' ?>" readonly>
                    <input type="text" id="marque_display" readonly>
                    <input type="text" id="proprietaire_display" readonly>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>🏢 Agence départ *</label>
                        <select name="agence_depart_id" class="form-control" required>
                            <option value="">— Sélectionner —</option>
                            <?php foreach ($agences as $a): ?>
                            <option value="<?= $a['id'] ?>" <?= $b['agence_depart_id'] == $a['id'] ? 'selected' : '' ?>><?= e($a['nom']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>🏢 Destination *</label>
                        <select name="agence_destination_id" class="form-control" required>
                            <option value="">— Sélectionner —</option>
                            <?php foreach ($agences as $a): ?>
                            <option value="<?= $a['id'] ?>" <?= $b['agence_destination_id'] == $a['id'] ? 'selected' : '' ?>><?= e($a['nom']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>🏢 Agence complément</label>
                        <select name="agence_complement_id" class="form-control">
                            <option value="">— Aucune —</option>
                            <?php foreach ($agences as $a): ?>
                            <option value="<?= $a['id'] ?>" <?= $b['agence_complement_id'] == $a['id'] ? 'selected' : '' ?>><?= e($a['nom']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>🕐 Heure départ</label>
                        <input type="time" name="heure_depart" class="form-control" value="<?= e($b['heure_depart']) ?>">
                    </div>
                    <div class="form-group">
                        <label>🕐 Heure arrivée</label>
                        <input type="time" name="heure_arrivee" class="form-control" value="<?= e($b['heure_arrivee']) ?>">
                    </div>
                    <div class="form-group">
                        <label>📄 N° Bordereau</label>
                        <input type="text" name="numero_bordereau" class="form-control" value="<?= e($b['numero_bordereau']) ?>">
                    </div>
                </div>

                <h4 style="margin:20px 0 12px;">📍 Passagers par étape</h4>
                <p style="font-size:13px;color:var(--gray-500);margin-bottom:12px;">Saisissez le nombre de passagers montés à chaque ville étape.</p>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th style="width:40px;">#</th>
                                <th>Ville étape</th>
                                <th style="width:150px;">Passagers montés</th>
                            </tr>
                        </thead>
                        <tbody id="etapes-container">
                            <?php if (!empty($etapes)): ?>
                                <?php $i=1; foreach ($etapes as $e): ?>
                                <tr>
                                    <td><?= $i ?></td>
                                    <td><input type="text" name="ville[]" class="form-control" value="<?= e($e['ville']) ?>" style="min-width:180px;"></td>
                                    <td><input type="number" name="passagers[]" class="form-control etape-passagers" value="<?= (int)$e['passagers_montees'] ?>" min="0" style="width:120px;"></td>
                                </tr>
                                <?php $i++; endforeach; ?>
                            <?php else: ?>
                                <?php foreach ($defaultEtapes as $i => $ville): ?>
                                <tr>
                                    <td><?= $i+1 ?></td>
                                    <td><input type="text" name="ville[]" class="form-control" value="<?= e($ville) ?>" style="min-width:180px;"></td>
                                    <td><input type="number" name="passagers[]" class="form-control etape-passagers" value="0" min="0" style="width:120px;"></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div style="margin:12px 0;">
                    <button type="button" class="btn btn-sm btn-ghost" id="addEtapeBtn">➕ Ajouter une étape</button>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>👥 Total passagers</label>
                        <input type="number" name="total_passagers" id="total_passagers" class="form-control" value="<?= (int)$b['total_passagers'] ?>" readonly>
                    </div>
                    <div class="form-group">
                        <label>📊 Taux de remplissage (%)</label>
                        <input type="number" step="0.1" name="taux_remplissage" id="taux_remplissage" class="form-control" value="<?= $b['taux_remplissage'] ?>" readonly>
                    </div>
                </div>

                <div class="form-group">
                    <label>📝 Observation</label>
                    <textarea name="observation" class="form-control"><?= e($b['observation']) ?></textarea>
                </div>

                <div class="form-actions">
                    <a href="index.php?page=bulletins" class="btn btn-ghost">Annuler</a>
                    <button type="submit" class="btn btn-primary">💾 Enregistrer le bulletin</button>
                </div>
            </form>
        </div>

        <script>
        document.getElementById('addEtapeBtn')?.addEventListener('click', function() {
            const tbody = document.getElementById('etapes-container');
            const row = tbody.insertRow();
            const num = tbody.children.length;
            row.innerHTML = `
                <td>${num}</td>
                <td><input type="text" name="ville[]" class="form-control" style="min-width:180px;"></td>
                <td><input type="number" name="passagers[]" class="form-control etape-passagers" value="0" min="0" style="width:120px;"></td>
            `;
            row.querySelector('.etape-passagers')?.addEventListener('input', recalcTotal);
        });
        </script>
        <?php
        break;

    default: // list
        requirePermission('bulletins.view');
        $page = isset($_GET['p']) ? (int)$_GET['p'] : 1;
        $perPage = 20;
        $offset = ($page - 1) * $perPage;

        $total = $db->query("SELECT COUNT(*) FROM bulletins")->fetchColumn();
        $totalPages = ceil($total / $perPage);

        $bulletins = $db->query("
            SELECT b.*, v.immatriculation, a1.nom as agence_dep, a2.nom as agence_dest, c.nom as chauffeur_nom
            FROM bulletins b
            LEFT JOIN vehicules v ON b.vehicule_id = v.id
            LEFT JOIN agences a1 ON b.agence_depart_id = a1.id
            LEFT JOIN agences a2 ON b.agence_destination_id = a2.id
            LEFT JOIN chauffeurs c ON b.chauffeur_id = c.id
            ORDER BY b.date_voyage DESC, b.created_at DESC
            LIMIT $perPage OFFSET $offset
        ")->fetchAll();
        ?>
        <div class="actions-bar">
            <div class="search-box">
                <span class="search-icon">🔍</span>
                <input type="text" class="table-search" placeholder="Rechercher un bulletin...">
            </div>
            <?php if (hasPermission('bulletins.create')): ?>
            <a href="index.php?page=bulletins&action=create" class="btn btn-primary">➕ Nouveau bulletin</a>
            <?php endif; ?>
        </div>

        <?php if ($success = flash('success')): ?><div class="alert alert-success">✅ <?= e($success) ?></div><?php endif; ?>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">📋 Bulletins d'exploitation</h3>
                <span class="badge badge-info"><?= $total ?> bulletin(s)</span>
            </div>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>N°</th>
                            <th>Date</th>
                            <th>Véhicule</th>
                            <th>Chauffeur</th>
                            <th>Départ</th>
                            <th>Destination</th>
                            <th>Passagers</th>
                            <th>Taux</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($bulletins)): ?>
                        <tr><td colspan="9" style="text-align:center;padding:40px;color:var(--gray-400);">Aucun bulletin pour le moment. 📋</td></tr>
                        <?php else: foreach ($bulletins as $b): ?>
                        <tr>
                            <td><?= $b['id'] ?></td>
                            <td><?= formatDate($b['date_voyage']) ?></td>
                            <td><strong><?= e($b['immatriculation']) ?></strong></td>
                            <td><?= e($b['chauffeur_nom']) ?></td>
                            <td><?= e($b['agence_dep']) ?></td>
                            <td><?= e($b['agence_dest']) ?></td>
                            <td><strong><?= (int)$b['total_passagers'] ?></strong></td>
                            <td>
                                <span class="badge badge-<?= ($b['taux_remplissage'] ?? 0) >= 75 ? 'success' : (($b['taux_remplissage'] ?? 0) >= 50 ? 'warning' : 'danger') ?>">
                                    <?= $b['taux_remplissage'] ?>%
                                </span>
                            </td>
                            <td>
                                <div class="btn-group">
                                    <a href="index.php?page=bulletins&action=view&id=<?= $b['id'] ?>" class="btn-icon" title="Voir">👁️</a>
                                    <?php if (hasPermission('bulletins.edit')): ?>
                                    <a href="index.php?page=bulletins&action=edit&id=<?= $b['id'] ?>" class="btn-icon" title="Modifier">✏️</a>
                                    <?php endif; ?>
                                    <?php if (hasPermission('bulletins.delete')): ?>
                                    <a href="index.php?page=bulletins&action=delete&id=<?= $b['id'] ?>" class="btn-icon" data-confirm="Supprimer ce bulletin ?" title="Supprimer">🗑️</a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <a href="index.php?page=bulletins&p=<?= $i ?>" class="<?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
                <?php endfor; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php
        break;
}
