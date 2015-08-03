<?php
namespace Ytnuk\Orm\Metadata;

use Nette;
use Nextras;

/**
 * Class Storage
 *
 * @package Ytnuk\Orm
 */
final class Storage
	extends Nextras\Orm\Model\MetadataStorage
{

	/**
	 * @inheritDoc
	 */
	protected static $metadata;

	/**
	 * @var array
	 */
	private $repositories = [];

	/**
	 * @var Nextras\Orm\Model\IRepositoryLoader
	 */
	private $repositoryLoader;

	/**
	 * @inheritdoc
	 */
	public function __construct(
		Nette\Caching\IStorage $cacheStorage,
		array $entityClassesMap,
		Nextras\Orm\Model\IRepositoryLoader $repositoryLoader,
		array $repositories = []
	) {
		$this->repositoryLoader = $repositoryLoader;
		$this->repositories = $repositories;
		//parent::__construct();
		$cache = new Nette\Caching\Cache(
			$cacheStorage,
			'Nextras.Orm.metadata'
		);
		static::$metadata = $cache->load(
			$entityClassesMap,
			function (& $dp) use
			(
				$entityClassesMap,
				$repositoryLoader
			) {
				$metadata = $this->parseMetadata(
					$entityClassesMap,
					$dp
				);
				$validator = new Nextras\Orm\Entity\Reflection\MetadataValidator();
				$validator->validate(
					$metadata,
					$repositoryLoader
				);

				return $metadata;
			}
		);
		new Nextras\Orm\Model\MetadataStorage(
			$cacheStorage,
			$entityClassesMap,
			$repositoryLoader
		);
		parent::__construct(
			$cacheStorage,
			$entityClassesMap,
			$repositoryLoader
		);
	}

	/**
	 * @inheritdoc
	 */
	public static function get($className)
	{
		if ( ! isset(static::$metadata[$className])) {
			return parent::get($className);
		}

		return static::$metadata[$className];
	}

	/**
	 * @inheritdoc
	 */
	private function parseMetadata(
		$entityClassesMap,
		& $dp
	) {
		$cache = [];
		$annotationParser = new Nextras\Orm\Entity\Reflection\AnnotationParser($entityClassesMap);
		foreach (
			array_keys($entityClassesMap) as $className
		) {
			$cache[$className] = $annotationParser->parseMetadata(
				$className,
				$dp[Nette\Caching\Cache::FILES]
			);
		}
		foreach (
			$cache as $entity => $metadata
		) {
			if ($metadata instanceof Nextras\Orm\Entity\Reflection\EntityMetadata) {
				foreach (
					$metadata->getProperties() as $property
				) {
					if ( ! ($relationship = $property->relationship) || ! $this->repositoryLoader->hasRepository(
							$repositoryName = $relationship->repository
						)
					) {
						continue;
					}
					$relationshipMetadata = $cache[$relationshipEntity = current(
						call_user_func(
							[
								$repositoryName,
								'getEntityClassNames',
							]
						)
					)];
					if ($relationshipMetadata instanceof Nextras\Orm\Entity\Reflection\EntityMetadata && ! $relationshipMetadata->hasProperty($relationship->property)) {
						$relationship->property = implode(
							'__',
							[
								str_replace(
									'\\',
									'_',
									$metadata->getClassName()
								),
								$property->name,
								$relationship->property,
							]
						);
						$relationshipContainer = $property->container;
						$relationshipIsMain = $relationship->isMain;
						switch ($relationshipType = $property->relationship->type) {
							case Nextras\Orm\Entity\Reflection\PropertyRelationshipMetadata::ONE_HAS_MANY:
								$relationshipType = Nextras\Orm\Entity\Reflection\PropertyRelationshipMetadata::MANY_HAS_ONE;
								$relationshipContainer = Nextras\Orm\Relationships\ManyHasOne::class;
								break;
							case Nextras\Orm\Entity\Reflection\PropertyRelationshipMetadata::MANY_HAS_ONE:
								$relationshipType = Nextras\Orm\Entity\Reflection\PropertyRelationshipMetadata::ONE_HAS_MANY;
								$relationshipContainer = Nextras\Orm\Relationships\OneHasMany::class;
								break;
							case Nextras\Orm\Entity\Reflection\PropertyRelationshipMetadata::ONE_HAS_ONE_DIRECTED:
							case Nextras\Orm\Entity\Reflection\PropertyRelationshipMetadata::MANY_HAS_MANY:
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
						$relationshipProperty = new Nextras\Orm\Entity\Reflection\PropertyMetadata(
							$property->relationship->property,
							$types,
							$property->access
						);
						$relationshipProperty->relationship = new Nextras\Orm\Entity\Reflection\PropertyRelationshipMetadata;
						$relationshipProperty->relationship->type = $relationshipType;
						$relationshipProperty->container = $relationshipContainer;
						$relationshipProperty->relationship->isMain = $relationshipIsMain;
						$relationshipProperty->relationship->property = $property->name;
						$relationshipProperty->isNullable = TRUE;
						if (isset($this->repositories[$entity]) && $this->repositoryLoader->hasRepository(
								$relationshipRepository = $this->repositories[$entity]
							)
						) {
							$relationshipProperty->relationship->repository = $relationshipRepository;
							$relationshipProperty->relationship->entity = $entity;
						}
						$relationshipMetadata->setProperty(
							$relationshipProperty->name,
							$relationshipProperty
						);
					}
				}
			}
		}

		return $cache;
	}
}
