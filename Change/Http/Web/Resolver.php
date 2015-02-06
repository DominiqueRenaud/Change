<?php
/**
 * Copyright (C) 2014 Ready Business System
 *
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/.
 */
namespace Change\Http\Web;

use Change\Http\BaseResolver;
use Change\Http\Web\Actions\DisplayDocument;
use Change\Http\Web\Actions\ExecuteByName;
use Change\Http\Web\Actions\GetStorageItemContent;
use Change\Http\Web\Actions\GetThemeResource;
use Change\Http\Web\Actions\RedirectPathRule;
use Change\Presentation\Interfaces\Website;
use Zend\Http\Response as HttpResponse;

/**
 * @name \Change\Http\Web\Resolver
 */
class Resolver extends BaseResolver
{
	/**
	 * @param Event $event
	 * @return void
	 */
	public function resolve($event)
	{
		$website = $event->getWebsite();
		$pathRule = $this->findRule($event, $website);
		if ($pathRule)
		{
			$event->setParam('pathRule', $pathRule);
			$documentManager = $event->getApplicationServices()->getDocumentManager();
			$document = null;
			if ($pathRule->getDocumentId())
			{
				$eventManager = $documentManager->getEventManager();
				$args = $eventManager->prepareArgs(['documentId' => $pathRule->getDocumentId(), 'httpEvent' => $event]);
				$eventManager->trigger('getDisplayableDocument', $documentManager, $args);
				if (isset($args['displayableDocument']) && $args['displayableDocument'] instanceof \Change\Documents\AbstractDocument)
				{
					$document = $args['displayableDocument'];
					$event->setParam('document', $document);
				}
			}

			$urlManager = $event->getUrlManager();
			if ($pathRule->getHttpStatus() !== HttpResponse::STATUS_CODE_200 && $pathRule->getLocation() === null)
			{
				//Generic document URL
				if (!$document)
				{
					return;
				}
				$queryParameters = $event->getRequest()->getQuery()->toArray();
				$pathRule->setQueryParameters($queryParameters);
				$validPathRule = $urlManager->getValidDocumentRule($document, $pathRule);
				$absoluteUrl = $urlManager->absoluteUrl(true);
				if ($validPathRule instanceof PathRule)
				{
					$location = $urlManager->getByPathInfo($validPathRule->getRelativePath(), $queryParameters);
					$pathRule->setLocation($location->normalize()->toString());
					$urlManager->absoluteUrl($absoluteUrl);
				}
				elseif ($pathRule->getRuleId())
				{
					$location = $urlManager->getCanonicalByDocument($document, $queryParameters);
					$pathRule->setLocation($location->normalize()->toString());
					$urlManager->absoluteUrl($absoluteUrl);
				}
				else
				{
					$pathRule->setHttpStatus(HttpResponse::STATUS_CODE_200);
				}
			}

			if ($pathRule->getLocation())
			{
				$action = function ($event)
				{
					$action = new RedirectPathRule();
					$action->execute($event);
				};
				$event->setAction($action);
				return;
			}
			elseif ($pathRule->getHttpStatus() == HttpResponse::STATUS_CODE_200 && $document)
			{
				if (!$pathRule->getSectionId() && $document instanceof \Change\Documents\Interfaces\Publishable)
				{
					$section = $document->getCanonicalSection($website);
					if ($section  && !($section instanceof Website))
					{
						$pathRule->setSectionId($section->getId());
					}
				}

				$action = function ($event)
				{
					(new DisplayDocument())->execute($event);
				};
				$event->setAction($action);
				$authorizedSectionId = $pathRule->getSectionId() ? $pathRule->getSectionId() : $pathRule->getWebsiteId();
				$this->setPathRuleAuthorization($event, $authorizedSectionId, $pathRule->getWebsiteId());
				return;
			}
		}
		else
		{
			$websiteRelativePath = $website ? $website->getRelativePath() : null;
			$relativePath = $this->getRelativePath($event->getRequest()->getPath(),  $websiteRelativePath);
			$event->setParam('relativePath', $relativePath);
			if (preg_match('/^Theme\/([A-Z][A-Za-z0-9]+)\/([A-Z][A-Za-z0-9]+)\/(.+)$/', $relativePath, $matches))
			{
				$themeName = $matches[1] . '_' . $matches[2];
				$themeResourcePath = $matches[3];
				$themeManager = $event->getApplicationServices()->getThemeManager();
				$theme = $themeManager->getByName($themeName);
				if (!$theme)
				{
					$theme = $event->getApplicationServices()->getThemeManager()->getDefault();
				}
				$event->setParam('theme', $theme);
				$event->setParam('themeResourcePath', $themeResourcePath);
				$action = function ($event)
				{
					$action = new GetThemeResource();
					$action->execute($event);
				};
				$event->setAction($action);
				return;
			}

			if (preg_match('/^Action\/([A-Z][A-Za-z0-9]+)\/([A-Z][A-Za-z0-9]+)\/([A-Z][A-Za-z0-9\/]+)$/', $relativePath,
				$matches)
			)
			{
				$request = $event->getRequest();
				$event->setParam('action', array($matches[1], $matches[2], $matches[3]));
				$action = function ($event)
				{
					$action = new ExecuteByName();
					$action->execute($event);
				};
				$event->setAction($action);
				if (!$request->getPost('anonymous', $request->getQuery('anonymous')))
				{
					$event->setAuthorization(function ()
					{
						return true;
					});
				}
				return;
			}

			if (preg_match('/^Storage\/([A-Za-z0-9_]+)\/(.+)$/', $relativePath, $matches))
			{
				$storageName = $matches[1];
				$changeURI = $event->getApplicationServices()->getStorageManager()
					->buildChangeURI($storageName, '/' . $matches[2]);
				$event->setParam('changeURI', $changeURI);
				$action = function ($event)
				{
					$action = new GetStorageItemContent();
					$action->execute($event);
				};
				$event->setAction($action);
				return;
			}
		}
	}

