<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;

/**
 * Tests sécurité : CSRF (csrf / verifyCsrf).
 *
 * NB : verifyCsrf() appelle die() en cas d'échec (sortie brutale, non capturable
 * sans mock). On teste donc : génération du token, acceptation d'un token valide,
 * et la sémantique de comparaison constante-temps (hash_equals) recommandée.
 * La conversion de die()→Exception/419 est un sujet Phase 2.
 */
final class SecurityTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }

    public function testCsrfReturnsNonEmptyHexToken(): void
    {
        $token = csrf();
        $this->assertNotEmpty($token);
        $this->assertSame(64, strlen($token)); // 32 bytes → 64 hex chars
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $token);
    }

    public function testVerifyCsrfAcceptsValidToken(): void
    {
        $_POST['csrf'] = csrf();
        $this->expectNotToPerformAssertions();
        verifyCsrf();
    }

    public function testInvalidTokenDoesNotEqualSessionToken(): void
    {
        $session = csrf();
        $forged = bin2hex(random_bytes(32));
        $this->assertFalse(hash_equals($session, $forged));
        $this->assertTrue(hash_equals($session, $session));
    }
}