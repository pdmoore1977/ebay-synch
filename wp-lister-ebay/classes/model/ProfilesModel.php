<?php

class ProfilesModel extends WPL_Model {

	public $total_items;

	public function __construct() {
		parent::__construct();

		global $wpdb;
		$this->tablename = $wpdb->prefix . 'ebay_profiles';
	}

	/**
	 * Get the profile details for the given listing ID with the product properties applied
	 *
	 * @param $listing_id
	 *
	 * @return array Profile details
	 */
	public static function getProfileDetailsforProduct( $listing_id ) {
		$listing         = ListingsModel::getItem( $listing_id );
		$product_id      = $listing['post_id'];
		$profile_details = $listing['profile_data']['details'];

		// use parent post_id for split variations
		if ( ProductWrapper::isSingleVariation( $product_id ) ) {
			$product_id = ProductWrapper::getVariationParent( $product_id );
		}

		$property_map = [
			'condition_id' => '_ebay_condition_id',
			'condition_description' => '_ebay_condition_description',
			'professional_grader'   => '_ebay_professional_grader',
			'grade' => '_ebay_grade',
			'certification_number' => '_ebay_certification_number',
			'bestoffer_enabled' => '_ebay_bestoffer_enabled',
			'bo_autoaccept_price' => '_ebay_bo_autoaccept_price',
			'bo_minimum_price' => '_ebay_bo_minimum_price',
			'autopay' => '_ebay_autopay',
			'ebayplus_enabled' => '_ebay_ebayplus_enabled',
			'seller_payment_profile_id' => '_ebay_seller_payment_profile_id',
			'seller_return_profile_id' => '_ebay_seller_return_profile_id',
			'ShipToLocations' => '_ebay_shipping_ShipToLocations',
			'ExcludeShipToLocations' => '_ebay_shipping_ExcludeShipToLocations'
		];

		foreach ( $property_map as $property => $product_key ) {
			if ( get_post_meta( $product_id, $product_key, true ) ) {
				$profile_details[ $property ] = get_post_meta( $product_id, $product_key, true );
			}
		}

		// check for custom product level shipping options - if enabled
		$product_shipping_service_type = get_post_meta( $product_id, '_ebay_shipping_service_type', true );
		if ( ( $product_shipping_service_type != '' ) && ( $product_shipping_service_type != 'disabled' ) ) {

			$profile_details['shipping_service_type']               = $product_shipping_service_type;
			$profile_details['loc_shipping_options']                = get_post_meta( $product_id, '_ebay_loc_shipping_options', true );
			$profile_details['int_shipping_options']                = get_post_meta( $product_id, '_ebay_int_shipping_options', true );
			$profile_details['PackagingHandlingCosts']              = get_post_meta( $product_id, '_ebay_PackagingHandlingCosts', true );
			$profile_details['InternationalPackagingHandlingCosts'] = get_post_meta( $product_id, '_ebay_InternationalPackagingHandlingCosts', true );
			$profile_details['shipping_loc_enable_free_shipping']   = get_post_meta( $product_id, '_ebay_shipping_loc_enable_free_shipping', true );

			// retain the profile's shipping package if the product-level package isn't set
			$shipping_package = get_post_meta( $product_id, '_ebay_shipping_package', true );

			if ( ! empty( $shipping_package ) ) {
				$profile_details['shipping_package'] = $shipping_package;
			}

			// check for custom product level seller profiles
			if ( get_post_meta( $product_id, '_ebay_seller_shipping_profile_id', true ) ) {
				$product_level_profile_id = get_post_meta( $product_id, '_ebay_seller_shipping_profile_id', true );
				$profile_details['seller_shipping_profile_id'] = $product_level_profile_id;
			}

		}

		return apply_filters( 'wple_adjusted_profile_details', $profile_details, $product_id );
	}

