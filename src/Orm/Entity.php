<?php

namespace Ytnuk\Orm;

use Nextras;
use Ytnuk;

/**
 * Class Entity
 *
 * @package Ytnuk\Orm
 */
abstract class Entity extends Nextras\Orm\Entity\Entity implements Ytnuk\Cache\Provider
{

	const PROPERTY_NAME = 'id';

	/**
	 * @return string
	 */
	public function __toString()
	{
		if ( ! $value = $this->getValue(static::PROPERTY_NAME)) {
			$value = $this->getValue(self::PROPERTY_NAME);
		}

		return (string) $value;
	}

	/**
	 * @param bool $invalidate
	 *
	 * @inheritdoc
	 */
	public function getCacheTags($invalidate = FALSE)
	{
		$tags = [
			$this->getCacheKey()
		];
		if ($invalidate) {
			foreach ($this->getMetadata()->getProperties() as $property) {
				$relations = [
					Nextras\Orm\Entity\Reflection\PropertyMetadata::RELATIONSHIP_ONE_HAS_ONE_DIRECTED,
					Nextras\Orm\Entity\Reflection\PropertyMetadata::RELATIONSHIP_MANY_HAS_ONE
				];
				if ( ! $property->relationshipIsMain && in_array($property->relationshipType, $relations)) {
					if ($relationEntity = $this->getValue($property->name)) {
						if ($relationEntity instanceof Ytnuk\Cache\Provider) {
							$tags = array_merge($tags, $relationEntity->getCacheTags($invalidate));
						}
					}
				}
			}
		} else {
			$tags[] = get_class($this);
		}

		return $tags;
	}

	/**
	 * @inheritdoc
	 */
	public function getCacheKey()
	{
		return implode('::', [
			get_class($this),
			$this->id
		]);
	}
}
