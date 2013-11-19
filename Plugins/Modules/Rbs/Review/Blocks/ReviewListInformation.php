<?php
namespace Rbs\Review\Blocks;

use Change\Documents\Property;
use Change\Presentation\Blocks\Information;

/**
 * @name \Rbs\Review\Blocks\ReviewListInformation
 */
class ReviewListInformation extends Information
{
	public function onInformation(\Change\Events\Event $event)
	{
		parent::onInformation($event);
		$i18nManager = $event->getApplicationServices()->getI18nManager();
		$ucf = array('ucf');
		$this->setLabel($i18nManager->trans('m.rbs.review.front.review_list', $ucf));
		$this->addInformationMeta('targetId', Property::TYPE_DOCUMENTID, false, null)
			->setLabel($i18nManager->trans('m.rbs.review.front.review_target', $ucf));
		$this->addInformationMeta('reviewsPerPage', Property::TYPE_INTEGER, true, 10)
			->setLabel($i18nManager->trans('m.rbs.review.front.review_list_items_per_page', $ucf));
	}
}