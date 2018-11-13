<?php

namespace Uay\YEntityGeneratorBundle\Make;

class EntityClassProperty
{
    /**
     * @var string
     */
    protected $name;

    /**
     * @var string
     */
    protected $type;

    /**
     * @var string|null
     */
    protected $default;

    /**
     * @var string[]
     */
    protected $annotations;

    /**
     * @var bool
     */
    protected $constant;

    /**
     * @param string $name
     * @param string $type
     * @param string|null $default
     * @param string[] $annotations
     */
    public function __construct(string $name, string $type, ?string $default, array $annotations)
    {
        $this->name = $name;
        $this->type = $type;
        $this->default = $default;
        $this->annotations = $annotations;
        $this->constant = false;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getDefault(): ?string
    {
        return $this->default;
    }

    /**
     * @return string[]
     */
    public function getAnnotations(): array
    {
        return $this->annotations;
    }

    public function isConstant(): bool
    {
        return $this->constant;
    }

    public function setConstant(bool $constant): void
    {
        $this->constant = $constant;
    }
}
