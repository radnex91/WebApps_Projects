<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;

/**
 * Tests des helpers globaux (e, getParam, fideliteActive, fmtMoney, genRef).
 * Nécessite la BDD de test (pharmacare_test) initialisée par le bootstrap.
 */
final class HelpersTest extends TestCase
{
    public function testEEscapesHtmlSpecialChars(): void
    {
        $this->assertSame('&lt;script&gt;x&lt;/script&gt;', e('<script>x</script>'));
        $this->assertSame('a &amp; b', e('a & b'));
        $this->assertSame('&quot;q&quot;', e('"q"'));
        $this->assertSame('&#039;s&#039;', e("'s'"));
    }

    public function testGetParamReturnsDefaultWhenMissing(): void
    {
        // getParam est mis en cache (static) ; on teste une clé inexistante.
        $this->assertSame('def', getParam('cle_inexistante_' . uniqid(), 'def'));
    }

    public function testGetParamReturnsSeededValue(): void
    {
        // database.sql seed 'tva' = 19.25
        $this->assertSame('19.25', getParam('tva', '0'));
    }

    public function testFideliteActiveDefaultsToFalse(): void
    {
        // fidelite_active n'est pas seedée dans database.sql → défaut '0' → false
        $this->assertFalse(fideliteActive());
    }

    public function testFmtMoneyFormatsCfaAfter(): void
    {
        // devise par défaut = XAF, symbole FCFA, position after, 0 décimales
        $this->assertStringEndsWith('FCFA', fmtMoney(1500.0));
        $this->assertStringContainsString('1 500', fmtMoney(1500.0));
    }

    public function testGenRefProducesUniquePrefixedReference(): void
    {
        $r1 = genRef('VNT');
        $r2 = genRef('VNT');
        $this->assertStringStartsWith('VNT', $r1);
        $this->assertStringStartsWith('VNT', $r2);
        $this->assertNotEquals($r1, $r2);
    }
}