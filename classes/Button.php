<?php

namespace Bread\WooCommerceGateway;

use Modern\Wordpress\Pattern\Singleton;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Access denied.' );
}

class Button extends Singleton {
	/**
	 * @var object
	 */
	protected static $_instance;

	/**
	 * @var    \Bread\WooCommerceGateway\Plugin        Provides access to the plugin instance
	 */
	protected $plugin;

	/**
	 * @var     \Bread\WooCommerceGateway\Utilities
	 */
	private $util;

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

	public function init() {
	}

	/**
	 * Add template 'do_action' hooks to render the Bread button in the  default location for each
	 * WooCommerce page type.
	 *
	 * @Wordpress\Action( for="wp" )
	 */
	public function addTemplateHooks() {

		$useCustomSize = $this->getPlugin()->getSetting( 'button_size' ) === 'custom';
		$wcAjax        = defined( 'WC_DOING_AJAX' ) ? $_GET['wc-ajax'] : false;

		// Category Page Hooks
		if ( $this->util->getPageType() === 'category' && $this->getPlugin()->getSetting( 'button_location_category' ) ) {
			/*
			 * The 'Add To Cart' button an category pages is rendered by a single action hook and provides no
			 * before/after option. We simulate that here by putting a before/after modifier with the action
			 * name in our settings and use that to set the rendering priority at 9 or 11 respectively since
			 * the action we are targeting uses a default priority of 10.
			 */
			$categoryHook = explode( ':', $this->getPlugin()->getSetting( 'button_location_category' ) );
			add_action( 'woocommerce_' . $categoryHook[0], function () use ( $useCustomSize ) {
				global $product;
				do_shortcode( '[bread_button productId="' . $product->get_id() . '" usecustomsize="' . $useCustomSize . '"]' );
			}, ( $categoryHook[1] === 'before' ) ? 9 : 11 );
		}

		// Product Page Hooks
		if ( $this->util->getPageType() === 'product' && $this->getPlugin()->getSetting( 'button_location_product' ) ) {
			add_action( 'woocommerce_' . $this->plugin->getSetting( 'button_location_product' ), function () use ( $useCustomSize ) {
				global $product;
				do_shortcode( '[bread_button productId="' . $product->get_id() . '" usecustomsize="' . $useCustomSize . '"]' );
			} );
		}

		// Cart Summary Page Hooks
		if ( $this->util->getPageType() === 'cart_summary' || $wcAjax === 'update_shipping_method' ) {
			if ( $this->plugin->getSetting( 'button_location_cart' ) ) {
				add_action( 'woocommerce_' . $this->plugin->getSetting( 'button_location_cart' ), function () use ( $useCustomSize ) {
					$this->renderBreadButton( array( 'buttonId' => 'bread_checkout_button' ), array(), $useCustomSize );
				} );
			}
		}

		/*
		 * NOTE: for category pages, add `data-bread_token` attribute and add `bread_token` parameter to the
		 * href attribute of the 'Add to Cart' link button. This should cover both ajax-add-to-cart and
		 * conventional add-to-cart actions.
		 */

	}

	/**
	 * Shortcode: Renders the Bread Button or Label
	 *
	 * @Wordpress\Shortcode( name="bread_button" )
	 *
	 * @param $atts array
	 * @param $content
	 */
	public function shortcodeBreadButton( $atts, $content ) {

		// Check if Bread gateway is enabled before doing anything.
		if ( ! $this->getPlugin()->isGatewayEnabled() ) {
			return;
		}

		global $product;
		$buttonProduct = $product ?: wc_get_product( $atts['productid'] );

		// Product doesn't exist
		if ( ! $buttonProduct ) {
			return;
		}

		// Product type not supported
		if ( ! $this->getPlugin()->supportsProduct( $buttonProduct ) ) {
			return;
		}

		$buttonId = ( $buttonProduct ) ? 'bread_checkout_button_' . $buttonProduct->get_id() : 'bread_checkout_button';

		$meta = array(
			'productId'   => $buttonProduct->get_id(),
			'productType' => $buttonProduct->get_type()
		);

		$opts = array_merge(
		// Button ID & Location
			array(
				'buttonId'       => $buttonId,
				'buttonLocation' => $this->util->getPageType(),
			),

			// Shortcode Attribute Overrides
			array_filter( $atts, function ( $key ) use ( $meta ) {
				return ( $key !== 'productid' && $key !== 'usecustomsize' );
			}, ARRAY_FILTER_USE_KEY )
		);

		$useCustomSize = array_key_exists( 'usecustomsize', $atts ) ? $atts['usecustomsize'] : false;

		$this->renderBreadButton( $opts, $meta, $useCustomSize );

	}

	/**
	 * @param $meta
	 * @param $opts
	 * @param bool $customSize
	 */
	public function renderBreadButton( $opts, $meta = array(), $customSize = false ) {
		$dataBindBread         = $meta;
		$dataBindBread['opts'] = $opts;

		if(! apply_filters('bread_button_allowed', true, $opts, $meta)){
			return;
		}

		$dataBindBread['opts'] = apply_filters('tgmpa_button_opts',  $opts, $meta);

		$defaultPlaceholder = $this->getPlugin()->getTemplateContent( 'buttons/button-placeholder', array( 'title' => $this->getPlugin()->getBreadGateway()->get_option( 'title' ) ) );

		$placeholderContent = is_product()
			? $this->getPlugin()->getBreadGateway()->get_option( 'button_placeholder' ) ?: $defaultPlaceholder
			: '';

		printf( '<div id="%s" data-view-model="woocommerce-gateway-bread" class="bread-checkout-button" data-bread-default-size="%s" %s>' . $placeholderContent . '</div>',
			$opts['buttonId'],
			$customSize ? 'false' : 'true',
			"data-bind='bread: " . json_encode( $dataBindBread ) . "'"
		);
	}
}