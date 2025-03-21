<?php

class EbayController {
    
    var $logger;

    var $apiurl;
    var $signin;
    var $devId;
    var $appId;
    var $certId;
    var $RuName;
    var $siteId;
    var $sandbox;

	var $error_reporting_level;

    var $compLevel;

    /* @var EbatNs_Session */
    public $session;            // ebay session
    /* @var EbatNs_ServiceProxy */
    public $sp;                 // ebay service proxy    
    public $message     = false;    
    public $error       = false;
    public $lastResults = array();
    public $isSuccess   = null;
    public $hasErrors   = null;
    public $hasWarnings = null;

    public function __construct() {

        // set up autoloader for eBay classes
        self::loadEbayClasses();

    }

    public function config() {
    }

    static function loadEbayClasses() {

        // make sure this only runs once
        $autoload_functions = spl_autoload_functions();
        if ( is_array( $autoload_functions ) ) {
            foreach ( $autoload_functions as $func ) {
                // return if WPL_Autoloader already loaded
                if ( is_array($func) && $func[1] == 'autoloadEbayClasses' )
                    return;
            }
        }

        // we want to be patient when talking to ebay
        if( ! ini_get('safe_mode') && function_exists( 'set_time_limit' ) ) {
			set_time_limit(600);
        }

        ini_set( 'mysql.connect_timeout', 600 );
        ini_set( 'default_socket_timeout', 600 );

        // add EbatNs folder to include path - required for SDK
        $incPath = WPLE_PLUGIN_PATH . '/includes/EbatNs';
        set_include_path( get_include_path() . ':' . $incPath );

        // TODO: check if set_include_path() was successfull!

        // use autoloader to load EbatNs classes
        spl_autoload_register('WPL_Autoloader::autoloadEbayClasses');

    } // loadEbayClasses()


    function GetEbaySignInUrl($RuName = null, $Params = null)
    {
        $s = $this->session;
        if ($s->getAppMode() == 0) 
            $url = 'https://signin.' . self::getDomainnameBySiteId( $s->getSiteId() ) . '/ws/eBayISAPI.dll?SignIn';
        else 
            $url = 'https://signin.sandbox.' . self::getDomainnameBySiteId( $s->getSiteId() ) . '/ws/eBayISAPI.dll?SignIn';
        if ($RuName != null)
            $url .= '&runame=' . $RuName;
        if ($Params != null)
            $url .= '&ruparams=' . $Params;
        return $url;
    }
    
    
    // get SessionID and build AuthURL
    public function getAuthUrl(){ 

        // fetch SessionID - valid for about 5 minutes
        $SessionID = $this->GetSessionID( $this->RuName );

        // save SessionID to DB
        update_option('wplister_ebay_sessionid', $SessionID);
        WPLE()->logger->info( 'new SessionID: ' . $SessionID );


        // build auth url
        $query = array( 'RuName' => $this->RuName, 'SessID' => $SessionID );
        $url = $this->GetEbaySignInUrl() . '&' . http_build_query( $query, '', '&' );
        WPLE()->logger->info( 'AuthUrl: ' . $url );

        return $url;
    }

    public static function getOAuthStateString() {
        $url = trailingslashit( get_bloginfo('url'));
        WPLE()->logger->info( 'site url: '. $url);
        WPLE()->logger->info( 'state: '. md5($url));

        return md5( $url );
    }

    public static function getOAuthUrl( $sandbox = false ) {
        $state_code = self::getOAuthStateString();

        $auth_url = 'https://auth.wplister.com/ebay-oauth/?action=auth&state='. $state_code;

        if ( $sandbox ) {
            $auth_url = add_query_arg( 'test', 1, $auth_url );
        }

        return $auth_url;
    }

    public static function getOAuthRefreshToken() {
        WPLE()->logger->info( 'getOAuthRefreshToken' );

        $response = wp_remote_get(
            'https://auth.wplister.com/ebay-oauth/?action=get_refresh_token&key='. self::getOAuthStateString(),
            [
                'sslverify' => false,
                'timeout'   => 30
            ]
        );

        if ( is_wp_error( $response ) ) {
            WPLE()->logger->error( 'Error: '. $response->get_error_message() );
            wple_show_message( 'Error fetching token. Auth Server said '. $response->get_error_message() .'. Please Connect with eBay again.' );
            return false;
        }

        $refresh_token = urldecode(wp_remote_retrieve_body( $response ));

        WPLE()->logger->info('token: '. $refresh_token);

        return $refresh_token;
    }

    public static function getOAuthAccessToken( $refresh_token, $sandbox = false, $mint_token = false ) {
        WPLE()->logger->info('getOAuthAccessToken');
        $sandbox = ($sandbox) ? 1 : 0;
        $action = $mint_token ? 'mint_access_token' : 'get_access_token';
        $response = wp_remote_get(
            'https://auth.wplister.com/ebay-oauth/?action='.$action.'&key='. urlencode( $refresh_token ) .'&test='. $sandbox,
            array(
                'sslverify' => false,
                'timeout'   => 15
            )
        );
        $body = json_decode( wp_remote_retrieve_body( $response ) );

        // log request to db
        if ( get_option('wplister_log_to_db') == '1' ) {
            $dblogger = new WPL_EbatNs_Logger();
            $dblogger->updateLog( array(
                'callname'    => 'getOAuthAccessToken',
                'request_url' => '/ebay-oauth/',
                'request'     => 'action='.$action.'&key='. urlencode( $refresh_token ) .'&test='. $sandbox,
                'response'    => print_r($body,1),
                'success'     => wp_remote_retrieve_response_code( $response ) == 200 ? 'Success' : 'Failure'
            ));
        }

        if ( is_wp_error( $response ) ) {
            WPLE()->logger->error( 'getOauthAccessToken error: '. $response->get_error_message() );
            wple_show_message( 'Error fetching token. Auth Server said '. $response->get_error_message() .'. Please Connect with eBay again.', 'error' );
            return false;
        } elseif ( wp_remote_retrieve_response_code( $response ) != 200 ) {
            WPLE()->logger->error( 'Error HTTP('.wp_remote_retrieve_response_code( $response ).'): '. print_r($body,1) );

            if ( is_object($body) && isset( $body->errorMessage ) ) {
                $error = $body->errorMessage->error[0]->longMessage;
                $message = 'Error fetching token. eBay said "'. $error .'". Please try Connect with eBay again.';
            } else {
                $message = 'Error fetching token. Auth Server said '. $body->error_description .'. Please Connect with eBay again.';
            }

            wple_show_message( $message, 'error' );
            return false;
        }

        WPLE()->logger->debug( 'getOAuthAccessToken body: '. print_r($body,1) );
        return $body;
    }
 
    // do FetchToken and save to DB
    public function doFetchToken( $account_id = false ){ 
        
        // $account_id = $account_id ? $account_id : get_option('wplister_default_account_id'); // we can *not* fall back to the default account here, or adding a new account would overwrite the default account's token
        $SessionID  = get_option('wplister_ebay_sessionid');        
        $token      = $this->FetchToken( $SessionID );

        if ($token) {

            if ( $account_id ) {
                $account = new WPLE_eBayAccount( $account_id );
                $account->token = $token;
                $account->update();
            }

            // check if setup wizard is still active
            if ( get_option( 'wplister_setup_next_step' ) == 1 ) {

                // update legacy data
                update_option('wplister_ebay_token', $token);

                // move setup to step 2
                update_option('wplister_setup_next_step', '2');                

                // remember when WP-Lister was connected to an eBay account for the first time
                update_option( 'wplister_ignore_orders_before_ts', time() );
            }

            // // obsolete - already called in fetchTokenForAccount()
            // update_option('wplister_ebay_token_is_invalid', false );

        }
        
        return $token;
    }
 
