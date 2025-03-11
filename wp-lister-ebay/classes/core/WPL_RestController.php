<?php

/**
 * Class WPLE_Rest_Controller
 *
 * Example rest server that allows for CRUD operations on the wp_options table
 *
 */
## BEGIN PRO ##

use \WPLab\Ebay\Listings\Listing;

class WPLE_Rest_Response extends WP_HTTP_Response {

    public bool $success    = false;
    public string $message  = '';
    public array $errors    = array();

    public function __construct( $success, $message = '', $errors = [] ) {
         $this->success = $success;
         $this->errors  = $errors;
         $this->message = $message;
    }

}

class WPLE_Error {

	const SEVERITY_INFO = 'Info';
	const SEVERITY_WARN = 'Warning';
	const SEVERITY_ERROR = 'Error';

	public $severity;
	public $message;
	public $data;

	public function __construct( $message, $severity = self::SEVERITY_ERROR, $data = null) {
		$this->severity = $severity;
		$this->message = $message;
		$this->data = $data;
	}

	public function toArray() {
		return [
			'severity' => $this->severity,
			'message' => $this->message,
			'data'  => $this->data
		];
	}

}

class WPLE_Rest_Controller extends WC_REST_Controller {

    public $namespace = 'wple/';
    public $version   = 'v1';

    public function __construct() {
		add_action( 'plugins_loaded', array( $this, 'init') );
        //$this->init();
    }

    public function init() {
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
    }

    public function register_routes() {
        $namespace = $this->namespace . $this->version;

        register_rest_route( $namespace, '/listings', array(
            array(
                'methods'  => WP_REST_Server::READABLE,
                'callback' => array( $this, 'get_listings' ),
                'permission_callback'   => array( $this, 'permissions_check')
            ),
        ) );

	    register_rest_route( $namespace, '/grid-listings', array(
		    array(
			    'methods'  => WP_REST_Server::READABLE,
			    'callback' => array( $this, 'get_grid_listings' ),
			    'permission_callback'   => array( $this, 'permissions_check')
		    ),
	    ) );
	    register_rest_route( $namespace, '/grid-listings/(?P<id>(.*)+)', array(
		    array(
			    'methods'  => WP_REST_Server::EDITABLE,
			    'callback' => array( $this, 'edit_grid_listing' ),
			    'permission_callback'   => array( $this, 'permissions_check')
		    ),
	    ) );

        register_rest_route( $namespace, '/listings/(?P<id>(.*)+)', array(
            array(
                'methods'  => WP_REST_Server::READABLE,
                'callback' => array( $this, 'get_listing' ),
                'permission_callback'   => array( $this, 'permissions_check')
            ),
            array(
                'methods'  => WP_REST_Server::EDITABLE,
                'callback' => array( $this, 'edit_listing' ),
                'permission_callback'   => array( $this, 'permissions_check')
            ),
        ) );

	    register_rest_route( $namespace, '/listing', array(
		    array(
			    'methods'  => WP_REST_Server::CREATABLE,
			    'callback' => array( $this, 'prepare_listing' ),
			    'permission_callback'   => array( $this, 'permissions_check')
		    ),
	    ) );

	    register_rest_route( $namespace, '/listing/verify', array(
		    array(
			    'methods'  => WP_REST_Server::EDITABLE,
			    'callback' => array( $this, 'verify_listing' ),
			    'permission_callback'   => array( $this, 'permissions_check')
		    ),
	    ) );

	    register_rest_route( $namespace, '/listing/publish', array(
		    array(
			    'methods'  => WP_REST_Server::EDITABLE,
			    'callback' => array( $this, 'publish_listing' ),
			    'permission_callback'   => array( $this, 'permissions_check')
		    ),
	    ) );

	    register_rest_route( $namespace, '/listing/revise', array(
		    array(
			    'methods'  => WP_REST_Server::EDITABLE,
			    'callback' => array( $this, 'revise_listing' ),
			    'permission_callback'   => array( $this, 'permissions_check')
		    ),
	    ) );

	    register_rest_route( $namespace, '/listing/end', array(
		    array(
			    'methods'  => WP_REST_Server::EDITABLE,
			    'callback' => array( $this, 'end_listing' ),
			    'permission_callback'   => array( $this, 'permissions_check')
		    ),
	    ) );

    }

