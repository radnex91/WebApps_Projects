<?php
namespace Core;

class Validator
{
    private array $errors = [];
    private array $data = [];

    public function validate(array $data, array $rules): bool
    {
        $this->data = $data;
        $this->errors = [];
        foreach ($rules as $field => $fieldRules) {
            $fieldRules = is_array($fieldRules) ? $fieldRules : explode('|', $fieldRules);
            foreach ($fieldRules as $rule) {
                $params = [];
                if (str_contains($rule, ':')) {
                    $parts = explode(':', $rule);
                    $rule = $parts[0];
                    $params = explode(',', $parts[1]);
                }
                $value = $data[$field] ?? null;
                $method = 'rule' . ucfirst($rule);
                if (method_exists($this, $method)) {
                    $this->$method($field, $value, $params);
                }
            }
        }
        return empty($this->errors);
    }

    public function errors(): array { return $this->errors; }
    public function firstError(): ?string { $f = reset($this->errors); return $f[0] ?? null; }

    private function addError(string $field, string $msg): void { $this->errors[$field][] = $msg; }

    private function ruleRequired(string $f, mixed $v, array $p): void
    { if ($v === null || $v === '' || (is_array($v) && empty($v))) $this->addError($f, "Le champ {$f} est requis"); }

    private function ruleEmail(string $f, mixed $v, array $p): void
    { if ($v !== null && $v !== '' && !filter_var($v, FILTER_VALIDATE_EMAIL)) $this->addError($f, "Le champ {$f} doit être un email valide"); }

    private function ruleMin(string $f, mixed $v, array $p): void
    { $min = (int)($p[0] ?? 0); if (is_string($v) && strlen($v) < $min) $this->addError($f, "Minimum {$min} caractères"); if (is_numeric($v) && $v < $min) $this->addError($f, "Valeur minimale: {$min}"); }

    private function ruleMax(string $f, mixed $v, array $p): void
    { $max = (int)($p[0] ?? 0); if (is_string($v) && strlen($v) > $max) $this->addError($f, "Maximum {$max} caractères"); }

    private function ruleNumeric(string $f, mixed $v, array $p): void
    { if ($v !== null && $v !== '' && !is_numeric($v)) $this->addError($f, "Le champ {$f} doit être numérique"); }

    private function ruleInteger(string $f, mixed $v, array $p): void
    { if ($v !== null && $v !== '' && !filter_var($v, FILTER_VALIDATE_INT)) $this->addError($f, "Le champ {$f} doit être un entier"); }

    private function ruleDate(string $f, mixed $v, array $p): void
    { if ($v !== null && $v !== '' && !strtotime($v)) $this->addError($f, "Date invalide"); }

    private function ruleIn(string $f, mixed $v, array $p): void
    { if ($v !== null && $v !== '' && !in_array($v, $p)) $this->addError($f, "Valeur invalide"); }

    private function ruleConfirmed(string $f, mixed $v, array $p): void
    { $cf = $p[0] ?? $f . '_confirmation'; if ($v !== ($this->data[$cf] ?? null)) $this->addError($f, "La confirmation ne correspond pas"); }

    private function ruleUnique(string $f, mixed $v, array $p): void
    {
        if ($v === null || $v === '') return;
        $table = $p[0] ?? null; $col = $p[1] ?? $f; $exId = $p[2] ?? null;
        if ($table) {
            $db = Database::getInstance();
            $sql = "SELECT COUNT(*) as cnt FROM {$table} WHERE {$col} = ?";
            $bindings = [$v];
            if ($exId) { $sql .= " AND id != ?"; $bindings[] = $exId; }
            if ((int)$db->fetch($sql, $bindings)->cnt > 0) $this->addError($f, "Cette valeur existe déjà");
        }
    }

    private function rulePhone(string $f, mixed $v, array $p): void
    { if ($v !== null && $v !== '' && !preg_match('/^[+]?[0-9\s\-()]{6,20}$/', $v)) $this->addError($f, "Numéro de téléphone invalide"); }
}