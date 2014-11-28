<?php
namespace Kutny\Orm;

use Kdyby;
use Nextras;
use Kutny;

/**
 * Class Extension
 *
 * @package Kutny\Orm
 */
final class Extension extends Nextras\Orm\DI\OrmExtension implements Kutny\Config\Provider
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
			Kutny\Form\Extension::class => [
				'forms' => [
					$this->prefix('form')
				]
			],
			'services' => [
				$this->prefix('form') => [
					'implement' => Form\Factory::class,
					'parameters' => ['entity'],
					'arguments' => ['%entity%']
				],
				$this->prefix('gridControl') => [
					'implement' => Grid\Control\Factory::class,
					'parameters' => ['repository'],
					'arguments' => ['%repository%']
				]
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
		$repositories = parent::getRepositoryList($model);
		foreach ($config['repositories'] as $name => $repository) {
			$repositories[] = [
				'name' => $name,
				'serviceName' => $this->prefix('repositories.' . $name),
				'class' => $repository,
				'entities' => call_user_func([
					$repository,
					'getEntityClassNames'
				]),
			];
		}

		return $repositories;
	}
}
