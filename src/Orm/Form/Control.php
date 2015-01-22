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
	 * @param Ytnuk\Orm\Form $form
	 */
	public function submit(Ytnuk\Orm\Form $form)
	{
		if ($this->presenter->isAjax()) {
			$this->redrawControl();
		}
		if ( ! $form->isValid()) {
			return;
		}
		if ($form->submittedBy('delete')) {
			$this->presenter->redirect('Presenter:list');
		} elseif ($form->submittedBy('add')) {
			$this->presenter->redirect('Presenter:view', $this->entity->id);
		}
	}

	/**
	 * @return Ytnuk\Orm\Form
	 */
	protected function createComponentYtnukForm()
	{
		$form = $this->form->create($this->entity);
		$form->onSubmit[] = [
			$this,
			'submit'
		];

		return $form;
	}
}
