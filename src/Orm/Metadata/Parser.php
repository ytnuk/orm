<?php
namespace Ytnuk\Orm\Metadata;

use Nextras;

final class Parser
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
		list($metadata, $fileDependencies) = $this->classMetadata[$class] ?? ($this->classMetadata[$class] = [
			parent::parseMetadata(
				$class,
				$fileDependencies
			),
			$fileDependencies,
		]);
		if ($metadata instanceof Nextras\Orm\Entity\Reflection\EntityMetadata) {
			foreach (
				$metadata->getProperties() as $property
			) {
				$this->parseRelationshipPropertyMetadata(
					$class,
					$fileDependencies,
					$property
				);
			}
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
			$fileDependencies
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
			if ($typeKey = $types[$key = $propertyMetadata->relationship->entity] ?? NULL) {
				$types[$class] = $typeKey;
				unset($types[$key]);
			}
			if ($typeKey = $types[$key = $propertyMetadata->container] ?? NULL) {
				$types[$relationshipContainer] = $typeKey;
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
			$relationshipProperty->relationship->cascade = $propertyMetadata->relationship->cascade;
			$relationshipProperty->isNullable = TRUE;
			if ($relationshipProperty->relationship->repository = $this->entityClassesMap[$class] ?? NULL) {
				$relationshipProperty->relationship->entity = $class;
			}
			$relationshipMetadata->setProperty(
				$relationshipProperty->name,
				$relationshipProperty
			);
		}
	}
}
