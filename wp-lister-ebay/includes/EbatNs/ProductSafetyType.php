<?php
/* Generated on 14.02.18 14:28 by globalsync
 * $Id: $
 * $Log: $
 */

require_once 'EbatNs_ComplexType.php';
require_once 'PictogramsType.php';
require_once 'StatementsType.php';

/**
 * This type is deprecated.
 *
 **/

class ProductSafetyType extends EbatNs_ComplexType
{
	/**
	 * @var string
	 **/
	protected $Component;

	/**
	 * @var PictogramsType
	 **/
	protected $Pictograms;

	/**
	 * @var StatementsType
	 **/
	protected $Statements;

	/**
	 * Class Constructor
	 **/
	function __construct()
	{
		parent::__construct('ProductSafetyType', 'urn:ebay:apis:eBLBaseComponents');
		if (!isset(self::$_elements[__CLASS__]))
		{
			self::$_elements[__CLASS__] = array_merge(self::$_elements[get_parent_class(__CLASS__)],
				array(
					'Component' =>
						array(
							'required' => false,
							'type' => 'string',
							'nsURI' => 'http://www.w3.org/2001/XMLSchema',
							'array' => false,
							'cardinality' => '0..1'
						),
					'Pictograms' =>
						array(
							'required' => false,
							'type' => 'PictogramsType',
							'nsURI' => 'urn:ebay:apis:eBLBaseComponents',
							'array' => false,
							'cardinality' => '0..1'
						),
					'Statements' =>
						array(
							'required' => false,
							'type' => 'StatementsType',
							'nsURI' => 'urn:ebay:apis:eBLBaseComponents',
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
	function getComponent()
	{
		return $this->Component;
	}

	/**
	 * @return void
	 * @param string $value
	 **/
	function setComponent($value)
	{
		$this->Component = $value;
	}

	/**
	 * @return PictogramsType
	 **/
	function getPictograms()
	{
		return $this->Pictograms;
	}

	/**
	 * @return void
	 * @param PictogramsType $value
	 **/
	function setPictograms($value)
	{
		$this->Pictograms = $value;
	}

	/**
	 * @return StatementsType
	 **/
	function getStatements()
	{
		return $this->Statements;
	}

	/**
	 * @return void
	 * @param string $value
	 **/
	function setStatements($value)
	{
		$this->Statements = $value;
	}

}