<?php

namespace Uay\YEntityGeneratorBundle\DataModelGenerator;

use Uay\YEntityGeneratorBundle\Make\EntityClass;
use Uay\YEntityGeneratorBundle\Make\MakeFactory;
use Uay\YEntityGeneratorBundle\Make\EntityClassProperty;
use Uay\YEntityGeneratorBundle\Utils\FileUtil;
use Uay\YEntityGeneratorBundle\Utils\TextUtil;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Bridge\Doctrine\RegistryInterface;

class DataModelGenerator
{
    public const DIRECTORY_GENERATED = 'generated' . DIRECTORY_SEPARATOR;
    public const FILE_PLANTTEXT = self::DIRECTORY_GENERATED . 'entities.txt';
    public const FILE_PLANTTEXT_IMAGE = self::DIRECTORY_GENERATED . 'entities.png';

    public const TYPE_MAPPING = [
        'datetime' => \DateTime::class,
        'integer' => 'int',
        'boolean' => 'bool',
        'decimal' => 'float',
    ];

    /**
     * @var InputModel
     */
    protected $inputModel;

    /**
     * @var string
     */
    protected $pathApplication;

    /**
     * @var string
     */
    protected $pathEntities;

    /**
     * @var Entity[]
     */
    protected $entities = [];

    public function __construct(InputModel $inputModel, string $pathApplication, string $pathEntities)
    {
        $this->inputModel = $inputModel;
        $this->pathApplication = $pathApplication;
        $this->pathEntities = $pathEntities;
    }

    protected static function checkPropertyOrThrow(array &$input, string $property): void
    {
        if (!isset($input[$property])) {
            throw new \RuntimeException("The property `$property` is required but missing!");
        }
    }

    protected function parseEntityRelation(string $sourceEntity, string $fieldName, array $rawField): ?EntityRelation
    {
        if (!isset($rawField['relation'])) {
            return null;
        }

        $result = new EntityRelation();

        $source = new EntityRelationPoint();
        $source->setEntity($sourceEntity);
        $result->setSource($source);

        static::checkPropertyOrThrow($rawField, 'target');

        $target = new EntityRelationPoint();
        $target->setEntity($rawField['target']);
        $target->setName($fieldName);
        $target->setRelation($rawField['relation']);
        $result->setTarget($target);

        return $result;
    }

    protected function parseEntityField(string $fieldName, array $rawField): EntityField
    {
        static::checkPropertyOrThrow($rawField, 'type');
        $type = $rawField['type'];

        $size = $rawField['size'] ?? null;
        $nullable = ($rawField['size'] ?? 'false') === 'true';
        $modifier = $rawField['modifier'] ?? EntityField::MODIFIER_PROTECTED;
        $value = $rawField['value'] ?? $rawField['target'] ?? null;

        $result = new EntityField();

        $result->setName($fieldName);
        $result->setType($type);
        $result->setNullable($nullable);
        $result->setSize($size);
        $result->setModifier($modifier);
        $result->setValue($value);
        $result->setRawData($rawField);

        return $result;
    }

    /**
     * @param string[] $rawEntities
     * @return Entity[]
     */
    protected function parse(array $rawEntities): array
    {
        /** @var Entity[] $entities */
        $entities = [];

        foreach ($rawEntities as $entityName => $rawFields) {

            /** @var EntityField[] $fields */
            $fields = [];

            /** @var EntityRelation[] $relations */
            $relations = [];

            $isEnum = true;

            foreach ($rawFields as $fieldName => $rawField) {
                if (!\is_array($rawField)) {
                    if (!$isEnum) {
                        throw new \RuntimeException('Invalid configuration, enum and entity mixes are not allowed!');
                    }

                    $field = new EntityField();

                    $value = $rawField;
                    if (\is_string($value)) {
                        $type = EntityField::TYPE_STRING;
                    } else if (\is_numeric($value)) {
                        $type = EntityField::TYPE_INTEGER;
                    } else if (\is_bool($value)) {
                        $type = EntityField::TYPE_BOOLEAN;
                    } else {
                        $type = EntityField::TYPE_UNKNOWN;
                    }

                    $field->setName($fieldName);
                    $field->setType($type);
                    $field->setModifier(EntityField::MODIFIER_PUBLIC);
                    $field->setValue(json_encode($value));

                    $fields[$field->getName()] = $field;
                    continue;
                }

                $isEnum = false;

                $relation = $this->parseEntityRelation($entityName, $fieldName, $rawField);
                if ($relation !== null) {
                    $relations[$relation->getTarget()->getName()] = $relation;
                    continue;
                }

                $field = $this->parseEntityField($fieldName, $rawField);

                $fields[$field->getName()] = $field;
            }

            $type = $isEnum ? Entity::TYPE_ENUM : Entity::TYPE_ENTITY;

            $entity = new Entity();

            $entity->setName($entityName);
            $entity->setType($type);
            $entity->setFields($fields);
            $entity->setRelations($relations);

            $entities[$entity->getName()] = $entity;
        }

        foreach ($entities as $entitySource) {
            foreach ($entitySource->getRelations() as $relation) {
                $relationSourceEntity = $entitySource->getName();
                $relationTargetEntity = $relation->getTarget()->getEntity();

                $entityTarget = $entities[$relationTargetEntity];
                $entityTargetRelations = array_filter($entityTarget->getRelations(),
                    function (EntityRelation $relation) use ($relationSourceEntity): bool {
                        return $relation->getTarget()->getEntity() === $relationSourceEntity;
                    });

                $entityTargetRelationsCount = \count($entityTargetRelations);

                if ($entityTargetRelationsCount < 1) {
                    throw new \RuntimeException("Missing relation `$relationTargetEntity` -> `$relationSourceEntity`!");
                }

                if ($entityTargetRelationsCount > 1) {
                    throw new \RuntimeException('Not implemented yet: inversedBy currently not supported!');
                }

                $entityTargetRelation = reset($entityTargetRelations);

                $relation->getSource()->inverse($entityTargetRelation->getTarget());
            }
        }

        return $entities;
    }

