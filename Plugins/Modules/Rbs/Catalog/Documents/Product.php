<?php
/**
 * Copyright (C) 2014 Ready Business System
 *
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/.
 */
namespace Rbs\Catalog\Documents;

use Change\Http\Rest\V1\Link;
use Change\Stdlib\String;

/**
 * @name \Rbs\Catalog\Documents\Product
 */
class Product extends \Compilation\Rbs\Catalog\Documents\Product
{
	/**
	 * @return \Rbs\Media\Documents\Image|null
	 */
	public function getFirstVisual()
	{
		$visuals = $this->getVisuals();
		return $visuals->count() ? $visuals->offsetGet(0) : null;
	}

	/**
	 * @param \Change\Documents\Events\Event $event
	 */
	public function onDefaultUpdateRestResult(\Change\Documents\Events\Event $event)
	{
		parent::onDefaultUpdateRestResult($event);
		$restResult = $event->getParam('restResult');

		/** @var $document Product */
		$document = $event->getDocument();
		if ($restResult instanceof \Change\Http\Rest\V1\Resources\DocumentResult)
		{

			$documentResult = $restResult;
			$um = $documentResult->getUrlManager();
			$selfLinks = $documentResult->getRelLink('self');
			$selfLink = array_shift($selfLinks);
			if ($selfLink instanceof Link)
			{
				$pathParts = explode('/', $selfLink->getPathInfo());
				array_pop($pathParts);
				$baseUrl = implode('/', $pathParts);
				$this->addLinkOnResult($documentResult, $um, $baseUrl);

				$image = $document->getFirstVisual();
				if ($image)
				{
					$documentResult->addLink(['href' => $image->getPublicURL(512, 512), 'rel' => 'adminthumbnail']);
				}

				if ($document->getVariantGroup())
				{
					/** @var \Rbs\Catalog\Documents\Attribute[] $axesAttributes */
					$axesAttributes = $document->getVariantGroup()->getAxesAttributes()->toArray();
					$variantInfo = [];
					$variantInfo['isRoot'] = $document->hasVariants();
					$variantInfo['depth'] = count($axesAttributes);
					if (!$variantInfo['isRoot'])
					{
						$variantInfo['isFinal'] = true;
						$variantInfo['level'] = $variantInfo['depth'];
						for ($i = 0; $i < count($axesAttributes); $i++)
						{
							$v = $axesAttributes[$i]->getValue($document);
							if ($v === null)
							{
								$variantInfo['isFinal'] = false;
								$variantInfo['level'] = $i;
								break;
							}
						}
					}
					$documentResult->setProperty('variantInfo', $variantInfo);
				}
			}
		}
		elseif ($restResult instanceof \Change\Http\Rest\V1\Resources\DocumentLink)
		{
			$image = $document->getFirstVisual();
			if ($image)
			{
				$restResult->setProperty('adminthumbnail', $image->getPublicURL(512, 512));
			}
			if ($document->getVariantGroup())
			{
				$restResult->setProperty('variantGroup', ['id' => $document->getVariantGroup()->getId(),
					'rootProductId' => $document->getVariantGroup()->getRootProductId()]);
			}
			$restResult->setProperty('variant', $document->getVariant());
		}
	}

	/**
	 * @param \Change\Http\Rest\V1\Resources\DocumentResult $documentResult
	 * @param \Change\Http\UrlManager $urlManager
	 * @param string $baseUrl
	 */
	protected function addLinkOnResult($documentResult, $urlManager, $baseUrl)
	{
		$documentResult->addLink(new Link($urlManager, $baseUrl . '/ProductListItems/', 'productListItems'));
		$documentResult->addLink(new Link($urlManager, $baseUrl . '/Prices/', 'prices'));
	}

	protected $ignoredPropertiesForRestEvents = ['model', 'declinationGroup', 'declination'];

	/**
	 * @param \Zend\EventManager\EventManagerInterface $eventManager
	 */
	protected function attachEvents($eventManager)
	{
		parent::attachEvents($eventManager);
		$eventManager->attach(\Change\Documents\Events\Event::EVENT_CREATED, [$this, 'onDefaultCreated'], 5);
		$eventManager->attach(\Change\Documents\Events\Event::EVENT_CREATE, [$this, 'onDefaultCreate'], 10);
		$eventManager->attach(\Change\Documents\Events\Event::EVENT_UPDATE, [$this, 'onDefaultUpdate'], 10);
		$eventManager->attach(\Change\Documents\Events\Event::EVENT_UPDATED, [$this, 'onDefaultUpdated'], 10);
	}

