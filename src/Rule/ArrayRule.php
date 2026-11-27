<?php
declare(strict_types=1);

namespace Galaxy\Validation\Rule;

use Galaxy\Validation\ValidationError;
use Galaxy\Validation\ValidationRuleInterface;
use InvalidArgumentException;

readonly class ArrayRule implements ValidationRuleInterface
{

    public function __construct(public ValidationRuleInterface $elementRule = new MixedRule())
    {
        if ($elementRule instanceof self || $elementRule instanceof ArrayIntegerRule
            || $elementRule instanceof ArrayStringRule || $elementRule instanceof ArrayFloatRule
            || $elementRule instanceof ArrayBooleanRule) {
            throw new InvalidArgumentException('Nested ArrayRule is not supported.');
        }
    }

    /** @return array{0: list<ValidationError>, 1: mixed} */
    public function validate(mixed $value): array
    {

        if (!is_array($value)) {
            return [[new ValidationError('type_mismatch', 'array')], null];
        }
        if ($this->elementRule::class === MixedRule::class) {
            return [[], $value];
        }
        $normalized = [];
        $errors = [];
        foreach ($value as $key => $item) {

            $pair = $this->elementRule->validate($item);
            if (!array_is_list($pair) || count($pair) !== 2 || !is_array($pair[0]) || !array_is_list($pair[0])) {
                throw new InvalidArgumentException('An element rule must return an errors/value pair.');
            }
            [$elementErrors, $elementValue] = $pair;
            foreach ($elementErrors as $error) {

                if (!$error instanceof ValidationError) {
                    throw new InvalidArgumentException('Expected ValidationError.');
                }
                $errors[] = new ValidationError($error->reason, $error->expectedType, $key);

            }
            if ($elementErrors === []) {
                $normalized[$key] = $elementValue;
            }

        }
        return $errors === [] ? [[], $normalized] : [$errors, null];

    }

}