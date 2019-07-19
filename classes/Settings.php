<?php
/**
 * Settings Class File
 *
 * @vendor: Bread
 * @package: Bread Finance
 * @author: Bread
 * @link:
 * @since: July 11, 2019
 */
namespace Bread\WooCommerceGateway;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Access denied.' );
}

/**
 * Plugin Settings
 *
 * Settings are managed by the WooCommerce Gateway API in class `WC_Gateway_Bread_Finance`. This class is
 * therefore only acting as a proxy for loading settings in Modern Framework classes.
 */
class Settings extends \Modern\Wordpress\Plugin\Settings
{
	/**
	 * Instance Cache - Required for singleton
	 * @var	self
	 */
	protected static $_instance;

	protected $storageId = 'woocommerce_bread_finance_settings';

}