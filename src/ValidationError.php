<?php
declare(strict_types=1);

namespace Galaxy\Validation;

use InvalidArgumentException;

final readonly class ValidationError
{

    public function __construct(public string $reason, public string $expectedType, public int|string|null $key = null)
    {
        if ($reason === '' || $expectedType === '') {
            throw new InvalidArgumentException('Empty validation error descriptor.');
        }
    }

}
