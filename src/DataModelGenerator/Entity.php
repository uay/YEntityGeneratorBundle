<?php

namespace Uay\YEntityGeneratorBundle\DataModelGenerator;

class Entity
{
    public const TYPE_ENTITY = 'entity';
    public const TYPE_ENUM = 'enum';

    /**
     * @var string
     */
    protected $name;

    /**
     * @var string
     */
    protected $type;

    /**
     * @var EntityField[]
     */
    protected $fields = [];

    /**
     * @var EntityRelation[]
     */
    protected $relations = [];

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

    /**
     * @return EntityField[]
     */
    public function getFields(): array
    {
        return $this->fields;
    }

    /**
     * @param EntityField[] $fields
     */
    public function setFields(array $fields): void
    {
        $this->fields = $fields;
    }

    /**
     * @return EntityRelation[]
     */
    public function getRelations(): array
    {
        return $this->relations;
    }

    /**
     * @param EntityRelation[] $relations
     */
    public function setRelations(array $relations): void
    {
        $this->relations = $relations;
    }
}
