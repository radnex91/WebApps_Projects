<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/database.php';

try {
    Database::execute("CREATE TABLE IF NOT EXISTS itineraires (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nom VARCHAR(100) NOT NULL,
        description TEXT,
        actif TINYINT(1) DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB");
} catch (Exception $e) { echo "Table itineraires: " . $e->getMessage() . "<br>"; }

try {
    Database::execute("CREATE TABLE IF NOT EXISTS itinerary_etapes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        itineraires_id INT NOT NULL,
        ordre INT NOT NULL,
        agences_id INT NOT NULL,
        FOREIGN KEY (itineraires_id) REFERENCES itineraires(id) ON DELETE CASCADE,
        FOREIGN KEY (agences_id) REFERENCES agences(id)
    ) ENGINE=InnoDB");
} catch (Exception $e) { echo "Table itinerary_etapes: " . $e->getMessage() . "<br>"; }

$agences = Database::fetchAll("SELECT id, nom FROM agences ORDER BY nom");

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'create') {
    $nom = trim($_POST['nom']);
    $etapes = $_POST['etapes'] ?? [];
    
    if ($nom && count($etapes) >= 2) {
        $id = Database::insert("INSERT INTO itineraires (nom, description) VALUES (?, ?)", [$nom, $_POST['description'] ?? '']);
        
        foreach ($etapes as $ordre => $agenceId) {
            Database::execute("INSERT INTO itinerary_etapes (itineraires_id, ordre, agences_id) VALUES (?, ?, ?)", [$id, $ordre + 1, $agenceId]);
        }
        
        echo "<p style='color:green'>Itineraire cree</p>";
    } else {
        echo "<p style='color:red'>Nom requis et au moins 2 etapes</p>";
    }
}

$itineraires = Database::fetchAll("SELECT * FROM itineraires ORDER BY nom");

echo "<!DOCTYPE html><html><head><title>Itineraires</title>";
echo "<style>body{font-family:Arial;padding:20px;background:#f5f5f5}.card{background:white;padding:20px;border-radius:8px;max-width:800px;margin:20px auto;box-shadow:0 2px 8px rgba(0,0,0,0.1)}h2{margin-top:0}.item{background:#f9f9f9;padding:12px;margin:8px 0;border-radius:4px}.badge{background:#28a745;color:white;padding:2px 8px;border-radius:4px;font-size:12px}.form-group{margin-bottom:12px}label{display:block;margin-bottom:4px;font-weight:600}input,select,textarea{width:100%;padding:8px;border:1px solid #ddd;border-radius:4px;box-sizing:border-box}.btn{background:#0066cc;color:white;padding:10px 20px;border:none;border-radius:4px;cursor:pointer}.btn:hover{background:#0052a3}</style></head><body>";
echo "<div class='card'><h2>Creer un itineraire</h2>";
echo "<form method='post'><input type='hidden' name='action' value='create'>";
echo "<div class='form-group'><label>Nom de l'itineraire</label><input type='text' name='nom' required></div>";
echo "<div class='form-group'><label>Description</label><textarea name='description' rows='2'></textarea></div>";
echo "<div class='form-group'><label>Etapes (selectionnez dans l'ordre)</label>";
echo "<select name='etapes[]' id='etape1' onchange='addEtape()'><option value=''>-- Selectionner depart --</option>";
foreach ($agences as $a) echo "<option value='{$a['id']}'>{$a['nom']}</option>";
echo "</select><div id='etapes'></div></div>";
echo "<button type='submit' class='btn'>Creer</button></form></div>";

echo "<div class='card'><h2>Itineraires existants</h2>";
foreach ($itineraires as $it) {
    $etapes = Database::fetchAll("SELECT a.nom FROM itinerary_etapes e JOIN agences a ON e.agences_id=a.id WHERE e.itineraires_id=? ORDER BY e.ordre", [$it['id']]);
    $villes = implode(' → ', array_column($etapes, 'nom'));
    echo "<div class='item'><strong>{$it['nom']}</strong> <span class='badge'>" . count($etapes) . " etapes</span><br><small style='color:#666'>$villes</small></div>";
}
echo "</div>";

echo "<script>let count=1;function addEtape(){if(count>=6)return;let s=document.createElement('select');s.name='etapes[]';s.innerHTML=document.getElementById('etape1').innerHTML;s.onchange=addEtape;document.getElementById('etapes').appendChild(s);count++;}</script>";
echo "</body></html>";
