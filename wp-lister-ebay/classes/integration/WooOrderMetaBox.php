<?php
/**
 * custom metabox for woocommerce orders created by WP-Lister Pro
 */

## BEGIN PRO ##

class WpLister_Order_MetaBox {

	// var $providers;

	/**
	 * Constructor
	 */
	function __construct() {

		// add_action( 'admin_print_styles', array( __CLASS__, 'admin_styles' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_boxes' ), 10, 2 );
		// add_action( 'woocommerce_process_shop_order_meta', array( __CLASS__, 'save_meta_box' ), 0, 2 );
        add_action( 'wp_ajax_wple_update_ebay_tracking', array( __CLASS__, 'ajax_update_ebay_tracking_and_feedback' ) );

        // this hook needs to be registered even when is_admin() is false:
		add_action( 'woocommerce_order_status_completed', array( __CLASS__, 'handle_woocommerce_order_status_update' ), 0, 2 );

		// Listen to shipping tracking details from the DHL for WooCommerce plugin #36249
        add_action( 'pr_save_dhl_label_tracking', array( __CLASS__, 'save_dhl_tracking_details' ), 10, 2 );

		add_action( 'admin_post_wple_complete_order', array( __CLASS__, 'retry_complete_ebay_order' ) );

		add_action( 'wp_loaded', array( $this, 'displayOverdueOrders' ) );
		add_action( 'admin_post_wple_disable_overdue_orders_check', array( $this, 'disableOverdueOrdersCheck' ) );

		// Fix Order Totals Summary
		add_action( 'woocommerce_admin_order_totals_after_discount', array( $this, 'displayFeeSummary' ) );

	}


	// static function admin_styles() {
	// 	wp_enqueue_style( 'shipment_tracking_styles', plugins_url( basename( dirname( __FILE__ ) ) ) . '/assets/css/admin.css' );
	// }

	/**
	 * Add the meta box for shipment info on the order page
	 *
     * @param string $screen
     * @param WC_Order $wc_order
	 * @access public
	 */
	static function add_meta_boxes($screen = null, $wc_order = null) {
        if ( $screen && !in_array( $screen, ['shop_order', 'woocommerce_page_wc-orders'] ) ) return;

		$ebay_order_id = false;
        $wc_order = ( $wc_order instanceof WP_Post ) ? wc_get_order( $wc_order ) : $wc_order;


        if ( $wc_order ) {
		    $ebay_order_id = $wc_order->get_meta( '_ebay_order_id', true );
        }
		//$ebay_order_id 		 = get_post_meta( $post->ID, '_ebay_order_id', true );
		if ( ! $ebay_order_id ) return;

		$title = __( 'eBay', 'wp-lister-for-ebay' ) . ' <small style="color:#999"> #' . $ebay_order_id . '</small>';

		add_meta_box(
		    'woocommerce-ebay-details',
            $title,
            array( __CLASS__, 'meta_box' ),
            $screen,
            'side',
            'core'
        );

	}

