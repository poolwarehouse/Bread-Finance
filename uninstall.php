<?php
/**
 * Uninstall Script
 *
 * @package  WooCommerce Gateway Bread
 * @author   Miller Media
 * @since    0.1.0
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) or ! WP_UNINSTALL_PLUGIN ) {
	die( 'Access denied.' );
}

if ( file_exists( WP_PLUGINS_DIR . '/modern-framework/plugin.php' ) )
{
	include_once WP_PLUGINS_DIR . '/modern-framework/plugin.php';

	require_once 'vendor/autoload.php';

	/* Get the plugin instance */
	$plugin = \Bread\WooCommerceGateway\Plugin::instance();

	/**
	 * Uninstall it
	 *
	 * If you overload this method in your plugin, make sure to call
	 * parent::uninstall() because the modern wordpress framework performs
	 * automatic clean up operations such as the removal of your custom
	 * database tables.
	 */
	$plugin->uninstall();
}