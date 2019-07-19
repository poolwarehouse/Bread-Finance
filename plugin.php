<?php

/**
 * Plugin Name: Bread Finance
 * Description: Adds the Bread Gateway to your WooCommerce site.
 * Author: Bread
 * Author URI: https://www.getbread.com/
 * Depends: lib-modern-framework
 * Version: 1.0.0
 * Text Domain: bread-finance
 * Domain Path: /i18n/languages/
 *
 * Copyright: (c) 2019, Lon Operations, LLC. (support@getbread.com)
 *
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Access denied.' );
}

/* Load Only Once */
if ( ! class_exists( 'BreadWooCommerceGatewayPlugin' ) ) {
	class BreadWooCommerceGatewayPlugin {
		public static function init() {
			/* Plugin Core */
			$plugin = \Bread\WooCommerceGateway\Plugin::instance();
			$plugin->setPath( rtrim( plugin_dir_path( __FILE__ ), '/' ) );

			/* Plugin Settings */
			$settings = \Bread\WooCommerceGateway\Settings::instance();
			$plugin->addSettings( $settings );

			$ajaxHandlers = \Bread\WooCommerceGateway\AjaxHandlers::instance();

			/* Connect annotated resources to wordpress core */
			$framework = \Modern\Wordpress\Framework::instance()
			                                        ->attach( $plugin )
			                                        ->attach( $settings )
			                                        ->attach( \Bread\WooCommerceGateway\Button::instance() )
			                                        ->attach( \Bread\WooCommerceGateway\ButtonHelper::instance() )
			                                        ->attach( $ajaxHandlers );

			/* Enable Widgets */
			\Bread\WooCommerceGateway\BasicWidget::enableOn( $plugin );
		}

		public static function status() {
			if ( ! class_exists( 'ModernWordpressFramework' ) ) {
				echo '<td colspan="3" class="plugin-update colspanchange">
						<div class="update-message notice inline notice-error notice-alt">
							<p><strong style="color:red">INOPERABLE.</strong> Please activate <a href="' . admin_url( 'plugins.php?page=tgmpa-install-plugins' ) . '"><strong>Modern Framework for Wordpress</strong></a> to enable the operation of this plugin.</p>
						</div>
					  </td>';
			}
		}
	}

	/* Autoload Classes */
	require_once 'vendor/autoload.php';

	/* Bundled Framework */
	if ( file_exists( __DIR__ . '/framework/plugin.php' ) ) {
		include_once 'framework/plugin.php';
	}

	/* Register plugin dependencies */
	include_once 'includes/plugin-dependency-config.php';


	/* Register plugin status notice */
	add_action( 'after_plugin_row_' . plugin_basename( __FILE__ ), array( 'BreadWooCommerceGatewayPlugin', 'status' ) );

	/**
	 * DO NOT REMOVE
	 *
	 * This plugin depends on the modern wordpress framework.
	 * This block ensures that it is loaded before we init.
	 */
	if ( class_exists( 'ModernWordpressFramework' ) ) {
		BreadWooCommerceGatewayPlugin::init();
	} else {
		add_action( 'modern_wordpress_init', array( 'BreadWooCommerceGatewayPlugin', 'init' ) );
	}

}

