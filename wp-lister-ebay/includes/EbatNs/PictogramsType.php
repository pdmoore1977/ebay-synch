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

class PictogramsType extends EbatNs_ComplexType
{
	/**
	 * @var string[]
	 **/
	protected $Pictogram;

	/**
	 * Class Constructor
	 **/
	function __construct()
	{
		parent::__construct('PictogramsType', 'urn:ebay:apis:eBLBaseComponents');
		if (!isset(self::$_elements[__CLASS__]))
		{
			self::$_elements[__CLASS__] = array_merge(self::$_elements[get_parent_class(__CLASS__)],
				array(
					'Pictogram' =>
						array(
							'required' => false,
							'type' => 'string',
							'nsURI' => 'http://www.w3.org/2001/XMLSchema',
							'array' => true,
							'cardinality' => '0..4'
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
	function getPictogram()
	{
		return $this->Pictogram;
	}

	/**
	 * @return void
	 * @param string $value
	 **/
	function setPictogram($value)
	{
		$this->Pictogram = $value;
	}

	/**
	 * @return void
	 * @param string $value
	 **/
	function addPictogram($value)
	{
		$this->Pictogram[] = $value;
	}

}