<?php
/**
 * Copyright (C) 2014 Ready Business System
 *
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/.
 */
namespace Rbs\Commerce\Filters;

/**
* @name \Rbs\Commerce\Filters\Filters
*/
class Filters implements \Zend\EventManager\EventsCapableInterface
{
	use \Change\Events\EventsCapableTrait;

	const EVENT_MANAGER_IDENTIFIER = 'CommerceFilters';

	public function __construct(\Change\Application $application)
	{
		$this->setApplication($application);
	}

	/**
	 * @return string
	 */
	protected function getEventManagerIdentifier()
	{
		return static::EVENT_MANAGER_IDENTIFIER;
	}

	/**
	 * @return string[]
	 */
	protected function getListenerAggregateClassNames()
	{
		return $this->getApplication()->getConfiguredListenerClassNames('Rbs/Commerce/Filters');
	}

	/**
	 * @param \Change\Events\EventManager $eventManager
	 */
	protected function attachEvents(\Change\Events\EventManager $eventManager)
	{
		$eventManager->attach('getDefinitions', [$this, 'onDefaultGetDefinitions'], 5);

		$eventManager->attach(['isValidLinesAmountValue', 'isValidLinesPriceValue'], [$this, 'onDefaultIsValidLinesAmountValue'], 5);
		$eventManager->attach(['isValidTotalAmountValue', 'isValidTotalPriceValue'], [$this, 'onDefaultIsValidTotalAmountValue'], 5);
		$eventManager->attach('isValidPaymentAmountValue', [$this, 'onDefaultIsValidPaymentAmountValue'], 5);
		$eventManager->attach('isValidHasCoupon', [$this, 'onDefaultIsValidHasCoupon'], 5);
		$eventManager->attach('isValidHasCreditNote', [$this, 'onDefaultIsValidHasCreditNote'], 5);
		$eventManager->attach(['isValidLinesCountValue'], [$this, 'onDefaultIsValidCountUnitPerLine'], 5);
		$eventManager->attach(['isValidBrandProductCount'], [$this, 'onDefaultIsValidCountValueBrand'], 5);
		$eventManager->attach(['isValidBrandProductCountByLine'], [$this, 'onDefaultIsValidLinesCountValueBrandByLine'], 5);
		$eventManager->attach(['isValidBrandProductCountByRef'], [$this, 'onDefaultIsValidProductCountByRef'], 5);

	}

	/**
	 * @param array $options
	 * @return array
	 */
	public function getDefinitions($options = [])
	{
		$em = $this->getEventManager();
		$args = $em->prepareArgs(['filtersDefinition' => [], 'options' => $options]);
		$em->trigger('getDefinitions', $this, $args);
		return isset($args['filtersDefinition']) && is_array($args['filtersDefinition']) ? array_values($args['filtersDefinition']) : [];
	}

	/**
	 * @param \Change\Events\Event $event
	 */
	public function onDefaultGetDefinitions($event)
	{
		$i18nManager = $event->getApplicationServices()->getI18nManager();
		$filtersDefinition = $event->getParam('filtersDefinition');
		$defaultsDefinitions = json_decode(file_get_contents(__DIR__ . '/Assets/filtersDefinition.json'), true);
		foreach ($defaultsDefinitions as $definition)
		{
			$definition['config']['group'] = $i18nManager->trans($definition['config']['group'], ['ucf']);
			$definition['config']['listLabel'] = $i18nManager->trans($definition['config']['listLabel'], ['ucf']);
			$definition['config']['label'] = $i18nManager->trans($definition['config']['label'], ['ucf']);
			$filtersDefinition[] = $definition;
		}
		$event->setParam('filtersDefinition', $filtersDefinition);
	}

