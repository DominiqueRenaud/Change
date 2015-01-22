<?php
/**
 * Copyright (C) 2014 Ready Business System
 *
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/.
 */
namespace Rbs\Admin\Http\Rest\Actions;

use Change\Http\Rest\V1\ArrayResult;
use Zend\Http\Response as HttpResponse;

/**
 * @name \Rbs\Admin\Http\Rest\Actions\BlockList
 */
class BlockList
{
	/**
	 * @param \Change\Http\Event $event
	 */
	public function execute($event)
	{
		$result = new ArrayResult(HttpResponse::STATUS_CODE_200);
		$array = [];
		$isMailSuitable = $event->getRequest()->getQuery('isMailSuitable') === 'true' ? true : false;
		$blockManager = $event->getApplicationServices()->getBlockManager();
		$names = $blockManager->getBlockNames();
		foreach ($names as $name)
		{
			$information = $blockManager->getBlockInformation($name);
			if ($information)
			{
				// Filter blocks by keeping only those are mailSuitable or not depending on $isMailSuitable.
				if ($isMailSuitable != $information->isMailSuitable())
				{
					continue;
				}
				list($v, $m, $b) = explode('_', $name);
				$data = [
					'name' => $information->getName(),
					'label' => $information->getLabel(),
					'template' => 'Block/' . $v . '/' . $m . '/' . $b . '/parameters.twig'
				];

				// Default template information.
				$templateInformation = $information->getDefaultTemplateInformation();
				if ($templateInformation)
				{
					if (count($templateInformation->getParametersInformation()) > 0)
					{
						$data['defaultTemplate']['hasParameter'] = true;
					}
				}

				$array[$information->getSection()][] = $data;
			}
		}
		$result->setArray($array);
		$event->setResult($result);
	}
}