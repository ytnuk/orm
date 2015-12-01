<?php
namespace Ytnuk\Orm\Grid\Control;

use Nextras;
use Ytnuk;

interface Factory
{

	public function create(Nextras\Orm\Repository\IRepository $repository) : Ytnuk\Orm\Grid\Control;
}