	/**
	 * @param string $path
	 * @param string $websitePathPart
	 * @return boolean
	 */
	protected function isBasePath($path, $websitePathPart)
	{
		if ($websitePathPart)
		{
			if ($path)
			{
				if ($path[0] == '/')
				{
					$path = substr($path, 1);
				}
				if ($websitePathPart === $path || $websitePathPart . '/' === $path || strpos($path, $websitePathPart . '/') === 0)
				{
					return true;
				}
				else
				{
					return false;
				}
			}
		}
		return true;
	}

	/**
	 * @param string $path
	 * @param string $websitePathPart
	 * @return string
	 */
	protected function getRelativePath($path, $websitePathPart)
	{
		if ($websitePathPart)
		{
			$websitePathPart = '/' . $websitePathPart . '/';
			if (strpos($path, $websitePathPart) === 0)
			{
				return substr($path, strlen($websitePathPart));
			}
		}
		elseif ($path && $path[0] === '/')
		{
			$path = substr($path, 1);
		}
		return $path;
	}

	/**
	 * @param Event $event
	 * @param Website $website
	 * @return PathRule|null
	 */
	protected function findRule($event, $website)
	{
		if ($website instanceof Website)
		{
			$websiteRelativePath = $website->getRelativePath();
			$pathInfo = $event->getRequest()->getPath();
			if ($pathInfo === $website->getScriptName())
			{
				$pathInfo = '/';
			}
			$pathRule = new PathRule();
			$pathRule->setWebsiteId($website->getId())->setLCID($website->getLCID());

			if ($this->isBasePath($pathInfo, $websiteRelativePath))
			{
				$relativePath = $this->getRelativePath($pathInfo, $websiteRelativePath);
			}
			else
			{
				return null;
			}

			if (!$relativePath)
			{
				// Home.
				$pathRule->setRelativePath(null);
				$pathRule->setDocumentId($website->getId());
				if ($event->getRequest()->getQuery()->count() == 0 && $event->getRequest()->isGet())
				{
					$pathRule->setHttpStatus(HttpResponse::STATUS_CODE_200);
				}
				else
				{
					$pathRule->setHttpStatus(HttpResponse::STATUS_CODE_303);
				}
				return $pathRule;
			}
			else
			{
				$pathRule->setRelativePath($relativePath);
				if ($this->findDbRule($event->getApplicationServices()->getDbProvider(), $pathRule))
				{
					return $pathRule;
				}
				if ($this->findDefaultRule($pathRule))
				{
					return $pathRule;
				}
			}
		}
		return null;
	}