	/**
	 * Check whether a given request has permission to manage listings.
	 *
	 * @param  WP_REST_Request $request Full details about the request.
	 *
	 * @return true|WP_HTTP_Response
	 */
	public function permissions_check( $request ) {

		if ( ! current_user_can( 'manage_ebay_listings' ) ) {
			$error = new WPLE_Error( 'Sorry, you are not allowed to manage WP-Lister on this site' );
			return $this->return_update_response( false, $error->message, null, [$error->toArray()], 401 );
		}

		return true;
	}

    public function get_listings( WP_REST_Request $request ) {

	    $page       = $request->get_param( 'page' ) ?? 1;
		$per_page   = $request->get_param( 'per_page' ) ?? 10;

		// Filters
	    $filters['listing_status'] = $request->get_param( 'listing_status' ) ?? 'all';
		$filters['profile_id']     = $request->get_param( 'profile_id' ) ?? 0;
		$filters['account_id']     = $request->get_param( 'account_id' ) ?? 0;
		$filters['s']              = $request->get_param( 'search' ) ?? '';

        $result = WPLE_ListingQueryHelper::getPageItems( $page, $per_page, $filters );
		//var_dump($result);
	    $response   = [];
        foreach ($result->items as $key => $item) {
			$listing = $item;
	        $response[] = $this->prepare_listing_for_response( $listing );
        }

        return rest_ensure_response($response);
    }

	public function get_grid_listings( WP_REST_Request $request ) {

		$current_page = 1;
		$per_page     = get_option( 'wplister_grid_page_size', 10000 );
		$result       = WPLE_ListingQueryHelper::getPageItems( $current_page, $per_page );

		foreach ($result->items as $key => &$item) {

			// remove bulky data to improve performance
			unset( $result->items[$key]['details'] );
			unset( $result->items[$key]['profile_data'] );
			unset( $result->items[$key]['post_content'] );
			unset( $result->items[$key]['history'] );
			$result->items[$key]['last_errors'] = 'todo';

			// decode HTML entities on title
			$result->items[$key]['auction_title'] = html_entity_decode( $result->items[$key]['auction_title'] );

			// add meta data
			$result->items[$key]['sku']                   = get_post_meta( $result->items[$key]['post_id'], '_sku', true );
			$result->items[$key]['_ebay_start_price']     = get_post_meta( $result->items[$key]['post_id'], '_ebay_start_price', true );
			$result->items[$key]['_amazon_price']         = get_post_meta( $result->items[$key]['post_id'], '_amazon_price', true );
			$result->items[$key]['_amazon_minimum_price'] = get_post_meta( $result->items[$key]['post_id'], '_amazon_minimum_price', true );
			$result->items[$key]['_amazon_maximum_price'] = get_post_meta( $result->items[$key]['post_id'], '_amazon_maximum_price', true );
			$result->items[$key]['_regular_price']        = get_post_meta( $result->items[$key]['post_id'], '_regular_price', true );
			$result->items[$key]['_sale_price']           = get_post_meta( $result->items[$key]['post_id'], '_sale_price', true );
			$result->items[$key]['_msrp_price']           = get_post_meta( $result->items[$key]['post_id'], '_msrp_price', true );

			// get thumbnail
			$post_id   = $result->items[$key]['post_id'];
			$thumbnail = wp_get_attachment_image_src( get_post_thumbnail_id( $post_id ), "thumbnail" );
			$result->items[$key]['thumb'] = $thumbnail[0];
		}

		return $result;
	}

