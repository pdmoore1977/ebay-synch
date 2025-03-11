<?php

class WPL_AjaxHandler extends WPL_Core {

	public function config() {
		
		$this->configure_public_requests();
		$this->configure_private_requests();

	}
	
	// configure private AJAX requests
	private function configure_private_requests() {
	    // redirect to the Auth page
	    add_action( 'wp_ajax_wple_oauth_redirect',              array( $this, 'ajax_oauth_redirect' ) );

		// called from category tree
		add_action('wp_ajax_wple_get_ebay_categories_tree',  	array( &$this, 'ajax_get_ebay_categories_tree' ) );		
		add_action('wp_ajax_wple_get_store_categories_tree', 	array( &$this, 'ajax_get_store_categories_tree' ) );		

		// called from edit products page
		add_action('wp_ajax_wple_getCategorySpecifics',  		array( &$this, 'ajax_getCategorySpecifics' ) );		
		add_action('wp_ajax_wple_getCategoryConditions', 		array( &$this, 'ajax_getCategoryConditions' ) );		
		
		// called from jobs window
		add_action('wp_ajax_wpl_jobs_load_tasks', 				array( &$this, 'jobs_load_tasks' ) );	
		add_action('wp_ajax_wpl_jobs_run_task', 				array( &$this, 'jobs_run_task' ) );	
		add_action('wp_ajax_wpl_jobs_complete_job', 			array( &$this, 'jobs_complete_job' ) );	

		// logfile viewer
		add_action('wp_ajax_wple_tail_log', 					array( &$this, 'ajax_wple_tail_log' ) );
		add_action( 'wp_ajax_wple_load_log_file',               array( $this, 'ajax_wple_load_log_file' ) );
		add_action( 'wp_ajax_wple_download_log_file',           array( $this, 'ajax_wple_download_log_file' ) );

		// profile selector
		add_action('wp_ajax_wple_select_profile', 				array( &$this, 'ajax_wple_select_profile' ) );
		add_action('wp_ajax_wple_show_profile_selection', 		array( &$this, 'ajax_wple_show_profile_selection' ) );

		// product matcher
		add_action('wp_ajax_wple_show_product_matches', 		array( &$this, 'ajax_wple_show_product_matches' ) );

		add_action('wp_ajax_wple_add_responsible_person', 		array( &$this, 'ajax_wple_add_responsible_person' ) );
		add_action('wp_ajax_wple_delete_responsible_person', 		array( &$this, 'ajax_wple_delete_responsible_person' ) );
		add_action('wp_ajax_wple_get_responsible_persons', 		array( &$this, 'ajax_wple_get_responsible_persons' ) );

		add_action('wp_ajax_wple_add_manufacturer',             array( &$this, 'ajax_wple_add_manufacturer' ) );
		add_action('wp_ajax_wple_delete_manufacturer',          array( &$this, 'ajax_wple_delete_manufacturer' ) );
		add_action('wp_ajax_wple_get_manufacturers',             array( &$this, 'ajax_wple_get_manufacturers' ) );

		add_action('wp_ajax_wple_add_document',                 array( &$this, 'ajax_wple_add_document' ) );

        /*** ## BEGIN PRO ## ***/
		// create order
        add_action( 'wp_ajax_wple_create_order',                array( $this, 'ajax_wple_create_order' ) );
        /*** ## END PRO ## ***/

        add_action( 'wp_ajax_wple_dismiss_notice',              array( $this, 'ajax_dismiss_notice' ) );
        add_action( 'wp_ajax_wple_hide_gnutls_error',                  array( $this, 'ajax_hide_gnutls_error' ) );

    }
	
	// configure public AJAX requests
	private function configure_public_requests() {

		// handle dynamic listing galleries
		add_action('wp_ajax_wpl_gallery', 						array( &$this, 'ajax_wpl_gallery' ) );
		add_action('wp_ajax_nopriv_wpl_gallery', 				array( &$this, 'ajax_wpl_gallery' ) );

		// handle request for eBay store categories (JSON)
		add_action('wp_ajax_wpl_ebay_store_categories', 		array( &$this, 'ajax_wpl_ebay_store_categories' ) );
		add_action('wp_ajax_nopriv_wpl_ebay_store_categories', 	array( &$this, 'ajax_wpl_ebay_store_categories' ) );

		// handle request for eBay item queries
		add_action('wp_ajax_wpl_ebay_item_query', 				array( &$this, 'ajax_wpl_ebay_item_query' ) );
		add_action('wp_ajax_nopriv_wpl_ebay_item_query', 		array( &$this, 'ajax_wpl_ebay_item_query' ) );

		## BEGIN PRO ##
		// handle incoming ebay notifications
		add_action('wp_ajax_handle_ebay_notify', 				array( &$this, 'ajax_handle_ebay_notify' ) );
		add_action('wp_ajax_nopriv_handle_ebay_notify', 		array( &$this, 'ajax_handle_ebay_notify' ) );
		## END PRO ##
	}

    public function ajax_oauth_redirect() {
        check_admin_referer( 'wple_oauth_redirect' );

        if ( !current_user_can( 'manage_ebay_listings' ) ) {
            die(0);
        }

        $state_code = md5( get_bloginfo( 'url' ) );
        $auth_url = 'https://auth.wplister.com/ebay-oauth/auth.php?state='. $state_code;

        if ( !empty( $_GET['test'] ) ) {
            $auth_url = add_query_arg( 'test', 1, $auth_url );
        }

        // store the state_code for token retrieval after authentication
        set_transient( 'wple_auth_state', $state_code, 3600 ); // expires in an hour

        wp_redirect( $auth_url );
        exit;
	}


	// show profile selection
	public function ajax_wple_show_profile_selection() {

		// check nonce and permissions
	    // check_admin_referer( 'wple_ajax_nonce' ); // skip nonce check as this method accepts no user params
		if ( ! current_user_can('prepare_ebay_listings') ) return;

		// fetch profiles
		$pm = new ProfilesModel();
		$profiles = $pm->getAll();

		// load template
		$tpldata = array(
			'plugin_url'  => self::$PLUGIN_URL,
			'message'     => $this->message,
			'profiles'    => $profiles,				
			'form_action' => 'admin.php?page='.self::ParentMenuId
		);

		WPLE()->pages['listings']->display( 'profile/select_profile', $tpldata );
		exit();
	
	} // ajax_wple_show_profile_selection()


	// match product
	public function ajax_wple_select_profile() {

		// check nonce and permissions
	    check_admin_referer( 'wple_ajax_nonce' );
		if ( ! current_user_can('prepare_ebay_listings') ) return;

		// check parameters
		if ( ! isset( $_REQUEST['profile_id']  ) ) return;
		if ( ! isset( $_REQUEST['product_ids'] ) ) return;

		$profile_id  = wple_clean($_REQUEST['profile_id']);
		$product_ids = wple_clean($_REQUEST['product_ids']);
		$select_mode = wple_clean($_REQUEST['select_mode']);
		$default_account_id = get_option( 'wplister_default_account_id', 1 );

		$lm = new ListingsModel();
		if ( 'products' == $select_mode ) {

	        // get profile
			$pm = new ProfilesModel();
			$profile = $pm->getItem( $profile_id );
	
			// prepare new listings from products
			// $response = $lm->prepareListings( $product_ids, $profile_id );
			$response = $lm->prepareListings( $product_ids, $profile_id );

	        $lm->applyProfileToNewListings( $profile );		      

		} elseif ( 'listings' == $select_mode ) {

			// change profile for existing listings
			// $profile = WPLE_AmazonProfile::getProfile( $profile_id ); // doesn't work
			$pm = new ProfilesModel();
			$profile = $pm->getItem( $profile_id );
			// $items = $lm->applyProfileToListings( $profile, $product_ids );
			foreach ($product_ids as $listing_id) {
				$item = ListingsModel::getItem( $listing_id );
				$lm->applyProfileToItem( $profile, $item );
			}

			// build response
			$response = new stdClass();
			// $response->success     = $prepared_count ? true : false;
			$response->success        = true;
			$response->msg 			  = sprintf( __( 'Profile "%s" was applied to %s items.', 'wp-lister-for-ebay' ), $profile['profile_name'], count($product_ids) );
			$this->returnJSON( $response );
			exit();
		} else {
			die('invalid select mode: '.$select_mode);
		}
	
		if ( $response->success ) {

			// store ASIN as product meta
			// update_post_meta( $post_id, '_wple_asin', $asin );

			// show message
			if ( $response->skipped_count ) {
				$response->msg = sprintf( __( '%s product(s) have been prepared and %s products were skipped.', 'wp-lister-for-ebay' ), $response->prepared_count, $response->skipped_count );
			} else {
				$response->msg = sprintf( __( '%s product(s) have been prepared.', 'wp-lister-for-ebay' ), $response->prepared_count );
			}

			// include link to prepared listings
			$response->msg .= '&nbsp; <a href="admin.php?page=wplister&listing_status=prepared" class="button button-small button-primary">'.__( 'View prepared listings', 'wp-lister-for-ebay' ).'</a>';

			// show shorter message if no listings were prepared
			if ( ! $response->prepared_count )
				$response->msg = sprintf( __( '%s products have been skipped.', 'wp-lister-for-ebay' ), $response->skipped_count );

			if ( $response->errors )
				$response->msg .= '<br>'.join('<br>',$response->errors);
			if ( $response->warnings )
				$response->msg .= '<br>'.join('<br>',$response->warnings);


			$this->returnJSON( $response );
			exit();

		} else {
			if ( isset($lm->lastError) ) echo $lm->lastError."\n";
			echo "Failed to prepare product!";
			exit();
		}

	} // ajax_wple_select_profile()


