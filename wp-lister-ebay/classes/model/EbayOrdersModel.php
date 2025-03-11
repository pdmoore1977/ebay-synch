<?php
/**
 * EbayOrdersModel class
 *
 * responsible for managing orders and talking to ebay
 *
 */

class EbayOrdersModel extends WPL_Model {

	const TABLENAME = 'ebay_orders';

	var $_session;
	/* @var EbatNs_ServiceProxy */
	var $_cs;

	var $count_total    = 0;
	var $count_skipped  = 0;
	var $count_updated  = 0;
	var $count_inserted = 0;
	var $count_failed   = 0;
	var $report         = array();
	var $ModTimeTo      = false;
	var $ModTimeFrom    = false;
	var $NumberOfDays   = false;
	var $found_orders   = array();
	var $found_ids      = array();
	var $duplicate_ids  = array();

	var $total_items;
	var $total_pages;
	var $current_page;
	var $current_lastdate;

	public function __construct() {
		parent::__construct();

		global $wpdb;
		$this->tablename = $wpdb->prefix . 'ebay_orders';
	}

    public function updateActiveOrders( $session ) {
        global $wpdb;

        $last_order_date = $this->getDateOfLastOrder( $this->account_id );

        if ( $last_order_date ) {
        $orders = $wpdb->get_col( $wpdb->prepare("SELECT order_id FROM {$wpdb->prefix}ebay_orders WHERE CompleteStatus = 'Active' AND LastTimeModified < %s", $last_order_date ) );

        if ( $orders ) {
            $this->updateOrders( $session, false, 1, $orders );
        }
        }
    }

	function updateOrders( $session, $days = false, $current_page = 1, $order_ids = false ) {
		WPLE()->logger->info('*** updateOrders('.$days.') - page '.$current_page);

		// this is a cron job if no number of days and no order IDs are requested
		$is_cron_job = $days == false && $order_ids == false ? true : false;

		$this->initServiceProxy($session);

		// set request handler
		$this->_cs->setHandler( 'OrderType', array( & $this, 'handleOrderType' ) );
		// $this->_cs->setHandler( 'PaginationResultType', array( & $this, 'handlePaginationResultType' ) );

		// build request
		$req = new GetOrdersRequestType();
		$req->setOrderRole( 'Seller' );
		$req->setIncludeFinalValueFee( true );
		// $req->setIncludeContainingOrder(true);

		// check if we need to calculate lastdate
        // check if we need to calculate lastdate
        if ( $this->current_lastdate ) {
            $lastdate = $this->current_lastdate;
            WPLE()->logger->info('used current_lastdate from last run: '.$lastdate);
        } else {

            // period 30 days, which is the maximum allowed
            //$now = time();
            $now = gmdate('U');
            $lastdate = $this->getDateOfLastOrder( $this->account_id );
            WPLE()->logger->info("getDateOfLastOrder( {$this->account_id} ) returned: ".$lastdate);
            if ($lastdate) $lastdate = mysql2date('U', $lastdate);

            // if last date is older than 30 days, fall back to default
            if ( $lastdate < $now - 3600 * 24 * 30 ) {
                WPLE()->logger->info('resetting lastdate - fall back default ');
                $lastdate = false;
            }

        }

        // save lastdate for next page
        $this->current_lastdate = $lastdate;

		// fetch orders by IDs
		if ( is_array( $order_ids ) ) {
			$OrderIDArray = new OrderIDArrayType();
			foreach ( $order_ids as $id ) {
                /**
                 * Since 3.0.6, $order_ids now contain raw eBay order IDs and not the internal IDs from WP-Lister
                 */
                //$order = $this->getItem( $id );
                //$OrderIDArray->addOrderID( $order['order_id'] );
                $OrderIDArray->addOrderID( $id );
			}
			$req->setOrderIDArray( $OrderIDArray );
		// parameter $days
		} elseif ( $days ) {
			$req->NumberOfDays  = $days;
			$this->NumberOfDays = $days;
			WPLE()->logger->info('NumberOfDays: '.$req->NumberOfDays);

		// default: orders since last change
		} elseif ( $lastdate ) {
			$req->ModTimeFrom  = gmdate( 'Y-m-d H:i:s', $lastdate );
			$req->ModTimeTo    = gmdate( 'Y-m-d H:i:s', time() );
			$this->ModTimeFrom = $req->ModTimeFrom;
			$this->ModTimeTo   = $req->ModTimeTo;
			WPLE()->logger->info('lastdate: '.$lastdate);
			WPLE()->logger->info('ModTimeFrom: '.$req->ModTimeFrom);
			WPLE()->logger->info('ModTimeTo: '.$req->ModTimeTo);

		// fallback: one day (max allowed by ebay: 30 days)
		} else {
			$days = 1;
			$req->NumberOfDays  = $days;
			$this->NumberOfDays = $days;
			WPLE()->logger->info('NumberOfDays (fallback): '.$req->NumberOfDays);
		}


		// $req->DetailLevel = $Facet_DetailLevelCodeType->ReturnAll;
		//if ( ! $this->is_ajax() ) $req->setDetailLevel('ReturnAll');
        // set DetailLevel to return all data to include external transactions
        $req->setDetailLevel('ReturnAll');

		// set pagination for first page
		$custom_page_size   = get_option( 'wplister_fetch_orders_page_size', 50 );
		$items_per_page     = $is_cron_job ? $custom_page_size : 100; // For GetOrders, the maximum value is 100 and the default value is 25 (which is too low in some rare cases)
		$this->current_page = $current_page;

		$Pagination = new PaginationType();
		$Pagination->setEntriesPerPage( $items_per_page );
		$Pagination->setPageNumber( $this->current_page );
		$req->setPagination( $Pagination );


		// get orders (single page)
		WPLE()->logger->info('fetching orders - page '.$this->current_page);
		$res = $this->_cs->GetOrders( $req );

		$this->total_pages = $res->PaginationResult->TotalNumberOfPages;
		$this->total_items = $res->PaginationResult->TotalNumberOfEntries;

		// get order with pagination helper (doesn't work as expected)
		// EbatNs_PaginationHelper($proxy, $callName, $request, $responseElementToMerge = '__COUNT_BY_HANDLER', $maxEntries = 200, $pageSize = 200, $initialPage = 1)
		// $helper = new EbatNs_PaginationHelper( $this->_cs, 'GetOrders', $req, 'OrderArray', 20, 10, 1);
		// $res = $helper->QueryAll();

		// process order data collected by handleOrderType()
		$this->processFoundOrders();

		// handle response and check if successful
		if ( $this->handleResponse($res) ) {
			WPLE()->logger->info( "*** Orders updated successfully." );
			// WPLE()->logger->info( "*** PaginationResult:".print_r($res->PaginationResult,1) );
			// WPLE()->logger->info( "*** processed response:".print_r($res,1) );

			WPLE()->logger->info( "*** current_page : ".$this->current_page );
			WPLE()->logger->info( "*** total_pages  : ".$this->total_pages );
			WPLE()->logger->info( "*** total_items  : ".$this->total_items );

			WPLE()->logger->info( "** count_inserted: ".$this->count_inserted );
			WPLE()->logger->info( "** count_updated : ".$this->count_updated );
			WPLE()->logger->info( "** count_skipped : ".$this->count_skipped );
			WPLE()->logger->info( "** count_failed  : ".$this->count_failed );

			// fetch next page recursively - only in days mode, or if no new orders have been fetched yet
			if ( $res->HasMoreOrders && ( ! $is_cron_job || $this->count_inserted == 0 ) ) {
				$this->current_page++;
				$this->updateOrders( $session, $days, $this->current_page );
			}


		} else {
			WPLE()->logger->error( "Error on orders update".print_r( $res, 1 ) );
		}
	}

	// function handlePaginationResultType( $type, $Detail ) {
	// 	//#type $Detail PaginationResultType
	// 	$this->total_pages = $Detail->TotalNumberOfPages;
	// 	$this->total_items = $Detail->TotalNumberOfEntries;
	// 	WPLE()->logger->info( 'handlePaginationResultType()'.print_r( $Detail, 1 ) );
	// }

	function handleOrderType( $type, $Detail ) {

		// check if OrderID was already seen in this request
		if ( in_array( $Detail->OrderID, $this->found_ids ) ) {
			$this->duplicate_ids[] = $Detail->OrderID;
		}

		// add order $Detail to found orders
		$this->found_orders[] = $Detail;
		$this->found_ids[]    = $Detail->OrderID;

		// this will remove item from result
		return true;
	}

	function processFoundOrders() {
		foreach ( $this->found_orders as $Detail ) {
			$this->processOrderType( $Detail );
		}
	}

	function processOrderType( $Detail ) {

		// skip OrderID if it occurs more than once in this request #38475
		if ( in_array( $Detail->OrderID, $this->duplicate_ids ) ) return;

		// map OrderType to DB columns
		$data = $this->mapItemDetailToDB( $Detail );
		// WPLE()->logger->info( 'handleOrderType() mapped data: '.print_r( $data, 1 ) );

		// skip invalid orders
		if ( ! $data ) return;

		// insert or update eBay Order record
		$this->insertOrUpdate( $data, $Detail );
	}

