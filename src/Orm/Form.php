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

	public function success(self $form, $values)
	{
		$container = $this->getComponent('this');
		$container->setEntityValues($values->this);
		switch ($form->submitted) {
			case $this['action']['add']:
			case $this['action']['edit']:
				$this->repository->persistAndFlush($this->entity);
				break;
			case $this['action']['delete']:
				$container->removeEntity();
				$this->repository->flush();
				break;
		}
	}

	/**
	 * @return string
	 */
	public function formatMessage($type)
	{
		$message = parent::formatMessage($type);

		return $this->isSubmitted() ? 'orm.' . $message : $message;
	}

	/**
	 * @param $control
	 */
	protected function attached($control)
	{
		parent::attached($control);
		$this->addEntityContainer();
		$this->addActionContainer();
	}

	/**
	 * @return Form\Container
	 */
	public function addEntityContainer()
	{
		$class = rtrim($this->entity->getMetadata()->getClassName(), 'a..zA..Z') . 'Form\Container';
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
		$action->addSubmit('add', 'orm.form.action.add.label')->setDisabled($this->entity->id);
		$action->addSubmit('edit', 'orm.form.action.edit.label')->setDisabled(! $this->entity->id);
		$action->addSubmit('delete', 'orm.form.action.delete.label')->setValidationScope(FALSE)->setDisabled(! $this->entity->id);

		return $action;
	}
}
