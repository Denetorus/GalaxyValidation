<?php
declare(strict_types=1);

namespace Galaxy\Validation\Tests;

use Galaxy\Validation\{GValidator, ValidationRule};
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DateTimeTest extends TestCase
{

    public static function values(): iterable
    {

        foreach ([true, false] as $strict) {

            $plain = ['format' => 'Y-m-d H:i:s'];
            yield [$strict, $plain, '2024-02-29 23:59:59', null];
            yield [$strict, ['format' => 'd.m.Y G:i'], '22.11.2026 7:05', null];
            yield [$strict, ['format' => 'Y-m-d H:i:s.u'], '2024-01-01 00:00:00.123456', null];
            yield [$strict, ['format' => 'Y-m-d H:i:s.v'], '2024-01-01 00:00:00.123', null];
            foreach (['2024-02-30 00:00:00', '2024-01-01 24:00:00', '2024-01-01 12:60:00',
                '2024-01-01 12:00:60', '2024-01-01 1:00:00', '2024-01-01 12:00:00 ',
                "2024-01-01 12:00:00\0", '0000-01-01 00:00:00'] as $value) {
                yield [$strict, $plain, $value, 'invalid_datetime'];
            }
            foreach ([null, false, 123, [], new \DateTimeImmutable()] as $value) {
                yield [$strict, $plain, $value, 'type_mismatch'];
            }
            yield [$strict, $plain + ['offset' => '+03:00'], '2024-01-01 12:00:00', null];
            $berlin = $plain + ['timezone' => 'Europe/Berlin'];
            yield [$strict, $berlin, '2024-01-01 12:00:00', null];
            yield [$strict, $berlin, '2024-07-01 12:00:00', null];
            yield [$strict, $berlin, '2024-03-31 02:30:00', 'nonexistent_local_time'];
            yield [$strict, $berlin, '2024-10-27 02:30:00', 'ambiguous_local_time'];
            yield [$strict, $berlin + ['offset' => '+02:00'], '2024-10-27 02:30:00', null];
            yield [$strict, $berlin + ['offset' => '+01:00'], '2024-10-27 02:30:00', null];
            yield [$strict, $berlin + ['offset' => '+03:00'], '2024-10-27 02:30:00', 'timezone_offset_mismatch'];
            yield [$strict, $berlin + ['offset' => '+01:00'], '2024-07-01 12:00:00', 'timezone_offset_mismatch'];
            yield [$strict, $berlin + ['offset' => '+01:00'], '2024-03-31 02:30:00', 'nonexistent_local_time'];
            yield [$strict, $berlin, '2024-03-31 01:59:59', null];
            yield [$strict, $berlin, '2024-03-31 03:00:00', null];
            $inline = ['format' => 'Y-m-d\\TH:i:sP'];
            yield [$strict, $inline, '2024-01-01T12:00:00+05:45', null];
            yield [$strict, $inline, '2024-01-01T12:00:00-03:30', null];
            yield [$strict, $inline, '2024-01-01T12:00:00+14:00', null];
            yield [$strict, $inline, '2024-01-01T12:00:00+14:01', 'invalid_offset'];
            yield [$strict, $inline, '2024-01-01T12:00:00+03:60', 'invalid_offset'];
            yield [$strict, $inline, '2024-01-01T12:00:00Z', 'invalid_datetime'];
            $zonedInline = $inline + ['timezone' => 'Europe/Berlin'];
            yield [$strict, $zonedInline, '2024-10-27T02:30:00+02:00', null];
            yield [$strict, $zonedInline, '2024-10-27T02:30:00+01:00', null];
            yield [$strict, $zonedInline, '2024-10-27T02:30:00+03:00', 'timezone_offset_mismatch'];
            yield [$strict, $plain + ['timezone' => 'America/New_York'], '2024-03-10 02:30:00',
                'nonexistent_local_time'];
            yield [$strict, $plain + ['timezone' => 'America/New_York'], '2024-11-03 01:30:00',
                'ambiguous_local_time'];
            yield [$strict, $plain + ['timezone' => 'Australia/Lord_Howe'], '2024-04-07 01:45:00',
                'ambiguous_local_time'];
            yield [$strict, $plain + ['timezone' => 'Australia/Lord_Howe'], '2024-10-06 02:15:00',
                'nonexistent_local_time'];
            yield [$strict, $plain + ['timezone' => 'Pacific/Apia'], '2011-12-30 12:00:00',
                'nonexistent_local_time'];
            yield [$strict, $plain + ['timezone' => 'UTC'], '2024-10-27 02:30:00', null];
            yield [$strict, $plain + ['timezone' => 'Asia/Kathmandu', 'offset' => '+05:45'],
                '2024-01-01 12:00:00', null];
            yield [$strict, $plain + ['timezone' => 'UTC', 'offset' => '-00:00'], '2024-01-01 12:00:00', null];

        }

    }

    #[DataProvider('values')]
    public function testValue(bool $strict, array $options, mixed $value, ?string $reason): void
    {

        $result = GValidator::validate($value, new ValidationRule('datetime-string', $strict, $options));
        self::assertSame($reason === null, $result->isValid());
        if ($reason === null) {
            self::assertSame($value, $result->value());
        }
        else {
            self::assertCount(1, $result->violations);
            self::assertSame($reason, $result->violations[0]->reason);
            self::assertSame('datetime-string', $result->violations[0]->expectedType);
            self::assertNull($result->violations[0]->key);
        }

    }

    public static function invalidOptions(): iterable
    {

        foreach (['datetime-string', 'array[datetime-string]'] as $type) {

            foreach ([
                [], ['format' => ''], ['format' => 12], ['format' => 'Y-m-d'], ['format' => 'H:i:s'],
                ['format' => 'Y-m-d h:i:s a'], ['format' => 'Y-m-d H:i.u'], ['format' => 'Y-m-d H:i:suv'],
                ['format' => 'Y-m-d H:i:sPP'], ['format' => 'Y-m-d H:i:s e'],
                ['format' => 'Y-m-d H:i:s', 'offset' => '+14:01'],
                ['format' => 'Y-m-d H:i:s', 'offset' => '+25:00'],
                ['format' => 'Y-m-d H:i:s', 'offset' => 'UTC'],
                ['format' => 'Y-m-d H:i:s', 'offset' => null],
                ['format' => 'Y-m-d H:i:sP', 'offset' => '+00:00'],
                ['format' => 'Y-m-d H:i:s', 'timezone' => '+03:00'],
                ['format' => 'Y-m-d H:i:s', 'timezone' => 'CET'],
                ['format' => 'Y-m-d H:i:s', 'timezone' => 'Unknown/Zone'],
                ['format' => 'Y-m-d H:i:s', 'timezone' => null],
                ['format' => 'Y-m-d H:i:s', 'unexpected' => true],
            ] as $options) {
                yield [$type, $options];
            }

        }

    }

    #[DataProvider('invalidOptions')]
    public function testConfiguration(string $type, array $options): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new ValidationRule($type, options: $options);
    }

    public function testArraysCollectAllViolationsAndPreserveKeys(): void
    {

        $rule = new ValidationRule('array[datetime-string]', options: [
            'format' => 'Y-m-d H:i:s', 'timezone' => 'Europe/Berlin',
        ]);
        $input = ['gap' => '2024-03-31 02:30:00', 8 => '2024-10-27 02:30:00', 'bad' => null];
        $result = GValidator::validate($input, $rule);
        self::assertSame(['gap', 8, 'bad'], array_column($result->violations, 'key'));
        self::assertSame(
            ['nonexistent_local_time', 'ambiguous_local_time', 'type_mismatch'],
            array_column($result->violations, 'reason'),
        );
        self::assertSame(
            ['datetime-string', 'datetime-string', 'datetime-string'],
            array_column($result->violations, 'expectedType'),
        );
        self::assertSame([], GValidator::validate([], $rule)->value());
        $valid = ['winter' => '2024-01-01 12:00:00', 3 => '2024-07-01 12:00:00'];
        self::assertSame($valid, GValidator::validate($valid, $rule)->value());
        self::assertSame('array[datetime-string]', GValidator::validate('x', $rule)->violations[0]->expectedType);
        $this->expectException(\LogicException::class);
        $result->value();

    }

    public function testDefaultTimezoneDoesNotChangeTheResult(): void
    {

        $old = date_default_timezone_get();
        try {

            date_default_timezone_set('Pacific/Apia');
            $rule = new ValidationRule('datetime-string', options: ['format' => 'Y-m-d H:i:s']);
            self::assertSame('2011-12-30 12:00:00', GValidator::validate('2011-12-30 12:00:00', $rule)->value());

        }
        finally {
            date_default_timezone_set($old);
        }

    }

}