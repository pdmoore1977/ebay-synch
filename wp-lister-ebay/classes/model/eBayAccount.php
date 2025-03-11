<?php
/**
 * WPLE_eBayAccount class
 *
 */

// class WPLE_eBayAccount extends WPLE_NewModel {
class WPLE_eBayAccount extends WPL_Core {

	const TABLENAME = 'ebay_accounts';

	var $id;
	var $title;
	var $site_id;
	var $site_code;
	var $oauth_token;
	var $oauth_token_expiry;
	var $refresh_token;
	var $refresh_token_expiry;

	var $fieldnames;
	var $total_items;
	var $sandbox_mode;
	var $user_name;
	var $user_details;
	var $active;
	var $token;
	var $valid_until;
	var $ebay_motors;
	var $oosc_mode;
	var $seller_profiles;
	var $shipping_profiles;
	var $payment_profiles;
	var $return_profiles;
	var $shipping_discount_profiles;
	var $categories_map_ebay;
	var $categories_map_store;
	var $default_ebay_category_id;
	var $paypal_email;
	var $sync_orders;
	var $sync_products;
	var $last_orders_sync;

	function __construct( $id = null ) {
		
		$this->init();

		if ( $id ) {
			$this->id = $id;
			
			$account = $this->getAccount( $id );
			if ( ! $account ) return false; // this doesn't actually return an empty object - why?

			// load data into object		
			foreach( $account AS $key => $value ){
			    $this->$key = $value;
			}

			return $this;
		}

	}

	function init()	{

		$this->fieldnames = array(
			'title',
			'site_id',
			'site_code',
			'active',
			'sandbox_mode',
			'token',
			'oauth_token',
			'oauth_token_expiry',
			'refresh_token',
			'refresh_token_expiry',
			'user_name',
			'user_details',
			'valid_until',
			'ebay_motors',
			'oosc_mode',
			'seller_profiles',
			'shipping_profiles',
			'payment_profiles',
			'return_profiles',
			'shipping_discount_profiles',
			'categories_map_ebay',
			'categories_map_store',
			'default_ebay_category_id',
			'paypal_email',
			'sync_orders',
			'sync_products',
			'last_orders_sync',
		);

	}

	// get single account
	static function getAccount( $id, $mint_token = false )	{
		global $wpdb;

		// pull from cache
        $item = wp_cache_get( 'wple_account_get_account_'. intval($id), 'wple' );

        if ( $item ) {
            return $item;
        }

		$table = $wpdb->prefix . self::TABLENAME;
		
        $item = $wpdb->get_row( $wpdb->prepare("
			SELECT *
			FROM $table
			WHERE id = %d
		", $id
		), OBJECT);

        //if ( $item && $item->oauth_token && $mint_token ) {
            //$item = self::maybeMintToken( $item->id );
        //}

        if ( $item ) {
            wp_cache_set( 'wple_account_get_account_'. intval($id), $item, 'wple', 600 );
        }

		return $item;
	}

