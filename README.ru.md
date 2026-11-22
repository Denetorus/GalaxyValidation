# GalaxyValidation

[English](README.md) · [ADR-001](docs/adr/ADR-001-GalaxyValidation.ru.md)

Проверка и нормализация значений из любых источников. PHP >=8.4.
Календарные операции используют опубликованный `denetorus/galaxy-date ^0.1.0` из Packagist.
Лицензия [MIT](LICENSE). Copyright (c) 2026 Mylnikov Denis.

## Типы и строгость

Основные правила: `string`, `integer`, `float`, `boolean`, `mixed`.

| Календарное правило | strict:true | strict:false |
| --- | --- | --- |
| date-string | Верная строка даты, без изменения | То же |
| datetime-string | Верная строка даты/времени, без изменения | То же |
| datetime | DateTimeImmutable | Также настроенное преобразование строки → DateTimeImmutable |
| timestamp | PHP int (Unix-секунды) | Также настроенное преобразование строки → int |

У каждого типа есть `array[T]`, включая `array[mixed]` и все четыре календарных типа.
`datetime` проверяет неизменяемый объект; `datetime-string` проверяет строку.
`strict:false` разрешает преобразование, а не сохранение исходного типа.

array[mixed] требует PHP array и принимает любые элементы, включая null, объекты и вложенные массивы.
Пустой массив допустим; сохраняются ключи, порядок, идентичность объектов и обычная семантика ссылок PHP.
Элементы не преобразуются; strict не влияет на результат. Не-массив даёт type_mismatch.

## Пример

Выполните `composer install` в этом каталоге исходников, затем сохраните как example.php:

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

Запуск: `php -d zend.assertions=1 -d assert.exception=1 example.php`. Вывод: `2026-11-22`.
Для внешних значений проверяйте `isValid()` перед `value()`; при ошибке читайте `violations`.
Вызов `value()` при ошибке приводит к `LogicException`.

## Календарные настройки и массивы

Строковые календарные правила требуют `options.format`; datetime-string также допускает timezone/offset.
Готовым неизменяемым объектам и int настройки не нужны, они возвращаются без изменений.
Нестрогое преобразование строки требует непустых исходных настроек GDate с явным форматом.
Без options строка даёт `type_mismatch`; без исходной зоны/смещения она не может стать моментом времени.
Настройки проверяются при создании правила и влияют только на преобразование строки.
Дата без времени становится полуночью в указанной зоне. Пропуски/повторы и точность проверяет GDate.
Ненулевые микросекунды при преобразовании в timestamp дают `precision_loss`.

Массивы сохраняют ключи, проверяют все элементы и сообщают о каждом неверном.
При ошибке частичный нормализованный массив не выдаётся. Исходное значение не изменяется.
Контроллеры могут записывать успешный нестрогий результат обратно в CommandInput.

## Разработка

```sh
composer install
composer validate --strict --no-check-publish
php vendor/bin/phpunit
```

GalaxyDate автоматически устанавливается из Packagist; соседний исходный каталог и Date path-репозиторий не нужны.
Полный контракт строгости, настроек и нарушений описан в ADR.