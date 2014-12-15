<?php
/**
 * Copyright (C) 2014 Ready Business System
 *
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/.
 */
namespace Rbs\Payment;

/**
 * @name \Rbs\Payment\PaymentManager
 */
class PaymentManager implements \Zend\EventManager\EventsCapableInterface
{
	use \Change\Events\EventsCapableTrait;

	const EVENT_MANAGER_IDENTIFIER = 'PaymentManager';

	/**
	 * @var \Change\Documents\DocumentManager
	 */
	protected $documentManager;

	/**
	 * @var \Change\Transaction\TransactionManager
	 */
	protected $transactionManager;

	/**
	 * @param \Change\Documents\DocumentManager $documentManager
	 * @return $this
	 */
	public function setDocumentManager($documentManager)
	{
		$this->documentManager = $documentManager;
		return $this;
	}

	/**
	 * @return \Change\Documents\DocumentManager
	 */
	protected function getDocumentManager()
	{
		return $this->documentManager;
	}

	/**
	 * @param \Change\Transaction\TransactionManager $transactionManager
	 * @return $this
	 */
	public function setTransactionManager($transactionManager)
	{
		$this->transactionManager = $transactionManager;
		return $this;
	}

	/**
	 * @return \Change\Transaction\TransactionManager
	 */
	protected function getTransactionManager()
	{
		return $this->transactionManager;
	}

	/**
	 * @return null|string|string[]
	 */
	protected function getEventManagerIdentifier()
	{
		return static::EVENT_MANAGER_IDENTIFIER;
	}

	/**
	 * @return string[]
	 */
	protected function getListenerAggregateClassNames()
	{
		return $this->getApplication()->getConfiguredListenerClassNames('Rbs/Payment/Events/PaymentManager');
	}

	/**
	 * @param \Change\Events\EventManager $eventManager
	 */
	protected function attachEvents(\Change\Events\EventManager $eventManager)
	{
		$eventManager->attach('getMailCode', array($this, 'onDefaultGetMailCode'), 5);
		$eventManager->attach('getMailSubstitutions', array($this, 'onDefaultGetMailSubstitutions'), 5);
		$eventManager->attach('handleProcessingForTransaction', [$this, 'onHandleProcessingForTransactionJob'], 5);
		$eventManager->attach('handleSuccessForTransaction', [$this, 'onHandleSuccessForTransactionJob'], 5);
		$eventManager->attach('handleFailedForTransaction', [$this, 'onHandleFailedForTransactionJob'], 5);
		$eventManager->attach('getTransactionStatusInfo', [$this, 'onDefaultGetTransactionStatusInfo'], 5);
		$eventManager->attach('getTransactionData', [$this, 'onDefaultGetTransactionData'], 5);
	}

	/**
	 * @param \Rbs\Payment\Documents\Transaction $transaction
	 * @param string $status
	 * @return string|null
	 */
	public function getMailCode($transaction, $status)
	{
		$eventManager = $this->getEventManager();
		$args = $eventManager->prepareArgs(array(
			'status' => $status,
			'transaction' => $transaction
		));
		$eventManager->trigger('getMailCode', $this, $args);
		return isset($args['code']) ? $args['code'] : null;
	}

	/**
	 * @param \Change\Documents\Events\Event $event
	 * @return array
	 */
	public function onDefaultGetMailCode($event)
	{
		$transaction = $event->getParam('transaction');
		if ($transaction instanceof \Rbs\Payment\Documents\Transaction)
		{
			switch ($event->getParam('status'))
			{
				case \Rbs\Payment\Documents\Transaction::STATUS_PROCESSING:
					$event->setParam('code', 'rbs_payment_transaction_processing');
					break;
				case \Rbs\Payment\Documents\Transaction::STATUS_SUCCESS:
					$event->setParam('code', 'rbs_payment_transaction_success');
					break;
				case \Rbs\Payment\Documents\Transaction::STATUS_FAILED:
					$event->setParam('code', 'rbs_payment_transaction_failed');
					break;
			}
		}
	}

