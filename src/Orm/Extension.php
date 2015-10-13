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
		'metadataParserFactory' => Metadata\Parser\Factory::class,
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
}
