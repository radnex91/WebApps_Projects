<?php

class ApplicationModel extends Model
{
    protected $table = 'applications';
    protected $primaryKey = 'id';
    protected $fillable = [
        'job_posting_id', 'first_name', 'last_name', 'email', 'phone', 'cover_letter',
        'resume', 'status', 'interview_date', 'interview_notes', 'score', 'employee_id'
    ];

    public function getWithJob($jobPostingId)
    {
        $sql = "SELECT a.*, jp.title AS job_title, jp.location, jp.type AS job_type
                FROM applications a
                LEFT JOIN job_postings jp ON a.job_posting_id = jp.id
                WHERE a.job_posting_id = :job_posting_id
                ORDER BY a.score DESC, a.last_name, a.first_name";
        return $this->query($sql, ['job_posting_id' => $jobPostingId]);
    }
}