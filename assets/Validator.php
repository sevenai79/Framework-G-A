<?php

declare(strict_types=1);

namespace Assets;

final class Validator
{
    private array $data;
    private array $errors = [];

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function required(string $field, ?string $message = null): self
    {
        $value = $this->data[$field] ?? null;

        $isEmptyString = is_string($value) && trim($value) === '';
        $isEmptyArray = is_array($value) && $value === [];
        if ($value === null || $isEmptyString || $isEmptyArray) {
            $this->addError($field, $message ?? "Il campo {$field} e obbligatorio");
        }

        return $this;
    }

    public function email(string $field, ?string $message = null): self
    {
        if (!$this->hasValue($field)) {
            return $this;
        }

        $value = (string) $this->data[$field];
        if (filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
            $this->addError($field, $message ?? "Il campo {$field} deve essere una email valida");
        }

        return $this;
    }

    public function min(string $field, int $min, ?string $message = null): self
    {
        if (!$this->hasValue($field)) {
            return $this;
        }

        $value = $this->data[$field];
        if ($this->measure($value) < $min) {
            $this->addError($field, $message ?? "Il campo {$field} deve avere almeno {$min} caratteri");
        }

        return $this;
    }

    public function max(string $field, int $max, ?string $message = null): self
    {
        if (!$this->hasValue($field)) {
            return $this;
        }

        $value = $this->data[$field];
        if ($this->measure($value) > $max) {
            $this->addError($field, $message ?? "Il campo {$field} deve avere massimo {$max} caratteri");
        }

        return $this;
    }

    public function numeric(string $field, ?string $message = null): self
    {
        if (!$this->hasValue($field)) {
            return $this;
        }

        if (!is_numeric($this->data[$field])) {
            $this->addError($field, $message ?? "Il campo {$field} deve essere numerico");
        }

        return $this;
    }

    public function in(string $field, array $allowedValues, ?string $message = null): self
    {
        if (!$this->hasValue($field)) {
            return $this;
        }

        $value = $this->data[$field];
        if (!in_array($value, $allowedValues, true)) {
            $allowed = implode(', ', array_map(static fn($item) => (string) $item, $allowedValues));
            $this->addError($field, $message ?? "Il campo {$field} deve essere uno tra: {$allowed}");
        }

        return $this;
    }

    public function regex(string $field, string $pattern, ?string $message = null): self
    {
        if (!$this->hasValue($field)) {
            return $this;
        }

        $value = (string) $this->data[$field];
        if (preg_match($pattern, $value) !== 1) {
            $this->addError($field, $message ?? "Il campo {$field} non ha un formato valido");
        }

        return $this;
    }

    public function fails(): bool
    {
        return $this->errors !== [];
    }

    public function passes(): bool
    {
        return !$this->fails();
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function first(?string $field = null): ?string
    {
        if ($field !== null) {
            return $this->errors[$field][0] ?? null;
        }

        foreach ($this->errors as $messages) {
            if (isset($messages[0])) {
                return $messages[0];
            }
        }

        return null;
    }

    private function hasValue(string $field): bool
    {
        if (!array_key_exists($field, $this->data)) {
            return false;
        }

        $value = $this->data[$field];
        return !(is_string($value) && trim($value) === '');
    }

    private function addError(string $field, string $message): void
    {
        if (!isset($this->errors[$field])) {
            $this->errors[$field] = [];
        }

        $this->errors[$field][] = $message;
    }

    private function measure($value): int
    {
        if (is_string($value)) {
            $normalized = trim($value);
            return strlen($normalized);
        }

        if (is_array($value)) {
            return count($value);
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        return 0;
    }
}
