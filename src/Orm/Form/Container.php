<?php

namespace Ytnuk\Orm\Form;

use Kdyby;
use Nette;
use Nextras;
use Ytnuk;

/**
 * Class Container
 *
 * @package Ytnuk\Database
 */
abstract class Container extends Ytnuk\Form\Container
{

	/**
	 * @var Ytnuk\Orm\Entity
	 */
	protected $entity;

	/**
	 * @var Nextras\Orm\Entity\Reflection\EntityMetadata
	 */
	protected $metadata;

	/**
	 * @var Ytnuk\Orm\Repository
	 */
	protected $repository;

	/**
	 * @var Ytnuk\Orm\Mapper
	 */
	protected $mapper;

	/**
	 * @var Ytnuk\Orm\Model
	 */
	protected $model;

	/**
	 * @param Ytnuk\Orm\Entity $entity
	 * @param Ytnuk\Orm\Repository $repository
	 */
	public function __construct(Ytnuk\Orm\Entity $entity, Ytnuk\Orm\Repository $repository)
	{
		$this->entity = $entity;
		$this->repository = $repository;
		$this->metadata = $entity->getMetadata();
		$this->mapper = $repository->getMapper();
		$this->model = $repository->getModel();
		$this->monitor(Ytnuk\Orm\Form::class);
		if ( ! $entity->isAttached()) {
			$repository->attach($entity);
		}
	}

	/**
	 * @return Ytnuk\Orm\Entity
	 */
	public function getEntity()
	{
		return $this->entity;
	}

	/**
	 * @return Ytnuk\Orm\Repository
	 */
	public function getRepository()
	{
		return $this->repository;
	}

	/**
	 * @param bool $flush
	 */
	public function saveEntity($flush = TRUE)
	{
		$this->setValues($this->getValues());
		$this->repository->persist($this->entity);
		if ($flush) {
			$this->repository->flush();
		}
	}

	/**
	 * @param array|\Traversable $values
	 * @param bool $erase
	 *
	 * @return Nette\Forms\Container
	 */
	public function setValues($values, $erase = FALSE)
	{
		foreach ($values as $property => $value) {
			if ($this[$property] instanceof Nette\Forms\IControl) {
				$this->entity->setValue($property, $value === '' ? NULL : $value);
			}
		}

		return parent::setValues($values, $erase);
	}

	/**
	 * @param Nette\ComponentModel\IComponent $component
	 */
	public function removeComponent(Nette\ComponentModel\IComponent $component)
	{
		if ($component instanceof self) {
			foreach ($component->getComponents(TRUE, self::class) as $container) {
				if ($group = $container->getCurrentGroup()) {
					$this->getForm()->removeGroup($group);
				}
			}
			if ($group = $component->getCurrentGroup()) {
				$this->getForm()->removeGroup($group);
			}
		}
		parent::removeComponent($component);
	}

	/**
	 * @param Ytnuk\Form $form
	 */
	protected function attached($form)
	{
		parent::attached($form);
		$this->setCurrentGroup($this->getForm()->addGroup($this->prefix('group')));
		$this->addProperties($this->metadata->getProperties());
	}

	/**
	 * @param string $string
	 *
	 * @return string
	 */
	protected function prefix($string)
	{
		return implode('.', [
			str_replace('_', '.', $this->mapper->getTableName()),
			'form',
			'container',
			$string
		]);
	}

	/**
	 * @param Nextras\Orm\Entity\Reflection\PropertyMetadata[] $properties
	 */
	protected function addProperties(array $properties)
	{
		//		usort($properties, function(Nextras\Orm\Entity\Reflection\PropertyMetadata $a, Nextras\Orm\Entity\Reflection\PropertyMetadata $b){
		//			return is_subclass_of($a->container, Nextras\Orm\Relationships\HasMany::class) && ! is_subclass_of($b->container, Nextras\Orm\Relationships\HasMany::class);
		//		});
		foreach ($properties as $property) {
			if (in_array($property->name, $this->metadata->getPrimaryKey()) || $property->isVirtual) {
				continue;
			}
			if (is_subclass_of($property->container, Nextras\Orm\Relationships\HasOne::class)) {
				if ($container = $this->lookup(self::class, FALSE)) {
					$path = $this->lookupPath(self::class, FALSE);
					$delimiter = strpos($path, '-');
					if (($delimiter === FALSE || $property->relationshipProperty === substr($path, 0, $delimiter)) && $property->relationshipRepository === get_class($container->getRepository())) {
						$this->entity->setReadOnlyValue($property->name, $container->getEntity());
						continue;
					}
				}
			}
			$this->addProperty($property);
		}
	}

