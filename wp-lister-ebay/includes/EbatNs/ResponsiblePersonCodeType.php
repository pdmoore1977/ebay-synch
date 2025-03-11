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
class ResponsiblePersonCodeType extends EbatNs_ComplexType {
	/**
	 * @var string[]
	 **/
	protected $Type;

	/**
	 * Class Constructor
	 **/
	function __construct() {
		parent::__construct( 'ResponsiblePersonType', 'urn:ebay:apis:eBLBaseComponents' );
		if ( ! isset( self::$_elements[ __CLASS__ ] ) ) {
			self::$_elements[ __CLASS__ ] = array_merge( self::$_elements[ get_parent_class( __CLASS__ ) ],
				array(
					'Type' =>
						array(
							'required'    => false,
							'type'        => 'string',
							'nsURI'       => 'http://www.w3.org/2001/XMLSchema',
							'array'       => true,
							'cardinality' => '1..*'
						),
				) );
		}
		$this->_attributes = array_merge( $this->_attributes,
			array() );
	}

	/**
	 * @return string
	 **/
	function getType() {
		return $this->Type;
	}

	/**
	 * @param string $value
	 **@return void
	 */
	function setType( $value ) {
		$this->Type = $value;
	}

	/**
	 * @param string $value
	 **@return void
	 */
	function addType( $value ) {
		$this->Type[] = $value;
	}

}