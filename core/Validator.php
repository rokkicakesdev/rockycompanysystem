<?php
// core/Validator.php
// ─────────────────────────────────────────────────────────────
// Central input validation class for Rocky Company System.
// Usage:
//   $v = new Validator($_POST);
//   $v->required('name')->maxLen('name', 100)->email('email');
//   if ($v->fails()) { $errors = $v->errors(); }
// ─────────────────────────────────────────────────────────────

class Validator
{
    private array $data;
    private array $errors = [];

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    // ── Helpers ──────────────────────────────────────────────

    private function val(string $field): string
    {
        return trim($this->data[$field] ?? '');
    }

    private function addError(string $field, string $message): static
    {
        $this->errors[$field][] = $message;
        return $this;
    }

    public function fails(): bool
    {
        return !empty($this->errors);
    }

    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * Returns a single flat HTML string of all errors, ready to echo in a Bootstrap alert.
     */
    public function errorHtml(): string
    {
        $lines = [];
        foreach ($this->errors as $msgs) {
            foreach ($msgs as $msg) {
                $lines[] = '<li>' . htmlspecialchars($msg) . '</li>';
            }
        }
        return "<div class='alert alert-danger'><ul class='mb-0'>" . implode('', $lines) . "</ul></div>";
    }

    // ── Rules ────────────────────────────────────────────────

    /** Field must be present and non-empty. */
    public function required(string $field, string $label = ''): static
    {
        $label = $label ?: ucfirst(str_replace('_', ' ', $field));
        if ($this->val($field) === '') {
            $this->addError($field, "{$label} is required.");
        }
        return $this;
    }

    /** Maximum string length. */
    public function maxLen(string $field, int $max, string $label = ''): static
    {
        $label = $label ?: ucfirst(str_replace('_', ' ', $field));
        if (mb_strlen($this->val($field)) > $max) {
            $this->addError($field, "{$label} must not exceed {$max} characters.");
        }
        return $this;
    }

    /** Minimum string length. */
    public function minLen(string $field, int $min, string $label = ''): static
    {
        $label = $label ?: ucfirst(str_replace('_', ' ', $field));
        $v = $this->val($field);
        if ($v !== '' && mb_strlen($v) < $min) {
            $this->addError($field, "{$label} must be at least {$min} characters.");
        }
        return $this;
    }

    /** Must be a valid email address. */
    public function email(string $field, string $label = ''): static
    {
        $label = $label ?: ucfirst(str_replace('_', ' ', $field));
        $v = $this->val($field);
        if ($v !== '' && !filter_var($v, FILTER_VALIDATE_EMAIL)) {
            $this->addError($field, "{$label} must be a valid email address.");
        }
        return $this;
    }

    /** Must be numeric (int or float). */
    public function numeric(string $field, string $label = ''): static
    {
        $label = $label ?: ucfirst(str_replace('_', ' ', $field));
        $v = $this->val($field);
        if ($v !== '' && !is_numeric($v)) {
            $this->addError($field, "{$label} must be a number.");
        }
        return $this;
    }

    /** Must be a positive number greater than zero. */
    public function positiveNumber(string $field, string $label = ''): static
    {
        $label = $label ?: ucfirst(str_replace('_', ' ', $field));
        $v = $this->val($field);
        if ($v !== '' && (!is_numeric($v) || (float)$v <= 0)) {
            $this->addError($field, "{$label} must be a positive number.");
        }
        return $this;
    }

    /** Must be a non-negative number (zero allowed). */
    public function nonNegative(string $field, string $label = ''): static
    {
        $label = $label ?: ucfirst(str_replace('_', ' ', $field));
        $v = $this->val($field);
        if ($v !== '' && (!is_numeric($v) || (float)$v < 0)) {
            $this->addError($field, "{$label} must be 0 or greater.");
        }
        return $this;
    }

    /** Must be a valid date (Y-m-d). */
    public function date(string $field, string $label = ''): static
    {
        $label = $label ?: ucfirst(str_replace('_', ' ', $field));
        $v = $this->val($field);
        if ($v !== '') {
            $d = DateTime::createFromFormat('Y-m-d', $v);
            if (!$d || $d->format('Y-m-d') !== $v) {
                $this->addError($field, "{$label} must be a valid date (YYYY-MM-DD).");
            }
        }
        return $this;
    }

    /** Second date must be on or after the first date. */
    public function dateAfter(string $fieldFrom, string $fieldTo, string $labelFrom = '', string $labelTo = ''): static
    {
        $labelTo = $labelTo ?: ucfirst(str_replace('_', ' ', $fieldTo));
        $from = $this->val($fieldFrom);
        $to   = $this->val($fieldTo);
        if ($from !== '' && $to !== '' && $to < $from) {
            $this->addError($fieldTo, "{$labelTo} must be on or after the start date.");
        }
        return $this;
    }

    /** Value must be one of the allowed options. */
    public function inList(string $field, array $allowed, string $label = ''): static
    {
        $label = $label ?: ucfirst(str_replace('_', ' ', $field));
        $v = $this->val($field);
        if ($v !== '' && !in_array($v, $allowed, true)) {
            $this->addError($field, "{$label} contains an invalid value.");
        }
        return $this;
    }

    /** Must be a valid Philippine phone number (basic format check). */
    public function phone(string $field, string $label = ''): static
    {
        $label = $label ?: ucfirst(str_replace('_', ' ', $field));
        $v = $this->val($field);
        if ($v !== '' && !preg_match('/^[0-9+\-\s()]{7,20}$/', $v)) {
            $this->addError($field, "{$label} must be a valid phone number.");
        }
        return $this;
    }

    /** Must be a valid YYYY-MM payroll period format. */
    public function payrollPeriod(string $field, string $label = ''): static
    {
        $label = $label ?: ucfirst(str_replace('_', ' ', $field));
        $v = $this->val($field);
        if ($v !== '' && !preg_match('/^\d{4}-\d{2}$/', $v)) {
            $this->addError($field, "{$label} must be in YYYY-MM format.");
        }
        return $this;
    }
}