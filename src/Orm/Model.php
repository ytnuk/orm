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
			$tags = [];
			$keys = [];
			array_map(function (Nextras\Orm\Entity\IEntity $entity = NULL) use (& $tags, & $keys) {
				if ($entity instanceof Ytnuk\Cache\Provider) {
					$tags += array_flip($entity->getCacheTags(TRUE));
					$keys[$entity->getCacheKey()] = TRUE;
				}
			}, $persisted, $removed);
			foreach (array_keys($keys) as $key) {
				$storage->remove($key);
			}
			$storage->clean([
				Nette\Caching\Cache::TAGS => array_keys($tags)
			]);
		};
	}
}
