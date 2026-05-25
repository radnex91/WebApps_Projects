<?php

class TrainingProgramModel extends Model
{
    protected $table = 'training_programs';
    protected $primaryKey = 'id';
    protected $fillable = ['title', 'description', 'trainer', 'start_date', 'end_date', 'location', 'capacity', 'budget', 'status'];

    public function getWithEnrollmentCount()
    {
        $sql = "SELECT tp.*, COUNT(te.id) AS enrollment_count
                FROM training_programs tp
                LEFT JOIN training_enrollments te ON tp.id = te.training_program_id
                GROUP BY tp.id
                ORDER BY tp.start_date DESC";
        return $this->query($sql);
    }
}