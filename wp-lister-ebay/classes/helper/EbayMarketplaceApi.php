<?php

require_once WPLE_PLUGIN_PATH . '/includes/ebay-rest-api/vendor/autoload.php';

class EbayMarketplaceApi {
	protected $api_url;
	protected $wpl_account;

	/* @var Swagger\Client\Configuration */
	protected $api_config;

	public function __construct( $account_id ) {
		$account = WPLE()->accounts[ $account_id ];

		$this->wpl_account  = $account;
		$this->api_url      = $account->sandbox_mode
			? 'https://api.sandbox.ebay.com/sell/metadata/v1'
			: 'https://api.ebay.com/sell/metadata/v1';

		$this->api_config = Swagger\Client\Configuration::getDefaultConfiguration()
		                                                ->setAccessToken($account->oauth_token)
		                                                ->setHost( $this->api_url );
	}

	/**
	 * @return false|\Swagger\Client\Model\HazardousMaterialDetailsResponse
	 * @throws Exception
	 */
	public function getHazardousMaterialsLabels() {
		$api = new \Swagger\Client\Api\MarketplaceApi( new \GuzzleHttp\Client(), $this->api_config );

		WPLE()->initEC( $this->wpl_account->id );
		WPLE_eBayAccount::maybeMintToken( $this->wpl_account->id );

		try {
			$site = WPLE_eBaySite::getSite( $this->wpl_account->site_id );
			$resp = $api->getHazardousMaterialsLabels( $site->code );
		} catch ( Exception $e ) {
			return false;
		}
		return $resp;
	}

	/**
	 * @return false|\Swagger\Client\Model\ProductSafetyLabelsResponse
	 * @throws Exception
	 */
	public function getProductSafetyLabels() {
		$api = new \Swagger\Client\Api\MarketplaceApi( new \GuzzleHttp\Client(), $this->api_config );

		WPLE()->initEC( $this->wpl_account->id );
		WPLE_eBayAccount::maybeMintToken( $this->wpl_account->id );

		try {
			$site = WPLE_eBaySite::getSite( $this->wpl_account->site_id );
			$resp = $api->getProductSafetyLabels( $site->code );
		} catch ( Exception $e ) {
			return false;
		}
		return $resp;
	}

}