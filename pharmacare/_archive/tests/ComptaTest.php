<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;

/**
 * Tests d'intégration comptabilité : ecritureCreate (double partie OHADA).
 * Nécessite la BDD de test (plan_comptable, exercices, ecritures, ecriture_lignes).
 */
final class ComptaTest extends TestCase
{
    private function twoAccounts(): array
    {
        $db = getDB();
        $rows = $db->query("SELECT id FROM plan_comptable ORDER BY id LIMIT 2")->fetchAll(PDO::FETCH_COLUMN);
        if (count($rows) < 2) {
            $this->markTestSkipped('plan_comptable non seedé dans la base de test');
        }
        return array_map('intval', $rows);
    }

    public function testEcritureCreateThrowsWhenUnbalanced(): void
    {
        $db = getDB();
        [$c1, $c2] = $this->twoAccounts();
        $lignes = [
            [$c1, 100.0, 0.0, 'Débit'],
            [$c2, 0.0, 90.0, 'Crédit'],   // déséquilibré (100 ≠ 90)
        ];
        $this->expectException(Exception::class);
        $db->beginTransaction();
        try {
            ecritureCreate($db, 'Test déséquilibré', date('Y-m-d'), $lignes, 'manuel', 'TST-1', 1);
            $db->rollBack();
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }

    public function testEcritureCreateInsertsBalancedEntry(): void
    {
        $db = getDB();
        [$c1, $c2] = $this->twoAccounts();
        $lignes = [
            [$c1, 100.0, 0.0, 'Débit test'],
            [$c2, 0.0, 100.0, 'Crédit test'],
        ];

        $db->beginTransaction();
        try {
            $eid = ecritureCreate($db, 'Test équilibré', date('Y-m-d'), $lignes, 'manuel', 'TST-2', 1);
            $this->assertGreaterThan(0, $eid);

            $ec = $db->prepare("SELECT * FROM ecritures WHERE id = ?");
            $ec->execute([$eid]);
            $row = $ec->fetch();
            $this->assertNotFalse($row);
            $this->assertSame('Test équilibré', $row['libelle']);
            $this->assertSame('manuel', $row['source']);

            $nb = (int)$db->query("SELECT COUNT(*) FROM ecriture_lignes WHERE ecriture_id = $eid")->fetchColumn();
            $this->assertSame(2, $nb);
        } finally {
            if ($db->inTransaction()) $db->rollBack();
        }
    }
}