    // do getTokenExpirationTime and save to DB (deprecated)
    public function getTokenExpirationTime( $site_id, $sandbox_enabled ){ 

        $token = get_option('wplister_ebay_token');
        $expdate = $this->fetchTokenExpirationTime( $token );

        // update legacy option (1.x)
        update_option('wplister_ebay_token_expirationtime', $expdate);
        
        return $expdate;
    }
 
    // establish connection to eBay API
    public function initEbay( $site_id, $sandbox_enabled, $token = false, $oauth_token = false, $account_id = false ){

        // init autoloader fro EbatNs classes
        $this->loadEbayClasses();

        WPLE()->logger->info("initEbay( $account_id )");
        // require_once 'EbatNs_ServiceProxy.php';
        // require_once 'EbatNs_Logger.php';

        // hide inevitable cURL warnings from SDK 
        // *** DISABLE FOR DEBUGGING ***
        $this->error_reporting_level = error_reporting();
        WPLE()->logger->debug( 'original error reporting level: '.$this->error_reporting_level );

        // // regard php_error_handling option
        // // first bit (1) will show all php errors if set
        // if ( 1 & get_option( 'wplister_php_error_handling', 0 ) ) {
        //     error_reporting( E_ALL | E_STRICT );            
        // } else {
        //     // only show fatal errors (default)
        //     error_reporting( E_ERROR );            
        // }
        error_reporting( E_ERROR );            
        WPLE()->logger->debug( 'new error reporting level: '.error_reporting() );

        $this->siteId = $site_id;
        $this->sandbox = $sandbox_enabled;
        #$this->compLevel = 765;

        if ( $sandbox_enabled ) {
            
            // sandbox keys
            $this->devId  = 'db0c17b6-c357-4a38-aa60-7e80158f57dc';
            $this->appId  = 'LWSWerbu-c159-4552-8411-1406ca5a2bba';
            $this->certId = '33272b6e-ef02-4d22-a487-a1a3f02b9c66';
            $this->RuName = 'LWS_Werbung_Gmb-LWSWerbu-c159-4-tchfyrowj';

            //$this->apiurl = 'https://api.sandbox.ebay.com/ws/api.dll';
            $this->apiurl = 'https://api.sandbox.ebay.com/wsapi';
            $this->signin = 'https://signin.sandbox.ebay.com/ws/eBayISAPI.dll?SignIn&';

        } else {

            // production keys
            $instance_key = str_replace( array('http://','https://','www.'), '', get_site_url() ); // example.com
            $admin_email  = wple_get_license_email();
            $this->appId  = urlencode( $instance_key );
            $this->devId  = $admin_email   ? $admin_email : get_option( 'admin_email' );
            $this->certId = WPLE_IS_LITE_VERSION ? 'LITE'       : wple_get_license_key();
            $this->RuName = 'LWS_Werbung_Gmb-LWSWerbu-6147-4-ywstl';

            //$this->apiurl = 'https://api.ebay.com/ws/api.dll';
            $this->apiurl = 'https://api.sandbox.ebay.com/wsapi';
            $this->signin = 'https://signin.ebay.com/ws/eBayISAPI.dll?SignIn&';
        }

        // filter RuName
        if ( defined('WPLISTER_RESELLER_VERSION') ) {
            $this->RuName = apply_filters_deprecated( 'wplister_runame', array($this->RuName, $sandbox_enabled), '2.8.4', 'wple_runame' );
            $this->RuName = apply_filters( 'wple_runame', $this->RuName, $sandbox_enabled );
        }

        // init session
        $session = new EbatNs_Session();

        // depends on the site working on (needs ID-Value !)
        $session->setSiteId($site_id);
        $session->wple_account_id = $account_id;

        // regard WP proxy server
        if ( defined('WP_USEPROXY') && WP_USEPROXY ) {
            if ( defined('WP_PROXY_HOST') && defined('WP_PROXY_PORT') )
                $session->setProxyServer( WP_PROXY_HOST . ':' . WP_PROXY_PORT );
        }

        // environment (0=production, 1=sandbox)
        if ( $sandbox_enabled == '1' ) {
            WPLE()->logger->info('initEbay(): SANDBOX ENABLED');
            $session->setAppMode(1);    // this must be set *before* setting the keys (appId, devId, ...)
        } else {
            $session->setAppMode(0);    
        }

        $session->setAppId($this->appId);
        $session->setDevId($this->devId);
        $session->setCertId($this->certId);

        if ( $oauth_token ) {
            $session->setAuthType(EBAY_AUTHTYPE_OAUTH);

            // use a token as credential
            $session->setTokenMode(2);

            // do NOT use a token file !
            $session->setTokenUsePickupFile(false);

            // token of the user
            $session->setRequestToken($oauth_token);
        } elseif ( $token ) {
            
            // use a token as credential
            $session->setTokenMode(true);

            // do NOT use a token file !
            $session->setTokenUsePickupFile(false);

            // token of the user
            $session->setRequestToken($token);

        } else {
            $session->setTokenMode(false);
        }

        // prevent Undefined index: UseStandardLogger
        $session->setUseStandardLogger(false);

        // creating a proxy for UTF8
        $sp = new EbatNs_ServiceProxy($session, 'EbatNs_DataConverterUtf8');

        // // logger doc: http://www.intradesys.com/de/forum/1528
        // if ( get_option('wplister_log_level') > 5 ) {
        //     #$sp->attachLogger( new EbatNs_Logger(false, 'stdout', true, false) );
        //     $sp->attachLogger( new EbatNs_Logger(false, WPLE()->logger->file ) );
        // }

        // attach custom DB Logger for Tools page
        if ( isset($_REQUEST['page']) && $_REQUEST['page'] == 'wplister-tools' ) {
            $sp->attachLogger( new WPL_EbatNs_Logger( false, 'db', $account_id, $site_id ) );
        }
        
        // save service proxy - and session
        $this->sp = $sp;
        $this->session = $session;

    }

    // re-attach logger - required to log multiple requests in the same session
    public function initLogger(){ 
        $this->sp->attachLogger( new WPL_EbatNs_Logger( false, 'db', $this->session->wple_account_id, $this->siteId ) );
    }

    // close connection to eBay API
    public function closeEbay(){ 
        // restore error reporting level 
        error_reporting( $this->error_reporting_level );
        // WPLE()->logger->info( 'switched back error reporting level to: '.error_reporting() );
    }
 

    // get SessionID for Auth&Auth
    public function GetSessionID( $RuName ){ 

        // prepare request
        $req = new GetSessionIDRequestType();
        $req->setRuName($RuName);
        
        // send request
        $res = $this->sp->GetSessionID($req);

        // handle errors like blocked ips
        if ( $res->Ack != 'Success' ) {
            echo "<h1>Problem connecting to eBay</h1>";
            echo "<p>WP-Lister can't seem to establish a connection to eBay's servers. This could be caused by a firewall blocking cURL from accessing unkown ip addresses.</p>";
            echo "<p>Only your hosting company can sort out the problems causing cURL not to connect properly. Your hosting company's server administrator should be able to resolve the permission problems preventing cURL from working. They've probably got overly limiting restrictions configured on the server, preventing it from being able to do the communication required for listing items on eBay.</p>";
            echo "<p>debug output:</p>";
            echo "<pre>"; print_r($res); echo "</pre>";
            echo "<pre>"; print_r($req); echo "</pre>";
            die();
        }

        // TODO: handle error        
        return ( $res->SessionID );
        
    }
    public function FetchToken( $SessionID ){ 

        // prepare request
        $req = new FetchTokenRequestType();
        $req->setSessionID($SessionID);
        
        // send request
        $res = $this->sp->FetchToken($req);

        // TODO: handle error
        if ( ! $res->eBayAuthToken ) {
            echo "<pre>Error in FetchToken(): ";print_r($res);echo"</pre>";
            return false;
        }

        return ( urldecode( $res->eBayAuthToken ) );
    }

