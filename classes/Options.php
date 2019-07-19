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

use Modern\Wordpress\Pattern\Singleton;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Access denied.' );
}

/**
 * Button Class
 */
abstract class Options extends Singleton {
	/**
	 * @var    self
	 */
	protected static $_instance;

	/**
	 * @var    \Bread\WooCommerceGateway\Plugin     Provides access to the plugin instance
	 */
	protected $plugin;

	/**
	 * @var     \Bread\WooCommerceGateway\Utilities
	 */
	protected $util;

	/**
	 * @return \Bread\WooCommerceGateway\Plugin;
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
	protected function __construct( \Modern\Wordpress\Plugin $plugin = null ) {
		$this->setPlugin( $plugin ?: \Bread\WooCommerceGateway\Plugin::instance() );
		$this->util = \Bread\WooCommerceGateway\Utilities::instance();
	}

	public function init() {

	}

	public abstract function getOptions( $config, $form = array() );

	/**
	 * Get the billing contact for the current cart session.
	 *
	 * All pages except checkout. See \Bread\WooCommerceGateway\ButtonCart->getContact
	 *
	 * @return array
	 */
	public function getContact() {

		/*
		 * Pre-population of user is disabled via configuration.
		 */
		if ( $this->getPlugin()->getSetting( 'pre_populate' ) === 'no' ) {
			return array();
		}

		/*
		 * User has already pre-qualified. Do not send new contact information from these pages.
		 */
		$qualstate = WC()->session->get( 'bread_qualstate' ) ?: 'NONE';
		if ( in_array( $qualstate, [ 'PREQUALIFIED', 'PARTIALLY_PREQUALIFIED' ] ) ) {
			return array();
		}

		/*
		 * User has not logged in or entered any checkout data.
		 */
		if ( WC()->customer->get_billing_address() === '' ) {
			return array();
		}

		$required = array( 'first_name', 'last_name', 'address_1', 'postcode', 'city', 'state', 'phone', 'email' );

		$customer = WC()->customer;
		foreach ( $required as $field ) {
			if ( "" === call_user_func( array( $customer, 'get_billing_' . $field ) ) ) {
				return array();
			}
		}

		return array(
			'billingContact' => array(
				'firstName' => $customer->get_billing_first_name(),
				'lastName'  => $customer->get_billing_last_name(),
				'address'   => $customer->get_billing_address_1(),
				'address2'  => $customer->get_billing_address_2(),
				'zip'       => preg_replace( '/[^0-9]/', '', $customer->get_billing_postcode() ),
				'city'      => $customer->get_billing_city(),
				'state'     => $customer->get_billing_state(),
				'phone'     => substr( preg_replace( '/[^0-9]/', '', $customer->get_billing_phone() ), - 10 ),
				'email'     => $customer->get_billing_email()
			)
		);

	}


	/**
	 * Gets the Bread `item` properties for a product.
	 *
	 * Variable, grouped and other product types eventually resolve to a simple or variation product
	 * which have a common set of properties we can use to build out the item array.
	 *
	 * @param $product  \WC_Product
	 *
	 * @return array
	 */
	protected function getItem( $product ) {
		$item = array(
			'name'      => wp_strip_all_tags( $product->get_formatted_name() ),
			'price'     => $this->util->priceToCents( $product->get_price() ),
			'sku'       => strval( $product->get_id() ),
			'detailUrl' => $product->get_permalink(),
			'quantity'  => $product->get_min_purchase_quantity()
		);

		return array_merge( $item, $this->getProductImageUrl( $product ) );

	}

	/**
	 * @param $product  \WC_Product
	 *
	 * @return array
	 */
	protected function getProductImageUrl( $product ) {
		if ( $imageId = $product->get_image_id() ) {
			return array( 'imageUrl' => wp_get_attachment_image_src( $imageId )[0] );
		} else {
			return array();
		}
	}

}
