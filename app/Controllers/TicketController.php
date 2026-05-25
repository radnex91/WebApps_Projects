<?php
namespace App\Controllers;
use App\Core\Controller;
use App\Core\Database;
use App\Helpers\Auth;
use App\Models\Ticket;
use App\Models\Category;
use App\Models\Priority;
use App\Models\Status;
use App\Models\User;
use App\Models\Comment;
use App\Models\TicketHistory;
use App\Models\Notification;
class TicketController extends Controller
{
    public function __construct()
    {
        parent::__construct();
        Auth::requireAuth();
    }
    public function index(): void
    {
        $role = Auth::role();
        $userId = Auth::id();
        if ($role === 'admin') {
            $tickets = Database::fetchAll(
                "SELECT t.id, t.reference, t.titre as title, t.description, t.user_id as created_by, t.categorie_id as category_id, t.priorite_id as priority_id, t.statut_id as status_id, t.technicien_id as assigned_to, t.date_souhaitee, t.archive as archived, t.created_at, t.updated_at, c.nom as category_name, p.nom as priority_name, s.nom as status_name, u.nom as assigned_name
                 FROM tickets t
                 LEFT JOIN categories c ON t.categorie_id = c.id
                 LEFT JOIN priorities p ON t.priorite_id = p.id
                 LEFT JOIN statuses s ON t.statut_id = s.id
                 LEFT JOIN users u ON t.technicien_id = u.id
                 WHERE t.archive = 0
                 ORDER BY t.created_at DESC"
            );
        } elseif ($role === 'technicien') {
            $tickets = Database::fetchAll(
                "SELECT t.id, t.reference, t.titre as title, t.description, t.user_id as created_by, t.categorie_id as category_id, t.priorite_id as priority_id, t.statut_id as status_id, t.technicien_id as assigned_to, t.date_souhaitee, t.archive as archived, t.created_at, t.updated_at, c.nom as category_name, p.nom as priority_name, s.nom as status_name, u.nom as assigned_name
                 FROM tickets t
                 LEFT JOIN categories c ON t.categorie_id = c.id
                 LEFT JOIN priorities p ON t.priorite_id = p.id
                 LEFT JOIN statuses s ON t.statut_id = s.id
                 LEFT JOIN users u ON t.technicien_id = u.id
                 WHERE (t.technicien_id = :userId OR t.user_id = :userId2) AND t.archive = 0
                 ORDER BY t.created_at DESC",
                ['userId' => $userId, 'userId2' => $userId]
            );
        } else {
            $tickets = Database::fetchAll(
                "SELECT t.id, t.reference, t.titre as title, t.description, t.user_id as created_by, t.categorie_id as category_id, t.priorite_id as priority_id, t.statut_id as status_id, t.technicien_id as assigned_to, t.date_souhaitee, t.archive as archived, t.created_at, t.updated_at, c.nom as category_name, p.nom as priority_name, s.nom as status_name, u.nom as assigned_name
                 FROM tickets t
                 LEFT JOIN categories c ON t.categorie_id = c.id
                 LEFT JOIN priorities p ON t.priorite_id = p.id
                 LEFT JOIN statuses s ON t.statut_id = s.id
                 LEFT JOIN users u ON t.technicien_id = u.id
                 WHERE t.user_id = :userId AND t.archive = 0
                 ORDER BY t.created_at DESC",
                ['userId' => $userId]
            );
        }
        $this->layout('main', 'tickets/index', ['tickets' => $tickets]);
    }
    public function create(): void
    {
        $categories = Category::all();
        $priorities = Priority::all();
        $this->layout('main', 'tickets/create', ['categories' => $categories, 'priorities' => $priorities]);
    }
    public function store(): void
    {
        $categoryId = $this->post('category_id');
        $priorityId = $this->post('priority_id');
        $data = [
            'titre'       => $this->post('title'),
            'description' => $this->post('description'),
            'categorie_id' => $categoryId !== '' ? (int) $categoryId : null,
            'priorite_id' => $priorityId !== '' ? (int) $priorityId : null,
            'statut_id'   => 1,
            'user_id'     => Auth::id(),
        ];
        $ticketId = Ticket::create($data);
        TicketHistory::create([
            'ticket_id' => $ticketId,
            'user_id'   => Auth::id(),
            'champ'    => 'created',
            'nouvelle_valeur'   => 'Ticket créé',
        ]);
        $this->setFlash('success', 'Ticket créé avec succès');
        $this->redirect('/gestion-support/tickets/' . $ticketId);
    }
    public function show(int $id): void
    {
        $ticket = Database::fetch(
            "SELECT t.id, t.reference, t.titre as title, t.description, t.user_id as created_by, t.categorie_id as category_id, t.priorite_id as priority_id, t.statut_id as status_id, t.technicien_id as assigned_to, t.date_souhaitee, t.archive as archived, t.created_at, t.updated_at, c.nom as category_name, p.nom as priority_name, p.couleur as priority_color, s.nom as status_name, u.nom as created_by_name, a.nom as assigned_name
             FROM tickets t
             LEFT JOIN categories c ON t.categorie_id = c.id
             LEFT JOIN priorities p ON t.priorite_id = p.id
             LEFT JOIN statuses s ON t.statut_id = s.id
             LEFT JOIN users u ON t.user_id = u.id
             LEFT JOIN users a ON t.technicien_id = a.id
             WHERE t.id = :id",
            ['id' => $id]
        );
        if (!$ticket) {
            $this->setFlash('danger', 'Ticket introuvable');
            $this->redirect('/gestion-support/tickets');
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
        $technicians = User::where('role', 'technicien');
        $this->layout('main', 'tickets/show', [
            'ticket'      => $ticket,
            'comments'    => $comments,
            'history'     => $history,
            'statuses'    => $statuses,
            'technicians' => $technicians,
        ]);
    }
    public function edit(int $id): void
    {
        $ticket = Ticket::find($id);
        if (!$ticket) {
            $this->setFlash('danger', 'Ticket introuvable');
            $this->redirect('/gestion-support/tickets');
        }
        $categories = Category::all();
        $priorities = Priority::all();
        $this->layout('main', 'tickets/edit', ['ticket' => $ticket, 'categories' => $categories, 'priorities' => $priorities]);
    }
    public function update(int $id): void
    {
        $categoryId = $this->post('category_id');
        $priorityId = $this->post('priority_id');
        Ticket::updateRecord($id, [
            'titre'       => $this->post('title'),
            'description' => $this->post('description'),
            'categorie_id' => $categoryId !== '' ? (int) $categoryId : null,
            'priorite_id' => $priorityId !== '' ? (int) $priorityId : null,
        ]);
        TicketHistory::create([
            'ticket_id' => $id,
            'user_id'   => Auth::id(),
            'champ'    => 'updated',
            'nouvelle_valeur'   => 'Ticket mis à jour',
        ]);
        $this->setFlash('success', 'Ticket mis à jour');
        $this->redirect('/gestion-support/tickets/' . $id);
    }
    public function addComment(int $id): void
    {
        Comment::create([
            'ticket_id' => $id,
            'user_id'   => Auth::id(),
            'contenu'   => $this->post('content'),
        ]);
        TicketHistory::create([
            'ticket_id' => $id,
            'user_id'   => Auth::id(),
            'champ'    => 'commented',
            'nouvelle_valeur'   => 'Commentaire ajouté',
        ]);
        $this->setFlash('success', 'Commentaire ajouté');
        $this->redirect('/gestion-support/tickets/' . $id);
    }
    public function updateStatus(int $id): void
    {
        $statusId = $this->post('status_id');
        $status = Status::find($statusId);
        Ticket::updateRecord($id, ['statut_id' => $statusId]);
        TicketHistory::create([
            'ticket_id' => $id,
            'user_id'   => Auth::id(),
            'champ'    => 'status_changed',
            'nouvelle_valeur'   => 'Statut changé à: ' . ($status->nom ?? 'Inconnu'),
        ]);
        $this->setFlash('success', 'Statut mis à jour');
        $this->redirect('/gestion-support/tickets/' . $id);
    }
    public function assign(int $id): void
    {
        $techId = $this->post('assigned_to');
        $tech = $techId ? User::find((int)$techId) : null;
        Ticket::updateRecord($id, ['technicien_id' => $techId ? (int)$techId : null]);
        TicketHistory::create([
            'ticket_id' => $id,
            'user_id'   => Auth::id(),
            'champ'    => 'assigned',
            'nouvelle_valeur'   => 'Assigné à: ' . ($tech->nom ?? 'Personne'),
        ]);
        $this->setFlash('success', 'Ticket assigné');
        $this->redirect('/gestion-support/tickets/' . $id);
    }
    public function archive(int $id): void
    {
        Ticket::updateRecord($id, ['archive' => 1]);
        TicketHistory::create([
            'ticket_id' => $id,
            'user_id'   => Auth::id(),
            'champ'    => 'archived',
            'nouvelle_valeur'   => 'Ticket archivé',
        ]);
        $this->setFlash('success', 'Ticket archivé');
        $this->redirect('/gestion-support/tickets');
    }
    public function archived(): void
    {
        $tickets = Database::fetchAll(
            "SELECT t.id, t.reference, t.titre as title, t.description, t.user_id as created_by, t.categorie_id as category_id, t.priorite_id as priority_id, t.statut_id as status_id, t.technicien_id as assigned_to, t.date_souhaitee, t.archive as archived, t.created_at, t.updated_at, c.nom as category_name, p.nom as priority_name, s.nom as status_name, u.nom as assigned_name
             FROM tickets t
             LEFT JOIN categories c ON t.categorie_id = c.id
             LEFT JOIN priorities p ON t.priorite_id = p.id
             LEFT JOIN statuses s ON t.statut_id = s.id
             LEFT JOIN users u ON t.technicien_id = u.id
             WHERE t.archive = 1
             ORDER BY t.updated_at DESC"
        );
        $this->layout('main', 'tickets/archived', ['tickets' => $tickets]);
    }
}
