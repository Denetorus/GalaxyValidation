<?php
declare(strict_types=1);

namespace Galaxy\Validation\Tests;

require_once __DIR__ . '/Support/TestRules.php';

use Galaxy\Validation\Rule\DateTimeRule;
use Galaxy\Validation\Rule\TimestampRule;
use Galaxy\Validation\Tests\Support\TestRules;
use DateTime;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use LogicException;

use Galaxy\Validation\{GValidator};
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TemporalTypeTest extends TestCase
{

    public function testStrictTypesPreserveReadyValues(): void
    {

        $object = new DateTimeImmutable('2026-11-22', new DateTimeZone('UTC'));
        foreach ([true, false] as $strict) {

            self::assertSame($object, GValidator::validate($object, new DateTimeRule($strict))->value());
            foreach ([PHP_INT_MIN, -1, 0, PHP_INT_MAX] as $value) {
                self::assertSame($value, GValidator::validate($value, new TimestampRule($strict))->value());
            }

        }

    }

    public static function mismatches(): iterable
    {

        foreach ([true, false] as $strict) {

            foreach (['2026-11-22', 0, null, false, [], new DateTime('2026-11-22')] as $value) {
                yield ['datetime', $strict, $value];
            }
            foreach (['0', '2026-11-22', 0.0, null, false, [], new DateTimeImmutable('2026-11-22')] as $value) {
                yield ['timestamp', $strict, $value];
            }

        }

    }

    #[DataProvider('mismatches')]
    public function testMismatchesWithoutConversionOptions(string $type, bool $strict, mixed $value): void
    {

        $result = GValidator::validate($value, TestRules::create($type, $strict));
        self::assertFalse($result->isValid());
        self::assertSame('type_mismatch', $result->errors[0]->reason);
        self::assertSame($type, $result->errors[0]->expectedType);
        self::assertNull($result->errors[0]->key);

    }

    public function testStrictDoesNotConvertEvenWhenOptionsAreConfigured(): void
    {

        foreach (['datetime', 'timestamp'] as $type) {

            $rule = TestRules::create($type, options: ['format' => 'Y-m-d', 'timezone' => 'UTC']);
            self::assertSame('type_mismatch', GValidator::validate('1970-01-01', $rule)->errors[0]->reason);

        }

    }

    public function testDateToMidnightObjectAndTimestamp(): void
    {

        $options = ['format' => 'Y-m-d', 'timezone' => 'Europe/Berlin'];
        $input = '2026-11-22';
        $object = GValidator::validate($input, new DateTimeRule(false, $options))->value();
        self::assertInstanceOf(DateTimeImmutable::class, $object);
        self::assertSame('2026-11-22 00:00:00+01:00', $object->format('Y-m-d H:i:sP'));
        self::assertSame(
            $object->getTimestamp(),
            GValidator::validate($input, new TimestampRule(false, $options))->value(),
        );
        self::assertSame('2026-11-22', $input);

    }

    public function testEpochAndPrecisionRules(): void
    {

        $options = ['format' => 'Y-m-d H:i:s.uP'];
        $rule = new TimestampRule(false, $options);
        self::assertSame(0, GValidator::validate('1970-01-01 00:00:00.000000+00:00', $rule)->value());
        self::assertSame(-1, GValidator::validate('1969-12-31 23:59:59.000000+00:00', $rule)->value());
        self::assertSame(
            'precision_loss',
            GValidator::validate('1970-01-01 00:00:00.123456+00:00', $rule)->errors[0]->reason,
        );
        $object = GValidator::validate(
            '1970-01-01 00:00:00.123456+00:00',
            new DateTimeRule(false, $options),
        )->value();
        self::assertSame('123456', $object->format('u'));

    }

    public static function failures(): iterable
    {

        yield [['format' => 'Y-m-d'], '2026-11-22', 'timezone_required'];
        yield [['format' => 'Y-m-d', 'timezone' => 'UTC'], '2026-02-30', 'invalid_date'];
        yield [['format' => 'Y-m-d', 'timezone' => 'Pacific/Apia'], '2011-12-30', 'nonexistent_local_time'];
        yield [['format' => 'Y-m-d H:i:s', 'timezone' => 'Europe/Berlin'],
            '2024-10-27 02:30:00', 'ambiguous_local_time'];
        yield [['format' => 'Y-m-d H:i:s', 'timezone' => 'Europe/Berlin'],
            '2024-03-31 02:30:00', 'nonexistent_local_time'];
        yield [['format' => 'Y-m-d H:i:sP', 'timezone' => 'Europe/Berlin'],
            '2024-07-01 12:00:00+01:00', 'timezone_offset_mismatch'];

    }

    #[DataProvider('failures')]
    public function testGDateReasonsArePreserved(array $options, string $value, string $reason): void
    {

        foreach (['datetime', 'timestamp'] as $type) {

            $result = GValidator::validate($value, TestRules::create($type, false, $options));
            self::assertFalse($result->isValid());
            self::assertSame($reason, $result->errors[0]->reason);
            self::assertSame($type, $result->errors[0]->expectedType);

        }

    }







    public function testInvalidConverterOptionsFailWhenRuleIsConstructed(): void
    {

        foreach (['datetime', 'timestamp'] as $type) {

            try {
                TestRules::create($type, false, ['format' => 'Y-m-d', 'timezone' => 'Unknown/Zone']);
                self::fail('Invalid configuration accepted.');
            }
            catch (InvalidArgumentException) {
                self::assertTrue(true);
            }

        }

    }

    public function testOldDateTypeIsRejected(): void
    {

        foreach (['date'] as $type) {

            try {
                TestRules::create($type, options: ['format' => 'Y-m-d']);
                self::fail('Old type must not remain an alias.');
            }
            catch (InvalidArgumentException) {
                self::assertTrue(true);
            }

        }

    }




    public function testReadyValuesAreNotConstrainedByConversionOptions(): void
    {

        $object = new DateTimeImmutable('2026-11-22', new DateTimeZone('Europe/Berlin'));
        foreach ([true, false] as $strict) {

            $rule = new DateTimeRule($strict, ['format' => 'Y-m-d', 'timezone' => 'UTC']);
            self::assertSame($object, GValidator::validate($object, $rule)->value());
            $rule = new TimestampRule($strict, ['format' => 'Y-m-d', 'timezone' => 'UTC']);
            self::assertSame(-1, GValidator::validate(-1, $rule)->value());

        }

    }

    public function testExplicitOffsetResolvesTypedFold(): void
    {

        $options = ['format' => 'Y-m-d H:i:sP', 'timezone' => 'Europe/Berlin'];
        $input = '2024-10-27 02:30:00+01:00';
        $date = GValidator::validate($input, new DateTimeRule(false, $options))->value();
        self::assertSame($input, $date->format($options['format']));
        self::assertSame(
            $date->getTimestamp(),
            GValidator::validate($input, new TimestampRule(false, $options))->value(),
        );

    }
}