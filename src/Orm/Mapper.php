<?php

namespace Ytnuk\Orm;

use Nextras;

/**
 * Class Mapper
 *
 * @package Ytnuk\Orm
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
			$this->tableName = Nextras\Orm\StorageReflection\DbStorageReflection::underscore(implode($namespace));
		}

		return $this->tableName;
	}
}