	// fetch category specifics
	public function ajax_getCategorySpecifics() {

		// check nonce and permissions
	    check_admin_referer( 'wple_getCategorySpecifics' );
		if ( ! current_user_can('prepare_ebay_listings') ) return;

		// check parameters
		if ( ! isset( $_REQUEST['id']  ) ) return;
		
		$category_id = intval($_REQUEST['id']);
		$account_id  = isset( $_REQUEST['account_id'] ) ? wple_clean($_REQUEST['account_id']) : get_option( 'wplister_default_account_id' );
		$site_id     = isset( $_REQUEST['site_id'] )    ? wple_clean($_REQUEST['site_id']   ) : 0;

		// $this->initEC( $account_id );
		// $result = $this->EC->getCategorySpecifics( $category_id );
		// $this->EC->closeEbay();

		// improved version of the above, using ebay_categories as cache
		$specifics = EbayCategoriesModel::getItemSpecificsForCategory( $category_id, $site_id, $account_id );

		$this->returnJSON( $specifics );
		exit();
	}
	
	// fetch category conditions
	public function ajax_getCategoryConditions() {

		// check nonce and permissions
	    check_admin_referer( 'wple_getCategoryConditions' );
		if ( ! current_user_can('prepare_ebay_listings') ) return;

		// check parameters
		if ( ! isset( $_REQUEST['id']  ) ) return;
			
		$category_id = intval($_REQUEST['id']);
		$account_id  = isset( $_REQUEST['account_id'] ) ? wple_clean($_REQUEST['account_id']) : get_option( 'wplister_default_account_id' );
		$site_id     = isset( $_REQUEST['site_id'] )    ? wple_clean($_REQUEST['site_id']   ) : 0;

		// $this->initEC( $account_id );
		// $result = $this->EC->getCategoryConditions( $category_id );
		// $this->EC->closeEbay();

		// improved version of the above, using ebay_categories as cache
		$conditions = EbayCategoriesModel::getConditionsForCategory( $category_id, $site_id, $account_id );

		$this->returnJSON( $conditions );
		exit();
	}
	
	function shutdown_handler() {
		global $wpl_shutdown_handler_enabled;
		if ( ! $wpl_shutdown_handler_enabled ) return;

		// check for fatal error
        $error = error_get_last();
        if ($error['type'] === E_ERROR) {

	        $logmsg  = "<br><br>";
	        $logmsg .= "<b>There has been a fatal PHP error - the server said:</b><br>";
	        $logmsg .= '<span style="color:darkred">'.$error['message']."</span><br>";
	        $logmsg .= "In file: <code>".$error['file']."</code> (line ".$error['line'].")<br>";

	        $logmsg .= "<br>";
	        $logmsg .= "<b>Please contact support in order to resolve this.</b><br>";
	        $logmsg .= "If this error is related to memory limits or timeouts, you need to contact your server administrator or hosting provider.<br>";
	        echo $logmsg;

		} 
		// debug all errors
		// echo "<br>Last error: <pre>".print_r($error,1)."</pre>"; 
	}

