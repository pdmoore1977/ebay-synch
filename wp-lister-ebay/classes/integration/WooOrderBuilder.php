<?php
/**
 * creates an order in WooCommerce - and optionally a customer in WP
 */

## BEGIN PRO ##

class WPL_WooOrderBuilder {

	var $id;
	var $vat_enabled    = false;
	var $vat_total      = 0;
	var $vat_rates      = array();
	var $shipping_taxes = array();
	var $profile_tax    = array();

    /**
     * Update WooCommerce order from eBay. Used in WC 3.0+ only
     * @param int $id
     * @param int|bool $post_id
     *
     * @return bool
     * @throws WC_Data_Exception
     */
	function updateOrderFromEbayOrder( $id, $post_id = false ) {
		WPLE()->logger->debug( 'updateOrderFromEbayOrder #'.$id );

		// get order details
		$ordersModel = new EbayOrdersModel();
		$item        = $ordersModel->getItem( $id );
		$details     = $item['details'];
		if ( ! $post_id ) $post_id = $item['post_id'];

		// prevent WooCommerce from sending out notification emails when updating order status
		$this->disableEmailNotifications();

		// prevent WP-Lister from sending CompleteSale request when the status for an already shipped order is set to Completed
		remove_action( 'woocommerce_order_status_completed', array( 'WpLister_Order_MetaBox', 'handle_woocommerce_order_status_update' ), 0 );

        // clear post and object cache before doing anything with the WC_Order object
        // Apparently fixes status updates not sticking in some sites #23910
        clean_post_cache( $post_id );

		// get order
		$order = OrderWrapper::getOrder( $post_id );

		// update order creation date
		$timestamp     = strtotime($item['date_created'].' UTC');
		$post_date     = $ordersModel->convertTimestampToLocalTime( $timestamp );
		$post_date_gmt = date_i18n( 'Y-m-d H:i:s', $timestamp, true );
		// $post_date  = date_i18n( 'Y-m-d H:i:s', strtotime($item['date_created'].' UTC'), false );

		// check if shipping address has changed
		$shipping_details = $details->ShippingAddress;
		$billing_details  = $details->ShippingAddress;
		$new_shipping_address = false;

		if (is_string( $shipping_details ) ) {
		    $shipping_details = $this->getAddressObject();
        }

        if (is_string( $billing_details ) ) {
            $billing_details = $this->getAddressObject();
        }

		// strip out the ABN before comparing
        $order_without_tracking = clone $order;
        $order_without_tracking = $this->removeTrackingFromAddress( $order_without_tracking );
        $street2 = $order_without_tracking->get_shipping_address_2();
        /*if ( ( $shipping_details->Country == 'AU' || $shipping_details->Country == 'NZ' ) && strpos( $street2, 'ABN#' ) !== false ) {
            $street2 = preg_replace( '/ABN#([0-9]+) CODE:PAID/', '', $street2 );
            $street2 = trim( $street2 );
        }*/

		if ( $order->get_shipping_address_1() != stripslashes( $shipping_details->Street1 ) )       $new_shipping_address = true;
		if ( $street2 != stripslashes( $shipping_details->Street2 ) )                               $new_shipping_address = true;
		if ( $order->get_shipping_postcode()  != stripslashes( $shipping_details->PostalCode ) )    $new_shipping_address = true;
		if ( $order->get_billing_address_1()  != stripslashes( $billing_details->Street1 ) )        $new_shipping_address = true;
		if ( $order->get_billing_postcode()   != stripslashes( $billing_details->PostalCode ) )     $new_shipping_address = true;

		// never update shipping address for orders with multi leg shipping enabled
		// if ( $details->IsMultiLegShipping ) $this->processMultiLegShipping( $details, $post_id );
		if ( $details->IsMultiLegShipping ) $new_shipping_address = false;

		// update shipping address if required
        $update_order_address = apply_filters_deprecated( 'wplister_update_ebay_order_address', array(true), '2.8.4', 'wple_update_ebay_order_address' );
        $update_order_address = apply_filters( 'wple_update_ebay_order_address', $update_order_address );
		if ( $new_shipping_address &&  $update_order_address ) {

			// optional fields
            // strip out spaces so WC displays it #14208 #16959
			if ($billing_details->Phone == 'Invalid Request') $billing_details->Phone = '';
			$order->set_billing_phone( str_replace( ' ', '', stripslashes( $billing_details->Phone ) ) );

			if ( get_option( 'wplister_remove_tracking_from_address', 0 ) && apply_filters( 'wple_remove_ebay_tracking', true ) ) {
                // eBay suddenly uses Street1 to inject the ebay:xxx string #35946 #35918
                if ( strpos( $billing_details->Street1, 'ebay:' ) !== false ) {
                    // Move Street2 into Street1 and clear Street2
                    $billing_details->Street1 = $billing_details->Street2;
                    $billing_details->Street2 = '';
                }
                if ( strpos( $shipping_details->Street1, 'ebay:' ) !== false ) {
                    // Move Street2 into Street1 and clear Street2
                    $shipping_details->Street1 = $shipping_details->Street2;
                    $shipping_details->Street2 = '';
                }

                // Remove ebay tracking from Street2 as well #42175
                if ( is_string( $billing_details ) ) {
                    WPLE()->logger->error( 'Unexpected $billing_details datatype for order #'. $id .'('. $post_id .')');
                }
                $billing_details->Street1 = preg_replace( '/ebay[:]?([a-z0-9]{7})/i', '', $billing_details->Street1 );
                $billing_details->Street2 = preg_replace( '/ebay[:]?([a-z0-9]{7})/i', '', $billing_details->Street2 );

                $shipping_details->Street1 = preg_replace( '/ebay[:]?([a-z0-9]{7})/i', '', $shipping_details->Street1 );
                $shipping_details->Street2 = preg_replace( '/ebay[:]?([a-z0-9]{7})/i', '', $shipping_details->Street2 );
            }

			// billing address
			@list( $billing_firstname, $billing_lastname )     = explode( " ", $billing_details->Name, 2 );
			$order->set_billing_first_name( stripslashes( $billing_firstname ) );
			$order->set_billing_last_name( stripslashes( $billing_lastname ) );
			$order->set_billing_company( stripslashes( $billing_details->CompanyName ) );
			$order->set_billing_address_1( stripslashes( $billing_details->Street1 ) );
			$order->set_billing_address_2( stripslashes( $billing_details->Street2 ) );
			$order->set_billing_city( stripslashes( $billing_details->CityName ) );
			$order->set_billing_postcode( stripslashes( $billing_details->PostalCode ) );
			$order->set_billing_country( stripslashes( $billing_details->Country ) );
			$order->set_billing_state( stripslashes( $billing_details->StateOrProvince ) );

			// update shipping address
			@list( $shipping_firstname, $shipping_lastname )   = explode( " ", $shipping_details->Name, 2 );
			$order->set_shipping_first_name( stripslashes( $shipping_firstname ) );
			$order->set_shipping_last_name( stripslashes( $shipping_lastname ) );
			$order->set_shipping_company( stripslashes( $shipping_details->CompanyName ) );
			$order->set_shipping_address_1( stripslashes( $shipping_details->Street1 ) );
			$order->set_shipping_address_2( stripslashes( $shipping_details->Street2 ) );
			$order->set_shipping_city( stripslashes( $shipping_details->CityName ) );
			$order->set_shipping_postcode( stripslashes( $shipping_details->PostalCode ) );
			$order->set_shipping_country( stripslashes( $shipping_details->Country ) );
			$order->set_shipping_state( stripslashes( $shipping_details->StateOrProvince ) );

            $order = $this->handleTrackingAddress( $order, $item );

			$order->save(); // save this immediately #28171

			// add order note
			$history_message = "Order #$post_id shipping address was modified.";
			$history_details = array( 'post_id' => $post_id );
			$ordersModel->addHistory( $item['order_id'], 'update_order', $history_message, $history_details );
		}

		// update _paid_date (mysql time format)
		if ( $details->PaidTime != '' ) {
			$paid_date = $ordersModel->convertTimestampToLocalTime( strtotime( $details->PaidTime ) );
			$order->set_date_paid( $paid_date );
		}

		// handle refunded orders
		WPLE()->logger->info('updateOrderFromeBayOrder: handle_refunds: ' . get_option( 'wplister_handle_ebay_refunds', 1 ) );
		if ( get_option( 'wplister_handle_ebay_refunds', 0 ) > 0 ) {
			$this->handleOrderRefunds( $item, $order );
		}

        // update the WC order with the PayPal transaction ID if available
        $this->processExternalTransactionId( $post_id, $details, $order );

        // update shipment details
        $this->recordShipmentTracking( $post_id, $details, $order );

        // update shipping totals in case PayPal charges a different shipping amount than the eBay order #18515
        if ( ! $details->IsMultiLegShipping ) {
            $this->updateShippingTotal( $post_id, $details, $order );
        }

		// do nothing if order is already marked as completed, refunded, cancelled or failed
		// if ( $order->status == 'completed' ) return $post_id;
		if ( in_array( $order->get_status(), array( 'completed', 'cancelled', 'refunded', 'failed' ) ) ) return $post_id;

        if ( ! apply_filters( 'wple_update_custom_order_status', false ) ) {
            // the above blacklist won't work for custom order statuses created by the WooCommerce Order Status Manager extension
            // a custom order status should be left untouched as it probably serves a custom purpose - so whitelist all values used by WP-Lister:
            if ( ! in_array( $order->get_status(), array( 'pending', 'processing', 'on-hold', 'completed' ) ) ) return $post_id;
        }

		// set $new_order_status to the current order status so no update takes place if it doesn't change
        $new_order_status = $order->get_status();

        // wple_orderbuilder_update_order_status (#48219)
        if ( apply_filters( 'wple_orderbuilder_update_order_status', true ) ) {
            // order status
            if ( ( $item['eBayPaymentStatus'] == 'PayPalPaymentInProcess' ) || ( $details->PaidTime == '' ) ) {
                $new_order_status = get_option( 'wplister_unpaid_order_status', 'on-hold' );
            } elseif ( ( $item['CompleteStatus'] == 'Completed' ) && ( $details->ShippedTime != '' ) ) {
                // if order is marked as shipped on eBay, change status to completed
                $new_order_status = get_option( 'wplister_shipped_order_status', 'completed' );
            } elseif ( $item['CompleteStatus'] == 'Completed' ) {
                $new_order_status = get_option( 'wplister_new_order_status', 'processing' );
            } else {
                $new_order_status = 'pending';
            }
        }

        // clear post and object cache before updating the order's status
        // Apparently fixes status updates not sticking in some sites #23910
        clean_post_cache( $post_id );

		// update order status
		if ( $order->get_status() != $new_order_status ) {

			$history_message = "Order #$post_id status was updated from {$order->get_status()} to $new_order_status";
			$history_details = array( 'post_id' => $post_id );
			$ordersModel->addHistory( $item['order_id'], 'update_order', $history_message, $history_details );

			$order->set_status( $new_order_status );

		}

        $order->set_date_created( $post_date );

        //$order = $this->recordFinalValueFees( $order, $details );

		do_action_deprecated( 'wplister_update_order_pre_save', [ $order, $item ], '3.1.4', 'wple_update_order_pre_save' );
        do_action( 'wple_update_order_pre_save', $order, $item );

        $order->save();

        do_action_deprecated( 'wplister_after_update_order', [ $post_id ], '3.1.4', 'wple_after_update_order' );
        do_action( 'wple_after_update_order', $post_id );

		return $post_id;
	} // updateOrderFromEbayOrder()

    /**
     * Handles the adding and removal of the tax code in the order address depending on the country of the seller and buyer
     *
     * > Orders going to a UK address from a non-UK seller need to have eBay's GB tax number in the address (GB 365 6085 76 Code:Paid)
     * > UK orders from a UK seller do not need to display the GB tax number
     * > Orders with an AU address from a non-AU seller need to have the Address ID in the address (ABN <addressID> Code:Paid)
     * > AU orders from AU sellers do not need ABN in the address
     *
     * @param WC_Order $wc_order
     * @param Array $ebay_order
     * @return WC_Order
     */
    public function handleTrackingAddress( $wc_order, $ebay_order ) {
        WPLE()->logger->info( 'handleTrackingAddress for order #'. $wc_order->get_id() );
        $seller_site_id     = $ebay_order['site_id'];
        $details            = $ebay_order['details'];
        $shipping_country   = $wc_order->get_shipping_country();
        $shipping_details   = $details->ShippingAddress;

        WPLE()->logger->info( 'Found seller site ID: '. $seller_site_id );
        WPLE()->logger->info( 'shipping country: '. $shipping_country );

        $wc_order = $this->removeTrackingFromAddress( $wc_order );

        // Check for ABN for AU orders - only add tax code if there's eBay tax collected #56309
        if ( !get_option( 'wplister_remove_tracking_from_address', 0 ) && $this->geteBaySalesTaxTotal( $details ) > 0 ) {
            if ( $seller_site_id != 3 && $shipping_country == 'GB' ) {
                WPLE()->logger->info( 'UK buyer and non-UK seller. Adding GB tax code' );
                // UK buyer
                $wc_order->set_shipping_address_2( $wc_order->get_shipping_address_2() . ' GB 365 6085 76 Code:Paid' );
            } elseif ( $seller_site_id != 15 && ( $shipping_country == 'AU' || $shipping_country == 'NZ' ) && $shipping_details->AddressID ) {
                WPLE()->logger->info( 'AU buyer and non-AU seller. Adding ABN to the address' );
                // AU buyer
                $wc_order->set_shipping_address_2( $wc_order->get_shipping_address_2() . ' ABN#'. $shipping_details->AddressID .' Code:Paid' );
            } elseif ( $shipping_country == 'NO' ) {
                // Look for VOEC tax code and add it to the address if found
                $voec = $this->getVOECCode( $details );

                if ( !empty($voec) ) {
                    WPLE()->logger->info( 'Found VOEC code. Adding to the address' );
                    $row = array_pop( $voec );
                    $wc_order->set_shipping_address_2( $wc_order->get_shipping_address_2() . ' VOEC '. $row['VOEC'] .' Code:Paid' );
                }
            } else {
                // Add IOSS if found
                $ioss = $this->getOrderIOSS( $details );

                if ( !empty( $ioss ) ) {
                    WPLE()->logger->info( 'Found IOSS code. Adding to the address' );
                    $row = array_pop( $ioss );
                    $wc_order->set_shipping_address_2( $wc_order->get_shipping_address_2() . ' VAT PAID: IOSS - '. $row['IOSS'] );
                }
            }
        }

        return $wc_order;
    }

    /**
     * Strip off ABN and other tax IDs from the shipping address
     * @param WC_Order $order
     * @return WC_Order
     */
    public function removeTrackingFromAddress( $order ) {
        $street2 = $order->get_shipping_address_2();

        // remove the GB Tax ID
        $street2 = str_replace( 'GB 365 6085 76 Code:Paid', '', $street2 );

        // strip out the ABN
        $street2 = preg_replace( '/ABN#([0-9]+) CODE:PAID/', '', $street2 );

        // strip out the IOSS
        $street2 = preg_replace( '/VAT PAID: IOSS - ([A-Z0-9]+)/', '', $street2 );

        $street2 = trim( $street2 );
        $order->set_shipping_address_2( $street2 );

        return $order;
    }


