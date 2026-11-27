<?php
declare(strict_types=1);

namespace Galaxy\Validation\Rule;

use Galaxy\Date\GDate;

readonly class DateTimeStringRule extends ScalarRule
{

    public const string TYPE = 'datetime-string';

    protected function normalizeOptions(array $options): array
    {

        GDate::checkDateTimeOptions($options);
        return $options;

    }

    protected function check(mixed $value): array
    {

        $result = GDate::validateDateTime($value, $this->options);
        return [$result->reason, $result->isValid() ? $result->value() : null];

    }

}