	/**
	 * @param \Change\Documents\Events\Event $event
	 */
	public function onDefaultCreate(\Change\Documents\Events\Event $event)
	{
		/** @var $product Product */
		$product = $event->getDocument();
		$cs = $event->getServices('commerceServices');

		if ($cs instanceof \Rbs\Commerce\CommerceServices)
		{
			$attributeValues = $product->getCurrentLocalization()->getAttributeValues();
			$normalizedAttributeValues =
				$cs->getAttributeManager()->normalizeRestAttributeValues($attributeValues, $product->getAttribute());
			$product->getCurrentLocalization()->setAttributeValues($normalizedAttributeValues);
		}

		if ($product->getNewSkuOnCreation())
		{
			/* @var $sku \Rbs\Stock\Documents\Sku */
			$sku = $product->getDocumentManager()->getNewDocumentInstanceByModelName('Rbs_Stock_Sku');
			$sku->setCode($product->buildSkuCodeFromLabel($cs));
			$sku->save();
			$product->setSku($sku);
		}
	}

	/**
	 * @param \Change\Documents\Events\Event $event
	 */
	public function onDefaultCreated(\Change\Documents\Events\Event $event)
	{
		$product = $event->getDocument();
		if ($product instanceof Product)
		{
			// Section product list synchronization.
			if ($product->getCategorizable() && $product->getPublicationSectionsCount())
			{
				$product->synchronizeSectionDocumentLists();
			}

			$cs = $event->getServices('commerceServices');
			if ($cs instanceof \Rbs\Commerce\CommerceServices)
			{
				$normalizedAttributeValues = $product->getCurrentLocalization()->getAttributeValues();
				$cs->getAttributeManager()->setAttributeValues($product, $normalizedAttributeValues);
			}
		}
	}

	/**
	 * @param \Rbs\Commerce\CommerceServices $commerceServices
	 * @return string
	 */
	protected function buildSkuCodeFromLabel(\Rbs\Commerce\CommerceServices $commerceServices = null)
	{
		$retry = 0;
		$baseCode = String::subString(preg_replace('/[^a-zA-Z0-9]+/', '-',
			String::stripAccents(String::toUpper($this->getLabel()))), 0, 80);
		$skuCode = $baseCode;
		if ($commerceServices)
		{
			$sku = $commerceServices->getStockManager()->getSkuByCode($skuCode);
			while ($sku && $retry++ < 100)
			{
				$skuCode = String::subString($baseCode, 0, 73) . '-' . String::toUpper(String::random(6, false));
				$sku = $commerceServices->getStockManager()->getSkuByCode($skuCode);
			}
		}
		return $skuCode;
	}

	/**
	 * @param \Change\Documents\Events\Event $event
	 */
	public function onDefaultUpdate(\Change\Documents\Events\Event $event)
	{
		/** @var $product Product */
		$product = $event->getDocument();
		$modifiedPropertyNames = $product->getModifiedPropertyNames();
		if (in_array('attributeValues', $modifiedPropertyNames) || in_array('attribute', $modifiedPropertyNames))
		{
			$cs = $event->getServices('commerceServices');
			if ($cs instanceof \Rbs\Commerce\CommerceServices)
			{
				$attributeValues = $product->getCurrentLocalization()->getAttributeValues();
				$normalizedAttributeValues =
					$cs->getAttributeManager()->normalizeRestAttributeValues($attributeValues, $product->getAttribute());
				$product->getCurrentLocalization()->setAttributeValues($normalizedAttributeValues);
				$cs->getAttributeManager()->setAttributeValues($product, $normalizedAttributeValues);
			}
		}

		// Section product list synchronization.
		if (in_array('publicationSections', $modifiedPropertyNames) || in_array('categorizable', $modifiedPropertyNames))
		{
			if ($product->getCategorizable())
			{
				$product->synchronizeSectionDocumentLists();
			}
		}

		if (in_array('variantGroup', $modifiedPropertyNames))
		{
			$variantGroup = $product->getVariantGroup();
			if ($variantGroup && $variantGroup->getRootProduct() === $this)
			{
				$product->setSku(null);
			}
		}
	}

