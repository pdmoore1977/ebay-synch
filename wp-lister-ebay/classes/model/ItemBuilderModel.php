<?php

use WPLab\Ebay\Listings\Listing;
require_once WPLE_PLUGIN_PATH .'/includes/EbatNs/ItemType.php';

/**
 * ItemBuilderModel class
 *
 * responsible for building listing items
 *
 */

class ItemBuilderModel extends WPL_Model {
	private ItemType $item;
	protected Listing $listing;

	protected $variationAttributes = array();
	protected $variationSplitAttributes = array();
	private $tmpVariationSpecificsSet = array();
	public $VariationsHaveStock;

	public $result = false;
	public $site_id = null;
	public $listing_id = null;
	public $account_id = null;

	protected ListingsModel $lm;
	protected int $product_id;
	protected array $profile_details;
	protected EbatNs_Session $session;

	public function __construct() {
		parent::__construct();

		// provide listings model
		$this->lm       = new ListingsModel();
		$this->item     = new ItemType();
		$this->listing  = new Listing();
	}

	private function prepareItem() {

	}


	/**
	 * @param int $id Listing ID
	 * @param EbatNs_Session $session
	 * @param bool $reviseItem
	 * @param bool $preview
	 *
	 * @return ItemType
	 */
	public function buildItem( $id, $session, $reviseItem = false, $preview = false ) {
        // allow 3rd-party plugins to run code prior to building item listings
        do_action( 'wplister_before_build_item', $id );

		$listing            = new Listing( $id );
		$post_id            = $listing->getProductId();
		$profile_details    = $listing->getProfileDetails();
		$hasVariations      = $listing->isVariable();
		$isVariation        = $listing->isSplitVariation();


		// remember listing id and account id for checkItem() and buildPayment()
		$this->listing      = $listing;
		$this->listing_id   = $id;
		$this->product_id   = $post_id;
		$this->account_id   = $listing->getAccountId();
		$this->profile_details = $profile_details;
		$this->session = $session;

		$this->setQuantity();

		// set listing title
		$this->item->setTitle( $this->prepareTitle( $listing->getTitle() ) );

		// set listing duration
		$product_listing_duration = get_post_meta( $post_id, '_ebay_listing_duration', true );
		$product_listing_duration = $product_listing_duration  ? $product_listing_duration : $listing->getDuration();
		$this->item->setListingDuration( $product_listing_duration );

		// omit ListingType when revising item
		if ( ! $reviseItem ) {
			$product_listing_type = get_post_meta( $post_id, '_ebay_auction_type', true );
			$ListingType = $product_listing_type ? $product_listing_type : $listing->getType();

			// handle classified ads
			if ( $ListingType == 'ClassifiedAd' ) {
				$ListingType = 'LeadGeneration';
				$this->item->setListingSubtype2( 'ClassifiedAd' );
			}
			$this->item->setListingType( $ListingType );
		}


		// set eBay Site
		$this->setEbaySite();

		// add prices
		$this->buildPrices();

		// add images
		$this->buildImages();


		// if this is a split variation, use parent post_id for all further processing
		if ( $isVariation ) {

			// prepare item specifics / variation attributes
			$this->prepareSplitVariation( $id, $this->product_id, $this->listing );

			// use parent post_id for all further processing
			$this->product_id = ProductWrapper::getVariationParent( $this->product_id );
		}


		// add ebay categories and store categories
        // for split variations, load categories from the parent product
        if ( ! $hasVariations && $this->listing->getParentId() > 0 ) {
            $this->buildCategories( $this->listing->getParentId() );
        } else {
            $this->buildCategories( $this->product_id );
        }

		// add various options from $profile_details
		$this->buildProfileOptions();


		// add various options that depend on $profile_details and $post_id
		$this->buildProductOptions();

		// add payment and return options
		$this->buildPayment();

		// add shipping services and options
		$this->buildShipping();

		// add seller profiles
		$this->buildSellerProfiles();

		// add variations
		if ( $hasVariations ) {
			if ( @$this->profile_details['variations_mode'] == 'flat' ) {
				// don't build variations - list as flat item
				$this->flattenVariations();
			} else {
				// default: list as variations
				$this->buildVariations();
			}
		}

		// add item specifics (attributes) - after variations
		$this->buildItemSpecifics();

		$this->buildGpsr();

		// add part compatibility list
		$this->buildCompatibilityList();

		// set listing description - after $item has been built
		$this->item->setDescription( $this->getFinalHTML( $id, $this->item, $preview ) );

		// qTranslate support - translate title and description
        if ( function_exists( 'qtranxf_use' ) ) {
            $lang = WPLE_eBayAccount::getAccountLocale( $listing->getAccountId() );

            $this->item->setTitle( qtranxf_use( $lang, $this->item->getTitle() ) );
            $this->item->setDescription( qtranxf_use( $lang, $this->item->getDescription() ) );
        }


		// adjust item if this is a ReviseItem request
		if ( $reviseItem ) {
			$this->adjustItemForRevision();
		} else {
			$this->buildSchedule();
		}

		// add UUID to prevent duplicate AddItem or RelistItem calls
		if ( ! $reviseItem ) {
			// build UUID from listing Title, product_id, previous ItemID and today's date and hour
			$uuid_src = $this->item->getTitle() . $this->product_id . $this->listing->getEbayId() . gmdate('Y-m-d h');
			$this->item->setUUID( md5( $uuid_src ) );
			WPLE()->logger->info('UUID src: '.$uuid_src);
		}

		// filter final item object before it's sent to eBay
		$this->item = apply_filters_deprecated( 'wplister_filter_listing_item', array($this->item, $this->listing, $this->profile_details, $this->product_id), '2.8.4', 'wple_filter_listing_item' );
		$this->item = apply_filters( 'wple_filter_listing_item', $this->item, $this->listing, $this->profile_details, $this->product_id, $reviseItem );

		return $this->item;
	} /* end of buildItem() */

	/**
	 * adjust item for ReviseItem request
	 */
	public function adjustItemForRevision() {

		// check if title should be omitted:
		// The title or subtitle cannot be changed if an auction-style listing has a bid or ends within 12 hours,
		// or a fixed price listing has a sale or a pending Best Offer.
		if ( LISTING::TYPE_AUCTION == $this->listing->getType() ) {
			// auction listing
			$hours_left = ( strtotime($this->listing->getEndDate()) - gmdate('U') ) / 3600;
			if ( $hours_left < 12 ) {
				$this->item->setTitle( null );
				$this->item->setSubTitle( null );
			}
		} else {
			// fixed price listing
			// (disabled for now - eBay does seem to allow title changes when an item has sales)
			// if ( $listing['quantity_sold'] > 0 ) {
			// 	$item->setTitle( null );
			// 	$item->setSubTitle( null );
			// }
		}

	} /* end of adjustItemForRevision() */

	/**
	 * @return void
	 */
    public function setQuantity() {
		$stock = $this->listing->getStockQuantity();

	    $this->item->setQuantity( $stock );
    }

	/**
	 * Set the listing's marketplace
	 *
	 * @return void
	 */
	public function setEbaySite() {

		// set eBay site from global site iD
		// http://developer.ebay.com/DevZone/XML/docs/Reference/eBay/types/SiteCodeType.html
		$site_id = $this->session->getSiteId();
		$sites = EbayController::getEbaySites();
		$site_name = $sites[$site_id];
		$this->item->setSite( $site_name );

		// remember site_id for checkItem()
		$this->site_id = $site_id;
	} /* end of setEbaySite() */

	/**
	 * @param int $product_id
	 */
	public function buildCategories( $product_id ) {
		$profile_details    = $this->profile_details;
		$listing            = $this->listing;
        $mapped_categories = $this->getMappedCategories( $product_id, $listing->getAccountId() );

		$primary_category = $listing->getPrimaryCategory( $product_id );
		$category = new CategoryType();
		$category->setCategoryID( $primary_category );
		$this->item->setPrimaryCategory( $category );

		$secondary_category = $listing->getSecondaryCategory( $product_id );

		if ( $secondary_category && $secondary_category != $primary_category ) {
			$category = new CategoryType();
			$category->setCategoryID( $secondary_category );
			$this->item->setSecondaryCategory( $category );
		}

		// if no secondary category, set to zero
		// Also set to zero if Secondary Category in the profile is disabled
		$secondary = $this->item->getSecondaryCategory();
		if ( empty( $secondary ) || @$profile_details['enable_secondary_category'] == 0 ) {
			$category = new CategoryType();
			$category->setCategoryID( 0 );

			$this->item->setSecondaryCategory( $category );
		}

		$primary_store_category = $listing->getPrimaryStoreCategory( $product_id);
		$secondary_store_category = $listing->getSecondaryStoreCategory( $product_id );

		$storefront = new StorefrontType();
		if ( intval( $primary_store_category ) > 0 ) {
			$storefront->setStoreCategoryID( $primary_store_category );
		}

		if ( $secondary_store_category && $secondary_store_category != $primary_store_category ) {
			$storefront->setStoreCategory2ID( $secondary_store_category );
		}

		$this->item->setStorefront( $storefront );

		// adjust Site if required - eBay Motors (beta)
		if ( $this->item->getSite() == 'US' ) {
			// if primary category's site_id is 100, set Site to eBayMotors
			$primary_category = EbayCategoriesModel::getItem( $this->item->getPrimaryCategory()->getCategoryID() );
			if ( $primary_category && $primary_category['site_id'] == 100 ) {
				$this->item->setSite('eBayMotors');
			}
		}

	} /* end of buildCategories() */


	/**
	 * adjust profile details from product level options
	 *
	 * @param int $id Listing ID
	 * @param int $post_id Product ID
	 * @param array $profile_details
	 * @depecated Use ProfilesModel::getProfileDetailsforProduct() instead
	 */
	public function adjustProfileDetails( $id, $post_id, $profile_details ) {
		_deprecated_function( 'ItemBuilderModel::adjustProfileDetails()', '3.6.0', 'ProfilesModel::getProfileDetailsforProduct()');

		// use parent post_id for split variations
		if ( ProductWrapper::isSingleVariation( $post_id ) ) {
			$post_id = ProductWrapper::getVariationParent( $post_id );
		}

		// check for custom product level condition options
		if ( get_post_meta( $post_id, '_ebay_condition_id', true ) )
			$profile_details['condition_id']						= get_post_meta( $post_id, '_ebay_condition_id', true );
		if ( get_post_meta( $post_id, '_ebay_condition_description', true ) )
			$profile_details['condition_description']				= get_post_meta( $post_id, '_ebay_condition_description', true );
		if ( get_post_meta( $post_id, '_ebay_professional_grader', true ) )
			$profile_details['professional_grader']				= get_post_meta( $post_id, '_ebay_professional_grader', true );
		if ( get_post_meta( $post_id, '_ebay_grade', true ) )
			$profile_details['grade']				= get_post_meta( $post_id, '_ebay_grade', true );
		if ( get_post_meta( $post_id, '_ebay_certification_number', true ) )
			$profile_details['certification_number']				= get_post_meta( $post_id, '_ebay_certification_number', true );

		// check for custom product level bestoffer options
		if ( get_post_meta( $post_id, '_ebay_bestoffer_enabled', true ) )
			$profile_details['bestoffer_enabled']					= get_post_meta( $post_id, '_ebay_bestoffer_enabled', true );
		if ( get_post_meta( $post_id, '_ebay_bo_autoaccept_price', true ) )
			$profile_details['bo_autoaccept_price']					= get_post_meta( $post_id, '_ebay_bo_autoaccept_price', true );
		if ( get_post_meta( $post_id, '_ebay_bo_minimum_price', true ) )
			$profile_details['bo_minimum_price']					= get_post_meta( $post_id, '_ebay_bo_minimum_price', true );

		// check for custom product level autopay options
		if ( get_post_meta( $post_id, '_ebay_autopay', true ) )
			$profile_details['autopay']								= get_post_meta( $post_id, '_ebay_autopay', true );

		// check for custom product level ebayplus options
		if ( get_post_meta( $post_id, '_ebay_ebayplus_enabled', true ) )
			$profile_details['ebayplus_enabled']					= get_post_meta( $post_id, '_ebay_ebayplus_enabled', true ) == 'yes' ? 1 : 0;

		// check for custom product level seller profiles
		// if ( get_post_meta( $post_id, '_ebay_seller_shipping_profile_id', true ) )
		// 	$profile_details['seller_shipping_profile_id']			= get_post_meta( $post_id, '_ebay_seller_shipping_profile_id', true );
		if ( get_post_meta( $post_id, '_ebay_seller_payment_profile_id', true ) )
			$profile_details['seller_payment_profile_id']			= get_post_meta( $post_id, '_ebay_seller_payment_profile_id', true );
		if ( get_post_meta( $post_id, '_ebay_seller_return_profile_id', true ) )
			$profile_details['seller_return_profile_id']			= get_post_meta( $post_id, '_ebay_seller_return_profile_id', true );

		// check for custom product level shipping options - if enabled
		$product_shipping_service_type = get_post_meta( $post_id, '_ebay_shipping_service_type', true );
		if ( ( $product_shipping_service_type != '' ) && ( $product_shipping_service_type != 'disabled' ) ) {

			$profile_details['shipping_service_type']               = $product_shipping_service_type;
			$profile_details['loc_shipping_options']                = get_post_meta( $post_id, '_ebay_loc_shipping_options', true );
			$profile_details['int_shipping_options']                = get_post_meta( $post_id, '_ebay_int_shipping_options', true );
			$profile_details['PackagingHandlingCosts']              = get_post_meta( $post_id, '_ebay_PackagingHandlingCosts', true );
			$profile_details['InternationalPackagingHandlingCosts'] = get_post_meta( $post_id, '_ebay_InternationalPackagingHandlingCosts', true );
			$profile_details['shipping_loc_enable_free_shipping']   = get_post_meta( $post_id, '_ebay_shipping_loc_enable_free_shipping', true );

			// retain the profile's shipping package if the product-level package isn't set
			$shipping_package = get_post_meta( $post_id, '_ebay_shipping_package', true );

			if ( ! empty( $shipping_package ) ) {
                $profile_details['shipping_package'] = $shipping_package;
            }

			// check for custom product level seller profiles
			if ( get_post_meta( $post_id, '_ebay_seller_shipping_profile_id', true ) ) {

				$product_level_profile_id = get_post_meta( $post_id, '_ebay_seller_shipping_profile_id', true );
				$profile_details['seller_shipping_profile_id'] = $product_level_profile_id;

				// // check if shipping profile id exists (done in buildSellerProfiles())
				// $seller_shipping_profiles	= get_option('wplister_ebay_seller_shipping_profiles');
				// foreach ( $seller_shipping_profiles as $profile ) {
				// 	if ( $profile->ProfileID == $product_level_profile_id )
				// 		$profile_details['seller_shipping_profile_id'] = $product_level_profile_id;
				// }

			}

			// check for custom product level ship to locations
			if ( get_post_meta( $post_id, '_ebay_shipping_ShipToLocations', true ) )
				$profile_details['ShipToLocations']					= get_post_meta( $post_id, '_ebay_shipping_ShipToLocations', true );
			if ( get_post_meta( $post_id, '_ebay_shipping_ExcludeShipToLocations', true ) )
				$profile_details['ExcludeShipToLocations']			= get_post_meta( $post_id, '_ebay_shipping_ExcludeShipToLocations', true );

		}

		// Product-level shipping profile shouldn't be used if the shipping service type is disabled for the product #38483
//		elseif ( ( $product_level_profile_id = get_post_meta( $post_id, '_ebay_seller_shipping_profile_id', true ) ) ) {
//            $profile_details['seller_shipping_profile_id'] = $product_level_profile_id;
//        }

		return apply_filters( 'wple_adjusted_profile_details', $profile_details, $post_id );

	} /* end of adjustProfileDetails() */


	/**
	 * Build the listing's seller profile properties
	 */
	public function buildSellerProfiles() {

		$SellerProfiles = new SellerProfilesType();

		if ( @$this->profile_details['seller_shipping_profile_id'] ) {

			// get seller profiles for account
			$accounts = WPLE()->accounts;
			if ( isset( $accounts[ $this->account_id ] ) && ( $accounts[ $this->account_id ]->shipping_profiles ) ) {
				$seller_shipping_profiles = maybe_unserialize( $accounts[ $this->account_id ]->shipping_profiles );
			} else {
				$seller_shipping_profiles = get_option( 'wplister_ebay_seller_shipping_profiles' );
			}

			// check if shipping profile id exists
			// TODO: show warning to user if non-existing seller profile was ignored
			// $seller_shipping_profiles	= get_option('wplister_ebay_seller_shipping_profiles');
			$profile_exists = false;
			foreach ( $seller_shipping_profiles as $profile ) {
				if ( $profile->ProfileID == $this->profile_details['seller_shipping_profile_id'] )
					$profile_exists = true;
			}

			if ( $profile_exists ) {
				$SellerProfiles->SellerShippingProfile = new SellerShippingProfileType();
				$SellerProfiles->SellerShippingProfile->setShippingProfileID( $this->profile_details['seller_shipping_profile_id'] );
			}

		}

		if ( @$this->profile_details['seller_payment_profile_id'] ) {
			$SellerProfiles->SellerPaymentProfile = new SellerPaymentProfileType();
			$SellerProfiles->SellerPaymentProfile->setPaymentProfileID( $this->profile_details['seller_payment_profile_id'] );
		}

		if ( @$this->profile_details['seller_return_profile_id'] ) {
			$SellerProfiles->SellerReturnProfile = new SellerReturnProfileType();
			$SellerProfiles->SellerReturnProfile->setReturnProfileID( $this->profile_details['seller_return_profile_id'] );
		}

		$this->item->setSellerProfiles( $SellerProfiles );
	} /* end of buildSellerProfiles() */


