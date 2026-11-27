<?php
declare(strict_types=1);

namespace Galaxy\Validation\Rule;

use Galaxy\Validation\ValidationError;

/** @internal Shared traversal for the built-in typed array rules. */
trait ArrayValidationTrait
{

    /** @return array{0: list<ValidationError>, 1: mixed} */
    final public function validate(mixed $value): array
    {

        if (!is_array($value)) {
            return [[new ValidationError('type_mismatch', 'array')], null];
        }
        $normalized = [];
        $errors = [];
        foreach ($value as $key => $item) {

            [$reason, $elementValue] = $this->check($item);
            if ($reason === null) {
                [$reason, $elementValue] = $this->constrain($elementValue);
            }
            if ($reason === null) {
                $normalized[$key] = $elementValue;
            } else {
                $errors[] = new ValidationError($reason, static::TYPE, $key);
            }

        }
        return $errors === [] ? [[], $normalized] : [$errors, null];

    }

}
