<?php

class DocumentModel extends Model
{
    protected $table = 'documents';
    protected $primaryKey = 'id';
    protected $fillable = ['employee_id', 'title', 'category', 'file_path', 'file_type', 'file_size', 'description', 'uploaded_by'];

    public function getWithEmployee()
    {
        $sql = "SELECT d.*, CONCAT(e.first_name, ' ', e.last_name) AS employee_name
                FROM documents d
                LEFT JOIN employees e ON d.employee_id = e.id
                ORDER BY d.id DESC";
        return $this->query($sql);
    }
}