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
		$this->repository->persist($this->entity, TRUE);
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
	 * @param Ytnuk\Form $form
	 */
	protected function attached($form)
	{
		parent::attached($form);
		$this->setCurrentGroup($this->getForm()->addGroup($this->formatGroupCaption()));
		$this->addProperties($this->metadata->getProperties());
	}

	/**
	 * @return string
	 */
	protected function formatGroupCaption()
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
		foreach ($properties as $property) {
			if (in_array($property->name, $this->metadata->getPrimaryKey()) || $property->isVirtual) {
				continue;
			}
			if (is_subclass_of($property->container, Nextras\Orm\Relationships\HasOne::class)) {
				if ($container = $this->lookup(self::class, FALSE)) {
					$path = $this->lookupPath(self::class, FALSE);
					if ($property->relationshipProperty === substr($path, 0, strpos($path, '-')) && $property->relationshipRepository === get_class($container->getRepository())) {
						$this->entity->setValue($property->name, $container->getEntity());
						continue;
					}
				}
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
			$input->setDefaultValue($this->entity->getRawValue($property->name));
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
			$method = 'addProperty' . ucfirst($type);
			if ( ! method_exists($this, $method)) {
				continue;
			}

			return call_user_func([
				$this,
				$method
			], $property);
		}
		if ( ! $property->container || ($property->container === Nextras\Orm\Relationships\OneHasMany::class && $property->relationshipRepository === $this->repository->getReflection()->getName())) {
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
	protected function prefixProperty(Nextras\Orm\Entity\Reflection\PropertyMetadata $property)
	{
		return $this->prefix(implode('.', [
			'entity',
			$property->name
		]));
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
		$repository = $this->model->getRepository($property->relationshipRepository);
		$collection = $this->entity->getValue($property->name)->getIterator();
		$container = $this->addDynamic($property->name, function (Nette\Forms\Container $container) use ($property, $repository, $collection) {
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
		$container->addSubmit('add', $this->formatPropertyAction($property, 'add'))->setValidationScope(FALSE)->addCreateOnClick();

		//TODO: fix rendering at end of form + add groups relative to property->name from outside
		return $container;
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