	/**
	 * @api
	 * @param \Rbs\Commerce\Cart\Cart|\Rbs\Order\Documents\Order $value
	 * @param array $filter
	 * @param array $options
	 * @return boolean
	 */
	public function isValid($value, $filter, $options = [])
	{
		if (is_array($filter) && isset($filter['name']))
		{
			$name = $filter['name'];
			if ($name === 'group')
			{
				if (isset($filter['operator']) && isset($filter['filters']) && is_array($filter['filters']))
				{
					return $this->isValidGroupFilters($value, $filter['operator'], $filter['filters']);
				}
			}
			else
			{
				$em = $this->getEventManager();
				$args = $em->prepareArgs(['value' => $value, 'name' => $name, 'filter' => $filter, 'options' => $options]);
				$em->trigger('isValid' . ucfirst($name), $this, $args);
				if (isset($args['valid']))
				{
					return ($args['valid'] == true);
				}
			}
		}
		return true;
	}

	/**
	 * @param \Rbs\Commerce\Cart\Cart|\Rbs\Order\Documents\Order $value
	 * @param string $operator
	 * @param array $filters
	 * @return boolean
	 */
	protected function isValidGroupFilters($value, $operator, $filters)
	{
		if (!count($filters))
		{
			return true;
		}
		if ($operator === 'OR')
		{
			foreach ($filters as $filter)
			{
				if ($this->isValid($value, $filter))
				{
					return true;
				}
			}
			return false;
		}
		else
		{
			foreach ($filters as $filter)
			{
				if (!$this->isValid($value, $filter))
				{
					return false;
				}
			}
			return true;
		}
	}

	/**
	 * @param \Change\Events\Event $event
	 */
	public function onDefaultIsValidLinesAmountValue($event)
	{
		$filter = $event->getParam('filter');
		if (isset($filter['parameters']) && is_array($filter['parameters']))
		{
			$parameters = $filter['parameters'] + ['operator' => 'isNull', 'value' => null];
			$expected = $parameters['value'];
			$operator = $parameters['operator'];

			$value = $event->getParam('value');
			if ($value instanceof \Rbs\Commerce\Cart\Cart)
			{
				$amount = $value->getPricesValueWithTax() ? $value->getLinesAmountWithTaxes() : $value->getLinesAmountWithoutTaxes();
				$event->setParam('valid', $this->testNumValue($amount, $operator, $expected));
			}
			elseif ($value instanceof \Rbs\Order\Documents\Order)
			{
				$amount = $value->getPricesValueWithTax() ? $value->getLinesAmountWithTaxes() : $value->getLinesAmountWithoutTaxes();
				$event->setParam('valid', $this->testNumValue($amount, $operator, $expected));
			}
		}
	}

	/**
	 * @param \Change\Events\Event $event
	 */
	public function onDefaultIsValidTotalAmountValue($event)
	{
		$filter = $event->getParam('filter');
		if (isset($filter['parameters']) && is_array($filter['parameters']))
		{
			$parameters = $filter['parameters'] + ['operator' => 'isNull', 'value' => null];
			$expected = $parameters['value'];
			$operator = $parameters['operator'];

			$value = $event->getParam('value');
			if ($value instanceof \Rbs\Commerce\Cart\Cart)
			{
				$amount = $value->getPricesValueWithTax() ? $value->getTotalAmountWithTaxes() : $value->getTotalAmountWithoutTaxes();
				$event->setParam('valid', $this->testNumValue($amount, $operator, $expected));
			}
			elseif ($value instanceof \Rbs\Order\Documents\Order)
			{
				$amount = $value->getPricesValueWithTax() ? $value->getTotalAmountWithTaxes() : $value->getTotalAmountWithoutTaxes();
				$event->setParam('valid', $this->testNumValue($amount, $operator, $expected));
			}
		}
	}

