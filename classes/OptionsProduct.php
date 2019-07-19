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
class OptionsProduct extends OptionsCart {

	protected static $_instance;

	public function getOptions( $config, $form = array() ) {

		$options = array(
			'buttonId'      => $config['opts']['buttonId'],
			'asLowAs'       => $this->util->toBool( $this->getPlugin()->getSetting( 'button_as_low_as_product' ) ),
			'actAsLabel'    => $this->util->toBool( $this->getPlugin()->getSetting( 'button_act_as_label_product' ) ),
			'allowCheckout' => $this->util->toBool( $this->getPlugin()->getSetting( 'button_checkout_product' ) ),
			'showInWindow'  => $this->util->toBool( $this->getPlugin()->getSetting( 'default_show_in_window' ) ),
		);

		if ( $customCSS = $this->getPlugin()->getSetting( 'button_custom_css' ) ) {
			$options['customCSS'] = $customCSS;
		}

		/*
		 * This class extends `OptionsCart`, which reads the Bread line items from the active cart session.
		 * When this class is instantiated, the cart session handler should be WC_Session_Handler_Bread, which
		 * does not persist the cart in any way. Using this approach, a product is added to a temporary cart
		 * using native WooCommerce functions. This allows all WC actions & filters to run so we get an accurate
		 * price, tax, & shipping calculation.
		 */
		$enableHealthcareMode = $this->util->toBool( $this->getPlugin()->getSetting( 'healthcare_mode' ) );

		if ( ! $enableHealthcareMode ) {
			$options['items'] = $this->getItems();
		} else {
			$options['healthcareMode'] = true;
			$product = wc_get_product( $config['productId'] );
			$options['customTotal'] = $this->util->priceToCents( $product->get_price());
		}

		return array_merge( $options, $this->getContact() );
	}

}
