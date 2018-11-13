<?php

namespace Uay\YEntityGeneratorBundle\DataModelGenerator;

class EntityField
{
    public const MODIFIER_PUBLIC = '+';
    public const MODIFIER_PROTECTED = '#';
    public const MODIFIER_PRIVATE = '-';

    /**
     * @var string
     */
    protected $name = '';

    /**
     * @var string
     */
    protected $type = '';

    /**
     * @var bool
     */
    protected $nullable = false;

    /**
     * @var null|int
     */
    protected $size;

    /**
     * @var string
     */
    protected $modifier = self::MODIFIER_PUBLIC;

    /**
     * @var null|string
     */
    protected $value;

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): void
    {
        $this->type = $type;
    }

    public function isNullable(): bool
    {
        return $this->nullable;
    }

    public function setNullable(bool $nullable): void
    {
        $this->nullable = $nullable;
    }

    public function getSize(): ?int
    {
        return $this->size;
    }

    public function setSize(?int $size): void
    {
        $this->size = $size;
    }

    public function getModifier(): string
    {
        return $this->modifier;
    }

    public function setModifier(string $modifier): void
    {
        $this->modifier = $modifier;
    }

    public function getValue(): ?string
    {
        return $this->value;
    }

    public function setValue(?string $value): void
    {
        $this->value = $value;
    }
}
