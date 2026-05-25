<?php
/**
 * Définition des routes
 */

// ===== AUTH =====
Router::get('/login', 'AuthController@login');
Router::post('/login', 'AuthController@loginPost');
Router::get('/logout', 'AuthController@logout');
Router::get('/profile', 'AuthController@profile');
Router::post('/profile', 'AuthController@profilePost');
Router::post('/profile/password', 'AuthController@changePassword');

// ===== DASHBOARD =====
Router::get('/dashboard', 'Dashboard@index');
Router::get('/', 'Dashboard@index');

// ===== EMPLOYEES =====
Router::get('/employees', 'Employee@index');
Router::get('/employees/create', 'Employee@create');
Router::post('/employees/create', 'Employee@store');
Router::get('/employees/:id', 'Employee@show');
Router::get('/employees/:id/edit', 'Employee@edit');
Router::post('/employees/:id/edit', 'Employee@update');
Router::post('/employees/:id/delete', 'Employee@delete');

// ===== DEPARTMENTS =====
Router::get('/departments', 'Department@index');
Router::get('/departments/create', 'Department@create');
Router::post('/departments/create', 'Department@store');
Router::get('/departments/:id/edit', 'Department@edit');
Router::post('/departments/:id/edit', 'Department@update');
Router::post('/departments/:id/delete', 'Department@delete');

// ===== POSITIONS =====
Router::get('/positions', 'Position@index');
Router::get('/positions/create', 'Position@create');
Router::post('/positions/create', 'Position@store');
Router::get('/positions/:id/edit', 'Position@edit');
Router::post('/positions/:id/edit', 'Position@update');
Router::post('/positions/:id/delete', 'Position@delete');

// ===== CONTRACTS =====
Router::get('/contracts', 'Contract@index');
Router::get('/contracts/create', 'Contract@create');
Router::post('/contracts/create', 'Contract@store');
Router::get('/contracts/:id/edit', 'Contract@edit');
Router::post('/contracts/:id/edit', 'Contract@update');
Router::post('/contracts/:id/delete', 'Contract@delete');

// ===== LEAVES =====
Router::get('/leaves', 'Leave@index');
Router::get('/leaves/create', 'Leave@create');
Router::post('/leaves/create', 'Leave@store');
Router::get('/leaves/:id', 'Leave@show');
Router::post('/leaves/:id/approve', 'Leave@approve');
Router::post('/leaves/:id/reject', 'Leave@reject');
Router::post('/leaves/:id/cancel', 'Leave@cancel');
Router::get('/leaves/balances', 'Leave@balances');
Router::get('/leaves/calendar', 'Leave@calendar');

// ===== ATTENDANCE =====
Router::get('/attendance', 'Attendance@index');
Router::get('/attendance/create', 'Attendance@create');
Router::post('/attendance/create', 'Attendance@store');
Router::post('/attendance/clock-in', 'Attendance@clockIn');
Router::post('/attendance/clock-out', 'Attendance@clockOut');
Router::get('/attendance/:id/edit', 'Attendance@edit');
Router::post('/attendance/:id/edit', 'Attendance@update');
Router::post('/attendance/:id/delete', 'Attendance@delete');

// ===== PAYROLL =====
Router::get('/payroll', 'Payroll@index');
Router::get('/payroll/create', 'Payroll@create');
Router::post('/payroll/create', 'Payroll@store');
Router::get('/payroll/:id', 'Payroll@show');
Router::post('/payroll/:id/calculate', 'Payroll@calculate');
Router::post('/payroll/:id/validate', 'Payroll@validate');
Router::post('/payroll/:id/close', 'Payroll@close');
Router::get('/payroll/:id/payslip/:employeeId', 'Payroll@payslip');

// ===== RECRUITMENT =====
Router::get('/recruitment', 'Recruitment@index');
Router::get('/recruitment/create', 'Recruitment@create');
Router::post('/recruitment/create', 'Recruitment@store');
Router::get('/recruitment/:id', 'Recruitment@show');
Router::get('/recruitment/:id/edit', 'Recruitment@edit');
Router::post('/recruitment/:id/edit', 'Recruitment@update');
Router::post('/recruitment/:id/delete', 'Recruitment@delete');
Router::post('/recruitment/:id/apply', 'Recruitment@apply');
Router::post('/recruitment/applications/:id/status', 'Recruitment@updateApplicationStatus');

// ===== EVALUATIONS =====
Router::get('/evaluations', 'Evaluation@index');
Router::get('/evaluations/create', 'Evaluation@create');
Router::post('/evaluations/create', 'Evaluation@store');
Router::get('/evaluations/:id', 'Evaluation@show');
Router::post('/evaluations/:id/evaluate', 'Evaluation@evaluate');
Router::post('/evaluations/:id/complete', 'Evaluation@complete');

// ===== TRAINING =====
Router::get('/training', 'Training@index');
Router::get('/training/create', 'Training@create');
Router::post('/training/create', 'Training@store');
Router::get('/training/:id', 'Training@show');
Router::get('/training/:id/edit', 'Training@edit');
Router::post('/training/:id/edit', 'Training@update');
Router::post('/training/:id/enroll', 'Training@enroll');
Router::post('/training/:id/complete', 'Training@completeEnrollment');
Router::post('/training/:id/delete', 'Training@delete');

// ===== DOCUMENTS =====
Router::get('/documents', 'Document@index');
Router::get('/documents/create', 'Document@create');
Router::post('/documents/create', 'Document@store');
Router::get('/documents/:id', 'Document@show');
Router::get('/documents/:id/download', 'Document@download');
Router::post('/documents/:id/delete', 'Document@delete');

// ===== ROLES & PERMISSIONS =====
Router::get('/roles', 'Role@index');
Router::get('/roles/create', 'Role@create');
Router::post('/roles/create', 'Role@store');
Router::get('/roles/:id/edit', 'Role@edit');
Router::post('/roles/:id/edit', 'Role@update');
Router::post('/roles/:id/delete', 'Role@delete');

// ===== REPORTS =====
Router::get('/reports', 'Report@index');
Router::get('/reports/employees', 'Report@employees');
Router::get('/reports/leaves', 'Report@leaves');
Router::get('/reports/payroll', 'Report@payroll');
Router::get('/reports/attendance', 'Report@attendance');
Router::get('/reports/recruitment', 'Report@recruitment');
Router::get('/reports/export/:type', 'Report@export');