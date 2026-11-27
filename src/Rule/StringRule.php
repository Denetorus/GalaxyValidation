<?php
declare(strict_types=1);

namespace Galaxy\Validation\Rule;

readonly class StringRule extends ScalarRule
{

    public const string TYPE = 'string';

    protected function check(mixed $value): array
    {

        return is_string($value) ? [null, $value] : ['type_mismatch', null];

    }

}
