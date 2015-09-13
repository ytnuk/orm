<?php
namespace Ytnuk\Orm;

use Nette;
use Nextras;
use Ytnuk;

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
	 * @var Nette\Caching\IStorage
	 */
	private $storage;

	public function __construct(Nextras\Orm\Entity\IEntity $entity)
	{
		parent::__construct();
		$this->entity = $entity;
	}

	public function setCacheStorage(Nette\Caching\IStorage $storage)
	{
		parent::setCacheStorage($storage);
		$this->storage = $storage;
	}

	protected function createComponentYtnukOrmPaginationControl() : Nette\Application\UI\Multiplier
	{
		return new Nette\Application\UI\Multiplier(
			function ($key) : Ytnuk\Orm\Pagination\Control {
				$control = new Ytnuk\Orm\Pagination\Control(
					$this->entity->getValue($key),
					is_array(
						static::$itemsPerPage
					) ? (isset(static::$itemsPerPage[$key]) ? static::$itemsPerPage[$key] : self::$itemsPerPage) : static::$itemsPerPage
				);
				if ($this->storage) {
					$control->setCacheStorage($this->storage);
				}

				return $control;
			}
		);
	}
}