	/**
	 * @param Nextras\Orm\Entity\Reflection\PropertyMetadata $property
	 *
	 * @return Nette\ComponentModel\IComponent
	 */
	protected function addProperty(Nextras\Orm\Entity\Reflection\PropertyMetadata $property)
	{
		$component = $this->addPropertyComponent($property);
		switch (TRUE) {
			case $component instanceof Nette\Forms\Controls\BaseControl:
				$component->setRequired(! $property->isNullable);
				$component->setDisabled($property->isReadonly);
				$component->setDefaultValue($this->entity->getRawValue($property->name));
				$component->setAttribute('placeholder', $this->formatPropertyPlaceholder($property));
				break;
			case $component instanceof Nette\ComponentModel\IContainer:
				if ($property->isReadonly) {
					foreach ($component->getComponents(TRUE, Nette\Forms\Controls\BaseControl::class) as $control) {
						$value = $control->getValue();
						$control->setDisabled();
						$control->setDefaultValue($value);
					}
				}
				break;
		}

		return $component;
	}

	/**
	 * @param Nextras\Orm\Entity\Reflection\PropertyMetadata $property
	 *
	 * @return Nette\ComponentModel\IComponent
	 */
	protected function addPropertyComponent(Nextras\Orm\Entity\Reflection\PropertyMetadata $property)
	{
		foreach ([$property->name => TRUE] + $property->types + [substr($property->container, strrpos($property->container, '\\') + 1) => TRUE] as $type => $value) {
			$method = 'addProperty' . ucfirst($type);
			if ( ! method_exists($this, $method)) {
				continue;
			}

			return call_user_func([
				$this,
				$method
			], $property);
		}
	}

	/**
	 * @param Nextras\Orm\Entity\Reflection\PropertyMetadata $property
	 *
	 * @return string
	 */
	protected function formatPropertyPlaceholder(Nextras\Orm\Entity\Reflection\PropertyMetadata $property)
	{
		return implode('.', [
			$this->prefixProperty($property),
			'placeholder'
		]);
	}

	/**
	 * @param Nextras\Orm\Entity\Reflection\PropertyMetadata $property
	 *
	 * @return string
	 */
	protected function prefixProperty(Nextras\Orm\Entity\Reflection\PropertyMetadata $property)
	{
		return $this->prefix(implode('.', [
			'property',
			$property->name
		]));
	}

	/**
	 * @param Nextras\Orm\Entity\Reflection\PropertyMetadata $property
	 *
	 * @return \Nette\Forms\Container|NULL
	 */
	protected function addPropertyOneHasOneDirected(Nextras\Orm\Entity\Reflection\PropertyMetadata $property)
	{
		if ( ! $property->relationshipIsMain) {
			return NULL;
		}
		if ($this->entity->hasValue($property->name)) {
			$entity = $this->entity->getValue($property->name);
		} else {
			$repository = $this->model->getRepository($property->relationshipRepository);
			$relationshipEntityClass = $repository->getEntityMetadata()->getClassName();
			$entity = new $relationshipEntityClass;
		}

		return $this->addComponent($this->form->createComponent($entity), $property->name);
	}

	/**
	 * @param Nextras\Orm\Entity\Reflection\PropertyMetadata $property
	 *
	 * @return \Nette\Forms\Controls\SelectBox
	 */
	protected function addPropertyManyHasOne(Nextras\Orm\Entity\Reflection\PropertyMetadata $property)
	{
		$repository = $this->model->getRepository($property->relationshipRepository);
		$class = $repository->getEntityClassName([]);
		$entity = new $class;
		$primaryKeys = $entity->getMetadata()->getPrimaryKey();
		$primaryKey = reset($primaryKeys);
		$items = $repository->findAll()->fetchPairs($primaryKey, $entity::PROPERTY_NAME);

		return $this->addSelect($property->name, $this->formatPropertyLabel($property), $items)->setPrompt($this->formatPropertyPrompt($property));
	}

	/**
	 * @param Nextras\Orm\Entity\Reflection\PropertyMetadata $property
	 *
	 * @return string
	 */
	protected function formatPropertyLabel(Nextras\Orm\Entity\Reflection\PropertyMetadata $property)
	{
		return implode('.', [
			$this->prefixProperty($property),
			'label'
		]);
	}

