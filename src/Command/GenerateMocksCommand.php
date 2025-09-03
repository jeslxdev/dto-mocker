<?php
declare(strict_types=1);

namespace DtoMocker\Command;

use DtoMocker\DtoMocker;
use DtoMocker\Util\ClassScanner;
use ReflectionClass;
use ReflectionObject;
use DateTimeInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class GenerateMocksCommand extends Command
{
    protected static $defaultName = 'dto:generate';

    public function configure(): void
    {
        $this->setDescription('Generate fixtures from DTO classes')
            ->addArgument('path', InputArgument::OPTIONAL, 'Path to scan', 'src')
            ->addOption('out', null, InputOption::VALUE_REQUIRED, 'Output directory', 'tests/_fixtures')
            ->addOption('count', null, InputOption::VALUE_REQUIRED, 'Instances per class', 3)
            ->addOption('format', null, InputOption::VALUE_REQUIRED, 'Output format (json|php)', 'json')
            ->addOption('include-non-dto', null, InputOption::VALUE_NONE, 'Include non-Dto classes')
            ->addOption('only', null, InputOption::VALUE_REQUIRED, 'Comma separated list of classes to include', '')
            ->addOption('max-depth', null, InputOption::VALUE_REQUIRED, 'Max serialization depth', 3);

        // options added separately to keep chaining clear
        $this->addOption('mocks', null, InputOption::VALUE_NONE, 'Generate PHP mock classes');
        $this->addOption('mocks-namespace', null, InputOption::VALUE_REQUIRED, 'Namespace for generated mocks', 'App\\Test\\Mocks');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
    // __DIR__ is src/Command; two levels up is project root (src/Command -> src -> project)
    $projectRoot = realpath(__DIR__ . '/../../');
        $path = $projectRoot . DIRECTORY_SEPARATOR . $input->getArgument('path');
        $out = $projectRoot . DIRECTORY_SEPARATOR . (string) $input->getOption('out');
        $count = (int) $input->getOption('count');
        $format = (string) $input->getOption('format');
        $includeNonDto = (bool) $input->getOption('include-non-dto');
        $only = array_filter(array_map('trim', explode(',', (string) $input->getOption('only'))));
        $maxDepth = max(0, (int) $input->getOption('max-depth'));

        if (!is_dir($path)) {
            $output->writeln("<error>Scan path not found: {$path}</error>");
            return Command::FAILURE;
        }

        if (!is_dir($out)) {
            mkdir($out, 0777, true);
        }

        $scanner = new ClassScanner();
        $classMap = $scanner->scan($path);

        $classes = array_keys($classMap);
        $filtered = [];
        foreach ($classes as $class) {
            $short = (strpos($class, '\\') !== false) ? substr($class, strrpos($class, '\\') + 1) : $class;
            if (!empty($only) && !in_array($short, $only, true) && !in_array($class, $only, true)) {
                continue;
            }
            if (!$includeNonDto && str_ends_with($short, 'Dto')) {
                $filtered[] = $class;
                continue;
            }
            if ($includeNonDto) {
                try {
                    if (!class_exists($class)) {
                        require_once $classMap[$class];
                    }
                    $ref = new ReflectionClass($class);
                    foreach ($ref->getProperties() as $p) {
                        if ($p->getType() !== null) {
                            $filtered[] = $class;
                            break;
                        }
                    }
                } catch (\Throwable $e) {
                    continue;
                }
            }
        }

        $filtered = array_unique($filtered);
        if (empty($filtered)) {
            $output->writeln('<comment>No DTO-like classes found.</comment>');
            return Command::SUCCESS;
        }

        $mocker = new DtoMocker();
    $generateMocks = (bool) $input->getOption('mocks');
    $mocksNamespace = (string) $input->getOption('mocks-namespace');

        foreach ($filtered as $class) {
            $output->writeln("Generating for {$class}...");
            $items = [];
            for ($i = 0; $i < $count; $i++) {
                $obj = $mocker->make($class, $maxDepth);
                $items[] = is_object($obj) ? $this->objectToArray($obj, $maxDepth) : $obj;
            }

            $fileBase = str_replace('\\', '_', $class);
            $filePath = $out . DIRECTORY_SEPARATOR . $fileBase . ($format === 'php' ? '.php' : '.json');
            if ($format === 'php') {
                $content = "<?php\n\nreturn " . var_export($items, true) . ";\n";
                file_put_contents($filePath, $content);
            } else {
                file_put_contents($filePath, json_encode($items, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            }

            if ($generateMocks) {
                $this->generatePhpMock($class, $mocksNamespace, $out, $mocker, $maxDepth);
            }

            $output->writeln("Wrote {$filePath}");
        }

        return Command::SUCCESS;
    }

    private function generatePhpMock(string $class, string $mocksNamespace, string $outDir, DtoMocker $mocker, int $maxDepth): void
    {
        // derive short name
        $short = (strpos($class, '\\') !== false) ? substr($class, strrpos($class, '\\') + 1) : $class;
        $mockClassName = $short . 'Mock';

        // generate two examples
        $default = $mocker->make($class, $maxDepth);
        $guest = $mocker->make($class, $maxDepth);

        $namespaceLine = trim($mocksNamespace, '\\');
        $uses = "use {$class};";

        $defaultArgs = $this->buildConstructorArgs($default);
        $guestArgs = $this->buildConstructorArgs($guest, true);

        $code = "<?php\n\nnamespace {$namespaceLine};\n\n{$uses}\n\nclass {$mockClassName}\n{\n";
        $code .= "    public static function default(): {$short}\n    {\n        return new {$short}({$defaultArgs});\n    }\n\n";
        $code .= "    public static function guest(): {$short}\n    {\n        return new {$short}({$guestArgs});\n    }\n}\n";

        $mocksPath = $outDir . DIRECTORY_SEPARATOR . 'mocks';
        if (!is_dir($mocksPath)) {
            mkdir($mocksPath, 0777, true);
        }

        $filePath = $mocksPath . DIRECTORY_SEPARATOR . $mockClassName . '.php';
        file_put_contents($filePath, $code);
    }

    private function buildConstructorArgs(object $obj, bool $guest = false): string
    {
        $ref = new ReflectionObject($obj);
        // If DTO has constructor, map parameters; otherwise, return empty list
        $classRef = $ref->getParentClass() ? $ref->getParentClass() : $ref;
        try {
            $ctor = (new ReflectionClass($ref->getName()))->getConstructor();
        } catch (\ReflectionException $e) {
            return '';
        }
        if ($ctor === null) {
            return '';
        }
        $values = [];
        foreach ($ctor->getParameters() as $param) {
            $name = $param->getName();
            $prop = $ref->getProperty($name);
            $prop->setAccessible(true);
            $val = $prop->getValue($obj);
            if (is_string($val)) {
                $values[] = $name . ": '" . addslashes($val) . "'";
            } elseif (is_int($val) || is_float($val)) {
                $values[] = $name . ": " . $val;
            } elseif (is_bool($val)) {
                $values[] = $name . ": " . ($val ? 'true' : 'false');
            } else {
                $values[] = $name . ": null";
            }
        }

        return implode(',\n            ', $values);
    }

    private function objectToArray(object $o, int $depth = 3)
    {
        if ($depth < 0) {
            return [];
        }

        if ($o instanceof DateTimeInterface) {
            return $o->format(DATE_ATOM);
        }

        $result = [];
        $ref = new ReflectionObject($o);
        foreach ($ref->getProperties() as $prop) {
            $prop->setAccessible(true);
            $name = $prop->getName();
            $value = $prop->getValue($o);
            if (is_object($value)) {
                $result[$name] = $this->objectToArray($value, $depth - 1);
            } elseif (is_array($value)) {
                $result[$name] = array_map(fn($v) => is_object($v) ? $this->objectToArray($v, $depth - 1) : $v, $value);
            } else {
                $result[$name] = $value;
            }
        }

        return $result;
    }
}
