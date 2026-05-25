<?php
namespace App\Services;

use App\Models\Product;
use App\Models\Category;
use App\Models\Unit;
use App\Repositories\BaseRepository;

class ProductService
{
    private BaseRepository $repo;
    private Product $productModel;
    private Category $categoryModel;
    private Unit $unitModel;

    public function __construct(BaseRepository $repo, Product $productModel, Category $categoryModel, Unit $unitModel)
    {
        $this->repo = $repo;
        $this->productModel = $productModel;
        $this->categoryModel = $categoryModel;
        $this->unitModel = $unitModel;
    }

    // ---- Products ----

    public function getAllProducts(?int $categoryId = null, bool $activeOnly = true): array
    {
        return $this->productModel->allWithDetails($categoryId, $activeOnly);
    }

    public function getProduct(int $id): ?array
    {
        return $this->productModel->withCategory($id);
    }

    public function createProduct(array $data): int
    {
        if (empty($data['sku'])) {
            $data['sku'] = 'PRD-' . str_pad($this->productModel->count() + 1, 5, '0', STR_PAD_LEFT);
        }
        return $this->productModel->create($data);
    }

    public function updateProduct(int $id, array $data): bool
    {
        return $this->productModel->update($id, $data);
    }

    public function deleteProduct(int $id): bool
    {
        return $this->productModel->update($id, ['is_active' => 0]);
    }

    public function searchProducts(string $query): array
    {
        return $this->repo->query(
            "SELECT p.*, c.name AS category_name, u.abbreviation AS unit_abbr
             FROM products p
             LEFT JOIN categories c ON c.id = p.category_id
             LEFT JOIN units u ON u.id = p.unit_id
             WHERE p.is_active = 1 AND (p.name LIKE :q OR p.sku LIKE :q2)
             ORDER BY p.name ASC",
            ['q' => "%{$query}%", 'q2' => "%{$query}%"]
        );
    }

    // ---- Categories ----

    public function getAllCategories(): array
    {
        return $this->categoryModel->allWithProductCount();
    }

    public function createCategory(array $data): int
    {
        return $this->categoryModel->create($data);
    }

    public function updateCategory(int $id, array $data): bool
    {
        return $this->categoryModel->update($id, $data);
    }

    public function deleteCategory(int $id): bool
    {
        return $this->categoryModel->update($id, ['is_active' => 0]);
    }

    // ---- Units ----

    public function getAllUnits(): array
    {
        return $this->unitModel->all(['is_active' => 1], 'name ASC');
    }
}