    /**
     * Create a WooCommerce order from eBay
     * @param int $id
     *
     * @return int|WP_Error
     * @throws WC_Data_Exception
     */
	function createWooOrderFromEbayOrder( $id ) {
		global $wpdb;

		// get order details
		$ordersModel = new EbayOrdersModel();
		$item        = $ordersModel->getItem( $id );
		$details     = $item['details'];

		$order = wc_create_order();

		$post_id       = $order->get_id();
		$timestamp     = strtotime($item['date_created'].' UTC');
		$post_date     = $ordersModel->convertTimestampToLocalTime( $timestamp );
		$post_date_gmt = date_i18n( 'Y-m-d H:i:s', $timestamp, true );
		// $date_created  = $item['date_created'];
		// $post_date_gmt = date_i18n( 'Y-m-d H:i:s', strtotime($item['date_created'].' UTC'), true );
		// $post_date     = date_i18n( 'Y-m-d H:i:s', strtotime($item['date_created'].' UTC'), false );

		// create order comment
		$order_comment  = '';
		if ( is_callable(array( $details, 'getBuyerCheckoutMessage' ) ) && $details->getBuyerCheckoutMessage() != '' ) {
			$order_comment  = $details->getBuyerCheckoutMessage() . "\n";
		}

		if ( $details->ContainseBayPlusTransaction == true ) {
			$order_comment .= "\n" . __( 'Contains eBay Plus Transaction', 'wp-lister-for-ebay' );
		}

		// Create shop_order post object
        $post_data = apply_filters_deprecated( 'wplister_order_post_data', array( array(
			'post_excerpt'   => stripslashes( $order_comment ),
			'post_date'      => $post_date, //The time post was made.
			'post_date_gmt'  => $post_date_gmt, //The time post was made, in GMT.
		), $id, $item ), '2.8.4', 'wple_order_post_data' );

        $post_data = apply_filters( 'wple_order_post_data', $post_data, $id, $item );

        $order_notes_save_location = get_option( 'wplister_ebay_order_ids_storage', 'notes' );
        $order_note = sprintf( __( 'eBay User ID: %s', 'wp-lister-for-ebay' ), $details->BuyerUserID );
        if ( $details->ShippingDetails->SellingManagerSalesRecordNumber != '' ) {
            $order_note .= "\n" . sprintf( __( 'eBay Sales Record ID: %s', 'wp-lister-for-ebay' ), $details->ShippingDetails->SellingManagerSalesRecordNumber );
        }

        if ( $order_notes_save_location == 'notes' || $order_notes_save_location == 'both' ) {
            $order->add_order_note( $order_note );
        }
        if ( $order_notes_save_location == 'excerpt' || $order_notes_save_location == 'both' ) {
            $post_data['post_excerpt'] = $order_note . "\n" . $post_data['post_excerpt'];
        }

        $order->set_customer_note( $post_data['post_excerpt'] );
		$order->set_date_created( $post_data['post_date'] );
		$order->set_status( 'pending' );

		// Update wp_order_id of order record
		$ordersModel->updateWpOrderID( $id, $post_id );

        // store OrderID to mark order originated on eBay
		$order->update_meta_data( '_ebay_order_id',            $item['order_id'] );
        $order->update_meta_data( '_ebay_extended_order_id',   $details->ExtendedOrderID );
        $order->update_meta_data( '_ebay_user_id',             $details->BuyerUserID );
        $order->update_meta_data( '_ebay_account_id',          $item['account_id'] );
        $order->update_meta_data( '_ebay_site_id',             $item['site_id'] );
        $order->update_meta_data( '_ebay_sales_record_id',     $details->ShippingDetails->SellingManagerSalesRecordNumber );

		// Order Attribution
		$order = $this->addOrderAttributionTracking( $order );

		// Store the eBay Plus data in the postmeta so we don't have to load the whole ebay order in the Orders table #56258
		$containseBayPlus = 0;
        if ( $details->ContainseBayPlusTransaction == true ) {
            $containseBayPlus = 1;
        }
        $order->update_meta_data( '_ebay_contains_ebay_plus_transaction', $containseBayPlus );

		// store eBay user name for account
		$accounts = WPLE()->accounts;
		$account  = isset( $accounts[ $item['account_id'] ] ) ? $accounts[ $item['account_id'] ] : false;
		if ( $account ) $order->update_meta_data( '_ebay_account_name', $account->user_name );

		/* the following code is inspired by woocommerce_process_shop_order_meta() in writepanel-order_data.php */

		// add order key
        $order->set_order_key( 'wc_' . uniqid('order_') );
        $order->set_created_via( 'ebay' );
        $order->set_version( WC_VERSION );

		// update address
		$billing_details = $details->ShippingAddress;
		$shipping_details = $details->ShippingAddress;


		// optional fields
        // strip out spaces so WC displays it #14208 #16959
		if ($billing_details->Phone == 'Invalid Request') $billing_details->Phone = '';
		$order->set_billing_phone( stripslashes( $billing_details->Phone ) );

		// Check that the set_shipping_phone method exists to prevent getting fatal errors on older WC versions #53004
		if ( method_exists( $order, 'set_shipping_phone' ) ) {
		    $order->set_shipping_phone( stripslashes( $billing_details->Phone ) );
        }

		
        if ( get_option( 'wplister_remove_tracking_from_address', 0 ) && apply_filters( 'wple_remove_ebay_tracking', true ) ) {
            // eBay suddenly uses Street1 to inject the ebay:xxx string #35946 #35918
            if ( strpos( $billing_details->Street1, 'ebay:' ) !== false ) {
                // Move Street2 into Street1 and clear Street2
                $billing_details->Street1 = $billing_details->Street2;
                $billing_details->Street2 = '';
            }
            if ( strpos( $shipping_details->Street1, 'ebay:' ) !== false ) {
                // Move Street2 into Street1 and clear Street2
                $shipping_details->Street1 = $shipping_details->Street2;
                $shipping_details->Street2 = '';
            }

            // Remove ebay tracking from Street2 as well #42175
            $billing_details->Street1 = preg_replace( '/ebay[:]?([a-z0-9]{7})/i', '', $billing_details->Street1 );
            $billing_details->Street2 = preg_replace( '/ebay[:]?([a-z0-9]{7})/i', '', $billing_details->Street2 );

            $shipping_details->Street1 = preg_replace( '/ebay[:]?([a-z0-9]{7})/i', '', $shipping_details->Street1 );
            $shipping_details->Street2 = preg_replace( '/ebay[:]?([a-z0-9]{7})/i', '', $shipping_details->Street2 );
        }

		// billing address
		@list( $billing_firstname, $billing_lastname )     = explode( " ", $billing_details->Name, 2 );
		$order->set_billing_first_name( stripslashes( $billing_firstname ) );
		$order->set_billing_last_name( stripslashes( $billing_lastname ) );
		$order->set_billing_company( stripslashes( $billing_details->CompanyName ) );
		$order->set_billing_address_1( stripslashes( $billing_details->Street1 ) );
		$order->set_billing_address_2( stripslashes( $billing_details->Street2 ) );
		$order->set_billing_city( stripslashes( $billing_details->CityName ) );
		$order->set_billing_postcode( stripslashes( $billing_details->PostalCode ) );
		$order->set_billing_country( stripslashes( $billing_details->Country ) );
		$order->set_billing_state( stripslashes( $billing_details->StateOrProvince ) );

		// shipping address
		@list( $shipping_firstname, $shipping_lastname )   = explode( " ", $shipping_details->Name, 2 );
		$order->set_shipping_first_name( stripslashes( $shipping_firstname ) );
		$order->set_shipping_last_name( stripslashes( $shipping_lastname ) );
		$order->set_shipping_company( stripslashes( $shipping_details->CompanyName ) );
		$order->set_shipping_address_1( stripslashes( $shipping_details->Street1 ) );
		$order->set_shipping_address_2( stripslashes( $shipping_details->Street2 ) );
		$order->set_shipping_city( stripslashes( $shipping_details->CityName ) );
		$order->set_shipping_postcode( stripslashes( $shipping_details->PostalCode ) );
		$order->set_shipping_country( stripslashes( $shipping_details->Country ) );
		$order->set_shipping_state( stripslashes( $shipping_details->StateOrProvince ) );

		$order = $this->handleTrackingAddress( $order, $item );

		// order details
        // email address - if enabled
        if ( ! get_option('wplister_create_orders_without_email') && is_email( $item['buyer_email'] ) ) {
            $order->set_billing_email( $item['buyer_email'] );
        }

        // Add billing and shipping address index so order becomes searchable #28767
        $order->update_meta_data( '_billing_address_index', implode( ' ', $order->get_address( 'billing' ) ) );
        $order->update_meta_data( '_shipping_address_index', implode( ' ', $order->get_address( 'shipping' ) ) );

        $order->set_discount_total( 0 );
        $order->set_shipping_tax( 0 );
        $order->set_customer_id( 0 );
        //$order->set_prices_include_tax( get_option( 'woocommerce_prices_include_tax' ) );


        $order->set_prices_include_tax( $this->getPricesIncludeTax() );

		// convert state names to ISO code
		self::fixCountryStates( $post_id, $order );

		// Order Total
		$order_total = $details->Total->value;

		// update_post_meta( $post_id, '_order_currency', get_woocommerce_currency() );

        $order->set_currency( $details->Total->attributeValues['currencyID'] );
        $order->set_total( $order_total );

		// update shipping
		// update_post_meta( $post_id, '_order_shipping', 			$shipping_total );
		// update_post_meta( $post_id, '_shipping_method', 		stripslashes( $shipping_method )); // TODO: mapping
		// update_post_meta( $post_id, '_shipping_method_title', 	$shipping_title );
		// update_post_meta( $post_id, '_order_shipping', isset($details->ShippingServiceSelected->ShippingServiceCost->value) ? $details->ShippingServiceSelected->ShippingServiceCost->value : '' );


		// Payment method handling
		$pm = new EbayPaymentModel();
		$payment_title  = $pm->getTitleByServiceName( $details->CheckoutStatus->PaymentMethod );
		$payment_method = $details->CheckoutStatus->PaymentMethod;

        /**
         * This field indicates which payment method was used by the German buyer who was offered the 'Pay Upon Invoice' option.
         * This field will only be returned if a German buyer was offered the 'Pay Upon Invoice' option.
         * Otherwise, the buyer's selected payment method is returned in the PaymentMethod field.
         */
		if ( $details->CheckoutStatus->PaymentInstrument && $payment_method != $details->CheckoutStatus->PaymentInstrument ) {
            $payment_method = $payment_method .' ('. $details->CheckoutStatus->PaymentInstrument .')';
        }

		// convert some eBay payment methods to WooCommerce equivalents
		// https://developer.ebay.com/DevZone/flex/docs/Reference/com/ebay/shoppingservice/BuyerPaymentMethodCodeType.html
		if ( $payment_method == 'PayPal' ) 						$payment_method = 'paypal';
		if ( $payment_method == 'COD' ) 						$payment_method = 'cod';
		if ( $payment_method == 'MoneyXferAccepted' ) 			$payment_method = 'bacs';
		if ( $payment_method == 'MoneyXferAcceptedInCheckout' ) $payment_method = 'bacs';

        $payment_gateway = get_option( 'wplister_orders_default_payment_method', '' );
        $payment_method_title = get_option( 'wplister_orders_default_payment_title', 'Other' );

        if ( !empty( $payment_gateway ) ) {
            if ( $payment_gateway == 'other' ) {
                // Custom gateway
                $payment_method = $payment_method_title;
                $payment_title = $payment_method_title;
            } else {
                $all_methods = WC()->payment_gateways()->payment_gateways();
                $payment_method = $payment_gateway;
                $payment_title = $payment_gateway;

                if ( isset( $all_methods[ $payment_gateway ] ) ) {
                    $payment_title = $all_methods[ $payment_gateway ]->title;
                }
            }
        }

		$order->set_payment_method( apply_filters( 'wple_order_payment_method', $payment_method, $item, $details ) );
		$order->set_payment_method_title( apply_filters( 'wple_order_payment_method_title', $payment_title, $item, $details ) );

		// update _paid_date (mysql time format)
		if ( $details->PaidTime != '' ) {
			$paid_date = $ordersModel->convertTimestampToLocalTime( strtotime( $details->PaidTime ) );
			$order->set_date_paid( $paid_date );
		}

		$this->processExternalTransactionId( $post_id, $details, $order );

		// Tax rows (WC 1.x)
		// $order_taxes = array();
		// [...]
		// update_post_meta( $post_id, '_order_taxes', $order_taxes );

        // Other fees included in the order that need to be added as line items
        $this->processAdditionalFees( $details, $post_id, $order );

		// Order line item(s)
		$this->processOrderLineItems( $details, $post_id, $order );

		// shipping info
		$this->processOrderShipping( $post_id, $item, $order );

        // process sales tax
        $this->processSalesTax( $post_id, $item, $details, $order );
        
		// process tax
		$this->processOrderVAT( $post_id, $item, $order );

		// process orders which use Global Shipping Program
		$this->processMultiLegShipping( $details, $post_id, $order );


		// prevent WooCommerce from sending out notification emails when updating order status or creating customers
		$this->disableEmailNotifications();

		// prevent WP-Lister from sending CompleteSale request when the status for an already shipped order is set to Completed
		remove_action( 'woocommerce_order_status_completed', array( 'WpLister_Order_MetaBox', 'handle_woocommerce_order_status_update' ), 0 );


		// create customer user - if enabled
		if ( get_option( 'wplister_create_customers' ) ) {
			$user_id = $this->addCustomer( $item['buyer_email'], $details );
			$order->set_customer_id( $user_id );
		}

		// support for WooCommerce Sequential Order Numbers Pro 1.5.6
		if ( isset( $GLOBALS['wc_seq_order_number_pro'] ) && method_exists( $GLOBALS['wc_seq_order_number_pro'], 'set_sequential_order_number' ) )
			$GLOBALS['wc_seq_order_number_pro']->set_sequential_order_number( $post_id );

		// support for WooCommerce Sequential Order Numbers Pro 1.7.0+
		if ( function_exists('wc_seq_order_number_pro') && method_exists( wc_seq_order_number_pro(), 'set_sequential_order_number' ) )
			wc_seq_order_number_pro()->set_sequential_order_number( $post_id );


		// order metadata had been saved, now get it so we can manipulate status
		if ( ( $item['eBayPaymentStatus'] == 'PayPalPaymentInProcess' ) || ( $details->PaidTime == '' ) ) {
			$new_order_status = get_option( 'wplister_unpaid_order_status', 'on-hold' );
		} elseif ( ( $item['CompleteStatus'] == 'Completed' ) && ( $details->ShippedTime != '' ) ) {
			// if order is marked as shipped on eBay, change status to completed
			$new_order_status = get_option( 'wplister_shipped_order_status', 'completed' );
		} elseif ( $item['CompleteStatus'] == 'Completed') {
			$new_order_status = get_option( 'wplister_new_order_status', 'processing' );
		} else {
			$new_order_status = 'pending';
		}

        // clear post and object cache before updating the order's status
        // Apparently fixes status updates not sticking in some sites #23910
        clean_post_cache( $post_id );

		// As of WC 3.5, stocks are getting reduced when an order's status gets update to processing or completed.
        // This tells WC that we're taking care of updating the stocks so they do not get reduced twice!
        if ( get_option( 'wplister_handle_stock' ) == '1' ) {
            // Ensure stock is marked as "reduced" in case payment complete or other stock actions are called.
	        if ( method_exists( $order, 'set_order_stock_reduced' ) ) {
		        $order->set_order_stock_reduced( true );
	        } else {
		        $order->get_data_store()->set_stock_reduced( $post_id, true );
	        }
        }

		$order->set_status( $new_order_status );
		$order->set_date_created( $post_date );

		// fix the completed date for completed orders - which is set to the current time by update_status()
		if ( $new_order_status == 'completed' ) {
		    $order->set_date_completed( $post_date );
		}

		// handle refunded orders
		WPLE()->logger->info('updateOrderFromeBayOrder: handle_refunds: ' . get_option( 'wplister_handle_ebay_refunds', 1 ) );
		if ( get_option( 'wplister_handle_ebay_refunds', 0 ) > 0 ) {
			$this->handleOrderRefunds( $item, $order );
		}

		// Handle sales tax collected by eBay
        if ( get_option( 'wplister_ebay_sales_tax_action', 'ignore' ) == 'remove' ) {
            $total_sales_tax = $this->geteBaySalesTaxTotal( $details );
            $order->set_total( $order->get_total() - $total_sales_tax );
        }

        // Handle IOSS tax collected by eBay
        $ioss_data = $this->getOrderIOSS( $details );

        // Record IOSS to postmeta and order notes
        $this->recordIOSS( $ioss_data, $order );
        WPLE()->logger->info( 'Recorded IOSS' );

        if ( get_option( 'wplister_ebay_ioss_action', 'ignore' ) == 'record' && !empty( $ioss_data ) ) {
            $ioss_total = 0;
            foreach ( $ioss_data as $item_id => $ioss ) {
                $ioss_total += $ioss['amount'];

                $ioss_fee = new WC_Order_Item_Fee();
                $ioss_fee->set_total( $ioss['amount'] );
                $ioss_fee->set_name( 'IOSS ('. $ioss['IOSS'] .') for eBay #'. $item_id );
                //$ioss_fee->save();
                $order->add_item( $ioss_fee );
                WPLE()->logger->info( 'Added IOSS order fee' );
            }

            $order->set_total( $order->get_total() + $ioss_total );

        }

        $order = $this->recordFinalValueFees( $order, $details );

		do_action( 'wplister_create_order_pre_save', $order, $item );
		
        // Save the new order
        WPLE()->logger->info( 'Saving WC_Order' );
        $order->save();
        WPLE()->logger->info( sprintf( 'WC_Order #%s saved', $order->get_id() ) );

        // German Market support for temporary tax reduction #38976
        if ( function_exists( 'german_market_temporary_tax_reduction_checkout_order_processed' ) ) {
            german_market_temporary_tax_reduction_checkout_order_processed( $order->get_id(), null, $order );
        }

        /**
         * Code block stopped working and is now causing a fatal error. Plugin author refused to help.
         */
//        if ( get_option( 'wplister_handle_stock' ) == '1' ) {
//            // ATUM Product Levels (BOM) support
//            if ( class_exists( '\AtumLevels\Inc\Hooks' ) ) {
//                // Perhaps $order->get_items() isn't ready yet since we just executed the save() method. Get a new instance and use the get_items() there instead #40022 #51048
//                $new_order = wc_get_order( $order->get_id() );
//
//                $hooks = AtumLevels\Inc\Hooks::get_instance();
//                $hooks->reduce_bom_stock_order_items( $new_order->get_items(), 1 );
//            }
//        }

		// allow other developers to post-process orders created by WP-Lister
		// if you hook into this, please check if get_product() actually returns a valid product object
		// WP-Lister might create order line items which do not exist in WooCommerce!
		//
		// bad code looks like this:
		// $product = get_product( $item['product_id'] );
		// echo $product->get_sku();
		//
		// good code should look like this:
		// $_product = $order->get_product_from_item( $item );
		// if ( $_product->exists() ) { ... };

		do_action_deprecated( 'wplister_after_create_order_with_nonexisting_items', [$post_id], '3.1.4', 'wple_after_create_order_with_nonexisting_items' );
		do_action( 'wple_after_create_order_with_nonexisting_items', $post_id );

		do_action_deprecated( 'wplister_after_create_order', [$post_id], '3.1.4', 'wple_after_create_order' ); // deprecated, but still used by WooCommerce Cost Of Goods 1.7.4
		do_action( 'wple_after_create_order', $post_id );

		// trigger WooCommerce webhook order.created - by simulating an incoming WC REST API request
		do_action( 'woocommerce_api_create_order', $post_id, array(), $order );

		WPLE()->logger->info( sprintf( 'createWooOrderFromEbayOrder finished. Order #%s', $post_id ) );

		return $post_id;

	} // createWooOrderFromEbayOrder()