    public function fetchTokenExpirationTime( $SessionID ){ 

        // prepare request
        $req = new GetTokenStatusRequestType();
        $req->setSessionID($SessionID);
        
        // send request
        $res = $this->sp->GetTokenStatus($req);

        // TODO: handle error        
        return ( $res->ExpirationTime );
        
    }

    // ajax: initialize categories update
    // returns: tasklist
    public function initCategoriesUpdate( $site_id ){ 
        $cm = new EbayCategoriesModel();
        return $cm->initCategoriesUpdate( $this->session, $site_id );
    }
    // ajax: load single branch of ebay categories
    // returns: result
    public function loadEbayCategoriesBranch( $cat_id, $site_id ){ 
        $cm = new EbayCategoriesModel();
        return $cm->loadEbayCategoriesBranch( $cat_id, $this->session, $site_id );
    }

    // load Store categories list and insert to db
    public function loadStoreCategories( $account_id ) { 
        $cm = new EbayCategoriesModel();
        $cm->downloadStoreCategories( $this->session, $account_id );
    }

	public function loadHazardousMaterialsLabels( $account_id ) {
		$account = WPLE()->accounts[$account_id];
		$marketplace = new EbayMarketplaceApi( $account_id );
		$labels = $marketplace->getHazardousMaterialsLabels();

		$data = [
			'signal_words'  => [],
			'statements'    => [],
			'pictograms'    => []
		];
		if ( $labels ) {
			foreach ( $labels->getSignalWords() as $signal_word ) {
				$data['signal_words'][] = [
					'signal_word_id'            => $signal_word->getSignalWordId(),
					'signal_word_description'   => $signal_word->getSignalWordDescription()
				];
			}

			foreach ( $labels->getStatements() as $statement ) {
				$data['statements'][] = [
					'statement_id'          => $statement->getStatementId(),
					'statement_description' => $statement->getStatementDescription()
				];
			}

			foreach ( $labels->getPictograms() as $pictogram ) {
				$data['pictograms'][] = [
					'pictogram_id'          => $pictogram->getPictogramId(),
					'pictogram_description' => $pictogram->getPictogramDescription(),
					'pictogram_url'         => $pictogram->getPictogramUrl()
				];
			}
		}

		$Site = new WPLE_eBaySite( $account->site_id );
		$Site->HazardousMaterialsLabels = serialize( $data );
		$Site->update();
	}

	public function loadProductSafetyLabels( $account_id ) {
		$account = WPLE()->accounts[$account_id];
		$marketplace = new EbayMarketplaceApi( $account_id );
		$labels = $marketplace->getProductSafetyLabels();

		$data = [
			'statements'    => [],
			'pictograms'    => []
		];
		if ( $labels ) {
			foreach ( $labels->getStatements() as $statement ) {
				$data['statements'][] = [
					'statement_id'          => $statement->getStatementId(),
					'statement_description' => $statement->getStatementDescription()
				];
			}

			foreach ( $labels->getPictograms() as $pictogram ) {
				$data['pictograms'][] = [
					'pictogram_id'          => $pictogram->getPictogramId(),
					'pictogram_description' => $pictogram->getPictogramDescription(),
					'pictogram_url'         => $pictogram->getPictogramUrl()
				];
			}
		}

		$Site = new WPLE_eBaySite( $account->site_id );
		$Site->ProductSafetyLabels = serialize( $data );
		$Site->update();
	}

    // load shipping services and insert to db
    public function loadShippingServices( $site_id ){ 
        $sm = new EbayShippingModel();
        $sm->downloadCountryDetails( $this->session, $site_id );
        $sm->downloadShippingLocations( $this->session, $site_id );
        $sm->downloadShippingDetails( $this->session, $site_id );
        $sm->downloadDispatchTimes( $this->session, $site_id );      
        $sm->downloadShippingPackages( $this->session, $site_id );      
        $sm->downloadExcludeShippingLocations( $this->session, $site_id );
        $sm->fetchDoesNotApplyText( $this->session, $site_id );
        $sm->downloadShippingDiscountProfiles( $this->session );
    }

    public function getCountryDetails( $site_id ) {
        $sm = new EbayShippingModel();
        $sm->downloadCountryDetails( $this->session, $site_id );
    }

    public function getShippingLocations( $site_id ) {
        $sm = new EbayShippingModel();
        $sm->downloadShippingLocations( $this->session, $site_id );
    }

    public function getShippingDetails( $site_id ) {
        $sm = new EbayShippingModel();
        $sm->downloadShippingDetails( $this->session, $site_id );
    }

    public function getDispatchTimes( $site_id ) {
        $sm = new EbayShippingModel();
        $sm->downloadDispatchTimes( $this->session, $site_id );
    }

    public function getShippingPackages( $site_id ) {
        $sm = new EbayShippingModel();
        $sm->downloadShippingPackages( $this->session, $site_id );
    }

    public function getExcludeShippingLocations( $site_id ) {
        $sm = new EbayShippingModel();
        $sm->downloadExcludeShippingLocations( $this->session, $site_id );
    }

    public function getDoesNotApplyText( $site_id ) {
        $sm = new EbayShippingModel();
        $sm->fetchDoesNotApplyText( $this->session, $site_id );
    }

    // load shipping services and insert to db
    public function loadPaymentOptions( $site_id ){ 
        $sm = new EbayPaymentModel();
        $sm->downloadPaymentDetails( $this->session, $site_id );      
        $sm->downloadMinimumStartPrices( $this->session, $site_id );      
        $sm->downloadReturnPolicyDetails( $this->session, $site_id );      

        // set date of last update for site
        $Site = new WPLE_eBaySite( $site_id );
        $Site->last_refresh = gmdate('Y-m-d H:i:s');
        $Site->update();
    }

    public function getPaymentDetails( $site_id ) {
        $sm = new EbayPaymentModel();
        $sm->downloadPaymentDetails( $this->session, $site_id );
    }

    public function getMinimumStartPrices( $site_id ) {
        $sm = new EbayPaymentModel();
        $sm->downloadMinimumStartPrices( $this->session, $site_id );
    }

    public function getReturnPolicyDetails( $site_id ) {
        $sm = new EbayPaymentModel();
        $sm->downloadReturnPolicyDetails( $this->session, $site_id );

        // set date of last update for site
        $Site = new WPLE_eBaySite( $site_id );
        $Site->last_refresh = gmdate('Y-m-d H:i:s');
        $Site->update();
    }

    // load user / account specific details from eBay
    public function loadUserAccountDetails() { 

        // update user details
        $this->initLogger();
        $this->GetUser();
        $this->initLogger();
        $this->GetUserPreferences();

        // Store the discount profiles in the ebay_accounts table
        $sm = new EbayShippingModel();
        $discount_profiles = $sm->downloadShippingDiscountProfiles( $this->session );

        if ( $discount_profiles ) {
            $am = new WPLE_eBayAccount();
            $am->id = $this->session->wple_account_id;
            $am->shipping_discount_profiles = maybe_serialize( $discount_profiles );
            $am->update();
        }
    }

    // load available dispatch times
    public function loadDispatchTimes(){ 
        $sm = new EbayShippingModel();
        return $sm->downloadDispatchTimes( $this->session );      
    }
    
    // load available shipping packages
    public function loadShippingPackages(){ 
        $sm = new EbayShippingModel();
        return $sm->downloadShippingPackages( $this->session );      
    }

    // load available shipping discount profiles
    public function loadShippingDiscountProfiles(){ 
        $sm = new EbayShippingModel();
        return $sm->downloadShippingDiscountProfiles( $this->session );      
    }


    // update ebay orders (deprecated)
    public function loadEbayOrders( $days = null ){ 
        $m = new EbayOrdersModel();
        $m->updateOrders( $this->session, $days );
        return $m;
    }
    // update ebay orders (new)
    public function updateEbayOrders( $days = false, $order_ids = false ){ 
        $m = new EbayOrdersModel();
        $m->updateOrders( $this->session, $days, 1, $order_ids );
        return $m;
    }

