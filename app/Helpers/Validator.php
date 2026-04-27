<?php
/**
 * Validator Helper
 */

namespace App\Helpers;

class Validator
{
    private array $errors = [];

    /**
     * Validate required field
     */
    public function required(string $field, $value, string $label = ''): self
    {
        $label = $label ?: $field;
        if (empty(trim($value ?? ''))) {
            $this->errors[$field] = "{$label} không được để trống.";
        }
        return $this;
    }

    /**
     * Validate minimum length
     */
    public function minLength(string $field, $value, int $min, string $label = ''): self
    {
        $label = $label ?: $field;
        if (strlen($value) < $min) {
            $this->errors[$field] = "{$label} phải có ít nhất {$min} ký tự.";
        }
        return $this;
    }

    /**
     * Validate email
     */
    public function email(string $field, $value, string $label = ''): self
    {
        $label = $label ?: $field;
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->errors[$field] = "{$label} không hợp lệ.";
        }
        return $this;
    }

    /**
     * Validate value is in allowed list
     */
    public function inList(string $field, $value, array $allowed, string $label = ''): self
    {
        $label = $label ?: $field;
        if (!in_array($value, $allowed)) {
            $this->errors[$field] = "{$label} không hợp lệ.";
        }
        return $this;
    }

    /**
     * Validate numeric value
     */
    public function numeric(string $field, $value, string $label = ''): self
    {
        $label = $label ?: $field;
        if (!is_numeric($value)) {
            $this->errors[$field] = "{$label} phải là số.";
        }
        return $this;
    }

    /**
     * Check if validation passed
     */
    public function passes(): bool
    {
        return empty($this->errors);
    }

    /**
     * Get all errors
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Get first error message
     */
    public function getFirstError(): ?string
    {
        return !empty($this->errors) ? reset($this->errors) : null;
    }
}
