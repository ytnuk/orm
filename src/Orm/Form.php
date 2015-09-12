<?php
namespace Ytnuk\Orm;

use Kdyby;
use Nette;
use SplObjectStorage;
use Ytnuk;

final class Form
	extends Ytnuk\Form
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

	public function __construct(
		Entity $entity,
		Model $model
	) {
		parent::__construct();
		$this->entity = $entity;
		$this->model = $model;
		$this->repository = $model->getRepositoryForEntity($entity);
		$this->onSuccess[] = [
			$this,
			'success',
		];
	}

	public function success(self $form)
	{
		$container = $this->getComponent('this');
		if ($container instanceof Form\Container) {
			switch ($form->isSubmitted()) {
				case $this['action']['add']:
				case $this['action']['edit']:
					$container->persistEntity();
					break;
				case $this['action']['delete']:
					$container->removeEntity();
					break;
			}
		}
	}

	protected function getControl() : Nette\Application\UI\Control
	{
		switch ($this->submitted) {
			case $this['action']['delete']:
				return $this->getPresenter();
		}

		return parent::getControl();
	}

	protected function formatFlashMessage(string $type) : string
	{
		$message = [
			parent::formatFlashMessage($type),
		];
		if ($this->submitted instanceof Nette\Forms\Controls\Button && $this->submitted->getParent() === $this['action']) {
			array_unshift(
				$message,
				'orm'
			);
		}

		return implode(
			'.',
			$message
		);
	}

	protected function attached($control)
	{
		parent::attached($control);
		$this->addComponent(
			$this->createComponent($this->entity),
			'this'
		);
		$this->addGroup('orm.form.action.group');
		$action = $this->addContainer('action');
		$action->addSubmit(
			'add',
			'orm.form.action.add.label'
		)->setDisabled($this->entity->isPersisted());
		$action->addSubmit(
			'edit',
			'orm.form.action.edit.label'
		)->setDisabled(! $this->entity->isPersisted());
		$action->addSubmit(
			'delete',
			'orm.form.action.delete.label'
		)->setValidationScope(FALSE)->setDisabled(! $this->entity->isPersisted());
		$controlGroupReflection = Nette\Reflection\ClassType::from('Nette\Forms\ControlGroup');
		$controlsProperty = $controlGroupReflection->getProperty('controls');
		$controlsProperty->setAccessible(TRUE);
		do {
			$detached = FALSE;
			foreach (
				$this->getGroups() as $group
			) {
				$controls = $controlsProperty->getValue($group);
				if ($controls instanceof SplObjectStorage) {
					foreach (
						$controls as $control
					) {
						if ($control instanceof Nette\ComponentModel\Component && ! $control->lookup(
								Nette\Forms\Form::class,
								FALSE
							)
						) {
							$detached = TRUE;
							$controls->detach($control);
						}
					}
					if ( ! count($controls)) {
						$this->removeGroup($group);
					}
				}
			}
		} while ($detached);
	}

	protected function createComponent($name)
	{
		if ($name instanceof Entity) {
			$class = rtrim(
					$name->getMetadata()->getClassName(),
					'a..zA..Z'
				) . 'Form\Container';

			return new $class(
				$name,
				$this->model->getRepositoryForEntity($name)
			);
		}

		return parent::createComponent($name);
	}

	public function addGroup(
		$caption = NULL,
		$setAsCurrent = TRUE
	) : Nette\Forms\ControlGroup
	{
		$group = parent::addGroup(
			NULL,
			$setAsCurrent
		);
		$group->setOption(
			'label',
			$caption
		);

		return $group;
	}
}
