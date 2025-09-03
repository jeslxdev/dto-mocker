<?php
declare(strict_types=1);

namespace DtoMocker\Util;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;

final class ClassScanner
{
    /**
     * Scan a directory and return a map of FQCN => file path for classes found.
     * This is a lightweight scanner based on regexp and is intended for test fixtures.
     *
     * @return array<string,string>
     */
    public function scan(string $dir): array
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
}
