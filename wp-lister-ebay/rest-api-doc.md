# WP-Lister for eBay REST API Documentation

Welcome to the WP-Lister for eBay REST API documentation. This API allows developers to interact with WP-Lister for eBay plugin programmatically, enabling integration with third-party applications and services.

## Base URL

The base URL for accessing the WP-Lister for eBay API is:

```
https://your-wordpress-site.com/wp-json/wple/v1/
```

## Authentication

To authenticate requests to the WP-Lister for eBay API, you should use one of the accepted authentication methods supported by the WordPress REST API. For detailed instructions on authentication methods supported by the WordPress REST API, please refer to the [official WordPress REST API documentation](https://developer.wordpress.org/rest-api/using-the-rest-api/authentication/).

## Endpoints

## 1. Listings

### 1.1 GetListings

- **URL**: `/listings`
- **Method**: `GET`
- **Description**: Retrieves a list of eBay listings managed by WP-Lister.
- **Parameters**:
  - `per_page` (optional): The number of listings to retrieve per page. Default is 10.
  - `page` (optional): The page number of the listings to retrieve.
  - `listing_status` (optional): Filter results by listing status.
  - `profile_id` (optional): Filter results by Profile ID.
  - `account_id` (optional): Filter results by Account ID.
  - `search` (optional): Search for listings using keywords.

**Request**: `GET /listings?per_page=10&listing_status=prepared`

**Response**: An array of listing objects.

```
[
  {
    "id": "59",
    "ebay_id": null,
    "sku": "v-neck-t-shirt-blue",
    "title": "V-Neck T-Shirt - Blue",
    "subtitle": "",
    "price": 99.9,
    "quantity": 10,
    "final_quantity": 5,
    "listing_type": "FixedPriceItem",
    "listing_duration": "GTC",
    "condition": 1000,
    "condition_description": "",
    "epid": "",
    "upc": "",
    "ean": "",
    "isbn": "",
    "mpn": "",
    "brand": "FFP Trading",
    "buyitnow_price": 0,
    "reserve_price": 0,
    "primary_image": "https://example.com/wp-content/uploads/imported/8/vneck11-1.jpg",
    "images": [
        "https://example.com/wp-content/uploads/imported/8/vneck11-1.jpg",
        "https://example.com/wp-content/uploads/imported/8/vneck11-2.jpg",
        "https://example.com/wp-content/uploads/imported/8/vneck11-3.jpg"
    ],
    "global_shipping": 0,
    "ebay_plus": 0,
    "ebay_url": "https://www.ebay.com.au/itm/V-Neck-Shirt/222999999038",
    "status": "changed",
    "locked": 0,
    "variations": [{
      "v-neck-shirt-blue" : {
        "sku": "v-neck-shirt-blue",
        "price": 99,
        "stock": 30,
        "sold": 0,
        "variation_attributes": {
          "Color": "Blue"
        }
      },
      "v-neck-shirt-red" : {
        "sku": "v-neck-shirt-red",
        "price": 99,
        "stock": 30,
        "sold": 0,
        "variation_attributes": {
          "Color": "Red"
        }
      }
    }],
    "wc_product_id": 501,
    "wc_parent_id": 0,
    "profile_id": 1,
    "account_id": 1
  },
  {
    "id": "60",
    "ebay_id": null,
    "sku": "round-neck-t-shirt",
    "title": "Round Neck T-Shirt",
    "subtitle": "",
    "price": 99.9,
    "quantity": 10,
    "final_quantity": 5,
    "listing_type": "FixedPriceItem",
    "listing_duration": "GTC",
    "condition": 1000,
    "condition_description": "",
    "epid": "",
    "upc": "",
    "ean": "",
    "isbn": "",
    "mpn": "",
    "brand": "FFP Trading",
    "buyitnow_price": 0,
    "reserve_price": 0,
    "primary_image": "https://example.com/wp-content/uploads/imported/8/rneck11-1.jpg",
    "images": [
        "https://example.com/wp-content/uploads/imported/8/rneck11-1.jpg",
        "https://example.com/wp-content/uploads/imported/8/rneck11-2.jpg",
        "https://example.com/wp-content/uploads/imported/8/rneck11-3.jpg"
    ],
    "global_shipping": 0,
    "ebay_plus": 0,
    "ebay_url": "https://www.ebay.com.au/itm/Round-Neck-Shirt/222999999038",
    "status": "changed",
    "locked": 0,
    "variations": [{
      "round-neck-shirt-blue" : {
        "sku": "round-neck-shirt-blue",
        "price": 99,
        "stock": 30,
        "sold": 0,
        "variation_attributes": {
          "Color": "Blue"
        }
      },
      "round-neck-shirt-red" : {
        "sku": "round-neck-shirt-red",
        "price": 99,
        "stock": 30,
        "sold": 0,
        "variation_attributes": {
          "Color": "Red"
        }
      }
    }],
    "wc_product_id": 502,
    "wc_parent_id": 0,
    "profile_id": 1,
    "account_id": 1
  }
]
```


### 1.2 GetListing

- **URL**: `/listings/{listing_id}`
- **Method**: `GET`
- **Description**: Retrieves details of a specific eBay listing.
- **Parameters**:
  - `listing_id`: The ID of the listing to retrieve details for.

**Request**: `GET /listings/60`

**Response**: A single listing object.

```
{
  "id": "59",
  "ebay_id": null,
  "sku": "v-neck-t-shirt-blue",
  "title": "V-Neck T-Shirt - Blue",
  "subtitle": "",
  "price": 99.9,
  "quantity": 10,
  "final_quantity": 5,
  "listing_type": "FixedPriceItem",
  "listing_duration": "GTC",
  "condition": 1000,
  "condition_description": "",
  "epid": "",
  "upc": "",
  "ean": "",
  "isbn": "",
  "mpn": "",
  "brand": "FFP Trading",
  "buyitnow_price": 0,
  "reserve_price": 0,
  "primary_image": "https://example.com/wp-content/uploads/imported/8/vneck11-1.jpg",
  "images": [
      "https://example.com/wp-content/uploads/imported/8/vneck11-1.jpg",
      "https://example.com/wp-content/uploads/imported/8/vneck11-2.jpg",
      "https://example.com/wp-content/uploads/imported/8/vneck11-3.jpg"
  ],
  "global_shipping": 0,
  "ebay_plus": 0,
  "ebay_url": "https://www.ebay.com.au/itm/V-Neck-Shirt/222999999038",
  "status": "changed",
  "locked": 0,
  "variations": [{
    "v-neck-shirt-blue" : {
      "sku": "v-neck-shirt-blue",
      "price": 99,
      "stock": 30,
      "sold": 0,
      "variation_attributes": {
        "Color": "Blue"
      }
    },
    "v-neck-shirt-red" : {
      "sku": "v-neck-shirt-red",
      "price": 99,
      "stock": 30,
      "sold": 0,
      "variation_attributes": {
        "Color": "Red"
      }
    }
    ...
  }],
  "wc_product_id": 501,
  "wc_parent_id": 0,
  "profile_id": 1,
  "account_id": 1
}
```

### 1.3 UpdateListing

This method allows you to update product-level properties for your listings. Updating a listing marks it as `Changed` so you will
have to call **ReviseListing** to push the changes to eBay.

- **URL**: `/listings/{listing_id}`
- **Method**: `POST`
- **Parameters**:
  - `title`
  - `subtitle` 
  - `price`
  - `listing_type`
  - `listing_duration` 
  - `condition`
  - `condition_description` 
  - `epid` 
  - `upc` 
  - `ean` 
  - `isbn` 
  - `mpn`
  - `brand`
  - `buyitnow_price` 
  - `reserve_price`
  - `gallery_image_url` 
  - `global_shipping` 
  - `ebay_plus` 
  - `best_offer`
  - `auto_accept_price`
  - `minimum_offer_price` 
  - `immediate_payment` 
  - `payment_policy_id` 
  - `return_policy_id`
  - `payment_instructions`
  - `primary_ebay_category_id`
  - `secondary_ebay_category_id` 
  - `primary_store_category_id`
  - `secondary_store_category_id`

**Response**

```
{
  "success": true,
  "errors": [],
  "message": "",
  "listing": {
    "id": "77",
    "ebay_id": "110554918989",
    "sku": "woo-single",
    "title": "TEST Single",
    "subtitle": "",
    "price": "100.91",
    "quantity": "0",
    "final_quantity": 0,
    "listing_type": "FixedPriceItem",
    "listing_duration": "GTC",
    "condition": "1000",
    "condition_description": "",
    "epid": "",
    "upc": "",
    "ean": "",
    "isbn": "",
    "mpn": "",
    "brand": "",
    "buyitnow_price": 0,
    "reserve_price": 0,
    "primary_image": "https://example.com/wp-content/uploads/2023/11/single-1.jpg",
    "images": [],
    "global_shipping": "0",
    "ebay_plus": "0",
    "ebay_url": "https://www.sandbox.ebay.com/itm/TEST-Single-/110554918989",
    "date_created": "2024-05-01 01:54:29",
    "date_published": "2024-05-01 01:01:33",
    "date_finished": null,
    "end_date": "2024-05-31 01:01:33",
    "relist_date": null,
    "status": "changed",
    "locked": 0,
    "variations": null,
    "wc_product_id": "3419",
    "wc_parent_id": "0",
    "profile_id": "2",
    "account_id": "12"
  }
}
```

### 1.4 PrepareListing

- **URL**: `/listing`
- **Method**: `POST`
- **Description**: Prepares an existing product to be listed on eBay.
- **Parameters**:
- `product_id`: The ID of the product you want to list.
- `profile_id`: The ID of the profile to assign to the listing.

**Request**
```
curl -X POST https://example.com/wp-json/wple/v1/listing \
-u user:password \
-H "Content-Type: application/x-www-form-urlencoded" \
-d "product_id=9025&profile_id=1"
```

**Response**
```
{
  "success": true,
  "errors": [],
  "message": "Successfully prepared product for listing",
  "listing": {
    "id": "77",
    "ebay_id": null,
    "sku": "woo-single",
    "title": "Test Single",
    "subtitle": "",
    "price": 2.58,
    "quantity": "0",
    "final_quantity": 0,
    "listing_type": "FixedPriceItem",
    "listing_duration": "GTC",
    "condition": "",
    "condition_description": "",
    "epid": "",
    "upc": "",
    "ean": "",
    "isbn": "",
    "mpn": "",
    "brand": "",
    "buyitnow_price": 0,
    "reserve_price": 0,
    "primary_image": "https://example.com/wp-content/uploads/2023/11/single.jpg",
    "images": [],
    "global_shipping": "0",
    "ebay_plus": "",
    "ebay_url": null,
    "date_created": "2024-03-01 00:36:52",
    "date_published": null,
    "date_finished": null,
    "end_date": null,
    "relist_date": null,
    "status": "prepared",
    "locked": 0,
    "variations": null,
    "wc_product_id": "3419",
    "wc_parent_id": "0",
    "profile_id": "2",
    "account_id": "12"
  }
}
```

### 1.5 VerifyListing

- **URL**: `/listing/verify`
- **Method**: `POST`
- **Description**: Verify a listing to check for listing errors.
- **Parameters**:
  - `id`: The ID of the listing to verify.

**Request**
```
curl -X POST https://example.com/wp-json/wple/v1/listing/verify \
-u user:password \
-H "Content-Type: application/x-www-form-urlencoded" \
-d "id=76"
```

**Response**
```
{
  "success": true,
  "errors": [
    {
      "severity": "Warning",
      "message": "Seller has opted into business policies. Please use policy IDs rather than legacy fields for Shipping, Payments or Returns or new policies may be automatically created seller&#039;s behalf."
    }
  ],
  "message": "Listing #77 was verified successfully",
  "listing": {
    "id": "77",
    "ebay_id": null,
    "sku": "woo-single",
    "title": "TEST Single",
    "subtitle": "",
    "price": 2.58,
    "quantity": "0",
    "final_quantity": 0,
    "listing_type": "FixedPriceItem",
    "listing_duration": "GTC",
    "condition": "1000",
    "condition_description": "",
    "epid": "",
    "upc": "",
    "ean": "",
    "isbn": "",
    "mpn": "",
    "brand": "",
    "buyitnow_price": 0,
    "reserve_price": 0,
    "primary_image": "https://example.com/wp-content/uploads/2023/11/single-1.jpg",
    "images": [],
    "global_shipping": "0",
    "ebay_plus": "0",
    "ebay_url": null,
    "date_created": "2024-03-01 00:36:52",
    "date_published": null,
    "date_finished": null,
    "end_date": null,
    "relist_date": null,
    "status": "verified",
    "locked": 0,
    "variations": null,
    "wc_product_id": "3419",
    "wc_parent_id": "0",
    "profile_id": "2",
    "account_id": "12"
  }
}
```

### 1.6 PublishListing

- **URL**: `/listing/publish`
- **Method**: `POST`
- **Description**: Publishes a listing to eBay.
- **Parameters**:
  - `id`: The ID of the listing to publish.

**Request**
```
curl -X POST https://example.com/wp-json/wple/v1/listing/publish \
-u user:password \
-H "Content-Type: application/x-www-form-urlencoded" \
-d "id=76"

```
**Response**
```
{
  "success": true,
  "errors": [],
  "message": "Listing #77 was published successfully",
  "listing": {
    "id": "77",
    "ebay_id": "110554918989",
    "sku": "woo-single",
    "title": "TEST Single",
    "subtitle": "",
    "price": 2.58,
    "quantity": "0",
    "final_quantity": 0,
    "listing_type": "FixedPriceItem",
    "listing_duration": "GTC",
    "condition": "1000",
    "condition_description": "",
    "epid": "",
    "upc": "",
    "ean": "",
    "isbn": "",
    "mpn": "",
    "brand": "",
    "buyitnow_price": 0,
    "reserve_price": 0,
    "primary_image": "https://example.com/wp-content/uploads/2023/11/single-1.jpg",
    "images": [],
    "global_shipping": "0",
    "ebay_plus": "0",
    "ebay_url": "https://www.sandbox.ebay.com/itm/TEST-Single-/110554918989",
    "date_created": "2024-03-01 00:36:52",
    "date_published": "2024-03-01 01:01:33",
    "date_finished": null,
    "end_date": "2024-03-31 01:01:33",
    "relist_date": null,
    "status": "published",
    "locked": 0,
    "variations": null,
    "wc_product_id": "3419",
    "wc_parent_id": "0",
    "profile_id": "2",
    "account_id": "12"
  }
}
```

### 1.7 ReviseListing

- **URL**: `/listing/revise`
- **Method**: `POST`
- **Description**: Revises a listing on eBay.
- **Parameters**:
  - `id`: The ID of the listing to revise.

**Request**
```
curl -X POST https://example.com/wp-json/wple/v1/listing/revise \
-u user:password \
-H "Content-Type: application/x-www-form-urlencoded" \
-d "id=76"
```

**Response**
```
{
  "success": true,
  "errors": [
    {
      "severity": "Warning",
      "message": "If your item sells for $10,000 or more, you agree to accept payment via Escrow.com and to allow eBay to amend your return policy to offer 7-day returns. Learn more at https://pages.ebay.com/escrow"
    }
  ],
  "message": "Listing #76 was revised successfully",
  "item": {
    "id": "76",
    "ebay_id": "110599996666",
    "auction_title": "TEST Pennant",
    "auction_type": "FixedPriceItem",
    "listing_duration": "GTC",
    "date_created": "2024-04-26 06:18:02",
    "date_published": "2024-04-26 06:43:42",
    "date_finished": null,
    "end_date": "2024-05-26 06:43:42",
    "relist_date": null,
    "price": "14.25",
    "quantity": "0",
    "quantity_sold": "0",
    "status": "published",
    "locked": "0",
    "variations": null,
    "ViewItemURL": "https://www.ebay.com/itm/TEST-Pennant-/110599996666",
    "GalleryURL": "https://example.com/wp-content/uploads/2023/11/pennant-1.jpg",
    "post_id": "3429",
    "parent_id": "0",
    "profile_id": "2",
    "template": "/uploads/wp-lister/templates/new-listing-template",
    "account_id": "12",
    "site_id": "0",
    "sku": "wp-pennant",
    "_ebay_start_price": "",
    "_regular_price": "11.05",
    "_sale_price": "",
    "_msrp_price": "",
    "thumb": "https://example.com/wp-content/uploads/2023/11/pennant-1-150x150.jpg"
  }
}
```


### 1.8 EndListing

- **URL**: `/listing/end`
- **Method**: `POST`
- **Description**: Ends a listing on eBay.
- **Parameters**:
  - `id`: The ID of the listing to end.

**Request**
```
curl -X POST https://example.com/wp-json/wple/v1/listing/end \
-u user:password \
-H "Content-Type: application/x-www-form-urlencoded" \
-d "id=76"
```

**Response**

```
{
  "success": true,
  "errors": [],
  "message": "Listing #76 was ended successfully",
  "item": {
    "id": "76",
    "ebay_id": "110599996666",
    "auction_title": "TEST Pennant",
    "auction_type": "FixedPriceItem",
    "listing_duration": "GTC",
    "date_created": "2024-04-26 06:18:02",
    "date_published": "2024-04-26 06:43:42",
    "date_finished": null,
    "end_date": "2024-04-26 07:07:40",
    "relist_date": null,
    "price": "14.25",
    "quantity": "0",
    "quantity_sold": "0",
    "status": "sold",
    "locked": "0",
    "variations": null,
    "ViewItemURL": "https://www.ebay.com/itm/TEST-Pennant-/110599996666",
    "GalleryURL": "https://example.com/wp-content/uploads/2023/11/pennant-1.jpg",
    "post_id": "3429",
    "parent_id": "0",
    "profile_id": "2",
    "template": "/uploads/wp-lister/templates/new-listing-template",
    "account_id": "12",
    "site_id": "0",
    "sku": "wp-pennant",
    "_ebay_start_price": "",
    "_regular_price": "11.05",
    "_sale_price": "",
    "_msrp_price": "",
    "thumb": "https://example.com/wp-content/uploads/2023/11/pennant-1-150x150.jpg"
  }
}
```

### Error Handling

The WP-Lister for eBay API uses standard HTTP status codes to indicate the success or failure of a request. In case of an error, additional information may be provided in the response body.

### Example Response

```
{
  "success": false,
  "errors":[
    {
      "severity": "Warning",
      "message": "\"Logo Collection\" already exists in account 12 and has been skipped.",
      "data": null
    }
  ],
  "message": "Unable to prepare product for listing."
}
```

### Common Errors

- **400 Bad Request**: The request was invalid or missing required parameters.
- **401 Unauthorized**: Authentication credentials are missing or invalid.
- **404 Not Found**: The requested resource was not found.
- **500 Internal Server Error**: An unexpected error occurred on the server.

## Rate Limiting

Requests to the WP-Lister for eBay API are subject to rate limiting to prevent abuse. The rate limits are enforced per user and per IP address.

## Conclusion

This concludes the documentation for the WP-Lister for eBay REST API. If you have any questions or need further assistance, please feel free to contact our support team. Happy coding!
