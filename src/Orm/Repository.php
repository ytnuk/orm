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
	 * @inheritdoc
	 */
	public static function getEntityClassNames()
	{
		return array_map(function ($name) {
			return $name . 'Entity';
		}, parent::getEntityClassNames());
	}
}
