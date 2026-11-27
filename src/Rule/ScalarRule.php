<?php
declare(strict_types=1);

namespace Galaxy\Validation\Rule;

use Galaxy\Validation\ValidationRuleInterface;
use Galaxy\Validation\ValidationError;
use InvalidArgumentException;

abstract readonly class ScalarRule implements ValidationRuleInterface
{

    public const string TYPE = '';
    public array $options;

    public function __construct(public bool $strict = true, array $options = [])
    {
        $this->options = $this->normalizeOptions($options);
    }

    protected function normalizeOptions(array $options): array
    {
        if ($options !== []) {
            throw new InvalidArgumentException('Validation options are not supported for this rule.');
        }
        return [];
    }

    abstract protected function check(mixed $value): array;

    protected function constrain(mixed $value): array
    {
        return [null, $value];
    }

    /** @return array{0: list<ValidationError>, 1: mixed} */
    public function validate(mixed $value): array
    {

        [$reason, $normalized] = $this->check($value);
        if ($reason === null) {
            [$reason, $normalized] = $this->constrain($normalized);
        }
        return $reason === null ? [[], $normalized] : [[new ValidationError($reason, static::TYPE)], null];

    }

}
