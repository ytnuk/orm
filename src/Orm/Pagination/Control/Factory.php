<?php

namespace Ytnuk\Orm\Pagination\Control;

use Nextras;
use Ytnuk;

/**
 * Interface Factory
 *
 * @package Ytnuk\Orm
 */
interface Factory
{

	/**
	 * @param Nextras\Orm\Collection\ICollection $collection
	 *
	 * @return Ytnuk\Orm\Pagination\Control
	 */
	public function create(Nextras\Orm\Collection\ICollection $collection);
}
