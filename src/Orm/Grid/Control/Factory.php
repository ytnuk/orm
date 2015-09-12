<?php
namespace Ytnuk\Orm\Grid\Control;

use Ytnuk;

interface Factory
{

	public function create(Ytnuk\Orm\Repository $repository) : Ytnuk\Orm\Grid\Control;
}
