<?php
declare(strict_types=1);

namespace Galaxy\Validation\Rule;

readonly class ArrayStringRule extends StringRule
{
    use ArrayValidationTrait;
}
