<?php

use WPLab\Ebay\Listings\Listing;

/**
 * ListingsPage class
 *
 */

class ListingsPage extends WPL_Page {

	const slug = 'auctions';

    protected $listingsTable;

	function config()
	{
		add_action( 'admin_menu', array( &$this, 'onWpTopAdminMenu' ), 10 );
		add_action( 'admin_menu', array( &$this, 'fixSubmenu' ), 30 );

		// add_action( 'network_admin_menu', array( &$this, 'onWpNetworkAdminMenu' ) );
	}

	public function onWpInit() {

		// Add custom screen options
		add_action( "load-toplevel_page_wplister", array( &$this, 'addScreenOptions' ) );

		// handle actions when WP is loaded
		add_action( "wp_loaded", array( &$this, 'handleActionsOnWpLoaded' ), 1 );
	}

	// public function onWpNetworkAdminMenu() {
	// 	$settingsPage = WPLE()->pages['settings'];

	// 	$page_id = add_menu_page( $this->app_name, $this->main_admin_menu_label, self::ParentPermissions,
	// 				   self::ParentMenuId, array( $settingsPage, 'onDisplaySettingsPage' ), $this->getImageUrl( 'hammer-16x16.png' ), ProductWrapper::menu_page_position );
	// }

	public function onWpTopAdminMenu() {
		$page_id = add_menu_page( $this->app_name, $this->main_admin_menu_label, self::ParentPermissions,
					   self::ParentMenuId, array( $this, 'onDisplayListingsPage' ), $this->getImageUrl( 'hammer-16x16.png' ), ProductWrapper::menu_page_position );
		// $page_id: toplevel_page_wplister
	}

	public function handleActionsOnWpLoaded() {
		if ( $this->requestAction() == 'wple_prepare_auction' ) {
            if ( ! current_user_can('prepare_ebay_listings') ) return;
		    check_admin_referer( 'prepare_listing' );

			$listingsModel = new ListingsModel();
	        $listings = $listingsModel->prepareListings( wple_clean($_REQUEST['post']) );

	        // redirect to listings page
			wp_redirect( get_admin_url().'admin.php?page=wplister' );
			exit();
		}

		if ( $this->requestAction() == 'wple_reselect' ) {
            if ( ! current_user_can('manage_ebay_listings') ) return;

			ListingsModel::reSelectListings( wple_clean($_REQUEST['auction']) );

	        // redirect to listings page
			wp_redirect( get_admin_url().'admin.php?page=wplister' );
			exit();
		}

		if ( $this->requestAction() == 'wple_apply_listing_profile' ) {
            if ( ! current_user_can('manage_ebay_listings') ) return;

	        WPLE()->logger->info( 'apply_listing_profile' );

	        check_admin_referer( 'wplister_apply_listing_profile' );

	        #WPLE()->logger->info( print_r( $_REQUEST, 1 ) );
			$profilesModel = new ProfilesModel();
	        $profile = $profilesModel->getItem( intval($_REQUEST['wpl_e2e_profile_to_apply']) );

			$listingsModel = new ListingsModel();
	        $items = $listingsModel->applyProfileToNewListings( $profile );

			// remember selected profile
			self::updateOption('last_selected_profile', intval($_REQUEST['wpl_e2e_profile_to_apply']) );

	        // redirect to listings page
			if ( @$_REQUEST['wpl_e2e_verify_after_profile']=='1') {
				// verify new listings if asked to
				wp_redirect( get_admin_url().'admin.php?page=wplister&action=verifyPreparedItemsNow' );
			} else {
				wp_redirect( get_admin_url().'admin.php?page=wplister' );
			}
			exit();
		}

		// handle preview action
		if ( $this->requestAction() == 'wple_preview_auction' ) {

		    check_admin_referer( 'wplister_preview_auction' );
			$this->previewListing( wple_clean($_REQUEST['auction']) );
			exit();
		}

		// handle remove_from_ebay action (WooCommerce Products page)
		if ( $this->requestAction() == 'wple_remove_from_ebay' ) {
            if ( ! current_user_can('manage_ebay_listings') ) return;
		    check_admin_referer( 'bulk-posts' );
			$products =  is_array( $_REQUEST['post'] ) ? wple_clean($_REQUEST['post']) : array( wple_clean($_REQUEST['post']) );
			// WPLE()->logger->info('remove_from_ebay / products: '.print_r($products,1));
			if ( empty($products) ) return;

			foreach ( $products as $product_id ) {

				if ( ! $product_id ) continue; // prevent ending all items with empty parent_id

				$listings = WPLE_ListingQueryHelper::getAllListingsFromPostOrParentID( $product_id );
				WPLE()->logger->info('Ending all listings for post_id '.$product_id);

				foreach ( $listings as $listing ) {

					$listing_id = $listing->id;
					$account_id = isset( $listing_id ) ? WPLE_ListingQueryHelper::getAccountID( $listing_id ) : false;
					$this->initEC( $account_id );
					$this->EC->endItemsOnEbay( $listing_id );
					$this->EC->closeEbay();

				}

			} // each $product_id
			wple_show_message( __( 'Selected listings were ended.', 'wp-lister-for-ebay' ) ); // TODO: implement as persistent admin message (save to db and show once)
		}

	} // handleActionsOnWpLoaded()