	// run single task
	public function jobs_run_task() {

		// check nonce and permissions
	    check_admin_referer( 'wple_ajax_nonce' );
		if ( ! current_user_can('manage_ebay_listings') ) return;

		// quit if no job name provided
		if ( ! isset( $_REQUEST['job'] ) ) return false;
		if ( ! isset( $_REQUEST['task'] ) ) return false;

		$job        = wple_clean($_REQUEST['job']);
		$task       = wple_clean($_REQUEST['task']);
		$site_id    = isset( $task['site_id'] ) ? $task['site_id'] : false;
		$account_id = isset( $task['account_id'] ) ? $task['account_id'] : false;

		// register shutdown handler
		global $wpl_shutdown_handler_enabled;
		$wpl_shutdown_handler_enabled = true;
		register_shutdown_function( array( $this, 'shutdown_handler' ) );

		WPLE()->logger->info('running task: '.print_r($task,1));

		// handle job name
		switch ( $task['task'] ) {
			/*case 'loadShippingServices':
				
				// call EbayController
				$this->initEC( $account_id, $site_id );
				$result = $this->EC->loadShippingServices( $site_id );
				$this->EC->closeEbay();

				// build response
				$response = new stdClass();
				$response->job  	= $job;
				$response->task 	= $task;
				$response->result 	= $result;
				$response->errors   = array();
				$response->success  = true;
				
				$this->returnJSON( $response );
				exit();*/

            case 'getCountryDetails':
                // call EbayController
                $this->initEC( $account_id, $site_id );
                $result = $this->EC->getCountryDetails( $site_id );
                $this->EC->closeEbay();

                // build response
                $response = new stdClass();
                $response->job  	= $job;
                $response->task 	= $task;
                $response->result 	= $result;
                $response->errors   = array();
                $response->success  = true;

                $this->returnJSON( $response );
                exit();

            case 'getShippingLocations':
                // call EbayController
                $this->initEC( $account_id, $site_id );
                $result = $this->EC->getShippingLocations( $site_id );
                $this->EC->closeEbay();

                // build response
                $response = new stdClass();
                $response->job  	= $job;
                $response->task 	= $task;
                $response->result 	= $result;
                $response->errors   = array();
                $response->success  = true;

                $this->returnJSON( $response );
                exit();

            case 'getShippingDetails':
                // call EbayController
                $this->initEC( $account_id, $site_id );
                $result = $this->EC->getShippingDetails( $site_id );
                $this->EC->closeEbay();

                // build response
                $response = new stdClass();
                $response->job  	= $job;
                $response->task 	= $task;
                $response->result 	= $result;
                $response->errors   = array();
                $response->success  = true;

                $this->returnJSON( $response );
                exit();

            case 'getDispatchTimes':
                // call EbayController
                $this->initEC( $account_id, $site_id );
                $result = $this->EC->getDispatchTimes( $site_id );
                $this->EC->closeEbay();

                // build response
                $response = new stdClass();
                $response->job  	= $job;
                $response->task 	= $task;
                $response->result 	= $result;
                $response->errors   = array();
                $response->success  = true;

                $this->returnJSON( $response );
                exit();

            case 'getShippingPackages':
                // call EbayController
                $this->initEC( $account_id, $site_id );
                $result = $this->EC->getShippingPackages( $site_id );
                $this->EC->closeEbay();

                // build response
                $response = new stdClass();
                $response->job  	= $job;
                $response->task 	= $task;
                $response->result 	= $result;
                $response->errors   = array();
                $response->success  = true;

                $this->returnJSON( $response );
                exit();

            case 'getExcludeShippingLocations':
                // call EbayController
                $this->initEC( $account_id, $site_id );
                $result = $this->EC->getExcludeShippingLocations( $site_id );
                $this->EC->closeEbay();

                // build response
                $response = new stdClass();
                $response->job  	= $job;
                $response->task 	= $task;
                $response->result 	= $result;
                $response->errors   = array();
                $response->success  = true;

                $this->returnJSON( $response );
                exit();

            case 'getDoesNotApplyText':
                // call EbayController
                $this->initEC( $account_id, $site_id );
                $result = $this->EC->getDoesNotApplyText( $site_id );
                $this->EC->closeEbay();

                // build response
                $response = new stdClass();
                $response->job  	= $job;
                $response->task 	= $task;
                $response->result 	= $result;
                $response->errors   = array();
                $response->success  = true;

                $this->returnJSON( $response );
                exit();
			
			/*case 'loadPaymentOptions':
				
				// call EbayController
				$this->initEC( $account_id, $site_id );
				$result = $this->EC->loadPaymentOptions( $site_id );
				$this->EC->closeEbay();

				// build response
				$response = new stdClass();
				$response->job  	= $job;
				$response->task 	= $task;
				$response->result 	= $result;
				$response->errors   = array();
				$response->success  = true;
				
				$this->returnJSON( $response );
				exit();*/

            case 'getPaymentDetails':

                // call EbayController
                $this->initEC( $account_id, $site_id );
                $result = $this->EC->getPaymentDetails( $site_id );
                $this->EC->closeEbay();

                // build response
                $response = new stdClass();
                $response->job  	= $job;
                $response->task 	= $task;
                $response->result 	= $result;
                $response->errors   = array();
                $response->success  = true;

                $this->returnJSON( $response );
                exit();

            case 'getMinimumStartPrices':

                // call EbayController
                $this->initEC( $account_id, $site_id );
                $result = $this->EC->getMinimumStartPrices( $site_id );
                $this->EC->closeEbay();

                // build response
                $response = new stdClass();
                $response->job  	= $job;
                $response->task 	= $task;
                $response->result 	= $result;
                $response->errors   = array();
                $response->success  = true;

                $this->returnJSON( $response );
                exit();

            case 'getReturnPolicyDetails':

                // call EbayController
                $this->initEC( $account_id, $site_id );
                $result = $this->EC->getReturnPolicyDetails( $site_id );
                $this->EC->closeEbay();

                // build response
                $response = new stdClass();
                $response->job  	= $job;
                $response->task 	= $task;
                $response->result 	= $result;
                $response->errors   = array();
                $response->success  = true;

                $this->returnJSON( $response );
                exit();
			
			case 'loadStoreCategories':
				
				// call EbayController
				$this->initEC( $account_id );
				$result = $this->EC->loadStoreCategories( $account_id );
				$this->EC->closeEbay();

				// build response
				$response = new stdClass();
				$response->job  	= $job;
				$response->task 	= $task;
				$response->result 	= $result;
				$response->errors   = array();
				$response->success  = true;
				
				$this->returnJSON( $response );
				exit();

			case 'loadHazardousMaterialsLabels':
				// call EbayController
				$this->initEC( $account_id );
				$result = $this->EC->loadHazardousMaterialsLabels( $account_id );
				$this->EC->closeEbay();

				// build response
				$response = new stdClass();
				$response->job  	= $job;
				$response->task 	= $task;
				$response->result 	= $result;
				$response->errors   = array();
				$response->success  = true;

				$this->returnJSON( $response );
				exit();

			case 'loadProductSafetyLabels':
				// call EbayController
				$this->initEC( $account_id );
				$result = $this->EC->loadProductSafetyLabels( $account_id );
				$this->EC->closeEbay();

				// build response
				$response = new stdClass();
				$response->job  	= $job;
				$response->task 	= $task;
				$response->result 	= $result;
				$response->errors   = array();
				$response->success  = true;

				$this->returnJSON( $response );
				exit();
			
			case 'getUserToken':
				
				// call EbayController
				$this->initEC( $account_id );
				$result = $this->EC->loadUserAccountDetails();
				$this->EC->closeEbay();

		        // update account (seller profiles etc.)
		        $account = new WPLE_eBayAccount( $account_id );
		        if ( $account ) $account->getUserToken();

				// build response
				$response = new stdClass();
				$response->job  	= $job;
				$response->task 	= $task;
				$response->result 	= $result;
				$response->errors   = array();
				$response->success  = true;
				
				$this->returnJSON( $response );
				exit();

            case 'getUserDetails':

                // update account (seller profiles etc.)
                $account = new WPLE_eBayAccount( $account_id );
                if ( $account ) $account->getUserDetails();

                // build response
                $response = new stdClass();
                $response->job  	= $job;
                $response->task 	= $task;
                $response->result 	= true;
                $response->errors   = array();
                $response->success  = true;

                $this->returnJSON( $response );
                exit();

            case 'getUserPreferences':

                // update account (seller profiles etc.)
                $account = new WPLE_eBayAccount( $account_id );
                if ( $account ) $account->getUserPreferences();

                // build response
                $response = new stdClass();
                $response->job  	= $job;
                $response->task 	= $task;
                $response->result 	= true;
                $response->errors   = array();
                $response->success  = true;

                $this->returnJSON( $response );
                exit();
			
			case 'loadEbayCategoriesBranch':
				
				// call EbayController
				$this->initEC( $account_id, $site_id );
				$result = $this->EC->loadEbayCategoriesBranch( $task['cat_id'], $task['site_id'] );
				$this->EC->closeEbay();

				// build response
				$response = new stdClass();
				$response->job  	= $job;
				$response->task 	= $task;
				$response->result 	= $result;
				$response->errors   = array();
				$response->success  = true;
				
				$this->returnJSON( $response );
				exit();
			
			case 'verifyItem':
				
				// call EbayController
				$this->initEC( $account_id );
				$results = $this->EC->verifyItems( $task['id'] );
				$this->EC->closeEbay();
				$this->handleSubTasksInResults( $results, $job, $task );

				// build response
				$response = new stdClass();
				$response->job  	= $job;
				$response->task 	= $task;
				$response->errors   = $results[0]->errors;
				$response->success  = $results[0]->success;
			
				$this->returnJSON( $response );
				exit();
			
			case 'publishItem':
				
				// call EbayController
				$this->initEC( $account_id );
				$results = $this->EC->sendItemsToEbay( $task['id'] );
				$this->EC->closeEbay();
				$this->handleSubTasksInResults( $results, $job, $task );

				// build response
				$response = new stdClass();
				$response->job  	= $job;
				$response->task 	= $task;
				$response->errors   = $results[0]->errors;
				$response->success  = $results[0]->success;
				
				$this->returnJSON( $response );
				exit();
			
			case 'reviseItem':
				
				// call EbayController
				$this->initEC( $account_id );
				$results = $this->EC->reviseItems( $task['id'] );
				$this->EC->closeEbay();
				$this->handleSubTasksInResults( $results, $job, $task );

				// build response
				$response = new stdClass();
				$response->job  	= $job;
				$response->task 	= $task;
				$response->errors   = $results[0]->errors;
				$response->success  = $results[0]->success;
				
				$this->returnJSON( $response );
				exit();
			
			case 'updateItem':
				
				// call EbayController
				$this->initEC( $account_id );
				$results = $this->EC->updateItemsFromEbay( $task['id'] );
				$this->EC->closeEbay();

				// build response
				$response = new stdClass();
				$response->job  	= $job;
				$response->task 	= $task;
				$response->errors   = $results[0]->errors;
				$response->success  = $results[0]->success;
				
				$this->returnJSON( $response );
				exit();
			
			case 'endItem':
				
				// call EbayController
				$this->initEC( $account_id );
				$results = $this->EC->endItemsOnEbay( $task['id'] );
				$this->EC->closeEbay();
				$this->handleSubTasksInResults( $results, $job, $task );

				// build response
				$response = new stdClass();
				$response->job  	= $job;
				$response->task 	= $task;
				$response->errors   = $results[0]->errors;
				$response->success  = $results[0]->success;
				
				$this->returnJSON( $response );
				exit();
			
			case 'relistItem':
				
				// call EbayController
				$this->initEC( $account_id );
				$results = $this->EC->relistItems( $task['id'] );
				$this->EC->closeEbay();
				$this->handleSubTasksInResults( $results, $job, $task );

				// build response
				$response = new stdClass();
				$response->job  	= $job;
				$response->task 	= $task;
				$response->errors   = $results[0]->errors;
				$response->success  = $results[0]->success;
				
				$this->returnJSON( $response );
				exit();
			
			case 'uploadToEPS':
				
				// call EbayController
				$this->initEC( $account_id );

				$lm = new ListingsModel();
				$eps_url = $lm->uploadPictureToEPS( $task['img'], $task['id'], $this->EC->session );

				$this->EC->closeEbay();

				// build response
				$response = new stdClass();
				$response->job  	= $job;
				$response->task 	= $task;
				// $response->errors   = $eps_url ? false : $lm->result->errors;
				$response->errors   = is_object( $lm->result ) && isset( $lm->result->errors ) ? $lm->result->errors : array();
				// $response->success  = $lm->result->success;
				$response->success  = $eps_url ? true : false;
				
				$this->returnJSON( $response );
				exit();
			
			case 'applyProfileDelayed':
				
				$profile_id = $task['profile_id'];
				$offset     = $task['offset'];
				$limit      = $task['limit'];

				$profilesModel = new ProfilesModel();
		        $profile = $profilesModel->getItem( $profile_id );

		        $lm = new ListingsModel();
				$items1 = WPLE_ListingQueryHelper::getAllPreparedWithProfile( $profile_id );
				$items2 = WPLE_ListingQueryHelper::getAllVerifiedWithProfile( $profile_id );
				$items3 = WPLE_ListingQueryHelper::getAllPublishedWithProfile( $profile_id );
				$items  = array_merge( $items1, $items2, $items3 );
				$total_items = sizeof($items);

				// extract batch
				$items = array_slice( $items, $offset, $limit );

				// apply profile to items
		        $lm->applyProfileToItems( $profile, $items );

		        // reset reminder option when last batch is run
		        if ( $offset + $limit >= $total_items ) {
		        	delete_option( 'wple_job_reapply_profile_id' );
		        }

				// build response
				$response = new stdClass();
				$response->job  	= $job;
				$response->task 	= $task;
				$response->errors   = array();
				// $response->errors   = array( array( 'HtmlMessage' => ' Profile was applied to '.sizeof($items).' items ') );
				$response->success  = true;
				
				$this->returnJSON( $response );
				exit();
			
			case 'applyTemplateDelayed':
				
				$template_id = $task['template_id'];
				$offset      = $task['offset'];
				$limit       = $task['limit'];

		        $lm = new ListingsModel();
				$items = WPLE_ListingQueryHelper::getAllPublishedWithTemplate( $template_id, $limit, $offset );
				$total_items = sizeof($items);

				// extract batch
				//$items = array_slice( $items, $offset, $limit );

				// apply profile to items
		        foreach ($items as $item) {

		        	// don't mark locked items as changed
		        	if ( ! $item['locked'] ) {
			        	$lm->reapplyProfileToItem( $item['id'] );
		        	}
			        
		        }

		        // reset reminder option when last batch is run
		        if ( $offset + $limit >= $total_items ) {
		        	//update_option( 'wple_job_reapply_template_id', '' );
                    delete_option( 'wple_job_reapply_template_id' );
		        }

				// build response
				$response = new stdClass();
				$response->job  	= $job;
				$response->task 	= $task;
				$response->errors   = array();
				// $response->errors   = array( array( 'HtmlMessage' => ' Template was applied to '.sizeof($items).' items ') );
				$response->success  = true;
				
				$this->returnJSON( $response );
				exit();
			
			default:
				// echo "unknown task";
				// exit();
		}

	}
	
