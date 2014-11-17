<?php

namespace WebEdit\Orm\Grid\Control;

use WebEdit;

/**
 * Interface Factory
 *
 * @package WebEdit\Orm
 */
interface Factory
{

	/**
	 * @param WebEdit\Orm\Repository $repository
	 *
	 * @return WebEdit\Orm\Grid\Control
	 */
	public function create($repository);
}
