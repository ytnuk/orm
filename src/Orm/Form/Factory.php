<?php

namespace Kutny\Orm\Form;

use Kutny;

/**
 * Interface Factory
 *
 * @package Kutny\Orm
 */
interface Factory
{

	/**
	 * @param Kutny\Orm\Entity $entity
	 *
	 * @return Kutny\Orm\Form
	 */
	public function create($entity);
}
