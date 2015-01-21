<?php
/**
 * Copyright (C) 2014 Ready Business System, Eric Hauswald
 *
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/.
 */
namespace Rbs\Admin\Http\Actions;

use Change\Http\Event;
use Zend\Http\Response as HttpResponse;

/**
 * @name \Rbs\Admin\Http\Actions\GetHtmlBlockParameters
 */
class GetHtmlBlockParameters
{
	/**
	 * Use Required Event Params: vendor, shortModuleName, shortBlockName
	 * @param Event $event
	 * @throws \RuntimeException
	 */
	public function execute($event)
	{
		$result = new \Rbs\Admin\Http\Result\Renderer();
		$vendor = $event->getParam('vendor');
		$shortModuleName = $event->getParam('shortModuleName');
		$shortBlockName = $event->getParam('shortBlockName');

		$blockName = $vendor . '_' . $shortModuleName . '_' . $shortBlockName;
		$information = $event->getApplicationServices()->getBlockManager()->getBlockInformation($blockName);

		if ($information instanceof \Change\Presentation\Blocks\Information)
		{
			$plugin = $event->getApplicationServices()->getPluginManager()->getModule($vendor, $shortModuleName);
			if ($plugin && $plugin->isAvailable())
			{
				$fullyQualifiedTemplateName = $event->getRequest()->getQuery('fullyQualifiedTemplateName');
				$workspace = $event->getApplication()->getWorkspace();

				if ($fullyQualifiedTemplateName)
				{
					$templateInformation = $information->getTemplateInformation($fullyQualifiedTemplateName);
					if ($templateInformation)
					{
						$filePath = $workspace->pluginsModulesPath('Rbs', 'Admin', 'Assets',
							'block-template-parameters.twig');
						$result->setHttpStatusCode(HttpResponse::STATUS_CODE_200);

						$genericServices = $event->getServices('genericServices');
						if ($genericServices instanceof \Rbs\Generic\GenericServices)
						{
							$manager = $genericServices->getAdminManager();
						}
						else
						{
							throw new \RuntimeException('GenericServices not set', 999999);
						}

						$attributes = array('templateInformation' => $templateInformation, 'information' => $information);
						$renderer = function () use ($filePath, $manager, $attributes)
						{
							return $manager->renderTemplateFile($filePath, $attributes);
						};
						$result->setRenderer($renderer);
						$event->setResult($result);
						return;
					}
				}
				else
				{
					$filePath = $workspace->composePath($plugin->getAssetsPath(), 'Admin', 'Blocks', $shortBlockName . '.twig');
					if (is_readable($filePath))
					{
						$moduleName = $plugin->getName();
						$pathName = $workspace->composePath('Blocks', $shortBlockName . '.twig');
					}
					else
					{
						$moduleName = 'Rbs_Admin';
						$pathName = 'block-parameters.twig';
					}

					$result->setHttpStatusCode(HttpResponse::STATUS_CODE_200);

					$genericServices = $event->getServices('genericServices');
					if ($genericServices instanceof \Rbs\Generic\GenericServices)
					{
						$manager = $genericServices->getAdminManager();
					}
					else
					{
						throw new \RuntimeException('GenericServices not set', 999999);
					}

					$attributes = array('information' => $information);
					$renderer = function () use ($moduleName, $pathName, $manager, $attributes)
					{
						return $manager->renderModuleTemplateFile($moduleName, $pathName, $attributes);
					};
					$result->setRenderer($renderer);
					$event->setResult($result);
					return;
				}
			}
		}

		$result->setHttpStatusCode(HttpResponse::STATUS_CODE_404);
		$result->setRenderer(function ()
		{
			return null;
		});
		$event->setResult($result);
	}

	protected function renderParameters()
	{
	}
}