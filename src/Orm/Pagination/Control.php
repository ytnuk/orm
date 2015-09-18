<?php
namespace Ytnuk\Orm\Pagination;

use IteratorAggregate;
use Nette;
use Nextras;
use Ytnuk;

class Control
	extends Ytnuk\Pagination\Control
{

	/**
	 * @var Nextras\Orm\Collection\ICollection
	 */
	private $collection;

	/**
	 * @var Nette\Utils\Paginator
	 */
	private $paginator;

	public function __construct(
		Nextras\Orm\Collection\ICollection $collection,
		int $itemsPerPage = 1,
		Nette\Utils\Paginator $paginator = NULL
	) {
		parent::__construct(
			$this->collection = $collection,
			$itemsPerPage,
			$this->paginator = $paginator ? : new Nette\Utils\Paginator
		);
	}

	public function count() : int
	{
		return $this->collection->countStored() ? : parent::count();
	}

	public function getIterator() : IteratorAggregate
	{
		return $this->collection->limitBy(
			$this->paginator->getItemsPerPage(),
			$this->paginator->getOffset()
		) ? : parent::getIterator();
	}
}
