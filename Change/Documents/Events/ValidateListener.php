<?php
namespace Change\Documents\Events;

use Change\Documents\AbstractDocument;
use Change\Documents\DocumentManager;
use Change\Documents\Events\Event as DocumentEvent;
use Change\Documents\Interfaces\Editable;
use Change\Documents\Interfaces\Localizable;
use Change\Documents\Property;
use Change\I18n\PreparedKey;

/**
 * @name \Change\Documents\Events\ValidateListener
 */
class ValidateListener
{
	/**
	 * @var array
	 */
	protected $propertiesErrors;

	/**
	 * @param DocumentEvent $event
	 */
	public function onValidate($event)
	{
		if ($event instanceof DocumentEvent)
		{
			$document = $event->getDocument();
			$this->updateSystemProperties($document);

			$this->propertiesErrors = array();
			$this->validateProperties($document);

			if ($event->getName() === DocumentEvent::EVENT_UPDATE && $document instanceof Editable)
			{
				/* @var $document AbstractDocument|Editable */
				if ($document->isPropertyModified('documentVersion'))
				{
					$this->addPropertyError('documentVersion', new PreparedKey('c.constraints.isinvalidfield', array('ucf')));
				}
			}

			$event->setParam('propertiesErrors', count($this->propertiesErrors) ? $this->propertiesErrors : null);
		}
	}

	/**
	 * @param AbstractDocument $document
	 */
	protected function updateSystemProperties($document)
	{
		if ($document->getCreationDate() === null)
		{
			$document->setCreationDate(new \DateTime());
		}

		if ($document->getModificationDate() === null)
		{
			$document->setModificationDate(new \DateTime());
		}

		if ($document->getPersistentState() === DocumentManager::STATE_NEW && $document instanceof Localizable)
		{
			$document->setRefLCID($document->getLCID());
		}
	}

	/**
	 * @param AbstractDocument $document
	 */
	protected function validateProperties($document)
	{
		$modifiedPropertyNames = $document->getModifiedPropertyNames();
		foreach ($document->getDocumentModel()->getProperties() as $propertyName => $property)
		{
			/* @var $property Property */
			if ($document->isNew() || in_array($propertyName, $modifiedPropertyNames))
			{
				$this->validatePropertyValue($property, $document);
			}
		}
	}

	/**
	 * @param Property $property
	 * @param AbstractDocument $document
	 * @return boolean
	 */
	protected function validatePropertyValue($property, $document)
	{
		$value = $property->getValue($document);
		if ($property->getType() === Property::TYPE_DOCUMENTARRAY)
		{
			$nbValue = count($value);
			if ($nbValue === 0)
			{
				if (!$property->isRequired())
				{
					return true;
				}
				$this->addPropertyError($property->getName(), new PreparedKey('c.constraints.isempty', array('ucf')));
				return false;
			}
			elseif ($property->getMaxOccurs() > 1 && $nbValue > $property->getMaxOccurs())
			{
				$args = array('maxOccurs' => $property->getMaxOccurs());
				$this->addPropertyError($property->getName(), new PreparedKey('c.constraints.maxoccurs', array('ucf'), array($args)));
				return false;
			}
			elseif ($property->getMinOccurs() > 1 && $nbValue < $property->getMinOccurs())
			{
				$args = array('minOccurs' => $property->getMinOccurs());
				$this->addPropertyError($property->getName(), new PreparedKey('c.constraints.minoccurs', array('ucf'), array($args)));
				return false;
			}

		}
		elseif ($value === null || $value === '')
		{
			if (!$property->isRequired())
			{
				return true;
			}
			$this->addPropertyError($property->getName(), new PreparedKey('c.constraints.isempty', array('ucf')));
			return false;
		}
		elseif ($property->hasConstraints())
		{
			$constraintManager = $document->getDocumentServices()->getConstraintsManager();
			$defaultParams =  array('document' => $document, 'property' => $property);

			foreach ($property->getConstraintArray() as $name => $params)
			{
				$params += $defaultParams;
				$c = $constraintManager->getByName($name, $params);
				if (!$c->isValid($value))
				{
					$this->addPropertyErrors($property->getName(), $c->getMessages());
					return false;
				}
			}
		}
		return true;
	}

	/**
	 * @param string $propertyName
	 * @param string[] $errors
	 */
	protected function addPropertyErrors($propertyName, $errors)
	{
		if (is_array($errors) && count($errors))
		{
			foreach ($errors as $error)
			{
				/* @var $error string */
				$this->addPropertyError($propertyName, $error);
			}
		}
	}

	/**
	 * @param string $propertyName
	 * @param string $error
	 */
	protected function addPropertyError($propertyName, $error)
	{
		if ($error !== null)
		{
			$this->propertiesErrors[$propertyName][] = $error;
		}
	}
}