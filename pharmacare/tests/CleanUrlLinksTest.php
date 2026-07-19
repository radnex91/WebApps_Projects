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
