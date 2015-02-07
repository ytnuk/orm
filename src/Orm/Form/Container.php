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
abstract class Container extends Ytnuk\Form\Container //TODO: use extra inputs from Nextras\Forms
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
	public function __construct(Ytnuk\Orm\Entity $entity = NULL, Ytnuk\Orm\Repository $repository = NULL)
	{
		$this->entity = $entity;
		$this->repository = $repository;
		$this->monitor(Ytnuk\Form::class);
	}

	public function removeEntity()
	{
		foreach ($this->metadata->getProperties() as $property) {
			if ($property->container === Nextras\Orm\Relationships\OneHasOneDirected::class && $property->relationshipIsMain) {
				$this->getComponent($property->name)
					->removeEntity();
			}
		}
		$this->repository->remove($this->entity);
	}

	/**
	 * @param Nette\Utils\ArrayHash $values
	 *
	 * @return Ytnuk\Orm\Entity
	 */
	public function setEntityValues(Nette\Utils\ArrayHash $values) //TODO: fuck everything in this class!
	{
		foreach ($values as $property => $value) {
			if ($this[$property] instanceof Kdyby\Replicator\Container) {
				//TODO:
				continue;
			} elseif ($value instanceof Nette\Utils\ArrayHash) {
				$container = $this->getComponent($property);
				if ($container instanceof self) {
					$container->setEntityValues($value);
				} else {
					$container->setValues($value);
				}
			}
			if ($this->isValid()) {
				$this->entity->setValue($property, $value !== '' ? $value : NULL);
			}
		}

		return $this->entity;
	}

	/**
	 * @param Ytnuk\Form $form
	 */
	protected function attached($form)
	{
		parent::attached($form);
		if ($this->entity && $this->repository) {
			$this->init($this->entity, $this->repository);
		}
	}

	public function init(Ytnuk\Orm\Entity $entity, Ytnuk\Orm\Repository $repository)
	{
		if ( ! $this->parent) {
			throw new Nextras\Orm\InvalidStateException;
		}
		$this->entity = $entity;
		$this->metadata = $entity->getMetadata();
		$this->repository = $repository;
		$this->mapper = $repository->getMapper();
		$this->model = $repository->getModel();
		$this->setCurrentGroup($this->getForm()
			->addGroup($this->getGroupName()));
		$this->addProperties($this->metadata->getProperties());
	}

	/**
	 * @return string
	 */
	protected function getGroupName()
	{
		return $this->prefix('form.container.group');
	}

	/**
	 * @param string $string
	 *
	 * @return string
	 */
	protected function prefix($string)
	{
		return $this->mapper->getTableName() . '.' . $string;
	}

	/**
	 * @param Nextras\Orm\Entity\Reflection\PropertyMetadata[] $properties
	 */
	protected function addProperties(array $properties)
	{
		foreach ($properties as $property) {
			if (in_array($property->name, $this->metadata->getPrimaryKey()) || $property->isVirtual) {
				continue;
			}
			$this->addProperty($property);
		}
	}

	/**
	 * @param Nextras\Orm\Entity\Reflection\PropertyMetadata $property
	 */
	protected function addProperty(Nextras\Orm\Entity\Reflection\PropertyMetadata $property)
	{
		if ( ! $input = $this->addPropertyInput($property, [$property->name => TRUE])) {
			return;
		}
		if ( ! $property->isNullable) {
			$input->setRequired();
		}
		if ($this->entity->hasValue($property->name)) {
			$value = $this->entity->getValue($property->name);
			$input->setDefaultValue($value instanceof Ytnuk\Orm\Entity ? $value->id : $value);
		}
		$input->setAttribute('placeholder', $this->formatPropertyPlaceholder($property));
	}

	/**
	 * @param Nextras\Orm\Entity\Reflection\PropertyMetadata $property
	 * @param array $types
	 *
	 * @return \Nette\Forms\Controls\SelectBox|NULL
	 */
	protected function addPropertyInput(Nextras\Orm\Entity\Reflection\PropertyMetadata $property, $types = [])
	{
		foreach ($types + $property->types as $type => $value) {
			$method = $this->formatAddPropertyMethod($type);
			if ( ! method_exists($this, $method)) {
				continue;
			}

			return call_user_func([
				$this,
				$method
			], $property);
		}
		if ( ! $property->container || $property->container === Nextras\Orm\Relationships\OneHasMany::class && $property->relationshipRepository === $this->repository->reflection->name) {
			return NULL;
		}
		switch ($property->container) {
			case Nextras\Orm\Relationships\OneHasOneDirected::class:
				$this->addPropertyOneHasOneDirected($property);

				return NULL;
			case Nextras\Orm\Relationships\ManyHasOne::class:
				return $this->addPropertyManyHasOne($property);
			case Nextras\Orm\Relationships\OneHasMany::class:
				$this->addPropertyOneHasMany($property);

				return NULL;
			default:
				throw new Nextras\Orm\NotImplementedException;
		}
	}

	/**
	 * @param string $name
	 *
	 * @return string
	 */
	protected function formatAddPropertyMethod($name)
	{
		return 'addProperty' . ucfirst($name);
	}

	/**
	 * @param Nextras\Orm\Entity\Reflection\PropertyMetadata $property
	 *
	 * @return \Nette\Forms\Container|NULL
	 */
	protected function addPropertyOneHasOneDirected(Nextras\Orm\Entity\Reflection\PropertyMetadata $property)
	{
		if ( ! $property->relationshipRepository || ! $property->relationshipIsMain) {
			return NULL;
		}
		if ($this->entity->hasValue($property->name)) {
			$entity = $this->entity->getValue($property->name);
		} else {
			$repository = $this->model->getRepository($property->relationshipRepository);
			$relationshipEntityClass = $repository->getEntityMetadata()
				->getClassName();
			$entity = new $relationshipEntityClass;
		}

		return $this->addEntityContainer($entity, $property->name);
	}

	/**
	 * @param Ytnuk\Orm\Entity $entity
	 * @param $name
	 *
	 * @return self
	 */
	public function addEntityContainer(Ytnuk\Orm\Entity $entity, $name)
	{
		return $this->addComponent($this->createEntityContainer($entity), $name);
	}

	protected function createEntityContainer(Ytnuk\Orm\Entity $entity)
	{
		$class = rtrim($entity->getMetadata()
				->getClassName(), 'a..zA..Z') . 'Form\Container';
		$repository = $this->model->getRepositoryForEntity($entity);
		$repository->attach($entity);

		return new $class($entity, $repository);
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
		$primaryKeys = $entity->getMetadata()
			->getPrimaryKey();
		$primaryKey = reset($primaryKeys);
		$items = $repository->findAll()
			->fetchPairs($primaryKey, $entity::PROPERTY_NAME);

		return $this->addSelect($property->name, $this->formatPropertyLabel($property), $items)
			->setPrompt($this->formatPropertyPrompt($property));
	}

	/**
	 * @param Nextras\Orm\Entity\Reflection\PropertyMetadata $property
	 *
	 * @return string
	 */
	protected function formatPropertyLabel(Nextras\Orm\Entity\Reflection\PropertyMetadata $property)
	{
		return $this->prefixProperty($property) . '.label';
	}

	/**
	 * @param Nextras\Orm\Entity\Reflection\PropertyMetadata $property
	 *
	 * @return string
	 */
	protected function prefixProperty(Nextras\Orm\Entity\Reflection\PropertyMetadata $property)
	{
		return $this->prefix('entity.' . $property->name);
	}

	/**
	 * @param Nextras\Orm\Entity\Reflection\PropertyMetadata $property
	 *
	 * @return string
	 */
	protected function formatPropertyPrompt(Nextras\Orm\Entity\Reflection\PropertyMetadata $property)
	{
		return $this->prefixProperty($property) . '.placeholder';
	}

	/**
	 * @param Nextras\Orm\Entity\Reflection\PropertyMetadata $property
	 *
	 * @return \Nette\Forms\Container|NULL
	 */
	protected function addPropertyOneHasMany(Nextras\Orm\Entity\Reflection\PropertyMetadata $property)
	{
		$repository = $this->model->getRepository($property->relationshipRepository);
		$relationshipEntityClass = $repository->getEntityMetadata()
			->getClassName();
		$entity = new $relationshipEntityClass;
		$container = $this->addDynamic($property->name, function (Nette\Forms\Container $container) use ($entity, $repository, $property) {
			if ($container instanceof self) {
				$container->init($entity, $repository);
			}
			$container->addSubmit('delete', 'Delete')
				->setValidationScope(FALSE)
				->addRemoveOnClick();
		});
		$container->addSubmit('add', 'Add')//TODO: when filled should recreate with that values, and add next empty one
		->setValidationScope(FALSE)
			->addCreateOnClick();
		$container->containerClass = rtrim($entity->getMetadata()
				->getClassName(), 'a..zA..Z') . 'Form\Container'; //TODO: is it really needed?
		//TODO: fix rendering at end of form
		return $container;
	}

	/**
	 * @param Nextras\Orm\Entity\Reflection\PropertyMetadata $property
	 *
	 * @return string
	 */
	protected function formatPropertyPlaceholder(Nextras\Orm\Entity\Reflection\PropertyMetadata $property)
	{
		return $this->prefixProperty($property) . '.placeholder';
	}

	/**
	 * @param Nextras\Orm\Entity\Reflection\PropertyMetadata $property
	 *
	 * @return \Nette\Forms\Controls\TextInput
	 */
	protected function addPropertyInt(Nextras\Orm\Entity\Reflection\PropertyMetadata $property)
	{
		return $this->addPropertyString($property)
			->setType('number');
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