	/**
	 * @param WC_Order $order
	 *
	 * @return WC_Order
	 */
	private function addOrderAttributionTracking( $order ) {
		if ( $utm_source = get_option( 'wplister_order_utm_source', 'eBay' ) ) {
			$order->update_meta_data( '_wc_order_attribution_source_type', 'utm' );
			$order->update_meta_data( '_wc_order_attribution_utm_source', $utm_source );
		}

		if ( $utm_campaign = get_option( 'wplister_order_utm_campaign' ) ) {
			$order->update_meta_data( '_wc_order_attribution_utm_campaign', $utm_campaign );
		}

		if ( $utm_medium = get_option( 'wplister_order_utm_medium', 'WP-Lister' ) ) {
			$order->update_meta_data( '_wc_order_attribution_utm_medium', $utm_medium );
		}

		return $order;
	}

    /**
     * convert country state names to ISO code (New South Wales -> NSW)
     * @param int $post_id
     * @param WC_Order $order
     */
	function fixCountryStates( $post_id, &$order = null ) {
		if ( ! class_exists('WC_Countries') ) return; // requires WC2.3+

        $billing_country_code = $order->get_billing_country();
        $billing_state_name   = $order->get_billing_state();


		$country_states       = WC()->countries->get_states( $billing_country_code );
		if ( $country_states && $state_code = array_search( $billing_state_name, $country_states ) ) {
            $order->set_billing_state( $state_code );
		}

        $shipping_country_code  = $order->get_shipping_country();
        $shipping_state_name    = $order->get_shipping_state();

		$country_states        = WC()->countries->get_states( $shipping_country_code );
		if ( $country_states && $state_code = array_search( $shipping_state_name, $country_states ) ) {
            $order->set_shipping_state( $state_code );
		}

	} // fixCountryStates()


	/**
     * process shipping info - create shipping line item
     * @param int $post_id
     * @param array $item
     * @param WC_Order $order
     */
	function processOrderShipping( $post_id, $item, &$order = null ) {
	    WPLE()->logger->info( 'processOrderShipping #'.  $post_id );
        $details = $item['details'];

        // Skip recording the shipping line item if order is GSP and WPLE is set to ignore shipping fee #52850
        if ( $details->IsMultiLegShipping && get_option( 'wplister_process_multileg_orders', 0 ) == 1 ) {
            WPLE()->logger->info( 'Global shipping: setting shipping_total to 0' );
            $shipping_total = 0;
        } else {
            // shipping fee (gross)
            $shipping_total = $this->getShippingTotal( $item );
            WPLE()->logger->info( 'getShippingTotal: '. $shipping_total );
        }

		// get shipping method title
		$sm = new EbayShippingModel();
		$shipping_method = $this->getShippingMethod( $item );
		$shipping_title  = $sm->getTitleByServiceName( $shipping_method );

		WPLE()->logger->info( 'getShippingMethod: '. $shipping_method );
        WPLE()->logger->info( 'getTitleByServiceName: '. $shipping_title );

		// calculate shipping tax amount - and adjust shipping total
		$shipping_tax_amount = 0;
		//if ( $this->vat_enabled ) {
            $vat_percent         = get_option( 'wplister_orders_fixed_vat_rate' );
			$shipping_tax_amount = $this->calculateShippingTaxAmount( $shipping_total, $post_id, $order );
            //$shipping_tax_amount = $vat_percent ? $shipping_tax_amount : 0; // disable VAT if no percentage set
			$shipping_total      = $shipping_total - $shipping_tax_amount;
		//}

		// update shipping total (net - after substracting taxes)
        if ( $shipping_total > 0 ) {
            $order->set_shipping_total( $shipping_total );
            WPLE()->logger->info( 'order shipping total set to '. $shipping_total );
        }

		// shipping method
		//$details = $item['details'];
		$shipping_method_id_map    = apply_filters_deprecated( 'wplister_shipping_service_id_map', array(array()), '2.8.4', 'wple_shipping_service_id_map' );
		$shipping_method_id_map    = apply_filters( 'wple_shipping_service_id_map', $shipping_method_id_map );
		$shipping_method_id        = array_key_exists($shipping_method, $shipping_method_id_map) ? $shipping_method_id_map[$shipping_method] : $shipping_method;
		$shipping_method_title_map = apply_filters_deprecated( 'wplister_shipping_service_title_map', array(array()), '2.8.4', 'wple_shipping_service_title_map' );
		$shipping_method_title_map = apply_filters( 'wple_shipping_service_title_map', $shipping_method_title_map );
		$shipping_method_title     = array_key_exists($shipping_method, $shipping_method_title_map) ? $shipping_method_title_map[$shipping_method] : $shipping_title;

        // Added for #40816
        $shipping_method_title = apply_filters_deprecated( 'wplister_shipping_method_title', array( $shipping_method_title, $post_id, $item, $order ), '2.8.4', 'wple_shipping_method_title' );
        $shipping_method_title = apply_filters( 'wple_shipping_method_title', $shipping_method_title, $post_id, $item, $order );

        WPLE()->logger->info( 'shipping_method_id after filters: '. $shipping_method_id );
        WPLE()->logger->info( 'shipping_method_title after filters: '. $shipping_method_title );

        // create shipping info as order line items - WC2.2
        $shipping_taxes = $this->shipping_taxes;
        $shipping_taxes['total'] = $shipping_taxes;
        $method_id = $shipping_total == 0 ? 'free_shipping' : $shipping_method_id;

        if ( $order ) {
            $line = new WC_Order_Item_Shipping();
            $line->set_method_title( $shipping_method_title );
            $line->set_total( $shipping_total );
            $line->set_method_id( $shipping_method_id );
            $line->set_taxes( $shipping_taxes );

            WPLE()->logger->info( 'Adding shipping line item: '. print_r( $line, 1 ) );

            $order->add_item( apply_filters( 'wple_wc_shipping_line_item', $line, $item, $order ) );
            WPLE()->logger->info( 'line item added' );
        } else {
            $item_id = wc_add_order_item( $post_id, array(
                'order_item_name' 		=> $shipping_method_title,
                'order_item_type' 		=> 'shipping'
            ) );

            if ( $item_id ) {
                wc_add_order_item_meta( $item_id, 'cost', 		$shipping_total );
                wc_add_order_item_meta( $item_id, 'method_id', $shipping_method_id );
                wc_add_order_item_meta( $item_id, 'taxes', 	$shipping_taxes );
            }
        }

        // Record the shipment's HandleByTime value
        if ( $details->TransactionArray[0]->ShippingServiceSelected->ShippingPackageInfo[0]->HandleByTime ) {
            $date = new DateTime( $details->TransactionArray[0]->ShippingServiceSelected->ShippingPackageInfo[0]->HandleByTime );
            $order->add_meta_data( '_ebay_handle_by_time', $date->format( 'Y-m-d H:i:s' ) );
        }

		// filter usage:
		// add_filter( 'wplister_shipping_service_title_map', 'my_amazon_shipping_service_title_map' );
		// function my_amazon_shipping_service_title_map( $map ) {
		// 	$map = array_merge( $map, array(
		// 		'Std DE Dom' => 'DHL Paket'
		// 	));
		// 	return $map;
		// }
		// add_filter( 'wplister_shipping_service_id_map', 'my_amazon_shipping_service_id_map' );
		// function my_amazon_shipping_service_id_map( $map ) {
		// 	$map = array_merge( $map, array(
		// 		'Std DE Dom' => 'flat_rate'
		// 	));
		// 	return $map;
		// }

	} // processOrderShipping()

    /**
     * @deprecated Sales Taxes are now being processed in the self::createOrderLineItem() method
     *
     * @param int $post_id
     * @param array $item
     * @param stdClass $details
     * @param WC_Order $order
     */
	function processSalesTax( $post_id, $item, $details, &$order = null ) {
		global $wpdb;

		WPLE()->logger->info( 'processSalesTax disabled. Sales tax now being added at line item level' );
        return;

		// Get the sales tax amount from eBay
        $amount = $this->getSalesTaxTotal( $details, $order->get_total() );

        if ( $amount ) {
            $this->vat_enabled  = true;
        }

        $tax_rate_id = get_option( 'wplister_process_order_sales_tax_rate_id' );

        $this->addOrderLineTax( $post_id, $tax_rate_id, $amount, 0, $order );

        // Record the sales tax in self::vat_total so it gets stored in the order #41169
        WPLE()->logger->info('Added sales tax to vat_total');
        $this->vat_total += $amount;
        $this->vat_enabled = true;

	} // processSalesTax()



	// calculate shipping tax amount based on global VAT rate
	// (VAT is usually applied to shipping fee)
	function calculateShippingTaxAmount( $shipping_total, $post_id, &$order = null ) {
	    WPLE()->logger->info( 'calculateShippingTaxAmount( '. $shipping_total .', '. $post_id .')' );

		// get global VAT rate
		$vat_percent        = get_option( 'wplister_orders_fixed_vat_rate' );
        $autodetect_taxes   = get_option( 'wplister_orders_autodetect_tax_rates', 0 );
        $shipping_tax_amount= 0;
        $shipping_taxes     = array();

		if ( !$autodetect_taxes && !$vat_percent ) {
		    if ( !empty( $this->profile_tax ) ) {
		        // apply profile tax to shipping
                $tax_rate_id = $this->profile_tax['rate_id'];
                $vat_percent = $this->profile_tax['rate_percent'];

                $shipping_tax       = $shipping_total / ( 1 + ( 1 / ( $vat_percent / 100 ) ) );	// calc VAT from gross amount
                $shipping_taxes     = $shipping_tax == 0 ? array() : array( $tax_rate_id => $shipping_tax );

                // Allow 3rd-party plugins to modify or remove entirely the shipping taxes #38926
                $shipping_taxes = apply_filters_deprecated( 'wplister_order_shipping_taxes', array( $shipping_taxes, $shipping_total, $post_id, $order ), '2.8.4', 'wple_order_shipping_taxes' );
                $shipping_taxes = apply_filters( 'wple_order_shipping_taxes', $shipping_taxes, $shipping_total, $post_id, $order );

                if ( !empty( $shipping_taxes ) ) {
                    $this->shipping_taxes = $shipping_taxes;
                    $shipping_tax_amount = array_sum( $shipping_taxes );
                }

                return $shipping_tax_amount;
            } else {
                return 0;
            }
        }

        if ( !$autodetect_taxes ) {
            // calculate VAT
            $tax_rate_id        = get_option( 'wplister_process_order_tax_rate_id' );
            $shipping_tax       = $shipping_total / ( 1 + ( 1 / ( $vat_percent / 100 ) ) );	// calc VAT from gross amount
            $shipping_taxes     = $shipping_tax == 0 ? array() : array( $tax_rate_id => $shipping_tax );

            if ( $shipping_tax ) {
                $this->vat_enabled  = true;
            }
        } else {
		    if ( !$order ) {
                $order = wc_get_order( $post_id );
            }

            // get the order tax location
            $location = $this->get_tax_location( $order );

		    // Handle multiple tax classes #45067
		    if ( 'inherit' == get_option( 'woocommerce_shipping_tax_class' ) ) {
		        WPLE()->logger->info( 'inherit shipping tax class' );
                // loop through the order and find the highest tax class rate

                //find out the max tax rate for all purchases items
                $max_item_tax_rate = 0;
                $max_item_tax_id = false;

                foreach ( $order->get_items() as $item ) {
                    //get item tax_class
                    $location['tax_class'] = $item->get_tax_class();

                    //find item tax rate
                    $matched_tax_rates = WC_Tax::find_shipping_rates($location);
                    WPLE()->logger->info( 'Found rates for item: '. print_r( $matched_tax_rates, 1 ) );

                    //update max tax rate
                    foreach ( $matched_tax_rates as $id => $rate ) {
                        if ($rate['rate'] > $max_item_tax_rate) {
                            $max_item_tax_id = $id;
                            $max_item_tax_rate = $rate['rate'];
                        }
                    }
                }

                WPLE()->logger->info( 'Found max_item_tax_rate: '. $max_item_tax_rate );
                WPLE()->logger->info( 'Found max_item_tax_id: '. $max_item_tax_id );

                // get the gross shipping fee (without VAT) and the applied VAT amount
                $tax_rate = (100 + $max_item_tax_rate) / 100;
                $new_shipping_total = $shipping_total / $tax_rate;
                $shipping_tax = $shipping_total - $new_shipping_total;

                // set shipping tax
                if ($max_item_tax_id !== false) {
                    $shipping_taxes[ $max_item_tax_id ] = $shipping_tax;
                }
            } else {
                $matched_tax_rates  = WC_Tax::find_shipping_rates( $location );

                $shipping_taxes = array();
                $new_shipping_total = false;
                WPLE()->logger->info('Matched rates: '. print_r($matched_tax_rates, true));
                foreach ( $matched_tax_rates as $key => $rate ) {
                    if ( $rate['shipping'] != 'yes' ) {
                        continue;
                    }

                    $this->vat_enabled = true;

                    // get the gross shipping fee (without VAT) and the applied VAT amount
                    $tax_rate = (100 + $rate['rate']) / 100;
                    $new_shipping_total = $shipping_total / $tax_rate;
                    $shipping_tax = $shipping_total - $new_shipping_total;


                    // Add rate
                    if ( ! isset( $shipping_taxes[ $key ] ) )
                        $shipping_taxes[ $key ] = $shipping_tax;
                    else
                        $shipping_taxes[ $key ] += $shipping_tax;

                    // Recording shipping taxes in the vat_rates array duplicates the value in the order totals
                    //if ( ! isset( $this->vat_rates[ $key ] ) )
                    //    $this->vat_rates[ $key ] = $shipping_tax;
                    //else
                    //    $this->vat_rates[ $key ] += $shipping_tax;

                }
            }
        }

        WPLE()->logger->info( 'shipping_taxes: '. print_r( $shipping_taxes, 1 ) );

        // Allow 3rd-party plugins to modify or remove entirely the shipping taxes #38926
        $shipping_taxes = apply_filters_deprecated( 'wplister_order_shipping_taxes', array( $shipping_taxes, $shipping_total, $post_id, $order ), '2.8.4', 'wple_order_shipping_taxes' );
        $shipping_taxes = apply_filters( 'wple_order_shipping_taxes', $shipping_taxes, $shipping_total, $post_id, $order );

        if ( !empty( $shipping_taxes ) ) {
            $this->shipping_taxes = $shipping_taxes;
            $shipping_tax_amount = array_sum( $shipping_taxes );
        }

		return $shipping_tax_amount;
	}

