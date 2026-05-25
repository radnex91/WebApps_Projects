<?php
// models/CaisseSession.php

class CaisseSession extends BaseModel {
    protected string $table = 'caisse_sessions';

    public function getActiveSession(int $caisseId): ?array {
        return $this->queryOne(
            "SELECT cs.*, u.name as user_name, c.name as caisse_name
             FROM caisse_sessions cs
             JOIN users u ON cs.user_id = u.id
             JOIN caisses c ON cs.caisse_id = c.id
             WHERE cs.caisse_id = ? AND cs.status = 'open'
             ORDER BY cs.id DESC LIMIT 1",
            [$caisseId]
        );
    }

    public function getActiveSessionForUser(int $userId, int $storeId): ?array {
        return $this->queryOne(
            "SELECT cs.*, u.name as user_name, c.name as caisse_name
             FROM caisse_sessions cs
             JOIN users u ON cs.user_id = u.id
             JOIN caisses c ON cs.caisse_id = c.id
             WHERE cs.user_id = ? AND cs.store_id = ? AND cs.status = 'open'
             ORDER BY cs.id DESC LIMIT 1",
            [$userId, $storeId]
        );
    }

    public function open(int $caisseId, int $storeId, int $userId, float $openingBalance): int {
        return $this->insert([
            'caisse_id'       => $caisseId,
            'store_id'        => $storeId,
            'user_id'         => $userId,
            'opening_balance' => $openingBalance,
            'opening_time'    => date('Y-m-d H:i:s'),
            'status'          => 'open',
        ]);
    }

    public function close(int $sessionId, float $actualBalance, int $closedBy, ?string $notes = null): bool {
        $summary = $this->getSessionSummary($sessionId);
        $expected = $summary['expected_balance'];

        $data = [
            'closing_balance_expected' => $expected,
            'closing_balance_actual'   => $actualBalance,
            'closing_discrepancy'      => $actualBalance - $expected,
            'closed_by'                => $closedBy,
            'closing_time'             => date('Y-m-d H:i:s'),
            'status'                   => 'closed',
        ];
        if ($notes !== null) {
            $data['notes'] = $notes;
        }
        return $this->update($sessionId, $data);
    }

    public function getSessionSummary(int $sessionId): array {
        $session = $this->find($sessionId);
        if (!$session) {
            return [
                'opening_balance' => 0,
                'cash_sales'      => 0,
                'deposits'        => 0,
                'withdrawals'     => 0,
                'expected_balance'=> 0,
            ];
        }

        $cashSales = $this->queryOne(
            "SELECT COALESCE(SUM(total_amount), 0) as total
             FROM sales
             WHERE session_id = ? AND status = 'completed'
               AND payment_method IN ('cash', 'mobile_money', 'orange_money', 'momo')",
            [$sessionId]
        )['total'] ?? 0;

        $deposits = $this->queryOne(
            "SELECT COALESCE(SUM(amount), 0) as total
             FROM caisse_operations
             WHERE session_id = ? AND type = 'deposit'",
            [$sessionId]
        )['total'] ?? 0;

        $withdrawals = $this->queryOne(
            "SELECT COALESCE(SUM(amount), 0) as total
             FROM caisse_operations
             WHERE session_id = ? AND type = 'withdrawal'",
            [$sessionId]
        )['total'] ?? 0;

        $openingBalance = (float)($session['opening_balance'] ?? 0);
        $expected = $openingBalance + (float)$cashSales + (float)$deposits - (float)$withdrawals;

        return [
            'opening_balance'  => $openingBalance,
            'cash_sales'       => (float)$cashSales,
            'deposits'         => (float)$deposits,
            'withdrawals'      => (float)$withdrawals,
            'expected_balance' => $expected,
        ];
    }

    public function getReportData(int $sessionId): array {
        $session = $this->queryOne(
            "SELECT cs.*, u.name as user_name, c.name as caisse_name,
                    st.name as store_name, cu.name as closed_by_name
             FROM caisse_sessions cs
             JOIN users u ON cs.user_id = u.id
             JOIN caisses c ON cs.caisse_id = c.id
             JOIN stores st ON cs.store_id = st.id
             LEFT JOIN users cu ON cs.closed_by = cu.id
             WHERE cs.id = ?",
            [$sessionId]
        );

        $summary = $this->getSessionSummary($sessionId);

        $operations = $this->query(
            "SELECT co.*, u.name as user_name
             FROM caisse_operations co
             JOIN users u ON co.user_id = u.id
             WHERE co.session_id = ?
             ORDER BY co.created_at ASC",
            [$sessionId]
        );

        $paymentBreakdown = $this->query(
            "SELECT payment_method, COUNT(*) as count, COALESCE(SUM(total_amount), 0) as total
             FROM sales
             WHERE session_id = ? AND status = 'completed'
             GROUP BY payment_method",
            [$sessionId]
        );

        $salesCount = $this->queryOne(
            "SELECT COUNT(*) as cnt FROM sales WHERE session_id = ? AND status = 'completed'",
            [$sessionId]
        )['cnt'] ?? 0;

        return [
            'session'           => $session,
            'summary'           => $summary,
            'operations'        => $operations,
            'payment_breakdown' => $paymentBreakdown,
            'sales_count'       => (int)$salesCount,
        ];
    }

    public function getHistory(int $storeId, string $date = ''): array {
        $where = "cs.store_id = ?";
        $params = [$storeId];

        if ($date) {
            $where .= " AND DATE(cs.opening_time) = ?";
            $params[] = $date;
        }

        return $this->query(
            "SELECT cs.*, u.name as user_name, c.name as caisse_name,
                    cu.name as closed_by_name
             FROM caisse_sessions cs
             JOIN users u ON cs.user_id = u.id
             JOIN caisses c ON cs.caisse_id = c.id
             LEFT JOIN users cu ON cs.closed_by = cu.id
             WHERE $where
             ORDER BY cs.opening_time DESC
             LIMIT 50",
            $params
        );
    }
}

class CaisseOperation extends BaseModel {
    protected string $table = 'caisse_operations';

    public function getBySession(int $sessionId): array {
        return $this->query(
            "SELECT co.*, u.name as user_name
             FROM caisse_operations co
             JOIN users u ON co.user_id = u.id
             WHERE co.session_id = ?
             ORDER BY co.created_at DESC",
            [$sessionId]
        );
    }

    public function addDeposit(int $sessionId, float $amount, string $reason, int $userId): int {
        return $this->insert([
            'session_id' => $sessionId,
            'type'       => 'deposit',
            'amount'     => $amount,
            'reason'     => $reason,
            'user_id'    => $userId,
        ]);
    }

    public function addWithdrawal(int $sessionId, float $amount, string $reason, int $userId): int {
        return $this->insert([
            'session_id' => $sessionId,
            'type'       => 'withdrawal',
            'amount'     => $amount,
            'reason'     => $reason,
            'user_id'    => $userId,
        ]);
    }
}
