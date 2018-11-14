<?php

namespace Uay\YEntityGeneratorBundle\DataModelGenerator;

class EntityRelationPoint
{
    public const RELATION_ONE = 'one';
    public const RELATION_MANY = 'many';
    public const RELATION_ENUM = 'enum';

    /**
     * @var string
     */
    protected $entity = '';

    /**
     * @var string
     */
    protected $name = '';

    /**
     * @var string
     */
    protected $relation = self::RELATION_ONE;

    public function __toString(): string
    {
        return "{$this->getEntity()} {$this->getName()} [{$this->getRelation()}]";
    }

    /**
     * @param EntityRelationPoint|static $input
     */
    public function inverse(EntityRelationPoint $input): void
    {
        $properties = [
            'name',
            'entity',
            'relation',
        ];

        foreach ($properties as $property) {
            $property = ucfirst($property);
            $this->{"set$property"}($input->{"get$property"}());
        }
    }

    public function getEntity(): string
    {
        return $this->entity;
    }

    public function setEntity(string $entity): void
    {
        $this->entity = $entity;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getRelation(): string
    {
        return $this->relation;
    }

    public function setRelation(string $relation): void
    {
        $this->relation = $relation;
    }
}
