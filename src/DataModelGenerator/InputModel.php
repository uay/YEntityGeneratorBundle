<?php

namespace Uay\YEntityGeneratorBundle\DataModelGenerator;

use Uay\YEntityGeneratorBundle\Utils\ArrayUtil;

class InputModel
{
    protected const CONFIG_KEY_ENTITIES = 'entities';
    protected const CONFIG_REQUIRED_PATHS = [
        'entitiesData' => self::CONFIG_KEY_ENTITIES,
    ];

    protected const CONFIG_KEY_NAMESPACE = 'namespace';
    protected const CONFIG_KEY_CLASS_POSTFIX = 'classPostfix';

    /**
     * @var array
     */
    protected $configuration = [
        self::CONFIG_KEY_NAMESPACE => [
            'app' => 'App',
            'base' => 'Generated',
            'enum' => 'Enum',
            'entity' => 'Entity',
            'repository' => 'Repository',
        ],
        self::CONFIG_KEY_CLASS_POSTFIX => [
            'entity' => 'Generated',
            'repository' => 'Repository',
        ],
    ];

    /**
     * @var array
     */
    protected $entitiesData = [];

    public function __construct(array $config)
    {
        $paths = ArrayUtil::getPathsRecursive($config);

        foreach (static::CONFIG_REQUIRED_PATHS as $property => $requiredPath) {
            if (!\in_array($requiredPath, $paths, true)) {
                throw new \RuntimeException("The path `$requiredPath` is missing in yaml configuration!");
            }

            $this->{$property} = ArrayUtil::getValueByPath($config, $requiredPath);
        }

        $this->configuration = ArrayUtil::fillRecursive($this->configuration, $config);
    }

    protected function getConfigStringOrThrow(string $group, string $name): string
    {
        if (!isset($this->configuration[$group][$name])) {
            throw new \RuntimeException("The configuration `$group.$name` is missing!");
        }

        return $this->configuration[$group][$name];
    }

    /**
     * @param string $name
     * @return string
     */
    public function getNamespace(string $name): string
    {
        return $this->getConfigStringOrThrow(static::CONFIG_KEY_NAMESPACE, $name);
    }

    /**
     * @param string $name
     * @return string
     */
    public function getClassPostfix(string $name): string
    {
        return $this->getConfigStringOrThrow(static::CONFIG_KEY_CLASS_POSTFIX, $name);
    }

    public function getEntitiesData(): array
    {
        return $this->entitiesData;
    }
}