	public function get_grid_listings2( WP_REST_Request $request ) {

		$page       = $request->get_param( 'page' ) ?? 1;
		$per_page   = $request->get_param( 'per_page' ) ?? 10;

		// Filters
		$filters['listing_status'] = $request->get_param( 'listing_status' ) ?? 'all';
		$filters['profile_id']     = $request->get_param( 'profile_id' ) ?? 0;
		$filters['account_id']     = $request->get_param( 'account_id' ) ?? 0;
		$filters['s']              = $request->get_param( 'search' ) ?? '';

		$result = WPLE_ListingQueryHelper::getPageItems( $page, $per_page, $filters );
		//var_dump($result);
		$response   = [];
		foreach ($result->items as $key => $item) {
			$product        = wc_get_product( $item['post_id'] );
			$listing_obj    = new Listing( $item['id'] );
			$profile_data   = $listing_obj->getProfileDetails();

			$listing = [
				'id'                        => $listing_obj->getId(),
				'ebay_id'                   => $listing_obj->getEbayId(),
				'sku'                       => $listing_obj->getProduct()->get_sku(),
				'auction_title'             => $listing_obj->getTitle(),
				'_ebay_start_price'         => $listing_obj->getStartPrice(),
				'_msrp_price'               => get_post_meta( $item['post_id'], '_msrp_price', true ),
				'_amazon_price'             => get_post_meta( $item['post_id'], '_amazon_price', true ),
				'_amazon_minimum_price'             => get_post_meta( $item['post_id'], '_amazon_minimum_price', true ),
				'_amazon_maximum_price'             => get_post_meta( $item['post_id'], '_amazon_maximum_price', true ),
				'_regular_price'            => $product->get_regular_price(),
				'_sale_price'               => $product->get_sale_price(),
				'thumb'                     => get_the_post_thumbnail_url($item['post_id']),
				'quantity'                  => $listing_obj->getQuantity(),
				'final_quantity'            => $listing_obj->getStockQuantity(),
				'listing_type'              => $listing_obj->getType(),
				'listing_duration'          => $listing_obj->getDuration(),
				'condition'                 => $profile_data['condition_id'],
				'condition_description'     => $profile_data['condition_description'],
				'epid'                      => $listing_obj->getProductProperty( '_ebay_epid' ),
				'upc'                       => $listing_obj->getProductProperty( '_ebay_upc' ),
				'ean'                       => $listing_obj->getProductProperty( '_ebay_ean' ),
				'isbn'                      => $listing_obj->getProductProperty( '_ebay_isbn'),
				'mpn'                       => $listing_obj->getProductProperty( '_ebay_mpn'),
				'brand'                     => $listing_obj->getProductProperty( '_ebay_brand'),
				'buyitnow_price'            => $listing_obj->getBuyItNowPrice(),
				'reserve_price'             => $listing_obj->getReservePrice(),
				'primary_image'             => $listing_obj->getPrimaryImage( $item['post_id'], true ),

				'global_shipping'           => $profile_data['global_shipping'],
				'ebay_plus'                 => $profile_data['ebayplus_enabled'],
				'ebay_url'                  => $listing_obj->getViewItemUrl(),
				'status'            => $listing_obj->getStatus(),
				'locked'            => $listing_obj->isLocked() ? 1 : 0,
				'wc_product_id'     => $listing_obj->getProductId(),
				'wc_parent_id'      => $listing_obj->getParentId(),
				'profile_id'        => $listing_obj->getProfileId(),
				'account_id'        => $listing_obj->getAccountId(),

			];

			$response[] = $listing;
		}

		return rest_ensure_response($response);
	}

	public function get_listing( WP_REST_Request $request ) {
		$id = $this->get_id_from_request( $request );
		$listing = ListingsModel::getItem( $id );

		if ( !$listing ) {
			$error = new WPLE_Error( 'No listing found matching the request' );
			return $this->return_update_response( false, $error->message, null, [$error->toArray()], 404 );
		}

		$listing = $this->prepare_listing_for_response( $listing );

		return rest_ensure_response($listing);
	}

	/**
	 * @param $id
	 *
	 * @return array
	 */
    public function get_item_data( $id ) {
        $item = ListingsModel::getItem( $id );

		return $this->prepare_listing_for_response( $item );
    }

	/**
	 * @param bool $success
	 * @param string $msg
	 * @param ?int $id
	 * @param ?array $errors
	 * @param int $status
	 *
	 * @return WP_HTTP_Response
	 */
    protected function return_update_response( $success, $msg, $id = null, $errors = null, $status = 200 ) {
        $response = new stdClass();
        $response->success      = $success;
        $response->errors       = $errors;
        $response->message      = $msg;

		if ( $id ) {
			$listing = ListingsModel::getItem( $id );
			if ( $listing ) {
				$response->listing = $this->prepare_listing_for_response( $listing );
			}
		}

		return new WP_HTTP_Response( $response, $status );
    }

