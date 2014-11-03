<?php
namespace WebEdit\Orm;

use Nextras\Orm;
use WebEdit\Config;
use WebEdit\Database;

/**
 * Class Extension
 *
 * @package WebEdit\Orm
 */
final class Extension extends Orm\DI\OrmExtension implements Config\Provider
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
		return [self::class => $this->defaults];
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
