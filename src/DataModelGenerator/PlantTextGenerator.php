<?php

namespace Uay\YEntityGeneratorBundle\DataModelGenerator;

use Uay\YEntityGeneratorBundle\Utils\TextUtil;
use function Jawira\PlantUml\encodep;

class PlantTextGenerator
{
    /**
     * @var string
     */
    protected $plantText;

    protected function parseEntity(Entity $entity): array
    {
        $result = [];

        $type = $entity->getType() === Entity::TYPE_ENUM ? 'enum' : 'class';

        $result[] = "{$type} {$entity->getName()} {";

        if ($entity->getType() === Entity::TYPE_ENTITY) {
            $result[] = '  {field}+integer id';
        }

        foreach ($entity->getFields() as $field) {
            $fieldName = $field->getName();

            if ($entity->getType() === Entity::TYPE_ENUM) {
                $fieldName = strtoupper($fieldName);

                $result[] = "  {$fieldName} = {$field->getValue()}";
                continue;
            }

            $fieldType = $field->getType();

            $fieldSize = $field->getSize();
            if ($fieldSize !== null && $fieldSize > 0) {
                $fieldType .= "({$field->getSize()})";
            }

            if ($field->isNullable()) {
                $fieldName .= ' [null]';
            }

            $fieldModifier = $field->getModifier();

            $result[] = "  {field}{$fieldModifier}{$fieldType} {$fieldName}";
        }

        foreach ($entity->getRelations() as $relation) {
            $targetRelation = $relation->getTargetRelation();

            switch ($targetRelation) {
                case EntityRelation::RELATION_MANY:
                    $targetRelation = 999;
                    break;
                case EntityRelation::RELATION_ONE:
                case EntityRelation::RELATION_ENUM:
                    $targetRelation = 1;
                    break;
                default:
                    $targetRelation = (int)$targetRelation;
                    break;
            }

            $name = TextUtil::pluralize($targetRelation, $relation->getTarget());

            $name = lcfirst($name);

            $result[] = "  {method}+{$relation->getTarget()} {$name}";
        }

        $result[] = '}';
        $result[] = '';

        return $result;
    }

    protected function parseRelation(string $relation, string $default = null): string
    {
        if ($relation === 'one' || $relation === 'enum') {
            return '1';
        }

        if ($relation === 'many') {
            return 'n';
        }

        if (is_numeric($relation) || $default === null) {
            return $relation;
        }

        return $default;
    }

    /**
     * @param Entity[] $entities
     */
    public function __construct(array $entities)
    {
        $plantText = [];

        $plantText[] = '@startuml';
        $plantText[] = '';

        $plantText[] = 'title Entities';
        $plantText[] = '';
        $plantText[] = '';

        foreach ($entities as $entity) {
            foreach ($this->parseEntity($entity) as $line) {
                $plantText[] = $line;
            }
        }

        $plantText[] = '';
        $plantText[] = '';

        $plantTextRelations = [];
        foreach ($entities as $entity) {
            foreach ($entity->getRelations() as $relation) {
                $relationId = $relation->generateId();

                $relationSource = $this->parseRelation($relation->getSourceRelation(), '?');
                $relationTarget = $this->parseRelation($relation->getTargetRelation(), '?');

                $members = [
                    $relation->getSource() => $relationSource,
                    $relation->getTarget() => $relationTarget,
                ];
                ksort($members);
                $membersKeys = array_keys($members);

                $relationData = '';
                $relationData .= "{$membersKeys[0]} \"{$members[$membersKeys[0]]}\"";
                $relationData .= ' -up- ';
                $relationData .= "\"{$members[$membersKeys[1]]}\" {$membersKeys[1]}";

                if (isset($plantTextRelations[$relationId]) && $plantTextRelations[$relationId] !== $relationData) {
                    throw new \RuntimeException("Incompatible relation `{$relationData}`!");
                }

                $plantTextRelations[$relationId] = $relationData;
            }
        }
        $plantTextRelations = array_unique($plantTextRelations);
        foreach ($plantTextRelations as $plantTextRelation) {
            $plantText[] = $plantTextRelation;
        }

        $plantText[] = '';
        $plantText[] = '@enduml';

        $this->plantText = implode("\n", $plantText);
    }

    public function write(string $path): void
    {
        file_put_contents($path, $this->plantText);
    }

    public function writeImage(string $path): void
    {
        $plantTextImage = 'http://www.plantuml.com/plantuml/png/' . encodep($this->plantText);

        copy($plantTextImage, $path);
    }
}
