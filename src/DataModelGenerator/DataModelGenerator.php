<?php

namespace Uay\YEntityGeneratorBundle\DataModelGenerator;

use Uay\YEntityGeneratorBundle\Make\EntityClass;
use Uay\YEntityGeneratorBundle\Make\MakeFactory;
use Uay\YEntityGeneratorBundle\Make\EntityClassProperty;
use Uay\YEntityGeneratorBundle\Utils\FileUtil;
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
    protected $pathKernelRoot;

    /**
     * @var string
     */
    protected $pathEntities;

    /**
     * @var Entity[]
     */
    protected $entities = [];

    public function __construct(InputModel $inputModel, string $pathKernelRoot, string $pathEntities)
    {
        $this->inputModel = $inputModel;
        $this->pathKernelRoot = $pathKernelRoot;
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
        $nullable = ($rawField['nullable'] ?? 'false') === 'true';
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
        $rawEntities = $this->inputModel->getEntitiesData();

        $this->entities = $this->parse($rawEntities);
    }

    protected function generatePlantTextUml(): void
    {
        $plantTextGenerator = new PlantTextGenerator($this->entities);

        $plantTextGenerator->write($this->pathEntities . DIRECTORY_SEPARATOR . static::FILE_PLANTTEXT);

        $plantTextGenerator->writeImage($this->pathEntities . DIRECTORY_SEPARATOR . static::FILE_PLANTTEXT_IMAGE);
    }

    protected function generateBaseEntities(): void
    {
        FileUtil::removeRecursive(implode(DIRECTORY_SEPARATOR, [
            $this->pathKernelRoot,
            'src',
            'Entity',
            $this->inputModel->getNamespace('base'),
        ]));

        $relationsToInverse = [];

        foreach ($this->entities as $entity) {
            if ($entity->getType() === Entity::TYPE_ENUM) {
                $class = new EntityClass($entity->getName(), '\\' . $this->inputModel->getNamespace('enum'));

                $class->setModifiers([
                    'abstract',
                ]);

                foreach ($entity->getFields() as $field) {
                    // TODO: also support strings, etc.
                    $property = new EntityClassProperty($field->getName(), 'int', $field->getValue(), []);

                    $property->setConstant(true);

                    $class->addProperty($property);
                }

                $factory = new MakeFactory($this->pathKernelRoot, $this->inputModel->getNamespace('app'), $class);

                $factory->make(true);

                continue;
            }

            if ($entity->getType() !== Entity::TYPE_ENTITY) {
                throw new \RuntimeException("Unexpected entity type `{$entity->getType()}`!");
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

            $appNamespace = $this->inputModel->getNamespace('app');
            $entityNamespace = $appNamespace . '\\' . $this->inputModel->getNamespace('entity');
            $enumNamespace = $appNamespace . '\\' . $this->inputModel->getNamespace('enum');

            foreach ($entity->getFields() as $field) {
                $fieldType = $field->getType();

                if ($fieldType === EntityField::TYPE_ENUM) {
                    $fieldType = $field->getValue();

                    $ormColumnData = [
                        // TODO: also support strings, etc.
                        'type' => '"integer"',
                    ];

                    $fieldTypeImport = $enumNamespace . '\\' . $fieldType;
                    if (!isset($imports[$fieldType])) {
                        $imports[$fieldType] = $fieldTypeImport;
                    } elseif ($imports[$fieldType] !== $fieldTypeImport) {
                        throw new \RuntimeException('Import conflict!');
                    }
                } else {
                    $ormColumnData = $field->getRawData() ?? [];

                    $ormColumnData['type'] = json_encode($fieldType);
                }

                if ($field->isNullable()) {
                    $ormColumnData['nullable'] = 'true';
                }

                foreach ($ormColumnData as $key => $value) {
                    if (!\is_bool($value)) {
                        continue;
                    }

                    $ormColumnData[$key] = $value ? 'true' : 'false';
                }

                $phpDocType = static::TYPE_MAPPING[$fieldType] ?? $fieldType;

                if ($field->isNullable()) {
                    $phpDocType .= '|null';
                }

                $ormColumnData = array_map(function (string $key, string $value) {
                    return "{$key}={$value}";
                }, array_keys($ormColumnData), $ormColumnData);

                $property = new EntityClassProperty($field->getName(), $phpDocType, null, [
                    '@ORM\Column(' . implode(', ', $ormColumnData) . ')',
                ]);

                $properties[$property->getName()] = $property;
            }

            foreach ($entity->getRelations() as $relation) {
                $target = $relation->getTarget();
                $targetEntity = $target->getEntity();
                $targetName = $target->getName();
                $targetRelation = $relation->getTarget()->getRelation();
                $source = $relation->getSource();
                $sourceName = $source->getName();
                $sourceRelation = $source->getRelation();

                $targetNamespace = $targetRelation === EntityRelationPoint::RELATION_ENUM
                    ? $enumNamespace
                    : $entityNamespace;

                $targetEntityImport = $targetNamespace . '\\' . $targetEntity;
                if (!isset($imports[$targetEntity])) {
                    $imports[$targetEntity] = $targetEntityImport;
                } elseif ($imports[$targetEntity] !== $targetEntityImport) {
                    throw new \RuntimeException('Import conflict!');
                }

                if ($targetRelation === EntityRelationPoint::RELATION_ENUM) {
                    $property = new EntityClassProperty($targetName, 'int|null', null, [
                        '@ORM\Column(type="integer", nullable=true)',
                        "@see {$targetEntity}",
                    ]);

                    $properties[$property->getName()] = $property;
                    continue;
                }

                $ormRelation = ucfirst($sourceRelation) . 'To' . ucfirst($targetRelation);

                $ormColumnData = [
                    "targetEntity=\"{$targetEntity}\"",
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
                $ormColumnData[] = "{$mappingType}=\"{$sourceName}\"";

                $type = $targetEntity;

                if ($targetRelation === EntityRelationPoint::RELATION_MANY) {
                    $type .= '[]|Collection';
                }

                $defaultValue = 'null';

                if ($targetRelation === EntityRelationPoint::RELATION_MANY) {
                    $defaultValue = 'new ArrayCollection()';
                }

                $property = new EntityClassProperty($targetName, $type, $defaultValue, [
                    "@ORM\\$ormRelation(" . implode(', ', $ormColumnData) . ')',
                ]);

                $properties[$property->getName()] = $property;
            }

            $basePath = '\\' . $this->inputModel->getNamespace('entity')
                . '\\' . $this->inputModel->getNamespace('base');
            $class = new EntityClass($entity->getName() . $this->inputModel->getClassPostfix('entity'), $basePath, $imports);

            foreach ($properties as $property) {
                $class->addProperty($property);
            }

            $factory = new MakeFactory($this->pathKernelRoot, $appNamespace, $class);

            $factory->make();
        }
    }

    protected function generateMissingEntities(): void
    {
        $appNamespace = $this->inputModel->getNamespace('app');
        $repositoryNamespace = $appNamespace . '\\' . $this->inputModel->getNamespace('repository');
        $entityNamespace = $appNamespace . '\\' . $this->inputModel->getNamespace('entity');
        $entityBaseNamespace = $entityNamespace . '\\' . $this->inputModel->getNamespace('base');

        foreach ($this->entities as $entity) {
            if ($entity->getType() !== Entity::TYPE_ENTITY) {
                continue;
            }

            $className = $entityNamespace . '\\' . $entity->getName();
            $classNameBase = $entityBaseNamespace . '\\' . $entity->getName();
            $repository = $entity->getName() . $this->inputModel->getClassPostfix('repository');
            $repositoryName = $repositoryNamespace . '\\' . $repository;

            if (!class_exists($repositoryName)) {
                $class = new EntityClass($repository, '\\' . $this->inputModel->getNamespace('repository'), [
                    ServiceEntityRepository::class,
                    RegistryInterface::class,
                    $className,
                ]);

                $class->setModifiers([]);
                $class->setExtends('ServiceEntityRepository');

                $class->setBody(MakeFactory::renderArrayAsString([
                    'public function __construct(RegistryInterface $registry)',
                    '{',
                    [
                        "parent::__construct(\$registry, {$entity->getName()}::class);",
                    ],
                    '}',
                ]));

                $factory = new MakeFactory($this->pathKernelRoot, $appNamespace, $class);

                $factory->make(false);
            }

            if (!class_exists($className)) {
                $imports = [
                    'Doctrine\ORM\Mapping as ORM',
                ];

                $classPostfix = $this->inputModel->getClassPostfix('entity');
                $generatedClassName = $entity->getName() . $classPostfix;
                $aliasClassName = $entity->getName() . 'Generated';

                $imports[] = $classNameBase . $classPostfix .
                    (($aliasClassName !== $generatedClassName) ? " as {$aliasClassName}" : '');

                $class = new EntityClass($entity->getName(), '\\' . $this->inputModel->getNamespace('entity'), $imports, [
                    "@ORM\Entity(repositoryClass=\"{$repositoryName}\")",
                ]);

                $class->setModifiers([]);
                $class->setExtends($aliasClassName);

                $factory = new MakeFactory($this->pathKernelRoot, $appNamespace, $class);

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
