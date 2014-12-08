<?php
/**
 * Copyright (C) 2014 Ready Business System, Eric Hauswald
 *
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/.
 */
namespace Rbs\Catalog\Blocks;

use Change\Documents\Property;
use Change\Presentation\Blocks\Information;

/**
 * @name \Rbs\Catalog\Blocks\ProductListInformation
 */
class ProductListInformation extends Information
{
	public function onInformation(\Change\Events\Event $event)
	{
		parent::onInformation($event);
		$i18nManager = $event->getApplicationServices()->getI18nManager();
		$ucf = array('ucf');
		$this->setSection($i18nManager->trans('m.rbs.catalog.admin.module_name', $ucf));
		$this->setLabel($i18nManager->trans('m.rbs.catalog.admin.product_list_label', $ucf));
		$this->addInformationMetaForDetailBlock('Rbs_Catalog_ProductList', $i18nManager)
		->setLabel($i18nManager->trans('m.rbs.catalog.admin.product_list_list', $ucf));

		$this->addInformationMeta('useCurrentSectionProductList', Property::TYPE_BOOLEAN, false, false)
			->setLabel($i18nManager->trans('m.rbs.catalog.admin.product_list_use_current', $ucf));
		$this->addInformationMeta('contextualUrls', Property::TYPE_BOOLEAN, false, true)
			->setLabel($i18nManager->trans('m.rbs.catalog.admin.product_list_contextual_urls', $ucf));
		$this->addInformationMeta('itemsPerLine', Property::TYPE_INTEGER, true, 3)
			->setLabel($i18nManager->trans('m.rbs.catalog.admin.product_list_items_per_line', $ucf));
		$this->addInformationMeta('itemsPerPage', Property::TYPE_INTEGER, true, 9)
			->setLabel($i18nManager->trans('m.rbs.catalog.admin.product_list_items_per_page', $ucf));
		$this->addInformationMeta('showOrdering', Property::TYPE_BOOLEAN, false, true)
			->setLabel($i18nManager->trans('m.rbs.catalog.admin.product_list_show_ordering', $ucf));
		$this->addInformationMeta('showUnavailable', Property::TYPE_BOOLEAN, false, true)
			->setLabel($i18nManager->trans('m.rbs.catalog.admin.product_list_show_unavailable', $ucf));

		$templateInformation = $this->addTemplateInformation('Rbs_Catalog', 'product-list-infinite-scroll.twig');
		$templateInformation->setLabel($i18nManager->trans('m.rbs.catalog.admin.product_list_infinite_scroll_label', ['ucf']));
	}
}