<?php

namespace Uay\YEntityGeneratorBundle\DataModelGenerator;

use Uay\YEntityGeneratorBundle\Utils\ArrayUtil;

class InputModel
{
    protected const CONFIG_KEY_NAMESPACE = 'namespace';
    protected const CONFIG_KEY_ENTITIES = 'entities';
    protected const CONFIG_REQUIRED_PATHS = [
        'namespace' => self::CONFIG_KEY_NAMESPACE,
        '$entitiesData' => self::CONFIG_KEY_ENTITIES,
    ];

    /**
     * @var string
     */
    protected $namespace;

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

        $this->entitiesData = $config[static::CONFIG_KEY_ENTITIES];
    }

    public function getNamespace(): string
    {
        return $this->namespace;
    }

    public function getEntitiesData(): array
    {
        return $this->entitiesData;
    }
}
