<?php

namespace Uay\YEntityGeneratorBundle\Make;

class MakeFactory
{
    public const FILE_INTEND = '    ';

    public const FILTER_VALUE = '~[^A-Za-z0-9\\\\\\s\\.\\(\\)\\[\\]\\,\\r\\n\\"]~';

    /**
     * @var EntityClass
     */
    protected $class;

    /**
     * @var string
     */
    protected $namespace;

    /**
     * @var string
     */
    protected $namespacePath;

    public function __construct(string $kernelRootPath, string $namespace, EntityClass $class)
    {
        $this->class = $class;

        $basePath = $this->class->getBasePath();

        $this->namespace = $namespace . $basePath;

        $this->namespacePath = $kernelRootPath . DIRECTORY_SEPARATOR . $basePath;
        if (!file_exists($this->namespacePath)
            && !mkdir($concurrentDirectory = $this->namespacePath, 0777, true) && !is_dir($concurrentDirectory)) {
            throw new \RuntimeException(sprintf('Directory "%s" was not created', $concurrentDirectory));
        }
        $this->namespacePath = realpath($this->namespacePath);
    }

    public static function renderArrayAsString(array $array, ?string $intend = null): string
    {
        if ($intend === null) {
            $intend = static::FILE_INTEND;
        }

        $result = [];

        foreach ($array as $item) {
            if (\is_array($item)) {
                $result[] = static::renderArrayAsString($item, $intend . static::FILE_INTEND);
                continue;
            }

            $result[] = $intend . $item;
        }

        return implode(PHP_EOL, $result);
    }

    protected function getClassname(): string
    {
        return '\\' . $this->namespace . '\\' . $this->class->getName();
    }

    /**
     * @param string[] $lines
     * @return string[]
     */
    protected static function intentLines(array $lines): array
    {
        return array_map(function (string $line) {
            if ($line === '') {
                return $line;
            }

            return static::FILE_INTEND . $line;
        }, $lines);
    }

    protected static function typeIsArray(string $type): bool
    {
        return substr($type, -2) === '[]';
    }

    protected static function parseReturnType(string $type): ?string
    {
        $types = explode('|', $type);
        $types = array_unique($types);
        $typesCount = \count($types);

        if ($typesCount > 2) {
            return null;
        }

        if ($typesCount === 2) {
            if ($types[0] === 'null') {
                $type = '?' . $types[1];
            } elseif ($types[1] === 'null') {
                $type = '?' . $types[0];
            } else {
                return null;
            }
        } else {
            $type = $types[0];
        }

        if ($type === 'null') {
            return null;
        }

        if (static::typeIsArray($type)) {
            return 'array';
        }

        return $type;
    }

    /**
     * @return string[]
     */
    protected function getConstantes(): array
    {
        $lines = [];

        foreach ($this->class->getProperties() as $property) {
            if (!$property->isConstant()) {
                continue;
            }

            $name = strtoupper($property->getName());

            $defaultValue = preg_replace(static::FILTER_VALUE, '', $property->getDefault());

            if ($defaultValue === '') {
                $defaultValue = 'null';
            }

            $annotations = array_map(function (string $annotation) {
                return str_replace('*/', '', $annotation);
            }, $property->getAnnotations());

            $lines[] = '/**';
            if (\count($annotations) > 0) {
                foreach ($property->getAnnotations() as $annotation) {
                    $annotation = str_replace('*/', '', $annotation);

                    $lines[] = " * {$annotation}";
                }
                $lines[] = ' * ';
            }
            $lines[] = " * @var {$property->getType()}";
            $lines[] = ' */';
            $lines[] = "const {$name} = {$defaultValue};";
            $lines[] = '';
        }

        return static::intentLines($lines);
    }

    /**
     * @return string[]
     */
    protected function getProperties(): array
    {
        $lines = [];

        foreach ($this->class->getProperties() as $property) {
            if ($property->isConstant()) {
                continue;
            }

            $annotations = array_map(function (string $annotation) {
                return str_replace('*/', '', $annotation);
            }, $property->getAnnotations());

            $lines[] = '/**';
            if (\count($annotations) > 0) {
                foreach ($property->getAnnotations() as $annotation) {
                    $annotation = str_replace('*/', '', $annotation);

                    $lines[] = " * {$annotation}";
                }
                $lines[] = ' * ';
            }
            $lines[] = " * @var {$property->getType()}";
            $lines[] = ' */';
            $lines[] = "protected \${$property->getName()};";
            $lines[] = '';
        }

        return static::intentLines($lines);
    }

