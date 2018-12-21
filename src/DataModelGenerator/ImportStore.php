<?php

namespace Uay\YEntityGeneratorBundle\DataModelGenerator;

class ImportStore
{
    /**
     * @var string[]
     */
    protected $imports;

    /**
     * @var string[]
     */
    protected $usedImportKeys;

    /**
     * @param string[] $imports
     * @param string[] $usedImportKeys
     */
    public function __construct(array $imports = [], array $usedImportKeys = [])
    {
        $this->imports = $imports;
        $this->usedImportKeys = $usedImportKeys;
    }

    /**
     * @param string $key
     * @param string $import
     * @param bool $require
     * @param bool $overwrite true to overwrite, false to skip
     * @return bool true on new key, false on overwrite or skip
     */
    public function add(string $key, string $import, bool $require = false, bool $overwrite = true): bool
    {
        $newKey = !isset($this->imports[$key]);

        if ($newKey || $overwrite) {
            $this->imports[$key] = $import;
        }

        if ($require) {
            $this->require($key);
        }

        return $newKey;
    }

    public function require(string $key): void
    {
        if (isset($this->usedImportKeys[$key])) {
            return;
        }

        $this->usedImportKeys[$key] = $key;
    }

    /**
     * @return string[]
     */
    public function getRequiredImports(): array
    {
        $result = [];

        foreach ($this->usedImportKeys as $key) {
            $result[$key] = $this->imports[$key];
        }

        return $result;
    }
}
