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
		$form->onSubmit[] = function (Ytnuk\Orm\Form $form) {
			if ($this->presenter->isAjax()) {
				$this->redrawControl();
			}
		};
		$form->onSuccess[] = function (Ytnuk\Orm\Form $form) {
			switch ($form->submitted) {
				case $form['action']['add']:
					$this->presenter->redirect('Presenter:view', $this->entity->id);
				case $form['action']['delete']:
					$this->presenter->redirect('Presenter:list');
			}
		};

		return $form;
	}
}
