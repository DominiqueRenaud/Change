<?php
namespace Rbs\Commerce\Setup;

/**
 * @name \Rbs\Commerce\Setup\Install
 */
class Install
{
	/**
	 * @param \Change\Plugins\Plugin $plugin
	 */
	public function initialize($plugin)
	{
	}

	/**
	 * @param \Change\Plugins\Plugin $plugin
	 * @param \Change\Application $application
	 * @throws \RuntimeException
	 */
	public function executeApplication($plugin, $application)
	{
		/* @var $config \Change\Configuration\EditableConfiguration */
		$config = $application->getConfiguration();
		$config->addPersistentEntry('Change/Events/Http/Web/Rbs_Commerce', '\\Rbs\\Commerce\\Http\\Web\\ListenerAggregate');
		$config->addPersistentEntry('Change/Events/Http/Rest/Rbs_Commerce', '\\Rbs\\Commerce\\Http\\Rest\\ListenerAggregate');

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
	}

	/**
	 * @param \Change\Plugins\Plugin $plugin
	 */
	public function finalize($plugin)
	{
		$plugin->setConfigurationEntry('locked', true);
	}
}