	/**
	 * @param \Change\Db\DbProvider $dbProvider
	 * @param PathRule $pathRule
	 * @return boolean
	 */
	protected function findDbRule($dbProvider, $pathRule)
	{
		$qb = $dbProvider->getNewQueryBuilder();
		$fb = $qb->getFragmentBuilder();

		$qb->select($fb->alias($fb->column('rule_id'), 'ruleId'),
			$fb->alias($fb->column('document_id'), 'documentId'),
			$fb->alias($fb->column('section_id'), 'sectionId'),
			$fb->alias($fb->column('relative_path'), 'relativePath'),
			$fb->alias($fb->column('http_status'), 'httpStatus'),
			$fb->alias($fb->column('query'), 'query'),
			$fb->alias($fb->column('user_edited'), 'userEdited'));

		$qb->from($qb->getSqlMapping()->getPathRuleTable());

		$qb->where($fb->logicAnd(
			$fb->eq($fb->column('website_id'), $fb->integerParameter('websiteId')),
			$fb->eq($fb->column('lcid'), $fb->parameter('LCID')),
			$fb->eq($fb->column('hash'), $fb->parameter('hash'))
		));

		$sq = $qb->query();
		$sq->bindParameter('websiteId', $pathRule->getWebsiteId());
		$sq->bindParameter('LCID', $pathRule->getLCID());
		$sq->bindParameter('hash', $pathRule->getHash());

		$row = $sq->getFirstResult($sq->getRowsConverter()
			->addIntCol('ruleId', 'documentId', 'sectionId', 'httpStatus')
			->addTxtCol('relativePath', 'query')
			->addBoolCol('userEdited'));

		if ($row)
		{
			$pathRule->setRuleId($row['ruleId']);
			$pathRule->setRelativePath($row['relativePath']);
			$pathRule->setHttpStatus($row['httpStatus']);
			$pathRule->setQuery($row['query']);
			$pathRule->setUserEdited($row['userEdited']);

			if ($row['documentId'])
			{
				$pathRule->setDocumentId(intval($row['documentId']));
			}

			if ($row['sectionId'])
			{
				$pathRule->setSectionId(intval($row['sectionId']));
			}
			return true;
		}
		return null;
	}

	/**
	 * @param PathRule $pathRule
	 * @return boolean
	 */
	protected function findDefaultRule($pathRule)
	{
		if (preg_match('/^document(?:\/(\d{4,10}))?\/(\d{4,10})(\.html|\/)$/', $pathRule->getRelativePath(), $matches))
		{
			$pathRule->setDocumentId(intval($matches[2]));
			if ($matches[1] !== '')
			{
				$pathRule->setSectionId(intval($matches[1]));
			}
			$pathRule->setHttpStatus(HttpResponse::STATUS_CODE_303);
			return true;
		}
		return false;
	}

	/**
	 * @param Event $event
	 * @param integer $sectionId
	 * @param integer $websiteId
	 */
	protected function setPathRuleAuthorization($event, $sectionId, $websiteId)
	{
		if ($sectionId)
		{
			$authorisation = function (Event $event) use ($sectionId, $websiteId)
			{
				return $event->getPermissionsManager()->isWebAllowed($sectionId, $websiteId);
			};
			$event->setAuthorization($authorisation);
		}
	}
}