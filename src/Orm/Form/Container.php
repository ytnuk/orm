<?php
namespace Ytnuk\Orm\Form;

use Kdyby;
use Nette;
use Nextras;
use Ytnuk;

abstract class Container
	extends Ytnuk\Form\Container
{

	/**
	 * @var array
	 */
	private static $manyHasOneItems = [];

	/**
	 * @var Nextras\Orm\Entity\IEntity
	 */
	private $entity;

	/**
	 * @var Nextras\Orm\Repository\IRepository
	 */
	private $repository;

	/**
	 * @var Nextras\Orm\Entity\Reflection\EntityMetadata
	 */
	private $metadata;

	/**
	 * @var Nextras\Orm\Mapper\IMapper
	 */
	private $mapper;

	/**
	 * @var Nextras\Orm\Model\IModel
	 */
	private $model;

	/**
	 * @var Nextras\Orm\Entity\IEntity[]
	 */
	private $relations = [];

	public function __construct(
		Nextras\Orm\Entity\IEntity $entity,
		Nextras\Orm\Repository\IRepository $repository
	) {
		parent::__construct();
		$this->entity = $entity;
		$this->repository = $repository;
		$this->metadata = $entity->getMetadata();
		$this->mapper = $repository->getMapper();
		$this->model = $repository->getModel();
		$this->monitor(Ytnuk\Orm\Form::class);
		$repository->attach($entity);
	}

	public function getEntity() : Nextras\Orm\Entity\IEntity
	{
		return $this->entity;
	}

	public function getRepository() : Nextras\Orm\Repository\IRepository
	{
		return $this->repository;
	}

	public function getMetadata() : Nextras\Orm\Entity\Reflection\EntityMetadata
	{
		return $this->metadata;
	}

	public function persistEntity(bool $flush = FALSE)
	{
		$containers = iterator_to_array($this->getComponents(TRUE, self::class));
		array_walk($containers, function (self $container) {
			$container->initEntityRelations();
		});
		$this->setValues($this->getValues());
		$this->repository->persist($this->entity);
		if ($flush) {
			$this->repository->flush();
		}
	}

	public function initEntityRelations() : array
	{
		foreach (
			$this->relations as $property => $value
		) {
			$this->entity->setValue($property, $value);
			unset($this->relations[$property]);
		}

		return $this->relations;
	}

	public function removeEntity(bool $flush = FALSE)
	{
		$this->repository->remove($this->entity, TRUE);
		if ($flush) {
			$this->repository->flush();
		}
	}

	protected function attached($form)
	{
		parent::attached($form);
		$this->setCurrentGroup($this->getForm()->addGroup($this->prefixContainer('group')));
		$this->addProperties($this->metadata->getProperties());
	}

	public function removeComponent(Nette\ComponentModel\IComponent $component)
	{
		parent::removeComponent($component);
		if ($component instanceof self) {
			$component->initEntityRelations();
		}
	}

	public function setValues(
		$values,
		$erase = FALSE
	) : self
	{
		$this->initEntityRelations();
		foreach (
			$values as $property => $value
		) {
			if ($this[$property] instanceof Nette\Forms\IControl) {
				$value = $value === '' ? NULL : $value;
				if ($this[$property] instanceof Nette\Forms\Controls\Checkbox && ! $value && $this->metadata->getProperty($property)->isNullable) {
					$value = NULL;
				}
				$this->entity->setValue($property, $value);
			}
		}

		return parent::setValues($values, $erase);
	}

	protected function prefixContainer(string $string) : string
	{
		return $this->prefix(implode('.', [
			'form',
			'container',
			$string,
		]));
	}

	protected function prefix(string $string) : string
	{
		return implode('.', [
			str_replace('_', '.', $this->mapper->getTableName()),
			$string,
		]);
	}

	protected function addProperties(array $properties)
	{
		$path = $this->lookupPath(self::class, FALSE);
		$delimiter = strpos($path, '-');
		if ($delimiter !== FALSE) {
			$path = substr($path, 0, $delimiter);
		}
		$parent = $this->lookup(self::class, FALSE);
		if ($path && $parent instanceof self) {
			$parentProperty = $parent->getMetadata()->getProperty($path);
			if ($parentProperty->relationship && ! $parentProperty->relationship->property) {
				$parent->relations[$path] = $this->getEntity();
			}
		}
		foreach (
			$properties as $metadata
		) {
			if (in_array($metadata->name, $this->metadata->getPrimaryKey())) {
				continue;
			}
			if ($path && $parent instanceof self && is_subclass_of($metadata->container, Nextras\Orm\Relationships\HasOne::class)) {
				if ($metadata->relationship && $metadata->relationship->property === $path && $metadata->relationship->repository === get_class($parent->getRepository())) {
					$this->relations[$metadata->name] = $parent->getEntity();
					continue;
				}
			}
			$this->addProperty($metadata);
		}
	}

	protected function addProperty(Nextras\Orm\Entity\Reflection\PropertyMetadata $metadata)
	{
		$component = $this->addPropertyComponent($metadata);
		switch (TRUE) {
			case $component instanceof Nette\Forms\Controls\BaseControl:
				if ( ! $component instanceof Nette\Forms\Controls\Checkbox) {
					$component->setRequired(! $metadata->isNullable);
				}
				$component->setDisabled($metadata->isReadonly);
				$component->setDefaultValue($this->entity->getRawValue($metadata->name));
				$component->setAttribute('placeholder', $this->formatPropertyPlaceholder($metadata));
				break;
			case $component instanceof Nette\ComponentModel\IContainer:
				if ($metadata->isReadonly) {
					foreach (
						$component->getComponents(TRUE, Nette\Forms\Controls\BaseControl::class) as $control
					) {
						$value = $control->getValue();
						$control->setDisabled();
						$control->setDefaultValue($value);
					}
				}
				break;
		}

		return $component;
	}

	protected function addPropertyComponent(Nextras\Orm\Entity\Reflection\PropertyMetadata $metadata)
	{
		foreach (
			[$metadata->name => TRUE] + ($metadata->isVirtual ? [] : $metadata->types + [
					substr($metadata->container, strrpos($metadata->container, '\\') + 1) => TRUE,
				]) as $type => $value
		) {
			$method = 'createComponent' . ucfirst($type);
			if ( ! method_exists($this, $method)) {
				continue;
			}

			return call_user_func([
				$this,
				$method,
			], $metadata);
		}

		return NULL;
	}

	protected function formatPropertyPlaceholder(Nextras\Orm\Entity\Reflection\PropertyMetadata $metadata) : string
	{
		return implode('.', [
			$this->prefixProperty($metadata),
			'placeholder',
		]);
	}

	protected function prefixProperty(Nextras\Orm\Entity\Reflection\PropertyMetadata $metadata) : string
	{
		return $this->prefixContainer(implode('.', [
			'property',
			$metadata->name,
		]));
	}

	protected function createComponentOneHasOne(
		Nextras\Orm\Entity\Reflection\PropertyMetadata $metadata,
		bool $force = FALSE
	) {
		if ( ! $force && ! $metadata->relationship->isMain) {
			return NULL;
		}
		if ($this->entity->hasValue($metadata->name)) {
			$entity = $this->entity->getValue($metadata->name);
		} else {
			$repository = $this->model->getRepository($metadata->relationship->repository);
			$relationshipEntityClass = $repository->getEntityMetadata()->getClassName();
			$entity = new $relationshipEntityClass;
		}

		return $this->addComponent($this->form->createComponent($entity), $metadata->name);
	}

	protected function createComponentManyHasOne(Nextras\Orm\Entity\Reflection\PropertyMetadata $metadata) : Nette\Forms\Controls\SelectBox
	{
		$repository = $this->model->getRepository($metadata->relationship->repository);
		if ( ! isset(self::$manyHasOneItems[$metadata->relationship->repository])) {
			self::$manyHasOneItems[$metadata->relationship->repository] = $repository->findAll()->fetchPairs(current($repository->getEntityMetadata()->getPrimaryKey()));
		}
		$items = self::$manyHasOneItems[$metadata->relationship->repository];
		$container = $this->lookup(self::class, FALSE);
		if ($container instanceof self && $container->getRepository() === $repository && $entity = $container->getEntity()) {
			if ($id = $entity->getPersistedId()) {
				unset($items[$id]);
			}
		}

		return $this->addSelect($metadata->name, $this->formatPropertyLabel($metadata), $items)->setPrompt($this->formatPropertyPrompt($metadata));
	}

	protected function formatPropertyLabel(Nextras\Orm\Entity\Reflection\PropertyMetadata $metadata) : string
	{
		return implode('.', [
			$this->prefixProperty($metadata),
			'label',
		]);
	}

	protected function formatPropertyPrompt(Nextras\Orm\Entity\Reflection\PropertyMetadata $metadata) : string
	{
		return implode('.', [
			$this->prefixProperty($metadata),
			'placeholder',
		]);
	}

	protected function createComponentOneHasMany(
		Nextras\Orm\Entity\Reflection\PropertyMetadata $metadata,
		int $forceDefault = 0
	) : Kdyby\Replicator\Container
	{
		$repository = $this->model->getRepository($metadata->relationship->repository);
		$collection = $this->entity->getValue($metadata->name)->get()->fetchPairs(current($repository->getEntityMetadata()->getPrimaryKey()));
		$replicator = new Kdyby\Replicator\Container(function (Nette\Forms\Container $container) use
		(
			$metadata,
			$repository,
			$collection
		) {
			$replicator = $container->parent;
			$name = $container->getName();
			unset($container->parent[$name]);
			if ( ! $entity = $collection[$name] ?? NULL) {
				$entityClassName = $repository->getEntityMetadata()->getClassName();
				$entity = new $entityClassName;
			}
			$replicator->addComponent($container = $this->form->createComponent($entity), $name);
			if ($container instanceof Nette\Forms\Container) {
				$container->addSubmit('delete', $this->formatPropertyAction($metadata, 'delete'))->addRemoveOnClick(function (
					Kdyby\Replicator\Container $replicator,
					self $container
				) {
					$container->removeEntity(TRUE);
				});
			}
		});
		$add = $replicator->addSubmit('add', $this->formatPropertyAction($metadata, 'add'));
		call_user_func([
			$add,
			'addCreateOnClick',
		]);
		$add->setValidationScope([$replicator]);
		$replicator->setCurrentGroup(($this->getForm()->addGroup($this->prefixPropertyGroup($metadata), FALSE)->add($add)));
		$this[$metadata->name] = $replicator;
		if ($createDefault = max(count($collection), $forceDefault)) {
			if ( ! $this->getForm()->isSubmitted()) {
				$count = 0;
				while ($count++ < $createDefault) {
					$replicator->createOne(key($collection));
					next($collection);
				}
			} elseif ($forceDefault) {
				while (iterator_count($replicator->getContainers()) < $createDefault) {
					$replicator->createOne();
				}
			}
		}
		if ($add->isSubmittedBy()) {
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
		foreach (
			$replicator->getContainers() as $container
		) {
			$delete = $container['delete'] ?? NULL;
			if ($delete instanceof Nette\Forms\Controls\SubmitButton && ! $delete->isSubmittedBy()) {
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
		foreach (
			$containers as $key => $container
		) {
			if ($container instanceof Nette\ComponentModel\IContainer) {
				foreach (
					$container->getComponents(FALSE, Nette\Forms\Controls\BaseControl::class) as $control
				) {
					if ($control instanceof Nette\Forms\Controls\BaseControl && $unique = $control->getOption('unique')) {
						foreach (
							array_diff_key($containers, [$key => $container]) as $sibling
						) {
							$condition = $control->addCondition(Nette\Forms\Form::FILLED);
							if (is_string($unique) && isset($sibling[$unique]) && $uniqueControl = $container[$unique] ?? NULL) {
								if ($uniqueControl instanceof Nette\Forms\IControl) {
									$condition = $condition->addConditionOn($uniqueControl, Nette\Forms\Form::EQUAL, $sibling[$unique]);
								}
							}
							$condition->addRule(Nette\Forms\Form::NOT_EQUAL, NULL, $sibling[$control->name]);
						}
					}
				}
			}
		}

		return $replicator;
	}

	protected function prefixPropertyGroup(Nextras\Orm\Entity\Reflection\PropertyMetadata $metadata) : string
	{
		return implode('.', [
			$this->prefixProperty($metadata),
			'group',
		]);
	}

	protected function formatPropertyAction(
		Nextras\Orm\Entity\Reflection\PropertyMetadata $metadata,
		string $action
	) : string
	{
		return implode('.', [
			$this->prefixProperty($metadata),
			'action',
			$action,
		]);
	}

	protected function createComponentInt(Nextras\Orm\Entity\Reflection\PropertyMetadata $metadata) : Nette\Forms\Controls\TextInput
	{
		return $this->createComponentString($metadata)->setType('number');
	}

	protected function createComponentString(Nextras\Orm\Entity\Reflection\PropertyMetadata $metadata) : Nette\Forms\Controls\TextInput
	{
		return $this->addText($metadata->name, $this->formatPropertyLabel($metadata));
	}

	protected function createComponentBool(Nextras\Orm\Entity\Reflection\PropertyMetadata $metadata) : Nette\Forms\Controls\Checkbox
	{
		return $this->addCheckbox($metadata->name, $this->formatPropertyLabel($metadata));
	}

	protected function render(
		Nette\Forms\Form $form,
		string $file = NULL
	) {
		if ( ! $file) {
			return NULL;
		}
		$presenter = $form->lookup(Nette\Application\UI\Presenter::class, FALSE);
		if ($presenter instanceof Nette\Application\UI\Presenter) {
			$template = $presenter->getTemplateFactory()->createTemplate($form->lookup(Nette\Application\UI\Control::class, FALSE));
			if ( ! $template instanceof Nette\Bridges\ApplicationLatte\Template) {
				return NULL;
			}
			$template->setFile($file);
			$template->setParameters([
				'form' => $form,
				'_form' => $this,
			]);

			return (string) $template;
		}

		return NULL;
	}
}