	/**
	 * @return void
	 */
	public function buildPrices() {
        WPLE()->logger->info( 'buildPrices #'. $this->product_id );


		// price has been calculated when applying the profile
		$start_price  = $this->listing->getStartPrice();
		WPLE()->logger->info( 'listing[price]: '. $start_price );

		$price = new AmountType();
		$price->setTypeValue( self::dbSafeFloatval( $start_price ) );
		$price->setTypeAttribute('currencyID', $this->profile_details['currency'] );
		$this->item->setStartPrice( $price );

		// optional BuyItNow price
		$bin_price = $this->listing->getBuyItNowPrice();

		if ( $bin_price ) {
			$price = new AmountType();
			$price->setTypeValue( $bin_price );
			$price->setTypeAttribute('currencyID', $this->profile_details['currency'] );
			$this->item->setBuyItNowPrice( $price );
            WPLE()->logger->info( 'BIN Price: '. $bin_price );
		}

		// optional ReservePrice
        $product_reserve_price = $this->listing->getReservePrice();

        if ( $this->listing->getType() == Listing::TYPE_AUCTION && !$product_reserve_price ) {
            // Delete the reserve price by setting it to 0
            $price = new AmountType();
            $price->setTypeValue( 0 );
            $price->setTypeAttribute('currencyID', $this->profile_details['currency'] );
			$this->item->setReservePrice( $price );
        }

		if ( $product_reserve_price ) {
			$price = new AmountType();
			$price->setTypeValue( $product_reserve_price );
			$price->setTypeAttribute('currencyID', $this->profile_details['currency'] );
			$this->item->setReservePrice( $price );
            WPLE()->logger->info( 'Product Reserve Price: '. $product_reserve_price );
		}

		// optional DiscountPriceInfo.OriginalRetailPrice
		if ( intval($this->profile_details['strikethrough_pricing']) != 0) {
			// mode 1 - use sale price
			if ( 1 == $this->profile_details['strikethrough_pricing'] ) {
				$original_price = ProductWrapper::getOriginalPrice( $this->listing->getProductId() );
				if ( ( $original_price ) && ( $start_price != $original_price ) ) {
					$retail = new AmountType();
					$retail->setTypeValue( $original_price );
					$retail->setTypeAttribute( 'currencyID', $this->profile_details['currency'] );

					$discount = new DiscountPriceInfoType();
					$discount->setOriginalRetailPrice( $retail );

					$this->item->setDiscountPriceInfo( $discount );
				}
			}

			// mode 2 - use MSRP
			if ( 2 == $this->profile_details['strikethrough_pricing'] ) {
				$msrp_price = $this->listing->getMsrpPrice();
				if ( ( $msrp_price ) && ( $start_price != $msrp_price ) ) {
					$price = new AmountType();
					$price->setTypeValue( $msrp_price );
					$price->setTypeAttribute('currencyID', $this->profile_details['currency'] );

					$discount = new DiscountPriceInfoType();
					$discount->setOriginalRetailPrice( $price );

					$this->item->setDiscountPriceInfo( $discount );
				}
			}

		} // OriginalRetailPrice / STP

        // Minimum Advertised Price (MAP)
        if ( 1 == @$this->profile_details['map_pricing'] ) {
            $original_price = ProductWrapper::getOriginalPrice( $this->listing->getProductId() );
            $sale_price     = ProductWrapper::getPrice( $this->listing->getProductId() );

            if ( ( $original_price ) && ( $start_price != $original_price ) ) {
                // set the StartPrice to the Original Price
	            $this->item->getStartPrice()->setTypeValue( self::dbSafeFloatval( $start_price ) );

                $exposure = empty( $this->profile_details['map_exposure'] ) ? 'DuringCheckout' : $this->profile_details['map_exposure'];

                if ( !$this->item->DiscountPriceInfo || !is_a( $this->item->DiscountPriceInfo, 'DiscountPriceInfoType' ) ) {
                    $this->item->setDiscountPriceInfo( new DiscountPriceInfoType() );
                }

				$price = new AmountType();
				$price->setTypeValue( $original_price );
				$price->setTypeAttribute( 'currencyID', $this->profile_details['currency'] );
				$this->item->getDiscountPriceInfo()->setOriginalRetailPrice( $price );

				$price = new AmountType();
				$price->setTypeValue( $sale_price );
				$price->setTypeAttribute('currencyID', $this->profile_details['currency'] );

				$this->item->getDiscountPriceInfo()->setMinimumAdvertisedPrice( $price );
                $this->item->getDiscountPriceInfo()->setMinimumAdvertisedPriceExposure( $exposure );
                $this->item->getDiscountPriceInfo()->setPricingTreatment( 'MAP' );
            }
        }

		## BEGIN PRO ##

        // handle BestOffer options
		$nyp_enabled = get_post_meta( $this->product_id, '_nyp', true ) == 'yes';
		$nyp_enabled = apply_filters( 'wple_name_your_price_enabled', $nyp_enabled, $this->product_id );
        if ( ( @$this->profile_details['bestoffer_enabled'] == '1' ) || ( @$this->profile_details['bestoffer_enabled'] == 'yes' ) || $nyp_enabled ) {

			$best_offer = new BestOfferDetailsType();
			$best_offer->setBestOfferEnabled( 1 );
        	$this->item->setBestOfferDetails( $best_offer );

        	$listing_details = new ListingDetailsType();

	        if ( @$this->profile_details['bo_autoaccept_price'] != '' ) {
	        	$bo_autoaccept_price = ListingsModel::applyProfilePrice( $start_price, $this->profile_details['bo_autoaccept_price'] );
        		$listing_details->setBestOfferAutoAcceptPrice( $bo_autoaccept_price );
				$this->item->setListingDetails( $listing_details );
        	}

	        if ( @$this->profile_details['bo_minimum_price'] != '' ) {
	        	$bo_minimum_price = ListingsModel::applyProfilePrice( $start_price, $this->profile_details['bo_minimum_price'] );
        		$listing_details->setMinimumBestOfferPrice( $bo_minimum_price );
		        $this->item->setListingDetails( $listing_details );
        	}

			if ( $nyp_enabled ) {
				$nyp_minimum_price = get_post_meta( $this->listing->getProductId(), '_min_price', true );
				if ( $nyp_minimum_price ) {
					$listing_details->setMinimumBestOfferPrice( $nyp_minimum_price );
					$this->item->setListingDetails( $listing_details );
				}
				WPLE()->logger->info( 'NYP enabled: ' . $nyp_minimum_price );
			}

        } else {

        	$this->item->setBestOfferDetails( new BestOfferDetailsType() );
        	$this->item->getBestOfferDetails()->setBestOfferEnabled( 0 ); // false would cause soap error 37

        }

		## END PRO ##
	} /* end of buildPrices() */


	public function buildImages() {
		$images         = $this->listing->getImages( true );
		$main_image     = $this->listing->getPrimaryImage( $this->listing->getProductId(), true );

		if ( ( trim($main_image) == '' ) && ( sizeof($images) > 0 ) ) $main_image = $images[0];

		// handle product image
		$pic = new PictureDetailsType();
		$pic->addPictureURL( wple_encode_url( $main_image ) );

		// handle gallery type
		$gallery_type = $this->profile_details['gallery_type'] ??  'Gallery';
		$gallery_type = in_array( $gallery_type, array('Gallery','Plus','Featured') ) ? $gallery_type : 'Gallery';

		if ( $this->profile_details['with_gallery_image'] ) {
			$pic->setGalleryType( $gallery_type );
		}

		$this->item->setPictureDetails( $pic );

		## BEGIN PRO ##

        // upload ALL additional images if enabled
        $with_additional_images = $this->profile_details['with_additional_images'] ?? false;
        if ( $with_additional_images == '0' ) $with_additional_images = false;

        if ( $with_additional_images ) {

        	// set upload limit in regard to selected mode
        	if ( $with_additional_images == '1' ) $images_upload_limit = false;
        	if ( $with_additional_images == '2' ) $images_upload_limit = 24;
        	if ( $with_additional_images == '3' ) $images_upload_limit = 0;

			// upload main image
			$image_url = $this->lm->uploadPictureToEPS( $main_image, $this->listing_id, $this->session );
            $main_image_local_url = $main_image;
            WPLE()->logger->info( "uploaded main image $image_url" );

			$pic = new PictureDetailsType();
			$pic->addPictureURL( $image_url );
			$pic->setGalleryType( $gallery_type);
			$pic->setPhotoDisplay( 'PicturePack' );
			$this->item->setPictureDetails( $pic );

			// upload additional images - if enabled
			if ( $with_additional_images != '3' ) {

				$images_upload_count = 1; // main image has already been added
	        	foreach ($images as $additional_image) {
	        	    // Compare the additional_image URL against the main image's local URL
                    // because just comparing the basenames would return true even if the images
                    // were uploaded in different months (2018/01/123.jpg vs 2018/04/123.jpg) #23937
	        		//if ( basename($additional_image) != basename($main_image) ) {
	        		if ( $additional_image != $main_image_local_url ) {
	        			// upload image
	        			$image_url = $this->lm->uploadPictureToEPS( $additional_image, $this->listing_id, $this->session );
                        if ( $image_url ) {
							$this->item->getPictureDetails()->addPictureURL( $image_url );
                        }
						WPLE()->logger->info( "uploaded additional image #$images_upload_count: $additional_image - limit is $images_upload_limit" );
						$images_upload_count++;
	        		}
	        		// break loop when upload limit is reached
	        		if ( ( $images_upload_limit ) && ( $images_upload_count >= $images_upload_limit ) ) break;
	        	}
			}

        } // $with_additional_images

		## END PRO ##

	} /* end of buildImages() */


	public function buildProductListingDetails( $product_sku ) {
		$product_id = $this->product_id;
		$hasVariations   = ProductWrapper::hasVariations( $this->listing->getProductId() );
		$isVariation     = ProductWrapper::isSingleVariation( $this->listing->getProductId() );

		// if this is a variable product to be flattened, set $hasVariations to false (allow UPC/EAN to be set to "Does not apply")
		if ( $hasVariations && $this->profile_details['variations_mode'] == 'flat' ) {
			$hasVariations = false;
		}

		// if this is a single split variation, use variation post_id - but remember parent_id to fetch Brand
		$parent_id = $product_id;
		if ( $isVariation ) $product_id = $this->listing->getProductId();

		// handle Product ID (UPC, EAN, MPN, etc.)
		$autofill_missing_gtin = get_option('wplister_autofill_missing_gtin');
		$DoesNotApplyText = WPLE_eBaySite::getSiteObj( $this->site_id )->DoesNotApplyText;
		$DoesNotApplyText = empty( $DoesNotApplyText ) ? 'Does not apply' : $DoesNotApplyText;
		WPLE()->logger->info('DoesNotApplyText for site ID '.$this->site_id.': '.$DoesNotApplyText);

		// check if primary category requires UPC or EAN
		$primary_category_id = $this->item->getPrimaryCategory()->getCategoryID();
		$UPCEnabled          = EbayCategoriesModel::getUPCEnabledForCategory( $primary_category_id, $this->site_id, $this->account_id );
		$EANEnabled          = EbayCategoriesModel::getEANEnabledForCategory( $primary_category_id, $this->site_id, $this->account_id );
		if ( $UPCEnabled == 'Required' && $autofill_missing_gtin != 'both' ) $autofill_missing_gtin = 'upc';
		if ( $EANEnabled == 'Required' && $autofill_missing_gtin != 'both' ) $autofill_missing_gtin = 'ean';
		//WPLE()->logger->info('UPCEnabled for category ID '.$primary_category_id.': '.$UPCEnabled);
		//WPLE()->logger->info('EANEnabled for category ID '.$primary_category_id.': '.$EANEnabled);

		// build ProductListingDetails
        $tplModel              = new TemplatesModel();
		$ProductListingDetails = new ProductListingDetailsType();
		$has_details           = false;

		// set UPC from product - if provided
		if ( $product_upc = get_post_meta( $product_id, '_ebay_upc', true ) ) {
		    $product_upc = $tplModel->processAttributeShortcodes( $product_id, $product_upc );
		    $product_upc = $tplModel->processCustomMetaShortcodes( $product_id, $product_upc );

            $ProductListingDetails->setUPC( $product_upc );
            $has_details = true;
        } elseif ( $product_sku && ( $this->profile_details['use_sku_as_upc'] == '1' ) ) {
		    // Set UPC from SKU
            $ProductListingDetails->setUPC( $product_sku );
            $has_details = true;
		} elseif ( ( $autofill_missing_gtin == 'upc' || $autofill_missing_gtin == 'both' )  && ! $hasVariations ) {
			$ProductListingDetails->setUPC( $DoesNotApplyText );
			$has_details = true;
		}

		// set EAN from product - if provided
        if ( !$hasVariations ) {
            if ( $product_ean = get_post_meta( $product_id, '_ebay_ean', true ) ) {
                $product_ean = $tplModel->processAttributeShortcodes( $product_id, $product_ean );
                $product_ean = $tplModel->processCustomMetaShortcodes( $product_id, $product_ean );


                $ProductListingDetails->setEAN( $product_ean );
                $has_details = true;
            } elseif ( $product_sku && ( @$this->profile_details['use_sku_as_ean'] == '1' ) ) {
                // Set EAN from SKU
                $ProductListingDetails->setEAN( $product_sku );
                $has_details = true;
            } elseif ( class_exists( 'WPM_Product_GTIN_WC' ) && $product_ean = get_post_meta( $product_id, '_wpm_gtin_code', true ) ) {
                // Support for the Product GTIN plugin (https://wordpress.org/plugins/product-gtin-ean-upc-isbn-for-woocommerce/) #39320
                $ProductListingDetails->setEAN( $product_ean );
                $has_details = true;
            } elseif ( $autofill_missing_gtin == 'ean' || $autofill_missing_gtin == 'both' ) {
                $ProductListingDetails->setEAN( $DoesNotApplyText );
                $has_details = true;
            }
        }

		// set ISBN from product - if provided
		if ( $product_isbn = get_post_meta( $product_id, '_ebay_isbn', true ) ) {
            $product_isbn = $tplModel->processAttributeShortcodes( $product_id, $product_isbn );
            $product_isbn = $tplModel->processCustomMetaShortcodes( $product_id, $product_isbn );

			$ProductListingDetails->setISBN( $product_isbn );
			$has_details = true;
		}

		// set EPID from product - if provided
		if ( $product_epid = get_post_meta( $product_id, '_ebay_epid', true ) ) {
            $product_epid = $tplModel->processAttributeShortcodes( $product_id, $product_epid );
            $product_epid = $tplModel->processCustomMetaShortcodes( $product_id, $product_epid );

			$ProductListingDetails->setProductReferenceID( $product_epid );
			$has_details = true;
		}

		// set Brand/MPN from product - if provided
		$product_brand = get_post_meta( $parent_id, '_ebay_brand', true );
		$product_mpn   = get_post_meta( $product_id,   '_ebay_mpn',   true );

        if ( $product_sku && ( @$this->profile_details['use_sku_as_mpn'] == '1' ) ) {
            $product_mpn = $product_sku;
        }
		if ( $product_brand && $product_mpn ) {
            $product_brand = $tplModel->processAttributeShortcodes( $product_id, $product_brand );
            $product_brand = $tplModel->processCustomMetaShortcodes( $product_id, $product_brand );

            $product_mpn = $tplModel->processAttributeShortcodes( $product_id, $product_mpn );
            $product_mpn = $tplModel->processCustomMetaShortcodes( $product_id, $product_mpn );

			// Note: MPN is always paired with Brand for single-variation listings,
			// but for multiple-variation listings, only the Brand value should be specified in the BrandMPN container
			// and the MPN for each product variation will be specified through a VariationSpecifics.NameValueList container.
			// (the above might be wrong - submitting a BrandMPN container without MPN set results in error 37...)
			$ProductListingDetails->BrandMPN = new BrandMPNType();
			$ProductListingDetails->BrandMPN->setBrand( $product_brand );

			if ( $product_mpn ) {
				$ProductListingDetails->BrandMPN->setMPN( $product_mpn );
			}

			$has_details = true;
		} elseif ( $this->listing->getDuration() == 'GTC' && !$product_brand && !$product_mpn ) {
		    // For GTC listings, brand and MPN cannot be both empty! #17790
            $ProductListingDetails->BrandMPN = new BrandMPNType();
            $ProductListingDetails->BrandMPN->setBrand( 'Unbranded' );
            $ProductListingDetails->BrandMPN->setMPN( 'Does not apply' );

            $has_details = true;
        }

		// include prefilled info (default) - if enabled in profile
		$include_prefilled_info = isset( $this->profile_details['include_prefilled_info'] ) ? (bool)$this->profile_details['include_prefilled_info'] : true;

		// Set IncludePrefilledItemInformation to pass it on to eBay even if $include_prefilled_info is false #16018
		// $ProductListingDetails->setIncludePrefilledItemInformation( $include_prefilled_info ? 1 : 0 ); // does not exist in API version 1045

		if ( $include_prefilled_info ) {
            $ProductListingDetails->setIncludeeBayProductDetails( 'true' ); // #52060
			$ProductListingDetails->setUseFirstProduct( true );
			$ProductListingDetails->setIncludeStockPhotoURL( true );
			//$ProductListingDetails->setIncludePrefilledItemInformation( $include_prefilled_info ? 1 : 0 );
			// $ProductListingDetails->setUseStockPhotoURLAsGallery( true );
		} else {
            $ProductListingDetails->setIncludeeBayProductDetails( 'false' );  // #52060
        }

		// only set ProductListingDetails if at least one product ID is set
		if ( $has_details ) {
			$this->item->setProductListingDetails( $ProductListingDetails );
			// WPLE()->logger->info("buildProductListingDetails: " . print_r($item->getProductListingDetails(),1) );
		}

	} /* end of buildProductListingDetails() */


	/**
	 * Set item properties based on data from the WC Product
	 */
	public function buildProductOptions() {
		$product_id = $this->product_id;
		$hasVariations   = ProductWrapper::hasVariations( $this->listing->getProductId() );
		$isVariation     = ProductWrapper::isSingleVariation( $this->listing->getProductId() );

		// get product SKU
		$product_sku = ProductWrapper::getSKU( $product_id );

		// if this is a single split variation, use variation SKU instead of parent SKU
		if ( $isVariation ) {
			$product_sku = ProductWrapper::getSKU( $this->listing->getProductId() );
		}

		// set SKU - if not empty
		if ( trim( $product_sku ) == '' ) $product_sku = false;
		if ( $product_sku ) {
			$this->item->setSKU( $product_sku );
		}

		// build buildProductListingDetails (UPC, EAN, MPN, etc.)
		//$item = $this->buildProductListingDetails( $id, $item, $post_id, $profile_details, $listing, $hasVariations, $isVariation, $product_sku );
		$this->buildProductListingDetails( $product_sku );

		// add subtitle if enabled
		if ( @$this->profile_details['subtitle_enabled'] == 1 ) {

			// check if custom post meta field '_ebay_subtitle' exists
			if ( get_post_meta( $product_id, '_ebay_subtitle', true ) ) {
				$subtitle = get_post_meta( $product_id, '_ebay_subtitle', true );
			} elseif ( get_post_meta( $product_id, 'ebay_subtitle', true ) ) {
				$subtitle = get_post_meta( $product_id, 'ebay_subtitle', true );
			} else {
				// check for custom subtitle from profile
				$subtitle = @$this->profile_details['custom_subtitle'];
			}

			// if empty use product excerpt
			if ( $subtitle == '' && apply_filters( 'wple_use_excerpt_as_subtitle', true ) ) {
				$the_post = get_post( $product_id );
				$subtitle = strip_tags( $the_post->post_excerpt );
			}

			if ( !empty( $subtitle ) ) {
                // limit to 55 chars to avoid error
                // decode HTML characters so they are sent to eBay in its normal form #23656
                $subtitle = html_entity_decode( $subtitle, ENT_QUOTES, 'UTF-8' );
                $subtitle = mb_substr( $subtitle, 0, 55 );

                $this->item->setSubTitle( $subtitle );
                WPLE()->logger->debug( 'setSubTitle: '.$subtitle );
            }

		}

		// item condition description
		$condition_description = false;
		if ( @$this->profile_details['condition_description'] != '' && $this->item->getPrimaryCategory() && !in_array( $this->item->getPrimaryCategory()->getCategoryID(), EbayCategoriesModel::getTradingCardsCategories() ) ) {
			$condition_description =  $this->profile_details['condition_description'];
			$templatesModel = new TemplatesModel();
			$condition_description = $templatesModel->processAllTextShortcodes( $product_id, $condition_description );
			$this->item->setConditionDescription( $condition_description );
		}

	} /* end of buildProductOptions() */

