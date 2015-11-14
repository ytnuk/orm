<?php
namespace Ytnuk\Orm;

use Nextras;

abstract class Repository
	extends Nextras\Orm\Repository\Repository
{

	public function setModel(Nextras\Orm\Model\IModel $model)
	{
		parent::setModel($model);
		if ($model instanceof Model) {
			$this->onAfterPersist[] = $this->onBeforeRemove[] = [
				$model,
				'processEntityCache',
			];
		}
	}
}
