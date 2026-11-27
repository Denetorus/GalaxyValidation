# ADR-001 — GalaxyValidation

**Статус: Accepted.**

[English](ADR-001-GalaxyValidation.md)

## Назначение и пакет

GalaxyValidation проверяет и нормализует значения из любых источников данных.
Пакет: denetorus/galaxy-validation. Namespace: Galaxy\Validation.
Требования выполнения: PHP >=8.4, ext-filter, denetorus/galaxy-date ^0.1.0.
Зависимость разработки: phpunit/phpunit ^13.4.
Лицензия MIT. Copyright (c) 2026 Mylnikov Denis.

## Выполнение проверки

ValidationRuleInterface объявляет validate(mixed $value): array.
Результат — ровно [errors, value], где errors является списком ValidationError.
Успех — [[], normalizedValue]; ошибка — [непустойСписокОшибок, null].

GValidator::validate(mixed $value, ValidationRuleInterface $rule): ValidationResult вызывает правило,
проверяет структуру пары и создаёт публичный результат. Между вызовами состояние не хранится.
Некорректная пара, не-список ошибок или элементы другого типа вызывают InvalidArgumentException.
Значение ошибочной пары отбрасывается. Исключения пользовательского правила передаются вызывающему коду.

~~~php
use Galaxy\Validation\GValidator;
use Galaxy\Validation\Rule\IntegerRule;

$rule = new IntegerRule(strict: false, options: ['min' => 0]);
$result = GValidator::validate('002', $rule);
$result->isValid(); // true
$result->value();   // 2
~~~

## Результат и ошибки

ValidationResult — readonly-класс. Его API:
- success(mixed $value): self;
- failure(array $errors): self;
- isValid(): bool;
- value(): mixed;
- public array $errors.

Пустой errors означает успех. Вызов failure([]) недопустим.
value() при ошибке вызывает LogicException. Успешное значение может быть null.
Debug-представление содержит errors и скрывает нормализованное значение.

ValidationError — readonly-класс с полями:
- reason: непустая машинно-читаемая причина;
- expectedType: непустое обозначение ожидаемого типа;
- key: int|string|null — ключ неверного элемента массива либо null для скалярной проверки.

Ошибки не содержат отклонённые значения. Порядок соответствует порядку проверки.
Действует обычная семантика значений/ссылок/объектов PHP; изменяемые объекты глубоко не копируются.

## Скалярные правила

Правила находятся в Galaxy\Validation\Rule. Каждый конструктор принимает
bool $strict = true, array $options = [].

| Класс | expectedType | strict=true | Дополнительный вход при strict=false |
| --- | --- | --- | --- |
| StringRule | string | PHP string, включая пустую | То же |
| IntegerRule | integer | PHP int | Полная десятичная строка ASCII |
| FloatRule | float | Конечный PHP float | PHP int или десятичная строка с экспонентой |
| BooleanRule | boolean | PHP bool | Целые 0/1 и строки 0/1/true/false |
| MixedRule | mixed | Любое значение | То же |
| DateStringRule | date-string | Корректная строка даты | Та же строковая проверка |
| DateTimeStringRule | datetime-string | Корректная строка даты со временем | Та же строковая проверка |
| DateTimeRule | datetime | DateTimeImmutable | Преобразование строки по настройкам |
| TimestampRule | timestamp | PHP int, Unix-секунды | Преобразование строки по настройкам |

Целочисленная строка допускает знак и ведущие нули; пробелы, дробная часть и экспонента недопустимы.
Значение должно помещаться в PHP_INT_MIN..PHP_INT_MAX.
Строка float допускает знак, десятичную точку и экспоненту, включая .75, 1. и -1.25e3.
Пробелы, шестнадцатеричная запись, запятая, NAN/INF и переполнение в бесконечность недопустимы.
Для float действуют округление и потеря слишком малых значений по правилам PHP.

Boolean-строки сопоставляются без учёта ASCII-регистра, без удаления пробелов.
Другие целые, float, null, пустая строка и yes/no отклоняются BooleanRule.
MixedRule сохраняет значения и идентичность объектов; массив рассматривается как непрозрачное значение.

Strict управляет описанными преобразованиями. Встроенные правила не изменяют переданное значение.
ValidationResult предоставляет нормализованное значение только при успехе.

## Числовые опции

IntegerRule и FloatRule принимают min (>=), max (<=), greater (>) и less (<).
Границы — конечные PHP int/float. Ограничения применяются после проверки типа и преобразования
в порядке min, max, greater, less; возвращается первая ошибка.
Причины: below_min, above_max, not_greater_than, not_less_than.

Алиасы >=, <=, >, < при создании нормализуются в min, max, greater, less.
Неизвестные ключи, некорректные границы и дубли имени/алиаса вызывают InvalidArgumentException.
Противоречивые границы допустимы, но вход должен удовлетворять каждому ограничению.
StringRule, BooleanRule и MixedRule принимают только пустые options.

## Календарные опции

Каждое календарное правило проверяет собственные options и напрямую вызывает публичный метод GDate.

- DateStringRule требует format и вызывает GDate::validateDate.
- DateTimeStringRule требует format, допускает offset/timezone и вызывает GDate::validateDateTime.
- DateTimeRule вызывает GDate::toDateTime для нестрогого преобразования строки.
- TimestampRule вызывает GDate::toTimestamp для нестрогого преобразования строки.

Строковые календарные правила сохраняют строку при обоих strict. DateTimeStringRule требует времени в формате.
DateTimeRule/TimestampRule сохраняют готовый объект/целое без изменений; options могут быть пустыми.
Преобразование строки требует непустых опций исходного формата. Без них строка даёт type_mismatch.
Опции проверяются при создании даже для строгого правила и готового входного значения.
Изменяемый DateTime не подходит DateTimeRule; числовая строка не считается автоматически Unix timestamp.

