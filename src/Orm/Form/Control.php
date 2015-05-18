<?php
namespace Ytnuk\Orm\Form;

use Ytnuk;

/**
 * Class Control
 *
 * @package Ytnuk\Orm\Form
 */
abstract class Control extends Ytnuk\Form\Control
{

	/**
	 * @var Ytnuk\Orm\Entity
	 */
	private $entity;

	/**
	 * @var Factory
	 */
	private $form;

	/**
	 * @param Ytnuk\Orm\Entity $entity
	 * @param Factory $form
	 */
	public function __construct(Ytnuk\Orm\Entity $entity, Factory $form)
	{
		$this->entity = $entity;
		$this->form = $form;
	}

	/**
	 * @return Ytnuk\Orm\Form
	 */
	protected function createComponentYtnukForm()
	{
		$form = $this->form->create($this->entity);
		$form->onSuccess[] = function (Ytnuk\Orm\Form $form) {
			$presenter = $this->getPresenter();
			switch ($form->isSubmitted()) {
				case $form['action']['add']:
					$presenter->redirect('Presenter:edit', $this->entity->id);
					break;
				case $form['action']['delete']:
					$presenter->redirect('Presenter:list');
					break;
			}
		};

		return $form;
	}
}
