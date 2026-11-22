# ADR-001 — GalaxyValidation

**Status: Accepted.**

[Русский](ADR-001-GalaxyValidation.ru.md)

## Purpose and dependencies

Validate and, when allowed, normalize values from CLI, HTTP, databases, files, configuration,
external APIs and calculations. The component does not interpret transport, required parameters,
business error codes or GResult.

Package: denetorus/galaxy-validation. Namespace: Galaxy\Validation.
Runtime: PHP >=8.4 and denetorus/galaxy-date ^0.1.0 from Packagist, without a local path repository.
Development: phpunit/phpunit ^13.4.

## Public API

- GValidator::validate(mixed $value, ValidationRule $rule): ValidationResult.
- ValidationRule(string $type, bool $strict = true, array $options = []).
- ValidationResult::success(mixed $value), failure(array $violations), isValid(): bool, value(): mixed.
- ValidationViolation(string $reason, string $expectedType, int|string|null $key = null).

Supported scalar rules: string, integer, float, boolean, date-string, datetime-string, datetime, timestamp, mixed.
Each rule has an array[T] form, including array[mixed].
Rule names are case-sensitive.
Only the listed rule names are supported. Nested rules, schemas and union types are not provided.

Nonempty options are permitted only for date-string, datetime-string, datetime, timestamp and their array[T] forms.
For all other rules, options must be empty; otherwise the ValidationRule constructor throws InvalidArgumentException.

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
Nonempty options are validated at ValidationRule construction through the public GDate API;
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

## Arrays and results

array[mixed] requires a PHP array and accepts any elements, including null, objects and nested arrays.
Empty arrays are valid; keys, order, object identity and ordinary PHP reference semantics are preserved.
No element conversion occurs; strict does not affect the result. Non-arrays produce type_mismatch.

array[T] requires a PHP array; empty arrays are valid. Keys and iteration order are preserved.
Each element is independently checked using T and the same options/strictness.
Every invalid element produces its own violation, in input order. Nested arrays are accepted by mixed and array[mixed]; other array rules reject nested arrays.
No scalar wrapping occurs in the validator.

A successful result contains the entire normalized array. If any element fails, no partial array is exposed.
Ready immutable elements preserve identity; converted elements are new objects.
Ordinary PHP semantics apply to values, references and objects; there is no deep-copy policy.

violations is an ordered list of ValidationViolation; an empty list means success.
failure([]), non-list violations and non-violation entries are invalid configuration.
reason is a machine-readable cause; expectedType is the whole rule for a top-level mismatch or T for an element.
key is null at the top level, otherwise the original array key.
Invalid values are not retained in violations. value() on failure throws LogicException.
A successful mixed value may be null. Debug information omits the normalized value; this is not secure secret storage.

Reasons include type_mismatch, invalid_integer, integer_out_of_range, invalid_float, float_out_of_range,
invalid_boolean and the delegated GDate reasons invalid_date, invalid_datetime, invalid_offset,
nonexistent_local_time, ambiguous_local_time, timezone_offset_mismatch, timezone_required,
precision_loss and timestamp_out_of_range.
Invalid rule/result construction throws InvalidArgumentException; invalid data returns violations.

## Application integration and verification

Required/optional presence, wrapping, business constraints and GResult messages/codes belong to callers.
GalaxyApplicationConsole helpers write a successful non-strict normalized value back to CommandInput.
String temporal rules therefore keep strings, datetime writes an immutable object and timestamp writes an int.
The validator itself assigns no 4/4 or 5/4 codes and has no dependency on GalaxyResult, Console or a container.

Run composer install, then php vendor/bin/phpunit.
Tests cover scalar normalization, temporal strictness, ready values, configured string conversion,
calendar/zone/precision failures, arrays and unchanged input.
The published GalaxyDate dependency is installed automatically from Packagist.
