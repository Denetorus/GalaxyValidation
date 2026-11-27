<?php
declare(strict_types=1);

namespace Galaxy\Validation\Tests;

use Galaxy\Validation\GValidator;
use Galaxy\Validation\ValidationRuleInterface;
use Galaxy\Validation\ValidationResult;
use Galaxy\Validation\ValidationError;
use Galaxy\Validation\Rule\IntegerRule;
use Galaxy\Validation\Rule\DateStringRule;
use Galaxy\Validation\Rule\TimestampRule;
use Galaxy\Validation\Rule\MixedRule;
use Galaxy\Validation\Rule\StringRule;
use LogicException;
use stdClass;
use TypeError;
use PHPUnit\Framework\TestCase;

readonly class PositiveIntegerRule extends IntegerRule
{

    public function __construct(bool $strict = true)
    {
        parent::__construct($strict, ['greater' => 0]);
    }

}

readonly class LabelledDateRule extends DateStringRule
{
    public const string TYPE = 'labelled-date';
}

readonly class LabelledTimestampRule extends TimestampRule
{
    public const string TYPE = 'labelled-timestamp';
}

final class RuleClassesTest extends TestCase
{

    public function testRulePairAndValidatorResultAgree(): void
    {

        $rule = new IntegerRule(strict: false, options: ['min' => 0]);
        self::assertSame([[], 2], $rule->validate('002'));
        self::assertSame(2, GValidator::validate('002', $rule)->value());
        self::assertSame(2, GValidator::validate('002', $rule)->value());
        self::assertSame('type_mismatch', GValidator::validate('2', (new IntegerRule()))->errors[0]->reason);
        self::assertSame('type_mismatch', GValidator::validate([], (new StringRule()))->errors[0]->reason);

    }

    public function testBuiltInRuleCanBeExtendedWithoutAHandler(): void
    {

        self::assertSame(2, GValidator::validate('02', (new PositiveIntegerRule(false)))->value());
        self::assertSame('type_mismatch', GValidator::validate('02', (new PositiveIntegerRule()))->errors[0]->reason);
        self::assertSame('not_greater_than', GValidator::validate(0, (new PositiveIntegerRule()))->errors[0]->reason);
        self::assertFalse(GValidator::validate(-1, (new PositiveIntegerRule()))->isValid());

    }

    public function testCustomRuleOnlyNeedsTheInterface(): void
    {

        $rule = new class implements ValidationRuleInterface {

            public function validate(mixed $value): array
            {
                return $value === 'allowed' ? [[], $value]
                    : [[new ValidationError('not_allowed', 'allowed-string')], null];
            }

        };
        self::assertSame('allowed', GValidator::validate('allowed', $rule)->value());
        $result = GValidator::validate('other', $rule);
        self::assertSame('not_allowed', $result->errors[0]->reason);
        self::assertSame('allowed-string', $result->errors[0]->expectedType);
        self::assertNull($result->errors[0]->key);

    }

    public function testDateRuleAlgorithmDoesNotDependOnItsLabel(): void
    {

        $rule = new LabelledDateRule(options: ['format' => 'Y-m-d']);
        self::assertSame('2026-11-27', GValidator::validate('2026-11-27', $rule)->value());
        $invalid = GValidator::validate('2026-02-30', $rule);
        self::assertSame('invalid_date', $invalid->errors[0]->reason);
        self::assertSame('labelled-date', $invalid->errors[0]->expectedType);

    }

    public function testTimestampRuleAlgorithmDoesNotDependOnItsLabel(): void
    {

        $options = ['format' => 'Y-m-d', 'timezone' => 'UTC'];
        $rule = new LabelledTimestampRule(false, $options);
        self::assertSame(0, GValidator::validate('1970-01-01', $rule)->value());
        self::assertSame(-1, GValidator::validate(-1, $rule)->value());
        $invalid = GValidator::validate('1970-01-01', (new LabelledTimestampRule(true, $options)));
        self::assertSame('type_mismatch', $invalid->errors[0]->reason);
        self::assertSame('labelled-timestamp', $invalid->errors[0]->expectedType);

    }

    public function testNonRuleIsRejected(): void
    {
        $this->expectException(TypeError::class);
        GValidator::validate(1, new stdClass());
    }

    public function testCustomExceptionsRemainProgrammingErrors(): void
    {

        $rule = new class implements ValidationRuleInterface {

            public function validate(mixed $value): array
            {
                throw new LogicException('Custom configuration failure.');
            }

        };
        $this->expectException(LogicException::class);
        GValidator::validate(1, $rule);

    }

    public function testMixedRetainsValuesWithoutElementValidation(): void
    {

        $object = new stdClass();
        $value = ['object' => $object, 'nested' => [null, '001']];
        self::assertSame($value, GValidator::validate($value, (new MixedRule()))->value());
        self::assertSame($object, GValidator::validate($object, (new MixedRule()))->value());
        self::assertNull(GValidator::validate(null, (new MixedRule()))->value());

    }

}