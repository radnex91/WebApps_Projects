<?php
namespace App\Controllers;
use App\Core\Controller;
use App\Helpers\Auth;
use App\Models\User;
use App\Models\Ticket;
use App\Models\Category;
use App\Models\Priority;
use App\Models\Status;
use App\Models\Sla;
use App\Models\Notification;
class AdminController extends Controller
{
    public function __construct()
    {
        parent::__construct();
        Auth::requireRole('admin');
    }
    public function index(): void
    {
        $totalTickets = Ticket::count();
        $openTickets = Ticket::countWhere('statut_id', 1);
        $inProgressTickets = Ticket::countWhere('statut_id', 2);
        $resolvedTickets = Ticket::countWhere('statut_id', 4);
        $totalUsers = User::count();
        $technicians = User::where('role', 'technicien');
        $recentTickets = Ticket::paginate(1, 5);
        $this->layout('main', 'admin/dashboard', [
            'totalTickets'      => $totalTickets,
            'openTickets'       => $openTickets,
            'inProgressTickets' => $inProgressTickets,
            'resolvedTickets'   => $resolvedTickets,
            'totalUsers'        => $totalUsers,
            'technicians'       => $technicians,
            'recentTickets'     => $recentTickets['items'],
        ]);
    }
    public function users(): void
    {
        $users = User::all();
        $this->layout('main', 'admin/users', ['users' => $users]);
    }
    public function addUserForm(): void
    {
        $this->layout('main', 'admin/add_user');
    }
    public function addUser(): void
    {
        $data = [
            'nom'     => $this->post('name'),
            'email'    => $this->post('email'),
            'mot_de_passe' => password_hash($this->post('password'), PASSWORD_DEFAULT),
            'role'     => $this->post('role'),
        ];
        User::create($data);
        $this->setFlash('success', 'Utilisateur ajouté avec succès');
        $this->redirect('/gestion-support/admin/users');
    }
    public function editUserForm(int $id): void
    {
        $user = User::find($id);
        if (!$user) {
            $this->setFlash('danger', 'Utilisateur introuvable');
            $this->redirect('/gestion-support/admin/users');
        }
        $this->layout('main', 'admin/edit_user', ['user' => $user]);
    }
    public function editUser(int $id): void
    {
        $data = [
            'nom'  => $this->post('name'),
            'email' => $this->post('email'),
            'role'  => $this->post('role'),
        ];
        if ($this->post('password')) {
            $data['mot_de_passe'] = password_hash($this->post('password'), PASSWORD_DEFAULT);
        }
        User::updateRecord($id, $data);
        $this->setFlash('success', 'Utilisateur modifié avec succès');
        $this->redirect('/gestion-support/admin/users');
    }
    public function deleteUser(int $id): void
    {
        User::deleteRecord($id);
        $this->setFlash('success', 'Utilisateur supprimé avec succès');
        $this->redirect('/gestion-support/admin/users');
    }
    public function categories(): void
    {
        $categories = Category::all();
        $this->layout('main', 'admin/categories', ['categories' => $categories]);
    }
    public function addCategory(): void
    {
        Category::create(['nom' => $this->post('name')]);
        $this->setFlash('success', 'Catégorie ajoutée');
        $this->redirect('/gestion-support/admin/categories');
    }
    public function editCategory(int $id): void
    {
        Category::updateRecord($id, ['nom' => $this->post('name')]);
        $this->setFlash('success', 'Catégorie modifiée');
        $this->redirect('/gestion-support/admin/categories');
    }

    public function deleteCategory(int $id): void
    {
        Category::deleteRecord($id);
        $this->setFlash('success', 'Catégorie supprimée');
        $this->redirect('/gestion-support/admin/categories');
    }
    public function priorities(): void
    {
        $priorities = Priority::all();
        $this->layout('main', 'admin/priorities', ['priorities' => $priorities]);
    }
    public function addPriority(): void
    {
        Priority::create([
            'nom'   => $this->post('name'),
            'couleur'  => $this->post('color'),
            'temps_resolution_heures' => $this->post('sla_hours') ?: 24,
        ]);
        $this->setFlash('success', 'Priorité ajoutée');
        $this->redirect('/gestion-support/admin/priorities');
    }
    public function editPriority(int $id): void
    {
        Priority::updateRecord($id, [
            'nom'   => $this->post('name'),
            'couleur'  => $this->post('color'),
            'temps_resolution_heures' => $this->post('sla_hours') ?: 24,
        ]);
        $this->setFlash('success', 'Priorité modifiée');
        $this->redirect('/gestion-support/admin/priorities');
    }

