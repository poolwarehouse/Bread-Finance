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

use Modern\Wordpress\Pattern\Singleton;
use Bread\WooCommerceGateway\BreadApi;

/**
 * AjaxHandlers Class
 */
class AjaxHandlers extends Singleton {
	/**
	 * @var    self
	 */
	protected static $_instance;

	/**
	 * @var     \Bread\WooCommerceGateway\Plugin
	 */
	protected $plugin;

	/**
	 * @var     \Bread\WooCommerceGateway\Utilities
	 */
	protected $util;

	/**
	 * Get plugin
	 *
	 * @return    \Bread\WooCommerceGateway\Plugin
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
		$this->util = \Bread\WooCommerceGateway\Utilities::instance();
	}

	/**
	 * @Wordpress\AjaxHandler( "bread_get_options" )
	 */
	public function getBreadCheckoutOptions() {

		if ( ! $this->getPlugin()->isGatewayEnabled() ) {
			return;
		}

		try {
			$buttonHelper = \Bread\WooCommerceGateway\ButtonHelper::instance();
			$options      = $buttonHelper->getBreadOptions();

			wp_send_json_success( $options );
		} catch ( \Exception $e ) {
			wp_send_json_error( __( "Error getting Bread options.", $this->getPlugin()->getTextDomain() ) );
		}
	}

	/**
	 * @Wordpress\AjaxHandler( "bread_calculate_shipping" )
	 */
	public function getBreadShippingOptions() {

		/** @var ButtonHelper $buttonHelper */
		$buttonHelper = \Bread\WooCommerceGateway\ButtonHelper::instance();

		try {
			if ( $_REQUEST['source'] === 'cart_summary' ) {
				$buttonHelper->updateCartContact( $_REQUEST['shipping_contact'] );
			} else {
				$buttonHelper->createBreadCart( $_REQUEST['button_opts'], $_REQUEST['shipping_contact'] );
			}

			$shippingOptions = $buttonHelper->getShipping();
			wp_send_json( $shippingOptions );

		} catch ( \Exception $e ) {
			wp_send_json_error( __( "Error calculating shipping.", $this->getPlugin()->getTextDomain() ) );
		}

	}

	/**
	 * @Wordpress\AjaxHandler ( "bread_calculate_tax" )
	 */
	public function getBreadSalesTax() {

		/** @var ButtonHelper $buttonHelper */
		$buttonHelper = \Bread\WooCommerceGateway\ButtonHelper::instance();

		try {
			$shippingContact = $_REQUEST['shipping_contact'];
			$billingContact  = ( array_key_exists( 'billing_contact', $_REQUEST ) )
				? $_REQUEST['billing_contact']
				: null;

			if ( $_REQUEST['source'] === 'cart_summary' ) {
				$buttonHelper->updateCartContact( $shippingContact, $billingContact );
			} else {
				$buttonHelper->createBreadCart( $_REQUEST['button_opts'], $shippingContact, $billingContact );
			}

			$tax = $buttonHelper->getTax();
			wp_send_json( $tax );

		} catch ( \Exception $e ) {
			wp_send_json_error( __( "Error calculating sales tax.", $this->getPlugin()->getTextDomain() ) );
		}

	}


	/**
	 * @Wordpress\AjaxHandler( "bread_set_qualstate" )
	 *
	 * Pre-set chosen payment method on successful Bread pre-qualification.
	 */
	public function setBreadQualstate() {

		if ( ! $this->getPlugin()->isGatewayEnabled() ) {
			return;
		} 

		if ( $this->getPlugin()->getSetting( 'default_payment' ) === 'no' ) {
			return;
		}

		switch ( $_REQUEST['customer_data']['state'] ) {
			case 'PREQUALIFIED':
			case 'PARTIALLY_PREQUALIFIED':
				WC()->session->set( 'chosen_payment_method', WCGatewayBreadFinance::WC_BREAD_GATEWAY_ID );
				break;
			default:
				WC()->session->set( 'chosen_payment_method', '' );
		}

		WC()->session->set( 'bread_qualstate', $_REQUEST['customer_data']['state'] );

		wp_send_json_success();
	}