    /**
     * Set listing properties based on the profile data
     */
	public function buildProfileOptions() {

		// Set Local Info
		$this->item->Currency        = $this->profile_details['currency'];
		$this->item->Country         = $this->profile_details['country'];
		$this->item->Location        = $this->profile_details['location'];
		$this->item->DispatchTimeMax = $this->profile_details['dispatch_time'];

		// disable GetItFast if dispatch time does not allow it - fixes revising imported items
		if ( intval($this->profile_details['dispatch_time']) > 1 ) {
			$this->item->setGetItFast( 0 );
		}

		// item condition
		if ( $this->profile_details['condition_id'] != 'none' && !empty( $this->profile_details['condition_id'] ) ) {
			$this->item->setConditionID( $this->profile_details['condition_id'] );

			// For "Ungraded" trading cards, we'll need to include the ConditionDescriptorType to the request #62252
			if ( $this->item->getPrimaryCategory() && in_array($this->item->getPrimaryCategory()->getCategoryID(), EbayCategoriesModel::getTradingCardsCategories() ) ) {
				$conditionDescContainer = new ConditionDescriptorsType();
				if ( $this->item->getConditionID() == 4000 ) {
					
					$conditionType = new ConditionDescriptorType();
					$conditionType->setName('40001');
					$conditionType->setValue($this->profile_details['condition_description']);
					$conditionDescContainer->addConditionDescriptor( $conditionType );
					//$item->setConditionDescriptors( $conditionDescContainer );
					$this->item->ConditionDescriptors = $conditionDescContainer;
				} elseif ( $this->item->getConditionID() == 2750 ) {
					// Professional Grader
					if ( !empty( $this->profile_details['professional_grader'] ) ) {
						$conditionType = new ConditionDescriptorType();
						$conditionType->setName('27501');
						$conditionType->setValue($this->profile_details['professional_grader']);
						$conditionDescContainer->addConditionDescriptor( $conditionType );
					}

					// Grade
					if ( !empty( $this->profile_details['grade'] ) ) {
						$conditionType = new ConditionDescriptorType();
						$conditionType->setName('27502');
						$conditionType->setValue($this->profile_details['grade']);
						$conditionDescContainer->addConditionDescriptor( $conditionType );
					}

					// Certification Number
					if ( !empty( $this->profile_details['certification_number'] ) ) {
						$conditionType = new ConditionDescriptorType();
						$conditionType->setName('27503');
						$conditionType->setAdditionalInfo( $this->profile_details['certification_number'] );
						$conditionDescContainer->addConditionDescriptor( $conditionType );
					}

				}
				$this->item->ConditionDescriptors = $conditionDescContainer;
			}
		}

		// postal code
		if ( $this->profile_details['postcode'] != '' ) {
			$this->item->setPostalCode( $this->profile_details['postcode'] );
		}

		// handle VAT (percent)
		if ( $this->profile_details['tax_mode'] == 'fix' ) {
			$this->item->VATDetails = new VATDetailsType();
			$this->item->VATDetails->VATPercent = self::dbSafeFloatval( $this->profile_details['vat_percent'] );
		}

		// handle B2B option
		if ( @$this->profile_details['b2b_only'] == 1 ) {
			if ( $this->item->getVATDetails() == null ) $this->item->VATDetails = new VATDetailsType();
			$this->item->VATDetails->BusinessSeller = true;
			$this->item->VATDetails->RestrictedToBusiness = true;
		}

		// handle eBay Plus option
		if ( @$this->profile_details['ebayplus_enabled'] == 1 ) {
			$this->item->seteBayPlus( 'true' );
		} else {
		    // dont set it at all
			//$item->eBayPlus = false;
		}

		// use Sales Tax Table if enabled
        $this->item->setUseTaxTable( 0 );
        if ( $this->profile_details['tax_mode'] == 'ebay_table' ) {
			$this->item->setUseTaxTable( 1 );
		}

		// private listing - disabled as of version 2.0.34
		// "The PrivateListing field has been deprecated and removed from the WSDL with Version 1045. This field should no longer be used."
		// https://developer.ebay.com/devzone/xml/docs/reference/ebay/AddFixedPriceItem.html
		// if ( @$profile_details['private_listing'] == 1 ) {
		// 	$item->setPrivateListing( true );
		// }

		// bold title
		if ( @$this->profile_details['bold_title'] == 1 ) {
			$this->item->addListingEnhancement('BoldTitle');
		}

		// Removed in WSDL v.1311
		//$this->item->setHitCounter( $this->profile_details['counter_style'] );
		// $item->addListingEnhancement('Highlight');


		## BEGIN PRO ##

		// cross border trade / International site visibility
		if ( @$this->profile_details['cross_border_trade'] != '' ) {
			$this->item->addCrossBorderTrade( $this->profile_details['cross_border_trade'] );
		}

		## END PRO ##

	} /* end of buildProfileOptions() */


	// schedule listing
	public function buildSchedule() {

		## BEGIN PRO ##

		// schedule listing
		if ( @$this->profile_details['schedule_time'] != '' ) {

			// parse schedule time
			list( $hour, $minute ) = explode(':', $this->profile_details['schedule_time'] );
			if ( @$this->profile_details['schedule_minute'] != '' )
				$minute = $this->profile_details['schedule_minute'];

			$days_offset = @$this->profile_details['schedule_days'];

			if ( empty( $days_offset ) ) {
				$days_offset = 0;
			}

			// get the day (today or tomorrow)
			$date = gmdate('Y-m-d', time() + ( 86400 * $days_offset ) );

			// get GMT timestamp of schedule time
			$scheduled_datetime_gmt = gmdate('U', strtotime( $date.' '.$hour.':'.$minute.':00' ));
			$current_datetime_gmt = gmdate('U', time() );

			// check if scheduled time has already passed
			if ( $scheduled_datetime_gmt < $current_datetime_gmt ) {

				// add 24 hours
				$date = gmdate('Y-m-d', time() + 24 * 60 * 60 );

				// update ts
				$scheduled_datetime_gmt = gmdate('U', strtotime( $date.' '.$hour.':'.$minute.':00' ));

			}

			WPLE()->logger->info( 'Listing was scheduled in ' . human_time_diff( $current_datetime_gmt, $scheduled_datetime_gmt ) );

			// set ScheduleTime
			$ScheduleTime = $date.'T'.$hour.':'.$minute.':00.000Z';
			$this->item->setScheduleTime( $ScheduleTime );

		}

		## END PRO ##
	} /* end of buildSchedule() */


	public function buildPayment() {

		// no payment options for classified ads
		if ( $this->item->getListingType() == 'LeadGeneration' ) {
			return;
		}

		// get paypal email address
		$accounts = WPLE()->accounts;
		if ( isset( $accounts[ $this->account_id ] ) && ( $accounts[ $this->account_id ]->paypal_email ) ) {
			$PayPalEmailAddress = $accounts[ $this->account_id ]->paypal_email;
		} else {
			$PayPalEmailAddress = get_option( 'wplister_paypal_email' );
		}

		// set payment methods
		foreach ( (array)$this->profile_details['payment_options'] as $payment_method ) {
            // check to prevent fatal errors reported in #48872
            if ( !isset( $payment_method ) || empty( $payment_method['payment_name'] ) ) continue;

			# BuyerPaymentMethodCodeType
			$this->item->addPaymentMethods( $payment_method['payment_name'] );
			if ( $payment_method['payment_name'] == 'PayPal' ) {
				$this->item->PayPalEmailAddress = $PayPalEmailAddress;
			}
		}

        // handle require immediate payment option
        if ( @$this->profile_details['autopay'] == '1' ) {
			$this->item->setAutoPay( true );
        } else {
			$this->item->setAutoPay( 0 );
        }

		// ReturnPolicy - only set this if there is no Return Profile ID selected
		if ( empty( $this->profile_details['seller_return_profile_id'] ) ) {
			$this->item->ReturnPolicy = new ReturnPolicyType();
			if ( $this->profile_details['returns_accepted'] == 1 ) {
				$this->item->ReturnPolicy->ReturnsAcceptedOption = 'ReturnsAccepted';
				$this->item->ReturnPolicy->ReturnsWithinOption   = $this->profile_details['returns_within'];
				$this->item->ReturnPolicy->Description           = stripslashes( $this->profile_details['returns_description'] );

				if ( ( isset( $this->profile_details['RestockingFee'] ) ) && ( $this->profile_details['RestockingFee'] != '' ) ) {
					$this->item->ReturnPolicy->RestockingFeeValueOption = $this->profile_details['RestockingFee'];
				}

				if ( ( isset( $this->profile_details['ShippingCostPaidBy'] ) ) && ( $this->profile_details['ShippingCostPaidBy'] != '' ) ) {
					$this->item->ReturnPolicy->ShippingCostPaidByOption = $this->profile_details['ShippingCostPaidBy'];
				}

				if ( ( isset( $this->profile_details['RefundOption'] ) ) && ( $this->profile_details['RefundOption'] != '' ) ) {
					$this->item->ReturnPolicy->RefundOption = $this->profile_details['RefundOption'];
				}

			} else {
				$this->item->ReturnPolicy->ReturnsAcceptedOption = 'ReturnsNotAccepted';
			}
		}
	} /* end of buildPayment() */


	public function buildShipping() {
		$product_id = $this->product_id;
		$isVariation = ProductWrapper::isSingleVariation( $product_id );

		// no shipping options for classified ads
		if ( $this->item->getListingType() == 'LeadGeneration' ) return;

		// handle flat and calc shipping
		WPLE()->logger->info('shipping_service_type: '.$this->profile_details['shipping_service_type'] );
		// $isFlat = $profile_details['shipping_service_type'] != 'calc' ? true : false;
		// $isCalc = $profile_details['shipping_service_type'] == 'calc' ? true : false;

		// if this is a single split variation, use variation post_id instead of parent post_id for weight and dimensions
		$actual_post_id = $isVariation ? $this->listing->getProductId() : $product_id;

		// handle flat and calc shipping (new version)
		$service_type = $this->profile_details['shipping_service_type'];
		if ( $service_type == '' )     $service_type = 'Flat';
		if ( $service_type == 'flat' ) $service_type = 'Flat';
		if ( $service_type == 'calc' ) $service_type = 'Calculated';
		$isFlatLoc = ( in_array( $service_type, array('Flat','FreightFlat','FlatDomesticCalculatedInternational') ) ) ? true : false;
		$isFlatInt = ( in_array( $service_type, array('Flat','FreightFlat','CalculatedDomesticFlatInternational') ) ) ? true : false;
		$hasWeight = ( in_array( $service_type, array('Calculated','FreightFlat','FlatDomesticCalculatedInternational','CalculatedDomesticFlatInternational') ) ) ? true : false;
		$isCalcLoc = ! $isFlatLoc;
		$isCalcInt = ! $isFlatInt;

		$shippingDetails = new ShippingDetailsType();
		$shippingDetails->setShippingType( $service_type );
		WPLE()->logger->info('shippingDetails->ShippingType: '.$shippingDetails->getShippingType() );

		// local shipping options
		$localShippingOptions = (array)$this->profile_details['loc_shipping_options'];
		WPLE()->logger->debug('localShippingOptions: '.print_r($localShippingOptions,1));

        $pr = 1;
        $localShippingServices = array();
        $lastShippingCategory = '';

		// Add a ShippingService value for Freight service #58749
		if ( $service_type == 'FreightFlat' && apply_filters( 'wple_add_freight_shipping_service_value', false ) ) {
            $shipping_service = new ShippingServiceOptionsType();
            $shipping_service->setShippingService( 'Freight' );

            $localShippingServices[] = $shipping_service;
        }

		foreach ( array_filter( $localShippingOptions ) as $opt) {

			$price = $this->getDynamicShipping( $opt['price'], $product_id );
			$add_price = $this->getDynamicShipping( $opt['add_price'], $product_id );
			if ( $price == '' ) $price = 0;
			if ( $opt['service_name'] == '' ) continue;

			// Freight service must submit an empty ShippingServiceOptions container #56994
            if ( $service_type == 'FreightFlat' ) continue;

            $ShippingServiceOptions = new ShippingServiceOptionsType();
			$ShippingServiceOptions->setShippingService( $opt['service_name'] );
			$ShippingServiceOptions->setShippingServicePriority($pr);

			// set shipping costs for flat services
			if ( $isFlatLoc ) {
				$ShippingServiceOptions->setShippingServiceCost( $price );
				// FreeShipping is only allowed for the first shipping service
				if ( ( $price == 0 ) && ( $pr == 1 ) ) $ShippingServiceOptions->setFreeShipping( true );

				// price for additonal items
				if ( trim( $add_price ) == '' ) {
					$ShippingServiceOptions->setShippingServiceAdditionalCost( $price );
				} else {
					$ShippingServiceOptions->setShippingServiceAdditionalCost( $add_price );
				}
			} else {
				// enable FreeShipping option for calculated shipping services if specified in profile (or product meta)
				$free_shipping_enabled = isset( $this->profile_details['shipping_loc_enable_free_shipping'] ) ? $this->profile_details['shipping_loc_enable_free_shipping'] : false;
				// $free_shipping_enabled = $free_shipping_enabled || get_post_meta( $post_id, '_ebay_shipping_loc_enable_free_shipping', true );
				if ( ( $free_shipping_enabled ) && ( $pr == 1 ) ) $ShippingServiceOptions->setFreeShipping( true );
			}

			$localShippingServices[] = $ShippingServiceOptions;
			$pr++;

			$EbayShippingModel = new EbayShippingModel();
			$lastShippingCategory = $EbayShippingModel->getShippingCategoryByServiceName( $opt['service_name'] );
			WPLE()->logger->debug('ShippingCategory: '.print_r($lastShippingCategory,1));
		}
		// apply filter and set shipping services
		$localShippingServices = apply_filters( 'wple_local_shipping_services', $localShippingServices, $product_id, $actual_post_id, $this->listing );
		$shippingDetails->setShippingServiceOptions( $localShippingServices, null );


		// $intlShipping = array(
		// 	'UK_RoyalMailAirmailInternational' => array (
		// 		'Europe' => 1,
		// 		'Worldwide' => 1.50
		// 	),
		// 	'UK_RoyalMailInternationalSignedFor' => array (
		// 		'Europe' => 5,
		// 	)
		// );
		$intlShipping = $this->profile_details['int_shipping_options'];
		if ( empty( $intlShipping ) ) $intlShipping = array();
		WPLE()->logger->debug('intlShipping: '.print_r($intlShipping,1));

		$pr = 1;
		$shippingInternational = array();
		foreach ($intlShipping as $opt) {
		    if ( !is_array( $opt ) || empty( $opt ) ) continue;

		    // foreach ($opt as $loc=>$price) {
				$price = $this->getDynamicShipping( $opt['price'], $product_id );
				$add_price = $this->getDynamicShipping( $opt['add_price'], $product_id );
				// if ( ( $price == '' ) || ( $opt['service_name'] == '' ) ) continue;
				if ( $price                == '' ) $price = 0;
				if ( @$opt['location']     == '' ) continue;
				if ( @$opt['service_name'] == '' ) continue;

				$InternationalShippingServiceOptions = new InternationalShippingServiceOptionsType();
				$InternationalShippingServiceOptions->setShippingService( $opt['service_name'] );
				$InternationalShippingServiceOptions->setShippingServicePriority($pr);
				// $InternationalShippingServiceOptions->setShipToLocation( $opt['location'] );
				if ( is_array( $opt['location'] ) ) {
					foreach ( $opt['location'] as $location ) {
						$InternationalShippingServiceOptions->addShipToLocation( $location );
					}
				} else {
					$InternationalShippingServiceOptions->setShipToLocation( $opt['location'] );
				}

				$InternationalShippingServiceOptions->setShipToLocation( $opt['location'] );

				// set shipping costs for flat services
				if ( $isFlatInt ) {
					$InternationalShippingServiceOptions->setShippingServiceCost( $price );
					if ( trim( $add_price ) == '' ) {
						$InternationalShippingServiceOptions->setShippingServiceAdditionalCost( $price );
					} else {
						$InternationalShippingServiceOptions->setShippingServiceAdditionalCost( $add_price );
					}
				}
				$shippingInternational[] = $InternationalShippingServiceOptions;
				$pr++;
			// }
		}

		// filter international shipping services
		$shippingInternational = apply_filters( 'wple_international_shipping_services', $shippingInternational, $product_id, $actual_post_id, $this->listing );

		// only set international shipping if $intlShipping array contains one or more valid items
		if ( isset( $intlShipping[0]['service_name'] ) && ( $intlShipping[0]['service_name'] != '' ) )
			$shippingDetails->setInternationalShippingServiceOption( $shippingInternational, null );


		// set CalculatedShippingRate
		if ( $isCalcLoc || $isCalcInt ) {
			$calculatedShippingRate = new CalculatedShippingRateType();

			// deprecated
			//$calculatedShippingRate->setOriginatingPostalCode( $profile_details['postcode'] );

            if ( $isCalcLoc ) {
                $calculatedShippingRate->setPackagingHandlingCosts( self::dbSafeFloatval( @$this->profile_details['PackagingHandlingCosts'] ) );
            }
            if ( $isCalcInt ) {
                // $calculatedShippingRate->setPackagingHandlingCosts( self::dbSafeFloatval( @$profile_details['PackagingHandlingCosts'] ) );
                $calculatedShippingRate->setInternationalPackagingHandlingCosts( self::dbSafeFloatval( @$this->profile_details['InternationalPackagingHandlingCosts'] ) );
            }

            /**
             * Commented out because in the latest EbatNS, shipping packages are to be defined in ShipPackageDetailsType

			// set ShippingPackage if calculated shipping is used
			//if ( $isCalcInt ) $calculatedShippingRate->setShippingPackage( $profile_details['shipping_package'] );
			//if ( $isCalcLoc ) $calculatedShippingRate->setShippingPackage( $profile_details['shipping_package'] );



			list( $weight_major, $weight_minor ) = ProductWrapper::getEbayWeight( $actual_post_id );
			$calculatedShippingRate->setWeightMajor( self::dbSafeFloatval( $weight_major) );
			$calculatedShippingRate->setWeightMinor( self::dbSafeFloatval( $weight_minor) );

			$dimensions = ProductWrapper::getDimensions( $actual_post_id );
			if ( trim( @$dimensions['width']  ) != '' ) $calculatedShippingRate->setPackageWidth( $dimensions['width'] );
			if ( trim( @$dimensions['length'] ) != '' ) $calculatedShippingRate->setPackageLength( $dimensions['length'] );
			if ( trim( @$dimensions['height'] ) != '' ) $calculatedShippingRate->setPackageDepth( $dimensions['height'] );
             */

            $calculatedShippingRate = apply_filters_deprecated( 'wplister_item_shipping_rate', array($calculatedShippingRate, $actual_post_id, [], $this->item, $this->profile_details), '2.8.4', 'wple_item_shipping_rate' );
            $calculatedShippingRate = apply_filters( 'wple_item_shipping_rate', $calculatedShippingRate, $actual_post_id, [], $this->item, $this->profile_details );

			$shippingDetails->setCalculatedShippingRate( $calculatedShippingRate );
		}

		// handle option to always send weight and dimensions
		if ( get_option( 'wplister_send_weight_and_size', 'default' ) == 'always' ) {
			$hasWeight = ProductWrapper::getWeight( $actual_post_id );
		}

		// set ShippingPackageDetails
		if ( $hasWeight ) {
			$shippingPackageDetails = new ShipPackageDetailsType();

			// set ShippingPackage if calculated shipping is used
			if ( $isCalcInt ) $shippingPackageDetails->setShippingPackage( $this->profile_details['shipping_package'] );
			if ( $isCalcLoc ) $shippingPackageDetails->setShippingPackage( $this->profile_details['shipping_package'] );
			if ( $isFlatInt && !empty( $this->profile_details['shipping_package'] ) ) $shippingPackageDetails->setShippingPackage( $this->profile_details['shipping_package'] );
			if ( $isFlatLoc && !empty( $this->profile_details['shipping_package'] ) ) $shippingPackageDetails->setShippingPackage( $this->profile_details['shipping_package'] );

			list( $weight_major, $weight_minor ) = ProductWrapper::getEbayWeight( $actual_post_id );
			$shippingPackageDetails->setWeightMajor( self::dbSafeFloatval( $weight_major) );
			$shippingPackageDetails->setWeightMinor( self::dbSafeFloatval( $weight_minor) );

			$dimensions = ProductWrapper::getDimensions( $actual_post_id );
			if ( trim( @$dimensions['width']  ) != '' ) $shippingPackageDetails->setPackageWidth( $dimensions['width'] );
			if ( trim( @$dimensions['length'] ) != '' ) $shippingPackageDetails->setPackageLength( $dimensions['length'] );
			if ( trim( @$dimensions['height'] ) != '' ) $shippingPackageDetails->setPackageDepth( $dimensions['height'] );

			// debug
			// $weight = ProductWrapper::getWeight( $actual_post_id ) ;
			// WPLE()->logger->info('weight: '.print_r($weight,1));
			// WPLE()->logger->info('dimensions: '.print_r($dimensions,1));

			$this->item->setShippingPackageDetails( $shippingPackageDetails );
		}


		// set local shipping discount profile
		if ( $isFlatLoc ) {
			$local_profile_id = $this->profile_details['shipping_loc_flat_profile'] ?? false;
			if ( $custom_profile_id = get_post_meta( $product_id, '_ebay_shipping_loc_flat_profile', true ) ) $local_profile_id = $custom_profile_id;
		} else {
			$local_profile_id = $this->profile_details['shipping_loc_calc_profile'] ?? false;
			if ( $custom_profile_id = get_post_meta( $product_id, '_ebay_shipping_loc_calc_profile', true ) ) $local_profile_id = $custom_profile_id;
		}
		if ( $local_profile_id ) {
			$shippingDetails->setShippingDiscountProfileID( $local_profile_id );
		}

		// set international shipping discount profile
		if ( $isFlatLoc ) {
			$int_profile_id = $this->profile_details['shipping_int_flat_profile'] ?? false;
			if ( $custom_profile_id = get_post_meta( $product_id, '_ebay_shipping_int_flat_profile', true ) ) $int_profile_id = $custom_profile_id;
		} else {
			$int_profile_id = $this->profile_details['shipping_int_calc_profile'] ?? false;
			if ( $custom_profile_id = get_post_meta( $product_id, '_ebay_shipping_int_calc_profile', true ) ) $int_profile_id = $custom_profile_id;
		}
		if ( $int_profile_id ) {
			$shippingDetails->setInternationalShippingDiscountProfileID( $int_profile_id );
		}

		// PromotionalShippingDiscount
		$PromotionalShippingDiscount = $this->profile_details['PromotionalShippingDiscount'] ?? false;
		if ( $PromotionalShippingDiscount == '1' )
			$shippingDetails->setPromotionalShippingDiscount( true );

		// InternationalPromotionalShippingDiscount
		$InternationalPromotionalShippingDiscount = $this->profile_details['InternationalPromotionalShippingDiscount'] ?? false;
		if ( $InternationalPromotionalShippingDiscount == '1' )
			$shippingDetails->setInternationalPromotionalShippingDiscount( true );


		// ShipToLocations
		if ( is_array( $ShipToLocations = maybe_unserialize( $this->profile_details['ShipToLocations'] ?? false ) ) ) {
			foreach ( $ShipToLocations as $location ) {
				$this->item->addShipToLocations( $location );
			}
		}

		// ExcludeShipToLocations
		if ( is_array( $ExcludeShipToLocations = maybe_unserialize( $this->profile_details['ExcludeShipToLocations'] ?? false ) ) ) {
			foreach ( $ExcludeShipToLocations as $location ) {
				$shippingDetails->addExcludeShipToLocation( $location );
			}
		}

		// global shipping
		if ( @$this->profile_details['global_shipping'] == 1 ) {
			$shippingDetails->setGlobalShipping( true ); // available since api version 781
		}
		if ( get_post_meta( $product_id, '_ebay_global_shipping', true ) == 'yes' ) {
			$shippingDetails->setGlobalShipping( true );
		}

		// store pickup
		if ( @$this->profile_details['store_pickup'] == 1 ) {
			$this->item->PickupInStoreDetails = new PickupInStoreDetailsType();
			$this->item->PickupInStoreDetails->setEligibleForPickupInStore( true );
		}

		// Payment Instructions
		if ( trim( @$this->profile_details['payment_instructions'] ) != '' ) {
			$shippingDetails->setPaymentInstructions( nl2br( $this->profile_details['payment_instructions'] ) );
		}
		if ( trim( get_post_meta( $product_id, '_ebay_payment_instructions', true ) ) != '' ) {
			$shippingDetails->setPaymentInstructions( nl2br( get_post_meta( $product_id, '_ebay_payment_instructions', true ) ) );
		}

		// COD cost
		if ( isset( $this->profile_details['cod_cost'] ) && trim( $this->profile_details['cod_cost'] ) ) {
			$shippingDetails->setCODCost( str_replace( ',', '.', $this->profile_details['cod_cost'] ) );
		}

		// check if we have local pickup only
		if ( ( count($localShippingOptions) == 1 ) && ( $lastShippingCategory == 'PICKUP' ) ) {

			$this->item->setShipToLocations( 'None' );
			$this->item->setDispatchTimeMax( null );
			WPLE()->logger->info('PICKUP ONLY mode');

			// don't set ShippingDetails for pickup-only in UK!
			if ( $this->item->getSite() != 'UK' ) {
				$this->item->setShippingDetails($shippingDetails);
			}

		} else {
			$this->item->setShippingDetails($shippingDetails);
		}

		// force AutoPay off for Freight shipping
		if ( $service_type == 'FreightFlat' ) {
			$this->item->setAutoPay( 0 );
		}

	} /* end of buildShipping() */