	function addScreenOptions() {

		if ( ( isset($_GET['action']) ) && ( $_GET['action'] == 'edit' ) ) {
			// on edit page render developers options
			add_screen_options_panel('wplister_developer_options', '', array( &$this, 'renderDeveloperOptions'), 'toplevel_page_wplister' );

		} else {
			// on listings page render table options
			$option = 'per_page';
			$args = array(
		    	'label' => 'Listings',
		        'default' => 20,
		        'option' => 'listings_per_page'
		        );
			add_screen_option( $option, $args );
			$this->listingsTable = new ListingsTable();
		}

	    // add_thickbox();
		wp_enqueue_script( 'thickbox' );
		wp_enqueue_style( 'thickbox' );

		// ProfileSelector
		wp_register_script( 'wple_profile_selector', self::$PLUGIN_URL.'js/classes/ProfileSelector.js', array( 'jquery' ), WPLE_PLUGIN_VERSION );
		wp_enqueue_script ( 'wple_profile_selector' );
		wp_localize_script( 'wple_profile_selector', 'wple_ProfileSelector_i18n', array(
				'WPLE_URL' 	      => WPLE_PLUGIN_URL,
				'wple_ajax_nonce' => wp_create_nonce('wple_ajax_nonce')
			)
		);

	} // addScreenOptions()

    private function verifyListing( $listing_id, $account_id ) {
	    $this->initEC( $account_id );
	    $this->EC->verifyItems( $listing_id );
	    $this->EC->closeEbay();
	    if ( $this->EC->isSuccess ) {
		    wple_show_message( __( 'Selected items were verified with eBay.', 'wp-lister-for-ebay' ), 'info', ['persistent' => true]);
	    } else {
		    wple_show_message( __( 'There were some problems verifying your items.', 'wp-lister-for-ebay' ), 'error', ['persistent' => true] );
	    }
    }

    private function reviseListing( $listing_id, $account_id ) {
	    $this->initEC( $account_id );
	    $this->EC->reviseItems( $listing_id );
	    $this->EC->closeEbay();
	    if ( $this->EC->isSuccess ) {
		    wple_show_message( __( 'Selected items were revised on eBay.', 'wp-lister-for-ebay' ), 'info', ['persistent' => true] );
	    } else {
		    wple_show_message( __( 'There were some problems revising your items.', 'wp-lister-for-ebay' ), 'error', ['persistent' => true] );
	    }
    }

    private function publishListing( $listing_id, $account_id ) {
	    $this->initEC( $account_id );
	    $this->EC->sendItemsToEbay( $listing_id );
	    $this->EC->closeEbay();
	    if ( $this->EC->isSuccess ) {
		    wple_show_message( __( 'Selected items were published on eBay.', 'wp-lister-for-ebay' ), 'info', ['persistent' => true] );
	    } else {
		    wple_show_message( __( 'Some items could not be published.', 'wp-lister-for-ebay' ), 'error', ['persistent' => true] );
	    }
    }

    private function relistListing( $listing_id, $account_id ) {
	    $this->initEC( $account_id );
	    $this->EC->relistItems( $listing_id );
	    $this->EC->closeEbay();
	    if ( $this->EC->isSuccess ) {
		    wple_show_message( __( 'Selected items were re-listed on eBay.', 'wp-lister-for-ebay' ), 'info', ['persistent' => true] );
	    } else {
		    wple_show_message( __( 'There were some problems relisting your items.', 'wp-lister-for-ebay' ), 'error', ['persistent' => true] );
	    }
    }

    private function endListing( $listing_id, $account_id ) {
	    $this->initEC( $account_id );
	    $this->EC->endItemsOnEbay( $listing_id );
	    $this->EC->closeEbay();
	    wple_show_message( __( 'Selected listings were ended.', 'wp-lister-for-ebay' ), 'info', ['persistent' => true] );
    }

    private function updateListing( $listing_id, $account_id ) {
	    $this->initEC( $account_id );
	    $this->EC->updateItemsFromEbay( $listing_id );
	    $this->EC->closeEbay();
	    wple_show_message( __( 'Selected items were updated from eBay.', 'wp-lister-for-ebay' ), 'info', ['persistent' => true] );
    }

