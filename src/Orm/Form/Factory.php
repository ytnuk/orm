<?php

namespace Ytnuk\Orm\Form;

use Ytnuk;

/**
 * Interface Factory
 *
 * @package Ytnuk\Orm
 */
interface Factory
{

	/**
	 * @param Ytnuk\Orm\Entity $entity
	 *
	 * @return Ytnuk\Orm\Form
	 */
	public function create($entity);
}
