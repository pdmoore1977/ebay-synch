<?php
// ***** BEGIN EBATNS PATCH *****
require_once 'EbatNs_ComplexType.php';
require_once 'DocumentsType.php';
require_once 'EconomicOperatorType.php';
require_once 'EnergyEfficiencyType.php';
require_once 'HazmatType.php';
require_once 'ManufacturerType.php';
require_once 'ProductSafetyType.php';
require_once 'ResponsiblePersonsType.php';


class RegulatoryType extends EbatNs_ComplexType
{
	/**
	 * @var DocumentsType
	 **/
	protected $Documents;

	/**
	 * @var EconomicOperatorType
	 */
	protected $EconomicOperator;

	/**
	 * @var EnergyEfficiencyType
	 */
	protected $EnergyEfficiencyLabel;

	/**
	 * @var HazmatType
	 */
	protected $Hazmat;

	/**
	 * @var ManufacturerType
	 */
	protected $Manufacturer;

	/**
	 * @var ProductSafetyType
	 */
	protected $ProductSafety;

	/**
	 * @var float
	 */
	protected $RepairScore;

	/**
	 * @var ResponsiblePersonsType
	 */
	protected $ResponsiblePersons;


	/**
	 * Class Constructor
	 **/
	function __construct()
	{
		parent::__construct('RegulatoryType', 'urn:ebay:apis:eBLBaseComponents');
		if (!isset(self::$_elements[__CLASS__]))
		{
			self::$_elements[__CLASS__] = array_merge(self::$_elements[get_parent_class(__CLASS__)],
				array(
					'Documents' =>
						array(
							'required' => false,
							'type' => 'DocumentsType',
							'nsURI' => 'urn:ebay:apis:eBLBaseComponents',
							'array' => false,
							'cardinality' => '0..1'
						),
					'EconomicOperator' =>
						array(
							'required' => false,
							'type' => 'EconomicOperatorType',
							'nsURI' => 'urn:ebay:apis:eBLBaseComponents',
							'array' => false,
							'cardinality' => '0..1'
						),
					'EnergyEfficiencyLabel' =>
						array(
							'required' => false,
							'type' => 'EnergyEfficiencyLabelType',
							'nsURI' => 'urn:ebay:apis:eBLBaseComponents',
							'array' => false,
							'cardinality' => '0..1'
						),
					'Hazmat' =>
						array(
							'required' => false,
							'type' => 'HazmatType',
							'nsURI' => 'urn:ebay:apis:eBLBaseComponents',
							'array' => false,
							'cardinality' => '0..1'
						),
					'Manufacturer' =>
						array(
							'required' => false,
							'type' => 'ManufacturerType',
							'nsURI' => 'urn:ebay:apis:eBLBaseComponents',
							'array' => false,
							'cardinality' => '0..1'
						),
					'ProductSafety' =>
						array(
							'required' => false,
							'type' => 'ProductSafetyType',
							'nsURI' => 'urn:ebay:apis:eBLBaseComponents',
							'array' => false,
							'cardinality' => '0..1'
						),
					'RepairScore' =>
						array(
							'required' => false,
							'type' => 'float',
							'nsURI' => 'urn:ebay:apis:eBLBaseComponents',
							'array' => false,
							'cardinality' => '0..1'
						),
					'ResponsiblePersons' =>
						array(
							'required' => false,
							'type' => 'ResponsiblePersonsType',
							'nsURI' => 'urn:ebay:apis:eBLBaseComponents',
							'array' => false,
							'cardinality' => '0..1'
						)
				));
		}
	}

	/**
	 * @return DocumentsType
	 **/
	function getDocuments()
	{
		return $this->Documents;
	}

	/**
	 * @return void
	 **/
	function setDocuments($value)
	{
		$this->Documents = $value;
	}

	/**
	 * @return EconomicOperatorType
	 **/
	function getEconomicOperator()
	{
		return $this->EconomicOperator;
	}

	/**
	 * @return void
	 **/
	function setEconomicOperator($value)
	{
		$this->EconomicOperator = $value;
	}

	/**
	 * @return EnergyEfficiencyLabelType
	 **/
	function getEnergyEfficiencyLabel()
	{
		return $this->EnergyEfficiencyLabel;
	}

	/**
	 * @param EnergyEfficiencyType $value
	 * @return void
	 **/
	function setEnergyEfficiencyLabel($value)
	{
		$this->EnergyEfficiencyLabel = $value;
	}

	/**
	 * @return HazmatType
	 **/
	function getHazmat()
	{
		return $this->Hazmat;
	}

	/**
	 * @param HazmatType $value
	 * @return void
	 **/
	function setHazmat($value)
	{
		$this->Hazmat = $value;
	}

	/**
	 * @return ManufacturerType
	 **/
	function getManufacturer()
	{
		return $this->Manufacturer;
	}

	/**
	 * @param ManufacturerType $value
	 * @return void
	 **/
	function setManufacturer($value)
	{
		$this->Manufacturer = $value;
	}

	/**
	 * @return ProductSafetyType
	 **/
	function getProductSafety()
	{
		return $this->ProductSafetyType;
	}

	/**
	 * @param ProductSafetyType $value
	 * @return void
	 **/
	function setProductSafety($value)
	{
		$this->ProductSafety = $value;
	}

	/**
	 * @return float
	 **/
	function getRepairScore()
	{
		return $this->RepairScore;
	}

	/**
	 * @return void
	 **/
	function setRepairScore($value)
	{
		$this->RepairScore = $value;
	}

	/**
	 * @return ResponsiblePersonsType
	 **/
	function getResponsiblePersons()
	{
		return $this->ResponsiblePersons;
	}

	/**
	 * @return void
	 **/
	function setResponsiblePersons($value)
	{
		$this->ResponsiblePersons = $value;
	}

}
// ***** END EBATNS PATCH *****