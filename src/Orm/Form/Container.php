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
	private $entity;

	/**
	 * @var Ytnuk\Orm\Repository
	 */
	private $repository;

	/**
	 * @var Nextras\Orm\Entity\Reflection\EntityMetadata
	 */
	private $metadata;

	/**
	 * @var Ytnuk\Orm\Mapper
	 */
	private $mapper;

	/**
	 * @var Ytnuk\Orm\Model
	 */
	private $model;

	/**
	 * @var Ytnuk\Orm\Entity[]
	 */
	private $relations = [];

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
		$repository->attach($entity);
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
	 * @return Nextras\Orm\Entity\Reflection\EntityMetadata
	 */
	public function getMetadata()
	{
		return $this->metadata;
	}

	/**
	 * @param bool $flush
	 */
	public function persistEntity($flush = TRUE)
	{
		$this->setValues($this->getValues());
		$this->repository->persist($this->entity);
		if ($flush) {
			$this->repository->flush();
		}
	}

	/**
	 * @inheritdoc
	 */
	public function setValues($values, $erase = FALSE)
	{
		$this->initEntityRelations();
		foreach ($values as $property => $value) {
			if ($this[$property] instanceof Nette\Forms\IControl) {
				$this->entity->setValue($property, $value === '' ? NULL : $value);
			}
		}

		return parent::setValues($values, $erase);
	}

	/**
	 * @return Ytnuk\Orm\Entity[]
	 */
	public function initEntityRelations()
	{
		foreach ($this->relations as $property => $value) {
			$this->entity->setValue($property, $value);
		}

		return $this->relations;
	}

	/**
	 * @inheritdoc
	 */
	public function removeComponent(Nette\ComponentModel\IComponent $component)
	{
		parent::removeComponent($component);
		if ($component instanceof self) {
			$component->initEntityRelations();
		}
	}

	/**
	 * @inheritdoc
	 */
	protected function attached($form)
	{
		parent::attached($form);
		$this->setCurrentGroup($this->getForm()->addGroup($this->prefixContainer('group')));
		$this->addProperties($this->metadata->getProperties());
	}

	/**
	 * @param string $string
	 *
	 * @return string
	 */
	protected function prefixContainer($string)
	{
		return $this->prefix(implode('.', [
			'form',
			'container',
			$string
		]));
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
			$string
		]);
	}

	/**
	 * @param Nextras\Orm\Entity\Reflection\PropertyMetadata[] $properties
	 */
	protected function addProperties(array $properties)
	{
		$path = $this->lookupPath(self::class, FALSE);
		$delimiter = strpos($path, '-');
		if ($delimiter !== FALSE) {
			$path = substr($path, 0, $delimiter);
		}
		$parent = $this->lookup(self::class, FALSE);
		foreach ($properties as $property) {
			if (in_array($property->name, $this->metadata->getPrimaryKey())) {
				continue;
			}
			if ($path && $parent && is_subclass_of($property->container, Nextras\Orm\Relationships\HasOne::class)) {
				if ($property->relationshipProperty === $path && $property->relationshipRepository === get_class($parent->getRepository())) {
					$this->relations[$property->name] = $parent->getEntity();
					continue;
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
				if ( ! $component instanceof Nette\Forms\Controls\Checkbox) {
					$component->setRequired(! $property->isNullable);
				}
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
		foreach ([$property->name => TRUE] + ($property->isVirtual ? [] : $property->types + [substr($property->container, strrpos($property->container, '\\') + 1) => TRUE]) as $type => $value) {
			$method = 'addProperty' . ucfirst($type);
			if ( ! method_exists($this, $method)) {
				continue;
			}

			return call_user_func([
				$this,
				$method
			], $property);
		}

		return NULL;
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
		return $this->prefixContainer(implode('.', [
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
		$items = $repository->findAll()->fetchPairs(implode('-', $repository->getEntityMetadata()->getPrimaryKey()));
		if ($container = $this->lookup(self::class, FALSE)) {
			if ($container->getRepository() === $repository && $entity = $container->getEntity()) {
				if ($entity->id) {
					unset($items[$entity->id]);
				}
			}
		}

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
	 * @param int $forceDefault
	 *
	 * @return Kdyby\Replicator\Container
	 */
	protected function addPropertyOneHasMany(Nextras\Orm\Entity\Reflection\PropertyMetadata $property, $forceDefault = 0)
	{
		$this->setCurrentGroup($this->getForm()->addGroup($this->prefixPropertyGroup($property), FALSE));
		$repository = $this->model->getRepository($property->relationshipRepository);
		$collection = $this->entity->getValue($property->name)->getIterator();
		$parent = $this->lookup(Ytnuk\Orm\Form\Container::class, FALSE);
		$parentProperty = $parent ? $parent->getMetadata()->getProperty($this->name) : NULL;
		$isNullable = $parentProperty && $parentProperty->relationshipType === Nextras\Orm\Entity\Reflection\PropertyMetadata::RELATIONSHIP_ONE_HAS_ONE_DIRECTED && $parentProperty->isNullable;
		if ($isNullable) {
			$forceDefault = 0;
		}
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
		}, max(count($collection), $forceDefault), (bool) $forceDefault);
		if ($isNullable && $this->getForm()->isSubmitted()) {
			$containers = array_filter($replicator->getContainers()->getArrayCopy(), function ($container) {
				return $container instanceof self && ! $container['delete']->isSubmittedBy();
			});
			if ( ! $containers) {
				$this->removeEntity();
			}
		}
		$replicator->getCurrentGroup()->add($add = $replicator->addSubmit('add', $this->formatPropertyAction($property, 'add'))->setValidationScope([$replicator])->addCreateOnClick());
		if ($this->getForm()->isSubmitted() === $add) {
			$isValid = TRUE;
			if ($scope = $add->getValidationScope()) {
				$isValid = ! array_filter($scope, function (Nette\Forms\Container $container) {
					return ! $container->isValid();
				});
			}
			if ($isValid) {
				$add->setValidationScope(FALSE);
			}
			$add->click();
			$add->onClick = [];
		}
		$containers = [];
		foreach ($replicator->getContainers() as $container) {
			if ($this->getForm()->isSubmitted() !== $container['delete']) {
				$containers[$container->name] = $container;
			}
		}
		if (count($containers) <= $forceDefault) {
			array_map(function (self $container) {
				unset($container['delete']);
			}, $containers);
		} else {
			$persistedContainers = array_filter($containers, function (self $container) {
				return $container->getEntity()->isPersisted();
			});
			if (count($persistedContainers) <= $forceDefault) {
				array_map(function (self $container) {
					unset($container['delete']);
				}, $persistedContainers);
			}
		}
		foreach ($containers as $key => $container) {
			foreach ($container->getComponents(FALSE, Nette\Forms\Controls\BaseControl::class) as $control) {
				if ($unique = $control->getOption('unique')) {
					foreach (array_diff_key($containers, [$key => $container]) as $sibling) {
						$condition = $control->addCondition(Nette\Forms\Form::FILLED);
						if (is_string($unique) && isset($container[$unique]) && isset($sibling[$unique])) {
							$condition = $condition->addConditionOn($container[$unique], Nette\Forms\Form::EQUAL, $sibling[$unique]);
						}
						$condition->addRule(Nette\Forms\Form::NOT_EQUAL, NULL, $sibling[$control->name]);
					}
				}
			}
		}

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