	// handle subtasks
	public function handleSubTasksInResults( $results, $job, $task ) {

		// if ( isset( $results[0]->subtasks ) ) {

		// 	// build response
		// 	$response = new stdClass();
		// 	$response->job  	= $job;
		// 	$response->task 	= $task;
		// 	$response->errors   = $results[0]->errors;
		// 	$response->success  = $results[0]->success;
		// 	$this->returnJSON( $response );
		// 	exit;

		// }

	}
	
	// load task list
	public function jobs_load_tasks() {

		// check nonce and permissions
	    check_admin_referer( 'wple_ajax_nonce' );
		if ( ! current_user_can('manage_ebay_listings') ) return;

		// quit if no job name provided
		if ( ! isset( $_REQUEST['job'] ) ) return false;
		$jobname = wple_clean($_REQUEST['job']);

		// check if an array of listing IDs was provided
		$listing_ids = ( isset( $_REQUEST['listing_ids'] ) && is_array( $_REQUEST['listing_ids'] ) ) ? wple_clean($_REQUEST['listing_ids']) : false;
		if ( $listing_ids ) {
	        $items = WPLE_ListingQueryHelper::getItemsByIdArray( $listing_ids );
		}

		// handle job name
		switch ( $jobname ) {
			case 'updateEbayData':
				
				// call EbayController
				$site_id    = ( isset($_REQUEST['site_id'])    ? wple_clean($_REQUEST['site_id']   ) : get_option('wplister_ebay_site_id') );
				$account_id = ( isset($_REQUEST['account_id']) ? wple_clean($_REQUEST['account_id']) : get_option('wplister_default_account_id') );

				$this->initEC( $account_id );
				$tasks = $this->EC->initCategoriesUpdate( $site_id );
				$this->EC->closeEbay();

				// update store categories for each account using this site_id
				$accounts = WPLE_eBayAccount::getAll();
				foreach ( $accounts as $account ) {

					if ( $site_id != $account->site_id ) continue;
				
					// add task - load user specific details
                    if ( empty( $account->oauth_token ) ) {
                        $tasks[] = array(
                            'task'        => 'getUserToken',
                            'displayName' => 'loading eBay account details for '.$account->title,
                            'account_id'  => $account->id,
                        );
                    }


                    $tasks[] = array(
                        'task'        => 'getUserDetails',
                        'displayName' => 'getting details for '.$account->title,
                        'account_id'  => $account_id,
                    );

                    $tasks[] = array(
                        'task'        => 'getUserPreferences',
                        'displayName' => 'getting preferences for '.$account->title,
                        'account_id'  => $account_id,
                    );

					// add task - load store categories
					$tasks[] = array( 
						'task'        => 'loadStoreCategories', 
						'displayName' => 'update custom store categories for '.$account->title,
						'account_id'  => $account_id,
					);

					// download GPSR Metadata
					$tasks[] = array(
						'task'        => 'loadHazardousMaterialsLabels',
						'displayName' => 'update Hazardous Materials Labels for '.$account->title,
						'account_id'  => $account_id,
					);

					$tasks[] = array(
						'task'        => 'loadProductSafetyLabels',
						'displayName' => 'update Product Safety Labels for '.$account->title,
						'account_id'  => $account_id,
					);

				} // for each account


				// build response
				$response = new stdClass();
				$response->tasklist = $tasks;
				$response->total_tasks = count( $tasks );
				$response->error    = '';
				$response->success  = true;
				
				// create new job
				$newJob = new stdClass();
				$newJob->jobname = $jobname;
				$newJob->tasklist = $tasks;
				$job = new JobsModel( $newJob );
				$response->job_key = $job->key;

				$this->returnJSON( $response );
				exit();
			
			case 'verifyItems':
				
		        $response = $this->_create_bulk_listing_job( 'verifyItem', $items, $jobname );
				$this->returnJSON( $response );
				exit();
			
			case 'publishItems':
				
		        $response = $this->_create_bulk_listing_job( 'publishItem', $items, $jobname );
				$this->returnJSON( $response );
				exit();
			
			case 'reviseItems':
				
		        $response = $this->_create_bulk_listing_job( 'reviseItem', $items, $jobname );
				$this->returnJSON( $response );
				exit();
			
			case 'updateItems':
				
		        $response = $this->_create_bulk_listing_job( 'updateItem', $items, $jobname );
				$this->returnJSON( $response );
				exit();
			
			case 'endItems':
				
		        $response = $this->_create_bulk_listing_job( 'endItem', $items, $jobname );
				$this->returnJSON( $response );
				exit();
			
			case 'relistItems':
				
		        $response = $this->_create_bulk_listing_job( 'relistItem', $items, $jobname );
				$this->returnJSON( $response );
				exit();
			
			case 'verifyAllPreparedItems':
				
				// get prepared items
		        $items = WPLE_ListingQueryHelper::getAllPrepared();
		        
		        // create job from items and send response
		        $response = $this->_create_bulk_listing_job( 'verifyItem', $items, $jobname );
				$this->returnJSON( $response );
				exit();
			
			case 'publishAllVerifiedItems':
				
				// get verified items
		        $items = WPLE_ListingQueryHelper::getAllVerified();
		        
		        // create job from items and send response
		        $response = $this->_create_bulk_listing_job( 'publishItem', $items, $jobname );
				$this->returnJSON( $response );
				exit();
			
			case 'publishAllPreparedItems':
				
				// get prepared items
		        $items = WPLE_ListingQueryHelper::getAllPrepared();
		        
		        // create job from items and send response
		        $response = $this->_create_bulk_listing_job( 'publishItem', $items, $jobname );
				$this->returnJSON( $response );
				exit();
			
			case 'reviseAllChangedItems':
			    $revise_limit = get_option( 'wplister_revise_all_listings_limit', null );
				
				// get changed items
		        $items = WPLE_ListingQueryHelper::getAllChangedItemsToRevise( $revise_limit, true );
		        
		        // create job from items and send response
		        $response = $this->_create_bulk_listing_job( 'reviseItem', $items, $jobname );
				$this->returnJSON( $response );
				exit();
			
			case 'relistAllRestockedItems':
				
				// get restocked items
		        $items = WPLE_ListingQueryHelper::getAllEndedItemsToRelist();
		        
		        // create job from items and send response
		        $response = $this->_create_bulk_listing_job( 'relistItem', $items, $jobname );
				$this->returnJSON( $response );
				exit();
			
			case 'updateAllPublishedItems':
				
				// get published items
		        $items = WPLE_ListingQueryHelper::getAllPublished();
		        
		        // create job from items and send response
		        $response = $this->_create_bulk_listing_job( 'updateItem', $items, $jobname );
				$this->returnJSON( $response );
				exit();
			
			case 'updateAllRelistedItems':
				
				// get published items
		        $items = WPLE_ListingQueryHelper::getAllRelisted();
		        
		        // create job from items and send response
		        $response = $this->_create_bulk_listing_job( 'updateItem', $items, $jobname );
				$this->returnJSON( $response );
				exit();
			
			case 'runDelayedProfileApplication':
				
				// get items using given profile
				$profile_id = get_option('wple_job_reapply_profile_id' );
				if ( ! $profile_id ) return;

				$items1 = WPLE_ListingQueryHelper::getAllPreparedWithProfile( $profile_id );
				$items2 = WPLE_ListingQueryHelper::getAllVerifiedWithProfile( $profile_id );
				$items3 = WPLE_ListingQueryHelper::getAllPublishedWithProfile( $profile_id );
				$items  = array_merge( $items1, $items2, $items3 );

				$total_items = sizeof($items);
				$batch_size  = get_option( 'wplister_apply_profile_batch_size', 1000 );
				$tasks       = array();

				// echo "<pre>profile_id: ";echo $profile_id;echo"</pre>";
				// echo "<pre>total: ";echo $total_items;echo"</pre>";die();
		        
				for ( $page=0; $page < ($total_items / $batch_size); $page++ ) { 

					$from = $page * $batch_size + 1;
					$to   = $page * $batch_size + $batch_size;
					$to   = min( $to, $total_items );

					// add task - load user specific details
					$tasks[] = array( 
						'task'        => 'applyProfileDelayed', 
						'displayName' => 'Apply profile to items '.$from.' to '.$to,
						'profile_id'  => $profile_id,
						'offset'      => $page * $batch_size,
						'limit'       => $batch_size,
					);

				}

				// build response
				$response = new stdClass();
				$response->tasklist    = $tasks;
				$response->total_tasks = count( $tasks );
				$response->error       = '';
				$response->success     = true;
				
				// create new job
				$newJob = new stdClass();
				$newJob->jobname = $jobname;
				$newJob->tasklist = $tasks;
				$job = new JobsModel( $newJob );
				$response->job_key = $job->key;

				$this->returnJSON( $response );
				exit();
			
			case 'runDelayedTemplateApplication':
				
				// get items using given profile
				$template_id = get_option('wple_job_reapply_template_id' );
				if ( ! $template_id ) return;

				$total_items = WPLE_ListingQueryHelper::countItemsUsingTemplate( $template_id, 'published' );

				//$total_items = sizeof($items);
				$batch_size  = get_option( 'wplister_apply_profile_batch_size', 1000 );
				$tasks       = array();

				// echo "<pre>template_id: ";echo $template_id;echo"</pre>";
				// echo "<pre>total: ";echo $total_items;echo"</pre>";die();
		        
				for ( $page=0; $page < ($total_items / $batch_size); $page++ ) { 

					$from = $page * $batch_size + 1;
					$to   = $page * $batch_size + $batch_size;
					$to   = min( $to, $total_items );

					// add task - load user specific details
					$tasks[] = array( 
						'task'        => 'applyTemplateDelayed', 
						'displayName' => 'Apply template to items '.$from.' to '.$to,
						'template_id' => $template_id,
						'offset'      => $page * $batch_size,
						'limit'       => $batch_size,
					);

				}

				// build response
				$response = new stdClass();
				$response->tasklist    = $tasks;
				$response->total_tasks = count( $tasks );
				$response->error       = '';
				$response->success     = true;
				
				// create new job
				$newJob = new stdClass();
				$newJob->jobname = $jobname;
				$newJob->tasklist = $tasks;
				$job = new JobsModel( $newJob );
				$response->job_key = $job->key;

				$this->returnJSON( $response );
				exit();
			
			default:
				// echo "unknown job";
				// break;
		}
		// exit();

	}

