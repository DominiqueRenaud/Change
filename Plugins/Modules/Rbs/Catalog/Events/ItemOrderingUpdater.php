<?php
/**
 * Copyright (C) 2014 Ready Business System, Eric Hauswald
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/.
 */
namespace Rbs\Catalog\Events;

/**
 * @name \Rbs\Catalog\Events\ItemOrderingUpdater
 */
class ItemOrderingUpdater
{
	/**
	 * @param \Change\Events\Event $event
	 * @throws \Exception
	 */
	public function onItemChange($event)
	{
		if ($event instanceof \Change\Documents\Events\Event)
		{
			/** @var $commerceServices \Rbs\Commerce\CommerceServices */
			$commerceServices = $event->getServices('commerceServices');
			$document = $event->getDocument();
			if ($document instanceof \Rbs\Catalog\Documents\ProductListItem)
			{
				$commerceServices->getCatalogManager()->updateItemOrdering($document);
			}
		}
	}

	/**
	 * @param \Change\Events\Event $event
	 * @throws \Exception
	 */
	public function onPriceChange($event)
	{
		if ($event instanceof \Change\Documents\Events\Event)
		{
			/** @var $commerceServices \Rbs\Commerce\CommerceServices */
			$commerceServices = $event->getServices('commerceServices');
			$document = $event->getDocument();
			if ($document instanceof \Rbs\Price\Documents\Price)
			{
				$sku = $document->getSku();
				if ($sku instanceof \Rbs\Stock\Documents\Sku)
				{
					$products = $commerceServices->getCatalogManager()->getProductsBySku($sku);
					foreach ($products as $product)
					{
						$commerceServices->getCatalogManager()->updateItemsOrdering($product);
					}
				}
			}
		}
	}

	/**
	 * @param \Change\Events\Event $event
	 * @throws \Exception
	 */
	public function onSkuChange($event)
	{
		if ($event instanceof \Change\Documents\Events\Event)
		{
			/** @var $commerceServices \Rbs\Commerce\CommerceServices */
			$commerceServices = $event->getServices('commerceServices');
			$document = $event->getDocument();
			if ($document instanceof \Rbs\Stock\Documents\Sku)
			{
				$modifiedPropertyNames = $event->getParam('modifiedPropertyNames', []);
				if (count(array_intersect(['thresholds', 'unlimitedInventory'], $modifiedPropertyNames)))
				{
					$products = $commerceServices->getCatalogManager()->getProductsBySku($document);
					foreach ($products as $product)
					{
						$commerceServices->getCatalogManager()->updateItemsOrdering($product);
					}
				}
			}
		}
	}

	/**
	 * @param \Change\Events\Event $event
	 * @throws \Exception
	 */
	public function onInventoryEntryChange($event)
	{
		if ($event instanceof \Change\Documents\Events\Event)
		{
			/** @var $commerceServices \Rbs\Commerce\CommerceServices */
			$commerceServices = $event->getServices('commerceServices');
			$catalogManager = $commerceServices->getCatalogManager();
			$document = $event->getDocument();
			if ($document instanceof \Rbs\Stock\Documents\InventoryEntry)
			{
				$sku = $document->getSku();
				if ($sku instanceof \Rbs\Stock\Documents\Sku)
				{
					$products = $catalogManager->getProductsBySku($sku);
					foreach ($products as $product)
					{
						$catalogManager->updateItemsOrdering($product);

						$query = $event->getApplicationServices()->getDocumentManager()->getNewQuery('Rbs_Catalog_ProductSet');
						$query->andPredicates($query->eq('products', $product));
						$productSets = $query->getDocuments();

						/** @var $productSet \Rbs\Catalog\Documents\ProductSet */
						foreach ($productSets as $productSet)
						{
							$rootProduct = $productSet->getRootProduct();
							if ($rootProduct)
							{
								$catalogManager->updateItemsOrdering($rootProduct);
							}
						}
					}
				}
			}
		}
	}

	/**
	 * @param \Change\Job\Event $event
	 * @throws \Exception
	 */
	public function onScheduledActivation($event)
	{
		if ($event instanceof \Change\Job\Event)
		{
			$job = $event->getJob();
			$documentId = $job->getArgument('documentId');
			if ($documentId)
			{
				$document = $event->getApplicationServices()->getDocumentManager()->getDocumentInstance($documentId);
				if ($document instanceof \Rbs\Price\Documents\Price)
				{
					$commerceServices = $event->getServices('commerceServices');
					$sku = $document->getSku();
					if ($sku instanceof \Rbs\Stock\Documents\Sku && $commerceServices instanceof \Rbs\Commerce\CommerceServices)
					{
						$catalogManager = $commerceServices->getCatalogManager();
						$products = $catalogManager->getProductsBySku($sku);
						foreach ($products as $product)
						{
							if ($product instanceof \Rbs\Catalog\Documents\Product)
							{
								$catalogManager->updateItemsOrdering($product);
							}
						}
					}
				}
				elseif ($document instanceof \Rbs\Catalog\Documents\ProductListItem)
				{
					$commerceServices = $event->getServices('commerceServices');
					if ($commerceServices instanceof \Rbs\Commerce\CommerceServices)
					{
						$commerceServices->getCatalogManager()->updateItemOrdering($document);
					}
				}
			}
		}
	}
} 