    /**
     * @param int $post_id
     * @param array $item
     * @param WC_Order $order
     */
	function processOrderVAT( $post_id, $item, &$order = null ) {
		global $wpdb;

		WPLE()->logger->info( 'processOrderVAT() #'. $post_id );
		WPLE()->logger->debug( print_r( $item, true ) );

		if ( ! $this->vat_enabled ) {
		    WPLE()->logger->info( 'vat_enabled is false' );
		    return;
        }

		$tax_rate_id        = get_option( 'wplister_process_order_tax_rate_id' );
		$autodetect_taxes   = get_option( 'wplister_orders_autodetect_tax_rates', 0 );

		WPLE()->logger->info( '$tax_rate_id: '. $tax_rate_id );
		WPLE()->logger->info( '$autodetect_taxes: '. $autodetect_taxes );

        /*if ( !$autodetect_taxes && !$tax_rate_id ) {
            // don't add VAT if no tax rate set.
            WPLE()->logger->info( 'autodetect_taxes and tax_rate_id are not set. Exiting.' );
            return;
        }*/

		// shipping fee (gross)
		$shipping_total = $this->getShippingTotal( $item );
        WPLE()->logger->info( 'getShippingTotal(): '. $shipping_total );

		// calculate shipping tax (from gross amount)
        $shipping_tax_amount = $this->calculateShippingTaxAmount( $shipping_total, $post_id, $order );
        WPLE()->logger->info( 'calculateShippingTaxAmount: '. $shipping_tax_amount );

		// disabled this since it's already being checked in self::calculateShippingTaxAmount()
        //$shipping_tax_amount = $vat_percent ? $shipping_tax_amount : 0; // disable VAT if no percentage set


        WPLE()->logger->debug( 'vat_rates: ' . print_r( $this->vat_rates, true ) );

        // store shipping taxes separately if vat_rates is empty #17729
        if ( empty( $this->vat_rates ) && !empty( $this->shipping_taxes ) ) {
            foreach ( $this->shipping_taxes as $rate_id => $tax_amount ) {
                $this->addOrderLineTax( $post_id, $rate_id, 0, $tax_amount, $order );
            }
        } else {
            foreach ( $this->vat_rates as $tax_rate_id => $tax_amount ) {
                // Pull the correct shipping tax for the current tax rate
                $shipping_tax = isset( $this->shipping_taxes[ $tax_rate_id ] ) ? $this->shipping_taxes[ $tax_rate_id ] : 0;

                $this->addOrderLineTax( $post_id, $tax_rate_id, $tax_amount, $shipping_tax, $order );
            }
        }

		// store total order tax
        WPLE()->logger->info( 'Storing _order_tax: '. $this->vat_total );
        WPLE()->logger->info( 'Storing _order_shipping_tax: '. $shipping_tax_amount );

        if ( $shipping_tax_amount ) $order->set_shipping_tax( $this->format_decimal( $shipping_tax_amount ) );
        if ( $this->vat_total )     $order->set_cart_tax( $this->format_decimal( $this->vat_total ) );

        // Update: Allow $include_vat_in_order even when $autodetect_taxes is disabled #47269
        // if autodetect taxes is enabled and woocommerce_prices_include_tax is disabled,
        // add the tax total to the order total #15043
        //
        // Added the 'wplister_include_vat_in_order_total' filter to allow external code to prevent VAT from being added to the order total #16294
        $include_vat_in_order_setting = get_option('wplister_ebay_include_vat_in_order_total', '1' );
        $include_vat_in_order = apply_filters_deprecated( 'wplister_include_vat_in_order_total', array( $include_vat_in_order_setting, $post_id, $item ), '2.8.4', 'wple_include_vat_in_order_total' );
        $include_vat_in_order = apply_filters( 'wple_include_vat_in_order_total', $include_vat_in_order, $post_id, $item );
        //if ( $autodetect_taxes && get_option( 'woocommerce_prices_include_tax', 'no' ) == 'no' && $include_vat_in_order ) {

        if ( $this->getPricesIncludeTax() == 'no' && $include_vat_in_order ) {
            $order_total = $order->get_total();
            $order->set_total( $order_total + $this->vat_total );
        }
	} // processOrderVAT()


    /**
     * @param int $post_id
     * @param stdClass $details
     * @param WC_Order $order
     * @deprecated Use WPL_WooOrderBuilder::processExternalTransactionId() instead
     */
	function processPayPalTransactionID( $post_id, $details, &$order = null ) {
	    $this->processExternalTransactionId( $post_id, $details, $order );
	    return;
		if ( ! $details->ExternalTransaction ) return;

		// fetch PayPal transaction ID
		$transaction_id = is_array( $details->ExternalTransaction ) ? $details->ExternalTransaction[0]->ExternalTransactionID : null;

		// alternative way of fetching the PayPal transaction ID
		// if ( $details->MonetaryDetails && is_array( $details->MonetaryDetails->Payments->Payment ) ) {
		// 	$transaction_id = $details->MonetaryDetails->Payments->Payment[0]->ReferenceID->value;
		// }

        $order->set_transaction_id( $transaction_id );

	} // processPayPalTransactionID()

    /**
     * Store the payment's transaction ID into the WC order
     * @param int $post_id
     * @param object $details
     * @param WC_Order $order
     */
    public function processExternalTransactionId( $post_id, $details, &$order ) {
        $transaction_id = false;
         if ( $details->MonetaryDetails && is_array( $details->MonetaryDetails->Payments->Payment ) ) {
         	$transaction_id = $details->MonetaryDetails->Payments->Payment[0]->ReferenceID->value;
         }

         if ( $transaction_id ) {
             $order->set_transaction_id( $transaction_id );
         }
    }


	function createOrderLineItem( $Transaction, $post_id, &$order, $Details ) {
		// get listing item from db
        if ( get_option( 'wplister_match_sales_by_sku', 0 ) == 1) {
            // Get local product from the listing's SKU
            $listing_sku = $Transaction->Item->SKU;

            // Consider variable products where the parent has no SKU
            if ( is_object( @$Transaction->Variation ) && $Transaction->Variation->SKU ) {
                $listing_sku = $Transaction->Variation->SKU;
            }
            $listingItem = WPLE_ListingQueryHelper::findItemBySku( $listing_sku, true );
        } else {
            $listingItem = WPLE_ListingQueryHelper::findItemByEbayID( $Transaction->Item->ItemID );
        }

		WPLE()->logger->info( 'createOrderLineItem for order #'.$post_id );
		// WPLE()->logger->info( 'createOrderLineItem - listingItem: '.print_r($listingItem,1) );
		// WPLE()->logger->info( 'createOrderLineItem - Transaction: '.print_r($Transaction,1) );

		$product_id			= $listingItem ? $listingItem->post_id : '0';
		$wc_product         = ( $product_id ) ? wc_get_product( $product_id ) : false;
		$item_name 			= $listingItem ? $listingItem->auction_title : $Transaction->Item->Title;

		$item_quantity 		= $Transaction->QuantityPurchased;
		$line_subtotal		= $item_quantity * $Transaction->TransactionPrice->value;
		$line_total 		= $item_quantity * $Transaction->TransactionPrice->value;
		$product_price      = $Transaction->TransactionPrice->value;

		// default to no tax
		$line_subtotal_tax	= '0.00';
		$line_tax		 	= '0.00';
		$item_tax_class		= '';
		$tax_rate_id		= ''; // prevent "Notice: Undefined variable"
        $vat_enabled        = false;

        // Record product bundles #47565
        $bundle_id = 0;
        if ( $wc_product && $wc_product->get_type() === 'bundle' && class_exists('WC_PB_Order')) {
            $instance = \WC_PB_Order::instance();
            $bundle_id = $instance->add_bundle_to_order( $wc_product, $order, $item_quantity );

            if (is_wp_error($bundle_id)) {
                $bundle_id = 0;
            }
        }

        $sales_tax_total = 0;
        if ( get_option( 'wplister_ebay_sales_tax_action', 'ignore' ) == 'record' ) {
            $sales_tax_total = $this->getLineSalesTax( $Transaction );
        }

		// if auto-detect is disabled and use profile VAT is enabled
		if ( 0 == get_option( 'wplister_orders_autodetect_tax_rates', 0 ) && 1 == get_option( 'wplister_process_order_vat', 1 ) ) {
			// check if listing has VAT enabled in its profile
			$vat_enabled = $listingItem && $listingItem->profile_data['details']['tax_mode'] == 'fix' ? true : false;
			$taxes = $this->getProductTaxFromProfile( $listingItem, $product_price, $item_quantity );

            if ( $sales_tax_total > 0 ) {
                $taxes = $this->addSalesTaxToLineItemTax( $taxes, $sales_tax_total );
            }

            if ( $taxes['line_tax'] > 0 ) {
                $vat_enabled = true;
            }

            // don't add VAT if no tax rate set
            // (set $vat_enabled to false here here to prevent subtracting tax from line item price - processOrderVAT() will not add VAT without tax_rate_id!)
            if ( ! get_option( 'wplister_process_order_tax_rate_id' ) ) $vat_enabled = false;
		} else {
			$taxes = $this->getProductTax( $product_price, $product_id, $item_quantity, $post_id, $order, $Details );

			if ( $sales_tax_total > 0 ) {
			    $taxes = $this->addSalesTaxToLineItemTax( $taxes, $sales_tax_total );
            }

			if ( $taxes['line_tax'] > 0 ) {
				$vat_enabled = true;
			}
		}

		WPLE()->logger->info( 'Found $taxes: '. print_r( $taxes, 1 ) );
		WPLE()->logger->info( 'vat_enabled: '. ($vat_enabled ? 1 : 0) );

		$vat_enabled = apply_filters( 'wple_order_has_vat_enabled', $vat_enabled, $post_id, $Transaction );

		// process VAT if enabled
		if ( $vat_enabled ) {
			//WPLE()->logger->info( 'VAT%: '. $vat_percent );

			// calculate VAT included in line total
			// $vat_tax = $line_total * $vat_percent / 100; 					// calc VAT from net amount
			//$vat_tax = $line_total / ( 1 + ( 1 / ( $vat_percent / 100 ) ) );	// calc VAT from gross amount
			// WPLE()->logger->info( 'VAT: '.$vat_tax );

	        if ( $taxes['line_subtotal_tax'] ) {
	            $line_subtotal_tax = $taxes['line_subtotal_tax'];
	        }

	        if ( $taxes['line_tax'] ) {
	            $line_tax = $taxes['line_tax'];
	        }

	        if ( $taxes['tax_rate_id'] ) {
	            $tax_rate_id = $taxes['tax_rate_id'];
	        }

	        if ( $taxes['line_total'] ) {
	            $line_total = $taxes['line_total'];
	        }

	        if ( $taxes['line_subtotal'] ) {
	            $line_subtotal = $taxes['line_subtotal'];
	        }

			// keep record of total VAT
			$vat_tax = $line_tax;
			$this->vat_enabled = true;
			$this->vat_total  += $vat_tax;

			// and keep track of the used tax rates so we can store them with the order later
			//if ( $tax_rate_id ) {
            //    @$this->vat_rates[ $tax_rate_id ] += $vat_tax;
            //}
            //
            // Use $taxes['line_tax_data'] to store multiple tax rates if available #13585
            if ( is_array( $taxes['line_tax_data']['total'] ) ) {
                foreach ( $taxes['line_tax_data']['total'] as $rate_id => $amount ) {
                    @$this->vat_rates[ $rate_id ] += $amount;
                }
            }

			// $vat_tax = wc_round_tax_total( $vat_tax );
			$vat_tax = $this->format_decimal( $vat_tax );
			WPLE()->logger->info( 'VAT: '.$vat_tax );
			WPLE()->logger->info( 'vat_total: '.$this->vat_total );

			$line_subtotal_tax	= $vat_tax;
			$line_tax		 	= $vat_tax;

			// adjust item price if prices include tax
			// if prices do not include tax, but VAT is enabled, adjust item price as well (the same happens with shipping fee)
			// if ( get_option( 'woocommerce_prices_include_tax' ) == 'yes' ) {
				// $line_total    = $line_total    - $vat_tax;
				// $line_subtotal = $line_subtotal - $vat_tax;
			// }
		}

        // try to get product object to set tax class
        if ( $listingItem && is_object( $listingItem ) ) {
            $wc_product = ProductWrapper::getProduct( $listingItem->post_id );
        }

        // set tax class
        if ( isset( $wc_product ) && is_object($wc_product) ) {
            $item_tax_class		= $wc_product->get_tax_class();
            WPLE()->logger->info( 'found product '. wple_get_product_meta( $wc_product, 'id' ).' - using tax_class: '.$item_tax_class );
        }

        WPLE()->logger->info( 'tax_class: '.$item_tax_class );

		// process sales tax
        $fees = array();

		if ( is_array($Transaction->Taxes->TaxDetails) ) {
			foreach ( $Transaction->Taxes->TaxDetails as $Tax ) {
                if ( ! floatval( $Tax->TaxAmount->value ) ) continue;

			    // Record SalesTax as such but everything else as a custom fee #39950
                $sales_taxes = apply_filters( 'wple_ebay_sales_taxes', array( 'SalesTax' ) );

                if ( in_array( $Tax->Imposition, $sales_taxes ) ) {
                    $line_subtotal_tax	= $Tax->TaxAmount->value;
                    $line_tax		 	= $Tax->TaxAmount->value;
                    $item_tax_class		= '';
                    WPLE()->logger->info( 'SalesTax: '.$Tax->TaxAmount->value );
                } else {
                    $record_tax_as_fee = apply_filters_deprecated( 'wplister_record_tax_details_as_fees', array(false), '2.8.4', 'wple_record_tax_details_as_fees' );
                    $record_tax_as_fee = apply_filters( 'wple_record_tax_details_as_fees', $record_tax_as_fee );
                    if ( $record_tax_as_fee ) {
                        if ( $Tax->TaxAmount->value ) {
                            $label = apply_filters_deprecated( 'wplister_fee_label_'. $Tax->TaxDescription, array($Tax->TaxDescription), '2.8.4', 'wple_fee_label_'. $Tax->TaxDescription );
                            $label = apply_filters( 'wple_fee_label_'. $Tax->TaxDescription, $label );
                            $fees[] = array(
                                'label' => $label,
                                'amount'    => $Tax->TaxAmount->value
                            );
                        }
                    }
                }
			}
		}

		// check if item has variation
		$isVariation        = false;
		$VariationSKU       = false;
		$VariationSpecifics = array();
        if ( is_object( @$Transaction->Variation ) ) {
            foreach ($Transaction->Variation->VariationSpecifics as $spec) {
                $VariationSpecifics[ $spec->Name ] = $spec->Value[0];
            }
			$isVariation  = true;
			$VariationSKU = $Transaction->Variation->SKU;
        }

		// get variation_id
		if ( $isVariation ) {
			$variation_id = ProductWrapper::findVariationID( $product_id, $VariationSpecifics, $VariationSKU );
		}

		// support split variations since variation check above doesn't account for them #
        if ( ! $isVariation && $listingItem && $listingItem->parent_id > 0 ) {
            $product_id = $listingItem->parent_id;
            $variation_id = $listingItem->post_id;
        }

        if ( get_option( 'wplister_use_local_product_name_in_orders', 0 ) && $wc_product ) {
            $item_name = $wc_product->get_name();

            // Use the variation's name if available #57640
            if ( isset( $variation_id ) ) {
                $variation_product = wc_get_product( $variation_id );
                $item_name = $variation_product->get_name();
            }
        }


        $order_item = array();

		$order_item['product_id'] 			= $product_id;
		$order_item['variation_id'] 		= isset( $variation_id ) ? $variation_id : '0';
		$order_item['name'] 				= $item_name;
		// $order_item['tax_class']			= $_product->get_tax_class();
		$order_item['tax_class']			= $item_tax_class;
		$order_item['qty'] 					= $item_quantity;
		$order_item['line_subtotal'] 		= $this->format_decimal( $line_subtotal );
		$order_item['line_subtotal_tax'] 	= $line_subtotal_tax;
		$order_item['line_total'] 			= $this->format_decimal( $line_total );
		$order_item['line_tax'] 			= $line_tax;
		$order_item['line_tax_data'] 		= array(
			//'total' 	=> array( $tax_rate_id => $line_tax ),
			//'subtotal' 	=> array( $tax_rate_id => $line_subtotal_tax ),
            'total'     => $taxes['line_tax_data']['total'],
            'subtotal'  => $taxes['line_tax_data']['subtotal']
		);

        $order_item = apply_filters_deprecated( 'wplister_order_builder_line_item', array($order_item, $post_id, $Transaction), '2.8.4', 'wple_order_builder_line_item' );
        $order_item = apply_filters( 'wple_order_builder_line_item', $order_item, $post_id, $Transaction );

        if ( $order ) {
            $line_item = new WC_Order_Item_Product( $bundle_id );

            if ( $wc_product ) {
                $line_item->set_product( $wc_product );
            }

            $line_item->set_name( $order_item['name'] );
            $line_item->set_order_id( $order->get_id() );
            $line_item->set_quantity( $order_item['qty'] );
            $line_item->set_total( $order_item['line_total'] );
            $line_item->set_subtotal( $order_item['line_subtotal'] );
            $line_item->set_total_tax( floatval( $order_item['line_tax'] ) );
            $line_item->set_subtotal_tax( floatval( $order_item['line_subtotal_tax'] ) );
            $line_item->set_taxes( $order_item['line_tax_data'] );

            if ( isset( $variation_id ) ) {
                try {
                    $line_item->set_variation_id( $variation_id );
                    $line_item->set_variation( $VariationSpecifics );
                } catch ( WC_Data_Exception $exception ) {
                    WPLE()->logger->info( 'Error assigning variation ID: '. $variation_id );
                    WPLE()->logger->info( $exception->getMessage() );
                }

            }

            $item_meta = array();

            // This tells WC that we're taking care of updating the stocks so they do not get reduced twice!
            // This is also used to tell WC to restock this item in case of a refund #31230 #31062 #42685
            if ( get_option( 'wplister_handle_stock' ) == '1' ) {
                $item_meta['_reduced_stock'] = $order_item['qty'];
            }

            // Store the ebay item ID in the line item meta #48765
            if ( is_object( $listingItem ) ) {
                $item_meta['_ebay_id'] = $listingItem->ebay_id;
            }

            // store SKU as order line item meta field
            $ItemSKU = $VariationSKU ? $VariationSKU : $Transaction->Item->SKU;
            $store_sku_as_order_meta = get_option( 'wplister_store_sku_as_order_meta', 1 );
            if ( $ItemSKU && $store_sku_as_order_meta ) {
                $item_meta['SKU'] = $ItemSKU;
            }

            if ( get_option( 'wplister_record_ebay_fee', 'no' ) == 'meta' && is_object( $Transaction->FinalValueFee ) ) {
                $item_meta['eBay Fee'] = $Transaction->FinalValueFee->value;
            }

            $item_meta = apply_filters( 'wple_new_order_item_meta', $item_meta, $line_item, $listingItem, $post_id, $order );

            if ( $item_meta ) {
                foreach ( $item_meta as $key => $value ) {
                    $line_item->update_meta_data( $key, $value );
                }
                $line_item->save_meta_data();
            }

            $line_item = apply_filters( 'wple_new_order_item_product', $line_item, $listingItem, $order_item, $post_id, $order );

            $order->add_item( $line_item );
        } else {
            // Add line item
            $item_id = wc_add_order_item( $post_id, array(
                'order_item_name' 		=> $order_item['name'],
                'order_item_type' 		=> 'line_item'
            ) );

            // Add line item meta
            if ( $item_id ) {
                wc_add_order_item_meta( $item_id, '_qty', 				$order_item['qty'] );
                wc_add_order_item_meta( $item_id, '_tax_class', 		$order_item['tax_class'] );
                wc_add_order_item_meta( $item_id, '_product_id', 		$order_item['product_id'] );
                wc_add_order_item_meta( $item_id, '_variation_id', 	$order_item['variation_id'] );
                wc_add_order_item_meta( $item_id, '_line_subtotal', 	$order_item['line_subtotal'] );
                wc_add_order_item_meta( $item_id, '_line_subtotal_tax',$order_item['line_subtotal_tax'] );
                wc_add_order_item_meta( $item_id, '_line_total', 		$order_item['line_total'] );
                wc_add_order_item_meta( $item_id, '_line_tax', 		$order_item['line_tax'] );
                wc_add_order_item_meta( $item_id, '_line_tax_data', 	$order_item['line_tax_data'] );

                // store SKU as order line item meta field
                $ItemSKU = $VariationSKU ? $VariationSKU : $Transaction->Item->SKU;
                $store_sku_as_order_meta = get_option( 'wplister_store_sku_as_order_meta', 1 );
                if ( $ItemSKU && $store_sku_as_order_meta ) {
                    wc_add_order_item_meta( $item_id, 'SKU', 			$ItemSKU );
                }

                // This tells WC that we're taking care of updating the stocks so they do not get reduced twice!
                // This is also used to tell WC to restock this item in case of a refund #31230 #31062
                if ( get_option( 'wplister_handle_stock' ) == '1' ) {
                    wc_add_order_item_meta( $item_id, '_reduced_stock', $order_item['qty'] );
                }

                // add variation attributes as order item meta (WC2.2)
                if ( $item_id && $isVariation ) {
                    foreach ($VariationSpecifics as $attribute_name => $value) {
                        wc_add_order_item_meta( $item_id, $attribute_name,	$value );
                    }
                }

                do_action( 'wplister_added_order_item_meta', $item_id, $order_item );
            }
        }

        if ( $order && !empty( $fees ) ) {
            foreach ( $fees as $fee ) {
                $line = new WC_Order_Item_Fee();
                $line->set_name( $fee['label'] );
                $line->set_amount( $fee['amount'] );
                $line->set_total( $fee['amount'] );

                /*$item_id = wc_add_order_item( $post_id, array(
                    'order_item_name' 		=> $fee['label'],
                    'order_item_type' 		=> 'fee'
                ) );

                wc_add_order_item_meta( $item_id, '_fee_amount', $fee['amount'] );
                wc_add_order_item_meta( $item_id, '_tax_class', 0 );
                wc_add_order_item_meta( $item_id, '_line_total', $fee['amount'] );
                wc_add_order_item_meta( $item_id, '_line_tax', 0 );*/
                WPLE()->logger->info( 'Added fee to the order: '. $fee['label'] .': '. $fee['amount'] );
            }
        }

		WPLE()->logger->info( 'order item created' );
		WPLE()->logger->debug( 'order item data: '.print_r($order_item,1) );
		WPLE()->logger->info( '***' );

	} // createOrderLineItem()