	/**
	 * @param \Rbs\Payment\Documents\Transaction $transaction
	 * @param string $status
	 * @return array
	 */
	public function getMailSubstitutions($transaction, $status)
	{
		$eventManager = $this->getEventManager();
		$args = $eventManager->prepareArgs(array(
			'status' => $status,
			'transaction' => $transaction
		));
		$eventManager->trigger('getMailSubstitutions', $this, $args);
		return isset($args['substitutions']) ? $args['substitutions'] : [];
	}

	/**
	 * @param \Change\Documents\Events\Event $event
	 * @return array
	 */
	public function onDefaultGetMailSubstitutions($event)
	{
		//TODO
		$event->setParam('substitutions', []);
	}

	/**
	 * @param \Rbs\Payment\Documents\Transaction $transaction
	 * @param string $status
	 * @param \Rbs\Mail\MailManager $mailManager
	 * @throws \RuntimeException
	 */
	public function sendTransactionStatusChangedMail($transaction, $status, $mailManager)
	{
		$connector = $transaction->getConnector();
		$contextData = $transaction->getContextData();
		$email = $transaction->getEmail();
		$websiteId = isset($contextData['websiteId']) ? $contextData['websiteId'] : null;
		$LCID = isset($contextData['LCID']) ? $contextData['LCID'] : null;

		if ($email && $websiteId && $LCID && $connector->getProcessingMail())
		{
			/* @var $website \Rbs\Website\Documents\Website */
			$website = $this->getDocumentManager()->getDocumentInstance($websiteId);
			if ($website instanceof \Rbs\Website\Documents\Website)
			{
				$code = $this->getMailCode($transaction, $status);
				$substitutions = $this->getMailSubstitutions($transaction, $status);
				$mailManager->send($code, $website, $LCID, [$email], $substitutions);
			}
		}
	}

	/**
	 * @param \Rbs\Payment\Documents\Transaction $transaction
	 */
	public function handleProcessingForTransaction($transaction)
	{
		$em = $this->getEventManager();
		$args = $em->prepareArgs(array('transaction' => $transaction));
		$this->getEventManager()->trigger('handleProcessingForTransaction', $this, $args);
	}

	/**
	 * @param \Change\Events\Event $event
	 * @throws \Exception
	 */
	public function onHandleProcessingForTransactionJob(\Change\Events\Event $event)
	{
		/* @var $transaction \Rbs\Payment\Documents\Transaction */
		$transaction = $event->getParam('transaction');

		$arguments = array(
			'status' => \Rbs\Payment\Documents\Transaction::STATUS_PROCESSING,
			'transactionId' => $transaction->getId()
		);
		$event->getApplicationServices()->getJobManager()->createNewJob('Rbs_Payment_TransactionStatusChanged', $arguments);
	}

	/**
	 * @param \Rbs\Payment\Documents\Transaction $transaction
	 */
	public function handleSuccessForTransaction($transaction)
	{
		$em = $this->getEventManager();
		$args = $em->prepareArgs(array('transaction' => $transaction));
		$this->getEventManager()->trigger('handleSuccessForTransaction', $this, $args);
	}

	/**
	 * @param \Change\Events\Event $event
	 * @throws \Exception
	 */
	public function onHandleSuccessForTransactionJob(\Change\Events\Event $event)
	{
		/* @var $transaction \Rbs\Payment\Documents\Transaction */
		$transaction = $event->getParam('transaction');

		$arguments = array(
			'status' => \Rbs\Payment\Documents\Transaction::STATUS_SUCCESS,
			'transactionId' => $transaction->getId()
		);
		$event->getApplicationServices()->getJobManager()->createNewJob('Rbs_Payment_TransactionStatusChanged', $arguments);
	}

	/**
	 * @param \Rbs\Payment\Documents\Transaction $transaction
	 */
	public function handleFailedForTransaction($transaction)
	{
		$em = $this->getEventManager();
		$args = $em->prepareArgs(array('transaction' => $transaction));
		$this->getEventManager()->trigger('handleSuccessForTransaction', $this, $args);
	}

	/**
	 * @param \Change\Events\Event $event
	 * @throws \Exception
	 */
	public function onHandleFailedForTransactionJob(\Change\Events\Event $event)
	{
		/* @var $transaction \Rbs\Payment\Documents\Transaction */
		$transaction = $event->getParam('transaction');

		$arguments = array(
			'status' => \Rbs\Payment\Documents\Transaction::STATUS_FAILED,
			'transactionId' => $transaction->getId()
		);
		$event->getApplicationServices()->getJobManager()->createNewJob('Rbs_Payment_TransactionStatusChanged', $arguments);
	}