	/**
	 * @param Nextras\Orm\Entity\Reflection\PropertyMetadata $property
	 *
	 * @return string
	 */
	protected function formatPropertyPrompt(Nextras\Orm\Entity\Reflection\PropertyMetadata $property)
	{
		return implode('.', [
			$this->prefixProperty($property),
			'placeholder'
		]);
	}

	/**
	 * @param Nextras\Orm\Entity\Reflection\PropertyMetadata $property
	 *
	 * @return \Nette\Forms\Container|NULL
	 */
	protected function addPropertyOneHasMany(Nextras\Orm\Entity\Reflection\PropertyMetadata $property)
	{
		$this->setCurrentGroup($this->getForm()->addGroup($this->prefixPropertyGroup($property), FALSE));
		$repository = $this->model->getRepository($property->relationshipRepository);
		$collection = $this->entity->getValue($property->name)->getIterator();
		$replicator = $this->addDynamic($property->name, function (Nette\Forms\Container $container) use ($property, $repository, $collection) {
			if ($entity = $collection->current()) {
				$collection->next();
			} else {
				$entityClassName = $repository->getEntityMetadata()->getClassName();
				$entity = new $entityClassName;
			}
			$replicator = $container->parent;
			$name = $container->getName();
			unset($container->parent[$name]);
			$replicator->addComponent($container = $this->form->createComponent($entity), $name);
			$container->addSubmit('delete', $this->formatPropertyAction($property, 'delete'))->addRemoveOnClick(function (Kdyby\Replicator\Container $replicator, self $container) {
				$container->removeEntity();
			});
		}, count($collection));
		$replicator->getCurrentGroup()->add($replicator->addSubmit('add', $this->formatPropertyAction($property, 'add'))->setValidationScope([$replicator])->addCreateOnClick());
		$containers = [];
		foreach ($replicator->getContainers() as $container) {
			$containers[$container->name] = $container;
		}
		if ($this->getForm()->isSubmitted() instanceof Nette\Forms\Controls\SubmitButton) {
			unset($containers[$this->getForm()->isSubmitted()->getParent()->getName()]);
		}
		foreach ($containers as $key => $container) {
			foreach ($container->getComponents(FALSE, Nette\Forms\Controls\BaseControl::class) as $control) {
				if ($control->getOption('unique')) {
					foreach (array_diff_key($containers, [$key => $container]) as $sibling) {
						$control->addRule(Nette\Forms\Form::NOT_EQUAL, NULL, $sibling[$control->name]);
					}
				}
			}
		}

		//TODO: fix rendering at end of form + add groups relative to property->name from outside
		return $replicator;
	}

	/**
	 * @param Nextras\Orm\Entity\Reflection\PropertyMetadata $property
	 *
	 * @return string
	 */
	protected function prefixPropertyGroup(Nextras\Orm\Entity\Reflection\PropertyMetadata $property)
	{
		return implode('.', [
			$this->prefixProperty($property),
			'group'
		]);
	}

	/**
	 * @param Nextras\Orm\Entity\Reflection\PropertyMetadata $property
	 * @param string $action
	 *
	 * @return string
	 */
	protected function formatPropertyAction(Nextras\Orm\Entity\Reflection\PropertyMetadata $property, $action)
	{
		return implode('.', [
			$this->prefixProperty($property),
			'action',
			$action
		]);
	}

	/**
	 * @param bool $flush
	 */
	public function removeEntity($flush = TRUE)
	{
		$this->repository->remove($this->entity, TRUE);
		if ($flush) {
			$this->repository->flush();
		}
	}

	/**
	 * @param Nextras\Orm\Entity\Reflection\PropertyMetadata $property
	 *
	 * @return \Nette\Forms\Controls\TextInput
	 */
	protected function addPropertyInt(Nextras\Orm\Entity\Reflection\PropertyMetadata $property)
	{
		return $this->addPropertyString($property)->setType('number');
	}

	/**
	 * @param Nextras\Orm\Entity\Reflection\PropertyMetadata $property
	 *
	 * @return \Nette\Forms\Controls\TextInput
	 */
	protected function addPropertyString(Nextras\Orm\Entity\Reflection\PropertyMetadata $property)
	{
		return $this->addText($property->name, $this->formatPropertyLabel($property));
	}

	/**
	 * @param Nextras\Orm\Entity\Reflection\PropertyMetadata $property
	 *
	 * @return \Nette\Forms\Controls\Checkbox
	 */
	protected function addPropertyBool(Nextras\Orm\Entity\Reflection\PropertyMetadata $property)
	{
		return $this->addCheckbox($property->name, $this->formatPropertyLabel($property));
	}
}
