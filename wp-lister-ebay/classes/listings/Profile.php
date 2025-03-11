<?php

namespace WPLab\Ebay\Listings;

class Profile {

	private $data = array(
		'profile_id' => 0,
		'profile_name'  => '',
		'profile_description' => '',
		'listing_duration' => 'GTC',
		'type' => '',
		'sort_order' => '100',
		'details' => [],
		'conditions' => '',
		'category_specifics' => '',
		'account_id' => '',
		'site_id' => ''
	);

	/**
	 * @param $data_array
	 */
	public function __construct( $id = null ) {
		if ( $id ) {
			$mdl = new \ProfilesModel();
			$profile_data = $mdl->getItem( $id );

			if ( $profile_data ) {
				$this->populate_data( $profile_data );
			}

		}
	}

	private function populate_data( $profile_data ) {
		foreach ( $profile_data as $key => $value ) {
			if ( array_key_exists( $key, $this->data ) ) {
				$this->data[ $key ] = $value;
			}
		}
	}

	public function getId() {
		return $this->data['profile_id'];
	}

	public function setId( $id ) {
		$this->data['profile_id'] = $id;
		return $this;
	}

	public function getName() {
		return $this->data['profile_name'];
	}

	public function setName( $name ) {
		$this->data['profile_name'] = $name;
		return $this;
	}

	public function getDescription() {
		return $this->data['profile_description'];
	}

	public function setDescription( $description ) {
		$this->data['profile_description'] = $description;
		return $this;
	}

	public function getListingDuration() {
		return $this->data['listing_duration'];
	}

	public function setListingDuration( $duration ) {
		$this->data['listing_duration'] = $duration;
		return $this;
	}

	public function getListingType() {
		return $this->data['type'];
	}

	public function setListingType( $type ) {
		$this->data['type'] = $type;
		return $this;
	}

	public function getProfileDetails() {
		return $this->data['details'];
	}

	/**
	 * Get the final profile details to use based on priority. Product-level profile details (stored in postmeta)
	 * will always have higher priority over those set in the profile
	 *
	 * Profile details are always saved in the listing's profile_details property for accessibility.
	 * Everytime a profile is changed, the listing's profile_details data is updated.
	 *
	 * @param Listing $listing
	 * @return array
	 */
	public function getProductProfileDetails( Listing $listing ) {
		$profile_details = $this->getProfileDetails();

		$product_id = $listing->getProductId();
		if ( $listing->isSplitVariation() ) {
			$product_id = $listing->getProduct()->get_parent_id();
		}
		$product = wc_get_product( $product_id );

		// map of attributes that can be overridden from the postmeta table
		$attributes = [
			'condition_id'              => '_ebay_condition_id',
			'condition_description'     => '_ebay_condition_description',
			'professional_grader'       => '_ebay_professional_grader',
			'grade'                     => '_ebay_grade',
			'certification_number'      => '_ebay_certification_number',
			'bestoffer_enabled'         => '_ebay_bestoffer_enabled',
			'bo_autoaccept_price'       => '_ebay_bo_autoaccept_price',
			'bo_minimum_price'          => '_ebay_bo_minimum_price',
			'autopay'                   => '_ebay_autopay',
			'ebayplus_enabled'          => '_ebay_ebayplus_enabled',
			'seller_payment_profile_id' => '_ebay_seller_payment_profile_id',
			'seller_return_profile_id'  => '_ebay_seller_return_profile_id',
		];


		foreach ( $attributes as $profile_key => $meta_key ) {
			if ( !isset( $profile_details[ $profile_key ] ) ) {
				$profile_details[ $profile_key ] = '';
			}

			if ($product) {
				$value = $product->get_meta( $meta_key );

				if ($value) {
					$profile_details[ $profile_key ] = $value;
				}
			}

		}

		if ($product) {
			// check for custom product level shipping options - if enabled
			$product_shipping_service_type = $product->get_meta( '_ebay_shipping_service_type' );
			if ( ( $product_shipping_service_type != '' ) && ( $product_shipping_service_type != 'disabled' ) ) {

				$profile_details['shipping_service_type']               = $product_shipping_service_type;
				$profile_details['loc_shipping_options']                = $product->get_meta( '_ebay_loc_shipping_options' );
				$profile_details['int_shipping_options']                = $product->get_meta( '_ebay_int_shipping_options' );
				$profile_details['PackagingHandlingCosts']              = $product->get_meta( '_ebay_PackagingHandlingCosts' );
				$profile_details['InternationalPackagingHandlingCosts'] = $product->get_meta( '_ebay_InternationalPackagingHandlingCosts' );
				$profile_details['shipping_loc_enable_free_shipping']   = $product->get_meta( '_ebay_shipping_loc_enable_free_shipping' );

				// retain the profile's shipping package if the product-level package isn't set
				$shipping_package = $product->get_meta( '_ebay_shipping_package' );

				if ( ! empty( $shipping_package ) ) {
					$profile_details['shipping_package'] = $shipping_package;
				}

				// check for custom product level seller profiles
				if ( $product->get_meta( '_ebay_seller_shipping_profile_id' ) ) {
					$product_level_profile_id = $product->get_meta( '_ebay_seller_shipping_profile_id' );
					$profile_details['seller_shipping_profile_id'] = $product_level_profile_id;
				}

				// check for custom product level ship to locations
				if ( $product->get_meta( '_ebay_shipping_ShipToLocations' ) )
					$profile_details['ShipToLocations']					= $product->get_meta( '_ebay_shipping_ShipToLocations' );
				if ( $product->get_meta( '_ebay_shipping_ExcludeShipToLocations' ) )
					$profile_details['ExcludeShipToLocations']			= $product->get_meta( '_ebay_shipping_ExcludeShipToLocations' );

			}
		}

		return apply_filters( 'wple_adjusted_profile_details', $profile_details, $product_id );

	}

	public function setProfileDetails( $data ) {
		$this->data['details'] = $data;
		return $this;
	}

	public function getConditions() {
		return $this->data['conditions'];
	}

	public function setConditions( $conditions ) {
		$this->data['conditions'] = $conditions;
		return $this;
	}

	public function getCategorySpecifics() {
		return $this->data['category_specifics'];
	}

	public function setCategorySpecifics( $category_specifics ) {
		$this->data['category_specifics'] = $category_specifics;
		return $this;
	}

	public function getAccountId() {
		return $this->data['account_id'];
	}

	public function setAccountId( $id ) {
		$this->data['account_id'] = $id;
		return $this;
	}

	public function getSiteId() {
		return $this->data['site_id'];
	}

	public function setSiteId( $id ) {
		$this->data['site_id'] = $id;
		return $this;
	}
}