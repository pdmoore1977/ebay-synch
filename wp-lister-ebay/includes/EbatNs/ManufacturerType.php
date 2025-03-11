<?php
/* Generated on 14.02.18 14:28 by globalsync
 * $Id: $
 * $Log: $
 */

require_once 'EbatNs_ComplexType.php';
require_once 'CountryCodeType.php';

/**
 * This type is deprecated.
 *
 **/

class ManufacturerType extends EbatNs_ComplexType
{
	/**
	 * @var string
	 **/
	protected $CityName;

	/**
	 * @var string
	 **/
	protected $CompanyName;

	/**
	 * @var CountryCodeType
	 **/
	protected $Country;

	/**
	 * @var string
	 **/
	protected $Email;

	/**
	 * @var string
	 **/
	protected $Phone;

	/**
	 * @var string
	 **/
	protected $PostalCode;

	/**
	 * @var string
	 **/
	protected $StateOrProvince;

	/**
	 * @var string
	 **/
	protected $Street1;

	/**
	 * @var string
	 **/
	protected $Street2;

	/**
	 * Class Constructor
	 **/
	function __construct()
	{
		parent::__construct('EconomicsOperatorType', 'urn:ebay:apis:eBLBaseComponents');
		if (!isset(self::$_elements[__CLASS__]))
		{
			self::$_elements[__CLASS__] = array_merge(self::$_elements[get_parent_class(__CLASS__)],
				array(
					'CityName' =>
						array(
							'required' => false,
							'type' => 'string',
							'nsURI' => 'http://www.w3.org/2001/XMLSchema',
							'array' => false,
							'cardinality' => '0..1'
						),
					'CompanyName' =>
						array(
							'required' => false,
							'type' => 'string',
							'nsURI' => 'http://www.w3.org/2001/XMLSchema',
							'array' => false,
							'cardinality' => '0..1'
						),
					'Country' =>
						array(
							'required' => false,
							'type' => 'CountryCodeType',
							'nsURI' => 'urn:ebay:apis:eBLBaseComponents',
							'array' => false,
							'cardinality' => '0..1'
						),
					'Email' =>
						array(
							'required' => false,
							'type' => 'string',
							'nsURI' => 'http://www.w3.org/2001/XMLSchema',
							'array' => false,
							'cardinality' => '0..1'
						),
					'Phone' =>
						array(
							'required' => false,
							'type' => 'string',
							'nsURI' => 'http://www.w3.org/2001/XMLSchema',
							'array' => false,
							'cardinality' => '0..1'
						),
					'PostalCode' =>
						array(
							'required' => false,
							'type' => 'string',
							'nsURI' => 'http://www.w3.org/2001/XMLSchema',
							'array' => false,
							'cardinality' => '0..1'
						),
					'StateOrProvince' =>
						array(
							'required' => false,
							'type' => 'string',
							'nsURI' => 'http://www.w3.org/2001/XMLSchema',
							'array' => false,
							'cardinality' => '0..1'
						),
					'Street1' =>
						array(
							'required' => false,
							'type' => 'string',
							'nsURI' => 'http://www.w3.org/2001/XMLSchema',
							'array' => false,
							'cardinality' => '0..1'
						),
					'Street2' =>
						array(
							'required' => false,
							'type' => 'string',
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
	function getCityName()
	{
		return $this->CityName;
	}

	/**
	 * @return self
	 * @param string $value
	 **/
	function setCityName($value)
	{
		$this->CityName= $value;
		return $this;
	}

	/**
	 * @return string
	 **/
	function getCompanyName()
	{
		return $this->CompanyName;
	}

	/**
	 * @return self
	 * @param string $value
	 **/
	function setCompanyName($value)
	{
		$this->CompanyName= $value;
		return $this;
	}

	/**
	 * @return CountryCodeType
	 **/
	function getCountry()
	{
		return $this->Country;
	}

	/**
	 * @param string $value
	 **/
	function setCountry($value)
	{
		$this->Country = $value;
		return $this;
	}

	/**
	 * @return string
	 **/
	function getEmail()
	{
		return $this->Email;
	}

	/**
	 * @param string $value
	 **/
	function setEmail($value)
	{
		$this->Email = $value;
		return $this;
	}

	/**
	 * @return string
	 **/
	function getPhone()
	{
		return $this->Phone;
	}

	/**
	 * @param string $value
	 **/
	function setPhone($value)
	{
		$this->Phone = $value;
		return $this;
	}

	/**
	 * @return string
	 **/
	function getPostalCode()
	{
		return $this->PostalCode;
	}

	/**
	 * @param string $value
	 **/
	function setPostalCode($value)
	{
		$this->PostalCode = $value;
		return $this;
	}

	/**
	 * @return string
	 **/
	function getStateOrProvince()
	{
		return $this->StateOrProvince;
	}

	/**
	 * @param string $value
	 **/
	function setStateOrProvince($value)
	{
		$this->StateOrProvince = $value;
		return $this;
	}

	/**
	 * @return string
	 **/
	function getStreet1()
	{
		return $this->Street1;
	}

	/**
	 * @param string $value
	 **/
	function setStreet1($value)
	{
		$this->Street1 = $value;
		return $this;
	}

	/**
	 * @return string
	 **/
	function getStreet2()
	{
		return $this->Street2;
	}

	/**
	 * @param string $value
	 **/
	function setStreet2($value)
	{
		$this->Street2 = $value;
		return $this;
	}

}