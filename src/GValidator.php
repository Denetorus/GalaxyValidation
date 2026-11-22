<?php
declare(strict_types=1);

namespace Galaxy\Validation;

final class GValidator
{

    public static function validate(mixed $value, ValidationRule $rule): ValidationResult
    {

        $type = $rule->type;
        if ($type === 'mixed') {
            return ValidationResult::success($value);
        }
        if (str_starts_with($type, 'array[')) {

            if (!is_array($value)) {
                return ValidationResult::failure([new ValidationViolation('type_mismatch',$type)]);
            }
            if ($type === 'array[mixed]') {
                return ValidationResult::success($value);
            }
            $inner = substr($type,6,-1);
            $violations = [];
            $normalized = [];
            foreach ($value as $key=>$item) {

                [$reason,$converted] = self::scalar($item, $inner, $rule->strict, $rule->options);
                if ($reason !== null) {
                    $violations[] = new ValidationViolation($reason,$inner,$key);
                }
                else {
                    $normalized[$key] = $converted;
                }

            }
            return $violations === [] ? ValidationResult::success($normalized) : ValidationResult::failure($violations);

        }
        [$reason,$normalized] = self::scalar($value, $type, $rule->strict, $rule->options);
        return $reason === null ? ValidationResult::success($normalized)
        : ValidationResult::failure([new ValidationViolation($reason,$type)]);

    }
    private static function scalar(mixed $value, string $type, bool $strict, array $options): array
    {

        if ($type === 'datetime-string') {
            $result = \Galaxy\Date\GDate::validateDateTime($value, $options);
            return [$result->reason, $result->isValid() ? $result->value() : null];
        }
        if ($type === 'date-string') {
            $result = \Galaxy\Date\GDate::validateDate($value, $options);
            return [$result->reason, $result->isValid() ? $result->value() : null];
        }
        if ($type === 'datetime' || $type === 'timestamp') {
            return self::temporal($value, $type, $strict, $options);
        }
        if ($type === 'string') {
            return is_string($value) ? [null,$value] : ['type_mismatch',null];
        }

        if ($type === 'float') {
            return self::floatingPoint($value, $strict);
        }
        if ($type === 'boolean') {
            return self::boolean($value, $strict);
        }
        if (is_int($value)) {
            return [null,$value];
        }
        if ($strict || !is_string($value)) {
            return ['type_mismatch',null];
        }
        if (!preg_match('/\\A[+-]?[0-9]+\\z/D',$value)) {
            return ['invalid_integer',null];
        }
        $negative = $value[0] === '-';
        $magnitude = ltrim(ltrim($value,'+-'),'0');
        $magnitude = $magnitude === '' ? '0' : $magnitude;
        $bound = $negative ? substr((string)PHP_INT_MIN,1) : (string)PHP_INT_MAX;
        if (strlen($magnitude)>strlen($bound) || (strlen($magnitude)===strlen($bound) && strcmp($magnitude,$bound)>0)) {
            return ['integer_out_of_range',null];
        }
        $canonical = ($negative && $magnitude !== '0' ? '-' : '') . $magnitude;
        return [null,(int)$canonical];

    }


    private static function floatingPoint(mixed $value, bool $strict): array
    {

        if (is_float($value)) {
            return is_finite($value) ? [null, $value] : ['float_out_of_range', null];
        }
        if ($strict || (!is_int($value) && !is_string($value))) {
            return ['type_mismatch', null];
        }
        if (is_string($value) && !preg_match('/\\A[+-]?(?:[0-9]+(?:\\.[0-9]*)?|\\.[0-9]+)(?:[eE][+-]?[0-9]+)?\\z/D', $value)) {
            return ['invalid_float', null];
        }
        $normalized = (float) $value;
        return is_finite($normalized) ? [null, $normalized] : ['float_out_of_range', null];

    }

    private static function boolean(mixed $value, bool $strict): array
    {

        if (is_bool($value)) {
            return [null, $value];
        }
        if ($strict || (!is_int($value) && !is_string($value))) {
            return ['type_mismatch', null];
        }
        if ($value === 0 || $value === '0') {
            return [null, false];
        }
        if ($value === 1 || $value === '1') {
            return [null, true];
        }
        if (is_string($value)) {

            $lower = strtr($value, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz');
            if ($lower === 'true' || $lower === 'false') {
                return [null, $lower === 'true'];
            }

        }
        return ['invalid_boolean', null];

    }

    private static function temporal(mixed $value, string $type, bool $strict, array $options): array
    {

        if (($type === 'datetime' && $value instanceof \DateTimeImmutable) || ($type === 'timestamp' && is_int($value))) {
            return [null, $value];
        }
        if ($strict || !is_string($value) || $options === []) {
            return ['type_mismatch', null];
        }
        $result = $type === 'datetime'
            ? \Galaxy\Date\GDate::toDateTime($value, $options)
            : \Galaxy\Date\GDate::toTimestamp($value, $options);
        return [$result->reason, $result->isValid() ? $result->value() : null];

    }
}
