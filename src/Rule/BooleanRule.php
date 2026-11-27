<?php
declare(strict_types=1);

namespace Galaxy\Validation\Rule;

use Galaxy\Validation\GCheck;

readonly class BooleanRule extends ScalarRule
{

    public const string TYPE = 'boolean';

    protected function check(mixed $value): array
    {

        if (is_bool($value)) {
            return [null, $value];
        }
        if ($this->strict || (!is_int($value) && !is_string($value))) {
            return ['type_mismatch', null];
        }
        if (is_int($value) && ($value === 0 || $value === 1)) {
            return [null, $value === 1];
        }
        if (GCheck::booleanString($value)) {
            return [null, $value === '1' || strcasecmp($value, 'true') === 0];
        }
        return ['invalid_boolean', null];

    }

}
