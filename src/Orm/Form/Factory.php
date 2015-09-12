<?php
namespace Ytnuk\Orm\Form;

use Ytnuk;

interface Factory
{

	public function create(Ytnuk\Orm\Entity $entity) : Ytnuk\Orm\Form;
}
