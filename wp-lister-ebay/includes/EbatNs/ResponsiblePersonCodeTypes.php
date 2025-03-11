<?php
/* Generated on 14.02.18 14:28 by globalsync
 * $Id: $
 * $Log: $
 */

require_once 'EbatNs_ComplexType.php';
require_once 'ResponsiblePersonCodeType.php';

/**
 * This type is deprecated.
 *
 **/

class ResponsiblePersonCodeTypes extends EbatNs_ComplexType
{
	/**
	 * @var ResponsiblePersonCodeType[]
	 **/
	protected $Type;

	/**
	 * Class Constructor
	 **/
	function __construct()
	{
		parent::__construct('ResponsiblePersonCodeTypes', 'urn:ebay:apis:eBLBaseComponents');
		if (!isset(self::$_elements[__CLASS__]))
		{
			self::$_elements[__CLASS__] = array_merge(self::$_elements[get_parent_class(__CLASS__)],
				array(
					'Type' =>
						array(
							'required' => false,
							'type' => 'ResponsiblePersonCodeType',
							'nsURI' => 'urn:ebay:apis:eBLBaseComponents',
							'array' => true,
							'cardinality' => '1..*'
						),
				));
		}
		$this->_attributes = array_merge($this->_attributes,
			array(
			));
	}

	/**
	 * @return ResponsiblePersonCodeType
	 **/
	function getType()
	{
		return $this->Type;
	}

	/**
	 * @return void
	 * @param ResponsiblePersonCodeType $value
	 * @param integer $index
	 **/
	function setType($value, $index = null)
	{
		if ($index !== null)
		{
			$this->Type[$index] = $value;
		}
		else
		{
			$this->Type = $value;
		}
	}

	/**
	 * @return void
	 * @param ResponsiblePersonCodeType $value
	 **/
	function addType($value)
	{
		$this->Type[] = $value;
	}

}