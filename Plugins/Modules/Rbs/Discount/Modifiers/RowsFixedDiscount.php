<?php
/**
 * Copyright (C) 2014 Eric Hauswald
 *
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/.
 */
namespace Rbs\Discount\Modifiers;

/**
* @name \Rbs\Discount\Modifiers\RowsFixedDiscount
*/
class RowsFixedDiscount extends \Rbs\Commerce\Cart\CartDiscountModifier
{
	/**
	 * @return boolean
	 */
	public function apply()
	{
		$data = $this->discount->getParametersData();
		$amount = is_array($data) && isset($data['amount']) ? floatval($data['amount']) : 0.0;
		$withTax = is_array($data) && isset($data['withTax']) ? $data['withTax'] == true : true;
		if ($amount > 0.0 && count($this->cart->getLines()))
		{
			$linesAmount = $withTax ? $this->cart->getLinesAmountWithTaxes() : $this->cart->getLinesAmountWithoutTaxes();
			if ($linesAmount > $amount)
			{
				$percent = -($amount / $linesAmount);
				$priceValueWithTax = $this->cart->getPricesValueWithTax();
				if ($priceValueWithTax)
				{
					$value =  $this->cart->getLinesAmountWithTaxes() * $percent;
				}
				else
				{
					$value =  $this->cart->getLinesAmountWithoutTaxes() * $percent;
				}
				$price = new \Rbs\Commerce\Std\BasePrice(['value' => $value, 'withTax' => $priceValueWithTax]);
				foreach ($this->cart->getLines() as $line)
				{
					$this->lineKeys[] = $line->getKey();
				}
				$taxCategories = [];
				$taxesApplication = [];
				foreach ($this->cart->getLinesTaxes() as $taxApplication)
				{
					$taxCategories[$taxApplication->getTaxCode()] = $taxApplication->getCategory();
					$dpt = clone($taxApplication);
					$dpt->setValue($dpt->getValue() * $percent);
					$taxesApplication[] = $dpt;
				}
				$price->setTaxCategories($taxCategories);
				$this->setPrice($price);
				$this->setTaxes($taxesApplication);
				return parent::apply();
			}
		}
	}
} 