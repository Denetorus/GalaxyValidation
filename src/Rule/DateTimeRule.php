<?php
declare(strict_types=1);

namespace Galaxy\Validation\Rule;

use DateTimeImmutable;
use Galaxy\Date\GDate;

readonly class DateTimeRule extends ScalarRule
{

    public const string TYPE = 'datetime';

    protected function normalizeOptions(array $options): array
    {

        if ($options !== []) {
            GDate::toDateTime(null, $options);
        }
        return $options;

    }

    protected function check(mixed $value): array
    {

        if ($value instanceof DateTimeImmutable) {
            return [null, $value];
        }
        if ($this->strict || !is_string($value) || $this->options === []) {
            return ['type_mismatch', null];
        }
        $result = GDate::toDateTime($value, $this->options);
        return [$result->reason, $result->isValid() ? $result->value() : null];

    }

}
