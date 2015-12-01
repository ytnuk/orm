<?php
namespace Ytnuk\Orm\Form;

use Nextras;
use Ytnuk;

abstract class Control
	extends Ytnuk\Orm\Control
{

	/**
	 * @var Nextras\Orm\Entity\IEntity
	 */
	private $entity;

	/**
	 * @var Factory
	 */
	private $form;

	//TODO: is Ytnuk\Orm\Entity required instead of IEntity? (NO! replace it) + Repository in form / container
	public function __construct(
		Nextras\Orm\Entity\IEntity $entity,
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
						$this->entity->getPersistedId()
					);
					break;
				case $form['action']['edit']:
					$presenter->redirect('this#' . $this->getSnippetId());
					break;
				case $form['action']['delete']:
					$presenter->redirect('Presenter:list');
					break;
			}
		};

		return $form;
	}
}
