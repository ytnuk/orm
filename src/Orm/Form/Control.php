<?php
namespace Ytnuk\Orm\Form;

use Ytnuk;

abstract class Control
	extends Ytnuk\Orm\Control
{

	const NAME = 'form';

	/**
	 * @var Ytnuk\Orm\Entity
	 */
	private $entity;

	/**
	 * @var Factory
	 */
	private $form;

	public function __construct(
		Ytnuk\Orm\Entity $entity,
		Factory $form
	) {
		parent::__construct($entity);
		$this->entity = $entity;
		$this->form = $form;
	}

	protected function createComponentForm() : Ytnuk\Orm\Form
	{
		$form = $this->form->create($this->entity);
		$form->onSuccess[] = function (Ytnuk\Orm\Form $form) {
			$presenter = $this->getPresenter();
			switch ($form->isSubmitted()) {
				case $form['action']['add']:
					$presenter->redirect(
						'Presenter:edit',
						$this->entity->id
					);
					break;
				case $form['action']['edit']:
					$presenter->forward('this');
					break;
				case $form['action']['delete']:
					$presenter->redirect('Presenter:list');
					break;
			}
		};

		return $form;
	}
}