    public function read(): void
    {
        $rawEntities = $this->inputModel->getRawEntities();

        $this->entities = $this->parse($rawEntities);
    }

    protected function generatePlantTextUml(): void
    {
        $plantTextGenerator = new PlantTextGenerator($this->entities);

        $plantTextGenerator->write($this->pathEntities . DIRECTORY_SEPARATOR . static::FILE_PLANTTEXT);

        $plantTextGenerator->writeImage($this->pathEntities . DIRECTORY_SEPARATOR . static::FILE_PLANTTEXT_IMAGE);
    }

    protected static function getFieldName(string $entity, string $relation): string
    {
        if ($relation === EntityRelation::RELATION_MANY) {
            $fieldSize = 999;
        } else {
            $fieldSize = 1;
        }

        return TextUtil::pluralize($fieldSize, lcfirst($entity));
    }

    protected function generateBaseEntities(): void
    {
        FileUtil::removeRecursive(implode(DIRECTORY_SEPARATOR, [
            $this->pathApplication,
            'src',
            'Entity',
            'Base',
        ]));

        $relationsToInverse = [];

        foreach ($this->entities as $entity) {
            if ($entity->getType() === Entity::TYPE_ENUM) {
                $class = new EntityClass($entity->getName());

                $class->setBasePath('\\Enum');
                $class->setModifiers([
                    'abstract',
                ]);

                foreach ($entity->getFields() as $field) {
                    $property = new EntityClassProperty($field->getName(), 'int', $field->getValue(), []);

                    $property->setConstant(true);

                    $class->addProperty($property);
                }

                $factory = new MakeFactory($class);

                $factory->make(true);

                continue;
            }

            if ($entity->getType() !== Entity::TYPE_ENTITY) {
                throw new \RuntimeException("Unexpected type `{$entity->getType()}`!");
            }

            $imports = [];

            if (\count($entity->getRelations()) > 0) {
                $imports['ArrayCollection'] = ArrayCollection::class;
                $imports['Collection'] = Collection::class;
            }

            $imports['ORM'] = 'Doctrine\ORM\Mapping as ORM';

            /** @var EntityClassProperty[] $properties */
            $properties = [];

            $properties[] = new EntityClassProperty('id', 'int', null, [
                '@ORM\Id()',
                '@ORM\GeneratedValue()',
                '@ORM\Column(type="integer")',
            ]);

            foreach ($entity->getFields() as $field) {
                $type = $field->getType();

                $ormColumnData = [
                    "type=\"{$type}\"",
                ];

                if ($field->getSize() !== null) {
                    $ormColumnData[] = "length={$field->getSize()}";
                }

                if ($type === 'decimal') {
                    $ormColumnData[] = 'precision=' . ($field->getSize() ?: 20);
                    $ormColumnData[] = 'scale=10';
                }

                if ($field->isNullable()) {
                    $ormColumnData[] = 'nullable=true';
                }

                if (isset(static::TYPE_MAPPING[$type])) {
                    $type = static::TYPE_MAPPING[$type];
                }

                if ($field->isNullable()) {
                    $type .= '|null';
                }

                $property = new EntityClassProperty($field->getName(), $type, null, [
                    '@ORM\Column(' . implode(', ', $ormColumnData) . ')',
                ]);

                $properties[$property->getName()] = $property;
            }

            $appNamespace = MakeFactory::getAppNamespace();
            $entityNamespace = $appNamespace . '\\Entity\\Base';
            $enumNamespace = $appNamespace . '\\Enum';
            foreach ($entity->getRelations() as $relation) {
                $targetNamespace = $relation->getTargetRelation() === EntityRelation::RELATION_ENUM
                    ? $enumNamespace
                    : $entityNamespace;
                $target = $relation->getTarget();

                if (!isset($imports[$target])) {
                    $imports[$target] = $targetNamespace . '\\' . $target;
                } elseif ($imports[$target] !== $targetNamespace . '\\' . $target) {
                    throw new \RuntimeException('Import conflict!');
                }

                $fieldNameSource = static::getFieldName($relation->getSource(), $relation->getSourceRelation());
                $fieldNameTarget = static::getFieldName($target, $relation->getTargetRelation());

                if ($relation->getTargetRelation() === EntityRelation::RELATION_ENUM) {
                    $property = new EntityClassProperty($fieldNameTarget, 'int|null', null, [
                        '@ORM\Column(type="integer", nullable=true)',
                        "@see {$target}",
                    ]);

                    $properties[$property->getName()] = $property;
                    continue;
                }

                $ormRelation = ucfirst($relation->getSourceRelation()) . 'To' . ucfirst($relation->getTargetRelation());

                $ormColumnData = [
                    "targetEntity=\"{$target}\"",
                ];

                switch ($ormRelation) {
                    case 'ManyToMany':
                        $rel = $relation->generateId();
                        if (!isset($relationsToInverse[$rel])) {
                            $relationsToInverse[$rel] = $relation;
                            $mappingType = 'mappedBy';
                        } else {
                            $mappingType = 'inversedBy';
                        }
                        break;
                    case 'ManyToOne':
                        $mappingType = 'inversedBy';
                        break;
                    case 'OneToMany':
                        $mappingType = 'mappedBy';
                        break;
                    default:
                        throw new \RuntimeException("Unexpected relation '{$ormRelation}'!");
                }
                $ormColumnData[] = "{$mappingType}=\"{$fieldNameSource}\"";

                $type = $target;

                if ($relation->getTargetRelation() === EntityRelation::RELATION_MANY) {
                    $type .= '[]|Collection';
                }

                $defaultValue = 'null';

                if ($relation->getTargetRelation() === EntityRelation::RELATION_MANY) {
                    $defaultValue = 'new ArrayCollection()';
                }

                $property = new EntityClassProperty($fieldNameTarget, $type, $defaultValue, [
                    "@ORM\\$ormRelation(" . implode(', ', $ormColumnData) . ')',
                ]);

                $properties[$property->getName()] = $property;
            }

            $class = new EntityClass($entity->getName(), $imports);

            foreach ($properties as $property) {
                $class->addProperty($property);
            }

            $factory = new MakeFactory($class);

            $factory->make();
        }
    }

