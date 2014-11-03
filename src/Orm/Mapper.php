<?php

namespace WebEdit\Orm;

use Nextras\Orm;

/**
 * Class Mapper
 *
 * @package WebEdit\Orm
 */
abstract class Mapper extends Orm\Mapper\Mapper
{

	/**
	 * @return string
	 */
	public function getTableName()
	{
		if ( ! $this->tableName) {
			$namespace = strtolower(str_replace('\\', '_', $this->reflection->getNamespaceName()));
			$this->tableName = substr($namespace, strpos($namespace, '_') + 1);
		}

		return $this->tableName;
	}
}
