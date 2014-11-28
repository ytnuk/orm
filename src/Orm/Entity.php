<?php

namespace Ytnuk\Orm;

use Nextras;

/**
 * Class Entity
 *
 * @package Ytnuk\Orm
 */
abstract class Entity extends Nextras\Orm\Entity\Entity
{

	const PROPERTY_NAME = 'id';

	/**
	 * @return string
	 */
	public function __toString()
	{
		$property = self::PROPERTY_NAME;
		if ($this->hasValue(static::PROPERTY_NAME)) {
			$property = static::PROPERTY_NAME;
		}

		return (string) $this->getValue($property);
	}
}
