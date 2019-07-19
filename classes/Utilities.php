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
 * Utilities Class
 */
class Utilities extends Singleton {

	/**
	 * @var object
	 */
	protected static $_instance;

	/**
	 * @var    \Modern\Wordpress\Plugin        Provides access to the plugin instance
	 */
	protected $plugin;

	private $boolvals;

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
	}

	public function init() {
		$this->boolvals = array( 'yes', 'on', 'true', 'checked' );
	}

	/**
	 * Capitalize the first letter and lower-case all remaining letters of a string.
	 *
	 * @param $string
	 *
	 * @return string
	 */
	public function properCase( $string ) {
		return mb_strtoupper( substr( $string, 0, 1 ) ) . mb_strtolower( substr( $string, 1 ) );
	}

	/**
	 * Checks the parameter, usually a string form value, for truthiness (i.e. yes, on, checked).
	 * If the parameter is not a string value, use the native type-coercion function.
	 *
	 * @param $value mixed        The value to check.
	 *
	 * @return bool
	 */
	public function toBool( $value ) {
		return is_string( $value ) ? in_array( strtolower( $value ), $this->boolvals ) : boolval( $value );
	}

	/**
	 * Convert a price value in dollars to cents.
	 *
	 * @param $price
	 *
	 * @return int
	 */
	public function priceToCents( $price ) {
		$price = explode( wc_get_price_decimal_separator(), number_format( $price, 2, '.', '' ) );

		$dollars = intval( $price[0] ) * 100;
		$cents = ( count( $price ) > 1 )
			? intval( str_pad( $price[1], 2, '0' ) )
			: 0;

		return $dollars + $cents;
	}


	/**
	 * Convert a price value in cents to dollars.
	 *
	 * @param $price
	 * @param $quantity
	 *
	 * @return float
	 */
	public function priceToDollars( $price, $quantity = 1 ) {
		return round( $price / 100 * $quantity, 2 );
	}

	/**
	 * Get the current WooCommerce page type. If no page type can be determined, as can be the case when using
	 * shortcode, default to 'Product'.
	 *
	 * NOTE: The return values of this function correspond with the Bread `buttonLocation` option allowed values.
	 *
	 * @return string
	 */
	public function getPageType() {

		if ( is_post_type_archive( 'product' ) || is_product_category() ) {
			return 'category';
		}

		if ( is_product() ) {
			return 'product';
		}

		if ( is_cart() ) {
			return 'cart_summary';
		}

		if ( is_checkout() ) {
			return 'checkout';
		}

		return 'other';

	}

	public function getProductType() {

		if ( is_product() ) {
			global $product, $post;

			if ( is_string( $product ) ) {
				if(!isset($post)){
					$post           = get_page_by_path( $product, OBJECT, 'product' );
				}
				$currentProduct = wc_get_product( $post->ID );
			}
				elseif(is_null($product)){
					$currentProduct = wc_get_product( $post->ID );
			}
			else
				{
				$currentProduct = $product;
			}

			if(is_object($currentProduct)){
			return $currentProduct->get_type();
			}
		}

		return '';

	}

	/**
	 * Check if Avalara tax plugin exists and is enabled
	 *
	 * @return bool
	 */
	public function isAvataxEnabled() {
		return function_exists( 'wc_avatax' ) && wc_avatax()->get_tax_handler()->is_enabled();
	}


	public function getTaxHelper( $shippingCost ) {
		$tax;
		$cart = WC()->cart;
		
		/* For merchants using AvaTax, use Avalara method to calculate tax on virtual cart */	
		if ( $this->isAvataxEnabled() ) {
			$cart->set_shipping_total( $shippingCost ); // At checkout, Avalara needs shipping cost to calculate shipping tax properly
			$avaResponse = wc_avatax()->get_api()->calculate_cart_tax( $cart );
			$tax = $this->priceToCents( $avaResponse->response_data->totalTax );
		} else {
			$tax = $this->priceToCents( $cart->get_taxes_total() );
		}

		return array( 'tax' => $tax );
	}
}
