<?php
namespace Controllers;

use Core\Controller;
use Models\Vehicule;

class VehiculeController extends Controller
{
    public function index(): void
    {
        $this->requireAuth();
        $vehicules = Vehicule::paginate((int) ($this->get('page') ?? 1));
        $this->render('vehicules.index', ['vehicules' => $vehicules]);
    }

    public function create(): void
    {
        $this->requireAuth();
        $this->requireRole('admin');
        $this->render('vehicules.create', ['csrf' => $this->csrf]);
    }

    public function store(): void
    {
        $this->requireAuth();
        $this->requireRole('admin');
        $vehicule = new Vehicule($this->post());
        $vehicule->save();
        $this->setFlash('success', 'Véhicule ajouté');
        $this->redirect(BASE_URL . '/vehicules');
    }
}