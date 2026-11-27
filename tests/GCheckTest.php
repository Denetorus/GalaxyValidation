<?php
declare(strict_types=1);

namespace Galaxy\Validation\Tests;

use Galaxy\Validation\Rule\FloatRule;
use Galaxy\Validation\Rule\IntegerRule;
use Galaxy\Validation\Rule\BooleanRule;
use Galaxy\Validation\GCheck;
use Galaxy\Validation\GValidator;
use InvalidArgumentException;
use stdClass;
use PHPUnit\Framework\TestCase;

final class GCheckTest extends TestCase
{

    public function testNumericSyntaxDoesNotNormalizeOrCheckMachineRange(): void
    {

        foreach (['0', '+001', '-0', str_repeat('9', 100)] as $value) {
            self::assertTrue(GCheck::integerString($value));
        }
        foreach (['1', '-1.25e3', '.75', '1.', '+0.0E-2', '1e9999'] as $value) {
            self::assertTrue(GCheck::floatString($value));
        }
        foreach (['', ' 1', "1\n", '1.0', '1e2', '--1', '١'] as $value) {
            self::assertFalse(GCheck::integerString($value));
        }
        foreach (['', ' 1', "1\n", '.', '1e', 'NaN', 'INF', '0x10', '1,25', '١'] as $value) {
            self::assertFalse(GCheck::floatString($value));
        }
        self::assertFalse(GValidator::validate('1e9999', new FloatRule(false))->isValid());
        self::assertFalse(GValidator::validate(str_repeat('9', 100), new IntegerRule(false))->isValid());

    }

    public function testNonStringsAreRejectedWithoutConversion(): void
    {

        foreach (['integerString', 'floatString', 'booleanString', 'email', 'url', 'uuidV4'] as $method) {
            foreach ([null, true, 123, 1.2, [], new stdClass()] as $value) {
                self::assertFalse(GCheck::$method($value));
            }
        }
        self::assertFalse(GCheck::matches(123, '/123/'));

    }

    public function testBooleanStringsAndValidatorNormalization(): void
    {

        foreach (['0' => false, '1' => true, 'true' => true, 'TRUE' => true,
            'TrUe' => true, 'false' => false, 'FALSE' => false, 'FaLsE' => false] as $key => $expected) {

            $value = (string) $key;
            self::assertTrue(GCheck::booleanString($value));
            $result = GValidator::validate($value, new BooleanRule(false));
            self::assertTrue($result->isValid());
            self::assertSame($expected, $result->value());
            self::assertFalse(GValidator::validate($value, new BooleanRule())->isValid());

        }
        foreach (['', ' true', 'false ', "true\n", 'yes', 'no', '00', '+1', '2', '1.0'] as $value) {

            self::assertFalse(GCheck::booleanString($value));
            $result = GValidator::validate($value, new BooleanRule(false));
            self::assertFalse($result->isValid());
            self::assertSame('invalid_boolean', $result->errors[0]->reason);

        }

    }

    public function testEmailAndUrlAreSyntaxChecksOnly(): void
    {

        self::assertTrue(GCheck::email('user+tag@example.com'));
        self::assertFalse(GCheck::email(' user@example.com'));
        self::assertFalse(GCheck::email('user@'));
        self::assertFalse(GCheck::email("user@example.com\n"));
        self::assertTrue(GCheck::url('https://example.com/path?q=1'));
        self::assertTrue(GCheck::url('ftp://example.com/file'));
        self::assertFalse(GCheck::url('/relative'));
        self::assertFalse(GCheck::url('example.com'));
        self::assertFalse(GCheck::url(' https://example.com'));
        self::assertFalse(GCheck::url('javascript:alert(1)'));

    }

    public function testUuidRequiresVersionFourAndRfcVariant(): void
    {

        $uuid = '861b307b-31b6-4381-8036-c0d1fa3a48c7';
        self::assertTrue(GCheck::uuidV4($uuid));
        self::assertTrue(GCheck::uuidV4(strtoupper($uuid)));
        self::assertFalse(GCheck::uuidV4(str_replace('-4381-', '-1381-', $uuid)));
        self::assertFalse(GCheck::uuidV4(str_replace('-8036-', '-7036-', $uuid)));
        self::assertFalse(GCheck::uuidV4('{' . $uuid . '}'));
        self::assertFalse(GCheck::uuidV4($uuid . "\n"));

    }

    public function testMatchesUsesCallerPatternWithoutImplicitAnchoring(): void
    {
        self::assertTrue(GCheck::matches('abc123def', '/[0-9]+/'));
        self::assertFalse(GCheck::matches('abc123def', '/\A[0-9]+\z/D'));
        self::assertFalse(GCheck::matches('abc', '/[0-9]+/'));
    }

    public function testInvalidPatternThrowsWithoutEmittingWarning(): void
    {
        $this->expectException(InvalidArgumentException::class);
        GCheck::matches('abc', '/[/');
    }

}