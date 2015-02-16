<?php
/**
 * Copyright (C) 2014 Ready Business System
 *
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/.
 */
namespace Rbs\Commerce\Cart;

/**
 * @name \Rbs\Commerce\Cart\Cart
 */
class Cart implements \Serializable
{
	/**
	 * @var \Rbs\Commerce\Cart\CartManager
	 */
	protected $cartManager;

	/**
	 * @var string
	 */
	protected $identifier;

	/**
	 * @var \Rbs\Price\Tax\BillingAreaInterface|null
	 */
	protected $billingArea;

	/**
	 * @var string|null
	 */
	protected $zone;

	/**
	 * @var integer
	 */
	protected $webStoreId = 0;

	/**
	 * @var integer
	 */
	protected $userId = 0;

	/**
	 * @var integer[]
	 */
	protected $priceTargetIds = 0;

	/**
	 * @var integer
	 */
	protected $ownerId = 0;

	/**
	 * @var boolean
	 */
	protected $locked = false;

	/**
	 * @var boolean
	 */
	protected $processing = false;

	/**
	 * @var integer
	 */
	protected $transactionId = 0;

	/**
	 * @var integer
	 */
	protected $orderId = 0;

	/**
	 * @var \DateTime
	 */
	protected $lastUpdate;

	/**
	 * @var \Zend\Stdlib\Parameters
	 */
	protected $context;

	/**
	 * @var CartError[]
	 */
	protected $errors = [];

	/**
	 * @var \Rbs\Commerce\Cart\CartLine[]
	 */
	protected $lines = [];

	/**
	 * @var float|null
	 */
	protected $linesAmountWithoutTaxes;

	/**
	 * @var \Rbs\Price\Tax\TaxApplication[]
	 */
	protected $linesTaxes = [];

	/**
	 * @var float|null
	 */
	protected $linesAmountWithTaxes;

	/**
	 * @var string
	 */
	protected $email;

	/**
	 * @var \Rbs\Geo\Address\AddressInterface
	 */
	protected $address;

	/**
	 * @var \Rbs\Commerce\Process\ShippingModeInterface[]
	 */
	protected $shippingModes = [];

	/**
	 * @var \Rbs\Commerce\Process\CouponInterface[]
	 */
	protected $coupons = [];

	/**
	 * @var \Rbs\Commerce\Cart\CartLine[]
	 */
	protected $fees = [];

	/**
	 * @var \Rbs\Commerce\Cart\CartDiscount[]
	 */
	protected $discounts = [];

	/**
	 * @var float|null
	 */
	protected $totalAmountWithoutTaxes;

	/**
	 * @var \Rbs\Price\Tax\TaxApplication[]
	 */
	protected $totalTaxes = [];

	/**
	 * @var float|null
	 */
	protected $totalAmountWithTaxes;

	/**
	 * @var \Rbs\Commerce\Process\BaseCreditNote[]
	 */
	protected $creditNotes = [];

	/**
	 * @var float|null
	 */
	protected $paymentAmount;

	/**
	 * @var array|null
	 */
	protected $serializedData;

	/**
	 * @param string $identifier
	 * @param \Rbs\Commerce\Cart\CartManager $cartManager
	 */
	function __construct($identifier, \Rbs\Commerce\Cart\CartManager $cartManager = null)
	{
		$this->identifier = $identifier;
		$this->cartManager = $cartManager;
	}

	/**
	 * @return \Rbs\Commerce\Cart\CartManager
	 */
	public function getCartManager()
	{
		return $this->cartManager;
	}

	/**
	 * @param \Rbs\Commerce\Cart\CartManager $cartManager
	 * @return $this
	 */
	public function setCartManager(\Rbs\Commerce\Cart\CartManager $cartManager)
	{
		$this->cartManager = $cartManager;
		if ($this->serializedData)
		{
			$this->restoreSerializedData();
		}
		return $this;
	}

	/**
	 * @param string $identifier
	 * @return $this
	 */
	public function setIdentifier($identifier)
	{
		$this->identifier = $identifier;
		return $this;
	}

	/**
	 * @return string
	 */
	public function getIdentifier()
	{
		return $this->identifier;
	}

	/**
	 * @param integer $webStoreId
	 * @return $this
	 */
	public function setWebStoreId($webStoreId)
	{
		$this->webStoreId = intval($webStoreId);
		return $this;
	}

