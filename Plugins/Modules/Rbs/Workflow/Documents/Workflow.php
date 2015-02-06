<?php
/**
 * Copyright (C) 2014 Ready Business System
 *
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/.
 */
namespace Rbs\Workflow\Documents;

use Change\Workflow\Validator;

/**
 * @name \Rbs\Workflow\Documents\Workflow
 */
class Workflow extends \Compilation\Rbs\Workflow\Documents\Workflow implements \Change\Workflow\Interfaces\Workflow
{

	/**
	 * @var array
	 */
	protected $items;

	/**
	 * Return Short name
	 * @return string
	 */
	public function getName()
	{
		return $this->getLabel();
	}

	/**
	 * @return \DateTime|null
	 */
	public function getStartDate()
	{
		return $this->getStartActivation();
	}

	/**
	 * @return \DateTime|null
	 */
	public function getEndDate()
	{
		return $this->getEndActivation();
	}

	/**
	 * @return string
	 */
	public function startTask()
	{
		return $this->getStartTask();
	}

	/**
	 * Return all Workflow items defined
	 * @return \Change\Workflow\Interfaces\WorkflowItem[]
	 */
	public function getItems()
	{
		if ($this->items === null)
		{
			$s = new \Rbs\Workflow\Std\Serializer();
			$this->items = $s->unserializeItems($this, $this->getItemsData());
		}
		return $this->items;
	}

	/**
	 * @param boolean $identify
	 * @return \Rbs\Workflow\Std\Place
	 */
	public function getNewPlace($identify = true)
	{
		$place = new \Rbs\Workflow\Std\Place($this);
		if ($identify)
		{
			$place->setId($this->nextId());
			$this->addItem($place);
		}
		return $place;
	}

	/**
	 * @param boolean $identify
	 * @return \Rbs\Workflow\Std\Transition
	 */
	public function getNewTransition($identify = true)
	{
		$transition =  new \Rbs\Workflow\Std\Transition($this);
		if ($identify)
		{
			$transition->setId($this->nextId());
			$this->addItem($transition);
		}
		return $transition;
	}

	/**
	 * @param boolean $identify
	 * @return \Rbs\Workflow\Std\Arc
	 */
	public function getNewArc($identify = true)
	{
		$arc = new \Rbs\Workflow\Std\Arc($this);
		if ($identify)
		{
			$arc->setId($this->nextId());
			$this->addItem($arc);
		}
		return $arc;
	}

	/**
	 * @return integer
	 */
	public function nextId()
	{
		$lastId = 0;
		foreach ($this->getItems() as $item)
		{
			if ($item instanceof \Change\Workflow\Interfaces\WorkflowItem)
			{
				$lastId = max($lastId, $item->getId());
			}
		}
		return $lastId + 1;
	}

	/**
	 * @param integer $id
	 * @return \Change\Workflow\Interfaces\WorkflowItem|null
	 */
	public function getItemById($id)
	{
		if ($id !== null)
		{
			foreach ($this->getItems() as $item)
			{
				if ($item instanceof \Change\Workflow\Interfaces\WorkflowItem && $item->getId() === $id)
				{
					return $item;
				}
			}
		}
		return null;
	}

	/**
	 * @param \Change\Workflow\Interfaces\WorkflowItem $item
	 * @throws \RuntimeException
	 * @return $this
	 */
	public function addItem(\Change\Workflow\Interfaces\WorkflowItem $item)
	{
		$items = $this->getItems();
		if (!in_array($item, $items, true))
		{
			if ($item->getWorkflow() !== $this)
			{
				throw new \RuntimeException('Invalid item Workflow', 999999);
			}

			if (!$item->getId())
			{
				throw new \RuntimeException('Empty item Id', 999999);
			}
			if (!is_int($item->getId()) || $this->getItemById($item->getId()) !== null)
			{
				throw new \RuntimeException('Invalid item Id', 999999);
			}
			$items[] = $item;
			$this->setItems($items);
		}
		return $this;
	}

	/**
	 * @param \Change\Workflow\Interfaces\WorkflowItem[] $items
	 */
	protected function setItems(array $items)
	{
		$this->items = $items;
	}

	/**
	 * @return $this
	 */
	protected function serializeItems()
	{
		if ($this->items !== null)
		{
			$s = new \Rbs\Workflow\Std\Serializer();
			$array = $s->serializeItems($this->items);
			$this->setItemsData(count($array) ? $array : null);
		}
		return $this;
	}

	/**
	 * @return boolean
	 */
	public function isValid()
	{
		$validator = new Validator();
		try
		{
			$validator->isValid($this);
		}
		catch (\Exception $e)
		{
			$this->setErrors($e->getMessage());
			return false;
		}
		$this->setErrors(null);
		return true;
	}

	public function reset()
	{
		parent::reset();
		$this->items = null;
	}

	protected function onCreate()
	{
		$this->serializeItems();
	}

	protected function onUpdate()
	{
		$this->serializeItems();
	}

	/**
	 * @return \Rbs\Workflow\Documents\WorkflowInstance
	 */
	public function createWorkflowInstance()
	{
		/* @var $workflowInstance \Rbs\Workflow\Documents\WorkflowInstance */
		$workflowInstance = $this->getDocumentManager()->getNewDocumentInstanceByModelName('Rbs_Workflow_WorkflowInstance');
		$workflowInstance->setWorkflow($this);
		return $workflowInstance;
	}

	/**
	 * @param \Change\Documents\Events\Event $event
	 */
	public function onDefaultUpdateRestResult(\Change\Documents\Events\Event $event)
	{
		parent::onDefaultUpdateRestResult($event);

		$document = $event->getDocument();
		if (!$document instanceof Workflow)
		{
			return;
		}

		$restResult = $event->getParam('restResult');
		if ($restResult instanceof \Change\Http\Rest\V1\Resources\DocumentResult)
		{
			$restResult->removeRelAction('delete');
		}
		elseif ($restResult instanceof \Change\Http\Rest\V1\Resources\DocumentLink)
		{
			$restResult->removeRelAction('delete');
		}
	}
}