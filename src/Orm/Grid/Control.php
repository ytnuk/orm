<?php

namespace Ytnuk\Orm\Grid;

use Ytnuk;

/**
 * Class Control
 *
 * @package Ytnuk\Orm
 */
final class Control extends Ytnuk\Application\Control
{

	/**
	 * @var Ytnuk\Orm\Repository
	 */
	private $repository;

	/**
	 * @var Ytnuk\Orm\Form\Factory
	 */
	private $form;

	/**
	 * @param Ytnuk\Orm\Repository $repository
	 * @param Ytnuk\Orm\Form\Factory $form
	 */
	public function __construct(Ytnuk\Orm\Repository $repository, Ytnuk\Orm\Form\Factory $form)
	{
		$this->repository = $repository;
		$this->form = $form;
	}

	/**
	 * @return Control
	 */
	protected function createComponentYtnukGridControl()
	{
		$grid = new Ytnuk\Grid\Control(function (Ytnuk\Orm\Entity $entity = NULL) {
			if ( ! $entity) {
				$entityClass = $this->repository->getEntityClassName([]);
				$entity = new $entityClass;
			}
			$form = $this->form->create($entity);
			$form->onSubmit[] = function () {
				$this->redirect('this');
			};

			return $form;
		}, function (array $order, array $filter) {
			return $this->repository->findBy($this->prepareValues($filter))
				->orderBy($this->prepareValues($order))
				->fetchPairs('id');
		});

		return $grid->setLink(function ($entity) {
			return $entity ? $this->presenter->link('Presenter:view', ['id' => $entity->id]) : $this->presenter->link('Presenter:add');
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
