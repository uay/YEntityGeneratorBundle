<?php

namespace Uay\YEntityGeneratorBundle\DataModelGenerator;

class EntityRelation
{
    public const RELATION_ONE = 'one';
    public const RELATION_MANY = 'many';
    public const RELATION_ENUM = 'enum';

    /**
     * @var string
     */
    protected $target = '';

    /**
     * @var string
     */
    protected $targetRelation = self::RELATION_ONE;

    /**
     * @var string
     */
    protected $source = '';

    /**
     * @var string
     */
    protected $sourceRelation = self::RELATION_ONE;

    public function generateId(): string
    {
        $members = [
            $this->getSource() => $this->getSourceRelation(),
            $this->getTarget() => $this->getTargetRelation(),
        ];

        ksort($members);

        $points = [];

        foreach ($members as $member => $relation) {
            $points[] = "{$member} [{$relation}]";
        }

        return implode(' -> ', $points);
    }

    public function getTarget(): string
    {
        return $this->target;
    }

    public function setTarget(string $target): void
    {
        $this->target = $target;
    }

    public function getTargetRelation(): string
    {
        return $this->targetRelation;
    }

    public function setTargetRelation(string $targetRelation): void
    {
        $this->targetRelation = $targetRelation;
    }

    public function getSource(): string
    {
        return $this->source;
    }

    public function setSource(string $source): void
    {
        $this->source = $source;
    }

    public function getSourceRelation(): string
    {
        return $this->sourceRelation;
    }

    public function setSourceRelation(string $sourceRelation): void
    {
        $this->sourceRelation = $sourceRelation;
    }
}