	/**
	 * Process a bread checkout
	 *
	 * @Wordpress\AjaxHandler( action="bread_complete_checkout" )
	 *
	 * @return    void
	 * @throws \WC_Data_Exception
	 */
	public function processBreadCheckout() {

		if ( ! $this->getPlugin()->isGatewayEnabled() ) {
			return;
		}

		$tx_id = $_REQUEST['tx_id'];
		$form  = $_REQUEST['form'];
		if ( ! $tx_id ) {
			wp_send_json( array(
				'success' => false,
				'message' => __( "Invalid Transaction ID", $this->getPlugin()->getTextDomain() )
			) );
		}

		$breadGateway  = $this->getPlugin()->getBreadGateway();
		$breadApi      = BreadApi::instance();
		$transaction   = $breadApi->getTransaction( $tx_id );

		if ( is_wp_error( $transaction ) ) {
			wp_send_json( array(
				'success' => false,
				'message' => $transaction->get_error_message(),
				'url'     => $breadApi->apiUrl
			) );
		}

		if ( ! $transaction['error'] ) {
			if ( ! $transaction['merchantOrderId'] ) {
				$user_email = $transaction['billingContact']['email'];
				$order_user = get_user_by( 'email', $user_email );

				if ( $order_user === false ) {
					$user_password = wp_generate_password();
					$user_id       = wp_create_user( $user_email, $user_password, $user_email );
					if ( is_wp_error( $user_id ) ) {
						wp_send_json( array( 'success' => false, 'message' => $user_id->get_error_message() ) );
					}
					$order_user = get_user_by( 'id', $user_id );
				}

				$billing_names      = explode( ' ', $transaction['billingContact']['fullName'] );
				$billing_last_name  = array_pop( $billing_names );
				$billing_first_name = implode( ' ', $billing_names );

				$shipping_names      = explode( ' ', $transaction['shippingContact']['fullName'] );
				$shipping_last_name  = array_pop( $shipping_names );
				$shipping_first_name = implode( ' ', $shipping_names );

				$order = wc_create_order( array( 'customer_id' => $order_user->ID ) );

				/* Set the payment method details */
				$order->set_payment_method( $breadGateway->id );
				$order->set_payment_method_title( $breadGateway->method_title );
				$order->set_transaction_id( $tx_id );

				/* Set billing address */
				$order->set_address( array(
					'first_name' => $billing_first_name,
					'last_name'  => $billing_last_name,
					'company'    => '',
					'email'      => $transaction['billingContact']['email'],
					'phone'      => $transaction['billingContact']['phone'],
					'address_1'  => $transaction['billingContact']['address'],
					'address_2'  => '',
					'city'       => $transaction['billingContact']['city'],
					'state'      => $transaction['billingContact']['state'],
					'postcode'   => $transaction['billingContact']['zip'],
					'country'    => 'US',
				), 'billing' );

				/* Set shipping address */
				$order->set_address( array(
					'first_name' => $shipping_first_name,
					'last_name'  => $shipping_last_name,
					'company'    => '',
					'email'      => $transaction['shippingContact']['email'],
					'phone'      => $transaction['shippingContact']['phone'],
					'address_1'  => $transaction['shippingContact']['address'],
					'address_2'  => '',
					'city'       => $transaction['shippingContact']['city'],
					'state'      => $transaction['shippingContact']['state'],
					'postcode'   => $transaction['shippingContact']['zip'],
					'country'    => 'US',
				), 'shipping' );

				/* Add products */
				foreach ( $transaction['lineItems'] as $item ) {	
				/**
					* WooCommerce may be overriding line breaks ("\n") and causing loss of formatting.
					* This code modifies the product name so that each line appears as its own div and
					* creates the appearance of line breaks. 
					*/
					$name = $item['product']['name'];
					$name = "<div>" . $name . "</div>";
					$name = str_replace("\n", "</div><div>", $name);

					$product = wc_get_product( $item['sku'] );
					$args    = array(
						'name'     => $name,
						'subtotal' => $this->util->priceToDollars( $item['price'], $item['quantity'] ),
						'total'    => $this->util->priceToDollars( $item['price'], $item['quantity'] ),
					);

					/* Set Variation data for variable products */
					if ( $product->get_type() === 'variation' ) {
						$variation = array();
						foreach ( $form as $input ) {
							if ( preg_match( '/attribute_(.+)/', $input['name'], $matches ) ) {
								$variation[ $matches[1] ] = $input['value'];
							}
						}

						foreach ( $product->get_attributes() as $key => $value ) {
							if ( $value ) {
								$variation[ $key ] = $value;
							}
						}
						$args['variation'] = $variation;
					}

					$order->add_product( $product, $item['quantity'], $args );
				}

				/* Add shipping */
				$shippingItem = new \WC_Order_Item_Shipping();
				$shippingItem->set_method_title( $transaction['shippingMethodName'] );
				$shippingItem->set_method_id( $transaction['shippingMethodCode'] );
				$shippingItem->set_total( $this->util->priceToDollars( $transaction['shippingCost'], 1 ) );
				// $shippingItem->set_total( round( $transaction['shippingCost'] / 100, 2 ) );
				$order->add_item( $shippingItem );
				$order->save();
	
				/* Add discounts */
				foreach ( $transaction['discounts'] as $discount ) {
					$coupon_response = $order->apply_coupon( $discount['description'] );
					if ( is_wp_error( $coupon_response ) ) {
						$message = esc_html__( "Error: " . $coupon_response->get_error_message(), $this->getPlugin()->getTextDomain() );
						$order->update_status( "failed", $message );
						wp_send_json_error( __( $message, $this->getPlugin()->getTextDomain() ) );
					}
				}
				
				/* Add tax */
				/* For merchants using AvaTax, use Avalara method to calculate tax for order */
				/* Tax calculation MUST happen after discounts are added to grab the correct AvaTax amount */
				if ( $this->util->isAvataxEnabled() ) {
					wc_avatax()->get_order_handler()->calculate_order_tax( $order );
				} 
				$order->calculate_totals();

				$breadGateway->add_order_note( $order, $transaction );

				/* Validate calculated totals */
				if ( abs( $this->util->priceToCents( $order->get_total() ) - $transaction['adjustedTotal'] ) > 2 ) {
					$message = esc_html__( "ALERT: Transaction amount does not equal order total.", $this->getPlugin()->getTextDomain() );
					$order->update_status( "failed", $message );
					wp_send_json_error( __( "ALERT: Bread transaction total does not match order total.", $this->getPlugin()->getTextDomain() ) );
				}

				if ( floatval( $order->get_total_tax() ) !== floatval( $transaction['totalTax'] / 100 ) ) {
					$order->add_order_note( "ALERT: Bread tax total does not match order tax total." );
				}

				if ( floatval( $order->get_shipping_total() ) !== floatval( $transaction['shippingCost'] / 100 ) ) {
					$order->add_order_note( "Bread shipping total does not match order shipping total." );
				}

				/* Authorize Bread transaction */
				$transaction = $breadApi->authorizeTransaction( $tx_id );
				if ( $transaction['status'] !== 'AUTHORIZED' ) {
					$order->add_order_note( "Transaction failed to AUTHORIZE." );
					wp_send_json_error( array(
						'message' => __( "Transaction failed to AUTHORIZE.", $this->getPlugin()->getTextDomain() )
					) );
				}

				$order->update_status( 'on-hold' );
				$order->update_meta_data( 'bread_tx_id', $tx_id );
				$order->update_meta_data( 'bread_tx_status', 'authorized' );
				$order->save();

				/* Settle Bread transaction (if auto-settle enabled) */
				if ( $breadGateway->is_auto_settle() ) {
					$breadGateway->settle_transaction( $order->get_id() );
					$order->update_status( 'processing' );
				}

				/* Update Bread transaction with the order id */
				$breadApi->updateTransaction( $tx_id, array( 'merchantOrderId' => (string) $order->get_id() ) );

				/* Clear the cart if requested */
				if ( isset( $_REQUEST['clear_cart'] ) and $_REQUEST['clear_cart'] ) {
					WC()->cart->empty_cart();
				}

				wp_send_json_success( array(
					'transaction' => $order->get_meta_data( 'bread_tx_status' ),
					'order_id'    => $order->get_id(),
					'redirect'    => $order->get_checkout_order_received_url()
				) );
			} else {
				wp_send_json_error( array(
					'message' => __( 'Transaction has already been recorded to order #', $this->getPlugin()->getTextDomain() ) . $transaction['merchantOrderId']
				) );
			}
		} else {
			wp_send_json_error( array(
				'message' => $transaction['description']
			) );
		}

	}
}