	/**
	 *
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_HTTP_Response
	 */
    public function edit_listing( WP_REST_Request $request ) {
        $params = $request->get_params();

        if ( empty( $params['id'] ) ) {
			$error = new WPLE_Error('Missing `id` parameter' );
            return $this->return_update_response( false, $error->message, null, [$error->toArray()], 400 );
        }

		$listing_id = intval($params['id']);
		unset( $params['id'] );

		$allowed_properties = [];

	    // check if listing ID exists
	    if ( ! WPLE_ListingQueryHelper::getStatus( $listing_id ) ) {
			$error = new WPLE_Error( sprintf('Listing #%d not found', $listing_id) );
		    return $this->return_update_response( false, $error->message, null, [$error->toArray()], 404);
	    }
	    WPLE()->logger->info( 'REST params: '. print_r($params,1));
	    // update listing record in WPLE
	    $result = self::update_listing( $listing_id, $params );
	    return rest_ensure_response( self::return_update_response( $result->success, $result->message, $listing_id, $result->errors ) );

    }

	/**
	 * A dedicated EditListing endpoint for the Grid.
	 *
	 * This method takes care of saving the non-eBay fields such as Amazon Prices
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_HTTP_Response
	 */
	public function edit_grid_listing( WP_REST_Request $request ) {
		$params = $request->get_params();

		if ( ! isset( $params['id'] ) || empty( $params['id'] ) ) {
			return new WP_Error( 'no-param', __( 'No id param' ) );
		}

		$body = $request->get_body();

		if ( empty( $body ) ) {
			return new WP_Error( 'no-body', __( 'Request body empty' ) );
		}

		$decoded_body = json_decode( $body );

		// return new WP_Error( 'no-param', __( 'Yes, body is: '.print_r($decoded_body,1) ) );

		if ( $decoded_body ) {
			if ( isset( $decoded_body->id, $decoded_body->col, $decoded_body->val ) ) {

				// check if listing ID exists
				if ( ! WPLE_ListingQueryHelper::getStatus( $decoded_body->id ) ) {
					return false;
				}

				// update listing record in WPLE
				$result = self::update_grid_listing( $decoded_body->id, $decoded_body->col, $decoded_body->val );
				return self::return_update_response( $result->success, $result->msg, $decoded_body->id, $result->errors );

			}
		}

		return false;
	}


