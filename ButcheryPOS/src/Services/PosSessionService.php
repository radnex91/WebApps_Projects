<?php
namespace App\Services;

use App\Models\PosSession;
use App\Core\Auth;
use App\Repositories\BaseRepository;

class PosSessionService
{
    private BaseRepository $repo;
    private PosSession $posSessionModel;

    public function __construct(BaseRepository $repo, PosSession $posSessionModel)
    {
        $this->repo = $repo;
        $this->posSessionModel = $posSessionModel;
    }

    /**
     * Open a new POS terminal session
     */
    public function openSession(int $userId, string $terminalName, float $openingCash): array
    {
        // Check if user already has an open session
        $existing = $this->posSessionModel->findOpenByUser($userId);
        if ($existing) {
            return ['success' => true, 'session' => $existing, 'already_open' => true];
        }

        $token = bin2hex(random_bytes(32));
        $id = $this->posSessionModel->create([
            'session_token' => $token,
            'user_id' => $userId,
            'terminal_name' => $terminalName,
            'opening_cash' => $openingCash,
            'status' => 'open',
        ]);

        $session = $this->posSessionModel->find($id);
        $_SESSION['pos_session_id'] = $id;
        $_SESSION['pos_session_token'] = $token;

        return ['success' => true, 'session' => $session, 'already_open' => false];
    }

    /**
     * Close a POS terminal session
     */
    public function closeSession(int $sessionId, float $closingCash): bool
    {
        $session = $this->posSessionModel->find($sessionId);
        if (!$session || $session['status'] !== 'open') return false;

        $this->posSessionModel->update($sessionId, [
            'closed_at' => date('Y-m-d H:i:s'),
            'closing_cash' => $closingCash,
            'status' => 'closed',
        ]);

        unset($_SESSION['pos_session_id'], $_SESSION['pos_session_token']);
        return true;
    }

    /**
     * Get current session for a user
     */
    public function getCurrentSession(int $userId): ?array
    {
        return $this->posSessionModel->findOpenByUser($userId);
    }

    /**
     * Validate a session token
     */
    public function validateToken(string $token): ?array
    {
        $session = $this->posSessionModel->findByToken($token);
        if ($session && $session['status'] === 'open') {
            return $session;
        }
        return null;
    }

    /**
     * Get session summary (sales total, payment breakdown)
     */
    public function getSessionSummary(int $sessionId): array
    {
        $sales = $this->repo->query(
            "SELECT COUNT(*) AS sale_count, COALESCE(SUM(total_amount), 0) AS total_sales
             FROM sales WHERE pos_session_id = :sid",
            ['sid' => $sessionId]
        )[0] ?? ['sale_count' => 0, 'total_sales' => 0];

        $payments = $this->repo->query(
            "SELECT payment_method, COALESCE(SUM(amount), 0) AS total
             FROM payments p
             INNER JOIN sales s ON s.id = p.sale_id
             WHERE s.pos_session_id = :sid
             GROUP BY payment_method",
            ['sid' => $sessionId]
        );

        $summary = ['sale_count' => 0, 'total_sales' => 0, 'payments' => []];
        $summary['sale_count'] = (int)$sales['sale_count'];
        $summary['total_sales'] = (float)$sales['total_sales'];
        foreach ($payments as $p) {
            $summary['payments'][$p['payment_method']] = (float)$p['total'];
        }

        return $summary;
    }
}