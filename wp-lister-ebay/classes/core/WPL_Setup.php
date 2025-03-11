<?php

class WPL_Setup extends WPL_Core {
	
	// check if setup is incomplete and display next step
	public function checkSetup( $page = false ) {
		global $pagenow;

		// check if safe mode is enabled
		$this->isPhpSafeMode();

        // check if PHP, WooCommerce and WP are up to date
        $this->checkVersions();

		// check if incomatible plugins are active
		$this->checkPlugins();

		// check if a recent version of WooCommerce is installed
		$this->checkWooCommerce();

		// check if cURL is loaded
		if ( ! $this->isCurlLoaded() ) return false;

		// check for cURL using GnuTLS
		$this->checkForGnuTLS();

		// check for windows server
		// if ( $this->isWindowsServer() ) return false;
		$this->isWindowsServer( $page );

		// create folders if neccessary
		if ( $this->checkFolders() ) return false;

		// check for updates
		$this->checkForUpdates();

		###
        # Moved self::checkCron() so it is called and displayed on all admin pages since this is a very important notice #50867
        ###
		// check if cron is working properly
		//$this->checkCron();

		// check if any sites need to be refreshed
		$this->checkSites();

		// check database after migration
		// $this->checkDatabase();
		// $this->checkDbForInvalidAccounts();

		// check for multisite installation
		// if ( $this->checkMultisite() ) return false;

		$current_tab = isset( $_REQUEST['tab'] ) ? sanitize_key($_REQUEST['tab']) : false;

		// setup wizard

        // If there' already  a default account and the setup wizard is on step 1,
        // chances are this is a migrated site so there's no need to run the setup wizard again
        if ( get_option( 'wplister_default_account_id' ) && '1' == self::getOption('setup_next_step') ) {
            self::updateOption( 'setup_next_step', 0 );
        }

		// if ( self::getOption('ebay_token') == '' ) {
		if ( ( '1' == self::getOption('setup_next_step') ) && ( $current_tab != 'accounts') ) {
		
			$msg1 = __( 'You have not linked WP-Lister to your eBay account yet.', 'wp-lister-for-ebay' );
			$msg2 = __( 'To complete the setup procedure go to %s and follow the instructions.', 'wp-lister-for-ebay' );
			$accounts_page = get_option( 'wplister_enable_accounts_page' ) ? 'wplister-settings-accounts' : 'wplister-settings&tab=accounts';
			$link = sprintf( '<a href="admin.php?page=%s">%s</a>', $accounts_page, __( 'Account Settings', 'wp-lister-for-ebay' ) );
			$msg2 = sprintf($msg2, $link);
			$msg = "<b>$msg1</b></p><p>$msg2";
			wple_show_message($msg,'info');
		
		} elseif ( '2' == self::getOption('setup_next_step') ) {
		
			$title = __( 'Setup - Step 2', 'wp-lister-for-ebay' );
			$msg1  = __( 'Before creating your first profile, we need to download certain information which are specific to the eBay site you selected.', 'wp-lister-for-ebay' );
			$msg2  = __( 'This includes shipping options, payment methods, your custom store categories as well as the whole eBay category tree, which might take a while.', 'wp-lister-for-ebay' );

			// old button
			// $button = '<a href="#" id="btn_update_ebay_data" onclick="return false;" class="button-primary">'.__( 'Update eBay details', 'wp-lister-for-ebay' ).'</a>';

			// new button - use site_id of default (first) account
			$account = WPLE()->accounts[ get_option('wplister_default_account_id') ];
	        $button = '<a href="#" data-site_id="'.$account->site_id.'" data-account_id="'.$account->id.'" class="btn_update_ebay_data_for_site button-primary">'.__( 'Refresh eBay details', 'wp-lister-for-ebay' ).'</a>';

			$msg   = "<p><b>$title</b></p><p>$msg1</p><p>$msg2</p>";
			$msg  .= $button;
			wple_show_message($msg,'info');

			// // remember when WP-Lister was connected to an eBay account for the first time
			// update_option( 'ignore_orders_before_ts', time() );
		
		} elseif ( '3' == self::getOption('setup_next_step') ) {
		
			$tm = new TemplatesModel();
			$templates = $tm->getAll();
			if ( sizeof($templates) > 0 ) {
				self::updateOption('setup_next_step', '4');
			} else {
				$title = __( 'Setup - Step 3', 'wp-lister-for-ebay' );
				$msg1 = __( 'Create a default listing template.', 'wp-lister-for-ebay' );
				$msg2 = __( 'To create your first listing template click on %s.', 'wp-lister-for-ebay' ).'<br>';
				if ( @$_GET['action'] == 'add_new_template' )
					$msg2 = __( 'Replace the default text according to your requirements and save your template to continue.', 'wp-lister-for-ebay' );
				$link = '<a href="admin.php?page=wplister-templates&action=add_new_template">'.__( 'New Template', 'wp-lister-for-ebay' ).'</a>';
				$msg2 = sprintf($msg2, $link);
				$msg = "<p><b>$title</b></p><p><b>$msg1</b></p><p>$msg2</p>";
				wple_show_message($msg,'info');			
			}
		
		} elseif ( '4' == self::getOption('setup_next_step') ) {
		
			$pm = new ProfilesModel();
			$profiles = $pm->getAll();
			if ( sizeof($profiles) > 0 ) {
				self::updateOption('setup_next_step', '0');
			} else {
				$title = __( 'Setup - Step 4', 'wp-lister-for-ebay' );
				$msg1  = __( 'The final step: create your first listing profile.', 'wp-lister-for-ebay' );
				$msg2  = __( 'Click on %s and start defining your listing options.<br>After saving your profile, visit your Products page and select the products to list on eBay.', 'wp-lister-for-ebay' );
				$link  = '<a href="admin.php?page=wplister-profiles&action=add_new_profile">'.__( 'New Profile', 'wp-lister-for-ebay' ).'</a>';
				$msg2  = sprintf($msg2, $link);
				$msg   = "<b>$msg1</b></p><p>$msg2";
				wple_show_message($msg,'info');
			}
		
		} elseif ( '5' == self::getOption('setup_next_step') ) {
		
			$title = __( 'Setup is complete.', 'wp-lister-for-ebay' );
			$msg1  = __( 'You are ready now to list your first items.', 'wp-lister-for-ebay' );
			$msg2  = __( 'Visit your Products page, select a few items and select "List on eBay" from the bulk actions menu.', 'wp-lister-for-ebay' );
			$msg   = "<b>$msg1</b></p><p>$msg2";
			wple_show_message($msg,'info');
			update_option('wplister_setup_next_step', '0');
		
		}

		// db upgrade
		WPLE_UpgradeHelper::upgradeDB();

		// check token expiration date
		self::checkToken();

		self::showOAuthAccountNotice();

		// check if all db tables exist
		self::checkDatabaseTables( $page );
			
	}