    private function deleteListing( $listing_id ) {
	    $id = $listing_id;

	    if ( is_array( $id )) {
		    foreach( $id as $single_id ) {
			    WPLE_ListingQueryHelper::deleteItem( $single_id );
		    }
	    } else {
		    WPLE_ListingQueryHelper::deleteItem( $id );
	    }

	    wple_show_message( __( 'Selected items were removed.', 'wp-lister-for-ebay' ), 'info', ['persistent' => true] );
    }

    private function archiveListing( $listing_id ) {
	    $id = $listing_id;
	    $data = array( 'status' => 'archived' );

	    if ( is_array( $id )) {
		    foreach( $id as $single_id ) {
			    ListingsModel::updateListing( $single_id, $data );
		    }
	    } else {
		    ListingsModel::updateListing( $id, $data );
	    }

	    wple_show_message( __( 'Selected items were archived.', 'wp-lister-for-ebay' ), 'info', ['persistent' => true] );
    }

    private function fixSplitTitle( $parent_id ) {
	    $lm = new ListingsModel();

	    $listings   = WPLE_ListingQueryHelper::getAllListingsFromParentID( $parent_id );
	    $variations = ProductWrapper::getListingVariations( $parent_id );

	    //if ( count( $listings ) == count( $variations ) ) {
	    foreach ( $variations as $variation ) {
		    foreach ( $listings as $idx => $listing ) {
			    // insert the attributes into the listing
			    //$listing->variations = serialize( $variation['variation_attributes'] );
			    ListingsModel::updateListing( $listing->id, array( 'post_id' => $variation['post_id'], 'variations' => serialize( $variation['variation_attributes'] ) ) );
			    $lm->reapplyProfileToItem( $listing->id );
			    unset( $listings[ $idx ] );
			    continue 2;
		    }
	    }

	    //}

	    //wple_show_message( __( 'Selected items were archived.', 'wp-lister-for-ebay' ) );
    }

    private function resetStatus( $listing_id ) {
	    $lm = new ListingsModel();
	    $id = $listing_id;

	    if ( is_array( $id ) ) {
		    $lm->errors = [];
		    $has_errors = false;
		    foreach( $id as $single_id ) {
			    if ( !$lm->resetListing( $single_id ) ) {
				    $has_errors = true;
			    }
		    }

		    if ( $has_errors ) {
			    foreach ( $lm->errors as $error ) {
				    wple_show_message( $error, 'warn', ['persistent' => true] );
			    }
		    } else {
			    wple_show_message( __( 'Selected items had their status reset to prepared.', 'wp-lister-for-ebay' ), 'info', ['persistent' => true] );
		    }

	    }
    }

    private function clearEPS( $listing_id ) {
	    if ( is_array( $listing_id ) ) {
		    foreach( $listing_id as $single_id ) {
			    ListingsModel::updateWhere(
				    array( 'id' => $single_id ),
				    array( 'eps' => '' )
			    );
		    }
		    wple_show_message( __( 'EPS cache was cleared for selected items.', 'wp-lister-for-ebay' ), 'info', ['persistent' => true] );
	    }
    }

    private function lockListing( $listing_id ) {
	    $data = array( 'locked' => true );

	    if ( is_array( $listing_id )) {
		    foreach( $listing_id as $single_id ) {
			    ListingsModel::updateListing( $single_id, $data );
		    }
	    } else {
		    ListingsModel::updateListing( $listing_id, $data );
	    }

	    wple_show_message( __( 'Selected items were locked.', 'wp-lister-for-ebay' ), 'info', ['persistent' => true] );
    }

    private function unlockListing( $listing_id ) {
	    $data = array( 'locked' => false );

	    if ( is_array( $listing_id )) {
		    foreach( $listing_id as $single_id ) {
			    ListingsModel::updateListing( $single_id, $data );
		    }
	    } else {
		    ListingsModel::updateListing( $listing_id, $data );
	    }

	    wple_show_message( __( 'Selected items were unlocked.', 'wp-lister-for-ebay' ), 'info', ['persistent' => true] );
    }

    private function cancelRelistSchedule( $listing_id ) {
	    $data = array( 'relist_date' => null );

	    if ( is_array( $listing_id )) {
		    foreach( $listing_id as $single_id ) {
			    ListingsModel::updateListing( $single_id, $data );
		    }
	    } else {
		    ListingsModel::updateListing( $listing_id, $data );
	    }

	    wple_show_message( __( 'Selected items were unscheduled from auto relist.', 'wp-lister-for-ebay' ), 'info', ['persistent' => true] );
    }

