<?php
declare(strict_types=1);

namespace Galaxy\Validation\Rule;

use Galaxy\Validation\GCheck;

readonly class IntegerRule extends NumericRule
{

    public const string TYPE = 'integer';

    protected function check(mixed $value): array
    {

        if (is_int($value)) {
            return [null,$value];
        }
        if ($this->strict || !is_string($value)) {
            return ['type_mismatch',null];
        }
        if (!GCheck::integerString($value)) {
            return ['invalid_integer',null];
        }
        $negative = $value[0] === '-';
        $magnitude = ltrim(ltrim($value,'+-'),'0');
        $magnitude = $magnitude === '' ? '0' : $magnitude;
        $bound = $negative ? substr((string)PHP_INT_MIN,1) : (string)PHP_INT_MAX;
        if (strlen($magnitude)>strlen($bound) || (strlen($magnitude)===strlen($bound) && strcmp($magnitude,$bound)>0)) {
            return ['integer_out_of_range',null];
        }
        $canonical = ($negative && $magnitude !== '0' ? '-' : '') . $magnitude;
        return [null,(int)$canonical];

    }

}
