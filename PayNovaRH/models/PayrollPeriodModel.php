<?php

class PayrollPeriodModel extends Model
{
    protected $table = 'payroll_periods';
    protected $primaryKey = 'id';
    protected $fillable = ['month', 'year', 'status', 'payment_date'];
}