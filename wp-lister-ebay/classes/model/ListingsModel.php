<?php

use WPLab\Ebay\Listings\Listing;

/**
 * ListingsModel class
 *
 * responsible for managing listings and talking to ebay
 *
 */

class ListingsModel extends WPL_Model {

	const TABLENAME = 'ebay_auctions';

	var $_session;
	var $_cs;
	var $errors = array();
	var $warnings = array();
	var $result;

	public $handle_error_code;

	public function __construct() {
		parent::__construct();

		global $wpdb;
		$this->tablename = $wpdb->prefix . 'ebay_auctions';
		$this->result = new stdClass(); // fix error "Attempt to assign property "success" on null" #51265
	}

	/* the following methods could go into WPLE_ListingQueryHelper since they use wpdb instead of EbatNs_DatabaseProvider */

	static function getItem( $id ) {
		global $wpdb;
		$table = $wpdb->prefix . self::TABLENAME;

		$item = $wpdb->get_row( $wpdb->prepare("
			SELECT *
			FROM $table
			WHERE id = %s
		", $id
		), ARRAY_A );

		if ( !empty($item) ) $item['profile_data'] = self::decodeObject( $item['profile_data'], true );
		// $item['details'] = self::decodeObject( $item['details'] );

		return $item;
	}

	static function getItemByEbayID( $id, $decode_details = true ) {
		global $wpdb;
		$table = $wpdb->prefix . self::TABLENAME;

		$item = $wpdb->get_row( $wpdb->prepare("
			SELECT *
			FROM $table
			WHERE ebay_id = %s
		", $id ) );
		if (!$item) return false;
		if (!$decode_details) return $item;

		$item->profile_data = self::decodeObject( $item->profile_data, true );
		$item->details = self::decodeObject( $item->details );

		return $item;
	}

	static function getEbayIDFromID( $id ) {
		global $wpdb;
		$table = $wpdb->prefix . self::TABLENAME;

		$item = $wpdb->get_var( $wpdb->prepare("
			SELECT ebay_id
			FROM $table
			WHERE id         = %s
			  AND status <> 'archived'
		", $id ) );
		return $item;
	}


	static function getHistory( $ebay_id ) {
		global $wpdb;
		$table = $wpdb->prefix . self::TABLENAME;

		$item = $wpdb->get_var( $wpdb->prepare("
			SELECT history
			FROM $table
			WHERE ebay_id = %s
		", $ebay_id ) );
		return maybe_unserialize( $item );
	}

	static function setHistory( $ebay_id, $history ) {
		global $wpdb;
		$table = $wpdb->prefix . self::TABLENAME;

		$data = array(
			'history' => maybe_serialize( $history )
		);

		$result = $wpdb->update( $table, $data, array( 'ebay_id' => $ebay_id ) );
		return $result;
	}

	static function addItemIdToHistory( $ebay_id, $previous_id ) {

		$history = self::getHistory( $ebay_id );

		WPLE()->logger->info( "addItemIdToHistory($ebay_id, $previous_id) " );
		WPLE()->logger->info( "history: ".print_r($history,1) );

		// init empty history
		if ( ! isset($history['previous_ids'] ) ) {
			$history = array(
				'previous_ids' => array()
			);
		}

		// return if ID already exists in history
		if ( in_array( $previous_id, $history['previous_ids'] ) ) return;

		// add ID to history
		$history['previous_ids'][] = $previous_id;

		// update history
		self::setHistory( $ebay_id, $history );

	}


	static function isUsingEPS( $id ) {
		WPLE()->logger->info( "isUsingEPS( $id ) " );

		$listing_item    = self::getItem( $id );
		$profile_details = $listing_item['profile_data']['details'];

        $with_additional_images = isset( $profile_details['with_additional_images'] ) ? $profile_details['with_additional_images'] : false;
        if ( $with_additional_images == '0' ) $with_additional_images = false;

        return $with_additional_images;
	}

	static function isUsingVariationImages( $id ) {
		WPLE()->logger->info( "isUsingVariationImages( $id ) " );

		$listing_item = self::getItem( $id );
		$profile_details = $listing_item['profile_data']['details'];

        $with_variation_images = isset( $profile_details['with_variation_images'] ) ? $profile_details['with_variation_images'] : false;
        if ( $with_variation_images == '0' ) $with_variation_images = false;

        return $with_variation_images;
	}


	// check if there are new variations in WooCommerce which do not exist in the cache
    static function matchCachedVariations( $item, $filter_unchanged = false, $force = false ) {
        $success   = true;
        $new_count = 0;

        // make sure we have an actual listing item
        if ( is_numeric( $item ) ) $item = self::getItem( $item );
        if ( ! $item ) {
            WPLE()->logger->info( '$item not found - returning FALSE' );
            return false;
        }

        $cached_variations  = maybe_unserialize( $item['variations'] );
        $product_variations = ProductWrapper::getListingVariations( $item['post_id'] );

        // TODO: update cache
        if ( empty($cached_variations) ) {
            WPLE()->logger->info( '$cached_variations is empty. Returning FALSE' );
            return false;
        }

        // loop product variations (what we want listed)
        foreach ( $product_variations as $key => $pv ) {

            // check if variation exists in cache
            if ( $cv = self::checkIfVariationExistsInCache( $pv, $cached_variations ) ) {

            	// check if price or quantity have changed - if told to do so
            	if ( $filter_unchanged && !$force ) {
            	    WPLE()->logger->info( 'filtering variation inventory '. $key );
            		if ( ! self::checkIfVariationInventoryHasChanged( $pv, $cv, $item ) ) {
            			// remove unchanged variations from the list
	                    unset( $product_variations[ $key ] );
            		}
            	}

            } else {

                // check stock level
                if ( $pv['stock'] > 0 ) {

                    $new_count++;
                    $success = false;

                     WPLE()->logger->debug('found NEW variation: '.print_r( $pv, 1 ) );
                    // WPLE()->logger->info( 'found NEW variation: '.$pv['sku'] );

                } else {
                    // no stock, so just remove from list
                    unset( $product_variations[ $key ] );
                     WPLE()->logger->info( 'removed out of stock variation: '.$pv['sku'] );
                }

            }

        }

        $result = new stdClass();
        $result->success    = $success;
        $result->new_count  = $new_count;
        $result->variations = $product_variations;

        return $result;
    } // matchCachedVariations()

    static function checkIfVariationExistsInCache( $pv, &$cached_variations ) {

        // loop cached variations
        foreach ( $cached_variations as $key => $cv ) {

            // compare SKU
            if ( $pv['sku'] == $cv['sku'] ) {

                // remove from list
                unset( $cached_variations[ $key ] );

                WPLE()->logger->info('matched variation by SKU: '.$cv['sku'] );
                return $cv;
            }

            // compare variation attributes
            if ( serialize( $pv['variation_attributes'] ) == serialize( $cv['variation_attributes'] ) ) {

                // remove from list
                unset( $cached_variations[ $key ] );

                WPLE()->logger->info('matched variation by attributes: '.serialize($cv['variation_attributes']) );
                return $cv;
            }

        }

        return false;
    } // checkIfVariationExistsInCache()

    static function generateVariationKeyFromAttributes( $variation_attributes ) {
        // WPLE()->logger->info('generateVariationKeyFromAttributes() called: '.print_r($variation_attributes,1) );

    	// sort attributes alphabetically
    	ksort( $variation_attributes );
    	$key = '';

    	foreach ($variation_attributes as $attribute => $value) {
    		$key .= $attribute.'__'.$value.'|';
    	}

        WPLE()->logger->info('generateVariationKeyFromAttributes() returned: '.$key );
        return $key;
    } // generateVariationKeyFromAttributes()

    static function checkIfVariationInventoryHasChanged( $pv, $cv, $listing_item ) {
        WPLE()->logger->debug( 'Product Variation Stock: '. $pv['stock'] );
        WPLE()->logger->debug( 'Cached Variation Stock: '. $cv['stock'] );

        // compare stock level
        if ( $pv['stock'] != $cv['stock'] ) {
            WPLE()->logger->info('found changed stock level for variation: '.$cv['sku'] );
            return true;
        }

		// apply profile price - if set
		$profile_details = $listing_item['profile_data']['details'];
		$profile_price   = $profile_details['start_price'];
		$pv['price']     = empty( $profile_price )  ?  $pv['price']  :  self::applyProfilePrice( $pv['price'], $profile_price );

        // compare price
        if ( $pv['price'] != $cv['price'] ) {
            WPLE()->logger->info('found changed price for variation: '.$cv['sku'] );
            return true;
        }

        return false;
    } // checkIfVariationInventoryHasChanged()


	## BEGIN PRO ##

	static function filterPurchasedItemsForRevision( $cart_items ) {
		// WPLE()->logger->info( "filterPurchasedItemsForRevision() ".print_r($cart_items,1) );

		// loop trough product ids and build new array
		$items_to_revise  = array();
		$listing_id_cache = array();
		foreach ( $cart_items as $cart_item ) {
			WPLE()->logger->info( "filterPurchasedItemsForRevision() - processing cart item ".$cart_item->post_id );

			// get ALL listings for this post_id / variation_id
			if ( $cart_item->variation_id ) {
				$listings1 = WPLE_ListingQueryHelper::getAllListingsFromPostID( $cart_item->post_id );
				$listings2 = WPLE_ListingQueryHelper::getAllListingsFromPostID( $cart_item->variation_id ); // find split variations as well
				$listings  = array_merge( $listings1, $listings2 );
			} else {
				$listings = WPLE_ListingQueryHelper::getAllListingsFromPostID( $cart_item->post_id );
			}

			// // get ALL listings for this post_id / parent_id
			// $post_id   = $cart_item->post_id;
			// $listings1 = WPLE_ListingQueryHelper::getAllListingsFromPostID(   $post_id );
			// $listings2 = WPLE_ListingQueryHelper::getAllListingsFromParentID( $post_id );
			// $listings  = array_merge( $listings1, $listings2 );

			// get listing
			// $listing_id   = WPLE_ListingQueryHelper::getListingIDFromPostID( $cart_item->post_id );
			// $listing_item = self::getItem( $listing_id );

			foreach ( $listings as $listing_item ) {

				$listing_id   = $listing_item->id;
				// $profile_data = maybe_unserialize( $listing_item->profile_data );
				$profile_data = self::decodeObject( $listing_item->profile_data, true );

				// skip if listing is not published
				WPLE()->logger->info( "checking listing $listing_id status: ".$listing_item->status );
				if ( ! in_array( $listing_item->status, array('published','changed') ) ) {
					WPLE()->logger->info( "skipped listing $listing_id status: ".$listing_item->status );
					continue;
				}

				// skip if profile quantity override is effective
				if ( is_array($profile_data) && isset($profile_data['quantity']) && ( $profile_data['quantity'] != '' ) ) {
					WPLE()->logger->info( "skipped listing $listing_id - fixed quantity in profile: ".$profile_data['quantity'] );
					continue;
				}

				// skip if listing id has already been added
				if ( in_array( $listing_id, $listing_id_cache ) ) {
					WPLE()->logger->info( "skipped listing $listing_id - already exists in cache: ".print_r($listing_id_cache,1) );
					continue;
				}

				// add to items_to_revise
				$new_cart_item = new stdClass();
				$new_cart_item->post_id      = $cart_item->post_id;
				$new_cart_item->variation_id = $cart_item->variation_id;
				$new_cart_item->sku          = $cart_item->sku;
				$new_cart_item->listing_id   = $listing_id;
				$listing_id_cache[]          = $listing_id;
				// $items_to_revise[]        = $new_cart_item;

				// add to items_to_revise (grouped by account)
				$account_id = $listing_item->account_id;
				if ( ! isset( $items_to_revise[ $account_id ] ) ) $items_to_revise[ $account_id ] = array(); // init inner array
				$items_to_revise[ $account_id ][] = $new_cart_item;

				WPLE()->logger->info( "listing $listing_id is going to be revised... " );
			}

		}
		return $items_to_revise;
	} // filterPurchasedItemsForRevision()

	// retrieves the attachment ID from the file URL
	static function get_attachment_id_from_url($image_url) {
		global $wpdb;
		$attachment = $wpdb->get_col( $wpdb->prepare("SELECT ID FROM {$wpdb->prefix}posts WHERE guid = %s ", $image_url ) );
	    return $attachment[0];
	}

	// check picture requirements
	// http://pages.ebay.com/sellerinformation/news/springupdate2013/picturerequirements.html
	function checkPictureRequirements( $url ) {
		$success = true;

		// skip check if not enabled in settings
		if ( get_option( 'wplister_validate_eps_images' ) != '1' )
			return $url;

		// try to find attachment ID
		$attachment_id = self::get_attachment_id_from_url( $url );
		if ( ! $attachment_id ) return $url;

		// get image metadata
		$metadata = wp_get_attachment_metadata( $attachment_id );
		if ( ! $metadata ) return $url;


		// check if at least one side is 500px or more
		if ( ( $metadata['width'] < 5000 ) && ( $metadata['height'] < 5000 ) ) {

			// don't upload to EPS
			$longMessage  = 'Warning: This image is smaller than 500px on its longest side:<br>';
			$longMessage .= '<code>'.$url.'</code>';
			$success = false;
		}


		// check if filesize is too big
		$imgpath = WP_CONTENT_DIR . '/uploads/' . $metadata['file'];
		if ( file_exists( $imgpath ) ) {

			if ( filesize( $filepath ) > 2 * 1024 * 1024 ) {

				// don't upload to EPS
				$longMessage  = 'Warning: This image is bigger than 2mb:<br>';
				$longMessage .= '<code>'.$url.'</code>';
				$success = false;

			}
			WPLE()->logger->info('image filesize: '.filesize($imgpath));
		}


		// debug
		// echo "<pre>";print_r($metadata);echo"</pre>";
		WPLE()->logger->info('image metadata: '.print_r($metadata,1));

		// return url if no problem found
		if ( $success )	return $url;

		wple_show_message( $longMessage, 'error' );

		// build error message for user
		$htmlMsg  = '<div id="message" class="error" style="display:block !important;"><p>';
		$htmlMsg .= '<b>' . 'This image does not meet the requirements' . ':</b>';
		$htmlMsg .= '<br>' . $longMessage . '';
		$htmlMsg .= '<br>Image: ' . $url . '';
		$htmlMsg .= '<br>Height: ' . @$metadata['height'] . '';
		$htmlMsg .= '<br>Width: ' . @$metadata['width'] . '';
		$htmlMsg .= '<br>Size: ' . @filesize( $filepath ) . '';
		$htmlMsg .= '</p></div>';

		// save error as array of objects
		$errorObj = new stdClass();
		$errorObj->SeverityCode = 'PictureRequirements';
		$errorObj->ErrorCode 	= '44';
		$errorObj->ShortMessage = $longMessage;
		$errorObj->LongMessage 	= $longMessage;
		$errorObj->HtmlMessage 	= $htmlMsg;
		$errors = array( $errorObj );

		// save results as local property
		$this->result = new stdClass();
		$this->result->success = $success;
		$this->result->errors  = $errors;

		return $url;
	} // checkPictureRequirements()

	/**
	 * @param $url
	 * @param $listing_id
	 * @param $session
	 *
	 * @return false|string The EPS URL for the image
	 */
	function uploadPictureToEPS( $url, $listing_id, $session ) {

		// preparation - set up new ServiceProxy with given session
		$this->initServiceProxy($session);

		// preprocess url
		$url = $this->checkPictureRequirements( $url );
		if ( ! $url ) return null;

		// rawurlencode filename only
        // run through rawurldecode first to prevent double encoding #60080
		$url = str_replace( basename($url), rawurlencode( rawurldecode( basename($url) ) ), $url );

		// fix remaining spaces in path
		$url = str_replace(' ', '%20', $url );

		// check EPS cache before upload
		$listing = self::getItem( $listing_id );

		WPLE()->initEC( $listing['account_id'] );

		if ( ! $uploaded_images = maybe_unserialize( $listing['eps'] ) ) $uploaded_images = array();

		// debug
		// WPLE()->logger->info( "loaded EPS cache for listing $listing_id: ".print_r($uploaded_images,1) );
		// WPLE()->logger->info( "raw EPS cache for listing $listing_id: ".print_r($listing['eps'],1) );

		foreach ($uploaded_images as $img) {
			if ( $img->local_url == $url ) {
				WPLE()->logger->info( "found cached EPS image for $url" );
				WPLE()->logger->info( "using cached EPS image: ".$img->remote_url );
				return $img->remote_url;
			}
		}

		$res = WPLE()->EC->uploadToEPS( $url, $session );

		if ( is_wp_error( $res ) ) {
			// let the user know which image failed to upload if there was an error
			wple_show_message( $res->get_error_message(), 'error' );
			return false;
		}

		// create cache object
		$img = new stdClass();
		$img->local_url     = $url;
		$img->remote_url    = $res->SiteHostedPictureDetails->FullURL;
		$img->use_by_date   = $res->SiteHostedPictureDetails->UseByDate;
		$img->uploaded_date = time();
		$img->hash          = md5( wp_remote_fopen($url) );
		$uploaded_images[]  = $img;

		// update EPS cache
		$listing = new Listing( $listing_id );
		$listing->setEps( $uploaded_images );
		$listing->save();

		return $res->SiteHostedPictureDetails->FullURL;
	} // uploadPictureToEPS()






	// // sendXmlMessage without using cURL - does not work, response is always empty, no matter what!
	// // (this method is only used to upload images to EPS)
	// function sendXmlMessageWithoutCurl( $message, $extraXmlHeaders, $multiPartImageData = null ) {

	// 	$this->_currentResult = null;
	// 	$this->_cs->log( $this->_ep, 'RequestUrl' );
	// 	$this->_cs->logXml( $message, 'Request' );

	// 	if ($multiPartImageData !== null) {

	// 		$boundary = "MIME_boundary";

	// 		$CRLF = "\r\n";

	// 		$mp_message  = '';
	// 		$mp_message .= "--" . $boundary . $CRLF;
	// 		$mp_message .= 'Content-Disposition: form-data; name="XML Payload"' . $CRLF;
	// 		$mp_message .= 'Content-Type: text/xml;charset=utf-8' . $CRLF . $CRLF;
	// 		$mp_message .= $message;
	// 		$mp_message .= $CRLF;

	// 		$mp_message .= "--" . $boundary . $CRLF;
	// 		$mp_message .= 'Content-Disposition: form-data; name="dumy"; filename="dummy"' . $CRLF;
	// 		$mp_message .= "Content-Transfer-Encoding: binary" . $CRLF;
	// 		$mp_message .= "Content-Type: application/octet-stream" . $CRLF . $CRLF;
	// 		$mp_message .= $multiPartImageData;

	// 		$mp_message .= $CRLF;
	// 		$mp_message .= "--" . $boundary . "--" . $CRLF;

	// 		$message = $mp_message;

	// 		$reqHeaders[] = 'Content-Type: multipart/form-data; boundary=' . $boundary;
	// 		$reqHeaders[] = 'Content-Length: ' . strlen($message);

	// 	} else {
	// 		$reqHeaders[] = 'Content-Type: text/xml;charset=utf-8';
	// 	}


	// 	if (is_array($extraXmlHeaders))
	// 		$reqHeaders = array_merge((array)$reqHeaders, $extraXmlHeaders);

	// 	WPLE()->logger->info( "sendXmlMessageWithoutCurl() URL: ".$this->_ep );
	// 	WPLE()->logger->info( "sendXmlMessageWithoutCurl() reqHeaders: ".print_r($reqHeaders,1) );
	// 	WPLE()->logger->info( "sendXmlMessageWithoutCurl() message: ".$message );


	// 	$wp_post_args = array(
	// 		'body'        => $message,
	// 		'headers'     => $reqHeaders,
	// 		'timeout'     => '300',
	// 		// 'redirection' => '5',
	// 		// 'httpversion' => '1.0',
	// 	    // 'user-agent'  => 'WordPress/' . $wp_version . '; ' . home_url(),
	// 	    'user-agent'  => 'ebatns;xmlstyle;1.0',
	// 	);

	// 	$response = wp_remote_post( $this->_ep, $wp_post_args );

	// 	if ( ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) == 200 ) {

	// 		$responseBody = wp_remote_retrieve_body( $response );
	// 		WPLE()->logger->info( "loaded ".strlen($responseBody)." bytes from URL: ".$url );
	// 		$this->_cs->logXml( $responseBody, 'Response' );

	// 	} elseif ( is_wp_error( $response ) ) {

	// 		$details  = 'wp_remote_post() failed to connect to ' . $url . '<br>';
	// 		$details .= 'Error:' . ' ' . $response->get_error_message() . '<br>';
	// 		wple_show_message( 'There was a problem uploading your image to eBay.: '.$details, 'error' );
	// 		WPLE()->logger->error( 'Connection to '.$url.' failed: '.$details );
	// 		$this->_cs->log( $details, 'eps_upload_error' );
	// 		return null;

	// 	} else {
	// 		$details  = 'wp_remote_post() returned an unexpected HTTP status code: ' . wp_remote_retrieve_response_code( $response );
	// 		wple_show_message( 'There was a problem uploading your image to eBay.: '.$details, 'error' );
	// 		WPLE()->logger->error( 'Connection to '.$url.' failed: '.$details );
	// 		WPLE()->logger->error( "reponse object: ".print_r($response,1) );
	// 		$this->_cs->log( $details, 'eps_upload_error' );
	// 		$this->_currentResult = new EbatNs_ResponseError();
	// 		$this->_currentResult->raise( 'curl_error ' . curl_errno( $ch ) . ' ' . curl_error( $ch ), 80000 + 1, EBAT_SEVERITY_ERROR );
	// 		return null;
	// 	}

	// 	return $responseBody;
	// } // sendXmlMessageWithoutCurl()


	// // sendMessage in XmlStyle (DEPRECATED and UNUSED - TODO: remove entirely)
	// // the only difference is the extra headers we use here
	// function sendMessageXmlStyle( $message, $extraXmlHeaders, $multiPartImageData = null )
	// {
	// 	$this->_currentResult = null;
	// 	$this->_cs->log( $this->_ep, 'RequestUrl' );
	// 	$this->_cs->logXml( $message, 'Request' );

	// 	$timeout = 300;

	// 	$ch = curl_init();

	// 	if ($multiPartImageData !== null)
	// 	{
	// 		$boundary = "MIME_boundary";

	// 		$CRLF = "\r\n";

	// 		$mp_message  = '';
	// 		$mp_message .= "--" . $boundary . $CRLF;
	// 		$mp_message .= 'Content-Disposition: form-data; name="XML Payload"' . $CRLF;
	// 		$mp_message .= 'Content-Type: text/xml;charset=utf-8' . $CRLF . $CRLF;
	// 		$mp_message .= $message;
	// 		$mp_message .= $CRLF;

	// 		$mp_message .= "--" . $boundary . $CRLF;
	// 		$mp_message .= 'Content-Disposition: form-data; name="dumy"; filename="dummy"' . $CRLF;
	// 		$mp_message .= "Content-Transfer-Encoding: binary" . $CRLF;
	// 		$mp_message .= "Content-Type: application/octet-stream" . $CRLF . $CRLF;
	// 		$mp_message .= $multiPartImageData;

	// 		$mp_message .= $CRLF;
	// 		$mp_message .= "--" . $boundary . "--" . $CRLF;

	// 		$message = $mp_message;

	// 		$reqHeaders[] = 'Content-Type: multipart/form-data; boundary=' . $boundary;
	// 		$reqHeaders[] = 'Content-Length: ' . strlen($message);
	// 	}
	// 	else
	// 	{
	// 		$reqHeaders[] = 'Content-Type: text/xml;charset=utf-8';
	// 	}


	// 	if ($this->_cs->_transportOptions['HTTP_COMPRESS'])
	// 	{
	// 		$reqHeaders[] = 'Accept-Encoding: gzip, deflate';
	// 		curl_setopt( $ch, CURLOPT_ENCODING, "gzip");
	// 		curl_setopt( $ch, CURLOPT_ENCODING, "deflate");
	// 	}

	// 	if (is_array($extraXmlHeaders))
	// 		$reqHeaders = array_merge((array)$reqHeaders, $extraXmlHeaders);

	// 	WPLE()->logger->info( "sendMessageXmlStyle() URL: ".$this->_ep );
	// 	WPLE()->logger->info( "sendMessageXmlStyle() reqHeaders: ".print_r($reqHeaders,1) );

	// 	curl_setopt( $ch, CURLOPT_URL, $this->_ep );

	// 	curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, 0);
	// 	curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, 0);

	// 	curl_setopt( $ch, CURLOPT_HTTPHEADER, $reqHeaders );
	// 	curl_setopt( $ch, CURLOPT_USERAGENT, 'ebatns;xmlstyle;1.0' );
	// 	curl_setopt( $ch, CURLOPT_TIMEOUT, $timeout );

	// 	curl_setopt( $ch, CURLOPT_POST, 1 );
	// 	curl_setopt( $ch, CURLOPT_POSTFIELDS, $message );

	// 	curl_setopt( $ch, CURLOPT_FAILONERROR, 0 );
	// 	curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, 1 );
	// 	curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
	// 	curl_setopt( $ch, CURLOPT_HEADER, 1 );
	// 	curl_setopt( $ch, CURLOPT_HTTP_VERSION, 1 );

	// 	// regard WP proxy server
	// 	if ( defined('WP_USEPROXY') && WP_USEPROXY ) {
	// 		if ( defined('WP_PROXY_HOST') && defined('WP_PROXY_PORT') )
	// 			curl_setopt( $ch, CURLOPT_PROXY, WP_PROXY_HOST . ':' . WP_PROXY_PORT );
	// 	}

	// 	// added support for multi-threaded clients
	// 	if (isset($this->_cs->_transportOptions['HTTP_CURL_MULTITHREADED']))
	// 	{
	// 		curl_setopt( $ch, CURLOPT_DNS_USE_GLOBAL_CACHE, 0 );
	// 	}

	// 	$responseRaw = curl_exec( $ch );
	// 	if ( !$responseRaw )
	// 	{
	// 		$this->_currentResult = new EbatNs_ResponseError();
	// 		$this->_currentResult->raise( 'curl_error ' . curl_errno( $ch ) . ' ' . curl_error( $ch ), 80000 + 1, EBAT_SEVERITY_ERROR );
	// 		$this->_cs->log( curl_error( $ch ), 'curl_error' );
	// 		wple_show_message( 'There was a problem uploading your image to eBay. cURL error: '.curl_error( $ch ), 'error' );
	// 		curl_close( $ch );

	// 		return null;
	// 	}
	// 	else
	// 	{
	// 		curl_close( $ch );

	// 		$responseRaw = str_replace
	// 		(
	// 			array
	// 			(
	// 				"HTTP/1.1 100 Continue\r\n\r\nHTTP/1.1 200 OK\r\n",
	// 				"HTTP/1.1 100 Continue\n\nHTTP/1.1 200 OK\n"
	// 			),
	// 			array
	// 			(
	// 				"HTTP/1.1 200 OK\r\n",
	// 				"HTTP/1.1 200 OK\n"
	// 			),
	// 			$responseRaw
	// 		);

	// 		$responseBody = null;
	// 		if ( preg_match( "/^(.*?)\r?\n\r?\n(.*)/s", $responseRaw, $match ) )
	// 		{
	// 			$responseBody = $match[2];
	// 			$headerLines = preg_split( "/\r?\n/", $match[1] );
	// 			foreach ( $headerLines as $line )
	// 			{
	// 				if ( strpos( $line, ':' ) === false )
	// 				{
	// 					$responseHeaders[0] = $line;
	// 					continue;
	// 				}
	// 				list( $key, $value ) = explode( ':', $line );
	// 				$responseHeaders[strtolower( $key )] = trim( $value );
	// 			}
	// 		}

	// 		if ($responseBody)
	// 			$this->_cs->logXml( $responseBody, 'Response' );
	// 		else
	// 			$this->_cs->logXml( $responseRaw, 'ResponseRaw' );
	// 	}

	// 	return $responseBody;
	// } // sendMessageXmlStyle()


	// DEPRECATION WARNING: 
	// Please note that the Selling Manager Pro API will no longer be accessible to use starting June 1, 2021.
	function SetSellingManagerItemAutomationRule( $ItemID, $profile_details, $session )
	{

		// make sure the required values are defined
		if ( ! $profile_details['AutomatedRelistingRule_Type'] ) return;
		if ( ! $profile_details['AutomatedRelistingRule_RelistCondition'] ) return;

		// build AutomatedRelistingRule
		$AutomatedRelistingRule = new SellingManagerAutoRelistType();
		$AutomatedRelistingRule->setType( $profile_details['AutomatedRelistingRule_Type'] );
		$AutomatedRelistingRule->setRelistCondition( $profile_details['AutomatedRelistingRule_RelistCondition'] );

		if ( intval( $profile_details['RelistAfterDays'] ) > 0 ) {
			$AutomatedRelistingRule->setRelistAfterDays( $profile_details['RelistAfterDays'] );
		}
		if ( intval( $profile_details['RelistAfterHours'] ) > 0 ) {
			$AutomatedRelistingRule->setRelistAfterHours( $profile_details['RelistAfterHours'] );
		}
		if ( intval( $profile_details['RelistAtSpecificTimeOfDay'] ) > 0 ) {
			$AutomatedRelistingRule->setRelistAtSpecificTimeOfDay( $profile_details['RelistAtSpecificTimeOfDay'] );
		}
		if ( intval( $profile_details['ListingHoldInventoryLevel'] ) > 0 ) {
			$AutomatedRelistingRule->setListingHoldInventoryLevel( $profile_details['ListingHoldInventoryLevel'] );
		}


		// preparation - set up new ServiceProxy with given session
		$this->initServiceProxy($session);

		$req = new SetSellingManagerItemAutomationRuleRequestType();
		$req->setItemID($ItemID);
		$req->setAutomatedRelistingRule($AutomatedRelistingRule);

		// WARNING: Selling Manager Pro API will no longer be accessible to use starting June 1, 2021
		WPLE()->logger->debug( "Request: ".print_r($req,1) );
		$res = $this->_cs->SetSellingManagerItemAutomationRule($req);


		// handle response and check if successful
		if ( $this->handleResponse($res) ) {

			WPLE()->logger->info( "post processing finished on Item #$id - ItemID: ".$res->ItemID );
			WPLE()->logger->info( "Response: ".print_r($res,1) );

		} // call successful

		return $this->result;

	} // SetSellingManagerItemAutomationRule()


	## END PRO ##

	static function listingUsesFixedPriceItem( $listing_item )
	{
		// regard auction_type by default
		$useFixedPriceItem = 'FixedPriceItem' == $listing_item['auction_type'];

		// but switch to AddItem if BestOffer is enabled
		$profile_details = $listing_item['profile_data']['details'];
        if ( @$profile_details['bestoffer_enabled'] == '1' ) $useFixedPriceItem = false;

		// or switch to AddItem if product level listing type is Chinese
		$product_listing_type = get_post_meta( $listing_item['post_id'], '_ebay_auction_type', true );
        if ( $product_listing_type == 'Chinese' ) $useFixedPriceItem = false;

        // or switch to AddItem when relisting an ended auction as fixed price
        $ItemDetails = self::decodeObject( $listing_item['details'] );
        if ( $ItemDetails && is_object( $ItemDetails ) ) {
        	if (isset( $ItemDetails->ListingType ) && $ItemDetails->ListingType == 'Chinese' )
 				$useFixedPriceItem = false;
        }

        // never use FixedPriceItem if variations are disabled
        if ( get_option( 'wplister_disable_variations' ) == '1' ) $useFixedPriceItem = false;

		return $useFixedPriceItem;
	}

	// handle additional requests after AddItem(), ReviseItem(), etc.
	function postProcessListing( $id, $ItemID, $item, $listing_item, $res, $session ) {
		## BEGIN PRO ##
		$profile_details = $listing_item['profile_data']['details'];

		WPLE()->logger->info( 'postProcessListing() - ItemID: '.$ItemID );
		WPLE()->logger->debug('profile_details: '.print_r($profile_details,1));

		// handle SetSellingManagerItemAutomationRule
		if ( ( isset($profile_details['sellingmanager_enabled']) ) && ( $profile_details['sellingmanager_enabled'] == '1' ) ) {
			$this->SetSellingManagerItemAutomationRule( $ItemID, $profile_details, $session );
		}

		## END PRO ##
	}

	function addItem( $id, $session )
	{
		// skip this item if item status not allowed
		$allowed_statuses = array( 'prepared', 'verified' );
		if ( ! $this->itemHasAllowedStatus( $id, $allowed_statuses ) ) return $this->result;

        // build item
		$ibm = new ItemBuilderModel();
		$item = $ibm->buildItem( $id, $session );
		if ( ! $ibm->checkItem($item) ) return $ibm->result;

        // Allow 3rd-party plugins to add their own checks
        $listing = ListingsModel::getItem( $id );
        $custom_check = apply_filters_deprecated( 'wplister_check_item', array(true, $listing), '2.8.4', 'wple_check_item' );
        $custom_check = apply_filters( 'wple_check_item', $custom_check, $listing, $item );

        if ( $custom_check !== true ) {
            // Assume now that $custom_check contains the error message
            // create error object
            $errorObj = new stdClass();
            $errorObj->SeverityCode = 'Info';
            $errorObj->ErrorCode 	= 103;
            $errorObj->ShortMessage = 'Listing failed requirements';
            $errorObj->LongMessage 	= $custom_check;
            $errorObj->HtmlMessage 	= $custom_check;
            // $errors[] = $errorObj;

            // save results as local property
            $this->result = new stdClass();
            $this->result->success = false;
            $this->result->errors  = array( $errorObj );
            wple_show_message( $custom_check, 'error' );
            return $this->result;
        }

		// eBay Motors (beta)
		if ( $item->Site == 'eBayMotors' ) $session->setSiteId( 100 );

		// preparation - set up new ServiceProxy with given session
		$this->initServiceProxy($session);

		// switch to FixedPriceItem if product has variations
		$listing_item = self::getItem( $id );
		// $useFixedPriceItem = ( ProductWrapper::hasVariations( $listing_item['post_id'] ) ) ? true : false;
		// $useFixedPriceItem = ( 'FixedPriceItem' == $listing_item['auction_type'] ) ? true : false;

		WPLE()->logger->info( "Adding #$id: ".$item->Title );
		if ( self::listingUsesFixedPriceItem( $listing_item ) ) {

			$req = new AddFixedPriceItemRequestType();
			$req->setItem($item);

			WPLE()->logger->debug( "Request: ".print_r($req,1) );
			$res = $this->_cs->AddFixedPriceItem($req);

		} else {

			$req = new AddItemRequestType();
			$req->setItem($item);

			WPLE()->logger->debug( "Request: ".print_r($req,1) );
			$res = $this->_cs->AddItem($req);

		}

		// handle response and check if successful
		if ( $this->handleResponse($res) ) {

			// save ebay ID and fees to db
			$listingFee = self::getListingFeeFromResponse( $res );
			$data['ebay_id'] = $res->ItemID;
			$data['fees'] = $listingFee;
			$data['status'] = 'published';
			self::updateListing( $id, $data );

			// get details like ViewItemURL from ebay automatically
			$this->updateItemDetails( $id, $session );
			$this->postProcessListing( $id, $res->ItemID, $item, $listing_item, $res, $session );

			WPLE()->logger->info( "Item #$id sent to ebay, ItemID is ".$res->ItemID );

		} // call successful
		self::processErrorsAndWarnings( $id, $this->result );

		do_action( 'wplister_listing_published', $id, $listing_item );

		return $this->result;

	} // addItem()

	function relistItem( $id, $session )
	{
		// skip this item if item status not allowed
		$allowed_statuses = array( 'ended', 'sold' );
		if ( ! $this->itemHasAllowedStatus( $id, $allowed_statuses ) ) return $this->result;

		// reapply profile before relisting an ended item
        $this->reapplyProfileToItem( $id );

		// build item
		$ibm = new ItemBuilderModel();
		$item = $ibm->buildItem( $id, $session );
		if ( ! $ibm->checkItem($item) ) return $ibm->result;

		// eBay Motors (beta)
		if ( $item->Site == 'eBayMotors' ) $session->setSiteId( 100 );

		// preparation - set up new ServiceProxy with given session
		$this->initServiceProxy($session);

		// switch to FixedPriceItem if product has variations
		$listing_item = self::getItem( $id );
		// $useFixedPriceItem = ( ProductWrapper::hasVariations( $listing_item['post_id'] ) ) ? true : false;
		// $useFixedPriceItem = ( 'FixedPriceItem' == $listing_item['auction_type'] ) ? true : false;

		// add old ItemID for relisting
		$item->setItemID( $listing_item['ebay_id'] );

		WPLE()->logger->info( "Relisting #$id (ItemID ".$listing_item['ebay_id'].") - ".$item->Title );
		if ( self::listingUsesFixedPriceItem( $listing_item ) ) {

			$req = new RelistFixedPriceItemRequestType();
			$req->setItem($item);

			WPLE()->logger->debug( "Request: ".print_r($req,1) );
			$res = $this->_cs->RelistFixedPriceItem($req);

		} else {

			$req = new RelistItemRequestType();
			$req->setItem($item);

			WPLE()->logger->debug( "Request: ".print_r($req,1) );
			$res = $this->_cs->RelistItem($req);

		}

		// handle response and check if successful
		if ( $this->handleResponse($res) ) {

			// save ebay ID and fees to db
			$listingFee = self::getListingFeeFromResponse( $res );
			$data['ebay_id'] = $res->ItemID;
			$data['fees'] = $listingFee;
			$data['status'] = 'published';

			// update listing status
			if (  17 == $this->handle_error_code ) $data['status'] = 'archived';
			self::updateListing( $id, $data );

			// get details like ViewItemURL from ebay automatically - unless item does not exist on eBay (17)
			if (  17 != $this->handle_error_code ) {
				$this->updateItemDetails( $id, $session, true );
				$this->postProcessListing( $id, $res->ItemID, $item, $listing_item, $res, $session );
			}

			WPLE()->logger->info( "Item #$id relisted on ebay, NEW ItemID is ".$res->ItemID );
			self::addItemIdToHistory( $res->ItemID, $listing_item['ebay_id'] );
			do_action( 'wplister_item_relisted', $listing_item, $res->ItemID );

		} // call successful
		self::processErrorsAndWarnings( $id, $this->result );

		return $this->result;

	} // relistItem()

	function autoRelistItem( $id, $session )
	{
		// skip this item if item status not allowed
		$allowed_statuses = array( 'ended', 'sold' );
		if ( ! $this->itemHasAllowedStatus( $id, $allowed_statuses ) ) return $this->result;

		// build item
		$ibm  = new ItemBuilderModel();
        $item = $ibm->buildItem( $id, $session );
        if ( ! $ibm->checkItem($item) ) return $ibm->result;

		//$ibm->setEbaySite();

		// add old ItemID for relisting
		$listing_item = self::getItem( $id );
		$item->setItemID( $listing_item['ebay_id'] );

		// use Item.Site from listing details - this way we don't need to check primary category for eBayMotors
		// $listing_details = maybe_unserialize( $listing_item['details'] );
        $listing_details = self::decodeObject( $listing_item['details'] );
		$item->Site = $listing_details->Site;

		// eBay Motors (beta)
		if ( $item->Site == 'eBayMotors' ) $session->setSiteId( 100 );

		// preparation - set up new ServiceProxy with given session
		$this->initServiceProxy($session);

		// switch to FixedPriceItem if product has variations
		WPLE()->logger->info( "Auto-Relisting #$id (ItemID ".$listing_item['ebay_id'].") - ".$item->Title );
		if ( self::listingUsesFixedPriceItem( $listing_item ) ) {

			$req = new RelistFixedPriceItemRequestType();
			$req->setItem($item);

			WPLE()->logger->debug( "Request: ".print_r($req,1) );
			$res = $this->_cs->RelistFixedPriceItem($req);

		} else {

			$req = new RelistItemRequestType();
			$req->setItem($item);

			WPLE()->logger->debug( "Request: ".print_r($req,1) );
			$res = $this->_cs->RelistItem($req);

		}

		// handle response and check if successful
		if ( $this->handleResponse($res) ) {

			// save new ebay ID and details to db
			$listingFee = self::getListingFeeFromResponse( $res );
			$data['ebay_id']     = $res->ItemID;
			$data['fees']        = $listingFee;
			$data['status']      = 'published';
			$data['relist_date'] = NULL;

			// update listing status
			if (  17 == $this->handle_error_code ) $data['status'] = 'archived';
			self::updateListing( $id, $data );

			// get details like ViewItemURL from ebay automatically - unless item does not exist on eBay (17)
			if (  17 != $this->handle_error_code ) {
				$this->updateItemDetails( $id, $session, true );
				$this->postProcessListing( $id, $res->ItemID, $item, $listing_item, $res, $session );
			}

			WPLE()->logger->info( "Item #$id auto-relisted on ebay, NEW ItemID is ".$res->ItemID );
			self::addItemIdToHistory( $res->ItemID, $listing_item['ebay_id'] );

			do_action( 'wplister_item_relisted', $listing_item, $res->ItemID );

		} // call successful
		self::processErrorsAndWarnings( $id, $this->result );

		return $this->result;

	} // autoRelistItem()

    /**
     * Revise a changed listing on eBay. Locked listings will be switched to self::reviseInventoryStatus()
     *
     * @param $id
     * @param $session
     * @param bool $force_full_update
     * @param bool $restricted_mode
     * @return bool|stdClass
     */
	function reviseItem( $id, $session, $force_full_update = false, $restricted_mode = false )
	{
		// skip this item if item status not allowed
		$allowed_statuses = array( 'published', 'changed' );
		if ( ! $this->itemHasAllowedStatus( $id, $allowed_statuses ) ) return $this->result;

		// check if product has variations
		$listing_item = self::getItem( $id );
		// $useFixedPriceItem = ( ProductWrapper::hasVariations( $listing_item['post_id'] ) ) ? true : false;
		// $useFixedPriceItem = ( 'FixedPriceItem' == $listing_item['auction_type'] ) ? true : false;

        // Allow 3rd-party plugins to add their own checks
        $listing = ListingsModel::getItem( $id );
        $custom_check = apply_filters_deprecated( 'wplister_check_item', array(true, $listing), '2.8.4', 'wple_check_item' );
        $custom_check = apply_filters( 'wple_check_item', $custom_check, $listing );

        if ( $custom_check !== true ) {
            // Assume now that $custom_check contains the error message
            // create error object
            $errorObj = new stdClass();
            $errorObj->SeverityCode = 'Info';
            $errorObj->ErrorCode 	= 103;
            $errorObj->ShortMessage = 'Listing failed requirements';
            $errorObj->LongMessage 	= $custom_check;
            $errorObj->HtmlMessage 	= $custom_check;
            // $errors[] = $errorObj;

            // save results as local property
            $this->result = new stdClass();
            $this->result->success = false;
            $this->result->errors  = array( $errorObj );
            //wple_show_message( $custom_check, 'error' );
            return $this->result;
        }

		// handle locked items
		if ( $listing_item['locked'] && ! $force_full_update ) {
			return $this->reviseInventoryStatus( $id, $session, false );
		}

		// build item
		$ibm = new ItemBuilderModel();
		$item = $ibm->buildItem( $id, $session, true );
		if ( ! $ibm->checkItem( $item, true ) ) return $ibm->result;

		// check for variations to be deleted
		$item = $ibm->fixDeletedVariations( $item, $listing_item );

		// handle restricted revise mode
		if ( $restricted_mode ) {
			$item = $ibm->applyRestrictedReviseMode( $item );
		}

		// if quantity is zero, end item instead
		if ( ( $item->Quantity == 0 ) && ( ! $ibm->VariationsHaveStock ) && ( ! self::thisListingUsesOutOfStockControl( $listing_item ) ) ) {
			WPLE()->logger->info( "Item #$id has no stock, switching from reviseItem() to endItem()" );
			return $this->endItem( $id, $session );
		}

		// checkItem should run after check for zero quantity - not it shouldn't as VariationsHaveStock will be undefined
		// TODO: separate quantity checks from checkItem() and run checkQuantity() first, maybe end item, if not then run other sanity checks
		// (This helps users who use the import plugin and WP-Lister Pro but forgot to set a primary category in their profile)
		// if ( ! $ibm->checkItem($item) ) return $ibm->result;

		// eBay Motors (beta)
		if ( $item->Site == 'eBayMotors' ) $session->setSiteId( 100 );

		// preparation - set up new ServiceProxy with given session
		$this->initServiceProxy($session);

		// set ItemID to revise
		$item->setItemID( self::getEbayIDFromID($id) );
		WPLE()->logger->info( "Revising #$id: ".$listing_item['auction_title'] );

		// switch to FixedPriceItem if product has variations
		if ( self::listingUsesFixedPriceItem( $listing_item ) ) {

			$req = new ReviseFixedPriceItemRequestType();
			$req->setItem($item);
			$req = $ibm->setDeletedFields( $req, $listing_item );

			WPLE()->logger->debug( "Request: ".print_r($req,1) );
			$res = $this->_cs->ReviseFixedPriceItem($req);

		} else {

			$req = new ReviseItemRequestType();
			$req->setItem($item);
			$req = $ibm->setDeletedFields( $req, $listing_item );

			WPLE()->logger->debug( "Request: ".print_r($req,1) );
			$res = $this->_cs->ReviseItem($req);

		}

		// handle response and check if successful
		if ( $this->handleResponse($res) ) {

			// handle Error 21916734: Variation pictures cannot be removed during restricted revise.
			if ( 21916734 == $this->handle_error_code ) {
				if ( ! $restricted_mode ) { // make sure we try again only once
					WPLE()->logger->info( "Error 21916734 - switching to restricted revise mode for item $id" );
					return $this->reviseItem( $id, $session, $force_full_update, $restricted_mode = true );
				}
			}

			// update listing status
			$data['status'] = 'published';
			if ( 1047 == $this->handle_error_code ) $data['status'] = 'ended';
			if (  291 == $this->handle_error_code ) $data['status'] = 'ended';
			if (  21916750 == $this->handle_error_code ) $data['status'] = 'ended';
			if (   17 == $this->handle_error_code ) $data['status'] = 'archived';
			self::updateListing( $id, $data );

			// get details like ViewItemURL from ebay automatically - unless item does not exist on eBay (17)
			if (  17 != $this->handle_error_code ) {
				$this->updateItemDetails( $id, $session, true );
				$this->postProcessListing( $id, $res->ItemID, $item, $listing_item, $res, $session );
			}

			WPLE()->logger->info( "Item #$id was revised, ItemID is ".$res->ItemID );

		} // call successful
		self::processErrorsAndWarnings( $id, $this->result );

        do_action( 'wplister_listing_revised', $id, $listing_item );

		return $this->result;

	} // reviseItem()

	function reviseInventoryStatus( $id, $session, $cart_item = false, $force = false )
	{
	    WPLE()->logger->info( 'reviseInventoryStatus #'. $id );

	    if ( !$force ) {
            // skip this item if item status not allowed
            $allowed_statuses = array( 'published', 'changed' );
            if ( ! $this->itemHasAllowedStatus( $id, $allowed_statuses ) ) return $this->result;
        }

		// check listing type and if product has variations
		$listing_item = self::getItem( $id );
		$profile_details = $listing_item['profile_data']['details'] ?? [];
		$post_id = $listing_item['post_id'];

		// check listing type - ignoring best offer etc...
		$useFixedPriceItem = ( 'FixedPriceItem' == $listing_item['auction_type'] ) ? true : false;
		$product_listing_type = get_post_meta( $post_id, '_ebay_auction_type', true );
        if ( $product_listing_type == 'Chinese' ) $useFixedPriceItem = false;

		// ReviseInventoryStatus only works on FixedPriceItems so use ReviseItem otherwise
		if ( ! $useFixedPriceItem ) {
			WPLE()->logger->info( "Item #$id is not of type FixedPriceItem ({$listing_item['auction_type']}), switching to reviseItem()" );
			wple_show_message( 'Revising the inventory status (price and quantity) is only possible for fixed price items. This is item is not a fixed price item, so the entire listing will be revised on eBay.', 'warn' );
			return $this->reviseItem( $id, $session, true );
		}

		// check for single variation in cart
		$isVariationInCart = ( $cart_item && is_object($cart_item) && $cart_item->variation_id ) ? true : false;

		// check for variable product (update all variations)
		$isVariableProduct = ProductWrapper::hasVariations( $post_id );

		// fall back to reviseItem if cart variation without SKU
		if ( $isVariationInCart && ! $cart_item->sku ) {
			WPLE()->logger->info( "Item #$id has variations without SKU, switching to reviseItem()" );
			return $this->reviseItem( $id, $session, true );
		}

		// if stock level is zero, end item instead
		if ( ( ! self::thisListingUsesOutOfStockControl( $listing_item ) ) && ( ! self::checkStockLevel( $listing_item ) ) ) {
			WPLE()->logger->info( "Item #$id has no stock, switching from reviseInventoryStatus() to endItem()" );
			return $this->endItem( $id, $session );
		}

        // get max_quantity and fixed qty from profile
        $max_quantity = ( isset( $profile_details['max_quantity'] ) && intval( $profile_details['max_quantity'] )  > 0 ) ? $profile_details['max_quantity'] : PHP_INT_MAX ;
        $fix_quantity = ( isset( $profile_details['quantity']     ) && intval( $profile_details['quantity']     )  > 0 ) ? $profile_details['quantity']     : false ;
		$wc_product   = wc_get_product( $post_id );

		// set inventory status
		if ( $isVariableProduct ) {

            // if this is a flattened variation, fall back to reviseItem
	        $variations_mode = isset( $profile_details['variations_mode'] ) ? $profile_details['variations_mode'] : null;
            if ( $variations_mode == 'flat' ) {
				WPLE()->logger->info( "Item #$id is a flattened variation, switching to reviseItem()" );
				wple_show_message( 'Revising only price and quantity is not possible for flattened variations, so the entire listing will be revised on eBay.', 'warn' );
				return $this->reviseItem( $id, $session, true );
            }

			// get all variations
			$variations = ProductWrapper::getVariations( $post_id );
			// echo "<pre>";print_r($variations);echo"</pre>";die();

            // check variations cache
            $result = self::matchCachedVariations( $listing_item, apply_filters( 'wple_filter_unchanged_variations', true, $post_id, $listing_item ), $force );
            if ( $result && $result->success )
                $variations = $result->variations;

            // if there are new variations, fall back to reviseItem
            if ( $result && ! $result->success ) {
				WPLE()->logger->info( "Item #$id has NEW variations, switching to reviseItem()" );
				wple_show_message( 'New variations have been added to this product. Revising only price and quantity is not possible when adding new variations, so the entire listing will be revised on eBay.', 'warn' );
				return $this->reviseItem( $id, $session, true );
            }

            // do nothing if no changed variations found
            if ( sizeof( $variations ) == 0 ) {
				WPLE()->logger->info( "Item #$id has NO CHANGED variations - skipping revise request..." );
				wple_show_message( 'No variations have been modified, the revise request will be skipped.', 'info' );
				$this->result->success = true;
				$this->result->errors = false;

				// Reset status back to published if no variations have been modified #25818
                // And remove previous errors so it does not confuse users #55852
				if ( $listing_item['status'] == 'changed' ) {
                    self::updateListing( $id, array( 'status' => 'published', 'last_errors' => '' ) );
                }

            	return $this->result;
            }

            // check if all variations have unique SKUs
			if ( ! self::checkVariationSKUs( $variations ) ) {
				WPLE()->logger->info( "Item #$id does not have unique SKUs, switching to reviseItem()" );
				wple_show_message( 'Warning: Some variations have no SKU or are using the same SKU. Revising only the inventory status (price and quantity) requires unique SKUs for each variation, so the entire listing will be revised on eBay now.', 'warn' );
				return $this->reviseItem( $id, $session, true );
			}

			// calc number of requests
			$batch_size = 4;
			// $requests_required = intval( sizeof($variations) / $batch_size ) + 1;

			// revise inventory of up to 4 variations at a time
			for ( $offset=0; $offset < sizeof($variations); $offset += $batch_size ) {

				// revise inventory status
				$res = $this->reviseVariableInventoryStatus( $id, $post_id, $listing_item, $session, $variations, $max_quantity, $fix_quantity, $offset, $batch_size );

			}

		} else {

			// preparation - set up new ServiceProxy with given session
			$this->initServiceProxy($session);

			// build request
			$req = new ReviseInventoryStatusRequestType();

			// set ItemID
			$stat = new InventoryStatusType();
			$stat->setItemID( self::getEbayIDFromID($id) );

			if ( $isVariationInCart && $cart_item->sku ) {
                // Adding support for ATUM Multi Inventory #37394
                add_filter( 'atum/multi_inventory/bypass_mi_get_stock_quantity', '__return_false');

                // get stock level for this variation in cart
				$variation_qty = get_post_meta( $cart_item->variation_id, '_stock', true );

                remove_filter( 'atum/multi_inventory/bypass_mi_get_stock_quantity', '__return_false');
                // regard WooCommerce's Out Of Stock Threshold option - if enabled
                if ( $out_of_stock_threshold = get_option( 'woocommerce_notify_no_stock_amount' ) ) {
                    if ( 1 == get_option( 'wplister_enable_out_of_stock_threshold' ) ) {
                        $variation_qty = $variation_qty - $out_of_stock_threshold;
                    }
                }

                if ( $variation_qty < 0 ) $variation_qty = 0; // prevent error for negative qty

				$stat->setQuantity( min( $max_quantity, $variation_qty ) );
				$revised_quantity = min( $max_quantity, $variation_qty );

		        // handle fixed quantity
		    	if ( $fix_quantity ) {
				    if ( $wc_product && intval( $profile_details['restrict_fixed_quantity'] ) > 0 ) {
					    if ( $profile_details['restrict_fixed_quantity'] == 1 ) {
						    // only do this if the WC product is not out of stock #49283
						    if ( $wc_product->is_in_stock() ) {
							    $fix_quantity = $profile_details['quantity'];
							    WPLE()->logger->info( 'Quantity from profile_details: '. $fix_quantity );
							    $stat->setQuantity( $fix_quantity );
							    $revised_quantity = $fix_quantity;
						    }
					    } elseif ( $profile_details['restrict_fixed_quantity'] == 2 ) {
						    // only apply to WC products not using Manage Stock #49660
						    if ( ! $wc_product->managing_stock() ) {
							    $fix_quantity = $profile_details['quantity'];
							    WPLE()->logger->info( 'Quantity from profile_details: '. $fix_quantity );
							    $stat->setQuantity( $fix_quantity );
							    $revised_quantity = $fix_quantity;
						    }
					    }
				    } else {
					    $fix_quantity = $profile_details['quantity'];
					    WPLE()->logger->info( 'Quantity'. $fix_quantity );

						$stat->setQuantity( $fix_quantity );
					    $revised_quantity = $fix_quantity;
				    }

		    	}

				// do not set SKU for single split variations
				if ( ! $listing_item['parent_id'] ) {
					$stat->setSKU( $cart_item->sku );
				}

				$stat = apply_filters( 'wple_revise_inventory_status_object', $stat, $cart_item, $id, $listing_item );

				$req->addInventoryStatus( $stat );
				WPLE()->logger->info( "Revising inventory status for cart variation #$id ($post_id) - sku: ".$stat->SKU." - qty: ".$stat->Quantity );

			} else {
				// default - simple product
				$this->initServiceProxy($session);

				// regard custom eBay price for locked items as well
                if ( get_option( 'wplister_enable_custom_product_prices', 1 ) ) {
                    if ( $ebay_start_price = get_post_meta( $post_id, '_ebay_start_price', true ) ) {
                        $listing_item['price'] = $ebay_start_price;
                    }
                }

				// skip price when revising inventory during checkout - or when promotional sale is active
                $skip_price_update = apply_filters_deprecated( 'wplister_revise_inventory_status_skip_price', array(false, $id, $listing_item), '2.8.4', 'wple_revise_inventory_status_skip_price' );
                $skip_price_update = apply_filters( 'wple_revise_inventory_status_skip_price', $skip_price_update, $id, $listing_item );
				if ( ! $cart_item && ! self::thisListingHasPromotionalSale( $id ) && ! $skip_price_update ) {
					$stat->setStartPrice( ItemBuilderModel::dbSafeFloatval( $listing_item['price'] ) );
				}

				// skip quantity updates when revising #49784
                $skip_qty_update = apply_filters( 'wple_revise_inventory_status_skip_quantity', false, $id, $listing_item );

				if ( !$skip_qty_update ) {
                    // get available stock
                    // $available_stock = $listing_item['quantity'] - intval( $listing_item['quantity_sold'] );
                    $available_stock = intval( ProductWrapper::getStock( $post_id ) );

                    // regard WooCommerce's Out Of Stock Threshold option - if enabled
                    if ( $out_of_stock_threshold = get_option( 'woocommerce_notify_no_stock_amount' ) ) {
                        if ( 1 == get_option( 'wplister_enable_out_of_stock_threshold' ) ) {
                            $available_stock = $available_stock - $out_of_stock_threshold;
                        }
                    }

                    if ( $available_stock < 0 ) $available_stock = 0; // prevent error for negative qty

                    $stat->setQuantity( min( $max_quantity, $available_stock ) );
                    $revised_quantity = min( $max_quantity, $available_stock );

                    // handle fixed quantity
					if ( $fix_quantity ) {
						if ( $wc_product && intval( $profile_details['restrict_fixed_quantity'] ) > 0 ) {
							if ( $profile_details['restrict_fixed_quantity'] == 1 ) {
								// only do this if the WC product is not out of stock #49283
								if ( $wc_product->is_in_stock() ) {
									$fix_quantity = $profile_details['quantity'];
									WPLE()->logger->info( 'Quantity from profile_details: '. $fix_quantity );
									$stat->setQuantity( $fix_quantity );
									$revised_quantity = $fix_quantity;
								}
							} elseif ( $profile_details['restrict_fixed_quantity'] == 2 ) {
								// only apply to WC products not using Manage Stock #49660
								if ( ! $wc_product->managing_stock() ) {
									$fix_quantity = $profile_details['quantity'];
									WPLE()->logger->info( 'Quantity from profile_details: '. $fix_quantity );
									$stat->setQuantity( $fix_quantity );
									$revised_quantity = $fix_quantity;
								}
							}
						} else {
							$fix_quantity = $profile_details['quantity'];
							WPLE()->logger->info( 'Quantity'. $fix_quantity );

							$stat->setQuantity( $fix_quantity );
							$revised_quantity = $fix_quantity;
						}

					}
                }

                $stat = apply_filters( 'wple_revise_inventory_status_object', $stat, $cart_item, $id, $listing_item );

				$req->addInventoryStatus( $stat );
				WPLE()->logger->info( "Revising inventory status #$id ($post_id) - qty: ".$stat->Quantity );
			}

			// revise inventory
			WPLE()->logger->debug( "Request: ".print_r($req,1) );
			$res = $this->_cs->ReviseInventoryStatus($req);

		}

		// handle response and check if successful
		if ( $this->handleResponse($res) ) {

			// update listing quantity after revising inventory status
			// (The 'quantity' column holds the total qty of available and sold items! It's a mirror of Item.Quantity)
			// TODO: process Quantity returned in ReviseInventoryStatus response instead (or not?)
			if ( isset($revised_quantity) ) {
				$listing_quantity = $revised_quantity + intval( $listing_item['quantity_sold'] );
				self::updateListing( $id, array( 'quantity' => $listing_quantity ) );
			}

			// update listing status for ended items
			if ( 291 == $this->handle_error_code ) {
                self::updateListing( $id, array( 'status' => 'ended' ) );
            } elseif ( 21916750 == $this->handle_error_code ) {
                self::updateListing( $id, array( 'status' => 'ended' ) );
			} elseif ( 1047 == $this->handle_error_code ) {
				self::updateListing( $id, array( 'status' => 'ended' ) );
			} elseif ( 17 == $this->handle_error_code ) {
                self::updateListing( $id, array( 'status' => 'archived' ) );
            } else {
			//} elseif ( ! $cart_item ) { // this is causing listings to be stuck in the changed status when bought via WC #28574
				self::updateListing( $id, array( 'status' => 'published' ) );
			}

			WPLE()->logger->info( "Inventory status for #$id was revised successfully" );

		} // call successful
		self::processErrorsAndWarnings( $id, $this->result );

		return $this->result;

	} // reviseInventoryStatus()


	private function reviseVariableInventoryStatus( $id, $post_id, $listing_item, $session, $variations, $max_quantity, $fix_quantity, $offset = 0, $batch_size = 4 ) {
		WPLE()->logger->info( "reviseVariableInventoryStatus() #$id - variations: ".sizeof($variations)." - offset: ".$offset );

		// preparation - set up new ServiceProxy with given session
		$this->initServiceProxy($session);

		// build request
		$req = new ReviseInventoryStatusRequestType();

		// // set ItemID
		// $stat = new InventoryStatusType();
		// $stat->setItemID( self::getEbayIDFromID($id) );

		// slice variations array
		$variations = array_slice( $variations, $offset, $batch_size );

		// check for profile price modifier
		$profile_details = $listing_item['profile_data']['details'];
		$profile_price   = $profile_details['start_price'];

		$variation_qtys = array();
		foreach ( $variations as $var ) {
            $quantity = min( $max_quantity, $var['stock'] );

            // regard WooCommerce's Out Of Stock Threshold option - if enabled
            if ( $out_of_stock_threshold = get_option( 'woocommerce_notify_no_stock_amount' ) ) {
                if ( 1 == get_option( 'wplister_enable_out_of_stock_threshold' ) ) {
                    $quantity = $quantity - $out_of_stock_threshold;
                }
            }

            if ( $quantity < 0 ) $quantity = 0; // prevent error for negative qty

            // handle fixed quantity
            if ( $fix_quantity ) $quantity = $fix_quantity;

            /**
             * Set the product's price.
             *
             * Check for a custom Start Price in the parent and use it if it exists. Otherwise, load the price of the
             * default variation.
             */
            $product_start_price = get_post_meta( $var['post_id'], '_ebay_start_price', true );
            if ( get_option( 'wplister_enable_custom_product_prices', 1 ) && $product_start_price ) {
                if ( 0 == get_option( 'wplister_apply_profile_to_ebay_price', 0 ) ) {
                    // default behavior - always use the _ebay_start_price if present
                    $start_price = $product_start_price;
                    WPLE()->logger->info( 'Custom product price: '. $product_start_price );
                } else {
                    // Apply the profile pricing rule on the _ebay_start_price
                    $start_price = ListingsModel::applyProfilePrice( $product_start_price, $profile_details['start_price'] );
                    WPLE()->logger->info( 'Custom product price from profile: '. $start_price );
                }
            } else {
                // Use the price of the default variation
                $start_price = $var['price'];
                $start_price = ListingsModel::applyProfilePrice( $start_price, $profile_details['start_price'] );
            }
            /*if ( get_option( 'wplister_apply_profile_to_ebay_price', 0 ) ) {
                // apply profile price - if set
                $var['price'] = empty( $profile_price ) ? $var['price'] : self::applyProfilePrice( $var['price'], $profile_price );
            }*/

			$stat = new InventoryStatusType();
			$stat->setItemID( self::getEbayIDFromID($id) );
			$stat->setSKU( $var['sku'] );
			$stat->setQuantity( $quantity );

            // skip price when revising inventory during checkout - or when promotional sale is active
            $skip_price_update = apply_filters_deprecated( 'wplister_revise_inventory_status_skip_price', array(false, $id, $listing_item), '2.8.4', 'wple_revise_inventory_status_skip_price' );
            $skip_price_update = apply_filters( 'wple_revise_inventory_status_skip_price', $skip_price_update, $id, $listing_item );
            if ( ! $skip_price_update ) {
                $stat->setStartPrice( ItemBuilderModel::dbSafeFloatval( $start_price ) );
            }

            $stat = apply_filters( 'wple_revise_variable_inventory_status_object', $stat, $var, $id, $post_id, $listing_item, $variations );

			$req->addInventoryStatus( $stat );
			WPLE()->logger->info( "Revising inventory status for product variation #$id ($post_id) - sku: ".$stat->SKU." - qty: ".$stat->Quantity );

			$variation_qtys[ $var['sku'] ] = $quantity;
		}

		// revise inventory
		WPLE()->logger->debug( "Request: ".print_r($req,1) );
		$res = $this->_cs->ReviseInventoryStatus($req);

		// process result and update variation cache
		$InventoryStatusNodes = ($res && method_exists($res, 'getInventoryStatus')) ? $res->getInventoryStatus() : false;
		WPLE()->logger->debug( "ReviseInventoryStatus response node: ".print_r( $InventoryStatusNodes, 1) );
		if ( is_array($InventoryStatusNodes) ) {

			$listing_item = self::getItem( $id );
			$item_variations = maybe_unserialize( $listing_item['variations'] );
			foreach ( $InventoryStatusNodes as $node ) {

				// find variation in cache
				// ReviseInventoryStatus is only used if there are SKUs, so we don't need to generate key from attributes (which are not provided in the result anyway)
				$key = $node->SKU;

                /**
                 * The $node->Quantity value should not be used to determine the current stock quantity
                 * of $variations[$key] because as per the [eBay API](https://developer.ebay.com/devzone/xml/docs/reference/ebay/types/InventoryStatusType.html),
                 * the Quantity attribute is actually the sum of the current quantity and the number of sold items.
                 *
                 * The most accurate way of doing this is to GetItem but for now, let's rely on the quantity value that was sent to eBay.
                 */

				// update variations cache
				if ( isset( $item_variations[$key] ) ) {
                    $item_variations[$key]['stock'] = $variation_qtys[ $key ];
					$item_variations[$key]['price'] = $node->StartPrice->value;
				}

				// if zero stock, remove from cache - eBay does the same

                // node->Quantity is not always returned so make sure to skip removing the variation
                // from the cache if the Quantity is not available #560068
				if ( isset($node->Quantity) && $node->Quantity == 0 ) {
				    WPLE()->logger->info( 'node->Quantity is 0 - removing variation '. $key .' from the cache' );
				    unset( $item_variations[$key] );
                }

			}
			self::updateListing( $id, array( 'variations' => maybe_serialize( $item_variations ) ) );

		}


		return $res;

	} // reviseVariableInventoryStatus()


	static function checkVariationSKUs( $variations ) {
		$VariationsSkuAreUnique = true;
		$VariationsSkuMissing   = false;
		$VariationsSkuArray     = array();

		// check each variation
		foreach ($variations as $var) {

			// SKUs must be unique - if present
			if ( ($var['sku']) != '' ) {
				if ( in_array( $var['sku'], $VariationsSkuArray )) {
					$VariationsSkuAreUnique = false;
				} else {
					$VariationsSkuArray[] = $var['sku'];
				}
			} else {
				$VariationsSkuMissing = true;
			}

		}

		if ( $VariationsSkuMissing )
			return false;

		if ( ! $VariationsSkuAreUnique )
			return false;

		return true;
	} // checkVariationSKUs()


	static function thisListingHasPromotionalSale( $listing ) {

		// fetch item from DB unless item array was provided
		if ( ! is_array( $listing ) ) {
			$listing = self::getItem( $listing );
			if ( ! $listing ) return false;
		}

		// get listing details
		$details = self::decodeObject( $listing['details'], false, true );
		if ( ! $details ) return false;
		if ( ! is_object($details) ) return false;

		// check whether promotional sale is enabled
        if ( !is_callable( array( $details, 'getSellingStatus' ) ) ) {
            return false;
        }

		$SellingStatus = $details->getSellingStatus();
		if ( ! $SellingStatus ) return false;

		$PromotionalSaleDetails = $SellingStatus->getPromotionalSaleDetails();
		if ( ! $PromotionalSaleDetails ) return false;

		// get promotional sale details
		$OriginalPrice = $PromotionalSaleDetails->getOriginalPrice()->value;
		$StartTime     = $PromotionalSaleDetails->getStartTime();
		$EndTime       = $PromotionalSaleDetails->getEndTime();

		// check whether sale is active
		if ( strtotime( $StartTime ) > time() ) return false;
		if ( strtotime( $EndTime   ) < time() ) return false;

		WPLE()->logger->info('Item '.$listing['id'].' has an active promotional sale. Original price: '.$OriginalPrice);
		return $OriginalPrice;
	} // thisListingHasPromotionalSale()

	static function thisListingUsesOutOfStockControl( $listing_item ) {
		if ( ! is_array( $listing_item ) ) return false;

		// only GTC listings can use OOSC
		if ( $listing_item['listing_duration'] != 'GTC' ) return false;

		return self::thisAccountUsesOutOfStockControl( $listing_item['account_id'] );
	} // thisListingUsesOutOfStockControl()

	static function thisAccountUsesOutOfStockControl( $account_id ) {
		if ( ! $account_id ) $account_id = get_option( 'wplister_default_account_id' );

		$accounts = WPLE()->accounts;
		if ( isset( $accounts[ $account_id ] ) && ( $accounts[ $account_id ]->oosc_mode == 1 ) ) {
			return true;
		}

		return false;
	} // thisAccountUsesOutOfStockControl()


	static function checkStockLevel( $listing_item ) {
		if ( ! is_array( $listing_item) ) $listing_item = (array) $listing_item;

		$post_id         = $listing_item['post_id'];
		$profile_details = $listing_item['profile_data']['details'];
		$locked          = $listing_item['locked'];

		if ( ProductWrapper::hasVariations( $post_id ) ) {

		    $variations = ProductWrapper::getVariations( $post_id );
		    $stock = 0;

		    foreach ( $variations as $var ) {
		        $var_stock = intval( $var['stock'] );
                $stock += $var_stock > 0 ? $var_stock : 0;
		    }

		} else {

			$stock = ProductWrapper::getStock( $post_id );

		}

		// fixed profile quantity will always be in stock - except for locked items
    	if ( ! $locked && ( intval( $profile_details['quantity'] ) > 0 ) ) $stock = $profile_details['quantity'];
		WPLE()->logger->info( "checkStockLevel() result: ".$stock );

		return ( intval($stock) > 0 ) ? $stock : false;

	} // checkStockLevel()


	function verifyAddItem( $id, $session )
	{
		// skip this item if item status not allowed
		$allowed_statuses = array( 'prepared', 'verified' );
		if ( ! $this->itemHasAllowedStatus( $id, $allowed_statuses ) ) return $this->result;

		// build item
		$ibm = new ItemBuilderModel();
		$item = $ibm->buildItem( $id, $session );
		if ( ! $ibm->checkItem($item) ) return $ibm->result;

		// eBay Motors (beta)
		if ( $item->Site == 'eBayMotors' ) $session->setSiteId( 100 );

		// preparation - set up new ServiceProxy with given session
		$this->initServiceProxy($session);

		// switch to FixedPriceItem if product has variations
		$listing_item = self::getItem( $id );
		// $useFixedPriceItem = ( ProductWrapper::hasVariations( $listing_item['post_id'] ) ) ? true : false;
		// $useFixedPriceItem = ( 'FixedPriceItem' == $listing_item['auction_type'] ) ? true : false;

		WPLE()->logger->info( "Verifying #$id: ".$item->Title );
		if ( self::listingUsesFixedPriceItem( $listing_item ) ) {

			$req = new VerifyAddFixedPriceItemRequestType();
			$req->setItem($item);

			WPLE()->logger->debug( "Request: ".print_r($req,1) );
			$res = $this->_cs->VerifyAddFixedPriceItem($req);

		} else {

			$req = new VerifyAddItemRequestType();
			$req->setItem($item);

			WPLE()->logger->debug( "Request: ".print_r($req,1) );
			$res = $this->_cs->VerifyAddItem($req);

		}

		// handle response and check if successful
		if ( $this->handleResponse($res) ) {

			// save listing fees to db
			$listingFee = self::getListingFeeFromResponse( $res );
			// $data['ebay_id'] = $res->ItemID;
			$data['fees'] = $listingFee;
			$data['status'] = 'verified';
			self::updateListing( $id, $data );

			WPLE()->logger->info( "Item #$id verified with ebay, getAck(): ".$res->getAck() );

		} // call successful
		self::processErrorsAndWarnings( $id, $this->result );

		return $this->result;

	} // verifyAddItem()


	static function processErrorsAndWarnings( $id, $preprocessed_result ) {
		// echo "<pre>preprocessed_result: ";print_r($preprocessed_result);echo"</pre>";#die();

		// handle errors and warnings
		$errors         = array();
		$warnings       = array();
		$listing_data   = array();

		if ( is_array( $preprocessed_result->errors ) )
		foreach ( $preprocessed_result->errors as $original_error_obj ) {

			// clone error object and remove HtmlMessage
			$error_obj = clone $original_error_obj;
			unset( $error_obj->HtmlMessage );

			if ( 'Error' == $error_obj->SeverityCode ) {
				$errors[]         = $error_obj;
			} elseif ( 'Warning' == $error_obj->SeverityCode ) {
				$warnings[]       = $error_obj;
			}

		} // foreach error or warning


		// update listing
		if ( ! empty( $errors ) ) {

			// $listing_data['status']  = 'failed';
			$listing_data['last_errors'] = serialize( array( 'errors' => $errors, 'warnings' => $warnings ) );
			self::updateListing( $id, $listing_data );
			// WPLE()->logger->info('changed status to FAILED: '.$id);

		} elseif ( ! empty( $warnings ) ) {

			// $listing_data['status']  = 'published';
			$listing_data['last_errors'] = serialize( array( 'errors' => $errors, 'warnings' => $warnings ) );
			self::updateListing( $id, $listing_data );
			// WPLE()->logger->info('changed status to published: '.$id);

		} else {

			$listing_data['last_errors'] = '';
			self::updateListing( $id, $listing_data );

		}

		// echo "<pre>id: ";print_r($id);echo"</pre>";#die();
		// echo "<pre>data: ";print_r($listing_data);echo"</pre>";#die();

	} // processErrorsAndWarnings()


	function endItem( $id, $session )
	{
		// skip this item if item status not allowed
		$allowed_statuses = array( 'published', 'changed' );
		if ( ! $this->itemHasAllowedStatus( $id, $allowed_statuses ) ) return $this->result;

		// preparation - set up new ServiceProxy with given session
		$this->initServiceProxy($session);

		// get eBay ID
		$item = self::getItem( $id );
		$item_id = $item['ebay_id'];

		$req = new EndItemRequestType(); # ***
        $req->setItemID( $item_id );
        // $req->setEndingReason('LostOrBroken');
        $req->setEndingReason('NotAvailable');

		WPLE()->logger->info( "calling EndItem($id) #$item_id " );
		WPLE()->logger->debug( "Request: ".print_r($req,1) );
		$res = $this->_cs->EndItem($req); # ***
		WPLE()->logger->info( "EndItem() Complete #$item_id" );
		WPLE()->logger->debug( "Response: ".print_r($res,1) );

		// handle response and check if successful
		if ( $this->handleResponse($res) ) {

			// save ebay ID and fees to db
			$data['end_date'] = $res->EndTime;
			$data['status'] = 'ended';

			// mark as sold if no stock remaining
			if ( ! self::checkStockLevel( $item ) )
				$data['status'] = 'sold';

			// update listing status
			if (  17 == $this->handle_error_code ) $data['status'] = 'archived';
			self::updateListing( $id, $data );

			WPLE()->logger->info( "Item #$id was ended manually. " );

			do_action( 'wplister_ended_item', $id, $item, $res );

		} // call successful
		self::processErrorsAndWarnings( $id, $this->result );

		return $this->result;

	} // endItem()


	function itemHasAllowedStatus( $id, $allowed_statuses )
	{
		$item = self::getItem( $id );
		if ( in_array( $item['status'], $allowed_statuses ) ) {
			return true;
		} else {
			WPLE()->logger->info("skipped item $id with status ".$item['status']);
			WPLE()->logger->debug("allowed_statuses: ".print_r($allowed_statuses,1) );
			$msg = sprintf( 'Skipped %s item "%s" as its listing status is neither %s', $item['status'], $item['auction_title'], join( ' nor ', $allowed_statuses ) );
			if ( sizeof($allowed_statuses) == 1 )
				$msg = sprintf( 'Skipped %s item "%s" as its listing status is not %s', $item['status'], $item['auction_title'], join( ' or ', $allowed_statuses ) );


            wple_show_message( $msg, 'error' );

			// create error object
			$errorObj = new stdClass();
			$errorObj->SeverityCode = 'Info';
			$errorObj->ErrorCode 	= 102;
			$errorObj->ShortMessage = 'Invalid listing status';
			$errorObj->LongMessage 	= $msg;
			$errorObj->HtmlMessage 	= $msg;
			// $errors[] = $errorObj;

			// save results as local property
			$this->result = new stdClass();
			$this->result->success = false;
			$this->result->errors  = array( $errorObj );

			return false;
		}

	} // itemHasAllowedStatus()


	static function getListingFeeFromResponse( $res )
	{

		$fees = new FeesType();
		$fees = $res->GetFees();
		if ( ! $fees ) return false;
		foreach ($fees->getFee() as $fee) {
			if ( $fee->GetName() == 'ListingFee' ) {
				$listingFee = $fee->GetFee()->getTypeValue();
			}
			WPLE()->logger->debug( 'FeeName: '.$fee->GetName(). ' is '. $fee->GetFee()->getTypeValue().' '.$fee->GetFee()->getTypeAttribute('currencyID') );
		}
		return $listingFee;

	} // getListingFeeFromResponse()


	public function getLatestDetails( $ebay_id, $session ) {

		// preparation
		$this->initServiceProxy($session);

		// $this->_cs->setHandler('ItemType', array(& $this, 'updateItemDetail'));

		// download the shipping data
		$req = new GetItemRequestType();
        $req->setItemID( $ebay_id );

		$res = $this->_cs->GetItem($req);

		// handle response and check if successful
		if ( $this->handleResponse($res) ) {
			WPLE()->logger->info( "Item #$ebay_id was fetched from eBay... ".$res->ItemID );
			return $res->Item;
		} // call successful

		return $this->result;

	}

	public function updateItemDetails( $id, $session, $is_second_request = false ) {

		// get item data
		$item = self::getItem( $id );

		// preparation
		$this->initServiceProxy($session);

		$this->_cs->setHandler('ItemType', array(& $this, 'handleItemDetail'));

		// download the shipping data
		$req = new GetItemRequestType();
        $req->setItemID( $item['ebay_id'] );
        $req->setIncludeWatchCount( true );
		#$req->setDetailName( 'PaymentOptionDetails' );
		#$req->setActiveList( true );

		$res = $this->_cs->GetItem($req);

		// handle response and check if successful
		if ( $this->handleResponse($res, $is_second_request ) ) {
			WPLE()->logger->info( "Item #$id was updated from eBay, ItemID is ".$item['ebay_id'] );

			// archive listing if API returned error 17: "This item cannot be accessed..."
			if ( 17 == $this->handle_error_code ) {
				$data = array();
				$data['status'] = 'archived';
				self::updateListing( $id, $data );
			}

			do_action( 'wplister_updated_item_details', $id, $item );

		} // call successful

		return $this->result;

	}


	function handleItemDetail( $type, $Detail )
	{
		global $wpdb;
		$listing = new Listing();

		//#type $Detail ItemType

		// map ItemType to DB columns
		$data = self::mapItemDetailToDB( $Detail );

		$data = apply_filters( 'wple_data_pre_handleItemDetail', $data, $Detail );

		WPLE()->logger->debug('Detail: '.print_r($Detail,1) );
		WPLE()->logger->debug('data: '.print_r($data,1) );
		if ( ! $Detail->ItemID ) return true; // avoid problems when item does not exist anymore

        // Update WatchCount #50534
        if ( isset( $data['watch_count'] ) ) {
            $post_id  = WPLE_ListingQueryHelper::getPostIDFromEbayID( $Detail->ItemID );

            if ( $post_id ) {
                update_post_meta( $post_id, '_ebay_watch_count', $data['watch_count'] );
            }

            // unset so it doesnt get inserted into the ebay_auctions table
            unset( $data['watch_count'] );
        }

        if ( !empty( $data ) ) {
            $result = $wpdb->update( $this->tablename, $data, array( 'ebay_id' => $Detail->ItemID ) );
            if ( $result === false ) {
                WPLE()->logger->error('sql: '.$wpdb->last_query );
                WPLE()->logger->error( $wpdb->last_error );
            }
        }


		// check for an updated ItemID

		// if item was relisted manually on ebay.com
		if ( $Detail->ListingDetails->RelistedItemID ) {

			// keep item id in history
			self::addItemIdToHistory( $Detail->ItemID, $Detail->ItemID );

			// mark as relisted - ie. should be updated once again
			$wpdb->update( $this->tablename, array( 'status' => 'relisted' ), array( 'ebay_id' => $Detail->ItemID ) );

			// update the listings ebay_id
			$wpdb->update( $this->tablename, array( 'ebay_id' => $Detail->ListingDetails->RelistedItemID ), array( 'ebay_id' => $Detail->ItemID ) );

		}

		// if item was relisted through WP-Lister
		if ( $Detail->RelistParentID ) {

			// if listing is still active, it was not relisted!
			// RelistParentID can also be set when "Sell similar item" is used - even with the api docs saying otherwise
			if ( $Detail->SellingStatus->ListingStatus != 'Active' ) {

				// keep item id in history
				self::addItemIdToHistory( $Detail->ItemID, $Detail->RelistParentID );

			}

		}

		#WPLE()->logger->info('sql: '.$wpdb->last_query );
		#WPLE()->logger->info( $wpdb->last_error );
        do_action( 'wple_post_handleItemDetail', $Detail, $data );

		return true;
	} // handleItemDetail()

	/**
	 * Map the listing properties so the fieldnames match those used in the ebay_auctions table
	 * @param $listing_data
	 * @return array
	 */
	public static function mapListingToDB( $listing_data ) {
		$map = [
			'title'         => 'auction_title',
			'content'       => 'post_content',
			'type'          => 'auction_type',
			'duration'      => 'listing_duration',
			'view_item_url' => 'ViewItemURL',
			'gallery_url'   => 'GalleryURL',
		];

		// remove product_properties
		unset( $listing_data['product_properties'] );

		$new_data = [];
		foreach ( $listing_data as $field => $value ) {
			if ( isset( $map[ $field ] ) ) {
				$new_data[ $map[$field] ] = $value;
			} else {
				$new_data[ $field ] = $value;
			}
		}

		return $new_data;
	}
	static function mapItemDetailToDB( $Detail )
	{
		//#type $Detail ItemType
		$data['ebay_id'] 			= $Detail->ItemID;
		$data['auction_title'] 		= $Detail->Title;
		$data['auction_type'] 		= $Detail->ListingType;
		$data['listing_duration'] 	= $Detail->ListingDuration;
		$data['date_published']     = self::convertEbayDateToSql( $Detail->ListingDetails->StartTime );
		$data['end_date']     		= self::convertEbayDateToSql( $Detail->ListingDetails->EndTime );
		$data['price'] 				= $Detail->SellingStatus->CurrentPrice->value;
		$data['quantity_sold'] 		= $Detail->SellingStatus->QuantitySold;
		$data['quantity'] 			= $Detail->Quantity;
		$data['watch_count']		= $Detail->WatchCount;
		// $data['quantity'] 		= $Detail->Quantity - $Detail->SellingStatus->QuantitySold; // this is how it should work in the future...
		$data['ViewItemURL'] 		= $Detail->ListingDetails->ViewItemURL;
		$data['GalleryURL'] 		= $Detail->PictureDetails->PictureURL[0];

		// check if this item has variations
        if ( $Detail->getVariations() && count( $Detail->Variations->Variation ) > 0 ) {

			$variations = array();
			foreach ($Detail->Variations->Variation as $Variation ) {
				$new_var = array();
				$new_var['sku']      = $Variation->SKU;
				$new_var['price']    = $Variation->StartPrice->value;
				$new_var['stock']    = $Variation->Quantity - $Variation->SellingStatus->QuantitySold;
				$new_var['sold']     = $Variation->SellingStatus->QuantitySold;

				$new_var['variation_attributes'] = array();
				foreach ( $Variation->VariationSpecifics as $VariationSpecifics ) {
					$name = $VariationSpecifics->Name;
					$new_var['variation_attributes'][ $name ] = $VariationSpecifics->Value[0];
				}

				// use SKU as array key - or generate key from attributes
				$key = $Variation->SKU;
				if ( ! $key ) $key = self::generateVariationKeyFromAttributes( $new_var['variation_attributes'] );

				// add variation to cache
				$variations[$key] = $new_var;
			}
			$data['variations'] = maybe_serialize( $variations );
			WPLE()->logger->info('updated variations cache: '.print_r($variations,1) );
			// echo "<pre>";print_r($variations);echo"</pre>";

			// if this item has variations, we don't update quantity
			unset( $data['quantity'] );
			WPLE()->logger->info('skip quantity for variation #'.$Detail->ItemID );
		}

		// set status to ended if end_date is in the past
		// if ( time() > mysql2date('U', $data['end_date']) ) {

		// set status to ended if ListingStatus is Ended or Completed
		if ( $Detail->SellingStatus->ListingStatus != 'Active' ) {
			$data['status'] 		= 'ended';

			// but mark as sold if no stock remaining
			// $lm = new ListingsModel();
			$item = self::getItemByEbayID( $data['ebay_id'] );
			if ( $item && ! self::checkStockLevel( $item ) ) $data['status'] = 'sold';

		} else {
			$data['status'] 		= 'published';
		}

		$data['details'] = self::encodeObject( $Detail );

		return $data;
	} // mapItemDetailToDB()



	static public function updateListing( $id, $data ) {
		global $wpdb;
		$table = $wpdb->prefix . self::TABLENAME;

		// handle NULL values
		foreach ($data as $key => $value) {
			if ( NULL === $value ) {
				$key = esc_sql( $key );
				$wpdb->query( $wpdb->prepare("UPDATE {$table} SET $key = NULL WHERE id = %s ", $id ) );
				WPLE()->logger->info('SQL to set NULL value: '.$wpdb->last_query );
				WPLE()->logger->info( $wpdb->last_error );
				unset( $data[$key] );
				if ( empty($data) ) return;
			}
		}

		// update
		$wpdb->update( $table, $data, array( 'id' => $id ) );

		WPLE()->logger->debug('sql: '.$wpdb->last_query );
		WPLE()->logger->info( $wpdb->last_error );
	}

	static public function updateWhere( $where, $data ) {
		global $wpdb;
		$table = $wpdb->prefix . self::TABLENAME;

		// update
		$wpdb->update( $table, $data, $where );
	}

	public function updateEndedListings( $session ) {
		global $wpdb;


		// set listing status to archived for all listings with an end_date < 90 days in the past
		$items = WPLE_ListingQueryHelper::getAllOldListingsToBeArchived();
		WPLE()->logger->info('getAllOldListingsToBeArchived() found '.sizeof($items).' items');
		foreach ($items as $item) {
			// TODO: use self::updateWhere()
			$wpdb->update( $this->tablename, array( 'status' => 'archived' ), array( 'id' => $item['id'] ) );
			WPLE()->logger->info('updateEndedListings() changed item '.$item['id'].' to status archived');
		}


		// set listing status to ended for all listings with an end_date in the past
		$items = WPLE_ListingQueryHelper::getAllPastEndDate();
		WPLE()->logger->info('getAllPastEndDate() found '.sizeof($items).' items');

		$auto_update_ended_items = get_option( 'wplister_auto_update_ended_items' );

		foreach ($items as $item) {
			// if quantity sold is greater than quantity, mark as sold instead of ended
			// $status = intval( $item['quantity_sold'] ) < intval( $item['quantity'] ) ? 'ended' : 'sold';

            // check if details for ended items should be fetched from ebay automatically
			// diabled by default for performance reasons - it is not recommended to relist items on eBay anyway
            if ( $auto_update_ended_items ) {

				// suggested by Kim - to check if an ended item has been relisted
				$oldItemID = $item['ebay_id'];
				$this->updateItemDetails( $item['id'], $session );

			}

			// default status is ended
			$status = 'ended';

			// load item details
			$item = self::getItem( $item['id'] );

			// check eBay available quantity first - if all were sold
			if ( intval( $item['quantity_sold'] ) >= intval( $item['quantity'] ) ) {

				// if eBay indicates item was sold, check WooCommerce stock - updateDetails does the same
				if ( ! self::checkStockLevel( $item ) )
					$status = 'sold';

			}

            WPLE()->logger->info( 'Checking for GTC listings' );
			// check item details to make sure we don't end GTC items
			// (if GTC listings are imported from eBay and assigned a listing profile not using GTC, they would be ended...)
            // #57454: Check for product-level ListingDuration and use it if present
            $product_listing_duration = get_post_meta( $item['post_id'], '_ebay_listing_duration', true );
            $actual_listing_duration = $product_listing_duration ? $product_listing_duration : $item['listing_duration'];

            WPLE()->logger->info( '$actual_listing_duration: '. $actual_listing_duration );
            if ( 'GTC' == $actual_listing_duration ) {
                WPLE()->logger->info('skipped GTC item, assuming it is still published: '.$item['ebay_id']);
                continue;
            } else {
                WPLE()->logger->debug( 'item[details]: '. print_r( $item['details'], 1 ) );
                //WPLE()->logger->debug( 'decoded item_details: '. print_r( $item_details, 1 ) );
            }

			// check if ebay ID has changed - ie. item has been relisted
            if ( $auto_update_ended_items ) {

				if ( $oldItemID != $item['ebay_id'] )
					$status = 'published';

			}


			$wpdb->update( $this->tablename, array( 'status' => $status ), array( 'id' => $item['id'] ) );
			WPLE()->logger->info('updateEndedListings() changed item '.$item['ebay_id'].' ('.$item['id'].') to status '.$status);
		}

		## BEGIN PRO ##

		// schedule ended listings for auto relist - if enabled
		$items = WPLE_ListingQueryHelper::getAllEnded();
		foreach ($items as $item) {

			$profile_data    = self::decodeObject( $item['profile_data'], true );
			$profile_details = $profile_data['details'];

			// skip if auto relist is disabled
			if ( ! isset( $profile_details['autorelist_enabled'] ) ) continue;
			if ( ! $profile_details['autorelist_enabled'] ) continue;

			// check relist condition
			if ( 'RelistAfterHours' == $profile_details['autorelist_condition'] ) {

				if ( ! @$profile_details['autorelist_after_hours'] ) return;

				// schedule in x hours
				$relist_ts   = time() + intval( $profile_details['autorelist_after_hours'] ) * 3600;
				$relist_date = gmdate('Y-m-d H:i:s', $relist_ts );

			} elseif ( 'RelistAtTimeOfDay' == $profile_details['autorelist_condition'] ) {

				if ( ! @$profile_details['autorelist_at_timeofday'] ) return;

				// schedule at specific time of day
				$time_of_day = $profile_details['autorelist_at_timeofday'];
				$today       = strtotime( $time_of_day );
				$tomorrow    = strtotime( $time_of_day ) + 24 * 3600;

				if ( $today > time() ) {
					$relist_date = gmdate('Y-m-d H:i:s', $today );
				} else {
					$relist_date = gmdate('Y-m-d H:i:s', $tomorrow );
				}

			} else { // RelistImmediately

				// schedule now
				$relist_date = gmdate('Y-m-d H:i:s');

			}

			$wpdb->update( $this->tablename, array( 'relist_date' => $relist_date ), array( 'id' => $item['id'] ) );
			WPLE()->logger->info('updateEndedListings() scheduled item '.$item['ebay_id'].' ('.$item['id'].') to be relisted at '.$relist_date);
			WPLE()->logger->info('autorelist_condition: '.$profile_details['autorelist_condition']);
		}

		## END PRO ##

		#WPLE()->logger->info('sql: '.$wpdb->last_query );
		#WPLE()->logger->info( $wpdb->last_error );
	}


	// set quantity and regarding quantity_sold
	// (Item.Quantity holds the total quantity of available and sold units combined)
	static public function setListingQuantity( $post_id, $quantity ) {
		global $wpdb;
		$table = $wpdb->prefix . self::TABLENAME;

		// get current quantity_sold
		$listings = WPLE_ListingQueryHelper::getAllListingsFromPostID( $post_id );
		if ( empty($listings) ) return;

		foreach ( $listings as $listing_item ) {

			// get current quantity_sold
			$quantity_sold = $listing_item->quantity_sold;
			$quantity      = $quantity + intval( $quantity_sold );

			$wpdb->update( $table, array( 'quantity' => $quantity ), array( 'id' => $listing_item->id ) );

		}

	} // setListingQuantity()

	/**
	 * @param int $post_id
	 * @param Listing $listing
	 *
	 * @return void
	 */
    public static function setListingVariationsQuantity( $post_id, $listing ) {
	    global $wpdb;
        WPLE()->logger->info( 'setListingVariationsQuantity for post #'. $post_id );

        $table = $wpdb->prefix . self::TABLENAME;

	    $product_variations = ProductWrapper::getVariations( $post_id );
        $variations = maybe_unserialize( @$listing->getVariations() );

        if ( is_array( $product_variations ) ) WPLE()->logger->debug( 'Found product variations: '. count( $product_variations ) );
        if ( is_array( $variations ) ) WPLE()->logger->debug( 'Found listing variations: '. count( $variations ) );

        if ( empty( $product_variations ) || empty( $variations ) ) {
            WPLE()->logger->info( 'Empty variations. Skipping.' );
            return;
        }

        foreach ( $product_variations as $var ) {
            WPLE()->logger->debug( 'Processing variation '. print_r( $var, true ) );
            if ( empty( $var['sku'] ) ) {
                WPLE()->logger->info( 'Product variation has no SKU. Skipping' );
                continue;
            }

            $sku = $var['sku'];
            if ( isset( $variations[ $sku ] ) ) {
                $variations[ $sku ]['stock'] = $var['stock'];
                $variations[ $sku ]['price'] = \ProductWrapper::getPrice( $var['post_id'] );
                WPLE()->logger->info( 'SKU '. $sku .' new stock: '. $var['stock'] .' / new price: '. $variations[$sku]['price'] );
            }
        }

        $variations = serialize( $variations );

        $wpdb->update( $table, array( 'variations' => $variations ), array( 'id' => $listing->getId() ) );
    } // setListingVariationsQuantity()

    /**
     * Returns an array Listing IDs that's been marked as modified/changed
     * @param $post_id
     * @return int[]
     */
	public function markItemAsModified( $post_id ) {
		global $wpdb;

		WPLE()->logger->info( 'markItemAsModified #'. $post_id );
        $changed_ids = [];

		// get single listing for post_id
		$listing_id = WPLE_ListingQueryHelper::getListingIDFromPostID( $post_id );
        $this->reapplyProfileToItem( $listing_id );
        $changed_ids[] = $listing_id;

        WPLE()->logger->debug( 'Found listing #'. $listing_id .' from post #'. $post_id );

        // process all listings for post_id
		$listings = WPLE_ListingQueryHelper::getAllListingsFromPostID( $post_id );
		WPLE()->logger->debug( 'Found listings for post #'. $post_id .': '. print_r( $listings, true ) );
        if ( is_array( $listings ) && ( sizeof( $listings ) > 1 ) ) {
        	foreach ( $listings as $listing_item ) {
        	    // skip the matching listing from the first query
                if ( $listing_id == $listing_item->id ) {
                    continue;
                }

		        $this->reapplyProfileToItem( $listing_item->id );
		        $changed_ids[] = $listing_item->id;
        	}
        }

        // process split variations - fetched by parent_id
		$listings = WPLE_ListingQueryHelper::getAllListingsFromParentID( $post_id );
        if ( is_array( $listings ) ) {
        	foreach ( $listings as $listing_item ) {
		        $this->reapplyProfileToItem( $listing_item->id );
                $changed_ids[] = $listing_item->id;
				WPLE()->logger->info('reapplied profile to SPLIT variation for post_id '.$post_id.' - listing_id: '.$listing_item->id );
        	}
        }

        return array_unique( $changed_ids );

		// set published items to changed
		// $wpdb->update( $this->tablename, array( 'status' => 'changed' ), array( 'status' => 'published', 'post_id' => $post_id ) );

		// set verified items to prepared
		// $wpdb->update( $this->tablename, array( 'status' => 'prepared' ), array( 'status' => 'verified', 'post_id' => $post_id ) );
	}


	static public function reSelectListings( $ids ) {
		global $wpdb;
		$table = $wpdb->prefix . self::TABLENAME;

		foreach( $ids as $id ) {
			$status = WPLE_ListingQueryHelper::getStatus( $id );
			if ( ( $status == 'published' ) || ( $status == 'changed' ) ) {
				$wpdb->update( $table, array( 'status' => 'changed_profile' ), array( 'id' => $id ) );
			} elseif ( $status == 'ended' ) {
				$wpdb->update( $table, array( 'status' => 'reselected'      ), array( 'id' => $id ) );
			} else {
				$wpdb->update( $table, array( 'status' => 'selected'        ), array( 'id' => $id ) );
			}
		}
	}

	static public function cancelSelectingListings() {
		global $wpdb;
		$table = $wpdb->prefix . self::TABLENAME;

		// $selectedProducts = WPLE_ListingQueryHelper::selectedProducts();
		$selected = WPLE_ListingQueryHelper::getAllSelected();

		foreach( $selected as $listing ) {
			$id     = $listing['id'];
			$status = $listing['status'];
			if ( ( $status == 'changed_profile' ) ) {
				$wpdb->update( $table, array( 'status' => 'changed'  ), array( 'id' => $id ) );
			} elseif ( $status == 'reselected' ) {
				$wpdb->update( $table, array( 'status' => 'ended'    ), array( 'id' => $id ) );
			} else {
				$wpdb->update( $table, array( 'status' => 'archived' ), array( 'id' => $id ) );
			}
		}
	}

	## BEGIN PRO ##
	public function splitVariation( $id ) {
		global $wpdb;

		// get listing item
		$listing_item = self::getItem( $id );

		// return if listing has already been published - only allow prepared items to be split
		if ( ! in_array( $listing_item['status'], array('selected','prepared') ) ) {
			WPLE()->logger->info('skipped splitting '.$listing_item['status'].' listing '.$id);
			return false;
		}

		// return if there are no variations
		if ( ! ProductWrapper::hasVariations( $listing_item['post_id'] ) ) return false;

        // get profile
		$profilesModel = new ProfilesModel();
        $profile = $profilesModel->getItem( $listing_item['profile_id'] );

		// get variations
        $variations = ProductWrapper::getListingVariations( $listing_item['post_id'] );

        // loop variations
        $new_items = array();
        foreach ($variations as $var) {

        	// append attribute values to title
        	$title = self::processSingleVariationTitle( $listing_item['auction_title'], $var['variation_attributes'] );
        	$title = apply_filters( 'wple_process_single_variation_title', $title, $listing_item, $var );

			// create new item
            $item_id = $this->prepareProductForListing( $var['post_id'], false, '', $title, $listing_item['post_id'], $var['variation_attributes'] );

            // store the variation attributes so we could match using the attributes #26135
            //$data['variations'] = maybe_serialize( $var['variation_attributes'] );
            //self::updateListing( $item_id, $data );

            // get item object and save to array
           	$new_items[] = self::getItem( $item_id );
        }

        /*
         * Seems like we need to update the title to allow for the prefix and suffix
         * to be added to the new split listings #22126
        // apply profile to new items - without modifying the title again
        $this->applyProfileToNewListings( $profile, $new_items, false );
        */
        $this->applyProfileToNewListings( $profile, $new_items, false );

        // end original item with variations
		$wpdb->update( $this->tablename, array( 'status' => 'ended' ), array( 'id' => $id ) );

		WPLE()->logger->info('created '.count($new_items).' new items from variable listing '.$id.' - product ID '.$listing_item['post_id'].'');
	}
	## END PRO ##

	static function processSingleVariationTitle( $title, $variation_attributes ) {

    	$title = trim( $title );
    	if ( ! is_array( $variation_attributes ) ) return $title;

    	foreach ( $variation_attributes as $attrib_name => $attrib_value ) {
    		$title .= ' - ' . $attrib_value;
    	}

    	return $title;
	}

	public function prepareListings( $ids, $profile_id = false ) {
		$listings = array();
		$prepared_count = 0;
		$skipped_count  = 0;
		$this->errors   = array();
		$this->warnings = array();

		foreach( $ids as $id ) {
			$listing_id = $this->prepareProductForListing( $id, $profile_id );
			if ( $listing_id ) {
				$listings[] = $listing_id;
				$prepared_count++;
			} else {
				$skipped_count++;
			}
		}

		// build response
		$response = new stdClass();
		// $response->success     = $prepared_count ? true : false;
		$response->success        = true;
		$response->prepared_count = $prepared_count;
		$response->skipped_count  = $skipped_count;
		$response->profile_id     = $profile_id;
		$response->errors         = $this->errors;
		$response->warnings       = $this->warnings;

		return $response;
	}

	public function prepareProductForListing( $post_id, $profile_id = false, $post_content = false, $post_title = false, $parent_id = false, $variation_attributes = null ) {
		global $wpdb;

		WPLE()->logger->info( 'prepareProductForListing #'. $post_id );

		// get wp post record
		$post = get_post( $post_id );
		$post_title   = $post_title ? $post_title : $post->post_title;
		$post_content = $post_content ? $post_content : $post->post_content;

		// skip pending products and drafts
		// if ( $post->post_status != 'publish' ) {
		if ( ! in_array( $post->post_status, get_option( 'wplister_allowed_product_status', array('publish','private') ) ) ) {
			if ( isset( $_REQUEST['action'] ) && $_REQUEST['action'] == 'wpl_prepare_single_listing' ) {
			    $msg = __( 'Skipped product with status', 'wp-lister-for-ebay' ) . ' <em>' . $post->post_status . '</em>: ' . $post_title;
			    WPLE()->logger->info( $msg );
				wple_show_message( $msg, 'warn' );
			}
			$msg = sprintf( __( 'Skipped product %s with status %s.', 'wp-lister-for-ebay' ), $post_id, $post->post_status );
			WPLE()->logger->info( $msg );
			$this->warnings[] = $msg;
			return false;
		}

		// skip duplicates
		if ( $profile_id ) {

			// get profile
			$pm      = new ProfilesModel();
			$profile = $pm->getItem( $profile_id );

			// check if this product already exists in profile account
			if ( WPLE_ListingQueryHelper::productExistsInAccount( $post_id, $profile['account_id'] ) ) {
			    $msg = sprintf( __( '"%s" already exists in account %s and has been skipped.', 'wp-lister-for-ebay' ), ProductWrapper::getProductTitle( $post_id ), $profile['account_id'] );
			    WPLE()->logger->info( $msg );
				$this->warnings[] = $msg;
				return false;
			}
		}

		// skip non-existing products
		$product = ProductWrapper::getProduct( $post_id );
		if ( ! $product || ! $product->exists() ) {
		    $msg = "Product $post_id could not be found.";
			$this->errors[] = $msg;
			WPLE()->logger->info( $msg );
			return false;
		}

		// support for qTranslate
		if ( function_exists('qtrans_useCurrentLanguageIfNotFoundUseDefaultLanguage') ) {
			$post_title   = qtrans_useCurrentLanguageIfNotFoundUseDefaultLanguage( $post_title );
			$post_content = qtrans_useCurrentLanguageIfNotFoundUseDefaultLanguage( $post_content );
		}

		// trim title to 255 characters - longer titles will break $wpdb->insert()
		$post_title = strlen( $post_title ) < 255 ? $post_title : self::mb_substr( $post_title, 0, 80 ); // eBay titles can not be longer than 80 characters

		// gather product data
		$variations = $variation_attributes ? serialize($variation_attributes) : '';
		$listing = new Listing();
		$listing
			->setProductId( $post_id )
			->setParentId( $parent_id ?? 0 )
			->setTitle( $post_title )
			->setPrice( ProductWrapper::getPrice( $post_id ) )
			->setLockedStatus( 0 )
			->setStatus( 'selected' );

		if ( $variations ) {
			$listing->setVariations( $variations );
		}

		$result = $listing->save();

		WPLE()->logger->info('insert new auction '.$post_id.' - title: '.$listing->getTitle());
		WPLE()->logger->debug( print_r($post,1) );

		// handle unexpected SQL issues properly
		if ( is_wp_error( $result ) ) {
			WPLE()->logger->info( 'insert_id: '.$wpdb->insert_id );
			WPLE()->logger->info( 'result: ' . print_r($result,1) );
			WPLE()->logger->info( 'sql: '.$wpdb->last_query );
			WPLE()->logger->info( $wpdb->last_error );
			$this->errors[] = sprintf( __( 'Error: MySQL failed to create listing record for "%s" (%s) using profile %s. Please contact support and include this error message.', 'wp-lister-for-ebay' ), ProductWrapper::getProductTitle( $post_id ), $post_id, $profile['profile_id'] );
			return false;
		}

		return $wpdb->insert_id;
	} // prepareProductForListing()

	/**
	 * Reset a listing's state back to Prepared. This is usually used to list an ended or archived listing as a new listing instead of relisting it
	 *
	 * @param int $listing_id
	 * @return bool
	 */
	public function resetListing( $listing_id ) {
		$item = ListingsModel::getItem( $listing_id );

		if ( !$item ) {
			$this->errors[] = sprintf( 'Error resetting listing %d. Listing not found.', $listing_id );
			return false;
		}

		$status = $item['status'];
		if ( ! in_array( $status, array('ended','sold','archived') ) ) {
			$this->errors[] = sprintf( "Item with status <i>%s</i> was skipped. Only ended and sold items can have their status reset to <i>prepared</i>", $status );
			return false;
		}

		if ( ! $item['ebay_id'] ) {
			$this->errors[] = sprintf("Skipped item without an eBay ID (#%d)", $listing_id );
			return false;
		}

		$data = array(
			'status'         => 'prepared',
			'ebay_id'        => NULL,
			'end_date'       => NULL,
			'date_published' => NULL,
			'last_errors'    => '',
		);

		ListingsModel::updateListing( $listing_id, $data );
		$this->reapplyProfileToItem( $listing_id );

		return true;
	}

	static function applyProfilePrice( $product_price, $profile_price = 0 ) {
		if ( $profile_price ) {
			$product_price = self::calculateProfilePrice( $product_price, $profile_price );
		}

		$product_price = apply_filters_deprecated( 'wplister_ebay_price', array($product_price), '2.8.4', 'wple_ebay_price' );
		$product_price = apply_filters( 'wple_ebay_price', floatval( $product_price ) );
		return $product_price;
	}

	static function calculateProfilePrice( $product_price, $profile_price ) {
		WPLE()->logger->debug('calculateProfilePrice(): '.$product_price.' - '.$profile_price );

		# Disable this check to allow empty WC prices and only use profile start prices #40129
		//if ( empty( $product_price ) ) {
		//    WPLE()->logger->debug( 'Skipping empty product price' );
		//   return $product_price;
        //}

		// remove all spaces from profile setting
		$profile_price = str_replace( ' ','', trim($profile_price) );

		// return product price if profile is empty
		if ( $profile_price == '' ) return $product_price;

		// parse percent syntax
		// examples: +10% | -10% | 90%
		if ( preg_match('/([\+\-]?)([0-9\.]+)(\%)/',$profile_price, $matches) ) {
			WPLE()->logger->debug('percent mode');
			WPLE()->logger->debug('matches:' . print_r($matches,1) );

			$modifier      = $matches[1];
			$value         = $matches[2];
			$fullexpr      = $matches[1].$matches[2].$matches[3];
			$profile_price = str_replace( $fullexpr, '', $profile_price ); // remove matched expression from profile price

            if ( $product_price ) {
                // Make sure these are floats #53125
                $product_price = floatval( $product_price );
                $value = floatval( $value );

                if ($modifier == '+') {
                    $product_price = $product_price + ( $product_price * $value/100 );
                } elseif ($modifier == '-') {
                    $product_price = $product_price - ( $product_price * $value/100 );
                } else {
                    $product_price =                  ( $product_price * $value/100 );
                }
            }

		}

		// return product price if profile is empty - or has been emptied
		if ( $profile_price == '' ) return $product_price;


		// parse value syntax
		// examples: +5 | -5 | 5
		if ( preg_match('/([\+\-]?)([0-9\.]+)/',$profile_price, $matches) ) {
			WPLE()->logger->debug('value mode');
			WPLE()->logger->debug('matches:' . print_r($matches,1) );

			$modifier = $matches[1];
			$value = floatval( $matches[2] );
			$product_price = floatval( $product_price );

			if ($modifier == '+') {
				$product_price = $product_price + $value;
			} elseif ($modifier == '-') {
				$product_price = $product_price - $value;
			} else {
				$product_price =                  $value;
			}

		}

		return $product_price;
	} // calculateProfilePrice()

    // Get all changed listings and queue them up for background revision
    static function queueChangedListings() {
        WPLE()->logger->info('Running queueChangedListings');

        if ( ! get_option( 'wplister_background_revisions', 0 ) ) {
            WPLE()->logger->info('Background revisions disabled. Skipping.');
            return;
        }

        $listings = WPLE_ListingQueryHelper::getAllChangedItemsToRevise();

        WPLE()->logger->info(sprintf('Found %d changed listings', count($listings)));

        if ( $listings ) {
            foreach ( $listings as $listing ) {
                wple_async_revise_listing( $listing['id'] );
            }
        }

    }

    static function countQueuedChangedListings() {
        $items = as_get_scheduled_actions(array(
            'hook'      => 'wple_revise_item',
            'status'    => ActionScheduler_Store::STATUS_PENDING,
            'per_page'  => 5000,
        ), 'ids');

        return count( $items );
    }

	/**
	 * @param $profile
	 * @param Listing $listing
	 * @param bool $update_title
	 *
	 * @return void
	 */
	public function applyProfileToListing( $profile, $listing, $update_title = true ) {
		global $wpdb;

		// get item data
		$id 		= $listing->getId();
		$post_id 	= $listing->getProductId();
		$parent_id  = 0;
		$status 	= WPLE_ListingQueryHelper::getStatus( $id );
		$ebay_id 	= $listing->getEbayId();
		$post_title = ProductWrapper::getProductTitle( $post_id );
		$item = ListingsModel::getItem( $listing->getId() ); // backwards compatibility

		WPLE()->logger->info("applyProfileToItem() - listing_id: $id / post_id: $post_id");
		if ( $update_title ) WPLE()->logger->info( 'update_title enabled' );
		// WPLE()->logger->callStack( debug_backtrace() );

		// skip ended auctions - or not, if you want to relist them...
		// if ( $status == 'ended' ) return;

		// use parent title for single (split) variation
		if ( $listing->isSplitVariation() ) {
			WPLE()->logger->debug( 'Working with a single variation product (split)' );
			$parent_id  = $listing->getParentId();
			$post_title = ProductWrapper::getProductTitle( $parent_id );

			WPLE()->logger->debug( 'Parent product title: '. $post_title );
			// check if parent product has a custom eBay title set
			if ( get_post_meta( $parent_id, '_ebay_title', true ) ) {
				$post_title = trim( get_post_meta( $parent_id, '_ebay_title', true ) );
				WPLE()->logger->debug( 'Found title from _ebay_title: '. $post_title );
			}

			// get variations
			$variations = ProductWrapper::getListingVariations( $parent_id );

			// find this variation in all variations of this parent
			foreach ($variations as $var) {
				WPLE()->logger->debug( 'Working on variation #'. $var['post_id'] );
				###
				# Matching if variation attributes can now be enabled in the Advanced Settings page
				# for those who are having issues with split listing titles
				###

				/**
				 * Reverted 0fe90490 because it's causing issues in generating split variation titles for others
				 */
				### Compare the variations instead of the Post ID to also match variable products with no default variation selected #26135
				//WPLE()->logger->info( 'comparing attributes' );
				if ( apply_filters( 'wple_split_variation_match_variation_attributes', true ) === false ) {
					$match = $var['post_id'] == $post_id;
				} else {
					$match = $var['post_id'] == $post_id && serialize( $var['variation_attributes'] ) == serialize( $listing->getVariations() );
				}

				if ( $match ) {
					WPLE()->logger->info( 'match found for '. serialize( $listing->getVariations() ) .': '. serialize( $var['variation_attributes'] ) );
					//if ( serialize( $var['variation_attributes'] ) == $item['variations'] ) {
					//if ( $var['post_id'] == $post_id ) {
					// append attribute values to title
					$post_title = self::processSingleVariationTitle( $post_title, $var['variation_attributes'] );
					$post_title = apply_filters( 'wple_process_single_variation_title', $post_title, $item, $var );
					WPLE()->logger->info( 'processed title: '. $post_title );
					break;
				}
			}

		}

		// gather profile data
		$data = array();
		$data['profile_id'] 		= $profile['profile_id'];
		$data['account_id'] 		= $profile['account_id'];
		$data['site_id'] 			= $profile['site_id'];
		$data['auction_type'] 		= $profile['type'];
		$data['listing_duration'] 	= $profile['listing_duration'];
		$data['template'] 			= $profile['details']['template'];
		$data['quantity'] 			= $profile['details']['quantity'];
		$data['date_created'] 		= gmdate( 'Y-m-d H:i:s' );
		$data['profile_data'] 		= self::encodeObject( $profile );
		// echo "<pre>";print_r($data);echo"</pre>";die();

		// add prefix and suffix to product title
		if ( $update_title ) {
			if ( function_exists( 'qtranxf_use' ) ) {
				$lang = WPLE_eBayAccount::getAccountLocale( $data['account_id'] );
				$post_title = qtranxf_use( $lang, $post_title );
			}

			// append space to prefix, prepend space to suffix
			// TODO: make this an option
			$title_prefix = trim( $profile['details']['title_prefix'] ) . ' ';
			$title_suffix = ' ' . trim( $profile['details']['title_suffix'] );

			// custom post meta fields override profile values
			if ( get_post_meta( $post_id, 'ebay_title_prefix', true ) ) {
				$title_prefix = trim( get_post_meta( $post_id, 'ebay_title_prefix', true ) ) . ' ';
			}
			if ( get_post_meta( $post_id, 'ebay_title_suffix', true ) ) {
				$title_suffix = ' ' . trim( get_post_meta( $post_id, 'ebay_title_suffix', true ) );
			}

			$data['auction_title'] = trim( $title_prefix . $post_title . $title_suffix );
			WPLE()->logger->info( 'New auction_title: '. $data['auction_title'] );

			// custom post meta title override
			if ( get_post_meta( $post_id, '_ebay_title', true ) ) {
				$data['auction_title']  = trim( $title_prefix . get_post_meta( $post_id, '_ebay_title', true ) . $title_suffix );
			} elseif ( get_post_meta( $post_id, 'ebay_title', true ) ) {
				$data['auction_title']  = trim( $title_prefix . get_post_meta( $post_id, 'ebay_title', true ) . $title_suffix );
			}


			// process attribute shortcodes in title - like [[attribute_Brand]]
			if ( strpos( $data['auction_title'], ']]' ) > 0 ) {
				$templatesModel = new TemplatesModel();
				WPLE()->logger->info('auction_title before processing: '.$data['auction_title']);
				$data['auction_title'] = $templatesModel->processAllTextShortcodes( $listing->getProductId(), $data['auction_title'], 80 );
			}
			WPLE()->logger->info('auction_title after processing : '.$data['auction_title']);

			// trim title to 255 characters - longer titles will break $wpdb->update()
			if ( strlen( $data['auction_title'] ) > 255 ) {
				$data['auction_title'] = self::mb_substr( $data['auction_title'], 0, 80 ); // eBay titles can not be longer than 80 characters
			}

			$data['auction_title'] = apply_filters_deprecated( 'wplister_apply_profile_auction_title', array($data['auction_title'], $listing, $profile), '2.8.4', 'wple_apply_profile_auction_title' );
			$data['auction_title'] = apply_filters( 'wple_apply_profile_auction_title', $data['auction_title'], $listing, $profile );

		}

		// apply profile price

		// Support for AELIA Currency Switcher - use the profile's currency value #40393
		if ( $profile['details']['currency'] ) {
			$profile_currency = $profile['details']['currency'];

			add_filter('wc_aelia_cs_selected_currency', function( $currency ) use ( $profile_currency ) {
				return $profile_currency;
			}, 0);
		}

		$data['price'] = ProductWrapper::getPrice( $post_id );
		$data['price'] = self::applyProfilePrice( $data['price'], $profile['details']['start_price'] );

		// fetch product stock if no quantity set in profile - and apply max_quantity limit
		if ( intval( $data['quantity'] ) == 0 ) {
			$max = ( isset( $profile['details']['max_quantity'] ) && intval( $profile['details']['max_quantity'] )  > 0 ) ? $profile['details']['max_quantity'] : PHP_INT_MAX ;
			$data['quantity'] = min( $max , intval( ProductWrapper::getStock( $post_id ) ) );

			// update listing quantity properly - using setListingQuantity() which regards current quantity_sold
			self::setListingQuantity( $post_id, $data['quantity'] );
			unset( $data['quantity'] );
		}

		/**
		 * Update cached variable quantities so the Inventory Check tool wouldn't report false issues.
		 *
		 * However, updating the cache will make WPLister think that there is nothing to update on eBay
		 * since the quantity in WC will match what's in the cache. To prevent that, here are some exceptions:
		 *
		 *  # Do not update cache if the update is coming from WPLA (#13006 #13858 #13775)
		 *  # Do not update listing variations when updating from the Edit Product screen (#15470)
		 *  # Do not update when updating from the REST API #21943
		 *  # Do not update when the wplister_set_listing_variations_quantity filter is FALSE
		 */
		$set_var_quantity = apply_filters_deprecated( 'wplister_set_listing_variations_quantity', array(true), '2.8.4', 'wple_set_listing_variations_quantity' );
		$set_var_quantity = apply_filters( 'wple_set_listing_variations_quantity', $set_var_quantity );

		if (
			did_action( 'wpla_inventory_status_changed' ) === 0 &&
			did_action( 'woocommerce_process_product_meta' ) === 0 &&
			did_action( 'woocommerce_rest_insert_product_object' ) === 0 &&
			did_action( 'woocommerce_rest_insert_product_variation_object' ) === 0 &&
			did_action( 'woocommerce_variation_set_stock' ) === 0 &&
			did_action( 'pmxi_saved_post' ) === 0 &&
			$set_var_quantity
		) {
			self::setListingVariationsQuantity( $post_id, $listing );
		}

		// default new status is 'prepared'
		$data['status'] = 'prepared';

		// except for already published items where it is 'changed'
		if ( intval($ebay_id) > 0 ) 		$data['status'] = 'changed';

		// ended items stay 'ended' and sold items stay sold
		if ( $status == 'ended' ) 			$data['status'] = 'ended';
		if ( $status == 'sold'  ) 			$data['status'] = 'sold';
		if ( $status == 'archived' ) 		$data['status'] = 'archived';

		// locked items simply keep their status
		if ( $listing->isLocked() ) 			$data['status'] = $status;

		// but if apply_changes_to_all_locked checkbox is ticked, even locked published items will be marked as 'changed'
		if ( $listing->isLocked() && ($status == 'published') && isset($_POST['wpl_e2e_apply_changes_to_all_locked']) )
			$data['status'] = 'changed';

		// and if this item is a split variation and is locked, switch it to changed so it will get revised automatically #15922
		if  ( $listing->isLocked() && $status == 'published' && $parent_id > 0 ) {
			$data['status'] = 'changed';
		}

		// except for selected items which shouldn't be locked in the first place
		if ( $status == 'selected' ) 		$data['status'] = 'prepared';
		// and reselected items which have already been 'ended'
		if ( $status == 'reselected' ) 		$data['status'] = 'ended';
		// and items which have already been 'changed' and now had a new profile applied
		if ( $status == 'changed_profile' ) $data['status'] = 'changed';

		// debug
		if ( $status != $data['status'] ) {
			WPLE()->logger->info('applyProfileToItem('.$id.') old status: '.$status );
			WPLE()->logger->info('applyProfileToItem('.$id.') new status: '.$data['status'] );
		}

		$data = apply_filters( 'wple_apply_profile_to_item_data', $data, $profile, $listing );

		// update auctions table
		$wpdb->update( $this->tablename, $data, array( 'id' => $id ) );

		// WPLE()->logger->info('updating listing ID '.$id);
		WPLE()->logger->debug('Listing update using data: '.print_r($data,1));
		// WPLE()->logger->info('sql: '.$wpdb->last_query);
		// WPLE()->logger->info('error: '.$wpdb->last_error);

		## BEGIN PRO ##
		if ( isset( $profile['details']['variations_mode'] ) && ( $profile['details']['variations_mode'] == 'split' ) ) {
			if ( ProductWrapper::hasVariations( $post_id ) ) {
				$this->splitVariation( $id );
			}
		}
		## END PRO ##
	}

	// applyProfileToItem() received a limited $item array with only id, post_id, locked and status
	public function applyProfileToItem( $profile, $item, $update_title = true ) {
		$listing = new Listing( $item['id'] );

		$this->applyProfileToListing( $profile, $listing, $update_title );
	} // applyProfileToItem()

	public function applyProfileToItems( $profile, $items, $update_title = true ) {

		// apply profile to all items
		$current     = 1;
		$total_count = sizeof($items);
		foreach( $items as $item ) {
			WPLE()->logger->info('applying profile to item '.$item['id'].' ( '.$current.' / '.$total_count.' )');
			$this->applyProfileToItem( $profile, $item, $update_title );
			$current++;
		}

		return $items;
	}


	public function applyProfileToNewListings( $profile, $items = false, $update_title = true ) {

		// get selected items - if no items provided
		if (!$items) $items = WPLE_ListingQueryHelper::getAllSelected();

		$items = $this->applyProfileToItems( $profile, $items, $update_title );

		return $items;
	}

	public function reapplyProfileToItem( $id ) {

		// get item
		if ( !$id ) return;
		$item = self::getItem( $id );
		if ( empty($item) ) return;

		// get profile
		$profilesModel = new ProfilesModel();
        $profile = $profilesModel->getItem( $item['profile_id'] );

        // re-apply profile
        $this->applyProfileToItem( $profile, $item );

	}

	public function reapplyProfileToItems( $ids ) {
		foreach( $ids as $id ) {
			$this->reapplyProfileToItem( $id );
		}
	}

	public function cleanListingPolicies( $listing ) {
	    if ( is_numeric( $listing ) ) {
	        $listing = self::getItem( $listing );
        }

        $post_id = ( $listing['parent_id'] ) ? $listing['parent_id'] : $listing['post_id'];

	    // delete product meta
        delete_post_meta( $post_id, '_ebay_seller_payment_profile_id' );
        delete_post_meta( $post_id, '_ebay_seller_shipping_profile_id' );
        delete_post_meta( $post_id, '_ebay_seller_return_profile_id' );

        $details = ListingsModel::decodeObject( $listing['details'], false, true );

        if ( $details && is_object( $details ) ) {
            $details->SellerProfiles = null;
        }

        $details_str = serialize( $details );

        ListingsModel::updateListing( $listing['id'], array( 'details' => $details_str ) );
    }


} // class ListingsModel
