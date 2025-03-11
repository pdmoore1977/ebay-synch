<?php
// ***** BEGIN EBATNS PATCH *****
require_once 'EbatNs_ComplexType.php';

/**
 * This type is used to display the value of the <b>type</b> attribute of the <b>AddressAttribute</b> field.
 *
 * The only supported value for this attribute is <code>ReferenceNumber</code>, but in the future, other address attributes may be supported. The <code>ReferenceNumber</code> is a unique identifier for a 'Click and Collect' order. Click and Collect orders are only available on the eBay UK and eBay Australia sites.
 *
 **/

class ConditionDescriptorType extends EbatNs_ComplexType
{
    /**
     * @var string
     **/
    protected $AdditionalInfo;

    /**
     * @var string
     **/
    protected $Name;

    /**
     * @var string
     **/
    protected $Value;

    /**
     * Class Constructor
     **/
    function __construct()
    {
        parent::__construct('ConditionDescriptorType', 'urn:ebay:apis:eBLBaseComponents');
        if (!isset(self::$_elements[__CLASS__]))
        {
            self::$_elements[__CLASS__] = array_merge(self::$_elements[get_parent_class(__CLASS__)],
                array(
                    'AdditionalInfo' =>
                        array(
                            'required' => false,
                            'type' => 'string',
                            'nsURI' => 'urn:ebay:apis:eBLBaseComponents',
                            'array' => false,
                            'cardinality' => '0..1'
                        ),
                    'Name' =>
                        array(
                            'required' => false,
                            'type' => 'string',
                            'nsURI' => 'urn:ebay:apis:eBLBaseComponents',
                            'array' => false,
                            'cardinality' => '0..*'
                        ),
                    'Value' =>
                        array(
                            'required' => false,
                            'type' => 'array',
                            'nsURI' => 'urn:ebay:apis:eBLBaseComponents',
                            'array' => false,
                            'cardinality' => '0..*'
                        )
                ));
        }
    }

    /**
     * @return string
     **/
    function getAdditionalInfo()
    {
        return $this->AdditionalInfo;
    }

    /**
     * @return void
     **/
    function setAdditionalInfo($value)
    {
        $this->AdditionalInfo = $value;
    }

    /**
     * @return string
     **/
    function getName()
    {
        return $this->Name;
    }

    /**
     * @return void
     **/
    function setName($value)
    {
        $this->Name = $value;
    }

    /**
     * @return string
     **/
    function getValue()
    {
        return $this->Value;
    }

    /**
     * @return void
     **/
    function setValue($value)
    {
        $this->Value = $value;
    }

}
// ***** END EBATNS PATCH *****