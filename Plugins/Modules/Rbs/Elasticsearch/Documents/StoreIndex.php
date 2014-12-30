<?php
/**
 * Copyright (C) 2014 Ready Business System
 *
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/.
 */
namespace Rbs\Elasticsearch\Documents;

use Change\Documents\Interfaces\Publishable;

/**
 * @name \Rbs\Elasticsearch\Documents\StoreIndex
 */
class StoreIndex extends \Compilation\Rbs\Elasticsearch\Documents\StoreIndex
{
	protected function attachEvents($eventManager)
	{
		parent::attachEvents($eventManager);
		$eventManager->attach('getFacetsDefinition', [$this, 'onDefaultGetFacetsDefinition'], 5);
		$eventManager->attach('getDocumentIndexData', [$this, 'onDefaultGetDocumentIndexData'], 5);
		$eventManager->attach('getDocumentIndexData', [$this, 'onDefaultGetDocumentIndexFacetData'], 1);
	}

	protected function onCreate()
	{
		$this->setCategory('store');
		if (!$this->getName())
		{
			$this->setName($this->getCategory() . '_' . $this->getWebsiteId() . '_' . strtolower($this->getAnalysisLCID()));
		}
		parent::onCreate();
	}

	/**
	 * @param \Change\I18n\I18nManager $i18nManager
	 * @return string
	 */
	public function composeRestLabel(\Change\I18n\I18nManager $i18nManager)
	{
		if ($this->getWebsite())
		{
			$key = 'm.rbs.elasticsearch.admin.store_label_website';
			return $i18nManager->trans($key, array('ucf'), array('websiteLabel' => $this->getWebsite()->getLabel()));
		}
		return $this->getLabel();
	}

	/**
	 * @param \Change\Documents\Events\Event $event
	 */
	public function onDefaultGetFacetsDefinition(\Change\Documents\Events\Event $event)
	{

		$facetsDefinition = [];
		$query = $this->getDocumentManager()->getNewQuery('Rbs_Elasticsearch_Facet');
		$query->andPredicates($query->eq('indexCategory', $this->getCategory()));

		$facets = $query->getDocuments();
		/** @var $facet \Rbs\Elasticsearch\Documents\Facet */
		foreach ($facets as $facet)
		{
			$facetDefinition = $facet->getFacetDefinition();
			if ($facetDefinition instanceof \Rbs\Elasticsearch\Facet\FacetDefinitionInterface)
			{
				$facetsDefinition[] = $facetDefinition;
			}
		}
		$event->setParam('facetsDefinition', $facetsDefinition);
	}

