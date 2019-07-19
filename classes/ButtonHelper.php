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

use Braintree\Exception;
use Modern\Wordpress\Pattern\Singleton;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Access denied.' );
}

/**
 * ButtonHelper Class
 */
class ButtonHelper extends Singleton {
	/**
	 * @var     \Bread\WooCommerceGateway\ButtonHelper
	 */
	protected static $_instance;

	/**
	 * @var     \Bread\WooCommerceGateway\Plugin        Provides access to the plugin instance
	 */
	protected $plugin;

	/**
	 * @var     \Bread\WooCommerceGateway\Utilities
	 */
	private $util;

	/**
	 * @var     array       Global front-end options applied to all button instances.
	 */
	private $buttonOptionsGlobal;

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
	 * @return    self      Chainable
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

	public function init() {

		// Check if Bread gateway is enabled before doing anything.
		if ( ! $this->getPlugin()->isGatewayEnabled() ) {
			return;
		}

		$this->buttonOptionsGlobal = array();

	}


	/**
	 * Get Bread Options
	 *
	 * Helper method to call the `getBreadOptions` method for the current page-type/source.
	 *
	 * @return array
	 */
	public function getBreadOptions() {

		$pageType = $_REQUEST['source'];

		switch ( $pageType ) {
			case 'category':
				return $this->getBreadOptionsForCategory();
			case 'cart_summary':
				return $this->getBreadOptionsForCart();
			case 'checkout':
				return $this->getBreadOptionsForCheckout();
			case 'product':
			case 'other':
				return $this->getBreadOptionsForProduct();
			default:
				return array();
		}

	}

	/**
	 * Get Bread Options for Category Pages
	 *
	 * @return array    Configuration options for multiple buttons.
	 */
	private function getBreadOptionsForCategory() {

		$buttonClass = \Bread\WooCommerceGateway\OptionsCategory::instance();

		$buttonOptions = array();
		foreach ( $_REQUEST['configs'] as $config ) {
			array_push( $buttonOptions, array_merge( $buttonClass->getOptions( $config ), $this->buttonOptionsGlobal ) );
		}

		return array_filter( $buttonOptions, function ( $item ) {
			return $item !== null;
		} );

	}

	/**
	 * Get Bread Options for Product Pages
	 *
	 * @return array    Configuration options for a single button/product.
	 */
	private function getBreadOptionsForProduct() {

		$config = $_REQUEST['config'];

		$buttonClass = \Bread\WooCommerceGateway\OptionsProduct::instance();

		$buttonOptions = array_merge( $buttonClass->getOptions( $config ), $this->buttonOptionsGlobal );

		return $buttonOptions;

	}

	/**
	 * Get Bread Options for the View Cart Page
	 *
	 * @return array    Configuration options for a single button containing all items currently in the cart.
	 */
	private function getBreadOptionsForCart() {

		$config = $_REQUEST['config'];
		$form   = $_REQUEST['form'];

		$buttonClass = \Bread\WooCommerceGateway\OptionsCart::instance();

		$buttonOptions = array_merge( $buttonClass->getOptions( $config, $form ), $this->buttonOptionsGlobal );

		return $buttonOptions;

	}

	/**
	 * Get Bread Options for Checkout Page
	 *
	 * @return array    Configuration options for all cart items, selected shipping, checkout form data, & tax.
	 */
	private function getBreadOptionsForCheckout() {

		/*
		 * Borrowed from plugins/woocommerce/includes/class-wc-checkout.php->process_checkout
		 */
		$buttonClass = \Bread\WooCommerceGateway\OptionsCheckout::instance();

		$buttonOptions = array_merge( $buttonClass->getOptions( $_REQUEST['form'] ), $this->buttonOptionsGlobal );

		return $buttonOptions;

	}