	public function buildItemSpecifics() {
        $tm = new TemplatesModel();
		$profile_data = $this->listing->getProfileDetails();

        /**
         * Convert certain item specific names to pass eBay validation #59486
         */
        $name_translations = apply_filters( 'wple_item_specific_name_translations', [
            'Merk'  => 'Brand'
        ], $this->listing_id, $this->item, $this->listing, $this->product_id );

    	// new ItemSpecifics
    	$ItemSpecifics = new NameValueListArrayType();

		// get listing data
		// $listing = ListingsModel::getItem( $id );

        $convert_attributes_mode = get_option( 'wplister_convert_attributes_mode', 'all' );

		// get product attributes
		$processed_attributes = array();
        $attributes = ProductWrapper::getAttributes( $this->product_id );
		WPLE()->logger->info('product attributes: '. ( sizeof($attributes)>0 ? print_r($attributes,1) : '-- empty --' ) );

        // Account locale for i18n
        $locale = WPLE_eBayAccount::getAccountLocale( $this->account_id );

		// apply item specifics from profile
		$specifics = $profile_data['item_specifics'];

		// merge item specifics from product
		$product_specifics = get_post_meta( $this->product_id, '_ebay_item_specifics', true );
		if ( ! empty($product_specifics) ) {
			$specifics = array_merge( (array)$product_specifics, (array)$specifics );
		}

		// WPLE()->logger->info('item_specifics: '.print_r($specifics,1));
		// WPLE()->logger->debug('get variationAttributes: '.print_r($this->variationAttributes,1));
		// WPLE()->logger->debug('get variationSplitAttributes: '.print_r($this->variationSplitAttributes,1));
        $added_specs = array();
        foreach ((array)$specifics as $spec) {
            if ( !is_array( $spec ) || !isset( $spec['name'] ) ) {
                WPLE()->logger->info( 'Skipping invalid specific: '. print_r( $spec, 1 ) );
                continue;
            }

            // run translations for the attribute name
            if ( array_key_exists( $spec['name'], $name_translations ) ) {
                $spec['name'] = $name_translations[ $spec['name'] ];
            }
            
            if ( in_array( $spec['name'], $added_specs ) ) {
                WPLE()->logger->info( 'Skipping duplicate specific: '. $spec['name'] );
                continue;
            }

        	if ( $spec['value'] != '' ) {

        		// fixed value
        		$value = stripslashes( $spec['value'] );
        		$value = html_entity_decode( $value, ENT_QUOTES );

        		// Pull from postmeta #38685
                if ( preg_match_all("/\\[meta_(.*)\\]/uUsm", $value, $matches ) ) {
                    foreach ( $matches[1] as $meta_name ) {

                        $meta_value = get_post_meta( $this->product_id, $meta_name, true );
                        // $meta_value = wpautop( $meta_value ); // might do more harm than good

                        // Allow 3rd-party code to disable the nl2br in the postmeta value
                        $nl2br_value = apply_filters_deprecated( 'wplister_meta_nl2br_value', array(true), '2.8.4', 'wple_meta_nl2br_value' );
                        $nl2br_value = apply_filters( 'wple_meta_nl2br_value', $nl2br_value );

                        if ( $nl2br_value ) {
                            $meta_value = nl2br( $meta_value ); // nl2br() is required for WYSIWYG fields by Advanced Custom Field plugin (and probably others)
                        }

                        $meta_value = apply_filters_deprecated( 'wplister_meta_shortcode_value', array($meta_value, $meta_name, $this->product_id), '2.8.4', 'wple_meta_shortcode_value' );
                        $meta_value = apply_filters( 'wple_meta_shortcode_value', $meta_value, $meta_name, $this->product_id );
                        $meta_value = html_entity_decode( $meta_value ); // Decode HTML entities that have been encoded by update_post_meta #25722
                        $processed_html = str_replace( '[meta_'.$meta_name.']', $meta_value,  $value );

                        $value = $processed_html;

                    }
                }

                // process template shortcodes in item specifics custom values
				$value = $tm->processAllTextShortcodes( $this->product_id, $value );

                /**
                 * Move this check further down to allow for the $value to be processed first #59704
                 */
				// skip values longer than 65 characters
        		//if ( $this->mb_strlen( $value ) > 65 ) continue;

                // qTranslate support
                if ( function_exists( 'qtranxf_use' ) ) {
                    $spec['name']   = qtranxf_use( $locale, $spec['name'] );
                    $value          = qtranxf_use( $locale, $value );
                }

                // support for multi value attributes
                // $value = 'blue|red|green';
                if ( apply_filters( 'wple_attribute_comma_separated_values', true ) ) {
                    $value = str_replace( ',', '|', $value );
                }

                $values = explode('|', $value);

	            $NameValueList = new NameValueListType();
		    	$NameValueList->setName ( $spec['name']  );

		    	foreach ( $values as $value ) {
                    // skip values longer than 65 characters
                    if ( $this->mb_strlen( $value ) > 65 ) {
                        WPLE()->logger->info( 'Skipping value due to its length' );
                        WPLE()->logger->debug( $value );
                        continue;
                    }

                    $NameValueList->addValue( $value );
                }

	        	if ( ! in_array( $spec['name'], $this->variationAttributes ) ) {
		        	$ItemSpecifics->addNameValueList( $NameValueList );
		        	$processed_attributes[] = $spec['name'];
					WPLE()->logger->info("specs: added custom value: {$spec['name']} - $value");
	        	}

                $added_specs[] = $spec['name'];

        	} elseif ( $spec['attribute'] != '' ) {

        		// pull value from product attribute
        		$value = $attributes[ $spec['attribute'] ] ?? '';
        		$value = html_entity_decode( $value, ENT_QUOTES );

        		// process custom attributes
        		$custom_attributes = apply_filters_deprecated( 'wplister_custom_attributes', array(array()), '2.8.4', 'wple_custom_attributes' );
        		$custom_attributes = apply_filters( 'wple_custom_attributes', $custom_attributes );
        		foreach ( $custom_attributes as $attrib ) {
        			if ( $spec['attribute'] == $attrib['id'] ) {

        				// pull value from attribute
        				if ( isset( $attrib['meta_key'] ) ) {
	        				$value = get_post_meta( $this->product_id, $attrib['meta_key'], true );

	        				// for split variations, check for value on variation level
	        				if ( $this->product_id != $this->listing->getProductId() ) {
	        					$variation_value = get_post_meta( $this->listing->getProductId(), $attrib['meta_key'], true );
								if ( $variation_value ) $value = $variation_value;
								// WPLE()->logger->info("specs: variation_value for: {$spec['name']} - " . $variation_value );
	        				}
        				}

        				// set fixed value (since 2.0.9.5)
        				if ( isset( $attrib['value'] ) ) {
	        				$value = $attrib['value'];
        				}

        				// use callback (since 2.0.9.6)
        				if ( isset( $attrib['callback'] ) && is_callable( $attrib['callback'] ) ) {
	        				$value = call_user_func( $attrib['callback'], $this->product_id, $this->listing_id );
        				}

        			}
        		}
        		// if ( '_sku' == $spec['attribute'] ) $value = ProductWrapper::getSKU( $post_id );

        		// handle variation attributes for a single split variation
        		// instead of listing all values, use the correct attribute value from variationSplitAttributes
        		if ( array_key_exists( $spec['attribute'], $this->variationSplitAttributes ) ) {
        			$value = $this->variationSplitAttributes[ $spec['attribute'] ];
        		}

        		// skip empty values
        		if ( ! $value ) {
					WPLE()->logger->info("specs: skipped empty product attribute: {$spec['name']} - " . $value );
        			continue;
        		}

                // qTranslate support
                if ( function_exists( 'qtranxf_use' ) ) {
                    $spec['name']   = qtranxf_use( $locale, $spec['name'] );
                    $value          = qtranxf_use( $locale, $value );
                }

	            $NameValueList = new NameValueListType();
		    	$NameValueList->setName ( $spec['name'] );
	    		// $NameValueList->setValue( $value );

	    		// support for multi value attributes
	    		// $value = 'blue|red|green';
	    		$values = explode('|', $value);
	    		foreach ($values as $value) {
	        		if ( $this->mb_strlen( $value ) > 65 ) continue;
		    		$NameValueList->addValue( $value );

                    if ( $convert_attributes_mode == 'single' ) break; // only use first value in 'single' mode
	    		}

	        	if ( ! in_array( $spec['name'], $this->variationAttributes ) ) {
		        	$ItemSpecifics->addNameValueList( $NameValueList );
		        	$processed_attributes[] = $spec['attribute'];
                    $processed_attributes[] = $spec['name']; // Need to store the name as well to make sure the unit info meta doesn't get re-added/duplicated #47608
					WPLE()->logger->info("specs: added product attribute: {$spec['name']} - " . join(', ',$values) );
	        	}

	        	$added_specs[] = $spec['name'];
        	}
        } // foreach $specifics

        // skip if item has no attributes
        // if ( count($attributes) == 0 ) return $item;

        // get excluded attributes and merge with processed attributes
        $excluded_attributes  = $this->getExcludedAttributes();
        $excluded_profile_attributes = $this->getProfileExcludedAttributes( $this->listing );
		$processed_attributes = apply_filters_deprecated( 'wplister_item_specifics_processed_attributes', array(array_merge( $processed_attributes, $excluded_attributes, $excluded_profile_attributes ), $this->item, $this->listing), '2.8.4', 'wple_item_specifics_processed_attributes' );
		$processed_attributes = apply_filters( 'wple_item_specifics_processed_attributes', $processed_attributes, $this->item, $this->listing );

    	// add ItemSpecifics from product attributes - if enabled
        foreach ($attributes as $name => $value) {

    		$value = html_entity_decode( $value, ENT_QUOTES );
    		if ( $this->mb_strlen( $value ) > 65 ) continue;
			if ( $convert_attributes_mode == 'none' ) continue;

            // run translations for the attribute name
            if ( array_key_exists( $name, $name_translations ) ) {
                $name = $name_translations[ $name ];
            }

    		// handle variation attributes for a single split variation
    		// instead of listing all values, use the correct attribute value from variationSplitAttributes
    		if ( array_key_exists( $name, $this->variationSplitAttributes ) ) {
    			$value = $this->variationSplitAttributes[ $name ];
    		}

            // qTranslate support
            if ( function_exists( 'qtranxf_use' ) ) {
                $name = qtranxf_use( $locale, $name );
            }

	        $name = apply_filters( 'wple_item_specifics_attribute_name', $name, $this );

            $NameValueList = new NameValueListType();
	    	$NameValueList->setName ( $name  );

    		// support for multi value attributes
    		// $value = 'blue|red|green';
    		$values = explode('|', $value);
    		foreach ($values as $value) {
                if ( function_exists( 'qtranxf_use' ) ) {
                    $value = qtranxf_use( $locale, $value );
                }

                $value = $this->processSizeMapReplacements( $name, $value, $this->profile_details );

	    		$NameValueList->addValue( $value );
	    		if ( $convert_attributes_mode == 'single' ) break; // only use first value in 'single' mode
    		}

        	// only add attribute to ItemSpecifics if not already present in variations or processed attributes
        	if ( ( ! in_array( $name, $this->variationAttributes ) ) && ( ! in_array( $name, $processed_attributes ) ) ) {
	        	$ItemSpecifics->addNameValueList( $NameValueList );
	        	$processed_attributes[] = $name;
				WPLE()->logger->info("attrib: added product attribute: {$name} - " . join(', ',$values) );
        	}
        }

        // include the MPN, if set
        if ( !$this->listing->getVariations() && !in_array( 'MPN', $added_specs ) && ( $product_mpn = get_post_meta( $this->product_id, '_ebay_mpn', true ) ) ) {
            $product_mpn = $tm->processAllTextShortcodes( $this->product_id, $product_mpn );
            $NameValueList = new NameValueListType();
            $NameValueList->setName ( 'MPN' );
            $NameValueList->setValue( $product_mpn );
            $ItemSpecifics->addNameValueList( $NameValueList );
        }

        $unit_quantity  = false;
        $unit_type      = false;

        // Add UnitInfo from WC Germanized #32412
        // reference https://developer.ebay.com/devzone/shopping/docs/callref/types/UnitInfoType.html
        // reference https://ebaydts.com/eBayKBDetails?KBid=2202
        if ( $this->is_plugin_active( 'woocommerce-germanized/woocommerce-germanized.php' ) ) {
            $unit_quantity = get_post_meta( $this->product_id, '_unit_product', true );
            $unit_type = get_post_meta( $this->product_id, '_unit', true );

            // Seems like we need to convert . to , for the unit_quantity
            $unit_quantity = str_replace( '.', ',', $unit_quantity );
        }

        // And add support for the German Market plugin as well #34774
        if ( $this->is_plugin_active( 'woocommerce-german-market/WooCommerce-German-Market.php' ) ) {
            $unit_quantity = get_post_meta( $this->product_id, '_unit_regular_price_per_unit_mult', true );
            $unit_type = get_post_meta( $this->product_id, '_unit_regular_price_per_unit', true );

            // Seems like we need to convert . to , for the unit_quantity
            $unit_quantity = str_replace( '.', ',', $unit_quantity );
        }

        // Added filters for #43788
        $unit_type      = apply_filters( 'wple_item_unit_type', $unit_type, $this->listing_id, $this->item, $this->listing, $this->product_id );
        $unit_quantity  = apply_filters( 'wple_item_unit_quantity', $unit_quantity, $this->listing_id, $this->item, $this->listing, $this->product_id );

        if( $unit_quantity && $unit_type ) {
            // only applies for quantities > 1

            // Only record Maßeinheit if it hasn't been processed yet #38611
            if ( !in_array( 'Maßeinheit', $processed_attributes ) ) {
                $NameValueList1 = new NameValueListType();
                $NameValueList1->setName( 'Maßeinheit' );
                $NameValueList1->setValue($unit_type);
                $ItemSpecifics->addNameValueList( $NameValueList1 );
                WPLE()->logger->info("attrib: added product attribute: Maßeinheit - " . $unit_type );
            }

            if ( !in_array( 'Anzahl der Einheiten', $processed_attributes ) ) {
                $NameValueList2 = new NameValueListType();
                $NameValueList2->setName( 'Anzahl der Einheiten' );
                $NameValueList2->setValue($unit_quantity);
                $ItemSpecifics->addNameValueList( $NameValueList2 );
                WPLE()->logger->info("attrib: added product attribute: Anzahl der Einheiten - " . $unit_quantity );
            }
        }

        if ( $ItemSpecifics->getNameValueList() && @count( $ItemSpecifics->getNameValueList() ) > 0 ) {
    		$this->item->setItemSpecifics( $ItemSpecifics );
			WPLE()->logger->info( @count($ItemSpecifics->getNameValueList() ) . " item specifics were added.");
        }

	} /* end of buildItemSpecifics() */

