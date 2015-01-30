<?php
/**
 * Copyright (C) 2014 Ready Business System
 *
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/.
 */
namespace Rbs\Workflow\Setup;

/**
 * @name \Rbs\Workflow\Setup\Install
 */
class Install extends \Change\Plugins\InstallBase
{
	/**
	 * @param \Change\Plugins\Plugin $plugin
	 * @param \Change\Application $application
	 * @param \Change\Configuration\EditableConfiguration $configuration
	 * @throws \RuntimeException
	 */
	public function executeApplication($plugin, $application, $configuration)
	{
		$configuration->addPersistentEntry('Change/Events/Workflow/publicationProcess/Rbs_Workflow',
			'\\Rbs\\Workflow\\Tasks\\PublicationProcess\\Listeners');

		$configuration->addPersistentEntry('Change/Events/Workflow/correctionPublicationProcess/Rbs_Workflow',
			'\\Rbs\\Workflow\\Tasks\\CorrectionPublicationProcess\\Listeners');
	}

	/**
	 * @param \Change\Plugins\Plugin $plugin
	 * @param \Change\Services\ApplicationServices $applicationServices
	 * @throws \Exception
	 */
	public function executeServices($plugin, $applicationServices)
	{
		$workflowManager = $applicationServices->getWorkflowManager();
		try
		{
			$applicationServices->getTransactionManager()->begin();

			/** @var \Rbs\Workflow\Documents\Workflow|null $workflow */
			$workflow = $workflowManager->getWorkflow('publicationProcess');
			if ($workflow === null)
			{
				$workflow = $applicationServices->getDocumentManager()->getNewDocumentInstanceByModelName('Rbs_Workflow_Workflow');
			}
			$publicationProcessWorkflow = new PublicationProcessWorkflow($applicationServices);
			$workflow = $publicationProcessWorkflow->install($workflow);
			$plugin->setConfigurationEntry('publicationProcess', $workflow->getId());
			$applicationServices->getTransactionManager()->commit();
		}
		catch (\Exception $e)
		{
			throw $applicationServices->getTransactionManager()->rollBack( $e);
		}

		try
		{
			$applicationServices->getTransactionManager()->begin();

			/** @var \Rbs\Workflow\Documents\Workflow|null $workflow */
			$workflow = $workflowManager->getWorkflow('correctionPublicationProcess');
			if ($workflow === null)
			{
				$workflow = $applicationServices->getDocumentManager()->getNewDocumentInstanceByModelName('Rbs_Workflow_Workflow');
			}

			$publicationProcessWorkflow = new CorrectionPublicationProcessWorkflow($applicationServices);
			$workflow = $publicationProcessWorkflow->install($workflow);
			$plugin->setConfigurationEntry('correctionPublicationProcess', $workflow->getId());

			$applicationServices->getTransactionManager()->commit();
		}
		catch (\Exception $e)
		{
			throw $applicationServices->getTransactionManager()->rollBack( $e);
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