	public function update_grid_listing( $id, $col, $val ) {
		if ( ! class_exists('ListingsModel' ) ) return new WPLE_Rest_Response( false, 'wple missing' );

		$editable_columns = array(
			'sku',
			'auction_title',
			'price',
			'quantity',
			'profile_id',
			'locked',
			'status',
			'_ebay_start_price',
			'_amazon_price',
			'_amazon_minimum_price',
			'_amazon_maximum_price',
			'_regular_price',
			'_msrp_price',
			'_sale_price',
		);

		// check if column key is valid and editable
		if ( ! in_array( $col, $editable_columns ) ) {
			return new WPLE_Rest_Response( false, 'invalid column key '.$col );
		}

		// get previous item data
		$previous_data = self::get_item_data( $id );

		// perform status change - before updating listing record
		if ( 'status' == $col ) {

			$previous_status = $previous_data['status'];

			switch ($val) {
				case 'prepared':
					# set status to prepared
					if ( ! in_array( $previous_status, array('prepared','verified','ended') ) ) {
						return new WPLE_Rest_Response( false, "It is not possible to change the listing status from $previous_status to $val." );
					}
					ListingsModel::updateListing( $id, array( $col => $val ) );
					return new WPLE_Rest_Response( true );
					break;

				case 'verified':
					# verify listing...
					$results = apply_filters( 'wple_verify_item', $id );
					if ( is_array($results) ) {
						if ($results[0]->success) {
							// ListingsModel::updateListing( $id, array( $col => $val ) ); // status should already be updated
						}
						return new WPLE_Rest_Response( $results[0]->success, 'verified', $results[0]->errors );
					}
					return new WPLE_Rest_Response( false, 'unknown result: '.$results );
					break;

				case 'published':
					# publish listing...
					$results = apply_filters( 'wple_publish_item', $id );
					if ( is_array($results) ) {
						if ($results[0]->success) {
							// ListingsModel::updateListing( $id, array( $col => $val ) ); // status should already be updated
						}
						return new WPLE_Rest_Response( $results[0]->success, 'published', $results[0]->errors );
					}
					return new WPLE_Rest_Response( false, 'unknown result: '.$results );
					break;

				case 'ended':
					# end listing...
					if ( ! in_array( $previous_status, array('published','changed') ) ) {
						return new WPLE_Rest_Response( false, "It is not possible to change the listing status from $previous_status to $val." );
					}
					$results = apply_filters( 'wple_end_item', $id );
					if ( is_array($results) ) {
						if ($results[0]->success) {
							// ListingsModel::updateListing( $id, array( $col => $val ) ); // status should already be updated
						}
						return new WPLE_Rest_Response( $results[0]->success, 'ended', $results[0]->errors );
					}
					return new WPLE_Rest_Response( false, 'unknown result: '.$results );
					break;

				case 'changed':
					# set status to changed
					if ( ! in_array( $previous_status, array('published','changed') ) ) {
						return new WPLE_Rest_Response( false, "It is not possible to change the listing status from $previous_status to $val." );
					}
					ListingsModel::updateListing( $id, array( $col => $val ) );
					return new WPLE_Rest_Response( true, 'Listing status was set to "changed".' );
					break;

				default:
					# unknown status
					return new WPLE_Rest_Response( false, 'unknown status: '.$val );
					break;
			}
		}


		// update SKU (WPLE patch)
		if ( 'sku' == $col ) {
			$col = '_sku';
		}


		// process meta data
		$product_meta_fields = array(
			'_sku',
			'_ebay_start_price',
			'_amazon_price',
			'_amazon_minimum_price',
			'_amazon_maximum_price',
			'_msrp_price',
			'_sale_price',
			'_price',
		);

		if ( in_array( $col, $product_meta_fields ) ) {
			$post_id = $previous_data['post_id'];
			update_post_meta( $post_id, $col, $val );
		}

		ListingsModel::updateListing( $id, array( $col => $val ) );

		// check if profile needs to be reapplied
		if ( in_array( $col, array('profile_id') ) ) {
			$profilesModel = new ProfilesModel();
			$profile = $profilesModel->getItem( intval($val) );
			$listingsModel = new ListingsModel();
			$listingsModel->reapplyProfileToItem( $id );
		}

		return new WPLE_Rest_Response( true );
	}


	public function prepare_listing( WP_REST_Request $request ) {
		$product_id = intval( $request->get_param( 'product_id' ) );
		$profile_id = intval( $request->get_param( 'profile_id' ) );

		if ( empty( $product_id ) || empty( $profile_id ) ) {
			$error = new WPLE_Error( 'Missing required parameters' );
			return $this->return_update_response( false, $error->message, null, [$error->toArray()], 400 );
		}

		$lm = new ListingsModel();

		// prepare new listings from products
		$listing_id = $lm->prepareProductForListing( $product_id, $profile_id );

		if ( false === $listing_id ) {
			$errors = [];
			foreach ( $lm->warnings as $warning ) {
				$errors[] = new WPLE_Error( $warning, WPLE_Error::SEVERITY_WARN );

			}
			foreach ( $lm->errors as $error ) {
				$errors[] = new WPLE_Error( $error, WPLE_Error::SEVERITY_ERROR );
			}
			return rest_ensure_response( self::return_update_response( false, 'Unable to prepare product for listing.', null, $errors, 400 ) );
		}

		$item = $lm->getItem( $listing_id );

		// get and apply profile
		$profilesModel = new ProfilesModel();
		$profile = $profilesModel->getItem( $profile_id );
		$lm->applyProfileToItem( $profile, $item );


		return rest_ensure_response( self::return_update_response( true, 'Successfully prepared product for listing', $listing_id, [], 201 ) );
	}