	/**
	 * @return EnergyEfficiencyType
	 */
	private function getEnergyEfficiencyProperties() {
		// Energy Efficiency Label
		$ee = new EnergyEfficiencyType();

		$product_image_id       = $this->listing->getGpsrEnergyEfficiencyImageId();
		$product_image_url_eps  = $this->listing->getGpsrEnergyEfficiencyImageEps();

		//$product_image_id       = $this->listing->getProductProperty( '_ebay_gpsr_energy_efficiency_image' );
		$product_image_url      = $this->listing->getProductProperty( '_ebay_gpsr_energy_efficiency_image_url' );
		//$product_image_url_eps  = $this->listing->getProductProperty( '_ebay_gpsr_energy_efficiency_image_eps' );

		if ( ! $product_image_url_eps ) {
			// if there's no EPS URL, upload this to EPS if an ID or URL is provided
			if ( $product_image_id ) {
				// Upload to EPS
				$product_image_url = wp_get_attachment_url( $product_image_id );
			}

			if ( $product_image_url ) {
				$result = WPLE()->EC->uploadToEPS( $product_image_url, $this->session );

				if ( is_wp_error( $result ) ) {
					// let the user know which image failed to upload if there was an error
					wple_show_message( $result->get_error_message(), 'error' );
				} else {
					// assign URL to $product_image_url_eps
					$product_image_url_eps = $result->SiteHostedPictureDetails->FullURL;
					update_post_meta( $this->product_id, '_ebay_gpsr_energy_efficiency_image_eps', $product_image_url_eps );
				}
			}
		}

		if ( $product_image_url_eps ) {
			$ee->setImageURL( $product_image_url_eps );
		}

		$product_sheet_image_id     = $this->listing->getGpsrEnergyEfficiencySheetImageId();
		$product_sheet_image_url    = $this->listing->getProductProperty( '_ebay_gpsr_energy_efficiency_sheet_image_url' );
		$product_sheet_image_eps    = $this->listing->getGpsrEnergyEfficiencySheetImageEps();

		if ( empty( $product_sheet_image_eps ) ) {
			// If there's an ID but no EPS URL, we need to upload to EPS to get one
			if ( $product_sheet_image_id  ) {
				$product_sheet_image_url = wp_get_attachment_url( $product_sheet_image_id );
			}

			if ( $product_sheet_image_url ) {
				$result = WPLE()->EC->uploadToEPS( $product_sheet_image_url, $this->session );

				if ( is_wp_error( $result ) ) {
					// let the user know which image failed to upload if there was an error
					wple_show_message( $result->get_error_message(), 'error' );
				} else {
					// assign URL to $product_image_url_eps
					$product_sheet_image_eps = $result->SiteHostedPictureDetails->FullURL;
					update_post_meta( $this->product_id, '_ebay_gpsr_energy_efficiency_sheet_image_eps', $product_sheet_image_eps );
				}
			}
		}

		if ( $product_sheet_image_eps ) {
			$ee->setProductInformationsheet( $product_sheet_image_eps );
		}

		$product_label_description  = $this->listing->getGpsrEnergyEfficiencyLabelDescription();

		if ( $product_label_description ) {
			$ee->setImageDescription( $product_label_description );
		}

		return $ee;
	}

	/**
	 * @return HazmatType|bool
	 */
	private function getHazmatProperties() {
		$hazmat = new HazmatType();

		$component  = $this->listing->getGpsrHazmatComponent();
		$pictograms = $this->listing->getGpsrHazmatPictograms();
		$signalword = $this->listing->getGpsrHazmatSignalWord();
		$statements = $this->listing->getGpsrHazmatStatements();

		$pictograms_type = new PictogramsType();
		$statements_type = new StatementsType();

		foreach ( $pictograms as $pictogram ) {
			$pictograms_type->addPictogram( $pictogram );
		}

		foreach ( $statements as $statement ) {
			$statements_type->addStatement( $statement );
		}

		if ( $component || $pictograms || $statements ) {
			$hazmat->setComponent( $component );

			if ( !empty($pictograms) ) {
				$hazmat->setPictograms( $pictograms_type );
			}

			$hazmat->setSignalWord( $signalword );

			if ( !empty($statements) ) {
				$hazmat->setStatements( $statements_type );
			}


			return $hazmat;
		}

		return false;
	}

	/**
	 * @return ManufacturerType
	 */
	private function getManufacturer() {
		$manufacturer = new ManufacturerType();

		$street1 = $this->listing->getProductProperty( '_ebay_gpsr_manufacturer_street1' );
		$street2 = $this->listing->getProductProperty( '_ebay_gpsr_manufacturer_street2' );
		$city    = $this->listing->getProductProperty( '_ebay_gpsr_manufacturer_city' );
		$state   = $this->listing->getProductProperty( '_ebay_gpsr_manufacturer_state' );
		$country = $this->listing->getProductProperty( '_ebay_gpsr_manufacturer_country' );
		$postcode= $this->listing->getProductProperty( '_ebay_gpsr_manufacturer_postcode' );
		$company = $this->listing->getProductProperty( '_ebay_gpsr_manufacturer_company' );
		$phone   = $this->listing->getProductProperty( '_ebay_gpsr_manufacturer_phone' );
		$email   = $this->listing->getProductProperty( '_ebay_gpsr_manufacturer_email' );

		if ( !empty( $street1 ) && !empty( $city ) && !empty( $country ) ) {
			$manufacturer
				->setStreet1( $street1 )
				->setStreet2( $street2 )
				->setCityName( $city )
				->setStateOrProvince( $state )
				->setCountry( $country )
				->setPostalCode( $postcode )
				->setCompanyName( $company )
				->setPhone( $phone )
				->setEmail( $email );

			return $manufacturer;
		} else {
			$product_manufacturer = $this->listing->getProductProperty( '_ebay_gpsr_manufacturer' );

			if ( empty( $product_manufacturer ) ) {
				$product_manufacturer = $this->profile_details['gpsr_manufacturer'] ?? '';
			}

			if ( $product_manufacturer ) {
				$obj = new \WPLab\Ebay\Models\EbayManufacturer( $product_manufacturer );

				$manufacturer
					->setStreet1( $obj->getStreet1() )
					->setStreet2( $obj->getStreet2() )
					->setCityName( $obj->getCity() )
					->setStateOrProvince( $obj->getState() )
					->setPostalCode( $obj->getPostcode() )
					->setCountry( $obj->getCountry() )
					->setCompanyName( $obj->getCompany() )
					->setPhone( $obj->getPhone() )
					->setEmail( $obj->getEmail() );

				return $manufacturer;
			}
		}
	}

	/**
	 * @return ProductSafetyType|bool
	 */
	private function getProductSafetyProperties() {
		$safety = new ProductSafetyType();

		$component  = $this->listing->getGpsrProductSafetyComponent();
		$pictograms = $this->listing->getGpsrProductSafetyPictograms();
		$statements = $this->listing->getGpsrProductSafetyStatements();

		$pictograms_type = new PictogramsType();
		$statements_type = new StatementsType();

		foreach ( $pictograms as $pictogram ) {
			$pictograms_type->addPictogram( $pictogram );
		}

		foreach ( $statements as $statement ) {
			$statements_type->addStatement( $statement );
		}

		if ( $component || $pictograms || $statements ) {
			$safety->setComponent( $component );
			$safety->setPictograms( $pictograms_type );
			$safety->setStatements( $statements_type );

			return $safety;
		}

		return false;
	}

	public function buildGpsr() {
		if ( !$this->listing->isGpsrEnabled() ) {
			return;
		}

		WPLE()->initEC( $this->account_id );

		$regulatory = new RegulatoryType();

		$energy_efficiency  = $this->getEnergyEfficiencyProperties();
		$hazmat             = $this->getHazmatProperties();
		$manufacturer       = $this->listing->getGpsrManufacturer();
		$product_safety     = $this->getProductSafetyProperties();
		$persons            = $this->listing->getGpsrResponsiblePersons();
		$repair_score       = $this->listing->getGpsrRepairScore();

		$regulatory->setRepairScore( floatval( $repair_score ) );
		$regulatory->setEnergyEfficiencyLabel( $energy_efficiency );
		$regulatory->setResponsiblePersons( $persons );

		if ( $manufacturer ) {
			$regulatory->setManufacturer( $manufacturer );
		}

		if ( $hazmat ) {
			$regulatory->setHazmat( $hazmat );
		}

		if ( $product_safety ) {
			$regulatory->setProductSafety( $product_safety );
		}

		$this->item->setRegulatory( $regulatory );
	}

    private function processSizeMapReplacements( $attr_name, $attr_value, $profile_details ) {
        if ( empty( $profile_details['sizemap_field'] ) ) {
            return $attr_value;
        }

        if ( $attr_name != $profile_details['sizemap_field'] ) {
            return $attr_value;
        }

        foreach ( $profile_details['sizemap_wc'] as $idx => $wc_value ) {
            $ebay_value = $profile_details['sizemap_ebay'][ $idx ];

            if ( empty( $wc_value ) || empty( $ebay_value ) ) {
                continue;
            }

            if ( $attr_value == $wc_value ) {
                $attr_value = $ebay_value;
                break;
            }

        }

        return $attr_value;
    }

	/**
	 * Get the mapped categories for the given product.
	 *
	 * @param int $post_id
	 * @param int $account_id
	 *
	 * @see \WPLab\Ebay\Listings\Listing::getMappedCategories()
	 * @depecated
	 * @return array
	 */
    public function getMappedCategories( $post_id, $account_id = 0 ) {
		//_deprecated_function( 'ItemBuilderModel::getMappedCategories', '3.6', '\WPLab\Ebay\Listings\Listing::getMappedCategories' );
		return $this->listing->getMappedCategories( $post_id );
    }

	public function getExcludedAttributes() {
		$excluded_attributes = get_option('wplister_exclude_attributes');
		if ( ! $excluded_attributes ) return array();

		$attribute_names = explode( ',', $excluded_attributes );
		$attributes = array();
		foreach ($attribute_names as $name) {
			$attributes[] = trim($name);
		}

		return $attributes;
	} // getExcludedAttributes()

	/**
	 * @param Listing $listing
	 *
	 * @return array
	 */
    public function getProfileExcludedAttributes( $listing ) {
		$profile_data = $listing->getProfileDetails();
        $excluded_attributes = @$profile_data['exclude_attributes'];
        if ( ! $excluded_attributes ) return array();

        $attribute_names = explode( ',', $excluded_attributes );
        $attributes = array();
        foreach ($attribute_names as $name) {
            $attributes[] = trim($name);
        }

        return $attributes;
    }

	public function buildCompatibilityList() {
		if ( get_option( 'wplister_disable_compat_list' ) == 1 ) {
			return;
		}

		// get compatibility list and names from product
		$compatibility_list   = wple_get_compatibility_list( $this->product_id );
		$compatibility_names  = wple_get_compatibility_names( $this->product_id );
		if ( empty($compatibility_list) ) {
			return;
		}

    	// new ItemCompatibilityList
    	$ItemCompatibilityList = new ItemCompatibilityListType();
    	$ItemCompatibilityList->setReplaceAll( 1 );

        foreach ($compatibility_list as $comp) {

        	$ItemCompatibility = new ItemCompatibilityType();
        	$ItemCompatibility->setCompatibilityNotes( $comp->notes );

        	foreach ( $comp->applications as $app ) {

        		$value = html_entity_decode( $app->value, ENT_QUOTES );

	            $NameValueList = new NameValueListType();
		    	$NameValueList->setName ( $app->name  );
	    		$NameValueList->setValue( $value );

	        	$ItemCompatibility->addNameValueList( $NameValueList );
        	}

        	// add to list
        	$ItemCompatibilityList->addCompatibility( $ItemCompatibility );
        }

		$this->item->setItemCompatibilityList( $ItemCompatibilityList );
		//WPLE()->logger->info( count($ItemCompatibilityList) . " compatible applications were added.");
	} /* end of buildCompatibilityList() */

