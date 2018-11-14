<?php

namespace Uay\YEntityGeneratorBundle\DataModelGenerator;

use Uay\YEntityGeneratorBundle\Utils\FileUtil;
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

        $plantTypeField = 'field';
        $plantTypeMethod = 'method';
        $plantTypeExternal = $plantTypeMethod;

        if ($entity->getType() === Entity::TYPE_ENTITY) {
            $result[] = "  {{$plantTypeField}}+integer id";
        }

        foreach ($entity->getFields() as $field) {
            $plantType = $plantTypeField;

            $fieldName = $field->getName();

            if ($entity->getType() === Entity::TYPE_ENUM) {
                $fieldName = strtoupper($fieldName);

                $value = json_decode($field->getValue(), true);
                $result[] = "  {$fieldName} = {$value}";
                continue;
            }

            $fieldType = $field->getType();

            if ($fieldType === EntityField::TYPE_ENUM) {
                $fieldType = $field->getValue();
                $plantType = $plantTypeExternal;
            }

            $fieldSize = $field->getSize();
            if ($fieldSize !== null && $fieldSize > 0) {
                $fieldType .= "({$field->getSize()})";
            }

            if ($field->isNullable()) {
                $fieldName .= ' [null]';
            }

            $fieldModifier = $field->getModifier();

            $result[] = "  {{$plantType}}{$fieldModifier}{$fieldType} {$fieldName}";
        }

        foreach ($entity->getRelations() as $relation) {
            $target = $relation->getTarget();

            $modifier = EntityField::MODIFIER_PROTECTED;
            $type = $target->getEntity() . ($target->getRelation() === EntityRelationPoint::RELATION_MANY ? '[]' : '');

            $result[] = "  {{$plantTypeExternal}}{$modifier}{$type} {$target->getName()}";
        }

        $result[] = '}';
        $result[] = '';

        return $result;
    }

    protected function parseRelation(string $relation, string $default = null): string
    {
        if ($relation === EntityRelationPoint::RELATION_ONE || $relation === EntityRelationPoint::RELATION_ENUM) {
            return '1';
        }

        if ($relation === EntityRelationPoint::RELATION_MANY) {
            return 'n';
        }

        if (is_numeric($relation) || $default === null) {
            return $relation;
        }

        return $default;
    }

    /**
     * @param string[] $members
     * @return string
     */
    protected function generatePlantRelation(array $members): string
    {
        ksort($members);
        $membersKeys = array_keys($members);

        $members = array_map(function (string $relation): string {
            return $this->parseRelation($relation, '?');
        }, $members);

        $relationData = [
            "{$membersKeys[0]} \"{$members[$membersKeys[0]]}\"",
            ' -up- ',
            "\"{$members[$membersKeys[1]]}\" {$membersKeys[1]}",
        ];
        return implode($relationData);
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

                /** @var EntityRelationPoint[] $points */
                $points = [
                    $relation->getSource(),
                    $relation->getTarget(),
                ];

                $members = [];
                foreach ($points as $point) {
                    $members[$point->getEntity()] = $point->getRelation();
                }

                $relationData = $this->generatePlantRelation($members);

                if (isset($plantTextRelations[$relationId]) && $plantTextRelations[$relationId] !== $relationData) {
                    throw new \RuntimeException("Incompatible relation `{$relationData}`!");
                }

                $plantTextRelations[$relationId] = $relationData;
            }

            foreach ($entity->getFields() as $field) {
                $fieldType = $field->getType();
                if ($fieldType !== EntityField::TYPE_ENUM) {
                    continue;
                }

                $entityName = $entity->getName();
                $fieldValue = $field->getValue();

                $relationId = "{$entityName}.{$fieldValue} [{$fieldType}]";
                $plantTextRelations[$relationId] = $this->generatePlantRelation([
                    $entityName => EntityRelationPoint::RELATION_MANY,
                    $fieldValue => EntityRelationPoint::RELATION_ONE,
                ]);
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
        $pathParent = \dirname($path);

        if (!file_exists($pathParent)) {
            FileUtil::mkdirRecursive($pathParent);
        }

        file_put_contents($path, $this->plantText);
    }

    public function writeImage(string $path): void
    {
        $plantTextImage = 'http://www.plantuml.com/plantuml/png/' . encodep($this->plantText);

        copy($plantTextImage, $path);
    }
}
