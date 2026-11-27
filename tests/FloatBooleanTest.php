<?php
declare(strict_types=1);

namespace Galaxy\Validation\Tests;

require_once __DIR__ . '/Support/TestRules.php';

use Galaxy\Validation\Tests\Support\TestRules;
use LogicException;
use stdClass;

use Galaxy\Validation\{GValidator};
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class FloatBooleanTest extends TestCase
{

    public static function scalarCases(): iterable
    {

        foreach ([true, false] as $strict) {

            yield ['float', $strict, 1.5, 1.5, null];
            yield ['float', $strict, -0.0, -0.0, null];
            yield ['float', $strict, PHP_FLOAT_MAX, PHP_FLOAT_MAX, null];
            yield ['float', $strict, NAN, null, 'float_out_of_range'];
            yield ['float', $strict, INF, null, 'float_out_of_range'];
            yield ['float', $strict, -INF, null, 'float_out_of_range'];
            yield ['boolean', $strict, true, true, null];
            yield ['boolean', $strict, false, false, null];
            foreach ([null, [], new stdClass()] as $value) {
                yield ['float', $strict, $value, null, 'type_mismatch'];
                yield ['boolean', $strict, $value, null, 'type_mismatch'];
            }

        }
        foreach ([12, '12', '12.5', '-0.5', '1e3'] as $value) {
            yield ['float', true, $value, null, 'type_mismatch'];
        }
        foreach ([0, 1, '0', '1', 'true', 'false'] as $value) {
            yield ['boolean', true, $value, null, 'type_mismatch'];
        }
        foreach ([0, 12, -12, PHP_INT_MAX, PHP_INT_MIN] as $value) {
            yield ['float', false, $value, (float) $value, null];
        }
        foreach (['12' => 12.0, '12.5' => 12.5, '-0.5' => -0.5, '1e3' => 1000.0] as $value => $expected) {
            yield ['float', false, (string) $value, $expected, null];
        }
        foreach (['+01.50' => 1.5, '.5' => 0.5, '1.' => 1.0, '-2.5E-2' => -0.025] as $value => $expected) {
            yield ['float', false, $value, $expected, null];
        }
        foreach (['', ' 12', '12 ', "12\n", "\t12", '1,2', '0x10', 'NAN', 'INF', '+', '1e', '١'] as $value) {
            yield ['float', false, $value, null, 'invalid_float'];
        }
        foreach (['1e9999', '-1e9999'] as $value) {
            yield ['float', false, $value, null, 'float_out_of_range'];
        }
        foreach ([true, false] as $value) {
            yield ['float', false, $value, null, 'type_mismatch'];
        }
        foreach ([1.0, 0.0, NAN, INF] as $value) {
            yield ['boolean', false, $value, null, 'type_mismatch'];
        }
        foreach ([0, '0', 'false', 'FALSE', 'FaLsE'] as $value) {
            yield ['boolean', false, $value, false, null];
        }
        foreach ([1, '1', 'true', 'TRUE', 'TrUe'] as $value) {
            yield ['boolean', false, $value, true, null];
        }
        foreach ([2, -1, '', 'yes', 'no', ' true', 'false ', '01', '1.0', 'null', '2'] as $value) {
            yield ['boolean', false, $value, null, 'invalid_boolean'];
        }

    }

    #[DataProvider('scalarCases')]
    public function testScalars(string $type, bool $strict, mixed $input, mixed $expected, ?string $reason): void
    {

        $result = GValidator::validate($input, TestRules::create($type, $strict));
        self::assertSame($reason === null, $result->isValid());
        if ($reason === null) {
            self::assertSame($expected, $result->value());
        }
        else {
            self::assertCount(1, $result->errors);
            self::assertSame($reason, $result->errors[0]->reason);
            self::assertSame($type, $result->errors[0]->expectedType);
            self::assertNull($result->errors[0]->key);
        }

    }







}