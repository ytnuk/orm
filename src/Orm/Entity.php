<?php
namespace Ytnuk\Orm;

use Nextras;
use Ytnuk;

/**
 * Class Entity
 *
 * @package Ytnuk\Orm
 */
abstract class Entity
	extends Nextras\Orm\Entity\Entity
	implements Ytnuk\Cache\Provider
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
		if ( ! $this->hasValue(static::PROPERTY_NAME) || ! $value = $this->getValue(static::PROPERTY_NAME)) {
			$value = $this->getterId();
		}

		return (string) $value;
	}

	/**
	 * @inheritdoc
	 */
	public function setValue(
		$name,
		$value
	) {
		if ($this->metadata->hasProperty($name) && is_subclass_of(
				$this->metadata->getProperty($name)->container,
				Nextras\Orm\Relationships\HasOne::class
			) && $this->hasValue($name) && $entity = $this->getValue($name)
		) {
			if ($entity instanceof self) {
				$this->tags += $entity->getCacheTags(TRUE);
			}
		}

		return parent::setValue(
			$name,
			$value
		);
	}

	/**
	 * @inheritdoc
	 */
	public function getCacheKey()
	{
		return [
			static::class,
			$this->id,
		];
	}

	/**
	 * @inheritdoc
	 *
	 * @param bool $invalidate
	 */
	public function getCacheTags($invalidate = FALSE)
	{
		$tags = [
			implode(
				'::',
				$this->getCacheKey()
			) => TRUE,
		];
		if ($invalidate) {
			$tags += $this->tags;
			foreach (
				$this->getMetadata()->getProperties() as $property
			) {
				if ($property->isVirtual || ! $property->relationship || $property->relationship->property === $invalidate || $property->relationship->isMain || ! $property->relationship->type || ! $this->hasValue(
						$property->name
					)
				) {
					continue;
				}
				$entities = is_subclass_of(
					$property->container,
					Nextras\Orm\Relationships\HasMany::class
				) ? $this->getValue($property->name) : [$this->getValue($property->name)];
				foreach (
					$entities as $entity
				) {
					if ($entity instanceof self) {
						$tags += $entity->getCacheTags($property->name);
					}
				}
			}
		} else {
			$tags[static::class] = TRUE;
		}

		return $tags;
	}
}
