<?php
/**
 * Copyright (C) 2014 Eric Hauswald
 *
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/.
 */
namespace Rbs\Geo\Http\Admin\Actions;

use Zend\Http\Response as HttpResponse;

/**
* @name \Rbs\Geo\Http\Admin\Actions\AddressFiltersDefinition
*/
class AddressFiltersDefinition
{
	public function execute(\Change\Http\Event $event)
	{
		$genericServices = $event->getServices('genericServices');
		if ($genericServices instanceof \Rbs\Generic\GenericServices)
		{
			$definitions = $genericServices->getGeoManager()->getAddressFiltersDefinition($event->getRequest()->getQuery()->toArray());
			$i18nManager = $event->getApplicationServices()->getI18nManager();
			$groupLabel = $i18nManager->trans('m.rbs.admin.admin.group_filter', ['ucf']);
			$groupDefinition = ['name' => 'group', 'config' => ['listLabel' => $groupLabel, 'label' => $groupLabel],
				'directiveName' => 'rbs-document-filter-group'];

			array_unshift($definitions, $groupDefinition);

			usort($definitions, function($a , $b) {
				$grpA = isset($a['config']['group']) ? $a['config']['group'] : '';
				$grpB = isset($b['config']['group']) ? $b['config']['group'] : '';
				if ($grpA == $grpB)
				{
					$labA =  isset($a['config']['listLabel']) ? $a['config']['listLabel'] : '';
					$labB =  isset($b['config']['listLabel']) ? $b['config']['listLabel'] : '';
					if ($labA == $labB)
					{
						return 0;
					}
					return strcmp($labA, $labB);
				}
				return strcmp($grpA, $grpB);
			});

			$result = new \Rbs\Admin\Http\Result\Renderer();
			$result->setHttpStatusCode(HttpResponse::STATUS_CODE_200);

			$manager = $genericServices->getAdminManager();
			$attributes = array('definitions' => $definitions);
			$filePath = __DIR__ . '/Assets/fitersDefinition.twig';
			$renderer = function () use ($filePath, $manager, $attributes)
			{
				return $manager->renderTemplateFile($filePath, $attributes);
			};
			$result->setRenderer($renderer);
			$event->setResult($result);
		}
	}
} 