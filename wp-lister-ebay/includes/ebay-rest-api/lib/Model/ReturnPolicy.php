<?php
/**
 * ReturnPolicy
 *
 * PHP version 5
 *
 * @category Class
 * @package  Swagger\Client
 * @author   Swagger Codegen team
 * @link     https://github.com/swagger-api/swagger-codegen
 */

/**
 * Metadata API
 *
 * The Metadata API has operations that retrieve configuration details pertaining to the different eBay marketplaces. In addition to marketplace information, the API also has operations that get information that helps sellers list items on eBay.
 *
 * OpenAPI spec version: v1.8.0
 * 
 * Generated by: https://github.com/swagger-api/swagger-codegen.git
 * Swagger Codegen version: 3.0.63
 */
/**
 * NOTE: This class is auto generated by the swagger code generator program.
 * https://github.com/swagger-api/swagger-codegen
 * Do not edit the class manually.
 */

namespace Swagger\Client\Model;

use \ArrayAccess;
use \Swagger\Client\ObjectSerializer;

/**
 * ReturnPolicy Class Doc Comment
 *
 * @category Class
 * @package  Swagger\Client
 * @author   Swagger Codegen team
 * @link     https://github.com/swagger-api/swagger-codegen
 */
class ReturnPolicy implements ModelInterface, ArrayAccess
{
    const DISCRIMINATOR = null;

    /**
      * The original name of the model.
      *
      * @var string
      */
    protected static $swaggerModelName = 'ReturnPolicy';

    /**
      * Array of property to type mappings. Used for (de)serialization
      *
      * @var string[]
      */
    protected static $swaggerTypes = [
        'category_id' => 'string',
        'category_tree_id' => 'string',
        'domestic' => '\Swagger\Client\Model\ReturnPolicyDetails',
        'international' => '\Swagger\Client\Model\ReturnPolicyDetails',
        'required' => 'bool'
    ];

    /**
      * Array of property to format mappings. Used for (de)serialization
      *
      * @var string[]
      */
    protected static $swaggerFormats = [
        'category_id' => null,
        'category_tree_id' => null,
        'domestic' => null,
        'international' => null,
        'required' => null
    ];

    /**
     * Array of property to type mappings. Used for (de)serialization
     *
     * @return array
     */
    public static function swaggerTypes()
    {
        return self::$swaggerTypes;
    }

    /**
     * Array of property to format mappings. Used for (de)serialization
     *
     * @return array
     */
    public static function swaggerFormats()
    {
        return self::$swaggerFormats;
    }

    /**
     * Array of attributes where the key is the local name,
     * and the value is the original name
     *
     * @var string[]
     */
    protected static $attributeMap = [
        'category_id' => 'categoryId',
        'category_tree_id' => 'categoryTreeId',
        'domestic' => 'domestic',
        'international' => 'international',
        'required' => 'required'
    ];

    /**
     * Array of attributes to setter functions (for deserialization of responses)
     *
     * @var string[]
     */
    protected static $setters = [
        'category_id' => 'setCategoryId',
        'category_tree_id' => 'setCategoryTreeId',
        'domestic' => 'setDomestic',
        'international' => 'setInternational',
        'required' => 'setRequired'
    ];

    /**
     * Array of attributes to getter functions (for serialization of requests)
     *
     * @var string[]
     */
    protected static $getters = [
        'category_id' => 'getCategoryId',
        'category_tree_id' => 'getCategoryTreeId',
        'domestic' => 'getDomestic',
        'international' => 'getInternational',
        'required' => 'getRequired'
    ];

    /**
     * Array of attributes where the key is the local name,
     * and the value is the original name
     *
     * @return array
     */
    public static function attributeMap()
    {
        return self::$attributeMap;
    }

    /**
     * Array of attributes to setter functions (for deserialization of responses)
     *
     * @return array
     */
    public static function setters()
    {
        return self::$setters;
    }

    /**
     * Array of attributes to getter functions (for serialization of requests)
     *
     * @return array
     */
    public static function getters()
    {
        return self::$getters;
    }

    /**
     * The original name of the model.
     *
     * @return string
     */
    public function getModelName()
    {
        return self::$swaggerModelName;
    }



    /**
     * Associative array for storing property values
     *
     * @var mixed[]
     */
    protected $container = [];

    /**
     * Constructor
     *
     * @param mixed[] $data Associated array of property values
     *                      initializing the model
     */
    public function __construct(array $data = null)
    {
        $this->container['category_id'] = isset($data['category_id']) ? $data['category_id'] : null;
        $this->container['category_tree_id'] = isset($data['category_tree_id']) ? $data['category_tree_id'] : null;
        $this->container['domestic'] = isset($data['domestic']) ? $data['domestic'] : null;
        $this->container['international'] = isset($data['international']) ? $data['international'] : null;
        $this->container['required'] = isset($data['required']) ? $data['required'] : null;
    }

