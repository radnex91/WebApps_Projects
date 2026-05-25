<?php
/**
 * Classe Model de base - CRUD avec PDO
 */
class Model
{
    protected static $pdo = null;
    protected $table;
    protected $primaryKey = 'id';
    protected $fillable = [];
    protected $timestamps = true;

    public function __construct()
    {
        $this->connect();
    }

    private function connect()
    {
        if (self::$pdo !== null) return;

        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            self::$pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]);
        } catch (PDOException $e) {
            die("Erreur de connexion : " . $e->getMessage());
        }
    }

    public function getPdo()
    {
        return self::$pdo;
    }

    public function all($orderBy = 'id DESC')
    {
        $sql = "SELECT * FROM {$this->table} ORDER BY {$orderBy}";
        return self::$pdo->query($sql)->fetchAll();
    }

    public function find($id)
    {
        $sql = "SELECT * FROM {$this->table} WHERE {$this->primaryKey} = :id";
        $stmt = self::$pdo->prepare($sql);
        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    }

    public function findBy($conditions, $orderBy = 'id DESC')
    {
        $where = [];
        $params = [];
        foreach ($conditions as $key => $value) {
            $where[] = "{$key} = :{$key}";
            $params[$key] = $value;
        }
        $whereClause = implode(' AND ', $where);
        $sql = "SELECT * FROM {$this->table} WHERE {$whereClause} ORDER BY {$orderBy}";
        $stmt = self::$pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function findOneBy($conditions)
    {
        $results = $this->findBy($conditions);
        return $results ? $results[0] : false;
    }

    public function create($data)
    {
        $fields = array_intersect_key($data, array_flip($this->fillable));
        if ($this->timestamps) {
            $fields['created_at'] = date('Y-m-d H:i:s');
        }
        $columns = implode(', ', array_keys($fields));
        $placeholders = ':' . implode(', :', array_keys($fields));
        $sql = "INSERT INTO {$this->table} ({$columns}) VALUES ({$placeholders})";
        $stmt = self::$pdo->prepare($sql);
        $stmt->execute($fields);
        return self::$pdo->lastInsertId();
    }

    public function update($id, $data)
    {
        $fields = array_intersect_key($data, array_flip($this->fillable));
        if ($this->timestamps) {
            $fields['updated_at'] = date('Y-m-d H:i:s');
        }
        $set = [];
        foreach ($fields as $key => $value) {
            $set[] = "{$key} = :{$key}";
        }
        $setClause = implode(', ', $set);
        $fields['id'] = $id;
        $sql = "UPDATE {$this->table} SET {$setClause} WHERE {$this->primaryKey} = :id";
        $stmt = self::$pdo->prepare($sql);
        return $stmt->execute($fields);
    }

    public function delete($id)
    {
        $sql = "DELETE FROM {$this->table} WHERE {$this->primaryKey} = :id";
        $stmt = self::$pdo->prepare($sql);
        return $stmt->execute(['id' => $id]);
    }

    public function count($conditions = [])
    {
        if (empty($conditions)) {
            $sql = "SELECT COUNT(*) as total FROM {$this->table}";
            return self::$pdo->query($sql)->fetch()['total'];
        }
        $where = [];
        $params = [];
        foreach ($conditions as $key => $value) {
            $where[] = "{$key} = :{$key}";
            $params[$key] = $value;
        }
        $whereClause = implode(' AND ', $where);
        $sql = "SELECT COUNT(*) as total FROM {$this->table} WHERE {$whereClause}";
        $stmt = self::$pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch()['total'];
    }

    public function paginate($page = 1, $perPage = ITEMS_PER_PAGE, $conditions = [], $orderBy = 'id DESC')
    {
        $offset = ($page - 1) * $perPage;

        if (empty($conditions)) {
            $countSql = "SELECT COUNT(*) as total FROM {$this->table}";
            $total = self::$pdo->query($countSql)->fetch()['total'];
            $sql = "SELECT * FROM {$this->table} ORDER BY {$orderBy} LIMIT {$perPage} OFFSET {$offset}";
            $stmt = self::$pdo->query($sql);
        } else {
            $where = [];
            $params = [];
            foreach ($conditions as $key => $value) {
                $where[] = "{$key} = :{$key}";
                $params[$key] = $value;
            }
            $whereClause = implode(' AND ', $where);
            $countSql = "SELECT COUNT(*) as total FROM {$this->table} WHERE {$whereClause}";
            $stmt = self::$pdo->prepare($countSql);
            $stmt->execute($params);
            $total = $stmt->fetch()['total'];

            $sql = "SELECT * FROM {$this->table} WHERE {$whereClause} ORDER BY {$orderBy} LIMIT {$perPage} OFFSET {$offset}";
            $stmt = self::$pdo->prepare($sql);
            $stmt->execute($params);
        }

        return [
            'data' => $stmt->fetchAll(),
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'totalPages' => ceil($total / $perPage)
        ];
    }

    public function query($sql, $params = [])
    {
        $stmt = self::$pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function queryOne($sql, $params = [])
    {
        $stmt = self::$pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch();
    }

    public function execute($sql, $params = [])
    {
        $stmt = self::$pdo->prepare($sql);
        return $stmt->execute($params);
    }
}