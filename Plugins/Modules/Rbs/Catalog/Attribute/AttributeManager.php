<?php
/**
 * Copyright (C) 2014 Ready Business System
 *
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/.
 */
namespace Rbs\Catalog\Attribute;

use Rbs\Catalog\Documents\Attribute;

/**
 * @name \Rbs\Catalog\Attribute\AttributeManager
 */
class AttributeManager
{
	/**
	 * @var \Change\Collection\CollectionManager
	 */
	protected $collectionManager;

	/**
	 * @var \Change\Documents\DocumentManager
	 */
	protected $documentManager;

	/**
	 * @var \Change\Db\DbProvider
	 */
	protected $dbProvider;

	/**
	 * @var \Change\I18n\I18nManager
	 */
	protected $i18nManager;

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
	 * @param \Change\Collection\CollectionManager $collectionManager
	 * @return $this
	 */
	public function setCollectionManager($collectionManager)
	{
		$this->collectionManager = $collectionManager;
		return $this;
	}

	/**
	 * @return \Change\Collection\CollectionManager
	 */
	protected function getCollectionManager()
	{
		return $this->collectionManager;
	}

	/**
	 * @param \Change\Db\DbProvider $dbProvider
	 * @return $this
	 */
	public function setDbProvider($dbProvider)
	{
		$this->dbProvider = $dbProvider;
		return $this;
	}

	/**
	 * @return \Change\Db\DbProvider
	 */
	protected function getDbProvider()
	{
		return $this->dbProvider;
	}

	/**
	 * @param \Change\I18n\I18nManager $i18nManager
	 * @return $this
	 */
	public function setI18nManager($i18nManager)
	{
		$this->i18nManager = $i18nManager;
		return $this;
	}

	/**
	 * @return \Change\I18n\I18nManager
	 */
	protected function getI18nManager()
	{
		return $this->i18nManager;
	}


	/**
	 * Use DbProvider
	 * @param \Rbs\Catalog\Documents\Product|integer $product
	 * @return array
	 */
	public function getAttributeValues($product)
	{
		$productId = ($product instanceof \Rbs\Catalog\Documents\Product) ? $product->getId() : intval($product);
		$qb = $this->getDbProvider()->getNewQueryBuilder();
		$fb = $qb->getFragmentBuilder();
		$qb->select($fb->column('attribute_id'), $fb->alias($fb->getDocumentColumn('valueType'), 'valueType'),
			$fb->column('integer_value'), $fb->column('date_value'), $fb->column('float_value'),
			$fb->column('string_value'), $fb->column('text_value'));
		$qb->from($fb->table('rbs_catalog_dat_attribute'));
		$qb->innerJoin($fb->getDocumentTable('Rbs_Catalog_Attribute'),
			$fb->eq($fb->getDocumentColumn('id'), $fb->column('product_id')));
		$qb->where($fb->eq($fb->column('product_id'), $fb->integerParameter('productId')));
		$query = $qb->query();
		$query->bindParameter('productId', $productId);
		$rows = $query->getResults($query->getRowsConverter()->addIntCol('attribute_id', 'integer_value')
			->addStrCol('valueType', 'string_value')->addNumCol('float_value')->addDtCol('date_value')->addTxtCol('text_value'));
		$values = array();
		
		foreach ($rows as $row)
		{
			$val = array('id' => $row['attribute_id'], 'valueType' => $row['valueType']);
			switch ($row['valueType'])
			{
				case Attribute::TYPE_BOOLEAN:
					$val['value'] = $row['integer_value'] != 0;
					break;
				case Attribute::TYPE_INTEGER:
				case Attribute::TYPE_DOCUMENTID:
					$val['value'] = $row['integer_value'];
					break;
				case Attribute::TYPE_DATETIME:
					/* @var $v \DateTime */
					$v = $row['date_value'];
					$val['value'] = $v->format(\DateTime::ISO8601);
					break;
				case Attribute::TYPE_FLOAT:
					$val['value'] = $row['float_value'];
					break;
				case Attribute::TYPE_STRING:
					$val['value'] = $row['string_value'];
					break;
				case Attribute::TYPE_TEXT:
					$val['value'] = $row['text_value'];
					break;
				default:
					$val['value'] = null;
					break;
			}
			$values[] = $val;
		}
		return $values;
	}

	protected $cachedAttrWithValue = [];

	/**
	 * @param Attribute $groupAttribute
	 * @return array
	 */
	protected function getAttributesWithValues(\Rbs\Catalog\Documents\Attribute $groupAttribute)
	{
		if (!isset($this->cachedAttrWithValue[$groupAttribute->getId()]))
		{
			$attributesWithValues = [];
			foreach ($groupAttribute->getAttributes() as $attribute)
			{
				$vt = $attribute->getValueType();
				if ($vt == Attribute::TYPE_GROUP)
				{
					$attributesWithValues = array_merge($attributesWithValues, $this->getAttributesWithValues($attribute));
				}
				elseif ($vt != Attribute::TYPE_PROPERTY)
				{
					$attributesWithValues[] = ['id' => $attribute->getId(), 'valueType' => $vt,
						'value' => $attribute->getDefaultValue()];
				}
			}
			$this->cachedAttrWithValue[$groupAttribute->getId()] = $attributesWithValues;
		}
		return $this->cachedAttrWithValue[$groupAttribute->getId()];
	}

