<?php
// ***** BEGIN EBATNS PATCH *****
require_once 'EbatNs_ComplexType.php';
require_once 'ConditionDescriptorType.php';

/**
 * This type is used to display the value of the <b>type</b> attribute of the <b>AddressAttribute</b> field.
 *
 * The only supported value for this attribute is <code>ReferenceNumber</code>, but in the future, other address attributes may be supported. The <code>ReferenceNumber</code> is a unique identifier for a 'Click and Collect' order. Click and Collect orders are only available on the eBay UK and eBay Australia sites.
 *
 **/

class ConditionDescriptorsType extends EbatNs_ComplexType
{
    /**
     * @var ConditionDescriptorType[]
     **/
    protected $ConditionDescriptor;

    /**
     * Class Constructor
     **/
    function __construct()
    {
        parent::__construct('ConditionDescriptorsType', 'urn:ebay:apis:eBLBaseComponents');
        if (!isset(self::$_elements[__CLASS__]))
        {
            self::$_elements[__CLASS__] = array_merge(self::$_elements[get_parent_class(__CLASS__)],
                array(
                    'ConditionDescriptor' =>
                        array(
                            'required' => false,
                            'type' => 'ConditionDescriptorType',
                            'nsURI' => 'urn:ebay:apis:eBLBaseComponents',
                            'array' => true,
                            'cardinality' => '0..*'
                        ),
                    ));
        }
        $this->_attributes = array_merge($this->_attributes,
            array(
            ));
    }

    function addConditionDescriptor( $descriptor ) {
        $this->ConditionDescriptor[] = $descriptor;
    }

    function getConditionDescriptors( $index = null ) {
        if ( is_null( $index ) ) {
            return $this->ConditionDescriptor;
        }

        return $this->ConditionDescriptor[ $index ];
    }

    function getTypeValue() {
        return $this->ConditionDescriptor;
    }

    function getValue() {
        return $this->ConditionDescriptor;
    }

}
// ***** END EBATNS PATCH *****