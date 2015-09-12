<?php
namespace Ytnuk\Orm;

use Nextras;

abstract class Mapper
	extends Nextras\Orm\Mapper\Mapper
{

	public function getTableName() : string
	{
		if ( ! $this->tableName) {
			$namespace = explode(
				'\\',
				$this->getReflection()->getNamespaceName()
			);
			array_shift($namespace);
			$this->tableName = Nextras\Orm\StorageReflection\StringHelper::underscore(implode($namespace));
		}

		return $this->tableName ? : parent::getTableName();
	}
}
