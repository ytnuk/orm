<?php
namespace Ytnuk\Orm\Grid;

use Ytnuk;

final class Control
	extends Ytnuk\Application\Control
{

	/**
	 * @var Ytnuk\Orm\Repository
	 */
	private $repository;

	/**
	 * @var Ytnuk\Orm\Form\Factory
	 */
	private $form;

	public function __construct(
		Ytnuk\Orm\Repository $repository,
		Ytnuk\Orm\Form\Factory $form
	) {
		parent::__construct();
		$this->repository = $repository;
		$this->form = $form;
	}

	protected function createComponentYtnukGridControl() : Ytnuk\Grid\Control
	{
		$grid = new Ytnuk\Grid\Control(
			function (Ytnuk\Orm\Entity $entity = NULL) {
				if ( ! $entity) {
					$entityClass = $this->repository->getEntityClassName([]);
					$entity = new $entityClass;
				}
				$form = $this->form->create($entity);
				$form->onSubmit[] = function () {
					$this->redirect('this');
				};

				return $form;
			},
			function (
				array $order,
				array $filter
			) {
				return $this->repository->findBy($this->prepareValues($filter))->orderBy(
					$this->prepareValues($order)
				)->fetchPairs(current($this->repository->getEntityMetadata()->getPrimaryKey()));
			}
		);

		return $grid->setLink(
			function ($entity) {
				return $entity ? $this->getPresenter()->link(
					'Presenter:edit',
					['id' => $entity->id]
				) : $this->getPresenter()->link('Presenter:add');
			}
		)->filterInputs(['this']);
	}

	private function prepareValues(
		array $values,
		string $separator = '->',
		string $prefix = NULL
	) : array
	{
		$data = [];
		foreach (
			$values as $key => $value
		) {
			if ($prefix) {
				$key = $prefix . $separator . $key;
			}
			if (is_array($value)) {
				$data += $this->prepareValues(
					$value,
					$separator,
					$key
				);
			} else {
				$data[$key] = $value;
			}
		}

		return $data;
	}
}