	/**
	 * @api
	 * @param \Rbs\User\Documents\User $user
	 * @param \Rbs\Payment\Documents\Transaction $transaction
	 */
	public function handleRegistrationForTransaction($user, $transaction)
	{
		$em = $this->getEventManager();
		$args = $em->prepareArgs(array('user' => $user, 'transaction' => $transaction));
		$this->getEventManager()->trigger('handleRegistrationForTransaction', $this, $args);
	}

	/**
	 * @param \Rbs\Payment\Documents\Transaction|integer $transaction
	 * @return array
	 */
	public function getTransactionStatusInfo($transaction)
	{
		$em = $this->getEventManager();
		$args = $em->prepareArgs(['transaction' => $transaction, 'statusInfo' => ['code' => null, 'title' => null]]);
		$this->getEventManager()->trigger('getTransactionStatusInfo', $this, $args);
		return $args['statusInfo'];
	}

	/**
	 * @param \Change\Events\Event $event
	 */
	public function onDefaultGetTransactionStatusInfo(\Change\Events\Event $event)
	{
		$transaction = $event->getParam('transaction');
		if (is_numeric($transaction))
		{
			$transaction = $event->getApplicationServices()->getDocumentManager()->getDocumentInstance($transaction);
		}

		$i18nManager = $event->getApplicationServices()->getI18nManager();
		if ($transaction instanceof \Rbs\Payment\Documents\Transaction)
		{
			$code = \Change\Stdlib\String::toUpper($transaction->getProcessingStatus());
			if ($code)
			{
				$statusInfo = ['code' => $code, 'title' => $code];
				switch ($code)
				{
					case 'INITIATED':
						$statusInfo['title'] = $i18nManager->trans('m.rbs.payment.front.transaction_status_initiated', ['ucf']);
						break;
					case 'PROCESSING':
						$statusInfo['title'] = $i18nManager->trans('m.rbs.payment.front.transaction_status_processing', ['ucf']);
						break;
					case 'SUCCESS':
						$statusInfo['title'] = $i18nManager->trans('m.rbs.payment.front.transaction_status_success', ['ucf']);
						break;
					case 'FAILED':
						$statusInfo['title'] = $i18nManager->trans('m.rbs.payment.front.transaction_status_failed', ['ucf']);
						break;
				}
				$event->setParam('statusInfo', $statusInfo);
			}
		}
	}

	/**
	 * Default context:
	 *  - *dataSetNames, *visualFormats, *URLFormats
	 *  - website, websiteUrlManager, section, page, detailed
	 *  - *data
	 * @api
	 * @param \Rbs\Payment\Documents\Transaction|integer $transaction
	 * @param array $context
	 * @return array
	 */
	public function getTransactionData($transaction, array $context)
	{
		$em = $this->getEventManager();
		if (is_numeric($transaction))
		{
			$transaction = $this->getDocumentManager()->getDocumentInstance($transaction);
		}

		if ($transaction instanceof \Rbs\Payment\Documents\Transaction)
		{
			$eventArgs = $em->prepareArgs(['transaction' => $transaction, 'context' => $context]);
			$em->trigger('getTransactionData', $this, $eventArgs);
			if (isset($eventArgs['transactionData']))
			{
				$transactionData = $eventArgs['transactionData'];
				if (is_object($transactionData))
				{
					$callable = [$transactionData, 'toArray'];
					if (is_callable($callable))
					{
						$transactionData = call_user_func($callable);
					}
				}
				if (is_array($transactionData))
				{
					return $transactionData;
				}
			}
		}
		return [];
	}

	/**
	 * Input params: transaction, context
	 * Output param: transactionData
	 * @param \Change\Events\Event $event
	 */
	public function onDefaultGetTransactionData(\Change\Events\Event $event)
	{
		if (!$event->getParam('transactionData'))
		{
			$transactionDataComposer = new \Rbs\Payment\TransactionDataComposer($event);
			$event->setParam('transactionData', $transactionDataComposer->toArray());
		}
	}
}