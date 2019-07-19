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

/**
 * ButtonProductSimple Class
 */
class OptionsCategory extends Options {

	protected static $_instance;

	public function getOptions( $config, $form = array() ) {

		$options = array(
			'buttonId'      => $config['opts']['buttonId'],
			'asLowAs'       => $this->util->toBool( $this->getPlugin()->getSetting( 'button_as_low_as_category' ) ),
			'actAsLabel'    => $this->util->toBool( $this->getPlugin()->getSetting( 'button_act_as_label_category' ) ),
			'allowCheckout' => false, // disable checkout from category pages
			'showInWindow'  => $this->util->toBool( $this->getPlugin()->getSetting( 'default_show_in_window' ) ),
		);

		if ( $customCSS = $this->getPlugin()->getSetting( 'button_custom_css' ) ) {
			$options['customCSS'] = $customCSS;
		}

		$items = $this->getItems( $options, $config );
		$enableHealthcareMode = $this->util->toBool( $this->getPlugin()->getSetting( 'healthcare_mode' ) );

		if ( ! $enableHealthcareMode ) {
			if ( ! empty( $items ) ) {
			$options['items'] = $items;
			}
		} else {
			$options['healthcareMode'] = true;
		}

		return array_merge( $options, $this->getContact() );
	}

	/**
	 * @return array
	 */
	public function getItems( &$options, $config ) {
		$product = wc_get_product( $config['productId'] );

		switch ( $product->get_type() ) {
			case 'simple':
				return $this->getItemsSimple( $options, $config );
			case 'grouped':
				return $this->getItemsGrouped( $options, $config );
			case 'variable':
				return $this->getItemsVariable( $options, $config );
			case 'composite':
				return $this->getItemsComposite( $options, $config );
			default:
				return array();
		}
	}

	public function getItemsSimple( &$options, $config ) {
		$enableHealthcareMode = $this->util->toBool( $this->getPlugin()->getSetting( 'healthcare_mode' ) );
		if ( $enableHealthcareMode ) {
			$product = wc_get_product( $config['productId'] );
			$options['customTotal'] = $this->util->priceToCents( $product->get_price());
		}
		return array( $this->getItem( wc_get_product( $config['productId'] ) ) );
	}

	public function getItemsGrouped( &$options, $config ) {
		/*
		 * Borrowed From `WC_Product_Grouped->get_price_html`
		 */

		/** @var \WC_Product_Grouped $product */
		$product  = wc_get_product( $config['productId'] );
		$children = array_filter( array_map( 'wc_get_product', $product->get_children() ), 'wc_products_array_filter_visible_grouped' );

		$prices = array();

		/** @var \WC_Product $child */
		foreach ( $children as $child ) {
			if ( '' !== $child->get_price() ) {
				$prices[] = $this->util->priceToCents( $child->get_price() );
			}
		}

		$options['allowCheckout'] = false;
		$options['asLowAs']       = true;
		$options['customTotal']   = min( $prices );

		return array();

	}

	public function getItemsVariable( &$options, $config ) {
		/*
		 * Borrowed from `WC_Products_Variable->get_price_html`
		 */

		$options['allowCheckout'] = false;
		$options['asLowAs']       = true;

		/** @var \WC_Product_Variable $product */
		$product = wc_get_product( $config['productId'] );

		$prices = $product->get_variation_prices();

		if ( empty( $prices['price'] ) ) {
			$options['customTotal'] = $this->util->priceToCents( $product->get_price() );
		} else {
			$variationPrices = array_map( function ( $price ) {
				return $this->util->priceToCents( $price );
			}, $prices['price'] );

			$options['customTotal'] = min( $variationPrices );
		}

		return array();

	}

	public function getItemsComposite( &$options, $config ) {

		/** @var \WC_Product_Composite $product */
		$product = wc_get_product( $config['productId'] );

		$options['allowCheckout'] = false;
		$options['asLowAs']       = true;
		$options['customTotal']   = $this->util->priceToCents( $product->get_composite_price( 'min' ) );

		return array();

	}

}
