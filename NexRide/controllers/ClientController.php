<?php
namespace Controllers;

use Core\Controller;
use Core\Helpers;
use Models\Client;

class ClientController extends Controller
{
    public function index(): void
    {
        $this->requireAuth();
        $page = (int) ($this->get('page') ?? 1);
        $query = $this->get('q');
        $where = '';
        $params = [];

        if ($query) {
            $where = 'nom LIKE ? OR prenom LIKE ? OR telephone LIKE ?';
            $params = ["%{$query}%", "%{$query}%", "%{$query}%"];
        }

        $clients = Client::paginate($page, 20, $where, $params);
        $this->render('clients.index', ['clients' => $clients, 'query' => $query]);
    }

    public function create(): void
    {
        $this->requireAuth();
        $this->render('clients.create', ['csrf' => $this->csrf]);
    }

    public function store(): void
    {
        $this->requireAuth();
        $client = new Client($this->post());
        $client->save();
        $this->setFlash('success', 'Client créé');
        $this->redirect(BASE_URL . '/clients');
    }

    public function show(int $id): void
    {
        $this->requireAuth();
        $client = Client::find($id);
        if (!$client) { $this->setFlash('error', 'Client introuvable'); $this->redirect(BASE_URL . '/clients'); }
        $this->render('clients.show', ['client' => $client]);
    }
}