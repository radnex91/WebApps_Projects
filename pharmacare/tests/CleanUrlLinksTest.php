<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;

/**
 * Régression : aucun lien GET (href) ne doit pointer vers /modules/<x>.php
 * après migration vers les clean URLs. Les formulaires POST (action="") et les
 * header('Location:') sont exclus du contrôle (action= n'est pas href=).
 *
 * Chaque tâche de conversion ajoute ses fichiers à la liste couverte.
 */
final class CleanUrlLinksTest extends TestCase
{
    /**
     * Fichiers à vérifier. Ajouter au fur et à mesure des tâches de conversion.
     */
    private function convertedFiles(): array
    {
        return [
            'includes/layout.php',
            'includes/dashboard-admin.php',
            'includes/dashboard-caissier.php',
            'includes/dashboard-pharmacien.php',
            'modules/produits.php',
            'modules/clients.php',
            'modules/fournisseurs.php',
            'modules/utilisateurs.php',
            'modules/magasin.php',
            'modules/comptabilite.php',
            'modules/caisse.php',
            'modules/marketing.php',
            'modules/remise_codes.php',
            'modules/commandes.php',
            'modules/retours.php',
            'modules/stock.php',
            'modules/stock_ajust.php',
            'modules/rapports.php',
            'modules/rapports_caissier.php',
            'modules/ventes_hist.php',
            'modules/vente.php',
            'modules/roles.php',
            'modules/categories.php',
            'modules/parametres.php',
            'modules/remise_approbateurs.php',
        ];
    }

    private function assertNoOldStyleGetHref(string $relPath): void
    {
        $abs = __DIR__ . '/../' . $relPath;
        $this->assertFileExists($abs, "Fichier manquant : $relPath");
        $src = file_get_contents($abs);
        // href="...modules/<x>.php" ou href='...modules/<x>.php' = lien GET old-style.
        $pattern = '/href\s*=\s*["\'][^"\']*\/modules\/[a-z_]+\.php/i';
        $this->assertDoesNotMatchRegularExpression(
            $pattern,
            $src,
            "Lien GET old-style vers modules/ encore présent dans $relPath — convertir en url()."
        );
    }

    public function testConvertedFilesHaveNoOldStyleGetHref(): void
    {
        foreach ($this->convertedFiles() as $f) {
            $this->assertNoOldStyleGetHref($f);
        }
    }
}