    private function prepareListing( $product_id, $profile_id ) {
	    if ( ! current_user_can('prepare_ebay_listings') ) return;

	    // get profile
	    $profilesModel = new ProfilesModel();


	    if ( $profile_id ) {

		    // prepare product
		    $listingsModel = new ListingsModel();
		    $listing_id = $listingsModel->prepareProductForListing( $product_id, $profile_id );

		    if ( $listing_id ) {
			    $listingsModel->applyProfileToNewListings( $profile_id );
			    wple_show_message( __( 'New listing was prepared from product.', 'wp-lister-for-ebay' ), 'info', ['persistent' => true] );
		    } else {
			    $msg = __( 'Could not create a new listing from this product.', 'wp-lister-for-ebay' );
			    if ( $listingsModel->errors )
				    $msg .= '<br>'.join('<br>',$listingsModel->errors);
			    if ( $listingsModel->warnings )
				    $msg .= '<br>'.join('<br>',$listingsModel->warnings);
			    wple_show_message( $msg, 'warn', ['persistent' => true] );
		    }


	    } elseif ( $product_id ) {

		    // prepare product
		    $listingsModel = new ListingsModel();
		    $listingsModel->prepareProductForListing( $product_id );

	    }
    }

    private function splitVariations( $listing_id, $account_id ) {
	    ## BEGIN PRO ##
	    $this->initEC( $account_id );
	    $this->EC->splitVariations( $listing_id );
	    $this->EC->closeEbay();
	    wple_show_message( __( 'Selected variations were split into single items.', 'wp-lister-for-ebay' ), 'info', ['persistent' => true] );
	    ## END PRO ##
    }

    private function reapplyProfile( $listing_id ) {
	    $listingsModel = new ListingsModel();
	    $listingsModel->reapplyProfileToItems( $listing_id );
	    wple_show_message( __( 'Profiles were re-applied to selected items.', 'wp-lister-for-ebay' ), 'info', ['persistent' => true] );
    }

	public function handleActions() {
        if ( empty( $this->requestAction() ) ) {
            return;
        }
        // set account_id
        $auction_id = isset( $_REQUEST['auction'] ) ? wple_clean($_REQUEST['auction']) : false;
        $account_id = isset( $_REQUEST['auction'] ) ? WPLE_ListingQueryHelper::getAccountID( $auction_id ) : false;
        $did_action = true;

        switch ( $this->requestAction() ) {

            case 'wple_save_listing':
	            check_admin_referer( 'wplister_save_listing' );
	            if ( ! current_user_can('manage_ebay_listings') ) return;
	            $this->saveListing();
                break;

            case 'wple_verify':
	            check_admin_referer( 'bulk-auctions' );
	            if ( ! current_user_can('publish_ebay_listings') ) return;
                $this->verifyListing( $auction_id, $account_id );
                break;

            case  'wple_revise':
	            check_admin_referer( 'bulk-auctions' );
	            if ( ! current_user_can('publish_ebay_listings') ) return;
                $this->reviseListing( $auction_id, $account_id );
                break;

            case 'wple_publish2e':
	            check_admin_referer( 'bulk-auctions' );
	            if ( ! current_user_can('publish_ebay_listings') ) return;
                $this->publishListing( $auction_id, $account_id );
                break;

            case 'wple_relist':
	            check_admin_referer( 'bulk-auctions' );
	            if ( ! current_user_can('publish_ebay_listings') ) return;
                $this->relistListing( $auction_id, $account_id );
                break;

            case 'wple_end_item':
	            check_admin_referer( 'bulk-auctions' );
	            if ( ! current_user_can('manage_ebay_listings') ) return;
                $this->endListing( $auction_id, $account_id );
                break;

            case 'wple_update':
	            check_admin_referer( 'bulk-auctions' );
	            if ( ! current_user_can('manage_ebay_listings') ) return;
                $this->updateListing( $auction_id, $account_id );
                break;

            case 'wple_delete_listing':
	            check_admin_referer( 'bulk-auctions' );
	            if ( ! current_user_can('manage_ebay_listings') ) return;
                $this->deleteListing( $auction_id );
                break;

            case 'wple_archive':
	            check_admin_referer( 'bulk-auctions' );
	            if ( ! current_user_can('manage_ebay_listings') ) return;
                $this->archiveListing( $auction_id );
                break;

            case 'wple_fix_split_title':
	            check_admin_referer( 'bulk-auctions' );
	            if ( ! current_user_can('manage_ebay_listings') ) return;
                $this->fixSplitTitle( intval( $_REQUEST['parent'] ) );
                break;

            case 'wple_reset_status':
	            check_admin_referer( 'bulk-auctions' );
	            if ( ! current_user_can('manage_ebay_listings') ) return;
                $this->resetStatus( $auction_id );
                break;

            case 'wple_clear_eps_data':
	            check_admin_referer( 'bulk-auctions' );
	            if ( ! current_user_can('publish_ebay_listings') ) return;
                $this->clearEPS( $auction_id );
                break;

            case 'wple_lock':
	            check_admin_referer( 'bulk-auctions' );
	            if ( ! current_user_can('manage_ebay_listings') ) return;
                $this->lockListing( $auction_id );
                break;

            case 'wple_unlock':
	            check_admin_referer( 'bulk-auctions' );
	            if ( ! current_user_can('manage_ebay_listings') ) return;
                $this->unlockListing( $auction_id );
	            break;

            case 'wple_cancel_schedule':
	            check_admin_referer( 'bulk-auctions' );
	            if ( ! current_user_can('manage_ebay_listings') ) return;
                $this->cancelRelistSchedule( $auction_id );
                break;

            case 'wple_clean_listing_archive':
	            check_admin_referer( 'wplister_clean_listing_archive' );
	            if ( ! current_user_can('manage_ebay_listings') ) return;
	            WPLE_ListingQueryHelper::cleanArchive();
	            wple_show_message( __( 'Archive was cleared.', 'wp-lister-for-ebay' ), 'info', ['persistent' => true] );
                break;

            case 'wpl_prepare_single_listing':
	            check_admin_referer( 'wplister_prepare_single_listing' );
	            if ( ! current_user_can('prepare_ebay_listings') ) return;
                $product_id = intval( $_REQUEST['product_id'] ?? 0 );
                $profile_id = intval( $_REQUEST['profile_id'] ?? 0 );
                $this->prepareListing( $product_id, $profile_id );
                break;

            case 'wple_split_variations':
	            check_admin_referer( 'wplister_split_variations' );
	            if ( ! current_user_can('publish_ebay_listings') ) return;
                $this->splitVariations( $auction_id, $account_id );
                break;

            case 'wple_reapply':
	            check_admin_referer( 'bulk-auctions' );
	            if ( ! current_user_can('manage_ebay_listings') ) return;
                $this->reapplyProfile( $auction_id );
                break;

            case 'wple_cancel_profile_selection':
	            check_admin_referer( 'wplister_cancel_profile_selection' );
	            if ( ! current_user_can('manage_ebay_listings') ) return;
	            ListingsModel::cancelSelectingListings();
                break;

            default:
                $did_action = false;
                break;

        }

        if ( $did_action ) {
	        // redirect back to the listings page to get rid of the action param in the request
	        wp_redirect( remove_query_arg( ['action', 'auction', 'profile', '_wpnonce'] ) );
	        exit;
        }


	} // handleActions()



