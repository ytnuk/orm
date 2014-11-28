<?php

namespace Ytnuk\Orm;

use Nextras;

/**
 * Class Repository
 *
 * @package Ytnuk\Orm
 */
abstract class Repository extends Nextras\Orm\Repository\Repository
{

	/**
	 * @return array
	 */
	public static function getEntityClassNames()
	{
		return array_map(function ($name) {
			return $name . 'Entity';
		}, parent::getEntityClassNames());
	}
}
