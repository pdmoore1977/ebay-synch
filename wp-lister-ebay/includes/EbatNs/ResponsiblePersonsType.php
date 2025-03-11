<?php
/* Generated on 14.02.18 14:28 by globalsync
 * $Id: $
 * $Log: $
 */

require_once 'EbatNs_ComplexType.php';
require_once 'ResponsiblePersonType.php';

/**
 * This type is deprecated.
 *
 **/

class ResponsiblePersonsType extends EbatNs_ComplexType
{
	/**
	 * @var string
	 **/
	protected $ResponsiblePerson;

	/**
	 * Class Constructor
	 **/
	function __construct()
	{
		parent::__construct('ResponsiblePersonsType', 'urn:ebay:apis:eBLBaseComponents');
		if (!isset(self::$_elements[__CLASS__]))
		{
			self::$_elements[__CLASS__] = array_merge(self::$_elements[get_parent_class(__CLASS__)],
				array(
					'ResponsiblePerson' =>
						array(
							'required' => false,
							'type' => 'ResponsiblePersonType',
							'nsURI' => 'urn:ebay:apis:eBLBaseComponents',
							'array' => true,
							'cardinality' => '0..*'
						),
				));
		}
		$this->_attributes = array_merge($this->_attributes,
			array(
			));
	}

	/**
	 * @return string
	 **/
	function getResponsiblePerson()
	{
		return $this->ResponsiblePerson;
	}

	/**
	 * @return void
	 * @param ResponsiblePersonType $value
	 * @param integer $index
	 **/
	function setResponsiblePerson($value, $index = null)
	{
		if ($index !== null)
		{
			$this->ResponsiblePerson[$index] = $value;
		}
		else
		{
			$this->ResponsiblePerson = $value;
		}
	}

	/**
	 * @return void
	 * @param ResponsiblePersonType $value
	 **/
	function addResponsiblePerson($value)
	{
		$this->ResponsiblePerson[] = $value;
	}

}