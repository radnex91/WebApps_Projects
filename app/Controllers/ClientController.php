<?php
namespace App\Controllers;
use App\Core\Controller;
use App\Helpers\Auth;
use App\Core\Database;
use App\Models\Ticket;
use App\Models\Category;
use App\Models\Priority;
use App\Models\Status;
class ClientController extends Controller
{
    public function __construct()
    {
        parent::__construct();
        Auth::requireRole('client');
    }
    public function index(): void
    {
        $userId = Auth::id();
        $myTickets = Database::fetchAll(
            "SELECT t.id, t.reference, t.titre as title, t.description, t.user_id as created_by, t.categorie_id as category_id, t.priorite_id as priority_id, t.statut_id as status_id, t.technicien_id as assigned_to, t.date_souhaitee, t.archive as archived, t.created_at, t.updated_at, c.nom as category_name, p.nom as priority_name, s.nom as status_name
             FROM tickets t
             LEFT JOIN categories c ON t.categorie_id = c.id
             LEFT JOIN priorities p ON t.priorite_id = p.id
             LEFT JOIN statuses s ON t.statut_id = s.id
             WHERE t.user_id = :userId AND t.archive = 0
             ORDER BY t.created_at DESC",
            ['userId' => $userId]
        );
        $openCount = Ticket::countWhere('statut_id', 1);
        $resolvedCount = Ticket::countWhere('statut_id', 4);
        $this->layout('main', 'client/dashboard', [
            'myTickets' => $myTickets,
            'openCount' => $openCount,
            'resolvedCount' => $resolvedCount,
        ]);
    }
    public function show(int $id): void
    {
        $ticket = Database::fetch(
            "SELECT t.id, t.reference, t.titre as title, t.description, t.user_id as created_by, t.categorie_id as category_id, t.priorite_id as priority_id, t.statut_id as status_id, t.technicien_id as assigned_to, t.date_souhaitee, t.archive as archived, t.created_at, t.updated_at, c.nom as category_name, p.nom as priority_name, p.couleur as priority_color, s.nom as status_name
             FROM tickets t
             LEFT JOIN categories c ON t.categorie_id = c.id
             LEFT JOIN priorities p ON t.priorite_id = p.id
             LEFT JOIN statuses s ON t.statut_id = s.id
             WHERE t.id = :id",
            ['id' => $id]
        );
        if (!$ticket || $ticket->created_by != Auth::id()) {
            $this->setFlash('danger', 'Ticket introuvable');
            $this->redirect('/gestion-support/client/dashboard');
        }
        $comments = Database::fetchAll(
            "SELECT co.id, co.ticket_id, co.user_id, co.contenu as content, co.est_interne, co.created_at, u.nom as user_name, u.role as user_role
             FROM comments co
             LEFT JOIN users u ON co.user_id = u.id
             WHERE co.ticket_id = :id
             ORDER BY co.created_at ASC",
            ['id' => $id]
        );
        $history = Database::fetchAll(
            "SELECT th.id, th.ticket_id, th.user_id, th.champ as action, th.ancienne_valeur, th.nouvelle_valeur as details, th.created_at, u.nom as user_name
             FROM ticket_history th
             LEFT JOIN users u ON th.user_id = u.id
             WHERE th.ticket_id = :id
             ORDER BY th.created_at DESC",
            ['id' => $id]
        );
        $statuses = Status::all();
        $this->layout('main', 'tickets/show', [
            'ticket'   => $ticket,
            'comments' => $comments,
            'history'  => $history,
            'statuses' => $statuses,
            'technicians' => [],
        ]);
    }
    public function create(): void
    {
        $categories = Category::all();
        $priorities = Priority::all();
        $this->layout('main', 'tickets/create', ['categories' => $categories, 'priorities' => $priorities]);
    }
    public function store(): void
    {
        $data = [
            'titre'       => $this->post('title'),
            'description' => $this->post('description'),
            'categorie_id' => $this->post('category_id'),
            'priorite_id' => $this->post('priority_id'),
            'statut_id'   => 1,
            'user_id'     => Auth::id(),
        ];
        $ticketId = Ticket::create($data);
        $this->setFlash('success', 'Ticket créé avec succès');
        $this->redirect('/gestion-support/client/dashboard');
    }
    public function addComment(int $id): void
    {
        \App\Models\Comment::create([
            'ticket_id' => $id,
            'user_id'   => Auth::id(),
            'contenu'   => $this->post('content'),
        ]);
        $this->setFlash('success', 'Commentaire ajouté');
        $this->redirect('/gestion-support/client/dashboard');
    }
}
