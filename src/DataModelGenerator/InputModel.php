<?php

namespace Uay\YEntityGeneratorBundle\DataModelGenerator;

use Uay\YEntityGeneratorBundle\Utils\ArrayUtil;

class InputModel
{
    protected const CONFIG_KEY_ENTITIES = 'entities';
    protected const CONFIG_REQUIRED_PATHS = [
        self::CONFIG_KEY_ENTITIES,
    ];

    /**
     * @var array
     */
    protected $rawEntities = [];

    public function __construct(array $config)
    {
        $paths = ArrayUtil::getPathsRecursive($config);

        foreach (static::CONFIG_REQUIRED_PATHS as $requiredPath) {
            if (!\in_array($requiredPath, $paths, true)) {
                throw new \RuntimeException("The path `$requiredPath` is missing in yaml configuration!");
            }
        }

        $this->rawEntities = $config[static::CONFIG_KEY_ENTITIES];
    }

    public function getRawEntities(): array
    {
        return $this->rawEntities;
    }
}