	public function onDisplayListingsPage() {
		$this->check_wplister_setup();

		// handle actions
		$this->handleActions();

		// do we have new products with no profile yet?
		$selectedProducts = WPLE_ListingQueryHelper::selectedProducts();
		if ( $selectedProducts ) {

			$this->displayPrepareListingsPage( $selectedProducts );

		// edit listing
		} elseif ( $this->requestAction() == 'edit' ) {

			$this->displayEditPage();

		// show list
		} else {

			// show warning if duplicate products found
			$this->checkForDuplicates();

			// check for profile waiting to be applied
			$this->checkForDelayedProfiles();
			$this->checkForDelayedTemplate();

	        // get listing status summary
	        $summary = WPLE_ListingQueryHelper::getStatusSummary();

	        // check for changed items and display reminder
	        if ( isset($summary->changed) && current_user_can( 'publish_ebay_listings' ) ) {
                if ( get_option( 'wplister_background_revisions', 0 ) ) {
                    $msg  = '<p>';
                    $msg .= sprintf( __( 'There are %s changed item(s), with %s item(s) queued to be revised.', 'wp-lister-for-ebay' ), $summary->changed, ListingsModel::countQueuedChangedListings() );
                    $msg .= '</p>';
                } else {
                    $msg  = '<p>';
                    $msg .= sprintf( __( 'There are %s changed item(s) which need to be revised on eBay to apply their latest changes.', 'wp-lister-for-ebay' ), $summary->changed );
                    // $msg .= '<br><br>';
                    $msg .= '&nbsp;&nbsp;';
                    $msg .= '<a id="btn_revise_all_changed_items_reminder" class="btn_revise_all_changed_items_reminder button wpl_job_button">' . __( 'Revise all changed items', 'wp-lister-for-ebay' ) . '</a>';
                    $msg .= '</p>';
                }

				wple_show_message( $msg );
	        }

	        // check for items to be relisted and display message
	        $listing_status = isset( $_REQUEST['listing_status'] ) ? sanitize_key($_REQUEST['listing_status']) : false;
	        if ( isset($summary->relist) && current_user_can( 'publish_ebay_listings' ) && $listing_status == 'relist' ) {
				$msg  = '<p>';
				$msg .= sprintf( __( 'There are %s items which are currently ended on eBay, but are in stock on your website and can be relisted.', 'wp-lister-for-ebay' ), $summary->relist );
				// $msg .= '<br><br>';
				$msg .= '&nbsp;&nbsp;';
				$msg .= '<a id="btn_relist_all_restocked_items" class="btn_relist_all_restocked_items button wpl_job_button">' . __( 'Relist all restocked items', 'wp-lister-for-ebay' ) . '</a>';
				$msg .= '</p>';
				wple_show_message( $msg );
	        }

	        // check for relisted items and display reminder
	        if ( isset($summary->relisted) ) {
				$msg  = '<p>';
				$msg .= sprintf( __( 'There are %s manually relisted item(s) which need to be updated from eBay to fetch their latest changes.', 'wp-lister-for-ebay' ), $summary->relisted );
				$msg .= '&nbsp;&nbsp;';
				$msg .= '<a id="btn_update_all_relisted_items_reminder" class="btn_update_all_relisted_items_reminder button wpl_job_button">' . __( 'Update all relisted items', 'wp-lister-for-ebay' ) . '</a>';
				$msg .= '</p>';
				wple_show_message( $msg, 'warn' );
	        }

			// get all items
			// $listings = WPLE_ListingQueryHelper::getAll();

		    //Create an instance of our package class...
		    // $this->listingsTable = new ListingsTable();
	    	//Fetch, prepare, sort, and filter our data...
		    $this->listingsTable->prepare_items();

			$aData = array(
				'plugin_url'				=> self::$PLUGIN_URL,
				'message'					=> $this->message,

				'listingsTable'				=> $this->listingsTable,
				'preview_html'				=> isset($preview_html) ? $preview_html : '',

				'form_action'				=> 'admin.php?page='.self::ParentMenuId
			);
			$this->display( 'listings_page', $aData );

		}

		WPLE()->logger->logSpentTime('getVariations');
	} // onDisplayListingsPage()


