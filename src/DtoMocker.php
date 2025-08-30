<?php
declare(strict_types=1);

namespace DtoMocker;

use DateTimeImmutable;
use InvalidArgumentException;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionType;

/**
 * Class DtoMocker
 *
 * Small, framework-agnostic generator for DTO-like objects used in tests.
 */
class DtoMocker
{
    /** @var array<string,callable> */
    protected array $generators;

    /**
     * @param array<string,callable> $generators Optional custom generators to merge with defaults
     */
    public function __construct(array $generators = [])
    {
        $defaults = [
            'string' => fn() => 'str_' . bin2hex(random_bytes(3)),
            'int'    => fn() => random_int(1, 1000),
            'float'  => fn() => round(mt_rand() / mt_getrandmax(), 2),
            'bool'   => fn() => (bool) random_int(0, 1),
            'array'  => fn() => ['item_' . random_int(1, 100)],
            'datetime' => fn() => new DateTimeImmutable(),
            'email' => fn() => 'user+' . bin2hex(random_bytes(2)) . '@example.test',
        ];

        // normalize keys to lowercase and validate callables
        $this->generators = $defaults;
        foreach ($generators as $k => $v) {
            if (!is_string($k) || !is_callable($v)) {
                throw new InvalidArgumentException('Generators must be an array of string => callable');
            }
            $this->extend((string) $k, $v);
        }
    }

    /**
     * Create one instance of $class populated with generated values.
     *
     * @param class-string $class
     * @param int $maxDepth recursion guard for nested objects
     * @return object
     */
    public function make(string $class, int $maxDepth = 3): object
    {
        if (!class_exists($class)) {
            throw new InvalidArgumentException("Class {$class} not found");
        }

        if ($maxDepth < 0) {
            // Avoid infinite recursion
            throw new InvalidArgumentException('Max depth exceeded while creating mocks');
        }

    $reflection = new ReflectionClass($class);

        // If constructor can be called with generated args, prefer that
        $constructor = $reflection->getConstructor();
        if ($constructor && $constructor->getNumberOfRequiredParameters() === 0) {
            $instance = $reflection->newInstance();
        } elseif ($constructor && $constructor->getNumberOfParameters() > 0) {
            // try to build args from parameter types when possible
            $params = [];
            foreach ($constructor->getParameters() as $param) {
                $type = $param->getType();
                try {
                    $params[] = $this->generateForType($type, $maxDepth - 1);
                } catch (InvalidArgumentException $e) {
                    if ($param->isDefaultValueAvailable()) {
                        $params[] = $param->getDefaultValue();
                    } else {
                        // cannot satisfy constructor param, fallback to no-constructor path
                        $params = null;
                        break;
                    }
                }
            }

            if ($params !== null) {
                $instance = $reflection->newInstanceArgs($params);
            } else {
                $instance = $reflection->newInstanceWithoutConstructor();
            }
        } else {
            $instance = $reflection->newInstanceWithoutConstructor();
        }

        // Populate properties (public, protected, private)
        foreach ($reflection->getProperties() as $prop) {
            // skip static
            if ($prop->isStatic()) {
                continue;
            }

            $type = $prop->getType();
            try {
                $value = $this->generateForType($type, $maxDepth - 1);
            } catch (InvalidArgumentException $e) {
                // fallback to string generator
                $value = $this->callGenerator('string');
            }

            // make property writable regardless of visibility
            if (method_exists($prop, 'setAccessible')) {
                $prop->setAccessible(true);
            }

            $prop->setValue($instance, $value);
        }

        return $instance;
    }

    /**
     * Create multiple instances.
     *
     * @param class-string $class
     * @return array<object>
     */
    public function makeMany(string $class, int $count = 1): array
    {
        if ($count < 1) {
            return [];
        }

        $results = [];
        for ($i = 0; $i < $count; $i++) {
            $results[] = $this->make($class);
        }

        return $results;
    }

    /**
     * Extend or override a generator for a given type name.
     */
    public function extend(string $type, callable $generator): void
    {
    $this->generators[strtolower($type)] = $generator;
    }

    /**
     * Generate a value for a (possibly null/union) ReflectionType.
     *
     * @param ReflectionType|null $type
     * @param int $maxDepth
     * @return mixed
     */
    protected function generateForType(?ReflectionType $type, int $maxDepth): mixed
    {
        if ($type === null) {
            return $this->callGenerator('string');
        }

        // If union type, prefer the first non-null named type
        if (!$type instanceof ReflectionNamedType) {
            // unsupported complex type (e.g., union/intersection) -> fallback
            return ($this->generators['string'])();
        }

    $name = $type->getName();
        if ($type->allowsNull() && random_int(0, 3) === 0) {
            return null;
        }

        // builtin types
        if ($type->isBuiltin()) {
        switch ($name) {
                case 'int':
            return $this->callGenerator('int');
                case 'float':
            return $this->callGenerator('float');
                case 'bool':
            return $this->callGenerator('bool');
                case 'array':
            return $this->callGenerator('array');
                case 'string':
            return $this->callGenerator('string');
                default:
                    // fallback for other builtins
            return $this->callGenerator('string');
            }
        }

        // class types: if we have a generator with that alias, use it
        $lower = strtolower($name);
        if (isset($this->generators[$lower])) {
            return $this->callGenerator($lower);
        }

        // If the class exists, recursively build it
        if (class_exists($name)) {
            return $this->make($name, $maxDepth);
        }

        throw new InvalidArgumentException("Cannot generate value for type {$name}");
    }

    /**
     * Call a generator by key, normalizing the key and validating existence.
     */
    private function callGenerator(string $key): mixed
    {
        $k = strtolower($key);
        if (!isset($this->generators[$k]) || !is_callable($this->generators[$k])) {
            throw new InvalidArgumentException("Generator for type {$key} not found");
        }

        return ($this->generators[$k])();
    }
}