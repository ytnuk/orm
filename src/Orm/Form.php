<?php

namespace WebEdit\Orm;

use Nextras\Orm;
use WebEdit;

/**
 * Class Form
 *
 * @package WebEdit\Orm
 */
final class Form extends WebEdit\Form
{

	/**
	 * @var Entity
	 */
	private $entity;

	/**
	 * @var Model
	 */
	private $model;

	/**
	 * @var Repository
	 */
	private $repository;

	/**
	 * @var Orm\Mapper\IMapper
	 */
	private $mapper;

	/**
	 * @param Entity $entity
	 * @param Model $model
	 */
	public function __construct(Entity $entity, Model $model)
	{
		$this->entity = $entity;
		$this->model = $model;
		$this->repository = $model->getRepositoryForEntity($entity);
		$this->mapper = $this->repository->getMapper();
		$this->onSuccess[] = [
			$this,
			'success'
		];
	}

	/**
	 * @param self $form
	 */
	public function success(self $form)
	{
		if ($form->submitted === TRUE || $form->submitted->name != 'delete') {
			$values = $form->getValues(TRUE);
			$this->getComponent('this')
				->setEntityValues($values['this']);
			$this->repository->persist($this->entity);
		} else {
			$this->getComponent('this')
				->removeEntity();
		}
		$this->repository->flush($this->entity);
	}

	/**
	 * @param self $form
	 */
	public function redirect(self $form)
	{
		switch ($form->submitted->name) {
			case 'delete':
				$this->getPresenter()
					->redirect('Presenter:list');
				break;
			case 'edit':
			case 'add':
				$this->getPresenter()
					->redirect('Presenter:view', $this->entity->id);
				break;
		}
	}

	/**
	 * @param $control
	 */
	protected function attached($control)
	{
		parent::attached($control);
		$this->addEntityContainer();
		$this->addActionContainer();
		$this->onSuccess[] = [
			$this,
			'redirect'
		];
	}

	/**
	 * @return Form\Container
	 */
	public function addEntityContainer()
	{
		$class = rtrim($this->entity->getMetadata()
				->getClassName(), 'a..zA..Z') . 'Form\Container';
		$repository = $this->model->getRepositoryForEntity($this->entity);
		$repository->attach($this->entity);

		return $this->addComponent(new $class($this->entity, $repository), 'this');
	}

	/**
	 * @return \Nette\Forms\Container
	 */
	public function addActionContainer()
	{
		$this->addGroup('form.action.group');
		$action = $this->addContainer('action');
		if ($this->entity->getId()) {
			$action->addSubmit('edit', 'form.action.edit.label');
			$action->addSubmit('delete', 'form.action.delete.label')
				->setValidationScope(FALSE);
		} else {
			$action->addSubmit('add', 'form.action.add.label');
		}

		return $action;
	}
}
