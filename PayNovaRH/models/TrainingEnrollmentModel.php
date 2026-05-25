<?php

class TrainingEnrollmentModel extends Model
{
    protected $table = 'training_enrollments';
    protected $primaryKey = 'id';
    protected $fillable = ['training_program_id', 'employee_id', 'status', 'score', 'feedback'];
}