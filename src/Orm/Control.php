<?php
namespace Ytnuk\Orm;

use Nette;
use Nextras;
use VojtechDobes;
use Ytnuk;

abstract class Control
	extends Ytnuk\Application\Control
{

	/**
	 * @var int|array
	 */
	protected static $itemsPerPage = 2;

	/**
	 * @var Nextras\Orm\Entity\IEntity
	 */
	private $entity;

	/**
	 * @var Nette\Caching\IStorage
	 */
	private $storage;

	/**
	 * @var VojtechDobes\NetteAjax\OnResponseHandler
	 */
	private $onResponseHandler;

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

	public function setOnResponseHandler(VojtechDobes\NetteAjax\OnResponseHandler $onResponseHandler)
	{
		parent::setOnResponseHandler($onResponseHandler);
		$this->onResponseHandler = $onResponseHandler;
	}

	protected function createComponentPagination() : Nette\Application\UI\Multiplier
	{
		return new Nette\Application\UI\Multiplier(
			function ($key) : Ytnuk\Orm\Pagination\Control {
				$control = new Ytnuk\Orm\Pagination\Control(
					$this->entity->getValue($key),
					is_array(
						static::$itemsPerPage
					) ? (static::$itemsPerPage[$key] ?? self::$itemsPerPage) : static::$itemsPerPage
				);
				if ($this->storage) {
					$control->setCacheStorage($this->storage);
				}
				if ($this->onResponseHandler) {
					$control->setOnResponseHandler($this->onResponseHandler);
				}

				return $control;
			}
		);
	}
}
