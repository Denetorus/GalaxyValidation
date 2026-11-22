<?php
declare(strict_types=1);

namespace Galaxy\Validation;

final readonly class ValidationViolation
{

    public function __construct(public string $reason, public string $expectedType, public int|string|null $key = null)
    {
        if ($reason === '' || $expectedType === '') {
            throw new \InvalidArgumentException('Empty violation descriptor.');
        }
    }

}
