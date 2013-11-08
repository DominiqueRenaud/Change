<?php
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
		$this->setLabel($i18nManager->trans('m.rbs.catalog.blocks.product-list-label', $ucf));
		$this->addInformationMeta('productListId', Property::TYPE_DOCUMENTID, false, null)
			->setAllowedModelsNames('Rbs_Catalog_ProductList')
			->setLabel($i18nManager->trans('m.rbs.catalog.blocks.product-list-list', $ucf));
		$this->addInformationMeta('contextualUrls', Property::TYPE_BOOLEAN, false, true)
			->setLabel($i18nManager->trans('m.rbs.catalog.blocks.product-list-contextual-urls', $ucf));
		$this->addInformationMeta('itemsPerLine', Property::TYPE_INTEGER, true, 3)
			->setLabel($i18nManager->trans('m.rbs.catalog.blocks.product-list-items-per-line', $ucf));
		$this->addInformationMeta('itemsPerPage', Property::TYPE_INTEGER, true, 9)
			->setLabel($i18nManager->trans('m.rbs.catalog.blocks.product-list-items-per-page', $ucf));
	}
}