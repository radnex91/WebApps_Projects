<?php
namespace Controllers;

use Core\Controller;
use Core\Helpers;
use Models\Billet;
use Models\Voyage;
use Models\Client;
use Models\Caisse;
use Models\AuditLog;
use Models\SyncQueue;

class BilletController extends Controller
{
    public function index(): void
    {
        $this->requireAuth();
        $page = (int) ($this->get('page') ?? 1);
        $statut = $this->get('statut');
        $where = '';
        $params = [];

        if ($statut && in_array($statut, ['EN_ATTENTE', 'CONFIRME', 'ANNULE', 'REMBOURSE'])) {
            $where = 'statut = ?';
            $params = [$statut];
        }

        $billets = Billet::paginate($page, 20, $where, $params);
        $stats = Billet::getStats();

        $this->render('billets.index', [
            'billets' => $billets, 'stats' => $stats, 'statut' => $statut,
            'formatMoney' => [Helpers::class, 'formatMoney'],
        ]);
    }

    public function create(): void
    {
        $this->requireAuth();
        $voyages = Voyage::raw(
            "SELECT v.*, veh.immatriculation, CONCAT(ch.prenom, ' ', ch.nom) as chauffeur_nom
            FROM voyages v LEFT JOIN vehicules veh ON v.vehicule_id = veh.id
            LEFT JOIN chauffeurs ch ON v.chauffeur_id = ch.id
            WHERE v.statut IN ('PROGRAMME', 'EN_COURS') AND v.places_disponibles > 0
            ORDER BY v.date_depart ASC"
        );

        $this->render('billets.create', ['voyages' => $voyages, 'csrf' => $this->csrf]);
    }

    public function store(): void
    {
        $this->requireAuth();
        if (!$this->csrf->validate($this->post('_csrf_token'))) {
            $this->setFlash('error', 'Token CSRF invalide');
            $this->redirect(BASE_URL . '/billets/create');
        }

        $data = [
            'nom_passager' => $this->post('nom_passager'),
            'telephone_passager' => $this->post('telephone_passager'),
            'email_passager' => $this->post('email_passager'),
            'voyage_id' => (int) $this->post('voyage_id'),
            'siege' => $this->post('siege'),
            'montant' => (float) $this->post('montant'),
            'remise' => (float) ($this->post('remise') ?? 0),
            'mode_paiement' => $this->post('mode_paiement'),
            'notes' => $this->post('notes'),
        ];

        $taxe = round($data['montant'] * TVA);
        $data['taxe'] = $taxe;
        $data['montant_total'] = $data['montant'] + $taxe - $data['remise'];
        $data['reference'] = Billet::generateReference();
        $data['code_validation'] = Billet::generateCodeValidation();
        $data['statut'] = 'EN_ATTENTE';
        $data['created_by'] = $this->session->get('user_id');
        $data['synced'] = 1;
        $data['offline_id'] = null;

        $clientPhone = $this->post('telephone_client');
        if ($clientPhone) {
            $clients = Client::search($clientPhone);
            if (!empty($clients)) {
                $data['client_id'] = $clients[0]->id;
            }
        }

        $voyage = Voyage::find($data['voyage_id']);
        if (!$voyage) {
            $this->setFlash('error', 'Voyage introuvable');
            $this->redirect(BASE_URL . '/billets/create');
        }
        if ($voyage->places_disponibles <= 0) {
            $this->setFlash('error', 'Plus de places disponibles pour ce voyage');
            $this->redirect(BASE_URL . '/billets/create');
        }

        $db = \Core\Database::getInstance();
        $db->beginTransaction();
        try {
            $billet = new Billet($data);
            $billet->save();

            $voyage->places_disponibles = $voyage->places_disponibles - 1;
            $voyage->save();

            // Record in caisse
            $caisse = Caisse::whereFirst('type', '=', 'PRINCIPALE');
            if ($caisse) {
                $caisse->ajouterMouvement('ENTREE', $data['montant_total'],
                    "Billet {$data['reference']} - {$data['nom_passager']}",
                    null, $billet->id, $this->session->get('user_id'));
            }

            $db->commit();
            AuditLog::log($this->session->get('user_id'), 'CREATE', 'billet', $billet->id, null, $data);
            $this->setFlash('success', 'Billet créé avec succès - Réf: ' . $data['reference']);
            $this->redirect(BASE_URL . '/billets/' . $billet->id);
        } catch (\Exception $e) {
            $db->rollback();
            $this->setFlash('error', 'Erreur: ' . $e->getMessage());
            $this->redirect(BASE_URL . '/billets/create');
        }
    }

    public function show(int $id): void
    {
        $this->requireAuth();
        $billet = Billet::find($id);
        if (!$billet) {
            $this->setFlash('error', 'Billet introuvable');
            $this->redirect(BASE_URL . '/billets');
        }
        $voyage = Voyage::find($billet->voyage_id);

        $this->render('billets.show', [
            'billet' => $billet, 'voyage' => $voyage,
            'formatMoney' => [Helpers::class, 'formatMoney'],
        ]);
    }

    public function confirmer(int $id): void
    {
        $this->requireAuth();
        $billet = Billet::find($id);
        if (!$billet) { $this->setFlash('error', 'Billet introuvable'); $this->redirect(BASE_URL . '/billets'); }

        $billet->statut = 'CONFIRME';
        $billet->save();
        AuditLog::log($this->session->get('user_id'), 'CONFIRM', 'billet', $id);
        $this->setFlash('success', 'Billet confirmé');
        $this->redirect(BASE_URL . '/billets/' . $id);
    }

    public function annuler(int $id): void
    {
        $this->requireAuth();
        $billet = Billet::find($id);
        if (!$billet) { $this->setFlash('error', 'Billet introuvable'); $this->redirect(BASE_URL . '/billets'); }

        $db = \Core\Database::getInstance();
        $db->beginTransaction();
        try {
            $billet->statut = 'ANNULE';
            $billet->save();

            // Restore place
            $voyage = Voyage::find($billet->voyage_id);
            if ($voyage) {
                $voyage->places_disponibles = $voyage->places_disponibles + 1;
                $voyage->save();
            }

            // Reverse caisse movement
            $caisse = Caisse::whereFirst('type', '=', 'PRINCIPALE');
            if ($caisse && $billet->montant_total > 0) {
                $caisse->ajouterMouvement('SORTIE', $billet->montant_total,
                    "Annulation billet {$billet->reference}",
                    null, $billet->id, $this->session->get('user_id'));
            }

            $db->commit();
            AuditLog::log($this->session->get('user_id'), 'CANCEL', 'billet', $id);
            $this->setFlash('success', 'Billet annulé');
        } catch (\Exception $e) {
            $db->rollback();
            $this->setFlash('error', 'Erreur: ' . $e->getMessage());
        }
        $this->redirect(BASE_URL . '/billets/' . $id);
    }
}