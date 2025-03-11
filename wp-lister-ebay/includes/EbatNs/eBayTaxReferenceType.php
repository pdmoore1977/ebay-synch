<?php
// ***** BEGIN EBATNS PATCH *****
/* Generated on 14.02.18 14:28 by globalsync
 * $Id: $
 * $Log: $
 */

require_once 'EbatNs_ComplexType.php';

/**
 * This type is used to display the value of the <b>type</b> attribute of the <b>AddressAttribute</b> field.
 *
 * The only supported value for this attribute is <code>ReferenceNumber</code>, but in the future, other address attributes may be supported. The <code>ReferenceNumber</code> is a unique identifier for a 'Click and Collect' order. Click and Collect orders are only available on the eBay UK and eBay Australia sites.
 *
 **/

class eBayTaxReferenceType extends EbatNs_ComplexType
{

    /**
     * Class Constructor
     **/
    function __construct()
    {
        parent::__construct('eBayTaxReferenceType', 'urn:ebay:apis:eBLBaseComponents');
        if (!isset(self::$_elements[__CLASS__]))
        {
            self::$_elements[__CLASS__] = array_merge(self::$_elements[get_parent_class(__CLASS__)],
                array(
                ));
        }
        $this->_attributes = array_merge($this->_attributes,
            array(
                'type' =>
                    array(
                        'name' => ' name',
                        'type' => 'string',
                        'use' => 'optional'
                    )));
    }



}
// ***** END EBATNS PATCH *****