    /**
     * Look for and update active orders that are passed that LastOrderDate value
     */
    public function updateActiveOrders(){
        $m = new EbayOrdersModel();
        $m->updateActiveOrders( $this->session );
        return $m;
    }

    // update ebay messages
    public function updateEbayMessages( $days = false, $message_ids = false ){ 

        if ( ! get_option( 'wplister_enable_messages_page' ) ) return;
        $m = new EbayMessagesModel();
        $m->updateMessages( $this->session, $days, 1, $message_ids );
        if ( $message_ids ) return $m;

        // automatically fetch message body for up to 10 messages
        $message_ids_to_update = EbayMessagesModel::getMessageIDsToFetch( $this->session->wple_account_id );
        if ( ! empty($message_ids_to_update) ) {
            $m->updateMessages( $this->session, $days, 1, $message_ids_to_update );
        }

        return $m;
    }

    // update listings
    // - update ended listings
    // - process auto relist schedule
    public function updateListings(){ 
        $lm = new ListingsModel();
        $lm->updateEndedListings( $this->session );

        $this->processAutoRelistSchedule();
    }

    // process listings scheduled for auto relist
    public function processAutoRelistSchedule(){ 
        ## BEGIN PRO ##
        
        $items = WPLE_ListingQueryHelper::getAllScheduled( true ); // get all pending listings

        // limit batch size... TODO: make this an option
        $batch_size = 10;
        $items = array_slice( $items, 0, $batch_size );

        // build array of item ids
        $item_ids = array();
        foreach ($items as $item) {
            $item_ids[] = $item['id'];
        }

        // auto relist batch
        $this->autoRelistItems( $item_ids );
        
        ## END PRO ##
    }

    // get category conditions
    public function getCategoryConditions( $category_id, $site_id = false ) { 
        if ( ! $site_id ) $site_id = $this->siteId;

        //$account = WPLE
        $cm = new EbayCategoriesModel();
        // always update conditions before specifics
        $conditions = $cm->fetchCategoryConditions( $this->session, $category_id, $site_id );
        //$specifics  = $cm->fetchCategorySpecifics(  $this->session, $category_id, $site_id );
        return $conditions;
    }

    // get category specifics
    public function getCategorySpecifics( $category_id, $site_id = false ) { 
        if ( ! $site_id ) $site_id = $this->siteId;
        $cm = new EbayCategoriesModel();
        // always update conditions before specifics
        $conditions = $cm->fetchCategoryConditions( $this->session, $category_id, $site_id );
        $specifics  = $cm->fetchCategorySpecifics(  $this->session, $category_id, $site_id );
        return $specifics;
    }



    // process $this->lastResults and look for errors and/or warnings
    public function processLastResults(){ 
        WPLE()->logger->debug('processLastResults()'.print_r( $this->lastResults, 1 ));

        // Filter out the empty values/arrays to prevent WPLister from displaying false error messages #16980
        $results = array_filter( $this->lastResults );

        $this->isSuccess = true;
        $this->hasErrors = false;
        $this->hasWarnings = false;

        foreach ($results as $result) {
            if ( ! $result->success ) $this->isSuccess = false;
        }

    }



    // call verifyAddItem on selected items
    public function verifyItems( $id ){ 
        WPLE()->logger->info('EC::verifyItems('.$id.')');
        
        $sm = new ListingsModel();

        if ( is_array( $id )) {
            foreach( $id as $single_id ) {
                $this->lastResults[] = $sm->verifyAddItem( $single_id, $this->session );   
            }
            $this->processLastResults();
        } else {
            $this->lastResults[] = $sm->verifyAddItem( $id, $this->session );          
            $this->processLastResults();
            return $this->lastResults;
        }
        
    }

    // call ReviseItem on selected items
    public function reviseItems( $id ){ 
        
        $sm = new ListingsModel();

        if ( is_array( $id )) {
            foreach( $id as $single_id ) {
                $this->lastResults[] = $sm->reviseItem( $single_id, $this->session );  
            }
            $this->processLastResults();
        } else {
            $this->lastResults[] = $sm->reviseItem( $id, $this->session );         
            $this->processLastResults();
        }
        return $this->lastResults;
    }

    // call ReviseInventoryStatus on selected cart items
    public function reviseInventoryForCartItems( $cart_items ){ 
        
        $sm = new ListingsModel();
        if ( ! is_array( $cart_items ) ) return;
        
        foreach( $cart_items as $item ) {
            $this->lastResults[] = $sm->reviseInventoryStatus( $item->listing_id, $this->session, $item );  
        }
        
        $this->processLastResults();
    }

    // call ReviseInventoryStatus for given listing ID
    // (called from 'wplister_revise_inventory_status' api hook)
    public function reviseInventoryForListing( $listing_id, $force = false ){

        $lm = new ListingsModel();
        $this->lastResults[] = $lm->reviseInventoryStatus( $listing_id, $this->session, false, $force );
       
        $this->processLastResults();
    } // reviseInventoryForListing()


    // // call ReviseInventoryStatus on selected products - DEPRECATED: use reviseInventoryForListing() instead
    // // Note: if $product_ids is an array containing multiple IDs, all items need to use the same account,
    // //       which is why this method is currently only called with a single $post_id as a parameter
    // // (previously called from 'wplister_revise_inventory_status' api hook - using only a single $post_id at a time)
    // public function reviseInventoryForProducts( $product_ids ){ 

    //     if ( ! is_array( $product_ids ) && ! is_numeric( $product_ids ) ) return; 
    //     if ( ! is_array( $product_ids ) ) $product_ids = array( $product_ids );
        
    //     $lm = new ListingsModel();
    //     foreach( $product_ids as $post_id ) {
    //         $listing_id = WPLE_ListingQueryHelper::getListingIDFromPostID( $post_id );

    //         // if no listing found, check parent_id for variations
    //         if ( ! $listing_id ) {
    //             $_product = ProductWrapper::getProduct( $post_id );
    //             if ( ! $_product ) continue;

    //             if ( wple_get_product_meta( $_product, 'product_type' ) == 'variation' ) {
    //                 $parent_id = is_callable( array( $_product, 'get_parent_id' ) ) ? $_product->get_parent_id() : $_product->parent->id;
    //                 $listing_id = WPLE_ListingQueryHelper::getListingIDFromPostID( $parent_id );
    //             }
    //         }

    //         // check if API is allowed to relist ended items (deprecated option)
    //         if ( get_option( 'wplister_api_enable_auto_relist' ) ) {

    //             // check listing status - only ended and sold items can be relisted
    //             $allowed_statuses = array( 'ended', 'sold' );
    //             if ( $lm->itemHasAllowedStatus( $listing_id, $allowed_statuses ) ) {

    //                 // ok, we have an ended item - check if it's in stock
    //                 $listing_item = ListingsModel::getItem( $listing_id );
    //                 if ( ListingsModel::checkStockLevel( $listing_item ) ) {

    //                     // let's relist
    //                     $this->lastResults[] = $lm->relistItem( $listing_id, $this->session );  
    //                     continue;

    //                 } // is in stock

    //             } // is ended

    //         } // if API relist enabled

    //         // revise inventory status (default)
    //         $this->lastResults[] = $lm->reviseInventoryStatus( $listing_id, $this->session, false );  

    //     } // each $post_id
        
    //     $this->processLastResults();
    // } // reviseInventoryForProducts()

    // call AddItem on selected items
    public function sendItemsToEbay( $id ){ 
        
        $sm = new ListingsModel();

        if ( is_array( $id )) {
            foreach( $id as $single_id ) {
                $this->lastResults[] = $sm->addItem( $single_id, $this->session ); 
            }
            $this->processLastResults();
        } else {
            $this->lastResults[] = $sm->addItem( $id, $this->session );            
            $this->processLastResults();
            return $this->lastResults;
        }
        
    }

