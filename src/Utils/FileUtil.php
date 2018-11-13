<?php

namespace Uay\YEntityGeneratorBundle\Utils;

abstract class FileUtil
{
    public const FILTER_RESULT_SKIP = 0;
    public const FILTER_RESULT_SKIP_BUT_SCAN = 1;
    public const FILTER_RESULT_KEEP = 2;

    /**
     * @param string|null $baseDir null for no base dir
     * @param callable|null $filter allows the file on true, skips the file on false
     * @param int|null $depth
     * @param string|null $dir
     * @param string[] $results
     * @param int $level
     * @return string[]
     * @see https://stackoverflow.com/a/24784144/3359418
     */
    public static function readRecursive(
        ?string $baseDir = null,
        ?callable $filter = null,
        ?int $depth = null,
        ?string $dir = null,
        array &$results = [],
        int $level = 0
    ): array
    {
        if ($depth !== null && $level >= $depth) {
            return $results;
        }

        if ($baseDir === null && ($baseDir = realpath($baseDir)) === false) {
            $path = realpath($dir);
        } else {
            $path = realpath($baseDir . DIRECTORY_SEPARATOR . $dir);
        }

        foreach (scandir($path, SCANDIR_SORT_NONE) as $object) {
            if ($object === '.' || $object === '..') {
                continue;
            }

            $pathObject = realpath($path . DIRECTORY_SEPARATOR . $object);
            if ($baseDir !== null) {
                $pathObjectBase = substr($pathObject, \strlen($baseDir));
            } else {
                $pathObjectBase = $pathObject;
            }

            $isDir = is_dir($pathObject);

            if ($filter !== null) {
                $filterResult = $filter($pathObjectBase, $object, $isDir, $level + 1);
            } else {
                $filterResult = static::FILTER_RESULT_KEEP;
            }

            if ($filterResult === static::FILTER_RESULT_SKIP) {
                continue;
            }

            if ($isDir) {
                static::readRecursive($baseDir, $filter, $depth, $pathObjectBase, $results, $level + 1);
            }

            if ($filterResult === static::FILTER_RESULT_SKIP_BUT_SCAN) {
                continue;
            }

            $results[] = $pathObjectBase;
        }

        return $results;
    }

    /**
     * @param string $path
     * @return bool
     * @see https://stackoverflow.com/a/3338133/3359418
     */
    public static function removeRecursive(string $path): bool
    {
        if (!file_exists($path)) {
            return true;
        }

        if (is_file($path)) {
            return unlink($path);
        }

        if (is_link($path)) {
            // Removes symlink on windows and unix systems
            $unlink = @unlink($path);

            if ($unlink === true || !file_exists($path)) {
                return $unlink;
            }

            return rmdir($path);
        }

        $result = true;

        foreach (scandir($path, SCANDIR_SORT_NONE) as $object) {
            if ($object === '.' || $object === '..') {
                continue;
            }

            if (!static::removeRecursive($path . DIRECTORY_SEPARATOR . $object)) {
                $result = false;
            }
        }

        rmdir($path);

        return $result;
    }

    public static function relativePath(
        string $path,
        string $relativeTo,
        ?string $pathSeperator = null,
        ?string $default = null
    ): ?string
    {
        if (strpos($path, $relativeTo) !== 0) {
            return $default;
        }

        $relPath = substr($path, \strlen($relativeTo));

        if ($pathSeperator !== null) {
            $relPath = str_replace(['\\', '/'], [$pathSeperator, $pathSeperator], $relPath);

            if (strpos($relPath, $pathSeperator) !== 0) {
                $relPath = $pathSeperator . $relPath;
            }

            $relPath = '.' . $relPath;
        }

        return $relPath;
    }

    public static function mkdirRecursive(string $path): bool
    {
        return mkdir($path, 0777, true);
    }

    public static function normalize(string $path, bool $trim = true, string $ds = DIRECTORY_SEPARATOR): string
    {
        $path = str_replace(['\\', '/'], [$ds, $ds], $path);

        $doubleDs = $ds . $ds;

        while (strpos($path, $doubleDs) !== false) {
            $path = str_replace($doubleDs, $ds, $path);
        }

        $pathParts = explode($ds, $path);

        $pathParts = array_filter($pathParts, function (string $part) {
            return $part !== '.';
        });

        $result = [];

        foreach ($pathParts as $part) {
            if ($part !== '..') {
                $result[] = $part;
                continue;
            }

            array_pop($result);
        }

        if ($trim) {
            while (end($result) === '') {
                array_pop($result);
            }
        }

        if (\count($result) < 2) {
            // To be cross platform compatible, normalization is only supported with two or more levels
            throw new \RuntimeException("Could not normalize path `{$path}`!");
        }

        return implode($ds, $result);
    }

    public static function symlink(string $from, string $to, bool $normalize = true, bool $throw = false): bool
    {
        try {
            if ($normalize) {
                $from = static::normalize($from);
                $to = static::normalize($to);
            }

            return symlink($from, $to);
        } catch (\Exception $ex) {
            if ($throw) {
                throw new \RuntimeException($ex);
            }

            return false;
        }
    }
}