	/**
	 * Show the meta box for shipment info on the order page
	 *
     * @param WP_Post|WC_Order $post_or_order_object
	 * @access public
	 */
	static function meta_box( $post_or_order_object ) {
		// global $post;
        $wc_order = ( $post_or_order_object instanceof WP_Post ) ? wc_get_order( $post_or_order_object->ID ) : $post_or_order_object;


        // Load eBay class files to be able to access the $details->ShippingDetails object
        EbayController::loadEbayClasses();

		// get order details
		$ebay_order_id = $wc_order->get_meta( '_ebay_order_id', true );
		$om            = new EbayOrdersModel();
		$order         = $om->getOrderByOrderID( $ebay_order_id );
		$details       = WPL_Model::decodeObject( $order['details'] );
		$account       = $order ? WPLE_eBayAccount::getAccount( $order['account_id'] ) : false;

        // display ebay info and account
        echo '<p>';

        echo __( 'This order was placed on eBay.', 'wp-lister-for-ebay' );
        if ( WPLE()->multi_account && $account ) echo ' ('.$account->title.')';

        // Try to display a link to the order on eBay #46277
        if ( $account && $details->ShippingDetails->SellingManagerSalesRecordNumber != '' ) {
            $site = WPLE_eBaySite::getSite( $account->site_id );
            $order_url = sprintf( 'https://www.%s/sh/ord/details?srn=%d&orderid=%s&source=Orders', $site->url, $details->ShippingDetails->SellingManagerSalesRecordNumber, $ebay_order_id );
            echo ' View in <a href="admin.php?page=wplister-orders&s='.$ebay_order_id.'" target="_blank">WP-Lister</a> or <a href="'. $order_url .'" target="_blank">eBay</a>';
        } else {
            echo ' [<a href="admin.php?page=wplister-orders&s='.$ebay_order_id.'" target="_blank">view</a>]';
        }

		$marked_as_shipped = $wc_order->get_meta( '_ebay_marked_as_shipped', true );
		if ( $marked_as_shipped ) echo '<br>'.'Marked as shipped: '.$marked_as_shipped;

		$feedback_left     = $wc_order->get_meta( '_ebay_feedback_left', true );
		if ( $feedback_left     ) echo '<br>'.'Feedback was left: '.$feedback_left;

        echo '</p>';


		// tracking providers
		$selected_provider  = $wc_order->get_meta( '_tracking_provider', true );
		if ( ! $selected_provider ) $selected_provider  = $wc_order->get_meta( '_custom_tracking_provider', true );
		$selected_provider  = apply_filters_deprecated( 'wplister_set_shipping_provider_for_order', array($selected_provider, $wc_order->get_id()), '2.8.4', 'wple_set_shipping_provider_for_order' );
		$selected_provider  = apply_filters( 'wple_set_shipping_provider_for_order', $selected_provider, $wc_order->get_id() );

		if ( empty( $selected_provider ) ) {
		    // load the default shipping provider
            $selected_provider = get_option( 'wplister_default_shipping_service', '' );
        }

		$shipping_providers = apply_filters_deprecated( 'wplister_available_shipping_providers', array(self::getProviders()), '2.8.4', 'wple_available_shipping_providers' );
		$shipping_providers = apply_filters( 'wple_available_shipping_providers', $shipping_providers );

		echo '<p class="form-field wpl_tracking_provider_field"><label for="wpl_tracking_provider">' . __( 'Shipping service', 'wp-lister-for-ebay' ) . ':</label><br/><select id="wpl_tracking_provider" name="wpl_tracking_provider" class="wple_chosen_select" style="width:100%;">';

		echo '<option value="">-- ' . __( 'Select shipping service', 'wp-lister-for-ebay' ) . ' --</option>';
		$matching_provider_found = false;
		foreach ( $shipping_providers as $provider => $display_name  ) {
			echo '<option value="' . $provider . '" ' . selected( $provider, $selected_provider, true ) . '>' . $display_name . '</option>';
			if ( $provider == $selected_provider ) $matching_provider_found = true;
		}
		// if no matching provider was found, add the selected provider to the list (support for WPLA and 3rd party plugins)
		if ( $selected_provider && ! $matching_provider_found ) {
			echo '<option value="' . $selected_provider . '" ' . selected( $selected_provider, $selected_provider, true ) . '>' . $selected_provider . '</option>';
		}
		echo '</select> ';

		// allow 3rd party code to fill in the tracking number and shipping date automatically
		$tracking_number = $wc_order->get_meta( '_tracking_number', true );
		$tracking_number = apply_filters_deprecated( 'wplister_set_tracking_number_for_order', array($tracking_number, $wc_order->get_id()), '2.8.4', 'wple_set_tracking_number_for_order' );
		$tracking_number = apply_filters( 'wple_set_tracking_number_for_order', $tracking_number, $wc_order->get_id() );

		$date_shipped    = $wc_order->get_meta( '_date_shipped', true );
		$shipping_date   = '';
		if ( !empty( $date_shipped ) ) {
            if ( is_numeric( $date_shipped ) ) {
                $shipping_date   = date( 'Y-m-d', $date_shipped );
            } else {
                $shipping_date   = date( 'Y-m-d', strtotime( $date_shipped ) );
            }
        }

		$shipping_date   = apply_filters_deprecated( 'wplister_set_shipping_date_for_order', array($shipping_date, $wc_order->get_id()), '2.8.4', 'wple_set_shipping_date_for_order' );
		$shipping_date   = apply_filters( 'wple_set_shipping_date_for_order', $shipping_date, $wc_order->get_id() );

		woocommerce_wp_text_input( array(
			'id' 			=> 'wpl_tracking_number',
			'label' 		=> __( 'Tracking ID:', 'wp-lister-for-ebay' ),
			'placeholder' 	=> '',
			'description' 	=> '',
			'value'			=> $tracking_number
		) );

		woocommerce_wp_text_input( array(
			'id' 			=> 'wpl_date_shipped',
			'label' 		=> __( 'Shipping date:', 'wp-lister-for-ebay' ),
			'placeholder' 	=> current_time( 'Y-m-d' ),
			'description' 	=> '',
			'class'			=> 'date-picker-field',
			'value'			=> $shipping_date
		) );

		woocommerce_wp_text_input( array(
			'id' 			=> 'wpl_feedback_text',
			'label' 		=> __( 'Your feedback:', 'wp-lister-for-ebay' ),
			'placeholder' 	=> '',
			'description' 	=> 'Feedback is always positive.',
			'custom_attributes' => array( 'maxlength' => 80 ),
			'value'			=> $wc_order->get_meta( '_feedback_text', true )
		) );

		// Mark order as paid - defaults to Yes
		$paid = $wc_order->get_meta( '_ebay_order_paid', true );
		if ( $paid != 0 ) {
		    $paid = 1;
        }

        echo '<p class="form-field wpl_order_paid_field"><label for="wpl_order_paid">' . __( 'Mark as Paid', 'wp-lister-for-ebay' ) . ':</label><br/><select id="wpl_order_paid" name="wpl_order_paid" class="wple_chosen_select" style="width:100%;">';
		echo '<option value="1" '. selected( $paid, 1, false ) .'>'. __( 'Yes', 'wp-lister-for-ebay' ) .'</option>';
		echo '<option value="0" '. selected( $paid, 0, false ) .'>'. __( 'No', 'wp-lister-for-ebay' ) .'</option>';
		echo '</select></p>';


		// woocommerce_wp_checkbox( array( 'id' => 'wpl_update_ebay_on_save', 'wrapper_class' => 'update_ebay', 'label' => __( 'Update on save?', 'wp-lister-for-ebay' ) ) );


        // $feedback_text = get_post_meta( $post->ID, '_feedback_text', true );
        // if ( $feedback_text ) {
        //     echo '<p>';
        //     echo '<a id="btn_update_again" href="'.$transaction_id.'" target="_blank" class="button">Update again</a>';
        //     echo '</p>';
        // } else {
            echo '<p>';
            echo '<div id="btn_update_ebay_feedback_spinner" style="float:left;display:none"><img src="'.WPLE_PLUGIN_URL.'img/ajax-loader-f9.gif"/></div>';
            echo '<a href="#" id="btn_update_ebay_feedback" class="button button-primary">Update on eBay</a>';
            // echo '<a id="btn_update_again" href="#" style="display:none" target="_blank" class="button">Update again</a>';
            echo '<div id="ebay_result_info" class="updated" style="display:none"><p></p></div>';
            echo '</p>';
            // echo "<br><br>";
        // }


        $ajax_nonce = wp_create_nonce( 'wple_ajax_update_tracking_details' );
        wc_enqueue_js("

            var wpl_updateEbayFeedback = function ( post_id ) {


                var tracking_provider = jQuery('#wpl_tracking_provider').val();
                var tracking_number = jQuery('#wpl_tracking_number').val();
                var date_shipped = jQuery('#wpl_date_shipped').val();
                var feedback_text = jQuery('#wpl_feedback_text').val();
                var order_paid = jQuery('#wpl_order_paid').val();
                
                // load task list
                var params = {
                    action: 'wple_update_ebay_tracking',
                    order_id: post_id,
                    wpl_tracking_provider: tracking_provider,
                    wpl_tracking_number: tracking_number,
                    wpl_date_shipped: date_shipped,
                    wpl_feedback_text: feedback_text,
                    wpl_order_paid: order_paid,
                    _wpnonce: '".$ajax_nonce."'
                };
                var jqxhr = jQuery.getJSON( 
                    ajaxurl, 
                    params,
                    function( response ) { 
    
                        jQuery('#btn_update_ebay_feedback_spinner').hide();
    
                        if ( response.success ) {
    
                            // var transaction_id = response.transaction_id;
                            // var logMsg = 'Transaction #'+transaction_id+' was created.';
                            var logMsg = 'Order details were updated on eBay.';
                            jQuery('#ebay_result_info p').html( logMsg );
                            jQuery('#ebay_result_info').slideDown();
                            jQuery('#btn_update_ebay_feedback').hide('fast');
                            jQuery('#btn_update_again').prop('href',response.invoice_url);
                            jQuery('#btn_update_again').show('fast');
    
                        } else {
    
                            var logMsg = '<b>There was a problem updating this order on eBay</b><br><br>'+response.error;
                            jQuery('#ebay_result_info p').html( logMsg );
                            jQuery('#ebay_result_info').addClass( 'error' ).removeClass('updated');
                            jQuery('#ebay_result_info').slideDown();
    
                            jQuery('#btn_update_ebay_feedback').removeClass('disabled');
                        }
                    } 
                )
                .fail( function(e,xhr,error) { 
                    jQuery('#ebay_result_info p').html( 'The server responded: ' + e.responseText + '<br>' );

                    jQuery('#btn_update_ebay_feedback_spinner').hide();
                    jQuery('#btn_update_ebay_feedback').removeClass('disabled');

                    console.log( 'error', xhr, error ); 
                    console.log( e.responseText ); 
                });

            }

            jQuery('#btn_update_ebay_feedback').click(function(){

                var post_id = jQuery('#post_ID').val();

                jQuery('#btn_update_ebay_feedback_spinner').show();
                jQuery(this).addClass('disabled');
                wpl_updateEbayFeedback( post_id );

                return false;
            });
        ");

	}

	/**
	 * Order Downloads Save
	 *
	 * Function for processing and storing all order downloads.
	 */
	static function save_meta_box( $post_id, $post ) {
	} // save_meta_box()

	// handle order status changed to "completed" - and complete ebay order
    static public function handle_woocommerce_order_status_update( $post_id, $wc_order = null ) {
        WPLE()->logger->info('handle_woocommerce_order_status_update_completed for #'. $post_id);

        // Skip completing orders again during order sync with HPOS
        if ( did_action( 'wc-admin_import_orders' ) ) {
            return;
        }

        if ( is_null( $wc_order ) ) {
            $wc_order = wc_get_order( $post_id );
        }

    	// check if auto complete option is enabled
    	if ( get_option( 'wplister_auto_complete_sales' ) != 1 ) {
            WPLE()->logger->info('wplister_auto_complete_sales is OFF. Skipping');
    	    return;
        }

    	// check if default status for new created orders is completed - skip further processing if it is
		if ( get_option( 'wplister_new_order_status', 'processing' ) == 'completed' ) {
            WPLE()->logger->info('wplister_new_order_status is COMPLETED. Skipping');
		    return;
        }

    	// check if this order came in from eBay
        $ebay_order_id = $wc_order->get_meta( '_ebay_order_id', true );
    	if ( ! $ebay_order_id ) {
            WPLE()->logger->info('_ebay_order_id not found. Skipping');
    	    return;
        }


		// build array
		$data = array();
		// $data['ShippedTime']  = gmdate('U');
		$data['ShippedTime']     = '_now_';
		$data['FeedbackText']    = get_option( 'wplister_default_feedback_text', '' );

		// check if there are tracking details stored by other plugins - like Shipstation or Shipment Tracking
		$wpl_tracking_provider = $wc_order->get_meta( '_tracking_provider', true );
		$wpl_tracking_number   = $wc_order->get_meta( '_tracking_number', true );
		if ( $wpl_tracking_number && $wpl_tracking_provider ) {
			$data['TrackingNumber']  = trim( $wpl_tracking_number );
			$data['TrackingCarrier'] = trim( $wpl_tracking_provider );
		}

		// add support for WC Shipment Tracking v1.6.6 which stores tracking data using a different meta key
        $record_wc_shipment = apply_filters_deprecated( 'wplister_record_wc_shipment_tracking_data', array(true), '2.8.4', 'wple_record_wc_shipment_tracking_data' );
        $record_wc_shipment = apply_filters( 'wple_record_wc_shipment_tracking_data', $record_wc_shipment );
        if ( $record_wc_shipment ) {
            $wc_tracking_data = $wc_order->get_meta( '_wc_shipment_tracking_items', true );
            if ( $wc_tracking_data ) {
                $wc_tracking_data = current( $wc_tracking_data );
                $data['TrackingNumber']  = $wc_tracking_data['tracking_number'];

                if ( !empty( $wc_tracking_data['custom_tracking_provider'] ) ) {
                    $data['TrackingCarrier'] = $wc_tracking_data['custom_tracking_provider'];
                } else {
                    $data['TrackingCarrier'] = $wc_tracking_data['tracking_provider'];
                }
            }
        }

        // Check for tracking data from YITH WC Order Tracking #51124
        $yith_carrier = $wc_order->get_meta( 'ywot_carrier_id', true );
        $yith_tracking_number = $wc_order->get_meta( 'ywot_tracking_code', true );
        if ( $yith_carrier ) {
            WPLE()->logger->info( 'Found YITH carrier. '. $yith_carrier .'/'. $yith_tracking_number );
            $data['TrackingNumber']  = $yith_tracking_number;
            $data['TrackingCarrier'] = $yith_carrier;
        }

		// check if tracking details are included in POST request (ie. an order is completed from the order details page)
		$wpl_tracking_provider	 = isset( $_REQUEST['wpl_tracking_provider'] ) ? wple_clean( $_REQUEST['wpl_tracking_provider'] ) : false;
		$wpl_tracking_number 	 = isset( $_REQUEST['wpl_tracking_number']   ) ? wple_clean( $_REQUEST['wpl_tracking_number']   ) : false;
		if ( $wpl_tracking_number && $wpl_tracking_provider ) {
			$data['TrackingNumber']  = trim( $wpl_tracking_number );
			$data['TrackingCarrier'] = trim( $wpl_tracking_provider );
		}

        // Check if this needs to be added to the queue for background processing
        if ( get_option( 'wplister_complete_sale_in_background', 0 ) ) {
            wple_enqueue_async_action( 'wple_bg_complete_sale_on_ebay', array( 'post_id' => $post_id, 'data' => $data ) );
        } else {
            // complete sale on eBay
            $response = self::callCompleteOrder( $post_id, $data, true );

            // Update order date if request was successful #45914
            if ( $response->success ) {
                $wc_order->update_meta_data( '_date_shipped', gmdate( 'U' ) );
                $wc_order->save_meta_data();
            }

            ### Moved to self::callCompleteOrder() ###
            // Update order data if request was successful
            //if ( $response->success ) {
            //    update_post_meta( $post_id, '_feedback_text', $data['FeedbackText'] );
            //}
        }


		// error handling is done in callCompleteOrder()
		// if ( WPLE()->EC->isSuccess ) {
		// if ( $response->success ) {
		// }
    }

    /**
     * Compatibility function for DHL for WooCommerce plugin. Stores tracking data from DHL.
     * @param int $order_id
     * @param array $tracking_details
     */
    static function save_dhl_tracking_details( $order_id, $tracking_details ) {
        WPLE()->logger->info('save_dhl_tracking_details()');
        //$data['TrackingNumber']     = $tracking_details['tracking_number'];
        //$data['TrackingCarrier']    = $tracking_details['carrier'];
        //$data['ShippedTime']        = $tracking_details['ship_date'];

        $order = wc_get_order( $order_id );

        $order->update_meta_data( '_tracking_provider', 'DHL' );
        $order->update_meta_data( '_tracking_number', $tracking_details['tracking_number'] );
        $order->save_meta_data();

        // complete sale on eBay
        //self::callCompleteOrder( $order_id, $data, true );
    }

    /**
     * Handle the [Retry] link in the Order Notes section for failed CompleteSale
     */
    static function retry_complete_ebay_order() {
	    $order_id = sanitize_key($_GET['post']);

	    self::handle_woocommerce_order_status_update( $order_id );
	    wp_redirect( admin_url( 'post.php?post='. $order_id .'&action=edit' ) );
	    exit;
    }

    /**
     * update feedback and tracking details on ebay (ajax)
     */
    static function ajax_update_ebay_tracking_and_feedback() {

		// check nonce and permissions
	    check_admin_referer( 'wple_ajax_update_tracking_details' );
		if ( ! current_user_can('manage_ebay_listings') ) return;

		// check parameters
		if ( ! isset( $_REQUEST['order_id']  ) ) return;

		// get field values
        $post_id 					= wple_clean($_REQUEST['order_id']);
		$wpl_tracking_provider		= wple_clean( $_REQUEST['wpl_tracking_provider'] );
		$wpl_tracking_number 		= wple_clean( $_REQUEST['wpl_tracking_number'] );
		$wpl_date_shipped			= wple_clean( strtotime( $_REQUEST['wpl_date_shipped'] ) );
		$wpl_feedback_text 			= wple_clean( $_REQUEST['wpl_feedback_text'] );
		$wpl_order_paid             = isset( $_REQUEST['wpl_order_paid'] ) ? wple_clean($_REQUEST['wpl_order_paid']) : 1;

		// if tracking number is set, but date is missing, set date today.
		if ( trim($wpl_tracking_number) != '' ) {
			if ( $wpl_date_shipped == '' ) $wpl_date_shipped = gmdate('U');
		}

		// build array
		$data = array();
		$data['TrackingNumber']  = trim( $wpl_tracking_number );
		$data['TrackingCarrier'] = trim( $wpl_tracking_provider );
		$data['ShippedTime']     = trim( $wpl_date_shipped );
		$data['FeedbackText']    = trim( $wpl_feedback_text );
		$data['Paid']            = $wpl_order_paid;

		// if feedback text is empty, use default feedback text
		if ( ! $data['FeedbackText'] ) {
			$data['FeedbackText'] = get_option( 'wplister_default_feedback_text', '' );
		}

		$order = wc_get_order( $post_id );

    	// check if this order came in from eBay
        $ebay_order_id = $order->get_meta( '_ebay_order_id', true );
    	if ( ! $ebay_order_id ) die('This is not an eBay order.');

    	// moved to self::callCompleteOrder() so it will be triggered for do_action(wple_complete_sale_on_ebay)
    	//$data = apply_filters( 'wplister_complete_order_data', $data, $post_id );

    	// complete sale on eBay
		$response = self::callCompleteOrder( $post_id, $data );

		// WPLE()->initEC();
		// $response = WPLE()->EC->completeOrder( $post_id, $data );
		// WPLE()->EC->closeEbay();

		// Update order data if request was successful
		if ( $response->success ) {
			$order->update_meta_data( '_tracking_provider', $wpl_tracking_provider );
			$order->update_meta_data( '_tracking_number', $wpl_tracking_number );
			$order->update_meta_data( '_date_shipped', $wpl_date_shipped );
			//update_post_meta( $post_id, '_feedback_text', $wpl_feedback_text );
            $order->save();
		}

        self::returnJSON( $response );
        exit();

    } // ajax_update_ebay_tracking_and_feedback()

    static public function callCompleteOrder( $post_id, $data, $verbose = false ) {
        WPLE()->logger->info('callCompleteOrder for #'. $post_id );

        // get eBay order
        $sm = new EbayOrdersModel();
        $ebay_order = $sm->getOrderByPostID( $post_id );
    	if ( ! $ebay_order ) {
            WPLE()->logger->info('ebay order not found. Skipping');
    	    return;
        }

        $data = apply_filters_deprecated( 'wplister_complete_order_data', array($data, $post_id, $ebay_order), '2.8.4', 'wple_complete_order_data' );
        $data = apply_filters( 'wple_complete_order_data', $data, $post_id, $ebay_order );

		// get account_id for eBay order
		$account_id = $ebay_order['account_id'];

		// get account title for order notes
        $account_title = isset( WPLE()->accounts[ $account_id ] ) ? WPLE()->accounts[ $account_id ]->title : '_unknown_';
        $account_title = ' ('.$account_title.')';

		// get order
		$order = wc_get_order( $post_id );
		$order_modified = false;

		// add order note - only when acting on order status change event
		if ( $verbose )	$order->add_order_note( __( 'Preparing to complete sale on eBay...', 'wp-lister-for-ebay' ) . $account_title );


		// make sure feedback is only left once - prevent Error 55
		$feedback_left = $order->get_meta( '_ebay_feedback_left', true );
		if ( isset($data['FeedbackText']) && $feedback_left == 'yes' ) unset( $data['FeedbackText'] );

		// make sure ShippedTime is a timestamp
		if ( isset($data['ShippedTime']) && ! is_numeric($data['ShippedTime']) ) {
		    // try to read from the postmeta, but only if there's no numeric ShippedTime set #58834
            $shipped_time = $order->get_meta( '_shipped_time', true );

            if ( is_numeric( $shipped_time ) ) {
                $data['ShippedTime'] = $shipped_time;
            } else {
                $data['ShippedTime'] = strtotime( $data['ShippedTime'] );
            }
		}

		// fuzzy match tracking provider
		if ( isset($data['TrackingCarrier']) ) {
			$data['TrackingCarrier'] = self::findMatchingTrackingProvider( $data['TrackingCarrier'] );
		}


		// call eBay
		WPLE()->initEC( $account_id );
		$response = WPLE()->EC->completeOrder( $ebay_order['id'], $data );
		WPLE()->EC->closeEbay();


		// handle result
		if ( $response->success ) {
            $order->add_order_note(__('eBay sale was completed successfully.', 'wp-lister-for-ebay') . $account_title);

            // Check if FeedbackText is available #60407
            if ( isset( $data['FeedbackText'] ) ) {
                $order->update_meta_data( '_feedback_text', $data['FeedbackText']);
                $order_modified = true;
            }

            // Redo the overdue orders list
			delete_transient( 'wple_overdue_shipments' );
            do_action('wple_overdue_orders_check');
        } else {
            // set a max of 10 revision retries per order
            if ( isset( $data['bg_action'] ) ) {
                $attempts = $order->get_meta( '_wple_complete_order_retries', true );
                if ( !$attempts ) $attempts = 1;

                if ( $attempts > 10 ) {
                    $retry_link = ' [<a href="'. admin_url( 'admin-post.php?action=wple_complete_order&post='. $post_id ) .'">Retry</a>]';
                    $order->add_order_note( __( 'There was a problem completing the sale on eBay! Maximum retry limit reached. Please contact support.', 'wp-lister-for-ebay' )  . $account_title . $retry_link );
                    return $response;
                }
                $attempts++;
                $order->update_meta_data( '_wple_complete_order_retries', $attempts );
                $order_modified = true;
            }

            if ( $response->error ) {
                $error_msg = ' ' . $response->error;
                $order->add_order_note( sprintf( __( 'There was a problem completing the sale on eBay. %s. Retrying again in 5 minutes', 'wp-lister-for-ebay' ), "$error_msg  $account_title" ) );
            } else {
                $order->add_order_note( __( 'There was a problem completing the sale on eBay! Retrying again in 5 minutes.', 'wp-lister-for-ebay' ) . $account_title );
            }
            $order->update_meta_data( '_wple_debug_last_error', $response );
            $order_modified = true;

            // flag this request as a background action
            $data['bg_action'] = true;
            as_schedule_single_action( time() + 300, 'wple_bg_complete_sale_on_ebay', array( $order->get_id(), $data ), 'wple' );
		}

		// remember if feedback was left
		if ( $response->success && isset( $data['FeedbackText'] ) && trim( $data['FeedbackText'] ) ) {
			$order->update_meta_data( '_ebay_feedback_left', 'yes' );
            $order->update_meta_data( '_feedback_text', $data['FeedbackText'] );
            $order_modified = true;
		}

		// Error 55 usually means feedback was already left
		if ( $response->error_code == 55 ) {
			$order->update_meta_data( '_ebay_feedback_left', 'yes' );
            $order_modified = true;
		}

		// remember if order was marked as shipped on eBay
		if ( $response->success && isset( $data['ShippedTime'] ) ) {
			$order->update_meta_data( '_ebay_marked_as_shipped', 'yes' );
            $order_modified = true;
		}

		if ( $order_modified ) {
		    $order->save();
        }

    	return $response;
    } // callCompleteOrder()


    static public function findMatchingTrackingProvider( $provider_name ) {
    	$providers = self::getProviders();

    	foreach ( $providers as $key => $name ) {
    		// return lower case match
    		if ( strtolower($key) == strtolower($provider_name) ) {
    			$provider_name = $key;
    			break;
    		}
    	}

        // allow 3rd-party code to run their own checks #50611 #50645
        $provider_name = apply_filters( 'wple_find_matching_tracking_provider', $provider_name, $providers );

    	// Strip invalid characters. According to the API
        // only letters, numbers, and dashes are allowed
        // #23738 #23514
        $provider_name = str_replace( '_', '-', $provider_name );
        $provider_name = preg_replace( '/[^\da-z\-]/i', '', $provider_name );

    	// return 'Other';
    	return $provider_name; // if no match is found, return original provider name - eBay should accept most values
    } // findMatchingTrackingProvider()

	public static function checkOverdueOrders() {
		if ( !get_option( 'wplister_overdue_orders_check', 0 ) ) return;

		$om = new EbayOrdersModel();
		$om->checkOverdueShipments();
	}

	public static function displayOverdueOrders() {
    	if ( !get_option( 'wplister_overdue_orders_check', 0 ) ) return;

		$orders = get_transient( 'wple_overdue_shipments' );

		if ( !empty( $orders ) ) {
			$count = count( $orders );
			$html  = '<h3>Overdue eBay Orders</h3>';
			$html .= 'You have '. sprintf( _n('%d eBay order that needs', '%d eBay orders that need', $count, 'wp-lister-for-ebay'), $count ) .' to be shipped out.';
			$html .= '<ul>';
			foreach ( $orders as $order ) {
				$due_dt = new DateTime( $order['due_date'] );
				$date_str = $due_dt->format( get_option('date_format') .' '. get_option('time_format') );
				$order_link = admin_url( 'post.php?action=edit&post='. $order['wc_order_id'] );
				$html .= sprintf( '<li>[<a href="%s" target="_blank">#%s</a>] eBay Order #%s was due on %s</li>', $order_link, $order['wc_order_id'], $order['ebay_order_id'], $date_str );
			}

			$html .= '</ul><div><a href="'. admin_url('admin-post.php?action=wple_disable_overdue_orders_check&_wpnonce='. wp_create_nonce( 'wple_ajax_nonce') ) .'" class="button" >Do not show again</a></div>';

			wple_show_message( $html, 'error');
		}
	}

	public function disableOverdueOrdersCheck() {
		check_admin_referer( 'wple_ajax_nonce' );
		update_option( 'wplister_overdue_orders_check', 0 );
		wp_redirect( admin_url() );
		exit;
	}

	public function displayFeeSummary( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( 0 > $order->get_total_fees() ) : ?>
			<tr>
				<td class="label"><?php esc_html_e( 'eBay Fee:', 'woocommerce' ); ?></td>
				<td width="1%"></td>
				<td class="total">
					<?php echo wc_price( $order->get_total_fees(), array( 'currency' => $order->get_currency() ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</td>
			</tr>
		<?php endif;
	}


    // TODO: Commonly used shipping carriers can be found by calling GeteBayDetails with DetailName set to ShippingCarrierDetails
    // and examining the returned ShippingCarrierDetails.ShippingCarrier field.
    static public function getProviders() {
    	return array(
			'APC'                    => 'APC',
			'Australia Post'         => 'Australia Post',
			'Canada Post'            => 'Canada Post',
			'Chronopost'             => 'Chronopost',
			'City Link'              => 'City Link',
			'ColiposteDomestic'      => 'Coliposte Domestic',
			'ColiposteInternational' => 'Coliposte International',
			'Correos'                => 'Correos',
			'Deutsche Post'          => 'Deutsche Post',
			'DHL'                    => 'DHL',
			'DHL Global Mail'        => 'DHL Global Mail',
			'Direct Freight'         => 'Direct Freight',
			'DPD'                    => 'DPD',
			'DTDC'                   => 'DTDC',
			'FedEx'                  => 'Fedex',
			'GLS'                    => 'GLS',
			'Hermes'                 => 'Hermes',
			'iLoxx'                  => 'iLoxx',
			'Interlink Express'      => 'Interlink Express',
			'Nacex'                  => 'Nacex',
			'OnTrac'                 => 'OnTrac',
			'ParcelForce'            => 'ParcelForce',
			'PostNL'                 => 'PostNL',
			'Posten AB'              => 'Posten AB',
			'Royal Mail'             => 'Royal Mail',
			'SAPO'                   => 'SAPO',
			'StarTrack'              => 'Star Track',
			'SmartSend'              => 'Smart Send',
			'TNT'                    => 'TNT',
			'UK Mail'                => 'UK Mail',
			'UPS'                    => 'UPS',
			'USPS'                   => 'U.S. Postal Service',
			'Other'                  => 'Other postal service',
            // Map providers sent by Amazon from MCF orders
            'ROYAL_MAIL'            => 'Royal Mail',
            'Poste Italiane'        => 'Poste Italiane',
		);
    } // getProviders()


    static public function returnJSON( $data ) {
        header('content-type: application/json; charset=utf-8');
        echo json_encode( $data );
    }


}
$WpLister_Order_MetaBox = new WpLister_Order_MetaBox();

## END PRO ##


