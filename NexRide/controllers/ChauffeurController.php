<?php
namespace Controllers;

use Core\Controller;
use Models\Chauffeur;

class ChauffeurController extends Controller
{
    public function index(): void
    {
        $this->requireAuth();
        $chauffeurs = Chauffeur::paginate((int) ($this->get('page') ?? 1));
        $this->render('chauffeurs.index', ['chauffeurs' => $chauffeurs]);
    }

    public function create(): void
    {
        $this->requireAuth();
        $this->requireRole('admin');
        $this->render('chauffeurs.create', ['csrf' => $this->csrf]);
    }

    public function store(): void
    {
        $this->requireAuth();
        $this->requireRole('admin');
        $chauffeur = new Chauffeur($this->post());
        $chauffeur->save();
        $this->setFlash('success', 'Chauffeur ajouté');
        $this->redirect(BASE_URL . '/chauffeurs');
    }
}