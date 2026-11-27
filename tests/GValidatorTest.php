<?php
declare(strict_types=1);

namespace Galaxy\Validation\Tests;

require_once __DIR__ . '/Support/TestRules.php';

use Galaxy\Validation\Rule\MixedRule;
use Galaxy\Validation\Tests\Support\TestRules;
use LogicException;
use stdClass;

use Galaxy\Validation\{GValidator};
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
        foreach ([true, false, null, 18.0, [], new stdClass()] as $value) {
            yield ['integer', false, $value, null, 'type_mismatch'];
        }
        yield ['string', true, '', '', null];
        yield ['string', false, '18', '18', null];
        yield ['string', false, 18, null, 'type_mismatch'];

    }

    #[DataProvider('values')]
    public function testValues(string $type, bool $strict, mixed $input, mixed $expected, ?string $reason): void
    {

        $result = GValidator::validate($input, TestRules::create($type, $strict));
        self::assertSame($reason === null, $result->isValid());
        if ($reason === null) {
            self::assertSame([], $result->errors);
            self::assertSame($expected, $result->value());
        }
        else {
            self::assertCount(1, $result->errors);
            self::assertSame($reason, $result->errors[0]->reason);
            self::assertSame($type, $result->errors[0]->expectedType);
            self::assertNull($result->errors[0]->key);
        }

    }



    public function testMixedPreservesObjectIdentityAndNull(): void
    {

        $object = new stdClass();
        foreach ([true, false] as $strict) {
            self::assertSame($object, GValidator::validate($object, new MixedRule($strict))->value());
            self::assertNull(GValidator::validate(null, new MixedRule($strict))->value());
        }

    }

}