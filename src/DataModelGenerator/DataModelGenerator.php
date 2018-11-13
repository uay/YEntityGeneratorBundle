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
    public const FILE_ENTITIES = 'entities.md';
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

    public function __construct(string $pathApplication, string $pathEntities)
    {
        $this->pathApplication = $pathApplication;
        $this->pathEntities = $pathEntities;
    }

    /**
     * @param string $rawContent
     * @return string[]
     */
    protected function parseRawContent(string $rawContent): array
    {
        $rawContent = str_replace("\r\n", "\n", $rawContent);

        return explode("\n\n", $rawContent);
    }

    protected function parseRawEntityRelation(string $rawEntityRelation): ?EntityRelation
    {
        if (($idxRelation = strpos($rawEntityRelation, ' => ')) === false) {
            return null;
        }

        $target = substr($rawEntityRelation, 0, $idxRelation);
        $targetRelation = substr($rawEntityRelation, $idxRelation + \strlen(' => '));

        $result = new EntityRelation();

        $result->setTarget($target);
        $result->setTargetRelation($targetRelation);

        return $result;
    }

    protected function parseRawEntityField(string $rawEntityField): ?EntityField
    {
        if (preg_match_all('~^(?P<name>.*)\\s\\((?P<rawdata>.*)\\)$~',
                $rawEntityField, $fieldMatches, PREG_SET_ORDER
            ) !== 1) {
            return null;
        }

        $rawEntityField = $fieldMatches[0]['rawdata'];

        $rawEntityField = strtolower($rawEntityField);

        $data = explode(' ', $rawEntityField);

        $type = $data[0];
        $nullable = \in_array('null', $data, true);
        $size = null;
        if (\in_array('private', $data, true)) {
            $modifier = EntityField::MODIFIER_PRIVATE;
        } elseif (\in_array('protected', $data, true)) {
            $modifier = EntityField::MODIFIER_PROTECTED;
        } else {
            $modifier = EntityField::MODIFIER_PUBLIC;
        }

        foreach ($data as $entry) {
            if ($entry === $type) {
                continue;
            }

            if (is_numeric($entry)) {
                $size = (int)$entry;
                break;
            }
        }

        $result = new EntityField();

        $result->setName($fieldMatches[0]['name']);
        $result->setType($type);
        $result->setNullable($nullable);
        $result->setSize($size);
        $result->setModifier($modifier);

        return $result;
    }

    /**
     * @param string[] $rawEntities
     * @return Entity[]
     */
    protected function parseRawEntities(array $rawEntities): array
    {
        /** @var Entity[] $entities */
        $entities = [];

        foreach ($rawEntities as $rawEntity) {
            $rawEntity = explode("\n", $rawEntity);

            $nameParts = explode(' ', $rawEntity[0]);
            $name = $nameParts[0];

            $type = \count($nameParts) > 1 && $nameParts[1] === '(enum)' ? Entity::TYPE_ENUM : Entity::TYPE_ENTITY;

            /** @var EntityField[] $fields */
            $fields = [];

            /** @var EntityRelation[] $relations */
            $relations = [];

            for ($i = 1, $iMax = \count($rawEntity); $i < $iMax; $i++) {
                $rawEntityData = substr($rawEntity[$i], 2);

                $relation = $this->parseRawEntityRelation($rawEntityData);
                if ($relation !== null) {
                    $relations[$relation->getTarget()] = $relation;
                    continue;
                }

                if ($type === Entity::TYPE_ENUM) {
                    $rawEntityData = explode(' ', $rawEntityData);

                    $field = new EntityField();

                    $field->setName(strtoupper($rawEntityData[0]));
                    $field->setValue($rawEntityData[1]);

                    $fields[$field->getName()] = $field;
                    continue;
                }

                $field = $this->parseRawEntityField($rawEntityData);

                if ($field === null) {
                    continue;
                }

                $fields[$field->getName()] = $field;
            }

            $entity = new Entity();

            $entity->setName($name);
            $entity->setType($type);
            $entity->setFields($fields);
            $entity->setRelations($relations);

            $entities[$entity->getName()] = $entity;
        }

        foreach ($entities as $entitySource) {
            foreach ($entitySource->getRelations() as $relation) {
                $relationSource = $entitySource->getName();
                $relationTarget = $relation->getTarget();

                $entityTarget = $entities[$relationTarget];
                $entityTargetRelations = $entityTarget->getRelations();

                if (!isset($entityTargetRelations[$relationSource])) {
                    throw new \RuntimeException("Missing relation `{$relationTarget}` -> `{$relationSource}`!");
                }

                $relation->setSource($entityTargetRelations[$relationSource]->getTarget());
                $relation->setSourceRelation($entityTargetRelations[$relationSource]->getTargetRelation());
            }
        }

        return $entities;
    }

    public function read(): void
    {
        $path = realpath($this->pathEntities . DIRECTORY_SEPARATOR . static::FILE_ENTITIES);

        $rawEntities = $this->parseRawContent(file_get_contents($path));

        $this->entities = $this->parseRawEntities($rawEntities);
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
