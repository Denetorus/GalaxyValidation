# GalaxyValidation

[Русский](README.ru.md) · [ADR-001](docs/adr/ADR-001-GalaxyValidation.md)

MIT. Copyright (c) 2026 Mylnikov Denis.

## Installation

```sh
composer require denetorus/galaxy-validation:0.2.0
```

Target release: **v0.2.0**. The command becomes available after publication.
This release replaces the previous API; backward compatibility is not provided.

## Purpose and dependencies

Independent value validation and normalization. PHP >=8.4, ext-filter, denetorus/galaxy-date ^0.1.0 from Packagist.
Namespace Galaxy\Validation; development PHPUnit ^13.4.
No transport, container, GResult or business-code dependencies.

## Rule contract

ValidationRuleInterface::validate(mixed $value): array, with an errors/value pair.
Each rule performs validation and returns an errors/value pair.
GValidator::validate(mixed $value, ValidationRuleInterface $rule): ValidationResult calls the rule and constructs the public ValidationResult.
There is no string-type constructor, handler argument, handler interface or handler registry.

Classes in Galaxy\Validation\Rule:
IntegerRule, FloatRule, BooleanRule, StringRule, MixedRule,
DateStringRule, DateTimeStringRule, DateTimeRule, TimestampRule.
Each scalar-rule constructor accepts bool $strict = true, array $options = [].
Built-ins are inheritable readonly classes; readonly subclasses may preset options or extend protected checks.
ScalarRule shares pair construction; NumericRule shares numeric bounds.
Each calendar rule validates its own options and calls its specific GDate method directly;
TYPE only identifies expectedType in errors and never selects the validation algorithm.
ScalarRule::validate is overridable; custom rules may also implement the interface directly.
Custom rules own their settings and must document and honor any strict setting.
Exceptions from custom rules propagate; ordinary invalid values return errors.

~~~php
use Galaxy\Validation\GValidator;
use Galaxy\Validation\Rule\IntegerRule;

$rule = new IntegerRule(strict: false, options: ['min' => 0]);
$pair = $rule->validate('002');
$result = GValidator::validate('002', $rule);

readonly class PositiveIntegerRule extends IntegerRule
{
    public function __construct(bool $strict = true)
    {
        parent::__construct($strict, ['greater' => 0]);
    }
}
~~~

A custom rule only needs ValidationRuleInterface::validate and may return one or several errors.
Built-in expectedType descriptors remain integer/float/boolean/string/mixed/date-string/datetime-string/datetime/timestamp.
ArrayRule composes an optional element rule. String array[T] syntax and scalar wrapping are not provided.
MixedRule can accept an array as an opaque PHP value; it does not inspect or normalize its elements.
## Strictness and scalar normalization

strict defaults to true: values must already have the required type.
strict:false additionally enables the documented conversions. It does not mean "return the original type".
Input is never mutated; read a successful normalized value through ValidationResult::value().

string accepts PHP strings, including empty strings, in both modes; other types are not converted to string.
integer strictly accepts PHP int. Non-strict integer additionally converts complete ASCII strings [+-]?[0-9]+.
Leading zeros and plus signs are accepted, whitespace is not. Values must fit PHP_INT_MIN..PHP_INT_MAX.
Float, bool, null, decimal and scientific notation are not converted to integer.

float strictly accepts finite PHP float; NAN/INF/-INF fail.
Non-strict float also converts PHP int and strings matching
[+-]?(?:[0-9]+(?:\.[0-9]*)?|\.[0-9]+)(?:[eE][+-]?[0-9]+)? over the entire string.
Whitespace, hexadecimal notation and comma separators are rejected; overflow to infinity fails.
PHP rounding and underflow apply; exact decimal or lossless large-integer representation is not guaranteed.

boolean strictly accepts PHP bool. Non-strict mode also accepts integers 0/1 and strings "0"/"1"/"true"/"false".
ASCII case is ignored for true/false, but whitespace is not trimmed.
Floats (including 0.0/1.0), null, empty strings, yes/no and other integers are rejected.
mixed always succeeds and preserves ordinary PHP value semantics, including object identity.

## Temporal rules

| Rule | Strict | Non-strict | Successful value |
| --- | --- | --- | --- |
| date-string | Calendar string matching format | Same string check | Original string |
| datetime-string | Datetime string matching format | Same string check | Original string |
| datetime | DateTimeImmutable only | Also configured string conversion through GDate::toDateTime | DateTimeImmutable |
| timestamp | PHP int only | Also configured string conversion through GDate::toTimestamp | PHP int, Unix seconds |

