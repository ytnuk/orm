<?php

namespace Ytnuk\Orm;

use Nette;
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
	 * @param Entity $entity
	 * @param Model $model
	 */
	public function __construct(Entity $entity, Model $model)
	{
		$this->entity = $entity;
		$this->model = $model;
		$this->repository = $model->getRepositoryForEntity($entity);
		$this->onSuccess[] = [
			$this,
			'success'
		];
	}

	/**
	 * @param Form $form
	 */
	public function success(self $form)
	{
		$container = $this->getComponent('this');
		switch ($form->isSubmitted()) {
			case $this['action']['add']:
			case $this['action']['edit']:
				$container->saveEntity();
				break;
			case $this['action']['delete']:
				$container->removeEntity();
				break;
		}
	}

	/**
	 * @return Nette\Application\UI\Control
	 */
	protected function getControl()
	{
		switch ($this->submitted) {
			case $this['action']['delete']:
				return $this->getPresenter();
		}

		return parent::getControl();
	}

	/**
	 * @param string $type
	 *
	 * @return string
	 */
	protected function formatFlashMessage($type)
	{
		$message = [
			parent::formatFlashMessage($type)
		];
		if ($this->submitted && $this->submitted->getParent() === $this['action']) {
			array_unshift($message, 'orm');
		}

		return implode('.', $message);
	}

	/**
	 * @param $control
	 */
	protected function attached($control)
	{
		parent::attached($control);
		$this->addComponent($this->createComponent($this->entity), 'this');
		$this->addGroup('orm.form.action.group');
		$action = $this->addContainer('action');
		$action->addSubmit('add', 'orm.form.action.add.label')->setDisabled($this->entity->isPersisted());
		$action->addSubmit('edit', 'orm.form.action.edit.label')->setDisabled(! $this->entity->isPersisted());
		$action->addSubmit('delete', 'orm.form.action.delete.label')->setValidationScope(FALSE)->setDisabled(! $this->entity->isPersisted());
	}

	/**
	 * @param $name
	 *
	 * @return Nette\ComponentModel\IComponent
	 */
	protected function createComponent($name)
	{
		if ($name instanceof Entity) {
			$class = rtrim($name->getMetadata()->getClassName(), 'a..zA..Z') . 'Form\Container';

			return new $class($name, $this->model->getRepositoryForEntity($name));
		}

		return parent::createComponent($name);
	}
}
