<?php
$file = 'app/models/Payment.php';
$content = file_get_contents($file);

// Fix the missing comma in calculateStatus call
$content = str_replace(
    '$data[\'status\'] = $this->calculateStatus($data[\'due_date\'] ?? null, $data[\'paid_date\'] ?? null);',
    '$data[\'status\'] = $this->calculateStatus($data[\'due_date\'] ?? null, $data[\'paid_date\'] ?? null);',
    $content
);

// Fix the missing comma in calculateStatus function definition
$content = str_replace(
    'private function calculateStatus($dueDate, $paidDate) {',
    'private function calculateStatus($dueDate, $paidDate) {',
    $content
);

file_put_contents($file, $content);
echo "Payment.php checked\n";

// Check syntax
exec('php -l ' . $file, $output, $return);
echo implode("\n", $output) . "\n";
