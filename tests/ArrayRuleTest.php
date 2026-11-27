<?php
declare(strict_types=1);

namespace Galaxy\Validation\Tests;

use Galaxy\Validation\GValidator;
use Galaxy\Validation\ValidationError;
use Galaxy\Validation\ValidationResult;
use Galaxy\Validation\ValidationRuleInterface;
use Galaxy\Validation\Rule\ArrayRule;
use Galaxy\Validation\Rule\IntegerRule;
use Galaxy\Validation\Rule\MixedRule;
use Galaxy\Validation\Rule\StringRule;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\TestCase;
use stdClass;

readonly class RejectingMixedRule extends MixedRule
{

    protected function check(mixed $value): array
    {
        return ['custom_failure', null];
    }

}

final class ArrayRuleTest extends TestCase
{

    public function testDefaultRulePreservesOpaqueValuesAndReferences(): void
    {

        $object = new stdClass();
        $text = 'before';
        $input = ['object' => $object, 'nested' => [null, '01'], 'reference' => &$text];
        $rule = new ArrayRule();
        self::assertInstanceOf(MixedRule::class, $rule->elementRule);
        $result = GValidator::validate($input, $rule);
        self::assertTrue($result->isValid());
        self::assertSame($input, $result->value());
        $text = 'after';
        self::assertSame('after', $result->value()['reference']);
        self::assertSame($object, $result->value()['object']);

    }

    public function testNonArraysAreNotWrapped(): void
    {

        foreach ([null, false, 1, 1.5, 'value', new stdClass()] as $value) {
            $result = GValidator::validate($value, (new ArrayRule()));
            self::assertFalse($result->isValid());
            self::assertSame('type_mismatch', $result->errors[0]->reason);
            self::assertSame('array', $result->errors[0]->expectedType);
            self::assertNull($result->errors[0]->key);
        }

    }

    public function testTypedArrayConvertsValuesAndPreservesKeysAndInput(): void
    {

        $input = ['first' => '001', 9 => '2'];
        $result = GValidator::validate($input, (new ArrayRule(new IntegerRule(false))));
        self::assertSame(['first' => 1, 9 => 2], $result->value());
        self::assertSame(['first' => '001', 9 => '2'], $input);
        $strict = GValidator::validate($input, (new ArrayRule(new IntegerRule())));
        self::assertSame(['first', 9], array_column($strict->errors, 'key'));
        self::assertSame(['type_mismatch', 'type_mismatch'], array_column($strict->errors, 'reason'));

    }

    public function testAllFailuresHaveKeysAndNoPartialValue(): void
    {

        $rule = new ArrayRule(new IntegerRule(false, ['min' => 0]));
        $result = GValidator::validate(['ok' => '2', 'bad' => 'x', 7 => -1, 'nested' => []], $rule);
        self::assertSame(['bad', 7, 'nested'], array_column($result->errors, 'key'));
        self::assertSame(['invalid_integer', 'below_min', 'type_mismatch'], array_column($result->errors, 'reason'));
        self::assertSame(['integer', 'integer', 'integer'], array_column($result->errors, 'expectedType'));
        $this->expectException(LogicException::class);
        $result->value();

    }

    public function testEmptyArraysAreValid(): void
    {
        self::assertSame([], GValidator::validate([], (new ArrayRule()))->value());
        self::assertSame([], GValidator::validate([], (new ArrayRule(new IntegerRule())))->value());
        self::assertSame([], GValidator::validate([], (new ArrayRule(new StringRule())))->value());
    }

    public function testCustomRuleErrorsAreCollectedInElementOrder(): void
    {

        $rule = new class implements ValidationRuleInterface {

            public function validate(mixed $value): array
            {
                return [[
                    new ValidationError('first', 'custom'),
                    new ValidationError('second', 'custom'),
                ], null];
            }

        };
        $result = GValidator::validate(['a' => 'x', 9 => 'y'], (new ArrayRule($rule)));
        self::assertSame(['a', 'a', 9, 9], array_column($result->errors, 'key'));
        self::assertSame(['first', 'second', 'first', 'second'], array_column($result->errors, 'reason'));
        self::assertSame(['custom', 'custom', 'custom', 'custom'], array_column($result->errors, 'expectedType'));

    }

    public function testMixedSubclassIsNotBypassed(): void
    {
        $result = GValidator::validate(['key' => 1], (new ArrayRule(new RejectingMixedRule())));
        self::assertSame('custom_failure', $result->errors[0]->reason);
        self::assertSame('key', $result->errors[0]->key);
    }

    public function testNestedRulesAreConfigurationErrors(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new ArrayRule(new ArrayRule());
    }

    public function testCustomExceptionsPropagate(): void
    {

        $rule = new class implements ValidationRuleInterface {

            public function validate(mixed $value): array
            {
                throw new LogicException('Custom rule failure.');
            }

        };
        $this->expectException(LogicException::class);
        GValidator::validate([1], (new ArrayRule($rule)));

    }

}