    /**
     * Record additional order fees
     *
     * @param $details
     * @param int $post_id
     * @param WC_Order $order
     * @return WC_Order
     */
    function processAdditionalFees( $details, $post_id, &$order = null ) {
        // Check for COD Cost #23407
        if ( get_option( 'wplister_record_cod_cost', 0 ) && isset( $details->ShippingDetails->CODCost ) ) {
            $cost = $details->ShippingDetails->CODCost->value;

            $fee = new WC_Order_Item_Fee();
            $fee->set_name( 'COD Fee' );
            $fee->set_amount( floatval( $cost ) );
            $fee->set_total( floatval( $cost ) );

            $order->add_item( $fee );

        }

        // Record eBay fees
        $record_ebay_fees = get_option( 'wplister_record_ebay_fee', 'no' );
        $fvf              = $this->getTotalFinalValueFee( $details );
        if ( $fvf && $record_ebay_fees != 'no' ) {
            if ( $record_ebay_fees == 'fee' ) {
                $fee = new WC_Order_Item_Fee();
                $fee->set_name( __('eBay Fee', 'wp-lister-for-ebay') );
                $fee->set_total( floatval( $fvf ) );
                $fee->set_amount( floatval( $fvf ) );
                $order->add_item( $fee );
            } elseif ( $record_ebay_fees == 'meta' ) {
                $order->update_meta_data( '_ebay_fvf', $fvf );
                $order->update_meta_data( 'eBay Final Value Fee', $fvf ); // also store in a meta field that's visible to the customer #50043
            }
        }

        // Record AdjustmentAmount as an additional fee #32555
        if ( $details->AdjustmentAmount && $details->AdjustmentAmount->value ) {
            $cost = floatval( $details->AdjustmentAmount->value );

            if ( $cost ) {
                $fee = new WC_Order_Item_Fee();
                $fee->set_name( 'Adjustment Amount' );
                $fee->set_total( floatval( $cost ) );
                $fee->set_amount( floatval( $cost ) );

                $order->add_item( $fee );
            }
        }

        // Record Import Charge as an additional fee #50151
        $ShippingServiceSelected = $details->ShippingServiceSelected;
        if ( $ShippingServiceSelected && method_exists($ShippingServiceSelected, 'getImportCharge') ) {
            $charge = $ShippingServiceSelected->getImportCharge();
            $value = is_object( $charge ) ? $charge->value : false;

            if ( $value ) {

                $fee = new WC_Order_Item_Fee();
                $fee->set_name( 'Import Charge' );
                $fee->set_total( floatval( $value ) );
                $fee->set_amount( floatval( $value ) );

                $order->add_item( $fee );

            }
        }
    }

    /**
     * Check the order for eBay fees are record if none is found
     *
     * @param WC_Order $order
     * @param $details
     * @return WC_Order
     */
    public function recordFinalValueFees( $order, $details ) {
        WPLE()->logger->info('recordFinalValueFeeds #'. $order->get_id() );
        $record_ebay_fees = get_option( 'wplister_record_ebay_fee', 'no' );
        $fvf              = $this->getTotalFinalValueFee( $details );

        $fees = $order->get_items('fee');
        $fvf_found = false;

        foreach ( $fees as $fee ) {
            if ( 'eBay Fee' == $fee->get_name() ) {
                $fvf_found = true;
                break;
            }
        }

        if ( !$fvf_found ) {
            // Record eBay fees
            if ( $fvf && $record_ebay_fees != 'no' ) {
                if ( $record_ebay_fees == 'discount' ) {
                    $fee_amount = floatval( $fvf ) * -1; // convert to a negative number
                    $fee = new WC_Order_Item_Fee();
                    $fee->set_name( __('eBay Fee', 'wp-lister-for-ebay') );
                    $fee->set_total( $fee_amount );
                    $fee->set_amount( $fee_amount );
                    $order->add_item( $fee );
                    $order->set_total( $order->get_total() + $fee_amount );
                } elseif ( $record_ebay_fees == 'fee' ) {
                    $fee_amount = floatval( $fvf );
                    $fee = new WC_Order_Item_Fee();
                    $fee->set_name( __('eBay Fee', 'wp-lister-for-ebay') );
                    $fee->set_total( $fee_amount );
                    $fee->set_amount( $fee_amount );
                    $order->add_item( $fee );
                    $order->set_total( $order->get_total() + $fee_amount );
                } elseif ( $record_ebay_fees == 'meta' ) {
                    $order->update_meta_data( '_ebay_fvf', $fvf );
                    $order->update_meta_data( 'eBay Final Value Fee', $fvf ); // also store in a meta field that's visible to the customer #50043
                }
            }
        }

        WPLE()->logger->info( 'recordFinalValueFees() done' );
        return $order;
    }

	function processOrderLineItems( $Details, $post_id, &$order = null) {

		// WC 2.0 only
		if ( ! function_exists('woocommerce_add_order_item_meta') ) return;

		foreach ( $Details->TransactionArray as $Transaction ) {
			$this->createOrderLineItem( $Transaction, $post_id, $order, $Details );
		}

	} // processOrderLineItems()

	function getShippingTotal( $item ) {
		$details     = $item['details'];

		// check selected shipping service
		$shipping_total  = 0;

		$ShippingServiceSelected = $details->ShippingServiceSelected;
		if ( $ShippingServiceSelected && method_exists($ShippingServiceSelected, 'getShippingServiceCost'))
			$ShippingServiceCost = $ShippingServiceSelected->getShippingServiceCost();

		if ( isset( $ShippingServiceCost ) && $ShippingServiceCost->value )
			$shipping_total = $ShippingServiceCost->value;

		$shipping_total = apply_filters_deprecated( 'wplister_ebay_order_shipping_total', array( $shipping_total, $item ), '2.8.4', 'wple_ebay_order_shipping_total' );
		return apply_filters( 'wple_ebay_order_shipping_total', $shipping_total, $item );

	} // getShippingTotal()

	function getShippingMethod( $item ) {
		$details     = $item['details'];

		// check selected shipping service
		$shipping_method = 'N/A';

		$ShippingServiceSelected = $details->ShippingServiceSelected;
		if ( $ShippingServiceSelected && method_exists($ShippingServiceSelected, 'getShippingServiceCost'))
			$ShippingServiceCost = $ShippingServiceSelected->getShippingServiceCost();

		if ( $ShippingServiceSelected && method_exists($ShippingServiceSelected, 'getShippingService'))
			$shipping_method = $ShippingServiceSelected->getShippingService();

		return $shipping_method;
	} // getShippingMethod()

    /**
     * Since 3.1.2, WPLE uses eBay API version 1213 which moves eBay-collected taxes to the eBayCollectAndRemitTaxes container
     * @param $details
     * @return int
     */
    public function geteBaySalesTaxTotal( $details ) {
        WPLE()->logger->info( 'geteBaySalesTaxTotal()' );
        $total = 0;

        foreach ( $details->TransactionArray as $transaction ) {
            /*foreach ( $transaction->Taxes->TaxDetails as $tax ) {
                $sales_taxes = apply_filters( 'wple_ebay_sales_taxes', array( 'SalesTax' ) );
                if ( !in_array( $tax->Imposition, $sales_taxes ) ) {
                    continue;
                }

                $total += $tax->TaxAmount->value;
            }*/

            if ( $transaction->eBayCollectAndRemitTax == 1 ) {
                foreach ( $transaction->eBayCollectAndRemitTaxes->TaxDetails as $tax ) {
                    $sales_taxes = apply_filters( 'wple_ebay_sales_taxes', array( 'SalesTax' ) );
                    if ( !in_array( $tax->Imposition, $sales_taxes ) ) {
                        continue;
                    }

                    $total += $tax->TaxAmount->value;
                }
            }
        }
        WPLE()->logger->info( 'Found '. $total .' tax from eBay.' );

        return $total;
    }

    /**
     * Returns the sales tax collected by eBay on the given line item (Transaction)
     * @param EbayTransactionType $item
     * @return int
     */
    public function getLineSalesTax( $item ) {
        global $wpdb;
        $total = 0;

        WPLE()->logger->info( 'getLineSalesTax()' );

        if ( $item->eBayCollectAndRemitTax == 1 ) {
            foreach ( $item->eBayCollectAndRemitTaxes->TaxDetails as $tax ) {
                $sales_taxes = apply_filters( 'wple_ebay_sales_taxes', array( 'SalesTax' ) );
                if ( !in_array( $tax->Imposition, $sales_taxes ) ) {
                    continue;
                }

                $total += $tax->TaxAmount->value;
            }
        }

        return $total;
    }

