<?php
/* Generated on 14.02.18 14:28 by globalsync
 * $Id: $
 * $Log: $
 */

require_once 'EbatNs_ComplexType.php';
require_once 'DocumentType.php';

/**
 * This type is deprecated.
 *
 **/

class DocumentsType extends EbatNs_ComplexType
{
	/**
	 * @var DocumentType[]
	 **/
	protected $Document;


	/**
	 * Class Constructor
	 **/
	function __construct()
	{
		parent::__construct('DocumentsType', 'urn:ebay:apis:eBLBaseComponents');
		if (!isset(self::$_elements[__CLASS__]))
		{
			self::$_elements[__CLASS__] = array_merge(self::$_elements[get_parent_class(__CLASS__)],
				array(
					'Document' =>
						array(
							'required' => false,
							'type' => 'DocumentType',
							'nsURI' => 'urn:ebay:apis:eBLBaseComponents',
							'array' => true,
							'cardinality' => '0..*'
						)));
		}
		$this->_attributes = array_merge($this->_attributes,
			array(
			));
	}

	/**
	 * @return DocumentType
	 * @param integer $index
	 **/
	function getDocument($index = null)
	{
		if ($index !== null)
		{
			return $this->Document[$index];
		}
		else
		{
			return $this->Document;
		}
	}

	/**
	 * @return void
	 * @param DocumentType $value
	 * @param integer $index
	 **/
	function setDocument($value, $index = null)
	{
		if ($index !== null)
		{
			$this->Document[$index] = $value;
		}
		else
		{
			$this->Document= $value;
		}
	}

	/**
	 * @return void
	 * @param DocumentType $value
	 **/
	function addDocument($value)
	{
		$this->Document[] = $value;
	}

}