	/**
	 * Use DbProvider
	 * @param \Rbs\Catalog\Documents\Product|integer $product
	 * @param array $values
	 */
	public function setAttributeValues($product, $values)
	{
		$productId = ($product instanceof \Rbs\Catalog\Documents\Product) ? $product->getId() : intval($product);
		$groupAttribute = $product->getAttribute();
		if (!$groupAttribute)
		{
			$this->deleteAttributeValue($productId);
			return;
		}
		$attributesWithValues = $this->getAttributesWithValues($groupAttribute);
		if (count($attributesWithValues) == 0)
		{
			$this->deleteAttributeValue($productId);
			return;
		}

		if (!is_array($values))
		{
			$values = [];
		}

		$defined = $this->getDefinedAttributesValues($productId);
		foreach ($attributesWithValues as $attrWithValue)
		{
			$value = $attrWithValue;
			$id = $attrWithValue['id'];
			foreach ($values as $v)
			{
				if ($v['id'] == $id)
				{
					$value = $v;
					break;
				}
			}

			if (isset($defined[$id]))
			{
				$this->updateAttributeValue($defined[$id], $value);
			}
			else
			{
				$defined[$id] = $this->insertAttributeValue($productId, $value);
			}
		}
	}

	/**
	 * @api
	 * @param \Rbs\Catalog\Documents\Product|integer $product
	 * @param \Rbs\Catalog\Documents\Attribute[]|integer[] $attributes
	 * @return integer[]
	 */
	public function initProductAttributesValue($product, array $attributes = [])
	{
		$inserted = [];
		$productId = ($product instanceof \Rbs\Catalog\Documents\Product) ? $product->getId() : intval($product);
		$attributeIds = [];
		foreach ($attributes as $attribute)
		{
			$attributeIds[] = ($attribute instanceof \Rbs\Catalog\Documents\Attribute) ? $attribute->getId() : intval($attribute);
		}
		$defined = $this->getDefinedAttributesValues($productId);
		$value = ['valueType' => Attribute::TYPE_STRING, 'value' => null];

		foreach ($attributeIds as $attributeId)
		{
			if (!isset($defined[$attributeId]))
			{
				$value['id'] = $attributeId;
				$this->insertAttributeValue($productId, $value);
				$inserted[] = $attributeId;
			}
		}
		return $inserted;
	}

	/**
	 * @param integer $productId
	 * @return array
	 */
	protected function getDefinedAttributesValues($productId)
	{
		$qb = $this->getDbProvider()->getNewQueryBuilder();
		$fb = $qb->getFragmentBuilder();
		$qb->select($fb->column('id'), $fb->column('attribute_id'));
		$qb->from($fb->table('rbs_catalog_dat_attribute'));
		$qb->where($fb->eq($fb->column('product_id'), $fb->integerParameter('productId')));
		$query = $qb->query();
		$query->bindParameter('productId', $productId);
		$result = array();
		foreach ($query->getResults($query->getRowsConverter()->addIntCol('id', 'attribute_id')) as $row)
		{
			$result[$row['attribute_id']] = $row['id'];
		}
		return $result;
	}

	protected function dispatchValue($valueType, $value)
	{
		//integer_value, float_value, date_value, string_value, text_value
		$result = array(null, null, null, null, null);
		if ($value !== null && $value !== '')
		{
			switch ($valueType)
			{
				case Attribute::TYPE_BOOLEAN:
					$result[0] = $value ? 1 : 0;
					break;
				case Attribute::TYPE_INTEGER:
				case Attribute::TYPE_DOCUMENTID:
					$result[0] = is_array($value) ? intval($value['id']) : intval($value);
					break;
				case Attribute::TYPE_FLOAT:
					$result[1] = $value;
					break;
				case Attribute::TYPE_DATETIME:
					$result[2] = is_string($value) ? new \DateTime($value) : $value;
					break;
				case Attribute::TYPE_STRING:
					$result[3] = $value;
					break;
				case Attribute::TYPE_TEXT:
					$result[4] = (is_array($value)) ? (isset($value['t']) ? $value['t'] : null) : $value;
					break;
			}
		}
		return $result;
	}

	/**
	 * @param integer $productId
	 * @param array $value
	 * @return integer
	 */
	protected function insertAttributeValue($productId, $value)
	{
		$valueType = $value['valueType'];
		$attributeId = $value['id'];
		list($integerValue, $floatValue, $dateValue, $stringValue, $textValue) = $this->dispatchValue($valueType,
			$value['value']);
		$qb = $this->getDbProvider()->getNewStatementBuilder('insertAttributeValue');
		if (!$qb->isCached())
		{
			$fb = $qb->getFragmentBuilder();
			$qb->insert($fb->table('rbs_catalog_dat_attribute'),
				$fb->column('product_id'), $fb->column('attribute_id'),
				$fb->column('integer_value'), $fb->column('float_value'), $fb->column('date_value'),
				$fb->column('string_value'), $fb->column('text_value'));
			$qb->addValues($fb->integerParameter('productId'), $fb->integerParameter('attributeId'),
				$fb->integerParameter('integerValue'), $fb->decimalParameter('floatValue'), $fb->dateTimeParameter('dateValue'),
				$fb->parameter('stringValue'), $fb->lobParameter('textValue'));
		}
		$is = $qb->insertQuery();
		$is->bindParameter('productId', $productId)->bindParameter('attributeId', $attributeId)
			->bindParameter('integerValue', $integerValue)->bindParameter('floatValue', $floatValue)
			->bindParameter('dateValue', $dateValue)
			->bindParameter('stringValue', $stringValue)->bindParameter('textValue', $textValue);
		$is->execute();
		return $is->getDbProvider()->getLastInsertId('rbs_catalog_dat_attribute');
	}

