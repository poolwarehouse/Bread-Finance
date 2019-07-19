<?php
/**
 * Plugin Class File
 *
 * @vendor: Bread
 * @package: Bread Finance
 * @author: Bread
 * @link:
 * @since: July 11, 2019
 */

namespace Bread\WooCommerceGateway;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Access denied.' );
}


/**
 * Plugin Class
 */
class Plugin extends \Modern\Wordpress\Plugin {

	const TEXT_DOMAIN = 'woocommerce-gateway-bread';

	/**
	 * Instance Cache - Required
	 * @var    self
	 */
	protected static $_instance;

	/**
	 * @var string        Plugin Name
	 */
	public $name = 'WooCommerce Gateway Bread';

	/**
	 * Main Stylesheet
	 *
	 * @Wordpress\Stylesheet
	 */
	public $mainStyle = 'assets/css/style.css';

	/**
	 * Main Javascript Controller
	 *
	 * @Wordpress\Script( deps={"mwp"} )
	 */
	public $mainScript = 'assets/js/main.js';

	/**
	 * @Wordpress\Script( deps={"mwp"} )
	 */
	public $breadCheckout = 'assets/js/bread-checkout.js';

	/**
	 * @var array   Supported product-types
	 */
	public $supportedProducts;

	/**
	 * @var WCGatewayBreadFinance
	 */
	protected $gateway;

	public function init() {
		$this->supportedProducts = array( 'simple', 'grouped', 'variable', 'composite' );
	}

	public function getTextDomain() {
		return self::TEXT_DOMAIN;
	}

	public function getSetting( $name, $key = 'main' ) {
		return parent::getSetting( $name, $key );
	}

	/**
	 * Get the bread gateway
	 *
	 * @return    WCGatewayBreadFinance
	 */
	public function getBreadGateway() {
		if ( isset( $this->gateway ) ) {
			return $this->gateway;
		}

		$this->setBreadGateway( new WCGatewayBreadFinance );

		return $this->gateway;
	}

	/**
	 * Set the bread gateway
	 *
	 * @param    WCGatewayBreadFinance $gateway The gateway for bread
	 *
	 * @return    void
	 */
	public function setBreadGateway( $gateway ) {
		$this->gateway = $gateway;
	}

	/**
	 * @param $product \WC_Product
	 *
	 * @return bool
	 */
	public function supportsProduct( $product ) {
		return in_array( $product->get_type(), $this->supportedProducts );
	}

	/**
	 * @Wordpress\Action( for="send_headers" )
	 */
	public function addCorsHeaders( $origins ) {
		header( "Access-Control-Allow-Origin: " . $this->getBreadGateway()->get_resource_url() );
	}

	/**
	 * Enqueue scripts and stylesheets
	 *
	 * @Wordpress\Action( for="wp_enqueue_scripts" )
	 *
	 * @return    void
	 */
	public function enqueueScripts() {
		/** @var \Bread\WooCommerceGateway\Utilities $util */
		$util = \Bread\WooCommerceGateway\Utilities::instance();

		if ( $this->isGatewayEnabled() && ! is_admin() ) {
			$this->useStyle( $this->mainStyle );

			$script = $this->getBreadGateway()->get_resource_url() . '/bread.js';

			wp_enqueue_script( 'bread-api', $script, array( 'jquery-serialize-object', 'jquery-ui-dialog' ) );

			$this->useScript( $this->mainScript, array(
					'page_type'     => $util->getPageType(),
					'product_type'  => $util->getProductType(),
					'gateway_token' => WCGatewayBreadFinance::WC_BREAD_GATEWAY_ID,
					'bread_api_key' => $this->getBreadGateway()->get_api_key(),
					'ajaxurl'       => admin_url( 'admin-ajax.php' ),
					'debug'         => $util->toBool( $this->getSetting( 'debug' ) )
				)
			);
		}
	}

	/**
	 * Check API and Secret API key validity by creating a test Bread cart
	 * Priority 11 ensures settings are saved before Bread cart API call happens
	 *
	 * @Wordpress\Action( for="woocommerce_update_options_payment_gateways_bread_finance", priority=11 )
	 *
	 */

	public function validateBreadSettings() {
		$this->getBreadGateway()->validate_api_keys();
	}

	/**
	 * Add data-api-key attribute to bread-api script
	 *
	 * @Wordpress\Filter( for="script_loader_tag", priority=10, args=3 )
	 *
	 * @param $tag
	 * @param $handle
	 * @param $src
	 *
	 * @return    string
	 */
	public function addAPIKeyToScript( $tag, $handle, $src ) {
			if ( 'bread-api' === $handle ) {
					$tag = '<script type="text/javascript" src="' . esc_url( $src ) . '" data-api-key="' . $this->getBreadGateway()->get_api_key() . '"></script>';
			}

			return $tag;
	}