    // call EddItem on selected items
    public function endItemsOnEbay( $id ){ 
        
        $sm = new ListingsModel();

        if ( is_array( $id )) {
            foreach( $id as $single_id ) {
                $this->lastResults[] = $sm->endItem( $single_id, $this->session ); 
            }
            $this->processLastResults();
        } else {
            $this->lastResults[] = $sm->endItem( $id, $this->session );            
            $this->processLastResults();
            return $this->lastResults;
        }
        
    }

    // call relistItem on selected items
    public function relistItems( $id ){ 
        WPLE()->logger->info('EC::relistItems('.$id.')');
        
        $sm = new ListingsModel();

        if ( is_array( $id )) {
            foreach( $id as $single_id ) {
                $this->lastResults[] = $sm->relistItem( $single_id, $this->session );   
            }
            $this->processLastResults();
        } else {
            $this->lastResults[] = $sm->relistItem( $id, $this->session );          
            $this->processLastResults();
            return $this->lastResults;
        }
        
    }


    // call autoRelistItem on selected items - quick relist without any changes
    public function autoRelistItems( $id ){ 
        //WPLE()->logger->info('EC::autoRelistItems('.$id.')');
        
        $sm = new ListingsModel();

        if ( is_array( $id )) {
            foreach( $id as $single_id ) {
                $this->lastResults[] = $sm->autoRelistItem( $single_id, $this->session );   
            }
            $this->processLastResults();
        } else {
            $this->lastResults[] = $sm->autoRelistItem( $id, $this->session );          
            $this->processLastResults();
            return $this->lastResults;
        }
        
    }


    // call GetItemDetails on selected items
    public function updateItemsFromEbay( $id ){ 
        
        $sm = new ListingsModel();

        if ( is_array( $id )) {
            foreach( $id as $single_id ) {
                $this->lastResults[] = $sm->updateItemDetails( $single_id, $this->session );   
            }
            $this->processLastResults();
        } else {
            $this->lastResults[] = $sm->updateItemDetails( $id, $this->session );          
            $this->processLastResults();
            return $this->lastResults;
        }
        
    }


    // delete selected items
    public function deleteProfiles( $id ){ 
        
        $sm = new ProfilesModel();

        if ( is_array( $id )) {
            foreach( $id as $single_id ) {
                $sm->deleteItem( $single_id );  
            }
        } else {
            $sm->deleteItem( $id );         
        }
        
    }

    // delete selected items
    public function deleteTransactions( $id ){ 
        
        $sm = new TransactionsModel();

        if ( is_array( $id )) {
            foreach( $id as $single_id ) {
                $sm->deleteItem( $single_id );  
            }
        } else {
            $sm->deleteItem( $id );         
        }
        
    }


    // call verifyAddItem on all prepared items
    public function verifyAllPreparedItems(){   

        $items = WPLE_ListingQueryHelper::getAllPrepared();
        
        foreach( $items as $item ) {
            $sm->verifyAddItem( $item['id'], $this->session );  
        }
        
    }

    // call AddItem on all verified items
    public function publishAllVerifiedItems(){  

        $items = WPLE_ListingQueryHelper::getAllVerified();
        
        foreach( $items as $item ) {
            $sm->addItem( $item['id'], $this->session );    
        }
        
    }

    // call reviseItem on all changed items
    public function reviseAllChangedItems(){   

        $items = WPLE_ListingQueryHelper::getAllChangedItemsToRevise( null, true );
        $sm = new ListingsModel();

        foreach( $items as $item ) {
            $sm->reviseItem( $item['id'], $this->session );  
        }
        
    }

    ## BEGIN PRO ##

    // call splitVariation on all selected items
    public function splitVariations( $id ){ 
        
        $sm = new ListingsModel();

        if ( is_array( $id )) {
            foreach( $id as $single_id ) {
                $sm->splitVariation( $single_id );  
            }
        } else {
            $sm->splitVariation( $id );         
        }        
    }

    // call CompleteSale for an eBay order - with optional tracking and feedback data
    // $id refers to wp_ebay_orders.id
    public function completeOrder( $id, $data ) {
        if ( ! $id ) return;

        $sm = new EbayOrdersModel();
        $response = $sm->completeEbayOrder( $this->session, $id, $data );

        return $response;
    }

    ## END PRO ##

    // call updateSingleTransaction on selected transactions
    public function updateTransactionsFromEbay( $id ){ 
        
        $sm = new TransactionsModel();

        if ( is_array( $id )) {
            foreach( $id as $single_id ) {
                $sm->updateSingleTransaction( $this->session, $single_id );   
            }
        } else {
            $sm->updateSingleTransaction( $this->session, $id );          
        }
        
    }


    // GetNotificationPreferences
    public function GetNotificationPreferences(){ 
        $req = new GetNotificationPreferencesRequestType();
        
        // 1st request for user prefs
        $req->setPreferenceLevel('User');
        $res = $this->sp->GetNotificationPreferences($req);

        // 2nd request for user data
        $this->initLogger();
        $req->setPreferenceLevel('UserData');
        $res2 = $this->sp->GetNotificationPreferences($req);

        // 3rd request for app data
        $this->initLogger();
        $req->setPreferenceLevel('Application');
        $res3 = $this->sp->GetNotificationPreferences($req);

        // 4th request for event data (always empty?)
        $this->initLogger();
        $req->setPreferenceLevel('Event');
        $res4 = $this->sp->GetNotificationPreferences($req);

        // handle result
        return ( print_r( $res, 1 ) . print_r( $res2, 1 ) . print_r( $res3, 1 ) . print_r( $res4, 1 ) );
    }

    // SetNotificationPreferences for User
    // inspired by http://jolierouge.net/2011/05/spree-commerce-ebay-trading-api-and-the-ebay-accelerator-toolkit-from-intradesys-ebatns/
    public function SetUserNotificationPreferences( $mode = 'Enable' ){ 
        // $app_url = admin_url().'admin-ajax.php?action=handle_ebay_notify';

        // build request
        $req = new SetNotificationPreferencesRequestType();

        // // set UserData
        // $UserData = new NotificationUserDataType();
        // $UserData->setExternalUserData( admin_url() );
        // $req->setUserData( $UserData );

        // set UserDeliveryPreferenceArray
        $UserDeliveryPreferenceArray = new NotificationEnableArrayType();
        $NotificationEnable          = array();
        $mode                        = $mode == 'Enable' ? $mode : 'Disable';

        // subscribe to events
        $events = array(
            'ItemSold',
            'ItemClosed',
            'ItemListed',
            'ItemRevised',
            'BidReceived',
            'EndOfAuction',
            'FeedbackReceived',
            'FixedPriceTransaction',
        );

        foreach ( $events as $event ) {
            $n = new NotificationEnableType();
            $n->setEventType( $event );
            $n->setEventEnable( $mode ); // Enable / Disable
            $NotificationEnable[] = $n;
        }

        $UserDeliveryPreferenceArray->setNotificationEnable( $NotificationEnable, null );
        $req->setUserDeliveryPreferenceArray( $UserDeliveryPreferenceArray );

        // send request
        $res = $this->sp->SetNotificationPreferences($req);


        // send second request with ExternalUserData
        // https://ebaydts.com/eBayKBDetails?KBid=2042

        // build request
        $req = new SetNotificationPreferencesRequestType();

        // set ApplicationDeliveryPreferences (without this, eBay ignores UserData container)
        $req->ApplicationDeliveryPreferences = new ApplicationDeliveryPreferencesType();
        $req->ApplicationDeliveryPreferences->setApplicationEnable('Enable');

        // set UserData
        $UserData = new NotificationUserDataType();
        $UserData->setExternalUserData( admin_url() );
        $req->setUserData( $UserData );

        // send request
        $this->initLogger();
        $res2 = $this->sp->SetNotificationPreferences($req);


        // handle result
        return ( print_r( $res, 1 ) . print_r( $res2, 1 ) );        
    } // SetUserNotificationPreferences()


