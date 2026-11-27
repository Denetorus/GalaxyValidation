<?php
declare(strict_types=1);

namespace Galaxy\Validation;

use InvalidArgumentException;

final class GValidator
{

    public static function validate(mixed $value, ValidationRuleInterface $rule): ValidationResult
    {

        $pair = $rule->validate($value);
        if (!array_is_list($pair) || count($pair) !== 2 || !is_array($pair[0])) {
            throw new InvalidArgumentException('A rule must return an errors/value pair.');
        }
        [$errors, $normalized] = $pair;
        return $errors === [] ? ValidationResult::success($normalized) : ValidationResult::failure($errors);

    }

}
