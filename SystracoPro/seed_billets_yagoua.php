<?php
// seed_billets_yagoua.php — Insère des données de test pour Yagoua
require_once 'includes/config.php';

$aid = 1; // Yagoua
$guichetier_id = 11; // guichetier Yagoua

// Noms de passagers camerounais pour le seed
$noms = [
    ['nom'=>'Moussa','prenom'=>'Abdoulaye'],['nom'=>'Amadou','prenom'=>'Bouba'],
    ['nom'=>'Issa','prenom'=>'Hassan'],['nom'=>'Mahamat','prenom'=>'Ali'],
    ['nom'=>'Abba','prenom'=>'Kader'],['nom'=>'Baba','prenom'=>'Ousmanou'],
    ['nom'=>'Djibril','prenom'=>'Moustapha'],['nom'=>'Sali','prenom'=>'Aichatou'],
    ['nom'=>'Fadimatou','prenom'=>'Halima'],['nom'=>'Zara','prenom'=>'Amina'],
    ['nom'=>'Khadija','prenom'=>'Fatima'],['nom'=>'Oumarou','prenom'=>'Saidou'],
    ['nom'=>'Hamadou','prenom'=>'Idriss'],['nom'=>'Youssouf','prenom'=>'Adamou'],
    ['nom'=>'Gambo','prenom'=>'Lawan'],['nom'=>'Boukar','prenom'=>'Mairiga'],
    ['nom'=>'Ardo','prenom'=>'Malloum'],['nom'=>'Djamila','prenom'=>'Aïssatou'],
    ['nom'=>'Nafissa','prenom'=>'Mariama'],['nom'=>'Salamatou','prenom'=>'Rahina'],
];

// Itinéraires existants depuis Yagoua (id, nom, destination_id approx)
$trajets = [
    ['itineraire_id'=>1,'destination_id'=>1,'nom'=>'Yagoua → Maroua','prix'=>5000],
    ['itineraire_id'=>3,'destination_id'=>3,'nom'=>'Yagoua → Kalfou','prix'=>2500],
    ['itineraire_id'=>4,'destination_id'=>4,'nom'=>'Yagoua → Guidiguis','prix'=>3500],
    ['itineraire_id'=>5,'destination_id'=>5,'nom'=>'Yagoua → Kaele','prix'=>4000],
];

// Vérifier/créer la destination et l'itinéraire vers Mokolo
$mokolo_agence = $pdo->query("SELECT id FROM agences WHERE ville='Mokolo' LIMIT 1")->fetchColumn();
if (!$mokolo_agence) {
    $pdo->prepare("INSERT INTO agences (code,nom,ville,actif) VALUES ('MKL','Mokolo','Mokolo',1)") ->execute([]);
    $mokolo_agence = $pdo->lastInsertId();
    echo "✓ Agence Mokolo créée (id=$mokolo_agence)\n";
} else {
    echo "✓ Agence Mokolo existe (id=$mokolo_agence)\n";
}

$mokolo_dest = $pdo->query("SELECT id FROM destinations WHERE agence_depart=$aid AND agence_arrivee=$mokolo_agence LIMIT 1")->fetchColumn();
if (!$mokolo_dest) {
    $pdo->prepare("INSERT INTO destinations (agence_depart,agence_arrivee,distance_km,duree_minutes,actif) VALUES (?,?,0,180,1)") ->execute([$aid,$mokolo_agence]);
    $mokolo_dest = $pdo->lastInsertId();
    echo "✓ Destination Yagoua→Mokolo créée (id=$mokolo_dest)\n";
} else {
    echo "✓ Destination Yagoua→Mokolo existe (id=$mokolo_dest)\n";
}