	public function verify_listing( WP_REST_Request $request ) {
		$listing_id = $this->get_id_from_request( $request );

		if ( !$listing_id ) {
			$error = new WPLE_Error('ID is invalid');
			return $this->return_update_response( false, $error->message, null, [$error->toArray()], 400 );
		}

		$account_id = WPLE_ListingQueryHelper::getAccountID( $listing_id );
		WPLE()->logger->info('verifying listing '.$listing_id.' - account '.$account_id );

		// call EbayController
		WPLE()->initEC( $account_id );
		$results = WPLE()->EC->verifyItems( $listing_id );
		WPLE()->EC->closeEbay();

		WPLE()->logger->info('verified listing '.$listing_id );

		$result = $this->process_response_from_ebay( $results );

		if ( $result->success ) {
			$message = sprintf( __( 'Listing #%d was verified successfully', 'wp-lister-for-ebay' ), $listing_id );
			return rest_ensure_response( self::return_update_response( $result->success, $message, $listing_id, $result->errors ) );
		} else {
			$message = sprintf( __( 'There were some problems verifying listing #%d', 'wp-lister-for-ebay' ), $listing_id );
			return rest_ensure_response( self::return_update_response( $result->success, $message, $listing_id, $result->errors, 400 ) );
		}
	}

	public function publish_listing( WP_REST_Request $request ) {
		$listing_id = $this->get_id_from_request( $request );

		if ( !$listing_id ) {
			$error = new WPLE_Error( 'Invalid `id` parameter' );
			return $this->return_update_response( false, $error->message, null, [$error->toArray()], 400 );
		}

		$account_id = WPLE_ListingQueryHelper::getAccountID( $listing_id );
		WPLE()->logger->info('publishing listing '.$listing_id.' - account '.$account_id );

		// call EbayController
		WPLE()->initEC( $account_id );
		$results = WPLE()->EC->sendItemsToEbay( $listing_id );
		WPLE()->EC->closeEbay();

		WPLE()->logger->info('published listing '.$listing_id );

		$result = $this->process_response_from_ebay( $results );

		if ( $result->success ) {
			$message = sprintf( __( 'Listing #%d was published successfully', 'wp-lister-for-ebay' ), $listing_id );
			return rest_ensure_response( self::return_update_response( $result->success, $message, $listing_id, $result->errors, 201 ) );
		} else {
			$message = sprintf( __( 'There were some problems publishing listing #%d', 'wp-lister-for-ebay' ), $listing_id );
			return rest_ensure_response( self::return_update_response( $result->success, $message, $listing_id, $result->errors, 400 ) );
		}


	}

	public function revise_listing( WP_REST_Request $request ) {
		$listing_id = $this->get_id_from_request( $request );

		if ( !$listing_id ) {
			$error = new WPLE_Error( 'Invalid ID parameter' );
			return $this->return_update_response( false, $error->message, null, [$error->toArray()], 400 );
		}

		$account_id = WPLE_ListingQueryHelper::getAccountID( $listing_id );
		WPLE()->logger->info('revising listing '.$listing_id.' - account '.$account_id );

		// call EbayController
		WPLE()->initEC( $account_id );
		$results = WPLE()->EC->reviseItems( $listing_id );
		WPLE()->EC->closeEbay();

		WPLE()->logger->info('published listing '.$listing_id );

		$result = $this->process_response_from_ebay( $results );

		if ( $result->success ) {
			$message = sprintf( __( 'Listing #%d was revised successfully', 'wp-lister-for-ebay' ), $listing_id );
			return self::return_update_response( $result->success, $message, $listing_id, $result->errors );
		} else {
			$message = sprintf( __( 'There were some problems revising listing #%d', 'wp-lister-for-ebay' ), $listing_id );
			return self::return_update_response( $result->success, $message, $listing_id, $result->errors, 400 );
		}
	}

