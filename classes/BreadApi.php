<?php
/**
 * Plugin Class File
 *
 * Created:   July 11, 2019
 *
 * @package:  Bread Finance
 * @author:   Bread
 * @since:    0.1.0
 */

namespace Bread\WooCommerceGateway;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Access denied.' );
}

use \Modern\Wordpress\Pattern\Singleton;

/**
 * BreadApi Class
 */
class BreadApi extends Singleton {
	/**
	 * @var    self
	 */
	protected static $_instance;

	/**
	 * @var    \Modern\Wordpress\Plugin        Provides access to the plugin instance
	 */
	protected $plugin;

	/**
	 * @var string
	 */
	public $basicAuth;

	/**
	 * @var string
	 */
	public $apiUrl;

	/**
	 * @return BreadApi
	 */
	public static function instance() {
		return parent::instance();
	}

	/**
	 * Get plugin
	 *
	 * @return    \Modern\Wordpress\Plugin
	 */
	public function getPlugin() {
		return $this->plugin;
	}

	/**
	 * Set plugin
	 *
	 * @return    this            Chainable
	 */
	public function setPlugin( \Modern\Wordpress\Plugin $plugin = null ) {
		$this->plugin = $plugin;

		return $this;
	}

	/**
	 * Constructor
	 *
	 * @param    \Modern\Wordpress\Plugin $plugin The plugin to associate this class with, or NULL to auto-associate
	 *
	 * @return    void
	 */
	public function __construct( \Modern\Wordpress\Plugin $plugin = null ) {
		$this->setPlugin( $plugin ?: \Bread\WooCommerceGateway\Plugin::instance() );
		$breadGateway = $this->getPlugin()->getBreadGateway();
		$this->basicAuth = 'Basic ' . base64_encode( $breadGateway->get_api_key() . ':' . $breadGateway->get_api_secret_key() );
		$this->apiUrl    = $breadGateway->get_api_url();
	}

	/**
	 * Get a bread transaction
	 *
	 * @param    string $tx_id The transaction id
	 *
	 * @return    array
	 */
	public function getTransaction( $tx_id ) {
		return $this->makeRequest( 'GET', '/transactions/' . $tx_id );
	}

	/**
	 * Authorize a bread transaction
	 *
	 * @param    string $tx_id The transaction id
	 *
	 * @return    array
	 */
	public function authorizeTransaction( $tx_id, $order_id = null ) {
		$params = ( $order_id === null )
			? array( 'type' => 'authorize' )
			: array( 'type' => 'authorize', 'merchantOrderId' => strval( $order_id ) );

		return $this->makeRequest( 'POST', '/transactions/actions/' . $tx_id, $params );
	}

	/**
	 * Update a bread transaction
	 *
	 * @param    string $tx_id The transaction id
	 * @param    array $payload The updated transaction data
	 *
	 * @return    array
	 */
	public function updateTransaction( $tx_id, $payload ) {
		return $this->makeRequest( 'PUT', '/transactions/' . $tx_id, $payload );
	}

	/**
	 * Settle a bread transaction
	 *
	 * @param    string $tx_id The transaction id
	 *
	 * @return    array
	 */
	public function settleTransaction( $tx_id ) {
		return $this->makeRequest( 'POST', '/transactions/actions/' . $tx_id, array( 'type' => 'settle' ) );
	}

	/**
	 * Cancel a bread transaction
	 *
	 * @param    string $tx_id The transaction id
	 *
	 * @return    array
	 */
	public function cancelTransaction( $tx_id ) {
		return $this->makeRequest( 'POST', '/transactions/actions/' . $tx_id, array( 'type' => 'cancel' ) );
	}

	/**
	 * Refund a bread transaction
	 *
	 * @param    string $tx_id The transaction id
	 *
	 * @param int|null $amount Amount (in cents) to refund
	 * @param array|null $line_items
	 *
	 * @return    array
	 */
	public function refundTransaction( $tx_id, $amount = null, $line_items = null ) {
		$params = array( 'type' => 'refund' );

		if ( $amount ) {
			$params['amount'] = $amount;
		}

		if ( $line_items ) {
			$params['lineItems'] = $line_items;
		}

		return $this->makeRequest( 'POST', '/transactions/actions/' . $tx_id, $params );
	}


	/**
	 * Create Bread Cart
	 *
	 * @param    array
	 *
	 * @return    array
	 */
	public function createBreadCart( $payload ) {
		return $this->makeRequest( 'POST', '/carts', $payload );
	}

	/**
	 * Expire Bread Cart
	 *
	 * @param    string
	 *
	 * @return    void
	 */
	public function expireBreadCart ($cartId ) {
		$this->makeRequest( 'POST', '/carts/' . $cartId . '/expire' ); 
	}

	/**
	 * Make a request to the bread api
	 *
	 * @param    string $method The request method
	 * @param    string $endpoint The api endpoint to contact
	 * @param    array $payload The data to send to the endpoint
	 *
	 * @return    array|WP_Error
	 */
	protected function makeRequest( $method, $endpoint, $payload = array() ) {
		$wp_remote = $method == 'GET' ? 'wp_remote_get' : 'wp_remote_post';
		$api_url   = $this->apiUrl . $endpoint;

		$result = call_user_func( $wp_remote, $api_url, array(
			'method'  => $method,
			'headers' => array( 'Content-Type' => 'application/json', 'Authorization' => $this->basicAuth ),
			'body'    => json_encode( $payload ),
		) );

		if ( ! is_wp_error( $result ) ) {
			return json_decode( $result['body'], true );
		}

		return $result;
	}

}