    // reset NotificationPreferences for Application
    public function ResetNotificationPreferences(){ 

        // reset application prefs to default
        $req = new SetNotificationPreferencesRequestType();
        $req->ApplicationDeliveryPreferences = new ApplicationDeliveryPreferencesType();
        $req->ApplicationDeliveryPreferences->setAlertEmail('mailto://info@wplab.com');
        $req->ApplicationDeliveryPreferences->setAlertEnable('Enable');
        $req->ApplicationDeliveryPreferences->setApplicationEnable('Enable');
        $req->ApplicationDeliveryPreferences->setApplicationURL( 'http://ping.wplister.com/?key=wple_notify_handler' );
        $req->ApplicationDeliveryPreferences->setPayloadVersion( 1045 );

        // // disabled
        // $details = new DeliveryURLDetailType();
        // $details->setDeliveryURLName('wple_notify_handler');
        // $details->setDeliveryURL('mailto://info@wplab.com');
        // $details->setStatus('Disable');
        // $req->ApplicationDeliveryPreferences->setDeliveryURLDetails($details,null);

        // set UserData (if omitted here, ExternalUserData will be removed)
        $UserData = new NotificationUserDataType();
        $UserData->setExternalUserData( admin_url() );
        $req->setUserData( $UserData );

        // send request
        $res = $this->sp->SetNotificationPreferences($req);
        // echo "<pre>";print_r($req);echo"</pre>";

        // handle result
        return ( print_r( $res, 1 ) );
    } // ResetNotificationPreferences()


    // GetNotificationsUsage
    public function GetNotificationsUsage(){ 

        $EndTime   = gmdate('Y-m-d\TH:i:s').'.000Z';                        // now
        $StartTime = gmdate('Y-m-d\TH:i:s', strtotime('-2 days') ).'.000Z'; // 2 days ago (3 days max)
       
        // send request
        $req = new GetNotificationsUsageRequestType();
        $req->setEndTime( $EndTime );
        $req->setStartTime( $StartTime );
        // $req->setItemID( $ebay_id ); // get detailed events for single item
        $res = $this->sp->GetNotificationsUsage($req);

        // handle result
        return ( print_r( $res, 1 ) );
    }




    // GetUserPreferences
    public function GetUserPreferences( $return_result = false ){ 

        // prepare request
        $req = new GetUserPreferencesRequestType();
        $req->setShowSellerProfilePreferences( 'true' );
        $req->setShowOutOfStockControlPreference( 'true' );
        $req->setShoweBayPLUSPreference( 'true' );
        // $req->setShowSellerExcludeShipToLocationPreference( true );

        // send request
        $res = $this->sp->GetUserPreferences($req);
        // echo "<pre>";print_r($res);echo"</pre>";#die();

        // handle response error
        if ( !$res || 'EbatNs_ResponseError' == get_class( $res ) )
            return false;

        $result = new stdClass();
        $result->success                  = true;
        $result->seller_shipping_profiles = array();
        $result->seller_payment_profiles  = array();
        $result->seller_return_profiles   = array();

        $result->SellerProfileOptedIn     = $res->SellerProfilePreferences->SellerProfileOptedIn ?? false;
        $result->OutOfStockControl        = $res->OutOfStockControlPreference;

        // $profiles = $res->getSellerProfilePreferences()->getSupportedSellerProfiles()->getSupportedSellerProfile(); // can trigger Fatal Error: Call to a member function getSupportedSellerProfiles() on a non-object
        // echo "<pre>";print_r($profiles);echo"</pre>";#die();

        // if ( $result->SellerProfileOptedIn ) {
	    if ( is_array($res->SellerProfilePreferences->SupportedSellerProfiles->SupportedSellerProfile) ) {
		    foreach ( $res->SellerProfilePreferences->SupportedSellerProfiles->SupportedSellerProfile as $profile ) {
			    $seller_profile = new stdClass();
			    $seller_profile->ProfileID    = $profile->ProfileID;
			    $seller_profile->ProfileName  = $profile->ProfileName;
			    $seller_profile->ProfileType  = $profile->ProfileType;
			    $seller_profile->ShortSummary = $profile->ShortSummary;

			    switch ( $profile->ProfileType ) {
				    case 'SHIPPING':
					    $result->seller_shipping_profiles[] = $seller_profile;
					    break;

				    case 'PAYMENT':
					    $result->seller_payment_profiles[] = $seller_profile;
					    break;

				    case 'RETURN_POLICY':
					    $result->seller_return_profiles[] = $seller_profile;
					    break;
			    }
		    }

		    if ( $return_result ) return $result;

		    update_option('wplister_ebay_seller_shipping_profiles', $result->seller_shipping_profiles, false);
		    update_option('wplister_ebay_seller_payment_profiles', $result->seller_payment_profiles, false);
		    update_option('wplister_ebay_seller_return_profiles', $result->seller_return_profiles, false);
	    } else {
		    if ( $return_result ) return $result;
		    delete_option( 'wplister_ebay_seller_shipping_profiles' );
		    delete_option( 'wplister_ebay_seller_payment_profiles' );
		    delete_option( 'wplister_ebay_seller_return_profiles' );
	    }

        if ( $return_result ) return $result;
        update_option('wplister_ebay_seller_profiles_enabled', $result->SellerProfileOptedIn ? 'yes' : 'no' );
        delete_option( 'wplister_ebay_seller_profiles' );

    }



    // GetUser
    public function GetUser( $return_result = false ){ 

        // prepare request
        $req = new GetUserRequestType();
        
        // send request
        $res = $this->sp->GetUser($req);

        if ( $res instanceof EbatNs_ResponseError ) {
            $errors = '';
            foreach ( $res->Errors as $error ) {
                $errors .= '<p>'. $error->LongMessage .'</p>';
            }

            if ( $errors ) wple_show_message( $errors, 'error' );

            return false;
        } else {
            $user = new stdClass();
            $user->UserID              = $res->User->UserID;
            $user->Email               = $res->User->Email;
            $user->FeedbackScore       = $res->User->FeedbackScore;
            $user->FeedbackRatingStar  = $res->User->FeedbackRatingStar;
            $user->NewUser             = $res->User->NewUser;
            $user->IDVerified          = $res->User->IDVerified;
            $user->eBayGoodStanding    = $res->User->eBayGoodStanding;
            $user->Status              = $res->User->Status;
            $user->Site                = $res->User->Site;
            $user->VATStatus           = $res->User->VATStatus;
            $user->PayPalAccountLevel  = $res->User->PayPalAccountLevel;
            $user->PayPalAccountType   = $res->User->PayPalAccountType;
            $user->PayPalAccountStatus = $res->User->PayPalAccountStatus;

            $user->StoreOwner          = $res->User->SellerInfo->StoreOwner;
            $user->StoreURL            = $res->User->SellerInfo->StoreURL;
            $user->SellerBusinessType  = $res->User->SellerInfo->SellerBusinessType;
            $user->ExpressEligible     = $res->User->SellerInfo->ExpressEligible;
            $user->StoreSite           = $res->User->SellerInfo->StoreSite;

            if ( $return_result ) return $user;

            $UserID = $res->User->UserID;
            update_option('wplister_ebay_token_userid', $UserID);
            update_option('wplister_ebay_user', $user);

            return ( $UserID );
        }

    }

