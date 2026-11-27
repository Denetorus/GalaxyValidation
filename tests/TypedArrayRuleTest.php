<?php
declare(strict_types=1);

namespace Galaxy\Validation\Tests;

use Galaxy\Validation\GValidator;
use Galaxy\Validation\Rule\ArrayRule;
use Galaxy\Validation\Rule\ArrayIntegerRule;
use Galaxy\Validation\Rule\ArrayStringRule;
use Galaxy\Validation\Rule\ArrayFloatRule;
use Galaxy\Validation\Rule\ArrayBooleanRule;
use Galaxy\Validation\Rule\IntegerRule;
use Galaxy\Validation\Rule\StringRule;
use Galaxy\Validation\Rule\FloatRule;
use Galaxy\Validation\Rule\BooleanRule;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\TestCase;
use stdClass;

final class TypedArrayRuleTest extends TestCase
{

    public function testTypedAndGenericRulesHaveIdenticalPairs(): void
    {

        $common = ['null' => null, 'empty' => '', 'nested' => [], 'object' => new stdClass()];
        $cases = [
            [IntegerRule::class, ArrayIntegerRule::class, $common + [
                'number' => 2, 'string' => '002', 'bad' => '2.5', 'space' => ' 1',
                'overflow' => (string) PHP_INT_MAX . '0', 'float' => 2.0, 'bool' => true,
            ]],
            [StringRule::class, ArrayStringRule::class, $common + [
                'text' => 'abc', 'zero' => '0', 'number' => 2, 'bool' => false,
            ]],
            [FloatRule::class, ArrayFloatRule::class, $common + [
                'integer' => 2, 'float' => 2.5, 'exponent' => '-1.25e3', 'bad' => 'wrong',
                'overflow' => '1e9999', 'infinite' => INF, 'nan' => NAN, 'bool' => false,
            ]],
            [BooleanRule::class, ArrayBooleanRule::class, $common + [
                'true' => true, 'false' => false, 'integer' => 1, 'float' => 1.0,
                'falseString' => 'FaLsE', 'zero' => '0', 'bad' => 'yes', 'space' => ' true',
            ]],
        ];
        foreach ($cases as [$scalarClass, $arrayClass, $input]) {

            foreach ([true, false] as $strict) {

                $generic = new ArrayRule(new $scalarClass($strict));
                $typed = new $arrayClass($strict);
                self::assertSame(serialize($generic->validate($input)), serialize($typed->validate($input)));
                self::assertSame([[], []], $typed->validate([]));
                foreach ([null, '1', 1, new stdClass()] as $nonArray) {
                    self::assertSame(serialize($generic->validate($nonArray)), serialize($typed->validate($nonArray)));
                }

            }

        }

    }

    public function testSuccessPreservesKeysOrderAndInput(): void
    {

        $cases = [
            [new ArrayIntegerRule(false), ['a' => '002', 9 => 3], ['a' => 2, 9 => 3]],
            [new ArrayFloatRule(false), ['a' => '1.5', 9 => 2], ['a' => 1.5, 9 => 2.0]],
            [new ArrayBooleanRule(false), ['a' => 'FALSE', 9 => 1], ['a' => false, 9 => true]],
            [new ArrayStringRule(), ['a' => 'x', 9 => ''], ['a' => 'x', 9 => '']],
        ];
        foreach ($cases as [$rule, $input, $expected]) {

            $before = $input;
            self::assertSame([[], $expected], $rule->validate($input));
            self::assertSame($before, $input);
            self::assertSame($expected, GValidator::validate($input, $rule)->value());

        }

    }

    public function testNumericOptionsAreSharedAndAliasesNormalize(): void
    {

        foreach ([[IntegerRule::class, ArrayIntegerRule::class], [FloatRule::class, ArrayFloatRule::class]]
            as [$scalarClass, $arrayClass]) {

            foreach ([['min' => 0, 'less' => 4], ['>=' => 0, '<' => 4], ['max' => 2], ['greater' => 1]]
                as $options) {

                $scalar = new $scalarClass(false, $options);
                $typed = new $arrayClass(false, $options);
                self::assertSame($scalar->options, $typed->options);
                $input = ['low' => '-1', 'one' => '1', 7 => '4', 'bad' => 'x'];
                self::assertSame(serialize((new ArrayRule($scalar))->validate($input)), serialize($typed->validate($input)));

            }

        }

    }

    public function testAllErrorsCollectedAndNoPartialPublicValue(): void
    {

        $rule = new ArrayIntegerRule(false, ['min' => 0]);
        [$errors, $value] = $rule->validate(['ok' => '1', 'bad' => 'x', 9 => -1, 'nested' => []]);
        self::assertSame(['bad', 9, 'nested'], array_column($errors, 'key'));
        self::assertSame(['invalid_integer', 'below_min', 'type_mismatch'], array_column($errors, 'reason'));
        self::assertNull($value);
        $result = GValidator::validate(['bad' => 'x'], $rule);
        $this->expectException(LogicException::class);
        $result->value();

    }

    public function testInvalidOptionsAreRejectedByExistingScalarLogic(): void
    {

        foreach ([ArrayIntegerRule::class, ArrayFloatRule::class, ArrayStringRule::class, ArrayBooleanRule::class]
            as $class) {

            try {
                new $class(options: ['unsupported' => 1]);
                self::fail('Invalid options accepted.');
            } catch (InvalidArgumentException) {
                self::assertTrue(true);
            }

        }

    }

    public function testTypedArrayCannotBeNestedInsideGenericArray(): void
    {

        foreach ([new ArrayIntegerRule(), new ArrayFloatRule(), new ArrayStringRule(), new ArrayBooleanRule()] as $rule) {
            try {
                new ArrayRule($rule);
                self::fail('Nested typed array accepted.');
            } catch (InvalidArgumentException) {
                self::assertTrue(true);
            }
        }

    }

}