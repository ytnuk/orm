<?php
namespace Ytnuk\Orm;

use Nextras;

/**
 * Class Repository
 *
 * @package Ytnuk\Orm
 */
abstract class Repository
	extends Nextras\Orm\Repository\Repository
{

	/**
	 * @inheritdoc
	 */
	public static function getEntityClassNames()
	{
		return array_map(
			function ($name) {
				return $name . 'Entity';
			},
			parent::getEntityClassNames()
		);
	}

	/**
	 * @inheritdoc
	 */
	public function remove(
		$entity,
		$recursive = FALSE
	) {
		if ($recursive) {
			foreach (
				$entity->getMetadata()->getProperties() as $property
			) {
				if ($property->relationship && $property->relationship->isMain && $property->relationship->type === Nextras\Orm\Entity\Reflection\PropertyRelationshipMetadata::ONE_HAS_ONE_DIRECTED) {
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

	/**
	 * @inheritdoc
	 */
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