Дата без времени преобразуется в полночь явно заданной исходной зоны/смещения.
Смещение внутри строки можно описать токеном P. Отсутствие исходной зоны/смещения даёт timezone_required.
GDate определяет точность формата, календарные диапазоны, переходы зон и согласованность смещений.
Ненулевые микросекунды дают precision_loss в TimestampRule и сохраняются в DateTimeRule.
Для готового целого timestamp дополнительные бизнес-ограничения диапазона не предполагаются.
Семантика календарной проверки и преобразования определяется
[контрактом GalaxyDate](https://github.com/Denetorus/GalaxyDate/blob/v0.1.0/docs/adr/ADR-001-GalaxyDate.ru.md).

## Правила массивов

ArrayRule(ValidationRuleInterface $elementRule = new MixedRule()) проверяет PHP-массив.
Strict/options задаются правилом элемента. Собственного strict у ArrayRule нет.
Пустой массив допустим. Не-массив даёт type_mismatch с expectedType=array и key=null.

Проверяются все элементы. В ошибках сохраняются reason/expectedType, а key получает ключ внешнего массива.
Все ошибки собираются в порядке элементов и ошибок. Ключи и порядок успешных значений сохраняются.
При неуспехе массив возвращает value=null, а не частично преобразованный результат.
Результаты элементов — пары; ArrayRule не создаёт ValidationResult для каждого элемента.
Структура пар пользовательского правила проверяется. Его исключения передаются вызывающему коду.

Обычный MixedRule сохраняет семантику PHP-ссылок и идентичность объектов.
Пользовательский наследник MixedRule выполняется, а не пропускается.
MixedRule допускает непрозрачные вложенные массивы, не проверяя их содержимое.
Композиция вложенных встроенных массивных правил вызывает InvalidArgumentException.
Скалярное правило отклоняет элемент-массив, не соответствующий его типу.
ValidationError хранит один key, а не вложенный путь.

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

## Специализированные массивы и общая реализация

ArrayIntegerRule, ArrayStringRule, ArrayFloatRule и ArrayBooleanRule принимают
bool $strict = true, array $options = [].
Семантика значений/ошибок совпадает с ArrayRule и соответствующим скалярным правилом.
Числовые опции и алиасы общие; массивные правила строк/boolean принимают только пустые options.

Классы наследуют проверку типа, преобразование и ограничения соответствующего скалярного правила.
ArrayValidationTrait выполняет общий обход, напрямую вызывая protected-проверки.
Специализированный путь не проверяет публичную пару каждого элемента и не выделяет промежуточный список ошибок
для успешного элемента. Для каждого неверного элемента создаётся ValidationError.
ArrayRule запрещает эти специализированные массивные правила в качестве elementRule.

ScalarRule объединяет формирование пары; NumericRule — числовые опции/ограничения.
ScalarRule::validate переопределяемый; validate специализированных массивов — final.
TYPE обозначает expectedType в ошибках и не выбирает алгоритм проверки.
Публичный результат создаёт GValidator.

~~~php
use Galaxy\Validation\GValidator;
use Galaxy\Validation\Rule\ArrayIntegerRule;

$result = GValidator::validate(
    ['first' => '12', 7 => '-1'],
    new ArrayIntegerRule(strict: false, options: ['min' => 0]),
);
~~~

## Расширение

Правило приложения реализует ValidationRuleInterface и возвращает пару errors/value.
Оно может вернуть несколько ValidationError, определяет свои настройки и преобразования strict.
Пользовательское правило является доверенным кодом и не должно изменять вход или общее состояние.

Встроенные правила — наследуемые readonly-классы. Наследник также объявляется readonly.
Он может предустановить options или расширить protected-проверки.

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

## Предикаты GCheck

GCheck предоставляет статические bool-проверки без преобразования и удаления пробелов:
- integerString: полная десятичная запись ASCII со знаком и ведущими нулями, без проверки диапазона int;
- floatString: десятичная запись с экспонентой, без проверки представимости в float;
- booleanString: строки 0/1/true/false без учёта ASCII-регистра;
- email: синтаксис FILTER_VALIDATE_EMAIL;
- url: синтаксис абсолютного URL через FILTER_VALIDATE_URL;
- uuidV4: UUID v4 с дефисами и RFC-вариантом 8/9/a/b, без учёта ASCII-регистра;
- matches(mixed $value, string $pattern): проверка PCRE-шаблона с разделителями, заданного вызывающим.

Нестроковые значения дают false. Для них matches не вычисляет шаблон.
Ошибка выполнения PCRE вызывает InvalidArgumentException без вывода исходного предупреждения
и без включения шаблона/значения в текст исключения.
Проверки email/URL не подтверждают существование и не разрешают сетевое обращение.
Схемы URL не ограничены HTTP(S); IDN не преобразуется. Проверка UUID не подтверждает уникальность.
Встроенные числовые/логические правила используют GCheck для формы строки и сами выполняют преобразование/проверку диапазона.

## Проверки

Тесты проверяют strict/non-strict, числовые границы, календарные опции/причины,
наследование правил, пользовательские пары, ключи/ошибки массивов, эквивалентность специализированных правил и предикаты.
Автономная установка Composer использует только объявленные зависимости выполнения,
загружает публичные сущности через PSR-4 и выполняет примеры без зависимостей разработки.

Команды: composer validate --strict; php vendor/bin/phpunit --configuration phpunit.xml.
