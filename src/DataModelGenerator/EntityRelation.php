<?php

namespace Uay\YEntityGeneratorBundle\DataModelGenerator;

class EntityRelation
{
    /**
     * @var EntityRelationPoint
     */
    protected $target;

    /**
     * @var EntityRelationPoint
     */
    protected $source;

    public function generateId(): string
    {
        $members = [
            $this->getSource()->getName() => $this->getSource()->__toString(),
            $this->getTarget()->getName() => $this->getTarget()->__toString(),
        ];

        ksort($members);

        return implode(' -> ', $members);
    }

    public function getTarget(): EntityRelationPoint
    {
        return $this->target;
    }

    public function setTarget(EntityRelationPoint $target): void
    {
        $this->target = $target;
    }

    public function getSource(): EntityRelationPoint
    {
        return $this->source;
    }

    public function setSource(EntityRelationPoint $source): void
    {
        $this->source = $source;
    }
}
