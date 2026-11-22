<?php
declare(strict_types=1);

namespace Galaxy\Validation\Tests;

use Galaxy\Validation\GValidator;
use Galaxy\Validation\ValidationRule;
use PHPUnit\Framework\TestCase;

final class MixedArrayTest extends TestCase
{

    public function testMixedElementsArePreservedInBothModes(): void
    {

        $object = new \stdClass();
        $stream = fopen('php://memory', 'r+');
        try {

            $input = [
                'null' => null, 7 => '001', 'integer' => 42, 'float' => 1.5,
                'boolean' => false, 'object' => $object, 'nested' => ['value' => [null]],
                'resource' => $stream,
            ];
            foreach ([true, false] as $strict) {

                $result = GValidator::validate($input, new ValidationRule('array[mixed]', $strict));
                self::assertTrue($result->isValid());
                self::assertSame([], $result->violations);
                self::assertSame($input, $result->value());
                self::assertSame(array_keys($input), array_keys($result->value()));
                self::assertSame($object, $result->value()['object']);
                self::assertSame($stream, $result->value()['resource']);
                self::assertSame($input, GValidator::validate($input, new ValidationRule('mixed'))->value());

            }

        }
        finally {
            fclose($stream);
        }

    }

    public function testEmptyArrayIsValidInBothModes(): void
    {
        foreach ([true, false] as $strict) {
            $result = GValidator::validate([], new ValidationRule('array[mixed]', $strict));
            self::assertTrue($result->isValid());
            self::assertSame([], $result->value());
        }
    }

    public function testNonArraysFailWithoutWrappingInBothModes(): void
    {

        foreach ([null, false, 1, 1.5, 'value', new \stdClass(), new \ArrayObject([])] as $input) {
            foreach ([true, false] as $strict) {

                $result = GValidator::validate($input, new ValidationRule('array[mixed]', $strict));
                self::assertFalse($result->isValid());
                self::assertCount(1, $result->violations);
                self::assertSame('type_mismatch', $result->violations[0]->reason);
                self::assertSame('array[mixed]', $result->violations[0]->expectedType);
                self::assertNull($result->violations[0]->key);

            }
        }

    }

    public function testReferencesRetainOrdinaryPhpSemantics(): void
    {

        foreach ([true, false] as $strict) {

            $shared = 'before';
            $input = ['linked' => &$shared, 'plain' => 'before'];
            $output = GValidator::validate($input, new ValidationRule('array[mixed]', $strict))->value();
            self::assertSame('before', $shared);
            $shared = 'after';
            self::assertSame('after', $output['linked']);
            self::assertSame('before', $output['plain']);
            $output['plain'] = 'changed';
            self::assertSame('before', $input['plain']);

        }

    }

    public function testMixedArrayDoesNotAcceptOptions(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new ValidationRule('array[mixed]', options: ['format' => 'Y-m-d']);
    }

}
