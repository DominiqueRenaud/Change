<?php
/**
 * Copyright (C) 2015 Dominique Renaud
 *
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/.
 */
namespace Rbs\Discount\Modifiers;

/**
 * @name \Rbs\Discount\Modifiers\RowsCountReferenceBasedDiscount
 */
class RowsCountReferenceBasedDiscount extends \Rbs\Commerce\Cart\CartDiscountModifier
{
	/**
	 * @return boolean
	 */
	public function apply()
	{
		//init input parameters
		$data = $this->discount->getParametersData();
		$count = is_array($data) && isset($data['count']) ? intval($data['count']) : 0;
		$bonusCount = is_array($data) && isset($data['bonusCount']) ? intval($data['bonusCount']) : 0;
		$bonusOperator = is_array($data) && isset($data['bonusOperator']) ? $data['bonusOperator'] : "eq";
		$productId = is_array($data) && isset($data['productId']) ? intval($data['productId']) : 0;
		$multipleDiscount = is_array($data) && isset($data['multipleDiscount']) ? $data['multipleDiscount'] == true : false;

		//Apply conditions
		$lProductLine = null;
		if ($count > 0 && count($this->cart->getLines()) > 0)
		{
			$lProductLine = null;
			foreach ($this->cart->getLines() as $line)
			{
				if (($productId == $line->getOptions()->get('productId'))
				&& $this->testNumValue($line->getQuantity(), $bonusOperator, $count))
				{
					$lProductLine = $line;
					break;
				}
			}

			if ($lProductLine == null)
			{
				return false;
			}
			$lProductLineCount = $lProductLine->getQuantity();
			if ($lProductLineCount <= $bonusCount)
			{
				return false;
			}
			if ($multipleDiscount)
			{
				$lProductLineCountDelta = floor($lProductLineCount / $count) * $bonusCount;
			}
			else
			{
				if ($lProductLineCount > $count)
				{
					$lProductLineCountDelta = $bonusCount;
				}
				else
				{
					$lProductLineCountDelta = 0;
				}
			}
			if (($lProductLineCount <= $lProductLineCountDelta) || ($lProductLineCountDelta <= 0))
			{
				return false;
			}

			// Calculus of Discount and Taxes
			$taxCategories = [];
			$taxesApplication = $lProductLine->getTaxes();

			// get taxes collection
			foreach ($taxesApplication as $taxApplication)
			{
				$taxCategories[$taxApplication->getTaxCode()] = $taxApplication->getCategory();
				$taxes[] = $this->priceManager->getTaxByCode($taxApplication->getTaxCode());
			}

			// get prices collection and Discount value
			$withTax = $lProductLine->getItems()[0]->getPrice()->isWithTax();
			$priceForTaxes = [];
			$lProductLineDiscountDelta = 0;
			foreach($lProductLine->getItems() as $item)
			{
				$lLineDiscountDelta = $item->getPrice()->getValue() * $lProductLineCountDelta;
				$priceForTaxes[] = new \Rbs\Commerce\Std\BasePrice(['value' => (-1*$lLineDiscountDelta), 'withTax' => $item->getPrice()->isWithTax(), 'taxCategories' => $taxCategories]);
				$lProductLineDiscountDelta += $lLineDiscountDelta;
			}
			$lProductLineDiscountDelta = -1*$lProductLineDiscountDelta;
			$price = new \Rbs\Commerce\Std\BasePrice(['value' => $lProductLineDiscountDelta, 'withTax' => $withTax, 'taxCategories' => $taxCategories]);

			// Calculus of Taxes
			$lCart = $this->cart;
			$currencyCode = $lCart->getCurrencyCode();
			$zone = $lCart->getZone();
			$taxesApplication=[];
			foreach($priceForTaxes as $priceFromItem)
			{
				$taxesApplicationTmp = $this->priceManager->getTaxesApplication($priceFromItem, $taxes, $zone, $currencyCode, 1);
				$taxesApplication = $this->priceManager->addTaxesApplication($taxesApplication,$taxesApplicationTmp);
			}

			// set Values
			$this->lineKeys[] = $lProductLine->getKey();
			$price->setTaxCategories($taxCategories);
			$this->setPrice($price);
			$this->setTaxes($taxesApplication);
			return parent::apply();
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