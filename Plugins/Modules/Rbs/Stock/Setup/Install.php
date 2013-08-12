<?php
namespace Rbs\Stock\Setup;

/**
 * @name \Rbs\Stock\Setup\Install
 */
class Install
{
	/**
	 * @param \Change\Plugins\Plugin $plugin
	 */
	//	public function initialize($plugin)
	//	{
	//	}

	/**
	 * @param \Change\Plugins\Plugin $plugin
	 * @param \Change\Application $application
	 * @throws \RuntimeException
	 */
	public function executeApplication($plugin, $application)
	{
		/* @var $config \Change\Configuration\EditableConfiguration */
		$config = $application->getConfiguration();
		$config->addPersistentEntry('Change/Events/Rbs/Admin/Rbs_Stock', '\\Rbs\\Stock\\Admin\\Register');
	}

	/**
	 * @param \Change\Plugins\Plugin $plugin
	 * @param \Change\Application\ApplicationServices $applicationServices
	 * @param \Change\Documents\DocumentServices $documentServices
	 * @param \Change\Presentation\PresentationServices $presentationServices
	 * @throws \Exception
	 */
	public function executeServices($plugin, $applicationServices, $documentServices, $presentationServices)
	{
		$schema = new Schema($applicationServices->getDbProvider()->getSchemaManager());
		$schema->generate();
		$tm = $applicationServices->getTransactionManager();
		try
		{
			$tm->begin();
			/* @var $item \Rbs\Collection\Documents\Item */
			$item = $documentServices->getDocumentManager()->getNewDocumentInstanceByModelName('Rbs_Collection_Item');
			$item->setValue('PC');
			$item->setLabel('pc.');
			$item->setTitle($applicationServices->getI18nManager()->trans('m.rbs.stock.document.sku.unit-piece', array('ucf')));
			$item->setLocked(true);
			$item->save();

			/* @var $collection \Rbs\Collection\Documents\Collection */
			$collection = $documentServices->getDocumentManager()->getNewDocumentInstanceByModelName('Rbs_Collection_Collection');
			$collection->setLabel('SKU Units');
			$collection->setCode('Rbs_Stock_Collection_Unit');
			$collection->setLocked(true);
			$collection->getItems()->add($item);
			$collection->save();
			$tm->commit();
		}
		catch (\Exception $e)
		{
			throw $tm->rollBack($e);
		}

	}

	/**
	 * @param \Change\Plugins\Plugin $plugin
	 */
	public function finalize($plugin)
	{
		$plugin->setConfigurationEntry('locked', true);
	}
}