	public function end_listing( WP_REST_Request $request ) {
		$listing_id = $this->get_id_from_request( $request );

		if ( !$listing_id ) {
			$error = new WPLE_Error( 'Invalid ID parameter' );
			return $this->return_update_response( false, $error->message, null, [$error->toArray()], 400 );
		}

		$account_id = WPLE_ListingQueryHelper::getAccountID( $listing_id );
		WPLE()->logger->info('ending listing '.$listing_id.' - account '.$account_id );

		// if listing_id is an eBay Item ID, find the listing_id automatically
		if ( strlen( $listing_id ) > 10 ) {
			$listing = WPLE_ListingQueryHelper::findItemByEbayID( $listing_id, false );
			if ( $listing ) $listing_id = $listing->id;
		}

		// call EbayController
		WPLE()->initEC( $account_id );
		$results = WPLE()->EC->endItemsOnEbay( $listing_id );
		WPLE()->EC->closeEbay();

		WPLE()->logger->info('ended listing '.$listing_id );

		$result = $this->process_response_from_ebay( $results );

		if ( $result->success ) {
			$message = sprintf( __( 'Listing #%d was ended successfully', 'wp-lister-for-ebay' ), $listing_id );
		} else {
			$message = sprintf( __( 'There were some problems ending listing #%d', 'wp-lister-for-ebay' ), $listing_id );
		}

		return rest_ensure_response( self::return_update_response( $result->success, $message, $listing_id, $result->errors ) );
	}

	/**
	 *
	 * @param array $listing
	 *
	 * @return array
	 */
	private function prepare_listing_for_response( $listing ) {
		$post_id   = $listing['post_id'];
		$wc_product = wc_get_product( $post_id );

		if ( !$wc_product ) {
			$error = new WPLE_Error('Listing is missing its linked product');
			return $this->return_update_response( false, $error->message, null, [$error->toArray()], 400 );
		}

		$listing_obj = new Listing( $listing['id'] );
		$profile_data = $listing_obj->getProfileDetails();

		$response = [
			'id'                        => $listing_obj->getId(),
			'ebay_id'                   => $listing_obj->getEbayId(),
			'sku'                       => $listing_obj->getProduct()->get_sku(),
			'title'                     => $listing_obj->getTitle(),
			'subtitle'                  => $listing_obj->getProductProperty( '_ebay_subtitle' ),
			'price'                     => $listing_obj->getStartPrice(),
			'quantity'                  => $listing_obj->getQuantity(),
			'final_quantity'            => $listing_obj->getStockQuantity(),
			'listing_type'              => $listing_obj->getType(),
			'listing_duration'          => $listing_obj->getDuration(),
			'condition'                 => $profile_data['condition_id'],
			'condition_description'     => $profile_data['condition_description'],
			'epid'                      => $listing_obj->getProductProperty( '_ebay_epid' ),
			'upc'                       => $listing_obj->getProductProperty( '_ebay_upc' ),
			'ean'                       => $listing_obj->getProductProperty( '_ebay_ean' ),
			'isbn'                      => $listing_obj->getProductProperty( '_ebay_isbn'),
			'mpn'                       => $listing_obj->getProductProperty( '_ebay_mpn'),
			'brand'                     => $listing_obj->getProductProperty( '_ebay_brand'),
			'buyitnow_price'            => $listing_obj->getBuyItNowPrice(),
			'reserve_price'             => $listing_obj->getReservePrice(),
			'primary_image'             => $listing_obj->getPrimaryImage( $post_id, true ),
			'images'                    => $listing_obj->getImages( true ),
			'global_shipping'           => $profile_data['global_shipping'],
			'ebay_plus'                 => $profile_data['ebayplus_enabled'],
			'ebay_url'                  => $listing_obj->getViewItemUrl(),

			'date_created'      => $listing_obj->getDateCreated(),
			'date_published'    => $listing_obj->getDatePublished(),
			'date_finished'     => $listing_obj->getDateFinished(),
			'end_date'          => $listing_obj->getEndDate(),
			'relist_date'       => $listing_obj->getRelistDate(),
			'status'            => $listing_obj->getStatus(),
			'locked'            => $listing_obj->isLocked() ? 1 : 0,
			'variations'        => $listing_obj->getVariations(),
			'wc_product_id'     => $listing_obj->getProductId(),
			'wc_parent_id'      => $listing_obj->getParentId(),
			'profile_id'        => $listing_obj->getProfileId(),
			'account_id'        => $listing_obj->getAccountId(),

		];

		return $response;
	}

