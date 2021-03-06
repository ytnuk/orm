<?php
namespace Ytnuk\Orm;

use Kdyby;
use Nette;
use Nextras;
use Ytnuk;

final class Form
	extends Ytnuk\Form
{

	/**
	 * @var Nextras\Orm\Entity\IEntity
	 */
	private $entity;

	/**
	 * @var Nextras\Orm\Model\IModel
	 */
	private $model;

	public function __construct(
		Nextras\Orm\Entity\IEntity $entity,
		Nextras\Orm\Model\IModel $model
	) {
		parent::__construct();
		$this->entity = $entity;
		$this->model = $model;
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
					$container->persistEntity(TRUE);
					break;
				case $this['action']['delete']:
					$container->removeEntity(TRUE);
					break;
			}
		}
	}

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

	protected function createComponent($name)
	{
		if ($name instanceof Nextras\Orm\Entity\IEntity) {
			$class = rtrim($name->getMetadata()->getClassName(), 'a..zA..Z') . 'Form\Container';

			return new $class($name, $this->model->getRepositoryForEntity($name));
		}

		return parent::createComponent($name);
	}

	protected function formatFlashMessage(string $type) : string
	{
		$message = [
			parent::formatFlashMessage($type),
		];
		$submitted = $this->isSubmitted();
		if ($submitted instanceof Nette\ComponentModel\Component && $submitted->getParent() === $this['action']) {
			array_unshift($message, 'orm');
		}

		return implode('.', $message);
	}

	protected function getControl() : Nette\Application\UI\Control
	{
		switch ($this->isSubmitted()) {
			case $this['action']['delete']:
				return $this->getPresenter();
		}

		return parent::getControl();
	}
}