    public function buildVariations() {

        // build variations
        $this->item->setVariations( new VariationsType() );
		$parent_dimensions = $this->listing->getProduct()->get_dimensions( false );

        // get product variations
        // $listing = ListingsModel::getItem( $id );
        $variations = ProductWrapper::getVariations( $this->listing->getProductId(), false, $this->listing->getAccountId() );

        // Account locale for i18n
        $locale = WPLE_eBayAccount::getAccountLocale( $this->account_id );

        // get max_quantity from profile
        $max_quantity = ( isset( $this->profile_details['max_quantity'] ) && intval( $this->profile_details['max_quantity'] )  > 0 ) ? $this->profile_details['max_quantity'] : PHP_INT_MAX ;

        // get variation attributes / item specifics map according to profile
        $specifics_map = $this->profile_details['item_specifics'];
        $collectedMPNs = array();

        // check if primary category requires UPC or EAN
        $primary_category_id = $this->item->getPrimaryCategory()->getCategoryID();
        $UPCEnabled          = EbayCategoriesModel::getUPCEnabledForCategory( $primary_category_id, $this->site_id, $this->account_id );
        $EANEnabled          = EbayCategoriesModel::getEANEnabledForCategory( $primary_category_id, $this->site_id, $this->account_id );

        $profile_excluded_attributes = $this->getProfileExcludedAttributes( $this->listing );

        // loop each combination
        foreach ($variations as $var) {
            $newvar = new VariationType();

            // handle price
	        $start_price = ListingsModel::applyProfilePrice( $var['price'], $this->profile_details['start_price'] );

            // handle StartPrice on product level
            if ( get_option( 'wplister_enable_custom_product_prices', 1 ) ) {
                // handle StartPrice on parent product level
	            $product_start_price = $this->listing->getProductProperty( '_ebay_start_price' );

                // handle StartPrice on variation level
                if ( $var_start_price = get_post_meta( $var['post_id'], '_ebay_start_price', true ) ) {
                    $product_start_price = self::dbSafeFloatval( wc_format_decimal( $var_start_price ) );
                }

                if ( $product_start_price ) {
                    if ( 0 == get_option( 'wplister_apply_profile_to_ebay_price', 0 ) ) {
                        // default behavior - always use the _ebay_start_price if present
                        $start_price = wc_format_decimal( $product_start_price );
                    } else {
                        // Apply the profile pricing rule on the _ebay_start_price
                        $start_price = ListingsModel::applyProfilePrice( wc_format_decimal( $product_start_price ), $this->profile_details['start_price'] );
                    }
                }

				$newvar->setStartPrice( $start_price );
            }

            // handle variation quantity - if no quantity set in profile
            // if ( intval( $item->Quantity ) == 0 ) {
            if ( intval( $this->profile_details['quantity'] ) == 0 ) {
                $newvar->setQuantity( min( $max_quantity, intval( $var['stock'] ) ) );
            } else {
                $newvar->setQuantity( min( $max_quantity, $this->item->getQuantity() ) ); // should be removed in future versions
            }

            // regard WooCommerce's Out Of Stock Threshold option - if enabled
            if ( $out_of_stock_threshold = get_option( 'woocommerce_notify_no_stock_amount' ) ) {
                if ( 1 == get_option( 'wplister_enable_out_of_stock_threshold' ) ) {
                    $newvar->setQuantity( $newvar->getQuantity() - $out_of_stock_threshold );
                }
            }

            if ( $newvar->getQuantity() < 0 ) $newvar->setQuantity(0); // prevent error for negative qty

            // handle sku
            if ( $var['sku'] != '' ) {
                $newvar->setSKU( $var['sku'] );
            }

            // // add VariationSpecifics (v2)
            // $VariationSpecifics = new NameValueListArrayType();
            // foreach ($var['variation_attributes'] as $name => $value) {
            //     $NameValueList = new NameValueListType();
            //  $NameValueList->setName ( $name  );
            //  $NameValueList->setValue( $value );
            //  $VariationSpecifics->addNameValueList( $NameValueList );
            // }
            // $newvar->setVariationSpecifics( $VariationSpecifics );

            // add VariationSpecifics (v3 - regard profile mapping)
            $VariationSpecifics = new NameValueListArrayType();
            foreach ($var['variation_attributes'] as $name => $value) {


                if ( in_array( $name, $profile_excluded_attributes ) ) continue;

                if ( !isset( $this->profile_details['enable_attribute_mapping'] ) || $this->profile_details['enable_attribute_mapping'] ) {
                    // check for matching attribute name - replace woo attribute name with eBay attribute name
                    foreach ( $specifics_map as $spec ) {
                        if ( $name == $spec['attribute'] ) {
                            $name = $spec['name'];
                        }
                    }
                }

                if ( function_exists( 'qtranxf_use' ) ) {
                    $name  = qtranxf_use( $locale, $name );
                    $value = qtranxf_use( $locale, $value );
                }

				$name = apply_filters( 'wple_variation_attribute_name', $name, $var, $this );
                $value = $this->processSizeMapReplacements( $name, $value, $this->profile_details);

                $NameValueList = new NameValueListType();
                $NameValueList->setName ( $name  );
                $NameValueList->setValue( $value );
                $VariationSpecifics->addNameValueList( $NameValueList );
            }

            $newvar->setVariationSpecifics( $VariationSpecifics );

            // optional Variation.DiscountPriceInfo.OriginalRetailPrice
            $post_id     = $var['post_id'];
            $start_price = $newvar->getStartPrice();
            if ( intval($this->profile_details['strikethrough_pricing']) != 0) {

                // mode 1 - use sale price
                if ( 1 == $this->profile_details['strikethrough_pricing'] ) {
                    $original_price = ProductWrapper::getOriginalPrice( $post_id );
                    if ( ( $original_price ) && ( $start_price != $original_price ) ) {
                        $newvar->DiscountPriceInfo = new DiscountPriceInfoType();
                        $newvar->DiscountPriceInfo->OriginalRetailPrice = new AmountType();
                        $newvar->DiscountPriceInfo->OriginalRetailPrice->setTypeValue( $original_price );
                        $newvar->DiscountPriceInfo->OriginalRetailPrice->setTypeAttribute('currencyID', $this->profile_details['currency'] );
                    }
                }

                // mode 2 - use MSRP
                if ( 2 == $this->profile_details['strikethrough_pricing'] ) {
                    $msrp_price = get_post_meta( $post_id, '_msrp', true ); // variation
                    if ( ( $msrp_price ) && ( $start_price != $msrp_price ) ) {
                        $newvar->DiscountPriceInfo = new DiscountPriceInfoType();
                        $newvar->DiscountPriceInfo->OriginalRetailPrice = new AmountType();
                        $newvar->DiscountPriceInfo->OriginalRetailPrice->setTypeValue( $msrp_price );
                        $newvar->DiscountPriceInfo->OriginalRetailPrice->setTypeAttribute('currencyID', $this->profile_details['currency'] );
                    }
                }

            } // OriginalRetailPrice / STP


            // handle variation level Product ID (UPC/EAN)
            $autofill_missing_gtin = get_option('wplister_autofill_missing_gtin');
            $DoesNotApplyText = WPLE_eBaySite::getSiteObj( $this->site_id )->DoesNotApplyText;
            $DoesNotApplyText = empty( $DoesNotApplyText ) ? 'Does not apply' : $DoesNotApplyText;

            if ( $UPCEnabled == 'Required' && $autofill_missing_gtin != 'both' ) $autofill_missing_gtin = 'upc';
            if ( $EANEnabled == 'Required' && $autofill_missing_gtin != 'both' ) $autofill_missing_gtin = 'ean';

            // build VariationProductListingDetails
            $VariationProductListingDetails = new VariationProductListingDetailsType();
            $has_details                    = false;

            // set UPC from SKU - if enabled
            if ( $var['sku'] && ( @$this->profile_details['use_sku_as_upc'] == '1' ) ) {
                $VariationProductListingDetails->setUPC( $var['sku'] );
                $has_details = true;
            } elseif ( $product_upc = get_post_meta( $post_id, '_ebay_upc', true ) ) {
                $VariationProductListingDetails->setUPC( $product_upc );
                $has_details = true;
            } elseif ( $autofill_missing_gtin == 'upc' || $autofill_missing_gtin == 'both' ) {
                $VariationProductListingDetails->setUPC( $DoesNotApplyText );
                $has_details = true;
            }

            // set EAN
            if ( $var['sku'] && ( @$this->profile_details['use_sku_as_ean'] == '1' ) ) {
                $VariationProductListingDetails->setEAN( $var['sku'] );
                $has_details = true;
            } elseif ( $product_ean = get_post_meta( $post_id, '_ebay_ean', true ) ) {
                $VariationProductListingDetails->setEAN( $product_ean );
                $has_details = true;
            } elseif ( $autofill_missing_gtin == 'ean' || $autofill_missing_gtin == 'both' ) {
                $VariationProductListingDetails->setEAN( $DoesNotApplyText );
                $has_details = true;
            }

            // set ISBN
            if ( $product_isbn = get_post_meta( $post_id, '_ebay_isbn', true ) ) {
                $VariationProductListingDetails->setISBN( $product_isbn );
                $has_details = true;
            } elseif ( $autofill_missing_gtin == 'isbn') {
                $VariationProductListingDetails->setISBN( $DoesNotApplyText );
                $has_details = true;
            }

            // only set VariationProductListingDetails if at least one product ID is set
            if ( $has_details ) {
                $newvar->setVariationProductListingDetails( $VariationProductListingDetails );
            }

            // set MPN
            // Note: If Brand and MPN are being used to identify product variations in a multiple-variation listing,
            // the Brand must be specified at the item level (ItemSpecifics container)
            // and the MPN for each product variation must be specified at the variation level (VariationSpecifics container).
            // The Brand name must be the same for all variations within a single listing.
	        if ( $product_mpn = get_post_meta( $post_id, '_ebay_mpn', true ) ) {
            //if ( $product_mpn = $this->listing->getProductProperty( '_ebay_mpn' ) ) {

                $NameValueList = new NameValueListType();
                $NameValueList->setName ( 'MPN' );
                $NameValueList->setValue( $product_mpn );
                $VariationSpecifics->addNameValueList( $NameValueList );

                $newvar->setVariationSpecifics( $VariationSpecifics );

                $collectedMPNs[] = $product_mpn;
            } elseif ( $var['sku'] && ( $this->profile_details['use_sku_as_mpn'] == '1' ) ) {
                $NameValueList = new NameValueListType();
                $NameValueList->setName ( 'MPN' );
                $NameValueList->setValue( $var['sku'] );
                $VariationSpecifics->addNameValueList( $NameValueList );

                $newvar->setVariationSpecifics( $VariationSpecifics );

                $collectedMPNs[] = $var['sku'];
            }

            $newvar = apply_filters( 'wple_item_builder_variation', $newvar, $var, $post_id, $this->item, $this->profile_details );
            $this->item->getVariations()->addVariation( $newvar );

        } // each variation


        // build temporary array for VariationSpecificsSet
        $this->tmpVariationSpecificsSet = array();
        foreach ($variations as $var) {
            foreach ($var['variation_attributes'] as $name => $value) {

                if ( in_array( $name, $profile_excluded_attributes ) ) continue;

                if ( !isset( $this->profile_details['enable_attribute_mapping'] ) || $this->profile_details['enable_attribute_mapping'] ) {
                    // check for matching attribute name - replace woo attribute name with eBay attribute name
                    foreach ( $specifics_map as $spec ) {
                        if ( $name == $spec['attribute'] ) {
                            $this->variationAttributes[] = $name; // remember original name to exclude in builtItemSpecifics()
                            $name                        = $spec['name'];
                        }
                    }
                }

                if ( function_exists( 'qtranxf_use' ) ) {
                    $name  = qtranxf_use( $locale, $name );
                    $value = qtranxf_use( $locale, $value );
                }

	            $name = apply_filters( 'wple_variation_attribute_name', $name, $var, $this );
                $value = $this->processSizeMapReplacements( $name, $value, $this->profile_details );

                if ( ! isset($this->tmpVariationSpecificsSet[ $name ]) || ! is_array($this->tmpVariationSpecificsSet[ $name ]) ) {
                    $this->tmpVariationSpecificsSet[ $name ] = array();
                }
                if ( ! in_array( $value, $this->tmpVariationSpecificsSet[ $name ], true ) ) {
                    $this->tmpVariationSpecificsSet[ $name ][] = $value;
                }
            }

        }

        // add collected MPNs to tmp array
        foreach ( $collectedMPNs as $value ) {
            $name = 'MPN';

            if ( ! is_array($this->tmpVariationSpecificsSet[ $name ]) ) {
                $this->tmpVariationSpecificsSet[ $name ] = array();
            }
            if ( ! in_array( $value, $this->tmpVariationSpecificsSet[ $name ], true ) ) {
                $this->tmpVariationSpecificsSet[ $name ][] = $value;
            }
        }

        // build VariationSpecificsSet
        $VariationSpecificsSet = new NameValueListArrayType();
        foreach ($this->tmpVariationSpecificsSet as $name => $values) {

            $NameValueList = new NameValueListType();
            $NameValueList->setName ( $name );

            // Skip duplicate values in VariationSpecificsSet #23713
            WPLE()->logger->info( 'Setting VariationSpecificsSet for '. $name );
            WPLE()->logger->info( 'values: '. print_r( $values, true ) );

            $added_values = array();
            foreach ($values as $value) {
                $lowered = strtolower( $value );
                if ( in_array( $lowered, $added_values ) ) {
                    WPLE()->logger->info( $lowered .' has already been added. Skipping.' );
                    continue;
                }
                WPLE()->logger->info( 'Adding '. $lowered );
                $added_values[] = $lowered;

                $NameValueList->addValue( $value );
            }
            $VariationSpecificsSet->addNameValueList( $NameValueList );

        }
        $this->item->getVariations()->setVariationSpecificsSet( $VariationSpecificsSet );


        // build array of variation attributes, which will be needed in builtItemSpecifics()
        // $this->variationAttributes = array();
        foreach ($this->tmpVariationSpecificsSet as $key => $value) {
            $this->variationAttributes[] = $key;
        }
        WPLE()->logger->debug('set variationAttributes: '.print_r($this->variationAttributes,1));


        // select *one* VariationSpecificsSet for Pictures set
        // currently the first one is selected automatically, but there will be preferences for this later
        $VariationValuesForPictures =  reset($this->tmpVariationSpecificsSet);
        $VariationNameForPictures   =    key($this->tmpVariationSpecificsSet);

        // apply variation image attribute from profile - if set
        $variation_image_attribute = $this->profile_details['variation_image_attribute'] ?? false;
        if ( $variation_image_attribute && isset( $this->tmpVariationSpecificsSet[ $variation_image_attribute ] ) ) {
            $VariationValuesForPictures = $this->tmpVariationSpecificsSet[ $variation_image_attribute ];
            $VariationNameForPictures   = $variation_image_attribute;
        } else {
            // handle case where variation attribute is mapped to different item specifics
            // example: attribute 'Color' is mapped to item specific 'Main Color'
            foreach ( $specifics_map as $spec ) {
                if ( $variation_image_attribute == $spec['attribute'] ) {
                    if ( isset( $this->tmpVariationSpecificsSet[ $spec['name'] ] ) ) {
                        $VariationValuesForPictures = $this->tmpVariationSpecificsSet[ $spec['name'] ];
                        $VariationNameForPictures   = $spec['name'];
                        $VariationIndexForPictures  = $variation_image_attribute;
                    }
                }
            }
        }


        // build Pictures
        $Pictures = new PicturesType();
        $Pictures->setVariationSpecificName ( $VariationNameForPictures );
        foreach ($variations as $var) {

            $VariationValue = $var['variation_attributes'][$VariationNameForPictures];
            // handle case where variation attribute is mapped to different item specifics
            if ( isset($VariationIndexForPictures) && isset( $var['variation_attributes'][$VariationIndexForPictures] ) ) {
                $VariationValue = $var['variation_attributes'][$VariationIndexForPictures];
            }

            if ( in_array( $VariationValue, (array)$VariationValuesForPictures ) ) {

                $image_url = wple_encode_url( $var['image'] );
                // $image_url = $this->removeHttpsFromUrl( $image_url );

                ## BEGIN PRO ##

                // upload variation images if enabled
                $with_additional_images = $this->profile_details['with_additional_images'] ?? false;
                if ( $with_additional_images == '0' ) $with_additional_images = false;
                if ( $with_additional_images )
                    $image_url = $this->lm->uploadPictureToEPS( $image_url, $this->listing_id, $this->session );

                ## END PRO ##

                if ( ! $image_url ) continue;
                if ( $image_url == $this->item->getPictureDetails()->PictureURL[0] ) {
                    if ( ! ProductWrapper::getImageURL( $var['post_id'] ) ) continue; // avoid duplicate main image, if no variation image is set
                }
                WPLE()->logger->info( "using variation image: ".$image_url );

                $VariationSpecificPictureSet = new VariationSpecificPictureSetType();
                $VariationSpecificPictureSet->setVariationSpecificValue( $VariationValue );
                $VariationSpecificPictureSet->addPictureURL( $image_url );

                // check for additional variation images (WooCommerce Additional Variation Images Addon)
                if ( class_exists('WC_Additional_Variation_Images') ) {

                    $additional_var_images = get_post_meta( $var['post_id'], '_wc_additional_variation_images', true );
                    $additional_var_images = empty($additional_var_images) ? false : explode( ',', $additional_var_images );

                    if ( is_array( $additional_var_images ) ) {
                        foreach ( $additional_var_images as $attachment_id ) {

                            // get URL from attachment ID
                            $size = get_option( 'wplister_default_image_size', 'full' );
                            $large_image_url = wp_get_attachment_image_src( $attachment_id, $size );
                            $image_url = wple_encode_url( $large_image_url[0] );
                            WPLE()->logger->info( "found additional variation image: ".$image_url );

                            // upload variation images if enabled
                            if ( $with_additional_images )
                                $image_url = $this->lm->uploadPictureToEPS( $image_url, $this->listing_id, $this->session );

                            // add variation image to picture set
	                        if ( $image_url ) {
		                        $VariationSpecificPictureSet->addPictureURL( $image_url );
	                        }
                            WPLE()->logger->info( "added additional variation image: ".$image_url );
                        }
                    }
                }

                // Check for WooThumbs images
                if ( class_exists( 'Iconic_WooThumbs' ) ) {
                    $additional_var_images = get_post_meta( $var['post_id'], 'variation_image_gallery', true );
                    $additional_var_images = empty($additional_var_images) ? false : explode( ',', $additional_var_images );

                    // Woothumbs switched to using the core WC_Product::get_gallery_image_ids() method
                    if ( !$additional_var_images ) {
                        $variation = wc_get_product( $var['post_id'] );
                        $additional_var_images = $variation ? $variation->get_gallery_image_ids() : array();
                    }

                    if ( is_array( $additional_var_images ) ) {
                        foreach ( $additional_var_images as $attachment_id ) {

                            // get URL from attachment ID
                            $size = get_option( 'wplister_default_image_size', 'full' );
                            $large_image_url = wp_get_attachment_image_src( $attachment_id, $size );
                            $image_url = wple_encode_url( $large_image_url[0] );
                            WPLE()->logger->info( "found additional variation image: ".$image_url );

                            // upload variation images if enabled
                            if ( $with_additional_images )
                                $image_url = $this->lm->uploadPictureToEPS( $image_url, $this->listing_id, $this->session );

                            // add variation image to picture set
	                        if ( $image_url ) {
		                        $VariationSpecificPictureSet->addPictureURL( $image_url );
		                        WPLE()->logger->info( "added additional variation image: ".$image_url );
	                        }

                        }
                    }
                }

                // Check for Woo Product Variation Gallery
                if ( class_exists( 'WooProductVariationGallery' ) ) {
                    $additional_var_images = get_post_meta( $var['post_id'], 'rtwpvg_images', true );

                    if ( is_array( $additional_var_images ) ) {
                        foreach ( $additional_var_images as $attachment_id ) {

                            // get URL from attachment ID
                            $size = get_option( 'wplister_default_image_size', 'full' );
                            $large_image_url = wp_get_attachment_image_src( $attachment_id, $size );
                            $image_url = wple_encode_url( $large_image_url[0] );
                            WPLE()->logger->info( "found additional variation image: ".$image_url );

                            // upload variation images if enabled
                            if ( $with_additional_images )
                                $image_url = $this->lm->uploadPictureToEPS( $image_url, $this->listing_id, $this->session );

							if ( $image_url ) {
								// add variation image to picture set
								$VariationSpecificPictureSet->addPictureURL( $image_url );
								WPLE()->logger->info( "added additional variation image: ".$image_url );
							}

                        }
                    }
                }

                // only list variation images if enabled
                if ( @$this->profile_details['with_variation_images'] != '0' ) {
                    $Pictures->addVariationSpecificPictureSet( $VariationSpecificPictureSet );
                }

                // remove value from VariationValuesForPictures to avoid duplicates
                if ( is_array( $VariationValuesForPictures ) ) {
                    unset( $VariationValuesForPictures[ array_search( $VariationValue, $VariationValuesForPictures ) ] );
                }

            }

        }
        $this->item->getVariations()->setPictures( $Pictures );

        // ebay doesn't allow different weight and dimensions for varations
        // so for calculated shipping services we just fetch those from the first variation
        // and overwrite

        // $isCalc = $profile_details['shipping_service_type'] == 'calc' ? true : false;
        $service_type = $this->profile_details['shipping_service_type'];
        $isCalc = in_array( $service_type, array('calc','FlatDomesticCalculatedInternational' ,'CalculatedDomesticFlatInternational') );

        if ( $isCalc ) {

            // get weight and dimensions from first variation
            $first_variation = reset( $variations );
            $weight_major = $first_variation['weight_major'];
            $weight_minor = $first_variation['weight_minor'];
            $dimensions   = $first_variation['dimensions'];

            // Commented out because shipping package properties should be defined in ShipPackageDetailsType
            //$item->ShippingDetails->CalculatedShippingRate->setWeightMajor( self::dbSafeFloatval( $weight_major ) );
            //$item->ShippingDetails->CalculatedShippingRate->setWeightMinor( self::dbSafeFloatval( $weight_minor ) );

            //if ( trim( @$dimensions['width']  ) != '' ) $item->ShippingDetails->CalculatedShippingRate->setPackageWidth( $dimensions['width'] );
            //if ( trim( @$dimensions['length'] ) != '' ) $item->ShippingDetails->CalculatedShippingRate->setPackageLength( $dimensions['length'] );
            //if ( trim( @$dimensions['height'] ) != '' ) $item->ShippingDetails->CalculatedShippingRate->setPackageDepth( $dimensions['height'] );

            // update ShippingPackageDetails with weight and dimensions of first variations
            $shippingPackageDetails = new ShipPackageDetailsType();
            $shippingPackageDetails->setWeightMajor( self::dbSafeFloatval( $weight_major) );
            $shippingPackageDetails->setWeightMinor( self::dbSafeFloatval( $weight_minor) );
			$width  = $parent_dimensions['width'];
			$length = $parent_dimensions['length'];
			$height = $parent_dimensions['height'];

			if ( !empty( $dimensions['width'] ) ) {
				$width = $dimensions['width'];
			}

			if ( !empty( $dimensions['length'] ) ) {
				$length = $dimensions['length'];
			}

			if ( !empty( $dimensions['height'] ) ) {
				$height = $dimensions['height'];
			}

            if ( !empty( $width ) ) $shippingPackageDetails->setPackageWidth( $width );
            if ( !empty( $length )  ) $shippingPackageDetails->setPackageLength( $length );
            if ( !empty( $height ) ) $shippingPackageDetails->setPackageDepth( $height );
            $this->item->setShippingPackageDetails( $shippingPackageDetails );

            // debug
            //WPLE()->logger->info('first variations weight: '.print_r($weight,1));
            WPLE()->logger->info('first variations dimensions: '.print_r([$length,$width,$height],1));
        }


        // remove some settings from single item
        if ( apply_filters( 'wple_remove_variable_item_parent_sku', true ) ) {
            $this->item->setSKU(null);
        }

        $this->item->setQuantity(null);
        $this->item->setStartPrice(null);
        $this->item->setBuyItNowPrice(null);

        /* this we should get:
        <Variations>
            <Variation>
                <SKU />
                <StartPrice>15</StartPrice>
                <Quantity>1</Quantity>
                <VariationSpecifics>
                    <NameValueList>
                        <Name>Size</Name>
                        <Value>large</Value>
                    </NameValueList>
                </VariationSpecifics>
            </Variation>
            <Variation>
                <SKU />
                <StartPrice>10</StartPrice>
                <Quantity>1</Quantity>
                <VariationSpecifics>
                    <NameValueList>
                        <Name>Size</Name>
                        <Value>small</Value>
                    </NameValueList>
                </VariationSpecifics>
            </Variation>
            <Pictures>
                <VariationSpecificName>Size</VariationSpecificName>
                <VariationSpecificPictureSet>
                    <VariationSpecificValue>large</VariationSpecificValue>
                    <PictureURL>http://www.example.com/wp-content/uploads/2011/09/grateful-dead.jpg</PictureURL>
                </VariationSpecificPictureSet>
                <VariationSpecificPictureSet>
                    <VariationSpecificValue>small</VariationSpecificValue>
                    <PictureURL>www.example.com/wp-content/uploads/2011/09/grateful-dead.jpg</PictureURL>
                </VariationSpecificPictureSet>
            </Pictures>
            <VariationSpecificsSet>
                <NameValueList>
                    <Name>Size</Name>
                    <Value>large</Value>
                    <Value>small</Value>
                </NameValueList>
            </VariationSpecificsSet>
        </Variations>
        */

    } /* end of buildVariations() */

