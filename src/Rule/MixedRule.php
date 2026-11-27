<?php
declare(strict_types=1);

namespace Galaxy\Validation\Rule;

readonly class MixedRule extends ScalarRule
{

    public const string TYPE = 'mixed';

    protected function check(mixed $value): array
    {

        return [null, $value];

    }

}
