<?php
declare(strict_types=1);

namespace Galaxy\Validation\Rule;

use InvalidArgumentException;

abstract readonly class NumericRule extends ScalarRule
{

    protected function normalizeOptions(array $options): array
    {

        $aliases = ['>=' => 'min', '<=' => 'max', '>' => 'greater', '<' => 'less'];
        $normalized = [];
        foreach ($options as $key => $bound) {
            $key = $aliases[$key] ?? $key;
            if (array_key_exists($key, $normalized)) {
                throw new InvalidArgumentException('Duplicate numeric bound.');
            }
            if (!in_array($key, ['min', 'max', 'greater', 'less'], true)
                || (!is_int($bound) && !is_float($bound)) || (is_float($bound) && !is_finite($bound))) {
                throw new InvalidArgumentException('Numeric bounds must be finite numbers with supported keys.');
            }
            $normalized[$key] = $bound;
        }
        $options = $normalized;

        return $options;

    }

    protected function constrain(mixed $normalized): array
    {

        foreach (['min', 'max', 'greater', 'less'] as $key) {

            if (!array_key_exists($key, $this->options)) {
                continue;
            }
            $valid = match ($key) {
                'min' => $normalized >= $this->options[$key],
                'max' => $normalized <= $this->options[$key],
                'greater' => $normalized > $this->options[$key],
                'less' => $normalized < $this->options[$key],
            };
            if (!$valid) {
                return [match ($key) {
                    'min' => 'below_min', 'max' => 'above_max',
                    'greater' => 'not_greater_than', 'less' => 'not_less_than',
                }, null];
            }

        }
        return [null, $normalized];

    }

}
