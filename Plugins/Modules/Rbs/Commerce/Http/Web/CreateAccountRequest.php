<?php
namespace Rbs\Commerce\Http\Web;

use Zend\Http\Response as HttpResponse;

/**
* @name \Rbs\Commerce\Http\Web\CreateAccountRequest
*/
class CreateAccountRequest extends \Rbs\User\Http\Web\CreateAccountRequest
{
	/**
	 * @param \Change\Http\Web\Event $event
	 * @return mixed|void
	 * @throws \Exception
	 */
	public function execute(\Change\Http\Web\Event $event)
	{
		if ($event->getRequest()->getMethod() === 'POST')
		{
			$i18nManager = $event->getApplicationServices()->getI18nManager();
			$data = $event->getRequest()->getPost()->toArray();
			$documentManager = $event->getApplicationServices()->getDocumentManager();
			$transactionId = $event->getRequest()->getQuery('transactionId');
			$transaction = $documentManager->getDocumentInstance($transactionId);
			if (!($transaction instanceof \Rbs\Payment\Documents\Transaction) || !$transaction->getEmail())
			{
				$parametersErrors = array($i18nManager->trans('m.rbs.commerce.front.invalid_transaction'));
				$event->setResult($this->getErrorResult($parametersErrors, $data));
				return;
			}
			$data['email'] = $transaction->getEmail();

			// Instantiate constraint manager to register locales in validation.
			$event->getApplicationServices()->getConstraintsManager();
			$parametersErrors = $this->getParametersErrors($event, $data);
			if (count($parametersErrors) === 0)
			{
				$email = $data['email'];
				$parameters = $this->getAccountRequestParameters($event, $data);
				$parameters['Rbs_Commerce_TransactionId'] = $transactionId;
				$LCID = $event->getRequest()->getLCID();
				$website = $event->getWebsite();
				$this->createAccountRequest($event, $email, $parameters, $website, $LCID);

				$event->setResult($this->getSuccessResult($data));
			}
			else
			{
				$event->setResult($this->getErrorResult($parametersErrors, $data));
			}
		}
	}

	/**
	 * @param \Change\Http\Web\UrlManager $urlManager
	 * @param array $query
	 * @return string
	 */
	protected function getConfirmationUrl($urlManager, $query)
	{
		return $urlManager->getAjaxURL('Rbs_Commerce', 'CreateAccountConfirmation', $query);
	}
}