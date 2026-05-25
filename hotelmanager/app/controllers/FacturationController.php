<?php
/**
 * HotelPro Suite - FacturationController
 * Génération et gestion des factures et paiements
 */

class FacturationController
{
    public function index(): void
    {
        $page_num = max(1, (int)($_GET['p'] ?? 1));
        $offset   = ($page_num - 1) * ITEMS_PER_PAGE;

        $total = (int) Database::query(
            "SELECT COUNT(*) FROM factures"
        )->fetchColumn();

        $factures = Database::query(
            "SELECT f.*, CONCAT(c.prenom,' ',c.nom) AS client_nom, r.reference AS res_ref
             FROM factures f
             JOIN clients c ON c.id = f.client_id
             JOIN reservations r ON r.id = f.reservation_id
             ORDER BY f.created_at DESC
             LIMIT ? OFFSET ?",
            [ITEMS_PER_PAGE, $offset]
        )->fetchAll();

        $total_pages = ceil($total / ITEMS_PER_PAGE);

        require APP_ROOT . '/app/views/layouts/header.php';
        require APP_ROOT . '/app/views/facturation/index.php';
        require APP_ROOT . '/app/views/layouts/footer.php';
    }

    /**
     * Génère automatiquement une facture à partir d'une réservation
     */
    public function generate(): void
    {
        $res_id = (int)($_GET['reservation_id'] ?? 0);

        $res = Database::query(
            "SELECT r.*, CONCAT(c.prenom,' ',c.nom) AS client_nom
             FROM reservations r JOIN clients c ON c.id = r.client_id
             WHERE r.id = ?",
            [$res_id]
        )->fetch();

        if (!$res) {
            set_flash('danger', 'Réservation introuvable.');
            redirect(APP_URL . '/index.php?page=reservations');
        }

        // Vérifie qu'une facture n'existe pas déjà
        $exists = Database::query(
            "SELECT id FROM factures WHERE reservation_id = ? AND statut != 'annulee'",
            [$res_id]
        )->fetch();

        if ($exists) {
            set_flash('warning', 'Une facture existe déjà pour cette réservation.');
            redirect(APP_URL . '/index.php?page=facturation&action=show&id=' . $exists['id']);
        }

        $sous_total   = (float)$res['montant_total'];
        $tva_taux     = HOTEL_TVA;
        $tva_montant  = round($sous_total * $tva_taux / 100, 2);
        $total_ttc    = $sous_total + $tva_montant;
        $reference    = generate_reference('FAC', 'factures');

        $pdo = Database::getInstance();
        $pdo->beginTransaction();
        try {
            Database::query(
                "INSERT INTO factures
                 (reference, reservation_id, client_id, user_id, sous_total, tva_taux, tva_montant, remise, total_ttc, statut, date_emission)
                 VALUES (?,?,?,?,?,?,?,0,?,?,?)",
                [
                    $reference,
                    $res_id,
                    $res['client_id'],
                    $_SESSION['user_id'],
                    $sous_total,
                    $tva_taux,
                    $tva_montant,
                    $total_ttc,
                    'emise',
                    date('Y-m-d'),
                ]
            );
            $fac_id = (int) Database::lastInsertId();
            $pdo->commit();

            log_action('generate_invoice', 'facturation', $fac_id, 'facture', "Facture $reference générée");
            set_flash('success', "Facture <strong>$reference</strong> générée avec succès !");
            redirect(APP_URL . '/index.php?page=facturation&action=show&id=' . $fac_id);
        } catch (Exception $e) {
            $pdo->rollBack();
            set_flash('danger', 'Erreur lors de la génération de la facture.');
            redirect(APP_URL . '/index.php?page=reservations');
        }
    }

