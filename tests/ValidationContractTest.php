<?php
declare(strict_types=1);

namespace Galaxy\Validation\Tests;

use Galaxy\Validation\{ValidationResult, ValidationRule, ValidationViolation};
use PHPUnit\Framework\TestCase;

final class ValidationContractTest extends TestCase
{

    public function testUnsupportedTypeIsConfigurationError(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new ValidationRule('unknown');
    }

    public function testUnsupportedOptionsAreConfigurationError(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new ValidationRule('integer', options: ['min' => 0]);
    }

    public function testEmptyFailureIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        ValidationResult::failure([]);
    }

    public function testNonListViolationsAreRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        ValidationResult::failure(['key' => new ValidationViolation('type_mismatch', 'string')]);
    }

    public function testInvalidViolationObjectIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        ValidationResult::failure(['wrong']);
    }

    public function testEmptyReasonIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new ValidationViolation('', 'string');
    }

    public function testEmptyExpectedTypeIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new ValidationViolation('type_mismatch', '');
    }

    public function testResultPreservesViolationOrderAndExcludesValueFromDebugInfo(): void
    {

        $first = new ValidationViolation('type_mismatch', 'string', 3);
        $second = new ValidationViolation('invalid_integer', 'integer', 'number');
        $failure = ValidationResult::failure([$first, $second]);
        self::assertSame([$first, $second], $failure->violations);
        self::assertSame(['violations' => [$first, $second]], $failure->__debugInfo());
        $success = ValidationResult::success('secret');
        self::assertSame('secret', $success->value());
        self::assertSame(['violations' => []], $success->__debugInfo());

    }

}