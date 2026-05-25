<?php
namespace App\Models;

use App\Repositories\BaseRepository;

abstract class BaseModel
{
    protected BaseRepository $repo;
    protected string $table;
    protected array $fillable = [];
    protected bool $timestamps = true;

    public function __construct(BaseRepository $repo)
    {
        $this->repo = $repo;
    }

    public function all(array $conditions = [], string $orderBy = 'id DESC'): array
    {
        return $this->repo->all($this->table, $conditions, $orderBy);
    }

    public function find(int $id): ?array
    {
        return $this->repo->find($this->table, $id);
    }

    public function findBy(string $column, $value): ?array
    {
        return $this->repo->findBy($this->table, $column, $value);
    }

    public function create(array $data): int
    {
        $data = $this->filterFillable($data);
        return $this->repo->insert($this->table, $data);
    }

    public function update(int $id, array $data): bool
    {
        $data = $this->filterFillable($data);
        if ($this->timestamps) {
            $data['updated_at'] = date('Y-m-d H:i:s');
        }
        return $this->repo->update($this->table, $id, $data);
    }

    public function delete(int $id): bool
    {
        return $this->repo->delete($this->table, $id);
    }

    public function count(array $conditions = []): int
    {
        return $this->repo->count($this->table, $conditions);
    }

    public function getRepo(): BaseRepository
    {
        return $this->repo;
    }

    protected function filterFillable(array $data): array
    {
        if (empty($this->fillable)) {
            return $data;
        }
        return array_intersect_key($data, array_flip($this->fillable));
    }
}