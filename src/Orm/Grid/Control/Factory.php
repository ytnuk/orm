<?php

namespace WebEdit\Orm\Grid\Control;

use WebEdit\Orm;

/**
 * Interface Factory
 *
 * @package WebEdit\Orm
 */
interface Factory
{

	/**
	 * @param Orm\Repository $repository
	 *
	 * @return Orm\Grid\Control
	 */
	public function create($repository);
}
