<?php
/**
 * Plugin HTML Template
 *
 * Created:  December 14, 2017
 *
 * @package  WooCommerce Gateway Bread
 * @author   Miller Media
 * @since    0.1.0
 *
 * Here is an example of how to get the contents of this template while
 * providing the values of the $title and $content variables:
 * ```
 * $content = $plugin->getTemplateContent( 'buttons/product-simple', array( 'title' => 'Some Custom Title', 'content' => 'Some custom content' ) );
 * ```
 *
 * @param    Plugin $this The plugin instance which is loading this template
 *
 * @param    string $title The provided title
 * @param    string $content The provided content
 */

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Access denied.' );
}

printf( '<div id="%s" data-bread-default-size="%s"></div>', $settings['buttonId'], $settings['defaultSize'] );

?>