	/**
	 * @param \Change\Events\Event $event
	 */
	public function onDefaultIsValidPaymentAmountValue($event)
	{
		$filter = $event->getParam('filter');
		if (isset($filter['parameters']) && is_array($filter['parameters']))
		{
			$parameters = $filter['parameters'] + ['operator' => 'isNull', 'value' => null];
			$expected = $parameters['value'];
			$operator = $parameters['operator'];

			$value = $event->getParam('value');
			if ($value instanceof \Rbs\Commerce\Cart\Cart)
			{
				$amount = $value->getPaymentAmount();
				$event->setParam('valid', $this->testNumValue($amount, $operator, $expected));
			}
			elseif ($value instanceof \Rbs\Order\Documents\Order)
			{
				$amount =  $value->getPaymentAmount();
				$event->setParam('valid', $this->testNumValue($amount, $operator, $expected));
			}
		}
	}

	/**
	 * @param \Change\Events\Event $event
	 */
	public function onDefaultIsValidHasCreditNote($event)
	{
		$filter = $event->getParam('filter');
		if (isset($filter['parameters']) && is_array($filter['parameters']))
		{
			$value = $event->getParam('value');
			if ($value instanceof \Rbs\Commerce\Cart\Cart)
			{
				/** @var $creditNotes \Rbs\Commerce\Process\BaseCreditNote[] */
				$creditNotes = $value->getCreditNotes();
				$amount = null;
				if (is_array($creditNotes) && count($creditNotes))
				{
					$amount = 0.0;
					foreach ($creditNotes as $creditNote)
					{
						$amount += abs($creditNote->getAmount());
					}
				}
				$event->setParam('valid', $amount !== null && $amount > 0.0);
			}
			elseif ($value instanceof \Rbs\Order\Documents\Order)
			{
				/** @var $creditNotes \Rbs\Commerce\Process\BaseCreditNote[] */
				$creditNotes = $value->getCreditNotes();
				$amount = null;
				if (is_array($creditNotes) && count($creditNotes))
				{
					$amount = 0.0;
					foreach ($creditNotes as $creditNote)
					{
						$amount += abs($creditNote->getAmount());
					}
				}
				$event->setParam('valid', $amount !== null && $amount > 0.0);
			}
		}
	}

