<?php
namespace Ytnuk\Orm;

use Kdyby;
use Nextras;
use Ytnuk;

/**
 * Class Extension
 *
 * @package Ytnuk\Orm
 */
final class Extension extends Nextras\Orm\Bridges\NetteDI\OrmExtension implements Ytnuk\Config\Provider
{

	/**
	 * @var array
	 */
	private $defaults = [
		'model' => Model::class,
		'repositories' => []
	];

	/**
	 * @return array
	 */
	public function getConfigResources()
	{
		return [
			self::class => $this->defaults,
			Ytnuk\Form\Extension::class => [
				'forms' => [
					$this->prefix('form')
				]
			],
			'services' => [
				$this->prefix('form') => Form\Factory::class,
				Grid\Control\Factory::class
			],
			Kdyby\Translation\DI\TranslationExtension::class => [
				'dirs' => [
					__DIR__ . '/../../locale'
				]
			]
		];
	}

	/**
	 * @param string $model
	 *
	 * @return array
	 */
	protected function getRepositoryList($model)
	{
		$config = $this->getConfig($this->defaults);

		return parent::getRepositoryList($model) + $config['repositories'];
	}
}
