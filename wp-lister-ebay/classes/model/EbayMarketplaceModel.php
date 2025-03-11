<?php

/**
 * EbayMarketplaceModel class
 *
 * An interface for eBay's Marketplace API that's used to get properties from the different marketplaces
 *
 */

require_once WPLE_PLUGIN_PATH . '/includes/ebay-rest-api/vendor/wplab/guzzle/src/functions_include.php';
require_once WPLE_PLUGIN_PATH . '/includes/ebay-rest-api/vendor/guzzlehttp/guzzle/src/functions_include.php';
//require_once WPLE_PLUGIN_PATH . '/includes/ebay-rest-api/vendor/guzzlehttp/psr7/src/functions_include.php';
//require_once WPLE_PLUGIN_PATH . '/includes/ebay-rest-api/vendor/guzzlehttp/promises/src/functions_include.php';
require_once WPLE_PLUGIN_PATH . '/includes/ebay-rest-api/vendor/autoload.php';

class EbayMarketplaceModel extends WPL_Model {

    private $api_url;

    private $wpl_account;

    /* @var Swagger\Client\Configuration */
    private $api_config;

    public function __construct( $wple_account_id ) {
        $account = WPLE()->accounts[ $wple_account_id ];

        $this->wpl_account  = $account;
        $this->api_url      = $account->sandbox_mode
            ? 'https://api.sandbox.ebay.com/sell/metadata/v1'
            : 'https://api.ebay.com/sell/metadata/v1';

        $this->api_config = Swagger\Client\Configuration::getDefaultConfiguration()
            ->setAccessToken($account->oauth_token)
            ->setHost( $this->api_url );
    }

    public function getItemConditionPolicies( $marketplace_id ) {
        try {
            /**
             * Adding the Accept and Accept-Encoding headers fixes the 401 error we have been getting!
             */
            $client_options = [
                'timeout'   => 600,
                'headers'   => [
                    'Accept'    => 'application/json',
                    'Accept-Encoding' => 'gzip'
                ]
            ];
            $api = new \Swagger\Client\Api\MarketplaceApi( new WPLab\GuzzleHttp\Client($client_options), $this->api_config );

            //$response = $api->getItemConditionPolicies( $marketplace_id, '183050|183454|261328' );
            $response = $api->getItemConditionPolicies( $marketplace_id, 'categoryIds:{183050|183454|261328}' );
            $policies = $response->getItemConditionPolicies();
            // log request to db
            if ( get_option('wplister_log_to_db') == '1' ) {
                $dblogger = new WPL_EbatNs_Logger();
                $dblogger->updateLog( array(
                    'callname'    => 'getItemConditionPolicies',
                    'request_url' => '',
                    'request'     => $marketplace_id,
                    'response'    => '',
                    'success'     => 'Success'
                ));
            }

            // $response sometimes is null as reported in #53525
            if ( $response ) {
                $policies = $response->getItemConditionPolicies();
                //WPLE()->logger->debug( 'Received aspects from the API: '. print_r( $aspects, 1 ) );

                $categories_array = [];
                foreach ( $policies as $policy ) {
                    $cat_id = $policy['category_id'];
                    $item_conditions = [];
                    foreach ( $policy['item_conditions'] as $item_condition ) {
                        $item_conditions[] = [
                            'condition_description' => $item_condition['condition_description'],
                            'condition_id'          => $item_condition['condition_id'],
                            'usage'                 => $item_condition['usage']
                        ];
                    }
                    $categories_array[ $cat_id ] = $item_conditions;
                }

                return $categories_array;
            } else {
                WPLE()->logger->error('Error: Failed getting Item Condition Policies. WP-Lister could not connect to the API.');
                wple_show_message( __('Error: Failed getting Item Condition Policies. WP-Lister could not connect to the API.' ) );
                return false;
            }


        } catch ( Exception $e ) {
            WPLE()->logger->error('Error #'. $e->getCode() .': Failed getting Item Condition Policies. eBay said "'. $e->getMessage() .'".');
            wple_show_message( __('Error #'. $e->getCode() .': Failed getting Item Condition Policies. eBay said "'. $e->getMessage() .'".' ) );
            return false;
        }
    }

}