	/**
	 * @return integer
	 */
	public function getWebStoreId()
	{
		return $this->webStoreId;
	}

	/**
	 * @return boolean|null
	 */
	public function getPricesValueWithTax()
	{
		return $this->getContext()->get('pricesValueWithTax', null);
	}

	/**
	 * @param boolean $pricesValueWithTax
	 * @return $this|\Zend\Stdlib\Parameters
	 */
	public function setPricesValueWithTax($pricesValueWithTax)
	{
		$this->getContext()->set('pricesValueWithTax', $pricesValueWithTax);
		return $this;
	}

	/**
	 * @param \Rbs\Price\Tax\BillingAreaInterface|null $billingArea
	 * @return $this
	 */
	public function setBillingArea($billingArea)
	{
		$this->billingArea = $billingArea;
		return $this;
	}

	/**
	 * @return \Rbs\Price\Tax\BillingAreaInterface|null
	 */
	public function getBillingArea()
	{
		return $this->billingArea;
	}

	/**
	 * @return string|null
	 */
	public function getCurrencyCode()
	{
		return $this->billingArea ? $this->billingArea->getCurrencyCode() : null;
	}

	/**
	 * @param string|null $zone
	 * @return $this
	 */
	public function setZone($zone)
	{
		$this->zone = $zone;
		return $this;
	}

	/**
	 * @return string|null
	 */
	public function getZone()
	{
		return $this->zone;
	}

	/**
	 * @param integer $userId
	 * @return $this
	 */
	public function setUserId($userId)
	{
		$this->userId = $userId;
		return $this;
	}

	/**
	 * @return integer
	 */
	public function getUserId()
	{
		return $this->userId;
	}

	/**
	 * @return integer[]
	 */
	public function getPriceTargetIds()
	{
		return $this->priceTargetIds;
	}

	/**
	 * @param integer[] $priceTargetIds
	 * @return $this
	 */
	public function setPriceTargetIds($priceTargetIds)
	{
		$this->priceTargetIds = $priceTargetIds;
		return $this;
	}

	/**
	 * @param integer $ownerId
	 * @return $this
	 */
	public function setOwnerId($ownerId)
	{
		$this->ownerId = intval($ownerId);
		return $this;
	}

	/**
	 * @return integer
	 */
	public function getOwnerId()
	{
		return $this->ownerId;
	}

	/**
	 * @param string $email
	 * @return $this
	 */
	public function setEmail($email)
	{
		$this->email = $email;
		return $this;
	}

	/**
	 * @return string
	 */
	public function getEmail()
	{
		return $this->email;
	}

	/**
	 * @param \Rbs\Geo\Address\AddressInterface $address
	 * @return $this
	 */
	public function setAddress($address)
	{
		$this->address = $address;
		return $this;
	}

	/**
	 * @return \Rbs\Geo\Address\AddressInterface
	 */
	public function getAddress()
	{
		return $this->address;
	}

	/**
	 * @param boolean $locked
	 * @return $this
	 */
	public function setLocked($locked)
	{
		$this->locked = ($locked == true);
		return $this;
	}

	/**
	 * @return boolean
	 */
	public function isLocked()
	{
		return $this->locked;
	}

	/**
	 * @param boolean $processing
	 * @return $this
	 */
	public function setProcessing($processing)
	{
		$this->processing = ($processing == true);
		return $this;
	}

	/**
	 * @return boolean
	 */
	public function isProcessing()
	{
		return $this->processing;
	}

	/**
	 * @param integer $transactionId
	 * @return $this
	 */
	public function setTransactionId($transactionId)
	{
		$this->transactionId = $transactionId;
		return $this;
	}

	/**
	 * @return integer
	 */
	public function getTransactionId()
	{
		return $this->transactionId;
	}

	/**
	 * @return integer
	 */
	public function getOrderId()
	{
		return $this->orderId;
	}

	/**
	 * @return boolean
	 */
	public function isEmpty()
	{
		return count($this->lines) === 0;
	}

	/**
	 * @param \Rbs\Commerce\Cart\CartError[] $errors
	 * @return $this
	 */
	public function setErrors(array $errors)
	{
		$this->errors = [];
		foreach ($errors as $error)
		{
			$this->addError($error);
		}

		return $this;
	}

