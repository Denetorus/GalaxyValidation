<?php
declare(strict_types=1);

namespace Galaxy\Validation\Tests;

use Galaxy\Validation\{GValidator, ValidationRule};
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class GValidatorTest extends TestCase
{

    public static function values(): iterable
    {

        yield ['integer', true, 18, 18, null];
        yield ['integer', true, '18', null, 'type_mismatch'];
        yield ['integer', false, '+0018', 18, null];
        yield ['integer', false, '-000', 0, null];
        yield ['integer', false, '-18', -18, null];
        yield ['integer', false, (string) PHP_INT_MAX, PHP_INT_MAX, null];
        yield ['integer', false, (string) PHP_INT_MIN, PHP_INT_MIN, null];
        yield ['integer', false, (string) PHP_INT_MAX . '0', null, 'integer_out_of_range'];
        yield ['integer', false, (string) PHP_INT_MIN . '0', null, 'integer_out_of_range'];
        foreach (['', ' 18', '18 ', '1.0', '1e2', '+', '--1', "1\n", '١'] as $value) {
            yield ['integer', false, $value, null, 'invalid_integer'];
        }
        foreach ([true, false, null, 18.0, [], new \stdClass()] as $value) {
            yield ['integer', false, $value, null, 'type_mismatch'];
        }
        yield ['string', true, '', '', null];
        yield ['string', false, '18', '18', null];
        yield ['string', false, 18, null, 'type_mismatch'];
        yield ['array[integer]', false, ['a' => '01', 4 => '-2'], ['a' => 1, 4 => -2], null];
        yield ['array[string]', true, [], [], null];
        yield ['array[integer]', true, [], [], null];
        yield ['array[string]', false, ['a' => 'x'], ['a' => 'x'], null];
        yield ['array[string]', true, 'x', null, 'type_mismatch'];

    }

    #[DataProvider('values')]
    public function testValues(string $type, bool $strict, mixed $input, mixed $expected, ?string $reason): void
    {

        $result = GValidator::validate($input, new ValidationRule($type, $strict));
        self::assertSame($reason === null, $result->isValid());
        if ($reason === null) {
            self::assertSame([], $result->violations);
            self::assertSame($expected, $result->value());
        }
        else {
            self::assertCount(1, $result->violations);
            self::assertSame($reason, $result->violations[0]->reason);
            self::assertSame($type, $result->violations[0]->expectedType);
            self::assertNull($result->violations[0]->key);
        }

    }

    public function testEveryInvalidElementIsReportedWithoutMutatingInput(): void
    {

        $input = ['good' => '2', 'bad' => 'x', 9 => [], 'nested' => ['1']];
        $result = GValidator::validate($input, new ValidationRule('array[integer]', false));
        self::assertFalse($result->isValid());
        self::assertSame(['bad', 9, 'nested'], array_column($result->violations, 'key'));
        self::assertSame(
            ['invalid_integer', 'type_mismatch', 'type_mismatch'],
            array_column($result->violations, 'reason'),
        );
        self::assertSame(['integer', 'integer', 'integer'], array_column($result->violations, 'expectedType'));
        self::assertSame(['good' => '2', 'bad' => 'x', 9 => [], 'nested' => ['1']], $input);
        $this->expectException(\LogicException::class);
        $result->value();

    }

    public function testMixedPreservesObjectIdentityAndNull(): void
    {

        $object = new \stdClass();
        foreach ([true, false] as $strict) {
            self::assertSame($object, GValidator::validate($object, new ValidationRule('mixed', $strict))->value());
            self::assertNull(GValidator::validate(null, new ValidationRule('mixed', $strict))->value());
        }

    }

}