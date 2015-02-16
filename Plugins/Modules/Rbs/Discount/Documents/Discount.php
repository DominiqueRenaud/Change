<?php
/**
 * Copyright (C) 2014 Ready Business System
 *
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/.
 */
namespace Rbs\Discount\Documents;

/**
 * @name \Rbs\Discount\Documents\Discount
 */
class Discount extends \Compilation\Rbs\Discount\Documents\Discount
{

	/**
	 * @param \Zend\EventManager\EventManagerInterface $eventManager
	 */
	protected function attachEvents($eventManager)
	{
		parent::attachEvents($eventManager);
		$eventManager->attach('getValidModifier', [$this, 'onDefaultGetValidModifier'], 5);
	}

	public function onDefaultUpdateRestResult(\Change\Documents\Events\Event $event)
	{
		parent::onDefaultUpdateRestResult($event);
		$restResult = $event->getParam('restResult');

		/** @var $discount Discount */
		$discount = $event->getDocument();
		if ($restResult instanceof \Change\Http\Rest\V1\Resources\DocumentResult)
		{
			$restResult->setProperty('orderProcessId', $discount->getOrderProcessId());
		}
		elseif ($restResult instanceof \Change\Http\Rest\V1\Resources\DocumentLink)
		{
			$i18n = $event->getApplicationServices()->getI18nManager();
			$restResult->setProperty('modelName', $i18n->trans($discount->getDocumentModel()->getLabelKey(), ['ucf']));
			$restResult->setProperty('orderProcessId', $discount->getOrderProcessId());
		}
	}

	/**
	 * @param mixed $value
	 * @param array $options
	 * @return \Rbs\Commerce\Process\ModifierInterface|null
	 */
	public function getValidModifier($value, array $options = null)
	{
		$args = ['value' => $value];
		if (is_array($options))
		{
			$args = array_merge($options, $args);
		}
		$event = new \Change\Documents\Events\Event('getValidModifier', $this, $args);
		$this->getEventManager()->trigger($event);
		$modifier = $event->getParam('modifier');
		if ($modifier instanceof \Rbs\Commerce\Process\ModifierInterface)
		{
			return $modifier;
		}
		return null;
	}


	/**
	 * @param \Change\Documents\Events\Event $event
	 */
	public function onDefaultGetValidModifier(\Change\Documents\Events\Event $event)
	{
		/** @var $discount Discount */
		$discount = $event->getDocument();
		if (!($discount instanceof Discount) || !$discount->activated())
		{
			//Invalid Discount
			return;
		}

		$commerceServices = $event->getServices('commerceServices');
		if (!($commerceServices instanceof \Rbs\Commerce\CommerceServices))
		{
			return;
		}

		$value = $event->getParam('value');
		$filters = new \Rbs\Commerce\Filters\Filters($this->getApplication());
		if ($filters->isValid($value, $discount->getCartFilterData()))
		{
			switch ($discount->getDiscountType()) {
				case 'rbs-discount-free-shipping-fee':
					if ($value instanceof \Rbs\Commerce\Cart\Cart)
					{
						$modifier = new \Rbs\Discount\Modifiers\FreeShippingFee($discount, $value, $commerceServices->getPriceManager());
						$event->setParam('modifier', $modifier);
					}
					break;
				case 'rbs-discount-rows-fixed':
					if ($value instanceof \Rbs\Commerce\Cart\Cart)
					{
						$modifier = new \Rbs\Discount\Modifiers\RowsFixedDiscount($discount, $value, $commerceServices->getPriceManager());
						$event->setParam('modifier', $modifier);
					}
					break;
				case 'rbs-discount-rows-percent':
					if ($value instanceof \Rbs\Commerce\Cart\Cart)
					{
						$modifier = new \Rbs\Discount\Modifiers\RowsPercentDiscount($discount, $value, $commerceServices->getPriceManager());
						$event->setParam('modifier', $modifier);
					}
					break;
				case 'rbs-discount-rows-count-reference-based':
					if ($value instanceof \Rbs\Commerce\Cart\Cart)
					{
						$modifier = new \Rbs\Discount\Modifiers\RowsCountReferenceBasedDiscount($discount, $value, $commerceServices->getPriceManager());
						$event->setParam('modifier', $modifier);
					}
					break;
			}
		}
	}
}