	/**
	 * @param \Change\Events\Event $event
	 */
	public function onDefaultIsValidHasCoupon($event)
	{
		$filter = $event->getParam('filter');
		if (isset($filter['parameters']) && is_array($filter['parameters']))
		{
			$parameters = $filter['parameters'] + ['operator' => 'isNull', 'value' => null];
			$expected = $parameters['value'];
			$operator = $parameters['operator'];
			$valid = null;
			$value = $event->getParam('value');
			$coupons = [];
			if ($value instanceof \Rbs\Commerce\Cart\Cart)
			{
				$coupons = $value->getCoupons();
			}
			elseif ($value instanceof \Rbs\Order\Documents\Order)
			{
				$coupons = $value->getCoupons();
			}


			if ($operator === 'isNull')
			{
				$valid = (count($coupons) === 0);
			}
			elseif ($operator === 'eq')
			{
				$valid = false;
				foreach ($coupons as $coupon)
				{
					if ($coupon->getOptions()->get('id') == $expected)
					{
						$valid = true;
						break;
					}
				}
			}
			elseif ($operator === 'neq')
			{
				$valid = true;
				foreach ($coupons as $coupon)
				{
					if ($coupon->getOptions()->get('id') == $expected)
					{
						$valid = false;
						break;
					}
				}
			}

			if ($valid !== null)
			{
				$event->setParam('valid', $valid);
			}
		}
	}
	/**
 * @param \Change\Events\Event $event
 */
	public function onDefaultIsValidCountUnitPerLine($event)
	{
		$filter = $event->getParam('filter');
		if (isset($filter['parameters']) && is_array($filter['parameters']))
		{
			$parameters = $filter['parameters'] + ['operator' => 'isNull', 'value' => null];
			$expected = $parameters['value'];
			$operator = $parameters['operator'];

			$value = $event->getParam('value');
			if ($value instanceof \Rbs\Commerce\Cart\Cart)
			{
				$artCount = 0;
				foreach ($value->getLines() as $line)
				{
					$artCount = $line->getQuantity();
					$resultTest =  $this->testNumValue($artCount, $operator, $expected);
					if ($resultTest){
						$event->setParam('valid', true);
						return;
					}

				}
				$event->setParam('valid', $this->testNumValue($artCount, $operator, $expected));
			}
			elseif ($value instanceof \Rbs\Order\Documents\Order)
			{
				$artCount = 0;
				foreach ($value->getLines() as $line)
				{
					$artCount = $line->getQuantity();
					$resultTest =  $this->testNumValue($artCount, $operator, $expected);
					if ($resultTest){
						$event->setParam('valid', true);
						return;
					}
				}
				$event->setParam('valid', false);
			}
		}
	}
	/**
	 * @param \Change\Events\Event $event
	 */
	public function onDefaultIsValidCountUnitPerCart($event)
	{
		$filter = $event->getParam('filter');
		if (isset($filter['parameters']) && is_array($filter['parameters']))
		{
			$parameters = $filter['parameters'] + ['operator' => 'isNull', 'value' => null];
			$expected = $parameters['value'];
			$operator = $parameters['operator'];

			$value = $event->getParam('value');
			if ($value instanceof \Rbs\Commerce\Cart\Cart)
			{
				$artCount = 0;
				foreach ($value->getLines() as $line)
				{
					$artCount += $line->getQuantity();
				}
				$event->setParam('valid', $this->testNumValue($artCount, $operator, $expected));
			}
			elseif ($value instanceof \Rbs\Order\Documents\Order)
			{
				$artCount = 0;
				foreach ($value->getLines() as $line)
				{
					$artCount += $line->getQuantity();
				}

				$event->setParam('valid', $this->testNumValue($artCount, $operator, $expected));
			}
		}
	}
	/**
	 * @param \Change\Events\Event $event
	 */
	public function onDefaultIsValidCountValueBrand($event)
	{
		$filter = $event->getParam('filter');
		if (isset($filter['parameters']) && is_array($filter['parameters']))
		{
			$parameters = $filter['parameters'] + ['operator' => 'isNull', 'quantity' => null,'brand'=>null];
			$expected = $parameters['quantity'];
			$operator = $parameters['operator'];
			$brand = $parameters['brand'];

			$value = $event->getParam('value');
			if ($value instanceof \Rbs\Commerce\Cart\Cart)
			{
				$artCount = 0;
				foreach ($value->getLines() as $line)
				{
					$lProductId = $line->getOptions()->get('productId');
					$lProduct = $event->getApplicationServices()->getDocumentManager()->getDocumentInstance($lProductId);
					if ($lProduct instanceof \Rbs\Catalog\Documents\Product)
					{
						if ($lProduct->getBrandId() == $brand )
						{
							$artCount += $line->getQuantity();
						}
					}
				}
				$event->setParam('valid', $this->testNumValue($artCount, $operator, $expected));
			}
			elseif ($value instanceof \Rbs\Order\Documents\Order)
			{
				$artCount = 0;
				foreach ($value->getLines() as $line)
				{
					$lProductId = $line->getOptions()->get('productId');
					$lProduct = $event->getApplicationServices()->getDocumentManager()->getDocumentInstance($lProductId);
					if ($lProduct instanceof \Rbs\Catalog\Documents\Product)
					{
						if ($lProduct->getBrandId() == $brand )
						{
							$artCount += $line->getQuantity();
						}
					}
				}
				$event->setParam('valid', $this->testNumValue($artCount, $operator, $expected));
			}
		}
	}
	/**
	 * @param \Change\Events\Event $event
	 */
	public function onDefaultIsValidLinesCountValueBrandByLine($event)
	{
		$filter = $event->getParam('filter');
		if (isset($filter['parameters']) && is_array($filter['parameters']))
		{
			$parameters = $filter['parameters'] + ['operator' => 'isNull', 'quantity' => null,'brand'=>null];
			$expected = $parameters['quantity'];
			$operator = $parameters['operator'];
			$brand = $parameters['brand'];

			$value = $event->getParam('value');
			if ($value instanceof \Rbs\Commerce\Cart\Cart)
			{
				$artCount = 0;
				foreach ($value->getLines() as $line)
				{
					$lProductId = $line->getOptions()->get('productId');
					$lProduct = $event->getApplicationServices()->getDocumentManager()->getDocumentInstance($lProductId);
					if ($lProduct instanceof \Rbs\Catalog\Documents\Product)
					{
						if ($lProduct->getBrandId() == $brand )
						{
							$artCount = $line->getQuantity();
							$resultTest =  $this->testNumValue($artCount, $operator, $expected);
							if ($resultTest){
								$event->setParam('valid', true);
								return;
							}
						}
					}
				}
				$event->setParam('valid',false);
			}
			elseif ($value instanceof \Rbs\Order\Documents\Order)
			{
				$artCount = 0;
				foreach ($value->getLines() as $line)
				{
					$lProductId = $line->getOptions()->get('productId');
					$lProduct = $event->getApplicationServices()->getDocumentManager()->getDocumentInstance($lProductId);
					if ($lProduct instanceof \Rbs\Catalog\Documents\Product)
					{
						$artCount = $line->getQuantity();
						$resultTest =  $this->testNumValue($artCount, $operator, $expected);
						if ($resultTest){
							$event->setParam('valid', true);
							return;
						}
					}
				}
				$event->setParam('valid', false);
			}
		}
	}
	/**
	 * @param \Change\Events\Event $event
	 */
	public function onDefaultIsValidProductCountByRef($event)
	{
		$filter = $event->getParam('filter');
		if (isset($filter['parameters']) && is_array($filter['parameters']))
		{
			$parameters = $filter['parameters'] + ['operator' => 'isNull', 'quantity' => null,'product'=>null];
			$expected = $parameters['quantity'];
			$operator = $parameters['operator'];
			$productEspected = $parameters['product'];

			$value = $event->getParam('value');
			if ($value instanceof \Rbs\Commerce\Cart\Cart)
			{
				$artCount = 0;
				foreach ($value->getLines() as $line)
				{
					$lProductId = $line->getOptions()->get('productId');
					$lProduct = $event->getApplicationServices()->getDocumentManager()->getDocumentInstance($lProductId);
					if ($lProduct instanceof \Rbs\Catalog\Documents\Product)
					{
						if ($lProductId == $productEspected )
						{
							$artCount += $line->getQuantity();
						}
					}
				}
				$event->setParam('valid', $this->testNumValue($artCount, $operator, $expected));
			}
			elseif ($value instanceof \Rbs\Order\Documents\Order)
			{
				$artCount = 0;
				foreach ($value->getLines() as $line)
				{
					$lProductId = $line->getOptions()->get('productId');
					$lProduct = $event->getApplicationServices()->getDocumentManager()->getDocumentInstance($lProductId);
					if ($lProduct instanceof \Rbs\Catalog\Documents\Product)
					{
						if ($lProductId == $productEspected )
						{
							$artCount += $line->getQuantity();
						}
					}
				}
				$event->setParam('valid', $this->testNumValue($artCount, $operator, $expected));
			}
		}
	}
	/**
	 * @param $value
	 * @param $operator
	 * @param $expected
	 * @return boolean
	 */
	protected function testNumValue($value, $operator, $expected)
	{
		switch ($operator)
		{
			case 'eq':
				return abs($value - $expected) < 0.0001;
			case 'neq':
				return abs($value - $expected) > 0.0001;
			case 'lte':
				return $value <= $expected;
			case 'gte':
				return $value >= $expected;
			case 'isNull':
				return $value === null;
		}
		return false;
	}
} 