	function insertOrUpdate( $data, $Detail ) {
		global $wpdb;

		WPLE()->logger->info( 'insertOrUpdate() ' . $data['order_id'] );
		WPLE()->logger->info( print_r( $data, true ) );

        // extract the ShippedTime
        if ( $item_details = maybe_unserialize( $data['details'] ) ) {
            $shipped_time = self::convertEbayDateToSql( $item_details->ShippedTime );
            if ( $shipped_time ) $data['ShippedTime'] = $shipped_time;
        }

        // count order line items
        $line_item_count = count( maybe_unserialize( $data['items'] ) );

		// try to get existing order by order id
		$order = $this->getOrderByOrderID( $data['order_id'] );

		if ( $order ) {

			$this->addToReport( 'updated', $data );
			WPLE()->logger->info( 'update ebay order #' . $data['order_id'] . ' - LastTimeModified: ' . $data['LastTimeModified'] );

			// update existing order
			$result    = $wpdb->update( $this->tablename, $data, array( 'order_id' => $data['order_id'] ) );
			$insert_id = $order['id'];

			// handle db error
			if ( $result === false ) {
				WPLE()->logger->error( 'failed to update order - MySQL said: '.$wpdb->last_error );
				wple_show_message( 'Failed to update order #'.$data['order_id'].' - MySQL said: '.$wpdb->last_error, 'error' );
			}

			// check if order status has changed (Active/Completed/Cancelled)
			if ( $data['CompleteStatus'] != $order['CompleteStatus'] ) {

				// add history record
				$history_message = 'Order status has changed from '.$order['CompleteStatus'].' to '.$data['CompleteStatus'];
				$history_details = array( 'old_status' => $order['CompleteStatus'], 'new_status' => $data['CompleteStatus'] );
				$this->addHistory( $data['order_id'], 'order_status_changed', $history_message, $history_details );

                $wc_order = wc_get_order( $order['post_id'] );

                /*** ## BEGIN PRO ## ***/
                $revert_stock_changes = get_option( 'wplister_revert_stock_changes', 1 );

                WPLE()->logger->info( 'CompleteStatus is '. $data['CompleteStatus'] .' from '. $order['CompleteStatus'] );

                // Handle the CancelPending order status and WooCommerce's restocking of the order
                if ( $data['CompleteStatus'] == 'CancelPending' && ! $revert_stock_changes && $wc_order ) {
                    // Ensure stock is not marked as "reduced" when WPLE marks this order as Pending so WC does not restock the items #39760
	                if ( method_exists( $wc_order, 'set_order_stock_reduced' ) ) {
						$wc_order->set_order_stock_reduced( false );
	                } else {
		                $wc_order->get_data_store()->set_stock_reduced( $order['post_id'], false );
	                }
					$wc_order->save();
                    WPLE()->logger->info( 'set_stock_reduced called for order #'. $order['post_id'] );
                }
                /*** ## END PRO ## ***/

				if ( $data['CompleteStatus'] == 'Cancelled' ) {
                    /*** ## BEGIN PRO ## ***/
                    WPLE()->logger->info( 'Handling cancelled order' );

				    if ( ! $revert_stock_changes ) {
                        // Ensure stock is not marked as "reduced" anymore. #36903 #39893
                        if ( $wc_order ) {
	                        if ( method_exists( $wc_order, 'set_order_stock_reduced' ) ) {
		                        $wc_order->set_order_stock_reduced( false );
	                        } else {
		                        $wc_order->get_data_store()->set_stock_reduced( $order['post_id'], false );
	                        }
							$wc_order->save();

                            $history_message = "Skipped reverting stock due to the Revert Stock Changes setting being OFF";
                            $history_details = array( 'order_id' => $order['post_id'] );
                            $this->addHistory( $data['order_id'], 'wc_order_status_changed', $history_message, $history_details );
                        }
                    } else {
				        // Handle Active orders that have been cancelled #54729
                        if ( $data['CompleteStatus'] == 'Active' ) {
                            // this method takes care of adding history to the order
                            $this->revertOrderStockChanges( $data['order_id'] );
                        } elseif ( in_array( $order['CompleteStatus'], array( 'Completed', 'CancelPending' ) ) ) {
                            // $order['CompleteStatus'] refers to the previous eBay order status

                            // Only revert stocks if stock_reduced is TRUE for the order
                            // WC has the tendency of restocking orders when transitioning from Processing to Pending so we need to check this #37884
                            if ( $wc_order ) {
	                            $stock_reduced = method_exists( $wc_order, 'get_order_stock_reduced' ) ? $wc_order->get_order_stock_reduced() : $wc_order->get_data_store()->get_stock_reduced( $wc_order );

	                            if ( $stock_reduced ) {
                                    // Don't restock if the WC order has already been refunded or cancelled #38791
                                    if ( in_array( $wc_order->get_status(), array( 'refunded', 'cancelled' ) ) ) {
                                        $history_message = sprintf( "Skipped reverting stock on %s order", $wc_order->get_status() );
                                        $history_details = array( 'order_id' => $order['post_id'] );
                                        $this->addHistory( $data['order_id'], 'wc_order_status_changed', $history_message, $history_details );
                                    } else {
                                        $this->revertOrderStockChanges( $data['order_id'] );
                                    }
		                            // Ensure stock is not marked as "reduced" anymore. #36903
		                            if ( method_exists( $wc_order, 'set_order_stock_reduced' ) ) {
			                            $wc_order->set_order_stock_reduced( false );
		                            } else {
			                            $wc_order->get_data_store()->set_stock_reduced( $order['post_id'], false );
		                            }
									$wc_order->save();
                                }

                                // Set the WC order's status to Cancelled
                                $cancelled_status = get_option( 'wplister_cancelled_order_status', 'cancelled' );
                                $wc_order->update_status( $cancelled_status, __('Order cancelled on eBay', 'wp-lister-for-ebay' ) );

                                WPLE()->logger->info( 'cancelled_status: '. $cancelled_status );
                                WPLE()->logger->info( 'WC order updated to '. $cancelled_status );

                                // add history record
                                $history_message = 'WooCommerce Order (#'. $order['post_id'] .') status has changed to '. $cancelled_status;
                                $history_details = array( 'order_id' => $order['post_id'], 'new_status' => $cancelled_status );
                                $this->addHistory( $data['order_id'], 'wc_order_status_changed', $history_message, $history_details );
                            }
                        }
                    }

				    do_action( 'wple_ebay_order_cancelled', $data, $order, $order['post_id'] );
                    /*** ## END PRO ## ***/

                    // no further processing required
                    return;
                }
			}

			// skip already processed order listings #49554
            if ( $this->processed_order_listings( $data ) ) {
                WPLE()->logger->info( 'Skipping processListingItem() for this order.' );
                /*** ## BEGIN PRO ## ***/
                // This process needs to continue but skip all line item processing and go straight to
                // the updating of existng WC orders to possibly process refunds #56252
                $this->createOrUpdateWooCommerceOrder( $insert_id, $data );
                /*** ## END PRO ## ***/
                return;
            }

			WPLE()->logger->info( 'Processing order transactions' );
			// process any NEW order line items (as it happens when transactions are combined)
			$tm      = new TransactionsModel();
			$Details = maybe_unserialize( $data['details'] );
			foreach ( $Details->TransactionArray as $Transaction ) {

				// make sure we have a valid transaction ID
				$transaction_id = TransactionsModel::getRealTransactionID( $Transaction->TransactionID, $Transaction->OrderLineItemID );

				// check if we already processed this TransactionID - which is to be expected here
				if ( $existing_transaction = TransactionsModel::getTransactionByTransactionID( $transaction_id ) ) {

					// update transaction status to reflect latest CompleteStatus, LastTimeModified, etc.
					TransactionsModel::updateTransactionStatusFromEbayOrderData( $transaction_id, $data );

					// find previous order record and update it (from status "Active" to "Merged"?)
					// first check if there is a previous (active) order, with a different OrderID, for that existing transaction
					if ( $existing_transaction['order_id'] != $data['order_id'] ) {

						// get previous order record
						$previous_order = self::getOrderByOrderID( $existing_transaction['order_id'] );

						// update CompleteStatus to Merged - and add history record
						self::updateExistingOrderStatusFromNewOrderData( $previous_order, $data, 'Merged' );
					}

					continue;
				}

                /**
                 * Instead of relying on the CompleteStatus value, we should sync stock levels as
                 * soon as the order gets marked as paid
                 */
				// check if order is still active - the default is to skip active orders and not to reduce stock levels
				$sync_incomplete_orders = get_option( 'wplister_sync_incomplete_orders', 0 );
				if ( ! $this->orderIsPaid( $data ) && ! $sync_incomplete_orders ) {
					//WPLE()->logger->info( 'skipped stock reduction for now - status is '.$data['CompleteStatus'].' - order id #'.$data['order_id'] );
					WPLE()->logger->info( 'skipped stock reduction for now - pending payment - order id #'.$data['order_id'] );
					continue;
				}

//                $sync_incomplete_orders = get_option( 'wplister_sync_incomplete_orders', 0 );
//                if ( $data['CompleteStatus'] != 'Completed' && ! $sync_incomplete_orders ) {
//                    WPLE()->logger->info( 'skipped stock reduction for now - status is '.$data['CompleteStatus'].' - order id #'.$data['order_id'] );
//                    continue;
//                }

				// update listing sold quantity and status
				$this->processListingItem( $data['order_id'], $Transaction->Item->ItemID, $Transaction->QuantityPurchased, $data, $Transaction );

				// create transaction record for future reference
				$tm->createTransactionFromEbayOrder( $data, $Transaction );
			}

            /**
             * Same goes for creating orders - use the self::orderIsPaid() method to determine if the order is complete
             */
			// check if order is still active - the default is to skip active orders and not to reduce stock levels - or do any other processing/checking
			$sync_incomplete_orders = get_option( 'wplister_sync_incomplete_orders', 0 );
			if ( ! $this->orderIsPaid( $data ) && ! $sync_incomplete_orders ) {
				WPLE()->logger->info( 'skipped stock reduction checks - pending payment - order id #'.$data['order_id'] );
				return;
			}

//            $sync_incomplete_orders = get_option( 'wplister_sync_incomplete_orders', 0 );
//            if ( $data['CompleteStatus'] != 'Completed' && ! $sync_incomplete_orders ) {
//                WPLE()->logger->info( 'skipped stock reduction checks - status is '.$data['CompleteStatus'].' - order id #'.$data['order_id'] );
//                return;
//            }

	        /*** ## BEGIN PRO ## ***/

			// check for existing history record - to avoid history overflow
			$existing_history_record = self::findHistoryRecord( $data['order_id'], 'stock_reduction_mismatch' );

	        // check if the number of purchased units is different from the total stock units processed
	        if ( ! $existing_history_record && $wpl_order = WPL_InventoryCheck::checkThisOrderForStockReductionsMismatch( $insert_id ) ) {

				// add history record: CHECK FAILED
				$history_message = sprintf( 'Stock reduction mismatch. The number of purchased units (%s) is different from the total stock units processed (%s).', $wpl_order['units_purchased'], $wpl_order['stock_units_reduced'] );
				$history_details = array( 'src' => 'update_order', 'units_purchased' => $wpl_order['units_purchased'], 'stock_units_reduced' => $wpl_order['stock_units_reduced'], 'line_item_count' => $line_item_count );
				$this->addHistory( $data['order_id'], 'stock_reduction_mismatch', $history_message, $history_details, false );

				// send notification - if enabled
				self::notifyAboutOrderStockCheckIssue( $data, 'stock_reduction_mismatch', $history_message, $history_details );
	        }

	        /*** ## END PRO ## ***/

		} else {

			$this->addToReport( 'inserted', $data );
			WPLE()->logger->info( 'insert ebay order #' . $data['order_id'] . ' - LastTimeModified: ' . $data['LastTimeModified'] );

			// create new order
			$result    = $wpdb->insert( $this->tablename, $data );
			$insert_id = $wpdb->insert_id;

			// handle db error
			if ( $result === false ) {
				WPLE()->logger->error( 'insert order failed - MySQL said: '.$wpdb->last_error );
				$this->addToReport( 'error', $data, false, $wpdb->last_error );
				wple_show_message( 'Failed to insert order #'.$data['order_id'].' - MySQL said: '.$wpdb->last_error, 'error' );
				return false;
			}

			// add history record
			$history_message = 'New order '.$data['order_id'].' ('.$data['CompleteStatus'].') with '.$line_item_count.' line items';
			$history_details = array( 'status' => $data['CompleteStatus'], 'line_item_count' => $line_item_count );
			$this->addHistory( $data['order_id'], 'new_order', $history_message, $history_details );


			// process order line items
			$tm      = new TransactionsModel();
			$Details = maybe_unserialize( $data['details'] );
			foreach ( $Details->TransactionArray as $Transaction ) {

				// make sure we have a valid transaction ID
				$transaction_id = TransactionsModel::getRealTransactionID( $Transaction->TransactionID, $Transaction->OrderLineItemID );

				// check if we already processed this TransactionID
				if ( $existing_transaction = TransactionsModel::getTransactionByTransactionID( $transaction_id ) ) {

					// add history record
					$history_message = "Skipped already processed transaction {$transaction_id}";
					$history_details = array( 'ebay_id' => $Transaction->Item->ItemID );
					$this->addHistory( $data['order_id'], 'skipped_transaction', $history_message, $history_details );

					// update transaction status to reflect latest CompleteStatus, LastTimeModified, etc.
					TransactionsModel::updateTransactionStatusFromEbayOrderData( $transaction_id, $data );

					// find previous order record and update it (from status "Active" to "Merged"?)
					// first check if there is a previous (active) order, with a different OrderID, for that existing transaction
					if ( $existing_transaction['order_id'] != $data['order_id'] ) {

						// get previous order record
						$previous_order = self::getOrderByOrderID( $existing_transaction['order_id'] );

						// update CompleteStatus to Merged - and add history record
						self::updateExistingOrderStatusFromNewOrderData( $previous_order, $data, 'Merged' );
					}

					continue;
				}

                /**
                 * Instead of relying on the CompleteStatus value, we should sync stock levels as
                 * soon as the order gets marked as paid
                 */
                // check if order is still active - the default is to skip active orders and not to reduce stock levels
                $sync_incomplete_orders = get_option( 'wplister_sync_incomplete_orders', 0 );
                if ( ! $this->orderIsPaid( $data ) && ! $sync_incomplete_orders ) {
                    //WPLE()->logger->info( 'skipped stock reduction for now - status is '.$data['CompleteStatus'].' - order id #'.$data['order_id'] );
                    WPLE()->logger->info( 'skipped stock reduction for now - pending payment - order id #'.$data['order_id'] );
                    continue;
                }

//				// check if order is still active - the default is to skip active orders and not to reduce stock levels
//				$sync_incomplete_orders = get_option( 'wplister_sync_incomplete_orders', 0 );
//				if ( $data['CompleteStatus'] != 'Completed' && ! $sync_incomplete_orders ) {
//					WPLE()->logger->info( 'skipped stock reduction for now - status is '.$data['CompleteStatus'].' - order id #'.$data['order_id'] );
//					continue;
//				}

				// update listing sold quantity and status
				$this->processListingItem( $data['order_id'], $Transaction->Item->ItemID, $Transaction->QuantityPurchased, $data, $Transaction );

				// create transaction record for future reference
				$tm->createTransactionFromEbayOrder( $data, $Transaction );
			}

            /**
             * Instead of relying on the CompleteStatus value, we should sync stock levels as
             * soon as the order gets marked as paid
             */
            // check if order is still active - the default is to skip active orders and not to reduce stock levels
            $sync_incomplete_orders = get_option( 'wplister_sync_incomplete_orders', 0 );
            if ( ! $this->orderIsPaid( $data ) && ! $sync_incomplete_orders ) {
                //WPLE()->logger->info( 'skipped stock reduction for now - status is '.$data['CompleteStatus'].' - order id #'.$data['order_id'] );
                WPLE()->logger->info( 'skipped stock reduction for now - pending payment - order id #'.$data['order_id'] );
                return;
            }
//			// check if order is still active - the default is to skip active orders and not to reduce stock levels - or do any other processing/checking
//			$sync_incomplete_orders = get_option( 'wplister_sync_incomplete_orders', 0 );
//			if ( $data['CompleteStatus'] != 'Completed' && ! $sync_incomplete_orders ) {
//				WPLE()->logger->info( 'skipped stock reduction checks - status is '.$data['CompleteStatus'].' - order id #'.$data['order_id'] );
//				return;
//			}


	        /*** ## BEGIN PRO ## ***/

			// add history record
			$history_message = "Stock processing completed";
			$history_details = array( 'transactions_count' => count($Details->TransactionArray) );
			$this->addHistory( $data['order_id'], 'stock_processing_complete', $history_message, $history_details );

	        // check if the number of purchased units is different from the total stock units processed
	        if ( $wpl_order = WPL_InventoryCheck::checkThisOrderForStockReductionsMismatch( $insert_id ) ) {

				// add history record: CHECK FAILED
				$history_message = sprintf( 'Stock reduction mismatch. The number of purchased units (%s) is different from the total stock units processed (%s).', $wpl_order['units_purchased'], $wpl_order['stock_units_reduced'] );
				$history_details = array( 'src' => 'create_order', 'units_purchased' => $wpl_order['units_purchased'], 'stock_units_reduced' => $wpl_order['stock_units_reduced'], 'line_item_count' => $line_item_count );
				$this->addHistory( $data['order_id'], 'stock_reduction_mismatch', $history_message, $history_details, false );

				// send notification - if enabled
				self::notifyAboutOrderStockCheckIssue( $data, 'stock_reduction_mismatch', $history_message, $history_details );

	        } else {

				// add history record: CHECK PASSED
				$history_message = 'Stock reduction validation check: OK'.' ('.$line_item_count.' line items)';
				$history_details = array( 'ts' => time(), 'src' => 'create_order', 'line_item_count' => $line_item_count );
				$this->addHistory( $data['order_id'], 'stock_reduction_validated', $history_message, $history_details );

	        }

	        /*** ## END PRO ## ***/


		} // if ( $order ) {} else {...}


	    /*** ## BEGIN PRO ## ***/

		// create or update order from order - if enabled
        $this->createOrUpdateWooCommerceOrder( $insert_id, $data );

        /*** ## END PRO ## ***/


	} // insertOrUpdate()