	/**
	 * @param integer $attrId
	 * @param array $value
	 * @return integer
	 */
	protected function updateAttributeValue($attrId, $value)
	{
		$valueType = $value['valueType'];
		list($integerValue, $floatValue, $dateValue, $stringValue, $textValue) = $this->dispatchValue($valueType,
			$value['value']);
		$qb = $this->getDbProvider()->getNewStatementBuilder('updateAttributeValue');
		if (!$qb->isCached())
		{
			$fb = $qb->getFragmentBuilder();
			$qb->update($fb->table('rbs_catalog_dat_attribute'));
			$qb->assign($fb->column('integer_value'), $fb->integerParameter('integerValue'));
			$qb->assign($fb->column('float_value'), $fb->decimalParameter('floatValue'));
			$qb->assign($fb->column('date_value'), $fb->dateTimeParameter('dateValue'));
			$qb->assign($fb->column('string_value'), $fb->parameter('stringValue'));
			$qb->assign($fb->column('text_value'), $fb->lobParameter('textValue'));
			$qb->where($fb->eq($fb->column('id'), $fb->integerParameter('attrId')));
		}
		$uq = $qb->updateQuery();
		$uq->bindParameter('integerValue', $integerValue)->bindParameter('floatValue', $floatValue)
			->bindParameter('dateValue', $dateValue)
			->bindParameter('stringValue', $stringValue)->bindParameter('textValue', $textValue)
			->bindParameter('attrId', $attrId);
		$uq->execute();
	}

	/**
	 * @param integer $productId
	 * @param array $excludeAttrIds
	 */
	protected function deleteAttributeValue($productId, $excludeAttrIds = array())
	{
		$qb = $this->getDbProvider()->getNewStatementBuilder();
		$fb = $qb->getFragmentBuilder();
		$qb->delete($fb->table('rbs_catalog_dat_attribute'));
		if (count($excludeAttrIds))
		{
			$notIn = array();
			foreach ($excludeAttrIds as $id)
			{
				$notIn[] = $fb->number($id);
			}
			$qb->where($fb->logicAnd(
				$fb->eq($fb->column('product_id'), $fb->integerParameter('productId')),
				$fb->notIn($fb->column('id'), $notIn)
			));
		}
		else
		{
			$qb->where($fb->eq($fb->column('product_id'), $fb->integerParameter('productId')));
		}
		$dq = $qb->deleteQuery();
		$dq->bindParameter('productId', $productId);
		$dq->execute();
	}

	/**
	 * @param Attribute $attribute
	 * @return array|null
	 */
	public function buildEditorDefinition(Attribute $attribute)
	{
		if ($attribute->getValueType() === Attribute::TYPE_GROUP)
		{
			$definition = array('attributes' => array());
			$ids = array($attribute->getId());
			foreach ($attribute->getAttributes() as $childAttribute)
			{
				$ids[] = $childAttribute->getId();
				if ($childAttribute->getValueType() === Attribute::TYPE_GROUP)
				{
					$defGroup = $this->buildGroupDefinition($childAttribute, $ids);
					if (count($defGroup['attributes']))
					{
						$definition['attributes'][] = $defGroup;
					}
				}
				else
				{
					$def = $this->buildAttributeDefinition($childAttribute);
					if ($def)
					{
						$definition['attributes'][] = $def;
					}
				}
			}
			if (count($definition['attributes']))
			{
				$definition['ids'] = $ids;
				return $definition;
			}
		}
		return null;
	}

	/**
	 * @param Attribute $attribute
	 * @param $ids
	 * @return array
	 */
	public function buildGroupDefinition($attribute, &$ids)
	{
		$definition = array('label' => $attribute->getLabel(), 'attributes' => array());
		foreach ($attribute->getAttributes() as $childAttribute)
		{
			if (!in_array($childAttribute->getId(), $ids))
			{
				$ids[] = $childAttribute->getId();
				if ($childAttribute->getValueType() === Attribute::TYPE_GROUP)
				{
					$groupDef = $this->buildGroupDefinition($childAttribute, $ids);
					$definition['attributes'] = array_merge($definition['attributes'], $groupDef['attributes']);
				}
				else
				{
					$def = $this->buildAttributeDefinition($childAttribute);
					if ($def)
					{
						$definition['attributes'][] = $def;
					}
				}
			}
		}
		return $definition;
	}