	public function displayPrepareListingsPage( $selectedProducts ) {

		// show warning if duplicate products found
		$this->checkForDuplicates();

	    //Create an instance of our package class...
	    // $this->listingsTable = new ListingsTable();
    	//Fetch, prepare, sort, and filter our data...
	    $this->listingsTable->selectedItems = $selectedProducts;
	    $this->listingsTable->prepare_items();

		// get profiles
		$profilesModel = new ProfilesModel();
		$profiles = $profilesModel->getAll();

		$aData = array(
			'plugin_url'				=> self::$PLUGIN_URL,
			'message'					=> $this->message,

			'last_selected_profile'		=> self::getOption('last_selected_profile'),
			'profiles'					=> $profiles,
			'listingsTable'				=> $this->listingsTable,

			'form_action'				=> 'admin.php?page='.self::ParentMenuId
		);
		$this->display( 'listings_prepare_page', $aData );

	} // displayPrepareListingsPage()


	public function displayEditPage() {

		// get item
		$item = ListingsModel::getItem( intval($_REQUEST['auction']) );

		// unserialize details
		$this->initEC( $item['account_id'] );
		// $item['details'] = maybe_unserialize( $item['details'] );
		// echo "<pre>";print_r($item);echo"</pre>";die();

		// get ebay data
		$countries			 	= EbayShippingModel::getEbayCountries( $item['site_id'] );
		// $template_files 		= $this->getTemplatesList();
		$templatesModel = new TemplatesModel();
		$templates = $templatesModel->getAll();

		$aData = array(
			'plugin_url'				=> self::$PLUGIN_URL,
			'message'					=> $this->message,

			'item'						=> $item,
			'countries'					=> $countries,
			'template_files'			=> $templates,

			'form_action'				=> 'admin.php?page='.self::ParentMenuId . ( isset($_REQUEST['paged']) ? '&paged='.sanitize_key($_REQUEST['paged']) : '' )
		);
		$this->display( 'listings_edit_page', array_merge( $aData, $item ) );

	} // displayEditPage()


	private function saveListing() {
		global $wpdb;

        $listing_id = $this->getValueFromPost( 'listing_id' );
        $listing = new Listing( $listing_id );
        $listing
            ->setTitle( $this->getValueFromPost( 'auction_title' ) )
            ->setPrice( $this->getValueFromPost( 'price' ) )
            ->setQuantity( $this->getValueFromPost( 'quantity' ) )
            ->setDuration( $this->getValueFromPost( 'listing_duration' ) )
            ->setType( $this->getValueFromPost( 'auction_type' ) )
            ->setTemplate( $this->getValueFromPost( 'template' ) );

	    ## BEGIN PRO ##

		// get profile data and details
		$listingItem  = ListingsModel::getItem( $listing->getId() );
		$profile_data = $listingItem['profile_data'];

		$profile_data['details']['bestoffer_enabled']   = $this->getValueFromPost( 'bestoffer_enabled' );
		$profile_data['details']['bo_autoaccept_price'] = $this->getValueFromPost( 'bo_autoaccept_price' );
		$profile_data['details']['bo_minimum_price']    = $this->getValueFromPost( 'bo_minimum_price' );

//        $profile = $listing->getProfileData();
//        $profile->setProfileDetails( $profile_data );
        $listing->setProfileData( $profile_data );

	    ## END PRO ##

		// if item is published change status to changed
		if ( 'published' == $this->getValueFromPost( 'status' ) ) {
            $listing->setStatus( Listing::STATUS_CHANGED );
		}

		// handle developer settings
		if ( $this->getValueFromPost( 'enable_dev_mode' ) == '1' ) {
            $listing
                ->setStatus( $this->getValueFromPost( 'listing_status' ) )
                ->setEbayId( $this->getValueFromPost( 'ebay_id' ) )
                ->setProductId( $this->getValueFromPost( 'post_id' ) )
                ->setQuantitySold( $this->getValueFromPost( 'quantity_sold' ) )
                ->setSiteId( $this->getValueFromPost( 'site_id' ) )
                ->setAccountId( $this->getValueFromPost( 'account_id' ) );
		}

        $result = $listing->save();

        if ( is_wp_error( $result ) ) {
	        wple_show_message( "There was a problem saving your listing.<br>SQL:<pre>".$wpdb->last_query.'</pre>', 'error' );
	        return;
        }

		wple_show_message( __( 'Listing updated.', 'wp-lister-for-ebay' ) );

		// optionally revise item on save
		if ( 'yes' == $this->getValueFromPost( 'revise_item_on_save' ) ) {
			$account_id = WPLE_ListingQueryHelper::getAccountID( $listing->getId() );
			$this->initEC( $account_id );
			$this->EC->reviseItems( $listing->getId() );
			$this->EC->closeEbay();
			wple_show_message( __( 'Your changes were updated on eBay.', 'wp-lister-for-ebay' ) );
		}

	} // saveListing()