    /**
     * @return string[]
     */
    protected function getConstructor(): array
    {
        $properties = $this->class->getProperties();

        $properties = array_filter($properties, function (EntityClassProperty $property) {
            if ($property->isConstant()) {
                return false;
            }

            return \is_string($property->getDefault());
        });

        $lines = [];

        if (\count($properties) <= 0) {
            return $lines;
        }

        $lines[] = 'public function __construct()';
        $lines[] = '{';

        foreach ($properties as $property) {
            if ($property->isConstant()) {
                continue;
            }

            $defaultValue = preg_replace(static::FILTER_VALUE, '', $property->getDefault());

            if ($defaultValue === '') {
                $defaultValue = 'null';
            }

            $lines[] = static::FILE_INTEND . "\$this->{$property->getName()} = {$defaultValue};";
        }

        $lines[] = '}';
        $lines[] = '';

        return static::intentLines($lines);
    }

    /**
     * @return string[]
     */
    protected function getPropertyAccessors(): array
    {
        $lines = [];

        foreach ($this->class->getProperties() as $property) {
            if ($property->isConstant()) {
                continue;
            }

            $propertyName = ucfirst($property->getName());
            $propertyType = static::parseReturnType($property->getType());

            $getterReturnType = $propertyType !== null ? ": {$propertyType}" : '';
            $setterParameterType = $propertyType !== null ? "{$propertyType} " : '';

            // GETTER
            if ($propertyType === null) {
                $lines[] = '/**';
                $lines[] = " * @return {$property->getType()}";
                $lines[] = ' */';
            }
            $lines[] = "public function get{$propertyName}(){$getterReturnType}";
            $lines[] = '{';
            $lines[] = static::FILE_INTEND . "return \$this->{$property->getName()};";
            $lines[] = '}';
            $lines[] = '';

            // SETTER
            if ($propertyType === null) {
                $lines[] = '/**';
                $lines[] = " * @param {$property->getType()} \${$property->getName()}";
                $lines[] = ' */';
            }
            $lines[] = "public function set{$propertyName}({$setterParameterType}\${$property->getName()}): void";
            $lines[] = '{';
            $lines[] = static::FILE_INTEND . "\$this->{$property->getName()} = \${$property->getName()};";
            $lines[] = '}';
            $lines[] = '';
        }

        return static::intentLines($lines);
    }

    public function make(bool $generatedHint = true): bool
    {
        $classPath = $this->namespacePath . DIRECTORY_SEPARATOR . $this->class->getName() . '.php';

        $extends = $this->class->getExtends() ?? '';
        $extends = preg_replace('~[^A-Za-z0-9\\\\]~', '', $extends);
        if ($extends !== '') {
            $extends = " extends $extends";
        }

        $modifier = implode(' ', $this->class->getModifiers());
        if ($modifier !== '') {
            $modifier .= ' ';
        }

        $imports = array_map(function (string $import) {
            return preg_replace('~[^A-Za-z0-9\\\\\\s]~', '', $import);
        }, $this->class->getImports());
        $imports = array_filter($imports, function (string $import) {
            $importParts = explode('\\', $import);

            array_pop($importParts);

            return $importParts !== explode('\\', $this->namespace);
        });
        $annotations = array_map(function (string $annotation) {
            return str_replace('*/', '', $annotation);
        }, $this->class->getAnnotations());
        $annotationsCount = \count($annotations);

        $classLines = [
            '<?php',
            '',
            "namespace {$this->namespace};",
            '',
        ];

        foreach ($imports as $import) {
            $classLines[] = "use {$import};";
        }

        $classLines[] = '';
        $hasAnnotations = $annotationsCount > 0;
        if ($generatedHint || $hasAnnotations) {
            $classLines[] = '/**';
            if ($generatedHint) {
                $classLines[] = ' * This class was generated automatically, do not modify it here!';
            }
            if ($hasAnnotations) {
                if ($generatedHint) {
                    $classLines[] = ' * ';
                }
                foreach ($annotations as $annotation) {
                    $classLines[] = " * {$annotation}";
                }
            }
            $classLines[] = ' */';
        }
        $classLines[] = "{$modifier}class {$this->class->getName()}{$extends}";
        $classLines[] = '{';

        foreach ($this->getConstantes() as $line) {
            $classLines[] = $line;
        }

        foreach ($this->getProperties() as $line) {
            $classLines[] = $line;
        }

        foreach ($this->getConstructor() as $line) {
            $classLines[] = $line;
        }

        foreach ($this->getPropertyAccessors() as $line) {
            $classLines[] = $line;
        }

        $body = $this->class->getBody();
        if ($body !== null && $body !== '') {
            $classLines[] = $body;
        }

        // Removes last space
        $lastLine = array_pop($classLines);
        if ($lastLine !== '') {
            $classLines[] = $lastLine;
        }

        $classLines[] = '}';
        $classLines[] = '';

        file_put_contents($classPath, implode(PHP_EOL, $classLines));

        /** @noinspection PhpIncludeInspection */
        require_once $classPath;

        return class_exists('' . $this->getClassname());
    }
}