	/**
	 * Register the payment gateway w/ WooCommerce
	 *
	 * @Wordpress\Filter( for="woocommerce_payment_gateways", priority=10, args=1 )
	 *
	 * @param $gateways
	 *
	 * @return array
	 */
	public function registerBreadGateway( $gateways ) {
		$gateways[] = 'Bread\WooCommerceGateway\WCGatewayBreadFinance';

		return $gateways;
	}

	/**
	 * Return a 'settings' plugin_action_link
	 *
	 * @Wordpress\Filter( for="plugin_action_links_bread-finance/plugin.php", args=1 )
	 *
	 * @param $links
	 *
	 * @return array
	 */
	public function registerPluginActionLinks( $links ) {
			return array_merge( array(
					'<a href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout&section=bread_finance' ) ) . '">' . __( 'Settings', 'woocommerce-gateway-bread' ) . '</a>'
			), $links );
	}

	/**
	 * Check if the payment gateway is enabled
	 *
	 * @return    bool
	 */
	public function isGatewayEnabled() {
		return ( 'yes' === $this->getSetting( 'enabled' ) );
	}

	/**
	 * Refund or cancel a bread transaction when an order is cancelled
	 *
	 * @Wordpress\Action( for="woocommerce_order_status_cancelled" )
	 *
	 * @return    void
	 */
	public function orderCancelled( $order_id ) {

		$order   = wc_get_order( $order_id );
		$gateway = $this->getBreadGateway();

		if ( $order->get_payment_method() !== $gateway->id ) {
			return;
		}

		$result = $gateway->cancel_transaction( $order_id );

		if ( is_wp_error( $result ) ) {
            $order = wc_get_order( $order_id );
            $order->add_order_note( $result->get_error_message() );
		}
	}

	/**
	 * Settle a bread transaction when the order is completed
	 *
	 * @Wordpress\Action( for="woocommerce_order_status_on-hold_to_processing" )
     * @Wordpress\Action( for="woocommerce_order_status_on-hold_to_completed" )
	 *
	 * @return void
	 */
	public function settleOrder( $order_id ) {

		$order   = wc_get_order( $order_id );
		$gateway = $this->getBreadGateway();

		if ( $order->get_payment_method() !== $gateway->id ) {
			return;
		}

		$result = $gateway->settle_transaction( $order_id );

		if ( is_wp_error( $result ) ) {
		    $order = wc_get_order( $order_id );
		    $order->add_order_note( $result->get_error_message() );
		}

	}


	/**
	 * Prevent WooCommerce from loading/saving to the main cart session when performing certain AJAX requests.
	 *
	 * To properly calculate tax and shipping we need to create a `WC_Cart` session with the selected products
	 * and user data. This is complicated by the fact that WooCommerce will attempt to load the user's cart
	 * when creating an instance of `WC_Cart`, first by using the cart cookie, then from the logged-in user
	 * if the cookie fails.
	 *
	 * By using a custom null session handler we are able to create in-memory carts, disconnected from the
	 * user's main cart session, for the purposes of accurately calculating tax & shipping.
	 *
	 * @Wordpress\Action( for="before_woocommerce_init" )
	 */
	public function anonymizeTaxAndShippingAjax() {

		if ( ! ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ){
			return;
		}

		// @formatter:off
		if ( ! ( array_key_exists( 'action', $_REQUEST ) && strpos( $_REQUEST['action'], 'bread' ) === 0 ) ) {
			return;
		}
		// @formatter:on

		// We want to use the main cart session when the user is on the "view cart" page.
		if ( $_REQUEST['source'] === 'cart_summary' ) {
			return;
		}

		if ( in_array( $_REQUEST['action'], [ 'bread_calculate_tax', 'bread_calculate_shipping' ] ) ) {

			require_once( 'WCSessionHandlerBread.php' );

			add_filter( 'woocommerce_session_handler', function ( $handler ) {
				return "Bread\WooCommerceGateway\WC_Session_Handler_Bread";
			}, 99, 1 );

		}

	}

	/**
	 * Use our custom cart session handler during certain add-to-cart requests.
	 *
	 * In order to easily and accurately calculate the price, tax, & shipping of complex product types
	 * (composite, grouped, variable), we want to use the native WooCommerce add-to-cart functionality.
	 * This ensures that all relevant actions/filters are run and eliminates the need for complex, error-prone
	 * handling of individual product types.
	 *
	 * To accomplish this, we hook in to the WooCommerce execution pipeline at three key points:
	 *
	 * before_woocommerce_init: Substitute our non-persisting cart session handler.
	 * woocommerce_init: Ensure our bread cart is empty.
	 * woocommerce_add_to_cart: handle our specific json request (get options, tax, or shipping).
	 *                          Note: this should execute with a low priority to ensure cart totals
	 *                          and any other add-to-cart actions/filters are executed before the
	 *                          response is created.
	 *
	 * @Wordpress\Action( for="before_woocommerce_init" )
	 */
	public function initBreadCart() {

		if ( ! array_key_exists( 'add-to-cart', $_POST ) ) {
			return;
		}

		// @formatter:off
		if ( ! ( array_key_exists( 'action', $_REQUEST ) && in_array( $_REQUEST['action'], ['bread_get_options', 'bread_calculate_tax', 'bread_calculate_shipping'] ) ) ) {
			return;
		}
		// @formatter:on

		require_once( 'WCSessionHandlerBread.php' );

		add_filter( 'woocommerce_session_handler', function ( $handler ) {
			return "Bread\WooCommerceGateway\WC_Session_Handler_Bread";
		}, 99, 1 );

	}

	/**
	 * @param $check
	 * @param $object_id
	 * @param $meta_key
	 * @param $meta_value
	 * @param $prev_value
	 *
	 * @Wordpress\Filter( for="update_user_metadata", args=5 )
	 */
	public function blockBreadCartPersistence( $check, $object_id, $meta_key, $meta_value, $prev_value ) {

//		if ( ! array_key_exists( 'add-to-cart', $_POST ) ) {
//			return $check;
//		}

		// @formatter:off
		if ( ! ( array_key_exists( 'action', $_REQUEST ) && in_array( $_REQUEST['action'], ['bread_get_options', 'bread_calculate_tax', 'bread_calculate_shipping'] ) ) ) {
			return $check;
		}
		// @formatter:on

		return strpos( $meta_key, '_woocommerce_persistent_cart' ) === 0;

	}

    /**
     * Allows checkout data to be validated without creating an order. With this in place, native WooCommerce
     * validation can be run before presenting the user with the Bread checkout dialog.
     *
     * @param $data
     * @param $errors \WP_Error
     *
     * @Wordpress\Action( for="woocommerce_after_checkout_validation", args=2 )
     */
	public function preventOrderCreationDuringValidation( $data, $errors ) {

        if ( ! array_key_exists( 'bread_validate', $_REQUEST ) ) {
            return;
        }

        if ( empty( $errors->get_error_messages() ) ) {
            wp_send_json( array( 'result' => 'success' ) );
            wp_die( 0 );
        }

    }

	/**
	 * Ensure the Bread session cart is empty
	 *
	 * @Wordpress\Action( for="woocommerce_init" )
	 */
	public function emptyBreadCart() {

		if ( ! array_key_exists( 'add-to-cart', $_POST ) ) {
			return;
		}

		if ( ! ( array_key_exists( 'action', $_REQUEST ) && strpos( $_REQUEST['action'], 'bread' ) === 0 ) ) {
			return;
		}

		WC()->cart->empty_cart();
	}


	/**
	 * Bread add-to-cart action handler.
	 *
	 * @param $cart_item_key
	 * @param $product_id
	 * @param $quantity
	 * @param $variation_id
	 * @param $variation
	 * @param $cart_item_data
	 *
	 * @Wordpress\Action( for="woocommerce_add_to_cart", args=6, priority=99 )
	 */
	public function handleBreadCartAction( $cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data ) {

		if ( ! array_key_exists( 'add-to-cart', $_POST ) ) {
			return;
		}

		if ( ! ( array_key_exists( 'action', $_REQUEST ) && strpos( $_REQUEST['action'], 'bread' ) === 0 ) ) {
			return;
		}

		try {
			$shippingContact  = ( array_key_exists( 'shipping_contact', $_REQUEST ) ) ? $_REQUEST['shipping_contact'] : null;
			$billingContact  = ( array_key_exists( 'billing_contact', $_REQUEST ) ) ? $_REQUEST['billing_contact'] : null;

			/** @var ButtonHelper $buttonHelper */
			$buttonHelper = \Bread\WooCommerceGateway\ButtonHelper::instance();
			$error_message = "Error getting Bread options.";

			switch ( $_POST['action'] ) {
				case 'bread_get_options':
					wp_send_json_success( $buttonHelper->getBreadOptions() );
					break;

				case 'bread_calculate_tax':
					$error_message = "Error calculating sales tax.";
					$buttonHelper->updateCartContact( $shippingContact, $billingContact );
					wp_send_json( $buttonHelper->getTax() );
					break;

				case 'bread_calculate_shipping':
					$error_message = "Error calculating shipping.";
					$buttonHelper->updateCartContact( $shippingContact, $billingContact );
					wp_send_json( $buttonHelper->getShipping() );
					break;
			}
		} catch ( \Exception $e ) {
			wp_send_json_error( __( $error_message, $this->getPlugin()->getTextDomain() ) );
		}

	}

}