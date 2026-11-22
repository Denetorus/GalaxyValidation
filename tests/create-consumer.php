<?php
declare(strict_types=1);

$consumer = $argv[1] ?? '';
$package = realpath($argv[2] ?? dirname(__DIR__));
if ($consumer === '' || $package === false || !is_file($package . '/composer.json')) {
    throw new RuntimeException('Supply an empty consumer directory and GalaxyValidation source directory.');
}
if (!is_dir($consumer) && !mkdir($consumer, 0777, true)) {
    throw new RuntimeException('Cannot create consumer directory.');
}
if (is_link($consumer) || array_values(array_diff(scandir($consumer), ['.', '..'])) !== []) {
    throw new RuntimeException('Consumer directory must be empty and must not be a link.');
}
$manifest = json_decode(file_get_contents($package . '/composer.json'), true, flags: JSON_THROW_ON_ERROR);
if (($manifest['name'] ?? null) !== 'denetorus/galaxy-validation') {
    throw new RuntimeException('Expected GalaxyValidation source.');
}
$config = [
    'name' => 'galaxy-validation-check/consumer',
    'license' => 'proprietary',
    'require' => ['php' => '>=8.4', 'denetorus/galaxy-validation' => 'dev-ci'],
    'repositories' => [[
        'type' => 'path', 'url' => str_replace('\\', '/', $package),
        'options' => ['symlink' => false, 'versions' => ['denetorus/galaxy-validation' => 'dev-ci']],
    ]],
];
file_put_contents(
    $consumer . '/composer.json',
    json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL,
);
echo "Created independent runtime consumer.\n";