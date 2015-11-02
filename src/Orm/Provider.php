<?php
namespace Ytnuk\Orm;

interface Provider
{

	public function getOrmResources() : array;
}