	/**
	 * @return CartError[]
	 */
	public function getErrors()
	{
		return $this->errors;
	}

	/**
	 * @param CartError $error
	 * @return $this
	 */
	public function addError($error)
	{
		if ($error instanceof CartError)
		{
			$this->errors[] = $error;
		}
		return $this;
	}

	/**
	 * @return boolean
	 */
	public function hasError()
	{
		return count($this->errors) > 0;
	}

	/**
	 * @param \DateTime|null $lastUpdate
	 * @return \DateTime
	 */
	public function lastUpdate(\DateTime $lastUpdate = null)
	{
		if ($lastUpdate !== null)
		{
			$this->lastUpdate = $lastUpdate;
		}
		return $this->lastUpdate;
	}

	/**
	 * @return \Zend\Stdlib\Parameters
	 */
	public function getContext()
	{
		if ($this->context === null)
		{
			$this->context = new \Zend\Stdlib\Parameters();
		}
		return $this->context;
	}

	/**
	 * @return \Rbs\Commerce\Cart\CartLine[]
	 */
	public function getLines()
	{
		return $this->lines;
	}

	/**
	 * @param integer $lineNumber
	 * @return \Rbs\Commerce\Cart\CartLine|null
	 */
	public function getLineByNumber($lineNumber)
	{
		$idx = $lineNumber - 1;
		return (isset($this->lines[$idx])) ? $this->lines[$idx] : null;
	}

	/**
	 * @param string $lineKey
	 * @return \Rbs\Commerce\Cart\CartLine|null
	 */
	public function getLineByKey($lineKey)
	{
		foreach ($this->lines as $line)
		{
			if ($line->getKey() == $lineKey)
			{
				return $line;
			}
		}
		return null;
	}

	/**
	 * @param string|array|\Rbs\Commerce\Interfaces\LineInterface $parameters
	 * @return \Rbs\Commerce\Cart\CartLine
	 */
	public function getNewLine($parameters)
	{
		return new CartLine($parameters, $this->getCartManager());
	}

	/**
	 * @param array|\Rbs\Commerce\Process\DiscountInterface|null $parameters
	 * @return \Rbs\Commerce\Cart\CartDiscount
	 */
	public function getNewDiscount($parameters)
	{
		return new CartDiscount($parameters, $this->getCartManager());
	}

	/**
	 * @param CartLine $line
	 * @param integer $lineIndex
	 * @throws \RuntimeException
	 * @throws \InvalidArgumentException
	 * @return \Rbs\Commerce\Cart\CartLine
	 */
	public function insertLineAt(CartLine $line, $lineIndex = 0)
	{
		if ($line instanceof CartLine)
		{
			$countLines = count($this->lines);
			if ($lineIndex < 0 || $lineIndex >= $countLines)
			{
				return $this->appendLine($line);
			}
			if ($this->getLineByKey($line->getKey()))
			{
				throw new \RuntimeException('Duplicate line key: ' . $line->getKey(), 999999);
			}
			array_splice($this->lines, $lineIndex, 0, [$line]);
			foreach ($this->lines as $idx => $line)
			{
				$line->setIndex($idx);
			}
			return $line;
		}
		else
		{
			throw new \InvalidArgumentException('Argument 1 should be a CartLine', 999999);
		}
	}

	/**
	 * @param CartLine $line
	 * @throws \RuntimeException
	 * @throws \InvalidArgumentException
	 * @return \Rbs\Commerce\Cart\CartLine
	 */
	public function appendLine(CartLine $line)
	{
		if ($this->getLineByKey($line->getKey()))
		{
			throw new \RuntimeException('Duplicate line key: ' . $line->getKey(), 999999);
		}
		$index = count($this->lines);
		$this->lines[] = $line;
		$line->setIndex($index);
		return $line;
	}

	/**
	 * @param integer $index
	 * @return \Rbs\Commerce\Cart\CartLine|null
	 */
	public function removeLineAt($index)
	{
		if (!isset($this->lines[$index]))
		{
			return null;
		}
		$removedLine = $this->lines[$index];
		array_splice($this->lines, $index, 1);
		foreach ($this->lines as $idx => $line)
		{
			$line->setIndex($idx);
		}
		return $removedLine;
	}

