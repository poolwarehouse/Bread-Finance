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
 * ButtonCart Class
 */
class OptionsCheckout extends OptionsCart {

	protected static $_instance;

	public function getOptions( $config, $form = array() ) {

		$options = array(
			'allowCheckout'       => true,
			'asLowAs'             => $this->util->toBool( $this->getPlugin()->getSetting( 'button_as_low_as_checkout' ) ),
			'actAsLabel'          => false,
			'showInWindow'        => $this->util->toBool( $this->getPlugin()->getSetting( 'default_show_in_window' ) ),
			'disableEditShipping' => true,
		);

		$options['customTotal'] = $this->util->priceToCents( WC()->cart->get_total( 'float' ) );

		$shippingResponse = $this->getShipping();
		/* Include shipping cost in tax calculations to ensure Avalara accounts for shipping tax amount */
		$taxResponse      = $this->getTax( $shippingResponse['shippingOptions'][0]['cost'] );
		if ( $this->util->isAvataxEnabled() ) {
			$options['customTotal'] += $taxResponse['tax'];
		}

		$enableHealthcareMode = $this->util->toBool( $this->getPlugin()->getSetting( 'healthcare_mode' ) );
		if ( ! $enableHealthcareMode ) {
			$options['items'] = $this->getItems();
		} else {
			$options['healthcareMode'] = true;
		}

		return array_merge( $options, $this->getContact(), $this->getDiscounts(), $taxResponse, $shippingResponse );

	}

	/**
	 * Get the contact data as submitted on the checkout form.
	 *
	 * @return array
	 */
	public function getContact() {

		$checkout = WC()->checkout();
		$contact  = array();

		$contact['billingContact'] = array(
			'firstName' => $checkout->get_value( 'billing_first_name' ),
			'lastName'  => $checkout->get_value( 'billing_last_name' ),
			'address'   => $checkout->get_value( 'billing_address_1' ),
			'address2'  => $checkout->get_value( 'billing_address_2' ),
			'zip'       => preg_replace( '/[^0-9]/', '', $checkout->get_value( 'billing_postcode' ) ),
			'city'      => $checkout->get_value( 'billing_city' ),
			'state'     => $checkout->get_value( 'billing_state' ),
			'phone'     => substr( preg_replace( '/[^0-9]/', '', $checkout->get_value( 'billing_phone' ) ), - 10 ),
			'email'     => $checkout->get_value( 'billing_email' ),
		);

		if ( $checkout->get_value( 'ship_to_different_address' ) ) {
			$contact['shippingContact'] = array(
				'firstName' => $checkout->get_value( 'shipping_first_name' ),
				'lastName'  => $checkout->get_value( 'shipping_last_name' ),
				'address'   => $checkout->get_value( 'shipping_address_1' ),
				'address2'  => $checkout->get_value( 'shipping_address_2' ),
				'zip'       => preg_replace( '/[^0-9]/', '', $checkout->get_value( 'shipping_postcode' ) ),
				'city'      => $checkout->get_value( 'shipping_city' ),
				'state'     => $checkout->get_value( 'shipping_state' ),
				'phone'     => substr( preg_replace( '/[^0-9]/', '', $checkout->get_value( 'billing_phone' ) ), - 10 ),
			);
		} else {
			$contact['shippingContact'] = $contact['billingContact'];
		}

		return $contact;

	}

	/**
	 * Get the total shipping for this order.
	 *
	 * @return array
	 */
	public function getShipping() {

		if ( ! WC()->cart->needs_shipping() ) {
			return array();
		}

		$chosenMethods = WC()->session->get( 'chosen_shipping_methods' );

		/*
		 * For single-package shipments we can use the chosen shipping method title, otherwise use a generic
		 * title.
		 */
		WC()->shipping()->calculate_shipping( WC()->cart->get_shipping_packages() );
		if ( count( $chosenMethods ) === 1 ) {
			$chosenMethod = WC()->shipping()->get_shipping_methods()[ explode( ':', $chosenMethods[0] )[0] ];
			$shipping[]   = array(
				'typeId' => $chosenMethod->id,
				'cost'   => $this->util->priceToCents( WC()->cart->shipping_total ),
				'type'   => $chosenMethod->method_title
			);
		} else {
			$shipping[] = array(
				'typeId' => 0,
				'cost'   => $this->util->priceToCents( WC()->cart->shipping_total ),
				'type'   => esc_html__( 'Shipping', WCGatewayBreadFinance::TEXT_DOMAIN )
			);
		}

		return array( 'shippingOptions' => $shipping );

	}

	private function getTax( $shippingCost ) {
		$taxHelperResponse = $this->util->getTaxHelper( $shippingCost );
		return ( wc_tax_enabled() )
			? array( 'tax' => $taxHelperResponse['tax'] )
			: array();
	}
}