    // GetTokenStatus
    public function GetTokenStatus( $return_result = false ){ 

        // prepare request
        $req = new GetTokenStatusRequestType();
        
        // send request
        $res = $this->sp->GetTokenStatus($req);

        $expdate = $res->TokenStatus->ExpirationTime;

        if ( $expdate ) {

            $expdate = str_replace('T', ' ', $expdate);
            $expdate = str_replace('.000Z', '', $expdate);

            if ( $return_result ) return $expdate;

            update_option( 'wplister_ebay_token_expirationtime', $expdate );
            update_option( 'wplister_ebay_token_is_invalid', false );

        }

        // handle result
        return ( $expdate );
        
    }

    // GetApiAccessRules
    public function GetApiAccessRules(){ 
        $req = new GetApiAccessRulesRequestType();
        $res = $this->sp->GetApiAccessRules($req);
        return ( $res );       
    }

    // test connection to ebay api by single GetTokenStatus request
    // (used by import plugin until version 1.3.8)
    public function testConnection(){
	    $this->initEC();
	    $res = $this->EC->GetTokenStatus();
	    $this->EC->closeEbay();
        
		/*$req = new GeteBayOfficialTimeRequestType();
        $res = $this->sp->GeteBayOfficialTime($req);*/
        return $res;
    }
     
    // get current time on ebay
    public function getEbayTime(){ 
	    // prepare request
        $req = new GeteBayOfficialTimeRequestType();
        
        // send request
        $res = $this->sp->GeteBayOfficialTime($req);

        // process timestamp
        if ( $res->Ack == 'Success' ) {
            $ts = $res->Timestamp;              // 2013-06-06T07:45:19.898Z
            $ts = str_replace('T', ' ', $ts);   // 2013-06-06 07:45:19.898Z
            $ts = substr( $ts, 0, 19 );         // 2013-06-06 07:45:19
            return $ts;
        }

        // return result on error
        return ( $res );
        
    }

	public function getNTPTime() {
	    // Define NTP server and port
	    $server = 'time.google.com';
	    $port = 123;

	    // Create a 48-byte NTP packet
	    $packet = "\010" . str_repeat("\0", 47);

	    // Open a UDP connection to the NTP server
	    $socket = @fsockopen("udp://$server", $port, $err_no, $err_str, 1);

	    if ($socket) {
	        // Send the packet to the NTP server
	        fwrite($socket, $packet);

	        // Read the response (48 bytes)
	        $response = fread($socket, 48);
	        fclose($socket);

	        // Process the NTP response to extract the timestamp
	        if (strlen($response) == 48) {
	            $unpack = unpack('N12', $response);
	            $timestamp = sprintf('%u', $unpack[9]);

	            // Convert NTP timestamp to Unix epoch
	            $ntp_time = $timestamp - 2208988800;
	            return gmdate("Y-m-d H:i:s", $ntp_time);
	        } else {
				return false;
			}
		} else {
			return false;
		}
	}


// call Shopping API to fetch matching products
    public function callFindProducts( $query ) { 
        // $query = "test";

        // $api_url = 'http://open.api.ebay.com/shopping?callname=FindProducts&responseencoding=XML&appid=MYAPPID&siteid=0&version=525&QueryKeywords=harry%20potter&AvailableItemsOnly=true&MaxEntries=2'
        $api_url = $this->sandbox ? 'https://open.api.sandbox.ebay.com/shopping' : 'https://open.api.ebay.com/shopping';
        $api_id  = $this->appId;
        $params = array(
            'callname'           => 'FindProducts',
            'responseencoding'   => 'JSON',
            'appid'              => $api_id,
            'siteid'             => $this->siteId,
            // 'version'            => '885',
            'version'            => '789',
            'QueryKeywords'      => urlencode( $query ),
            'AvailableItemsOnly' => 'true',
            'MaxEntries'         => '2',
        );
        $request_url = add_query_arg( $params, $api_url );

        $default_account_id = get_option( 'wplister_default_account_id' );

        if (! isset( WPLE()->accounts[ $default_account_id ] ) ) {
            return [];
        }

        $account = WPLE()->accounts[$default_account_id];
        
        // call API
        $response = wp_remote_get( $request_url, ['headers' => ['X-EBAY-API-IAF-TOKEN' => $account->oauth_token] ] );

        // skip further processing if an error was returned
        if ( is_wp_error( $response ) ) return $response;

        // decode result
        $result = json_decode( wp_remote_retrieve_body( $response ) );

        // check if result was decoded
        if ( ! $result ) return 'Unable to parse FindProducts result for query '.$query;

        // check if no products found for query
        if ( $result->Ack == 'Failure' && is_array( $result->Errors ) ) {
            if ( $result->Errors[0]->ErrorCode == '10.20' ) {
                return array();                
            } else {
                return $result->Errors[0]->LongMessage;
            }
        }

        // return products array
        $products = $result->Product;

        // parse products and make EPID available
        foreach ($products as $product) {

            // parse all ProductID nodes
            foreach ( $product->ProductID as $pid ) {
                if ( $pid->Type == 'Reference' ) {
                    $product->EPID = $pid->Value;
                }
            }

        }

        return $products;
    } // callFindProducts()

    // get site code by site_id
    static public function getEbaySiteCode( $site_id ) {
        $sites = self::getEbaySites();
        if ( ! array_key_exists( $site_id, $sites) ) return false;
        return $sites[ $site_id ];        
    } // getEbaySiteCode()

    // TODO: fetch ebaySites from eBay
    static public function getEbaySites() {

        $sites = array (        
            '0'   => 'US', 
            '2'   => 'Canada', 
            '3'   => 'UK', 
            '77'  => 'Germany', 
            '15'  => 'Australia', 
            '71'  => 'France', 
            '100' => 'eBayMotors', 
            '101' => 'Italy', 
            '146' => 'Netherlands', 
            '186' => 'Spain', 
            '203' => 'India', 
            '201' => 'HongKong', 
            '216' => 'Singapore', 
            '207' => 'Malaysia', 
            '211' => 'Philippines', 
            '210' => 'CanadaFrench', 
            '212' => 'Poland', 
            '123' => 'Belgium_Dutch', 
            '23'  => 'Belgium_French', 
            '16'  => 'Austria', 
            '193' => 'Switzerland', 
            '205' => 'Ireland'
        );
        return $sites;
    }

    // get domain name by site_id
    static function getDomainnameBySiteId($siteid = 0)
    {
        switch ($siteid) {
            case 0:
                return 'ebay.com';
            case 2:
                return 'ebay.ca';
            case 3:
                return 'ebay.co.uk';
            case 15:
                return 'ebay.com.au';
            case 16:
                return 'ebay.at';
            case 23:
                return 'befr.ebay.be';
            case 71:
                return 'ebay.fr';
            case 77:
                return 'ebay.de';
            case 100:
                // return 'ebaymotors.com'; # doesnt work anymore
                return 'ebay.com';
            case 101:
                return 'ebay.it';
            case 123:
                return 'benl.ebay.be';
            case 146:
                return 'ebay.nl';
            case 186:
                return 'ebay.es';
            case 193:
                return 'ebay.ch';
            case 196:
                return 'ebay.tw';
            case 201:
                return 'ebay.com.hk';
            case 203:
                return 'ebay.in';
            case 205:
                return 'ebay.ie';
            case 207:
                return 'ebay.com.my';
            case 211:
                return 'ebay.ph';
            case 212:
                return 'ebay.pl';
            case 216:
                return 'ebay.com.sg';
            case 218:
                return 'ebay.se';
            case 223:
                return 'ebay.cn';
        }
        return 'ebay.com';

    } // getDomainnameBySiteId()

