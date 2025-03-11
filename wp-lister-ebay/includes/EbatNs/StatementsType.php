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

class StatementsType extends EbatNs_ComplexType
{
	/**
	 * @var string[]
	 **/
	protected $Statement;

	/**
	 * Class Constructor
	 **/
	function __construct()
	{
		parent::__construct('StatementsType', 'urn:ebay:apis:eBLBaseComponents');
		if (!isset(self::$_elements[__CLASS__]))
		{
			self::$_elements[__CLASS__] = array_merge(self::$_elements[get_parent_class(__CLASS__)],
				array(
					'Statement' =>
						array(
							'required' => false,
							'type' => 'string',
							'nsURI' => 'http://www.w3.org/2001/XMLSchema',
							'array' => true,
							'cardinality' => '0..8'
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
	function getStatement()
	{
		return $this->Statement;
	}

	/**
	 * @return void
	 * @param string $value
	 **/
	function setStatement($value)
	{
		$this->Statement = $value;
	}

	/**
	 * @return void
	 * @param string $value
	 **/
	function addStatement($value)
	{
		$this->Statement[] = $value;
	}

}