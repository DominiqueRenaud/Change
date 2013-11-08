<?php
namespace Rbs\Event\Blocks\Base;

use Change\Documents\Property;

/**
 * @name \Rbs\Event\Blocks\Base\BaseEventInformation
 */
abstract class BaseEventInformation extends \Change\Presentation\Blocks\Information
{
	public function onInformation(\Change\Events\Event $event)
	{
		parent::onInformation($event);
		$i18nManager = $event->getApplicationServices()->getI18nManager();
		$ucf = array('ucf');
		$this->addInformationMeta('docId', Property::TYPE_DOCUMENTID, false, null); // Label ans allowed model should be set in final class.
		$this->addInformationMeta('showTime', Property::TYPE_BOOLEAN, false, true)
			->setLabel($i18nManager->trans('m.rbs.event.blocks.base-event-show-time', $ucf));
		$this->addInformationMeta('showCategories', Property::TYPE_BOOLEAN, false, true)
			->setLabel($i18nManager->trans('m.rbs.event.blocks.base-event-show-categories', $ucf));
		$this->addInformationMeta('contextualUrls', Property::TYPE_BOOLEAN, false, true)
			->setLabel($i18nManager->trans('m.rbs.event.blocks.base-event-contextual-urls', $ucf));
		$this->addInformationMeta('templateName', Property::TYPE_STRING, false) // Default value should be set in final class.
			->setLabel($i18nManager->trans('m.rbs.event.blocks.base-event-template-name', $ucf));
	}
}
