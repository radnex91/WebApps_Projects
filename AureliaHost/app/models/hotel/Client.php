<?php
class Client extends Model
{
    protected string $table = 'clients';

    public function search(string $term): array
    {
        return $this->query(
            "SELECT * FROM {$this->table}
             WHERE nom LIKE :t OR prenom LIKE :t2 OR email LIKE :t3 OR telephone LIKE :t4
             ORDER BY nom LIMIT 20",
            ['t' => "%$term%", 't2' => "%$term%", 't3' => "%$term%", 't4' => "%$term%"]
        );
    }
}
