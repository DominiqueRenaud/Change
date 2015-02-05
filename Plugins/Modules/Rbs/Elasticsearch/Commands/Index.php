<?php
/**
 * Copyright (C) 2014 Ready Business System
 *
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/.
 */
namespace Rbs\Elasticsearch\Commands;

use Change\Commands\Events\Event;

/**
 * @name \Rbs\Elasticsearch\Commands\Index
 */
class Index
{
	/**
	 * @param Event $event
	 */
	public function execute(Event $event)
	{
		$response = $event->getCommandResponse();

		$applicationServices = $event->getApplicationServices();
		$genericServices = $event->getServices('genericServices');
		if (!($genericServices instanceof \Rbs\Generic\GenericServices))
		{
			$response->addErrorMessage('Generic services not registered');
			return;
		}
		$indexManager = $genericServices->getIndexManager();

		$hasClient = false;
		$all = $event->getParam('all') == true;
		$publishable = $event->getParam('publishable') == true;
		$specificModelName = $event->getParam('model');
		if ($all)
		{
			$specificModelName = null;
			$publishable = false;
		}
		elseif ($publishable)
		{
			$specificModelName = null;
		}

		if (!is_string($specificModelName) || count(explode('_', $specificModelName)) != 3)
		{
			$specificModelName = null;
		}

		if (!$all && !$publishable && !$specificModelName)
		{
			$response->addCommentMessage('No model specified.');
			return;
		}

		foreach ($indexManager->getClientsName() as $clientName)
		{
			try
			{
				$client = $indexManager->getElasticaClient($clientName);
				if ($client)
				{
					$srvStat = $client->getStatus()->getServerStatus();
					if (isset($srvStat['status']) && $srvStat['status'] == 200)
					{
						$hasClient = true;
						break;
					}
				}
			}
			catch (\Exception $e)
			{
				$applicationServices->getLogging()->exception($e);
			}
		}

		if ($hasClient)
		{
			if ($event->getParam('useJob'))
			{
				$jobManager = $applicationServices->getJobManager();
			}
			else
			{
				$jobManager = null;
			}

			$filters = [
				'abstract' => false,
				'stateless' => false,
				'inline' => false,
				'onlyInstalled' => true
			];
			if ($publishable)
			{
				$filters['publishable'] = true;
			}

			$documentCount = 0;
			foreach ($applicationServices->getModelManager()->getFilteredModelsNames($filters) as $modelName)
			{
				if ($specificModelName && $modelName != $specificModelName)
				{
					continue;
				}

				$model = $applicationServices->getModelManager()->getModelByName($modelName);
				if ($jobManager)
				{
					$response->addInfoMessage('Schedule indexation of ' . $modelName . ' model...');
				}
				else
				{
					$response->addInfoMessage('Indexing ' . $modelName . ' model...');
				}

				$LCID = $applicationServices->getDocumentManager()->getLCID();
				$id = 0;
				while (true)
				{
					$toIndex = [];
					$applicationServices->getDocumentManager()->reset();
					$q = $applicationServices->getDocumentManager()->getNewQuery($model);
					$q->andPredicates($q->gt('id', $id));
					$q->addOrder('id');
					$docs = $q->getDocuments(0, 50);

					foreach ($docs as $doc)
					{
						$documentCount++;
						if ($doc instanceof \Change\Documents\Interfaces\Localizable)
						{
							foreach ($doc->getLCIDArray() as $LCID)
							{
								$toIndex[] = ['id' => $doc->getId(), 'model' => $model->getName(), 'LCID' => $LCID,
									'deleted' => false];
							}
						}
						elseif ($doc instanceof \Change\Documents\AbstractDocument)
						{
							$toIndex[] = ['id' => $doc->getId(), 'model' => $model->getName(), 'LCID' => $LCID,
								'deleted' => false];
						}
					}

					if (count($toIndex))
					{
						if ($jobManager)
						{
							$jobManager->createNewJob('Elasticsearch_Index', $toIndex);
						}
						else
						{
							$indexManager->documentsBulkIndex($toIndex);
						}
					}

					if ($docs->count() < 50)
					{
						break;
					}
					else
					{
						$id = max($docs->ids());
					}
				}
			}

			if ($jobManager)
			{
				$response->addInfoMessage('Indexation of ' . $documentCount . ' documents are scheduled.');
			}
			else
			{
				$response->addInfoMessage($documentCount . ' documents are indexed.');
			}
		}
		else
		{
			$response->addErrorMessage('No active client detected.');
		}
	}
}