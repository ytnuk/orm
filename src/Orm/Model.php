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
	 * @var array
	 */
	private $tags = [];

	/**
	 * @var Nette\Caching\IStorage
	 */
	private $cacheStorage;

	/**
	 * @inheritdoc
	 */
	public function __construct(array $configuration, Nextras\Orm\Model\IRepositoryLoader $repositoryLoader, Nextras\Orm\Model\MetadataStorage $metadataStorage, Nette\Caching\IStorage $cacheStorage)
	{
		parent::__construct($configuration, $repositoryLoader, $metadataStorage);
		$this->cacheStorage = $cacheStorage;
		$this->onFlush[] = function () {
			$this->cacheStorage->clean([
				Nette\Caching\Cache::TAGS => array_keys($this->tags)
			]);
		};
	}

	/**
	 * @param Nextras\Orm\Entity\IEntity $entity
	 */
	public function processEntityCache(Nextras\Orm\Entity\IEntity $entity)
	{
		if ($entity instanceof Ytnuk\Cache\Provider) {
			$this->tags += $entity->getCacheTags(TRUE);
		}
	}
}
