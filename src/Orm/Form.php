<?php

namespace Ytnuk\Orm;

use Nette;
use Nextras;
use Ytnuk;

/**
 * Class Form
 *
 * @package Ytnuk\Orm
 */
final class Form extends Ytnuk\Form
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
	 * @var Nextras\Orm\Mapper\IMapper
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

	public function success()
	{
		if ($this->submitted === TRUE || $this->submitted->name != 'delete') {
			$values = $this->getValues(TRUE);
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
	 * @return string
	 */
	public function formatMessage()
	{
		$message = parent::formatMessage();

		return $this->submittedBy() ? 'orm.' . $message : $message;
	}

	public function redirect()
	{
		$presenter = $this->getPresenter();
		if ($this->submittedBy('delete') && $this->isValid()) {
			$presenter->redirect('Presenter:list');
		}
		$presenter->redirect('Presenter:view', $this->entity->id);
	}

	/**
	 * @return Nette\Application\UI\Control|Nette\Application\UI\Presenter|NULL
	 */
	protected function getParentControl()
	{
		return $this->submittedBy('delete') && $this->isValid() ? $this->getPresenter() : parent::getParentControl();
	}

	/**
	 * @param $control
	 */
	protected function attached($control)
	{
		parent::attached($control);
		$this->addEntityContainer();
		$this->addActionContainer();
		$this->onSubmit[] = [
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
	 * @return Nette\Forms\Container
	 */
	public function addActionContainer()
	{
		$this->addGroup('orm.form.action.group');
		$action = $this->addContainer('action');
		if ($this->entity->getId()) {
			$action->addSubmit('edit', 'orm.form.action.edit.label');
			$action->addSubmit('delete', 'orm.form.action.delete.label')
				->setValidationScope(FALSE);
		} else {
			$action->addSubmit('add', 'orm.form.action.add.label');
		}

		return $action;
	}
}