	public function checkForDuplicates() {

		// skip if dupe warning is disabled
		if ( self::getOption( 'hide_dupe_msg' ) ) return;

		// show warning if duplicate products found
		$duplicateProducts = WPLE_ListingQueryHelper::getAllDuplicateProducts();
		if ( ! empty($duplicateProducts) ) {

	        // get current page with paging as url param
	        $page = sanitize_key($_REQUEST['page']);
	        if ( isset( $_REQUEST['paged'] )) $page .= '&paged='.sanitize_key($_REQUEST['paged']);

			$msg  = '<p><b>'.sprintf( __( 'Warning: There are duplicate listings for %s product(s).', 'wp-lister-for-ebay' ), sizeof($duplicateProducts) ).'</b>';
			$msg .= '&nbsp; <a href="#" onclick="jQuery(\'#wpl_dupe_details\').toggle()" class="button button-small">'.__( 'Show details', 'wp-lister-for-ebay' ).'</a></p>';
			// $msg .= '<br>';
			$msg .= '<div id="wpl_dupe_details" style="display:none"><p>';
			$msg .= __( 'Creating multiple listings for one product is not recommended as it can cause issues syncing sales and other unexpected behaviour.', 'wp-lister-for-ebay' );
			$msg .= '<br>';
			$msg .= __( 'Please keep only one listing and move unwanted duplicates to the archive.', 'wp-lister-for-ebay' );
			$msg .= '<br><br>';

			$msg .= $this->renderDupeTable( $duplicateProducts );

			$msg .= __( 'If you are not planning to use the synchronize sales option, you can hide this warning in settings.', 'wp-lister-for-ebay' );
			// $msg .= '<br>';
			// $msg .= 'If you need to list single products multiple times for some reason, please contact support@wplab.com and we will find a solution.';
			$msg .= '</p></div>';
			wple_show_message( $msg, 'warn' );
		}

	} // checkForDuplicates()

	public function renderDupeTable( $listings, $column = 'post_id' ) {
		if ( empty($listings) ) return '';

        // get current page with paging as url param
        $page = sanitize_key($_REQUEST['page']);
        if ( isset( $_REQUEST['paged'] )) $page .= '&paged='.sanitize_key($_REQUEST['paged']);
		$msg = '';

		foreach ($listings as $dupe) {

			$account_title = WPLE_eBayAccount::getAccountTitle( $dupe->account_id );

			$msg .= '<b>'.__( 'Listings for product', 'wp-lister-for-ebay' ).' #'.$dupe->post_id.' ('.$account_title.'):</b>';
			$msg .= '<br>';

			$duplicateListings = WPLE_ListingQueryHelper::getAllListingsForProductAndAccount( $dupe->post_id, $dupe->account_id );

			foreach ($duplicateListings as $listing) {
				$color = $listing->status == 'archived' ? 'silver' : '';
				$msg .= '<span style="color:'.$color.'">';
				$msg .= '&nbsp;&bull;&nbsp;';
				$msg .= ''.$listing->auction_title.'';
				if ($listing->ebay_id) $msg .= ' (#'.$listing->ebay_id.')';
				$msg .= ' &ndash; <i>'.$listing->status.'</i>';
				$msg .= '<br>';
				if ( in_array( $listing->status, array( 'prepared', 'verified', 'ended', 'sold' ) ) ) {
					$archive_link = sprintf('<a class="archive button button-small" href="?page=%s&action=%s&auction=%s&_wpnonce=%s">%s</a>',$page,'wple_archive',$listing->id, wp_create_nonce( 'bulk-auctions' ), __( 'Click to move to archive', 'wp-lister-for-ebay' ));
					$msg .= '&nbsp;&nbsp;&nbsp;&nbsp;'.$archive_link;
					$msg .= '<br>';
				}
				if ( in_array( $listing->status, array( 'selected' ) ) ) {
					$delete_link = sprintf('<a class="delete button button-small button-primary" href="?page=%s&action=%s&auction=%s&_wpnonce=%s">%s</a>',$page,'wple_delete_listing',$listing->id, wp_create_nonce( 'wplister_delete_auction' ), __( 'Click to remove this listing', 'wp-lister-for-ebay' ));
					$msg .= '&nbsp;&nbsp;&nbsp;&nbsp;'.$delete_link;
					$msg .= '<br>';
				}
				$msg .= '</span>';
			}
			$msg .= '<br>';

		}

		return $msg;
	} // renderDupeTable()



