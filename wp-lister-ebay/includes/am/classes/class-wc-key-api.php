<?php

/**
 * WooCommerce API Manager API Key Class
 *
 * @package Update API Manager/Key Handler
 * @author Todd Lahman LLC
 * @copyright   Copyright (c) Todd Lahman LLC
 * @since 1.3
 *
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class WPLE_Api_Manager_Example_Key {

    /**
     * @var The single instance of the class
     */
    protected static $_instance = null;

    public static function instance() {

        if ( is_null( self::$_instance ) ) {
        	self::$_instance = new self();
        }

        return self::$_instance;
    }

	// API Key URL
	public function create_software_api_url( $args ) {

		$api_url = add_query_arg( 'wc-api', 'am-software-api', WPLEUP()->upgrade_url );

		return $api_url . '&' . http_build_query( $args );
	}

	public function activate( $args ) {

		$defaults = array(
			'request' 			=> 'activation',
			'product_id' 		=> WPLEUP()->ame_product_id,
			'instance' 			=> WPLEUP()->ame_instance_id,
			'platform' 			=> WPLEUP()->ame_domain,
			'software_version' 	=> WPLEUP()->ame_software_version
			);

		$args = wp_parse_args( $defaults, $args );

		// there is at least one server where this does not work via HTTP GET...
		// $target_url = self::create_software_api_url( $args );
		// $request = wp_remote_get( $target_url );

		// send request via HTTP POST
		$api_url = add_query_arg( 'wc-api', 'am-software-api', WPLEUP()->upgrade_url );
		$request = wp_safe_remote_post( $api_url, array( 'body' => $args, 'timeout' => 30 ) );

		if( is_wp_error( $request ) || wp_remote_retrieve_response_code( $request ) != 200 ) {
			// wple_show_message('wp_remote_post() failed to connect to '.$api_url.': <pre>'.print_r($request,1).'</pre>','error');
            $error = is_wp_error( $request ) ? '<br/>Error: '. $request->get_error_message() : '';
			wple_show_message('wp_remote_post() failed to connect to '.$api_url . $error ,'error');
			return false;
		}

		$response = wp_remote_retrieve_body( $request );

		return $response;
	}

	public function deactivate( $args ) {

		$defaults = array(
			'request' 		=> 'deactivation',
			'product_id' 	=> WPLEUP()->ame_product_id,
			'instance' 		=> WPLEUP()->ame_instance_id,
			'platform' 		=> WPLEUP()->ame_domain
			);

		$args = wp_parse_args( $defaults, $args );

		// $target_url = self::create_software_api_url( $args );
		// $request = wp_remote_get( $target_url );

		// send request via HTTP POST
		$api_url = add_query_arg( 'wc-api', 'am-software-api', WPLEUP()->upgrade_url );
		$request = wp_safe_remote_post( $api_url, array( 'body' => $args, 'timeout' => 30 ) );

		if( is_wp_error( $request ) || wp_remote_retrieve_response_code( $request ) != 200 ) {
			// wple_show_message('wp_remote_post() failed to connect to '.$api_url.': <pre>'.print_r($request,1).'</pre>','error');
			wple_show_message('wp_remote_post() failed to connect to '.$api_url.'.<br>Error: '.$request->get_error_message(),'error');
			return false;
		}

		$response = wp_remote_retrieve_body( $request );

		return $response;
	}

	/**
	 * Checks if the software is activated or deactivated
	 * @param  array $args
	 * @return array
	 */
	public function status( $args ) {
        WPLE()->logger->info( 'WPLE_AM::status()');
		$defaults = array(
			'request' 		=> 'status',
			'product_id' 	=> WPLEUP()->ame_product_id,
			'instance' 		=> WPLEUP()->ame_instance_id,
			'platform' 		=> WPLEUP()->ame_domain
			);

		$args = wp_parse_args( $defaults, $args );

		// $target_url = self::create_software_api_url( $args );
		// $request = wp_remote_get( $target_url );

		// send request via HTTP POST
		$api_url = add_query_arg( 'wc-api', 'am-software-api', WPLEUP()->upgrade_url );
		$request = wp_safe_remote_post( $api_url, array( 'body' => $args, 'timeout' => 30 ) );

		if( is_wp_error( $request ) || wp_remote_retrieve_response_code( $request ) != 200 ) {
			// wple_show_message('wp_remote_post() failed to connect to '.$api_url.': <pre>'.print_r($request,1).'</pre>','error');
            WPLE()->logger->error('wp_remote_post() failed to connect to '.$api_url.'.<br>Error: '.$request->get_error_message());
			wple_show_message('wp_remote_post() failed to connect to '.$api_url.'.<br>Error: '.$request->get_error_message(),'error');
			return false;
		}

		$response = wp_remote_retrieve_body( $request );

		return $response;
	}

}

// Class is instantiated as an object by other classes on-demand
