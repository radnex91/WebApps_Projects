<?php
// Constantes de l'application

// Statuts employé
define('EMPLOYEE_ACTIVE', 'actif');
define('EMPLOYEE_INACTIVE', 'inactif');
define('EMPLOYEE_ON_LEAVE', 'en_congé');
define('EMPLOYEE_TERMINATED', 'résilié');

// Statuts congé
define('LEAVE_PENDING', 'en_attente');
define('LEAVE_APPROVED', 'approuvé');
define('LEAVE_REJECTED', 'refusé');
define('LEAVE_CANCELLED', 'annulé');

// Statuts pointage
define('ATTENDANCE_PRESENT', 'présent');
define('ATTENDANCE_ABSENT', 'absent');
define('ATTENDANCE_LATE', 'retard');
define('ATTENDANCE_LEAVE', 'congé');
define('ATTENDANCE_HALF_DAY', 'mi-journée');

// Statuts paie
define('PAYROLL_DRAFT', 'brouillon');
define('PAYROLL_PROCESSING', 'en_cours');
define('PAYROLL_CLOSED', 'clôturé');

// Statuts candidature
define('APP_NEW', 'nouveau');
define('APP_INTERVIEW', 'entretien');
define('APP_TEST', 'test');
define('APP_OFFER', 'offre');
define('APP_HIRED', 'embauché');
define('APP_REJECTED', 'refusé');

// Statuts évaluation
define('EVAL_DRAFT', 'brouillon');
define('EVAL_SELF', 'auto_évaluation');
define('EVAL_MANAGER', 'évaluation_manager');
define('EVAL_COMPLETED', 'complété');

// Types de contrat
define('CONTRACT_CDI', 'CDI');
define('CONTRACT_CDD', 'CDD');
define('CONTRACT_STAGE', 'Stage');
define('CONTRACT_FREELANCE', 'Freelance');
define('CONTRACT_INTERIM', 'Intérim');