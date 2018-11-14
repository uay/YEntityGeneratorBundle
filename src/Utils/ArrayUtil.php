<?php

namespace Uay\YEntityGeneratorBundle\Utils;

abstract class ArrayUtil
{
    /**
     * @param array $config
     * @param string $path
     * @return string[]
     */
    public static function getPathsRecursive(array $config, string $path = ''): array
    {
        $paths = [];

        foreach ($config as $key => $value) {
            $currentPath = $path . $key;
            $paths[] = $currentPath;

            if (\is_array($value)) {
                foreach (static::getPathsRecursive($value, $currentPath . '.') as $item) {
                    $paths[] = $item;
                }
            }
        }

        return $paths;
    }
}
