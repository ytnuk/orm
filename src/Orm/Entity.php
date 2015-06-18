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
	 * @var array
	 */
	private $tags = [];

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
	 * @inheritdoc
	 */
	public function setValue($name, $value)
	{
		$property = $this->metadata->getProperty($name);
		if (is_subclass_of($property->container, Nextras\Orm\Relationships\HasOne::class) && $this->hasValue($name) && $entity = $this->getValue($name)) {
			if ($entity instanceof Ytnuk\Cache\Provider) {
				$this->tags += $entity->getCacheTags(TRUE);
			}
		}

		return parent::setValue($name, $value);
	}

	/**
	 * @param bool $invalidate
	 *
	 * @inheritdoc
	 */
	public function getCacheTags($invalidate = FALSE)
	{
		$tags = [current($this->getCacheKey()) => TRUE];
		if ($invalidate) {
			$tags += $this->tags;
			foreach ($this->getMetadata()->getProperties() as $property) {
				if ($property->isVirtual || $property->relationshipProperty === $invalidate || $property->relationshipIsMain || ! $property->relationshipType || ! $this->hasValue($property->name)) {
					continue;
				}
				$entities = is_subclass_of($property->container, Nextras\Orm\Relationships\HasMany::class) ? $this->getValue($property->name) : [$this->getValue($property->name)];
				foreach ($entities as $entity) {
					if ($entity instanceof Ytnuk\Cache\Provider) {
						$tags += $entity->getCacheTags($property->name);
					}
				}
			}
		} else {
			$tags[get_class($this)] = TRUE;
		}

		return $tags;
	}

	/**
	 * @inheritdoc
	 */
	public function getCacheKey()
	{
		return [
			implode('::', [
				get_class($this),
				$this->id
			])
		];
	}
}