	/**
	 * @param string $lineKey
	 * @return \Rbs\Commerce\Cart\CartLine|null
	 */
	public function removeLineByKey($lineKey)
	{
		$line = $this->getLineByKey($lineKey);
		if ($line)
		{
			return $this->removeLineAt($line->getIndex());
		}
		return $line;
	}

	/**
	 * @return \Rbs\Commerce\Cart\CartLine[]
	 */
	public function removeAllLines()
	{
		$removed = $this->lines;
		$this->lines = [];
		return $removed;
	}

	/**
	 * @param string $lineKey
	 * @param integer $newQuantity
	 * @return CartLine|null
	 */
	public function updateLineQuantity($lineKey, $newQuantity)
	{
		$line = $this->getLineByKey($lineKey);
		if ($line)
		{
			return $line->setQuantity(intval($newQuantity));
		}
		return $line;
	}


	/**
	 * @return \Rbs\Price\Tax\TaxInterface[]
	 */
	public function getTaxes()
	{
		$taxes = $this->billingArea ? $this->billingArea->getTaxes() : [];
		if ($taxes instanceof \Change\Documents\DocumentArrayProperty)
		{
			$taxes = $taxes->toArray();
		}
		return $taxes;
	}

	/**
	 * @param string $taxCode
	 * @return null|\Rbs\Price\Tax\TaxInterface
	 */
	public function getTaxByCode($taxCode)
	{
		$taxes = $this->getTaxes();
		foreach ($taxes as $tax)
		{
			if ($tax->getCode() == $taxCode)
			{
				return $tax;
			}
		}
		return null;
	}

	/**
	 * @param float|null $linesAmountWithoutTaxes
	 * @return $this
	 */
	public function setLinesAmountWithoutTaxes($linesAmountWithoutTaxes)
	{
		$this->linesAmountWithoutTaxes = $linesAmountWithoutTaxes;
		return $this;
	}

	/**
	 * @return float|null
	 */
	public function getLinesAmountWithoutTaxes()
	{
		return $this->linesAmountWithoutTaxes;
	}

	/**
	 * @return \Rbs\Price\Tax\TaxApplication[]
	 */
	public function getLinesTaxes()
	{
		return $this->linesTaxes;
	}

	/**
	 * @param \Rbs\Price\Tax\TaxApplication[] $linesTaxes
	 * @return $this
	 */
	public function setLinesTaxes(array $linesTaxes)
	{
		$this->linesTaxes = $linesTaxes;
		return $this;
	}

	/**
	 * @param float|null $linesAmountWithTaxes
	 * @return $this
	 */
	public function setLinesAmountWithTaxes($linesAmountWithTaxes)
	{
		$this->linesAmountWithTaxes = $linesAmountWithTaxes;
		return $this;
	}

	/**
	 * @return float|null
	 */
	public function getLinesAmountWithTaxes()
	{
		return $this->linesAmountWithTaxes;
	}

	/**
	 * @param float|null $totalAmountWithoutTaxes
	 * @return $this
	 */
	public function setTotalAmountWithoutTaxes($totalAmountWithoutTaxes)
	{
		$this->totalAmountWithoutTaxes = $totalAmountWithoutTaxes;
		return $this;
	}

	/**
	 * @return float|null
	 */
	public function getTotalAmountWithoutTaxes()
	{
		return $this->totalAmountWithoutTaxes;
	}



	/**
	 * @param \Rbs\Price\Tax\TaxApplication[] $totalTaxes
	 * @return $this
	 */
	public function setTotalTaxes(array $totalTaxes)
	{
		$this->totalTaxes = $totalTaxes;
		return $this;
	}

	/**
	 * @return \Rbs\Price\Tax\TaxApplication[]
	 */
	public function getTotalTaxes()
	{
		return $this->totalTaxes;
	}

	/**
	 * @param float|null $totalAmountWithTaxes
	 * @return $this
	 */
	public function setTotalAmountWithTaxes($totalAmountWithTaxes)
	{
		$this->totalAmountWithTaxes = $totalAmountWithTaxes;
		return $this;
	}

	/**
	 * @return float|null
	 */
	public function getTotalAmountWithTaxes()
	{
		return $this->totalAmountWithTaxes;
	}

	/**
	 * @param float|null $paymentAmount
	 * @return $this
	 */
	public function setPaymentAmount($paymentAmount)
	{
		$this->paymentAmount = $paymentAmount;
		return $this;
	}

