<?php
namespace Ytnuk\Orm\Form;

use Nextras;
use Ytnuk;

interface Factory
{

	public function create(Nextras\Orm\Entity\IEntity $entity) : Ytnuk\Orm\Form;
}