	public function getVariationImages( $post_id ) {

		// check if product has variations
        if ( ! ProductWrapper::hasVariations( $post_id ) ) return array();

		// get variations
        $variations = ProductWrapper::getVariations( $post_id );
        $variation_images = array();

        foreach ( $variations as $var ) {

        	if ( ! in_array( $var['image'], $variation_images ) ) {
        		$variation_images[] = wple_normalize_url( $var['image'], true );
        	}

        }
		WPLE()->logger->info("variation images: ".print_r($variation_images,1));

        return $variation_images;
	} // getVariationImages()


	public function prepareSplitVariation() {
		WPLE()->logger->info("prepareSplitVariation( $this->listing_id ) - parent_id: ".$this->listing->getParentId());
		$parent_id = $this->listing->getParentId();

		// get (all) parent variations
        $variations = ProductWrapper::getVariations( $parent_id );

        // find this single variation
        $single_variation = false;
        foreach ($variations as $var) {
        	if ( $var['post_id'] == $this->product_id ) {
        		$single_variation = $var;
        	}
        }
        if ( ! $single_variation ) return;

	    // add variation attributes to $this->variationSplitAttributes - to be used in builtItemSpecifics()
        foreach ($single_variation['variation_attributes'] as $name => $value) {
        	$this->variationSplitAttributes[ $name ] = $value;
        }
        WPLE()->logger->debug('set variationSplitAttributes: '.print_r($this->variationSplitAttributes,1));

	} // prepareSplitVariation()


	public function flattenVariations() {
		WPLE()->logger->info("flattenVariations($this->listing_id)");

		// get product variations
		// $p = ListingsModel::getItem( $id );
        $variations = ProductWrapper::getVariations( $this->product_id );
        $product    = wc_get_product( $this->product_id );

        $this->variationAttributes = array();
        $total_stock = 0;

        // find default variation
        $default_variation = reset( $variations );
        foreach ( $variations as $var ) {

	        // find default variation
        	if ( $var['is_default'] ) $default_variation = $var;

		    // build array of variation attributes, which will be needed in builtItemSpecifics()
            foreach ($var['variation_attributes'] as $name => $value) {
	        	$this->variationAttributes[] = $name;
	        }

	        // count total stock
	        $total_stock += $var['stock'];
        }

        // list accumulated stock quantity if not set in profile
        if ( ! $this->item->Quantity )
        	$this->item->Quantity = $total_stock;

        /**
         * Set the product's price.
         *
         * Check for a custom Start Price in the parent and use it if it exists. Otherwise, load the price of the
         * default variation.
         */
		$start_price = $this->listing->getStartPrice();

        $this->item->getStartPrice()->setTypeValue( self::dbSafeFloatval( $start_price ) );
        WPLE()->logger->info("using start price from Listing::getStartPrice(): ".print_r($this->item->getStartPrice()->getTypeValue(),1));


    	// ebay doesn't allow different weight and dimensions for varations
    	// so for calculated shipping services we just fetch those from the default variation
    	// and overwrite

		// $isCalc = $profile_details['shipping_service_type'] == 'calc' ? true : false;
		$service_type = $this->profile_details['shipping_service_type'];
		$isCalc    = ( in_array( $service_type, array('calc','FlatDomesticCalculatedInternational' ,'CalculatedDomesticFlatInternational') ) ) ? true : false;
		$hasWeight = ( in_array( $service_type, array('calc','FreightFlat','FlatDomesticCalculatedInternational','CalculatedDomesticFlatInternational') ) ) ? true : false;

		if ( $isCalc ) {

			// get weight and dimensions from default variation
			$weight_major = $default_variation['weight_major'];
			$weight_minor = $default_variation['weight_minor'];
			$dimensions   = $default_variation['dimensions'];

			//$item->ShippingDetails->CalculatedShippingRate->setWeightMajor( self::dbSafeFloatval( $weight_major ) );
			//$item->ShippingDetails->CalculatedShippingRate->setWeightMinor( self::dbSafeFloatval( $weight_minor ) );

			//if ( trim( @$dimensions['width']  ) != '' ) $item->ShippingDetails->CalculatedShippingRate->setPackageWidth( $dimensions['width'] );
			//if ( trim( @$dimensions['length'] ) != '' ) $item->ShippingDetails->CalculatedShippingRate->setPackageLength( $dimensions['length'] );
			//if ( trim( @$dimensions['height'] ) != '' ) $item->ShippingDetails->CalculatedShippingRate->setPackageDepth( $dimensions['height'] );

			// debug
			//WPLE()->logger->info('default variations weight: '.print_r($weight,1));
			WPLE()->logger->info('default variations dimensions: '.print_r($dimensions,1));
		}

		// set ShippingPackageDetails
		if ( $hasWeight ) {

			// get weight and dimensions from default variation
			$weight_major = $default_variation['weight_major'];
			$weight_minor = $default_variation['weight_minor'];
			$dimensions   = $default_variation['dimensions'];

			$shippingPackageDetails = new ShipPackageDetailsType();
			$shippingPackageDetails->setWeightMajor( self::dbSafeFloatval( $weight_major) );
			$shippingPackageDetails->setWeightMinor( self::dbSafeFloatval( $weight_minor) );

			if ( trim( @$dimensions['width']  ) != '' ) $shippingPackageDetails->setPackageWidth( $dimensions['width'] );
			if ( trim( @$dimensions['length'] ) != '' ) $shippingPackageDetails->setPackageLength( $dimensions['length'] );
			if ( trim( @$dimensions['height'] ) != '' ) $shippingPackageDetails->setPackageDepth( $dimensions['height'] );

			$this->item->setShippingPackageDetails( $shippingPackageDetails );
		}

	} /* end of flattenVariations() */

	// remove specific item details to allow revising in restricted mode
	// called from ListingsModel::reviseItem()
    function applyRestrictedReviseMode( $item ) {

    	// remove Item->Variations->Pictures node
    	if ( $item->Variations ) {
	    	$item->Variations->setPictures( null );
    	}

    	return $item;
	} // applyRestrictedReviseMode()



	// set DeletedField container to allow removing SubTitle and BoldTitle
	// called from ListingsModel::reviseItem()
    function setDeletedFields( $req, $listing_item ) {

    	WPLE()->logger->info("SUBTITLE: ".$req->Item->getSubTitle());
    	if ( ! $req->Item->getSubTitle() ) {
			$req->addDeletedField('Item.SubTitle');
    	}

    	WPLE()->logger->info("ListingEnhancement: ".$req->Item->getListingEnhancement());
    	if ( ! $req->Item->getListingEnhancement() ) {
			$req->addDeletedField('Item.ListingEnhancement[BoldTitle]');
    	}

		return $req;
	} // setDeletedFields()



	// check if there are existing variations on eBay which do not exist in WooCommerce and need to be deleted
	// called from ListingsModel::reviseItem()
    function fixDeletedVariations( $item, $listing_item ) {

        $cached_variations = maybe_unserialize( $listing_item['variations'] );
        if ( empty($cached_variations) ) return $item;

        // do nothing if this is not a variable item
        // if a user switches a variable product to simple, addVariation() below will throw a fatal error otherwise
		if ( ! is_object( $item->Variations ) ) return $item;

        // loop cached variations
        foreach ($cached_variations as $key => $var) {

        	if ( ! $this->checkIfVariationExistsInItem( $var, $item ) ) {

        		// build new variation to be deleted
	        	$newvar = new VariationType();

	        	// set quantity to zero - effectively remove variations that have sales
	        	$newvar->Quantity = 0;
				// $newvar->StartPrice = $var['price'];

				// handle sku
	        	if ( $var['sku'] != '' ) {
	        		$newvar->SKU = $var['sku'];
	        	}

	        	// add VariationSpecifics (v2)
	        	$VariationSpecifics = new NameValueListArrayType();
	            foreach ($var['variation_attributes'] as $name => $value) {
		            $NameValueList = new NameValueListType();
	    	    	$NameValueList->setName ( $name  );
	        		$NameValueList->setValue( $value );
		        	$VariationSpecifics->addNameValueList( $NameValueList );
	            }
	        	$newvar->setVariationSpecifics( $VariationSpecifics );

	        	// tell eBay to delete this variation - only possible for items without sales
                $delete_unsold = apply_filters( 'wplister_delete_unsold_variations', array(true), '2.8.4', 'wple_delete_unsold_variations' );
                $delete_unsold = apply_filters( 'wple_delete_unsold_variations', $delete_unsold );
	        	if ( isset($var['sold']) && ( intval($var['sold']) == 0 ) && $delete_unsold ) {
		        	$newvar->setDelete( true );
	                WPLE()->logger->info('setDelete(true) - sold qty: '.$var['sold']);
	        	} else {
	        	    // It doesn't make sense to continue if this variation cannot be deleted
                    continue;
                }

				$item->Variations->addVariation( $newvar );
                WPLE()->logger->info('added variation to be deleted: '.print_r($newvar,1) );

                //
                // update VariationSpecificsSet - to avoid Error 21916608: Variation cannot be deleted during restricted revise
                //

		        // build extra (!) temporary array for VariationSpecificsSet
		    	$extraVariationSpecificsSet = array();
	            foreach ($var['variation_attributes'] as $name => $value) {
	    	    	if ( ! is_array($this->tmpVariationSpecificsSet[ $name ]) ) {
			        	$this->tmpVariationSpecificsSet[ $name ] = array(); 	// make sure the second level array exists
	    	    	}
	    	    	if ( ! is_array($extraVariationSpecificsSet[ $name ]) ) {
			        	$extraVariationSpecificsSet[ $name ] = array();			// make sure the second level array exists
	    	    	}
		        	if ( ! in_array( $value, $this->tmpVariationSpecificsSet[ $name ] ) ) {
		        		$extraVariationSpecificsSet[ $name ][]     = $value;	// add extra value which doesn't exist yet
		        		$this->tmpVariationSpecificsSet[ $name ][] = $value;	// add extra value which doesn't exist yet
		        	}
	            }
		        // build VariationSpecificsSet
		    	// $VariationSpecificsSet = new NameValueListArrayType();
		        foreach ($extraVariationSpecificsSet as $name => $values) {

		        	foreach ($item->Variations->VariationSpecificsSet->NameValueList as $NameValueList) {

		        		// check if this is the attribute we're looking for
		        		if ( $NameValueList->Name != $name ) continue;

						// add missing attribute values
			            foreach ($values as $value) {
				        	$NameValueList->addValue( $value );
				        }

		        	}

		        } // foreach $extraVariationSpecificsSet


        	} // if checkIfVariationExistsInItem()

        } // foreach $cached_variations

    	return $item;
	} // fixDeletedVariations()

    function checkIfVariationExistsInItem( $variation, $item ) {
        WPLE()->logger->info( 'checkIfVariationExistsInItem' );
    	$variation_attributes = $variation['variation_attributes'];

        // loop existing item variations
        foreach ( $item->Variations->Variation as $Variation ) {
            $found_match = true;

            // compare variation SKU
            if ( ! empty( $variation['sku'] ) ) {
            	if ( $variation['sku'] == $Variation->SKU ) {
	                // WPLE()->logger->info('found matching variation by SKU: '.$Variation->SKU);
	                return true;
            	}
            }

            // compare variation attributes
        	foreach ($Variation->VariationSpecifics->NameValueList as $spec) {
        		$name = $spec->Name;
        		$val  = $spec->Value;
        		if ( $name == 'MPN' ) continue; // ignore virtual Item Specific 'MPN'
        		if ( isset( $variation_attributes[ $name ] ) ) {

        			if ( $variation_attributes[ $name ] == $val ) {
	                	// WPLE()->logger->info('found matching name value pair: '.print_r($spec,1) );
        				// $found_match = true;
        			} else {
        			    WPLE()->logger->info('variation spec value does not match with "'.$variation_attributes[ $name ].'": '.print_r($spec,1) );
        				$found_match = false;
        			}

        		} else {
                	WPLE()->logger->info('variation spec name does not exist "'.$name.'" does not exist in attributes: '.print_r($variation_attributes,1) );
    				$found_match = false;
        		}
        	}

            if ( $found_match ) {
                // WPLE()->logger->info('found matching variation by attributes: '.print_r($Variation->VariationSpecifics->NameValueList,1) );
                return true;
            }

        }

        return false;
    } // checkIfVariationExistsInItem()

	/**
	 * @param ItemType $item
	 * @param bool $reviseItem
	 *
	 * @return bool
	 */
	public function checkItem( ?ItemType $item = null, bool $reviseItem = false ) {
		$item = $item ?? $this->item;

		$success = true;
		$longMessage = '';
		$this->VariationsHaveStock = false;

		// check StartPrice, Quantity and SKU
		if ( is_object( $item->getVariations() ) ) {
			// item has variations

			$VariationsHaveStock = false;
			$VariationsSkuArray = array();
			$VariationsSkuAreUnique = true;
			$VariationsSkuMissing = false;
			$VariationsHaveMPNs = false;

			// check each variation
			foreach ($item->Variations->Variation as $var) {

				// StartPrice must be greater than 0
				if ( self::dbSafeFloatval( $var->StartPrice ) == 0 ) {
					$longMessage = __( 'Some variations seem to have no price.', 'wp-lister-for-ebay' );
					$success = false;
				}

				// Quantity must be greater than 0 - at least for one variation
				if ( intval($var->Quantity) > 0 ) $VariationsHaveStock = true;

				// SKUs must be unique - if present
				if ( ($var->SKU) != '' ) {
					if ( in_array( $var->SKU, $VariationsSkuArray )) {
						$VariationsSkuAreUnique = false;
					} else {
						$VariationsSkuArray[] = $var->SKU;
					}
				} else {
					$VariationsSkuMissing = true;
				}

				// VariationSpecifics values can't be longer than 65 characters
				foreach ($var->VariationSpecifics->NameValueList as $spec) {
					if ( self::mb_strlen( $spec->Value ) > 65 ) {
						$longMessage = __( 'eBay does not allow attribute values longer than 65 characters.', 'wp-lister-for-ebay' );
						$longMessage .= '<br>';
						$longMessage .= __( 'You need to shorten this value:', 'wp-lister-for-ebay' ) . ' <code>'.$spec->Value.'</code>';
						$success = false;
					}
				}

				// check for MPNs in VariationSpecifics container
				foreach ($var->VariationSpecifics->NameValueList as $spec) {
					if ( $spec->Name == 'MPN' ) $VariationsHaveMPNs = true;
				}

			}

			// fix missing MPNs in VariationSpecifics container - prevent Error: Missing name in name-value list. (21916587)
			if ( $VariationsHaveMPNs ) {

				$DoesNotApplyText = WPLE_eBaySite::getSiteObj( $this->site_id )->DoesNotApplyText;
				$DoesNotApplyText = empty( $DoesNotApplyText ) ? 'Does not apply' : $DoesNotApplyText;

				foreach ($item->Variations->Variation as &$var) {

					$thisVariationHasMPN = false;
					foreach ($var->VariationSpecifics->NameValueList as $spec) {
						if ( $spec->Name == 'MPN' ) $thisVariationHasMPN = true;
					}

					if ( ! $thisVariationHasMPN ) {

			            $NameValueList = new NameValueListType();
		    	    	$NameValueList->setName ( 'MPN' );
		        		$NameValueList->setValue( $DoesNotApplyText );
			        	$var->VariationSpecifics->addNameValueList( $NameValueList );

						$longMessage = __( 'Only some variations have MPNs.', 'wp-lister-for-ebay' );
						$longMessage .= '<br>';
						$longMessage .= __( 'To prevent listing errors, missing MPNs have been filled in with "Does not apply".', 'wp-lister-for-ebay' );
					}
				}
			}

			if ( ! $VariationsSkuAreUnique ) {
				foreach ($item->Variations->Variation as &$var) {
					$var->SKU = '';
				}
				$longMessage = __( 'You are using the same SKU for more than one variations which is not allowed by eBay.', 'wp-lister-for-ebay' );
				$longMessage .= '<br>';
				$longMessage .= __( 'To circumvent this issue, your item will be listed without SKU.', 'wp-lister-for-ebay' );
				// $success = false;
			}

			if ( $VariationsSkuMissing ) {
				$longMessage = __( 'Some variations are missing a SKU.', 'wp-lister-for-ebay' );
				$longMessage .= '<br>';
				$longMessage .= __( 'It is required to assign a unique SKU to each variation to prevent issues syncing sales.', 'wp-lister-for-ebay' );
				// $success = false;
			}

			if ( ! $VariationsHaveStock && ! $reviseItem && ! \ListingsModel::thisAccountUsesOutOfStockControl( $this->account_id ) ) {
				$longMessage = __( 'None of these variations are in stock.', 'wp-lister-for-ebay' );
				$success = false;
			}

			// make this info available to reviseItem()
			$this->VariationsHaveStock = $VariationsHaveStock;

		} else {
			// item has no variations

			// StartPrice must be greater than 0
			if ( self::dbSafeFloatval( $item->StartPrice->value ) == 0 ) {
				$longMessage = __( 'Price can not be zero.', 'wp-lister-for-ebay' );
				$success = false;
			}

			// check minimum start price if found
			// $min_prices = get_option( 'wplister_MinListingStartPrices', array() );
			$min_prices = $this->site_id ? maybe_unserialize( WPLE_eBaySite::getSiteObj( $this->site_id )->MinListingStartPrices ) : array();
			if ( ! is_array($min_prices) ) $min_prices = array();

			$listing_type = $item->ListingType ?? 'FixedPriceItem';
			if ( isset( $min_prices[ $listing_type ] ) ) {
				$min_price = $min_prices[ $listing_type ];
				if ( $item->StartPrice->value < $min_price ) {
					$longMessage = sprintf( __( 'eBay requires a minimum price of %s for this listing type.', 'wp-lister-for-ebay' ), $min_price );
					$success = false;
				}
			}

		}


		// check if any required item specifics are missing
		$primary_category_id = $item->PrimaryCategory->CategoryID;
		$specifics           = EbayCategoriesModel::getItemSpecificsForCategory( $primary_category_id, $this->site_id, $this->account_id );

		foreach ( $specifics as $req_spec ) {

			// skip non-required specs
			if ( ! $req_spec->MinValues ) continue;

			// skip if Name already exists in ItemSpecifics
			if ( self::thisNameExistsInNameValueList( $req_spec->Name, @$item->ItemSpecifics->NameValueList ) ) {
				continue;
			}

			// skip if Name already exists in VariationSpecificsSet
			if ( is_object( $item->Variations ) ) {
				$VariationSpecificsSet = $item->Variations->getVariationSpecificsSet();
				if ( self::thisNameExistsInNameValueList( $req_spec->Name, $VariationSpecificsSet->NameValueList ) ) {
					continue;
				}
			}

			$DoesNotApplyText = WPLE_eBaySite::getSiteObj( $this->site_id )->DoesNotApplyText;
			$DoesNotApplyText = empty( $DoesNotApplyText ) ? 'Does not apply' : $DoesNotApplyText;

			// // add missing item specifics
			$NameValueList = new NameValueListType();
			$NameValueList->setName ( $req_spec->Name  );
			$NameValueList->setValue( $DoesNotApplyText );

            if ( ! $item->ItemSpecifics ) {
                // new ItemSpecifics
                $item->ItemSpecifics = new NameValueListArrayType();
            }

			$item->ItemSpecifics->addNameValueList( $NameValueList );

			wple_show_message( '<b>Note:</b> Missing item specifics <b>'.$req_spec->Name.'</b> was set to "'.$DoesNotApplyText.'" in order to prevent listing errors.', 'warn' );
		}

		// check if any item specific have more values than allowed
		foreach ( $specifics as $req_spec ) {

			// skip specs without limit
			if ( ! $req_spec->MaxValues ) continue;

			// count values for this item specific
			$number_of_values = self::countValuesForNameInNameValueList( $req_spec->Name, $item->ItemSpecifics->NameValueList );
			if ( $number_of_values <= $req_spec->MaxValues ) continue;

			// remove additional values from item specific
			for ( $i=0; $i < sizeof( $item->ItemSpecifics->NameValueList ); $i++ ) {
				if ( $item->ItemSpecifics->NameValueList[ $i ]->Name != $req_spec->Name ) continue;
				$values_array =	$item->ItemSpecifics->NameValueList[ $i ]->Value;
				$item->ItemSpecifics->NameValueList[ $i ]->Value = is_array( $values_array ) ? reset( $values_array ) : $values_array;
			}

			wple_show_message( '<b>Note:</b> The item specifics <b>'.$req_spec->Name.'</b> has '.$number_of_values.' values, but eBay allows only '.$req_spec->MaxValues.' value(s).<br>In order to prevent listing errors, additional values will be omitted.', 'warn' );
		}


		// ItemSpecifics values can't be longer than 65 characters
		if ( $item->ItemSpecifics ) foreach ( $item->ItemSpecifics->NameValueList as $spec ) {
			$values = is_array( $spec->Value ) ? $spec->Value : array( $spec->Value );
			foreach ($values as $value) {
				if ( self::mb_strlen( $value ) > 65 ) {
					$longMessage = __( 'eBay does not allow attribute values longer than 65 characters.', 'wp-lister-for-ebay' );
					$longMessage .= '<br>';
					$longMessage .= __( 'You need to shorten this value:', 'wp-lister-for-ebay' ) . ' <code>'.$value.'</code>';
					$success = false;
				}
			}
		}

		// PrimaryCategory->CategoryID must be greater than 0
		if ( intval( @$item->PrimaryCategory->CategoryID ) == 0 ) {
			$longMessage = __( 'There has been no primary category assigned.', 'wp-lister-for-ebay' );
			$success = false;
		}

		// check for main image
		if ( trim( @$item->PictureDetails->PictureURL[0] ) == '' ) {
			$longMessage = __( 'You need to add at least one image to your product.', 'wp-lister-for-ebay' );
			$success = false;
		}

		// remove ReservedPrice on fixed price items
		if ( $item->getReservePrice() && $item->getListingType() == 'FixedPriceItem' ) {
			$item->setReservePrice( null );
			$longMessage = __( 'Reserve price does not apply to fixed price listings.', 'wp-lister-for-ebay' );
			// $success = false;
		}

		// omit price and shipping cost when revising an item with promotional sale enabled
		if ( $reviseItem && ListingsModel::thisListingHasPromotionalSale( $this->listing_id ) ) {
			$item->setStartPrice( null );
			$item->setShippingDetails( null );
			wple_show_message( __( 'Price and shipping were omitted since this item has promotional sale enabled.', 'wp-lister-for-ebay' ), 'info' );
		}

		if ( ! $success ) {
			wple_show_message( $longMessage, 'error' );
		} elseif ( ( $longMessage != '' ) ) {
			wple_show_message( $longMessage, 'warn' );
		}

		$htmlMsg  = '<div id="message" class="error" style="display:block !important;"><p>';
		$htmlMsg .= '<b>' . 'This item did not pass the validation check' . ':</b>';
		$htmlMsg .= '<br>' . $longMessage . '';
		$htmlMsg .= '</p></div>';

		// save error as array of objects
		$errorObj = new stdClass();
		$errorObj->SeverityCode = 'Validation';
		$errorObj->ErrorCode 	= '42';
		$errorObj->ShortMessage = $longMessage;
		$errorObj->LongMessage 	= $longMessage;
		$errorObj->HtmlMessage 	= $htmlMsg;
		$errors = array( $errorObj );

		// save results as local property
		$this->result = new stdClass();
		$this->result->success = $success;
		$this->result->errors  = $errors;

		return $success;

	} /* end of checkItem() */


