<?php

class LeaveTypeModel extends Model
{
    protected $table = 'leave_types';
    protected $primaryKey = 'id';
    protected $fillable = ['name', 'days_allowed', 'is_paid', 'requires_approval', 'description'];
}