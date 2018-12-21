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
    protected const BOOL_TRUE = 'true';
    protected const BOOL_FALSE = 'false';

    protected const PHP_TYPE_INT = 'int';
    protected const PHP_TYPE_STRING = 'string';
    protected const PHP_TYPE_FLOAT = 'float';
    protected const PHP_TYPE_RESOURCE = 'resource';
    protected const PHP_TYPE_BOOL = 'bool';
    protected const PHP_TYPE_DATETIME = '\\' . \DateTime::class;
    protected const PHP_TYPE_DATETIME_IMMUTABLE = '\\' . \DateTimeImmutable::class;
    protected const PHP_TYPE_DATEINTERVAL = '\\' . \DateInterval::class;
    protected const PHP_TYPE_ARRAY = 'array';
    protected const PHP_TYPE_OBJECT = 'object';

    protected const TYPE_MAPPING = [
        // https://www.doctrine-project.org/projects/doctrine-dbal/en/2.8/reference/types.html
        'smallint' => self::PHP_TYPE_INT,
        'integer' => self::PHP_TYPE_INT,
        'bigint' => self::PHP_TYPE_STRING,
        'decimal' => self::PHP_TYPE_STRING,
        'float' => self::PHP_TYPE_FLOAT,
        'string' => self::PHP_TYPE_STRING,
        'text' => self::PHP_TYPE_STRING,
        'guid' => self::PHP_TYPE_STRING,
        'binary' => self::PHP_TYPE_RESOURCE,
        'blob' => self::PHP_TYPE_RESOURCE,
        'boolean' => self::PHP_TYPE_BOOL,
        'date' => self::PHP_TYPE_DATETIME,
        'date_immutable' => self::PHP_TYPE_DATETIME_IMMUTABLE,
        'datetime' => self::PHP_TYPE_DATETIME,
        'datetime_immutable' => self::PHP_TYPE_DATETIME_IMMUTABLE,
        'datetimetz' => self::PHP_TYPE_DATETIME,
        'datetimetz_immutable' => self::PHP_TYPE_DATETIME_IMMUTABLE,
        'time' => self::PHP_TYPE_DATETIME,
        'time_immutable' => self::PHP_TYPE_DATETIME_IMMUTABLE,
        'dateinterval' => self::PHP_TYPE_DATEINTERVAL,
        'array' => self::PHP_TYPE_ARRAY,
        'simple_array' => self::PHP_TYPE_ARRAY,
        'json' => self::PHP_TYPE_ARRAY,
        'json_array' => self::PHP_TYPE_ARRAY,
        'object' => self::PHP_TYPE_OBJECT,
    ];

    protected const TYPE_DEFAULT_VALUE = '0';
    protected const TYPE_DEFAULT_VALUES_MAPPING = [
        self::PHP_TYPE_INT => '0',
        self::PHP_TYPE_STRING => '\'\'',
        self::PHP_TYPE_FLOAT => '0.0',
        self::PHP_TYPE_RESOURCE => 'null',
        self::PHP_TYPE_BOOL => self::BOOL_FALSE,
        self::PHP_TYPE_DATETIME => 'new ' . self::PHP_TYPE_DATETIME . '()',
        self::PHP_TYPE_DATETIME_IMMUTABLE => 'new ' . self::PHP_TYPE_DATETIME_IMMUTABLE . '()',
        self::PHP_TYPE_DATEINTERVAL => 'new ' . self::PHP_TYPE_DATEINTERVAL . '()',
        self::PHP_TYPE_ARRAY => '[]',
        self::PHP_TYPE_OBJECT => 'null',
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
        $nullable = ($nullable = $rawField['nullable'] ?? false) || $nullable === static::BOOL_TRUE;
        if (!\is_bool($nullable)) {
            $nullable = false;
        }
        $modifier = $rawField['modifier'] ?? EntityField::MODIFIER_PROTECTED;

        if (isset($rawField['value'])) {
            $value = EntityField::parseValue($rawField['value']);
        } else {
            $value = $rawField['target'] ?? null;
        }

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

                    $field->setName($fieldName);
                    $field->setType(EntityField::parseType($value));
                    $field->setModifier(EntityField::MODIFIER_PUBLIC);
                    $field->setValue(EntityField::parseValue($value));

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
        $plantTextGenerator = new PlantTextGenerator($this->inputModel, $this->entities);

        $plantTextGenerator->write($this->pathEntities . DIRECTORY_SEPARATOR . static::FILE_PLANTTEXT);

        $plantTextGenerator->writeImage($this->pathEntities . DIRECTORY_SEPARATOR . static::FILE_PLANTTEXT_IMAGE);
    }

    protected static function filterOrmColumnData(array &$ormColumnData): array
    {
        if (array_key_exists('value', $ormColumnData)) {
            unset($ormColumnData['value']);
        }

        return $ormColumnData;
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

            foreach ($this->inputModel->getImports() as $importKey => $importValue) {
                $imports[$importKey] = "$importValue as $importKey";
            }

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
                $isEnum = false;

                if ($fieldType === EntityField::TYPE_ENUM) {
                    $isEnum = true;

                    $fieldType = $field->getValue();

                    $ormColumnData = [
                        'type' => 'integer',
                    ];

                    $fieldTypeImport = $enumNamespace . '\\' . $fieldType;
                    if (!isset($imports[$fieldType])) {
                        $imports[$fieldType] = $fieldTypeImport;
                    } elseif ($imports[$fieldType] !== $fieldTypeImport) {
                        throw new \RuntimeException('Import conflict!');
                    }
                } else {
                    $ormColumnData = $field->getRawData() ?? [];

                    $ormColumnData['type'] = $fieldType;
                }

                $ormColumnData = static::filterOrmColumnData($ormColumnData);

                if ($field->isNullable()) {
                    $ormColumnData['nullable'] = true;
                } else {
                    $ormColumnData['nullable'] = false;
                }

                foreach ($ormColumnData as $key => $value) {
                    if (\is_bool($value)) {
                        $ormColumnData[$key] = $value ? static::BOOL_TRUE : static::BOOL_FALSE;

                        continue;
                    }

                    if ($value === static::BOOL_TRUE || $value === static::BOOL_FALSE) {
                        continue;
                    }

                    $ormColumnData[$key] = json_encode($value);
                }

                $phpDocType = static::TYPE_MAPPING[$fieldType] ?? $fieldType;

                if ($field->isNullable()) {
                    $phpDocType .= '|null';
                }

                $ormColumnData = array_map(function (string $key, string $value) {
                    return "{$key}={$value}";
                }, array_keys($ormColumnData), $ormColumnData);

                if ($isEnum) {
                    $fieldRawData = $field->getRawData() ?? [];

                    $defaultValue = $fieldRawData['default'] ?? null;

                    if ($defaultValue === null && !$field->isNullable()) {
                        throw new \RuntimeException('You must provide default value for nullable enum!');
                    }

                    if ($defaultValue !== null) {
                        $defaultValue = "$fieldType::$defaultValue";
                    }
                } else {
                    $defaultValue = $field->getValue();

                    if ($defaultValue === 'null') {
                        $defaultValue = null;
                    }

                    if ($defaultValue === null && !$field->isNullable()) {
                        $defaultValue = static::TYPE_DEFAULT_VALUES_MAPPING[$phpDocType]
                            ?? static::TYPE_DEFAULT_VALUE;
                    }
                }

                $property = new EntityClassProperty($field->getName(), $phpDocType, $defaultValue, [
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

                    // TODO: implement default value and nullable
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

                $phpDocType = $targetEntity;

                if ($targetRelation === EntityRelationPoint::RELATION_MANY) {
                    $phpDocType .= '[]|Collection';
                }

                $defaultValue = 'null';

                if ($targetRelation === EntityRelationPoint::RELATION_MANY) {
                    $defaultValue = 'new ArrayCollection()';
                }

                if ($defaultValue === 'null') {
                    $phpDocType .= '|null';
                }

                $property = new EntityClassProperty($targetName, $phpDocType, $defaultValue, [
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