	static public function thisNameExistsInNameValueList( $name, $NameValueList ) {
		if ( ! is_array( $NameValueList ) ) return false;
		foreach ( $NameValueList as $listitem ) {
		    // test the natural and lowercased name for a match #46778
			if ( $listitem->Name == $name || strtolower( $listitem->Name ) == strtolower( $name ) ) {
				// name exists, check value
				if ( is_array( $listitem->Value ) ) {
					if ( ! $listitem->Value[0]  &&  $listitem->Value[0] !== '0' ) return false;
				} else {
					if ( ! $listitem->Value     &&  $listitem->Value    !== '0' ) return false;
				}
				return true;
			}
		}
		return false;
	}

	static public function countValuesForNameInNameValueList( $name, $NameValueList ) {
		foreach ( $NameValueList as $listitem ) {
			if ( $listitem->Name == $name ) {
				// name found, count array values
				if ( is_array( $listitem->Value ) ) {
					return sizeof( $listitem->Value );
				} else {
					return 1;
				}
			}
		}
		return false;
	}


	public function getDynamicShipping( $price, $post_id ) {

		// return price if no mapping
		if ( ! substr( $price, 0, 1 ) == '[' ) return self::dbSafeFloatval($price);

		// split values list
		$values = substr( $price, 1, -1 );
		$values = explode( '|', $values );

		// first item is mode
		$mode = array_shift($values);


		// weight mode
		if ( $mode == 'weight' ) {

			$product_weight = ProductWrapper::getWeight( $post_id );
			foreach ($values as $val) {
				list( $limit, $price ) = explode(':', $val);
				if ( $product_weight >= $limit) $shipping_cost = $price;
			}
			return self::dbSafeFloatval($shipping_cost);
		}

		// convert '0.00' to '0' - ebay api doesn't like '0.00'
		if ( $price == 0 ) $price = '0';

		return self::dbSafeFloatval($price);

	}

	// this version of floatval() makes sure to use decimal points, no matter what locale is set for PHP
	static public function dbSafeFloatval( $value ) {
		// WPLE()->logger->info('dbSafeFloatval()  IN: '.$value);

		// set locale to use C style floats for numeric calculations
		setlocale( LC_NUMERIC, 'C' );
		$value = floatval( $value );

		// WPLE()->logger->info('dbSafeFloatval() OUT: '.$value);
	    return $value;
	}


	static public function prepareTitleAsHTML( $title ) {

		WPLE()->logger->debug('prepareTitleAsHTML()  in: ' . $title );
		$title = htmlentities( strip_tags( $title ), ENT_QUOTES, 'UTF-8', false );
		WPLE()->logger->debug('prepareTitleAsHTML() out: ' . $title );
		return $title;
	}


	public function prepareTitle( $title ) {

		WPLE()->logger->info('prepareTitle()  in: ' . $title );
		$title = html_entity_decode( strip_tags( $title ), ENT_QUOTES, 'UTF-8' );

        // limit item title to 80 characters
        // Except when the title contains [:] which means the title contains translations so it's meant to be really long
        if ( $this->mb_strlen($title) > 80 && false === strpos( $title, '[:]' ) ) {
            $use_ellipsis = apply_filters_deprecated( 'wplister_use_title_ellipsis', array(true), '2.8.4', 'wple_use_title_ellipsis' );
            $use_ellipsis = apply_filters( 'wple_use_title_ellipsis', $use_ellipsis );
            if (  $use_ellipsis === true ) {
                $title = self::mb_substr( $title, 0, 77 ) . '...';
            } else {
                $title = self::mb_substr( $title, 0, 80 );
            }

        }

        // remove control characters disallowed in XML (like 0x1f)
        $title = preg_replace('/[[:cntrl:]]/i', '', $title);

		WPLE()->logger->info('prepareTitle() out: ' . $title );
		return $title;
	}

	/**
	 * TODO Convert to use the new Listing class
	 * @param $id
	 * @param $ItemObj
	 * @param $preview
	 *
	 * @return array|string|string[]|null
	 */
	public function getFinalHTML( $id, $ItemObj, $preview = false ) {

		// get item data
		$listing = new Listing( $id );

		// load template
		$template = new TemplatesModel( $listing->getTemplate() );
		$html = $template->processItem( $listing, $ItemObj, $preview );

		// strip invalid XML characters
		$html = $this->stripInvalidXml( $html );

		// run through the description blacklist
        $html = $this->stripBlacklistedWords( $html );

		// remove all <script> tags as well #47376
        $html = preg_replace('#<script(.*?)>(.*?)</script>#is', '', $html);

		// return html
		return $html;
	}

	public function getPreviewHTML( $template_id, $id = false ) {

		// get item data
		if ( $id ) {
			$listing = new Listing( $id );
		} else {
			$listing = WPLE_ListingQueryHelper::getItemForPreview();
		}
		if ( ! $listing ) {
			return '<div style="text-align:center; margin-top:5em;">You need to prepare at least one listing in order to preview a listing template.</div>';
		}

		// use latest post_content from product - moved to TemplatesModel
		// $post = get_post( $item['post_id'] );
		// if ( ! empty($post->post_content) ) $item['post_content'] = $post->post_content;

		// load template
		if ( ! $template_id ) {
			$template_id = $listing->getTemplate();
		}

		$template = new TemplatesModel( $template_id );
		$html = $template->processItem( $listing, false, true );

		// return html
		return $html;
	}

	/**
	 * @param $post_id
	 * @param $allow_https
	 * @param $checking_parent
	 * @depecated Use \WPLab\Ebay\Listings\Listing::getPrimaryImage() instead
	 * @return string
	 */
	public function getProductMainImageURL( $post_id, $allow_https = false, $checking_parent = false ) {

		// check if custom post meta field '_ebay_gallery_image_url' exists
		if ( get_post_meta( $post_id, '_ebay_gallery_image_url', true ) ) {
			return wple_normalize_url( get_post_meta( $post_id, '_ebay_gallery_image_url', true ), $allow_https );
		}
		// check if custom post meta field 'ebay_image_url' exists
		if ( get_post_meta( $post_id, 'ebay_image_url', true ) ) {
			return wple_normalize_url( get_post_meta( $post_id, 'ebay_image_url', true ), $allow_https );
		}

		// get main product image (post thumbnail)
		$image_url = ProductWrapper::getImageURL( $post_id );

		// check if featured image comes from nextgen gallery
		if ( $this->is_plugin_active('nextgen-gallery/nggallery.php') ) {
			$thumbnail_id = get_post_meta($post_id, '_thumbnail_id', true);
			if ( 'ngg' == substr($thumbnail_id, 0, 3) ) {
				$imageID   = str_replace('ngg-', '', $thumbnail_id);
				$picture   = nggdb::find_image($imageID);
				$image_url = $picture->imageURL;
				WPLE()->logger->info( "NGG - image_url: " . print_r($image_url,1) );
			}
		}

		// check for the WP Intense External Images plugin #30840
		if ( function_exists( 'ei_get_external_image' ) ) {
            $image_url = ei_get_external_image( $post_id );
        }

		// filter image_url hook
		$image_url = apply_filters_deprecated( 'wplister_get_product_main_image', array($image_url, $post_id), '2.8.4', 'wple_get_product_main_image' );
		$image_url = apply_filters( 'wple_get_product_main_image', $image_url, $post_id );

		// if no main image found, check parent product
		if ( ( $image_url == '' ) && ( ! $checking_parent ) ) {
			$post      = get_post( $post_id );
			$parent_id = isset( $post->post_parent ) ? $post->post_parent : false;
			if ( $parent_id ) {
				return $this->getProductMainImageURL( $parent_id, $allow_https, true );
			}
		}

		// ebay doesn't accept https - only http and ftp
		$image_url = wple_normalize_url( $image_url, $allow_https );

		WPLE()->logger->debug( "getProductMainImageURL( $post_id $allow_https ) returned: " . print_r($image_url,1) );
		return $image_url;

	} // getProductMainImageURL()

	/**
	 * @param $id
	 * @param $allow_https
	 * @depecated Use \WPLab\Ebay\Listings\Listing::getImages() instead
	 * @return mixed|null
	 */
	public function getProductImagesURL( $id, $allow_https = false ) {
		global $wpdb;

		if ( version_compare( WC_VERSION, '3.0', '>=' ) ) {
            $product = wc_get_product( $id );
            $results = $product ? $product->get_gallery_image_ids() : array();
        } else {
            $results = $wpdb->get_col( $wpdb->prepare(" 
			SELECT id 
			FROM {$wpdb->prefix}posts
			WHERE post_type = 'attachment' 
			  AND post_parent = %s
			ORDER BY menu_order
		", $id ) );
        }

		WPLE()->logger->debug( "getProductImagesURL( $id ) : " . print_r($results,1) );
        #echo "<pre>";print_r($results);echo"</pre>";#die();

		// fetch images using default size
		$size = get_option( 'wplister_default_image_size', 'full' );

		$images = array();
		foreach($results as $row) {
            $url = wp_get_attachment_url( $row );
            // $url = $row->guid ? $row->guid : wp_get_attachment_url( $row->id ); // disabled due to SSL issues #19164
			$images[] = $url;
		}

		// support for WooCommerce 2.0 Product Gallery
		if ( get_option( 'wplister_wc2_gallery_fallback','none' ) == 'none' ) $images = array(); // discard images if fallback is disabled

		// H.Nieri : Check if _ebay_image_gallery meta field exists and set $product_image_gallery if _ebay_image_gallery field exists
		$product_image_gallery = get_post_meta( $id, '_ebay_image_gallery', true );
		if ( empty ( $product_image_gallery ) )
			$product_image_gallery = get_post_meta( $id, '_product_image_gallery', true );

		// use parent product for single (split) variation
		if ( ProductWrapper::isSingleVariation( $id ) ) {
			$parent_id = ProductWrapper::getVariationParent( $id );

            // H.Nieri : Check if _ebay_image_gallery meta field exists and set $product_image_gallery if _ebay_image_gallery field exists
            $product_image_gallery = get_post_meta( $parent_id, '_ebay_image_gallery', true );
            if ( empty ( $product_image_gallery ) )
                $product_image_gallery = get_post_meta( $parent_id, '_product_image_gallery', true );

            // check for additional variation images (WooCommerce Additional Variation Images Addon)
            if ( class_exists('WC_Additional_Variation_Images') ) {

                $additional_var_images = get_post_meta( $id, '_wc_additional_variation_images', true );
                $additional_var_images = empty($additional_var_images) ? false : explode( ',', $additional_var_images );

                if ( is_array( $additional_var_images ) ) {
                    // Unset the $product_image_gallery and use the additional variation images instead #44939
                    if ( apply_filters( 'wple_exclusive_split_variation_gallery', true ) ) {
                        // clear the image gallery so the main product gallery doesn't get included in the split variation's
                        $product_image_gallery = array();
                    } else {
                        // merge gallery with the parent product
                        $product_image_gallery = implode( ',', $additional_var_images) .','. $product_image_gallery;
                    }

                    $size = get_option( 'wplister_default_image_size', 'full' );

                    // use the main variation image as the first/primary image
                    $images[] = ProductWrapper::getImageURL( $id );
                    foreach ( $additional_var_images as $attachment_id ) {

                        // get URL from attachment ID

                        $large_image_url = wp_get_attachment_image_src( $attachment_id, $size );
                        $image_url = wple_encode_url( $large_image_url[0] );
                        $images[] = $image_url;
                        WPLE()->logger->info( "found additional variation image: ".$image_url );

                    }
                }
            }
		}

		if ( $product_image_gallery ) {

			// build clean array with main image as first item
			$images = array();
			$images[] = $this->getProductMainImageURL( $id, $allow_https );

			$image_ids = explode(',', $product_image_gallery );
			foreach ( $image_ids as $image_id ) {
	            $url = wp_get_attachment_url( $image_id );
				if ( $url && ! in_array($url, $images) ) $images[] = $url;
			}

			WPLE()->logger->info( "found WC2 product gallery images for product #$id " . print_r($images,1) );
		}

		$product_images = array();
		foreach( $images as $imageurl ) {
			$product_images[] = wple_normalize_url( $imageurl, $allow_https );
		}

		// call wplister_product_images filter
		// hook into this from your WP theme's functions.php - this won't work in listing templates!
		$product_images = apply_filters_deprecated( 'wplister_product_images', array($product_images, $id), '2.8.4', 'wple_product_images' );
		$product_images = apply_filters( 'wple_product_images', $product_images, $id );

		WPLE()->logger->debug( "getProductImagesURL( $id $allow_https ) returned: " . print_r($product_images,1) );
		return $product_images;
	} // getProductImagesURL()


	/**
     * @deprecated 2.0.43 Misleading name
     * @see ItemBuilderModel::normalizeUrl()
     */
	function removeHttpsFromUrl( $url, $allow_https = false ) {
	    return wple_normalize_url( $url, $allow_https );
	}

    /**
     * Fix content URLs and convert to HTTPS if necessary
     *
     * @param string    $url
     * @param bool      $allow_https
     *
     * @return string
     */
	function normalizeUrl( $url, $allow_https = false ) {
		_deprecated_function( 'ItemBuilderModel::normalizeUrl', '3.6', 'wple_normalize_url');
        return wple_normalize_url( $url, $allow_https );
    }

	// encode special characters and spaces for PictureURL
	function encodeUrl( $url ) {
		_deprecated_function( 'ItemBuilderModel::encodeUrl', '3.6', 'wple_encode_url');
		return wple_encode_url( $url );
	}

	// Removes invalid XML characters
	// Not all valid utf-8 characters are allowed in XML documents. For XML 1.0 the standard says:
	// Char ::= #x9 | #xA | #xD | [#x20-#xD7FF] | [#xE000-#xFFFD] | [#x10000-#x10FFFF]
	function stripInvalidXml( $value ) {
	    $ret = "";
//	    $current;
	    if (empty($value))
	        return $ret;

	    $length = strlen($value);
	    for ($i=0; $i < $length; $i++) {

	        $current = ord($value[$i]);
	        if (($current == 0x9) ||
	            ($current == 0xA) ||
	            ($current == 0xD) ||
	            (($current >= 0x20) && ($current <= 0xD7FF)) ||
	            (($current >= 0xE000) && ($current <= 0xFFFD)) ||
	            (($current >= 0x10000) && ($current <= 0x10FFFF))) {

	            $ret .= chr($current);

	        } else {

	            $ret .= " ";

	        }
	    }

	    return $ret;
	} // stripInvalidXml()

    public function stripBlacklistedWords( $html ) {
	    $blacklist = get_option( 'wplister_description_blacklist', '' );

	    // fix newlines
        $blacklist = str_replace( "\r\n", "\n", $blacklist );
        $blacklist = str_replace( "\n\n", "\n", $blacklist );

        $lines = explode( "\n", $blacklist );

        $html = str_ireplace( $lines, '', $html );
        return $html;
    }

} // class ItemBuilderModel
