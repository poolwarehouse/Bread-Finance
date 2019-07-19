<?php
/**
 *  WooCommerce Payment Gateway for Bread (Finance)
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

class WCGatewayBreadFinance extends \WC_Payment_Gateway {

	const WC_BREAD_GATEWAY_ID = 'bread_finance';
	const TEXT_DOMAIN = 'woocommerce-gateway-bread';

	const DOMAIN_SANDBOX = 'https://checkout-sandbox.getbread.com';
	const DOMAIN_PRODUCTION = 'https://checkout.getbread.com';

	/**
	 * @var \Bread\WooCommerceGateway\Plugin
	 */
	private $plugin;

	/**
	 * @var \Bread\WooCommerceGateway\Utilities;
	 */
	private $util;

	function __construct() {

		$this->plugin = \Bread\WooCommerceGateway\Plugin::instance();
		$this->plugin->setBreadGateway( $this );

		$this->util = \Bread\WooCommerceGateway\Utilities::instance();

		$this->id                 = self::WC_BREAD_GATEWAY_ID;
		$this->method_title       = __( 'Bread (Finance)', self::TEXT_DOMAIN );
		$this->method_description = __( 'Allow customers to pay for their purchase over time using Bread financing.', self::TEXT_DOMAIN );

		$this->title       = $this->get_option( 'title' );
		$this->description = $this->get_option( 'description' );

		if ( 'yes' === $this->get_option( 'display_icon' ) ) {
			$this->icon = plugins_url( $this->plugin->pluginSlug() . '/assets/image/logo.png' );
		}

		$this->supports = array( 'refunds' );

		$this->init_form_fields();
		$this->init_settings();

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array(
			$this,
			'process_admin_options'
		) );
	}

	function init_form_fields() {

		$general = array(
			'enabled' => array(
				'title'   => esc_html__( 'Enable / Disable', self::TEXT_DOMAIN ),
				'label'   => esc_html__( 'Enable this gateway', self::TEXT_DOMAIN ),
				'type'    => 'checkbox',
				'default' => 'no',
			),
			'title' => array(
				'title'    => esc_html__( 'Title', self::TEXT_DOMAIN ),
				'type'     => 'text',
				'desc_tip' => esc_html__( 'Payment method title that the customer will see during checkout.', self::TEXT_DOMAIN ),
				'default'  => esc_html__( 'Pay Over Time', self::TEXT_DOMAIN ),
			),
			'description' => array(
				'title'    => esc_html__( 'Description', self::TEXT_DOMAIN ),
				'type'     => 'textarea',
				'desc_tip' => esc_html__( 'Payment method description that the customer will see during checkout.', self::TEXT_DOMAIN ),
				'default'  => esc_html__( 'Bread lets you pay over time for the things you need.', self::TEXT_DOMAIN ),
			),
			'display_icon' => array(
				'title'   => esc_html__( 'Display Bread Icon', SELF::TEXT_DOMAIN ),
				'label'   => esc_html__( 'Display the Bread icon next to the payment method title during checkout.', SELF::TEXT_DOMAIN ),
				'type'    => 'checkbox',
				'default' => 'no'
			),
			'pre_populate' => array(
				'title'   => esc_html__( 'Auto-Populate Forms', self::TEXT_DOMAIN ),
				'label'   => esc_html__( 'Auto-populate Bread form fields for logged-in WooCommerce users.', self::TEXT_DOMAIN ),
				'type'    => 'checkbox',
				'default' => 'yes',
			),
			'default_payment' => array(
				'title'   => esc_html__( 'Bread as Default', self::TEXT_DOMAIN ),
				'label'   => esc_html__( 'Upon successful customer prequalification at product and category pages, set Bread as the default payment option at checkout.', self::TEXT_DOMAIN ),
				'type'    => 'checkbox',
				'default' => 'yes',
			),
			'debug'       => array(
				'title'   => esc_html__( 'Enable Debugging', self::TEXT_DOMAIN ),
				'label'   => esc_html__( 'Log errors to the Javascript console.', self::TEXT_DOMAIN ),
				'type'    => 'checkbox',
				'default' => 'no',
			),
		);

		$environment = array(
			'api_settings'              => array(
				'title' => esc_html__( 'API Settings', self::TEXT_DOMAIN ),
				'type'  => 'title'
			),
			'environment'               => array(
				'title'    => esc_html__( 'Environment', self::TEXT_DOMAIN ),
				'type'     => 'select',
				'desc_tip' => esc_html__( 'Select the gateway environment to use for transactions', self::TEXT_DOMAIN ),
				'default'  => 'sandbox',
				'options'  => array(
					'sandbox'    => 'Sandbox',
					'production' => 'Production'
				)
			),
			'sandbox_api_key'           => array(
				'title'    => esc_html__( 'Sandbox API Key', self::TEXT_DOMAIN ),
				'type'     => 'text',
				'desc_tip' => esc_html__( 'Your Bread Sandbox API Key' )
			),
			'sandbox_api_secret_key'    => array(
				'title'    => esc_html__( 'Sandbox API Secret Key', self::TEXT_DOMAIN ),
				'type'     => 'text',
				'desc_tip' => esc_html__( 'Your Bread Sandbox API Secret Key' )
			),
			'production_api_key'        => array(
				'title'    => esc_html__( 'Production API Key', self::TEXT_DOMAIN ),
				'type'     => 'text',
				'desc_tip' => esc_html__( 'Your Bread Production API Key' )
			),
			'production_api_secret_key' => array(
				'title'    => esc_html__( 'Production API Secret Key', self::TEXT_DOMAIN ),
				'type'     => 'text',
				'desc_tip' => esc_html__( 'Your Bread Production API Secret Key' )
			)
		);

		$button_appearance = array(
			'button_appearance'            => array(
				'title' => esc_html__( 'Button Appearance', self::TEXT_DOMAIN ),
				'type'  => 'title',
			),
			'button_custom_css'            => array(
				'title'       => esc_html__( 'Custom CSS', self::TEXT_DOMAIN ),
				'type'        => 'textarea',
				'description' => __( 'Overwrite the default Bread CSS with your own. More information <a href="http://docs.getbread.com/docs/manual-integration/button-styling/" target="blank">here</a>.', self::TEXT_DOMAIN ),
				'default'     => ''
			),
			'button_size'                  => array(
				'title'   => esc_html__( 'Button Size', self::TEXT_DOMAIN ),
				'type'    => 'select',
				'default' => 'default',
				'options' => array(
					'default' => 'Default (200px x 50px)',
					'custom'  => 'Custom (Using CSS)'
				)
			),
			'button_options_category'      => array(
				'title' => esc_html__( 'Category Page Options', self::TEXT_DOMAIN ),
				'type'  => 'title',
			),
			'button_as_low_as_category'    => array(
				'title'   => esc_html__( 'As Low As', self::TEXT_DOMAIN ),
				'type'    => 'checkbox',
				'label'   => esc_html__( 'Display price per month to logged out users using the lowest available APR and longest term length offered.', self::TEXT_DOMAIN ),
				'default' => 'yes'
			),
			'button_act_as_label_category' => array(
				'title'   => esc_html__( 'Act as Label', self::TEXT_DOMAIN ),
				'type'    => 'checkbox',
				'label'   => esc_html__( 'Prevent Bread modal from loading after prequalification. (Not recommended)', self::TEXT_DOMAIN ),
				'default' => 'no'
			),
			'button_location_category'     => array(
				'title'       => esc_html__( 'Button Placement', self::TEXT_DOMAIN ),
				'type'        => 'select',
				'description' => esc_html__( 'Location on the category pages where the Bread button should appear', self::TEXT_DOMAIN ),
				'options'     => array(
					'after_shop_loop_item:before' => esc_html__( 'Before Add to Cart Button', self::TEXT_DOMAIN ),
					'after_shop_loop_item:after'  => esc_html__( 'After Add to Cart Button', self::TEXT_DOMAIN ),
					''                            => esc_html__( "Don't Display Button on Category Pages", self::TEXT_DOMAIN )
				),
				'default'     => 'woocommerce_after_shop_loop_item:after'
			),
			'button_options_product'       => array(
				'title' => esc_html__( 'Product Page Options', self::TEXT_DOMAIN ),
				'type'  => 'title',
			),
			'button_checkout_product'      => array(
				'title'   => esc_html__( 'Allow Checkout', self::TEXT_DOMAIN ),
				'type'    => 'checkbox',
				'label'   => esc_html__( 'Allow users to complete checkout from the product page after prequalification.', self::TEXT_DOMAIN ),
				'default' => 'no'
			),
			'button_as_low_as_product'     => array(
				'title'   => esc_html__( 'As Low As', self::TEXT_DOMAIN ),
				'type'    => 'checkbox',
				'label'   => esc_html__( 'Display price per month to logged out users using the lowest available APR and longest term length offered.', self::TEXT_DOMAIN ),
				'default' => 'yes'
			),
			'button_act_as_label_product'  => array(
				'title'   => esc_html__( 'Act as Label', self::TEXT_DOMAIN ),
				'type'    => 'checkbox',
				'label'   => esc_html__( 'Prevent Bread modal from loading after prequalification. (Not recommended)', self::TEXT_DOMAIN ),
				'default' => 'no'
			),
			'button_location_product'      => array(
				'title'       => esc_html__( 'Button Placement', self::TEXT_DOMAIN ),
				'type'        => 'select',
				'description' => esc_html__( 'Location on the product pages where the Bread button should appear', self::TEXT_DOMAIN ),
				'options'     => array(
					'before_single_product_summary' => esc_html__( 'Before Product Summary', self::TEXT_DOMAIN ),
					'before_add_to_cart_form'       => esc_html__( 'Before Add to Cart Button', self::TEXT_DOMAIN ),
					'after_add_to_cart_form'        => esc_html__( 'After Add to Cart Button', self::TEXT_DOMAIN ),
					'after_single_product_summary'  => esc_html__( 'After Product Summary', self::TEXT_DOMAIN ),
					''                              => esc_html__( "Don't Display Button on Product Pages", self::TEXT_DOMAIN )
				),
				'default'     => 'after_add_to_cart_form'
			),			
			'button_options_cart'          => array(
				'title' => esc_html__( 'Cart Summary Page Options', self::TEXT_DOMAIN ),
				'type'  => 'title',
			),
			'button_checkout_cart'         => array(
				'title'   => esc_html__( 'Allow Checkout', self::TEXT_DOMAIN ),
				'type'    => 'checkbox',
				'label'   => esc_html__( 'Allow users to complete checkout from the cart page after prequalification.', self::TEXT_DOMAIN ),
				'default' => 'no'
			),
			'button_as_low_as_cart'        => array(
				'title'   => esc_html__( 'As Low As', self::TEXT_DOMAIN ),
				'type'    => 'checkbox',
				'label'   => esc_html__( 'Display price per month to logged out users using the lowest available APR and longest term length offered.', self::TEXT_DOMAIN ),
				'default' => 'yes'
			),
			'button_act_as_label_cart'     => array(
				'title'   => esc_html__( 'Act as Label', self::TEXT_DOMAIN ),
				'type'    => 'checkbox',
				'label'   => esc_html__( 'Prevent Bread modal from loading after prequalification. (Not recommended)', self::TEXT_DOMAIN ),
				'default' => 'no'
			),
			'button_location_cart'         => array(
				'title'       => esc_html__( 'Button Placement', self::TEXT_DOMAIN ),
				'type'        => 'select',
				'description' => esc_html__( 'Location on the cart summary page where the Bread button should appear', self::TEXT_DOMAIN ),
				'options'     => array(
					'after_cart_totals' => esc_html__( 'After Cart Totals', self::TEXT_DOMAIN ),
					''                  => esc_html__( "Don't Display Button on Cart Summary Page", self::TEXT_DOMAIN )
				),
				'default'     => 'after_cart_totals'
			),
			'button_options_checkout'      => array(
				'title' => esc_html__( 'Checkout Page Options', self::TEXT_DOMAIN ),
				'type'  => 'title',
			),
			'button_checkout_checkout'     => array(
				'title'   => esc_html__( 'Show Bread as Payment', self::TEXT_DOMAIN ),
				'type'    => 'checkbox',
				'label'   => esc_html( 'Enable Bread as a payment option on the checkout page.', self::TEXT_DOMAIN ),
				'default' => 'yes'
			),
			'button_as_low_as_checkout'    => array(
				'title'   => esc_html__( 'As Low As', self::TEXT_DOMAIN ),
				'type'    => 'checkbox',
				'label'   => esc_html__( 'Display price per month to logged out users using the lowest available APR and longest term length offered.', self::TEXT_DOMAIN ),
				'default' => 'yes'
			)
		);

		/*
		 * Don't forget to update `Option` classes `getOptions` method with new/removed defaults.
		 */
		$button_defaults = array(
			'button_defaults'        => array(
				'title' => esc_html__( 'Button Defaults', self::TEXT_DOMAIN ),
				'type'  => 'title'
			),
			'button_placeholder'     => array(
				'title'       => esc_html__( 'Button Placeholder', self::TEXT_DOMAIN ),
				'type'        => 'textarea',
				'description' => esc_html__( 'Custom HTML to show as a placeholder for bread buttons that have not yet been rendered.', self::TEXT_DOMAIN ),
			),
		);

		$advanced = array(
			'advanced_settings'        => array(
				'title' => esc_html__( 'Advanced Settings (require authorization from your Bread representative)', self::TEXT_DOMAIN ),
				'type'  => 'title'
			),
			'auto_settle' => array(
				'title'   => esc_html__( 'Auto-Settle', self::TEXT_DOMAIN ),
				'label'   => esc_html__( 'Auto-settle transactions from Bread.', self::TEXT_DOMAIN ),
				'type'    => 'checkbox',
				'default' => 'no',
			),
			'healthcare_mode' => array(
				'title'   => esc_html__( 'Enable Healthcare Mode', self::TEXT_DOMAIN ),
				'label'   => esc_html__( 'Enable healthcare mode.', self::TEXT_DOMAIN ),
				'type'    => 'checkbox',
				'default' => 'no',
			),
			'default_show_in_window' => array(
				'title'   => esc_html__( 'Show in New Window', self::TEXT_DOMAIN ),
				'type'    => 'checkbox',
				'label'   => esc_html__( 'Launch Bread checkout in a new window regardless of device or browser.', self::TEXT_DOMAIN ),
				'default' => 'no'
			),
		);

		$this->form_fields = array_merge( $general, $environment, $button_appearance, $button_defaults, $advanced );

	}

	public function is_production() {
		return ( $this->get_option( 'environment' ) === 'production' );
	}

	public function get_environment() {
		return $this->get_option( 'environment' );
	}

	public function get_api_key() {
		return $this->get_option( $this->get_environment() . '_api_key' );
	}

	public function get_api_secret_key() {
		return $this->get_option( $this->get_environment() . '_api_secret_key' );
	}

	public function get_api_url() {
		return $this->is_production() ? 'https://api.getbread.com' : 'https://api-sandbox.getbread.com';
	}

	public function get_resource_url() {
		return ( $this->is_production() )
			? self::DOMAIN_PRODUCTION
			: self::DOMAIN_SANDBOX;
	}

	/**
	 * Check if auto settle has been turned on
	 *
	 * @return    bool
	 */
	public function is_auto_settle() {
		return $this->get_option( 'auto_settle' ) === 'yes';
	}

	/**
	 * Parse the Bread API response.
	 *
	 * Pass every response through this function to automatically check for errors and return either
	 * the original response or an error response.
	 *
	 * @param $response array|\WP_Error
	 *
	 * @return array
	 */
	private function parse_api_response( $response ) {

		if( $response == null ) {
			return $response;
		}

		// curl or other error (WP_Error)
		if ( is_wp_error( $response ) ) {
			return array( 'error' => $response->get_error_message() );
		}

		// api error
		if ( array_key_exists( 'error', $response ) ) {
			return array( 'error' => $response['description'] );
		}

		return $response;

	}

	/**
	 * @param $response array
	 *
	 * @return bool
	 */
	private function has_error( $response ) {
		return ( array_key_exists( 'error', $response ) );
	}

	/**
	 * @param string|array $error The error message of a transaction error response object.
	 *
	 * @return array
	 */
	private function error_result( $error ) {
		return array(
			'result'  => 'failure',
			'message' => is_array( $error ) ? $error['error'] : $error
		);
	}

	/**
	 * Determine whether the Bread payment option is available on the checkout page.
	 *
	 * @return bool
	 */
	public function is_available() {
		if ( 'yes' == $this->get_option( 'button_checkout_checkout' ) ) {
			return parent::is_available();
		} else {
			return false;
		}
	}

	/**
	 * Check API and Secret API key validity by creating a test Bread cart
	 *
	 */
	public function validate_api_keys() {
		$testPayload = array (
			"options" => array (
				"cartName"    => "API Key Validation",
				"customTotal" => 100,
 			)
		);
		$response = $this->create_cart( $testPayload );
		$isValidResponse = isset( $response["url"] );
		if ( $response == null || ! $isValidResponse ) {
			echo '<script type="text/javascript">';
			echo 'alert("Your API and/or Secret key appear to be incorrect. Please ensure the inputted keys match the keys in your merchant portal.")';
			echo '</script>';
		}
		$this->expire_cart( $response["id"] );
	}

	/**
	 * Process a Bread payment
	 *
	 * @param $order_id
	 *
	 * @return array        Success/Failure result with the redirect url or error message.
	 */
	public function process_payment( $order_id ) {

		if ( ! array_key_exists( 'bread_tx_token', $_REQUEST ) ) {
			return $this->error_result( esc_html__( 'Missing Bread transaction token.', self::TEXT_DOMAIN ) );
		}

		$txToken = $_REQUEST['bread_tx_token'];
		$order   = wc_get_order( $order_id );
		$api     = BreadApi::instance();
		$util    = Utilities::instance();

		$tx = $this->parse_api_response( $api->getTransaction( $txToken ) );
		if ( $this->has_error( $tx ) ) {
			return $this->error_result( $tx );
		}

		// Authorize Transaction
		if ( $tx['status'] === 'PENDING' ) {
			$tx = $this->parse_api_response( $api->authorizeTransaction( $txToken, $order_id ) );
			if ( $this->has_error( $tx ) ) {
				return $this->error_result( $tx );
			}
		}

		// Validate Transaction Status / set order status
		if ( $tx['status'] !== 'AUTHORIZED' ) {
			$message = esc_html__( 'Transaction status is not currently AUTHORIZED', self::TEXT_DOMAIN );
			$order->update_status( 'failed', $message );

			return $this->error_result( $message );
		}

		$this->add_order_note( $order, $tx );

		// Validate Transaction Amount is within 2 cents
        if ( abs( $util->priceToCents( $order->get_total() ) - $tx['adjustedTotal'] ) > 2 ) {
			$message = esc_html__( 'Transaction amount does not equal order total.', self::TEXT_DOMAIN );
			$order->update_status( 'failed', $message );

			return $this->error_result( $message );
		}

		// Update billing contact from bread transaction
		$name    = explode( ' ', $tx['billingContact']['fullName'] );
		$contact = array_merge(
			array(
				'lastName'  => array_pop( $name ),
				'firstName' => implode( ' ', $name ),
				'address2'  => '',
				'country'   => $order->get_billing_country()
			),
			$tx['billingContact']
		);

		$order->set_address( array(
			'first_name' => $contact['firstName'],
			'last_name'  => $contact['lastName'],
			'address_1'  => $contact['address'],
			'address_2'  => $contact['address2'],
			'city'       => $contact['city'],
			'state'      => $contact['state'],
			'postcode'   => $contact['zip'],
			'country'    => $contact['country'],
			'email'      => $contact['email'],
			'phone'      => $contact['phone']
		), 'billing' );

		$this->updateOrderTxStatus( $order, $tx );
		$order->add_meta_data( 'bread_tx_id', $tx['breadTransactionId'] );
		$order->save();

		if ( $this->is_auto_settle() ) {
			$this->settle_transaction( $order->get_id() );
			$order->update_status( 'processing' );
		} else {
			$order->update_status( 'on-hold' );
		}

		WC()->cart->empty_cart();

		return array(
			'result'   => 'success',
			'redirect' => $this->get_return_url( $order )
		);

	}

	/**
	 * Process a refund
	 *
	 * @param $order_id
	 * @param null $amount
	 * @param string $reason
	 *
	 * @return bool|\WP_Error
	 */
	public function process_refund( $order_id, $amount = null, $reason = '' ) {

		$order = wc_get_order( $order_id );
		$api   = BreadApi::instance();

		$transactionId = $order->get_meta( 'bread_tx_id' );
		$refundAmount  = $amount ? $this->util->priceToCents( $amount ) : null;

		$tx = $this->parse_api_response( $api->refundTransaction( $transactionId, $refundAmount ) );
		if ( $this->has_error( $tx ) ) {
			return new \WP_Error( 'bread-error-refund', $tx['error'] );
		}

		if ( $order->get_total() === $order->get_total_refunded() ) {
			$order->update_status( 'refunded' );
		}

		$this->updateOrderTxStatus( $order, $tx );
		$this->add_note_refunded( $order, $tx, $amount );

		$order->save();

		return true;

	}

	/**
	 * Settle Bread transaction for the order.
	 *
	 * NOTE: This function is only initiated in response to the order status being changed to 'completed' and should
	 *       therefore not update the order status again unless the transaction fails.
	 *
	 * @param $order_id int
	 *
	 * @return bool|\WP_Error
	 */
	public function settle_transaction( $order_id ) {

		$order = wc_get_order( $order_id );
		$api   = BreadApi::instance();

		$transactionId     = $order->get_meta( 'bread_tx_id' );
		$transactionStatus = $order->get_meta( 'bread_tx_status' );

		// Temporary fix for orders marked as unsettled instead of authorized.
		if ( $transactionStatus === 'unsettled' ) {
			$transactionStatus = 'authorized';
		}

		if ( 'settled' === $transactionStatus ) {
			return true;
		}

		if ( 'authorized' !== $transactionStatus ) {
			if ( $transactionStatus === '' ) {
				$transactionStatus = 'undefined';
			}
			$error = new \WP_Error( 'bread-error-settle', __( "Transaction status is $transactionStatus. Unable to settle.", self::TEXT_DOMAIN ) );
			$order->update_status( 'failed', $error->get_error_message() );

			return $error;
		}

		$tx = $this->parse_api_response( $api->settleTransaction( $transactionId ) );
		if ( $this->has_error( $tx ) ) {
			$error = new \WP_Error( 'bread-error-settle', $tx['error'] );
			$order->update_status( 'failed', $error->get_error_message() );

			return $error;
		}

		$this->add_order_note( $order, $tx );
		$this->updateOrderTxStatus( $order, $tx );
		$order->save();

		return true;

	}

	/**
	 * Cancel/refund Bread transaction for the order.
	 *
	 * @param $order_id int
	 *
	 * @return bool|\WP_Error
	 */
	public function cancel_transaction( $order_id ) {

		$order = wc_get_order( $order_id );
		$api   = BreadApi::instance();

		$transactionId     = $order->get_meta( 'bread_tx_id' );
		$transactionStatus = $order->get_meta( 'bread_tx_status' );

		if ( in_array( $transactionStatus, [ 'pending', 'canceled', 'refunded' ] ) ) {
			return $this->add_note_error( $order, new \WP_Error( 'bread-error-cancel', __( "Transaction status is $transactionStatus. Unable to cancel.", self::TEXT_DOMAIN ) ) );
		}

		if ( 'authorized' === $transactionStatus ) {
			$tx = $this->parse_api_response( $api->cancelTransaction( $transactionId ) );
		} else {
			$tx = $this->parse_api_response( $api->refundTransaction( $transactionId ) );
		}

		if ( $this->has_error( $tx ) ) {
			return $this->add_note_error( $order, new \WP_Error( 'bread-error-cancel', $tx['error'] ) );
		}

		$this->add_order_note( $order, $tx );
		$this->updateOrderTxStatus( $order, $tx );

		$order->save();

		return true;

	}

	/**
	 * Update the order w/ the current Bread transaction status.
	 *
	 * @param $order \WC_Order
	 * @param $tx array Bread API transaction object
	 */
	private function updateOrderTxStatus( $order, $tx ) {
		$order->update_meta_data( 'bread_tx_status', strtolower( $tx['status'] ) );
	}

	/**
	 * Add a Bread status note to the order. Automatically calls the corresponding note function based
	 * on the current transaction status.
	 *
	 * @param $order
	 * @param $tx
	 */
	public function add_order_note( $order, $tx ) {
		call_user_func_array( array( $this, 'add_note_' . strtolower( $tx['status'] ) ), array( $order, $tx ) );
	}

	/**
	 * @param $order \WC_Order
	 * @param $tx array
	 */
	private function add_note_authorized( $order, $tx ) {
		$note = $this->method_title . " Transaction Authorized for " . wc_price( $tx['adjustedTotal'] / 100 ) . ".";
		$note .= " (Transaction ID " . $tx['breadTransactionId'] . ")";

		$order->add_order_note( $note );
	}

	/**
	 * @param $order \WC_Order
	 * @param $tx array
	 */
	private function add_note_settled( $order, $tx ) {
		$order->add_order_note( $this->method_title . " Transaction ID " . $tx['breadTransactionId'] . " Settled." );
	}

	/**
	 * @param $order \WC_Order
	 * @param $tx array
	 */
	private function add_note_refunded( $order, $tx, $amount = null ) {
		$refundAmount = $amount ? ' ' . wc_price( $amount ) . ' ' : '';
		$order->add_order_note( $this->method_title . " Transaction ID " . $tx['breadTransactionId'] . $refundAmount . " Refunded." );
	}

	/**
	 * @param $order \WC_Order
	 * @param $tx array
	 */
	private function add_note_canceled( $order, $tx ) {
		$order->add_order_note( $this->method_title . " Transaction ID " . $tx['breadTransactionId'] . " Canceled." );
	}

	/**
	 * @param $order \WC_Order
	 * @param $error \WP_Error
	 *
	 * @return \WP_Error
	 */
	private function add_note_error( $order, $error ) {
		$order->add_order_note( $error->get_error_message() );

		return $error;
	}

	public function expire_cart( $cart_id ) {
		$api = BreadApi::instance();
		$api->expireBreadCart( $cart_id );
	}

	public function create_cart( $opts ) {
		$api = BreadApi::instance();
		return $this->parse_api_response ( $api->createBreadCart($opts) );
	}
	
}