	/**
	 * @return float|null
	 */
	public function getPaymentAmount()
	{
		return $this->paymentAmount;
	}

	/**
	 * @param  \Rbs\Commerce\Process\BaseShippingMode[] $shippingModes
	 * @return $this
	 */
	public function setShippingModes(array $shippingModes)
	{
		$this->shippingModes = [];
		foreach ($shippingModes as $shippingMode)
		{
			if ($shippingMode instanceof \Rbs\Commerce\Process\BaseShippingMode)
			{
				$this->shippingModes[] = $shippingMode;
			}
			elseif (is_array($shippingMode))
			{
				$this->shippingModes[] = new \Rbs\Commerce\Process\BaseShippingMode($shippingMode);
			}
		}
		return $this;
	}

	/**
	 * @return \Rbs\Commerce\Process\BaseShippingMode[]
	 */
	public function getShippingModes()
	{
		return $this->shippingModes;
	}

	/**
	 * @param \Rbs\Commerce\Process\CouponInterface[] $coupons
	 * @return $this
	 */
	public function setCoupons($coupons)
	{
		$this->coupons = $coupons;
		return $this;
	}

	/**
	 * @param string $code
	 * @return \Rbs\Commerce\Cart\CartDiscount|null
	 */
	public function getCouponByCode($code)
	{
		foreach ($this->coupons as $coupon)
		{
			if ($coupon->getCode() == $code)
			{
				return $coupon;
			}
		}
		return null;
	}

	/**
	 * @param \Rbs\Commerce\Process\CouponInterface $coupon
	 * @throws \RuntimeException
	 * @return \Rbs\Commerce\Process\CouponInterface
	 */
	public function appendCoupon(\Rbs\Commerce\Process\CouponInterface $coupon)
	{
		if ($this->getCouponByCode($coupon->getCode()))
		{
			throw new \RuntimeException('Duplicate coupon code: ' . $coupon->getCode(), 999999);
		}
		$this->coupons[] = $coupon;
		return $coupon;
	}

	/**
	 * @return \Rbs\Commerce\Process\CouponInterface[]
	 */
	public function getCoupons()
	{
		return $this->coupons;
	}

	/**
	 * Return removed coupons
	 * @return \Rbs\Commerce\Process\CouponInterface[]
	 */
	public function removeAllCoupons()
	{
		$removed = $this->coupons;
		$this->coupons = [];
		return $removed;
	}

	/**
	 * String representation of object
	 * @link http://php.net/manual/en/serializable.serialize.php
	 * @return string the string representation of the object or null
	 */
	public function serialize()
	{
		$serializedData = get_object_vars($this);
		unset($serializedData['cartManager']);
		return serialize($this->getCartManager()->getSerializableValue($serializedData));
	}

	/**
	 * @link http://php.net/manual/en/serializable.unserialize.php
	 * @param string $serialized
	 * @return void
	 */
	public function unserialize($serialized)
	{
		$this->serializedData = unserialize($serialized);
	}

	/**
	 * @return array
	 */
	protected function restoreSerializedData()
	{
		$cartManager = $this->getCartManager();
		$serializedData = $cartManager->restoreSerializableValue($this->serializedData);
		$this->serializedData = null;
		foreach ($serializedData as $k => $v)
		{
			switch ($k) {
				case 'identifier': $this->identifier = $v; break;
				case 'billingArea': $this->billingArea = $v; break;
				case 'zone': $this->zone = $v; break;
				case 'webStoreId': $this->webStoreId = $v; break;
				case 'userId': $this->userId = $v; break;
				case 'priceTargetIds': $this->priceTargetIds = $v; break;
				case 'ownerId': $this->ownerId = $v; break;
				case 'locked': $this->locked = $v; break;
				case 'processing': $this->processing = $v; break;
				case 'transactionId': $this->transactionId = $v; break;
				case 'lastUpdate': $this->lastUpdate = $v; break;
				case 'context': $this->context = $v; break;
				case 'errors': $this->errors = $v; break;
				case 'lines': $this->lines = $v; break;
				case 'linesAmount':
				case 'linesAmountWithoutTaxes': $this->linesAmountWithoutTaxes = $v; break;
				case 'linesTaxes': $this->linesTaxes = $v; break;
				case 'linesAmountWithTaxes': $this->linesAmountWithTaxes = $v; break;
				case 'email': $this->email = $v; break;
				case 'address': $this->address = $v; break;
				case 'shippingModes': $this->shippingModes = $v; break;
				case 'coupons': $this->coupons = $v; break;
				case 'fees': $this->fees = $v; break;
				case 'discounts': $this->discounts = $v; break;
				case 'totalAmount':
				case 'totalAmountWithoutTaxes': $this->totalAmountWithoutTaxes = $v; break;
				case 'totalTaxes': $this->totalTaxes = $v; break;
				case 'totalAmountWithTaxes': $this->totalAmountWithTaxes = $v; break;
				case 'creditNotes': $this->creditNotes = $v; break;
				case 'paymentAmount': $this->paymentAmount = $v; break;
			}
		}

		/* @var $line CartLine */
		foreach ($this->lines as $line)
		{

			$line->setCartManager($cartManager);
		}

		/* @var $fee CartLine */
		foreach ($this->fees as $fee)
		{
			$fee->setCartManager($cartManager);
		}

		/** @var $discount CartDiscount */
		foreach ($this->discounts as $discount)
		{
			$discount->setCartManager($cartManager);
		}

		return $serializedData;
	}

