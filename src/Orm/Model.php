<?php
namespace Ytnuk\Orm;

use Nette;
use Nextras;
use Ytnuk;

final class Model
	extends Nextras\Orm\Model\Model
{

	/**
	 * @var array
	 */
	private $tags = [];

	public function __construct(
		array $configuration,
		Nextras\Orm\Model\IRepositoryLoader $repositoryLoader,
		Nextras\Orm\Model\MetadataStorage $metadataStorage,
		Nette\Caching\IStorage $cacheStorage
	) {
		parent::__construct($configuration, $repositoryLoader, $metadataStorage);
		$this->onFlush[] = function () use
		(
			$cacheStorage
		) {
			$cacheStorage->clean([
				Nette\Caching\Cache::TAGS => $this->tags,
			]);
			$this->tags = [];
		};
	}

	public function processEntityCache(Nextras\Orm\Entity\IEntity $entity)
	{
		if ($entity instanceof Entity && $entity->isPersisted()) {
			$this->tags = array_merge($this->tags, $entity->getCacheTags(TRUE));
		}
	}
}
