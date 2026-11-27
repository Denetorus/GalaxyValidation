<?php
declare(strict_types=1);

namespace Galaxy\Validation\Tests;

use Galaxy\Validation\GValidator;
use Galaxy\Validation\ValidationError;
use Galaxy\Validation\ValidationRuleInterface;
use Galaxy\Validation\Rule\ArrayRule;
use Galaxy\Validation\Rule\IntegerRule;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\TestCase;

final class RulePairTest extends TestCase
{

    public function testScalarPairsAndStrictArePreserved(): void
    {

        self::assertSame([[], 2], (new IntegerRule(false))->validate('002'));
        [$errors, $value] = (new IntegerRule())->validate('2');
        self::assertNull($value);
        self::assertCount(1, $errors);
        self::assertInstanceOf(ValidationError::class, $errors[0]);
        self::assertSame('type_mismatch', $errors[0]->reason);

    }

    public function testArrayReturnsOnePairWithoutPartialValues(): void
    {

        $rule = new ArrayRule(new IntegerRule(false));
        self::assertSame([[], ['a' => 2]], $rule->validate(['a' => '02']));
        [$errors, $value] = $rule->validate(['a' => 'bad', 'b' => '2', 9 => 'other']);
        self::assertNull($value);
        self::assertSame(['a', 9], array_column($errors, 'key'));
        self::assertSame(['invalid_integer', 'invalid_integer'], array_column($errors, 'reason'));

    }

    public function testValidatorDiscardsFailedValueAndDoesNotAccumulateAcrossCalls(): void
    {

        $rule = new class implements ValidationRuleInterface {

            public function validate(mixed $value): array
            {
                return [[new ValidationError('bad', 'custom')], 'must not be retained'];
            }

        };
        $failed = GValidator::validate('input', $rule);
        self::assertSame(['errors' => $failed->errors], $failed->__debugInfo());
        self::assertTrue(GValidator::validate(1, new IntegerRule())->isValid());
        $this->expectException(LogicException::class);
        $failed->value();

    }

    public function testMalformedPairsAreProgrammingErrors(): void
    {

        foreach ([[], [[], 1, 2], ['errors' => [], 'value' => 1], [null, 1],
            [[7 => new ValidationError('bad', 'custom')], null], [['not-an-error'], null]] as $pair) {

            $rule = new class($pair) implements ValidationRuleInterface {

                public function __construct(private array $pair)
                {
                }

                public function validate(mixed $value): array
                {
                    return $this->pair;
                }

            };
            try {
                GValidator::validate(1, $rule);
                self::fail('Malformed pair accepted.');
            } catch (InvalidArgumentException) {
                self::assertTrue(true);
            }

        }

    }

    public function testArrayRejectsMalformedElementPairs(): void
    {

        $rule = new class implements ValidationRuleInterface {

            public function validate(mixed $value): array
            {
                return [[], 1, 2];
            }

        };
        $this->expectException(InvalidArgumentException::class);
        GValidator::validate([1], new ArrayRule($rule));

    }

}