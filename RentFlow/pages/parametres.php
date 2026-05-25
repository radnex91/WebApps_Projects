<?php
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'save_settings') {
            $fields = ['nom_entreprise', 'devise', 'date_format', 'jour_avance', 'jour_retard', 'theme', 'pays', 'police', 'rappels_auto', 'rappels_jours', 'email_expediteur'];
            foreach ($fields as $field) {
                $value = $_POST[$field] ?? '';
                if ($field === 'rappels_auto') {
                    $value = isset($_POST['rappels_auto']) ? '1' : '0';
                }
                $stmt = $pdo->prepare("INSERT INTO parametres (cle, valeur) VALUES (?, ?) ON DUPLICATE KEY UPDATE valeur = VALUES(valeur)");
                $stmt->execute([$field, $value]);
            }
            $message = '<div class="alert alert-success">Paramètres enregistrés avec succès.</div>';
        }
    }
}

$rappels_auto = getParam('rappels_auto', '1');
$rappels_jours = getParam('rappels_jours', '3');
$email_expediteur = getParam('email_expediteur', 'noreply@rentflow.fr');
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Paramètres</h2>
</div>

<?php echo $message; ?>

<div class="row">
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header"><h5 class="mb-0">Général</h5></div>
            <div class="card-body">
                <form method="post">
                    <input type="hidden" name="action" value="save_settings">
                    <div class="mb-3">
                        <label class="form-label">Nom de l'entreprise</label>
                        <input type="text" name="nom_entreprise" class="form-control" value="<?php echo getParam('nom_entreprise', 'RentFlow'); ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Devise</label>
                        <select name="devise" class="form-select">
                            <option value="FCFA" <?php echo getParam('devise') === 'FCFA' ? 'selected' : ''; ?>>FCFA</option>
                            <option value="EUR" <?php echo getParam('devise') === 'EUR' ? 'selected' : ''; ?>>Euro</option>
                            <option value="USD" <?php echo getParam('devise') === 'USD' ? 'selected' : ''; ?>>Dollar</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Format de date</label>
                        <select name="date_format" class="form-select">
                            <option value="d/m/Y" <?php echo getParam('date_format') === 'd/m/Y' ? 'selected' : ''; ?>>JJ/MM/AAAA</option>
                            <option value="Y-m-d" <?php echo getParam('date_format') === 'Y-m-d' ? 'selected' : ''; ?>>AAAA-MM-JJ</option>
                            <option value="m/d/Y" <?php echo getParam('date_format') === 'm/d/Y' ? 'selected' : ''; ?>>MM/JJ/AAAA</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Jour limite avance (1-4)</label>
                        <input type="number" name="jour_avance" class="form-control" value="<?php echo getParam('jour_avance', 5); ?>" min="1" max="4">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Jour limite retard (5-15)</label>
                        <input type="number" name="jour_retard" class="form-control" value="<?php echo getParam('jour_retard', 10); ?>" min="5" max="15">
                    </div>
                    <button type="submit" class="btn btn-primary">Enregistrer</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header"><h5 class="mb-0">Rappels Automatiques</h5></div>
            <div class="card-body">
                <form method="post">
                    <input type="hidden" name="action" value="save_settings">
                    <div class="mb-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="rappels_auto" id="rappels_auto" <?php echo $rappels_auto === '1' ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="rappels_auto">Activer les rappels automatiques</label>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Délai avant rappel (jours après date limite)</label>
                        <input type="number" name="rappels_jours" class="form-control" value="<?php echo $rappels_jours; ?>" min="1" max="30">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email expediteur</label>
                        <input type="email" name="email_expediteur" class="form-control" value="<?php echo $email_expediteur; ?>">
                    </div>
                    <button type="submit" class="btn btn-primary">Enregistrer</button>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header"><h5 class="mb-0">Apparence</h5></div>
            <div class="card-body">
                <form method="post">
                    <input type="hidden" name="action" value="save_settings">
                    <div class="mb-3">
                        <label class="form-label">Thème</label>
                        <select name="theme" class="form-select">
                            <option value="blue" <?php echo getParam('theme') === 'blue' ? 'selected' : ''; ?>>Bleu</option>
                            <option value="indigo" <?php echo getParam('theme') === 'indigo' ? 'selected' : ''; ?>>Indigo</option>
                            <option value="purple" <?php echo getParam('theme') === 'purple' ? 'selected' : ''; ?>>Violet</option>
                            <option value="pink" <?php echo getParam('theme') === 'pink' ? 'selected' : ''; ?>>Rose</option>
                            <option value="red" <?php echo getParam('theme') === 'red' ? 'selected' : ''; ?>>Rouge</option>
                            <option value="orange" <?php echo getParam('theme') === 'orange' ? 'selected' : ''; ?>>Orange</option>
                            <option value="green" <?php echo getParam('theme') === 'green' ? 'selected' : ''; ?>>Vert</option>
                            <option value="dark" <?php echo getParam('theme') === 'dark' ? 'selected' : ''; ?>>Sombre</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">Enregistrer</button>
                </form>
            </div>
        </div>
    </div>
</div>