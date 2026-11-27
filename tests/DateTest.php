<?php
declare(strict_types=1);

namespace Galaxy\Validation\Tests;

require_once __DIR__ . '/Support/TestRules.php';

use Galaxy\Validation\Rule\DateStringRule;
use Galaxy\Validation\Tests\Support\TestRules;
use DateTimeImmutable;
use InvalidArgumentException;
use LogicException;

use Galaxy\Validation\{GValidator};
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DateTest extends TestCase
{

    public static function dates(): iterable
    {

        foreach ([true, false] as $strict) {

            foreach ([
                ['Y-m-d', '2026-11-22'], ['d.m.Y', '22.11.2026'], ['Y/m/d', '2026/11/22'],
                ['Y-n-j', '2026-2-3'], ['Ymd', '20261122'], ['d M Y', '22 Nov 2026'],
                ['j F Y', '3 February 2026'], ['d-m-y', '22-11-26'],
                ['Y-m-d', '2024-02-29'], ['Y-m-d', '2000-02-29'], ['Y-m-d', '0001-01-01'],
                ['Y-m-d', '9999-12-31'], ['Y\\Y-m-d', '2026Y-11-22'],
            ] as [$format, $value]) {
                yield [$strict, $format, $value, null];
            }
            foreach ([
                '', '2026-02-30', '2026-02-29', '1900-02-29', '2026-00-01',
                '2026-13-01', '2026-01-00', '2026-01-32', '0000-01-01',
                '2026-2-03', '26-11-22', '2026/11/22', ' 2026-11-22', '2026-11-22 ',
                "2026-11-22\n", "2026-11-22\0", '2026-11-22T12:00:00',
            ] as $value) {
                yield [$strict, 'Y-m-d', $value, 'invalid_date'];
            }
            foreach ([null, false, 20261122, 1.5, [], new DateTimeImmutable('2026-11-22')] as $value) {
                yield [$strict, 'Y-m-d', $value, 'type_mismatch'];
            }

        }

    }

    #[DataProvider('dates')]
    public function testDate(bool $strict, string $format, mixed $value, ?string $reason): void
    {

        $result = GValidator::validate($value, new DateStringRule($strict, ['format' => $format]));
        self::assertSame($reason === null, $result->isValid());
        if ($reason === null) {
            self::assertSame($value, $result->value());
        }
        else {
            self::assertCount(1, $result->errors);
            self::assertSame($reason, $result->errors[0]->reason);
            self::assertSame('date-string', $result->errors[0]->expectedType);
            self::assertNull($result->errors[0]->key);
        }

    }

    public static function invalidOptions(): iterable
    {

        foreach (['date-string'] as $type) {

            foreach ([
                [], ['format' => ''], ['format' => null], ['format' => 12],
                ['format' => 'Y-m-d', 'timezone' => 'UTC'], ['format' => 'Y-m'],
                ['format' => 'Y-m-d H:i:s'], ['format' => '!Y-m-d'], ['format' => 'Y-m-d+'],
                ['format' => 'Y-m-d Y'], ['format' => "Y-m-d\0"], ['format' => 'Y-m-d\\'],
                ['format' => 'c'], ['format' => 'U'], ['format' => 'Y-m-d P'],
            ] as $options) {
                yield [$type, $options];
            }

        }

    }

    #[DataProvider('invalidOptions')]
    public function testInvalidConfiguration(string $type, array $options): void
    {
        $this->expectException(InvalidArgumentException::class);
        TestRules::create($type, options: $options);
    }





    public function testIndependentOfDefaultTimezoneAndEarlierParserErrors(): void
    {

        $timezone = date_default_timezone_get();
        try {

            date_default_timezone_set('Pacific/Apia');
            $rule = new DateStringRule(options: ['format' => 'Y-m-d']);
            self::assertTrue(GValidator::validate('2011-12-30', $rule)->isValid());
            self::assertFalse(GValidator::validate('2026-02-30', $rule)->isValid());
            self::assertSame('2026-11-22', GValidator::validate('2026-11-22', $rule)->value());

        }
        finally {
            date_default_timezone_set($timezone);
        }

    }

}