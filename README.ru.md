# GalaxyValidation

[English](README.md) · [ADR-001](docs/adr/ADR-001-GalaxyValidation.ru.md)

MIT. Copyright (c) 2026 Mylnikov Denis.

## Установка

```sh
composer require denetorus/galaxy-validation:0.2.0
```

Версия **0.2.0** доступна через [Packagist](https://packagist.org/packages/denetorus/galaxy-validation).
Редакция заменяет прежний API; обратная совместимость не предоставляется.

## Назначение и зависимости

Независимая проверка и нормализация значений. PHP >=8.4, ext-filter, denetorus/galaxy-date ^0.1.0 из Packagist.
Namespace Galaxy\Validation; для разработки PHPUnit ^13.4.
Нет зависимости от транспорта, контейнера, GResult и бизнес-кодов приложения.

## Контракт правила

ValidationRuleInterface::validate(mixed $value): array с парой errors/value.
Каждое правило выполняет проверку и возвращает пару errors/value.
GValidator::validate(mixed $value, ValidationRuleInterface $rule): ValidationResult вызывает правило и формирует публичный ValidationResult.
Нет конструктора со строковым типом, аргумента handler, интерфейса или реестра обработчиков.

Классы в Galaxy\Validation\Rule:
IntegerRule, FloatRule, BooleanRule, StringRule, MixedRule,
DateStringRule, DateTimeStringRule, DateTimeRule, TimestampRule.
Конструктор каждого скалярного правила принимает bool $strict = true, array $options = [].
Встроенные классы наследуемые и readonly; readonly-наследник может предустановить параметры или расширить protected-проверки.
ScalarRule объединяет формирование пары; NumericRule — числовые ограничения.
Каждое календарное правило само проверяет свои options и напрямую вызывает нужный метод GDate.
TYPE служит только обозначением expectedType в нарушениях и не выбирает алгоритм проверки.
ScalarRule::validate допускает переопределение; собственное правило также может напрямую реализовать интерфейс.
Пользовательское правило отвечает за свои настройки и обязано описать и соблюдать strict, если использует этот параметр.
Исключения пользовательских правил передаются вызывающему; обычное неверное значение возвращает нарушения.

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

Собственному правилу достаточно реализовать ValidationRuleInterface::validate; оно может вернуть несколько нарушений.
Встроенные expectedType сохраняются: integer/float/boolean/string/mixed/date-string/datetime-string/datetime/timestamp.
ArrayRule получает необязательное правило элемента. Строковый синтаксис array[T] и упаковка скаляров не предоставляются.
MixedRule принимает массив как непрозрачное PHP-значение, не проверяя и не преобразуя его элементы.
## Строгость и нормализация скаляров

По умолчанию strict:true: значение уже должно иметь требуемый тип.
strict:false дополнительно разрешает описанные преобразования, а не сохранение исходного типа.
Вход не изменяется; успешное нормализованное значение читается через ValidationResult::value().

string принимает PHP-строки, включая пустые, в обоих режимах; другие типы в строки не преобразуются.
integer строго принимает PHP int. Нестрогий режим также преобразует целые ASCII-строки [+-]?[0-9]+.
Ведущие нули и плюс разрешены, пробелы нет. Значение должно входить в PHP_INT_MIN..PHP_INT_MAX.
Float, bool, null, дробная и экспоненциальная запись в integer не преобразуются.

float строго принимает конечный PHP float; NAN/INF/-INF запрещены.
Нестрогий float также преобразует PHP int и строки, целиком соответствующие
[+-]?(?:[0-9]+(?:\.[0-9]*)?|\.[0-9]+)(?:[eE][+-]?[0-9]+)?.
Пробелы, шестнадцатеричная запись и десятичная запятая запрещены; переполнение до бесконечности отклоняется.
Действуют округление и underflow PHP; точные десятичные значения и целые без потерь не гарантируются.

boolean строго принимает PHP bool. Нестрогий режим также принимает целые0/1 и строки "0"/"1"/"true"/"false".
Для true/false ASCII-регистр игнорируется, пробелы не удаляются.
Float (включая0.0/1.0), null, пустые строки, yes/no и другие целые запрещены.
mixed всегда успешен и сохраняет обычную семантику PHP, включая идентичность объектов.

## Календарные правила

| Правило | Строгий режим | Нестрогий режим | Успешное значение |
| --- | --- | --- | --- |
| date-string | Строка даты по format | Та же проверка строки | Исходная строка |
| datetime-string | Строка даты/времени по format | Та же проверка строки | Исходная строка |
| datetime | Только DateTimeImmutable | Также настроенное преобразование строки через GDate::toDateTime | DateTimeImmutable |
| timestamp | Только PHP int | Также настроенное преобразование строки через GDate::toTimestamp | PHP int, Unix-секунды |

Изменяемый DateTime не принимается правилом datetime ни в одном режиме.
Готовые DateTimeImmutable/int возвращаются без изменений, включая идентичность неизменяемого объекта.
Любой PHP int допустим как готовый timestamp; календарные и бизнес-ограничения не добавляются неявно.
Числовая строка не трактуется автоматически как секунды epoch.

date-string требует ровно options.format. datetime-string требует format и допускает offset/timezone.
Оба правила вызывают проверку GDate и сохраняют строку независимо от strict.
datetime-string требует время в формате; дата без времени в этом правиле не становится полуночью автоматически.

Для datetime/timestamp options может быть пустым при проверке готовых значений.
Нестрогое преобразование строки включается только с непустыми исходными настройками GDate.
Без этих настроек строка даёт type_mismatch, без угадывания формата.
Непустые options проверяются при создании календарного правила через публичный API GDate;
неверные формат, ключи, зона или смещение вызывают InvalidArgumentException даже при strict:true.
Настройки относятся только к преобразованию строки и не ограничивают и не меняют готовые значения.

Формат преобразования может описывать только дату либо дату/время.
Дата без времени становится полуночью в явной исходной зоне/смещении.
Явное смещение в строке datetime можно задать через P.
Верная календарная строка без исходной зоны/смещения даёт timezone_required.
GDate проверяет пропуски/повторы местного времени, согласованность смещения и точность.
Ненулевые микросекунды дают precision_loss для timestamp, но сохраняются в объектах datetime.
GalaxyValidation не выполняет пересчёт зон, форматирование или обновление базы зон.

Календарные обозначения, точное соответствие, границы и переходы определяет принятый ADR GalaxyDate:
https://github.com/Denetorus/GalaxyDate/blob/v0.1.0/docs/adr/ADR-001-GalaxyDate.ru.md
GValidator использует только публичные методы GDate; календарная реализация здесь не дублируется.
Диагностика находится в Galaxy\Date\Diagnostics\TimezoneDatabase и не вызывается при валидации.


## Результаты

ValidationResult::success(mixed $value), failure(array $errors), isValid():bool, value():mixed.
ValidationError(string $reason, string $expectedType, int|string|null $key = null).
Нарушения — упорядоченный список; пустой означает успех. Неверные значения не сохраняются.
Встроенные скалярные правила используют key=null; собственное правило может указать ключ.
failure([]), не-список нарушений или элементы другого типа вызывают InvalidArgumentException.
value() при ошибке вызывает LogicException. Успешное mixed-значение может быть null.
Действует обычная семантика ссылок/объектов PHP, глубокого копирования нет.
Debug-представление скрывает успешное значение, но не является безопасным хранилищем секретов.

## Интеграция

Обязательность параметров и бизнес-ошибки определяет вызывающий код.
Передаются объекты ValidationRuleInterface, а не строковые определения правил.
Компонент не обновляет автоматически существующие интеграции приложений.
## Числовые ограничения

IntegerRule и FloatRule поддерживают options: min (>=), max (<=), greater (>), less (<).
Границы — конечные PHP int/float; сравнение выполняется после успешной проверки типа и преобразования.
Нарушения: below_min, above_max, not_greater_than, not_less_than.
Одновременно заданные ограничения проверяются в указанном порядке; возвращается первое нарушение.
Противоречивый диапазон допустим, но значения, не удовлетворяющие всем ограничениям, отклоняются.
Алиасы >=, <=, >, < нормализуются в min, max, greater, less при создании правила.
Дублирование имени и алиаса, неизвестные опции и некорректные границы вызывают InvalidArgumentException.
Строковые/логические/MixedRule не принимают options.

~~~php
use Galaxy\Validation\Rule\IntegerRule;

$rule = new IntegerRule(strict: false, options: ['min' => 0, 'less' => 100]);
~~~
## Статические проверки: GCheck

GCheck предоставляет статические проверки с результатом bool без преобразования, нормализации, trim
и накопления ошибок. Проверяемое значение имеет тип mixed; нестроковые значения дают false.
Создавать экземпляр не требуется.

- integerString($value): строковая запись целого ASCII-цифрами, необязательный знак, ведущие нули допустимы;
  диапазон PHP integer не проверяется.
- floatString($value): десятичная запись с необязательной экспонентой; допустимы .75, 1., -1.25e3.
  Пробелы, NaN и INF недопустимы. Проверяется только форма: 1e9999 допустимо здесь,
  но не проходит проверку диапазона float в GValidator.
- booleanString($value): только строки 0, 1, true и false, без учёта ASCII-регистра.
  Пробелы и нестроковые значения недопустимы; преобразование не выполняется.
  GValidator использует эту проверку строк, сохраняя strict, нормализацию и причины ошибок.
- email($value): синтаксис PHP FILTER_VALIDATE_EMAIL, без проверки DNS и существования ящика.
- url($value): синтаксис абсолютного URL через PHP FILTER_VALIDATE_URL, без ограничения HTTP(S).
  Метод не разрешает использование схемы/адреса и не проверяет безопасность сетевого обращения;
  преобразование IDN не выполняется.
- uuidV4($value): UUID v4 с дефисами и RFC-вариантом (8/9/a/b), ASCII-регистр не учитывается.
  Скобки и пробелы недопустимы; уникальность и существование не проверяются.
- matches($value, string $pattern): булевый результат preg_match; разделители, якоря и модификаторы задаёт вызывающий.
  Значение не изменяется. Ошибка шаблона или выполнения PCRE вызывает InvalidArgumentException
  без вывода предупреждения и без включения значения/шаблона в текст исключения.
  Для нестрокового значения возвращается false без вычисления шаблона.

~~~php
use Galaxy\Validation\GCheck;

if (!GCheck::floatString($value)) {
    // Строка не соответствует десятичной записи числа с возможной экспонентой.
}
~~~

Встроенные правила используют GCheck для формы числовых строк, сохраняя проверку диапазона,
преобразование и причины ошибок. Проверки дат остаются в GDate.
GCheck не создаёт и не настраивает объекты правил.



PHPUnit: php vendor/bin/phpunit

## ArrayRule

ArrayRule(ValidationRuleInterface $elementRule = new MixedRule()) реализует ValidationRuleInterface.
Правило элемента необязательно: без него проверяется только факт PHP-массива.
Собственных strict/options у ArrayRule нет: строгость и преобразование определяет правило элемента.
Нестроковые/другие входы, не являющиеся массивом, дают type_mismatch с expectedType=array и key=null.
Автоматической упаковки скаляров нет. Пустые массивы допустимы. Ключи и порядок сохраняются.
Проверяются все элементы; все ошибки собираются в порядке элементов и ошибок внутри элемента.
В ошибках сохраняются reason/expectedType, а key получает ключ внешнего массива.
Успех возвращает весь преобразованный массив; при ошибке частичный результат недоступен.
Исключения пользовательского правила передаются без изменений.
Обычный MixedRule сохраняет семантику PHP-ссылок и идентичность объектов; пользовательские наследники MixedRule
всё равно выполняются. Глубокого копирования изменяемых объектов нет.
Передача ArrayRule как elementRule вызывает InvalidArgumentException: композиция вложенных массивных правил не поддерживается.
MixedRule может сохранить вложенный массив как непрозрачное значение, но его элементы не проверяются.
Скалярное правило отклоняет вложенный массив как неверный тип.
ValidationError хранит один key, а не путь. Правило структуры с разными полями не предоставляется.

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
## Пары правил и формирование результата

ValidationRuleInterface::validate(mixed $value): array возвращает ровно [errors, value].
errors — список ValidationError; успех — [[], normalizedValue], ошибка — [непустойСписокОшибок, null].
Правила не создают ValidationResult. ArrayRule собирает пары элементов без отдельных объектов результата.
GValidator::validate(value, rule): ValidationResult проверяет форму пары и создаёт один публичный результат:
success(value) при пустом errors, иначе failure(errors). Значение ошибочной пары отбрасывается.
В процессе валидации только GValidator создаёт итоговый ValidationResult; между вызовами данные не накапливаются.
Некорректная пара или элементы errors другого типа — программная ошибка InvalidArgumentException.
ArrayRule также проверяет форму пар элементов; исключения пользовательских правил передаются вызывающему.
Strict, преобразование, причины/ключи ошибок, MixedRule по умолчанию, порядок массива и запрет вложенных ArrayRule сохранены.

~~~php
use Galaxy\Validation\GValidator;
use Galaxy\Validation\Rule\IntegerRule;

$rule = new IntegerRule(strict: false);
[$errors, $value] = $rule->validate('002'); // [], 2
$result = GValidator::validate('002', $rule);
$result->isValid(); // true
$result->value();   // 2
~~~
## Специализированные правила массивов

ArrayIntegerRule, ArrayStringRule, ArrayFloatRule и ArrayBooleanRule находятся в Galaxy\Validation\Rule.
Конструкторы соответствуют скалярным правилам: bool $strict = true, array $options = [].
Числовые опции/алиасы совпадают с IntegerRule/FloatRule; строковые и логические правила не принимают options.
Правила реализуют ValidationRuleInterface и возвращают пару errors/value. Итоговый ValidationResult создаёт GValidator.

Классы наследуют protected-проверку типа, преобразование и ограничения соответствующего скалярного правила;
ArrayValidationTrait объединяет обход массива. Алгоритмы проверки чисел/строк/boolean не дублируются.
Обход напрямую вызывает protected-проверки без публичной проверки пары каждого элемента и без промежуточных
списков ошибок/объектов результата для успешных элементов. ValidationError создаётся только при ошибке элемента.
ScalarRule::validate допускает переопределение для такого обхода; validate специализированного массива — final.
Наследник может предустановить options или расширить protected-проверку. Для произвольных правил служит обычный ArrayRule.

Семантика совпадает с ArrayRule и соответствующим скалярным правилом:
не-массив => type_mismatch/array/key=null, пустой массив допустим, ключи/порядок сохранены,
все элементы проверяются, ошибки имеют скалярный expectedType и ключ элемента, при ошибках value=null.
Упаковки скаляров и проверки вложенных массивов нет. ArrayRule также запрещает эти специализированные правила как elementRule.
Непрозрачная обработка массивов MixedRule не изменяется.

~~~php
use Galaxy\Validation\GValidator;
use Galaxy\Validation\Rule\ArrayIntegerRule;

$result = GValidator::validate(
    ['first' => '12', 'second' => 'wrong'],
    new ArrayIntegerRule(strict: false, options: ['min' => 0]),
);
~~~