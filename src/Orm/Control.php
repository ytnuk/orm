<?php
namespace Ytnuk\Orm;

use Nette;
use Nextras;
use Ytnuk;

/**
 * Class Control
 *
 * @package Ytnuk\Orm
 */
abstract class Control
	extends Ytnuk\Application\Control
{

	/**
	 * @var int|array
	 */
	protected static $itemsPerPage = 10;

	/**
	 * @var Nextras\Orm\Entity\IEntity
	 */
	private $entity;

	/**
	 * @param Nextras\Orm\Entity\IEntity $entity
	 */
	public function __construct(Nextras\Orm\Entity\IEntity $entity)
	{
		parent::__construct();
		$this->entity = $entity;
	}

	/**
	 * @return Nette\Application\UI\Multiplier
	 */
	protected function createComponentYtnukOrmPaginationControl()
	{
		return new Nette\Application\UI\Multiplier(
			function ($key) {
				return new Ytnuk\Orm\Pagination\Control(
					$this->entity->getValue($key),
					is_array(
						static::$itemsPerPage
					) ? (isset(static::$itemsPerPage[$key]) ? static::$itemsPerPage[$key] : self::$itemsPerPage) : static::$itemsPerPage
				);
			}
		);
	}
}