    /**
     * Listings are considered processed when the matching WC order's status is Completed
     *
     * @param array $data
     * @return bool
     */
    public function processed_order_listings( $data ) {
	    global $wpdb;

	    $processed  = false;
	    $order      = $this->getOrderByOrderID( $data['order_id'] );

	    if ( $order['post_id'] ) {
	        $wc_order = wc_get_order( $order['post_id'] );

	        if ( $wc_order && $wc_order->get_status() == 'completed' ) {
	            $processed = true;
            }
        }

	    return $processed;
    }


	function notifyAboutOrderStockCheckIssue( $data, $error_key, $history_message, $history_details ) {

		// do nothing if not enabled
		if ( get_option('wplister_enable_order_notify',0) == 0 ) return;

		// build link to order in WP-Lister
		$ebay_order_id  = $data['order_id'];
		$wple_order_url = admin_url( "admin.php" ).'?page=wplister-orders&s=' . $ebay_order_id;

		// get domain
		$urlparts    = parse_url(home_url());
		$site_domain = $urlparts['host'];

		// build email
		$to          = get_option( 'wplister_notify_custom_email', get_bloginfo( 'admin_email' ) );
		$subject     = 'WPLE Notification - order #' . $ebay_order_id .' - '. $site_domain;
		$from_name   = 'WP-Lister for eBay';
		$from_email  = 'wplister@'.$site_domain;
		$headers     = 'From: '.$from_name.' <'.$from_email.'>' . "\r\n";
		$attachments = array();

		$message  = '';
		$message .= 'WP-Lister has detected an issue with an eBay order that requires your attention:<br><br>';
		$message .= 'Order: '.$ebay_order_id.'<br>';
		$message .= 'Issue: '.$error_key.'<br>';
		$message .= 'Link: '.'<a href="'.$wple_order_url.'">'.$wple_order_url.'</a>'.'<br>';
		$message .= 'Message: <br><br>'.nl2br($history_message).'<br>';
		$message .= '<br>';
		$message .= 'Please check the order in question and/or report this issue to support.'.'<br>';
		$message .= '<br>';
		$message .= 'Thanks in advance!'.'<br>';
		$message .= 'sincerely, WP-Lister Pro for eBay on '.$site_domain.'<br>';

		// send email as html
		add_filter('wp_mail_content_type',function() { return "text/html"; });

		wp_mail($to, $subject, $message, $headers, $attachments);

	} // notifyAboutOrderStockCheckIssue()

    /*** ## BEGIN PRO ## ***/

