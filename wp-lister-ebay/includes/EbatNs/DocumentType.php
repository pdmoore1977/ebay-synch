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

class DocumentType extends EbatNs_ComplexType
{
	/**
	 * @var string
	 **/
	protected $DocumentID;

	/**
	 * Class Constructor
	 **/
	function __construct()
	{
		parent::__construct('DocumentType', 'urn:ebay:apis:eBLBaseComponents');
		if (!isset(self::$_elements[__CLASS__]))
		{
			self::$_elements[__CLASS__] = array_merge(self::$_elements[get_parent_class(__CLASS__)],
				array(
					'DocumentID' =>
						array(
							'required' => false,
							'type' => 'string',
							'nsURI' => 'http://www.w3.org/2001/XMLSchema',
							'array' => false,
							'cardinality' => '0..1'
						)));
		}
		$this->_attributes = array_merge($this->_attributes,
			array(
			));
	}

	/**
	 * @return string
	 **/
	function getDocumentID()
	{
		return $this->DocumentID;
	}

	/**
	 * @return void
	 **/
	function setDocumentID($value)
	{
		$this->DocumentID = $value;
	}

}
