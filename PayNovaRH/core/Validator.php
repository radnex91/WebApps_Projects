<?php
/**
 * Validation des données
 */
class Validator
{
    private $errors = [];
    private $data = [];

    public function __construct($data)
    {
        $this->data = $data;
    }

    public function required($field, $message = null)
    {
        if (!isset($this->data[$field]) || trim($this->data[$field]) === '') {
            $this->errors[$field] = $message ?? "Le champ {$field} est requis.";
        }
        return $this;
    }

    public function email($field, $message = null)
    {
        if (isset($this->data[$field]) && !filter_var($this->data[$field], FILTER_VALIDATE_EMAIL)) {
            $this->errors[$field] = $message ?? "Le champ {$field} doit être un email valide.";
        }
        return $this;
    }

    public function minLength($field, $length, $message = null)
    {
        if (isset($this->data[$field]) && strlen($this->data[$field]) < $length) {
            $this->errors[$field] = $message ?? "Le champ {$field} doit contenir au moins {$length} caractères.";
        }
        return $this;
    }

    public function maxLength($field, $length, $message = null)
    {
        if (isset($this->data[$field]) && strlen($this->data[$field]) > $length) {
            $this->errors[$field] = $message ?? "Le champ {$field} ne doit pas dépasser {$length} caractères.";
        }
        return $this;
    }

    public function numeric($field, $message = null)
    {
        if (isset($this->data[$field]) && !is_numeric($this->data[$field])) {
            $this->errors[$field] = $message ?? "Le champ {$field} doit être un nombre.";
        }
        return $this;
    }

    public function date($field, $message = null)
    {
        if (isset($this->data[$field]) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->data[$field])) {
            $this->errors[$field] = $message ?? "Le champ {$field} doit être une date valide.";
        }
        return $this;
    }

    public function unique($field, $table, $excludeId = null, $message = null)
    {
        if (isset($this->data[$field])) {
            $model = new Model();
            $model->table = $table;
            $results = $model->findBy([$field => $this->data[$field]]);
            if ($results && ($excludeId === null || $results[0]['id'] != $excludeId)) {
                $this->errors[$field] = $message ?? "Cette valeur existe déjà.";
            }
        }
        return $this;
    }

    public function in($field, $values, $message = null)
    {
        if (isset($this->data[$field]) && !in_array($this->data[$field], $values)) {
            $this->errors[$field] = $message ?? "Valeur invalide pour {$field}.";
        }
        return $this;
    }

    public function passes()
    {
        return empty($this->errors);
    }

    public function fails()
    {
        return !empty($this->errors);
    }

    public function errors()
    {
        return $this->errors;
    }

    public function firstError()
    {
        return !empty($this->errors) ? reset($this->errors) : null;
    }
}