	// create or update WooCommere order
	function createOrUpdateWooCommerceOrder( $order_id, $data ) {
		global $wpdb;

		// check if order creation is enabled
		if ( get_option( 'wplister_create_orders' ) == '0' ) return;

		do_action( 'wple_create_update_wc_order', $order_id, $data );

		// maybe skip orders containing only foreign items
		if ( get_option( 'wplister_skip_foreign_item_orders' ) ) {

			// check if order line items exists in WP-Lister
			$order_items     = maybe_unserialize( $data['items'] );
			$has_known_items = false;
			foreach ( $order_items as $item ) {
				$listing_id = $wpdb->get_var( $wpdb->prepare("SELECT id FROM {$wpdb->prefix}ebay_auctions WHERE ebay_id = %s AND status <> 'archived'", $item['item_id'] ) );
				if ( $listing_id ) $has_known_items = true;
			}

			// skip if order contains no known items
			if ( ! $has_known_items ) {
				// $history_message = "Not creating order in WooCommerce - no known items found"; // don't add history row - or check first whether it was already added!
				// $this->addHistory( $data['order_id'], 'skipped_create_order', $history_message, array() );
				WPLE()->logger->info( 'No known items found, skipped creating order in WooCommerce - order id #'.$data['order_id'] );
				return;
			}

		}

		// check if WooCommerce order already exists
		// TODO: check if order has been deleted or moved to trash
		$ordersModel   = new EbayOrdersModel();
		$ebay_order    = $ordersModel->getItem( $order_id );
		$ebay_order_id = $data['order_id']; // eg. 161100001960-1007900745006
		// $woo_order_exists = $ebay_order['post_id'] != '' ? true : false;

		WPLE()->logger->info( 'checking if WooCommerce order exists for eBay order '.$ebay_order_id.' - order_id: '.$order_id );
		$woo_order_exists = false;
		$debug_log        = "\ndebug log:";

		// first check if an order exists for the stored post_id
		if ( $ebay_order['post_id'] ) {
			$debug_log .= "check 1: check if order exists for post_id: ".$ebay_order['post_id']."\n";
			WPLE()->logger->info( 'found possible woo order #'.$ebay_order['post_id'].' by reference' );
			if ( self::wooOrderExists( $ebay_order['post_id'] ) ) {
				$woo_order_exists = true;
				$post_id = $ebay_order['post_id'];
				WPLE()->logger->info( 'found existing woo order #'.$post_id.' by reference' );
				$debug_log .= "check 1: order $post_id exists \n";
			}
		};

		// if nothing found, check for an order with the same _ebay_order_id
		if ( ! $woo_order_exists ) {
			$debug_log .= "check 2: search for order in wp_postmeta: $ebay_order_id \n";

			$post_id = $wpdb->get_var( $wpdb->prepare("
				SELECT post_id FROM {$wpdb->prefix}postmeta
				WHERE meta_key   = '_ebay_order_id'
				  AND meta_value = %s
				ORDER BY post_id ASC
			", $ebay_order_id ) );
			$debug_log .= "check 2: check if order exists for post_id: $post_id \n";
			WPLE()->logger->info( 'found possible woo order #'.$post_id.' by ebay_order_id '.$ebay_order_id );
			if ( is_numeric( $post_id ) && self::wooOrderExists( $post_id ) ) {
				$woo_order_exists = true;
				WPLE()->logger->info( 'found existing woo order #'.$post_id.' by ebay_order_id '.$ebay_order_id );
				$debug_log .= "check 2: order $post_id exists \n";
			}
		}

		// if still nothing found, check for an order with the same _ebay_transaction_id
		if ( ! $woo_order_exists ) {
			$debug_log .= "check 3: still nothing found... \n";

			// check if ebay_order_id has the format ItemID-TransactionID (is not a combined order)
			if ( strpos( $ebay_order_id, '-' ) ) {
				list( $ebay_item_id, $ebay_transaction_id ) = explode( '-', $ebay_order_id );
				$debug_log .= "check 3: search for order in wp_postmeta by transaction_id: $ebay_transaction_id \n";

				$post_id = $wpdb->get_var( $wpdb->prepare("
					SELECT post_id FROM {$wpdb->prefix}postmeta
					WHERE meta_key   = '_ebay_transaction_id'
					  AND meta_value = %s
					ORDER BY post_id ASC
				", $ebay_transaction_id ) );
				$debug_log .= "check 3: check if order exists for post_id: $post_id \n";
				WPLE()->logger->info( 'found possible woo order #'.$post_id.' by ebay_transaction_id '.$ebay_transaction_id );

				if ( is_numeric( $post_id ) && self::wooOrderExists( $post_id ) ) {
					$woo_order_exists = true;
					WPLE()->logger->info( 'found existing woo order #'.$post_id.' by ebay_transaction_id '.$ebay_transaction_id );
					$debug_log .= "check 3: order $post_id exists \n";
				}

			}
		}

		// Check to make sure the order date is not before the ignore_orders_before_ts setting #42330
        if ( !$woo_order_exists && $ts = get_option('wplister_ignore_orders_before_ts', false) ) {
            $order_date_ts = mysql2date( 'U', $ebay_order['date_created'] );

            if ( $order_date_ts < $ts ) {
                WPLE()->logger->info( 'Ignoring order due to the Ignore Orders Before TS setting' );
                return;
            }
        }

        // Check to make sure the order date is not before the ignore_orders_before_ts setting #42330
        $order_updates_age_limit = get_option( 'wplister_filter_orders_older_than', 0 );
        WPLE()->logger->info( 'filter_orders_older_than: '. $order_updates_age_limit );
        if ( $order_updates_age_limit > 0 ) {
            $now_dt = new DateTime();
            $order_dt = date_create( $ebay_order['date_created'] );
            $diff = $now_dt->diff( $order_dt, true );

            $months = round($diff->days/30);

            WPLE()->logger->info( 'Now: '. $now_dt->format('r') );
            WPLE()->logger->info( 'Order: '. $order_dt->format('r') );
            WPLE()->logger->info( 'Diff: '. $months .' months' );
            if ( $months > $order_updates_age_limit ) {
                WPLE()->logger->info( 'Ignoring order update due to the Filter Orders Order Than setting.' );
                return;
            }

        }


		// update or create
		if ( $woo_order_exists ) {

			// if we found a post_id by now but $ebay_order['post_id'] is empty or different, let's update the order record
			if ( ! $ebay_order['post_id'] || $ebay_order['post_id'] != $post_id )
				$this->updateWpOrderID( $order_id, $post_id );

			// update order from order
			$wob = new WPL_WooOrderBuilder();
			$order_post_id = $wob->updateOrderFromEbayOrder( $order_id, $post_id );

			WPLE()->logger->info( 'updated order #'.$order_post_id.' - from ebay order id #'.$data['order_id'] );

		} else {

			// check if order is completed - and if it matters
//			if ( $data['CompleteStatus'] != 'Completed') {
//				if ( get_option( 'wplister_create_incomplete_orders', 0 ) == 0 ) {
//					WPLE()->logger->info( 'skipped order creation as status is '.$data['CompleteStatus'].' - order id #'.$data['order_id'] );
//					return;
//				}
//			}

            // Check if order is paid (completed)
            if ( ! $this->orderIsPaid( $data ) ){
                if ( get_option( 'wplister_create_incomplete_orders', 0 ) == 0 ) {
					WPLE()->logger->info( 'skipped order creation as order is pending payment - order id #'.$data['order_id'] );
					return;
				}
            }

			$debug_log .= "summary: order $order_id does not exist! \n";
			if ( isset( $post_id ) )
				$debug_log .= "note: an invalid post_id $post_id was found, but did not exist. \n";

			// create order from order
			$wob = new WPL_WooOrderBuilder();
			$order_post_id = $wob->createWooOrderFromEbayOrder( $order_id );

			WPLE()->logger->info( 'created woo order #'.$order_post_id.' - from ebay order id #'.$data['order_id'] );

			// add history record
			$history_message = "Order #$order_post_id was created";
			$history_details = array( 'post_id' => $order_post_id, 'ebay_order_id' => $ebay_order_id );
			$history_details['debug_log'] = $debug_log; 							 // debug data
			$this->addHistory( $data['order_id'], 'create_order', $history_message, $history_details );

		}

	} // createOrUpdateWooCommerceOrder()

    /*** ## END PRO ## ***/


	// check if woocommcer order exists and has not been moved to the trash
	static function wooOrderExists( $post_id ) {

        $_order = wc_get_order( $post_id );

		if ( $_order ) {

			if ( $_order->get_status() == 'trash' ) return false;

			return wple_get_order_meta( $_order, 'id' );

		}

		return false;
	} // wooOrderExists()


	// update listing sold quantity and status
	function processListingItem( $order_id, $ebay_id, $quantity_purchased, $data, $Transaction ) {
		global $wpdb;
		$has_been_replenished = false;

		WPLE()->logger->info( 'processListingItem #'. $ebay_id .' for order #'. $order_id );

		// build $VariationSpecifics array
		$VariationSpecifics = array();
        if ( is_object( @$Transaction->Variation ) ) {
			foreach ($Transaction->Variation->VariationSpecifics as $spec) {
                $VariationSpecifics[ $spec->Name ] = $spec->Value[0];
            }
        }
        WPLE()->logger->info( 'VariationSpecifics: '. print_r( $VariationSpecifics, 1 ) );

		do_action( 'wple_before_process_listing_item', $ebay_id, $order_id, $quantity_purchased, $data, $VariationSpecifics, $Transaction );

		// check if this listing exists in WP-Lister
        $listing_sku = $Transaction->Item->SKU;
        WPLE()->logger->info( 'Item->SKU: '. $listing_sku );

        // Consider variable products where the parent has no SKU
        if ( empty( $listing_sku ) && is_object( @$Transaction->Variation ) ) {
            $listing_sku = $Transaction->Variation->SKU;
        }
        WPLE()->logger->info( 'listing_sku: '. $listing_sku );

        $listing_id = $this->getListingIdFromEbayId( $ebay_id, $listing_sku, $order_id );
        WPLE()->logger->info( 'Found listing_id: '. $listing_id );

        /*if ( ! $listing_id && get_option( 'wplister_match_sales_by_sku', 0 ) == 1 ) {
            // If no listing is found using the eBay Item ID, check if we need to match using the SKU
            $listing_sku = $Transaction->Item->SKU;

            // Consider variable products where the parent has no SKU
            if ( empty( $listing_sku ) && is_object( @$Transaction->Variation ) ) {
                $listing_sku = $Transaction->Variation->SKU;
            }
            $listingItem = WPLE_ListingQueryHelper::findItemBySku( $listing_sku, true );

            if ( $listingItem ) {
                $listing_id = $listingItem->id;

                $history_message = "Matched SKU ({$Transaction->Item->SKU}) to Listing #$listing_id";
                $history_details = array( 'ebay_id' => $ebay_id );
                $this->addHistory( $order_id, 'match_sku', $history_message, $history_details );
            }
        }*/

        if ( ! $listing_id ) {
            $history_message = "Skipped foreign item #{$ebay_id}";
            $history_details = array( 'ebay_id' => $ebay_id );
            $this->addHistory( $order_id, 'skipped_item', $history_message, $history_details );
            return;
        }

		// get current values from db
		$quantity_total = $wpdb->get_var( $wpdb->prepare("SELECT quantity      FROM {$wpdb->prefix}ebay_auctions WHERE id = %s", $listing_id ) );
		$quantity_sold  = $wpdb->get_var( $wpdb->prepare("SELECT quantity_sold FROM {$wpdb->prefix}ebay_auctions WHERE id = %s", $listing_id ) );

		// increase the listing's quantity_sold
		$quantity_sold = $quantity_sold + $quantity_purchased;
		$wpdb->update( $wpdb->prefix.'ebay_auctions',
			array( 'quantity_sold' => $quantity_sold ),
			array( 'id' => $listing_id )
		);

		// add history record
		$history_message = "Sold quantity increased by $quantity_purchased for listing #{$listing_id} ({$ebay_id}) - sold $quantity_sold";
		$history_details = array( 'listing_id' => $listing_id, 'ebay_id' => $ebay_id, 'quantity_sold' => $quantity_sold, 'quantity_total' => $quantity_total );
		$this->addHistory( $order_id, 'reduce_wple_stock', $history_message, $history_details );


        /*** ## BEGIN PRO ## ***/

		// reduce product stock - if enabled
		if ( get_option( 'wplister_handle_stock' ) == '1' ) {

            if ( get_option( 'wplister_match_sales_by_sku', 0 ) == 1 ) {
                // Get local product from the listing's SKU
                $listing_sku = $Transaction->Item->SKU;

                // Consider variable products where the parent has no SKU
                if ( empty( $listing_sku ) && is_object( @$Transaction->Variation ) ) {
                    $listing_sku = $Transaction->Variation->SKU;
                }

                if ( $listing_sku ) {
                    $listingItem = WPLE_ListingQueryHelper::findItemBySku( $listing_sku, true );

                    if ( $listingItem ) {
                        if ( $listingItem->status == 'archived' ) {
                            $history_message = "Skipped archived listing #{$ebay_id}";
                            $history_details = array( 'ebay_id' => $ebay_id, 'sku' => $listing_sku );
                            $this->addHistory( $order_id, 'skipped_item', $history_message, $history_details );
                            return;
                        } else {
                            $post_id = $listingItem->post_id;

                            // add history record
                            $history_message = "Found product #$post_id for eBay SKU ". $listing_sku;
                            $history_details = array( 'product_id' => $post_id, 'ebay_id' => $ebay_id, 'sku' => $listing_sku );
                            $this->addHistory( $order_id, 'product_query_result', $history_message, $history_details );
                        }
                    } else {
                        $history_message = "No matching SKU found. Skipped foreign item #{$ebay_id}";
                        $history_details = array( 'ebay_id' => $ebay_id, 'sku' => $listing_sku );
                        $this->addHistory( $order_id, 'skipped_item', $history_message, $history_details );
                        return;
                    }
                } else {
                    $history_message = "No listing SKU found. Skipping";
                    $history_details = array( 'ebay_id' => $ebay_id, 'sku' => $listing_sku );
                    $this->addHistory( $order_id, 'skipped_item', $history_message, $history_details );
                    return;
                }
            } else {
                // get post_id for listing by ebay_id
                $post_id = $wpdb->get_var( $wpdb->prepare("SELECT post_id FROM {$wpdb->prefix}ebay_auctions WHERE ebay_id = %s AND status <> 'archived'", $ebay_id ) );

                // add history record
                $history_message = "Found product #$post_id for eBay item $ebay_id";
                $history_details = array( 'product_id' => $post_id, 'ebay_id' => $ebay_id );
                $this->addHistory( $order_id, 'product_query_result', $history_message, $history_details );
            }

			// handle variations
			if ( sizeof( $VariationSpecifics ) > 0 ) {
				$VariationSKU = $Transaction->Variation->SKU;
				$variation_id = ProductWrapper::findVariationID( $post_id, $VariationSpecifics, $VariationSKU );
			} else {
				$VariationSKU = null;
				$variation_id = null;
			}

			// add history record - track original stock level
			$actual_post_id  = $variation_id ? $variation_id : $post_id;
			$oldstock_before = ProductWrapper::getStock( $actual_post_id );
			$history_message = "Current stock level for product #$actual_post_id: $oldstock_before";
			$history_details = array( 'product_id' => $post_id, 'variation_id' => $variation_id, 'oldstock' => $oldstock_before );
			$this->addHistory( $order_id, 'check_stock_before', $history_message, $history_details );

			$reduce_stock_by = apply_filters( 'wple_order_item_quantity_purchased', $quantity_purchased, $post_id, $order_id, $variation_id );

			// reduce product stock
			$newstock = ProductWrapper::decreaseStockBy( $post_id, $reduce_stock_by, $order_id, $variation_id );

            // update item variation quantity cache
            $listing_id = WPLE_ListingQueryHelper::getListingIDFromPostID( $post_id );

            if ( $listing_id ) {
	            $listing = new WPLab\Ebay\Listings\Listing( $listing_id );
                ListingsModel::setListingVariationsQuantity( $post_id, $listing );
            }

            if ( $newstock !== false ) {
                WPLE()->logger->info( 'reduced product stock for #'.$post_id.' / '.$variation_id.' by '.$quantity_purchased.' - new qty: '.$newstock );
                do_action( 'wple_product_stock_decreased', $post_id, $quantity_purchased, $order_id, $variation_id );

                // add history record (detailed)
                $history_message = "Stock reduced by $quantity_purchased for product #$post_id ($actual_post_id) - new stock is $newstock";
                $history_details = array(
                    'product_id'         => $post_id,
                    'variation_id'       => $variation_id,
                    'post_id'            => $actual_post_id,
                    'newstock'           => $newstock,
                    'quantity_purchased' => $quantity_purchased,
                    'VariationSpecifics' => $VariationSpecifics,
                    'VariationSKU'       => $VariationSKU );
                $this->addHistory( $order_id, 'reduce_stock', $history_message, $history_details );

                // notify WP-Lister for Amazon (and other plugins)
                do_action( 'wplister_inventory_status_changed', $post_id );

                // add history record - double check new stock level
                $newstock_checked = ProductWrapper::getStock( $actual_post_id );
                $history_message = "Verified new stock for product #$actual_post_id: $newstock_checked";
                $history_details = array( 'product_id' => $post_id, 'variation_id' => $variation_id, 'newstock' => $newstock_checked );
                $this->addHistory( $order_id, 'check_stock_after', $history_message, $history_details );
            }
		} else {
            WPLE()->logger->info( 'wplister_handle_stock value: '. get_option( 'wplister_handle_stock' ) );
			// add history record - sync sales is disabled
			$history_message = "Synchronize sales is disabled - stock will not be reduced";
			$this->addHistory( $order_id, 'inventory_sync_off', $history_message, array() );

		}

		$listing         = ListingsModel::getItem( $listing_id );
		$post_id         = $listing['post_id'];
		$profile_details = $listing['profile_data']['details'];
		WPLE()->logger->info( 'Checking for other listings for product #'. $listing['post_id'] );


		// flag to make sure that the wplister_revise_inventory_status is called only once
        $revise_status_called = false;

		// handle auto_replenish option
		if ( isset( $profile_details['auto_replenish'], $profile_details['custom_quantity_enabled'] ) && $profile_details['custom_quantity_enabled'] && $profile_details['auto_replenish'] ) {
		    WPLE()->logger->info( 'Auto-replenish enabled in the profile' );

	        // get max_quantity from profile
    	    $max_quantity = ( isset( $profile_details['max_quantity'] ) && intval( $profile_details['max_quantity'] )  > 0 ) ? $profile_details['max_quantity'] : false ;

    	    // revise inventory status to replenish stock on eBay
    	    if ( $max_quantity ) {
    	        // This forces WPLE to push the listing to eBay regardless if the cache matches the new stocks for variations #28870
                add_filter( 'wple_filter_unchanged_variations', '__return_false' );

                // second parameter tells WPLE to disregard the cached quantities
				do_action( 'wplister_revise_inventory_status', $post_id, true );
				$revise_status_called = true;
				WPLE()->logger->info( 'stock level replenished for product '.$post_id);

				// add history record
				$history_message = "Stock level replenished for product #$post_id - max qty: $max_quantity";
				$history_details = array( 'product_id' => $post_id, 'max_quantity' => $max_quantity );
				$this->addHistory( $order_id, 'replenish_stock', $history_message, $history_details );

				// prevent listing from being marked as sold
				$quantity_total += $quantity_purchased;
				$has_been_replenished = true;
    	    }

		} // if auto_replenish

        // make sure wplister_revise_inventory_status is called for products
        // that are listed on multiple ebay sites
        if ( ! $revise_status_called ) {
            $product_listings = WPLE_ListingQueryHelper::getAllListingsFromPostID( $post_id );
            WPLE()->logger->info( 'getAllListingsFromPostID: '. count( $product_listings ) );

            if ( count( $product_listings ) > 1 ) {
                WPLE()->logger->info('Revising inventory status for listings linked to #'. $post_id);
                WPLE()->logger->info( print_r( $product_listings, true ) );
                do_action( 'wplister_revise_inventory_status', $post_id, true );
                WPLE()->logger->info( 'stock level revised for product ' . $post_id );
            }
        }

        /*** ## END PRO ## ***/

		// mark listing as sold when last item is sold - unless Out Of Stock Control (oosc) is enabled
        if ( ! ListingsModel::thisAccountUsesOutOfStockControl( $data['account_id'] ) ) {
			if ( $quantity_sold == $quantity_total && ! $has_been_replenished ) {

                // make sure this product is out of stock before we mark listing as sold - free version excluded
                $listing_item = ListingsModel::getItem( $listing_id );
                if ( WPLE_IS_LITE_VERSION || ListingsModel::checkStockLevel( $listing_item ) == false ) {

					$wpdb->update( $wpdb->prefix.'ebay_auctions',
						array( 'status' => 'sold', 'date_finished' => $data['date_created'], ),
						array( 'ebay_id' => $ebay_id )
					);
					WPLE()->logger->info( 'marked item #'.$ebay_id.' as SOLD ');

				}
			}
        }

	} // processListingItem()

    /**
     * @param $order_id eBay Order ID
     */
    public function revertOrderStockChanges( $order_id ) {
	    global $wpdb;

	    WPLE()->logger->info( 'revertOrderStockChanges for Order #'. $order_id );

        $order = $this->getOrderByOrderID( $order_id );

        // process any NEW order line items (as it happens when transactions are combined)
        $tm      = new TransactionsModel();
        $Details = maybe_unserialize( $order['details'] );

        foreach ( $Details->TransactionArray as $Transaction ) {

			$ebay_id = $Transaction->Item->ItemID;
			$sku     = $Transaction->Item->SKU;

            // build $VariationSpecifics array
            $VariationSpecifics = array();
            if ( is_object( @$Transaction->Variation ) ) {
                foreach ($Transaction->Variation->VariationSpecifics as $spec) {
                    $VariationSpecifics[ $spec->Name ] = $spec->Value[0];
                }
            }

            // Consider variable products where the parent has no SKU
            if ( empty( $sku ) && is_object( @$Transaction->Variation ) ) {
                $sku = $Transaction->Variation->SKU;
            }
			$quantity_purchased = $Transaction->QuantityPurchased;
			$listing_id         = $this->getListingIdFromEbayId( $ebay_id, $sku );

            WPLE()->logger->info( 'Processing eBay #'. $ebay_id .' (listing: '. $listing_id .')' );

            if ( ! $listing_id ) {
                $history_message = "Skipped reverting stock on foreign item #{$ebay_id}";
                $history_details = array( 'ebay_id' => $ebay_id );
                $this->addHistory( $order_id, 'skipped_item', $history_message, $history_details );
                WPLE()->logger->info( 'Skipped foreign item #'. $ebay_id );
                continue;
            }

            // get current values from db
            $quantity_total = $wpdb->get_var( $wpdb->prepare("SELECT quantity      FROM {$wpdb->prefix}ebay_auctions WHERE id = %s", $listing_id ) );
            $quantity_sold  = $wpdb->get_var( $wpdb->prepare("SELECT quantity_sold FROM {$wpdb->prefix}ebay_auctions WHERE id = %s", $listing_id ) );

            // deduct the quantity purchased from the sold total
            $quantity_sold = $quantity_sold - $quantity_purchased;
            $wpdb->update( $wpdb->prefix.'ebay_auctions',
                array( 'quantity_sold' => $quantity_sold ),
                array( 'id' => $listing_id )
            );

            // add history record
            $history_message = "Sold quantity reverted to $quantity_sold (deducted: $quantity_purchased) for listing #{$listing_id} ({$ebay_id}) - order cancelled";
            $history_details = array( 'listing_id' => $listing_id, 'ebay_id' => $ebay_id, 'quantity_sold' => $quantity_sold, 'quantity_total' => $quantity_total );
            $this->addHistory( $order_id, 'revert_wple_stock', $history_message, $history_details );

            /*** ## BEGIN PRO ## ***/

            // revert product stock - if enabled
            if ( get_option( 'wplister_handle_stock' ) == '1' ) {

                if ( get_option( 'wplister_match_sales_by_sku', 0 ) == 1 ) {
                    $post_id = 0;

                    if ( $sku ) {
                        $listingItem = WPLE_ListingQueryHelper::findItemBySku( $sku, true );

                        if ( $listingItem ) {
                            $post_id = $listingItem->post_id;

                            // add history record
                            $history_message = "Found product #$post_id for eBay SKU ". $sku;
                            $history_details = array( 'product_id' => $post_id, 'ebay_id' => $ebay_id, 'sku' => $sku );
                            $this->addHistory( $order_id, 'product_query_result', $history_message, $history_details );
                        }
                    } else {
                        // add history record
                        $history_message = "Skipping item ($ebay_id) with an empty SKU";
                        $history_details = array( 'product_id' => $post_id, 'ebay_id' => $ebay_id, 'sku' => $sku );
                        $this->addHistory( $order_id, 'product_query_result', $history_message, $history_details );
                    }
                } else {
                    // get post_id for listing by ebay_id
                    $post_id = $wpdb->get_var( $wpdb->prepare("SELECT post_id FROM {$wpdb->prefix}ebay_auctions WHERE ebay_id = %s", $ebay_id ) );

                    // add history record
                    $history_message = "Found product #$post_id for eBay item $ebay_id";
                    $history_details = array( 'product_id' => $post_id, 'ebay_id' => $ebay_id );
                    $this->addHistory( $order_id, 'product_query_result', $history_message, $history_details );
                }

                // handle variations
                if ( $post_id && sizeof( $VariationSpecifics ) > 0 ) {
                    $VariationSKU = $Transaction->Variation->SKU;
                    $variation_id = ProductWrapper::findVariationID( $post_id, $VariationSpecifics, $VariationSKU );
                } else {
                    $VariationSKU = null;
                    $variation_id = null;
                }

                // add history record - track original stock level
                $actual_post_id  = $variation_id ? $variation_id : $post_id;
                $oldstock_before = ProductWrapper::getStock( $actual_post_id );
                $history_message = "Current stock level for product #$actual_post_id: $oldstock_before";
                $history_details = array( 'product_id' => $post_id, 'variation_id' => $variation_id, 'oldstock' => $oldstock_before );
                $this->addHistory( $order_id, 'check_stock_before', $history_message, $history_details );

                // revert product stock
                $newstock = ProductWrapper::increaseStockBy( $post_id, $quantity_purchased, $order_id, $variation_id );

                // update item variation quantity cache
                if ( $listing_id ) {
					$listing = new WPLab\Ebay\Listings\Listing( $listing_id );
                    ListingsModel::setListingVariationsQuantity( $post_id, $listing );
                }

                if ( $newstock !== false ) {
                    WPLE()->logger->info( 'reverted product stock for #'.$post_id.' / '.$variation_id.' by '.$quantity_purchased.' - new qty: '.$newstock );
                    do_action( 'wple_product_stock_increased', $post_id, $quantity_purchased, $order_id, $variation_id );

                    // add history record (detailed)
                    $history_message = "Stock increased by $quantity_purchased for product #$post_id ($actual_post_id) - new stock is $newstock";
                    $history_details = array(
                        'product_id'         => $post_id,
                        'variation_id'       => $variation_id,
                        'post_id'            => $actual_post_id,
                        'newstock'           => $newstock,
                        'quantity_purchased' => $quantity_purchased,
                        'VariationSpecifics' => $VariationSpecifics,
                        'VariationSKU'       => $VariationSKU );
                    $this->addHistory( $order_id, 'revert_stock', $history_message, $history_details );

                    // notify WP-Lister for Amazon (and other plugins)
                    do_action( 'wplister_inventory_status_changed', $post_id );

                    // add history record - double check new stock level
                    $newstock_checked = ProductWrapper::getStock( $actual_post_id );
                    $history_message = "Verified new stock for product #$actual_post_id: $newstock_checked";
                    $history_details = array( 'product_id' => $post_id, 'variation_id' => $variation_id, 'newstock' => $newstock_checked );
                    $this->addHistory( $order_id, 'check_stock_after', $history_message, $history_details );
                }

            } else {

                // add history record - sync sales is disabled
                $history_message = "Synchronize sales is disabled - stock will not be reverted";
                $this->addHistory( $order_id, 'inventory_sync_off', $history_message, array() );

            }
            /*** ## END PRO ## ***/

        } // foreach ( $Transaction )

    } // revertOrderStockChanges()


	// add order history entry
	function addHistory( $order_id, $action, $msg, $details = array(), $success = true ) {
		global $wpdb;
		$table = $wpdb->prefix . self::TABLENAME;

		// load history
		$history = self::loadHistory( $order_id );

		// build history record
		$record = new stdClass();
		$record->action  = $action;
		$record->msg     = $msg;
		$record->details = $details;
		$record->success = $success;
		$record->time    = time();

		// add record
		$history[] = $record;

		// update history
		$history = serialize( $history );
		$wpdb->query( $wpdb->prepare("
			UPDATE $table
			SET history    = %s
			WHERE order_id = %s
		", $history, $order_id ) );

	}

	// add order history entry
	static function loadHistory( $order_id ) {
		global $wpdb;
		$table = $wpdb->prefix . self::TABLENAME;

		// load history
		$history = $wpdb->get_var( $wpdb->prepare("
			SELECT history
			FROM $table
			WHERE order_id = %s
		", $order_id ) );

		// init with empty array
		$history = maybe_unserialize( $history );
		if ( ! $history ) $history = array();

		// prevent fatal error if $history is not an array
		if ( ! is_array( $history ) ) {
			WPLE()->logger->error( "invalid history value in EbayOrdersModel::addHistory(): ".$history);

			// build history record
			$rec = new stdClass();
			$rec->action  = 'reset_history';
			$rec->msg     = 'Corrupted history data was cleared';
			$rec->details = array();
			$rec->success = false;
			$rec->time    = time();

			$history = array();
			$history[] = $rec;
		}

		return $history;
	} // loadHistory()


	// check order for history record with specific action key
	function findHistoryRecord( $order_id, $action, $details = array() ) {

		// load history
		$history = self::loadHistory( $order_id );

		// loop records
		foreach ( $history as $rec ) {
			if ( $rec->action == $action ) {
				return $rec;
			}
		}

		return false;
	} // findHistoryRecord()


	function mapItemDetailToDB( $Detail ) {
		//#type $Detail OrderType

		$data['date_created']              = self::convertEbayDateToSql( $Detail->CreatedTime );
		$data['LastTimeModified']          = self::convertEbayDateToSql( $Detail->CheckoutStatus->LastModifiedTime );

		$data['order_id']            	   = $Detail->OrderID;
		$data['total']                     = $Detail->Total->value;
		$data['currency']                  = $Detail->Total->attributeValues['currencyID'];
		$data['buyer_userid']              = $Detail->BuyerUserID;

		$data['CompleteStatus']            = $Detail->OrderStatus;
		$data['eBayPaymentStatus']         = $Detail->CheckoutStatus->eBayPaymentStatus;
		$data['PaymentMethod']             = $Detail->CheckoutStatus->PaymentMethod;
		$data['CheckoutStatus']            = $Detail->CheckoutStatus->Status;

		$data['ShippingService']           = $Detail->ShippingServiceSelected->ShippingService;
		$data['ShippingAddress_City']      = $Detail->ShippingAddress->CityName;
		$data['buyer_name']                = $Detail->Buyer->RegistrationAddress->Name ?? '';

		// Access to StaticAlias has been deprecated by eBay
		//$data['buyer_email']               = apply_filters( 'wple_use_static_buyer_email', false ) ? $Detail->TransactionArray[0]->Buyer->StaticAlias : $Detail->TransactionArray[0]->Buyer->Email;
		$data['buyer_email']               = $Detail->TransactionArray[0]->Buyer->Email;

		$data['site_id']    	 		   = $this->site_id;
		$data['account_id']    	 		   = $this->account_id;

		// use buyer name from shipping address if registration address is empty
		if ( $data['buyer_name'] == '' ) {
			$data['buyer_name'] = $Detail->ShippingAddress->Name;
		}

		// process transactions / items
		$items = array();
		foreach ( $Detail->TransactionArray as $Transaction ) {
			$VariationSpecifics = false;
			$sku = $Transaction->Item->SKU;

			// process variation details
			if ( is_object( @$Transaction->Variation ) ) {
				$VariationSpecifics = array();
				$sku = $Transaction->Variation->SKU;

				if ( is_array($Transaction->Variation->VariationSpecifics) )
				foreach ( $Transaction->Variation->VariationSpecifics as $varspec ) {
					$attribute_name  = $varspec->Name;
					$attribute_value = $varspec->Value[0];
					$VariationSpecifics[ $attribute_name ] = $attribute_value;
				}
			}

			$newitem = array();
			$newitem['item_id']            = $Transaction->Item->ItemID;
			$newitem['title']              = $Transaction->Item->Title;
			$newitem['sku']                = $sku;
			$newitem['quantity']           = $Transaction->QuantityPurchased;
			$newitem['transaction_id']     = $Transaction->TransactionID;
			$newitem['OrderLineItemID']    = $Transaction->OrderLineItemID;
			$newitem['TransactionPrice']   = $Transaction->TransactionPrice->value;
			$newitem['VariationSpecifics'] = $VariationSpecifics;
			$items[] = $newitem;
			// echo "<pre>";print_r($Transaction);echo"</pre>";die();
		}
		$data['items'] = serialize( $items );


		// maybe skip orders from foreign sites
		if ( get_option( 'wplister_skip_foreign_site_orders' ) ) {

			// get WP-Lister eBay site
			$ebay_sites	   = EbayController::getEbaySites();
			$wplister_site = $ebay_sites[ get_option( 'wplister_ebay_site_id' ) ];

            // check if sites match - skip if they don't
            if ( $Transaction->TransactionSiteID != $wplister_site ) {
                // Allow 3rd-party code to override this check #27203
                $skip_order = apply_filters_deprecated( 'wplister_skip_foreign_order_override', array(true, $Transaction, $data, $Detail), '2.8.4', 'wple_skip_foreign_order_override' );
                $skip_order = apply_filters( 'wple_skip_foreign_order_override', $skip_order, $Transaction, $data, $Detail );

                if ( $skip_order ) {
                    WPLE()->logger->info( "skipped order #".$Detail->OrderID." from foreign site #".$Detail->Item->Site." / ".$Transaction->TransactionSiteID );
                    $this->addToReport( 'skipped', $data );
                    return false;
                } else {
                    WPLE()->logger->info( "wplister_skip_foreign_order_override used for Order #".$Detail->OrderID.": ".$Detail->Item->Site." / ".$Transaction->TransactionSiteID );
                }
            }
		}

		// skip orders that are older than the oldest order in WP-Lister / when WP-Lister was first connected to eBay
		if ( $first_order_date_created_ts = $this->getDateOfFirstOrder() ) {

			// convert to timestamps
			$this_order_date_created_ts = strtotime( $data['date_created'] );

			// skip if order date is older
			if ( $this_order_date_created_ts < $first_order_date_created_ts ) {
				WPLE()->logger->info( "skipped old order #".$Detail->OrderID." created at ".$data['date_created'] );
				WPLE()->logger->info( "timestamps: $this_order_date_created_ts / ".gmdate('Y-m-d H:i:s',$this_order_date_created_ts)." (order)  <  $first_order_date_created_ts ".gmdate('Y-m-d H:i:s',$first_order_date_created_ts)." (ref)" );
				$this->addToReport( 'skipped', $data );
				return false;
			}

		}


        // save GetOrders reponse in details
		//$data['details'] = self::encodeObject( $Detail );
		$data['details'] = maybe_serialize( $Detail );

		WPLE()->logger->info( "IMPORTING order #".$Detail->OrderID );

		return $data;
	} // mapItemDetailToDB()


	function addToReport( $status, $data, $wp_order_id = false, $error = false ) {

		$rep = new stdClass();
		$rep->status           = $status;
		$rep->order_id         = $data['order_id'];
		$rep->date_created     = $data['date_created'];
		$rep->OrderLineItemID  = $data['OrderLineItemID'] ?? '';
		$rep->LastTimeModified = $data['LastTimeModified'] ?? '';
		$rep->total            = $data['total'];
		$rep->data             = $data;
		// $rep->newstock         = $newstock;
		$rep->wp_order_id      = $wp_order_id;
		$rep->error            = $error;

		$this->report[] = $rep;

		switch ($status) {
			case 'skipped':
				$this->count_skipped++;
				break;
			case 'updated':
				$this->count_updated++;
				break;
			case 'inserted':
				$this->count_inserted++;
				break;
			case 'error':
			case 'failed':
				$this->count_failed++;
				break;
		}
		$this->count_total++;

	}

	function getHtmlTimespan() {
		if ( $this->NumberOfDays ) {
			return sprintf( __( 'the last %s days', 'wp-lister-for-ebay' ), $this->NumberOfDays );
		} elseif ( $this->ModTimeFrom ) {
			return sprintf( __( 'from %s to %s', 'wp-lister-for-ebay' ), $this->ModTimeFrom , $this->ModTimeTo );
		}
	}

	function getHtmlReport() {

		$html  = '<div class="ebay_order_report" style="display:none">';
		$html .= '<br>';
		$html .= __( 'New orders created', 'wp-lister-for-ebay' ) .': '. $this->count_inserted .' '. '<br>';
		$html .= __( 'Existing orders updated', 'wp-lister-for-ebay' )  .': '. $this->count_updated  .' '. '<br>';
		if ( $this->count_skipped ) $html .= __( 'Old or foreign orders skipped', 'wp-lister-for-ebay' )  .': '. $this->count_skipped  .' '. '<br>';
		if ( $this->count_failed ) $html .= __( 'Orders failed to create', 'wp-lister-for-ebay' )  .': '. $this->count_failed  .' '. '<br>';
		$html .= '<br>';

		if ( $this->count_skipped ) $html .= __( 'Note: Orders from foreign eBay sites were skipping during update.', 'wp-lister-for-ebay' ) . '<br><br>';

		$html .= '<table style="width:99%">';
		$html .= '<tr>';
		$html .= '<th align="left">'.__( 'Last modified', 'wp-lister-for-ebay' ).'</th>';
		$html .= '<th align="left">'.__( 'Order ID', 'wp-lister-for-ebay' ).'</th>';
		$html .= '<th align="left">'.__( 'Action', 'wp-lister-for-ebay' ).'</th>';
		$html .= '<th align="left">'.__( 'Total', 'wp-lister-for-ebay' ).'</th>';
		// $html .= '<th align="left">'.__( 'Title', 'wp-lister-for-ebay' ).'</th>';
		$html .= '<th align="left">'.__( 'Buyer ID', 'wp-lister-for-ebay' ).'</th>';
		$html .= '<th align="left">'.__( 'Date created', 'wp-lister-for-ebay' ).'</th>';
		$html .= '</tr>';

		foreach ($this->report as $item) {
			$html .= '<tr>';
			$html .= '<td>'.$item->LastTimeModified.'</td>';
			$html .= '<td>'.$item->order_id.'</td>';
			$html .= '<td>'.$item->status.'</td>';
			$html .= '<td>'.$item->total.'</td>';
			// $html .= '<td>'.@$item->data['item_title'].'</td>';
			$html .= '<td>'.@$item->data['buyer_userid'].'</td>';
			$html .= '<td>'.$item->date_created.'</td>';
			$html .= '</tr>';
			if ( $item->error ) {
				$html .= '<tr>';
				$html .= '<td colspan="7" style="color:darkred;">ERROR: '.$item->error.'</td>';
				$html .= '</tr>';
			}
		}

		$html .= '</table>';
		$html .= '</div>';
		return $html;
	}

	/* the following methods could go into another class, since they use wpdb instead of EbatNs_DatabaseProvider */

	function getAll() {
		global $wpdb;
		$items = $wpdb->get_results( "
			SELECT *
			FROM $this->tablename
			ORDER BY id DESC
		", ARRAY_A );

		return $items;
	}

	static function getItem( $id ) {
		global $wpdb;
		$table = $wpdb->prefix . self::TABLENAME;

		$item = $wpdb->get_row( $wpdb->prepare("
			SELECT *
			FROM $table
			WHERE id = %s
		", $id
		), ARRAY_A );

		// decode OrderType object with eBay classes loaded
		$item['details'] = self::decodeObject( $item['details'], false, true );
		$item['history'] = maybe_unserialize( $item['history'] );
		$item['items']   = maybe_unserialize( $item['items'] );

		return $item;
	}

	static function getWhere( $column, $value ) {
		global $wpdb;
		$table = $wpdb->prefix . self::TABLENAME;

		$items = $wpdb->get_results( $wpdb->prepare("
			SELECT *
			FROM $table
			WHERE $column = %s
		", $value
		), OBJECT_K);

		return $items;
	}

	function getOrderByOrderID( $order_id ) {
		global $wpdb;

		$order = $wpdb->get_row( $wpdb->prepare("
			SELECT *
			FROM $this->tablename
			WHERE order_id = %s
		", $order_id
		), ARRAY_A );

		return $order;
	}
	function getAllOrderByOrderID( $order_id ) {
		global $wpdb;

		$order = $wpdb->get_results( $wpdb->prepare("
			SELECT *
			FROM $this->tablename
			WHERE order_id = %s
		", $order_id
		), ARRAY_A );

		return $order;
	}

	static function geteBayOrderIds( $wpl_order_ids ) {
	    global $wpdb;

	    if ( !is_array( $wpl_order_ids ) ) {
	        $wpl_order_ids = array( $wpl_order_ids );
        }

        $order_ids_csv = "'". implode( "','", esc_sql( $wpl_order_ids ) ) ."'";

	    return $wpdb->get_col( $wpdb->prepare( "SELECT order_id FROM {$wpdb->prefix}ebay_orders WHERE id IN ({$order_ids_csv})"));
    }

	static function getAllIdsWithStatus( $status ) {
		global $wpdb;
		$table = $wpdb->prefix . self::TABLENAME;

		$order_ids = $wpdb->get_results( $wpdb->prepare("
			SELECT id
			FROM $table
			WHERE CompleteStatus = %s
			ORDER BY id DESC
		", $status
		), ARRAY_A );

		return $order_ids;
	}

	function getOrderByPostID( $post_id ) {
		global $wpdb;

		$order = $wpdb->get_row( $wpdb->prepare("
			SELECT *
			FROM $this->tablename
			WHERE post_id = %s
		", $post_id
		), ARRAY_A );

		return $order;
	}

	function getAllDuplicateOrders() {
		global $wpdb;
		$items = $wpdb->get_results("
			SELECT order_id, COUNT(*) c
			FROM $this->tablename
			GROUP BY order_id 
			HAVING c > 1
		", OBJECT_K);

		if ( ! empty($items) ) {
			$order = array();
			foreach ($items as &$item) {
				$orders[] = $item->order_id;
			}
			$items = $orders;
		}

		return $items;
	}

	// get the newest modification date of all orders in WP-Lister
	function getDateOfLastOrder( $account_id ) {
		global $wpdb;
		$lastdate = $wpdb->get_var( $wpdb->prepare("
			SELECT LastTimeModified
			FROM $this->tablename
			WHERE account_id = %s
			ORDER BY LastTimeModified DESC LIMIT 1
		", $account_id ) );

		// if there are no orders yet, check the date of the last transaction
		if ( ! $lastdate ) {
			$lastdate = TransactionsModel::getDateOfLastCreatedTransaction( $account_id );
			if ($lastdate) {
				// add two minutes to prevent importing the same transaction again
				$lastdate = mysql2date('U', $lastdate) + 120;
				$lastdate = gmdate('Y-m-d H:i:s', $lastdate );
			}
		}
		return $lastdate;
	}

	// get the creation date of the oldest order in WP-Lister - as unix timestamp
	function getDateOfFirstOrder() {
		global $wpdb;

		// regard ignore_orders_before_ts timestamp if set
		if ( $ts = get_option('wplister_ignore_orders_before_ts') ) {
			WPLE()->logger->info( "getDateOfFirstOrder() - using ignore_orders_before_ts: $ts (raw)");
			return $ts;
		}

		$date = $wpdb->get_var( "
			SELECT date_created
			FROM $this->tablename
			ORDER BY date_created ASC LIMIT 1
		" );

		return strtotime($date);
	}

	function deleteItem( $id ) {
		global $wpdb;
		$wpdb->query( $wpdb->prepare("
			DELETE
			FROM $this->tablename
			WHERE id = %s
		", $id ) );
	}

	function updateWpOrderID( $id, $wp_order_id ) {
		global $wpdb;
		$wpdb->query( $wpdb->prepare("
			UPDATE $this->tablename
			SET post_id = %s
			WHERE id    = %s
		", $wp_order_id, $id ) );
		echo $wpdb->last_error;
	}

	static public function updateByOrderId( $order_id, $data ) {
		global $wpdb;
		$table = $wpdb->prefix . self::TABLENAME;

		// update
		$wpdb->update( $table, $data, array( 'order_id' => $order_id ) );
	}

	static function updateExistingOrderStatusFromNewOrderData( $previous_order, $new_order_data, $CompleteStatusOverwrite = false ) {
		// WPLE()->logger->debug( 'updateExistingOrderStatusFromNewOrderData()'.print_r( $data, 1 ) );
		if ( empty($new_order_data) ) return;

		// only update specific status columns from $new_order_data
		$data                         = array();
		$data['CompleteStatus']       = $new_order_data['CompleteStatus'];
		$data['CheckoutStatus']       = $new_order_data['CheckoutStatus'];
		$data['eBayPaymentStatus']    = $new_order_data['eBayPaymentStatus'];
		$data['PaymentMethod']        = $new_order_data['PaymentMethod'];
		$data['LastTimeModified']     = $new_order_data['LastTimeModified'];

		// $data['wp_order_id']          = $new_order_data['post_id'];
		// $data['ShippingService']      = $new_order_data['ShippingService'];
		// $data['ShippingAddress_City'] = $new_order_data['ShippingAddress_City'];

		// allow custom CompleteStatus, like "Merged"
		if ( $CompleteStatusOverwrite ) {
			$data['CompleteStatus']   = $CompleteStatusOverwrite;
		}

		// update order record
		self::updateByOrderId( $previous_order['order_id'], $data );

		// add history record
		$history_message = 'Order status has changed from '.$previous_order['CompleteStatus'].' to '.$data['CompleteStatus'];
		$history_details = array( 'old_status' => $previous_order['CompleteStatus'], 'new_status' => $data['CompleteStatus'] );

        $ordersModel = new EbayOrdersModel();
        $ordersModel->addHistory( $previous_order['order_id'], 'order_status_changed', $history_message, $history_details );

	} // updateExistingOrderStatusFromNewOrderData()

	function getStatusSummary() {
		global $wpdb;
		$result = $wpdb->get_results("
			SELECT CompleteStatus, count(*) as total
			FROM $this->tablename
			GROUP BY CompleteStatus
		");

		$summary = new stdClass();
		foreach ($result as $row) {
			$CompleteStatus = $row->CompleteStatus;
			$summary->$CompleteStatus = $row->total;
		}

		// count total items as well
		$total_items = $wpdb->get_var("
			SELECT COUNT( id ) AS total_items
			FROM $this->tablename
			WHERE NOT CompleteStatus = 'Active' 
		");
		$summary->total_items = $total_items;

        // Shipped and Unshipped
        $total_items = $wpdb->get_var("
			SELECT COUNT( o.id ) AS total_items
			FROM $this->tablename o
			WHERE ShippedTime <> ''
			AND NOT o.CompleteStatus = 'Active' 
		");
        $summary->shipped    = $total_items;
        $summary->unshipped  = $summary->total_items - $total_items;

        // count orders which do (not) exist in WooCommerce
        $total_items = $wpdb->get_var("
			SELECT COUNT( o.id ) AS total_items
			FROM $this->tablename o
			LEFT JOIN {$wpdb->prefix}posts p ON o.post_id = p.ID 
			WHERE p.ID IS NOT NULL
		");
        $summary->has_wc_order    = $total_items;
        $summary->has_no_wc_order = $summary->total_items - $total_items;

		return $summary;
	}


	function getPageItems( $current_page, $per_page ) {
		global $wpdb;

        $orderby  = (!empty($_REQUEST['orderby'])) ? esc_sql( $_REQUEST['orderby'] ) : 'date_created';
        $order    = (!empty($_REQUEST['order']))   ? esc_sql( $_REQUEST['order']   ) : 'desc';
        $offset   = ( $current_page - 1 ) * $per_page;
        $per_page = esc_sql( $per_page );

        $join_sql  = '';
        $where_sql = 'WHERE 1 = 1 ';

        // filter order_status
		$order_status = ( isset($_REQUEST['order_status']) ? esc_sql( $_REQUEST['order_status'] ) : 'all');
		if ( $order_status != 'all' ) {
			$where_sql .= "AND o.CompleteStatus = '".$order_status."' ";
		}

		// filter shipped status
        $shipped = isset($_REQUEST['shipped']) ? esc_sql( $_REQUEST['shipped'] ) : '';
        if ( $shipped ) {
            $where_sql .= $shipped == 'yes' ? "AND ShippedTime <> '' " : "AND ShippedTime = ''";
        }

        // filter has_wc_order
        $has_wc_order = isset($_REQUEST['has_wc_order']) ? esc_sql( $_REQUEST['has_wc_order'] ) : '';
        if ( $has_wc_order ) {
            // $where_sql .= $has_wc_order == 'yes' ? "AND o.post_id IS NOT NULL " : "AND o.post_id IS NULL ";
            $join_sql  .= "LEFT JOIN {$wpdb->prefix}posts p ON o.post_id = p.ID ";
            $where_sql .= $has_wc_order == 'yes' ? "AND p.ID IS NOT NULL " : "AND p.ID IS NULL ";
        }

        // filter account_id
		$account_id = ( isset($_REQUEST['account_id']) ? esc_sql( $_REQUEST['account_id'] ) : false);
		if ( $account_id ) {
			$where_sql .= "
				 AND o.account_id = '".$account_id."'
			";
		}

        // filter search_query
		$search_query = ( isset($_REQUEST['s']) ? esc_sql( wple_clean($_REQUEST['s']) ) : false);
		if ( $search_query ) {
			$where_sql .= "
				AND  ( o.buyer_name   LIKE '%".$search_query."%'
					OR o.items        LIKE '%".$search_query."%'
					OR o.details      LIKE '%".$search_query."%'
					OR o.history      LIKE '%".$search_query."%'
					OR o.buyer_userid     = '".$search_query."'
					OR o.buyer_email      = '".$search_query."'
					OR o.order_id         = '".$search_query."'
					OR o.post_id          = '".$search_query."'
					OR o.ShippingAddress_City LIKE '%".$search_query."%' )
			";
		}

		// hide 'Active' orders when showing 'all' orders - unless we have an actual search query
		if ( $order_status == 'all' && empty($search_query) ) {
			$where_sql .= "AND NOT o.CompleteStatus = 'Active' ";
		}

        // get items
		$items = $wpdb->get_results("
			SELECT *
			FROM $this->tablename o
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
				FROM $this->tablename o
	            $join_sql 
    	        $where_sql
				ORDER BY $orderby $order
			");
		}

		// foreach( $items as &$profile ) {
		// 	$profile['details'] = self::decodeObject( $profile['details'] );
		// }

		return $items;
	}


    ## BEGIN PRO ##

    // call CompleteSale for a single order
	public function completeEbayOrder( $session, $id, $data ) {

		$this->initServiceProxy($session);

		// get order item to update
		$order = $this->getItem( $id );

		// build request
		$req = new CompleteSaleRequestType();
		// $req->ItemID = $order['item_id'];
		$req->OrderID  = $order['order_id'];
		$req->Shipment = new ShipmentType();

		// handle shipping date
		if ( isset( $data['ShippedTime'] ) ) {

			// process ShippedTime parameter
			if ( $data['ShippedTime'] == '_now_' || $data['ShippedTime'] == '' ) {

				// use current gmt date and time
				$ShippedTime = gmdate( 'Y-m-d H:i:s' );

			} else {

				// or use the user provided date Y-m-d at 8am
				$ShippedTime = gmdate( 'Y-m-d', $data['ShippedTime'] ) . ' 08:00:00';

				// unless that is before the order creation date
				if ( strtotime( $ShippedTime ) < strtotime( $order['date_created'] ) ) {
					// in which case use current gmt date and time
					$ShippedTime = gmdate( 'Y-m-d H:i:s' );
				}

			}

			$ShippedTime = $this->convertSqlDateToEbay( $ShippedTime );
			$req->Shipment->ShippedTime = $ShippedTime;
			$req->Shipped = true;
			$req->Paid = (isset( $data['Paid'] ) && $data['Paid']) ? true : null ;
		}

		// handle tracking info
		if ( isset( $data['TrackingNumber'] ) && trim( $data['TrackingNumber'] ) ) {
			$req->Shipment->ShipmentTrackingDetails = new ShipmentTrackingDetailsType();
			$req->Shipment->ShipmentTrackingDetails->ShipmentTrackingNumber = $data['TrackingNumber'];
			$req->Shipment->ShipmentTrackingDetails->ShippingCarrierUsed    = $data['TrackingCarrier'];
		}

		// handle feedback
		if ( isset( $data['FeedbackText'] ) && trim( $data['FeedbackText'] ) ) {
		    // Decode html special characters
            $data['FeedbackText'] = html_entity_decode( $data['FeedbackText'], ENT_QUOTES | ENT_XML1, 'UTF8' );
			$data['FeedbackText'] = self::mb_strlen($data['FeedbackText']) > 80 ? self::mb_substr( $data['FeedbackText'], 0, 80 ) : $data['FeedbackText'];
			$req->FeedbackInfo = new FeedbackInfoType();
			$req->FeedbackInfo->CommentType = 'Positive';
			$req->FeedbackInfo->CommentText = $data['FeedbackText'];
			$req->FeedbackInfo->TargetUser  = $order['buyer_userid'];
		}

		// $req->Paid = true;
		// $req->Shipped = true;

		WPLE()->logger->info('completeEbayOrder(): '.$id);
		WPLE()->logger->info('OrderID: '.$req->OrderID);

		// $req->DetailLevel = $Facet_DetailLevelCodeType->ReturnAll;
		$req->setDetailLevel('ReturnAll');

		// If BestEffort is specified for CompleteSale, the Ack field in the response could return PartialFailure if one change fails but another succeeds.
		// For example, if the seller attempts to leave feedback twice for the same order line item,
		// the feedback changes would fail but any paid or shipped status changes would succeed.
		$req->setErrorHandling( 'BestEffort' );

		// download the data
		$res = $this->_cs->CompleteSale( $req );

		// handle response and check if successful
		$success = false;
		$error = '';
		if ( $this->handleResponse($res) ) {
			$success = true;
			WPLE()->logger->info( sprintf("Order %s updated successfully.", $id ) );
		} else {
			WPLE()->logger->error( "Error on CompleteSale(): ".print_r( $res, 1 ) );
			if ( is_object($res) ) {
				$error = "eBay said: ".$res->Errors[0]->LongMessage;
			} else {
				$error = "Error on CompleteSale() - invalid response: ".print_r( $res, 1 );
			}
		}

        // build response
        $response = new stdClass();
        $response->success      = $success;
        $response->error        = $error;
        $response->error_code   = $this->handle_error_code;
        return $response;

	} // completeEbayOrder()

	/**
	 * Search WC orders for unshipped orders where the HandleByTime has already passed.
	 *
	 * @return stdClass|WC_Order[]
	 */
    public static function getOverdueUnshippedOrders() {
	    $statuses = wc_get_order_statuses();
	    unset( $statuses['wc-completed'] );
	    unset( $statuses['wc-refunded'] );
	    unset( $statuses['wc-cancelled'] );
	    unset( $statuses['wc-failed'] );
	    $statuses = array_keys( $statuses );
	    return wc_get_orders([
	        'status'        => $statuses,
            'meta_query'    => [
                [
                    'key'       => '_ebay_handle_by_time',
                    'value'     => current_time( 'mysql', true ),
                    'compare'   => '<',
                ],
                [
                    'key'       => '_date_shipped',
                    'compare'   => 'NOT EXISTS',

                ]
            ]
        ]);
    }

	/**
	 * Look for orders that are overdue for shipment. Store the result in a transient named `wple_overdue_shipments`
	 */
    public function checkOverdueShipments() {
	    $orders = self::getOverdueUnshippedOrders();
	    delete_transient( 'wple_overdue_shipments' );

	    $new_orders = [];
	    if ( is_array( $orders ) ) {
	    	foreach ( $orders as $order ) {
	    		$ebay_order_id = $order->get_meta( '_ebay_order_id' );
	    		$wc_order_id    = $order->get_id();
	    		$due_date = $order->get_meta( '_ebay_handle_by_time' );
	    		$new_orders[] = [
	    			'ebay_order_id' => $ebay_order_id,
				    'wc_order_id'   => $wc_order_id,
				    'due_date'      => $due_date
			    ];
		    }
	    	set_transient( 'wple_overdue_shipments', $new_orders, 86400 );
	    }
    }

    ## END PRO ##

    /**
     * Check the payment status of the eBay order.
     * @param $order
     *
     * @return bool
     */
    public function orderIsPaid( $order ) {
        WPLE()->logger->info( 'orderIsPaid' );
        if ( is_numeric( $order ) ) {
            WPLE()->logger->info( 'Passed ID: '. $order );
            $order = $this->getOrderByOrderID( $order );
        }

        if ( !$order ) {
            WPLE()->logger->info( 'Order data invalid. Returning FALSE' );
            return false;
        }

        if ( is_string( $order['details'] ) ) {
            $order['details'] = maybe_unserialize( $order['details'] );
        }

        // OrderType::PaidTime will hold the date/time of the payment so if this isn't empty, it means the order's already been paid
        if ( is_callable( array( $order['details'], 'getPaidTime' ) ) && $order['details']->getPaidTime() ) {
            WPLE()->logger->info( 'Found PaidTime. Order is considered paid and complete' );
            return true;
        }

        // Some orders do not have a PaidTime property - but their CompleteStatus is already Completed so let's consider that as well #38125
        if ( $order['CompleteStatus'] == 'Completed' ) {
            WPLE()->logger->info( 'CompleteStatus is set to Completed' );
            return true;
        }

        WPLE()->logger->info( 'PaidTime not found. Order is active/incomplete' );
        return false;
    }

    /**
     * Check the shipped status of the eBay order.
     * @param $order
     *
     * @return bool
     */
    public function orderIsShipped( $order ) {
        if ( is_numeric( $order ) ) {
            $order = $this->getOrderByOrderID( $order );
        }

        if ( !$order ) {
            return false;
        }

        if ( $order['details']->getShippedTime() ) {
            return true;
        }

        return false;
    }

    /**
     * Get the matching Listing ID in WPLE from the given eBay Item ID. If the SKU Matching is enabled, the SKU is used
     * to retrieve the matching Listing ID. If no matches are found, this method returns FALSE.
     *
     * @param $ebay_id int
     * @param $sku string
     * @param $order_id int
     * @return int|bool Returns the local listing's ID or FALSE if no matches are found
     */
    private function getListingIdFromEbayId( $ebay_id, $sku = '', $order_id = 0 ) {
        global $wpdb;

        WPLE()->logger->info( 'getListingIdFromEbayId( '. $ebay_id .', '. $sku .', '. $order_id .')' );
        // check if this listing exists in WP-Lister
        $listing_id = $wpdb->get_var( $wpdb->prepare("SELECT id FROM {$wpdb->prefix}ebay_auctions WHERE ebay_id = %s AND status <> 'archived'", $ebay_id ) );
        WPLE()->logger->info( 'Found #'. $listing_id .' from ebay_auctions table' );


        if ( ! $listing_id && $sku && get_option( 'wplister_match_sales_by_sku', 0 ) == 1 ) {
            $listingItem = WPLE_ListingQueryHelper::findItemBySku( $sku, true );


            if ( $listingItem ) {
                $listing_id = $listingItem->id;
                WPLE()->logger->info( 'found #'. $listing_id .' from findItemBySku()' );
                $history_message = "Matched SKU ({$sku}) to Listing #$listing_id";
                $history_details = array( 'ebay_id' => $ebay_id );
                $this->addHistory( $order_id, 'match_sku', $history_message, $history_details );
            }
        }

        if ( !$listing_id ) {
            WPLE()->logger->info( 'Listing not found' );
            $listing_id = false;
        }

        return $listing_id;
    }


} // class EbayOrdersModel
