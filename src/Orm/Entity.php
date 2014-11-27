<?php

namespace WebEdit\Orm;

use Nextras;

/**
 * Class Entity
 *
 * @package WebEdit\Orm
 */
abstract class Entity extends Nextras\Orm\Entity\Entity
{

	const PROPERTY_NAME = 'id';

	public function __toString()
	{
		$property = self::PROPERTY_NAME;
		if ($this->hasValue(static::PROPERTY_NAME)) {
			$property = static::PROPERTY_NAME;
		}

		return (string) $this->getValue($property);
	}
}