	/**
	 * @param Attribute $attribute
	 * @return array|null
	 */
	public function buildAttributeDefinition($attribute)
	{
		$vt = $attribute->getValueType();
		$definition = array('id' => $attribute->getId(), 'label' => $attribute->getLabel(),
			'required' => $attribute->getRequiredValue(), 'valueType' => $vt, 'type' => $vt,
			'defaultValue' => null, 'collectionCode' => null, 'values' => null, 'usePicker' => $attribute->getUsePicker(), 'axis' => $attribute->getAxis());

		if (Attribute::TYPE_PROPERTY == $vt)
		{
			$property = $attribute->getModelProperty();
			if (!$property)
			{
				return null;
			}
			$propertyName = $property->getName();

			$definition['propertyName']  = $propertyName;
			switch ($property->getType())
			{
				case \Change\Documents\Property::TYPE_DOCUMENT :
				case \Change\Documents\Property::TYPE_DOCUMENTID :
				case \Change\Documents\Property::TYPE_DOCUMENTARRAY :
					$definition['type'] = $property->getType();
					$definition['documentType'] = ($property->getDocumentType()) ? $property->getDocumentType() : '';
					break;
				case \Change\Documents\Property::TYPE_STRING :
					$definition['type'] = Attribute::TYPE_STRING;
					break;
				case \Change\Documents\Property::TYPE_BOOLEAN :
					$definition['type'] = Attribute::TYPE_BOOLEAN;
					break;
				case \Change\Documents\Property::TYPE_INTEGER :
					$definition['type'] = Attribute::TYPE_INTEGER;
					break;
				case \Change\Documents\Property::TYPE_FLOAT :
				case \Change\Documents\Property::TYPE_DECIMAL :
					$definition['type'] = Attribute::TYPE_FLOAT;
					break;
				case \Change\Documents\Property::TYPE_DATE :
				case \Change\Documents\Property::TYPE_DATETIME :
					$definition['type'] = Attribute::TYPE_DATETIME;
					break;
				case \Change\Documents\Property::TYPE_RICHTEXT :
					$definition['type'] = Attribute::TYPE_TEXT;
					break;
				default:
					return null;
			}
		}
		elseif (Attribute::TYPE_DOCUMENTID == $vt || Attribute::TYPE_DOCUMENTIDARRAY == $vt)
		{
			$definition['documentType'] = ($attribute->getDocumentType()) ? $attribute->getDocumentType() : '';
		}

		if (($dv = $attribute->getDefaultValue()) !== null)
		{
			if ($vt === Attribute::TYPE_BOOLEAN)
			{
				$definition['defaultValue'] = ($dv == '1' || $dv == 'true');
			}
			elseif ($vt === Attribute::TYPE_INTEGER)
			{
				$definition['defaultValue'] = intval($dv);
			}
			elseif ($vt === Attribute::TYPE_FLOAT)
			{
				$definition['defaultValue'] = floatval($dv);
			}
			elseif ($vt === Attribute::TYPE_DATETIME)
			{
				$definition['defaultValue'] = (new \DateTime($dv))->format(\DateTime::ISO8601);
			}
			elseif ($vt === Attribute::TYPE_STRING)
			{
				$definition['defaultValue'] = $dv;
			}
		}

		if (in_array($vt, array(Attribute::TYPE_INTEGER, Attribute::TYPE_STRING, Attribute::TYPE_DOCUMENTID)) && $attribute->getCollectionCode())
		{
			$definition['values'] = $this->getCollectionValues($attribute);
			if (is_array($definition['values']))
			{
				$definition['collectionCode'] = $attribute->getCollectionCode();
			}
		}
		return $definition;
	}

	/**
	 * Use CollectionManager
	 * @param Attribute $attribute
	 * @return array|null
	 */
	public function getCollectionValues($attribute)
	{
		$cm = $this->getCollectionManager();
		if ($cm && $attribute instanceof Attribute && $attribute->getCollectionCode())
		{
			$collection = $cm->getCollection($attribute->getCollectionCode());
			if ($collection)
			{
				$values = array();
				foreach($collection->getItems() as $item)
				{
					$values[] = array('value' => $item->getValue(), 'label' => $item->getLabel(), 'title' => $item->getTitle());
				}
				return $values;
			}
		}
		return null;
	}