	/**
	 * @param \Rbs\Elasticsearch\Index\IndexManager $indexManager
	 * @param \Change\Documents\AbstractDocument|integer $document
	 * @param \Change\Documents\AbstractModel $model
	 * @return array [type => [propety => value]]
	 */
	public function getDocumentIndexData(\Rbs\Elasticsearch\Index\IndexManager $indexManager, $document, $model = null)
	{
		if ($this->getWebsite())
		{
			if ($document instanceof \Rbs\Catalog\Documents\Product)
			{
				$eventManager = $this->getEventManager();
				$args = $eventManager->prepareArgs(['document' => $document, 'indexManager' => $indexManager]);
				$eventManager->trigger('getDocumentIndexData', $this, $args);
				$documentData =  (isset($args['documentData']) && is_array($args['documentData'])) ? $args['documentData'] : [];
				return [$this->getDefaultTypeName() => $documentData];
			}
			else
			{
				$documentId = $document;
				if ($document instanceof \Change\Documents\AbstractDocument)
				{
					$model = $document->getDocumentModel();
					$documentId = $document->getId();
				}
				elseif ($model === null)
				{
					return [];
				}

				if ($model->isInstanceOf('Rbs_Catalog_Product'))
				{
					return [$this->getDefaultTypeName() => []];
				}
				elseif ($model->isInstanceOf('Rbs_Catalog_ProductSet'))
				{
					$rootProduct = $model->getPropertyValue($document, 'rootProduct');
					if ($rootProduct)
					{
						$this->indexProducts($indexManager, [$rootProduct]);
					}
				}
				elseif ($model->isInstanceOf('Rbs_Stock_InventoryEntry'))
				{
					$products = $this->getIndexedProductByInventoryEntryId($indexManager, $documentId);
					if (count($products))
					{
						$this->indexProducts($indexManager, $products);
					}
				}
				elseif ($model->isInstanceOf('Rbs_Price_Price'))
				{
					$products = $this->getIndexedProductByPriceId($indexManager, $documentId);
					$price = $document;
					if ($price instanceof \Rbs\Price\Documents\Price && $price->getSkuId())
					{
						$skuId = $price->getSkuId();
						$query = $this->getDocumentManager()->getNewQuery('Rbs_Catalog_Product');
						$query->andPredicates($query->eq('sku', $skuId));

						/** @var $product \Rbs\Catalog\Documents\Product */
						foreach ($query->getDocuments() as $product)
						{
							if (!isset($products[$product->getId()]))
							{
								$products[$product->getId()] = $product;
							}
						}
					}

					if (count($products))
					{
						$this->indexProducts($indexManager, $products);
					}
				}
				elseif ($model->isInstanceOf('Rbs_Catalog_ProductListItem'))
				{
					$indexedItem = $this->getIndexedListItemInfoId($indexManager, $documentId);
					if ($document instanceof \Rbs\Catalog\Documents\ProductListItem)
					{
						if ($indexedItem)
						{
							list($itemId, $listId, $productId, $position) = $indexedItem;
							if ($document->getPosition() != $position)
							{
								$query = $this->getDocumentManager()->getNewQuery('Rbs_Catalog_Product');
								$subQuery = $query->getModelBuilder('Rbs_Catalog_ProductListItem', 'product');
								$subQuery->andPredicates($subQuery->eq('productList', $listId));
								$products = $query->getDocuments();
								$this->indexProducts($indexManager, $products);
							}
							elseif ($document->getProduct())
							{
								$this->indexProducts($indexManager, [$document->getProduct()]);
							}
						}
						elseif ($document->getProduct())
						{
							$this->indexProducts($indexManager, [$document->getProduct()]);
						}
					}
					elseif ($indexedItem)
					{
						list($itemId, $listId, $productId, $position) = $indexedItem;
						$product = $this->getDocumentManager()->getDocumentInstance($productId, 'Rbs_Catalog_Product');
						if ($product instanceof \Rbs\Catalog\Documents\Product)
						{
							$this->indexProducts($indexManager, [$product]);
						}

						if ($position < 0)
						{
							$query = $this->getDocumentManager()->getNewQuery('Rbs_Catalog_Product');
							$subQuery = $query->getModelBuilder('Rbs_Catalog_ProductListItem', 'product');
							$subQuery->andPredicates($subQuery->eq('productList', $listId));
							$products = $query->getDocuments();
							$this->indexProducts($indexManager, $products);
						}
					}
				}
			}
		}
		return [];
	}

	/**
	 * @var \Rbs\Elasticsearch\Index\PublicationData
	 */
	protected $publicationData;

	/**
	 * @var \Rbs\Elasticsearch\Index\ProductData
	 */
	protected $productData;

