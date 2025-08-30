#!/usr/bin/env php
<?php

// Simple DTO mock generator script.
// Usage: php bin/generate-mocks.php [--path=src] [--out=tests/_fixtures] [--count=3]

if (PHP_SAPI !== 'cli') {
    echo "This script must be run from CLI.\n";
    exit(1);
}

require __DIR__ . '/../vendor/autoload.php';

use DtoMocker\DtoMocker;
use Composer\Autoload\ClassMapGenerator;

$options = [
    'path' => 'src',
    'out' => 'tests/_fixtures',
    'count' => 3,
];

// parse basic argv options
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--path=')) {
        $options['path'] = substr($arg, 7);
    }
    if (str_starts_with($arg, '--out=')) {
        $options['out'] = substr($arg, 6);
    }
    if (str_starts_with($arg, '--count=')) {
        $options['count'] = (int) substr($arg, 8);
    }
}

$projectRoot = realpath(__DIR__ . '/..');
$scanPath = $projectRoot . DIRECTORY_SEPARATOR . $options['path'];
$outPath = $projectRoot . DIRECTORY_SEPARATOR . $options['out'];

if (!is_dir($scanPath)) {
    fwrite(STDERR, "Scan path not found: {$scanPath}\n");
    exit(2);
}

if (!is_dir($outPath)) {
    mkdir($outPath, 0777, true);
}

echo "Scanning: {$scanPath}\n";

$classMap = ClassMapGenerator::createMap($scanPath);

$mocker = new DtoMocker();

$classes = array_keys($classMap);
$filtered = [];
foreach ($classes as $class) {
    // Heuristic: include classes that contain 'Dto' or have typed properties
    if (str_contains($class, 'Dto') || str_ends_with($class, 'Dto')) {
        $filtered[] = $class;
        continue;
    }

    // try to reflect and see if has typed properties
    try {
        if (!class_exists($class)) {
            require_once $classMap[$class];
        }
        $ref = new ReflectionClass($class);
        $props = $ref->getProperties();
        foreach ($props as $p) {
            if ($p->getType() !== null) {
                $filtered[] = $class;
                break;
            }
        }
    } catch (Throwable $e) {
        // ignore parse/load errors
        continue;
    }
}

$filtered = array_unique($filtered);
if (empty($filtered)) {
    echo "No DTO-like classes found.\n";
    exit(0);
}

echo "Found " . count($filtered) . " classes.\n";

foreach ($filtered as $class) {
    echo "Generating for {$class}... ";
    $items = [];
    for ($i = 0; $i < $options['count']; $i++) {
        $items[] = $mocker->make($class);
    }

    $fileName = str_replace('\\', '_', $class) . '.json';
    $filePath = $outPath . DIRECTORY_SEPARATOR . $fileName;
    file_put_contents($filePath, json_encode($items, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    echo "wrote {$filePath}\n";
}

echo "Done.\n";
