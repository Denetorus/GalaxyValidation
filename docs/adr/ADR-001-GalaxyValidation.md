# ADR-001 — GalaxyValidation

**Status: Accepted.**

[Русский](ADR-001-GalaxyValidation.ru.md)

## Purpose and package

GalaxyValidation validates and normalizes values from any data source.
Package: denetorus/galaxy-validation. Namespace: Galaxy\Validation.
Runtime requirements: PHP >=8.4, ext-filter, denetorus/galaxy-date ^0.1.0.
Development dependency: phpunit/phpunit ^13.4.
License: MIT. Copyright (c) 2026 Mylnikov Denis.

## Validation flow

ValidationRuleInterface declares validate(mixed $value): array.
Its result is exactly [errors, value], where errors is a list of ValidationError.
Success is [[], normalizedValue]; failure is [nonemptyErrors, null].

GValidator::validate(mixed $value, ValidationRuleInterface $rule): ValidationResult calls the rule,
checks the pair structure and builds the public result. It holds no state between calls.
An invalid pair, non-list errors or non-ValidationError entries cause InvalidArgumentException.
A failed pair's value is discarded. Exceptions raised by a custom rule propagate to the caller.

~~~php
use Galaxy\Validation\GValidator;
use Galaxy\Validation\Rule\IntegerRule;

$rule = new IntegerRule(strict: false, options: ['min' => 0]);
$result = GValidator::validate('002', $rule);
$result->isValid(); // true
$result->value();   // 2
~~~

## Result and errors

ValidationResult is readonly. Its API:
- success(mixed $value): self;
- failure(array $errors): self;
- isValid(): bool;
- value(): mixed;
- public array $errors.

An empty errors list means success. failure([]) is invalid.
value() on failure throws LogicException. Success may contain null.
Debug output contains errors and omits the normalized value.

ValidationError is readonly and contains:
- reason: nonempty machine-readable cause;
- expectedType: nonempty expected-type descriptor;
- key: int|string|null, identifying an invalid array element or null for a scalar check.

Errors do not store rejected values. They are ordered by validation order.
Ordinary PHP value/reference/object semantics apply; mutable objects are not deeply copied.

## Scalar rules

Rules live in Galaxy\Validation\Rule. Each constructor accepts
bool $strict = true, array $options = [].

| Class | expectedType | strict=true | Additional input with strict=false |
| --- | --- | --- | --- |
| StringRule | string | PHP string, including empty | Same |
| IntegerRule | integer | PHP int | Complete ASCII decimal integer string |
| FloatRule | float | Finite PHP float | PHP int or decimal/exponent string |
| BooleanRule | boolean | PHP bool | Integers 0/1 and strings 0/1/true/false |
| MixedRule | mixed | Any value | Same |
| DateStringRule | date-string | Valid date string | Same string validation |
| DateTimeStringRule | datetime-string | Valid datetime string | Same string validation |
| DateTimeRule | datetime | DateTimeImmutable | Configured string conversion |
| TimestampRule | timestamp | PHP int, Unix seconds | Configured string conversion |

Integer strings permit an optional sign and leading zeros; whitespace, fractions and exponent notation fail.
The value must fit PHP_INT_MIN..PHP_INT_MAX.
Float strings permit an optional sign, decimal point and exponent, including .75, 1. and -1.25e3.
Whitespace, hexadecimal notation, comma separators, NAN/INF and overflow to infinity fail.
PHP floating-point rounding and underflow apply.

Boolean strings compare ASCII case insensitively without trimming.
Other integers, floats, null, empty strings and yes/no fail BooleanRule.
MixedRule preserves values and object identity; array values are treated opaquely.

Strictness controls documented conversions. Built-in rules do not mutate the supplied value.
Only successful normalized values are exposed by ValidationResult.

## Numeric options

IntegerRule and FloatRule accept min (>=), max (<=), greater (>) and less (<).
Bounds are finite PHP int/float values. Constraints run after type validation and conversion,
in the order min, max, greater, less; the first failure is returned.
Corresponding reasons: below_min, above_max, not_greater_than, not_less_than.

Aliases >=, <=, >, < normalize to min, max, greater, less at construction.
Unknown keys, invalid bounds and duplicate name/alias pairs cause InvalidArgumentException.
Contradictory bounds are permitted, but an input must satisfy every constraint.
StringRule, BooleanRule and MixedRule accept empty options only.

## Calendar options

Each calendar rule validates its own options and directly calls the relevant public GDate method.

- DateStringRule requires format and calls GDate::validateDate.
- DateTimeStringRule requires format, optionally offset/timezone, and calls GDate::validateDateTime.
- DateTimeRule calls GDate::toDateTime for non-strict string conversion.
- TimestampRule calls GDate::toTimestamp for non-strict string conversion.

String calendar rules preserve the string in both strict modes. DateTimeStringRule requires time in the format.
For DateTimeRule/TimestampRule, ready objects/integers are returned unchanged; options may be empty.
String conversion requires nonempty source options. Without them, a string produces type_mismatch.
Options are validated at construction even for strict rules and ready input values.
A mutable DateTime is not a DateTimeRule value; numeric strings are not implicitly Unix timestamps.

