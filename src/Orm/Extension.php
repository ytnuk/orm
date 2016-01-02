<?php
namespace Ytnuk\Orm;

use Kdyby;
use Nextras;
use Ytnuk;

final class Extension
	extends Nextras\Orm\Bridges\NetteDI\OrmExtension
	implements Kdyby\Translation\DI\ITranslationProvider, Ytnuk\Form\Provider
{

	/**
	 * @var array
	 */
	private $defaults = [
		'model' => Model::class,
		'repositories' => [],
	];

	public function getTranslationResources() : array
	{
		return [
			__DIR__ . '/../../locale',
		];
	}

	public function getFormResources() : array
	{
		return [
			'forms' => [
				Form\Factory::class,
			],
		];
	}

	protected function getRepositoryList($model) : array
	{
		return parent::getRepositoryList($model) + $this->config['repositories'];
	}

	public function loadConfiguration()
	{
		$this->validateConfig($this->defaults);
		$providers = $this->compiler->getExtensions(Provider::class);
		array_walk($providers, function (Provider $provider) {
			$this->config = $this->validateConfig($this->config, $provider->getOrmResources());
		});
		parent::loadConfiguration();
		$builder = $this->getContainerBuilder();
		$builder->addDefinition($this->prefix('grid.control'))->setImplement(Grid\Control\Factory::class);
	}
}
