<?php
declare(strict_types=1);

namespace Galaxy\Validation\Rule;

use Galaxy\Validation\GCheck;

readonly class FloatRule extends NumericRule
{

    public const string TYPE = 'float';

    protected function check(mixed $value): array
    {

        if (is_float($value)) {
            return is_finite($value) ? [null, $value] : ['float_out_of_range', null];
        }
        if ($this->strict || (!is_int($value) && !is_string($value))) {
            return ['type_mismatch', null];
        }
        if (is_string($value) && !GCheck::floatString($value)) {
            return ['invalid_float', null];
        }
        $normalized = (float) $value;
        return is_finite($normalized) ? [null, $normalized] : ['float_out_of_range', null];

    }

}
