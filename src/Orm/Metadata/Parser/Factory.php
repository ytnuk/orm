<?php
namespace Ytnuk\Orm\Metadata\Parser;

use Nextras;
use Ytnuk;

final class Factory
	implements Nextras\Orm\Entity\Reflection\IMetadataParserFactory
{

	public function create(array $entityClassesMap)
	{
		return new Ytnuk\Orm\Metadata\Parser($entityClassesMap);
	}
}
