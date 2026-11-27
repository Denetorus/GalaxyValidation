<?php
declare(strict_types=1);

namespace Galaxy\Validation;

interface ValidationRuleInterface
{
    /** @return array{0: list<ValidationError>, 1: mixed} */
    public function validate(mixed $value): array;
}
