<?php
namespace Ytnuk\Orm\Grid\Control;

use Ytnuk;

/**
 * Interface Factory
 *
 * @package Ytnuk\Orm
 */
interface Factory
{

	/**
	 * @param Ytnuk\Orm\Repository $repository
	 *
	 * @return Ytnuk\Orm\Grid\Control
	 */
	public function create(Ytnuk\Orm\Repository $repository);
}
