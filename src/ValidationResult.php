<?php
declare(strict_types=1);

namespace Galaxy\Validation;

final readonly class ValidationResult
{

    public array $violations;
    private mixed $normalizedValue;

    private function __construct(array $violations, mixed $value)
    {

        if (!array_is_list($violations)) {
            throw new \InvalidArgumentException('Violations must be a list.');
        }
        $copy = [];
        foreach ($violations as $violation) {
            if (!$violation instanceof ValidationViolation) {
                throw new \InvalidArgumentException('Expected ValidationViolation.');
            }
            $copy[] = $violation;
        }
        $this->violations = $copy;
        $this->normalizedValue = $copy === [] ? $value : null;

    }
    public static function success(mixed $value): self {
        return new self([], $value);
    }
    public static function failure(array $violations): self
    {
        if ($violations === []) {
            throw new \InvalidArgumentException('Failure requires violations.');
        }
        return new self($violations, null);
    }
    public function isValid(): bool {
        return $this->violations === [];
    }
    public function value(): mixed
    {
        if (!$this->isValid()) {
            throw new \LogicException('Invalid validation result has no normalized value.');
        }
        return $this->normalizedValue;
    }
    public function __debugInfo(): array {
        return ['violations'=>$this->violations];
    }

}
