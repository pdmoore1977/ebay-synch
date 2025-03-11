<?php

require_once WPLE_PLUGIN_PATH . '/includes/ebay-rest-api/vendor/autoload.php';

class EbayMediaApi {
	protected $api_url;
	protected $wpl_account;

	/* @var Swagger\Client\Configuration */
	protected $api_config;

	public function __construct( $account_id ) {
		$account = WPLE()->accounts[ $account_id ];

		$this->wpl_account  = $account;
		$this->api_url      = 'https://api.ebay.com/commerce/media/v1_beta';

		$this->api_config = Swagger\Client\Configuration::getDefaultConfiguration()
		                                                ->setAccessToken($account->oauth_token)
		                                                ->setHost( $this->api_url );
	}

	/**
	 * @return false|\Swagger\Client\Model\CreateDocumentResponse
	 * @throws Exception
	 */
	public function createDocument( $type ) {
		$api = new \Swagger\Client\Api\DocumentApi( new \GuzzleHttp\Client(), $this->api_config );

		WPLE()->initEC( $this->wpl_account->id );
		WPLE_eBayAccount::maybeMintToken( $this->wpl_account->id );

		try {
			return $api->createDocument( $type );
		} catch ( Exception $e ) {
			WPLE()->logger->error( $e->getMessage() );
			return false;
		}
		return $resp;
	}

	/**
	 * @param $document_id
	 * @param $document_path
	 *
	 * @return false|\Swagger\Client\Model\DocumentResponse
	 * @throws Exception
	 */
	public function uploadDocument( $document_id, $document_path ) {
		$doc = fopen($document_path, 'r');
		$contents = fread($doc, filesize($document_path));
		fclose($doc);

		$api = new \Swagger\Client\Api\DocumentApi( new \GuzzleHttp\Client(['body'=>$contents]), $this->api_config );

		WPLE()->initEC( $this->wpl_account->id );
		WPLE_eBayAccount::maybeMintToken( $this->wpl_account->id );

		try {
			return $api->uploadDocument( $document_id, 'multipart/form-data' );
		} catch ( Exception $e ) {
			WPLE()->logger->error( 'Error in EbayMediaApi::uploadDocument - '. $e->getMessage() );
			return false;
		}
	}

}