A mutable DateTime is not accepted by datetime in either mode.
Ready DateTimeImmutable/int values are returned unchanged, including identity of an immutable object.
Any PHP int is accepted as a ready timestamp; no extra calendar-range or business constraint is inferred.
A numeric string is not implicitly treated as epoch seconds.

date-string requires exactly options.format. datetime-string requires format and optionally offset/timezone.
Both delegate to GDate validation and preserve the input string, regardless of strict.
datetime-string requires time in the format; a date-only string is not silently assigned midnight by this rule.

For datetime/timestamp, options may be empty when checking ready values.
Non-strict string conversion is enabled only with nonempty GDate source options.
Without those options a string produces type_mismatch, not a guessed format.
Nonempty options are validated at rule construction through the public GDate API;
invalid formats, unknown keys, zones or offsets throw InvalidArgumentException even if strict:true.
Options apply only to string conversion and do not constrain or change ready values.

Conversion format may describe a date alone or a datetime.
A date alone becomes midnight in an explicit source zone/offset.
An explicit inline offset may be supplied in a datetime format using P.
A valid calendar string without a source zone/offset fails with timezone_required.
GDate handles missing/repeated local times, offset consistency and source precision.
Nonzero microseconds fail timestamp conversion with precision_loss, but remain in datetime objects.
No timezone conversion, formatting or database update is performed by GalaxyValidation.

Calendar tokens, exact matching, ranges and transitions follow the Accepted GalaxyDate ADR:
https://github.com/Denetorus/GalaxyDate/blob/v0.1.0/docs/adr/ADR-001-GalaxyDate.md
GValidator uses only public GDate methods. No calendar implementation is duplicated here.
Timezone diagnostics belong to Galaxy\Date\Diagnostics\TimezoneDatabase and are never called by validation.


## Results

ValidationResult::success(mixed $value), failure(array $errors), isValid():bool, value():mixed.
ValidationError(string $reason, string $expectedType, int|string|null $key = null).
Errors are an ordered list; empty means success. Invalid values are not retained.
Built-in scalar rules use key=null. Custom rules may supply a key.
failure([]), non-list errors or non-error entries throw InvalidArgumentException.
value() on failure throws LogicException. A successful mixed value may be null.
Ordinary PHP reference/object semantics apply; there is no deep copying.
Debug information omits the successful value; this is not secure secret storage.

## Integration

Required/optional presence and business errors belong to callers.
Callers pass rule objects implementing ValidationRuleInterface, not string rule definitions.
The package does not automatically update existing application integrations.
## Numeric bounds

IntegerRule and FloatRule accept options: min (>=), max (<=), greater (>), less (<).
Bounds must be finite PHP int/float. Comparisons run after successful type validation/conversion.
Reasons: below_min, above_max, not_greater_than, not_less_than.
Constraints run in the above order; the first error is returned.
Contradictory bounds are allowed but values not satisfying every bound fail.
Aliases >=, <=, >, < normalize to min, max, greater, less at rule construction.
Duplicate name/alias pairs, unknown options and invalid bounds throw InvalidArgumentException.
StringRule, BooleanRule and MixedRule accept no options.

~~~php
use Galaxy\Validation\Rule\IntegerRule;

$rule = new IntegerRule(strict: false, options: ['min' => 0, 'less' => 100]);
~~~
## Static checks: GCheck

GCheck provides static boolean predicates without conversion, normalization, trimming or error accumulation.
All value parameters are mixed; non-string values return false. No object is constructed.

- integerString($value): ASCII signed decimal integer syntax, with leading zeros allowed; no PHP integer range check.
- floatString($value): ASCII decimal/exponent syntax; accepts .75, 1., -1.25e3; rejects whitespace, NaN and INF.
  It checks syntax only: 1e9999 passes this predicate but fails GValidator float range validation.
- booleanString($value): only strings 0, 1, true and false; ASCII case insensitive, no whitespace or conversion.
  GValidator uses it for string acceptance and retains strictness, normalization and error reasons.
- email($value): PHP FILTER_VALIDATE_EMAIL syntax; no mailbox/DNS lookup.
- url($value): PHP FILTER_VALIDATE_URL absolute URL syntax, not restricted to HTTP(S).
  It does not authorize schemes, destinations, network access or safe fetching; IDN conversion is not performed.
- uuidV4($value): canonical hyphenated UUID v4, RFC variant (8/9/a/b), ASCII case insensitive.
  No braces, whitespace or uniqueness/existence check.
- matches($value, string $pattern): preg_match boolean result; caller supplies delimiters, anchors and modifiers.
  The value is not rewritten. An invalid pattern or PCRE evaluation failure throws InvalidArgumentException
  without emitting the underlying warning or exposing the input/pattern in the exception text.
  Non-string input returns false without evaluating the pattern.

