<?php
declare(strict_types=1);

namespace Galaxy\Validation;

use Galaxy\Date\GDate;

final readonly class ValidationRule
{

    public array $options;

    public function __construct(public string $type, public bool $strict = true, array $options = [])
    {

        $scalar = str_starts_with($type, 'array[') && str_ends_with($type, ']') ? substr($type, 6, -1) : $type;
        $supported = ['string', 'integer', 'float', 'boolean', 'date-string', 'datetime-string', 'datetime', 'timestamp'];
        if (!in_array($type, $supported, true) && $type !== 'mixed' && $type !== 'array[mixed]'
            && !in_array($type, array_map(
            static fn (string $item): string => 'array[' . $item . ']',
            $supported,
        ), true)) {
            throw new \InvalidArgumentException('Unsupported validation type.');
        }
        if ($scalar === 'date-string') {
            GDate::checkDateOptions($options);
        }
        elseif ($scalar === 'datetime-string') {
            GDate::checkDateTimeOptions($options);
        }
        elseif ($scalar === 'datetime' || $scalar === 'timestamp') {

            if ($options !== []) {
                // Validate conversion configuration through the public GalaxyDate API; null is not converted.
                GDate::toDateTime(null, $options);
            }

        }
        elseif ($options !== []) {
            throw new \InvalidArgumentException('Validation options are not supported for this type.');
        }
        $this->options = $options;

    }

}