    public function show(): void
    {
        $id = (int)($_GET['id'] ?? 0);
        $facture = $this->getFactureById($id);

        if (!$facture) {
            set_flash('danger', 'Facture introuvable.');
            redirect(APP_URL . '/index.php?page=facturation');
        }

        $paiements = Database::query(
            "SELECT p.*, CONCAT(u.prenom,' ',u.nom) AS encaisse_par
             FROM paiements p JOIN users u ON u.id = p.user_id
             WHERE p.facture_id = ? ORDER BY p.date_paiement",
            [$id]
        )->fetchAll();

        $montant_paye = array_sum(array_column($paiements, 'montant'));
        $reste_a_payer = $facture['total_ttc'] - $montant_paye;

        require APP_ROOT . '/app/views/layouts/header.php';
        require APP_ROOT . '/app/views/facturation/show.php';
        require APP_ROOT . '/app/views/layouts/footer.php';
    }

    /**
     * Enregistre un paiement pour une facture
     */
    public function pay(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf()) {
            redirect(APP_URL . '/index.php?page=facturation');
        }

        $data       = sanitize($_POST);
        $facture_id = (int)($data['facture_id'] ?? 0);
        $montant    = (float)($data['montant'] ?? 0);
        $mode       = $data['mode'] ?? 'especes';
        $ref_pai    = $data['reference_paiement'] ?? null;

        if (!$facture_id || $montant <= 0) {
            set_flash('danger', 'Données de paiement invalides.');
            redirect(APP_URL . '/index.php?page=facturation&action=show&id=' . $facture_id);
        }

        $facture = $this->getFactureById($facture_id);
        if (!$facture) {
            set_flash('danger', 'Facture introuvable.');
            redirect(APP_URL . '/index.php?page=facturation');
        }

        $pdo = Database::getInstance();
        $pdo->beginTransaction();
        try {
            Database::query(
                "INSERT INTO paiements (facture_id, user_id, montant, mode, reference_paiement) VALUES (?,?,?,?,?)",
                [$facture_id, $_SESSION['user_id'], $montant, $mode, $ref_pai]
            );

            // Vérifie si la facture est entièrement payée
            $total_paye = (float) Database::query(
                "SELECT COALESCE(SUM(montant),0) FROM paiements WHERE facture_id = ?",
                [$facture_id]
            )->fetchColumn();

            if ($total_paye >= $facture['total_ttc']) {
                Database::query("UPDATE factures SET statut = 'payee' WHERE id = ?", [$facture_id]);
            }

            $pdo->commit();
            log_action('payment', 'facturation', $facture_id, 'facture', "Paiement de $montant FCFA ($mode)");
            set_flash('success', 'Paiement de ' . format_money($montant) . ' enregistré.');
        } catch (Exception $e) {
            $pdo->rollBack();
            set_flash('danger', 'Erreur lors de l\'enregistrement du paiement.');
        }

        redirect(APP_URL . '/index.php?page=facturation&action=show&id=' . $facture_id);
    }

    /**
     * Affiche la facture version impression (sans layout)
     */
    public function print(): void
    {
        $id = (int)($_GET['id'] ?? 0);
        $facture = $this->getFactureById($id);

        if (!$facture) {
            set_flash('danger', 'Facture introuvable.');
            redirect(APP_URL . '/index.php?page=facturation');
        }

        $paiements = Database::query(
            "SELECT * FROM paiements WHERE facture_id = ?",
            [$id]
        )->fetchAll();

        require APP_ROOT . '/app/views/facturation/print.php';
    }

    private function getFactureById(int $id): ?array
    {
        $fac = Database::query(
            "SELECT f.*, CONCAT(c.prenom,' ',c.nom) AS client_nom, c.telephone, c.email AS client_email,
                    c.adresse AS client_adresse, c.ville, c.pays,
                    r.reference AS res_ref, r.date_arrivee, r.date_depart, r.nb_nuits,
                    ch.numero AS chambre_num, tc.nom AS type_chambre,
                    CONCAT(u.prenom,' ',u.nom) AS emis_par
             FROM factures f
             JOIN clients c ON c.id = f.client_id
             JOIN reservations r ON r.id = f.reservation_id
             JOIN chambres ch ON ch.id = r.chambre_id
             JOIN types_chambres tc ON tc.id = ch.type_id
             JOIN users u ON u.id = f.user_id
             WHERE f.id = ?",
            [$id]
        )->fetch();
        return $fac ?: null;
    }
}
