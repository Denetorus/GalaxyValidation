<?php
declare(strict_types=1);

namespace Galaxy\Validation\Rule;

use Galaxy\Date\GDate;

readonly class DateStringRule extends ScalarRule
{

    public const string TYPE = 'date-string';

    protected function normalizeOptions(array $options): array
    {

        GDate::checkDateOptions($options);
        return $options;

    }

    protected function check(mixed $value): array
    {

        $result = GDate::validateDate($value, $this->options);
        return [$result->reason, $result->isValid() ? $result->value() : null];

    }

}
