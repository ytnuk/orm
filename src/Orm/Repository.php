<?php

namespace WebEdit\Orm;

use Nextras\Orm;

/**
 * Class Repository
 *
 * @package WebEdit\Orm
 */
abstract class Repository extends Orm\Repository\Repository
{

	/**
	 * @return array
	 */
	public static function getEntityClassNames()
	{
		return [rtrim(get_called_class(), 'a..zA..Z') . 'Entity'];
	}
}
