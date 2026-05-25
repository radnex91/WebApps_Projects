<?php

class EvaluationCriteriaModel extends Model
{
    protected $table = 'evaluation_criteria';
    protected $primaryKey = 'id';
    protected $fillable = ['name', 'description', 'max_score', 'weight', 'category'];
}