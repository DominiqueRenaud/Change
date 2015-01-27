<?php
/**
 * Copyright (C) 2014 Ready Business System
 *
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/.
 */
namespace Rbs\Catalog\Blocks;

/**
 * @name \Rbs\Catalog\Blocks\ProductAddedToCartInformation
 */
class ProductAddedToCartInformation extends \Change\Presentation\Blocks\Information
{
	/**
	 * @param \Change\Events\Event $event
	 */
	public function onInformation(\Change\Events\Event $event)
	{
		parent::onInformation($event);
		$i18nManager = $event->getApplicationServices()->getI18nManager();
		$ucf = array('ucf');
		$this->setSection($i18nManager->trans('m.rbs.catalog.admin.module_name', $ucf));
		$this->setLabel($i18nManager->trans('m.rbs.catalog.admin.product_added_to_cart_label', $ucf));
		$this->addParameterInformationForDetailBlock('Rbs_Catalog_Product', $i18nManager);

		$this->setDefaultTTL(60);
	}
} 