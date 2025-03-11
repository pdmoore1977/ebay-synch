<?php
/**
 * wrapper functions to access orders on WooCommerce
 */

class OrderWrapper {

	const plugin = 'woo';
	const post_type = 'shop_order';

	// get custom post type
	static function getPostType() {
		return self::post_type;
	}

	static function getOrder( $order_id ) {
	    if ( is_callable( 'wc_get_order' ) ) {
            return wc_get_order( $order_id );
        } else {
	        return new WC_Order( $order_id );
        }
    }


	## BEGIN PRO ##

	// handle local purchases
	static function listen_to_checkout_event() {

		add_action('woocommerce_reduce_order_stock', array( 'OrderWrapper', 'handle_reduce_order_stock'), 5, 1 );

	}

    /**
     * @param int|WC_Order $order
     */
	static function handle_reduce_order_stock( $order ) {
	    if ( is_numeric( $order ) ) {
	        $order = wc_get_order( $order );
        }

		WPLE()->logger->info('handle order #'. wple_get_order_meta( $order, 'id' ) );

		if ( WPLE()->isStagingSite() ) {
			WPLE()->logger->info("WP-CRON: staging site detected! terminating execution...");
			return;
		}

		// Reduce stock levels and do any other actions with products in the cart
        $bg_revise  = get_option( 'wplister_background_revisions', 0 );
		$cart_items = array();

		foreach ( $order->get_items() as $item ) {

			if ( (@$item['id']>0) || (@$item['product_id']>0) ) {

				// get post ID for WC2.0 and WC1.x
				$post_id = isset( $item['product_id'] ) ? $item['product_id'] : $item['id'];
				WPLE()->logger->info('processing reduce stock for product #' . $post_id . '');

				// check if this is a variable product and get SKU
				$variation_id = isset( $item['variation_id'] ) ? $item['variation_id'] : '';
				if ( $variation_id ) {
					$sku = get_post_meta( $variation_id, '_sku', true );
					WPLE()->logger->info('processing variation ID ' . $variation_id . ' - SKU: '.$sku);
				}

				if ( is_callable( array( $item, 'get_product' ) ) ) {
				    $_product = $item->get_product();
                } else {
                    $_product = $order->get_product_from_item( $item );
                }

				if ( $_product && $_product->exists() && $_product->managing_stock() ) {

					// update listing quantity - except for variations
					if ( ! ProductWrapper::hasVariations( $post_id ) ) {
						//ListingsModel::setListingQuantity( $post_id, $_product->get_stock_quantity() );
                        ListingsModel::setListingQuantity( $post_id, ProductWrapper::getStock( $post_id ) );
						// WPLE()->logger->info('new stock: ' . $_product->stock . '');
					}

					$cart_item = new stdClass();
					$cart_item->post_id      = $post_id;
					$cart_item->variation_id = $variation_id;
					$cart_item->sku          = $_product->get_sku();

					WPLE()->logger->info('adding purchased item to revision queue #' . $post_id . '');
					$cart_items[] = $cart_item;

				}

			}

		} // foreach cart item


		// filter items which need to be revised
		$items_grouped_by_account_id = ListingsModel::filterPurchasedItemsForRevision( $cart_items );

		// revise items
		if ( count($items_grouped_by_account_id) > 0 ) {

			foreach ( $items_grouped_by_account_id as $account_id => $items_to_revise ) {

				// get account title for order notes
		        $account_title = isset( WPLE()->accounts[ $account_id ] ) ? WPLE()->accounts[ $account_id ]->title : '_unknown_';
		        $account_title = ' ('.$account_title.')';

				WPLE()->logger->info('items_to_revise:' . print_r($items_to_revise,1) );

				if ( $bg_revise ) {
                    $order->add_order_note( sprintf( __( 'Scheduled to update inventory in the background for %s item(s) on eBay...', 'wp-lister-for-ebay' ), count($items_to_revise) ) . $account_title );
                    foreach( $items_to_revise as $item ) {
                        wple_schedule_revise_inventory( $item->listing_id, $account_id, $order->get_id() );
                    }
                } else {
                    $order->add_order_note( sprintf( __( 'Preparing to update inventory for %s item(s) on eBay...', 'wp-lister-for-ebay' ), count($items_to_revise) ) . $account_title );

                    // revise inventory for cart items using this account
                    WPLE()->initEC( $account_id );
                    WPLE()->EC->reviseInventoryForCartItems( $items_to_revise );
                    WPLE()->EC->closeEbay();

                    if ( WPLE()->EC->isSuccess ) {
                        $order->add_order_note( __( 'eBay inventory was updated successfully.', 'wp-lister-for-ebay' ) . $account_title );
                    } else {
                        // set a max of 10 revision retries per order
                        $revision_retries = $order->get_meta( '_wple_stock_revision_retries', true );

                        if ( ! $revision_retries ) $revision_retries = 0;

                        if ( $revision_retries <= 10 ) {
                            $order->add_order_note( __( 'There was a problem revising the inventory on eBay! Revision will be retried in 5 minutes. Please check the database log and contact support.', 'wp-lister-for-ebay' ) . $account_title );
                            WPLE()->logger->error('EC::lastResults:' . print_r(WPLE()->EC->lastResults,1) );

                            // Schedule a retry of the inventory sync in 5 minutes
                            as_schedule_single_action( time() + 300, 'wple_retry_handle_reduce_order_stock', array( $order->get_id() ), 'wple' );

                            $revision_retries++;
                            $order->update_meta_data( '_wple_stock_revision_retries', $revision_retries );
                            $order->save_meta_data();
                        } else {
                            $order->add_order_note( __( 'There was a problem revising the inventory on eBay for the products in this order and the revision retry limit has been reached. Please check the database log and contact support.', 'wp-lister-for-ebay' ) );
                        }
                    }
                }

			} // foreach account_id

		} else {
		    // This does not need to be in the order notes - leave non-WPLE orders as is #60799
		    WPLE()->logger->info( 'No active eBay listings found in order #'. $order->get_id() );
			//$order->add_order_note( __( 'No active eBay listings found in this order.', 'wp-lister-for-ebay' ) );
		}

        // store debug information in db
        $dblogger = new WPL_EbatNs_Logger();
        $dblogger->updateLog( array(
			'callname'    => 'handle_reduce_order_stock',
			'request_url' => 'woocommerce action hook',
			'request'     => maybe_serialize( $cart_items ),
			'response'    => maybe_serialize( $items_grouped_by_account_id ),
			'success'     => 'Success'
        ));

	} // handle_reduce_order_stock()

	## END PRO ##

} // class OrderWrapper


