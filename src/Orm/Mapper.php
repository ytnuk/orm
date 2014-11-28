<?php

namespace Kutny\Orm;

use Nextras;

/**
 * Class Mapper
 *
 * @package Kutny\Orm
 */
abstract class Mapper extends Nextras\Orm\Mapper\Mapper
{

	/**
	 * @return string
	 */
	public function getTableName()
	{
		if ( ! $this->tableName) {
			$namespace = explode('\\', $this->reflection->getNamespaceName());
			array_shift($namespace);
			$this->tableName = implode('_', array_map('lcfirst', $namespace));
		}

		return $this->tableName;
	}
}
