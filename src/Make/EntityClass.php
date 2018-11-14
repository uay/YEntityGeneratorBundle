<?php

namespace Uay\YEntityGeneratorBundle\Make;

class EntityClass
{
    /**
     * @var string
     */
    protected $name;

    /**
     * @var string[]
     */
    protected $imports;

    /**
     * @var string[]
     */
    protected $annotations;

    /**
     * @var EntityClassProperty[]
     */
    protected $properties;

    /**
     * @var string
     */
    protected $basePath;

    /**
     * @var string[]
     */
    protected $modifiers;

    /**
     * @var string|null
     */
    protected $extends;

    /**
     * @param string $name
     * @param string $basePath
     * @param string[] $imports
     * @param string[] $annotations
     */
    public function __construct(string $name, string $basePath, array $imports = [], array $annotations = [])
    {
        $this->name = $name;
        $this->imports = $imports;
        $this->annotations = $annotations;
        $this->properties = [];
        $this->basePath = $basePath;
        $this->modifiers = [
            'abstract',
        ];
        $this->extends = null;
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return string[]
     */
    public function getImports(): array
    {
        return $this->imports;
    }

    /**
     * @return string[]
     */
    public function getAnnotations(): array
    {
        return $this->annotations;
    }

    /**
     * @return EntityClassProperty[]
     */
    public function getProperties(): array
    {
        return $this->properties;
    }

    public function addProperty(EntityClassProperty $property): void
    {
        $this->properties[$property->getName()] = $property;
    }

    public function getBasePath(): string
    {
        return $this->basePath;
    }

    /**
     * @return string[]
     */
    public function getModifiers(): array
    {
        return $this->modifiers;
    }

    /**
     * @param string[] $modifiers
     */
    public function setModifiers(array $modifiers): void
    {
        $this->modifiers = $modifiers;
    }

    public function getExtends(): ?string
    {
        return $this->extends;
    }

    public function setExtends(?string $extends): void
    {
        $this->extends = $extends;
    }
}