~~~php
use Galaxy\Validation\GCheck;

if (!GCheck::floatString($value)) {
    // The string is not a decimal/exponent number.
}
~~~

Built-in rules use GCheck for numeric string syntax and retains range checking, normalization and errors.
Calendar operations remain in GDate. GCheck does not construct or configure rule objects.



PHPUnit: php vendor/bin/phpunit

## ArrayRule

ArrayRule(ValidationRuleInterface $elementRule = new MixedRule()) implements
ValidationRuleInterface. The element rule is optional; omission checks only that the input is a PHP array.
No strict/options parameters belong to ArrayRule. Each element rule owns its strictness and normalization.
Non-array input returns type_mismatch with expectedType=array and key=null; scalar wrapping is not performed.
Empty arrays are valid. Keys and iteration order are preserved.
Every element rule returns an errors/value pair; all errors are collected in element/error order.
Errors retain reason/expectedType and receive the outer array key.
On success the whole normalized array is returned; on failure no partial value is available.
Custom rule exceptions propagate unchanged.
The default/exact MixedRule preserves ordinary PHP references and object identity; custom MixedRule subclasses
are still executed. There is no deep-copy guarantee for mutable objects.
Passing ArrayRule as elementRule throws InvalidArgumentException: nested rule composition is not supported.
A nested array value may be retained opaquely by MixedRule; this does not validate its contents.
Typed scalar rules reject nested array values as type mismatches.
ValidationError stores one key, not a nested path. No StructureRule or field schema is provided.

~~~php
use Galaxy\Validation\Rule\ArrayRule;
use Galaxy\Validation\Rule\IntegerRule;

$pair = (new ArrayRule(new IntegerRule(
    strict: false,
    options: ['min' => 0],
)))->validate(['first' => '12', 'second' => 'wrong']);
// $pair[0][0]: invalid_integer, expectedType=integer, key=second.
$plainPair = (new ArrayRule())->validate(['anything' => null]);
~~~
## Rule pairs and result construction

ValidationRuleInterface::validate(mixed $value): array returns exactly [errors, value].
errors is a list of ValidationError; success is [[], normalizedValue]. Failure is [nonemptyErrors, null].
Rules do not construct ValidationResult. ArrayRule gathers element pairs and creates no per-element result object.
GValidator::validate(value, rule): ValidationResult validates the pair shape and constructs one public result:
success(value) for empty errors, failure(errors) otherwise. A failed pair's value is discarded.
Only GValidator creates the public validation result in the validation flow; it keeps no state across calls.
Malformed pairs/non-error entries are programming errors (InvalidArgumentException), not data failures.
ArrayRule checks element pair shape as well; custom exceptions propagate.
Strictness, conversion, error reasons/keys, default MixedRule, array order and no nested ArrayRule remain unchanged.

~~~php
use Galaxy\Validation\GValidator;
use Galaxy\Validation\Rule\IntegerRule;

$rule = new IntegerRule(strict: false);
[$errors, $value] = $rule->validate('002'); // [], 2
$result = GValidator::validate('002', $rule);
$result->isValid(); // true
$result->value();   // 2
~~~
## Specialized typed array rules

ArrayIntegerRule, ArrayStringRule, ArrayFloatRule and ArrayBooleanRule live in Galaxy\Validation\Rule.
Constructors match their scalar rules: bool $strict = true, array $options = [].
Integer/float options and aliases are identical to IntegerRule/FloatRule; string/boolean accept no options.
They implement ValidationRuleInterface, returning errors/value pairs. GValidator creates the final ValidationResult.

These classes inherit their scalar rule's protected type check, normalization and constraints;
ArrayValidationTrait shares the array traversal. No numeric/boolean/string validation algorithms are duplicated.
The traversal calls the protected checks directly, without public element-rule pair validation or intermediary
element errors lists/result objects. ValidationError objects are created only for invalid elements.
ScalarRule::validate is overridable to support this traversal; typed-array validate is final.
Custom subclasses may preset options or extend protected checks. Use ordinary ArrayRule for arbitrary rules.

Semantics match ArrayRule with the corresponding scalar element rule:
non-array => type_mismatch/array/key=null, empty valid, preserve keys/order, visit all elements,
collect errors with scalar expectedType/outer key, return null value on any error.
No scalar wrapping or nested array validation. ArrayRule rejects these specialized array rules as elements too.
Mixed opaque array handling remains unchanged.

~~~php
use Galaxy\Validation\GValidator;
use Galaxy\Validation\Rule\ArrayIntegerRule;

$result = GValidator::validate(
    ['first' => '12', 'second' => 'wrong'],
    new ArrayIntegerRule(strict: false, options: ['min' => 0]),
);
~~~