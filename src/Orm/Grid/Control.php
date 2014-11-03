<?php

namespace WebEdit\Orm\Grid;

use Nette\Forms;
use WebEdit\Application;
use WebEdit\Grid;
use WebEdit\Orm;

/**
 * Class Control
 *
 * @package WebEdit\Orm
 */
final class Control extends Application\Control
{

	/**
	 * @var Orm\Repository
	 */
	private $repository;

	/**
	 * @var Orm\Form\Factory
	 */
	private $form;

	/**
	 * @param Orm\Repository $repository
	 * @param Orm\Form\Factory $form
	 */
	public function __construct(Orm\Repository $repository, Orm\Form\Factory $form)
	{
		$this->repository = $repository;
		$this->form = $form;
	}

	/**
	 * @return Control
	 */
	protected function createComponentGrid()
	{
		$grid = new Grid\Control(function (Orm\Entity $entity = NULL) {
			if ( ! $entity) {
				$entityClass = $this->repository->getEntityClassName([]);
				$entity = new $entityClass;
			}
			$form = $this->form->create($entity);
			$form->onSuccess[] = function () {
				$this->redirect('this');
			};

			return $form;
		}, function (array $order, array $filter) {
			return $this->repository->findBy($this->prepareValues($filter))
				->orderBy($this->prepareValues($order))
				->fetchPairs('id');
		});

		return $grid->setLink(function ($entity) {
			return $entity ? $this->presenter->link('Presenter:edit', ['id' => $entity->id]) : $this->presenter->link('Presenter:add');
		})
			->filterInputs(['this']);
	}

	/**
	 * @param array $values
	 * @param string $separator
	 * @param string|NULL $prefix
	 *
	 * @return array
	 */
	private function prepareValues(array $values, $separator = '->', $prefix = NULL)
	{
		$data = [];
		foreach ($values as $key => $value) {
			if ($prefix) {
				$key = $prefix . $separator . $key;
			}
			if (is_array($value)) {
				$data += $this->prepareValues($value, $separator, $key);
			} else {
				$data[$key] = $value;
			}
		}

		return $data;
	}
}