	// create bulk listing job
	public function _create_bulk_listing_job( $taskname, $items, $jobname ) {
        // create tasklist
        $tasks = array();
        foreach( $items as $item ) {
			WPLE()->logger->info('adding task for item #'.$item['id'] . ' - '.$item['auction_title']);
			
			$tasks = $this->_prepare_eps_tasks( $item, $taskname, $tasks );

			$task = array( 
				'task'        => $taskname, 
				'displayName' => $this->get_display_name( $item ),
				'id'          => $item['id'],
				'site_id'     => $item['site_id'],
				'account_id'  => $item['account_id']
			);
			$tasks[] = $task;
        }

		// build response
		$response = new stdClass();
		$response->tasklist = $tasks;
		$response->total_tasks = count( $tasks );
		$response->error    = '';
		$response->success  = true;
		
		// create new job
		$newJob = new stdClass();
		$newJob->jobname = $jobname;
		$newJob->tasklist = $tasks;
		$job = new JobsModel( $newJob );
		$response->job_key = $job->key;

		return $response;
	}


	// load task list
	public function _prepare_eps_tasks( $item, $taskname, $tasks ) {

		// process only verify, publish and revise actions
		if ( ! in_array( $taskname, array('verifyItem','publishItem','reviseItem') ) ) return $tasks;

		## BEGIN PRO ##
		$post_id    = $item['post_id'];
		$listing_id = $item['id'];
		
		if ( ListingsModel::isUsingEPS( $listing_id ) ) {

			// load EPS cache for listing
			$listing = ListingsModel::getItem( $listing_id );
			if ( ! $uploaded_images = maybe_unserialize( $listing['eps'] ) ) $uploaded_images = array();

			// load product images
			$ibm = new ItemBuilderModel();
			$images = $ibm->getProductImagesURL( $post_id );

			// load variation images - if enabled
			if ( ListingsModel::isUsingVariationImages( $listing_id ) ) {
				$variation_images = $ibm->getVariationImages( $post_id );
				$images = array_merge( $images, $variation_images );
			}

			$i = 0;
			foreach ($images as $img) {

				// skip if $img exists in EPS cache
				$is_already_uploaded = false;
				foreach ( $uploaded_images as $uploaded_image ) {
					if ( $uploaded_image->local_url == $img ) {
						WPLE()->logger->info( "found cached EPS image for $img" );
						$is_already_uploaded = true;
						continue;
					}
				}

				if ( ! $is_already_uploaded ) {

					$task = array( 
						'task'        => 'uploadToEPS', 
						'displayName' => utf8_encode( $item['auction_title'] .' - '. __( 'image', 'wp-lister-for-ebay' ) . ' #' . ($i+1) ) ,
						'img'         => $img, 
						'id'          => $item['id'],
						'site_id'     => $item['site_id'],
						'account_id'  => $item['account_id']
					);
					$tasks[] = $task;
					$i++;

				}

			} // foreach $images

		}
		## END PRO ##

		return $tasks;
	}

	// Get the display name. If it is a product, link to the edit product page
    public function get_display_name( $item ) {
        $listing  = ListingsModel::getItem( $item['id'] );
        $display  = $item['auction_title'];

        if ( $listing ) {
            $product_id = !empty( $listing['parent_id'] ) ? $listing['parent_id'] : $listing['post_id'];
            $edit_url = 'post.php?post='. $product_id .'&action=edit';
            $display    = '<a href="'. $edit_url .'" target="_blank">'. $display .'</a>';
        }

        return $display;
    }


	// complete job
	public function jobs_complete_job() {

		// check nonce and permissions
	    check_admin_referer( 'wple_ajax_nonce' );
		if ( ! current_user_can('manage_ebay_listings') ) return;

		// quit if no job name provided
		if ( ! isset( $_REQUEST['job'] ) ) return false;

		// mark job as completed
		$job = new JobsModel( wple_clean($_REQUEST['job']) );
		$job->completeJob();

		if ( 'updateEbayData' == $job->item['job_name'] ) {
			// if we were updating ebay details as part of setup, move to next step
			if ( '2' == self::getOption('setup_next_step') ) self::updateOption('setup_next_step', 3);
		}

		// build response
		$response = new stdClass();
		$response->msg    = $job->item['job_name'].' completed';
		$response->error    = '';
		$response->success  = true;
		$response->job_key = $job->key;

		$this->returnJSON( $response );
		exit();
	}