    public function getSalesTaxTotal( $details, $price = 0 ) {
	    global $wpdb;

	    WPLE()->logger->info( 'getSalesTaxTotal()' );
        $total = 0;

        // calculate VAT
        $tax_rate_id        = get_option( 'wplister_process_order_sales_tax_rate_id' );

        // do not store sales tax if no sales tax rate ID is selected #18242
        if ( !$tax_rate_id ) {
            WPLE()->logger->info( 'No tax rate found for Sales Tax. Skipping.' );
            return $total;
        }

        if ( get_option( 'wplister_ebay_sales_tax_action', 'ignore' ) == 'record' ) {
            WPLE()->logger->info('sales_tax_action is set to record. Getting sales tax from eBay...');
            $total = $this->geteBaySalesTaxTotal( $details );
        } else {
            if ( is_callable( array( 'WC_Tax', 'get_rate_percent_value' ) ) ) {
                $rate_percent       = WC_Tax::get_rate_percent_value( $tax_rate_id );
            } else {
                $tax_rate    = $wpdb->get_row( "SELECT tax_rate_id, tax_rate, tax_rate_country, tax_rate_state, tax_rate_name, tax_rate_priority FROM {$wpdb->prefix}woocommerce_tax_rates WHERE tax_rate_id = '$tax_rate_id'" );
                $rate_percent = $tax_rate->tax_rate;
            }

            // make sure $rate_percent is not 0 #52230
            if ( $rate_percent > 0 ) {
                $total += $price / ( 1 + ( 1 / ( $rate_percent / 100 ) ) );	// calc VAT from gross amount
            }

        }

        WPLE()->logger->info( 'Total sales tax: '. $total );
        return apply_filters( 'wple_orderbuilder_sales_tax_total', $total, $details );
    } // getSalesTaxTotal()

    public function getTotalFinalValueFee( $details ) {
	    $fvf = 0;

	    foreach ( $details->TransactionArray as $transaction ) {
	        if ( $transaction->FinalValueFee ) {
	            $fvf += $transaction->FinalValueFee->value;
            }
        }

	    return $fvf;
    }

    public function getOrderIOSS( $details ) {
        WPLE()->logger->info( 'getOrderIOSS()' );
        $data = array();

        foreach ( $details->TransactionArray as $transaction ) {
            $id = $transaction->Item->ItemID;

            if ( $transaction->eBayCollectAndRemitTaxes->eBayReference && $transaction->eBayCollectAndRemitTaxes->eBayReference->attributeValues['name'] == 'IOSS' ) {
                $data[ $id ] = array(
                    'IOSS'  => $transaction->eBayCollectAndRemitTaxes->eBayReference->value,
                    'amount' => $transaction->eBayCollectAndRemitTaxes->TotalTaxAmount->value
                );
            }

        }
        WPLE()->logger->info( 'IOSS Data:' . print_r( $data, 1) );

        return $data;
    }

    public function getVOECCode( $details ) {
        WPLE()->logger->info( 'getVOECCode()' );
        $data = [];

        foreach ( $details->TransactionArray as $transaction ) {
            $id = $transaction->Item->ItemID;

            if ( $transaction->eBayCollectAndRemitTaxes->eBayReference && $transaction->eBayCollectAndRemitTaxes->eBayReference->attributeValues['name'] == 'VOEC' ) {
                $data[ $id ] = array(
                    'VOEC'  => $transaction->eBayCollectAndRemitTaxes->eBayReference->value,
                    'amount' => $transaction->eBayCollectAndRemitTaxes->TotalTaxAmount->value
                );
            }

        }
        WPLE()->logger->info( 'VOEC Data:' . print_r( $data, 1) );

        return $data;
    }

    /**
     * Store IOSS data in order meta and order notes
     * @param array $ioss
     * @param WC_Order $order
     */
    public function recordIOSS( $ioss, $order ) {
        WPLE()->logger->info( 'recordIOSS #'. $order->get_id() );
	    if ( empty( $ioss ) ) return;

        add_post_meta( $order->get_id(), '_ebay_order_ioss', $ioss );

	    foreach ( $ioss as $item_id => $data ) {
	        $order->add_order_note( 'IOSS ('. $data['IOSS'] .') for eBay listing #'. $item_id .': '. $data['amount'] );
        }
    }


    /**
     * Process Global Shipping Program orders
     * @param stdClass $details
     * @param int $post_id
     * @param WC_Order $order
     */
	function processMultiLegShipping( $details, $post_id, &$order = null ) {

		// check if multi leg shipping is enabled
        $process_multileg_orders = get_option( 'wplister_process_multileg_orders', 0 );
		if ( ! $process_multileg_orders ) return;
		if ( ! $details->IsMultiLegShipping ) return;

		// shortcuts
		$ShipToAddress          = $details->MultiLegShippingDetails->SellerShipmentToLogisticsProvider->ShipToAddress;
		$ShippingServiceDetails = $details->MultiLegShippingDetails->SellerShipmentToLogisticsProvider->ShippingServiceDetails;
		// echo "<pre>";print_r($ShipToAddress);echo"</pre>";
		// echo "<pre>";print_r($ShippingServiceDetails);echo"</pre>";


		// shipping address
		@list( $shipping_firstname, $shipping_lastname )   = explode( " ", $ShipToAddress->Name, 2 );

        $order->set_shipping_first_name( stripslashes( $shipping_firstname ) );
        $order->set_shipping_last_name( stripslashes( $shipping_lastname ) );
        $order->set_shipping_company( '' );
        $order->set_shipping_address_1( stripslashes( 'Reference# '.$ShipToAddress->ReferenceID ) );
        $order->set_shipping_address_2( stripslashes( $ShipToAddress->Street1 ) );
        $order->set_shipping_city( stripslashes( $ShipToAddress->CityName ) );
        $order->set_shipping_postcode( stripslashes( $ShipToAddress->PostalCode ) );
        $order->set_shipping_country( stripslashes( $ShipToAddress->Country ) );
        $order->set_shipping_state( stripslashes( $ShipToAddress->StateOrProvince ) );

		if ( $process_multileg_orders == 1 ) {
		    // remove shipping fee from order
            // shipping service
            $shipping_total   = 0;
            $shipping_method = 'N/A';

            if ( $ShippingServiceDetails && method_exists($ShippingServiceDetails, 'getTotalShippingCost'))
                $TotalShippingCost = $ShippingServiceDetails->getTotalShippingCost();

            if ( isset( $TotalShippingCost ) && $TotalShippingCost->value )
                $shipping_total = $TotalShippingCost->value;

            if ( $ShippingServiceDetails && method_exists($ShippingServiceDetails, 'getShippingService'))
                $shipping_method = $ShippingServiceDetails->getShippingService();

            // get shipping method title
            $sm = new EbayShippingModel();
            $shipping_title = $sm->getTitleByServiceName( $shipping_method );

            // Added for 58232
            $shipping_title = apply_filters( 'wple_shipping_method_title', $shipping_title, $details, $post_id, $order );

            // fix order total (which should not include the shipping fee eBay charges the buyer)
            // (TotalShippingCost / $shipping_total should be zero for orders that use global shipping)
            $order_total = rtrim(rtrim(number_format( $details->Subtotal->value + $shipping_total, 4, '.', ''), '0'), '.');

            $order->set_shipping_total( $shipping_total );
            $order->set_total( $order_total );
            $order->set_shipping_tax( 0 );

            $tax_items = $order->get_items( 'tax' );

            if ( $tax_items ) {
                foreach ( $tax_items as $tax_item_id => $tax_item ) {
                    wc_update_order_item_meta( $tax_item_id, 'shipping_tax_amount', 0 );
                }
            }

            // remove the shipping costs #24309
            $shipping_items = $order->get_items( 'shipping' );
            if ( $shipping_items ) {
                foreach ( $shipping_items as $item_id => $shipping_item ) {
                    wc_update_order_item_meta( $item_id, 'cost', 0 );
                    wc_update_order_item_meta( $item_id, 'taxes', '' );
                    wc_update_order_item_meta( $item_id, 'total_tax', 0 );
                }
            }
        }


	} // processMultiLegShipping()

	/**
	 * Handle refunds made on ebay/paypal
	 * @param object    $item
	 * @param WC_Order  $order
	 */
	function handleOrderRefunds( $item, &$order ) {
		global $wpdb;

		WPLE()->logger->info('handleOrderRefunds on order #'. $order->get_id() );

		$details = $item['details'];

		if ( $details->MonetaryDetails->Refunds ) {
		    // get existing refunds for the order
            $existing_refunds = $order->get_refunds();
            $refunds_reference_ids = array();
            foreach ( $existing_refunds as $wc_refund ) {
                $refund_id = $wc_refund->get_id();
                $ref_id = get_post_meta( $refund_id, '_ebay_reference_id', true );

                if ( $ref_id ) {
                    $refunds_reference_ids[] = $ref_id;
                }
            }

			WPLE()->logger->info('handleOrderRefunds: Refunds found' );
            WPLE()->logger->debug( 'Existing refunds: '. print_r( $refunds_reference_ids, 1 ) );
			foreach (  $details->MonetaryDetails->Refunds->Refund as $refund ) {
			    $reason = '';

				if ( $refund->ReferenceID ) {
                    $reason = sprintf( __( 'eBay order refund %s (eBay Order #: %s)', 'wp-lister-for-ebay' ), $refund->ReferenceID->value, $details->OrderID );

				    // check if this refund row has already been processed before
				    if ( in_array( $refund->ReferenceID->value, $refunds_reference_ids ) ) {
				        continue;
                    }
					$reason .= ' (Refund #: '. $refund->ReferenceID->value .')';
				} else {
				    // Only process refunds with no reference IDs once #35544 #35818
                    if ( !empty( $existing_refunds ) ) {
                        WPLE()->logger->info( 'Ignoring refund object because no Reference ID was included.' );
                        continue;
                    }
                }

				WPLE()->logger->info('handleOrderRefunds: eBay Order #'. $details->OrderID . ' (Amt: ' . $refund->RefundAmount->value . ')' );

				// for WC2.2+, record the refund so it reflects in the WC Order
				if ( function_exists( 'wc_create_refund' ) ) {
				    $refund_amount  = abs( $refund->RefundAmount->value );
                    $refund_lines   = array();
                    $order_total    = $order->get_total();

				    if ( $details->OrderStatus == 'Cancelled' || $item['CompleteStatus'] == 'Refunded' ) {
				        // For cancelled eBay orders, set the refund amount to the order total because eBay only refunds the total without the eBay fee #43601
                        if ( 2 == get_option( 'wplister_handle_ebay_refunds', 0 ) ) {
                            $items = $order->get_items( array('line_item', 'fee', 'shipping' ) );

                            foreach ( $items as $item_id => $item ) {
                                if ( empty( $item->get_quantity() ) && empty( $item->get_total() ) ) {
                                    continue;
                                }

                                $refund_lines[ $item_id ] = array(
                                    'qty'           => $item->get_quantity(),
                                    'refund_total'  => $item->get_total(),
                                    'refund_tax'    => $item->get_total_tax()
                                );
                            }
                        } else {
                            $refund_amount = $order->get_total();
                        }
                    }

				    try {
                        $wc_refund = wc_create_refund( array(
                            'line_items'    => $refund_lines,
                            'amount'        => $refund_amount,
                            'reason'        => $reason,
                            'order_id'      => $order->get_id()
                        ) );

                        if ( !is_wp_error( $wc_refund ) ) {
                            $refund_id = $wc_refund->get_id();

                            $wc_refund->update_meta_data( '_ebay_reference_id', $refund->ReferenceID->value );
                            $wc_refund->save_meta_data();
                        }
                    } catch ( Exception $e ) {
				        WPLE()->logger->error( 'Caught exception while creating refund: '. $e->getMessage() );
				        WPLE()->logger->error( print_r( $e, 1 ) );
                    }

                    // If this is a full order refund, we should be able to set the order status to Refunded #60468
                    // alternatively, the wple_on_refund_update_wc_order_status can be used to force a status update on partial refunds #60468
                    if ( $refund_amount == $order_total || apply_filters( 'wple_on_refund_update_wc_order_status', false ) == true ) {
                        $order->update_status( 'refunded' );
                    }

				}

                $order->add_order_note( $reason );

                /**
                 * Stop setting the status to refunded because WC automatically creates a new refund object
                 * thats worth the remaining order total #25870 #25221
                 */
				// update WC Order's status to refunded. Add the note separately so it gets added regardless of the order's previous status
				//$order->update_status( 'refunded' );

				// update ebay order's status too
				$wpdb->update( $wpdb->prefix . 'ebay_orders', array( 'CompleteStatus' => 'Refunded' ), array( 'id' => $item['id'] ) );

				WPLE()->logger->info('handleOrderRefunds: completed' );

				do_action( 'wplister_order_refund_processed', $order, $item );
			}
		}
	}

	/**
	 * Record shipment tracking details
	 *
	 * @param int $post_id
	 * @param OrderType $details
	 * @param WC_Order $order
	 */
	public function recordShipmentTracking( $post_id, $details, &$order ) {
		$provider           = $order->get_meta( '_tracking_provider', true );
		$tracking_number    = $order->get_meta( '_tracking_number', true );

		if ( ! empty( $provider ) && ! empty( $tracking_number ) ) {
			WPLE()->logger->info('recordShipmentTracking: Tracking already set for ' . $post_id );
			return;
		}

		if ( ! @$details->TransactionArray ) {
			WPLE()->logger->info('recordShipmentTracking: TransactionArray is empty for ' . $post_id );
			return;
		}

		$transaction = current( $details->TransactionArray );

		if ( $transaction->ShippingDetails->ShipmentTrackingDetails ) {
			foreach ( $transaction->ShippingDetails->ShipmentTrackingDetails as $shipment ) {
				$provider = WpLister_Order_MetaBox::findMatchingTrackingProvider( $shipment->ShippingCarrierUsed );
				$tracking_number = $shipment->ShipmentTrackingNumber;

				// WC Shipment Tracking requires a value for the date_shipped property #57918
                $shipped_date = $details->ShippedTime ? strtotime( $details->ShippedTime ) : '';

				$order->update_meta_data( '_tracking_provider', $provider );
				$order->update_meta_data( '_tracking_number', $tracking_number );
                $record_wc_shipment = apply_filters_deprecated( 'wplister_record_wc_shipment_tracking_data', array(true), '2.8.4', 'wple_record_wc_shipment_tracking_data' );
                $record_wc_shipment = apply_filters( 'wple_record_wc_shipment_tracking_data', $record_wc_shipment );

				if ( $record_wc_shipment ) {
                    $meta = array(
                        array(
							'tracking_id'   => '',
							'custom_tracking_provider' => '',
                            'tracking_provider' => $provider,
                            'tracking_number'   => $tracking_number,
                            'tracking_product_code' => '',
                            'date_shipped' => $shipped_date
                        )
                    );
                    $order->update_meta_data( '_wc_shipment_tracking_items', $meta );
                }
				$order->save();
				WPLE()->logger->info('recordShipmentTracking: Recorded '. $tracking_number . ' via ' . $provider . ' for '. $post_id );
				break;
			}
		}

	}

    /**
     * Update the shipping total if the value in $details is different that the currently stored value
     *
     * @param int       $order_id
     * @param OrderType $details
     * @param WC_Order $order
     */
	public function updateShippingTotal( $order_id, $details, &$order ) {
	    $current_total = get_post_meta( $order_id, '_order_shipping', true );
        $new_total     = $current_total;

        $ShippingServiceSelected = $details->ShippingServiceSelected;
        if ( $ShippingServiceSelected && method_exists($ShippingServiceSelected, 'getShippingServiceCost'))
            $ShippingServiceCost = $ShippingServiceSelected->getShippingServiceCost();

        if ( isset( $ShippingServiceCost ) && $ShippingServiceCost->value ) {
            $new_total = $ShippingServiceCost->value;
        }

        // calculate shipping tax amount - and adjust shipping total
        $shipping_tax_amount = $this->calculateShippingTaxAmount( $new_total, $order_id );

        $new_total      = $new_total - $shipping_tax_amount;

        if ( $new_total && $new_total != $current_total ) {
            // update the shipping fee
            $order->set_shipping_total( $new_total );
            $order->save();

            $shipping_items = $order->get_items( 'shipping' );

            if ( $shipping_items ) {
                $item = current( $shipping_items );
                $item_id = $item->get_id();

                wc_update_order_item_meta( $item_id, 'cost', $new_total );
            }
        }
    }