	/**
	 * @param $url
	 * @param $session
	 *
	 * @return WP_Error
	 */
	public function uploadToEPS( $url, $session ) {
		$req = new UploadSiteHostedPicturesRequestType();
		// $req->setExternalPictureURL( $url );
		$req->setPictureSet( 'Supersize' );

		// switch EPS transfer mode - according to settings
		$eps_xfer_mode = get_option( 'wplister_eps_xfer_mode', 'passive' );
		if ( $eps_xfer_mode == 'active' ) {
			$picture_data = null;

			// try to load image data from filesystem first
			$upload_dir = wp_upload_dir();

			// Fix the URL
			$ib = new ItemBuilderModel();
			$url = $ib->normalizeUrl( $url, true );

			$local_path = str_replace( $upload_dir['baseurl'], $upload_dir['basedir'], $url );
			if ( file_exists( $local_path ) && is_readable( $local_path ) ) {
				$picture_data = file_get_contents( $local_path );
				WPLE()->logger->info( "loaded ".strlen($picture_data)." bytes from local file: ".$local_path );
			}

			// if image data is empty, load from URL
			if ( empty( $picture_data ) ) {
				// $picture_data = file_get_contents($url);
				$response = wp_remote_get( $url );

				if ( ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) == 200 ) {
					$picture_data = wp_remote_retrieve_body( $response );
					WPLE()->logger->info( "loaded ".strlen($picture_data)." bytes from URL: ".$url );
				} elseif ( is_wp_error( $response ) ) {
					$details  = 'wp_remote_get() failed to connect to ' . $url . '<br>';
					$details .= 'Error:' . ' ' . $response->get_error_message() . '<br>';
					wple_show_message( 'Connection to '.$url.' failed: '.$details, 'error' );
					WPLE()->logger->info( 'Connection to '.$url.' failed: '.$details );
				} else {
					$details  = 'wp_remote_get() returned an unexpected HTTP status code: ' . wp_remote_retrieve_response_code( $response );
					wple_show_message( 'Connection to '.$url.' failed: '.$details, 'error' );
					WPLE()->logger->info( 'Connection to '.$url.' failed: '.$details );
					WPLE()->logger->info( "reponse object: ".print_r($response,1) );
				}

			}

			// if image data is STILL empty, show error message to the user
			if ( empty( $picture_data ) ) {
				WPLE()->logger->error( "Could not load image data for URL: ".$url );
				//wple_show_message("There was a problem loading the product image from $url. Please make sure this image URL is accessible or disable the <i>Upload to EPS</i> option in your listing profile.",'error');
				return new WP_Error( 400, "There was a problem loading the product image from $url. Please make sure this image URL is accessible or disable the <i>Upload to EPS</i> option in your listing profile.");
			}

			$req->setPictureName( basename($url) );
			$req->setPictureData( $picture_data );
			WPLE()->logger->info( "EPS mode: active - filesize: ".strlen($picture_data) );
		} else {
			$req->setExternalPictureURL( $url );
		}


		WPLE()->logger->info( "calling UploadSiteHostedPictures - $url " );
		$res = $this->callUploadSiteHostedPictures($req, $session );
		WPLE()->logger->info( "UploadSiteHostedPictures Complete" );
		WPLE()->logger->debug( "Response: ".print_r($res,1) );

		// handle response and check if successful
		$mdl = new WPL_Model();
		if ( $mdl->handleResponse($res) ) {

			// fetch final url
			//$eps_url = $res->SiteHostedPictureDetails->FullURL;

			WPLE()->logger->info( "image was uploaded to EPS successfully. " );

			return $res;

		} // call successful

		// let the user know which image failed to upload if there was an error
		//wple_show_message( 'Failed to upload image: <code>'.$url.'</code>', 'error' );

		return new WP_Error(500, 'Failed to upload image: <code>'. $url .'</code>');
	}

	/**
	 * @param $request
	 * @param $session
	 * @param $parseMode
	 *
	 * @return mixed
	 * @throws Exception
	 */
	private function callUploadSiteHostedPictures( $request, $session, $parseMode = EBATNS_PARSEMODE_CALL )
	{
		$service = new EbatNs_ServiceProxy($session, 'EbatNs_DataConverterUtf8');

		// Fixes the failed UploadSiteHostedPictures calls #60433
		$new_token = WPLE_eBayAccount::maybeMintToken( $session->wple_account_id );

		if ( $new_token ) {
			$session->setRequestToken( $new_token );
		}

		//$this->_session     = $session;
		$userToken          = $session->getRequestToken();
		$version            = $service->getVersion();
		$ExternalPictureURL = $request->getExternalPictureURL();
		$PictureName        = $request->getPictureName();

		///Build the request XML request which is first part of multi-part POST
		$xmlMessage  = '<?xml version="1.0" encoding="utf-8"?>' . "\n";
		$xmlMessage .= '<UploadSiteHostedPicturesRequest xmlns="urn:ebay:apis:eBLBaseComponents">' . "\n";
		$xmlMessage .= "<Version>$version</Version>\n";
		$xmlMessage .= $ExternalPictureURL ? "<ExternalPictureURL>$ExternalPictureURL</ExternalPictureURL>\n" : '';
		$xmlMessage .= $PictureName ? "<PictureName>$PictureName</PictureName>\n" : '';
		$xmlMessage .= "<PictureSet>Supersize</PictureSet>\n";
		$xmlMessage .= "<RequesterCredentials><eBayAuthToken>$userToken</eBayAuthToken></RequesterCredentials>\n";
		$xmlMessage .= '</UploadSiteHostedPicturesRequest>';

		// place all data into the HTTP header
		// Note: this does not use the actual key set anymore, but eBay seems to accept any keys here
		$reqHeaders[] = 'X-EBAY-API-COMPATIBILITY-LEVEL: ' . $version;
		$reqHeaders[] = 'X-EBAY-API-DEV-NAME: '  . $session->getDevId();
		$reqHeaders[] = 'X-EBAY-API-APP-NAME: '  . $session->getAppId();
		$reqHeaders[] = 'X-EBAY-API-CERT-NAME: ' . 'none'; // don't send the license API key to eBay
		$reqHeaders[] = 'X-EBAY-API-CALL-NAME: ' . 'UploadSiteHostedPictures';
		$reqHeaders[] = 'X-EBAY-API-SITEID: ' . $session->getSiteId();

		$multiPartData = null;
		if ( empty( $ExternalPictureURL ) ) {
			// extract picture-binary data from $request->PictureData property
			// and store as $multiPartData which is passed to sendXmlMessage method
			$multiPartData = $request->getPictureData();
			$request->setPictureData(null);
		}

		// Sandbox or Production
		if ($session->getAppMode() == 1)
			$ep = "https://api.sandbox.ebay.com/wsapi";
		else
			$ep = 'https://api.ebay.com/ws/api.dll';

		$ep .= '?callname=' . 'UploadSiteHostedPictures';
		$ep .= '&version=' . $version;

		// upload image using EbatNs_Client::sendMessageXmlStyle()
		$service->setEP( $ep ); // set endpoint in EbatNs_Client
		$responseMsg    = $service->sendMessageXmlStyle( $xmlMessage, $reqHeaders, $multiPartData );

		// old version - using custom method (works)
		// $responseMsg = $this->sendMessageXmlStyle( $xmlMessage, $reqHeaders, $multiPartData );
		// old version - using WP HTTP API (does not work!)
		// $responseMsg = $this->sendXmlMessageWithoutCurl( $xmlMessage, $reqHeaders, $multiPartData );

		if ( get_option('wplister_log_to_db') == '1' ) {
			$dblogger = new WPL_EbatNs_Logger();
			$dblogger->updateLog( array(
				'callname'    => 'UploadSiteHostedPictures',
				'request_url' => $ep,
				'request'     => $xmlMessage,
				'response'    => print_r($responseMsg,1),
				'success'     => strpos( $responseMsg, 'ErrorCode' ) ? 'Failure' : 'Success'
			));
		}

		if ( $responseMsg )	{
			$ret = $service->decodeMessage( 'UploadSiteHostedPictures', $responseMsg, $parseMode );
		} else {
			$ret = $service->getCurrentResult();
		}

		return $ret;
	} // callUploadSiteHostedPictures()


} // class EbayController