	public function addAdminMessagesToResult( $data ) {
		if ( ! is_object($data) ) return $data;
		if ( ! isset($data->errors) ) return $data;
		if ( ! is_array($data->errors) ) $data->errors = array();

		// merge admin notices with result errors
		$admin_errors = WPLE()->messages->get_admin_notices_for_json_result();
		$data->errors = array_merge( $data->errors, $admin_errors );

		return $data;
	}

	public function returnJSON( $data ) {
		global $wpl_shutdown_handler_enabled;
		$wpl_shutdown_handler_enabled = false;

		// add WPLE admin messages to result errors
		$data = $this->addAdminMessagesToResult( $data );

		// drop any output in the buffer that could be causing errors
		// parsing JSON data #9857
		ob_get_clean();

		header('content-type: application/json; charset=utf-8');

		echo json_encode( $data );
	}
	
	// get categories tree node - used on ProfilesPage
	public function ajax_get_ebay_categories_tree() {

		// check nonce and permissions
	    check_admin_referer( 'wple_ajax_nonce' );
		if ( ! current_user_can('prepare_ebay_listings') ) return;

		// Combine fixes for #38217 and #38883 so site_id will only be used if there's an actual value. Otherwise, fallback to using the default site ID
		$site_id = ( !empty($_REQUEST['site_id']) || $_REQUEST['site_id'] == 0 ) ? wple_clean($_REQUEST['site_id']) : get_option('wplister_ebay_site_id');
	
		$path          = wple_clean($_POST["dir"]);
		$parent_cat_id = basename( $path );
		$categories = EbayCategoriesModel::getChildrenOf( $parent_cat_id, $site_id );		
		$categories = apply_filters_deprecated( 'wplister_get_ebay_categories_node', array( $categories, $parent_cat_id, $path ), '2.8.4', 'wple_get_ebay_categories_node' );
		$categories = apply_filters( 'wple_get_ebay_categories_node', $categories, $parent_cat_id, $path );

		if( count($categories) > 0 ) { 
			echo "<ul class=\"jqueryFileTree\" style=\"display: none;\">";
			// All dirs
			foreach( $categories as $cat ) {
				if ( $cat['leaf'] == '0' ) {
					echo '<li class="directory collapsed"><a href="#" rel="' 
						. ($path . $cat['cat_id']) . '/">'. ($cat['cat_name']) . '</a></li>';
				}
			}
			// All files
			foreach( $categories as $cat ) {
				if ( $cat['leaf'] == '1' ) {
					$ext = 'txt';
					echo '<li class="file ext_txt"><a href="#" rel="' 
						. ($path . $cat['cat_id']) . '">' . ($cat['cat_name']) . '</a></li>';
				}
			}
			echo "</ul>";	
		}
		exit();
	}

	// get categories tree node - used on ProfilesPage
	public function ajax_get_store_categories_tree() {

		// check nonce and permissions
	    check_admin_referer( 'wple_ajax_nonce' );
		if ( ! current_user_can('prepare_ebay_listings') ) return;
	
		$account_id = isset($_REQUEST['account_id']) ? wple_clean($_REQUEST['account_id']) : get_option('wplister_default_account_id');

		$path          = wple_clean($_POST["dir"]);
		$parent_cat_id = basename( $path );
		$categories = EbayCategoriesModel::getChildrenOfStoreCategory( $parent_cat_id, $account_id );		
		$categories = apply_filters_deprecated( 'wplister_get_store_categories_node', array($categories, $parent_cat_id, $path), '2.8.4', 'wple_get_store_categories_node' );
		$categories = apply_filters( 'wple_get_store_categories_node', $categories, $parent_cat_id, $path );

		if( count($categories) > 0 ) { 

			echo "<ul class=\"jqueryFileTree\" style=\"display: none;\">";

			// All dirs and files
			foreach ( $categories as $cat ) {

				if ( $cat['leaf'] == '0' ) {
					echo '<li class="directory collapsed"><a href="#" rel="' 
						. ($path . $cat['cat_id']) . '/">'. ($cat['cat_name']) . '</a></li>';
				}

				if ( $cat['leaf'] == '1' ) {
					echo '<li class="file ext_txt"><a href="#" rel="' 
						. ($path . $cat['cat_id']) . '">' . ($cat['cat_name']) . '</a></li>';
				}

			}

			echo "</ul>";	
		}
		exit();
	}