	/**
	 * @param \Change\Events\Event $event
	 */
	public function onDefaultGetDocumentIndexData(\Change\Events\Event $event )
	{
		/** @var $product \Rbs\Catalog\Documents\Product */
		$product = $event->getParam('document');
		$productLocalization = $product->getCurrentLocalization();

		/** @var $index StoreIndex */
		$index = $event->getTarget();

		/** @var $indexManager \Rbs\Elasticsearch\Index\IndexManager */
		$indexManager = $event->getParam('indexManager');

		/* @var $commerceServices \Rbs\Commerce\CommerceServices */
		$commerceServices = $event->getServices('commerceServices');

		if ($commerceServices && $productLocalization->getPublicationStatus() == Publishable::STATUS_PUBLISHABLE)
		{
			$applicationServices = $event->getApplicationServices();
			if ($this->publicationData === null)
			{
				$publicationData = new \Rbs\Elasticsearch\Index\PublicationData();
				$publicationData->setDocumentManager($applicationServices->getDocumentManager());
				$publicationData->setTreeManager($applicationServices->getTreeManager());
			}
			else
			{
				$publicationData = $this->publicationData;
			}

			$canonicalSectionId = $publicationData->getCanonicalSectionId($product, $index->getWebsite());
			if (!$canonicalSectionId)
			{
				return;
			}

			$documentData = $event->getParam('documentData');
			if (!is_array($documentData))
			{
				$documentData = [];
			}

			$documentData = $publicationData->addPublishableMetas($product, $documentData);
			$documentData = $publicationData->addPublishableContent($product, $index->getWebsite(), $documentData,
				$index, $event->getParam('indexManager'));

			$creationDate = $productLocalization->getCreationDate();
			if ($creationDate)
			{
				$documentData['creationDate'] = $creationDate->format(\DateTime::ISO8601);
			}

			if ($this->productData === null)
			{
				$productData = new \Rbs\Elasticsearch\Index\ProductData();
				$productData->setDocumentManager($applicationServices->getDocumentManager());
				$productData->setCatalogManager($commerceServices->getCatalogManager());
				$productData->setPriceManager($commerceServices->getPriceManager());
				$productData->setStockManager($commerceServices->getStockManager());
				$this->productData = $productData;
			}
			else
			{
				$productData = $this->productData;
			}


			$documentData = $productData->addListItems($product, $documentData);
			$skuArray = $productData->getSkuArrayByProduct($product);
			if ($skuArray)
			{
				$documentData = $productData->addPrices($product, $skuArray, $documentData);
				$documentData = $productData->addStock($product, $skuArray, $documentData);
			}
			$event->setParam('documentData', $documentData);
		}
	}

	/**
	 * @param \Change\Events\Event $event
	 */
	public function onDefaultGetDocumentIndexFacetData(\Change\Events\Event $event)
	{
		$documentData = $event->getParam('documentData');
		if (!is_array($documentData))
		{
			return;
		}

		/* @var $commerceServices \Rbs\Commerce\CommerceServices */
		$commerceServices = $event->getServices('commerceServices');
		if ($commerceServices)
		{
			/** @var $product \Rbs\Catalog\Documents\Product */
			$product = $event->getParam('document');

			/** @var $index StoreIndex */
			$index = $event->getTarget();
			$facets = $index->getFacetsDefinition();
			foreach ($facets as $facet)
			{
				$documentData = $facet->addIndexData($product, $documentData);
			}
			$event->setParam('documentData', $documentData);
		}
	}

	/**
	 * @param \Rbs\Elasticsearch\Index\IndexManager $indexManager
	 * @return \Elastica\Index|null
	 */
	protected function getElasticaIndex(\Rbs\Elasticsearch\Index\IndexManager $indexManager)
	{
		$client = $indexManager->getElasticaClient($this->getClientName());
		if ($client)
		{
			return $client->getIndex($this->getName());
		}
		return null;
	}