	/**
	 * @return \Rbs\Commerce\Cart\CartLine[]
	 */
	public function getFees()
	{
		return $this->fees;
	}

	/**
	 * @param string $feeKey
	 * @return \Rbs\Commerce\Cart\CartLine|null
	 */
	public function getFeeByKey($feeKey)
	{
		foreach ($this->fees as $fee)
		{
			if ($fee->getKey() === $feeKey)
			{
				return $fee;
			}
		}
		return null;
	}

	/**
	 * @param CartLine $feeLine
	 * @throws \RuntimeException
	 * @return \Rbs\Commerce\Cart\CartLine
	 */
	public function appendFee(CartLine $feeLine)
	{
		if ($this->getFeeByKey($feeLine->getKey()))
		{
			throw new \RuntimeException('Duplicate fee key: ' . $feeLine->getKey(), 999999);
		}
		$index = count($this->fees);
		$this->fees[] = $feeLine;
		$feeLine->setIndex($index);
		return $feeLine;
	}

	/**
	 * @return \Rbs\Commerce\Cart\CartLine[]
	 */
	public function removeAllFees()
	{
		$removed = $this->fees;
		$this->fees = [];
		return $removed;
	}

	/**
	 * @return \Rbs\Commerce\Cart\CartDiscount[]
	 */
	public function getDiscounts()
	{
		return $this->discounts;
	}

	/**
	 * @param string $id
	 * @return \Rbs\Commerce\Cart\CartDiscount|null
	 */
	public function getDiscountById($id)
	{
		foreach ($this->discounts as $discount)
		{
			if ($discount->getId() == $id)
			{
				return $discount;
			}
		}
		return null;
	}

	/**
	 * @param CartDiscount $discount
	 * @throws \RuntimeException
	 * @return \Rbs\Commerce\Cart\CartDiscount
	 */
	public function appendDiscount(CartDiscount $discount)
	{
		if ($this->getDiscountById($discount->getId()))
		{
			throw new \RuntimeException('Duplicate discount id: ' . $discount->getId(), 999999);
		}
		$this->discounts[] = $discount;
		return $discount;
	}

	/**
	 * @return \Rbs\Commerce\Cart\CartDiscount[]
	 */
	public function removeAllDiscounts()
	{
		$removed = $this->discounts;
		$this->discounts = [];
		return $removed;
	}


	/**
	 * @return \Rbs\Commerce\Process\BaseCreditNote[]
	 */
	public function getCreditNotes()
	{
		return $this->creditNotes;
	}

	/**
	 * @param string $id
	 * @return \Rbs\Commerce\Process\BaseCreditNote|null
	 */
	public function getCreditNoteById($id)
	{
		foreach ($this->creditNotes as $creditNote)
		{
			if ($creditNote->getId() == $id)
			{
				return $creditNote;
			}
		}
		return null;
	}