	private function process_response_from_ebay( $response ) {
		$return             = new stdClass();
		$return->errors     = [];
		$return->success    = true;


		foreach ( $response as $result ) {
			if ( is_array( $result->errors ) ) {
				foreach ( $result->errors as $original_error_obj ) {
					if ( ! $result->success ) {
						$return->success = false;
					}

					// clone error object and remove HtmlMessage
					$error_obj = clone $original_error_obj;
					unset( $error_obj->HtmlMessage );

					$return->errors[] = [
						'severity' => $error_obj->SeverityCode,
						'message' => htmlspecialchars( $error_obj->LongMessage )
					];
				} // foreach error or warning
			}
		}

		return $return;
	}

	/**
	 * Get the ID from the parameters list
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return false|int
	 */
	private function get_id_from_request( $request ) {
		$params = $request->get_params();

		if ( empty( $params['id'] ) ) {
			return false;
		}

		return intval($params['id']);
	}

    public function update_listing( $id, $params ) {
        if ( ! class_exists('ListingsModel' ) ) {
	        $error = new WPLE_Error( 'ListingsModel class not found' );
			return new WPLE_Rest_Response( false, $error->message, [$error->toArray()] );
        }

        $editable_columns = array(
			// eBay Options
			'title'                         => '_ebay_title',
			'subtitle'                      => '_ebay_subtitle',
			'price'                         => '_ebay_start_price',
			'listing_type'                  => '_ebay_auction_type',
			'listing_duration'              => '_ebay_listing_duration',
			'condition'                     => '_ebay_condition_id',
			'condition_description'         => '_ebay_condition_description',

	        // Product Idents
	        'epid'                          => '_ebay_epid',
			'upc'                           => '_ebay_upc',
			'ean'                           => '_ebay_ean',
			'isbn'                          => '_ebay_isbn',
			'mpn'                           => '_ebay_mpn',
			'brand'                         => '_ebay_brand',

			// advanced options
	        'buyitnow_price'                => '_ebay_buynow_price',
			'reserve_price'                 => '_ebay_reserve_price',
			'gallery_image_url'             => '_ebay_gallery_image_url',
	        'global_shipping'               => '_ebay_global_shipping',
			'ebay_plus'                     => '_ebay_ebayplus_enabled',
			'best_offer'                    => '_ebay_bestoffer_enabled',
			'auto_accept_price'             => '_ebay_bo_autoaccept_price',
			'minimum_offer_price'           => '_ebay_bo_minimum_price',
			'immediate_payment'             => '_ebay_autopay',
			'payment_policy_id'             => '_ebay_seller_payment_profile_id',
			'return_policy_id'              => '_ebay_seller_return_profile_id',
			'payment_instructions'          => '_ebay_payment_instructions',
			'primary_ebay_category_id'      => '_ebay_category_1_id',
			'secondary_ebay_category_id'    => '_ebay_category_2_id',
			'primary_store_category_id'     => '_ebay_store_category_1_id',
			'secondary_store_category_id'   => '_ebay_store_category_2_id',

        );

		foreach ( $params as $key => $value ) {
			if ( !in_array( $key, array_keys( $editable_columns ) ) ) {
				$error = new WPLE_Error( 'Invalid property: ' . $key );
				return $this->return_update_response( false, $error->message, null, [$error->toArray()], 400 );
			}
		}

	    // get previous item data
	    $previous_data = self::get_item_data( $id );

	    // update SKU (WPLE patch)
	    if ( isset( $params['sku'] ) ) {
		    $params['_sku'] = $params['sku'];
		    unset( $params['sku'] );
	    }

	    foreach ( $params as $col => $value ) {
			$meta_key = $editable_columns[ $col ];

			if ( $meta_key ) {
				update_post_meta( $previous_data['wc_product_id'], $meta_key, $value );
			}
	    }

		$lm = new ListingsModel();
		$lm->reapplyProfileToItem( $id );

        return new WPLE_Rest_Response( true );
    }
} // class WPLE_Rest_Controller

## END PRO ##