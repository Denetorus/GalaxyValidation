<?php
declare(strict_types=1);

namespace Galaxy\Validation;

use InvalidArgumentException;
use LogicException;

final readonly class ValidationResult
{

    public array $errors;
    private mixed $normalizedValue;

    private function __construct(array $errors, mixed $value)
    {

        if (!array_is_list($errors)) {
            throw new InvalidArgumentException('Errors must be a list.');
        }
        $copy = [];
        foreach ($errors as $error) {
            if (!$error instanceof ValidationError) {
                throw new InvalidArgumentException('Expected ValidationError.');
            }
            $copy[] = $error;
        }
        $this->errors = $copy;
        $this->normalizedValue = $copy === [] ? $value : null;

    }
    public static function success(mixed $value): self {
        return new self([], $value);
    }
    public static function failure(array $errors): self
    {
        if ($errors === []) {
            throw new InvalidArgumentException('Failure requires errors.');
        }
        return new self($errors, null);
    }
    public function isValid(): bool {
        return $this->errors === [];
    }
    public function value(): mixed
    {
        if (!$this->isValid()) {
            throw new LogicException('Invalid validation result has no normalized value.');
        }
        return $this->normalizedValue;
    }
    public function __debugInfo(): array {
        return ['errors'=>$this->errors];
    }

}