	// get all accounts
	static function getAll( $include_inactive = false, $sort_by_id = false ) {
		global $wpdb;
		$table = $wpdb->prefix . self::TABLENAME;

		// return if DB has not been initialized yet
		if ( get_option('wplister_db_version') < 37 ) return array();

		$where_sql = $include_inactive ? '' : 'WHERE active = 1';
		$order_sql = $sort_by_id       ? '' : 'ORDER BY title ASC';
		$items = $wpdb->get_results("
			SELECT *
			FROM $table
			$where_sql
			$order_sql
		", OBJECT_K);

		return $items;
	}

	// get account title
	static function getAccountTitle( $id )	{
		global $wpdb;
		$table = $wpdb->prefix . self::TABLENAME;
		
		$account_title = $wpdb->get_var( $wpdb->prepare("
			SELECT title
			FROM $table
			WHERE id = %d
		", $id ) );
		return $account_title;
	}

	static function getSiteCode( $id ) {
	    global $wpdb;
        $table = $wpdb->prefix . self::TABLENAME;

        $code = $wpdb->get_var( $wpdb->prepare("
			SELECT site_code
			FROM $table
			WHERE id = %d
		", $id ) );
        return $code;
    }

    static function getAccountLocale( $id ) {
	    $site_code = self::getSiteCode( $id );

	    switch ( strtolower( $site_code ) ) {
            case 'germany':
                $lang = 'de';
                break;

            case 'france':
                $lang = 'fr';
                break;

            case 'italy':
                $lang = 'it';
                break;

            case 'spain':
                $lang = 'es';
                break;

            case 'netherlands':
                $lang = 'nl';
                break;

            default:
                $lang = 'en';
                break;
        }

        return apply_filters( 'wple_account_locale', $lang, $id );
    }

	// get this account's site
	function getSite()	{
		// return WPLA_AmazonSite::getSite( $this->site_id );
	}

	// save account
	function add() {
		global $wpdb;
		$table = $wpdb->prefix . self::TABLENAME;

		$data = array();
		$data['user_details']               = ''; // fix rare "Field 'user_details' doesn't have a default value" error on some MySQL servers
		$data['shipping_profiles']          = '';
		$data['payment_profiles']           = '';
		$data['return_profiles']            = '';
		$data['shipping_discount_profiles'] = '';
		$data['categories_map_ebay']        = '';
		$data['categories_map_store']       = '';
		
		foreach ( $this->fieldnames as $key ) {
			if ( isset( $this->$key ) ) {
				$data[ $key ] = $this->$key;
			} 
		}

		if ( sizeof( $data ) > 0 ) {
			$result = $wpdb->insert( $table, $data );

			if ( ! $wpdb->insert_id ) {
			    WPLE()->logger->error( 'Error adding account. '. $wpdb->last_error );
				wple_show_message( 'There was a problem adding your account. MySQL said: '.$wpdb->last_error, 'error' );
			}

			$this->id = $wpdb->insert_id;

			// automatically set to the default account if there's only 1 account
            self::setDefaultAccount();

			return $wpdb->insert_id;		
		}

	}

	// update feed
	function update() {
		global $wpdb;
		$table = $wpdb->prefix . self::TABLENAME;
		if ( ! $this->id ) return;

		$data = array();
		foreach ( $this->fieldnames as $key ) {
			if ( isset( $this->$key ) ) {
				$data[ $key ] = $this->$key;
			} 
		}

		if ( sizeof( $data ) > 0 ) {
			$result = $wpdb->update( $table, $data, array( 'id' => $this->id ) );
			echo $wpdb->last_error;
			// echo "<pre>";print_r($wpdb->last_query);echo"</pre>";#die();
			// return $wpdb->insert_id;		
		}

		wp_cache_delete( 'wple_account_get_account_'. $this->id, 'wple' );

	}

    function updateUserDetails() {
        // update token expiration date
        $this->initEC( $this->id );

        $this->getUserToken( $this->EC );
	    $this->getUserDetails( $this->EC );
	    $this->getUserPreferences( $this->EC );
    }

	function getUserToken( $EC = null ) {
		if ( ! $this->id ) return;

        if ( !empty( $this->oauth_token ) ) return;

		// update token expiration date
        if ( is_null( $EC ) ) {
            $this->initEC( $this->id );
            $EC = $this->EC;
        }

        $EC->initLogger();
		$expdate = $EC->GetTokenStatus( true );
		$EC->closeEbay();
		if ( $expdate ) {
			$this->valid_until = $expdate;
			$this->update();
			update_option( 'wplister_ebay_token_is_invalid', false );
		}

	} // updateUserDetails()

    function getUserDetails( $EC = null ) {
        if ( ! $this->id ) return;

        // update user details
        if ( is_null( $EC ) ) {
            $this->initEC( $this->id );
            $EC = $this->EC;
        }
        $EC->initLogger();
        $user_details = $EC->GetUser( true );
        $EC->closeEbay();
        if ( $user_details ) {
            $account = new WPLE_eBayAccount( $this->id );
            $account->user_name 	= $user_details->UserID;
            $account->user_details = maybe_serialize( $user_details );
            if ( $account->title == 'My Account' ) {
                $account->title    = $user_details->UserID; // use UserID as default title for new accounts
            }
            $account->update();
        }
    }

    function getUserPreferences( $EC = null ) {
        if ( ! $this->id ) return;

        // update seller profiles
        if ( is_null( $EC ) ) {
            $this->initEC( $this->id );
            $EC = $this->EC;
        }
        $EC->initLogger();
        $result = $EC->GetUserPreferences( true );
        $EC->closeEbay();
        if ( $result ) {
            $account = new WPLE_eBayAccount( $this->id );
            $account->oosc_mode         = $result->OutOfStockControl    ? 1 : 0;
            $account->seller_profiles   = $result->SellerProfileOptedIn ? 1 : 0;
            $account->shipping_profiles = maybe_serialize( $result->seller_shipping_profiles );
            $account->payment_profiles  = maybe_serialize( $result->seller_payment_profiles );
            $account->return_profiles   = maybe_serialize( $result->seller_return_profiles );
            $account->update();
        }
    }

	// delete account
	function delete() {
		global $wpdb;
		$table = $wpdb->prefix . self::TABLENAME;
		if ( ! $this->id ) return;

		$result = $wpdb->delete( $table, array( 'id' => $this->id ) );

        // automatically set to the default account if there's only 1 account
        self::setDefaultAccount();

		echo $wpdb->last_error;
	}

	function getPageItems( $current_page, $per_page ) {
		global $wpdb;
		$table = $wpdb->prefix . self::TABLENAME;

        $orderby  = (!empty($_REQUEST['orderby'])) ? esc_sql( $_REQUEST['orderby'] ) : 'title';
        $order    = (!empty($_REQUEST['order']))   ? esc_sql( $_REQUEST['order']   ) : 'asc';
        $offset   = ( $current_page - 1 ) * $per_page;
        $per_page = esc_sql( $per_page );

        // get items
		$items = $wpdb->get_results("
			SELECT *
			FROM $table
            ORDER BY active desc, $orderby $order
            LIMIT $offset, $per_page
		", ARRAY_A);

		// get total items count - if needed
		if ( ( $current_page == 1 ) && ( count( $items ) < $per_page ) ) {
			$this->total_items = count( $items );
		} else {
			$this->total_items = $wpdb->get_var("
				SELECT COUNT(*)
				FROM $table
				ORDER BY $orderby $order
			");			
		}

		foreach( $items as &$account ) {
			// $account['ReportTypeName'] = $this->getRecordTypeName( $account['ReportType'] );
		}

		return $items;
	} // getPageItems()

    // Automatically set a default account if there's only 1 account available
    static function setDefaultAccount() {
	    global $wpdb;

	    $accounts = $wpdb->get_col( "SELECT id FROM {$wpdb->prefix}ebay_accounts" );

	    if ( count( $accounts ) == 1 ) {
	        $id = array_pop( $accounts );
            update_option( 'wplister_default_account_id', $id );
        }
    }

    /**
     * Check if the current OAuth token needs to be minted.
     *
     * If token is expired or $force is TRUE, the new token is returned. Otherwise, FALSE is returned
     *
     * @param int $account_id
     * @param bool $force
     * @return bool|string
     * @throws Exception
     */
    static function maybeMintToken( $account_id, $force = false ) {
	    WPLE()->logger->info('maybeMintToken #'. $account_id);
	    $failures = get_transient( 'wple_minting_failures_'. $account_id );
	    if ( !$failures ) $failures = 0;

        $account = self::getAccount( $account_id );

	    //if ( $failures == 5 ) {
	        //wple_show_message( sprintf( __('Unable to get new access tokens for the eBay account %s. Please go to WP-Lister Settings > Accounts > Edit account to get a new token.'), $account->title ), 'error', ['persistent' => true] );
        //    WPLE()->logger->error(sprintf( __('Unable to get new access tokens for the eBay account %s. Please go to WP-Lister Settings > Accounts > Edit account to get a new token.'), $account->title ));
	    //    return $account;
        //}


	    if ( $account->oauth_token ) {
	        $token_expiry = $account->oauth_token_expiry;
	        $current_date = gmdate( 'Y-m-d H:i:s' );

	        WPLE()->logger->info('Now: '. $current_date .' / expiry: '. $token_expiry );
	        if ( $force || gmdate('U' )+120 >= strtotime( $account->oauth_token_expiry ) ) {
	            // access token is expired - generate a new one
	            WPLE()->logger->info('Token is expired or about to expire. Minting a new one.');
	            $token_data = EbayController::getOAuthAccessToken( $account->refresh_token, $account->sandbox_mode, true );

                if ( $token_data ) {
                    $access_dt = new DateTime();
                    $access_dt->add( new DateInterval( 'PT'. $token_data->expires_in .'S' ) );

                    $ebay_account = new WPLE_eBayAccount($account_id );
                    $ebay_account->oauth_token = $token_data->access_token;
                    $ebay_account->oauth_token_expiry = $access_dt->format( 'Y-m-d H:i:s' );
                    $ebay_account->update();

                    $account->oauth_token = $token_data->access_token;
                    $account->oauth_token_expiry = $ebay_account->oauth_token_expiry;

                    set_transient( 'wple_minting_failures_'. $account_id, 0 );

                    return $token_data->access_token;
                } else {
                    $failures++;
                    set_transient( 'wple_minting_failures_'. $account_id, $failures );
                }
            }
        }

	    return false;
    }


} // WPLE_eBayAccount()