	/**
	 * @param \Rbs\Catalog\Documents\Product $product
	 * @param \Change\Documents\Property | string $property
	 * @return boolean
	 */
	public function hasAttributeForProperty ($product, $property)
	{
		if ($property instanceof \Change\Documents\Property)
		{
			$property = $property->getName();
		}

		$groupAttribute = $product->getAttribute();
		if (!$groupAttribute || !$groupAttribute->getAttributesCount())
		{
			return false;
		}

		foreach ($groupAttribute->getAttributes() as $attribute)
		{
			if ($attribute->getValueType() == Attribute::TYPE_PROPERTY)
			{
				if ($attribute->getProductProperty() ==  $property)
				{
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * @param array $attributeValues
	 * @param Attribute $attribute
	 * @return array|null
	 */
	public function normalizeRestAttributeValues($attributeValues, $attribute)
	{
		if ($attribute instanceof Attribute)
		{
			$normalizedValues = $this->getInlineAttributeDefaultValue($attribute);
		}
		else
		{
			return null;
		}

		if (is_array($attributeValues) && count($attributeValues))
		{
			$utcTimeZone = new \DateTimeZone('UTC');
			foreach ($attributeValues as $attributeValue)
			{
				$id = intval($attributeValue['id']);
				if (!isset($normalizedValues[$id]))
				{
					continue;
				}
				$valueType = $normalizedValues[$id]['valueType'];
				$value = isset($attributeValue['value']) ? $attributeValue['value'] : null;
				if ($value === null)
				{
					//null value no need conversion
					$normalizedValues[$id]['value'] = null;
					continue;
				}

				switch ($valueType)
				{
					case Attribute::TYPE_DOCUMENTID:
						if (is_array($value) && isset($value['id']))
						{
							$value = $value['id'];
						}

						if (is_numeric($value) && $value > 0)
						{
							$value = intval($value);
						}
						else
						{
							$value = null;
						}
						break;
					case Attribute::TYPE_DOCUMENTIDARRAY:
						if (is_array($value))
						{
							$ids = array();
							foreach ($value as $docId)
							{
								if (is_array($docId) && isset($docId['id']))
								{
									$docId = $docId['id'];
								}

								if (is_numeric($docId) && $docId > 0)
								{
									$ids[] = intval($docId);
								}
							}
							$value = count($ids) ? $ids : null;
						}
						else
						{
							$value = null;
						}
						break;
					case Attribute::TYPE_DATETIME:
						$value = (new \DateTime($value, $utcTimeZone))->format(\DateTime::ISO8601);
						break;
					case Attribute::TYPE_TEXT:
						$value = (new \Change\Documents\RichtextProperty($value))->toArray();
						break;
				}
				$normalizedValues[$id]['value'] = $value;
			}
		}

		return array_values($normalizedValues);
	}

	/**
	 * @param Attribute $attribute
	 * @return array
	 */
	protected function getInlineAttributeDefaultValue(Attribute $attribute)
	{
		$defaultValues = [];
		$attributeId = $attribute->getId();
		$valueType = $attribute->getValueType();

		$value = ['id' => $attributeId, 'valueType' => $valueType, 'value' => null];
		switch ($valueType) {
			case Attribute::TYPE_PROPERTY:
				break;
			case Attribute::TYPE_GROUP:
				foreach ($attribute->getAttributes() as $subAttribute)
				{
					foreach ($this->getInlineAttributeDefaultValue($subAttribute) as $id => $value)
					{
						$defaultValues[$id] = $value;
					}
				}
				break;
			default:
				$defaultValues[$attributeId] = $value;
		}
		return $defaultValues;
	}

	/**
	 * @param Attribute $groupAttribute
	 * @return Attribute[]
	 */
	public function getAxisAttributes($groupAttribute)
	{
		$axeAttributes = array();
		if ($groupAttribute instanceof Attribute && $groupAttribute->getValueType() === Attribute::TYPE_GROUP)
		{
			foreach ($groupAttribute->getAttributes() as $axeAttribute)
			{
				if ($axeAttribute->getValueType() === Attribute::TYPE_GROUP )
				{
					$axeAttributes = array_merge($axeAttributes, $this->getAxisAttributes($axeAttribute));
				}
				elseif ($axeAttribute->getAxis())
				{
					$axeAttributes[] = $axeAttribute;
				}
			}
		}
		return $axeAttributes;
	}

	/**
	 * @param string $visibility
	 * @param \Rbs\Catalog\Documents\Product $product
	 * @return array
	 */
	public function getProductAttributesConfiguration($visibility, $product)
	{
		if (!($product instanceof \Rbs\Catalog\Documents\Product))
		{
			return array();
		}
		$groupAttribute = $product->getAttribute();
		if (!$groupAttribute || !$groupAttribute->getAttributesCount())
		{
			return array();
		}

		$attributeValues = $product->getCurrentLocalization()->getAttributeValues();
		if (!is_array($attributeValues))
		{
			$attributeValues = array();
		}

		$configuration = array('global' => array('items' => array()));
		foreach ($groupAttribute->getAttributes() as $attribute)
		{
			if (!$attribute->isVisibleFor($visibility))
			{
				continue;
			}

			if ($attribute->getAttributesCount())
			{
				$title = $attribute->getCurrentLocalization()->getTitle();
				$configuration[$attribute->getId()] = array('title' => $title,
					'items' => $this->generateItems($attribute, $visibility, $product, $attributeValues));
			}
			else
			{
				$item = $this->generateItem($attribute, $product, $attributeValues);
				if ($item)
				{
					$configuration['global']['items'][$attribute->getId()] = $item;
				}
			}
		}

		if (count($configuration['global']['items']))
		{
			$i18n = $this->getI18nManager();
			$configuration['global']['title'] = $i18n->trans('m.rbs.catalog.front.main_attributes', array('ucf'));
		}
		else
		{
			unset($configuration['global']);
		}
		return $configuration;
	}

	/**
	 * @param \Rbs\Catalog\Documents\Attribute $group
	 * @param string $visibility
	 * @param \Rbs\Catalog\Documents\Product $product
	 * @param array $attributeValues
	 * @return array
	 */
	protected function generateItems(\Rbs\Catalog\Documents\Attribute $group, $visibility, $product, $attributeValues)
	{
		$items = array();
		foreach ($group->getAttributes() as $attribute)
		{
			if (!$attribute->isVisibleFor($visibility))
			{
				continue;
			}
			if ($attribute->getAttributesCount())
			{
				$items = array_merge($items, $this->generateItems($attribute, $visibility, $product, $attributeValues));
			}
			else
			{
				$item = $this->generateItem($attribute, $product, $attributeValues);
				if ($item)
				{
					$items[$attribute->getId()] = $item;
				}
			}
		}
		return $items;
	}

	/**
	 * @param \Rbs\Catalog\Documents\Attribute $attribute
	 * @param \Rbs\Catalog\Documents\Product $product
	 * @param array $attributeValues
	 * @return array
	 */
	protected function generateItem(\Rbs\Catalog\Documents\Attribute $attribute, $product, $attributeValues)
	{
		$attributeId = $attribute->getId();

		$value = array_reduce($attributeValues, function($result, $attrVal) use ($attributeId) {
			return $attributeId == $attrVal['id'] ? (isset($attrVal['value']) ? $attrVal['value'] : null) : $result;
		});
		$valueType = $attribute->getValueType();
		switch ($valueType)
		{
			case \Rbs\Catalog\Documents\Attribute::TYPE_PROPERTY:
				if ($product)
				{
					$property = $attribute->getModelProperty();
					if ($property)
					{
						$valueType = $this->getAttributeTypeFromProperty($property);
						$value = $property->getValue($product);
					}
				}
				if ($valueType == \Rbs\Catalog\Documents\Attribute::TYPE_PROPERTY)
				{
					$valueType = \Rbs\Catalog\Documents\Attribute::TYPE_TEXT;
					$value = strval($value);
				}
				break;

			case \Rbs\Catalog\Documents\Attribute::TYPE_DOCUMENTID:
				if ($value !== null)
				{
					$value = $this->getDocumentManager()->getDocumentInstance($value);
				}
				break;

			case \Rbs\Catalog\Documents\Attribute::TYPE_DOCUMENTIDARRAY:
				$documents = array();
				if (is_array($value))
				{
					foreach ($value as $id)
					{
						$d = $this->getDocumentManager()->getDocumentInstance($id);
						if ($d)
						{
							$documents[] = $d;
						}
					}
					$value = $documents;
				}
				else
				{
					$value = $documents;
				}
				break;

			case \Rbs\Catalog\Documents\Attribute::TYPE_DATETIME:
				if ($value !== null)
				{
					$value = new \DateTime($value);
				}
				break;

			case \Rbs\Catalog\Documents\Attribute::TYPE_TEXT:
				if ($value !== null)
				{
					$value = new \Change\Documents\RichtextProperty($value);
				}
				break;
		}

		if ($value)
		{
			return $this->renderItem($attribute, $value, $valueType);
		}
		return null;
	}

	/**
	 * @param \Change\Documents\Property $property
	 * @return string
	 */
	protected function getAttributeTypeFromProperty($property)
	{
		switch ($property->getType())
		{
			case \Change\Documents\Property::TYPE_DOCUMENT :
			case \Change\Documents\Property::TYPE_DOCUMENTID :
				return \Rbs\Catalog\Documents\Attribute::TYPE_DOCUMENTID;
			case \Change\Documents\Property::TYPE_DOCUMENTARRAY :
				return \Rbs\Catalog\Documents\Attribute::TYPE_DOCUMENTIDARRAY;
			case \Change\Documents\Property::TYPE_STRING :
				return \Rbs\Catalog\Documents\Attribute::TYPE_STRING;
			case \Change\Documents\Property::TYPE_BOOLEAN :
				return \Rbs\Catalog\Documents\Attribute::TYPE_BOOLEAN;
			case \Change\Documents\Property::TYPE_INTEGER :
				return \Rbs\Catalog\Documents\Attribute::TYPE_INTEGER;
			case \Change\Documents\Property::TYPE_FLOAT :
			case \Change\Documents\Property::TYPE_DECIMAL :
				return \Rbs\Catalog\Documents\Attribute::TYPE_FLOAT;
			case \Change\Documents\Property::TYPE_DATE :
			case \Change\Documents\Property::TYPE_DATETIME :
				return \Rbs\Catalog\Documents\Attribute::TYPE_DATETIME;
			case \Change\Documents\Property::TYPE_RICHTEXT :
				return \Rbs\Catalog\Documents\Attribute::TYPE_TEXT;
			default:
				return null;
		}
	}

	/**
	 * @param \Rbs\Catalog\Documents\Attribute $attribute
	 * @param mixed $value
	 * @param string $valueType
	 * @return array
	 */
	protected function renderItem($attribute, $value, $valueType)
	{
		$title = $attribute->getCurrentLocalization()->getTitle();
		$item = array('title' => $title, 'value' => $value, 'valueType' => $valueType);

		$description = $attribute->getCurrentLocalization()->getDescription();
		if ($description && !$description->isEmpty())
		{
			$item['description'] = $description;
		}

		switch ($item['valueType'])
		{
			case \Rbs\Catalog\Documents\Attribute::TYPE_DOCUMENTID:
				if (!$this->isValidDocument($value))
				{
					return null;
				}
				$item['template'] = 'Rbs_Catalog/Blocks/Attribute/document.twig';
				break;

			case \Rbs\Catalog\Documents\Attribute::TYPE_DOCUMENTIDARRAY:
				foreach ($value as $index => $document)
				{
					if (!$this->isValidDocument($document))
					{
						unset($value[$index]);
					}
				}
				if (count($value) < 1)
				{
					return null;
				}
				$item['template'] = 'Rbs_Catalog/Blocks/Attribute/documentarray.twig';
				break;

			case \Rbs\Catalog\Documents\Attribute::TYPE_DATETIME:
				$item['template'] = 'Rbs_Catalog/Blocks/Attribute/datetime.twig';
				break;

			case \Rbs\Catalog\Documents\Attribute::TYPE_BOOLEAN:
				$item['template'] = 'Rbs_Catalog/Blocks/Attribute/boolean.twig';
				break;

			case \Rbs\Catalog\Documents\Attribute::TYPE_FLOAT:
				$item['template'] = 'Rbs_Catalog/Blocks/Attribute/float.twig';
				break;

			case \Rbs\Catalog\Documents\Attribute::TYPE_INTEGER:
				$value = $this->getCollectionItemTitle($attribute->getCollectionCode(), $value);
				if ($value !== false)
				{
					$item['value'] = $value;
					$item['template'] = 'Rbs_Catalog/Blocks/Attribute/text.twig';
				}
				else
				{
					$item['template'] = 'Rbs_Catalog/Blocks/Attribute/integer.twig';
				}
				break;

			case \Rbs\Catalog\Documents\Attribute::TYPE_TEXT:
				$item['template'] = 'Rbs_Catalog/Blocks/Attribute/richtext.twig';
				break;

			default:
				$value = $this->getCollectionItemTitle($attribute->getCollectionCode(), $value);
				if ($value !== false)
				{
					$item['value'] = $value;
				}
				$item['template'] = 'Rbs_Catalog/Blocks/Attribute/text.twig';
				break;
		}
		return $item;
	}

	/**
	 * @param \Change\Documents\AbstractDocument $document
	 * @return boolean
	 */
	protected function isValidDocument($document)
	{
		if ($document instanceof \Change\Documents\Interfaces\Publishable)
		{
			return $document->published();
		}
		elseif ($document instanceof \Change\Documents\Interfaces\Activable)
		{
			return $document->activated();
		}
		return true;
	}


	/**
	 * @param string $collectionCode
	 * @param string $value
	 * @return string|boolean
	 */
	protected function getCollectionItemTitle($collectionCode, $value)
	{
		if (is_string($collectionCode))
		{
			$c = $this->getCollectionManager()->getCollection($collectionCode);
			if ($c)
			{
				$i = $c->getItemByValue($value);
				if ($i)
				{
					return $i->getTitle();
				}
			}
		}
		return false;
	}

	/*
	 * @param array $attributeValues
	 * @param array $rootProductAttributeValues
	 * @return array
	 */
	public function updateVariantAttributeValues($attributeValues, $rootProductAttributeValues)
	{
		$updatedValues = array_merge($rootProductAttributeValues, $attributeValues);
		array_walk($updatedValues, array($this, 'updateAttributeValueWithVariant'), $attributeValues);
		return array_unique($updatedValues, SORT_REGULAR);
	}

	/**
	 * @param array $item
	 * @param integer $key
	 * @param array $array
	 */
	protected function updateAttributeValueWithVariant(&$item, $key, $array)
	{
		if (is_array($item))
		{
			foreach($array as $replacement)
			{
				if ($replacement['id'] == $item['id'] && $replacement['valueType'] == $item['valueType'])
				{
					$item = $replacement;
					break;
				}
			}
		}
	}

	/**
	 * @param \Rbs\Catalog\Documents\VariantGroup $variantGroup
	 * @param bool $onlyPublishedProduct
	 * @return array
	 */
	public function buildVariantConfiguration($variantGroup, $onlyPublishedProduct = false)
	{
		$configuration = ['axesValues' => [], 'products' => []];
		if ($variantGroup->getAxesAttributesCount())
		{
			foreach ($variantGroup->getAxesAttributes() as $axisAttribute)
			{
				if ($axisAttribute->getAxis())
				{

					$info = ['id' => $axisAttribute->getId(), 'values' => []];
					$defaultValues = $this->getCollectionValues($axisAttribute);
					$info['defaultValues'] =  ($defaultValues) ? $defaultValues : [];
					$configuration['axesValues'][] = $info;
				}
			}
		}

		/** @var $axesAttributes \Rbs\Catalog\Documents\Attribute[] */
		$axesAttributes = $variantGroup->getAxesAttributes()->toArray();
		$products = $variantGroup->getVariantProducts($onlyPublishedProduct);
		foreach ($products as $product)
		{
			$info = ['id' => $product->getId()];
			$info['values'] = $values = $this->getProductAxesValue($product, $axesAttributes);
			foreach ($values as $value)
			{
				$aId = $value['id'];
				$aVa = $value['value'];
				if (!is_null($aVa))
				{
					foreach ($configuration['axesValues'] as $axisIndex => $data)
					{
						if ($data['id'] == $aId)
						{
							if (!in_array($aVa, $configuration['axesValues'][$axisIndex]['values']))
							{
								$configuration['axesValues'][$axisIndex]['values'][] = $aVa;
							}
							break;
						}
					}
				}
			}
			$configuration['products'][] = $info;
		}
		return $configuration;
	}

	/**
	 * @param \Rbs\Catalog\Documents\Product|null $product
	 * @param \Rbs\Catalog\Documents\Attribute|\Rbs\Catalog\Documents\Attribute[] $axesAttributes
	 * @return array
	 */
	public function getProductAxesValue($product, $axesAttributes)
	{
		$values = [];
		if ($axesAttributes instanceof \Rbs\Catalog\Documents\Attribute)
		{
			$axesAttributes = [$axesAttributes];
		}
		elseif (!is_array($axesAttributes) || count($axesAttributes) === 0)
		{
			return $values;
		}

		foreach ($axesAttributes as $axisAttribute)
		{
			if ($axisAttribute instanceof \Rbs\Catalog\Documents\Attribute && $axisAttribute->getAxis())
			{
				$attributeId = $axisAttribute->getId();
				if ($axisAttribute->getValueType() === \Rbs\Catalog\Documents\Attribute::TYPE_GROUP)
				{
					/** @var $subAttr \Rbs\Catalog\Documents\Attribute[] */
					$subAttr = $axisAttribute->getAttributes()->toArray();
					$values = array_merge($values, $this->getProductAxesValue($product, $subAttr));
				}
				elseif ($axisAttribute->getValueType() === \Rbs\Catalog\Documents\Attribute::TYPE_PROPERTY)
				{
					$property = $axisAttribute->getModelProperty();
					$value = ($product && $property) ? $property->getValue($product) : null;
					if ($value instanceof \Change\Documents\AbstractDocument)
					{
						$value = $value->getId();
					}
					$values[] = ['id' => $attributeId, 'value' => $value];
				}
				else
				{
					$value = null;
					$refLocalization = $product ? $product->getRefLocalization() : null;
					$attrValues = $refLocalization ? $refLocalization->getAttributeValues() : null;
					if (is_array($attrValues))
					{
						foreach ($attrValues as $attrValue)
						{
							if (isset($attrValue['id']) && $attrValue['id'] == $attributeId)
							{
								$value = isset($attrValue['value']) ? $attrValue['value'] : null;
								break;
							}
						}
					}
					$values[] = ['id' => $attributeId, 'value' => $value];
				}
			}
		}
		return $values;
	}

	/**
	 * @param \Rbs\Catalog\Documents\Product $product
	 * @param \Rbs\Catalog\Documents\Attribute|\Rbs\Catalog\Documents\Attribute[] $axesAttributes
	 * @param array $axesValues
	 */
	public function setProductAxesValue($product, $axesAttributes, $axesValues)
	{
		if ($axesAttributes instanceof \Rbs\Catalog\Documents\Attribute)
		{
			$axesAttributes = [$axesAttributes];
		}
		elseif (!is_array($axesAttributes) || count($axesAttributes) === 0)
		{
			return;
		}

		$valuesById = array();

		if (is_array($axesValues))
		{
			foreach ($axesValues as $value)
			{
				if (isset($value['id']) && $value['value'])
				{
					$valuesById[$value['id']] = $value['value'];
				}
			}
		}

		foreach ($axesAttributes as $axisAttribute)
		{
			if ($axisAttribute instanceof \Rbs\Catalog\Documents\Attribute && $axisAttribute->getAxis())
			{
				$attributeId = $axisAttribute->getId();
				$value = isset($valuesById[$attributeId]) ? $valuesById[$attributeId] : null;

				if ($axisAttribute->getValueType() === \Rbs\Catalog\Documents\Attribute::TYPE_GROUP)
				{
					/** @var $subAttr \Rbs\Catalog\Documents\Attribute[] */
					$subAttr = $axisAttribute->getAttributes()->toArray();
					$this->setProductAxesValue($product, $subAttr, $axesValues);
				}
				elseif ($axisAttribute->getValueType() === \Rbs\Catalog\Documents\Attribute::TYPE_PROPERTY)
				{
					$property = $axisAttribute->getModelProperty();
					if ($property)
					{
						if (is_numeric($value) && $property->getType() === \Change\Documents\Property::TYPE_DOCUMENT)
						{
							$value = $this->getDocumentManager()->getDocumentInstance($value);
						}
						$property->setValue($product, $value);
					}
				}
				else
				{
					$attrValues = $product->getRefLocalization()->getAttributeValues();

					if (!is_array($attrValues))
					{
						$attrValues = [];
					}

					$attrKey = null;
					foreach ($attrValues as $key => $attrValue)
					{
						if (isset($attrValue['id']) && $attrValue['id'] == $attributeId)
						{
							$attrKey = $key;
							break;
						}
					}

					if ($attrKey === null)
					{
						$attrValues[] = ['id' => $attributeId, 'valueType' => $axisAttribute->getValueType(), 'value' => $value];
					}
					else
					{
						$attrValues[$attrKey] = ['id' => $attributeId, 'valueType' => $axisAttribute->getValueType(), 'value' => $value];
					}
					$product->getRefLocalization()->setAttributeValues($attrValues);
				}
			}
		}
	}
}