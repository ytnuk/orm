<?php

namespace Ytnuk\Orm\Pagination;

use Nextras;
use Ytnuk;

/**
 * Class Control
 *
 * @package Ytnuk\Orm
 */
class Control extends Ytnuk\Pagination\Control
{

	/**
	 * @var Nextras\Orm\Collection\ICollection
	 */
	private $collection;

	/**
	 * @inheritdoc
	 *
	 * @param Nextras\Orm\Collection\ICollection $collection
	 */
	public function __construct(Nextras\Orm\Collection\ICollection $collection, $itemsPerPage = 1)
	{
		parent::__construct($collection, $itemsPerPage);
		$this->collection = $collection;
	}

	/**
	 * @return Nextras\Orm\Collection\ICollection
	 */
	public function getCollection()
	{
		return $this->collection->limitBy($this->getPaginator()->getItemsPerPage(), $this->getPaginator()->getOffset());
	}

	/**
	 * @inheritdoc
	 *
	 * @param Nextras\Orm\Collection\ICollection|NULL $collection
	 */
	public function count(Nextras\Orm\Collection\ICollection $collection = NULL)
	{
		return $collection ? $collection->countStored() : parent::count();
	}
}