    /**
     * Show all the invalid properties with reasons.
     *
     * @return array invalid properties with reasons
     */
    public function listInvalidProperties()
    {
        $invalidProperties = [];

        return $invalidProperties;
    }

    /**
     * Validate all the properties in the model
     * return true if all passed
     *
     * @return bool True if all properties are valid
     */
    public function valid()
    {
        return count($this->listInvalidProperties()) === 0;
    }


    /**
     * Gets category_id
     *
     * @return string
     */
    public function getCategoryId()
    {
        return $this->container['category_id'];
    }

    /**
     * Sets category_id
     *
     * @param string $category_id The category ID to which the return policies apply.
     *
     * @return $this
     */
    public function setCategoryId($category_id)
    {
        $this->container['category_id'] = $category_id;

        return $this;
    }

    /**
     * Gets category_tree_id
     *
     * @return string
     */
    public function getCategoryTreeId()
    {
        return $this->container['category_tree_id'];
    }

    /**
     * Sets category_tree_id
     *
     * @param string $category_tree_id A value that indicates the root node of the category tree used for the response set. Each marketplace is based on a category tree whose root node is indicated by this unique category ID value. All category policy information returned by this call pertains to the categories included below this root node of the tree.    <br><br>A <i>category tree</i> is a hierarchical framework of eBay categories that begins at the root node of the tree and extends to include all the child nodes in the tree. Each child node in the tree is an eBay category that is represented by a unique <b>categoryId</b> value. Within a category tree, the root node has no parent node and <i>leaf nodes</i> are nodes that have no child nodes.
     *
     * @return $this
     */
    public function setCategoryTreeId($category_tree_id)
    {
        $this->container['category_tree_id'] = $category_tree_id;

        return $this;
    }

    /**
     * Gets domestic
     *
     * @return \Swagger\Client\Model\ReturnPolicyDetails
     */
    public function getDomestic()
    {
        return $this->container['domestic'];
    }

    /**
     * Sets domestic
     *
     * @param \Swagger\Client\Model\ReturnPolicyDetails $domestic domestic
     *
     * @return $this
     */
    public function setDomestic($domestic)
    {
        $this->container['domestic'] = $domestic;

        return $this;
    }

    /**
     * Gets international
     *
     * @return \Swagger\Client\Model\ReturnPolicyDetails
     */
    public function getInternational()
    {
        return $this->container['international'];
    }

    /**
     * Sets international
     *
     * @param \Swagger\Client\Model\ReturnPolicyDetails $international international
     *
     * @return $this
     */
    public function setInternational($international)
    {
        $this->container['international'] = $international;

        return $this;
    }

    /**
     * Gets required
     *
     * @return bool
     */
    public function getRequired()
    {
        return $this->container['required'];
    }

    /**
     * Sets required
     *
     * @param bool $required If set to <code>true</code>, this flag indicates that you must specify a return policy for items listed in the associated category.  <br><br>Note that not accepting returns (setting <b>returnsAcceptedEnabled</b> to <code>false</code>) is a valid return policy.
     *
     * @return $this
     */
    public function setRequired($required)
    {
        $this->container['required'] = $required;

        return $this;
    }
    /**
     * Returns true if offset exists. False otherwise.
     *
     * @param integer $offset Offset
     *
     * @return boolean
     */
    #[\ReturnTypeWillChange]
    public function offsetExists($offset)
    {
        return isset($this->container[$offset]);
    }

    /**
     * Gets offset.
     *
     * @param integer $offset Offset
     *
     * @return mixed
     */
    #[\ReturnTypeWillChange]
    public function offsetGet($offset)
    {
        return isset($this->container[$offset]) ? $this->container[$offset] : null;
    }

    /**
     * Sets value based on offset.
     *
     * @param integer $offset Offset
     * @param mixed   $value  Value to be set
     *
     * @return void
     */
    #[\ReturnTypeWillChange]
    public function offsetSet($offset, $value)
    {
        if (is_null($offset)) {
            $this->container[] = $value;
        } else {
            $this->container[$offset] = $value;
        }
    }

    /**
     * Unsets offset.
     *
     * @param integer $offset Offset
     *
     * @return void
     */
    #[\ReturnTypeWillChange]
    public function offsetUnset($offset)
    {
        unset($this->container[$offset]);
    }

    /**
     * Gets the string presentation of the object
     *
     * @return string
     */
    public function __toString()
    {
        if (defined('JSON_PRETTY_PRINT')) { // use JSON pretty print
            return json_encode(
                ObjectSerializer::sanitizeForSerialization($this),
                JSON_PRETTY_PRINT
            );
        }

        return json_encode(ObjectSerializer::sanitizeForSerialization($this));
    }
}
