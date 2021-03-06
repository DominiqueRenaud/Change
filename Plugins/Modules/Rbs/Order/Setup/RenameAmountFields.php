<?php
/**
 * Copyright (C) 2014 Proximis
 *
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/.
 */
require_once(getcwd() . '/Change/Application.php');

$application = new \Change\Application();
$application->start();


class RenameAmountFields
{
	public function migrate(\Change\Events\Event $event)
	{
		$tm = $event->getApplicationServices()->getTransactionManager();
		$dbProvider = $event->getApplicationServices()->getDbProvider();
		$upd = $dbProvider->getNewStatementBuilder();
		$fb = $upd->getFragmentBuilder();
		$upd->update($fb->getDocumentTable('Rbs_Order_Order'))
			->assign($fb->getDocumentColumn('paymentAmount'), $fb->getDocumentColumn('paymentAmountWithTaxes'));

		$upd->where($fb->isNotNull($fb->getDocumentColumn('paymentAmountWithTaxes')));

		$tm->begin();
		echo 'Rows updated: ', $upd->updateQuery()->execute(), PHP_EOL;
		$tm->commit();
	}
}

$eventManager = $application->getNewEventManager('Commerce');
$eventManager->attach('migrate', function (\Change\Events\Event $event)
{
	(new RenameAmountFields())->migrate($event);
});

$eventManager->trigger('migrate', null, []);