	/**
	 * @param \Rbs\Elasticsearch\Index\IndexManager $indexManager
	 * @param integer $inventoryEntryId
	 * @return array
	 */
	protected function getIndexedProductByInventoryEntryId(\Rbs\Elasticsearch\Index\IndexManager $indexManager, $inventoryEntryId)
	{
		$products = [];

		$index = $this->getElasticaIndex($indexManager);
		if (!$index)
		{
			return $products;
		}

		$nested = new \Elastica\Query\Nested();
		$nested->setPath('stocks');
		$nestedBool = new \Elastica\Query\Bool();
		$nestedBool->addMust(new \Elastica\Query\Term(['inventoryEntryId' => $inventoryEntryId]));
		$nested->setQuery($nestedBool);

		$query = new \Elastica\Query($nested);
		$query->setFields([]);

		$results = $index->getType($this->getDefaultTypeName())->search($query)->getResults();

		$documentManager = $this->getDocumentManager();
		foreach ($results as $result)
		{
			/** @var $result \Elastica\Result */
			$productId = intval($result->getId());
			$product = $documentManager->getDocumentInstance($productId, 'Rbs_Catalog_Product');
			if ($product instanceof \Rbs\Catalog\Documents\Product)
			{
				$products[$product->getId()] = $product;
			}
		}
		return $products;
	}
	/**
	 * @param \Rbs\Elasticsearch\Index\IndexManager $indexManager
	 * @param integer $priceId
	 * @return array
	 */
	protected function getIndexedProductByPriceId(\Rbs\Elasticsearch\Index\IndexManager $indexManager, $priceId)
	{
		$products = [];

		$index = $this->getElasticaIndex($indexManager);
		if (!$index)
		{
			return $products;
		}


		$nested = new \Elastica\Query\Nested();
		$nested->setPath('prices');
		$nestedBool = new \Elastica\Query\Bool();
		$nestedBool->addMust(new \Elastica\Query\Term(['priceId' => $priceId]));
		$nested->setQuery($nestedBool);

		$query = new \Elastica\Query($nested);
		$query->setFields([]);
		$results = $index->getType($this->getDefaultTypeName())->search($query)->getResults();

		$documentManager = $this->getDocumentManager();
		foreach ($results as $result)
		{
			/** @var $result \Elastica\Result */
			$productId = intval($result->getId());
			$product = $documentManager->getDocumentInstance($productId, 'Rbs_Catalog_Product');
			if ($product instanceof \Rbs\Catalog\Documents\Product)
			{
				$products[$product->getId()] = $product;
			}
		}
		return $products;
	}

	/**
	 * @param \Rbs\Elasticsearch\Index\IndexManager $indexManager
	 * @param integer $itemId
	 * @return array|null
	 */
	protected function getIndexedListItemInfoId(\Rbs\Elasticsearch\Index\IndexManager $indexManager, $itemId)
	{
		$index = $this->getElasticaIndex($indexManager);
		if (!$index)
		{
			return null;
		}

		$nested = new \Elastica\Query\Nested();
		$nested->setPath('listItems');
		$nestedBool = new \Elastica\Query\Bool();
		$nestedBool->addMust(new \Elastica\Query\Term(['itemId' => $itemId]));
		$nested->setQuery($nestedBool);
		$query = new \Elastica\Query($nested);
		$query->setSize(1);
		$query->setParam('_source', ['listItems.listId', 'listItems.itemId', 'listItems.position']);

		$typeIndex = $index->getType($this->getDefaultTypeName());
		$search = $typeIndex->search($query);
		$results = $search->getResults();

		if (count($results))
		{
			/** @var $result \Elastica\Result */
			$result = $results[0];
			$productId = intval($result->getId());
			foreach ($result->getSource()['listItems'] as $listItem)
			{
				if ($listItem['itemId'] == $itemId)
				{
					$listId = $listItem['listId'];
					$position = $listItem['position'];
					$found = [$itemId, $listId, $productId, $position];
					return $found;
				}
			}
		}
		return null;
	}

	/**
	 * @param \Rbs\Elasticsearch\Index\IndexManager $indexManager
	 * @param \Rbs\Catalog\Documents\Product[]|\Change\Documents\DocumentCollection $products
	 */
	protected function indexProducts(\Rbs\Elasticsearch\Index\IndexManager $indexManager, $products)
	{
		foreach ($products as $product)
		{
			$indexManager->documentBulkIndex($product->getDocumentModel(), $product->getId(), null);
		}
	}
}