$mokolo_itin = $pdo->query("SELECT id FROM itineraires WHERE agence_depart=$aid AND agence_arrivee=$mokolo_agence LIMIT 1")->fetchColumn();
if (!$mokolo_itin) {
    $pdo->prepare("INSERT INTO itineraires (code,nom,agence_depart,agence_arrivee,distance_km,duree_minutes,actif) VALUES (?,?,?,?,0,180,1)") ->execute(['YGA-MKL','Yagoua - Mokolo',$aid,$mokolo_agence]);
    $mokolo_itin = $pdo->lastInsertId();
    echo "✓ Itinéraire Yagoua→Mokolo créé (id=$mokolo_itin)\n";
} else {
    echo "✓ Itinéraire Yagoua→Mokolo existe (id=$mokolo_itin)\n";
}

$trajets[] = ['itineraire_id'=>$mokolo_itin,'destination_id'=>$mokolo_dest,'nom'=>'Yagoua → Mokolo','prix'=>6000];

// Récupérer les tarifs existants pour chaque destination
$tarifMap = [];
foreach ($trajets as $t) {
    $st = $pdo->prepare("SELECT classe, prix FROM tarifs WHERE destination_id=? AND actif=1");
    $st->execute([$t['destination_id']]);
    $rows = $st->fetchAll(PDO::FETCH_KEY_PAIR);
    $tarifMap[$t['destination_id']] = $rows;
}
echo "✓ Tarifs existants récupérés\n";

// Créer 1 voyage par trajet pour demain
$date_dep = date('Y-m-d 06:00:00', strtotime('+1 day'));
$vehicule_id = $pdo->query("SELECT id FROM vehicules WHERE statut='actif' LIMIT 1")->fetchColumn() ?: null;
$chauffeur_id = $pdo->query("SELECT id FROM personnel WHERE statut='actif' AND fonction='chauffeur' LIMIT 1")->fetchColumn() ?: null;

foreach ($trajets as $t) {
    $num = genNumero($pdo, 'voyages', 'numero', getParam('prefix_voyage','VOY'));
    $vc = $pdo->query("SELECT capacite FROM vehicules WHERE id=".(int)$vehicule_id);
    $places = (int)$vc->fetchColumn() ?: 70;

    $pdo->prepare("INSERT INTO voyages (numero,vehicule_id,chauffeur_id,itineraire_id,destination_id,agence_id,date_depart,classe_voyage,places_dispo,montant_carburant,montant_peage,created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)")
        ->execute([$num,$vehicule_id,$chauffeur_id,$t['itineraire_id'],$t['destination_id'],$aid,$date_dep,'cla',$places,25000,5000,$guichetier_id]);
    $vid = $pdo->lastInsertId();

    // Créer 3-8 tickets par voyage
    $classes_dispo = array_keys($tarifMap[$t['destination_id']] ?? ['cla'=>3000]);
    if (empty($classes_dispo)) {
        $classes_dispo = ['cla'];
        $tarifMap[$t['destination_id']] = ['cla'=>3000];
    }
    $nb_tickets = rand(3,8);
    for ($i=0;$i<$nb_tickets;$i++) {
        $passager = $noms[array_rand($noms)];
        $cl = $classes_dispo[array_rand($classes_dispo)];
        $montant = (float)($tarifMap[$t['destination_id']][$cl] ?? 3000);
        $num_tkt = genNumero($pdo, 'tickets', 'numero', getParam('prefix_ticket','TKT'));
        $siege = $i+1;
        $mode = ['especes','om','momo'][array_rand(['especes','om','momo'])];

        $pdo->prepare("INSERT INTO tickets (numero,voyage_id,passager_nom,passager_tel,siege,classe,montant,montant_total,statut,mode_paiement,agence_id,guichetier_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)")
            ->execute([$num_tkt,$vid,$passager['prenom'].' '.$passager['nom'],'6'.rand(90000000,99999999),$siege,$cl,$montant,$montant,'vendu',$mode,$aid,$guichetier_id]);
    }

    echo "✓ Voyage $num ({$t['nom']}) créé avec $nb_tickets tickets\n";
}

echo "\n🎉 Seed terminé !\n";
