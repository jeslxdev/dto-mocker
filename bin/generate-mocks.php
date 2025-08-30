#!/usr/bin/env php
<?php
declare(strict_types=1);

// Simple DTO mock generator script.
// Usage: php bin/generate-mocks.php [--path=src] [--out=tests/_fixtures] [--count=3]

if (PHP_SAPI !== 'cli') {
    echo "This script must be run from CLI.\n";
    exit(1);
}

require __DIR__ . '/../vendor/autoload.php';

use DtoMocker\DtoMocker;

/**
 * Build a class => file map by scanning PHP files and extracting namespace+class declarations.
 * This avoids requiring Composer internals at runtime.
 */
function buildClassMap(string $dir): array
{
    $map = [];
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
    foreach ($it as $file) {
        if (!$file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }
        $content = file_get_contents($file->getPathname());
        $namespace = null;
        $class = null;

        if (preg_match('/^\s*namespace\s+([^;]+);/m', $content, $m)) {
            $namespace = trim($m[1]);
        }
        if (preg_match('/^\s*(?:final\s+|abstract\s+)?class\s+([A-Za-z0-9_]+)/m', $content, $m2)) {
            $class = $m2[1];
        }

        if ($class !== null) {
            $fqcn = $namespace ? ($namespace . '\\' . $class) : $class;
            $map[$fqcn] = $file->getPathname();
        }
    }

    return $map;
}

$options = [
    'path' => 'src',
    'out' => 'tests/_fixtures',
    'count' => 3,
    'format' => 'json',
    'include_non_dto' => false,
    'only' => [],
    'max_depth' => 3,
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
    if (str_starts_with($arg, '--format=')) {
        $options['format'] = strtolower(substr($arg, 9));
    }
    if ($arg === '--include-non-dto') {
        $options['include_non_dto'] = true;
    }
    if (str_starts_with($arg, '--only=')) {
        $list = substr($arg, 7);
        $options['only'] = array_filter(array_map('trim', explode(',', $list)));
    }
    if (str_starts_with($arg, '--max-depth=')) {
        $options['max_depth'] = max(0, (int) substr($arg, 12));
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

$classMap = buildClassMap($scanPath);

$mocker = new DtoMocker();

// Serialize object (including protected/private) to array
function objectToArray(object $o, int $depth = 3)
{
    $result = [];
    if ($depth < 0) {
        return $result;
    }

    // handle DateTime-like objects
    if ($o instanceof DateTimeInterface) {
        return $o->format(DATE_ATOM);
    }

    $ref = new ReflectionObject($o);
    foreach ($ref->getProperties() as $prop) {
        $prop->setAccessible(true);
        $name = $prop->getName();
        $value = $prop->getValue($o);
        if (is_object($value)) {
            $result[$name] = objectToArray($value, $depth - 1);
        } elseif (is_array($value)) {
            $result[$name] = array_map(function ($v) use ($depth) { return is_object($v) ? objectToArray($v, $depth - 1) : $v; }, $value);
        } else {
            $result[$name] = $value;
        }
    }

    return $result;
}

$classes = array_keys($classMap);
$filtered = [];
foreach ($classes as $class) {
    $short = (strpos($class, '\\') !== false) ? substr($class, strrpos($class, '\\') + 1) : $class;
    // by default include classes that end with 'Dto'
    if (!$options['include_non_dto'] && str_ends_with($short, 'Dto')) {
        $filtered[] = $class;
        continue;
    }

    if ($options['include_non_dto']) {
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
}

$filtered = array_unique($filtered);
if (empty($filtered)) {
    $scanned = count($classes);
    echo "No DTO-like classes found. Scanned {$scanned} classes.\n";
    echo "Try --include-non-dto or --only to target specific classes.\n";
    exit(0);
}

echo "Found " . count($filtered) . " classes.\n";

foreach ($filtered as $class) {
    echo "Generating for {$class}... ";
    $items = [];
    for ($i = 0; $i < $options['count']; $i++) {
    $obj = $mocker->make($class, $options['max_depth']);
    $items[] = is_object($obj) ? objectToArray($obj, $options['max_depth']) : $obj;
    }

    $fileBase = str_replace('\\', '_', $class);
    $filePath = $outPath . DIRECTORY_SEPARATOR . $fileBase . ($options['format'] === 'php' ? '.php' : '.json');
    if ($options['format'] === 'php') {
        $content = "<?php\n\nreturn " . var_export($items, true) . ";\n";
        file_put_contents($filePath, $content);
    } else {
        file_put_contents($filePath, json_encode($items, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
    echo "wrote {$filePath}\n";
}

echo "Done.\n";