	protected function synchronizeSectionDocumentLists()
	{
		$dm = $this->getDocumentManager();

		if ($this->getPublicationSectionsCount())
		{
			$dqb1 = $dm->getNewQuery('Rbs_Catalog_SectionProductList');
			$pb1 = $dqb1->getPredicateBuilder();
			$dqb1->andPredicates($pb1->in('synchronizedSection', $this->getPublicationSectionsIds()));
			$requiredListIds = $dqb1->getDocuments()->ids();
		}
		else
		{
			$requiredListIds = [];
		}

		$dqb2 = $dm->getNewQuery('Rbs_Catalog_SectionProductList');
		$d2qb2 = $dqb2->getModelBuilder('Rbs_Catalog_ProductListItem', 'productList');
		$pb2 = $d2qb2->getPredicateBuilder();
		$d2qb2->andPredicates($pb2->eq('product', $this));
		$existingListIds = $dqb2->getDocuments()->ids();

		// Item creation.
		$listIds = array_diff($requiredListIds, $existingListIds);
		foreach ($listIds as $listId)
		{
			/* @var $list \Rbs\Catalog\Documents\SectionProductList */
			$list = $dm->getDocumentInstance($listId);

			/* @var $item \Rbs\Catalog\Documents\ProductListItem */
			$item = $dm->getNewDocumentInstanceByModelName('Rbs_Catalog_ProductListItem');
			$item->setProductList($list);
			$item->setProduct($this);
			$item->save();
		}

		// Item deletion.
		$listIds = array_diff($existingListIds, $requiredListIds);
		if (count($listIds))
		{
			$dqb3 = $dm->getNewQuery('Rbs_Catalog_ProductListItem');
			$pb3 = $dqb3->getPredicateBuilder();
			$dqb3->andPredicates($pb3->in('productList', $listIds), $pb3->eq('product', $this));
			foreach ($dqb3->getDocuments() as $item)
			{
				$item->delete();
			}
		}
	}

	/**
	 * @var boolean
	 */
	protected $newSkuOnCreation = true;

	/**
	 * @return boolean
	 */
	public function getNewSkuOnCreation()
	{
		return $this->newSkuOnCreation;
	}

	/**
	 * @param boolean $newSkuOnCreation
	 * @return $this
	 */
	public function setNewSkuOnCreation($newSkuOnCreation)
	{
		$this->newSkuOnCreation = $newSkuOnCreation;
		return $this;
	}

	/**
	 * @param string $crossSellingType
	 * @return \Rbs\Catalog\Documents\CrossSellingProductList|null
	 */
	public function getCrossSellingListByType($crossSellingType)
	{
		$query = $this->getDocumentManager()->getNewQuery('Rbs_Catalog_CrossSellingProductList');
		$query->andPredicates($query->eq('product', $this->getId()), $query->eq('crossSellingType', $crossSellingType));
		return $query->getFirstDocument();
	}

	/**
	 * @return Boolean
	 */
	public function hasVariants()
	{
		return !($this->getVariant()) && $this->getVariantGroup();
	}

	/**
	 * @return null|Product
	 */
	protected function getRelatedRootProduct()
	{
		if ($this->getVariant() && $this->getVariantGroupId())
		{
			$variantGroup = $this->getVariantGroup();
			if ($variantGroup)
			{
				return $variantGroup->getRootProduct();
			}
		}
		return null;
	}

	/**
	 * @param \Change\Documents\Events\Event $event
	 */
	public function onDefaultUpdated(\Change\Documents\Events\Event $event)
	{
		if ($this !== $event->getDocument())
		{
			return;
		}

		$modifiedPropertyNames = $event->getParam('modifiedPropertyNames');
		if (is_array($modifiedPropertyNames) && in_array('publicationSections', $modifiedPropertyNames))
		{
			if ($this->getVariantGroupId() && !$this->getVariant())
			{
				$query = $this->getDocumentManager()->getNewQuery($this->getDocumentModel());
				$query->andPredicates($query->eq('variantGroup', $this->getVariantGroupId()),
					$query->neq('id', $this->getId()));

				/** @var $variantProduct Product */
				foreach ($query->getDocuments() as $variantProduct)
				{
					$variantEvent =
						new \Change\Documents\Events\Event(\Change\Documents\Events\Event::EVENT_UPDATED, $variantProduct,
							['modifiedPropertyNames' => ['publicationSections']]);
					$variantProduct->getEventManager()->trigger($variantEvent);
				}
			}
		}
	}
}