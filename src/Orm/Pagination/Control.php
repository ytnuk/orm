<?php

namespace Ytnuk\Orm\Pagination;

use Ytnuk;
use Nextras;

/**
 * Class Control
 *
 * @package Ytnuk\Orm
 */
class Control extends Ytnuk\Pagination\Control
{

	/**
	 * @param Nextras\Orm\Collection\ICollection $collection
	 */
	public function __construct(Nextras\Orm\Collection\ICollection $collection)
	{
		parent::__construct($collection);
	}
}
