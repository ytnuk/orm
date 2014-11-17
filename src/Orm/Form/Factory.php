<?php

namespace WebEdit\Orm\Form;

use WebEdit;

/**
 * Interface Factory
 *
 * @package WebEdit\Orm
 */
interface Factory
{

	/**
	 * @param WebEdit\Orm\Entity $entity
	 *
	 * @return WebEdit\Orm\Form
	 */
	public function create($entity);
}
