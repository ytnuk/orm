<?php

namespace Kutny\Orm\Grid\Control;

use Kutny;

/**
 * Interface Factory
 *
 * @package Kutny\Orm
 */
interface Factory
{

	/**
	 * @param Kutny\Orm\Repository $repository
	 *
	 * @return Kutny\Orm\Grid\Control
	 */
	public function create($repository);
}
