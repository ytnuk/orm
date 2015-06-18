<?php

namespace Ytnuk\Orm;

use Ytnuk;
use Nette;
use Nextras;

/**
 * Class Control
 *
 * @package Ytnuk\Orm
 */
abstract class Control extends Ytnuk\Application\Control
{

	/**
	 * @var Nextras\Orm\Entity\IEntity
	 */
	private $entity;

	/**
	 * @param Nextras\Orm\Entity\IEntity $entity
	 */
	public function __construct(Nextras\Orm\Entity\IEntity $entity)
	{
		$this->entity = $entity;
	}

	/**
	 * @return Nette\Application\UI\Multiplier
	 */
	protected function createComponentYtnukOrmPaginationControl()
	{
		return new Nette\Application\UI\Multiplier(function ($key) {
			return new Ytnuk\Orm\Pagination\Control($this->entity->getValue($key), $this->entity->itemsPerPage[$key]); //TODO: do not define in entity
		});
	}
}
