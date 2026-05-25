<?php
class Controller {
    protected $db;
    
    public function __construct() {
        $this->db = getDb();
    }
    
    public function model($model) {
        $modelFile = ROOT_PATH . '/app/models/' . $model . '.php';
        if (file_exists($modelFile)) {
            require_once $modelFile;
            return new $model($this->db);
        }
        return null;
    }
    
    public function view($view, $data = []) {
        extract($data);
        $viewFile = ROOT_PATH . '/app/views/' . $view . '.php';
        if (!file_exists($viewFile)) {
            die("Vue non trouvée: " . $view);
        }
        require $viewFile;
    }
    
    public function json($data) {
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
    
    public function redirect($url) {
        redirect($url);
    }
}