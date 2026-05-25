<?php
namespace App\Core;

class Validator
{
    private array $data;
    private array $rules;
    private array $errors = [];

    public function __construct(array $data, array $rules)
    {
        $this->data = $data;
        $this->rules = $rules;
    }

    /**
     * Validate data against rules
     */
    public function validate(): bool
    {
        $this->errors = [];

        foreach ($this->rules as $field => $fieldRules) {
            $value = $this->data[$field] ?? null;
            $fieldRules = is_string($fieldRules) ? explode('|', $fieldRules) : $fieldRules;

            foreach ($fieldRules as $rule) {
                $this->applyRule($field, $value, $rule);
            }
        }

        return empty($this->errors);
    }

    /**
     * Apply a single validation rule
     */
    private function applyRule(string $field, $value, string $rule): void
    {
        $params = [];
        if (strpos($rule, ':') !== false) {
            [$rule, $paramStr] = explode(':', $rule, 2);
            $params = explode(',', $paramStr);
        }

        switch ($rule) {
            case 'required':
                if ($value === null || $value === '') {
                    $this->errors[$field] = t('required_field');
                }
                break;
            case 'email':
                if ($value && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $this->errors[$field] = 'Invalid email format';
                }
                break;
            case 'min':
                if ($value && strlen((string)$value) < (int)($params[0] ?? 0)) {
                    $this->errors[$field] = "Minimum length is {$params[0]}";
                }
                break;
            case 'max':
                if ($value && strlen((string)$value) > (int)($params[0] ?? 255)) {
                    $this->errors[$field] = "Maximum length is {$params[0]}";
                }
                break;
            case 'numeric':
                if ($value !== null && $value !== '' && !is_numeric($value)) {
                    $this->errors[$field] = 'Must be a number';
                }
                break;
            case 'min_value':
                if ($value !== null && $value !== '' && (float)$value < (float)($params[0] ?? 0)) {
                    $this->errors[$field] = "Minimum value is {$params[0]}";
                }
                break;
            case 'in':
                if ($value && !in_array($value, $params)) {
                    $this->errors[$field] = 'Invalid selection';
                }
                break;
        }
    }

    /**
     * Get validation errors
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Get first error
     */
    public function getFirstError(): ?string
    {
        return !empty($this->errors) ? reset($this->errors) : null;
    }

    /**
     * Static helper: quick validate and return errors
     */
    public static function check(array $data, array $rules): array
    {
        $v = new self($data, $rules);
        $v->validate();
        return $v->getErrors();
    }
}