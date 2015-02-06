<?php
/**
 * Copyright (C) 2014 Proximis
 *
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/.
 */
namespace Rbs\Storelocator;

/**
* @name \Rbs\Storelocator\StoreManager
*/
class StoreManager implements \Zend\EventManager\EventsCapableInterface
{
	use \Change\Events\EventsCapableTrait;

	const EVENT_MANAGER_IDENTIFIER = 'StoreManager';

	/**
	 * @var \Change\Documents\DocumentManager
	 */
	protected $documentManager;


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
		return $this->getApplication()->getConfiguredListenerClassNames('Rbs/Storelocator/Events/StoreManager');
	}

	protected function attachEvents(\Change\Events\EventManager $eventManager)
	{
		$eventManager->attach('getStoreByCode', [$this, 'onDefaultGetStoreByCode'], 5);
		$eventManager->attach('getStoreData', [$this, 'onDefaultGetStoreData'], 5);
		
		$eventManager->attach('getStoresData', [$this, 'onDefaultGetElasticaStoresData'], 10);
		$eventManager->attach('getStoresData', [$this, 'onDefaultGetStoresData'], 10);
		$eventManager->attach('getStoresData', [$this, 'onDefaultGetStoresArrayData'], 5);

		$eventManager->attach('getFacetsData', [$this, 'onDefaultGetFacetsData'], 5);
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
	 * @api
	 * @param string $storeCode
	 * @return \Rbs\Storelocator\Documents\Store|null
	 */
	public function getStoreByCode($storeCode)
	{
		$em = $this->getEventManager();
		$eventArgs = $em->prepareArgs(['storeCode' => $storeCode]);
		$em->trigger('getStoreByCode', $this, $eventArgs);
		$store = (isset($eventArgs['store'])) ? $eventArgs['store'] : null;
		return ($store instanceof \Rbs\Storelocator\Documents\Store) ? $store : null;
	}

	/**
	 * Input params: storeCode
	 * Output param: store
	 * @api
	 * @param \Change\Events\Event $event
	 */
	public function  onDefaultGetStoreByCode(\Change\Events\Event $event)
	{
		if (!$event->getParam('store'))
		{
			$storeCode = $event->getParam('storeCode');
			$query = $event->getApplicationServices()->getDocumentManager()->getNewQuery('Rbs_Storelocator_Store');
			$store = $query->andPredicates($query->published(), $query->eq('code', $storeCode))->getFirstDocument();
			if ($store)
			{
				$event->setParam('store', $store);
			}
		}
	}


	/**
	 * Default context:
	 *  - *dataSetNames, *visualFormats, *URLFormats
	 *  - website, websiteUrlManager, section, page, detailed
	 *  - data
	 * @api
	 * @param \Rbs\Storelocator\Documents\Store|integer $store
	 * @param array $context
	 * @return array
	 */
	public function getStoreData($store, array $context)
	{
		if (is_numeric($store))
		{
			$store = $this->getDocumentManager()->getDocumentInstance($store, 'Rbs_Storelocator_Store');
		}

		if ($store instanceof \Rbs\Storelocator\Documents\Store)
		{
			$em = $this->getEventManager();
			$eventArgs = $em->prepareArgs(['store' => $store, 'context' => $context]);
			$em->trigger('getStoreData', $this, $eventArgs);
			if (isset($eventArgs['storeData']))
			{
				$storeData = $eventArgs['storeData'];
				if (is_object($storeData))
				{
					$callable = [$storeData, 'toArray'];
					if (is_callable($callable))
					{
						$storeData = call_user_func($callable);
					}
				}
				if (is_array($storeData))
				{
					return $storeData;
				}
			}
		}
		return [];
	}

	/**
	 * Input params: store, context
	 * Output param: storeData
	 * @param \Change\Events\Event $event
	 */
	public function  onDefaultGetStoreData(\Change\Events\Event $event)
	{
		if (!$event->getParam('storeData'))
		{
			$storeDataComposer = new \Rbs\Storelocator\StoreDataComposer($event);
			$event->setParam('storeData', $storeDataComposer->toArray());
		}
	}


	/**
	 * Context:
	 *  - *dataSetNames, *visualFormats, *URLFormats, pagination
	 *  - website, websiteUrlManager, section, page, detailed
	 *  - *data
	 *     - coordinates
	 *     - distance
	 *     - commercialSign
	 *     - facetFilters
	 * @api
	 * @param array $context
	 * @return array
	 */
	public function getStoresData(array $context)
	{
		$em = $this->getEventManager();
		$eventArgs = $em->prepareArgs(['context' => $context]);
		$em->trigger('getStoresData', $this, $eventArgs);

		$storesData = [];
		$pagination = ['offset' => 0, 'limit' => 100, 'count' => 0];
		if (isset($eventArgs['storesData']) && is_array($eventArgs['storesData']))
		{
			if (isset($eventArgs['pagination']) && is_array($eventArgs['pagination']))
			{
				$pagination = $eventArgs['pagination'];
			}

			foreach ($eventArgs['storesData'] as $storeData)
			{
				if (is_object($storeData))
				{
					$callable = [$storeData, 'toArray'];
					if (is_callable($callable))
					{
						$storeData = call_user_func($callable);
					}
				}

				if (is_array($storeData) && count($storeData))
				{
					$storesData[] = $storeData;
				}
			}
		}
		return ['pagination' => $pagination, 'items' => $storesData];
	}

	/**
	 * Input params: context
	 *  - data :
	 *    - commercialSign
	 * Output param: storesData, pagination
	 * @param \Change\Events\Event $event
	 */
	public function onDefaultGetStoresData(\Change\Events\Event $event)
	{
		/** @var $context array */
		$context = $event->getParam('context');
		if (!is_array($context) || $event->getParam('stores') || $event->getParam('storesData') )
		{
			return;
		}
		$contextData = $context['data'];

		$pagination = isset($context['pagination']) && is_array($context['pagination']) ? $context['pagination'] : [];
		$offset = isset($pagination['offset']) ? intval($pagination['offset']) : 0;
		$limit = isset($pagination['limit']) ? intval($pagination['limit']) : 100;

		$applicationServices = $event->getApplicationServices();
		$commercialSignId =  isset($contextData['commercialSign']) ? intval($contextData['commercialSign']) : 0;

		$query = $applicationServices->getDocumentManager()->getNewQuery('Rbs_Storelocator_Store');
		if ($commercialSignId)
		{
			$query->andPredicates($query->published(), $query->eq('commercialSign', $commercialSignId));
		}
		else
		{
			$query->andPredicates($query->published());
		}

		$count = $query->getCountDocuments();
		if ($count && $offset < $count)
		{
			$stores = $query->getDocuments($offset, $limit);
			$event->setParam('stores', $stores->toArray());
			$event->setParam('pagination', ['offset' => $offset, 'limit' => $limit, 'count' => $count]);
		}
	}

	/**
	 * Input params: coordinates, distance
	 * Output param: storesData, pagination
	 * @param \Change\Events\Event $event
	 */
	public function onDefaultGetStoresArrayData(\Change\Events\Event $event)
	{
		$stores = $event->getParam('stores');
		$context = $event->getParam('context');
		$storesData = $event->getParam('storesData');
		if ($storesData === null && is_array($context) && is_array($stores) && count($stores))
		{
			$storesData = [];
			foreach ($stores as $store)
			{
				$storeData = $this->getStoreData($store, $context);
				if (is_array($storeData) && count($storeData))
				{
					$storesData[] = $storeData;
				}
			}
			$event->setParam('storesData', $storesData);
		}
	}


	/**
	 * Input params: coordinates, distance
	 * Output param: storesData, pagination
	 * @param \Change\Events\Event $event
	 */
	public function onDefaultGetElasticaStoresData(\Change\Events\Event $event)
	{
		/** @var $storesDataContext array */
		$storesDataContext = $event->getParam('context');
		if (!is_array($storesDataContext))
		{
			return;
		}

		$applicationServices = $event->getApplicationServices();
		$logging = $event->getApplication()->getLogging();
		$documentManager = $applicationServices->getDocumentManager();

		/** @var \Rbs\Generic\GenericServices $genericServices */
		$genericServices = $event->getServices('genericServices');
		if (!($genericServices instanceof \Rbs\Generic\GenericServices))
		{
			return;
		}
		$indexManager = $genericServices->getIndexManager();

		$website = isset($storesDataContext['website']) ? $storesDataContext['website'] : null;

		/** @var \Rbs\Storelocator\Documents\StoreLocatorIndex $storeLocatorIndex */
		$storeLocatorIndex = null;
		if ($website instanceof \Rbs\Website\Documents\Website)
		{
			$storeLocatorIndex = $indexManager->getIndexByCategory('storeLocator', $documentManager->getLCID(), ['website' => $website]);
		}
		if (!($storeLocatorIndex instanceof \Rbs\Storelocator\Documents\StoreLocatorIndex))
		{
			return;
		}

		$client = $indexManager->getElasticaClient($storeLocatorIndex->getClientName());
		if (!$client)
		{
			$logging->warn(__METHOD__ . ': invalid client ' . $storeLocatorIndex->getClientName());
			return;
		}

		$index = $client->getIndex($storeLocatorIndex->getName());
		if (!$index->exists())
		{
			$logging->warn(__METHOD__ . ': index not exist ' . $storeLocatorIndex->getName());
			return;
		}

		$data = $storesDataContext['data'];
		$coordinates = (isset($data['coordinates']) && $data['coordinates']) ? $data['coordinates'] : null;
		$distance = (isset($data['distance']) && $data['distance']) ? $data['distance'] : null;
		$commercialSignId =  isset($data['commercialSign']) ? intval($data['commercialSign']) : 0;


		$pagination = (isset($storesDataContext['pagination']) && is_array($storesDataContext['pagination'])) ? $storesDataContext['pagination'] : [];
		$pagination += ['offset' => 0, 'limit' => 10];
		$query = $this->getElasticaQuery($coordinates, $distance, $commercialSignId);

		$facetFilters = isset($data['facetFilters']) && is_array($data['facetFilters']) ? $data['facetFilters'] : [];
		if ($facetFilters)
		{
			$queryHelper = new \Rbs\Elasticsearch\Index\QueryHelper($storeLocatorIndex, $indexManager, $genericServices->getFacetManager());
			$facets = $storeLocatorIndex->getFacetsDefinition();
			$filter = $queryHelper->getFacetsFilter($facets, $facetFilters, $data);
			if ($filter)
			{
				$query->setFilter($filter);
			}
		}

		$query->setFrom($pagination['offset'])->setSize($pagination['limit']);

		if ($event->getApplication()->inDevelopmentMode()) {
			$logging->info(json_encode($query->toArray()));
		}

		$searchResult = $index->getType($storeLocatorIndex->getDefaultTypeName())->search($query);
		$pagination['count'] = $totalCount = $searchResult->getTotalHits();


		$storesData = [];
		if ($totalCount)
		{
			$results = $searchResult->getResults();
			if (!count($results)) {
				$pagination['offset'] = 0;
				$query->setFrom($pagination['offset']);
				$searchResult = $index->getType($storeLocatorIndex->getDefaultTypeName())->search($query);
				$results = $searchResult->getResults();
			}

			/* @var $result \Elastica\Result */
			foreach ($results as $result)
			{
				$store = $documentManager->getDocumentInstance($result->getId());
				if ($store instanceof \Rbs\Storelocator\Documents\Store && $store->published())
				{
					$storeData = $this->getStoreData($store, $storesDataContext);
					$sort = $result->getParam('sort');
					if (count($sort))
					{
						$storeData['coordinates']['distance'] = $sort[0];
						$storeData['coordinates']['distanceUnite'] = 'km';
					}
					$storesData[] = $storeData;
				}
			}
		}

		$event->setParam('storesData', $storesData);
		$event->setParam('pagination', $pagination);
	}

	/**
	 * @param array $coordinates
	 * @param string $distance
	 * @param integer $commercialSignId
	 * @return \Elastica\Query
	 */
	public function getElasticaQuery($coordinates, $distance, $commercialSignId = 0)
	{
		$now = (new \DateTime())->format(\DateTime::ISO8601);
		$multiMatch = new \Elastica\Query\MatchAll();
		$bool = new \Elastica\Filter\Bool();
		$bool->addMust(new \Elastica\Filter\Range('startPublication', array('lte' => $now)));
		$bool->addMust(new \Elastica\Filter\Range('endPublication', array('gt' => $now)));
		if ($coordinates)
		{
			if (!$distance) {$distance = '50km';}
			$location = ['lat' => $coordinates['latitude'], 'lon' =>  $coordinates['longitude']];
			$geoDistance = new \Elastica\Filter\GeoDistance('coordinates', $location, $distance);
			$bool->addMust($geoDistance);
		}

		if ($commercialSignId) {
			$nested = new \Elastica\Filter\Nested();
			$nested->setPath('commercialSigns');
			$nestedBool = new \Elastica\Query\Bool();
			$nestedBool->addMust(new \Elastica\Query\Term(['commercialSignId' => $commercialSignId]));
			$nested->setQuery($nestedBool);
			$bool->addMust($nested);
		}

		$filtered = new \Elastica\Query\Filtered($multiMatch, $bool);
		$query = new \Elastica\Query($filtered);
		if ($coordinates)
		{
			$distance = ['_geo_distance' => [
				'coordinates' => ['lat' => $coordinates['latitude'], 'lon' =>  $coordinates['longitude']],
				'order' => 'asc',
				'unit' => 'km'
				]
			];
			$query->addSort($distance);
		}

		return $query;
	}

	/**
	 * Context:
	 *  - pagination
	 *  - website
	 *  - *data
	 *     - coordinates
	 *     - distance
	 *     - commercialSign
	 *     - facets
	 *     - facetFilters
	 * @api
	 * @param array $context
	 * @return array
	 */
	public function getFacetsData(array $context)
	{
		$em = $this->getEventManager();
		$eventArgs = $em->prepareArgs(['context' => $context]);
		$em->trigger('getFacetsData', $this, $eventArgs);

		$facetsData = [];
		$pagination = ['offset' => 0, 'limit' => 100, 'count' => 0];
		if (isset($eventArgs['facetsData']) && is_array($eventArgs['facetsData']))
		{
			$facetsData = $eventArgs['facetsData'];
			if (isset($eventArgs['pagination']) && is_array($eventArgs['pagination']))
			{
				$pagination = $eventArgs['pagination'];
			}
		}
		return ['pagination' => $pagination, 'items' => $facetsData];
	}

	/**
	 * Input params: coordinates, distance
	 * Output param: storesData, pagination
	 * @param \Change\Events\Event $event
	 */
	public function onDefaultGetFacetsData(\Change\Events\Event $event)
	{
		/** @var $storesDataContext array */
		$storesDataContext = $event->getParam('context');
		if (!is_array($storesDataContext))
		{
			return;
		}
		$applicationServices = $event->getApplicationServices();
		$logging = $event->getApplication()->getLogging();
		$documentManager = $applicationServices->getDocumentManager();

		$data = $storesDataContext['data'];
		$website = isset($storesDataContext['website']) ? $storesDataContext['website'] : null;
		$facets = [];
		if (isset($data['facets']) && is_array($data['facets'])) {
			foreach ($data['facets'] as $facet)
			{
				if (is_numeric($facet))
				{
					$facet = $documentManager->getDocumentInstance($facet);
					if ($facet instanceof \Rbs\Elasticsearch\Documents\Facet)
					{
						$facet = $facet->getFacetDefinition();
					}
				}

				if ($facet instanceof \Rbs\Elasticsearch\Facet\FacetDefinitionInterface)
				{
					$facets[] = $facet;
				}
			}
		}

		if (!$facets)
		{
			return;
		}

		$facetFilters = [];
		if (isset($data['facetFilters']) && is_array($data['facetFilters']))
		{
			$facetFilters = $data['facetFilters'];
		}

		/** @var \Rbs\Generic\GenericServices $genericServices */
		$genericServices = $event->getServices('genericServices');
		if (!($genericServices instanceof \Rbs\Generic\GenericServices))
		{
			return;
		}
		$indexManager = $genericServices->getIndexManager();

		/** @var \Rbs\Storelocator\Documents\StoreLocatorIndex $storeLocatorIndex */
		$storeLocatorIndex = null;
		if ($website instanceof \Rbs\Website\Documents\Website)
		{
			$storeLocatorIndex = $indexManager->getIndexByCategory('storeLocator', $documentManager->getLCID(), ['website' => $website]);
		}
		if (!($storeLocatorIndex instanceof \Rbs\Storelocator\Documents\StoreLocatorIndex))
		{
			return;
		}

		$client = $indexManager->getElasticaClient($storeLocatorIndex->getClientName());
		if (!$client)
		{
			$logging->warn(__METHOD__ . ': invalid client ' . $storeLocatorIndex->getClientName());
			return;
		}

		$index = $client->getIndex($storeLocatorIndex->getName());
		if (!$index->exists())
		{
			$logging->warn(__METHOD__ . ': index not exist ' . $storeLocatorIndex->getName());
			return;
		}

		$coordinates = (isset($data['coordinates']) && $data['coordinates']) ? $data['coordinates'] : null;
		$distance = (isset($data['distance']) && $data['distance']) ? $data['distance'] : null;
		$commercialSignId =  isset($data['commercialSign']) ? intval($data['commercialSign']) : 0;

		$pagination = (isset($storesDataContext['pagination']) && is_array($storesDataContext['pagination'])) ? $storesDataContext['pagination'] : [];
		$pagination += ['offset' => 0, 'limit' => 100];
		$query = $this->getElasticaQuery($coordinates, $distance, $commercialSignId);
		$query->setSize(0);

		$queryHelper = new \Rbs\Elasticsearch\Index\QueryHelper($storeLocatorIndex, $indexManager, $genericServices->getFacetManager());
		$queryHelper->addFilteredFacets($query, $facets, $facetFilters, $data);

		if ($event->getApplication()->inDevelopmentMode())
		{
			$logging->info(json_encode($query->toArray()));
		}

		$result = $index->getType($storeLocatorIndex->getDefaultTypeName())->search($query);
		$aggregations = $result->getAggregations();

		$facetsValues = $queryHelper->formatAggregations($aggregations, $facets);

		$facetsData = [];
		foreach ($facetsValues as $facetValues)
		{
			$facetsData[] = $facetValues->toArray();
		}
		$pagination['count'] = count($facetsData);

		$event->setParam('facetsData', $facetsData);
		$event->setParam('pagination', $pagination);
	}
} 