    protected function generateMissingEntities(): void
    {
        $appNamespace = MakeFactory::getAppNamespace();
        $repositoryNamespace = $appNamespace . '\\Repository';
        $entityNamespace = $appNamespace . '\\Entity';
        $entityBaseNamespace = $entityNamespace . '\\Base';

        foreach ($this->entities as $entity) {
            if ($entity->getType() !== Entity::TYPE_ENTITY) {
                continue;
            }

            $className = $entityNamespace . '\\' . $entity->getName();
            $classNameBase = $entityBaseNamespace . '\\' . $entity->getName();
            $repository = $entity->getName() . 'Repository';
            $repositoryName = $repositoryNamespace . '\\' . $repository;

            if (!class_exists($repositoryName)) {
                $class = new EntityClass($repository, [
                    ServiceEntityRepository::class,
                    RegistryInterface::class,
                    $className,
                ]);

                $class->setBasePath('\\Repository');
                $class->setModifiers([]);
                $class->setExtends('ServiceEntityRepository');

                $factory = new MakeFactory($class);

                $factory->make(false);
            }

            if (!class_exists($className)) {
                $class = new EntityClass($entity->getName(), [
                    $classNameBase . " as {$entity->getName()}Base",
                    'Doctrine\ORM\Mapping as ORM',
                ], [
                    "@ORM\Entity(repositoryClass=\"{$repositoryName}\")",
                ]);

                $class->setBasePath('\\Entity');
                $class->setModifiers([]);
                $class->setExtends("{$entity->getName()}Base");

                $factory = new MakeFactory($class);

                $factory->make(false);
            }
        }
    }

    public function generate(): void
    {
        $this->generatePlantTextUml();

        $this->generateBaseEntities();

        $this->generateMissingEntities();
    }
}
