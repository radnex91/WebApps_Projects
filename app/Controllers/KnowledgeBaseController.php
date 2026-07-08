<?php
namespace App\Controllers;
use App\Core\Database;
use App\Helpers\Auth;
use App\Models\KnowledgeBase;
use App\Models\KnowledgeBaseCategory;
use App\Core\Controller;
class KnowledgeBaseController extends Controller
{
    public function __construct()
    {
        parent::__construct();
        Auth::requireAuth();
    }
    public function index(): void
    {
        $articles = Database::fetchAll(
            "SELECT a.id, a.titre as title, a.contenu as content, a.categorie_id as category_id, a.user_id as created_by, a.est_publie, a.vues, a.created_at, a.updated_at, c.nom as category_name, u.nom as author_name
             FROM knowledge_base_articles a
             LEFT JOIN knowledge_base_categories c ON a.categorie_id = c.id
             LEFT JOIN users u ON a.user_id = u.id
             ORDER BY a.created_at DESC"
        );
        $categories = KnowledgeBaseCategory::all();
        $this->layout('main', 'kb/index', ['articles' => $articles, 'categories' => $categories]);
    }
    public function show(int $id): void
    {
        $article = Database::fetch(
            "SELECT a.id, a.titre as title, a.contenu as content, a.categorie_id as category_id, a.user_id as created_by, a.est_publie, a.vues, a.created_at, a.updated_at, c.nom as category_name, u.nom as author_name
             FROM knowledge_base_articles a
             LEFT JOIN knowledge_base_categories c ON a.categorie_id = c.id
             LEFT JOIN users u ON a.user_id = u.id
             WHERE a.id = :id",
            ['id' => $id]
        );
        if (!$article) {
            $this->setFlash('danger', 'Article introuvable');
            $this->redirect('/gestion-support/kb');
        }
        $this->layout('main', 'kb/show', ['article' => $article]);
    }
    public function add(): void
    {
        $error = $this->requireFields(['title' => 'Titre', 'content' => 'Contenu']);
        if ($error) {
            $this->setFlash('danger', $error);
            $this->redirect('/gestion-support/kb');
        }
        KnowledgeBase::create([
            'titre'       => trim($this->post('title')),
            'contenu'     => trim($this->post('content')),
            'categorie_id' => $this->post('category_id') ?: null,
            'user_id'     => Auth::id(),
        ]);
        $this->setFlash('success', 'Article ajouté');
        $this->redirect('/gestion-support/kb');
    }
    public function edit(int $id): void
    {
        $error = $this->requireFields(['title' => 'Titre', 'content' => 'Contenu']);
        if ($error) {
            $this->setFlash('danger', $error);
            $this->redirect('/gestion-support/kb');
        }
        KnowledgeBase::updateRecord($id, [
            'titre'       => trim($this->post('title')),
            'contenu'     => trim($this->post('content')),
            'categorie_id' => $this->post('category_id') ?: null,
        ]);
        $this->setFlash('success', 'Article mis à jour');
        $this->redirect('/gestion-support/kb');
    }
    public function delete(int $id): void
    {
        try {
            KnowledgeBase::deleteRecord($id);
            $this->setFlash('success', 'Article supprimé');
        } catch (\Throwable $e) {
            $this->setFlash('danger', 'Impossible de supprimer cet article car il est lié à d\'autres enregistrements.');
        }
        $this->redirect('/gestion-support/kb');
    }
    public function addCategory(): void
    {
        $error = $this->requireFields(['name' => 'Nom']);
        if ($error) {
            $this->setFlash('danger', $error);
            $this->redirect('/gestion-support/kb');
        }
        KnowledgeBaseCategory::create(['nom' => trim($this->post('name'))]);
        $this->setFlash('success', 'Catégorie ajoutée');
        $this->redirect('/gestion-support/kb');
    }
    public function deleteCategory(int $id): void
    {
        try {
            KnowledgeBaseCategory::deleteRecord($id);
            $this->setFlash('success', 'Catégorie supprimée');
        } catch (\Throwable $e) {
            $this->setFlash('danger', 'Impossible de supprimer cette catégorie car elle contient des articles.');
        }
        $this->redirect('/gestion-support/kb');
    }
}
