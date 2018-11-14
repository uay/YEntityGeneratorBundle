<?php

namespace Uay\YEntityGeneratorBundle\Utils;

abstract class ArrayUtil
{
    protected const PATH_SEPERATOR = '.';

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
                foreach (static::getPathsRecursive($value, $currentPath . static::PATH_SEPERATOR) as $item) {
                    $paths[] = $item;
                }
            }
        }

        return $paths;
    }

    /**
     * @param array $input
     * @param string[]|string $path
     * @param mixed $default
     * @return mixed
     */
    public static function getValueByPath(array $input, $path, $default = null)
    {
        if (\is_string($path)) {
            $path = explode(static::PATH_SEPERATOR, $path);
        }

        $pathCount = \count($path);

        if ($pathCount < 1) {
            return $default;
        }

        $currentPath = array_shift($path);
        if (!isset($input[$currentPath])) {
            return $default;
        }

        $input = $input[$currentPath];

        if ($pathCount === 1) {
            return $input;
        }

        return static::getValueByPath($input, $path, $default);
    }

    /**
     * @param array $a
     * @param array $b
     * @return array
     */
    public static function fillRecursive(array $a, array $b): array
    {
        foreach ($a as $aKey => $aValue) {
            if (!isset($b[$aKey])) {
                continue;
            }

            $value = $b[$aKey];

            if (\is_array($aValue)) {
                if (!\is_array($value)) {
                    continue;
                }

                $value = self::fillRecursive($aValue, $value);
            }

            $a[$aKey] = $value;
        }

        return $a;
    }
}