	function format_decimal( $number ) {

		// wc_format_decimal() exists in WC 2.1+ only
		if ( function_exists('wc_format_decimal') )
			return wc_format_decimal( $number );

		$dp     = get_option( 'woocommerce_price_num_decimals' );
		$number = number_format( floatval( $number ), $dp, '.', '' );
		return $number;

	} // format_decimal()



	/**
	 * addCustomer, adds a new customer to newsletter subscriptions
	 *
	 * @param unknown $customers_name
	 * @return $customers_id
	 */
	public function addCustomer( $user_email, $details ) {
		global $wpdb;
		// WPLE()->logger->info( "addCustomer() - data: ".print_r($details,1) );

		// skip if user_email exists
		if ( $user_id = email_exists( $user_email ) ) {
			// $this->show_message('Error: email already exists: '.$user_email, 1 );
			WPLE()->logger->info( "email already exists $user_email" );
			return $user_id;
		}

		// get user data
		$ebay_user_id    = $details->BuyerUserID;
		$user_name       = $details->BuyerUserID;

		// get shipping address with first and last name
		$shipping_details = $details->ShippingAddress;
		@list( $shipping_firstname, $shipping_lastname ) = explode( " ", $shipping_details->Name, 2 );
		$user_firstname  = sanitize_user( $shipping_firstname, true );
		$user_lastname   = sanitize_user( $shipping_lastname, true );
		$user_fullname   = sanitize_user( $shipping_details->Name, true );

		// generate password
		$random_password = wp_generate_password( $length=12, $include_standard_special_chars=false );


		// check if user with ebay account as username exists
		$user_id = username_exists( $ebay_user_id );
		if ( $user_id ) {
			WPLE()->logger->info( "user already exists: $user_name - $user_email ($user_id) " );
			return $user_id;
		}

		// also check if the email already exists #59490
        $user_id = username_exists( $user_email );
		if ( $user_id ) {
            WPLE()->logger->info( "user email already exists: $user_email ($user_id) " );
            return $user_id;
        }

		// create wp_user
		$wp_user = array(
			'user_login' => $user_name,
			'user_email' => $user_email,
			'first_name' => $user_firstname,
			'last_name' => $user_lastname,
			// 'user_registered' => gmdate( 'Y-m-d H:i:s', strtotime($customer['customers_info_date_account_created']) ),
			'user_pass' => $random_password,
			'role' => 'customer'
			);
		$user_id = wp_insert_user( $wp_user ) ;

		if ( is_wp_error($user_id)) {

			WPLE()->logger->error( 'error creating user '.$user_email.' - WP said: '.$user_id->get_error_message() );
			return false;

		} else {

			// add user meta
			update_user_meta( $user_id, '_ebay_user_id', 		$ebay_user_id );
			update_user_meta( $user_id, 'billing_email', 		$user_email );
			update_user_meta( $user_id, 'paying_customer', 		1 );

			// optional phone number
            // strip out spaces so WC displays it #14208 #16959
			if ($shipping_details->Phone == 'Invalid Request') $shipping_details->Phone = '';
			update_user_meta( $user_id, 'billing_phone', str_replace( ' ', '', stripslashes( $shipping_details->Phone ) ) );

			// billing
			update_user_meta( $user_id, 'billing_first_name', 	$user_firstname );
			update_user_meta( $user_id, 'billing_last_name', 	$user_lastname );
			update_user_meta( $user_id, 'billing_company', 		stripslashes( $shipping_details->CompanyName ) );
			update_user_meta( $user_id, 'billing_address_1', 	stripslashes( $shipping_details->Street1 ) );
			update_user_meta( $user_id, 'billing_address_2', 	stripslashes( $shipping_details->Street2 ) );
			update_user_meta( $user_id, 'billing_city', 		stripslashes( $shipping_details->CityName ) );
			update_user_meta( $user_id, 'billing_postcode', 	stripslashes( $shipping_details->PostalCode ) );
			update_user_meta( $user_id, 'billing_country', 		stripslashes( $shipping_details->Country ) );
			update_user_meta( $user_id, 'billing_state', 		stripslashes( $shipping_details->StateOrProvince ) );

			// shipping
			update_user_meta( $user_id, 'shipping_first_name', 	$user_firstname );
			update_user_meta( $user_id, 'shipping_last_name', 	$user_lastname );
			update_user_meta( $user_id, 'shipping_company', 	stripslashes( $shipping_details->CompanyName ) );
			update_user_meta( $user_id, 'shipping_address_1', 	stripslashes( $shipping_details->Street1 ) );
			update_user_meta( $user_id, 'shipping_address_2', 	stripslashes( $shipping_details->Street2 ) );
			update_user_meta( $user_id, 'shipping_city', 		stripslashes( $shipping_details->CityName ) );
			update_user_meta( $user_id, 'shipping_postcode', 	stripslashes( $shipping_details->PostalCode ) );
			update_user_meta( $user_id, 'shipping_country', 	stripslashes( $shipping_details->Country ) );
			update_user_meta( $user_id, 'shipping_state', 		stripslashes( $shipping_details->StateOrProvince ) );

			WPLE()->logger->info( "added customer $user_id ".$user_email." ($ebay_user_id) " );

		}

		do_action( 'wplister_created_customer_from_order', $user_id, $details );

		return $user_id;

	} // addCustomer()


	function disableEmailNotifications() {

		// prevent WooCommerce from sending out notification emails when updating order status
		if ( get_option( 'wplister_disable_new_order_emails' ) )
            add_filter( 'woocommerce_email_enabled_new_order', array( $this, 'returnFalse' ), 10, 2 );
		if ( get_option( 'wplister_disable_completed_order_emails' ) )
            add_filter( 'woocommerce_email_enabled_customer_completed_order', array( $this, 'returnFalse' ), 10, 2 );
		if ( get_option( 'wplister_disable_processing_order_emails' ) )
            add_filter( 'woocommerce_email_enabled_customer_processing_order', array( $this, 'returnFalse' ), 10, 2 );

        // always disable invoice emails from PIP #26323
        $disable_pip_emails = apply_filters_deprecated( 'wplister_disable_pip_emails', array(true), '2.8.4', 'wple_disable_pip_emails' );
        $disable_pip_emails = apply_filters( 'wple_disable_pip_emails', $disable_pip_emails );
        if ( $disable_pip_emails ) {
            add_filter( 'woocommerce_email_enabled_pip_email_invoice', array( $this, 'returnFalse' ), 10, 2 );
            add_filter( 'woocommerce_email_enabled_pip_email_packing_list', array( $this, 'returnFalse' ), 10, 2 );
            add_filter( 'woocommerce_email_enabled_pip_email_pick_list', array( $this, 'returnFalse' ), 10, 2 );
        }
	}

	function returnFalse( $param1, $param2 = false ) {
		return false;
	}

	/**
	 * Calculate the taxes based on the product's tax class and the order's shipping address
	 *
	 * @param float $product_price
	 * @param int $product_id
	 * @param int $quantity
	 * @param int $order_id
     * @param WC_Order $order
	 * @return array
     * @todo Clean up and update this method using the latest tax calculation algo from WC 3.6.
	 */
	public function getProductTax( $product_price, $product_id, $quantity, $order_id, $order, $Details ) {
		global $woocommerce, $wpdb;
		WPLE()->logger->info( "calling getProductTax( $product_price, $product_id, $quantity, $order_id )" );

		$prices_include_tax = $this->getPricesIncludeTax();

        // get Sales Tax if available
        $tax_rate_id = get_option( 'wplister_process_order_sales_tax_rate_id' );
        //$tax_rate    = $wpdb->get_row( "SELECT tax_rate_id, tax_rate_country, tax_rate_state, tax_rate_name, tax_rate_priority FROM {$wpdb->prefix}woocommerce_tax_rates WHERE tax_rate_id = '$tax_rate_id'" );
        $tax_rate = WC_Tax::_get_tax_rate( $tax_rate_id );

		// Process sales tax from eBay
        $SalesTax        = $Details->ShippingDetails->SalesTax;
        $SalesTaxPercent = $SalesTax->SalesTaxPercent;
        $SalesTaxState   = $SalesTax->SalesTaxState;
        $SalesTaxAmount  = $SalesTax->SalesTaxAmount->value;

        if ( floatval($SalesTaxAmount) && $tax_rate_id ) {
            // convert single price to total price
            $product_price = $product_price * $quantity;

            $line_total    = $product_price;
            $line_subtotal = $product_price;

            // adjust item price if prices include tax
            if ( $prices_include_tax == 'yes' ) {
                $line_total    = $product_price - $SalesTaxAmount;
                $line_subtotal = $product_price - $SalesTaxAmount;
            }

            return array(
                'line_total'            => $line_total,
                'line_tax'              => $SalesTaxAmount,
                'line_subtotal'         => $line_subtotal,
                'line_subtotal_tax'     => $SalesTaxAmount,
                'line_tax_data'         => array(
                    'total' 	=> array( $tax_rate_id => $SalesTaxAmount ),
                    'subtotal' 	=> array( $tax_rate_id => $SalesTaxAmount ),
                ),
                'tax_rate_id'           => $tax_rate_id,
            );
        }

		// if auto-detect is disabled, defer to the previous method of tax computation
		if ( 0 == get_option( 'wplister_orders_autodetect_tax_rates', 0 ) ) {
			WPLE()->logger->info( 'Taxes Autodetect disabled' );
			$vat_percent = get_option( 'wplister_orders_fixed_vat_rate' );
			WPLE()->logger->info( 'VAT% (global): ' . $vat_percent );

			$vat_percent = floatval( $vat_percent );

			// convert single price to total price
			$product_price = $product_price * $quantity;

			// get global tax rate id for order item array
			$tax_rate_id = get_option( 'wplister_process_order_tax_rate_id' );
			$vat_tax     = 0;

            // allow filters to change the rate ID and percentage
            $vat_percent = apply_filters( 'wple_orderbuilder_fixed_vat_percent', $vat_percent, $product_price, $product_id, $quantity, $order, $Details );
            $tax_rate_id = apply_filters( 'wple_orderbuilder_fixed_tax_rate_id', $tax_rate_id, $product_price, $product_id, $quantity, $order, $Details );

			if ( $vat_percent ) {
				$vat_tax = $product_price / ( 1 + ( 1 / ( $vat_percent / 100 ) ) );	// calc VAT from gross amount
				$vat_tax = $this->format_decimal( $vat_tax );
			}

			$line_total    = $product_price;
			$line_subtotal = $product_price;

			// adjust item price if prices include tax
			if ( $prices_include_tax == 'yes' ) {
				$line_total    = $product_price - $vat_tax;
				$line_subtotal = $product_price - $vat_tax;
			}

			return array(
				'line_total'            => $line_total,
				'line_tax'              => $vat_tax,
				'line_subtotal'         => $line_subtotal,
				'line_subtotal_tax'     => $vat_tax,
				'line_tax_data'         => array(
					'total' 	=> array( $tax_rate_id => $vat_tax ),
					'subtotal' 	=> array( $tax_rate_id => $vat_tax ),
				),
				'tax_rate_id'           => $tax_rate_id,
			);
		} else {
			$this->loadCartClasses();

			$cart       = $woocommerce->cart;
			$product    = wc_get_product( $product_id );

			if ( ! $order ) {
                $order = wc_get_order( $order_id );
            }

			WPLE()->logger->debug( "getProductTax() cart object: ".print_r($cart,1) );

			if ( !$product || !is_object($product) ) {
				return apply_filters( 'wple_orderbuilder_no_product_tax_data', array(
					'line_total'            => $product_price * $quantity,
					'line_tax'              => '0.0',
					'line_subtotal'         => $product_price * $quantity,
					'line_subtotal_tax'     => '0.0',
					'line_tax_data'         => array('total' => array(), 'subtotal' => array())
				), $product_price, $quantity, $order );
			}

			$tax_rates      = array();
			$shop_tax_rates = array();

			// set the shipping and billing location to the order's shipping and billing addresses
			// so WC can determine whether or not this zone is taxable or not
			$customer = new WC_Customer();
			$customer->set_shipping_location(
			    $order->get_shipping_country(),
				$order->get_shipping_state(),
				$order->get_shipping_postcode(),
				$order->get_shipping_city()
			);
			$customer->set_billing_location(
			    $order->get_billing_country(),
                $order->get_billing_state(),
                $order->get_billing_postcode(),
                $order->get_billing_city()
            );

			// prevent fatal error:
			// Call to a member function needs_shipping() on a non-object in woocommerce/includes/class-wc-customer.php line 333
			add_filter( 'woocommerce_apply_base_tax_for_local_pickup', '__return_false' );

			$line_price         = $product_price * $quantity;
			$line_subtotal      = 0;
			$line_subtotal_tax  = 0;

			// calculate subtotal
			if ( !$product->is_taxable() ) {

				WPLE()->logger->info( "getProductTax() step 1 - not taxable (mode 1)" );

				$line_subtotal = $line_price;

			} elseif ( $prices_include_tax == 'yes' ) {
				/*
			    // Get base tax rates
				if ( empty( $shop_tax_rates[ $product->get_tax_class() ] ) ) {
					$shop_tax_rates[ $product->get_tax_class() ] = WC_Tax::get_base_tax_rates( $product->get_tax_class() );
				}

				// Get item tax rates
				if ( empty( $tax_rates[ $product->get_tax_class() ] ) ) {
					$tax_rates[ $product->get_tax_class() ] = WC_Tax::get_rates( $product->get_tax_class(), WC()->customer );
				}

				$base_tax_rates = $shop_tax_rates[ $product->get_tax_class() ];
				$item_tax_rates = $tax_rates[ $product->get_tax_class() ];
				*/

                $item_tax_rates = WC_Tax::get_rates( $product->get_tax_class(), $customer );
                $base_tax_rates = WC_Tax::get_base_tax_rates( $product->get_tax_class( 'unfiltered' ) );
                //$remove_taxes   = apply_filters( 'woocommerce_adjust_non_base_location_prices', true ) ? WC_Tax::calc_tax( $line_price, $base_tax_rates, true ) : WC_Tax::calc_tax( $line_price, $item_tax_rates, true );
                $remove_taxes   = WC_Tax::calc_tax( $line_price, $item_tax_rates, true );

                $line_subtotal = $line_price - array_sum( $remove_taxes );
                $line_subtotal_tax     = array_sum( $remove_taxes );

				/**
				 * ADJUST TAX - Calculations when base tax is not equal to the item tax
				 */
				/*if ( $item_tax_rates !== $base_tax_rates ) {
					WPLE()->logger->info( "getProductTax() step 1 - prices include tax (mode 2a)" );

					// Work out a new base price without the shop's base tax
					$taxes                 = WC_Tax::calc_tax( $line_price, $base_tax_rates, true, true );

					// Now we have a new item price (excluding TAX)
					$line_subtotal         = $line_price - array_sum( $taxes );

					// Now add modifed taxes
					$tax_result            = WC_Tax::calc_tax( $line_subtotal, $item_tax_rates );
					$line_subtotal_tax     = array_sum( $tax_result );


                // Regular tax calculation (customer inside base and the tax class is unmodified
				} else {
					WPLE()->logger->info( "getProductTax() step 1 - prices include tax (mode 2b)" );

					// Calc tax normally
					$taxes                 = WC_Tax::calc_tax( $line_price, $item_tax_rates, true );
					$line_subtotal_tax     = array_sum( $taxes );
					$line_subtotal         = $line_price - array_sum( $taxes );
				}*/

				/**
				 * Prices exclude tax
				 *
				 * This calculation is simpler - work with the base, untaxed price.
				 */
			} else {

				WPLE()->logger->info( "getProductTax() step 1 - prices exclude tax (mode 3)" );

				// Get item tax rates
				if ( empty( $tax_rates[ $product->get_tax_class() ] ) ) {
					$tax_rates[ $product->get_tax_class() ] = WC_Tax::get_rates( $product->get_tax_class(), $customer );
				}

				$item_tax_rates        = $tax_rates[ $product->get_tax_class() ];

				// Base tax for line before discount - we will store this in the order data
				$taxes                 = WC_Tax::calc_tax( $line_price, $item_tax_rates );
				$line_subtotal_tax     = array_sum( $taxes );
				$line_subtotal         = $line_price;
			}

			WPLE()->logger->info( "getProductTax() mid - line_subtotal    : $line_subtotal" );
			WPLE()->logger->info( "getProductTax() mid - line_subtotal_tax: $line_subtotal_tax" );

			// calculate line tax

			// Prices
			$base_price = $product_price;
			$line_price = $product_price * $quantity;

			// Tax data
			$taxes = array();
			$discounted_taxes = array();

			if ( !$product->is_taxable() ) {

				WPLE()->logger->info( "getProductTax() step 2 - not taxable (mode 1)" );

				// Discounted Price (price with any pre-tax discounts applied)
				$discounted_price      = $base_price;
				$line_subtotal_tax     = 0;
				$line_subtotal         = $line_price;
				$line_tax              = 0;
				$line_total            = wc_round_tax_total( $discounted_price * $quantity );

				/**
				 * Prices include tax
				 */
				// } elseif ( $cart->prices_include_tax ) { // this doesn't work - $cart is empty!
			} elseif ( $prices_include_tax == 'yes' ) {
                $item_tax_rates = WC_Tax::get_rates( $product->get_tax_class(), $customer );
                $base_tax_rates = WC_Tax::get_base_tax_rates( $product->get_tax_class( 'unfiltered' ) );
                //$remove_taxes   = apply_filters( 'woocommerce_adjust_non_base_location_prices', true ) ? WC_Tax::calc_tax( $line_price, $base_tax_rates, true ) : WC_Tax::calc_tax( $line_price, $item_tax_rates, true );
                $remove_taxes   = WC_Tax::calc_tax( $line_price, $item_tax_rates, true );
                $taxes = $remove_taxes; // $taxes is used in the returned array

                $line_subtotal = $line_price - array_sum( $remove_taxes );
                $line_subtotal_tax     = array_sum( $remove_taxes );

                // Adjusted price (this is the price including the new tax rate)
                $adjusted_price    = ( $line_subtotal + $line_subtotal_tax ) / $quantity;

                // Apply discounts
                $discounted_price  = $adjusted_price;
                $discounted_taxes  = WC_Tax::calc_tax( $discounted_price * $quantity, $item_tax_rates, true );
                $line_tax          = array_sum( $discounted_taxes );
                $line_total        = ( $discounted_price * $quantity ) - $line_tax;





                /*
				$base_tax_rates = $shop_tax_rates[ $product->get_tax_class() ];
				$item_tax_rates = $tax_rates[ $product->get_tax_class() ];

				/**
				 * ADJUST TAX - Calculations when base tax is not equal to the item tax
				 *\/
				if ( $item_tax_rates !== $base_tax_rates ) {

					WPLE()->logger->info( "getProductTax() step 2 - prices include tax (mode 2a)" );

					// Work out a new base price without the shop's base tax
					$taxes             = WC_Tax::calc_tax( $line_price, $base_tax_rates, true, true );

					// Now we have a new item price (excluding TAX)
					$line_subtotal     = wc_round_tax_total( $line_price - array_sum( $taxes ) );

					// Now add modifed taxes
					$taxes             = WC_Tax::calc_tax( $line_subtotal, $item_tax_rates );
					$line_subtotal_tax = array_sum( $taxes );



					// Adjusted price (this is the price including the new tax rate)
					$adjusted_price    = ( $line_subtotal + $line_subtotal_tax ) / $quantity;

					// Apply discounts
					$discounted_price  = $adjusted_price;
					$discounted_taxes  = WC_Tax::calc_tax( $discounted_price * $quantity, $item_tax_rates, true );
					$line_tax          = array_sum( $discounted_taxes );
					$line_total        = ( $discounted_price * $quantity ) - $line_tax;

					/**
					 * Regular tax calculation (customer inside base and the tax class is unmodified
					 *\/
				} else {

					WPLE()->logger->info( "getProductTax() step 2 - prices include tax (mode 2b)" );

					// Work out a new base price without the shop's base tax
					$taxes             = WC_Tax::calc_tax( $line_price, $item_tax_rates, true );

					// Now we have a new item price (excluding TAX)
					$line_subtotal     = $line_price - array_sum( $taxes );
					$line_subtotal_tax = array_sum( $taxes );

					// Calc prices and tax (discounted)
					$discounted_price = $base_price;
					$discounted_taxes = WC_Tax::calc_tax( $discounted_price * $quantity, $item_tax_rates, true );
					$line_tax         = array_sum( $discounted_taxes );
					$line_total       = ( $discounted_price * $quantity ) - $line_tax;
				}
                */

				/**
				 * Prices exclude tax
				 */
			} else {

				WPLE()->logger->info( "getProductTax() step 2 - prices exclude tax (mode 3)" );

				$item_tax_rates        = $tax_rates[ $product->get_tax_class() ];

				// Work out a new base price without the shop's base tax
				$taxes                 = WC_Tax::calc_tax( $line_price, $item_tax_rates );

				// Now we have the item price (excluding TAX)
				$line_subtotal         = $line_price;
				$line_subtotal_tax     = array_sum( $taxes );

				// Now calc product rates
				$discounted_price      = $base_price;
				$discounted_taxes      = WC_Tax::calc_tax( $discounted_price * $quantity, $item_tax_rates );
				$discounted_tax_amount = array_sum( $discounted_taxes );
				$line_tax              = $discounted_tax_amount;
				$line_total            = $discounted_price * $quantity;
			}

			$tax_rate_id = '';

			foreach ( $item_tax_rates as $rate_id => $rate ) {
				$tax_rate_id = $rate_id;
				break;
			}

			WPLE()->logger->info( "getProductTax() end - line_subtotal    : $line_subtotal" );
			WPLE()->logger->info( "getProductTax() end - line_subtotal_tax: $line_subtotal_tax" );

			return array(
				'tax_rate_id'           => $tax_rate_id,
				'line_total'            => $line_total,
				'line_tax'              => $line_tax,
				'line_subtotal'         => $line_subtotal,
				'line_subtotal_tax'     => $line_subtotal_tax,
				'line_tax_data'         => array('total' => $discounted_taxes, 'subtotal' => $taxes )
			);
		}
	} // getProductTax()

