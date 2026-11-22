<?php
declare(strict_types=1);

use Galaxy\Validation\GValidator;
use Galaxy\Validation\ValidationRule;
use Galaxy\Validation\ValidationResult;
use Galaxy\Validation\ValidationViolation;

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
foreach ([GValidator::class, ValidationRule::class, ValidationResult::class, ValidationViolation::class] as $class) {
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
ensure(GValidator::validate('18', new ValidationRule('integer', false))->value() === 18, 'Integer conversion failed.');
ensure(!GValidator::validate('18', new ValidationRule('integer'))->isValid(), 'Strict integer accepted string.');
ensure(GValidator::validate('1.5', new ValidationRule('float', false))->value() === 1.5, 'Float conversion failed.');
ensure(GValidator::validate('true', new ValidationRule('boolean', false))->value() === true, 'Boolean conversion failed.');
$object = new stdClass();
$input = ['object' => $object, 9 => [null, '001']];
foreach ([true, false] as $strict) {
    $result = GValidator::validate($input, new ValidationRule('array[mixed]', $strict));
    ensure($result->isValid() && $result->value() === $input, 'Mixed array was changed.');
    ensure(!GValidator::validate(null, new ValidationRule('array[mixed]', $strict))->isValid(), 'Non-array accepted.');
}
$invalid = GValidator::validate(['bad' => 'x'], new ValidationRule('array[integer]', false));
ensure($invalid->violations[0]->key === 'bad', 'Violation lost array key.');
ensure($invalid->violations[0]->reason === 'invalid_integer', 'Unexpected violation reason.');
$timestamp = GValidator::validate('1970-01-01', new ValidationRule('timestamp', false, [
    'format' => 'Y-m-d', 'timezone' => 'UTC',
]));
ensure($timestamp->value() === 0, 'Timestamp conversion failed.');
$date = GValidator::validate('2026-11-22', new ValidationRule('datetime', false, [
    'format' => 'Y-m-d', 'timezone' => 'Europe/Berlin',
]))->value();
ensure($date instanceof DateTimeImmutable, 'Immutable conversion failed.');
ensure($date->format('Y-m-d H:i:sP') === '2026-11-22 00:00:00+01:00', 'Midnight conversion failed.');
echo "Runtime-only consumer: $checks checks passed.", PHP_EOL;
