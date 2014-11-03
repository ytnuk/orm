<?php

namespace WebEdit\Orm\Form;

use Nextras;
use WebEdit\Form;
use WebEdit\Orm;

/**
 * Class Container
 *
 * @package WebEdit\Database
 */
abstract class Container extends Form\Container
{

	/**
	 * @var Orm\Entity
	 */
	protected $entity;

	/**
	 * @var Nextras\Orm\Entity\Reflection\EntityMetadata
	 */
	protected $metadata;

	/**
	 * @var Orm\Repository
	 */
	protected $repository;

	/**
	 * @var Orm\Mapper
	 */
	protected $mapper;

	/**
	 * @var Orm\Model
	 */
	protected $model;

	/**
	 * @param Orm\Entity $entity
	 * @param Orm\Repository $repository
	 */
	public function __construct(Orm\Entity $entity, Orm\Repository $repository)
	{
		$this->entity = $entity;
		$this->metadata = $entity->getMetadata();
		$this->repository = $repository;
		$this->mapper = $this->repository->getMapper();
		$this->model = $this->repository->getModel();
		$this->monitor(Form::class);
	}

	public function removeEntity()
	{
		foreach ($this->metadata->getProperties() as $property) {
			if ($property->container === Orm\Relationships\OneHasOneDirected::class && $property->relationshipIsMain) {
				$this->getComponent($property->name)
					->removeEntity();
			}
		}
		$this->repository->remove($this->entity);
	}

	/**
	 * @param array $values
	 *
	 * @return Orm\Entity
	 */
	public function setEntityValues(array $values)
	{
		foreach ($values as $property => $value) {
			if (is_array($value)) {
				$value = $this->getComponent($property)
					->setEntityValues($value);
			}
			$this->entity->setValue($property, $value ? : NULL);
		}

		return $this->entity;
	}

	/**
	 * @param Form $form
	 */
	protected function attached($form)
	{
		parent::attached($form);
		$this->setCurrentGroup($form->addGroup($this->getGroupName()));
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
			if (in_array($property->name, $this->metadata->getPrimaryKey())) {
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
			$input->setDefaultValue($value instanceof Database\Entity ? $value->getId() : $value);
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
		switch ($property->container) {
			case Nextras\Orm\Relationships\OneHasOneDirected::class:
				$this->addPropertyOneHasOneDirected($property);
				break;
			case Nextras\Orm\Relationships\ManyHasOne::class:
				return $this->addPropertyManyHasOne($property);
				break;
		}

		return NULL;
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
	 * @param Orm\Entity $entity
	 * @param string $name
	 *
	 * @return self
	 */
	public function addEntityContainer(Orm\Entity $entity, $name)
	{
		$class = rtrim($entity->getMetadata()
				->getClassName(), 'a..zA..Z') . 'Form\Container';
		$repository = $this->model->getRepositoryForEntity($entity);
		$repository->attach($entity);

		return $this->addComponent(new $class($entity, $repository), $name);
	}

	/**
	 * @param Nextras\Orm\Entity\Reflection\PropertyMetadata $property
	 *
	 * @return \Nette\Forms\Controls\SelectBox
	 */
	protected function addPropertyManyHasOne(Nextras\Orm\Entity\Reflection\PropertyMetadata $property)
	{
		return $this->addSelect($property->name, $this->formatPropertyLabel($property), [])
			->setPrompt($this->formatPropertyPrompt($property))
			->setItems($this->repository->findBy(['id!=' => $this->entity->getId()])
				->fetchPairs('id', 'title'));
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