A date-only conversion uses midnight in an explicit source timezone/offset.
An inline offset may be described with P. Missing source zone/offset produces timezone_required.
GDate defines exact format matching, calendar ranges, zone transitions and offset consistency.
Nonzero microseconds produce precision_loss for TimestampRule and are retained by DateTimeRule.
Ready timestamp integers have no additional inferred business range.
Calendar validation and conversion semantics follow the
[GalaxyDate contract](https://github.com/Denetorus/GalaxyDate/blob/v0.1.0/docs/adr/ADR-001-GalaxyDate.md).

## Array rules

ArrayRule(ValidationRuleInterface $elementRule = new MixedRule()) validates a PHP array.
Its element rule controls strictness/options. ArrayRule has no separate strict setting.
Empty arrays succeed. Non-arrays produce type_mismatch with expectedType=array and key=null.

Every element is checked. Errors retain their reason/expectedType and receive the outer array key.
All errors are collected in element/error order. Keys and order of successful values are preserved.
An unsuccessful array returns null value, never a partially normalized array.
Element results are pairs; ArrayRule does not create per-element ValidationResult objects.
It checks custom element pair structure. Custom exceptions propagate.

Default/exact MixedRule preserves ordinary PHP references and object identity.
A custom MixedRule subclass is executed rather than bypassed.
Opaque nested array values are permitted by MixedRule; their contents are not validated.
Nested built-in array-rule composition is rejected with InvalidArgumentException.
Scalar rules reject array elements that do not match their type.
ValidationError represents one key, not a nested path.

~~~php
use Galaxy\Validation\GValidator;
use Galaxy\Validation\Rule\ArrayRule;
use Galaxy\Validation\Rule\IntegerRule;

$result = GValidator::validate(
    ['first' => '12', 'second' => 'wrong'],
    new ArrayRule(new IntegerRule(strict: false, options: ['min' => 0])),
);
// $result->errors[0]: reason=invalid_integer, expectedType=integer, key=second.
~~~

## Specialized arrays and shared implementation

ArrayIntegerRule, ArrayStringRule, ArrayFloatRule and ArrayBooleanRule accept
bool $strict = true, array $options = [].
They have the same result/error semantics as ArrayRule with the corresponding scalar rule.
Numeric options and aliases are shared; string/boolean array rules accept empty options only.

These classes inherit the corresponding scalar check, normalization and constraints.
ArrayValidationTrait provides shared traversal, directly calling protected checks.
The specialized path avoids public per-element pair verification and intermediate error-list allocation
for successful elements. ValidationError is created for each failed element.
ArrayRule rejects these specialized array rules as element rules.

ScalarRule shares pair construction; NumericRule shares numeric options/constraints.
ScalarRule::validate is overridable; specialized-array validate is final.
TYPE identifies expectedType in errors; it does not select a validation algorithm.
The public result is constructed by GValidator.

~~~php
use Galaxy\Validation\GValidator;
use Galaxy\Validation\Rule\ArrayIntegerRule;

$result = GValidator::validate(
    ['first' => '12', 7 => '-1'],
    new ArrayIntegerRule(strict: false, options: ['min' => 0]),
);
~~~

## Extensibility

An application rule implements ValidationRuleInterface and returns the errors/value pair.
It may return multiple ValidationError entries and defines its settings and strict conversions.
The application rule is trusted code and should not mutate inputs or shared state.

Built-in rules are inheritable readonly classes. A subclass must also be readonly.
A subclass can preset options or extend protected checks.

~~~php
use Galaxy\Validation\Rule\IntegerRule;

readonly class PositiveIntegerRule extends IntegerRule
{
    public function __construct(bool $strict = true)
    {
        parent::__construct($strict, ['greater' => 0]);
    }
}
~~~

## GCheck predicates

GCheck exposes static bool predicates without conversion or trimming:
- integerString: complete ASCII signed decimal syntax, with leading zeros; no integer-range check;
- floatString: decimal/exponent syntax; no representable-float-range check;
- booleanString: strings 0/1/true/false, ASCII case insensitive;
- email: FILTER_VALIDATE_EMAIL syntax;
- url: FILTER_VALIDATE_URL absolute URL syntax;
- uuidV4: canonical hyphenated UUID v4 with RFC variant 8/9/a/b, ASCII case insensitive;
- matches(mixed $value, string $pattern): evaluates the caller's delimited PCRE pattern.

Non-string values return false. matches does not evaluate a pattern for non-string input.
PCRE evaluation failure causes InvalidArgumentException without emitting the underlying warning
or including the pattern/input in the exception text.
Email/URL checks do not prove existence or authorize network access. URL schemes are not restricted to HTTP(S);
IDN conversion is not performed. UUID validation does not prove uniqueness.
Built-in numeric/boolean rules use GCheck for string syntax and perform their own normalization/range checks.

## Verification

The test suite checks strict/non-strict conversions, numeric boundaries, calendar options/reasons,
rule inheritance, custom pair validation, array errors/keys, specialized/generic equivalence and predicates.
Autonomous Composer installation checks require only declared runtime dependencies,
load public symbols through PSR-4 and execute examples without development dependencies.

Commands: composer validate --strict; php vendor/bin/phpunit --configuration phpunit.xml.