	public function ajax_wple_get_responsible_persons() {
		global $wpdb;

		$persons = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}ebay_responsible_persons ORDER BY company ASC");
		die(json_encode($persons));
	}

	public function ajax_wple_add_responsible_person() {
		$person     = new \WPLab\Ebay\Models\EbayResponsiblePerson();
		$data   = [
			'company'   => sanitize_text_field( $_POST['company'] ?? '' ),
			'email'     => sanitize_email( $_POST['email'] ?? '' ),
			'phone'     => sanitize_text_field( $_POST['phone'] ?? '' ),
			'street1'   => sanitize_text_field( $_POST['street1'] ?? '' ),
			'street2'   => sanitize_text_field( $_POST['street2'] ?? '' ),
			'city'      => sanitize_text_field( $_POST['city'] ?? '' ),
			'state'     => sanitize_text_field( $_POST['state'] ?? '' ),
			'postcode'  => sanitize_text_field( $_POST['postcode'] ?? '' ),
			'country'   => sanitize_text_field( $_POST['country'] ?? '' )
		];

		$person
			->setEmail( $data['email'] )
			->setCompany( $data['company'] )
			->setPhone( $data['phone'] )
			->setStreet1( $data['street1'] )
			->setStreet2( $data['street2'] )
			->setCity( $data['city'] )
			->setState( $data['state'] )
			->setCountry( $data['country'] )
			->setPostcode( $data['postcode'] );
		$id = $person->save();

		if ( is_wp_error( $id ) ) {
			http_response_code(400);
			$response = ['success' => false, 'error' => $id->get_error_message()];
		} else {
			$response = ['success' => true, 'id' => $id, 'data' => $data ];
		}

		die(json_encode($response));
	}

	public function ajax_wple_delete_responsible_person() {
		if ( ! current_user_can('prepare_ebay_listings') ) {
			die( json_encode( [ 'success' => false ] ) );
		}

		$id         = intval( $_POST['id'] );
		$person     = new \WPLab\Ebay\Models\EbayResponsiblePerson( $id );

		if ( $person->delete() ) {
			$response = ['success' => true ];
		} else {
			$response = ['success' => false];
		}

		die(json_encode($response));
	}

	public function ajax_wple_get_manufacturers() {
		global $wpdb;

		$rows = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}ebay_manufacturers ORDER BY company ASC");
		die(json_encode($rows));
	}

	public function ajax_wple_add_manufacturer() {
		$manufacturer = new \WPLab\Ebay\Models\EbayManufacturer();

		$data   = [
			'company'   => sanitize_text_field( $_POST['company'] ?? '' ),
			'email'     => sanitize_text_field( $_POST['email'] ?? '' ),
			'phone'     => sanitize_text_field( $_POST['phone'] ?? '' ),
			'street1'   => sanitize_text_field( $_POST['street1'] ?? '' ),
			'street2'   => sanitize_text_field( $_POST['street2'] ?? '' ),
			'city'      => sanitize_text_field( $_POST['city'] ?? '' ),
			'state'     => sanitize_text_field( $_POST['state'] ?? '' ),
			'postcode'  => sanitize_text_field( $_POST['postcode'] ?? '' ),
			'country'   => sanitize_text_field( $_POST['country'] ?? '' )
		];
		$manufacturer
			->setEmail( $data['email'] )
			->setCompany( $data['company'] )
			->setPhone( $data['phone'] )
			->setStreet1( $data['street1'] )
			->setStreet2( $data['street2'] )
			->setCity( $data['city'] )
			->setState( $data['state'] )
			->setCountry( $data['country'] )
			->setPostcode( $data['postcode'] );
		$id = $manufacturer->save();

		if ( is_wp_error( $id ) ) {
			http_response_code(400);
			$response = ['success' => false, 'error' => $id->get_error_message()];
		} else {
			$response = ['success' => true, 'id' => $id, 'data' => $data ];
		}

		die(json_encode($response));
	}

	public function ajax_wple_delete_manufacturer() {
		if ( ! current_user_can('prepare_ebay_listings') ) {
			die( json_encode( [ 'success' => false ] ) );
		}

		$id         = intval( $_POST['id'] );
		$manufacturer     = new \WPLab\Ebay\Models\EbayManufacturer( $id );

		if ( $manufacturer->delete() ) {
			$response = ['success' => true ];
		} else {
			$response = ['success' => false];
		}

		die(json_encode($response));
	}

	public function ajax_wple_add_document() {
		if ( ! current_user_can('prepare_ebay_listings') ) {
			die( json_encode( [ 'success' => false ] ) );
		}

		$file = intval( $_POST['file'] );
		$type = $_POST['type'];
		$account_id = intval($_POST['account']);

		$path = get_attached_file($file);

		if ( ! $path ) {
			die( json_encode( [ 'success' => false ] ) );
		}

		$api = new EbayMediaApi( $account_id );
		$resp = $api->createDocument( $type );

		if ( $resp ) {
			$document_id = $resp->getDocumentId();

			// upload
			$upload = $api->uploadDocument( $document_id, $path );

			if ( $upload ) {
				$document = new \WPLab\Ebay\Models\EbayDocument();
				$document
					->setDocumentId( $document_id )
					->setDocumentType( $type )
					->setAccountId( $account_id )
					->setAttachmentId( $file )
					->save();


				$response = ['success' => true ];
			} else {
				$response = ['success' => false];
			}
		} else {
			$response = ['success' => false];
		}

		die(json_encode($response));
	}

	// show matching products
	public function ajax_wple_show_product_matches() {

		// check nonce and permissions
	    check_admin_referer( 'wple_match_product_ajax_nonce' );
		if ( ! current_user_can('prepare_ebay_listings') ) return;

		// check parameters
		if ( ! isset( $_REQUEST['id']  ) ) return;

		$product = ProductWrapper::getProduct( intval($_REQUEST['id']) );

		if ( $product ) {

			$product_attributes	= ProductWrapper::getAttributes( wple_get_product_meta( $product, 'id' ), true );
			$post = get_post( intval($_REQUEST['id']) );

		    $wpl_default_matcher_selection = get_option( 'wple_default_matcher_selection', 'title' );
		    switch ($wpl_default_matcher_selection) {
		    	case 'title':
		    		# product title
					$query = $post->post_title;
		    		break;
		    	
		    	case 'sku':
		    		# product sku
					$query = wple_get_product_meta( $product, 'sku' );
		    		break;
		    	
		    	default:
		    		# else check for attributes
		    		foreach ($product_attributes as $attribute_label => $attribute_value) {
		    			if ( $attribute_label == $wpl_default_matcher_selection )
		    				$query = $attribute_value;
		    		}
		    		break;
		    }

		    // fall back to title when query is empty
		    if ( empty($query) ) $query = $post->post_title;

		    // handle custom query
			if ( isset( $_REQUEST['query'] ) ) $query = wple_clean( $_REQUEST['query'] );

			// get product attributes - if possible from cache
			$transient_key = 'wple_product_match_results_'.sanitize_key( $query );
			$products = get_transient( $transient_key );
			if ( empty( $products ) ){

				// call API
				$this->initEC();
				$products = $this->EC->callFindProducts( $query );
				$this->EC->closeEbay();

				if ( is_array( $products ) ) {
	
					// save cache
					set_transient( $transient_key, $products, 300 );
				}
			}

			if ( is_array( $products ) )  {

				// load template
				$tpldata = array(
					'plugin_url'				=> self::$PLUGIN_URL,
					'message'					=> $this->message,
					'query'						=> $query,				
					'query_product'				=> $product,				
					'query_product_attributes'	=> $product_attributes,
					'products'					=> $products,				
					'post_id'					=> intval($_REQUEST['id']),				
					'query_select'				=> isset($_REQUEST['query_select']) ? wple_clean($_REQUEST['query_select']) : false,
					'form_action'				=> 'admin.php?page='.self::ParentMenuId
				);

				WPLE()->pages['listings']->display( 'match_product', $tpldata );

			// } elseif ( $product->Error->Message ) {
			// 	$errors  = sprintf( __( 'There was a problem fetching product details for %s.', 'wp-lister-for-ebay' ), $product->post->post_title ) .'<br>Error: '. $reports->Error->Message;
			} else {
				$errors  = sprintf( __( 'There were no products found for query %s.', 'wp-lister-for-ebay' ), $query );
				echo $errors;
				echo "<pre>Debug information: ";print_r($products);echo"</pre>";
			}
			exit();

		} else {
			echo "invalid product";
		}

	} // ajax_wple_show_product_matches()

    /*** ## BEGIN PRO ## ***/
    // handle create_order action
    public function ajax_wple_create_order() {
	    check_admin_referer( 'wplister_create_order' );

        $om = new EbayOrdersModel();
        $ebay_order_id = wple_clean($_REQUEST['ebay_order']);
        $ebay_order    = $om->getItem( $ebay_order_id );

        $wob = new WPL_WooOrderBuilder();
        $wp_order_id = $wob->createWooOrderFromEbayOrder( $ebay_order_id );


        if ( $wp_order_id ) {
            $msg  = __( 'WooCommerce order was created from eBay order.', 'wp-lister-for-ebay' );
            $msg .= '<br><a href="post.php?action=edit&post=' . $wp_order_id .'" class="button button-small" target="_blank">'. __( 'View order', 'wp-lister-for-ebay' ).'</a>';
            $msg .= '<!br> <span style="color:silver">Order ID: ' . $wp_order_id .'</span>';
            WPLE()->messages->add_message( $msg, 'info', array( 'persistent' => true ) );
            //$this->showMessage( $msg );

            $history_message = "Order #$wp_order_id was created manually";
            $history_details = array( 'order_id' => $wp_order_id );
            $om->addHistory( $ebay_order['order_id'], 'create_order', $history_message, $history_details );

        } else {
            WPLE()->messages->add_message( __( 'There was a problem creating an order in WooCommerce from this eBay order.', 'wp-lister-for-ebay' ), 'error', array( 'persistent' => true ) );
            //$this->showMessage( __( 'There was a problem creating an order in WooCommerce from this eBay order.', 'wp-lister-for-ebay' ), 1 );

            $history_message = "Failed to create WooCommerce order for order ID ".$ebay_order['order_id'];
            $history_details = array( 'error' => $wp_order_id );
            $om->addHistory( $ebay_order['order_id'], 'create_order', $history_message, $history_details, false );

        }

        wp_redirect( admin_url( 'admin.php?page='. rawurldecode( $_GET['return'] ) ) );
        exit;
    }
    /*** ## END PRO ## ***/

    public function ajax_dismiss_notice() {
        check_admin_referer( 'wple_ajax_nonce' );

        $listing_id = wple_clean($_REQUEST['id']);
        if ( ! $listing_id ) return;

        if ( is_string( $listing_id ) ) {
            update_option( "wple_dismissed_$listing_id", true );
            wp_die();
        }
    }

    public function ajax_hide_gnutls_error() {
        check_admin_referer( 'wple_ajax_nonce' );

        add_option( 'wple_skip_gnu_tls_compatibility_error', true );
        wp_redirect( admin_url( 'admin.php?page=wplister-settings' ) );
        exit;
    }



    // show dynamic listing gallery
	public function ajax_wpl_gallery() {
	
		$default_limit = get_option( 'wplister_gallery_items_limit', 12 );
		$type          = isset( $_REQUEST['type'] )   ? sanitize_key($_REQUEST['type']  ) : 'new';	
		$limit         = isset( $_REQUEST['limit'] )  ?       intval($_REQUEST['limit'] ) : $default_limit;	
		$id            = isset( $_REQUEST['id'] )     ?       intval($_REQUEST['id']    ) : false;	
		$format        = isset( $_REQUEST['format'] ) ? sanitize_key($_REQUEST['format']) : 'html';	

		$items = WPLE_ListingQueryHelper::getItemsForGallery( $id, $type, $limit );

		if ( $format == 'json' ) {

			$json_data = array();
			foreach ($items as $item) {
				$json_item = new stdClass();
				$json_item->ebay_id        = $item['ebay_id'];
				$json_item->post_id        = $item['post_id'];
				$json_item->listing_id     = $item['id'];
				$json_item->title          = $item['auction_title'];
				$json_item->type           = $item['auction_type'];
				$json_item->price          = $item['price'];
				$json_item->quantity       = $item['quantity'];
				$json_item->quantity_sold  = $item['quantity_sold'];
				$json_item->main_image_url = $item['GalleryURL'];
				$json_item->ebay_url       = $item['ViewItemURL'];
				$json_item->site_id        = $item['site_id'];
				$json_item->status         = $item['status'];
				$json_data[] = $json_item;
			}

			// check if callback parameter is set (JSONP support)
			if ( isset($_REQUEST['callback']) ) {				
				header('content-type: application/javascript; charset=utf-8');
			    echo esc_attr( wple_clean($_REQUEST['callback']) ) . '(' . json_encode( $json_data ) . ')'; // JSONP
			} else {
				header('content-type: application/json; charset=utf-8');
				echo json_encode( $json_data ); // plain JSON
			}

			exit();
		}

		// get from_item and template path
		$view = WPLE_PLUGIN_PATH.'/views/template/gallery.php';
		$from_item = $id ? ListingsModel::getItem( $id ) : false;
		if ( $from_item ) {
			// if gallery.php exists in listing template, use it
			$upload_dir = wp_upload_dir();
			$gallery_tpl_file = $upload_dir['basedir'] . '/wp-lister/templates/' . basename( $from_item['template'] ) . '/gallery.php';
			if ( file_exists( $gallery_tpl_file ) ) $view = $gallery_tpl_file;
		}

		// load gallery template
		if ( file_exists($view) ) {
			if ( function_exists('header_remove') ) {
				header_remove('X-Frame-Options'); 	// available since PHP5.3
			} else {
				header('X-Frame-Options: GOFORIT'); // http://stackoverflow.com/questions/6666423/overcoming-display-forbidden-by-x-frame-options
			}
			include( $view );
		} else {
			echo "file not found: ".$view;
		}
		exit();
	} // ajax_wpl_gallery()


	// show dynamic listing gallery
	public function ajax_wpl_ebay_store_categories() {
	
		$default_account_id = get_option( 'wplister_default_account_id' );
		$account_id         = isset( $_REQUEST['account_id'] ) ? intval($_REQUEST['account_id']) : $default_account_id;	

		$store_categories = EbayCategoriesModel::getEntireStoreCategoryTree( 0, $account_id );


		// check if callback parameter is set
		if ( isset($_REQUEST['callback']) ) {
			// return JSONP 
			header('content-type: application/javascript; charset=utf-8');
		    echo esc_attr( wple_clean($_REQUEST['callback']) ) . '(' . json_encode( $store_categories ) . ')';
		} else {
			// return plain JSON
			header('content-type: application/json; charset=utf-8');
			echo json_encode( $store_categories );
		}

		exit();
	} // ajax_wpl_ebay_store_categories()


	// process ebay item query (AJAX)
	// (this is/was used by JS in dynamic listing content to fetch the ebay ItemID for a specific listing_id)
	public function ajax_wpl_ebay_item_query() {
	
		$col         = isset( $_REQUEST['col'] ) ? sanitize_key($_REQUEST['col'] ) : 'ebay_id';	
		$id          = isset( $_REQUEST['id']  ) ?       intval($_REQUEST['id']  ) : false;	
		if ( $col != 'ebay_id' ) return; // limited to single use case for now
		if ( $id == '' ) return;

		$items  = WPLE_ListingQueryHelper::getWhere( 'id', $id );
		$result = $items ? reset($items)->ebay_id : false;

		// check if callback parameter is set
		if ( isset($_REQUEST['callback']) ) {
			// return JSONP 
			header('content-type: application/javascript; charset=utf-8');
		    echo esc_attr( wple_clean($_REQUEST['callback']) ) . '(' . json_encode( $result ) . ')';
		} else {
			// return plain JSON
			header('content-type: application/json; charset=utf-8');
			echo json_encode( $result );
		}

		exit();
	} // ajax_wpl_ebay_item_query()


	// handle calls to logfile viewer based on php-tail
	// http://code.google.com/p/php-tail
	public function ajax_wple_tail_log() {

		// check nonce and permissions
	    check_admin_referer( 'wple_tail_log' );
		if ( ! current_user_can('manage_ebay_listings') ) return;

		if ( WPLE_IS_LITE_VERSION ) {
			echo '<pre>';
			echo file_get_contents( WPLE()->logger->file );
			die();
		}

		## BEGIN PRO ##
		require_once( WPLE_PLUGIN_PATH . '/includes/php-tail/PHPTail.php' );
		
		// Initilize a new instance of PHPTail - 3 sec reload, 512k max
		$tail = new PHPTail( WPLE()->logger->file, 3000, 524288 );

		// handle ajax call
		if(isset($_GET['ajax']))  {
			echo $tail->getNewLines( wple_clean(@$_GET['lastsize']), wple_clean($_GET['grep']), wple_clean($_GET['invert']) );
			die();
		}

		// else show gui
		$tail->generateGUI();
		die();		
		## END PRO ##
	}

	public function ajax_wple_load_log_file() {
        // check nonce and permissions
        check_admin_referer( 'wple_load_log_file' );
        if ( ! current_user_can('manage_ebay_listings') ) return;

        $file = sanitize_file_name( $_GET['file'] );

        $uploads = wp_upload_dir();
        $path = $uploads['basedir'] . '/wp-lister/logs/'. $file;

        if ( !file_exists( $path ) ) die( 'Invalid file' );

        die( nl2br( file_get_contents( $path ) ) );
    }

    public function ajax_wple_download_log_file() {
        // check nonce and permissions
        check_admin_referer( 'wple_download_log_file' );

        if ( ! current_user_can('manage_ebay_listings') ) return;

        $file = sanitize_file_name( $_GET['file'] );
        $domain = parse_url( site_url(), PHP_URL_HOST );

        $uploads = wp_upload_dir();
        $path = $uploads['basedir'] . '/wp-lister/logs/'. $file;
        $size = filesize( $path );

        if ( !file_exists( $path ) ) die( 'Invalid file' );

        // download
        header("Pragma: public"); // required
        header("Expires: 0");
        header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
        header("Content-Description: File Transfer");
        header("Content-Type: text/plain");
        header('Content-Disposition: attachment; filename="wple-'.$domain .'-'. $file.'"');
        header("Content-Transfer-Encoding: binary");
        header("Content-Length: ". $size);
        readfile($path);
        exit;
    }

	## BEGIN PRO ##
	// there are still problems with eBay's notification system. 
	// this handler is for debugging purposes - it will send request details to the developer
	// for manual test call: www.example.com/wp-admin/admin-ajax.php?action=handle_ebay_notify
	public function ajax_handle_ebay_notify() {
		// require_once WPLE_PLUGIN_PATH . '/includes/EbatNs/' . 'EbatNs_NotificationClient.php';
		// require_once WPLE_PLUGIN_PATH . '/includes/EbatNs/' . 'EbatNs_ResponseError.php';

		// load eBay classes
		EbayController::loadEbayClasses();

		$handler = new EbatNs_NotificationClient();
		$body    = file_get_contents('php://input');
		$res     = $handler->getResponse($body);
	
		WPLE()->logger->info('handle_ebay_notify() - time: '.gmdate('Y-m-d H:i:s') );
		WPLE()->logger->info('REQUEST: '.print_r($_REQUEST,1));
		WPLE()->logger->info('SERVER:  '.print_r($_SERVER,1));
		
		$headers = getallheaders();
		WPLE()->logger->info('headers: '.print_r($headers,1));
		WPLE()->logger->info('body:    '.print_r($body,1));
		WPLE()->logger->info('response:'.print_r($res,1));

        // log to db
        $this->dblogger = new WPL_EbatNs_Logger();
        $this->dblogger->updateLog( array(
            'callname'    => 'handle_ebay_notify',
            'request_url' => wple_clean($_SERVER['REQUEST_URI']) . "\n" . 'EventName: ' . wple_clean($_REQUEST['EventName']),
            'request'     => wple_clean($body),
            'response'    => wple_clean(print_r( $res, 1 )),
            'success'     => 'Success'
        ));
		
		// // send email (for debugging only)
		// $msg = 'A notification was received at '.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI']."\n\n";
		// $msg .= 'Body:     '.print_r($body,1)."\n\n";
		// $msg .= 'Response: '.print_r($res,1)."\n";
		// $msg .= 'REQUEST:  '.print_r($_REQUEST,1)."\n";
		// $msg .= 'SERVER:   '.print_r($_SERVER,1)."\n";
		// $msg .= 'Headers:  '.print_r($headers,1)."\n";

		// // $to = get_option('admin_email', 'info@wplab.com');
		// $to      = 'info@wplab.com';
		// $subject = 'New WPLE platform notification (debug info)';
		// $success = wp_mail( $to, $subject, $msg );
		// echo 'ACK '.$success;

		echo 'ACK';
		exit();
	} // ajax_handle_ebay_notify()
	## END PRO ##
		
} // class WPL_AjaxHandler

// instantiate object
$oWPL_AjaxHandler = new WPL_AjaxHandler();
