<?php
namespace Ytnuk\Orm;

use Kdyby;
use Nextras;
use Ytnuk;

final class Extension
	extends Nextras\Orm\Bridges\NetteDI\OrmExtension
	implements Ytnuk\Config\Provider
{

	/**
	 * @var array
	 */
	private $defaults = [
		'model' => Model::class,
		'repositories' => [],
	];

	public function getConfigResources() : array
	{
		return [
			self::class => $this->defaults,
			Ytnuk\Form\Extension::class => [
				'forms' => [
					$this->prefix('form'),
				],
			],
			'services' => [
				$this->prefix('form') => Form\Factory::class,
				Grid\Control\Factory::class,
			],
			Kdyby\Translation\DI\TranslationExtension::class => [
				'dirs' => [
					__DIR__ . '/../../locale',
				],
			],
		];
	}

	protected function getRepositoryList($model) : array
	{
		$config = $this->getConfig($this->defaults);

		return parent::getRepositoryList($model) + $config['repositories'];
	}

	protected function setupMetadataStorage(array $repositoryConfig)
	{
		parent::setupMetadataStorage($repositoryConfig);
		$storage = $this->getContainerBuilder()->getDefinition($this->prefix('metadataStorage'));
		$storage->setClass(Metadata\Storage::class);
		$storage->getFactory()->setEntity($storage->getClass());
		$storage->getFactory()->arguments['repositories'] = $repositoryConfig[2];
	}
}
