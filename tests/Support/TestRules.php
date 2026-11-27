<?php
declare(strict_types=1);

namespace Galaxy\Validation\Tests\Support;

use Galaxy\Validation\ValidationRuleInterface;
use Galaxy\Validation\Rule\IntegerRule;
use Galaxy\Validation\Rule\FloatRule;
use Galaxy\Validation\Rule\BooleanRule;
use Galaxy\Validation\Rule\StringRule;
use Galaxy\Validation\Rule\MixedRule;
use Galaxy\Validation\Rule\DateStringRule;
use Galaxy\Validation\Rule\DateTimeStringRule;
use Galaxy\Validation\Rule\DateTimeRule;
use Galaxy\Validation\Rule\TimestampRule;
use InvalidArgumentException;

// Data-provider convenience, not a production string-rule API.
final class TestRules
{
    public static function create(string $type, bool $strict = true, array $options = []): ValidationRuleInterface
    {
        return match ($type) {
            'integer' => new IntegerRule($strict, $options),
            'float' => new FloatRule($strict, $options),
            'boolean' => new BooleanRule($strict, $options),
            'string' => new StringRule($strict, $options),
            'mixed' => new MixedRule($strict, $options),
            'date-string' => new DateStringRule($strict, $options),
            'datetime-string' => new DateTimeStringRule($strict, $options),
            'datetime' => new DateTimeRule($strict, $options),
            'timestamp' => new TimestampRule($strict, $options),
            default => throw new InvalidArgumentException('Unsupported test type.'),
        };
    }
}