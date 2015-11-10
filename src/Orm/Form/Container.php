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

	public function __construct(
		Ytnuk\Orm\Entity $entity,
		Ytnuk\Orm\Repository $repository
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

	public function getEntity() : Ytnuk\Orm\Entity
	{
		return $this->entity;
	}

	public function getRepository() : Ytnuk\Orm\Repository
	{
		return $this->repository;
	}

	public function getMetadata() : Nextras\Orm\Entity\Reflection\EntityMetadata
	{
		return $this->metadata;
	}

	public function persistEntity(bool $flush = TRUE)
	{
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
			$this->entity->setValue(
				$property,
				$value
			);
		}

		return $this->relations;
	}

	public function removeEntity(bool $flush = TRUE)
	{
		$this->repository->remove(
			$this->entity,
			TRUE
		);
		if ($flush) {
			$this->repository->flush();
		}
	}

	public function lookupSelf(bool $need = TRUE)
	{
		return $this->lookup(
			self::class,
			$need
		);
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
				if ($this[$property] instanceof Nette\Forms\Controls\Checkbox && ! $value && $this->metadata->getProperty(
						$property
					)->isNullable
				) {
					$value = NULL;
				}
				$this->entity->setValue(
					$property,
					$value
				);
			}
		}

		return parent::setValues(
			$values,
			$erase
		);
	}

	protected function prefixContainer(string $string) : string
	{
		return $this->prefix(
			implode(
				'.',
				[
					'form',
					'container',
					$string,
				]
			)
		);
	}

	protected function prefix(string $string) : string
	{
		return implode(
			'.',
			[
				str_replace(
					'_',
					'.',
					$this->mapper->getTableName()
				),
				$string,
			]
		);
	}

	protected function addProperties(array $properties)
	{
		$path = $this->lookupPath(
			self::class,
			FALSE
		);
		$delimiter = strpos(
			$path,
			'-'
		);
		if ($delimiter !== FALSE) {
			$path = substr(
				$path,
				0,
				$delimiter
			);
		}
		$parent = $this->lookupSelf(FALSE);
		foreach (
			$properties as $metadata
		) {
			if (in_array(
				$metadata->name,
				$this->metadata->getPrimaryKey()
			)) {
				continue;
			}
			if ($path && $parent && is_subclass_of(
					$metadata->container,
					Nextras\Orm\Relationships\HasOne::class
				)
			) {
				if ($metadata->relationship && $metadata->relationship->property === $path && $metadata->relationship->repository === get_class(
						$parent->getRepository()
					)
				) {
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
				$component->setAttribute(
					'placeholder',
					$this->formatPropertyPlaceholder($metadata)
				);
				break;
			case $component instanceof Nette\ComponentModel\IContainer:
				if ($metadata->isReadonly) {
					foreach (
						$component->getComponents(
							TRUE,
							Nette\Forms\Controls\BaseControl::class
						) as $control
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
					substr(
						$metadata->container,
						strrpos(
							$metadata->container,
							'\\'
						) + 1
					) => TRUE,
				]) as $type => $value
		) {
			$method = 'addProperty' . ucfirst($type);
			if ( ! method_exists(
				$this,
				$method
			)
			) {
				continue;
			}

			return call_user_func(
				[
					$this,
					$method,
				],
				$metadata
			);
		}

		return NULL;
	}

	protected function formatPropertyPlaceholder(Nextras\Orm\Entity\Reflection\PropertyMetadata $metadata) : string
	{
		return implode(
			'.',
			[
				$this->prefixProperty($metadata),
				'placeholder',
			]
		);
	}

	protected function prefixProperty(Nextras\Orm\Entity\Reflection\PropertyMetadata $metadata) : string
	{
		return $this->prefixContainer(
			implode(
				'.',
				[
					'property',
					$metadata->name,
				]
			)
		);
	}

	protected function addPropertyOneHasOne(
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

		return $this->addComponent(
			$this->form->createComponent($entity),
			$metadata->name
		);
	}

	protected function addPropertyManyHasOne(Nextras\Orm\Entity\Reflection\PropertyMetadata $metadata) : Nette\Forms\Controls\SelectBox
	{
		$repository = $this->model->getRepository($metadata->relationship->repository);
		if ( ! isset(self::$manyHasOneItems[$metadata->relationship->repository])) {
			self::$manyHasOneItems[$metadata->relationship->repository] = $repository->findAll()->fetchPairs(
				current($repository->getEntityMetadata()->getPrimaryKey())
			);
		}
		$items = self::$manyHasOneItems[$metadata->relationship->repository];
		if ($container = $this->lookupSelf(FALSE)) {
			if ($container->getRepository() === $repository && $entity = $container->getEntity()) {
				if ($entity->id) {
					unset($items[$entity->id]);
				}
			}
		}

		return $this->addSelect(
			$metadata->name,
			$this->formatPropertyLabel($metadata),
			$items
		)->setPrompt($this->formatPropertyPrompt($metadata));
	}

	protected function formatPropertyLabel(Nextras\Orm\Entity\Reflection\PropertyMetadata $metadata) : string
	{
		return implode(
			'.',
			[
				$this->prefixProperty($metadata),
				'label',
			]
		);
	}

	protected function formatPropertyPrompt(Nextras\Orm\Entity\Reflection\PropertyMetadata $metadata) : string
	{
		return implode(
			'.',
			[
				$this->prefixProperty($metadata),
				'placeholder',
			]
		);
	}

	protected function addPropertyOneHasMany(
		Nextras\Orm\Entity\Reflection\PropertyMetadata $metadata,
		int $forceDefault = 0
	) : Kdyby\Replicator\Container
	{
		$this->setCurrentGroup(
			$this->getForm()->addGroup(
				$this->prefixPropertyGroup($metadata),
				FALSE
			)
		);
		$repository = $this->model->getRepository($metadata->relationship->repository);
		$collection = $this->entity->getValue($metadata->name)->get()->fetchPairs(
			current($repository->getEntityMetadata()->getPrimaryKey())
		);
		$replicator = $this->addDynamic(
			$metadata->name,
			function (Nette\Forms\Container $container) use
			(
				$metadata,
				$repository,
				$collection
			) {
				$replicator = $container->parent;
				$name = $container->getName();
				unset($container->parent[$name]);
				if (isset($collection[$name])) {
					$entity = $collection[$name];
				} else {
					$entityClassName = $repository->getEntityMetadata()->getClassName();
					$entity = new $entityClassName;
				}
				$replicator->addComponent(
					$container = $this->form->createComponent($entity),
					$name
				);
				if ($container instanceof Nette\Forms\Container) {
					$container->addSubmit(
						'delete',
						$this->formatPropertyAction(
							$metadata,
							'delete'
						)
					)->addRemoveOnClick(
						function (
							Kdyby\Replicator\Container $replicator,
							self $container
						) {
							$container->removeEntity();
						}
					);
				}
			}
		);
		if ($createDefault = max(
			count($collection),
			$forceDefault
		)
		) {
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
		$add = $replicator->addSubmit(
			'add',
			$this->formatPropertyAction(
				$metadata,
				'add'
			)
		);
		$add->setValidationScope([$replicator]);
		$add->addCreateOnClick();
		$replicator->getCurrentGroup()->add($add);
		if ($add->isSubmittedBy()) {
			$isValid = TRUE;
			if ($scope = $add->getValidationScope()) {
				$isValid = ! array_filter(
					$scope,
					function (Nette\Forms\Container $container) {
						return ! $container->isValid();
					}
				);
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
			if (isset($container['delete'])) {
				if ($container['delete'] instanceof Nette\Forms\Controls\SubmitButton && ! $container['delete']->isSubmittedBy()) {
					$containers[$container->name] = $container;
				}
			}
		}
		if (count($containers) <= $forceDefault) {
			array_map(
				function (self $container) {
					unset($container['delete']);
				},
				$containers
			);
		} else {
			$persistedContainers = array_filter(
				$containers,
				function (self $container) {
					return $container->getEntity()->isPersisted();
				}
			);
			if (count($persistedContainers) <= $forceDefault) {
				array_map(
					function (self $container) {
						unset($container['delete']);
					},
					$persistedContainers
				);
			}
		}
		foreach (
			$containers as $key => $container
		) {
			if ($container instanceof Nette\ComponentModel\IContainer) {
				foreach (
					$container->getComponents(
						FALSE,
						Nette\Forms\Controls\BaseControl::class
					) as $control
				) {
					if ($control instanceof Nette\Forms\Controls\BaseControl && $unique = $control->getOption('unique')) {
						foreach (
							array_diff_key(
								$containers,
								[$key => $container]
							) as $sibling
						) {
							$condition = $control->addCondition(Nette\Forms\Form::FILLED);
							if (is_string($unique) && isset($container[$unique]) && isset($sibling[$unique]) && $uniqueControl = $container[$unique]) {
								if ($uniqueControl instanceof Nette\Forms\IControl) {
									$condition = $condition->addConditionOn(
										$uniqueControl,
										Nette\Forms\Form::EQUAL,
										$sibling[$unique]
									);
								}
							}
							$condition->addRule(
								Nette\Forms\Form::NOT_EQUAL,
								NULL,
								$sibling[$control->name]
							);
						}
					}
				}
			}
		}

		return $replicator;
	}

	protected function prefixPropertyGroup(Nextras\Orm\Entity\Reflection\PropertyMetadata $metadata) : string
	{
		return implode(
			'.',
			[
				$this->prefixProperty($metadata),
				'group',
			]
		);
	}

	protected function formatPropertyAction(
		Nextras\Orm\Entity\Reflection\PropertyMetadata $metadata,
		string $action
	) : string
	{
		return implode(
			'.',
			[
				$this->prefixProperty($metadata),
				'action',
				$action,
			]
		);
	}

	protected function addPropertyInt(Nextras\Orm\Entity\Reflection\PropertyMetadata $metadata) : Nette\Forms\Controls\TextInput
	{
		return $this->addPropertyString($metadata)->setType('number');
	}

	protected function addPropertyString(Nextras\Orm\Entity\Reflection\PropertyMetadata $metadata) : Nette\Forms\Controls\TextInput
	{
		return $this->addText(
			$metadata->name,
			$this->formatPropertyLabel($metadata)
		);
	}

	protected function addPropertyBool(Nextras\Orm\Entity\Reflection\PropertyMetadata $metadata) : Nette\Forms\Controls\Checkbox
	{
		return $this->addCheckbox(
			$metadata->name,
			$this->formatPropertyLabel($metadata)
		);
	}
}
