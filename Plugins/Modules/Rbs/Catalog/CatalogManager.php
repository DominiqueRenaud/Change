<?php
/**
 * Copyright (C) 2014 Ready Business System
 *
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/.
 */
namespace Rbs\Catalog;


/**
 * @name \Rbs\Catalog\CatalogManager
 */
class CatalogManager implements \Zend\EventManager\EventsCapableInterface
{
	use \Change\Events\EventsCapableTrait;

	const EVENT_MANAGER_IDENTIFIER = 'CatalogManager';

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
		return $this->getApplication()->getConfiguredListenerClassNames('Rbs/Commerce/Events/CatalogManager');
	}

	/**
	 * @param \Change\Events\EventManager $eventManager
	 */
	protected function attachEvents(\Change\Events\EventManager $eventManager)
	{

		$eventManager->attach('getProductData', [$this, 'onDefaultGetProductData'], 5);

		$eventManager->attach('getProductsData', [$this, 'onDefaultGetProductsData'], 10);
		$eventManager->attach('getProductsData', [$this, 'onDefaultGetProductsArrayData'], 5);

		$itemOrdering = null;

		$eventManager->attach('updateItemOrdering', function($event) use (&$itemOrdering) {
			if ($itemOrdering === null)
			{
				$itemOrdering = new \Rbs\Catalog\Events\ItemOrdering();
			}
			$itemOrdering->onUpdateProductListItem($event);
		}, 5);
	}

	/**
	 * @var \Change\Transaction\TransactionManager
	 */
	protected $transactionManager;

	/**
	 * @var \Change\Db\DbProvider
	 */
	protected $dbProvider;

	/**
	 * @var \Change\Documents\DocumentManager
	 */
	protected $documentManager;

	/**
	 * @var \Rbs\Price\PriceManager
	 */
	protected $priceManager;

	/**
	 * @var \Rbs\Stock\StockManager
	 */
	protected $stockManager;

	/**
	 * @var \Rbs\Catalog\Attribute\AttributeManager
	 */
	protected $attributeManager;

	/**
	 * @param \Change\Transaction\TransactionManager $transactionManager
	 * @return $this
	 */
	public function setTransactionManager($transactionManager)
	{
		$this->transactionManager = $transactionManager;
		return $this;
	}

	/**
	 * @return \Change\Transaction\TransactionManager
	 */
	protected function getTransactionManager()
	{
		return $this->transactionManager;
	}

	/**
	 * @param \Change\Db\DbProvider $dbProvider
	 * @return $this
	 */
	public function setDbProvider($dbProvider)
	{
		$this->dbProvider = $dbProvider;
		return $this;
	}

	/**
	 * @return \Change\Db\DbProvider
	 */
	protected function getDbProvider()
	{
		return $this->dbProvider;
	}

	/**
	 * @param \Change\Documents\DocumentManager $documentManager
	 * @return $this
	 */
	public function setDocumentManager($documentManager)
	{
		$this->documentManager = $documentManager;
		return $this;
	}

	/**
	 * @return \Change\Documents\DocumentManager
	 */
	protected function getDocumentManager()
	{
		return $this->documentManager;
	}

	/**
	 * @param \Rbs\Price\PriceManager $priceManager
	 * @return $this
	 */
	public function setPriceManager($priceManager)
	{
		$this->priceManager = $priceManager;
		return $this;
	}

	/**
	 * @return \Rbs\Price\PriceManager
	 */
	protected function getPriceManager()
	{
		return $this->priceManager;
	}

	/**
	 * @param \Rbs\Stock\StockManager $stockManager
	 * @return $this
	 */
	public function setStockManager($stockManager)
	{
		$this->stockManager = $stockManager;
		return $this;
	}

	/**
	 * @return \Rbs\Stock\StockManager
	 */
	protected function getStockManager()
	{
		return $this->stockManager;
	}

	/**
	 * @param \Rbs\Catalog\Attribute\AttributeManager $attributeManager
	 * @return $this
	 */
	public function setAttributeManager($attributeManager)
	{
		$this->attributeManager = $attributeManager;
		return $this;
	}

	/**
	 * @return \Rbs\Catalog\Attribute\AttributeManager
	 */
	protected function getAttributeManager()
	{
		return $this->attributeManager;
	}

	//
	// Product lists.
	//

	/**
	 * Add the product in a product list for the given condition/priority.
	 * @api
	 * @param \Rbs\Catalog\Documents\Product $product
	 * @param \Rbs\Catalog\Documents\ProductList $productList
	 * @param \Rbs\Catalog\Documents\Condition $condition
	 * @return \Rbs\Catalog\Documents\ProductListItem
	 * @throws \Exception
	 */
	public function addProductInProductList(\Rbs\Catalog\Documents\Product $product,
		\Rbs\Catalog\Documents\ProductList $productList, $condition)
	{
		$documentManager = $this->getDocumentManager();
		$tm = $this->getTransactionManager();
		$productListItem = null;
		try
		{
			$tm->begin();
			$productListItem = $this->getProductListItem($product, $productList, $condition);
			if (!$productListItem)
			{
				$productListItem = $documentManager->getNewDocumentInstanceByModelName('Rbs_Catalog_ProductListItem');
				/* @var $productListItem \Rbs\Catalog\Documents\ProductListItem */
				$productListItem->setProduct($product);
				$productListItem->setProductList($productList);
				$productListItem->setCondition($condition);
			}
			$productListItem->save();
			$tm->commit();
		}
		catch (\Exception $e)
		{
			throw $tm->rollBack($e);
		}
		return $productListItem;
	}

	/**
	 * @param \Rbs\Catalog\Documents\Product $product
	 * @param \Rbs\Catalog\Documents\ProductList $productList
	 * @param $condition
	 * @throws \Exception
	 */
	public function removeProductFromProductList(\Rbs\Catalog\Documents\Product $product,
		\Rbs\Catalog\Documents\ProductList $productList, $condition)
	{
		$tm = $this->getTransactionManager();
		try
		{
			$tm->begin();
			$productListItem = $this->getProductListItem($product, $productList, $condition);
			if ($productListItem instanceof \Rbs\Catalog\Documents\ProductListItem)
			{
				if ($productListItem->isHighlighted())
				{
					$this->downplayProductInProductList($product, $productList, $condition);
				}
				$productListItem->delete();
			}
			$tm->commit();
		}
		catch (\Exception $e)
		{
			throw $tm->rollBack($e);
		}
	}

	/**
	 * @param \Rbs\Catalog\Documents\Product $product
	 * @param \Rbs\Catalog\Documents\ProductList $productList
	 * @param \Rbs\Catalog\Documents\Condition $condition
	 * @return \Rbs\Catalog\Documents\ProductListItem|null
	 */
	public function getProductListItem(\Rbs\Catalog\Documents\Product $product, \Rbs\Catalog\Documents\ProductList $productList,
		$condition)
	{
		$query = $this->getDocumentManager()->getNewQuery('Rbs_Catalog_ProductListItem');
		$query->andPredicates($query->eq('product', $product), $query->eq('productList', $productList),
			$query->eq('condition', $condition));
		return $query->getFirstDocument();
	}

	/**
	 * This method performs a bulk update, so you should not be messing with positions at all!
	 * @api
	 * @param \Rbs\Catalog\Documents\Product $product
	 * @param \Rbs\Catalog\Documents\ProductList $productList
	 * @param \Rbs\Catalog\Documents\Condition $condition
	 * @param \Rbs\Catalog\Documents\Product $before
	 * @throws \RuntimeException
	 */
	public function highlightProductInProductList(\Rbs\Catalog\Documents\Product $product,
		\Rbs\Catalog\Documents\ProductList $productList, $condition = null, $before = null)
	{
		$productListItem = $this->getProductListItem($product, $productList, $condition);
		if (!$productListItem)
		{
			throw new \RuntimeException("Product to highlight is not in list", 999999);
		}
		$beforeProductListItem = null;
		if ($before instanceof \Rbs\Catalog\Documents\Product)
		{
			$beforeProductListItem = $this->getProductListItem($before, $productList, $condition);
		}
		$this->highlightProductListItem($productListItem, $beforeProductListItem);
	}

	/**
	 * This method performs a bulk update, so you should not be messing with positions at all!
	 * @api
	 * @param \Rbs\Catalog\Documents\Product $product
	 * @param \Rbs\Catalog\Documents\ProductList $productList
	 * @param \Rbs\Catalog\Documents\Condition $condition
	 * @throws \RuntimeException
	 */
	public function downplayProductInProductList(\Rbs\Catalog\Documents\Product $product,
		\Rbs\Catalog\Documents\ProductList $productList, $condition = null)
	{
		$productListItem = $this->getProductListItem($product, $productList, $condition);
		if (!$productListItem)
		{
			throw new \RuntimeException("Product to highlight is not in list", 999999);
		}
		$currentPosition = $productListItem->getPosition();
		if ($currentPosition === 0)
		{
			// Nothing to do.
			return;
		}
		$this->downplayProductListItem($productListItem);
	}

	/**
	 * @param \Rbs\Catalog\Documents\ProductListItem|integer $productListItem
	 * @throws \Exception
	 */
	public function downplayProductListItem($productListItem)
	{
		if (is_numeric($productListItem))
		{
			$productListItem = $this->getDocumentManager()->getDocumentInstance($productListItem);
		}
		if (!($productListItem instanceof \Rbs\Catalog\Documents\ProductListItem))
		{
			throw new \RuntimeException("Invalid Product List Item Identifier", 999999);
		}
		$productList = $productListItem->getProductList();
		$condition = $productListItem->getCondition();
		$tm = $this->getTransactionManager();
		$updateQuery = $this->getDbProvider()->getNewStatementBuilder();
		$fb = $updateQuery->getFragmentBuilder();
		$positionColumn = $fb->getDocumentColumn('position');
		$conditionId = ($condition) ? $condition->getId() : 0;
		$where = $fb->logicAnd(
			$fb->lt($fb->getDocumentColumn('position'), $fb->number(0)),
			$fb->eq($fb->getDocumentColumn('productList'), $fb->number($productList->getId())),
			$fb->eq($fb->getDocumentColumn('condition'), $fb->number($conditionId))
		);

		try
		{
			$tm->begin();
			$updateQuery->update($fb->getDocumentTable($productListItem->getDocumentModel()->getRootName()));
			$updateQuery->assign($positionColumn, $fb->addition($positionColumn, $fb->number(1)));
			$updateQuery->where($fb->logicAnd($where,
				$fb->lte($positionColumn, $fb->number($productListItem->getPosition()))));
			$updateQuery->updateQuery()->execute();
			$productListItem->setPosition(0);
			$productListItem->save();
			$tm->commit();
		}
		catch (\Exception $e)
		{
			throw $tm->rollBack($e);
		}
	}

	/**
	 * @param \Rbs\Catalog\Documents\ProductListItem|integer $productListItem
	 * @param \Rbs\Catalog\Documents\ProductListItem|null $beforeProductListItem
	 * @throws \RuntimeException
	 * @throws \Exception
	 * @internal param $before
	 */
	public function highlightProductListItem($productListItem, $beforeProductListItem = null)
	{
		if (is_numeric($productListItem))
		{
			$productListItem = $this->getDocumentManager()->getDocumentInstance($productListItem);
		}
		if (!($productListItem instanceof \Rbs\Catalog\Documents\ProductListItem))
		{
			throw new \RuntimeException("Invalid Product List Item Identifier", 999999);
		}
		$productList = $productListItem->getProductList();
		$condition = $productListItem->getCondition();
		$currentPosition = $productListItem->getPosition();
		$atPosition = -1;
		if ($beforeProductListItem instanceof \Rbs\Catalog\Documents\ProductListItem)
		{
			$beforePosition = $beforeProductListItem->getPosition();
			if ($currentPosition < 0)
			{
				// We are re-ordering.
				$atPosition = $currentPosition < $beforePosition ? $beforePosition - 1 : $beforePosition;
			}
			else
			{
				// We introduce a new node.
				$atPosition = $beforePosition - 1;
			}
		}
		if ($currentPosition === $atPosition)
		{
			// Nothing to do.
			return;
		}
		// Prepare what's needed for queries.
		$updateQuery = $this->getDbProvider()->getNewStatementBuilder();
		$fb = $updateQuery->getFragmentBuilder();
		$positionColumn = $fb->getDocumentColumn('position');
		$conditionId = ($condition) ? $condition->getId() : 0;
		$where = $fb->logicAnd(
			$fb->lt($fb->getDocumentColumn('position'), $fb->number(0)),
			$fb->eq($fb->getDocumentColumn('productList'), $fb->number($productList->getId())),
			$fb->eq($fb->getDocumentColumn('condition'), $fb->number($conditionId))
		);

		$tm = $this->getTransactionManager();
		if ($currentPosition == 0)
		{
			try
			{
				$tm->begin();
				$updateQuery->update($fb->getDocumentTable($productListItem->getDocumentModel()->getRootName()));
				$updateQuery->assign($positionColumn, $fb->subtraction($positionColumn, $fb->number(1)));
				$updateQuery->where($fb->logicAnd($where, $fb->lte($positionColumn, $fb->number($atPosition))));
				$updateQuery->updateQuery()->execute();
				$productListItem->setPosition($atPosition);
				$productListItem->save();
				$tm->commit();
			}
			catch (\Exception $e)
			{
				throw $tm->rollBack($e);
			}
		}
		else
		{
			try
			{
				$tm->begin();
				$updateQuery->update($fb->getDocumentTable($productListItem->getDocumentModel()->getRootName()));
				if ($currentPosition > $atPosition)
				{
					$updateQuery->assign($positionColumn, $fb->addition($positionColumn, $fb->number(1)));
					$updateQuery->where($fb->logicAnd($where, $fb->gte($positionColumn, $fb->number($atPosition),
						$fb->lt($positionColumn, $fb->number($currentPosition)))));
				}
				else
				{
					$updateQuery->assign($positionColumn, $fb->subtraction($positionColumn, $fb->number(1)));
					$updateQuery->where($fb->logicAnd($where, $fb->lte($positionColumn, $fb->number($atPosition),
						$fb->gt($positionColumn, $fb->number($currentPosition)))));
				}
				$updateQuery->updateQuery()->execute();
				$productListItem->setPosition($atPosition);
				$productListItem->save();
				$tm->commit();
			}
			catch (\Exception $e)
			{
				throw $tm->rollBack($e);
			}
		}
	}

	/**
	 * @api
	 * @param \Rbs\Catalog\Documents\ProductListItem|integer $productListItem
	 * @throws \RuntimeException
	 * @throws \Exception
	 */
	public function moveProductListItemDown($productListItem)
	{
		if (is_numeric($productListItem))
		{
			$productListItem = $this->getDocumentManager()->getDocumentInstance($productListItem);
		}
		if (!($productListItem instanceof \Rbs\Catalog\Documents\ProductListItem))
		{
			throw new \RuntimeException("Invalid Product List Item Identifier", 999999);
		}
		if ($productListItem->getPosition() == 0)
		{
			// Nothing to do.
			return;
		}
		$query = $this->getDocumentManager()->getNewQuery('Rbs_Catalog_ProductListItem');
		$condition = $productListItem->getCondition();

		$query->andPredicates(
			$query->eq('productList', $productListItem->getProductList()),
			$query->eq('condition', $condition),
			$query->gt('position', $query->getFragmentBuilder()->number($productListItem->getPosition())),
			$query->lt('position', $query->getFragmentBuilder()->number(0))
		);

		$query->addOrder('position', true);
		/* @var $downCat \Rbs\Catalog\Documents\ProductListItem */
		$downCat = $query->getFirstDocument();
		if ($downCat)
		{
			$tm = $this->getTransactionManager();
			try
			{
				$tm->begin();
				$toPosition = $downCat->getPosition();
				$fromPosition = $productListItem->getPosition();
				$productListItem->setPosition($toPosition);
				$downCat->setPosition($fromPosition);
				$productListItem->save();
				$downCat->save();
				$tm->commit();
			}
			catch (\Exception $e)
			{
				throw $tm->rollBack($e);
			}
		}
	}

	/**
	 * @api
	 * @param \Rbs\Catalog\Documents\ProductListItem|integer $productListItem
	 * @throws \RuntimeException
	 * @throws \Exception
	 */
	public function moveProductListItemUp($productListItem)
	{
		if (is_numeric($productListItem))
		{
			$productListItem = $this->getDocumentManager()->getDocumentInstance($productListItem);
		}
		if (!($productListItem instanceof \Rbs\Catalog\Documents\ProductListItem))
		{
			throw new \RuntimeException("Invalid Product List Item Identifier", 999999);
		}
		if ($productListItem->getPosition() == 0)
		{
			// Nothing to do.
			return;
		}
		$query = $this->getDocumentManager()->getNewQuery('Rbs_Catalog_ProductListItem');
		$condition = $productListItem->getCondition();
		$query->andPredicates(
			$query->eq('productList', $productListItem->getProductList()),
			$query->eq('condition', $condition),
			$query->lt('position', $query->getFragmentBuilder()->number($productListItem->getPosition()))
		);

		$query->addOrder('position', false);
		/* @var $upCat \Rbs\Catalog\Documents\ProductListItem */
		$upCat = $query->getFirstDocument();
		if ($upCat)
		{
			$tm = $this->getTransactionManager();
			try
			{
				$tm->begin();
				$toPosition = $upCat->getPosition();
				$fromPosition = $productListItem->getPosition();
				$productListItem->setPosition($toPosition);
				$upCat->setPosition($fromPosition);
				$productListItem->save();
				$upCat->save();
				$tm->commit();
			}
			catch (\Exception $e)
			{
				throw $tm->rollBack($e);
			}
		}
	}

	/**
	 * @param \Rbs\Catalog\Documents\ProductListItem|integer $productListItem
	 * @throws \RuntimeException
	 */
	public function highlightProductListItemTop($productListItem)
	{
		if (is_numeric($productListItem))
		{
			$productListItem = $this->getDocumentManager()->getDocumentInstance($productListItem);
		}
		if (!($productListItem instanceof \Rbs\Catalog\Documents\ProductListItem))
		{
			throw new \RuntimeException("Invalid Product List Item Identifier", 999999);
		}
		$query = $this->getDocumentManager()->getNewQuery('Rbs_Catalog_ProductListItem');
		$condition = $productListItem->getCondition();
		$query->andPredicates(
			$query->eq('productList', $productListItem->getProductList()),
			$query->eq('condition', $condition),
			$query->lt('position', $query->getFragmentBuilder()->number(0))
		);
		$query->addOrder('position', true);
		$topProductListItem = $query->getFirstDocument();
		if ($topProductListItem == null)
		{
			return;
		}
		/* @var $topProductListItem \Rbs\Catalog\Documents\ProductListItem */
		if ($topProductListItem->getId() == $productListItem->getId())
		{
			return;
		}
		$this->highlightProductListItem($productListItem, $topProductListItem);
	}

	/**
	 * @param \Rbs\Catalog\Documents\ProductListItem|integer $productListItem
	 * @throws \RuntimeException
	 */
	public function highlightProductListItemBottom($productListItem)
	{
		if (is_numeric($productListItem))
		{
			$productListItem = $this->getDocumentManager()->getDocumentInstance($productListItem);
		}
		if (!($productListItem instanceof \Rbs\Catalog\Documents\ProductListItem))
		{
			throw new \RuntimeException("Invalid Product List Item Identifier", 999999);
		}
		$this->highlightProductListItem($productListItem);
	}

	/**
	 * @param  \Rbs\Catalog\Documents\ProductListItem|integer $productListItem
	 * @throws \RuntimeException
	 */
	public function deleteProductListItem($productListItem)
	{
		if (is_numeric($productListItem))
		{
			$productListItem = $this->getDocumentManager()->getDocumentInstance($productListItem);
		}
		if (!($productListItem instanceof \Rbs\Catalog\Documents\ProductListItem))
		{
			throw new \RuntimeException("Invalid Product List Item Identifier", 999999);
		}
		if ($productListItem->isHighlighted())
		{
			$this->downplayProductListItem($productListItem);
		}
		$productListItem->delete();
	}

	/**
	 * @param \Rbs\Website\Documents\Section $section
	 * @return \Change\Documents\AbstractDocument|null
	 */
	public function getDefaultProductListBySection($section)
	{
		$query = $this->getDocumentManager()->getNewQuery('Rbs_Catalog_SectionProductList');
		$query->andPredicates(
			$query->eq('synchronizedSection', $section)
		);
		$query->addOrder('id', false);
		$defaultProductList = $query->getFirstDocument();

		return $defaultProductList;
	}

	//
	// Product Data.
	//

	/**
	 * @api
	 * @param \Rbs\Catalog\Documents\Product $product
	 * @param boolean $onlyPublishedProduct
	 * @return array
	 */
	public function getAllSku($product, $onlyPublishedProduct = false)
	{
		if ($product->getProductSet())
		{
			$set = $product->getProductSet();
			$skuArray = [];
			foreach($set->getProducts() as $subProduct)
			{
				if ($onlyPublishedProduct && !$subProduct->published())
				{
					continue;
				}

				if ($subProduct->getSku())
				{
					$skuArray[] = $subProduct->getSku();
				}
				else
				{
					$skuArray = array_merge($skuArray, $this->getAllSku($subProduct, $onlyPublishedProduct));
				}
			}
			return $skuArray;
		}
		else
		{
			if (!$product->getSku() && $product->getVariantGroup())
			{
				$skuArray = [];
				foreach ($this->getVariantDescendantIds($product) as $id)
				{
					$descProduct = $this->getDocumentManager()->getDocumentInstance($id);
					if ($descProduct instanceof \Rbs\Catalog\Documents\Product)
					{
						if (!$onlyPublishedProduct || $descProduct->published())
						{
							$sku = $descProduct->getSku();
							if ($sku)
							{
								$skuArray[] = $sku;
							}
						}
					}
				}
				return $skuArray;
			}
		}
		return [];
	}

	/**
	 * @api
	 * @param \Rbs\Stock\Documents\Sku $sku
	 * @return \Rbs\Catalog\Documents\Product[]
	 */
	public function getProductsBySku($sku)
	{
		$products = [];
		if ($sku instanceof \Rbs\Stock\Documents\Sku)
		{
			$documentManager = $this->getDocumentManager();
			$q = $documentManager->getNewQuery('Rbs_Catalog_Product');
			$q->andPredicates($q->eq('sku', $sku));

			/** @var $product \Rbs\Catalog\Documents\Product */
			foreach ($q->getDocuments() as $product)
			{
				if (isset($products[$product->getId()]))
				{
					continue;
				}

				$products[$product->getId()] = $product;

				if ($product->getVariant() && $product->getVariantGroup())
				{
					$ancestorIds = $this->getVariantAncestorIds($product);
					$ancestorIds[] = $product->getVariantGroup()->getRootProductId();
					foreach ($ancestorIds as $ancestorId)
					{
						if (isset($products[$ancestorId]))
						{
							continue;
						}
						$ancestorProduct = $documentManager->getDocumentInstance($ancestorId);
						if ($ancestorProduct instanceof \Rbs\Catalog\Documents\Product)
						{
							$products[$ancestorId] = $ancestorProduct;
						}
					}
				}

				$query = $documentManager->getNewQuery('Rbs_Catalog_ProductSet');
				$query->andPredicates($query->eq('products', $product));
				$productSets = $query->getDocuments();

				/** @var $productSet \Rbs\Catalog\Documents\ProductSet */
				foreach ($productSets as $productSet)
				{
					$rootProduct = $productSet->getRootProduct();
					if ($rootProduct)
					{
						$products[$rootProduct->getId()] = $rootProduct;
					}
				}
			}
		}
		return array_values($products);
	}

	/**
	 * Default context:
	 *  - *dataSetNames, *visualFormats, *URLFormats
	 *  - website, websiteUrlManager, section, page, detailed
	 *  - *data
	 *     - billingAreaId
	 *     - webStoreId
	 *     - zone
	 * @api
	 * @param \Rbs\Catalog\Documents\Product|integer $product
	 * @param array $context
	 * @return array
	 */
	public function getProductData($product, array $context)
	{
		$em = $this->getEventManager();
		if (is_numeric($product))
		{
			$product = $this->getDocumentManager()->getDocumentInstance($product, 'Rbs_Catalog_Product');
		}

		if ($product instanceof \Rbs\Catalog\Documents\Product && $product->published())
		{
			$eventArgs = $em->prepareArgs(['product' => $product, 'context' => $context]);
			$this->getEventManager()->trigger('getProductData', $this, $eventArgs);
			if (isset($eventArgs['productData']))
			{
				$productData = $eventArgs['productData'];
				if (is_object($productData))
				{
					$callable = [$productData, 'toArray'];
					if (is_callable($callable))
					{
						$productData = call_user_func($callable);
					}
				}
				if (is_array($productData))
				{
					return $productData;
				}
			}
		}
		return [];
	}

	/**
	 * Input params: product, context
	 * Output param: productData
	 * @param \Change\Events\Event $event
	 */
	public function  onDefaultGetProductData(\Change\Events\Event $event)
	{
		if (!$event->getParam('productData'))
		{
			$productDataComposer = new \Rbs\Catalog\Product\ProductDataComposer($event);
			$event->setParam('productData', $productDataComposer->toArray());
		}
	}

	/**
	 * Context:
	 *  - *dataSetNames, *visualFormats, *URLFormats, pagination
	 *  - website, websiteUrlManager, section, page, detailed
	 *  - *data
	 *     - listId
	 *     - conditionId
	 *     - sortBy
	 *     - showUnavailable
	 *     - billingAreaId
	 *     - webStoreId
	 *     - zone
	 * @api
	 * @param array $context
	 * @return array
	 */
	public function getProductsData(array $context)
	{
		$em = $this->getEventManager();
		$eventArgs = $em->prepareArgs(['context' => $context]);
		$this->getEventManager()->trigger('getProductsData', $this, $eventArgs);

		$productsData = [];
		$pagination = ['offset' => 0, 'limit' => 100, 'count' => 0];
		if (isset($eventArgs['productsData']) && is_array($eventArgs['productsData']))
		{
			if (isset($eventArgs['pagination']) && is_array($eventArgs['pagination']))
			{
				$pagination = $eventArgs['pagination'];
			}

			foreach ($eventArgs['productsData'] as $productData)
			{
				if (is_object($productData))
				{
					$callable = [$productData, 'toArray'];
					if (is_callable($callable))
					{
						$productData = call_user_func($callable);
					}
				}

				if (is_array($productData) && count($productData))
				{
					$productsData[] = $productData;
				}
			}
		}
		return ['pagination' => $pagination, 'items' => $productsData];
	}

	/**
	 * Input params: list, context
	 * Output param: productsData, pagination
	 * @param \Change\Events\Event $event
	 */
	public function onDefaultGetProductsData(\Change\Events\Event $event)
	{
		/** @var $context array */
		$context = $event->getParam('context');
		$products = $event->getParam('products');
		if (!is_array($context) || $products !== null)
		{
			return;
		}
		$list = (isset($context['data']) && isset($context['data']['listId'])) ? intval($context['data']['listId']) : null;
		if (is_numeric($list))
		{
			$list = $this->getDocumentManager()->getDocumentInstance($list);
		}

		if ($list instanceof \Rbs\Catalog\Documents\ProductList)
		{
			$applicationServices = $event->getApplicationServices();

			/* @var $commerceServices \Rbs\Commerce\CommerceServices */
			$commerceServices = $event->getServices('commerceServices');

			$productArrayResolver = new \Rbs\Catalog\Product\ProductArrayResolver($list, $context,
				$applicationServices->getDocumentManager(),
				$commerceServices->getStockManager(), $applicationServices->getDbProvider());

			$event->setParam('products', $productArrayResolver->getProductArray());
			$event->setParam('pagination', ['offset' => $productArrayResolver->getOffset(),
				'limit' => $productArrayResolver->getLimit(),
				'count' => $productArrayResolver->getTotalCount()]);
		}
	}

	/**
	 * Input params: list, context
	 * Output param: productsData, pagination
	 * @param \Change\Events\Event $event
	 */
	public function onDefaultGetProductsArrayData(\Change\Events\Event $event)
	{
		$products = $event->getParam('products');
		$context = $event->getParam('context');
		$productsData = $event->getParam('productsData');
		if ($productsData === null && is_array($context) && is_array($products) && count($products))
		{
			$productsData = [];
			foreach ($products as $product)
			{
				$productData = $this->getProductData($product, $context);
				if (is_array($productData) && count($productData))
				{
					$productsData[] = $productData;
				}
			}
			$event->setParam('productsData', $productsData);
		}
	}

	/**
	 * @param \Rbs\Catalog\Documents\ProductListItem $productListItem
	 * @param array $options
	 * @throws \Exception
	 */
	public function updateItemOrdering($productListItem, array $options = null)
	{
		if (!is_array($options))
		{
			$options = [];
		}
		$options['productListItem'] = $productListItem;
		$this->getEventManager()->trigger('updateItemOrdering', $this, $options);
	}

	/**
	 * @param \Rbs\Catalog\Documents\Product $product
	 * @param array $options
	 * @throws \Exception
	 */
	public function updateItemsOrdering($product, array $options = null)
	{
		if (!is_array($options))
		{
			$options = [];
		}

		$query = $this->getDocumentManager()->getNewQuery('Rbs_Catalog_ProductListItem');
		$items = $query->andPredicates($query->eq('product', $product))->getDocuments();
		if ($items->count())
		{
			try
			{
				$this->getTransactionManager()->begin();
				/** @var \Rbs\Catalog\Documents\ProductListItem $item */
				foreach ($items as $item)
				{
					$this->updateItemOrdering($item, $options);
				}
				$this->getTransactionManager()->commit();
			}
			catch (\Exception $e)
			{
				throw $this->getTransactionManager()->rollBack($e);
			}
		}
	}

	/**
	 * @var array
	 */
	protected $cachedVariantProductsData = [];

	/**
	 * @param \Rbs\Catalog\Documents\VariantGroup|\Rbs\Catalog\Documents\Product|integer $product
	 * @return array
	 */
	public function getVariantProductsData($product)
	{
		if (is_numeric($product))
		{
			$product = $this->getDocumentManager()->getDocumentInstance($product);
		}
		if ($product instanceof \Rbs\Catalog\Documents\Product)
		{
			$product = $product->getVariantGroup();
		}

		if ($product instanceof \Rbs\Catalog\Documents\VariantGroup)
		{
			if (isset($this->cachedVariantProductsData[$product->getId()]))
			{
				return $this->cachedVariantProductsData[$product->getId()];
			}
			$variantProducts = $product->getVariantProducts();

			/** @var \Rbs\Catalog\Documents\Attribute[] $axesAttributes */
			$axesAttributes = $product->getAxesAttributes()->toArray();
			$productsData = [];
			if (count($axesAttributes) && count($variantProducts))
			{
				foreach ($variantProducts as $variantProduct)
				{
					$productData = ['id' => $variantProduct->getId(), 'published' => $variantProduct->published()];
					$axesValues = [];
					$indexedValues = [];
					foreach ($this->getAttributeManager()->getProductAxesValue($variantProduct, $axesAttributes) as $axisValue)
					{
						$indexedValues[$axisValue['id']] = $axisValue['value'];
					}
					foreach ($axesAttributes as $axesAttribute)
					{
						$value = isset($indexedValues[$axesAttribute->getId()]) ? $indexedValues[$axesAttribute->getId()] : null;
						if ($value === null) {
							break;
						}
						$axesValues[] = $value;
					}
					$productData['axesValues'] = $axesValues;
					$productsData[] = $productData;
				}
				usort($productsData, function ($vPDA, $vPDB) {
					$axA = $vPDA['axesValues'];
					$axB = $vPDB['axesValues'];
					if ($axA == $axB) {return 0;}
					$cmp = min(count($axA), count($axB));
					for ($i = 0; $i < $cmp; $i++) {
						if ($axA[$i] != $axB[$i]) {
							return $axA[$i] < $axB[$i] ? -1 : 1;
						}
					}
					return (count($axA) < count($axB)) ? -1 : 1;
				});
			}
			$this->cachedVariantProductsData[$product->getId()] = $productsData;
			return $productsData;
		}
		return [];
	}

	/**
	 * @api
	 * @param integer|\Rbs\Catalog\Documents\Product $product
	 * @param array|null $variantProductsData @see \Rbs\Catalog\CatalogManager::getVariantProductsData()
	 * @return array
	 */
	public function getVariantAncestorIds($product, array $variantProductsData = null)
	{
		$ancestorIds = [];
		if ($variantProductsData === null)
		{
			$variantProductsData = $this->getVariantProductsData($product);
		}
		if (count($variantProductsData))
		{
			if ($product instanceof \Rbs\Catalog\Documents\Product)
			{
				$product = $product->getId();
			}
			if (is_numeric($product))
			{
				foreach ($variantProductsData as $index => $vPD)
				{
					if ($vPD['id'] == $product)
					{
						if (($countAxes = count($vPD['axesValues'])) > 1)
						{
							$ancestorsData = array_slice($variantProductsData, 0, $index);
							$axesValues = [];
							for ($i = 1; $i < $countAxes; $i++)
							{
								$axesValues[] = $vPD['axesValues'][$i-1];
								$ancestorIds = array_reduce($ancestorsData, function($ancestorIds, $data) use ($axesValues) {
									if ($axesValues == $data['axesValues'])
									{
										$ancestorIds[] = $data['id'];
									}
									return $ancestorIds;
								}, $ancestorIds);
							}
						}
						break;
					}
				}
			}
		}
		return $ancestorIds;
	}

	/**
	 * @api
	 * @param integer|\Rbs\Catalog\Documents\Product $product
	 * @param array|null $variantProductsData @see \Rbs\Catalog\CatalogManager::getVariantProductsData()
	 * @return array
	 */
	public function getVariantDescendantIds($product, array $variantProductsData = null)
	{
		$descendantIds = [];
		if ($variantProductsData === null)
		{
			$variantProductsData = $this->getVariantProductsData($product);
		}
		if (($countVariants = count($variantProductsData)) > 0)
		{
			$productId = ($product instanceof \Rbs\Catalog\Documents\Product) ? $product->getId() : $product;
			if (is_numeric($productId))
			{
				for ($index = 0; $index < $countVariants; $index++) {
					$vPD = $variantProductsData[$index];
					if ($vPD['id'] == $productId)
					{
						$countAxes = count($vPD['axesValues']);
						for($i = $index + 1; $i < $countVariants; $i++)
						{
							$cPD = $variantProductsData[$i];
							if (count($cPD['axesValues']) > $countAxes &&
								array_slice($cPD['axesValues'], 0 ,$countAxes) == $vPD['axesValues'])
							{
								$descendantIds[] = $cPD['id'];
							}
							else
							{
								break;
							}
						}
						return $descendantIds;
					}
				}
				foreach ($variantProductsData as $vPD)
				{
					$descendantIds[] = $vPD['id'];
				}
			}
		}
		return $descendantIds;
	}

	/**
	 * @api
	 * @param \Rbs\Catalog\Documents\Product|integer $product
	 * @return \Rbs\Media\Documents\Image[]
	 */
	public function getProductVisuals($product)
	{
		if (is_numeric($product))
		{
			$product = $this->getDocumentManager()->getDocumentInstance($product);
		}
		if (!($product instanceof \Rbs\Catalog\Documents\Product))
		{
			return [];
		}
		$visuals = $product->getVisualsCount() ? $product->getVisuals()->toArray() : [];
		if (!count($visuals) && $product->getVariant())
		{
			$ancestorProductIds = array_reverse($this->getVariantAncestorIds($product));
			if (count($ancestorProductIds))
			{
				foreach ($ancestorProductIds as $ancestorProductId)
				{
					$ancestorProduct = $this->getDocumentManager()->getDocumentInstance($ancestorProductId);
					if ($ancestorProduct instanceof \Rbs\Catalog\Documents\Product &&
						$ancestorProduct->getVisualsCount() && $ancestorProduct->published())
					{
						return $ancestorProduct->getVisuals()->toArray();
					}
				}
			}
			return $this->getProductVisuals($product->getVariantGroup()->getRootProduct());
		}
		return $visuals;
	}
}