<?php
declare(strict_types=1);

namespace Galaxy\Validation\Rule;

use Galaxy\Date\GDate;

readonly class TimestampRule extends ScalarRule
{

    public const string TYPE = 'timestamp';

    protected function normalizeOptions(array $options): array
    {

        if ($options !== []) {
            GDate::toDateTime(null, $options);
        }
        return $options;

    }

    protected function check(mixed $value): array
    {

        if (is_int($value)) {
            return [null, $value];
        }
        if ($this->strict || !is_string($value) || $this->options === []) {
            return ['type_mismatch', null];
        }
        $result = GDate::toTimestamp($value, $this->options);
        return [$result->reason, $result->isValid() ? $result->value() : null];

    }

}
