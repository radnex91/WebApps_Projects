<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;

final class UrlTest extends TestCase
{
    public function testSlugifyLowercasesAndDashes(): void
    {
        $this->assertSame('paracetamol-500mg', slugify('Paracétamol 500mg'));
        $this->assertSame('pharmacie-centrale', slugify('Pharmacie Centrale'));
        $this->assertSame('doliprane', slugify('  Doliprane  '));
    }

    public function testSlugifyEmptyOnBlank(): void
    {
        $this->assertSame('', slugify(''));
        $this->assertSame('', slugify('   '));
    }

    public function testSlugifyStripsPunctuation(): void
    {
        $this->assertSame('a-b-c-d-e-f-g-123', slugify('a,b.c!d?e@f#g 123'));
    }

    public function testUrlBaseNoParams(): void
    {
        $this->assertSame(APP_URL . '/vente', url('vente'));
        $this->assertSame(APP_URL . '/comptabilite', url('comptabilite'));
    }

    public function testUrlProducesIdSlugEdit(): void
    {
        $u = url('produits', ['action' => 'edit', 'id' => 5], 'Paracétamol 500mg');
        $this->assertSame(APP_URL . '/produits/5-paracetamol-500mg/edit', $u);
    }

    public function testUrlDetailWithoutActionSuffix(): void
    {
        $u = url('clients', ['action' => 'detail', 'id' => 12], 'Pharmacie Centrale');
        $this->assertSame(APP_URL . '/clients/12-pharmacie-centrale', $u);
    }

    public function testUrlIdOnlyWhenNameMissing(): void
    {
        $u = url('produits', ['action' => 'edit', 'id' => 5]);
        $this->assertSame(APP_URL . '/produits/5/edit', $u);
    }

    public function testUrlOnglet(): void
    {
        $this->assertSame(APP_URL . '/magasin/stock', url('magasin', ['onglet' => 'stock']));
        $this->assertSame(APP_URL . '/magasin', url('magasin'));
    }

    public function testUrlComptabilitePage(): void
    {
        $this->assertSame(APP_URL . '/comptabilite/cloture', url('comptabilite', ['action' => 'cloture']));
        $this->assertSame(APP_URL . '/comptabilite/journal', url('comptabilite', ['action' => 'journal']));
    }

    public function testUrlComptaPlanEditWithId(): void
    {
        $u = url('comptabilite', ['action' => 'plan_edit', 'id' => 7]);
        $this->assertSame(APP_URL . '/comptabilite/plan/7', $u);
    }

    public function testUrlResidualQuery(): void
    {
        $u = url('produits', ['q' => 'doliprane', 'page' => 2]);
        $this->assertSame(APP_URL . '/produits?q=doliprane&page=2', $u);
    }

    public function testUrlActionAndIdWithResidualQuery(): void
    {
        $t = 'TOKEN123';
        $u = url('clients', ['action' => 'disable', 'id' => 12, 'csrf' => $t], 'Pharmacie Centrale');
        $this->assertSame(APP_URL . '/clients/12-pharmacie-centrale/disable?csrf=' . $t, $u);
    }

    public function testUrlUnmappedActionFallsBackToOldStyle(): void
    {
        // 'livrer' is a POST-only action, not in the routes map → old-style fallback.
        $u = url('commandes', ['action' => 'livrer', 'id' => 7]);
        $this->assertSame(APP_URL . '/modules/commandes.php?action=livrer&id=7', $u);
    }

    public function testUrlUnmappedModuleFallsBack(): void
    {
        $u = url('inexistant_module', ['action' => 'x', 'id' => 1]);
        $this->assertSame(APP_URL . '/modules/inexistant_module.php?action=x&id=1', $u);
    }

    public function testUrlNewAction(): void
    {
        $this->assertSame(APP_URL . '/produits/new', url('produits', ['action' => 'add']));
        $this->assertSame(APP_URL . '/clients/new', url('clients', ['action' => 'add']));
    }
}
