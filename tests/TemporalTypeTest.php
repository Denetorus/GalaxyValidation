<?php
declare(strict_types=1);

namespace Galaxy\Validation\Tests;

use Galaxy\Validation\{GValidator, ValidationRule};
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TemporalTypeTest extends TestCase
{

    public function testStrictTypesPreserveReadyValues(): void
    {

        $object = new \DateTimeImmutable('2026-11-22', new \DateTimeZone('UTC'));
        foreach ([true, false] as $strict) {

            self::assertSame($object, GValidator::validate($object, new ValidationRule('datetime', $strict))->value());
            foreach ([PHP_INT_MIN, -1, 0, PHP_INT_MAX] as $value) {
                self::assertSame($value, GValidator::validate($value, new ValidationRule('timestamp', $strict))->value());
            }

        }

    }

    public static function mismatches(): iterable
    {

        foreach ([true, false] as $strict) {

            foreach (['2026-11-22', 0, null, false, [], new \DateTime('2026-11-22')] as $value) {
                yield ['datetime', $strict, $value];
            }
            foreach (['0', '2026-11-22', 0.0, null, false, [], new \DateTimeImmutable('2026-11-22')] as $value) {
                yield ['timestamp', $strict, $value];
            }

        }

    }

    #[DataProvider('mismatches')]
    public function testMismatchesWithoutConversionOptions(string $type, bool $strict, mixed $value): void
    {

        $result = GValidator::validate($value, new ValidationRule($type, $strict));
        self::assertFalse($result->isValid());
        self::assertSame('type_mismatch', $result->violations[0]->reason);
        self::assertSame($type, $result->violations[0]->expectedType);
        self::assertNull($result->violations[0]->key);

    }

    public function testStrictDoesNotConvertEvenWhenOptionsAreConfigured(): void
    {

        foreach (['datetime', 'timestamp'] as $type) {

            $rule = new ValidationRule($type, options: ['format' => 'Y-m-d', 'timezone' => 'UTC']);
            self::assertSame('type_mismatch', GValidator::validate('1970-01-01', $rule)->violations[0]->reason);

        }

    }

    public function testDateToMidnightObjectAndTimestamp(): void
    {

        $options = ['format' => 'Y-m-d', 'timezone' => 'Europe/Berlin'];
        $input = '2026-11-22';
        $object = GValidator::validate($input, new ValidationRule('datetime', false, $options))->value();
        self::assertInstanceOf(\DateTimeImmutable::class, $object);
        self::assertSame('2026-11-22 00:00:00+01:00', $object->format('Y-m-d H:i:sP'));
        self::assertSame(
            $object->getTimestamp(),
            GValidator::validate($input, new ValidationRule('timestamp', false, $options))->value(),
        );
        self::assertSame('2026-11-22', $input);

    }

    public function testEpochAndPrecisionRules(): void
    {

        $options = ['format' => 'Y-m-d H:i:s.uP'];
        $rule = new ValidationRule('timestamp', false, $options);
        self::assertSame(0, GValidator::validate('1970-01-01 00:00:00.000000+00:00', $rule)->value());
        self::assertSame(-1, GValidator::validate('1969-12-31 23:59:59.000000+00:00', $rule)->value());
        self::assertSame(
            'precision_loss',
            GValidator::validate('1970-01-01 00:00:00.123456+00:00', $rule)->violations[0]->reason,
        );
        $object = GValidator::validate(
            '1970-01-01 00:00:00.123456+00:00',
            new ValidationRule('datetime', false, $options),
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

            $result = GValidator::validate($value, new ValidationRule($type, false, $options));
            self::assertFalse($result->isValid());
            self::assertSame($reason, $result->violations[0]->reason);
            self::assertSame($type, $result->violations[0]->expectedType);

        }

    }

    public function testTypedArraysConvertEveryElementAndKeepKeys(): void
    {

        $options = ['format' => 'Y-m-d H:i:sP'];
        $ready = new \DateTimeImmutable('2024-01-01', new \DateTimeZone('UTC'));
        $input = ['ready' => $ready, 9 => '1970-01-01 00:00:00+00:00'];
        $objects = GValidator::validate($input, new ValidationRule('array[datetime]', false, $options))->value();
        self::assertSame(['ready', 9], array_keys($objects));
        self::assertSame($ready, $objects['ready']);
        self::assertInstanceOf(\DateTimeImmutable::class, $objects[9]);
        self::assertSame('1970-01-01 00:00:00+00:00', $input[9]);
        self::assertSame(
            ['ready' => -1, 9 => 0],
            GValidator::validate(['ready' => -1, 9 => $input[9]], new ValidationRule('array[timestamp]', false, $options))
                ->value(),
        );

    }

    public function testTypedArraysCollectAllFailuresWithoutPartialValue(): void
    {

        $rule = new ValidationRule('array[timestamp]', false, ['format' => 'Y-m-d H:i:s.uP']);
        $input = ['good' => 0, 'bad' => null, 8 => '1970-01-01 00:00:00.123456+00:00'];
        $result = GValidator::validate($input, $rule);
        self::assertSame(['bad', 8], array_column($result->violations, 'key'));
        self::assertSame(['type_mismatch', 'precision_loss'], array_column($result->violations, 'reason'));
        self::assertSame(['timestamp', 'timestamp'], array_column($result->violations, 'expectedType'));
        $this->expectException(\LogicException::class);
        $result->value();

    }

    public function testEmptyTypedArraysAreValidAndScalarArraysAreNotWrapped(): void
    {

        foreach (['datetime', 'timestamp'] as $type) {

            foreach ([true, false] as $strict) {

                $rule = new ValidationRule('array[' . $type . ']', $strict);
                self::assertSame([], GValidator::validate([], $rule)->value());
                $result = GValidator::validate('2026-11-22', $rule);
                self::assertSame('array[' . $type . ']', $result->violations[0]->expectedType);

            }

        }

    }

    public function testInvalidConverterOptionsFailWhenRuleIsConstructed(): void
    {

        foreach (['datetime', 'timestamp', 'array[datetime]', 'array[timestamp]'] as $type) {

            try {
                new ValidationRule($type, false, ['format' => 'Y-m-d', 'timezone' => 'Unknown/Zone']);
                self::fail('Invalid configuration accepted.');
            }
            catch (\InvalidArgumentException) {
                self::assertTrue(true);
            }

        }

    }

    public function testOldDateTypeIsRejected(): void
    {

        foreach (['date', 'array[date]'] as $type) {

            try {
                new ValidationRule($type, options: ['format' => 'Y-m-d']);
                self::fail('Old type must not remain an alias.');
            }
            catch (\InvalidArgumentException) {
                self::assertTrue(true);
            }

        }

    }


    public function testStrictArraysRejectStringsWithoutConvertingThem(): void
    {

        $object = new \DateTimeImmutable('2026-11-22', new \DateTimeZone('UTC'));
        $input = ['ready' => $object, 'string' => '2026-11-22'];
        $rule = new ValidationRule('array[datetime]', options: ['format' => 'Y-m-d', 'timezone' => 'UTC']);
        $result = GValidator::validate($input, $rule);
        self::assertSame(['string'], array_column($result->violations, 'key'));
        self::assertSame('type_mismatch', $result->violations[0]->reason);
        self::assertSame($object, $input['ready']);
        self::assertSame('2026-11-22', $input['string']);

    }

    public function testReadyValuesAreNotConstrainedByConversionOptions(): void
    {

        $object = new \DateTimeImmutable('2026-11-22', new \DateTimeZone('Europe/Berlin'));
        foreach ([true, false] as $strict) {

            $rule = new ValidationRule('datetime', $strict, ['format' => 'Y-m-d', 'timezone' => 'UTC']);
            self::assertSame($object, GValidator::validate($object, $rule)->value());
            $rule = new ValidationRule('timestamp', $strict, ['format' => 'Y-m-d', 'timezone' => 'UTC']);
            self::assertSame(-1, GValidator::validate(-1, $rule)->value());

        }

    }

    public function testExplicitOffsetResolvesTypedFold(): void
    {

        $options = ['format' => 'Y-m-d H:i:sP', 'timezone' => 'Europe/Berlin'];
        $input = '2024-10-27 02:30:00+01:00';
        $date = GValidator::validate($input, new ValidationRule('datetime', false, $options))->value();
        self::assertSame($input, $date->format($options['format']));
        self::assertSame(
            $date->getTimestamp(),
            GValidator::validate($input, new ValidationRule('timestamp', false, $options))->value(),
        );

    }
}