<?php
declare(strict_types=1);

namespace Galaxy\Validation\Tests;

require_once __DIR__ . '/Support/TestRules.php';

use Galaxy\Validation\Rule\IntegerRule;
use Galaxy\Validation\Rule\FloatRule;
use Galaxy\Validation\Rule\MixedRule;
use Galaxy\Validation\Tests\Support\TestRules;
use InvalidArgumentException;

use Galaxy\Validation\{GValidator};
use PHPUnit\Framework\TestCase;

final class NumericBoundsTest extends TestCase
{

    public function testBoundariesAndConversion(): void
    {

        foreach (['min' => 'below_min', 'max' => 'above_max',
            'greater' => 'not_greater_than', 'less' => 'not_less_than'] as $key => $reason) {

            $rule = new IntegerRule(strict: false, options: [$key => 10]);
            $valid = in_array($key, ['min', 'max'], true);
            self::assertSame($valid, GValidator::validate('10', $rule)->isValid());
            $bad = in_array($key, ['min', 'greater'], true) ? 9 : 11;
            self::assertSame($reason, GValidator::validate($bad, $rule)->errors[0]->reason);

        }
        $result = GValidator::validate('12', new IntegerRule(false, ['min' => 10, 'max' => 12]));
        self::assertSame(12, $result->value());
        self::assertSame('type_mismatch', GValidator::validate('12',
            new IntegerRule(true, ['min' => 0]))->errors[0]->reason);
        self::assertSame(1.5, GValidator::validate('1.5',
            new FloatRule(false, ['greater' => 1, 'less' => 2]))->value());
        self::assertFalse(GValidator::validate(5, new IntegerRule(options: ['min' => 10, 'max' => 0]))->isValid());

    }



    public function testInvalidOptionsAreRejected(): void
    {

        foreach ([['min' => '1'], ['max' => INF], ['greater' => NAN], ['less' => true], ['unknown' => 1]] as $options) {
            try {
                new IntegerRule(options: $options);
                self::fail('Invalid numeric options accepted.');
            } catch (InvalidArgumentException) {
                self::assertTrue(true);
            }
        }
        $this->expectException(InvalidArgumentException::class);
        new MixedRule(options: ['min' => 0]);

    }

    public function testSymbolicAliases(): void
    {

        foreach (['>=' => 'min', '<=' => 'max', '>' => 'greater', '<' => 'less'] as $symbol => $name) {

            foreach (['integer', 'float'] as $type) {
                $rule = TestRules::create($type, false, [$symbol => 10]);
                self::assertSame([$name => 10], $rule->options);
                foreach ([9, 10, 11] as $number) {
                    $input = (string) $number;
                    self::assertEquals(
                        GValidator::validate($input, TestRules::create($type, false, [$name => 10])),
                        GValidator::validate($input, $rule),
                    );
                }
            }
            foreach ([[$symbol => 10, $name => 10], [$name => 10, $symbol => 11]] as $options) {
                try {
                    new IntegerRule(options: $options);
                    self::fail('Duplicate alias accepted.');
                } catch (InvalidArgumentException) {
                    self::assertTrue(true);
                }
            }

        }
        self::assertSame(5, GValidator::validate('5',
            new IntegerRule(false, ['>=' => 0, '<' => 10]))->value());

    }
}