    public function deletePriority(int $id): void
    {
        Priority::deleteRecord($id);
        $this->setFlash('success', 'Priorité supprimée');
        $this->redirect('/gestion-support/admin/priorities');
    }
    public function sla(): void
    {
        $slaConfigs = Sla::all();
        $priorities = Priority::all();
        $this->layout('main', 'admin/sla', ['slaConfigs' => $slaConfigs, 'priorities' => $priorities]);
    }
    public function addSla(): void
    {
        Sla::create([
            'priorite_id'           => $this->post('priority_id'),
            'temps_reponse_heures'  => $this->post('response_hours'),
            'temps_resolution_heures' => $this->post('resolution_hours'),
        ]);
        $this->setFlash('success', 'Configuration SLA ajoutée');
        $this->redirect('/gestion-support/admin/sla');
    }
    public function editSla(int $id): void
    {
        Sla::updateRecord($id, [
            'priorite_id'           => $this->post('priority_id'),
            'temps_reponse_heures'  => $this->post('response_hours'),
            'temps_resolution_heures' => $this->post('resolution_hours'),
        ]);
        $this->setFlash('success', 'Configuration SLA modifiée');
        $this->redirect('/gestion-support/admin/sla');
    }

    public function deleteSla(int $id): void
    {
        Sla::deleteRecord($id);
        $this->setFlash('success', 'Configuration SLA supprimée');
        $this->redirect('/gestion-support/admin/sla');
    }
    public function archive(): void
    {
        $archivedTickets = Ticket::where('archive', 1);
        $this->layout('main', 'admin/archive', ['tickets' => $archivedTickets]);
    }
    public function restoreTicket(int $id): void
    {
        Ticket::updateRecord($id, ['archive' => 0]);
        $this->setFlash('success', 'Ticket restauré');
        $this->redirect('/gestion-support/admin/archive');
    }
    public function notifications(): void
    {
        $notifications = Notification::where('user_id', Auth::id());
        $this->layout('main', 'admin/notifications', ['notifications' => $notifications]);
    }
    public function settings(): void
    {
        $this->layout('main', 'admin/settings');
    }
    public function updateSettings(): void
    {
        $this->setFlash('success', 'Paramètres mis à jour');
        $this->redirect('/gestion-support/admin/settings');
    }
    public function reports(): void
    {
        $totalTickets = Ticket::count();
        $resolvedTickets = Ticket::countWhere('statut_id', 4);
        $openTickets = Ticket::countWhere('statut_id', 1);
        $stats = [
            'total'    => $totalTickets,
            'resolved' => $resolvedTickets,
            'open'     => $openTickets,
            'rate'     => $totalTickets > 0 ? round(($resolvedTickets / $totalTickets) * 100) : 0,
        ];
        $this->layout('main', 'admin/reports/index', ['stats' => $stats]);
    }
    public function ticketsByStatus(): void
    {
        header('Content-Type: application/json');
        $statuses = Status::all();
        $data = [];
        foreach ($statuses as $s) {
            $data[] = ['label' => $s->nom, 'count' => Ticket::countWhere('statut_id', $s->id)];
        }
        echo json_encode($data);
        exit;
    }
    public function ticketsByPriority(): void
    {
        header('Content-Type: application/json');
        $priorities = Priority::all();
        $data = [];
        foreach ($priorities as $p) {
            $data[] = ['label' => $p->nom, 'count' => Ticket::countWhere('priorite_id', $p->id)];
        }
        echo json_encode($data);
        exit;
    }
    public function ticketsOverTime(): void
    {
        header('Content-Type: application/json');
        $rows = \App\Core\Database::fetchAll("SELECT DATE(created_at) as date, COUNT(*) as count FROM tickets GROUP BY DATE(created_at) ORDER BY date ASC LIMIT 30");
        echo json_encode($rows);
        exit;
    }
    public function interventionsOverTime(): void
    {
        header('Content-Type: application/json');
        $rows = \App\Core\Database::fetchAll(
            "SELECT DATE(updated_at) as date, COUNT(*) as count
             FROM tickets
             WHERE statut_id = 4
               AND updated_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
             GROUP BY DATE(updated_at)
             ORDER BY date ASC"
        );
        echo json_encode($rows);
        exit;
    }
    public function topTechnicians(): void
    {
        header('Content-Type: application/json');
        $rows = \App\Core\Database::fetchAll(
            "SELECT u.nom, u.email, COUNT(t.id) as resolved_count
             FROM tickets t
             JOIN users u ON u.id = t.technicien_id
             WHERE t.statut_id = 4
             GROUP BY t.technicien_id
             ORDER BY resolved_count DESC
             LIMIT 5"
        );
        echo json_encode($rows);
        exit;
    }
    public function technicianLoad(): void
    {
        header('Content-Type: application/json');
        $techs = User::where('role', 'technicien');
        $data = [];
        foreach ($techs as $t) {
            $count = Ticket::countWhere('technicien_id', $t->id);
            $data[] = ['name' => $t->nom, 'count' => $count];
        }
        echo json_encode($data);
        exit;
    }
}
