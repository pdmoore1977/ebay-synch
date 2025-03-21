<?php

use WPLab\GuzzleHttp\Client;

class WPLE_Http_Client extends Client {
	const API_HOST = 'node3.wplister.com';

	private WPLE_Logger $db_logger;
	private ?int $wple_account_id   = null;
	private ?int $market_id         = null;

	public function __construct(array $config = [], $account_id = null, $market_id = null) {
		parent::__construct($config);

		$this->wple_account_id = $account_id;
		$this->market_id = $market_id;
	}

	public function send(\Psr\Http\Message\RequestInterface $request, array $options = []): \Psr\Http\Message\ResponseInterface {
		$request = $this->requestToProxy( $request );
		$clone = $request;

		if ( $request->getMethod() == 'GET' ) {
			$contents = $clone->getUri()->getQuery();
		} else {
			$contents = $clone->getBody()->getContents();
		}

		// log to db - before request
		$db_logger = new WPL_EbatNs_Logger();
		$log_data = array(
			'callname'    => WPL_EbatNs_Logger::getCallNameFromRequest( $clone ),
			'request'     => $request->getMethod() .' '. (string)$clone->getHeaderLine('X-EBAY-C-ENDPOINT') ."\n\n". serialize(['headers' => $clone->getHeaders(), 'payload' => maybe_serialize( $contents ) ]),
			'request_url' => (string)$clone->getHeaderLine('X-EBAY-C-ENDPOINT'),
			'account_id'  => $this->wple_account_id,
//			'market_id'   => $this->market_id,
			'success'     => 'pending'
		);
		$db_logger->updateLog($log_data);

		// ***** END MWSWPL PATCH *****
		//$options['debug'] = true;
		//$options['allow_redirects'] = false;
		//$options['verify'] = false;
		//$options['allow_redirects'] = false;
		//WPLA()->logger->debug( 'HEADERS: '. print_r( $request->getHeaders(), 1) );

		try {
			$response = parent::send($request, $options);

			// log to db - after request
			$http_code = $response->getStatusCode();
			$success = ($http_code >= 200 && $http_code < 300) ? 'Success' : 'Failure';
			$response_body = $response->getBody()->getContents();
			$db_logger->updateLog( array(
				'response'    => $response_body,
				//'result'      => $response_body,
				//'http_code'   => $http_code,
				'success'     => $success
			));

			return $response;
		} catch ( Exception $e ) {
			$db_logger->updateLog( array(
				'response'    => maybe_serialize( $e->getMessage() ),
				//'response'    => $e->getResponse()->getBody()->getContents(),
				//'http_code'   => $e->getCode(),
				'success'     => 'Failure'
			));

			throw $e;
		}

	}

	private function requestToProxy( \Psr\Http\Message\RequestInterface $request ) {
		$uri = $request->getUri();
		$endpoint = \WPLab\GuzzeHttp\Psr7\Uri::composeComponents( $uri->getScheme(), $uri->getAuthority(), $uri->getPath(), '', '' );
		$query = $uri->getQuery();

		//$wpla_api = new WPLA_Amazon_SP_API( $this->wpla_account_id );
		//$access_key = $wpla_api->buildSpecialAccessKey();

		if ( $request->getUri()->getHost() == 'auth.wplister.com' ) {
			// only accepts GET requests
			$contents = $request->getBody()->getContents();
			$contents_array = json_decode( $contents, true );
			$query = $uri->getQuery() .'&'. http_build_query( $contents_array );

			$uri = $uri->withQuery( $query );
			$request = $request->withMethod('GET');
		} else {
			/*$uri = $uri
				->withHost( self::API_HOST )
				->withPath( '/ebay/v1/' )
				->withQuery( $query );*/
		}

		$request = $request
			->withUri( $uri )
			->withHeader( 'X-EBAY-C-ENDPOINT', (string)$endpoint );
			//->withHeader( 'X-EBAY-C-TOKEN', $wpla_api->getSPAccessToken())
			//->withHeader( 'X-EBAY-C-REST-TOKEN', $wpla_api->getAwsToken())
			//->withHeader( 'X-AMZ-AWSACCESSKEYID', $access_key );

		return $request;
	}

}