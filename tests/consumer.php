<?php
declare(strict_types=1);

use Galaxy\Validation\Rule\IntegerRule;
use Galaxy\Validation\Rule\FloatRule;
use Galaxy\Validation\Rule\BooleanRule;
use Galaxy\Validation\Rule\TimestampRule;
use Galaxy\Validation\Rule\DateTimeRule;
use Galaxy\Validation\ValidationRuleInterface;
use Galaxy\Validation\GValidator;
use Galaxy\Validation\ValidationResult;
use Galaxy\Validation\ValidationError;

$root = realpath($argv[1] ?? '');
if ($root === false || !is_file($root . '/vendor/autoload.php')) {
    throw new RuntimeException('Supply the installed consumer directory.');
}
require $root . '/vendor/autoload.php';
$checks = 0;

function ensure(bool $condition, string $message): void
{

    global $checks;
    ++$checks;
    if (!$condition) {
        throw new RuntimeException($message);
    }

}

$lock = json_decode(file_get_contents($root . '/composer.lock'), true, flags: JSON_THROW_ON_ERROR);
ensure(
    array_column($lock['packages'], 'name') === ['denetorus/galaxy-date', 'denetorus/galaxy-validation'],
    'Unexpected runtime dependencies.',
);
ensure(($lock['packages-dev'] ?? []) === [], 'Unexpected dev dependencies.');
ensure($lock['packages'][0]['dist']['type'] === 'zip', 'GalaxyDate must come from Packagist, not a path repository.');
$installed = json_decode(
    file_get_contents($root . '/vendor/composer/installed.json'),
    true,
    flags: JSON_THROW_ON_ERROR,
);
ensure($installed['dev'] === false, 'Expected a runtime-only installation.');
ensure(!class_exists('PHPUnit\\Framework\\TestCase'), 'PHPUnit must not be installed.');
$vendor = realpath($root . '/vendor') . DIRECTORY_SEPARATOR;
foreach ([GValidator::class, ValidationRuleInterface::class, ValidationResult::class, ValidationError::class] as $class) {
    $file = (new ReflectionClass($class))->getFileName();
    ensure(str_starts_with(realpath($file), $vendor), 'Class loaded outside consumer vendor.');
}
$source = dirname(__DIR__) . '/src';
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source));
foreach ($iterator as $file) {

    if (!$file->isFile() || $file->getExtension() !== 'php') {
        continue;
    }
    $relative = substr($file->getPathname(), strlen($source) + 1);
    $copy = $root . '/vendor/denetorus/galaxy-validation/src/' . $relative;
    ensure(is_file($copy) && hash_file('sha256', $copy) === hash_file('sha256', $file->getPathname()), 'Source mismatch.');

}
ensure(GValidator::validate('18', new IntegerRule(false))->value() === 18, 'Integer conversion failed.');
ensure(!GValidator::validate('18', new IntegerRule())->isValid(), 'Strict integer accepted string.');
ensure(GValidator::validate('1.5', new FloatRule(false))->value() === 1.5, 'Float conversion failed.');
ensure(GValidator::validate('true', new BooleanRule(false))->value() === true, 'Boolean conversion failed.');
$timestamp = GValidator::validate('1970-01-01', new TimestampRule(false, [
    'format' => 'Y-m-d', 'timezone' => 'UTC',
]));
ensure($timestamp->value() === 0, 'Timestamp conversion failed.');
$date = GValidator::validate('2026-11-22', new DateTimeRule(false, [
    'format' => 'Y-m-d', 'timezone' => 'Europe/Berlin',
]))->value();
ensure($date instanceof DateTimeImmutable, 'Immutable conversion failed.');
ensure($date->format('Y-m-d H:i:sP') === '2026-11-22 00:00:00+01:00', 'Midnight conversion failed.');
echo "Runtime-only consumer: $checks checks passed.", PHP_EOL;
