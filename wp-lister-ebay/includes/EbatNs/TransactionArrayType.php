<?php
/* Generated on 14.02.18 14:28 by globalsync
 * $Id: $
 * $Log: $
 */

require_once 'EbatNs_ComplexType.php';
require_once 'EbayTransactionType.php';

/**
  * Type defining the <b>TransactionArray</b> container, which contains an
  * array of <b>Transaction</b> containers. Each <b>Transaction</b>
  * container consists of detailed information on one order line item.
  * 
 **/

class TransactionArrayType extends EbatNs_ComplexType
{
	/**
	* @var EbayTransactionType
	**/
	protected $Transaction;


	/**
	 * Class Constructor 
	 **/
	function __construct()
	{
		parent::__construct('TransactionArrayType', 'urn:ebay:apis:eBLBaseComponents');
		if (!isset(self::$_elements[__CLASS__]))
		{
			self::$_elements[__CLASS__] = array_merge(self::$_elements[get_parent_class(__CLASS__)],
			array(
				'Transaction' =>
				array(
					'required' => false,
					'type' => 'TransactionType',
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
	 * @param integer $index
	 **@return EbayTransactionType
	 */
	function getTransaction($index = null)
	{
		if ($index !== null)
		{
			return $this->Transaction[$index];
		}
		else
		{
			return $this->Transaction;
		}
	}

	/**
	 * @param EbayTransactionType $value
	 * @param integer $index
	 **@return void
	 */
	function setTransaction($value, $index = null)
	{
		if ($index !== null)
		{
			$this->Transaction[$index] = $value;
		}
		else
		{
			$this->Transaction= $value;
		}
	}

	/**
	 * @param EbayTransactionType $value
	 **@return void
	 */
	function addTransaction($value)
	{
		$this->Transaction[] = $value;
	}

}
?>
