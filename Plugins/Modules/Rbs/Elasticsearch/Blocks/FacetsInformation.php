<?php
namespace Rbs\Elasticsearch\Blocks;

use Change\Presentation\Blocks\Information;
use Change\Presentation\Blocks\ParameterInformation;

/**
 * @name \Rbs\Elasticsearch\Blocks\FacetsInformation
 */
class FacetsInformation extends Information
{
	public function onInformation(\Change\Events\Event $event)
	{
		parent::onInformation($event);
		$i18nManager = $event->getApplicationServices()->getI18nManager();
		$ucf = array('ucf');

		$this->setLabel($i18nManager->trans('m.rbs.elasticsearch.blocks.facets', $ucf));

		$this->addInformationMeta('facetGroups', ParameterInformation::TYPE_DOCUMENTIDARRAY, true)
			->setLabel($i18nManager->trans('m.rbs.elasticsearch.blocks.facets-facetgroups', $ucf))
			->setAllowedModelsNames(array('Rbs_Elasticsearch_FacetGroup'));

		$this->addTTL(0)->setLabel($i18nManager->trans('m.rbs.admin.blocks.ttl', $ucf));
	}
}
