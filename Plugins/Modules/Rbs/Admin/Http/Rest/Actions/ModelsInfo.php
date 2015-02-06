<?php
/**
 * Copyright (C) 2014 Ready Business System
 *
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/.
 */
namespace Rbs\Admin\Http\Rest\Actions;

use Change\Http\Event;
use Zend\Http\Response as HttpResponse;

/**
 * Returns the list of all the functions declared in the blocks.
 * @name \Rbs\Admin\Http\Rest\Actions\PublishableModels
 */
class ModelsInfo
{
	/**
	 * @param Event $event
	 * @throws \RuntimeException
	 */
	public function execute(Event $event)
	{
		$request = $event->getRequest();
		$billingArea = null;
		if ($request->isGet())
		{
			$event->setResult($this->generateResult($event->getApplicationServices()));
		}
	}

	/**
	 * @param \Change\Services\ApplicationServices $applicationServices
	 * @return \Change\Http\Rest\V1\ArrayResult
	 */
	protected function generateResult($applicationServices)
	{
		$result = new \Change\Http\Rest\V1\ArrayResult();

		$models = [];

		$i18n = $applicationServices->getI18nManager();
		$modelManager = $applicationServices->getModelManager();
		foreach ($modelManager->getModelsNames() as $modelName)
		{
			$model = $modelManager->getModelByName($modelName);
			if (!$model)
			{
				continue;
			}

			$pluginKey = strtolower('m.' . $model->getVendorName() . '.' . $model->getShortModuleName() . '.admin.module_name');
			$pluginLabel = $i18n->trans($pluginKey, ['ucf']);
			if ($pluginKey == $pluginLabel)
			{
				continue;
			}

			$models[] = [
				'name' => $model->getName(),
				'label' => $i18n->trans($model->getLabelKey(), ['ucf']),
				'leaf' => !$model->hasDescendants(),
				'root' => !$model->hasParent(),
				'abstract' => $model->isAbstract(),
				'inline' => $model->isInline(),
				'publishable' => $model->isPublishable(),
				'localized' => $model->isLocalized(),
				'activable' => $model->isActivable(),
				'stateless' => $model->isStateless(),
				'useCorrection' => $model->useCorrection(),
				'editable' => $model->isEditable(),
				'plugin' => $pluginLabel,
				'descendants' => $model->getDescendantsNames(),
				'ancestors' => $model->getAncestorsNames(),
				'compatible' => array_merge([$model->getName()], $model->getAncestorsNames())
			];
		}

		$result->setArray($models);
		return $result;
	}
}