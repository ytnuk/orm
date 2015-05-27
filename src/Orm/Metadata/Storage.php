<?php

namespace Ytnuk\Orm\Metadata;

use Nextras;
use Nette;

/**
 * Class Storage
 *
 * @package Ytnuk\Orm
 */
final class Storage extends Nextras\Orm\Model\MetadataStorage
{

	/**
	 * @inheritDoc
	 */
	protected static $metadata;

	/**
	 * @var array
	 */
	protected $repositories = [];

	/**
	 * @var Nextras\Orm\Model\IRepositoryLoader
	 */
	private $repositoryLoader;

	/**
	 * @inheritdoc
	 */
	public function __construct(Nette\Caching\IStorage $cacheStorage, array $entityClasses, Nextras\Orm\Model\IRepositoryLoader $repositoryLoader, array $repositories = [])
	{
		$this->repositoryLoader = $repositoryLoader;
		$this->repositories = $repositories;
		$cache = new Nette\Caching\Cache($cacheStorage, 'Nextras.Orm.metadata');
		static::$metadata = $cache->load($entityClasses, function (& $dp) use ($entityClasses, $repositoryLoader) {
			$metadata = $this->parseMetadata($entityClasses, $dp[Nette\Caching\Cache::FILES]);
			$validator = new Nextras\Orm\Entity\Reflection\MetadataValidator();
			$validator->validate($metadata, $repositoryLoader);

			return $metadata;
		});
		new Nextras\Orm\Model\MetadataStorage($cacheStorage, $entityClasses, $repositoryLoader);
		parent::__construct($cacheStorage, $entityClasses, $repositoryLoader);
	}

	/**
	 * @inheritdoc
	 */
	private function parseMetadata($entityList, & $fileDependencies)
	{
		$cache = [];
		$annotationParser = new Nextras\Orm\Entity\Reflection\AnnotationParser;
		foreach ($entityList as $className) {
			$cache[$className] = $annotationParser->parseMetadata($className, $fileDependencies);
		}
		foreach ($cache as $entity => $metadata) {
			foreach ($metadata->getProperties() as $property) {
				if ( ! $property->relationshipType || ! $this->repositoryLoader->hasRepository($repositoryName = $property->relationshipRepository)) {
					continue;
				}
				$relationshipMetadata = $cache[$relationshipEntity = $repositoryName::getEntityClassNames()[0]];
				if ( ! $relationshipMetadata->hasProperty($property->relationshipProperty)) {
					$property->relationshipProperty = implode('__', [
						str_replace('\\', '_', $metadata->getClassName()),
						$property->name,
						$property->relationshipProperty
					]);
					$relationshipContainer = $property->container;
					$relationshipIsMain = $property->relationshipIsMain;
					switch ($relationshipType = $property->relationshipType) {
						case Nextras\Orm\Entity\Reflection\PropertyMetadata::RELATIONSHIP_ONE_HAS_MANY:
							$relationshipType = Nextras\Orm\Entity\Reflection\PropertyMetadata::RELATIONSHIP_MANY_HAS_ONE;
							$relationshipContainer = Nextras\Orm\Relationships\ManyHasOne::class;
							break;
						case Nextras\Orm\Entity\Reflection\PropertyMetadata::RELATIONSHIP_MANY_HAS_ONE:
							$relationshipType = Nextras\Orm\Entity\Reflection\PropertyMetadata::RELATIONSHIP_ONE_HAS_MANY;
							$relationshipContainer = Nextras\Orm\Relationships\OneHasMany::class;
							break;
						case Nextras\Orm\Entity\Reflection\PropertyMetadata::RELATIONSHIP_ONE_HAS_ONE_DIRECTED:
						case Nextras\Orm\Entity\Reflection\PropertyMetadata::RELATIONSHIP_MANY_HAS_MANY:
							$relationshipIsMain = ! $relationshipIsMain;
							break;
					}
					$types = $property->types;
					if (isset($types[$key = strtolower($relationshipEntity)])) {
						$types[strtolower($entity)] = $types[$key];
						unset($types[$key]);
					}
					if (isset($types[$key = strtolower($property->container)])) {
						$types[strtolower($relationshipContainer)] = $types[$key];
						unset($types[$key]);
					}
					$relationshipProperty = new Nextras\Orm\Entity\Reflection\PropertyMetadata($property->relationshipProperty, $types, $property->access);
					$relationshipProperty->relationshipType = $relationshipType;
					$relationshipProperty->container = $relationshipContainer;
					$relationshipProperty->relationshipIsMain = $relationshipIsMain;
					$relationshipProperty->relationshipProperty = $property->name;
					$relationshipProperty->isNullable = TRUE;
					if (isset($this->repositories[$entity]) && $this->repositoryLoader->hasRepository($relationshipRepository = $this->repositories[$entity])) {
						$relationshipProperty->relationshipRepository = $relationshipRepository;
					}
					$relationshipMetadata->setProperty($relationshipProperty->name, $relationshipProperty);
				}
			}
		}

		return $cache;
	}

	/**
	 * @inheritdoc
	 */
	public static function get($className)
	{
		if ( ! isset(static::$metadata[$className])) {
			throw new Nextras\Orm\InvalidArgumentException("Entity metadata for '{$className}' does not exist.");
		}

		return static::$metadata[$className];
	}
}