	// update permissions
	public function updatePermissions() {

		$roles = array('administrator', 'shop_manager', 'super_admin');
		foreach ($roles as $role) {
			$role = get_role($role);
			if ( empty($role) )
				continue;
	 
			$role->add_cap('manage_ebay_listings');
			$role->add_cap('manage_ebay_options');
			$role->add_cap('prepare_ebay_listings');
			$role->add_cap('publish_ebay_listings');

		}

	}


	// check if cURL is loaded
	public function isCurlLoaded() {

		if( ! extension_loaded('curl') ) {
			wple_show_message("
				<b>Required PHP extension missing</b><br>
				<br>
				Your server doesn't seem to have the <a href='http://www.php.net/curl' target='_blank'>cURL</a> php extension installed.<br>
				cURL ist required by WP-Lister to be able to talk with eBay.<br>
				<br>
				On a recent debian based linux server running PHP 5 this should do the trick:<br>
				<br>
				<code>
					apt-get install php5-curl <br>
					/etc/init.d/apache2 restart
				</code>
				<br>
				<br>
				You'll require root access on your server to install additional php extensions!<br>
				If you are on a shared host, you need to ask your hoster to enable the cURL php extension for you.<br>
				<br>
				For more information on how to install the cURL php extension on other servers check <a href='http://stackoverflow.com/questions/1347146/how-to-enable-curl-in-php' target='_blank'>this page on stackoverflow</a>.
			",'error');
			return false;
		}

		return true;
	}

	// check if cURL is using GnuTLS 
	// GnuTLS/3.5.18 is known to have problems with load balancers not using keep-alive #34851
	public function checkForGnuTLS() {

		if( ! extension_loaded('curl') ) return;
		
		$curl_version = curl_version();
		$ssl_version  = $curl_version['ssl_version'];

		if ( strstr( $ssl_version, 'GnuTLS' ) === false ) return;

		if ( get_option( 'wple_skip_gnu_tls_compatibility_error', false ) ) return;

		$hide_url = wp_nonce_url( admin_url( 'admin-ajax.php?action=wple_hide_gnutls_error' ), 'wple_ajax_nonce' );
		wple_show_message("
			<b>Warning: Incompatible server setup (cURL with {$ssl_version})</b><br>
			<br>
			Your server seems to have a very rare version of the <a href='http://www.php.net/curl' target='_blank'>cURL</a> php extension installed, which was compiled to use the GnuTLS library instead of the standard OpenSSL library.<br>
			<br>
			Unfortunately this version of GnuTLS has a known issue with certain load balancer setups, including Cloudflare's CDN network. This means you might not be able to use WP-Lister until either your hoster has fixed this by switching to a version of cURL which uses OpenSSL, or you have moved to a different server or hosting provider.<br>
			<br>
			Please contact WP Lab support if you are seeing this warning while WP-Lister is actually working.<br>
			<br>
			If you are managing your own debian based linux server you can try the following commands:<br>
			<br>
			<code>
				apt-get remove  libcurl3-gnutls; apt-get install libcurl3 
			</code>
			<br>
			<br>
			You'll require root access on your server to install or remove these packages.<br>
			<br>
			If WP-Lister is working normally for you, you <a href='". $hide_url ."'>can hide this message</a>.
		",'error');

	}

	// check server is running windows
	public function isWindowsServer( $page ) {

		if ( $page != 'settings' ) return;
		if ( defined('WPLE_EXPERIMENTAL_WIN_SUPPORT') ) return;

		if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {

			wple_show_message("
				<b>Warning: Server requirements not met - this server runs on windows.</b><br>
				<br>
				WP-Lister currently only supports unixoid operating systems like Linux, FreeBSD and OS X.<br>
				Support for windows servers is still experimental and should not be used on production sites!
			",'warn');
			return true;
		}

		return false;
	}

	// check if PHP safe_mode is enabled
	public function isPhpSafeMode() {

        if ( ini_get('safe_mode') ) {

			wple_show_message("
				<b>Warning: PHP safe mode is enabled.</b><br>
				<br>
				Your server seems to have PHP safe mode enabled, which can cause unexpected behaviour or prevent WP-Lister from working properly.<br>
				PHP safe mode has been deprecated for years and will be completely removed in the next PHP version - so it is highly recommended to disable it or ask your hoster to do it for you.
			",'warn');
			return true;
		}

		return false;
	}

	// check WP-Lister version
	static public function isV2() {
		return version_compare( WPLE_PLUGIN_VERSION, '2.0', '>=');
	}

    // check versions
    public function checkVersions() {

	    // check if WooCommerce is up to date
        $required_version    = '3.0';
        $woocommerce_version = defined('WC_VERSION') ? WC_VERSION : WOOCOMMERCE_VERSION;
        if ( version_compare( $woocommerce_version, $required_version ) < 0 ) {
            wple_show_message("
				<b>Warning: Your WooCommerce version is outdated.</b><br>
				<br>
				WP-Lister requires WooCommerce $required_version to be installed. You are using WooCommerce $woocommerce_version.<br>
				You should always keep your site and plugins updated.<br>
			",'warn');
        }

        // PHP 5.3+
        if ( version_compare(phpversion(), '7.4', '<')) {
            wple_show_message(
                '<p>'
                . '<b>Warning: Your PHP version '.phpversion().' is outdated.</b>'
                . '<br><br>'
                . 'Your server should have PHP 7.4 or better installed.'
                . ' '
                . 'Please contact your hosting support and ask them to update your PHP version.'
                . '</p>'
                ,'warn', ['dismissible' => true]);
        }

    }

	// checks for incompatible plugins
	public function checkPlugins() {

		// Plugin Name: iThemes Slideshow
		// Plugin URI: http://ithemes.com/purchase/displaybuddy/
		// Version: 2.0.23
		if ( class_exists('pluginbuddy_slideshow') ) {

			wple_show_message("
				<b>Warning: An incompatible plugin was found.</b><br>
				<br>
				You seem to have the <i>iThemes Slideshow</i> plugin installed, which is known to cause issues with WP-Lister.<br>
				Version 2.0.23 of this plugin will slow down loading the listings page if you are using variations. This can render the entire listings page inaccessible, so please deactivate this plugin.
			",'warn');
			return false;

		}

		// Plugin Name: Yet Another Stars Rating
		// Plugin URI: http://wordpress.org/plugins/yet-another-stars-rating/
		// Version: 0.8.2
		if ( defined('YASR_VERSION_NUM') ) {

			wple_show_message("
				<b>Warning: An incompatible plugin was found.</b><br>
				<br>
				You seem to have the <i>Yet Another Stars Rating</i> plugin installed, which is known to cause issues with WP-Lister.<br>
				Version 0.8.2 of this plugin can break the process of preparing new eBay listings, so please deactivate this plugin if you experience any issues when applying a listing profile.
			",'warn');
			return false;

		}

		// Plugin Name: Booki
		// Plugin URI: http://codecanyon.net/item/booki-a-booking-plugin-for-wordpress/7460830
		// Version: 2.6
		if ( defined('BOOKI_VERSION') ) {

			wple_show_message("
				<b>Warning: An incompatible plugin was found.</b><br>
				<br>
				You seem to have the <i>Booki</i> plugin installed, which is known to cause issues with WP-Lister.<br>
				Version 2.6 of this plugin breaks WP-Lister's ability to talk to the eBay API by loading the PayPal SDK libraries on every page load. You need to deactivate this plugin in order to use WP-Lister for eBay.
			",'warn');
			return false;

		}

	} // checkPlugins()

	// check if a recent version of WooCommerce is installed
	public function checkWooCommerce() {

		// check if WooCommerce is installed
		if ( ! defined('WOOCOMMERCE_VERSION') && ! defined('WC_VERSION') ){

			wple_show_message("
				<b>WooCommerce is not installed.</b><br>
				<br>
				WP-Lister requires <a href='http://wordpress.org/plugins/woocommerce/' target='_blank'>WooCommerce</a> to be installed.<br>
			",'error');
			return false;

		}

		// check if WooCommerce is up to date
		$required_version    = '2.2.4';
		$woocommerce_version = defined('WC_VERSION') ? WC_VERSION : WOOCOMMERCE_VERSION;
		if ( version_compare( $woocommerce_version, $required_version ) < 0 ) {

			wple_show_message("
				<b>Warning: Your WooCommerce version is outdated.</b><br>
				<br>
				WP-Lister requires WooCommerce $required_version to be installed. You are using WooCommerce $woocommerce_version.<br>
				You should always keep your site and plugins updated.<br>
			",'error');
			return false;

		}

		// check if WooCommerce is up to date - warn if still on WC2.x
		$required_version    = '3.0';
		$woocommerce_version = defined('WC_VERSION') ? WC_VERSION : WOOCOMMERCE_VERSION;
		if ( version_compare( $woocommerce_version, $required_version ) < 0 ) {

			wple_show_message("
				<b>Warning: Your WooCommerce version is outdated.</b><br>
				<br>
				WP-Lister 2.3 will require WooCommerce 3.0 or better. You are still using the outdated WooCommerce $woocommerce_version which will no longer be supported.<br>
				You should always keep your site and plugins updated.<br>
			",'error');
			return false;

		}

	}


	// checks for multisite network
	public function checkMultisite() {

		if ( is_multisite() ) {

			// check for network activation
			if ( ! function_exists( 'is_plugin_active_for_network' ) )
				require_once( ABSPATH . '/wp-admin/includes/plugin.php' );

			if ( function_exists('is_network_admin') && is_plugin_active_for_network( plugin_basename( WPLE_PLUGIN_PATH.'/wp-lister-ebay.php' ) ) )
				wple_show_message("network activated!",1);
			else
				wple_show_message("not network activated!");


			// wple_show_message("
			// 	<b>Multisite installation detected</b><br>
			// 	<br>
			// 	This is a site network...<br>
			// ");
			return true;
		}

		return false;
	}


	// check for updates
	public function checkForUpdates() {
		## BEGIN PRO ##

		// check if current user has permission to update plugins
		if ( ! current_user_can( 'update_plugins' ) ) return;

		$update = get_option( 'wple_update_details' );

		if ( $update && is_object( $update ) ) {

			// check timestamp
			if ( ( time() - $update->timestamp ) > 24*3600 ) {
			
				$update = $this->check_for_new_version();

			}

		} else {
			$update = $this->check_for_new_version();
		}

		if ( $update && is_object( $update ) ) {

			if ( version_compare( $update->new_version, WPLE_PLUGIN_VERSION ) > 0 ) {

				// $install_update_button = '<a href="update-core.php" class="button">'.__( 'Install Update', 'wp-lister-for-ebay' ) . '</a>';
				// if ( 'beta' == get_option('wple_update_channel' ) ) {}

				// generate update URL with nonce
				$slug    = 'wp-lister-ebay/wp-lister-ebay.php';
				$action  = 'upgrade-plugin';
				$btn_url = wp_nonce_url(
				    add_query_arg(
				        array(
				            'action' => $action,
				            'plugin' => $slug
				        ),
				        admin_url( 'update.php' )
				    ),
				    $action.'_'.$slug
				);
				$install_update_button = '<a href="'.$btn_url.'" class="button button-primary">'.__( 'Install Update', 'wp-lister-for-ebay' ) . '</a>';

				wple_show_message( 
					'<p>'. sprintf( __( 'An update to %s is available.', 'wp-lister-for-ebay' ),  $update->title . ' ' . $update->new_version )
					// . __( 'Please visit your WordPress Updates to install the new version.', 'wp-lister-for-ebay' ) . '<br><br>'
					. '&nbsp;&nbsp;'
					. ( $update->upgrade_html ? '<a href="#"" onclick="jQuery(\'.update_details_info\').slideToggle();return false;" class="button">'.__( 'Show Details', 'wp-lister-for-ebay' ) . '</a>&nbsp;&nbsp;&nbsp;' : '' )
					. $install_update_button
					. '</p>'
					. '<div class="update_details_info" style="display:none; border-top: 2px dashed #eee;">' 
					. ( $update->upgrade_html ? $update->upgrade_html . '<br>' : '' )
					. ( isset( $update->upgrade_notice ) ? $update->upgrade_notice . '<br><br>' : '' )
					. '<br>'
					. '<em>Last checked: '.human_time_diff( $update->timestamp ) . ' ago</em>'
					. '</div>'
				,'warn');

			}

		}

		## END PRO ##
	}

	public function check_for_new_version() {

		if ( class_exists('WPLE_Update_API') ) { 

			$response = WPLEUP()->check_for_new_version( false );

			if ( ! $response->new_version ) return false;

			return true;
		}

		return $update;
	}


	// check if any sites need to be refreshed
	public function checkSites() {
		global $wpdb;

		// return if DB has not been initialized yet
		if ( get_option('wplister_db_version') < 41 ) return;

		// get all enabled sites
		$enabled_sites = $wpdb->get_var("SELECT count(id) FROM ".$wpdb->prefix."ebay_sites WHERE enabled = 1 ");

		// if no sites are enabled, check accounts and enable sites in use
		if ( ! $enabled_sites ) {
			// enable site for each account
			foreach ( WPLE()->accounts as $account ) {
				$wpdb->update( $wpdb->prefix.'ebay_sites', array( 'enabled' => 1 ), array( 'id' => $account->site_id ) );
			}			
			// reload enabled sites
			$enabled_sites = $wpdb->get_var("SELECT count(id) FROM ".$wpdb->prefix."ebay_sites WHERE enabled = 1 ");
			if ( ! $enabled_sites ) return;
		}

		$sites_to_update = $wpdb->get_var("SELECT count(id) FROM ".$wpdb->prefix."ebay_sites WHERE enabled = 1 AND last_refresh IS NULL ");
		if ( ! $sites_to_update ) return;

		$sites_to_update = $wpdb->get_col("SELECT title FROM ".$wpdb->prefix."ebay_sites WHERE enabled = 1 AND last_refresh IS NULL ");
		$sites_to_update_csv = implode( ', ', $sites_to_update );
		// show warning
		$msg1 = __( 'Site specific eBay details need to be updated.', 'wp-lister-for-ebay' );
		$msg2 = __( 'Please visit your %s and click on "Refresh Details" for the following sites: %s.', 'wp-lister-for-ebay' );
		$accounts_page = get_option( 'wplister_enable_accounts_page' ) ? 'wplister-settings-accounts' : 'wplister-settings&tab=accounts';
		$link = sprintf( '<a href="admin.php?page=%s">%s</a>', $accounts_page, __( 'Account Settings', 'wp-lister-for-ebay' ) );
		$msg2 = sprintf($msg2, $link, $sites_to_update_csv);
		$msg = "<b>$msg1</b></p><p>$msg2";
		wple_show_message($msg,'warn');

	} // checkSites()


	// check if WP_Cron is working properly
	public function checkCron() {

		// schedule daily event if not set yet
		if ( ! wp_next_scheduled( 'wple_daily_schedule' ) ) {
			wp_schedule_event( time(), 'daily', 'wple_daily_schedule' );
		}

		## BEGIN PRO ##
        // schedule daily event if not set yet
        if ( ! wp_next_scheduled( 'wple_license_check' ) ) {
            wp_schedule_event( time(), 'twicedaily', 'wple_license_check' );
        }

		if ( get_option( 'wplister_overdue_orders_check', 0 ) && ! wp_next_scheduled( 'wple_overdue_orders_check' ) ) {
			wp_schedule_event( time(), 'twicedaily', 'wple_overdue_orders_check' );
		}
		## END PRO ##

		// get current schedule settings
		$cron_interval  = get_option( 'wplister_cron_auctions' );
		$next_scheduled = wp_next_scheduled( 'wplister_update_auctions' ) ;
		if ( ! $cron_interval ) return;
		if ( 'external' == $cron_interval ) return;

		// check if schedule is active
		if ( $cron_interval && ! $next_scheduled ) {

			wple_show_message( 
				'<p>'
				. '<b>Warning: WordPress Cron Job has been disabled - scheduled WP-Lister tasks are not executed!</b>'
				. '<br><br>'
				. 'The task schedule has been reset just now in order to automatically fix this.'
				. '<br><br>'
				. 'If this message does not disappear, please visit the <a href="admin.php?page=wplister-settings">Settings</a> page and click <i>Save Settings</i> or contact support.'
				. '</p>'
			,'warn');

			// this should fix it:
			wp_schedule_event( time(), $cron_interval, 'wplister_update_auctions' );
			return;
		} 

		// check if schedule is delayed (by 1d)
		//$next_scheduled = $next_scheduled - 3600*148; // debug only
		if ( ( $next_scheduled < current_time('timestamp',1) - 3600*25 ) && ! WPLE()->isStagingSite() ) {

			wple_show_message( 
				'<p>'
				. '<b>Attention: WordPress cron jobs seem to be broken on your site!</b>'
				. '<br><br>'
				. 'There are active background jobs which were scheduled to run '
				. human_time_diff( $next_scheduled, current_time('timestamp',1) ) . ' ago, '
				. 'but never have been executed.'
				. '<br><br>'
				. 'You should contact your hoster or site administrator to get this fixed as soon as possible. Until then, WP-Lister will not be able to sync the inventory correctly nor process new orders from eBay.'
				. '<br><br>'
				. 'The quickest way to make sure this will not happen again is using an external cron job to trigger the background tasks every 5 minutes. To do so, change the "update interval" setting option to "use external cron job" and follow the instructions. This is strongly recommended if you are using WP-Lister for Amazon as well.'
				. '<br><br>'
				. 'Keep in mind that this issue is not related to WP-Lister but to WordPress itself. All plugins and features which rely on scheduled tasks are affected by this issue - which includes scheduled posts, internal cleanup routines in WooCommerce and more.'
				. '<br><br>'
				. 'To see all your scheduled tasks and when they were last executed, we recommend installing '
				. '<a href="https://wordpress.org/plugins/debug-bar/" target="_blank">Debug Bar</a> and the '
				. '<a href="https://wordpress.org/plugins/debug-bar-cron/" target="_blank">Debug Bar Cron</a> extension. '
				. 'A possible workaround for sites with broken WP-Cron is the '
				. '<a href="https://wordpress.org/plugins/wp-cron-control/" target="_blank">WP Cron Control</a> plugin, '
				. 'but we recommend to find out what is causing this and fixing it instead.'
				. '</p>'
			,'error');

		}

	} // checkCron()

    static function displayAnnouncements() {
        $data = self::downloadAnnouncements();

        if ( !$data ) return;

        if ( !empty( $data->alerts ) ) {
            foreach ( $data->alerts as $alert ) {
                wple_show_message( wp_kses_post( $alert ),'warn', ['dismissible' => true]);
            }
        }

        if ( !empty( $data->notifications ) ) {
            foreach ( $data->notifications as $notification ) {
                wple_show_message( wp_kses_post( $notification ),'info', ['dismissible' => true]);
            }
        }
    }

    /**
     * Check for important announcements like important new features or critical fixes
     */
    public static function downloadAnnouncements() {
        $data = get_option( 'wple_announcements', false );

        // Run checks twice a day
        $last_run   = get_option( 'wple_last_announcement_check', 0 );
        $now        = current_time( 'timestamp' );
        $download   = false;

        if ( !$data || !$last_run ) {
            $download = true;
        }


        if ( $last_run ) {
            $diff = $now - $last_run;
            //$diff = 86400; // testing
            if ( $diff > 43200 ) {
                $download = true;
            }
        }

        // check existing announcement's validity
        if ( ! self::checkForValidAnnouncements() ) {
            $download = true;
        }

        if ( $download ) {
            update_option( 'wple_last_announcement_check', $now );

            $endpoint = 'http://update.wplab.de/beta/wple-announcements.php';
            //$endpoint = 'https://lab.devel/wpla-announcements.php';
            $data = array(
                'wc'    => defined( 'WC_VERSION' ) ? WC_VERSION : '',
                'wp'    => get_bloginfo( 'version' ),
                'wple'  => WPLE_PLUGIN_VERSION
            );
            $endpoint .= '?'. http_build_query( $data );
            $result = wp_remote_get( $endpoint, array(
                    'sslverify'     => false,
                )
            );

            if ( is_wp_error( $result ) ) {
                WPLE()->logger->error( 'Error fetching announcements: '. $result->get_error_message() . '('. $result->get_error_code() .')' );
                return false;
            }

            $data = json_decode( wp_remote_retrieve_body( $result ) );
            update_option( 'wple_announcements', $data );
        }

        return $data;
    }

    static function checkForValidAnnouncements() {
        $data = get_option( 'wple_announcements', false );

        // no announcements - considered valid so no need to redownload
        if ( $data === false ) {
            return true;
        }

        // compare the versions of WC, WP and WPLA and if one of them is different, we need to check for new announcements
        $wc    = defined( 'WC_VERSION' ) ? WC_VERSION : '';
        $wp    = get_bloginfo( 'version' );
        $wple  = WPLE_PLUGIN_VERSION;

        if ( isset( $data->wc_version ) && version_compare( $data->wc_version, $wc, '!=' ) ) {
            return false;
        }

        if ( isset( $data->wp_version ) && version_compare( $data->wp_version, $wp, '!=' ) ) {
            return false;
        }

        if ( isset( $data->wple_version ) && version_compare( $data->wple_version, $wple, '!=' ) ) {
            return false;
        }

        return true;
    }


	// check if all database tables exist
	public function checkDatabaseTables( $page ) {
		global $wpdb;

		if ( $page != 'settings' ) return;
		if ( 0 == get_option('wplister_db_version', 0) ) return;

		$required_tables = array(
		    'ebay_accounts',
		    'ebay_auctions',
		    'ebay_categories',
		    'ebay_jobs',
		    'ebay_log',
		    'ebay_messages',
		    'ebay_orders',
		    'ebay_payment',
		    'ebay_profiles',
		    'ebay_shipping',
		    'ebay_sites',
		    'ebay_store_categories',
		    'ebay_transactions',
		);

		$tables  = $wpdb->get_col('show tables like "'.$wpdb->prefix.'ebay%" ');
		$missing = array();

		foreach ($required_tables as $tablename ) {
			if ( ! in_array( $wpdb->prefix.$tablename, $tables ) ) {
				// wple_show_message( 'Missing database table: ' . $tablename, 'error' );
				$missing[] = $tablename;
			}
		}

		if ( ! empty($missing) ) {
			wple_show_message( '<b>Error: The following table(s) are missing in your database: ' . join(', ', $missing) . '</b><br><!br>Please contact support or reinstall WP-Lister from scratch, by enabling the "Uninstall on deactivation" option on the <href="admin.php?page=wplister-settings&tab=advanced">advanced setting page</a>, then deactivate the plugin and activate it again.', 'error' );
		}

	} // checkDatabaseTables()

	// check if database has been corrupted during migration 
	public function checkDatabase() {
		global $wpdb;

		$rows_null_count = $wpdb->get_var("SELECT count(id) FROM ".$wpdb->prefix."ebay_auctions WHERE relist_date = '0000-00-00 00:00:00' OR date_finished = '0000-00-00 00:00:00'  ");
		if ( $rows_null_count ) {
			$wpdb->query("UPDATE ".$wpdb->prefix."ebay_auctions SET date_created   = NULL WHERE date_created   = '0000-00-00 00:00:00' ");
			$wpdb->query("UPDATE ".$wpdb->prefix."ebay_auctions SET date_published = NULL WHERE date_published = '0000-00-00 00:00:00' ");
			$wpdb->query("UPDATE ".$wpdb->prefix."ebay_auctions SET end_date       = NULL WHERE end_date       = '0000-00-00 00:00:00' ");
			$wpdb->query("UPDATE ".$wpdb->prefix."ebay_auctions SET relist_date    = NULL WHERE relist_date    = '0000-00-00 00:00:00' ");
			$wpdb->query("UPDATE ".$wpdb->prefix."ebay_auctions SET date_finished  = NULL WHERE date_finished  = '0000-00-00 00:00:00' ");
			wple_show_message( 'Repaired DB rows: ' . $rows_null_count );
			echo $wpdb->last_error;
		}

	}

	// this might help if there went something wrong during the upgrade from 1.5 to 1.6 or 2.0
	static function assignAllDataToDefaultAccount() {
		global $wpdb;
		$accounts   = WPLE()->accounts;
		$account_id = get_option( 'wplister_default_account_id' );
		if ( ! $account_id ) die('No default account set!');
		if ( ! isset( $accounts[ $account_id ] ) ) die('Invalid default account set!');

		$site_id    = $accounts[ $account_id ]->site_id;
		$site_id    = intval( $site_id    ); // sanitize parameters 
		$account_id = intval( $account_id );

		$wpdb->query("UPDATE ".$wpdb->prefix."ebay_auctions         SET account_id = $account_id, site_id = $site_id ");
		echo $wpdb->last_error;
		$wpdb->query("UPDATE ".$wpdb->prefix."ebay_categories       SET site_id = $site_id ");
		echo $wpdb->last_error;
		$wpdb->query("UPDATE ".$wpdb->prefix."ebay_log              SET account_id = $account_id, site_id = $site_id ");
		echo $wpdb->last_error;
		$wpdb->query("UPDATE ".$wpdb->prefix."ebay_messages         SET account_id = $account_id, site_id = $site_id ");
		echo $wpdb->last_error;
		$wpdb->query("UPDATE ".$wpdb->prefix."ebay_orders           SET account_id = $account_id, site_id = $site_id ");
		echo $wpdb->last_error;
		$wpdb->query("UPDATE ".$wpdb->prefix."ebay_profiles         SET account_id = $account_id, site_id = $site_id ");
		echo $wpdb->last_error;
		$wpdb->query("UPDATE ".$wpdb->prefix."ebay_payment          SET site_id = $site_id ");
		echo $wpdb->last_error;
		$wpdb->query("UPDATE ".$wpdb->prefix."ebay_shipping         SET site_id = $site_id ");
		echo $wpdb->last_error;
		$wpdb->query("UPDATE ".$wpdb->prefix."ebay_store_categories SET account_id = $account_id, site_id = $site_id ");
		echo $wpdb->last_error;
		$wpdb->query("UPDATE ".$wpdb->prefix."ebay_transactions     SET account_id = $account_id, site_id = $site_id ");
		echo $wpdb->last_error;

	} // assignAllDataToDefaultAccount()


	// check for listings, profiles and orders using an invalid / nonexisting account
	static function checkDbForInvalidAccounts() {
		global $wpdb;
		$accounts              = WPLE()->accounts;
		$default_account_id    = get_option( 'wplister_default_account_id' );
		$default_account       = isset( $accounts[ $default_account_id ] ) ? $accounts[ $default_account_id ] : false;
		$default_account_title = $default_account ? $default_account->title : 'MISSING DEFAULT ACCOUNT';
		if ( empty($accounts) ) return;

		// get list of all active account IDs
		$active_account_ids = array();
		foreach ($accounts as $account) {
			$active_account_ids[] = $account->id;
		}
		$active_account_ids_sql = join(', ', $active_account_ids);

		// find data with invalid account IDs
		$listings_count = $wpdb->get_var("
			SELECT count(account_id)
			  FROM ".$wpdb->prefix."ebay_auctions
			 WHERE NOT account_id IN ( $active_account_ids_sql )
		");
		$profiles_count = $wpdb->get_var("
			SELECT count(account_id)
			  FROM ".$wpdb->prefix."ebay_profiles
			 WHERE NOT account_id IN ( $active_account_ids_sql )
		");
		$orders_count = $wpdb->get_var("
			SELECT count(account_id)
			  FROM ".$wpdb->prefix."ebay_orders
			 WHERE NOT account_id IN ( $active_account_ids_sql )
		");

		// return if no problems found
		if ( ! $listings_count && ! $profiles_count && ! $orders_count ) return;

		// compile summary
		$what_exactly = array();
		if ( $listings_count ) $what_exactly[] = $listings_count . ' listings';
		if ( $profiles_count ) $what_exactly[] = $profiles_count . ' profiles';
		if ( $orders_count   ) $what_exactly[] = $orders_count   . ' orders';
		$what_exactly = join(' and ',$what_exactly);

		$btn_url = wp_nonce_url( 'admin.php?page=wplister-settings&tab=accounts&action=wple_assign_invalid_data_to_default_account', 'wple_assign_invalid_data_to_default_account' );

		// show message
		$msg = sprintf('<b>Warning: There are %s using an account which does not exist anymore.</b>',$what_exactly) . '<br><br>';
		$msg .= 'This can happen when you delete an account from WP-Lister without removing all listings, profiles and orders first.'. '<br>';
		$msg .= sprintf('Please click the button below to assign all found items to your default account <b>%s</b> (ID %s).', $default_account_title, $default_account_id ) . '<br><br>';
		$msg .= sprintf('<a href="%s" class="button button-secondary">Assign found items to default account</a>', $btn_url );
		wple_show_message($msg,'warn');

	} // checkDbForInvalidAccounts()

	// fix listings, profiles and orders using an invalid / nonexisting account
	static function fixItemsUsingInvalidAccounts() {
		global $wpdb;
		$accounts           = WPLE()->accounts;
		$default_account_id = get_option( 'wplister_default_account_id' );
		$default_account    = isset( $accounts[ $default_account_id ] ) ? $accounts[ $default_account_id ] : false;
		if ( ! $default_account ) die('Invalid default account set!');

		// get list of all active account IDs
		$active_account_ids = array();
		foreach ($accounts as $account) {
			$active_account_ids[] = $account->id;
		}
		$active_account_ids_sql = join(', ', $active_account_ids);

		// find data with invalid account IDs
		$listings_count = $wpdb->get_var("
			UPDATE ".$wpdb->prefix."ebay_auctions
			   SET account_id = '$default_account_id'
			 WHERE NOT account_id IN ( $active_account_ids_sql )
		");
		$profiles_count = $wpdb->get_var("
			UPDATE ".$wpdb->prefix."ebay_profiles
			   SET account_id = '$default_account_id'
			 WHERE NOT account_id IN ( $active_account_ids_sql )
		");
		$orders_count = $wpdb->get_var("
			UPDATE ".$wpdb->prefix."ebay_orders
			   SET account_id = '$default_account_id'
			 WHERE NOT account_id IN ( $active_account_ids_sql )
		");
		// echo $wpdb->last_query;
		echo $wpdb->last_error;

		// show message
		$msg = 'All items have been assigned to your default account.';
		wple_show_message($msg);

	} // fixItemsUsingInvalidAccounts()

    /*
     * Check for accounts that aren't using oAuth tokens yet
     */
    public function showOAuthAccountNotice() {
        // don't show this when in the Accounts page
        if ( isset( $_GET['page'] ) && $_GET['page'] == 'wplister-settings' && isset( $_GET['tab'] ) && $_GET['tab'] == 'accounts' ) {
            return;
        }

        $accounts = WPLE()->accounts;
        $valid = true;
        foreach ( $accounts as $account ) {
            if ( $account->active && empty( $account->refresh_token ) && empty( $account->refresh_token_expiry ) ) {
                $valid = false;
                break;
            }
        }

        if ( !$valid ) {
            // token has already expired
            $accounts_url = 'admin.php?page=wplister-settings&tab=accounts';
            wple_show_message(
                '<p>'
                // . '<b>Warning: '. __( 'Your ebay token has expired on', 'wp-lister-for-ebay' ) . ' ' . $expdate
                . __( 'Warning: WP-Lister is migrating to eBay\'s newer authentication system (oAuth). Please visit your Accounts page to get new tokens for your accounts.', 'wp-lister-for-ebay' )
                . ' &nbsp; <a href="'.$accounts_url.'" class="button button-primary">Go to the Accounts page</a>'
                . '</p>'
                ,'warn');
        }
    }

	// check token expiration date
	public function checkToken() {

		// legacy option - not used anymore
		// $expdate = get_option( 'wplister_ebay_token_expirationtime' );

		// skip check when fetching new token - no warning should be shown in that case
		if ( isset( $_GET['action'] ) && $_GET['action'] == 'wplister_fetch_ebay_token' ) return;

		// get all accounts
		$accounts = WPLE()->accounts;
		if ( ! is_array($accounts) || empty($accounts) ) return;

		$two_weeks_from_now = time() + 3600 * 24 * 7 * 4;

		foreach ($accounts as $account) {

			// get a valid expiration time
            if ( $account->oauth_token ) {
                $expdate = $account->refresh_token_expiry;
            } else {
                $expdate = $account->valid_until;
            }

			if ( ! $expdate ) continue;
			if ( ! $exptime = strtotime($expdate) ) continue;
			if ( ! $account->active ) continue;

			if ( $exptime < time() ) {

				// token has already expired
				$refresh_url = 'admin.php?page=wplister-settings&tab=accounts&action=wple_edit_account&ebay_account=' . $account->id;
				wple_show_message( 
					'<p>'
					// . '<b>Warning: '. __( 'Your ebay token has expired on', 'wp-lister-for-ebay' ) . ' ' . $expdate
					. '<b> '. sprintf( __( 'Warning: The token for your eBay account %s has expired on %s.', 'wp-lister-for-ebay' ), $account->title, $expdate )
					. ' (' . human_time_diff( strtotime($expdate) ) . ' ago) '.'</b>'
					. '<br><br>'
					. 'To refresh your eBay token, please click "Refresh Token" and follow the instructions in the right sidebar.'
					. ' &nbsp; <a href="'.$refresh_url.'" class="button button-primary">Refresh Token</a>'
					. '</p>'
				,'error');

			} elseif ( $exptime < $two_weeks_from_now ) {

				// token will expire soon
				$refresh_url = 'admin.php?page=wplister-settings&tab=accounts&action=wple_edit_account&ebay_account=' . $account->id;
				wple_show_message( 
					'<p>'
					// . '<b>Warning: '. __( 'Your eBay token will expire on', 'wp-lister-for-ebay' ) . ' ' . $expdate
					. '<b> '. sprintf( __( 'Warning: The token for your eBay account %s will expire on %s.', 'wp-lister-for-ebay' ), $account->title, $expdate )
					. ' (in ' . human_time_diff( strtotime($expdate) ) . ') '.'</b>'
					. '<br><br>'
					. 'To refresh your eBay token, please click "Refresh Token" and follow the instructions in the right sidebar.'
					. ' &nbsp; <a href="'.$refresh_url.'" class="button button-primary">Refresh Token</a>'
					. '</p>'
				,'warn');

			}

		} // foreach account


        /**
         * OBSOLETE
         *
         * OAuth token can now be refreshed without user interaction using a long-life refresh token
         */
		// warn about invalid token - could be obsolete since we check for expiry time already, but maybe it's still useful
		/*if ( $token_data = get_option('wplister_ebay_token_is_invalid') ) {
			if ( is_array( $token_data ) && isset($token_data['site_id'] ) ) {

				// update token expiration date
				$this->initEC( $token_data['account_id'] );
		        $this->EC->initLogger();
				$expdate = $this->EC->GetTokenStatus( true );
				$this->EC->closeEbay();
				if ( $expdate ) {

					update_option( 'wplister_ebay_token_is_invalid', false );
	
				} else {

					$account       = isset( WPLE()->accounts[ $token_data['account_id'] ] ) ? WPLE()->accounts[ $token_data['account_id'] ]: false;
					$account_title = $account ? $account->title . '('.$account->site_code.')' : 'Default';
					$recheck_url   = wp_nonce_url( 'admin.php?page=wplister-tools&action=GetTokenStatus', 'e2e_tools_page' );
					$refresh_url   = 'admin.php?page=wplister-settings&tab=accounts&action=wple_edit_account&ebay_account=' . $token_data['account_id'];
					$msg1  = sprintf( __( 'The token for your eBay account %s seems to be invalid.', 'wp-lister-for-ebay' ), $account_title );
					$msg2  = 'This could be due to a temporary issue with the eBay API, but it is recommended to check your token now. ';
					$msg2 .= 'If your token is valid, this message will disappear. If not, you need to refresh your token: ';
					$msg3  = '<a href="'.$recheck_url.'" class="button button-primary">Check Token</a> &nbsp; ';
					$msg3 .= '<a href="'.$refresh_url.'" class="button">Refresh Token</a>';
					$msg   = "<b>$msg1</b></p><p>$msg2</p><p>$msg3";
					wple_show_message($msg,'warn');			

				}

			}
		}*/
		
	} // checkToken()


	// check folders
	public function checkFolders() {
		// WPLE()->logger->info('creating wp-content/uploads/wp-lister/templates');		

		// create wp-content/uploads/wp-lister/templates if not exists
		$uploads = wp_upload_dir();
		$uploaddir = $uploads['basedir'];

		$wpldir = $uploaddir . '/wp-lister';
		if ( !is_dir($wpldir) ) {

			$result  = @mkdir( $wpldir );
			if ($result===false) {
				wple_show_message( "Could not create template folder: " . $wpldir, 1, 1 );	
				return false;
			}

		}

		$tpldir = $wpldir . '/templates';
		if ( !is_dir($tpldir) ) {

			$result  = @mkdir( $tpldir );
			if ($result===false) {
				wple_show_message( "Could not create template folder: " . $tpldir, 1, 1 );	
				return false;
			}

		}

		// WPLE()->logger->info('template folder: '.$tpldir);		
	
	}
	


}