	/**
	 * Update the shipping contact of the active cart session.
	 *
	 * This works for both the active & temporary carts since we are selectively swapping in
	 * our custom session handler when calculating tax/shipping.
	 *
	 * @param $shippingContact array
	 * @param $billingContact array|null
	 *
	 * @throws \WC_Data_Exception
	 */
	public function updateCartContact( $shippingContact, $billingContact = null ) {

		$customer = WC()->customer;
		$customer->set_shipping_address_1( $shippingContact['address'] );
		$customer->set_shipping_address_2( $shippingContact['address2'] );
		$customer->set_shipping_city( $shippingContact['city'] );
		$customer->set_shipping_state( $shippingContact['state'] );
		$customer->set_shipping_postcode( $shippingContact['zip'] );
		$customer->set_shipping_country( 'US' );

		if ( $billingContact ) {
			$customer->set_billing_address_1( $billingContact['address'] );
			$customer->set_billing_address_2( $billingContact['address2'] );
			$customer->set_billing_city( $billingContact['city'] );
			$customer->set_billing_state( $billingContact['state'] );
			$customer->set_billing_postcode( $billingContact['zip'] );
			$customer->set_billing_country( 'US' );
		}

		$this->updateSelectedShippingOption( $shippingContact);

		WC()->cart->calculate_totals();

	}

        /**
         * Update the chosen shipping option of the active session.
         * @param $shippingContact array
         *
         */
    	public function updateSelectedShippingOption($shippingContact) {
        	if(isset($shippingContact['selectedShippingOption'])) {
            		$chosen = $shippingContact['selectedShippingOption']['typeId'];
            		WC()->session->set('chosen_shipping_methods', array( '0' => $chosen ) );
       	 	}
   	 }

	/*
	 * The following functions are for creating a 'virtual' cart for the purposes of calculating tax and shipping
	 * only. The cart is not persisted in any way and should not affect the items a customer may already have in their
	 * cart.
	 *
	 * NOTE: These functions still rely on WC()->cart and WC()->customer globals. However, since these functions
	 *       should never be called other than via specific AJAX requests, it is assumed that WC()->session
	 *       will be our custom session handler, `WC_Session_Handler_Bread`
	 *
	 * function createBreadCart:    Create a virtual cart from the button options & shipping contact passed in
	 *                              as ajax parameters.
	 *
	 * function getShipping:        Gets the shipping options for the virtual cart.
	 *
	 * function getTax:             Gets the tax amount for the virtual cart.
	 */
	public function createBreadCart( $buttonOpts, $shippingContact, $billingContact = null ) {

		if ( ! ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ) {
			return false;
		}

		if ( ! in_array( $_REQUEST['action'], [ 'bread_calculate_tax', 'bread_calculate_shipping' ] ) ) {
			return false;
		}

		try {
			$cart = WC()->cart;
			$cart->empty_cart( true );

			foreach ( $buttonOpts['items'] as $item ) {
				$cart->add_to_cart( $item['sku'], intval( $item['quantity'] ) );
			}

			$this->updateCartContact( $shippingContact, $billingContact );

		} catch ( \Exception $e ) {
			return new \WP_Error( 'bread-error-cart', __( 'Error creating temporary cart.', $this->getPlugin()->getTextDomain() ) );
		}

		return true;
	}


	public function getShipping() {

		if ( ! WC()->cart->needs_shipping() ) {
			return array( 'success' => true, 'data' => array() );
		}

		$shipping = array();

		/*
		 * For multi-package shipments, we just need the total combined shipping per-method since Bread doesn't
		 * have any concept of a multi-package order.
		 */
		WC()->shipping()->calculate_shipping( WC()->cart->get_shipping_packages() );
		foreach ( WC()->shipping()->get_packages() as $i => $package ) {

			/** @var \WC_Shipping_Rate $method */
			foreach ( $package['rates'] as $method ) {

				if ( array_key_exists( $method->id, $shipping ) ) {
					$shipping[ $method->id ]['cost'] += $this->util->priceToCents( $method->cost );
				} else {
					$shipping[ $method->id ] = array(
						'typeId' => $method->id,
						'cost'   => $this->util->priceToCents( $method->cost ),
						'type'   => $method->get_label()
					);
				}

			}

		}

		return array( 'success' => true, 'data' => array( 'shippingOptions' => array_values( $shipping ) ) );

	}

	public function getTax() {
		// In Avalara, shipping tax is already accounted for at cart and PDP, pass in any parameter
		$taxHelperResponse = $this->util->getTaxHelper( 0 );
		return ( wc_tax_enabled() )
			? array( 'success' => true, 'data' => array( 'tax' => $taxHelperResponse['tax'] ) )
			: array( 'success' => true, 'data' => array() );
	}

}
