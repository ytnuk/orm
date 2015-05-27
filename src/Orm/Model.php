<?php

namespace Ytnuk\Orm;

use Nextras;
use Nette;
use Ytnuk;

/**
 * Class Database
 *
 * @package Ytnuk\Orm
 */
final class Model extends Nextras\Orm\Model\Model
{

	/**
	 * @inheritdoc
	 */
	public function __construct(array $configuration, Nextras\Orm\Model\IRepositoryLoader $repositoryLoader, Nextras\Orm\Model\MetadataStorage $metadataStorage, Nette\Caching\IStorage $storage)
	{
		parent::__construct($configuration, $repositoryLoader, $metadataStorage);
		$this->onFlush[] = function ($persisted, $removed) use ($storage) {
			array_map(function (Nextras\Orm\Entity\IEntity $entity) use ($storage) {
				if ($entity instanceof Ytnuk\Cache\Provider) {
					$storage->remove($entity->getCacheKey());
					$storage->clean([
						Nette\Caching\Cache::TAGS => $entity->getCacheTags(TRUE)
					]);
				}
			}, $persisted, $removed);
		};
	}
}
