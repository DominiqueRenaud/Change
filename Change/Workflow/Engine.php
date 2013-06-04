<?php
namespace Change\Workflow;

/**
 * @name \Change\Workflow\Engine
 */
class Engine
{
	/**
	 * @var \DateTime
	 */
	protected $dateTime;

	/**
	 * @var Interfaces\WorkflowInstance
	 */
	protected $workflowInstance;

	/**
	 * @param Interfaces\WorkflowInstance $workflowInstance
	 * @param \DateTime $dateTime
	 */
	function __construct(Interfaces\WorkflowInstance $workflowInstance, \DateTime $dateTime = null)
	{
		$this->workflowInstance = $workflowInstance;
		$this->dateTime = ($dateTime === null) ? new \DateTime() : $dateTime;
	}

	/**
	 * @return \Change\Workflow\Interfaces\Place|null
	 */
	public function getStartPlace()
	{
		$workflow = $this->workflowInstance->getWorkflow();
		foreach ($workflow->getItems() as $item)
		{
			if ($item instanceof \Change\Workflow\Interfaces\Place &&
				$item->getType() === \Change\Workflow\Interfaces\Place::TYPE_START)
			{
				return $item;
			}
		}
		return null;
	}

	/**
	 * @param Interfaces\Place $place
	 * @return Interfaces\Token
	 */
	public function enableToken($place)
	{
		if ($this->workflowInstance->getWorkflow() !== $place->getWorkflow())
		{
			throw new \RuntimeException('Invalid Place Workflow', 999999);
		}

		$token = $this->workflowInstance->createToken($place);
		$token->enable($this->dateTime);
		$place = $token->getPlace();
		if ($place->getType() === Interfaces\Place::TYPE_END)
		{
			$token->consume($this->dateTime);
			$this->workflowInstance->close();
			return $token;
		}
		$arcs = $place->getWorkflowOutputItems();
		foreach ($arcs as $arc)
		{
			/* @var $arc Interfaces\Arc */
			$transition = $arc->getTransition();
			$places = $this->getTransitionInputPlaces($transition);
			$inputTokens = array();
			foreach ($places as $inputPlace)
			{
				$inputToken = $this->getFreeTokenForPlace($this->workflowInstance, $inputPlace);
				if ($inputToken)
				{
					$inputTokens[] = $inputToken;
				}
			}
			if (($arc->getType() === Interfaces\Arc::TYPE_AND_JOIN && count($inputTokens) === count($places))
				|| ($arc->getType() !== Interfaces\Arc::TYPE_AND_JOIN && count($inputTokens))
			)
			{
				$this->enableTransition($transition);
			}
		}
		return $token;
	}

	/**
	 * @param string $taskId
	 * @return Interfaces\WorkItem|null
	 */
	public function getEnabledWorkItemByTaskId($taskId)
	{
		foreach ($this->workflowInstance->getItems() as $item)
		{
			if ($item instanceof Interfaces\WorkItem  && $item->getStatus() === Interfaces\WorkItem::STATUS_ENABLED)
			{
				if ($item->getTaskId() == $taskId)
				{
					return $item;
				}
			}
		}
		return null;
	}

	/**
	 * @param Interfaces\WorkItem $workItem
	 */
	public function firedWorkItem($workItem)
	{
		if ($this->workflowInstance !== $workItem->getWorkflowInstance())
		{
			throw new \RuntimeException('Invalid WorkItem Workflow Instance', 999999);
		}
		if ($workItem->fire())
		{
			$this->finishWorkItem($workItem);
		}
		else
		{
			$workItem->cancel($this->dateTime);
			$this->workflowInstance->cancel();
		}
	}

	/**
	 * @param Interfaces\Transition $transition
	 * @return Interfaces\WorkItem
	 */
	protected function enableTransition($transition)
	{
		$workItem = $this->workflowInstance->createWorkItem($transition);
		$workItem->enable($this->dateTime);
		if ($workItem->getTransitionTrigger() === Interfaces\Transition::TRIGGER_AUTO)
		{
			$this->firedWorkItem($workItem);
		}
		return $workItem;
	}



	/**
	 * @param Interfaces\WorkItem $workItem
	 */
	protected function finishWorkItem($workItem)
	{
		$workItem->finish($this->dateTime);


		$transition = $workItem->getTransition();
		$places = $this->getTransitionInputPlaces($transition);
		foreach ($places as $place)
		{
			$token = $this->getFreeTokenForPlace($this->workflowInstance, $place);
			if ($token)
			{
				$this->consumeToken($token);
			}
		}

		$arcs = $transition->getWorkflowOutputItems();
		if (count($arcs) > 1)
		{
			/* @var $arc Interfaces\Arc */
			$arc = $arcs[0];
			if ($arc->getType() === Interfaces\Arc::TYPE_EXPLICIT_OR_SPLIT)
			{
				$selectedArc = null;
				foreach ($arcs as $arc)
				{
					if ($arc->getPreCondition() === Interfaces\Arc::PRECONDITION_DEFAULT)
					{
						$selectedArc = $arc;
					}
					elseif ($workItem->guard($arc->getPreCondition()))
					{
						$selectedArc = $arc;
						break;
					}
				}

				if ($selectedArc)
				{
					$this->enableToken($selectedArc->getPlace());
				}
				else
				{
					//TODO WORKFLOW DESIGN ERROR
					$this->workflowInstance->cancel();
				}
			}
			else
			{
				foreach ($arcs as $arc)
				{
					$place = $arc->getPlace();
					$this->enableToken($place);
				}
			}
		}
		elseif (count($arcs))
		{
			/* @var $arc Interfaces\Arc */
			$arc = $arcs[0];
			$place = $arc->getPlace();
			$this->enableToken($place);
		}
		else
		{
			//TODO WORKFLOW DESIGN ERROR
			$this->workflowInstance->cancel();
		}
	}

	/**
	 * @param Interfaces\Token $token
	 */
	protected function consumeToken($token)
	{
		$token->consume($this->dateTime);

		$place = $token->getPlace();
		if ($place->getType() !== Interfaces\Place::TYPE_END)
		{
			$arcs = $token->getPlace()->getWorkflowOutputItems();
			foreach ($arcs as $arc)
			{
				/* @var $arc Interfaces\Arc */
				$transition = $arc->getTransition();
				foreach ($this->workflowInstance->getItems() as $item)
				{
					if ($item instanceof Interfaces\WorkItem && $item->getTransition() === $transition
						&& $item->getStatus() == Interfaces\WorkItem::STATUS_ENABLED
					)
					{
						$item->cancel($this->dateTime);
					}
				}
			}
		}
	}

	/**
	 * @param Interfaces\Transition $transition
	 * @return Interfaces\Place[]
	 */
	protected function getTransitionInputPlaces($transition)
	{
		$places = array();
		foreach ($transition->getWorkflowInputItems() as $arc)
		{
			/* @var $arc Interfaces\Arc */
			$places[] = $arc->getPlace();
		}
		return $places;
	}

	/**
	 * @param Interfaces\WorkflowInstance $workflowInstance
	 * @param Interfaces\Place $place
	 * @return Interfaces\Token|null
	 */
	protected function getFreeTokenForPlace($workflowInstance, $place)
	{
		foreach ($workflowInstance->getItems() as $token)
		{
			if ($token instanceof Interfaces\Token && $token->getStatus() === Interfaces\Token::STATUS_FREE
				&& $token->getPlace() === $place
			)
			{
				return $token;
			}
		}
		return null;
	}
}