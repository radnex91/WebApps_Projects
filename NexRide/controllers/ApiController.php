<?php
namespace Controllers;

use Core\Controller;
use Models\Billet;
use Models\Voyage;
use Models\Bordereau;
use Models\EcritureComptable;
use Models\SyncQueue;
use Models\Caisse;
use Models\AuditLog;

class ApiController extends Controller
{
    public function __construct()
    {
        parent::__construct();
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit;
        }
    }

    public function sync(): void
    {
        $input = json_decode(file_get_contents('php://input'), true);
        $action = $input['action'] ?? '';
        $table = $input['table'] ?? '';
        $offlineId = $input['offline_id'] ?? '';
        $data = $input['data'] ?? [];

        if (!$offlineId) {
            $this->json(['success' => false, 'error' => 'offline_id requis'], 400);
        }

        try {
            match ($table) {
                'billets' => $this->syncBillet($action, $offlineId, $data),
                'voyages' => $this->syncVoyage($action, $offlineId, $data),
                'bordereaux' => $this->syncBordereau($action, $offlineId, $data),
                'ecritures_comptables' => $this->syncEcriture($action, $offlineId, $data),
                default => throw new \InvalidArgumentException("Table {$table} non supportée"),
            };

            SyncQueue::db()->update('sync_queue', ['status' => 'PROCESSED', 'processed_at' => date('Y-m-d H:i:s')], 'offline_id = ?', [$offlineId]);
            $this->json(['success' => true, 'offline_id' => $offlineId]);
        } catch (\Throwable $e) {
            SyncQueue::db()->update('sync_queue', ['status' => 'FAILED', 'error_message' => $e->getMessage(), 'attempts' => \Core\Database::getInstance()->query("SELECT attempts FROM sync_queue WHERE offline_id = ?", [$offlineId])->fetchColumn() + 1], 'offline_id = ?', [$offlineId]);
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function syncStatus(): void
    {
        $pending = SyncQueue::countPending();
        $failed = SyncQueue::getFailed();
        $this->json([
            'pending' => $pending,
            'failed' => count($failed),
            'failed_items' => $failed,
        ]);
    }

    public function getUnsynced(): void
    {
        $this->json([
            'billets' => array_map(fn($b) => $b->toArray(), Billet::getUnsynced()),
            'bordereaux' => array_map(fn($b) => $b->toArray(), Bordereau::getUnsynced()),
            'ecritures' => array_map(fn($e) => $e->toArray(), EcritureComptable::getUnsynced()),
        ]);
    }

    public function dashboard(): void
    {
        $stats = [
            'recette_jour' => Billet::getRecetteJour(),
            'billets_aujourdhui' => count(Billet::getTodayBillets()),
            'voyages_aujourdhui' => count(Voyage::getVoyagesDuJour()),
            'solde_caisse' => Caisse::getSoldeGlobal(),
            'sync_pending' => SyncQueue::countPending(),
        ];
        $this->json($stats);
    }

    public function searchClients(): void
    {
        $query = $this->get('q');
        if (!$query) { $this->json([]); }
        $clients = \Models\Client::search($query);
        $this->json(array_map(fn($c) => [
            'id' => $c->id, 'nom' => $c->getNomComplet(),
            'telephone' => $c->telephone, 'email' => $c->email,
        ], $clients));
    }

    private function syncBillet(string $action, string $offlineId, array $data): void
    {
        match ($action) {
            'CREATE' => (new Billet($data))->save(),
            'UPDATE' => (new Billet($data))->save(),
            'DELETE' => Billet::whereFirst('offline_id', '=', $offlineId)?->delete(),
        };
    }

    private function syncVoyage(string $action, string $offlineId, array $data): void
    {
        match ($action) {
            'CREATE' => (new Voyage($data))->save(),
            default => null,
        };
    }

    private function syncBordereau(string $action, string $offlineId, array $data): void
    {
        match ($action) {
            'CREATE' => (new Bordereau($data))->save(),
            'UPDATE' => (new Bordereau($data))->save(),
            default => null,
        };
    }

    private function syncEcriture(string $action, string $offlineId, array $data): void
    {
        match ($action) {
            'CREATE' => (new EcritureComptable($data))->save(),
            default => null,
        };
    }
}