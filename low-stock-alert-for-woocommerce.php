<?php
/**
 * Plugin Name:          Low Stock Alert for WooCommerce
 * Plugin URI:           https://github.com/developer-naz/low-stock-alert-for-woocommerce
 * Description:          One daily or weekly email with every low and out-of-stock product, a dashboard widget, a low stock count in the admin bar and a "Low stock" view in the products list.
 * Version:              1.0.0
 * Author:               Md Nazmul Islam (developer-naz)
 * Author URI:           https://www.linkedin.com/in/developernaz/
 * License:              GPL-2.0-or-later
 * License URI:          https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:          lsa
 * Requires at least:    6.0
 * Requires PHP:         7.4
 * Requires Plugins:     woocommerce
 * WC requires at least: 8.0
 *
 * @package LSA
 */

defined( 'ABSPATH' ) || exit;

define( 'LSA_VERSION', '1.0.0' );
define( 'LSA_FILE', __FILE__ );
define( 'LSA_PATH', plugin_dir_path( __FILE__ ) );

add_action( 'before_woocommerce_init', function () {
	if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', LSA_FILE, true );
	}
} );

require_once LSA_PATH . 'includes/functions.php';

add_action( 'plugins_loaded', function () {
	if ( ! class_exists( 'WooCommerce' ) ) {
		return;
	}

	require_once LSA_PATH . 'includes/digest.php';
	require_once LSA_PATH . 'includes/admin.php';
} );

register_deactivation_hook( __FILE__, function () {
	wp_clear_scheduled_hook( 'lsa_send_digest' );
} );
