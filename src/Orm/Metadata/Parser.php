<?php
namespace Ytnuk\Orm\Metadata;

use Nextras;

class Parser
	extends Nextras\Orm\Entity\Reflection\MetadataParser
{

	/**
	 * @var array
	 */
	private $classMetadata = [];

	/**
	 * @var bool
	 */
	private $parsingRelationship = FALSE;

	public function parseMetadata(
		$class,
		& $fileDependencies
	) {
		if (isset($this->classMetadata[$class])) {
			list($metadata, $fileDependencies) = $this->classMetadata[$class];
		} else {
			$this->classMetadata[$class] = [
				$metadata = parent::parseMetadata(
					$class,
					$fileDependencies
				),
				$fileDependencies,
			];
		}
		foreach (
			$metadata->getProperties() as $property
		) {
			$this->parseRelationshipPropertyMetadata(
				$class,
				$fileDependencies,
				$property
			);
		}

		return $metadata;
	}

	private function parseRelationshipPropertyMetadata(
		$class,
		& $fileDependencies,
		Nextras\Orm\Entity\Reflection\PropertyMetadata $propertyMetadata
	) {
		$parseRelationshipPropertyMetadata = ! $this->parsingRelationship && $propertyMetadata->relationship && in_array(
				$propertyMetadata->relationship->repository,
				$this->entityClassesMap
			);
		if ( ! $parseRelationshipPropertyMetadata) {
			return;
		}
		$this->parsingRelationship = TRUE;
		$relationshipMetadata = $this->parseMetadata(
			$propertyMetadata->relationship->entity,
			$fileDependencies,
			FALSE
		);
		$this->parsingRelationship = FALSE;
		if ( ! $relationshipMetadata->hasProperty($propertyMetadata->relationship->property)) {
			$propertyMetadata->relationship->property = implode(
				'__',
				[
					str_replace(
						'\\',
						'_',
						$class
					),
					$propertyMetadata->name,
					$propertyMetadata->relationship->property,
				]
			);
			$relationshipContainer = $propertyMetadata->container;
			$relationshipIsMain = $propertyMetadata->relationship->isMain;
			switch ($relationshipType = $propertyMetadata->relationship->type) {
				case Nextras\Orm\Entity\Reflection\PropertyRelationshipMetadata::ONE_HAS_MANY:
					$relationshipType = Nextras\Orm\Entity\Reflection\PropertyRelationshipMetadata::MANY_HAS_ONE;
					$relationshipContainer = Nextras\Orm\Relationships\ManyHasOne::class;
					break;
				case Nextras\Orm\Entity\Reflection\PropertyRelationshipMetadata::MANY_HAS_ONE:
					$relationshipType = Nextras\Orm\Entity\Reflection\PropertyRelationshipMetadata::ONE_HAS_MANY;
					$relationshipContainer = Nextras\Orm\Relationships\OneHasMany::class;
					break;
				case Nextras\Orm\Entity\Reflection\PropertyRelationshipMetadata::ONE_HAS_ONE:
				case Nextras\Orm\Entity\Reflection\PropertyRelationshipMetadata::MANY_HAS_MANY:
					$relationshipIsMain = ! $relationshipIsMain;
					break;
			}
			$types = $propertyMetadata->types;
			if (isset($types[$key = $propertyMetadata->relationship->entity])) {
				$types[$class] = $types[$key];
				unset($types[$key]);
			}
			if (isset($types[$key = $propertyMetadata->container])) {
				$types[$relationshipContainer] = $types[$key];
				unset($types[$key]);
			}
			$relationshipProperty = new Nextras\Orm\Entity\Reflection\PropertyMetadata;
			$relationshipProperty->name = $propertyMetadata->relationship->property;
			$relationshipProperty->types = $types;
			$relationshipProperty->isReadonly = $propertyMetadata->isReadonly;
			$relationshipProperty->relationship = new Nextras\Orm\Entity\Reflection\PropertyRelationshipMetadata;
			$relationshipProperty->relationship->type = $relationshipType;
			$relationshipProperty->container = $relationshipContainer;
			$relationshipProperty->relationship->isMain = $relationshipIsMain;
			$relationshipProperty->relationship->property = $propertyMetadata->name;
			$relationshipProperty->isNullable = TRUE;
			if (isset($this->entityClassesMap[$class])) {
				$relationshipProperty->relationship->repository = $this->entityClassesMap[$class];
				$relationshipProperty->relationship->entity = $class;
			}
			$relationshipMetadata->setProperty(
				$relationshipProperty->name,
				$relationshipProperty
			);
		}
	}
}