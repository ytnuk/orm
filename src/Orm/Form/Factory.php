<?php

namespace WebEdit\Orm\Form;

use WebEdit\Orm;

/**
 * Interface Factory
 *
 * @package WebEdit\Orm
 */
interface Factory
{

	/**
	 * @param Orm\Entity $entity
	 *
	 * @return Orm\Form
	 */
	public function create($entity);
}