	function getAll() {
		global $wpdb;
		$profiles = $wpdb->get_results("
			SELECT * 
			FROM $this->tablename
			ORDER BY sort_order ASC, profile_name ASC
		", ARRAY_A);

		foreach( $profiles as &$profile ) {
			$profile['details'] = self::decodeObject( $profile['details'] );
		}

		return $profiles;
	}


	function getItem( $id ) {
		global $wpdb;
		$item = $wpdb->get_row( $wpdb->prepare("
			SELECT * 
			FROM $this->tablename
			WHERE profile_id = %s
		", $id
		), ARRAY_A);

		if ( $item ) {
            $item['details'] = self::decodeObject( $item['details'], true );
            $item['conditions'] = unserialize( $item['conditions'] );

            // get category names
            $item['details']['ebay_category_1_name'] = EbayCategoriesModel::getCategoryName( $item['details']['ebay_category_1_id'], $item['site_id'] );
            $item['details']['ebay_category_2_name'] = EbayCategoriesModel::getCategoryName( $item['details']['ebay_category_2_id'], $item['site_id'] );

            // make sure that at least one payment and shipping option exist
            $item['details']['loc_shipping_options'] = $this->fixShippingArray( isset( $item['details']['loc_shipping_options'] ) ? $item['details']['loc_shipping_options'] : false );
            $item['details']['int_shipping_options'] = $this->fixShippingArray( isset( $item['details']['int_shipping_options'] ) ? $item['details']['int_shipping_options'] : false );
            $item['details']['payment_options'] = $this->fixShippingArray( isset( $item['details']['payment_options'] ) ? $item['details']['payment_options'] : false );
        }

		return $item;
	}

	function newItem() {

		$item = array(
			"profile_id"          => false,
			"profile_name"        => "New profile",
			"profile_description" => "",
			"listing_duration"    => "Days_7",
			"account_id"    	  => get_option( 'wplister_default_account_id' ),
		);

		$item['details'] = array(
			"auction_type"            => "FixedPriceItem",
			"condition_id"            => "1000",
			"counter_style"           => "BasicStyle",
			"country"                 => "US",
			"currency"                => "USD",
			"dispatch_time"           => "2",
			"ebay_category_1_id"      => "",
			"ebay_category_1_name"    => null,
			"ebay_category_2_id"      => "",
			"ebay_category_2_name"    => null,
			"fixed_price"             => "",
			"int_shipping_options"    => array(),
			"listing_duration"        => "Days_7",
			"loc_shipping_options"    => array(),
			"location"                => "",
			"payment_options"         => array(),
			"profile_description"     => "",
			"profile_name"            => "New profile",
			"custom_quantity_enabled" => "",
			"max_quantity"            => "",
			"quantity"                => "",
			"returns_accepted"        => "1",
			"returns_description"     => "",
			"returns_within"          => "Days_30",
			"start_price"             => "",
			"store_category_1_id"     => "",
			"store_category_2_id"     => "",
			"tax_mode"                => "none",
			"template"                => "",
			"title_prefix"            => "",
			"title_suffix"            => "",
			"vat_percent"             => "",
			"with_gallery_image"      => "1",
			"b2b_only"                => "",
			"ebayplus_enabled"        => "",
		);

		$item['conditions'] = array();

		// make sure that at least one payment and shipping option exist
		$item['details']['loc_shipping_options'] = $this->fixShippingArray();
		$item['details']['int_shipping_options'] = $this->fixShippingArray();
		$item['details']['payment_options'] 	 = $this->fixShippingArray();

		return $item;
	}

	// make sure, $options array contains at least one item
	static function fixShippingArray( $options = false ) {
		if ( !is_array( $options )  ) $options = array( '' );
		if ( count( $options ) == 0 ) $options = array( '' );
		return $options;
	}

	function deleteItem( $id ) {
		global $wpdb;

		// check if there are listings using this profile
		$listings = WPLE_ListingQueryHelper::getAllWithProfile( $id );
		if ( ! empty($listings) ) {
			wple_show_message('<b>Error: This profile is applied to '.count($listings).' listings and can not be deleted.</b><br>Please remove all listings using this profile first, then try again to delete the profile. If you still see this error message, make sure to check archived listings as well.','error');
			return false;
		}

		$wpdb->query( $wpdb->prepare("
			DELETE
			FROM $this->tablename
			WHERE profile_id = %s
		", $id ) );

		wple_show_message('Listing profile '.$id.' was deleted.','info');
	}


	function insertProfile($id, $details)
	{
		global $wpdb;

		$data['profile_id'] = $id;
		$data['profile_name'] = $data['profile_name'];
		$data['details'] = self::encodeObject($details);

		$wpdb->insert($this->tablename, $data);

		return true;
	}

	function updateProfile($id, $data) {
		global $wpdb;
		$result = $wpdb->update( $this->tablename, $data, array( 'profile_id' => $id ) );

		return $result;

	}

	function duplicateProfile($id) {
		global $wpdb;

		// get raw db content
		$data = $wpdb->get_row( $wpdb->prepare("
			SELECT * 
			FROM $this->tablename
			WHERE profile_id = %s
		", $id
		), ARRAY_A);

		// adjust duplicate
		$data['profile_name'] = $data['profile_name'] .' ('. __( 'duplicated', 'wp-lister-for-ebay' ).')';
		unset( $data['profile_id'] );

		// insert record
		$wpdb->insert( $this->tablename, $data );

		return $wpdb->insert_id;

	}

	function getAllNames() {
		global $wpdb;

		// return if DB has not been initialized yet
		if ( get_option('wplister_db_version') < 37 ) return array();

		$results = $wpdb->get_results("
			SELECT profile_id, profile_name 
			FROM $this->tablename
			ORDER BY sort_order ASC, profile_name ASC
		");

		$profiles = array();
		foreach( $results as $result ) {
			$profiles[ $result->profile_id ] = $result->profile_name;
		}

		return $profiles;
	}


	function getPageItems( $current_page, $per_page ) {
		global $wpdb;

        $orderby  = (!empty($_REQUEST['orderby'])) ? esc_sql( $_REQUEST['orderby'] ) : 'profile_name';
        $order    = (!empty($_REQUEST['order']))   ? esc_sql( $_REQUEST['order']   ) : 'asc';
        $offset   = ( $current_page - 1 ) * $per_page;
        $per_page = esc_sql( $per_page );

        // regard sort order if sorted by profile name
        if ( $orderby == 'profile_name' ) $orderby = 'sort_order '.$order.', profile_name';

        $join_sql  = '';
        $where_sql = 'WHERE 1 = 1 ';

        // filter search_query
		$search_query = isset($_REQUEST['s']) ? esc_sql( wple_clean($_REQUEST['s']) ) : false;
		if ( $search_query ) {
			$where_sql .= "
				AND  ( profile_name        LIKE '%".$search_query."%'
					OR profile_description LIKE '%".$search_query."%' )
			";
		}

        // get items
		$items = $wpdb->get_results("
			SELECT *
			FROM $this->tablename
            $join_sql 
	        $where_sql
			ORDER BY $orderby $order
            LIMIT $offset, $per_page
		", ARRAY_A);

		// get total items count - if needed
		if ( ( $current_page == 1 ) && ( count( $items ) < $per_page ) ) {
			$this->total_items = count( $items );
		} else {
			$this->total_items = $wpdb->get_var("
				SELECT COUNT(*)
				FROM $this->tablename
	            $join_sql 
    	        $where_sql
				ORDER BY $orderby $order
			");
		}

		foreach( $items as &$profile ) {
			$profile['details'] = self::decodeObject( $profile['details'] );
		}

		return $items;
	}


} // class ProfilesModel
