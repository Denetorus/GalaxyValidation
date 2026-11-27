<?php
declare(strict_types=1);

namespace Galaxy\Validation\Tests;

use Galaxy\Validation\Rule\IntegerRule;
use InvalidArgumentException;

use Galaxy\Validation\{ValidationResult, ValidationError};
use PHPUnit\Framework\TestCase;

final class ValidationContractTest extends TestCase
{



    public function testUnsupportedOptionsAreConfigurationError(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new IntegerRule(options: ['unsupported' => 0]);
    }

    public function testEmptyFailureIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        ValidationResult::failure([]);
    }

    public function testNonListErrorsAreRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        ValidationResult::failure(['key' => new ValidationError('type_mismatch', 'string')]);
    }

    public function testInvalidErrorObjectIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        ValidationResult::failure(['wrong']);
    }

    public function testEmptyReasonIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new ValidationError('', 'string');
    }

    public function testEmptyExpectedTypeIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new ValidationError('type_mismatch', '');
    }

    public function testResultPreservesErrorOrderAndExcludesValueFromDebugInfo(): void
    {

        $first = new ValidationError('type_mismatch', 'string', 3);
        $second = new ValidationError('invalid_integer', 'integer', 'number');
        $failure = ValidationResult::failure([$first, $second]);
        self::assertSame([$first, $second], $failure->errors);
        self::assertSame(['errors' => [$first, $second]], $failure->__debugInfo());
        $success = ValidationResult::success('secret');
        self::assertSame('secret', $success->value());
        self::assertSame(['errors' => []], $success->__debugInfo());

    }

}