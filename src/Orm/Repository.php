<?php
namespace Ytnuk\Orm;

use Nextras;

abstract class Repository
	extends Nextras\Orm\Repository\Repository
{

	public function remove(
		$entity,
		$recursive = FALSE
	) : Nextras\Orm\Entity\IEntity
	{
		//TODO: is this needed anymore?
		if ($recursive) {
			foreach (
				$entity->getMetadata()->getProperties() as $property
			) {
				if ($property->relationship && $property->relationship->isMain && $property->relationship->type === Nextras\Orm\Entity\Reflection\PropertyRelationshipMetadata::ONE_HAS_ONE) {
					if ($entity->hasValue($property->name) && $relationEntity = $entity->getValue($property->name)) {
						if ($relationEntity->isAttached()) {
							$relationEntity->getRepository()->remove(
								$relationEntity,
								$recursive
							);
						}
					}
				}
			}
		}

		return parent::remove(
			$entity,
			$recursive
		);
	}

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
