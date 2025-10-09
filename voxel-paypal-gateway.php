<?php
/**
 * Plugin Name: Voxel PayPal Gateway
 * Plugin URI: https://your-site.com/voxel-paypal-gateway
 * Description: Seamless PayPal payment gateway integration for Voxel theme. Supports PayPal Checkout, subscriptions, and marketplace payments.
 * Version: 1.0.0
 * Author: Your Company
 * Author URI: https://your-site.com
 * Text Domain: voxel-paypal-gateway
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.1
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

namespace VoxelPayPal;

if ( ! defined('ABSPATH') ) {
	exit;
}

// Plugin constants
define( 'VOXEL_PAYPAL_VERSION', '1.0.0' );
define( 'VOXEL_PAYPAL_FILE', __FILE__ );
define( 'VOXEL_PAYPAL_PATH', plugin_dir_path( __FILE__ ) );
define( 'VOXEL_PAYPAL_URL', plugin_dir_url( __FILE__ ) );

/**
 * Check if Voxel theme is active
 */
function check_voxel_theme() {
	$theme = wp_get_theme();
	$parent_theme = $theme->parent();

	$is_voxel = ( $theme->get_template() === 'voxel' ) ||
	            ( $parent_theme && $parent_theme->get_template() === 'voxel' );

	if ( ! $is_voxel ) {
		add_action( 'admin_notices', function() {
			?>
			<div class="notice notice-error">
				<p>
					<strong><?php _e( 'Voxel PayPal Gateway Error:', 'voxel-paypal-gateway' ); ?></strong>
					<?php _e( 'This plugin requires the Voxel theme to be active.', 'voxel-paypal-gateway' ); ?>
				</p>
			</div>
			<?php
		} );
		return false;
	}

	return true;
}

/**
 * Initialize the plugin
 */
function init_plugin() {
	if ( ! check_voxel_theme() ) {
		return;
	}

	// Load composer autoloader if exists (for PayPal SDK)
	if ( file_exists( VOXEL_PAYPAL_PATH . 'vendor/autoload.php' ) ) {
		require_once VOXEL_PAYPAL_PATH . 'vendor/autoload.php';
	}

	// Load core files
	require_once VOXEL_PAYPAL_PATH . 'includes/class-paypal-client.php';
	require_once VOXEL_PAYPAL_PATH . 'includes/class-paypal-payment-service.php';
	require_once VOXEL_PAYPAL_PATH . 'includes/payment-methods/class-paypal-payment.php';
	require_once VOXEL_PAYPAL_PATH . 'includes/payment-methods/class-paypal-subscription.php';
	require_once VOXEL_PAYPAL_PATH . 'includes/controllers/class-paypal-controller.php';
	require_once VOXEL_PAYPAL_PATH . 'includes/controllers/class-frontend-payments-controller.php';
	require_once VOXEL_PAYPAL_PATH . 'includes/controllers/class-frontend-subscriptions-controller.php';
	require_once VOXEL_PAYPAL_PATH . 'includes/controllers/class-frontend-webhooks-controller.php';

	// Initialize main controller
	new Controllers\PayPal_Controller();
}

// Hook into WordPress
add_action( 'after_setup_theme', __NAMESPACE__ . '\\init_plugin', 20 );

/**
 * Register module with Voxel
 */
add_filter( 'voxel/modules', function( $modules ) {
	$modules[] = VOXEL_PAYPAL_FILE;
	return $modules;
}, 10, 1 );

/**
 * Plugin activation
 */
register_activation_hook( __FILE__, function() {
	if ( ! check_voxel_theme() ) {
		deactivate_plugins( plugin_basename( __FILE__ ) );
		wp_die(
			__( 'This plugin requires the Voxel theme to be active.', 'voxel-paypal-gateway' ),
			__( 'Plugin Activation Error', 'voxel-paypal-gateway' ),
			[ 'back_link' => true ]
		);
	}

	// Set default options
	if ( ! get_option( 'voxel_paypal_version' ) ) {
		update_option( 'voxel_paypal_version', VOXEL_PAYPAL_VERSION );
	}
} );

/**
 * Plugin deactivation
 */
register_deactivation_hook( __FILE__, function() {
	// Cleanup if needed
} );
