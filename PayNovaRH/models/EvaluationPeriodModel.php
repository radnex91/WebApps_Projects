<?php

class EvaluationPeriodModel extends Model
{
    protected $table = 'evaluation_periods';
    protected $primaryKey = 'id';
    protected $fillable = ['name', 'start_date', 'end_date', 'status'];
}