	// check if we need to apply a profile to all its items
	public function checkForDelayedProfiles() {

		$profile_id = get_option('wple_job_reapply_profile_id' );
		if ( ! $profile_id ) return;

		$msg  = '<p>';
		$msg .= 'Please wait a moment while the profile is applied to all linked items.';
		$msg .= '&nbsp;&nbsp;';
		$msg .= '<a id="btn_run_delayed_profile_application" class="btn_run_delayed_profile_application button wpl_job_button">' . __( 'Apply Profile', 'wp-lister-for-ebay' ) . '</a>';
		$msg .= '</p>';
		wple_show_message( $msg, 'warn' );

	} // checkForDelayedProfiles()

	// check if we need to apply a template to all its items
	public function checkForDelayedTemplate() {

		$template_id = get_option('wple_job_reapply_template_id' );
		if ( ! $template_id ) return;

		$msg  = '<p>';
		$msg .= 'Please wait a moment while the template is applied to all linked items.';
		$msg .= '&nbsp;&nbsp;';
		$msg .= '<a id="btn_run_delayed_template_application" class="btn_run_delayed_template_application button wpl_job_button">' . __( 'Apply Template', 'wp-lister-for-ebay' ) . '</a>';
		$msg .= '</p>';
		wple_show_message( $msg, 'warn' );

	} // checkForDelayedTemplate()


	public function previewListing( $id ) {

		// init model
		$ibm        = new ItemBuilderModel();
		$account_id = WPLE_ListingQueryHelper::getAccountID( $id );
		$account    = WPLE_eBayAccount::getAccount( $account_id );

		$this->initEC( $account_id );
		$item = $ibm->buildItem( $id, $this->EC->session, false, true );
		WPLE()->logger->debug( 'preview item: '. print_r( $item, 1 ) );

		// if ( ! $ibm->checkItem($item) ) return $ibm->result;
		$ibm->checkItem($item);

		// $preview_html = $ibm->getFinalHTML( $id, $item, true );
		$preview_html = $item->Description;
		// echo $preview_html;

		// set condition name
		$item->ConditionName = $this->getConditionDisplayName( $item->getConditionID() );

		$aData = array(
			'item'				=> $item,
			'site_id'			=> $account ? $account->site_id : false,
			'check_result'		=> $ibm->result,
			'preview_html'		=> $preview_html
		);
		header('Content-Type: text/html; charset=utf-8');
		$this->display( 'listings_preview', $aData );
		exit();

	} // previewListing()


	public function getConditionDisplayName( $ConditionID ) {

		$conditions = array(
			1000 => 'New',
			1500 => 'New other',
			1750 => 'New with defects',
			2000 => 'Manufacturer refurbished',
			2500 => 'Seller refurbished',
			3000 => 'Used',
			4000 => 'Very Good',
			5000 => 'Good',
			6000 => 'Acceptable',
			7000 => 'For parts or not working',
		);

		if ( ! isset( $conditions[ $ConditionID ] ) ) return $ConditionID;

		return $conditions[ $ConditionID ];
	} // getConditionDisplayName()

	public function fixSubmenu() {
		global $submenu;
		if ( isset( $submenu[self::ParentMenuId] ) ) {
			$submenu[self::ParentMenuId][0][0] = __( 'Listings', 'wp-lister-for-ebay' );
		}
	}


	public function renderDeveloperOptions() {
		?>
		<div class="hidden" id="screen-options-wrap" style="display: block;">
			<form method="post" action="" id="dev-settings">
				<h5>Show on screen</h5>
				<div class="metabox-prefs">
						<label for="dev-hide">
							<input type="checkbox" onclick="jQuery('.dev_box').toggle();" value="dev" id="dev-hide" name="dev-hide" class="hide-column-tog">
							Developer options
						</label>
					<br class="clear">
				</div>
			</form>
		</div>
		<?php
	}


}