	/**
	 * @param \Rbs\Commerce\Process\BaseCreditNote $creditNote
	 * @throws \RuntimeException
	 * @return \Rbs\Commerce\Process\BaseCreditNote
	 */
	public function appendCreditNote(\Rbs\Commerce\Process\BaseCreditNote $creditNote)
	{
		if ($this->getCreditNoteById($creditNote->getId()))
		{
			throw new \RuntimeException('Duplicate credit note id: ' . $creditNote->getId(), 999999);
		}
		$this->creditNotes[] = $creditNote;
		return $creditNote;
	}

	/**
	 * @return \Rbs\Commerce\Process\BaseCreditNote[]
	 */
	public function removeAllCreditNotes()
	{
		$removed = $this->creditNotes;
		$this->creditNotes = [];
		return $removed;
	}

	/**
	 * @return array
	 */
	public function toArray()
	{
		$array = [
			'identifier' => $this->identifier,
			'context' => $this->getContext()->toArray(),
			'errors' => [],
			'lastUpdate' => $this->lastUpdate ? $this->lastUpdate->format(\DateTime::ISO8601) : null,
			'webStoreId' => $this->webStoreId,
			'billingAreaId' => $this->billingArea ? $this->billingArea->getId() : null,
			'currencyCode' => $this->billingArea ? $this->billingArea->getCurrencyCode() : null,
			'taxes' => [],
			'zone' => $this->zone,

			'lines' => [],
			'linesAmountWithoutTaxes' => $this->linesAmountWithoutTaxes,
			'linesTaxes' => [],
			'linesAmountWithTaxes' => $this->linesAmountWithTaxes,

			'address' => ($this->address instanceof \Rbs\Geo\Address\AddressInterface) ? $this->address->toArray() : null,
			'shippingModes' => [],

			'coupons' => [],
			'fees' => [],
			'discounts' => [],

			'totalAmountWithoutTaxes' => $this->totalAmountWithoutTaxes,
			'totalTaxes' => [],
			'totalAmountWithTaxes' => $this->totalAmountWithTaxes,

			'creditNotes' => [],
			'paymentAmount' => $this->getPaymentAmount(),

			'userId' => $this->userId,
			'priceTargetIds' => $this->priceTargetIds,
			'ownerId' => $this->ownerId,
			'email' => $this->email,
			'locked' => $this->locked,
			'processing' => $this->processing,
			'transactionId' => $this->transactionId
		];

		foreach ($this->getTaxes() as $tax)
		{
			$array['taxes'][] = $tax->toArray();
		}

		foreach ($this->getTotalTaxes() as $taxApplication)
		{
			$array['totalTaxes'][] = $taxApplication->toArray();
		}

		foreach ($this->getLinesTaxes() as $taxApplication)
		{
			$array['linesTaxes'][] = $taxApplication->toArray();
		}

		foreach ($this->lines as $line)
		{
			$array['lines'][] = $line->toArray();
		}

		foreach ($this->shippingModes as $shippingMode)
		{
			$array['shippingModes'][] = $shippingMode->toArray();
		}

		foreach ($this->coupons as $coupon)
		{
			$array['coupons'][] = $coupon->toArray();
		}

		foreach ($this->fees as $fee)
		{
			$array['fees'][] = $fee->toArray();
		}

		foreach ($this->discounts as $discount)
		{
			$array['discounts'][] = $discount->toArray();
		}

		foreach ($this->creditNotes as $creditNote)
		{
			$array['creditNotes'][] = $creditNote->toArray();
		}

		foreach ($this->errors as $error)
		{
			$array['errors'][] = $error->toArray();
		}

		return $array;
	}


	/**
	 * @deprecated
	 */
	public function setLinesAmount($linesAmount)
	{
		return $this->setLinesAmountWithoutTaxes($linesAmount);
	}

	/**
	 * @deprecated
	 */
	public function getLinesAmount()
	{
		return $this->getLinesAmountWithoutTaxes();
	}


	/**
	 * @deprecated
	 */
	public function setTotalAmount($totalAmount)
	{
		return $this->setTotalAmountWithoutTaxes($totalAmount);
	}

	/**
	 * @deprecated
	 */
	public function getTotalAmount()
	{
		return $this->getTotalAmountWithoutTaxes();
	}

	/**
	 * @deprecated
	 */
	public function setPaymentAmountWithTaxes($paymentAmount)
	{
		return $this->setPaymentAmount($paymentAmount);
	}

	/**
	 * @deprecated
	 */
	public function getPaymentAmountWithTaxes()
	{
		return $this->getPaymentAmount();
	}
}