    public function addSalesTaxToLineItemTax( $item_taxes, $sales_tax_total ) {
        // get tax rate
        $tax_rate_id = get_option( 'wplister_process_order_sales_tax_rate_id' );

        // do not store sales tax if no sales tax rate ID is selected #18242
        if ( !$tax_rate_id ) {
            return $item_taxes;
        }

	    $item_taxes['line_tax'] += $sales_tax_total;
	    $item_taxes['line_subtotal_tax'] += $sales_tax_total;
	    $item_taxes['line_tax_data']['total'][ $tax_rate_id ] += $sales_tax_total;
	    $item_taxes['line_tax_data']['subtotal'][ $tax_rate_id ] += $sales_tax_total;
	    return $item_taxes;
    }

	public function getProductTaxFromProfile( $listing, $product_price, $quantity ) {
		$vat_enabled = $listing && $listing->profile_data['details']['tax_mode'] == 'fix' ? true : false;
		$vat_percent = $vat_enabled && $listing->profile_data['details']['vat_percent']
			? $listing->profile_data['details']['vat_percent']
			: get_option( 'wplister_orders_fixed_vat_rate' );
		WPLE()->logger->info( 'VAT%: ' . $vat_percent.' - ' . ($vat_enabled ? 'profile' : 'fallback') );

		$vat_percent = floatval( $vat_percent );

		// convert single price to total price
		$product_price = $product_price * $quantity;

		// get global tax rate id for order item array
		$tax_rate_id = get_option( 'wplister_process_order_tax_rate_id' );
		$vat_tax     = 0;

		if ( $vat_percent ) {
			$vat_tax = $product_price / ( 1 + ( 1 / ( $vat_percent / 100 ) ) );	// calc VAT from gross amount
			$vat_tax = $this->format_decimal( $vat_tax );

			// store tax rate and rate percent for shipping taxes later
            $this->profile_tax = array(
                'rate_id'   => $tax_rate_id,
                'rate_percent'  => $vat_percent
            );
		}

		$line_total    = $product_price;
		$line_subtotal = $product_price;

		// adjust item price if prices include tax
		//if ( get_option( 'woocommerce_prices_include_tax' ) == 'yes' ) {
			$line_total    = $product_price - $vat_tax;
			$line_subtotal = $product_price - $vat_tax;
		//}

		return array(
			'line_total'            => $line_total,
			'line_tax'              => $vat_tax,
			'line_subtotal'         => $line_subtotal,
			'line_subtotal_tax'     => $vat_tax,
			'line_tax_data'         => array(
				'total' 	=> array( $tax_rate_id => $vat_tax ),
				'subtotal' 	=> array( $tax_rate_id => $vat_tax ),
			),
			'tax_rate_id'           => $tax_rate_id,
		);
	}

    /**
     * Adds a 'tax' line item to the specified order
     *
     * @param int   $order_id
     * @param int   $tax_rate_id
     * @param float $tax_amount
     * @param float $shipping_tax_amount
     * @param WC_Order &$order
     * @return void
     */
	private function addOrderLineTax( $order_id, $tax_rate_id, $tax_amount = 0, $shipping_tax_amount = 0, &$order = null ) {
	    global $wpdb;

        // get tax rate
        //$tax_rate    = $wpdb->get_row( "SELECT tax_rate_id, tax_rate, tax_rate_country, tax_rate_state, tax_rate_name, tax_rate_priority FROM {$wpdb->prefix}woocommerce_tax_rates WHERE tax_rate_id = '$tax_rate_id'" );
        $tax_rate = WC_Tax::_get_tax_rate( $tax_rate_id, OBJECT );
        $tax_rate_label = WC_Tax::get_rate_label( $tax_rate_id );
        $tax_rate_percent = WC_Tax::get_rate_percent( $tax_rate_id );
        WPLE()->logger->debug( '$tax_rate: '. print_r( $tax_rate, true ) );

        $code      = WC_Tax::get_rate_code( $tax_rate_id );
        $tax_code  = $code ? $code : __( 'VAT', 'wp-lister-for-ebay' );
        $tax_label = $tax_rate_id ? $tax_rate_label : WC()->countries->tax_or_vat();

        if ( $order ) {
            $line = new WC_Order_Item_Tax();
            $line->set_name( $tax_code );
            $line->set_compound( false );
            $line->set_tax_total( $this->format_decimal( $tax_amount ) );
            $line->set_shipping_tax_total( $this->format_decimal( $shipping_tax_amount ) );
            $line->set_rate( $tax_rate_id );
            $line->set_label( $tax_label );

            // WC_Order_Item_Tax::set_rate_percent() is not available in WC 3.6 #47839
            if ( is_callable( array( $line, 'set_rate_percent' ) ) ) {
                if ( empty( WC_Tax::get_rate_percent_value( $tax_rate_id ) ) ) {
                    $tax_rate_percent = ($tax_amount / $order->get_subtotal()) * 100;
                }
                $line->set_rate_percent( $tax_rate_percent );
            }

            $order->add_item( $line );
        } else {
            $item_id = wc_add_order_item( $order_id, array(
                'order_item_name' 		=> $tax_code,
                'order_item_type' 		=> 'tax'
            ) );
            WPLE()->logger->info( 'Added order tax item: '. $item_id );

            // Add line item meta
            if ( $item_id ) {
                wc_add_order_item_meta( $item_id, 'compound', 0 );
                wc_add_order_item_meta( $item_id, 'tax_amount', $this->format_decimal( $tax_amount ) );
                wc_add_order_item_meta( $item_id, 'shipping_tax_amount', $this->format_decimal( $shipping_tax_amount ) );

                wc_add_order_item_meta( $item_id, 'rate_id', $tax_rate_id );
                wc_add_order_item_meta( $item_id, 'rate_percent', $tax_rate->tax_rate );
                wc_add_order_item_meta( $item_id, 'label', $tax_label );
            }
        }
    }

    /**
     * Get tax location for this order.
     *
     * @since  2.0.42
     * @param $order WC_Order
     * @return array
     */
    private function get_tax_location( $order ) {
        $tax_based_on = get_option( 'woocommerce_tax_based_on' );

        if ( 'shipping' === $tax_based_on && ! $order->get_shipping_country() ) {
            $tax_based_on = 'billing';
        }

        $args = array(
            'country'  => 'billing' === $tax_based_on ? $order->get_billing_country()  : $order->get_shipping_country(),
            'state'    => 'billing' === $tax_based_on ? $order->get_billing_state()    : $order->get_shipping_state(),
            'postcode' => 'billing' === $tax_based_on ? $order->get_billing_postcode() : $order->get_shipping_postcode(),
            'city'     => 'billing' === $tax_based_on ? $order->get_billing_city()     : $order->get_shipping_city(),
        );

        // Default to base
        if ( 'base' === $tax_based_on || empty( $args['country'] ) ) {
            $default          = wc_get_base_location();
            $args['country']  = $default['country'];
            $args['state']    = $default['state'];
            $args['postcode'] = '';
            $args['city']     = '';
        }

        return $args;
    }

    /**
     * Returns an empty address object
     * @return stdClass
     */
    private function getAddressObject() {
        $address = new stdClass();
        $address->CompanyName = '';
        $address->Street1 = '';
        $address->Street2 = '';
        $address->CityName = '';
        $address->PostalCode = '';
        $address->Country = '';
        $address->StateOrProvince = '';
        $address->Phone = '';
        return $address;
    }


    private function getPricesIncludeTax() {
        $force_prices_include_tax = get_option('wplister_ebay_force_prices_include_tax', 'ignore' );
        if ( $force_prices_include_tax != 'ignore' ) {
            $prices_include_tax = $force_prices_include_tax == 'force_yes' ? 'yes' : 'no';
        } else {
            // This filter allows 3rd-party code to override the Prices Include Tax setting in WooCommerce while calculating
            // for taxes in eBay order. This is because there are users with eBay stores where the prices already include taxes
            // yet their WC prices do not, which causes inaccurate prices and totals #32656
            // 'yes' or 'no' values ONLY
            $prices_include_tax = apply_filters( 'wple_orderbuilder_prices_include_tax', get_option( 'woocommerce_prices_include_tax' ) );
        }

        return $prices_include_tax;
    }

	/**
	 * Include cart files because WC only preloads them when the request
	 * is coming from the frontend
	 */
	public function loadCartClasses() {
		global $woocommerce;

		if ( file_exists($woocommerce->plugin_path() .'/classes/class-wc-cart.php') ) {
			require_once $woocommerce->plugin_path() .'/classes/abstracts/abstract-wc-session.php';
			require_once $woocommerce->plugin_path() .'/classes/class-wc-session-handler.php';
			require_once $woocommerce->plugin_path() .'/classes/class-wc-cart.php';
			require_once $woocommerce->plugin_path() .'/classes/class-wc-checkout.php';
			require_once $woocommerce->plugin_path() .'/classes/class-wc-customer.php';
		} else {
			require_once $woocommerce->plugin_path() .'/includes/abstracts/abstract-wc-session.php';
			require_once $woocommerce->plugin_path() .'/includes/class-wc-session-handler.php';
			require_once $woocommerce->plugin_path() .'/includes/class-wc-cart.php';
			require_once $woocommerce->plugin_path() .'/includes/class-wc-checkout.php';
			require_once $woocommerce->plugin_path() .'/includes/class-wc-customer.php';
		}

		if (! $woocommerce->session ) {
			$woocommerce->session = new WC_Session_Handler();
            if ( is_callable( array( $woocommerce->session, 'init_session_cookie' ) ) ) {
                $woocommerce->session->init_session_cookie();
            }
		}

		if (! $woocommerce->customer ) {
			$woocommerce->customer = new WC_Customer();
		}
	} // loadCartClasses()
} // class WPL_WooOrderBuilder
// $WPL_WooOrderBuilder = new WPL_WooOrderBuilder();

## END PRO ##


