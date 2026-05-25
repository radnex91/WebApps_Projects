<?php
namespace Controllers;

use Core\Controller;
use Core\Helpers;
use Models\Voyage;
use Models\Vehicule;
use Models\Chauffeur;
use Models\Billet;
use Models\AuditLog;

class VoyageController extends Controller
{
    public function index(): void
    {
        $this->requireAuth();
        $page = (int) ($this->get('page') ?? 1);
        $statut = $this->get('statut');
        $where = '';
        $params = [];

        if ($statut && in_array($statut, ['PROGRAMME', 'EN_COURS', 'TERMINE', 'ANNULE'])) {
            $where = 'statut = ?';
            $params = [$statut];
        }

        $voyages = Voyage::paginate($page, 20, $where, $params);
        $stats = Voyage::getStats();

        $this->render('voyages.index', [
            'voyages' => $voyages, 'stats' => $stats, 'statut' => $statut,
            'formatMoney' => [Helpers::class, 'formatMoney'],
        ]);
    }

    public function create(): void
    {
        $this->requireAuth();
        $this->requireRole('admin');
        $vehicules = Vehicule::getDisponibles();
        $chauffeurs = Chauffeur::getDisponibles();

        $this->render('voyages.create', [
            'vehicules' => $vehicules, 'chauffeurs' => $chauffeurs, 'csrf' => $this->csrf,
        ]);
    }

    public function store(): void
    {
        $this->requireAuth();
        $this->requireRole('admin');
        if (!$this->csrf->validate($this->post('_csrf_token'))) {
            $this->setFlash('error', 'Token CSRF invalide');
            $this->redirect(BASE_URL . '/voyages/create');
        }

        $data = [
            'reference' => Voyage::generateReference(),
            'titre' => $this->post('titre'),
            'type_voyage' => $this->post('type_voyage'),
            'depart' => $this->post('depart'),
            'destination' => $this->post('destination'),
            'date_depart' => $this->post('date_depart'),
            'date_arrivee' => $this->post('date_arrivee') ?: null,
            'vehicule_id' => (int) $this->post('vehicule_id') ?: null,
            'chauffeur_id' => (int) $this->post('chauffeur_id') ?: null,
            'tarif_base' => (float) $this->post('tarif_base'),
            'notes' => $this->post('notes'),
            'statut' => 'PROGRAMME',
            'created_by' => $this->session->get('user_id'),
        ];

        if ($data['vehicule_id']) {
            $vehicule = Vehicule::find($data['vehicule_id']);
            $data['nombre_places'] = $vehicule ? $vehicule->capacite : 4;
        } else {
            $data['nombre_places'] = (int) ($this->post('nombre_places') ?? 4);
        }
        $data['places_disponibles'] = $data['nombre_places'];

        $voyage = new Voyage($data);
        $voyage->save();

        AuditLog::log($this->session->get('user_id'), 'CREATE', 'voyage', $voyage->id, null, $data);
        $this->setFlash('success', 'Voyage créé - Réf: ' . $data['reference']);
        $this->redirect(BASE_URL . '/voyages/' . $voyage->id);
    }

    public function show(int $id): void
    {
        $this->requireAuth();
        $voyage = Voyage::find($id);
        if (!$voyage) {
            $this->setFlash('error', 'Voyage introuvable');
            $this->redirect(BASE_URL . '/voyages');
        }
        $billets = Billet::getByVoyage($id);
        $vehicule = $voyage->vehicule_id ? Vehicule::find($voyage->vehicule_id) : null;
        $chauffeur = $voyage->chauffeur_id ? Chauffeur::find($voyage->chauffeur_id) : null;

        $this->render('voyages.show', [
            'voyage' => $voyage, 'billets' => $billets,
            'vehicule' => $vehicule, 'chauffeur' => $chauffeur,
            'formatMoney' => [Helpers::class, 'formatMoney'],
        ]);
    }

    public function changerStatut(int $id): void
    {
        $this->requireAuth();
        $voyage = Voyage::find($id);
        if (!$voyage) { $this->setFlash('error', 'Voyage introuvable'); $this->redirect(BASE_URL . '/voyages'); }

        $nouveauStatut = $this->post('statut');
        if (!in_array($nouveauStatut, ['PROGRAMME', 'EN_COURS', 'TERMINE', 'ANNULE'])) {
            $this->setFlash('error', 'Statut invalide');
            $this->redirect(BASE_URL . '/voyages/' . $id);
        }

        $oldStatut = $voyage->statut;
        $voyage->statut = $nouveauStatut;
        $voyage->save();

        AuditLog::log($this->session->get('user_id'), 'STATUS_CHANGE', 'voyage', $id, ['statut' => $oldStatut], ['statut' => $nouveauStatut]);
        $this->setFlash('success', 'Statut du voyage mis à jour');
        $this->redirect(BASE_URL . '/voyages/' . $id);
    }
}