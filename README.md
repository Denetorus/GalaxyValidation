# GalaxyValidation

[Русский](README.ru.md) · [ADR-001](docs/adr/ADR-001-GalaxyValidation.md)

Validate and normalize values from any source. PHP >=8.4.
Calendar operations use published `denetorus/galaxy-date ^0.1.0` from Packagist.
Licensed under [MIT](LICENSE). Copyright (c) 2026 Mylnikov Denis.

## Installation

Install version 0.1.0 from [Packagist](https://packagist.org/packages/denetorus/galaxy-validation):

```sh
composer require denetorus/galaxy-validation:0.1.0
```

Composer installs GalaxyDate automatically. Load `vendor/autoload.php` in your application.

## Types and strictness

Basic rules: `string`, `integer`, `float`, `boolean`, `mixed`.

| Temporal rule | strict:true | strict:false |
| --- | --- | --- |
| date-string | Valid date string, returned unchanged | Same |
| datetime-string | Valid datetime string, returned unchanged | Same |
| datetime | DateTimeImmutable | Also configured string → DateTimeImmutable |
| timestamp | PHP int (Unix seconds) | Also configured string → int |

Every type has an `array[T]` form, including `array[mixed]` and all four temporal types.
`datetime` validates an immutable object; `datetime-string` validates a string.
`strict:false` permits conversion, not preservation of the original input type.

array[mixed] requires a PHP array and accepts any elements, including null, objects and nested arrays.
Empty arrays are valid; keys, order, object identity and ordinary PHP reference semantics are preserved.
No element conversion occurs; strict does not affect the result. Non-arrays produce type_mismatch.

## Example

After installation, save as `example.php` in your project directory:

```php
<?php
require __DIR__ . '/vendor/autoload.php';

use Galaxy\Validation\GValidator;
use Galaxy\Validation\ValidationRule;

$integer = GValidator::validate('18', new ValidationRule('integer', strict: false));
assert($integer->value() === 18);

$string = GValidator::validate('2026-11-22', new ValidationRule('date-string', options: ['format' => 'Y-m-d']));
assert($string->value() === '2026-11-22');

$options = ['format' => 'Y-m-d', 'timezone' => 'Europe/Berlin'];
$object = GValidator::validate('2026-11-22', new ValidationRule('datetime', strict: false, options: $options));
assert($object->value() instanceof DateTimeImmutable);
assert($object->value()->format('Y-m-d H:i:sP') === '2026-11-22 00:00:00+01:00');

$timestamp = GValidator::validate('1970-01-01', new ValidationRule('timestamp', strict: false, options: [
    'format' => 'Y-m-d', 'timezone' => 'UTC',
]));
assert($timestamp->value() === 0);

$dates = GValidator::validate(['first' => '2026-11-22'], new ValidationRule('array[datetime]', false, $options));
assert($dates->value()['first'] instanceof DateTimeImmutable);
echo $string->value(), PHP_EOL;
```

Run `php -d zend.assertions=1 -d assert.exception=1 example.php`. Output: `2026-11-22`.
With external values, check `isValid()` before calling `value()`; on failure inspect `violations`.
Calling `value()` on failure throws `LogicException`.

## Calendar options and arrays

String temporal rules require `options.format`; datetime-string also allows timezone/offset.
Ready immutable objects and ints need no options and are returned unchanged.
Non-strict string conversion requires nonempty GDate source options with an explicit format.
Without options a string fails with `type_mismatch`; without a source zone/offset it cannot become an instant.
Options are configuration-checked when the rule is constructed and only affect string conversion.
A date without time becomes midnight in the explicitly given zone. DST gap/fold and precision rules follow GDate.
Timestamp conversion rejects nonzero microseconds with `precision_loss`.

Arrays preserve keys, check all elements and report each invalid element.
On failure no partial normalized array is exposed. The input is never modified.
Controllers can store a successful non-strict normalized value back in CommandInput.

## Development

```sh
composer install
composer validate --strict --no-check-publish
php vendor/bin/phpunit
```

GalaxyDate is installed automatically from Packagist; no adjacent checkout or Date path repository is needed.
The complete strictness, configuration and failure contracts are documented in the ADR.