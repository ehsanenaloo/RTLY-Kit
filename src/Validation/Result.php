<?php

declare(strict_types=1);

namespace RtlyKit\Validation;

/**
 * Structured outcome of a validation.
 *
 * Error entries are stable, machine-readable codes (for example
 * `invalid_length`, `invalid_checksum`); map them to messages in your UI layer.
 */
final readonly class Result
{
    /**
     * @param  list<string>  $errors
     * @param  array<string, mixed>  $details
     */
    private function __construct(
        private bool $valid,
        private array $errors,
        private array $details,
    ) {}

    /**
     * @param  array<string, mixed>  $details
     */
    public static function valid(array $details = []): self
    {
        return new self(true, [], $details);
    }

    /**
     * @param  string|list<string>  $errors
     * @param  array<string, mixed>  $details
     */
    public static function invalid(string|array $errors, array $details = []): self
    {
        $list = is_string($errors) ? [$errors] : array_values($errors);

        return new self(false, $list, $details);
    }

    public function isValid(): bool
    {
        return $this->valid;
    }

    /**
     * @return list<string>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * @return array<string, mixed>
     */
    public function details(): array
    {
        return $this->details;
    }
}
