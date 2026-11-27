<?php
declare(strict_types=1);

namespace Galaxy\Validation;

use InvalidArgumentException;

final class GCheck
{

    private function __construct()
    {
    }

    public static function integerString(mixed $value): bool
    {
        return self::matches($value, '/\A[+-]?[0-9]+\z/D');
    }

    public static function floatString(mixed $value): bool
    {
        return self::matches($value, '/\A[+-]?(?:[0-9]+(?:\.[0-9]*)?|\.[0-9]+)(?:[eE][+-]?[0-9]+)?\z/D');
    }

    public static function booleanString(mixed $value): bool
    {

        return is_string($value) && (
            $value === '0' || $value === '1'
            || strcasecmp($value, 'true') === 0 || strcasecmp($value, 'false') === 0
        );

    }

    public static function email(mixed $value): bool
    {
        return is_string($value) && filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
    }

    public static function url(mixed $value): bool
    {
        return is_string($value) && filter_var($value, FILTER_VALIDATE_URL) !== false;
    }

    public static function uuidV4(mixed $value): bool
    {
        return self::matches($value, '/\A[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\z/iD');
    }

    public static function matches(mixed $value, string $pattern): bool
    {

        if (!is_string($value)) {
            return false;
        }
        $matched = @preg_match($pattern, $value);
        if ($matched === false) {
            throw new InvalidArgumentException('The regular expression could not be evaluated.');
        }
        return $matched === 1;

    }

}