<?php
/* Generated on 14.02.18 14:28 by globalsync
 * $Id: $
 * $Log: $
 */

require_once 'EbatNs_ComplexType.php';

/**
 * This type is deprecated.
 *
 **/

class EnergyEfficiencyType extends EbatNs_ComplexType
{
	/**
	 * @var string
	 **/
	protected $ImageDescription;

	/**
	 * @var string
	 **/
	protected $ImageURL;

	/**
	 * @var string
	 **/
	protected $ProductInformationsheet;

	/**
	 * Class Constructor
	 **/
	function __construct()
	{
		parent::__construct('EnergyEfficiencyType', 'urn:ebay:apis:eBLBaseComponents');
		if (!isset(self::$_elements[__CLASS__]))
		{
			self::$_elements[__CLASS__] = array_merge(self::$_elements[get_parent_class(__CLASS__)],
				array(
					'ImageDescription' =>
						array(
							'required' => false,
							'type' => 'string',
							'nsURI' => 'http://www.w3.org/2001/XMLSchema',
							'array' => false,
							'cardinality' => '0..1'
						),
					'ImageURL' =>
						array(
							'required' => false,
							'type' => 'anyURI',
							'nsURI' => 'http://www.w3.org/2001/XMLSchema',
							'array' => false,
							'cardinality' => '0..1'
						),
					'ProductInformationsheet' =>
						array(
							'required' => false,
							'type' => 'anyURI',
							'nsURI' => 'http://www.w3.org/2001/XMLSchema',
							'array' => false,
							'cardinality' => '0..1'
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
	function getImageDescription()
	{
		return $this->ImageDescription;
	}

	/**
	 * @return void
	 * @param string $value
	 **/
	function setImageDescription($value)
	{
		$this->ImageDescription= $value;
	}

	/**
	 * @return string
	 **/
	function getImageURL()
	{
		return $this->ImageURL;
	}

	/**
	 * @return void
	 * @param string $value
	 **/
	function setImageURL($value)
	{
		$this->ImageURL= $value;
	}


	/**
	 * @return string
	 **/
	function getProductInformationsheet()
	{
		return $this->ProductInformationsheet;
	}

	/**
	 * @return void
	 * @param string $value
	 **/
	function setProductInformationsheet($value)
	{
		$this->ProductInformationsheet = $value;
	}

}