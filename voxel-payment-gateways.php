<?php
/**
 * Plugin Name: Voxel Payment Gateways
 * Plugin URI: https://codewattz.com/voxel-payment-gateways/
 * Description: Payment gateway integrations for Voxel theme. Supports PayPal Checkout, subscriptions, and marketplace payments.
 * Version: 2.0.0
 * Author: Code Wattz
 * Author URI: https://codewattz.com
 * Text Domain: voxel-payment-gateways
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
define( 'VOXEL_GATEWAYS_VERSION', '2.0.0' );
define( 'VOXEL_GATEWAYS_FILE', __FILE__ );
define( 'VOXEL_GATEWAYS_PATH', plugin_dir_path( __FILE__ ) );
define( 'VOXEL_GATEWAYS_URL', plugin_dir_url( __FILE__ ) );

/**
 * Initialize licensing system
 */
function init_licensing() {
	// Load licensing classes
	if ( ! class_exists( '\VoxelPayPal\FluentLicensing' ) ) {
		require_once VOXEL_GATEWAYS_PATH . 'updater/FluentLicensing.php';
	}

	// Register licensing
	$licensing = new \VoxelPayPal\FluentLicensing();
	$licensing->register( [
		'version'  => VOXEL_GATEWAYS_VERSION,
		'item_id'  => '462',
		'basename' => plugin_basename( __FILE__ ),
		'api_url'  => 'https://codewattz.com/',
	] );

	// Load license settings page
	if ( ! class_exists( '\VoxelPayPal\LicenseSettings' ) ) {
		require_once VOXEL_GATEWAYS_PATH . 'updater/LicenseSettings.php';
	}

	// Initialize license settings page
	$license_settings = new \VoxelPayPal\LicenseSettings();
	$license_settings->register( $licensing )
		->setConfig( [
			'menu_title'   => 'Voxel Gateways License',
			'page_title'   => 'Voxel Payment Gateways License',
			'title'        => 'Voxel Payment Gateways License',
			'license_key'  => 'License Key',
			'purchase_url' => 'https://codewattz.com/voxel-payment-gateways/',
			'account_url'  => 'https://codewattz.com/account/',
			'plugin_name'  => 'Voxel Payment Gateways',
		] )
		->addPage( [
			'type'        => 'options', // Add under Settings menu
			'parent_slug' => '', // Not needed for options page
		] );
}

// Initialize licensing early
add_action( 'plugins_loaded', __NAMESPACE__ . '\\init_licensing', 1 );

/**
 * Add license link to plugin actions on Plugins page
 */
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), function( $links ) {
	$license_link = sprintf(
		'<a href="%s">%s</a>',
		admin_url( 'options-general.php?page=voxel-payment-gateways-manage-license' ),
		__( 'License', 'voxel-payment-gateways' )
	);

	// Add license link at the beginning
	array_unshift( $links, $license_link );

	return $links;
} );

/**
 * Show admin notices for license status
 */
add_action( 'admin_notices', function() {
	// Don't show on license settings page
	if ( isset( $_GET['page'] ) && $_GET['page'] === 'voxel-payment-gateways-manage-license' ) {
		return;
	}

	try {
		$licensing = \VoxelPayPal\FluentLicensing::getInstance();
		$status = $licensing->getStatus();
		$license_url = admin_url( 'options-general.php?page=voxel-payment-gateways-manage-license' );

		// License not activated
		if ( empty( $status['status'] ) || $status['status'] === 'unregistered' ) {
			?>
			<div class="notice notice-error">
				<p>
					<strong><?php _e( 'Voxel Payment Gateways:', 'voxel-payment-gateways' ); ?></strong>
					<?php _e( 'License activation required. The plugin will not function until you activate your license.', 'voxel-payment-gateways' ); ?>
					<a href="<?php echo esc_url( $license_url ); ?>"><?php _e( 'Activate License', 'voxel-payment-gateways' ); ?></a>
				</p>
			</div>
			<?php
			return;
		}

		// License invalid or error
		if ( $status['status'] === 'invalid' || $status['status'] === 'error' ) {
			?>
			<div class="notice notice-error">
				<p>
					<strong><?php _e( 'Voxel Payment Gateways:', 'voxel-payment-gateways' ); ?></strong>
					<?php _e( 'Your license is invalid. Please check your license key or contact support.', 'voxel-payment-gateways' ); ?>
					<a href="<?php echo esc_url( $license_url ); ?>"><?php _e( 'Manage License', 'voxel-payment-gateways' ); ?></a>
				</p>
			</div>
			<?php
			return;
		}

		// License disabled
		if ( $status['status'] === 'disabled' ) {
			?>
			<div class="notice notice-error">
				<p>
					<strong><?php _e( 'Voxel Payment Gateways:', 'voxel-payment-gateways' ); ?></strong>
					<?php _e( 'Your license has been disabled. Please contact support for assistance.', 'voxel-payment-gateways' ); ?>
					<a href="https://codewattz.com/account/" target="_blank"><?php _e( 'Contact Support', 'voxel-payment-gateways' ); ?></a>
				</p>
			</div>
			<?php
			return;
		}

		// Check if license is expired (only if valid status but needs remote check)
		if ( $status['status'] === 'valid' && isset( $status['expires'] ) && $status['expires'] !== 'lifetime' ) {
			$expiry_date = strtotime( $status['expires'] );
			$current_date = current_time( 'timestamp' );
			$days_until_expiry = ( $expiry_date - $current_date ) / DAY_IN_SECONDS;

			// Show warning if expiring in 7 days or less
			if ( $days_until_expiry <= 7 && $days_until_expiry >= 0 ) {
				?>
				<div class="notice notice-warning">
					<p>
						<strong><?php _e( 'Voxel Payment Gateways:', 'voxel-payment-gateways' ); ?></strong>
						<?php printf( __( 'Your license will expire in %d days. Renew now to continue receiving updates and support.', 'voxel-payment-gateways' ), ceil( $days_until_expiry ) ); ?>
						<a href="<?php echo esc_url( $license_url ); ?>"><?php _e( 'Manage License', 'voxel-payment-gateways' ); ?></a>
					</p>
				</div>
				<?php
			}

			// Show error if already expired
			if ( $days_until_expiry < 0 ) {
				?>
				<div class="notice notice-error">
					<p>
						<strong><?php _e( 'Voxel Payment Gateways:', 'voxel-payment-gateways' ); ?></strong>
						<?php _e( 'Your license has expired. Please renew to continue using the plugin.', 'voxel-payment-gateways' ); ?>
						<a href="https://codewattz.com/voxel-payment-gateways/" target="_blank"><?php _e( 'Renew License', 'voxel-payment-gateways' ); ?></a>
					</p>
				</div>
				<?php
			}
		}
	} catch ( \Exception $e ) {
		// Licensing not initialized - show error
		?>
		<div class="notice notice-error">
			<p>
				<strong><?php _e( 'Voxel Payment Gateways:', 'voxel-payment-gateways' ); ?></strong>
				<?php _e( 'License system not initialized. Please deactivate and reactivate the plugin.', 'voxel-payment-gateways' ); ?>
			</p>
		</div>
		<?php
	}
} );

/**
 * Check if license is valid
 *
 * @return bool True if license is valid, false otherwise
 */
function is_license_valid() {
	try {
		$licensing = \VoxelPayPal\FluentLicensing::getInstance();
		$status = $licensing->getStatus();

		// Check if license status is 'valid'
		return isset( $status['status'] ) && $status['status'] === 'valid';
	} catch ( \Exception $e ) {
		// If licensing is not initialized, consider it invalid
		return false;
	}
}

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
					<strong><?php _e( 'Voxel Payment Gateways Error:', 'voxel-payment-gateways' ); ?></strong>
					<?php _e( 'This plugin requires the Voxel theme to be active.', 'voxel-payment-gateways' ); ?>
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
	// Check license first - block everything if invalid
	if ( ! is_license_valid() ) {
		return;
	}

	if ( ! check_voxel_theme() ) {
		return;
	}

	// Load composer autoloader if exists (for PayPal SDK)
	if ( file_exists( VOXEL_GATEWAYS_PATH . 'vendor/autoload.php' ) ) {
		require_once VOXEL_GATEWAYS_PATH . 'vendor/autoload.php';
	}

	// Load PayPal gateway files
	require_once VOXEL_GATEWAYS_PATH . 'includes/class-paypal-client.php';
	require_once VOXEL_GATEWAYS_PATH . 'includes/class-paypal-connect-client.php';
	require_once VOXEL_GATEWAYS_PATH . 'includes/class-paypal-payment-service.php';
	require_once VOXEL_GATEWAYS_PATH . 'includes/payment-methods/class-paypal-payment.php';
	require_once VOXEL_GATEWAYS_PATH . 'includes/payment-methods/class-paypal-subscription.php';
	require_once VOXEL_GATEWAYS_PATH . 'includes/payment-methods/class-paypal-transfer.php';
	require_once VOXEL_GATEWAYS_PATH . 'includes/controllers/class-paypal-controller.php';
	require_once VOXEL_GATEWAYS_PATH . 'includes/controllers/class-paypal-connect-controller.php';
	require_once VOXEL_GATEWAYS_PATH . 'includes/controllers/class-frontend-connect-controller.php';
	require_once VOXEL_GATEWAYS_PATH . 'includes/controllers/class-frontend-payments-controller.php';
	require_once VOXEL_GATEWAYS_PATH . 'includes/controllers/class-frontend-subscriptions-controller.php';
	require_once VOXEL_GATEWAYS_PATH . 'includes/controllers/class-frontend-webhooks-controller.php';

	// Load Offline gateway files
	require_once VOXEL_GATEWAYS_PATH . 'includes/class-offline-payment-service.php';
	require_once VOXEL_GATEWAYS_PATH . 'includes/payment-methods/class-offline-payment.php';
	require_once VOXEL_GATEWAYS_PATH . 'includes/controllers/class-offline-controller.php';

	// Load Square gateway files
	require_once VOXEL_GATEWAYS_PATH . 'includes/class-square-client.php';
	require_once VOXEL_GATEWAYS_PATH . 'includes/class-square-payment-service.php';
	require_once VOXEL_GATEWAYS_PATH . 'includes/payment-methods/class-square-payment.php';
	require_once VOXEL_GATEWAYS_PATH . 'includes/payment-methods/class-square-subscription.php';
	require_once VOXEL_GATEWAYS_PATH . 'includes/controllers/class-square-controller.php';
	require_once VOXEL_GATEWAYS_PATH . 'includes/controllers/class-square-payments-controller.php';
	require_once VOXEL_GATEWAYS_PATH . 'includes/controllers/class-square-subscriptions-controller.php';
	require_once VOXEL_GATEWAYS_PATH . 'includes/controllers/class-square-webhooks-controller.php';

	// Load Mercado Pago gateway files
	require_once VOXEL_GATEWAYS_PATH . 'includes/class-mercadopago-client.php';
	require_once VOXEL_GATEWAYS_PATH . 'includes/class-mercadopago-connect-client.php';
	require_once VOXEL_GATEWAYS_PATH . 'includes/class-mercadopago-payment-service.php';
	require_once VOXEL_GATEWAYS_PATH . 'includes/payment-methods/class-mercadopago-payment.php';
	require_once VOXEL_GATEWAYS_PATH . 'includes/payment-methods/class-mercadopago-subscription.php';
	require_once VOXEL_GATEWAYS_PATH . 'includes/controllers/class-mercadopago-controller.php';
	require_once VOXEL_GATEWAYS_PATH . 'includes/controllers/class-mercadopago-connect-controller.php';
	require_once VOXEL_GATEWAYS_PATH . 'includes/controllers/class-mercadopago-payments-controller.php';
	require_once VOXEL_GATEWAYS_PATH . 'includes/controllers/class-mercadopago-webhooks-controller.php';

	// Load Paystack gateway files
	require_once VOXEL_GATEWAYS_PATH . 'includes/class-paystack-client.php';
	require_once VOXEL_GATEWAYS_PATH . 'includes/class-paystack-connect-client.php';
	require_once VOXEL_GATEWAYS_PATH . 'includes/class-paystack-payment-service.php';
	require_once VOXEL_GATEWAYS_PATH . 'includes/payment-methods/class-paystack-payment.php';
	require_once VOXEL_GATEWAYS_PATH . 'includes/payment-methods/class-paystack-subscription.php';
	require_once VOXEL_GATEWAYS_PATH . 'includes/controllers/class-paystack-controller.php';
	require_once VOXEL_GATEWAYS_PATH . 'includes/controllers/class-paystack-connect-controller.php';
	require_once VOXEL_GATEWAYS_PATH . 'includes/controllers/class-paystack-payments-controller.php';
	require_once VOXEL_GATEWAYS_PATH . 'includes/controllers/class-paystack-subscriptions-controller.php';
	require_once VOXEL_GATEWAYS_PATH . 'includes/controllers/class-paystack-webhooks-controller.php';

	// Load Stripe enhancements
	require_once VOXEL_GATEWAYS_PATH . 'includes/controllers/class-stripe-controller.php';

	// Initialize controllers
	new Controllers\PayPal_Controller();
	new Controllers\PayPal_Connect_Controller();
	new Controllers\Frontend_Connect_Controller();
	new Controllers\Offline_Controller();
	new Controllers\Square_Controller();
	new Controllers\MercadoPago_Controller();
	new Controllers\Paystack_Controller();
	new Controllers\Stripe_Controller();
}

// Initialize early but after Voxel
add_action( 'init', __NAMESPACE__ . '\\init_plugin', 0 );

/**
 * Register PayPal payment service directly with lower priority to appear after Stripe/Paddle
 */
add_filter( 'voxel/product-types/payment-services', function( $payment_services ) {
	// Check license - don't register service if invalid
	if ( ! is_license_valid() ) {
		return $payment_services;
	}

	if ( ! class_exists( '\VoxelPayPal\PayPal_Payment_Service' ) ) {
		return $payment_services;
	}

	$payment_services['paypal'] = new \VoxelPayPal\PayPal_Payment_Service();
	return $payment_services;
}, 100, 1 );

/**
 * Register Offline payment service directly with lower priority to appear after other gateways
 */
add_filter( 'voxel/product-types/payment-services', function( $payment_services ) {
	// Check license - don't register service if invalid
	if ( ! is_license_valid() ) {
		return $payment_services;
	}

	if ( ! class_exists( '\VoxelPayPal\Offline_Payment_Service' ) ) {
		return $payment_services;
	}

	$payment_services['offline'] = new \VoxelPayPal\Offline_Payment_Service();
	return $payment_services;
}, 101, 1 );

/**
 * Register Square payment service directly with lower priority to appear after other gateways
 */
add_filter( 'voxel/product-types/payment-services', function( $payment_services ) {
	// Check license - don't register service if invalid
	if ( ! is_license_valid() ) {
		return $payment_services;
	}

	if ( ! class_exists( '\VoxelPayPal\Square_Payment_Service' ) ) {
		return $payment_services;
	}

	$payment_services['square'] = new \VoxelPayPal\Square_Payment_Service();
	return $payment_services;
}, 102, 1 );

/**
 * Register Mercado Pago payment service directly with lower priority to appear after other gateways
 */
add_filter( 'voxel/product-types/payment-services', function( $payment_services ) {
	// Check license - don't register service if invalid
	if ( ! is_license_valid() ) {
		return $payment_services;
	}

	if ( ! class_exists( '\VoxelPayPal\MercadoPago_Payment_Service' ) ) {
		return $payment_services;
	}

	$payment_services['mercadopago'] = new \VoxelPayPal\MercadoPago_Payment_Service();
	return $payment_services;
}, 103, 1 );

/**
 * Register Paystack payment service directly with lower priority to appear after other gateways
 */
add_filter( 'voxel/product-types/payment-services', function( $payment_services ) {
	// Check license - don't register service if invalid
	if ( ! is_license_valid() ) {
		return $payment_services;
	}

	if ( ! class_exists( '\VoxelPayPal\Paystack_Payment_Service' ) ) {
		return $payment_services;
	}

	$payment_services['paystack'] = new \VoxelPayPal\Paystack_Payment_Service();
	return $payment_services;
}, 104, 1 );

/**
 * Inject PayPal icon and styles into payments screen
 */
add_action( 'admin_head', function() {
	// Use URL parameter check instead of screen ID
	if ( ! isset( $_GET['page'] ) || $_GET['page'] !== 'voxel-payments' ) {
		return;
	}
	?>
	<style>
		.paypal-panel {
			background: #0070ba !important;
			width: 50px;
			height: 50px;
			border-radius: 10px;
			display: flex;
			align-items: center;
			justify-content: center;
			flex-shrink: 0;
		}
		.paypal-panel svg {
			width: 28px !important;
			height: 28px !important;
		}
		.paypal-panel svg path {
			fill: #fff !important;
		}
		.paypal-panel svg g path {
			fill: #fff !important;
		}
		.vx-panel:not(.active).provider-paypal .paypal-panel {
			filter: grayscale(1) !important;
			opacity: 0.6 !important;
		}
		.vx-panel.provider-paypal.active {
			background: linear-gradient(45deg, rgba(0, 112, 186, .31) -20%, transparent 70%) !important;
			border-color: rgba(0, 112, 186, .537254902) !important;
		}

		/* Offline Payment Gateway Styles */
		.offline-panel {
			background: #2e7d32 !important;
			width: 50px;
			height: 50px;
			border-radius: 10px;
			display: flex;
			align-items: center;
			justify-content: center;
			flex-shrink: 0;
		}
		.offline-panel svg {
			width: 28px !important;
			height: 28px !important;
		}
		.offline-panel svg path {
			fill: #fff !important;
		}
		.vx-panel:not(.active).provider-offline .offline-panel {
			filter: grayscale(1) !important;
			opacity: 0.6 !important;
		}
		.vx-panel.provider-offline.active {
			background: linear-gradient(45deg, rgba(46, 125, 50, .31) -20%, transparent 70%) !important;
			border-color: rgba(46, 125, 50, .54) !important;
		}

		/* Square Payment Gateway Styles */
		.square-panel {
			background: #ffffff !important;
			width: 50px;
			height: 50px;
			border-radius: 10px;
			display: flex;
			align-items: center;
			justify-content: center;
			flex-shrink: 0;
			border: 1px solid #e0e0e0;
		}
		.square-panel svg {
			width: 32px !important;
			height: 32px !important;
		}
		.square-panel svg path {
			fill: #000 !important;
		}
		.vx-panel:not(.active).provider-square .square-panel {
			filter: grayscale(1) !important;
			opacity: 0.6 !important;
		}
		.vx-panel.provider-square.active {
			background: linear-gradient(45deg, rgba(255, 255, 255, .31) -20%, transparent 70%) !important;
			border-color: rgba(0, 0, 0, .15) !important;
		}

		/* Mercado Pago Payment Gateway Styles */
		.mercadopago-panel {
			background: #00bcff !important;
			width: 50px;
			height: 50px;
			border-radius: 10px;
			display: flex;
			align-items: center;
			justify-content: center;
			flex-shrink: 0;
			overflow: hidden;
		}
		.mercadopago-panel svg {
			width: 40px !important;
			height: 40px !important;
		}
		.vx-panel:not(.active).provider-mercadopago .mercadopago-panel {
			filter: grayscale(1) !important;
			opacity: 0.6 !important;
		}
		.vx-panel.provider-mercadopago.active {
			background: linear-gradient(45deg, rgba(0, 188, 255, .25) -20%, transparent 70%) !important;
			border-color: rgba(0, 188, 255, .54) !important;
		}

		/* Paystack Payment Gateway Styles */
		.paystack-panel {
			background: #58c0f2 !important;
			width: 50px;
			height: 50px;
			border-radius: 10px;
			display: flex;
			align-items: center;
			justify-content: center;
			flex-shrink: 0;
			overflow: hidden;
		}
		.paystack-panel svg {
			width: 32px !important;
			height: 32px !important;
		}
		.paystack-panel svg path {
			fill: #fff !important;
		}
		.vx-panel:not(.active).provider-paystack .paystack-panel {
			filter: grayscale(1) !important;
			opacity: 0.6 !important;
		}
		.vx-panel.provider-paystack.active {
			background: linear-gradient(45deg, rgba(88, 192, 242, .25) -20%, transparent 70%) !important;
			border-color: rgba(88, 192, 242, .54) !important;
		}
	</style>
	<?php
} );

add_action( 'admin_footer', function() {
	// Only run on Voxel payments page - check URL parameter instead
	if ( ! isset( $_GET['page'] ) || $_GET['page'] !== 'voxel-payments' ) {
		return;
	}
	?>
	<script>
	(function() {
		function addPayPalIcon() {
			const paypalPanels = document.querySelectorAll('.vx-panel.provider-paypal');

			if (!paypalPanels.length) {
				return false;
			}

			var success = false;
			paypalPanels.forEach(function(panel) {
				if (panel.querySelector('.panel-image')) {
					success = true;
					return;
				}

				const iconDiv = document.createElement('div');
				iconDiv.className = 'panel-image paypal-panel';
				iconDiv.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="7.056000232696533 3 37.35095977783203 45"><g clip-path="url(#a)"><path fill="#002991" d="M38.914 13.35c0 5.574-5.144 12.15-12.927 12.15H18.49l-.368 2.322L16.373 39H7.056l5.605-36h15.095c5.083 0 9.082 2.833 10.555 6.77a9.687 9.687 0 0 1 .603 3.58z"></path><path fill="#60CDFF" d="M44.284 23.7A12.894 12.894 0 0 1 31.53 34.5h-5.206L24.157 48H14.89l1.483-9 1.75-11.178.367-2.322h7.497c7.773 0 12.927-6.576 12.927-12.15 3.825 1.974 6.055 5.963 5.37 10.35z"></path><path fill="#008CFF" d="M38.914 13.35C37.31 12.511 35.365 12 33.248 12h-12.64L18.49 25.5h7.497c7.773 0 12.927-6.576 12.927-12.15z"></path></g></svg>';

				const panelInfo = panel.querySelector('.panel-info');
				if (panelInfo) {
					panel.insertBefore(iconDiv, panelInfo);
					success = true;
				}
			});

			return success;
		}

		function addOfflineIcon() {
			const offlinePanels = document.querySelectorAll('.vx-panel.provider-offline');

			if (!offlinePanels.length) {
				return false;
			}

			var success = false;
			offlinePanels.forEach(function(panel) {
				if (panel.querySelector('.panel-image')) {
					success = true;
					return;
				}

				const iconDiv = document.createElement('div');
				iconDiv.className = 'panel-image offline-panel';
				iconDiv.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 469.341 469.341"><g><g><g><path d="M437.337,384.007H362.67c-47.052,0-85.333-38.281-85.333-85.333c0-47.052,38.281-85.333,85.333-85.333h74.667c5.896,0,10.667-4.771,10.667-10.667v-32c0-22.368-17.35-40.559-39.271-42.323l-61.26-107c-5.677-9.896-14.844-16.969-25.813-19.906c-10.917-2.917-22.333-1.385-32.104,4.302L79.553,128.007H42.67c-23.531,0-42.667,19.135-42.667,42.667v256c0,23.531,19.135,42.667,42.667,42.667h362.667c23.531,0,42.667-19.135,42.667-42.667v-32C448.004,388.778,443.233,384.007,437.337,384.007z M360.702,87.411l23.242,40.596h-92.971L360.702,87.411z M121.953,128.007L300.295,24.184c4.823-2.823,10.458-3.573,15.844-2.135c5.448,1.458,9.99,4.979,12.813,9.906l0.022,0.039l-164.91,96.013H121.953z"/><path d="M437.337,234.674H362.67c-35.292,0-64,28.708-64,64c0,35.292,28.708,64,64,64h74.667c17.646,0,32-14.354,32-32v-64C469.337,249.028,454.983,234.674,437.337,234.674z M362.67,320.007c-11.76,0-21.333-9.573-21.333-21.333c0-11.76,9.573-21.333,21.333-21.333c11.76,0,21.333,9.573,21.333,21.333C384.004,310.434,374.431,320.007,362.67,320.007z"/></g></g></g></svg>';

				const panelInfo = panel.querySelector('.panel-info');
				if (panelInfo) {
					panel.insertBefore(iconDiv, panelInfo);
					success = true;
				}
			});

			return success;
		}

		function addSquareIcon() {
			const squarePanels = document.querySelectorAll('.vx-panel.provider-square');

			if (!squarePanels.length) {
				return false;
			}

			var success = false;
			squarePanels.forEach(function(panel) {
				if (panel.querySelector('.panel-image')) {
					success = true;
					return;
				}

				const iconDiv = document.createElement('div');
				iconDiv.className = 'panel-image square-panel';
				iconDiv.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 501.42 501.42"><path fill="#000" d="M501.42,83.79v333.84c0,46.27-37.5,83.79-83.79,83.79H83.79c-46.28,0-83.79-37.5-83.79-83.79V83.79C0,37.51,37.52,0,83.79,0h333.84c46.29,0,83.79,37.5,83.79,83.79h0ZM410.22,117.64c0-14.61-11.85-26.45-26.45-26.45H117.62c-14.61,0-26.45,11.84-26.45,26.45v266.19c0,14.61,11.84,26.45,26.45,26.45h266.17c14.61,0,26.45-11.85,26.45-26.45V117.64h-.02ZM182.31,197.59c0-8.43,6.79-15.26,15.17-15.26h106.4c8.39,0,15.17,6.84,15.17,15.26v106.24c0,8.43-6.75,15.26-15.17,15.26h-106.4c-8.39,0-15.17-6.84-15.17-15.26v-106.24Z"/></svg>';

				const panelInfo = panel.querySelector('.panel-info');
				if (panelInfo) {
					panel.insertBefore(iconDiv, panelInfo);
					success = true;
				}
			});

			return success;
		}

		function addMercadoPagoIcon() {
			const mercadopagoPanels = document.querySelectorAll('.vx-panel.provider-mercadopago');

			if (!mercadopagoPanels.length) {
				return false;
			}

			var success = false;
			mercadopagoPanels.forEach(function(panel) {
				if (panel.querySelector('.panel-image')) {
					success = true;
					return;
				}

				const iconDiv = document.createElement('div');
				iconDiv.className = 'panel-image mercadopago-panel';
				// Mercado Pago handshake icon (from official logo)
				iconDiv.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 288 199"><g transform="matrix(1,0,0,1,-206.307844,-135.002267)"><path d="M493.63,227.78C493.2,176.02 428.53,134.35 349.18,135.01C269.84,135.67 205.88,178.41 206.31,230.17C206.32,231.51 206.33,235.21 206.34,235.67C206.8,290.58 263.38,334.59 350.83,333.86C438.81,333.13 494.13,288.19 493.67,233.27L493.62,227.76L493.63,227.78ZM350.73,317.3C274.44,317.94 212.27,278.89 211.86,230.09C211.84,228.12 211.96,226.18 212.15,224.24L213.03,218.27C213.16,217.63 213.37,217.01 213.52,216.37C216.66,217.28 218.94,217.91 220.04,218.16C255.14,226.16 269.09,234.48 273.23,237.45C275.94,235.03 279.5,233.66 283.1,233.66L283.11,233.66C287.32,233.66 291.27,235.46 294.08,238.6C298.49,235.76 303.86,235.16 309.3,237.05C312.86,238.27 315.67,240.54 317.47,243.64C321.1,242.48 325.21,242.75 329.32,244.52C337.23,247.92 338.38,255.24 338.22,259.88C347.62,260.08 355.2,267.79 355.19,277.23C355.19,279.48 354.74,281.74 353.88,283.8C356.49,285.11 360.94,286.94 364.88,286.46C368.62,285.99 369.74,284.79 370,284.43C370.03,284.4 370.05,284.36 370.07,284.33L359.48,272.63C357.63,270.89 357.16,269.11 358.27,267.89C358.73,267.37 359.38,267.08 360.09,267.08C361.32,267.08 362.37,267.97 363.14,268.62C368.88,273.41 375.76,280.51 375.83,280.58L376,280.77C376.15,280.95 376.59,281.31 378.05,281.57C378.54,281.66 379.08,281.71 379.64,281.71C380.71,281.71 383.42,281.53 385.4,279.91C385.76,279.6 386.15,279.23 386.52,278.84L386.93,278.31C388.73,276.01 387.02,273.67 386.66,273.22L373.77,258.72C373.77,258.72 373.26,258.24 372.83,257.65C371.43,255.78 372.06,254.55 372.56,253.98C373.04,253.47 373.67,253.21 374.33,253.21C375.48,253.21 376.55,253.97 377.53,254.79C380.98,257.68 385.67,262.29 390.2,266.75L393.05,269.57C393.4,269.8 395.11,270.85 397.49,270.86C399.39,270.86 401.34,270.21 403.28,268.93C405.97,267.18 407.22,265.03 407.1,262.38C406.9,260.06 404.97,258.21 404.95,258.19L387.22,240.35C385.39,238.79 384.85,236.9 385.9,235.6C386.35,235.04 387.1,234.75 387.76,234.73C388.91,234.73 389.92,235.49 390.93,236.34C394.08,238.98 400.78,244.98 410.84,254.16C411.42,254.69 411.78,255.01 411.83,255.06C411.83,255.06 413.67,256.33 416.38,256.34C418.17,256.34 419.97,255.78 421.72,254.67C423.64,253.45 424.75,251.69 424.85,249.73C425.03,246.16 422.53,243.95 422.51,243.93C417.78,239.79 377.32,204.4 367.91,197.32C362.47,193.22 359.45,192.15 356.33,191.75C356,191.7 355.65,191.7 355.28,191.69C354.03,191.69 352.42,191.91 351.1,192.26C346.3,193.57 340,198.05 335.55,201.58C329.76,206.17 324.34,210.48 319.05,211.66C317.39,212.03 315.57,212.26 313.69,212.22C308.7,212.21 303.31,210.83 300.28,208.78C298.59,207.64 297.37,206.28 296.76,204.84C295.19,201.18 297.54,198.24 298.62,197.14L310.79,184C310.88,183.91 310.96,183.83 311.04,183.75C309.29,184.17 307.57,184.64 305.73,185.16C301.31,186.39 296.84,187.64 292.3,187.64L292.28,187.64C290.76,187.64 289.26,187.51 287.8,187.4L286.34,187.29C279.96,186.88 259.68,181.73 241.8,174.54C241.82,174.52 241.84,174.5 241.86,174.49L246.8,170.7C271.93,152.52 308.47,140.91 349.24,140.57C391.35,140.22 429.14,151.97 454.63,170.77L459.53,174.58C459.86,174.85 460.15,175.14 460.47,175.41L458.87,176.15C444.74,182.61 431.55,185.76 418.54,185.76L418.48,185.76C405.98,185.74 393.54,182.74 381.52,176.86C380.15,176.22 367.67,170.59 355.07,170.57C354.75,170.57 354.41,170.57 354.09,170.59C334.01,171.03 325.26,179.77 315.99,189.01L304.01,201.91C303.77,202.22 303.66,202.41 303.62,202.52C305,204.08 308.6,205.17 312.5,205.18C314.17,205.18 315.84,204.99 317.47,204.63C321.13,203.82 326.14,199.84 330.99,196L331.33,195.73C336.71,191.46 342.27,187.05 347.95,185.26C350.45,184.46 352.86,184.06 355.12,184.06L355.15,184.06C357.59,184.06 359.49,184.53 360.86,184.95C363.86,185.84 367.27,187.82 372.25,191.58C380.39,197.69 408.91,222.47 425.49,236.97C434.72,232.9 458.11,223.29 486.89,216.92C486.89,216.92 486.89,216.88 486.91,216.81C486.95,217.02 487.02,217.22 487.06,217.44L487.91,223.39C488.03,224.85 488.11,226.32 488.12,227.8C488.53,276.6 427.02,316.68 350.72,317.32L350.73,317.3Z" style="fill:white;"/><path d="M338.02,266.3C336.44,266.3 334.74,266.88 333.62,267.27C332.85,267.53 332.39,267.7 332.02,267.7L331.78,267.7L331.45,267.54C331.21,267.38 331.09,267.15 331.03,266.88C330.7,266.4 330.8,265.65 331.38,264.23C331.41,264.16 333.16,259.25 331.65,255.01C330.91,253.16 329.53,251.47 327.05,250.4C325.37,249.67 323.74,249.32 322.22,249.31C318.88,249.31 316.74,251.01 315.77,252.04C315.53,252.29 315.2,252.57 314.76,252.57C314.62,252.57 314.04,252.49 313.81,251.78C313.69,251.61 313.6,251.38 313.57,251.05C313.52,250.34 313.37,249.27 312.96,248.12C312.19,246.17 310.64,244.06 307.49,243.09C306.2,242.69 304.92,242.49 303.68,242.49C297.16,242.49 293.62,247.86 293.48,248.09L292.19,250.1L292.14,249.73C292.14,249.73 292.08,249.77 292.07,249.77C291.8,249.7 291.76,247.41 291.76,247.41C291.7,246.9 291.58,246.4 291.43,245.92C290.22,242.48 286.97,240.06 283.23,240.06C278.4,240.06 274.46,243.99 274.45,248.85C274.45,249.95 274.68,251 275.05,251.97C276.39,255.09 279.49,257.27 283.09,257.28C285.35,257.28 287.13,255.9 289.15,254.83C289.7,254.54 290.27,254.5 290.44,254.74C290.6,254.96 290.65,255.21 290.67,255.48C290.78,255.7 290.84,255.95 290.8,256.26C290.6,257.46 290.33,260.05 291.16,262.76C292.05,265.35 293.99,267.99 298.03,269.56C299.05,269.96 300.07,270.15 301.07,270.16C302.88,270.16 304.66,269.54 306.52,268.23C306.97,267.91 307.31,267.76 307.63,267.78C307.85,267.8 308.19,267.87 308.42,268.13C308.6,268.33 308.67,268.57 308.69,268.81C308.83,269.14 308.85,269.5 308.79,269.86C308.69,270.53 308.65,271.56 308.96,272.73C309.58,274.6 311.13,276.73 314.88,278.25C316.17,278.78 317.39,279.04 318.52,279.04C321,279.04 322.7,277.79 324.04,276.55C324.51,276.12 324.93,275.83 325.4,275.83C326.1,275.83 326.38,276.34 326.51,276.85C326.73,277.29 326.77,277.83 326.78,278.12C326.83,279.5 327.16,280.79 327.66,281.99C329.44,285.82 333.33,288.46 337.86,288.47C344.07,288.47 349.13,283.43 349.14,277.23C349.14,275.77 348.84,274.38 348.32,273.1C346.57,269.15 342.62,266.36 338.02,266.3Z" style="fill:white;"/></g></svg>';

				const panelInfo = panel.querySelector('.panel-info');
				if (panelInfo) {
					panel.insertBefore(iconDiv, panelInfo);
					success = true;
				}
			});

			return success;
		}

		function addPaystackIcon() {
			const paystackPanels = document.querySelectorAll('.vx-panel.provider-paystack');

			if (!paystackPanels.length) {
				return false;
			}

			var success = false;
			paystackPanels.forEach(function(panel) {
				if (panel.querySelector('.panel-image')) {
					success = true;
					return;
				}

				const iconDiv = document.createElement('div');
				iconDiv.className = 'panel-image paystack-panel';
				// Paystack logo (horizontal bars)
				iconDiv.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 44.6 44.3"><g><path d="M39.9,0H2.3C1.1,0,0,1.1,0,2.4v4.2C0,7.9,1.1,9,2.3,9h37.6c1.3,0,2.3-1.1,2.4-2.4V2.4C42.3,1.1,41.2,0,39.9,0L39.9,0z M39.9,23.6H2.3c-0.6,0-1.2,0.3-1.7,0.7C0.2,24.7,0,25.3,0,26v4.2c0,1.3,1.1,2.4,2.3,2.4h37.6c1.3,0,2.3-1,2.4-2.4V26C42.3,24.6,41.2,23.6,39.9,23.6L39.9,23.6z M23.5,35.4H2.3c-0.6,0-1.2,0.2-1.6,0.7c-0.4,0.4-0.7,1-0.7,1.7V42c0,1.3,1.1,2.4,2.3,2.4h21.1c1.3,0,2.3-1.1,2.3-2.4v-4.3C25.8,36.4,24.8,35.4,23.5,35.4L23.5,35.4z M42.3,11.8h-40c-0.6,0-1.2,0.2-1.6,0.7c-0.4,0.4-0.7,1-0.7,1.7v4.2c0,1.3,1.1,2.4,2.3,2.4h39.9c1.3,0,2.3-1.1,2.3-2.4v-4.2C44.6,12.9,43.6,11.8,42.3,11.8L42.3,11.8z"/></g></svg>';

				const panelInfo = panel.querySelector('.panel-info');
				if (panelInfo) {
					panel.insertBefore(iconDiv, panelInfo);
					success = true;
				}
			});

			return success;
		}

		function addAllIcons() {
			addPayPalIcon();
			addOfflineIcon();
			addSquareIcon();
			addMercadoPagoIcon();
			addPaystackIcon();
		}

		// Initial polling for icons on page load
		var attempts = 0;
		var maxAttempts = 100;
		var pollInterval = setInterval(function() {
			attempts++;
			var paypalDone = addPayPalIcon();
			var offlineDone = addOfflineIcon();
			var squareDone = addSquareIcon();
			var mercadopagoDone = addMercadoPagoIcon();
			var paystackDone = addPaystackIcon();
			if ((paypalDone && offlineDone && squareDone && mercadopagoDone && paystackDone) || attempts >= maxAttempts) {
				clearInterval(pollInterval);
			}
		}, 100);

		// Watch for DOM changes (Vue/SPA navigation) and re-add icons when panels appear
		var observer = new MutationObserver(function(mutations) {
			mutations.forEach(function(mutation) {
				if (mutation.addedNodes.length) {
					// Check if any provider panels were added without icons
					var panels = document.querySelectorAll('.vx-panel[class*="provider-"]:not(:has(.panel-image))');
					if (panels.length) {
						addAllIcons();
					}
				}
			});
		});

		observer.observe(document.body, {
			childList: true,
			subtree: true
		});
	})();
	</script>
	<?php
}, 100 );

/**
 * Register vendor order post type
 */
add_action( 'init', function() {
	register_post_type( 'voxel_vendor_order', [
		'labels' => [
			'name' => __( 'Vendor Orders', 'voxel-payment-gateways' ),
			'singular_name' => __( 'Vendor Order', 'voxel-payment-gateways' ),
		],
		'public' => false,
		'show_ui' => true,
		'show_in_menu' => false,
		'capability_type' => 'post',
		'hierarchical' => false,
		'supports' => [ 'title' ],
		'rewrite' => false,
		'query_var' => false,
	] );
}, 5 );

/**
 * Register delayed payout cron action
 */
add_action( 'voxel/paypal/process-delayed-payout', function( $order_id ) {
	$order = \Voxel\Product_Types\Orders\Order::find( [ 'id' => $order_id ] );

	if ( ! $order ) {
		return;
	}

	// Process the payout
	$result = \VoxelPayPal\PayPal_Connect_Client::process_order_payout( $order );

	if ( ! $result['success'] ) {
		// Log error
		error_log( sprintf(
			'PayPal: Failed to process delayed payout for order #%d: %s',
			$order_id,
			$result['error'] ?? 'Unknown error'
		) );

		// Store error for admin review
		$order->set_details( 'marketplace.payout_error', $result['error'] ?? 'Unknown error' );
		$order->save();
	}
}, 10, 1 );

/**
 * Register Elementor widgets
 */
add_action( 'elementor/widgets/register', function( $widgets_manager ) {
	// PayPal Connect Widget
	require_once VOXEL_GATEWAYS_PATH . 'includes/widgets/class-paypal-connect-widget.php';
	$widgets_manager->register( new \VoxelPayPal\Widgets\PayPal_Connect_Widget() );

	// Mercado Pago Connect Widget
	require_once VOXEL_GATEWAYS_PATH . 'includes/widgets/class-mercadopago-connect-widget.php';
	$widgets_manager->register( new \VoxelPayPal\Widgets\MercadoPago_Connect_Widget() );

	// Paystack Connect Widget
	require_once VOXEL_GATEWAYS_PATH . 'includes/widgets/class-paystack-connect-widget.php';
	$widgets_manager->register( new \VoxelPayPal\Widgets\Paystack_Connect_Widget() );
} );

/**
 * Register Elementor widget category
 */
add_action( 'elementor/elements/categories_registered', function( $elements_manager ) {
	$elements_manager->add_category(
		'voxel',
		[
			'title' => __( 'Voxel', 'voxel-payment-gateways' ),
			'icon' => 'fa fa-plug',
		]
	);
} );

/**
 * Register shortcode for vendor PayPal email field (fallback for non-Elementor pages)
 */
add_shortcode( 'paypal_vendor_email', function( $atts ) {
	$user_id = get_current_user_id();

	if ( ! $user_id ) {
		return '<p>' . __( 'Please log in to manage your PayPal settings.', 'voxel-payment-gateways' ) . '</p>';
	}

	// Check if marketplace is enabled
	$marketplace_enabled = (bool) \Voxel\get( 'payments.paypal.marketplace.enabled', 0 );

	if ( ! $marketplace_enabled ) {
		return '';
	}

	$current_email = \VoxelPayPal\PayPal_Connect_Client::get_vendor_paypal_email( $user_id );
	$user = wp_get_current_user();

	ob_start();
	?>
	<div class="paypal-vendor-settings-form">
		<h3><?php _e( 'PayPal Payout Settings', 'voxel-payment-gateways' ); ?></h3>
		<p><?php _e( 'Set your PayPal email to receive payments from your sales.', 'voxel-payment-gateways' ); ?></p>

		<form id="paypal-vendor-email-form" method="post">
			<div class="form-group">
				<label for="paypal_vendor_email">
					<?php _e( 'PayPal Email', 'voxel-payment-gateways' ); ?>
				</label>
				<input
					type="email"
					id="paypal_vendor_email"
					name="paypal_vendor_email"
					value="<?php echo esc_attr( $current_email ?? '' ); ?>"
					placeholder="<?php echo esc_attr( $user->user_email ); ?>"
					required
				/>
				<small>
					<?php _e( 'If not set, payments will be sent to your account email.', 'voxel-payment-gateways' ); ?>
				</small>
			</div>

			<button type="submit" class="btn btn-primary">
				<?php _e( 'Save PayPal Email', 'voxel-payment-gateways' ); ?>
			</button>

			<div id="paypal-vendor-message" style="display:none; margin-top: 10px;"></div>
		</form>

		<script>
		(function() {
			const form = document.getElementById('paypal-vendor-email-form');
			const messageDiv = document.getElementById('paypal-vendor-message');

			if (form) {
				form.addEventListener('submit', async function(e) {
					e.preventDefault();

					const email = document.getElementById('paypal_vendor_email').value;

					try {
						const response = await fetch('<?php echo home_url( '/?vx=1&action=paypal.vendor.save_paypal_email' ); ?>', {
							method: 'POST',
							headers: {
								'Content-Type': 'application/json',
							},
							body: JSON.stringify({ email: email })
						});

						const data = await response.json();

						if (data.success) {
							messageDiv.style.display = 'block';
							messageDiv.style.color = 'green';
							messageDiv.textContent = '<?php _e( 'PayPal email saved successfully!', 'voxel-payment-gateways' ); ?>';
						} else {
							messageDiv.style.display = 'block';
							messageDiv.style.color = 'red';
							messageDiv.textContent = data.error || '<?php _e( 'Failed to save email.', 'voxel-payment-gateways' ); ?>';
						}
					} catch (error) {
						messageDiv.style.display = 'block';
						messageDiv.style.color = 'red';
						messageDiv.textContent = '<?php _e( 'An error occurred.', 'voxel-payment-gateways' ); ?>';
					}
				});
			}
		})();
		</script>

		<style>
		.paypal-vendor-settings-form {
			max-width: 500px;
			padding: 20px;
		}
		.paypal-vendor-settings-form .form-group {
			margin-bottom: 20px;
		}
		.paypal-vendor-settings-form label {
			display: block;
			margin-bottom: 5px;
			font-weight: 600;
		}
		.paypal-vendor-settings-form input[type="email"] {
			width: 100%;
			padding: 10px;
			border: 1px solid #ddd;
			border-radius: 4px;
		}
		.paypal-vendor-settings-form small {
			display: block;
			margin-top: 5px;
			color: #666;
		}
		</style>
	</div>
	<?php
	return ob_get_clean();
} );

/**
 * Register shortcode for vendor Mercado Pago connection (fallback for non-Elementor pages)
 */
add_shortcode( 'mercadopago_vendor_connect', function( $atts ) {
	$user_id = get_current_user_id();

	if ( ! $user_id ) {
		return '<p>' . __( 'Please log in to manage your Mercado Pago settings.', 'voxel-payment-gateways' ) . '</p>';
	}

	// Check if marketplace is enabled
	if ( ! \VoxelPayPal\MercadoPago_Connect_Client::is_marketplace_enabled() ) {
		return '';
	}

	$is_connected = \VoxelPayPal\MercadoPago_Connect_Client::is_vendor_connected( $user_id );
	$account_info = null;

	if ( $is_connected ) {
		$account_info = \VoxelPayPal\MercadoPago_Connect_Client::get_vendor_account_info( $user_id );
	}

	$connect_url = add_query_arg( [
		'vx' => 1,
		'action' => 'mercadopago.oauth.connect',
	], home_url( '/' ) );

	$disconnect_url = add_query_arg( [
		'vx' => 1,
		'action' => 'mercadopago.oauth.disconnect',
		'_wpnonce' => wp_create_nonce( 'mercadopago_disconnect_' . $user_id ),
	], home_url( '/' ) );

	ob_start();
	?>
	<div class="mp-vendor-connect-form">
		<h3><?php _e( 'Mercado Pago Account', 'voxel-payment-gateways' ); ?></h3>

		<?php if ( $is_connected ) : ?>
			<div class="mp-connect-status mp-connected">
				<div class="mp-status-icon">
					<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="24" height="24">
						<path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/>
					</svg>
				</div>
				<div class="mp-status-info">
					<span class="mp-status-label"><?php _e( 'Connected', 'voxel-payment-gateways' ); ?></span>
					<?php if ( $account_info && ! empty( $account_info['email'] ) ) : ?>
						<span class="mp-account-email"><?php echo esc_html( $account_info['email'] ); ?></span>
					<?php endif; ?>
				</div>
			</div>

			<a href="<?php echo esc_url( $disconnect_url ); ?>" class="mp-connect-button mp-disconnect" onclick="return confirm('<?php esc_attr_e( 'Are you sure you want to disconnect your Mercado Pago account?', 'voxel-payment-gateways' ); ?>');">
				<?php _e( 'Disconnect', 'voxel-payment-gateways' ); ?>
			</a>
		<?php else : ?>
			<p class="mp-connect-description"><?php _e( 'Connect your Mercado Pago account to receive payments from your sales.', 'voxel-payment-gateways' ); ?></p>

			<a href="<?php echo esc_url( $connect_url ); ?>" class="mp-connect-button">
				<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="20" height="20" style="margin-right: 8px;">
					<path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
				</svg>
				<?php _e( 'Connect Mercado Pago', 'voxel-payment-gateways' ); ?>
			</a>
		<?php endif; ?>
	</div>

	<style>
	.mp-vendor-connect-form {
		max-width: 500px;
		padding: 20px;
		border: 1px solid #e0e0e0;
		border-radius: 8px;
		background: #fff;
	}
	.mp-vendor-connect-form h3 {
		margin: 0 0 10px 0;
		font-size: 18px;
		font-weight: 600;
	}
	.mp-connect-description {
		margin: 0 0 20px 0;
		color: #666;
	}
	.mp-connect-status {
		display: flex;
		align-items: center;
		padding: 15px;
		margin-bottom: 15px;
		border-radius: 6px;
		background: #e8f5e9;
	}
	.mp-connect-status.mp-connected .mp-status-icon {
		color: #4caf50;
		margin-right: 12px;
	}
	.mp-status-info {
		display: flex;
		flex-direction: column;
	}
	.mp-status-label {
		font-weight: 600;
		color: #2e7d32;
	}
	.mp-account-email {
		font-size: 13px;
		color: #666;
		margin-top: 2px;
	}
	.mp-connect-button {
		display: inline-flex;
		align-items: center;
		justify-content: center;
		padding: 12px 24px;
		border: none;
		border-radius: 6px;
		font-size: 14px;
		font-weight: 600;
		text-decoration: none;
		cursor: pointer;
		transition: opacity 0.2s;
		background-color: #009ee3;
		color: #fff;
	}
	.mp-connect-button:hover {
		opacity: 0.9;
	}
	.mp-connect-button.mp-disconnect {
		background: #f5f5f5 !important;
		color: #666 !important;
	}
	.mp-connect-button.mp-disconnect:hover {
		background: #e0e0e0 !important;
	}
	</style>
	<?php
	return ob_get_clean();
} );

/**
 * Register shortcode for vendor Paystack bank connection (fallback for non-Elementor pages)
 */
add_shortcode( 'paystack_vendor_connect', function( $atts ) {
	$user_id = get_current_user_id();

	if ( ! $user_id ) {
		return '<p>' . __( 'Please log in to manage your payout settings.', 'voxel-payment-gateways' ) . '</p>';
	}

	// Check if marketplace is enabled
	if ( ! \VoxelPayPal\Paystack_Connect_Client::is_marketplace_enabled() ) {
		return '';
	}

	$is_connected = \VoxelPayPal\Paystack_Connect_Client::is_vendor_connected( $user_id );
	$bank_info = null;

	if ( $is_connected ) {
		$bank_info = \VoxelPayPal\Paystack_Connect_Client::get_vendor_bank_info( $user_id );
	}

	$connect_nonce = wp_create_nonce( 'paystack_connect_' . $user_id );
	$disconnect_nonce = wp_create_nonce( 'paystack_disconnect_' . $user_id );
	$ajax_url = home_url( '/?vx=1' );

	ob_start();
	?>
	<div class="ps-vendor-connect-form" id="ps-shortcode-widget">
		<h3><?php _e( 'Paystack Payout Account', 'voxel-payment-gateways' ); ?></h3>

		<?php if ( $is_connected && $bank_info ) : ?>
			<div class="ps-connect-status ps-connected">
				<div class="ps-status-icon">
					<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="24" height="24">
						<path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/>
					</svg>
				</div>
				<div class="ps-status-info">
					<span class="ps-status-label"><?php _e( 'Connected', 'voxel-payment-gateways' ); ?></span>
					<?php if ( ! empty( $bank_info['account_name'] ) ) : ?>
						<span class="ps-account-name"><?php echo esc_html( $bank_info['account_name'] ); ?></span>
					<?php endif; ?>
					<?php if ( ! empty( $bank_info['account_number'] ) ) : ?>
						<span class="ps-account-number">****<?php echo esc_html( substr( $bank_info['account_number'], -4 ) ); ?></span>
					<?php endif; ?>
				</div>
			</div>

			<button type="button" class="ps-connect-button ps-disconnect" onclick="paystackShortcodeDisconnect()">
				<?php _e( 'Disconnect', 'voxel-payment-gateways' ); ?>
			</button>

		<?php else : ?>
			<p class="ps-connect-description"><?php _e( 'Connect your bank account to receive payments from your sales.', 'voxel-payment-gateways' ); ?></p>

			<form class="ps-connect-form" id="ps-shortcode-form">
				<input type="hidden" name="_wpnonce" value="<?php echo esc_attr( $connect_nonce ); ?>">

				<div class="ps-form-group">
					<label for="ps-shortcode-country"><?php _e( 'Country', 'voxel-payment-gateways' ); ?></label>
					<select id="ps-shortcode-country" name="country" required>
						<option value="nigeria"><?php _e( 'Nigeria', 'voxel-payment-gateways' ); ?></option>
						<option value="ghana"><?php _e( 'Ghana', 'voxel-payment-gateways' ); ?></option>
						<option value="south-africa"><?php _e( 'South Africa', 'voxel-payment-gateways' ); ?></option>
						<option value="kenya"><?php _e( 'Kenya', 'voxel-payment-gateways' ); ?></option>
					</select>
				</div>

				<div class="ps-form-group">
					<label for="ps-shortcode-bank"><?php _e( 'Bank', 'voxel-payment-gateways' ); ?></label>
					<select id="ps-shortcode-bank" name="bank_code" required disabled>
						<option value=""><?php _e( 'Loading banks...', 'voxel-payment-gateways' ); ?></option>
					</select>
				</div>

				<div class="ps-form-group">
					<label for="ps-shortcode-account"><?php _e( 'Account Number', 'voxel-payment-gateways' ); ?></label>
					<input type="text" id="ps-shortcode-account" name="account_number" required pattern="[0-9]{10,}" placeholder="<?php esc_attr_e( 'Enter your account number', 'voxel-payment-gateways' ); ?>">
				</div>

				<div class="ps-form-group ps-account-preview" id="ps-shortcode-preview" style="display: none;">
					<label><?php _e( 'Account Name', 'voxel-payment-gateways' ); ?></label>
					<div class="ps-account-name-display"></div>
				</div>

				<div class="ps-form-group">
					<label for="ps-shortcode-business"><?php _e( 'Business Name (Optional)', 'voxel-payment-gateways' ); ?></label>
					<input type="text" id="ps-shortcode-business" name="business_name" placeholder="<?php esc_attr_e( 'Your business or display name', 'voxel-payment-gateways' ); ?>">
				</div>

				<div class="ps-message" id="ps-shortcode-message" style="display: none;"></div>

				<button type="submit" class="ps-connect-button" id="ps-shortcode-submit">
					<?php _e( 'Connect Bank Account', 'voxel-payment-gateways' ); ?>
				</button>
			</form>
		<?php endif; ?>
	</div>

	<style>
	.ps-vendor-connect-form {
		max-width: 500px;
		padding: 20px;
		border: 1px solid #e0e0e0;
		border-radius: 8px;
		background: #fff;
	}
	.ps-vendor-connect-form h3 {
		margin: 0 0 10px 0;
		font-size: 18px;
		font-weight: 600;
	}
	.ps-connect-description {
		margin: 0 0 20px 0;
		color: #666;
	}
	.ps-connect-status {
		display: flex;
		align-items: center;
		padding: 15px;
		margin-bottom: 15px;
		border-radius: 6px;
		background: #e8f5e9;
	}
	.ps-connect-status.ps-connected .ps-status-icon {
		color: #4caf50;
		margin-right: 12px;
	}
	.ps-status-info {
		display: flex;
		flex-direction: column;
	}
	.ps-status-label {
		font-weight: 600;
		color: #2e7d32;
	}
	.ps-account-name, .ps-account-number {
		font-size: 13px;
		color: #666;
		margin-top: 2px;
	}
	.ps-connect-form {
		display: flex;
		flex-direction: column;
		gap: 15px;
	}
	.ps-form-group {
		display: flex;
		flex-direction: column;
		gap: 5px;
	}
	.ps-form-group label {
		font-weight: 500;
		font-size: 14px;
		color: #333;
	}
	.ps-form-group input,
	.ps-form-group select {
		padding: 10px 12px;
		border: 1px solid #ddd;
		border-radius: 6px;
		font-size: 14px;
	}
	.ps-form-group input:focus,
	.ps-form-group select:focus {
		outline: none;
		border-color: #58c0f2;
	}
	.ps-account-preview {
		background: #e3f2fd;
		padding: 12px;
		border-radius: 6px;
	}
	.ps-account-name-display {
		font-weight: 600;
		color: #1976d2;
	}
	.ps-message {
		padding: 12px;
		border-radius: 6px;
		font-size: 14px;
	}
	.ps-message.ps-error {
		background: #ffebee;
		color: #c62828;
	}
	.ps-message.ps-success {
		background: #e8f5e9;
		color: #2e7d32;
	}
	.ps-connect-button {
		display: inline-flex;
		align-items: center;
		justify-content: center;
		padding: 12px 24px;
		border: none;
		border-radius: 6px;
		font-size: 14px;
		font-weight: 600;
		text-decoration: none;
		cursor: pointer;
		transition: opacity 0.2s;
		background-color: #58c0f2;
		color: #fff;
	}
	.ps-connect-button:hover {
		opacity: 0.9;
	}
	.ps-connect-button:disabled {
		opacity: 0.6;
		cursor: not-allowed;
	}
	.ps-connect-button.ps-disconnect {
		background: #f5f5f5 !important;
		color: #666 !important;
	}
	.ps-connect-button.ps-disconnect:hover {
		background: #e0e0e0 !important;
	}
	</style>

	<script>
	(function() {
		const ajaxUrl = '<?php echo esc_js( $ajax_url ); ?>';
		const isConnected = <?php echo $is_connected ? 'true' : 'false'; ?>;
		const disconnectNonce = '<?php echo esc_js( $disconnect_nonce ); ?>';

		<?php if ( ! $is_connected ) : ?>
		const form = document.getElementById('ps-shortcode-form');
		const countrySelect = document.getElementById('ps-shortcode-country');
		const bankSelect = document.getElementById('ps-shortcode-bank');
		const accountInput = document.getElementById('ps-shortcode-account');
		const previewDiv = document.getElementById('ps-shortcode-preview');
		const messageDiv = document.getElementById('ps-shortcode-message');
		const submitBtn = document.getElementById('ps-shortcode-submit');

		let resolveTimeout = null;
		let resolvedAccountName = null;

		async function loadBanks(country) {
			bankSelect.disabled = true;
			bankSelect.innerHTML = '<option value=""><?php echo esc_js( __( 'Loading banks...', 'voxel-payment-gateways' ) ); ?></option>';

			try {
				const response = await fetch(ajaxUrl + '&action=paystack.connect.banks&country=' + encodeURIComponent(country));
				const data = await response.json();

				if (data.success && data.banks) {
					bankSelect.innerHTML = '<option value=""><?php echo esc_js( __( 'Select your bank', 'voxel-payment-gateways' ) ); ?></option>';
					data.banks.forEach(bank => {
						const option = document.createElement('option');
						option.value = bank.code;
						option.textContent = bank.name;
						bankSelect.appendChild(option);
					});
					bankSelect.disabled = false;
				}
			} catch (error) {
				console.error('Failed to load banks:', error);
			}
		}

		async function resolveAccount() {
			const accountNumber = accountInput.value.trim();
			const bankCode = bankSelect.value;

			if (accountNumber.length < 10 || !bankCode) {
				previewDiv.style.display = 'none';
				resolvedAccountName = null;
				return;
			}

			previewDiv.style.display = 'block';
			previewDiv.querySelector('.ps-account-name-display').textContent = '<?php echo esc_js( __( 'Verifying...', 'voxel-payment-gateways' ) ); ?>';

			try {
				const formData = new FormData();
				formData.append('account_number', accountNumber);
				formData.append('bank_code', bankCode);

				const response = await fetch(ajaxUrl + '&action=paystack.connect.resolve', {
					method: 'POST',
					body: formData
				});
				const data = await response.json();

				if (data.success && data.account_name) {
					previewDiv.querySelector('.ps-account-name-display').textContent = data.account_name;
					resolvedAccountName = data.account_name;
				} else {
					previewDiv.querySelector('.ps-account-name-display').textContent = '<?php echo esc_js( __( 'Could not verify account', 'voxel-payment-gateways' ) ); ?>';
					resolvedAccountName = null;
				}
			} catch (error) {
				previewDiv.querySelector('.ps-account-name-display').textContent = '<?php echo esc_js( __( 'Verification failed', 'voxel-payment-gateways' ) ); ?>';
				resolvedAccountName = null;
			}
		}

		function showMessage(text, type) {
			messageDiv.textContent = text;
			messageDiv.className = 'ps-message ps-' + type;
			messageDiv.style.display = 'block';
		}

		countrySelect.addEventListener('change', function() {
			loadBanks(this.value);
			previewDiv.style.display = 'none';
			resolvedAccountName = null;
		});

		bankSelect.addEventListener('change', function() {
			if (accountInput.value.length >= 10) {
				resolveAccount();
			}
		});

		accountInput.addEventListener('input', function() {
			clearTimeout(resolveTimeout);
			if (this.value.length >= 10 && bankSelect.value) {
				resolveTimeout = setTimeout(resolveAccount, 500);
			} else {
				previewDiv.style.display = 'none';
				resolvedAccountName = null;
			}
		});

		form.addEventListener('submit', async function(e) {
			e.preventDefault();
			messageDiv.style.display = 'none';

			if (!resolvedAccountName) {
				showMessage('<?php echo esc_js( __( 'Please wait for account verification to complete.', 'voxel-payment-gateways' ) ); ?>', 'error');
				return;
			}

			submitBtn.disabled = true;
			submitBtn.textContent = '<?php echo esc_js( __( 'Connecting...', 'voxel-payment-gateways' ) ); ?>';

			try {
				const formData = new FormData(form);
				const response = await fetch(ajaxUrl + '&action=paystack.connect.submit', {
					method: 'POST',
					body: formData
				});
				const data = await response.json();

				if (data.success) {
					showMessage(data.message || '<?php echo esc_js( __( 'Bank account connected successfully!', 'voxel-payment-gateways' ) ); ?>', 'success');
					setTimeout(() => location.reload(), 1500);
				} else {
					showMessage(data.message || '<?php echo esc_js( __( 'Failed to connect bank account.', 'voxel-payment-gateways' ) ); ?>', 'error');
					submitBtn.disabled = false;
					submitBtn.textContent = '<?php echo esc_js( __( 'Connect Bank Account', 'voxel-payment-gateways' ) ); ?>';
				}
			} catch (error) {
				showMessage('<?php echo esc_js( __( 'An error occurred.', 'voxel-payment-gateways' ) ); ?>', 'error');
				submitBtn.disabled = false;
				submitBtn.textContent = '<?php echo esc_js( __( 'Connect Bank Account', 'voxel-payment-gateways' ) ); ?>';
			}
		});

		loadBanks(countrySelect.value);
		<?php endif; ?>

		window.paystackShortcodeDisconnect = async function() {
			if (!confirm('<?php echo esc_js( __( 'Are you sure you want to disconnect your bank account?', 'voxel-payment-gateways' ) ); ?>')) {
				return;
			}

			try {
				const formData = new FormData();
				formData.append('_wpnonce', disconnectNonce);

				const response = await fetch(ajaxUrl + '&action=paystack.connect.disconnect', {
					method: 'POST',
					body: formData
				});
				const data = await response.json();

				if (data.success) {
					location.reload();
				} else {
					alert(data.message || '<?php echo esc_js( __( 'Failed to disconnect.', 'voxel-payment-gateways' ) ); ?>');
				}
			} catch (error) {
				alert('<?php echo esc_js( __( 'An error occurred.', 'voxel-payment-gateways' ) ); ?>');
			}
		};
	})();
	</script>
	<?php
	return ob_get_clean();
} );

/**
 * Plugin activation
 */
register_activation_hook( __FILE__, function() {
	if ( ! check_voxel_theme() ) {
		deactivate_plugins( plugin_basename( __FILE__ ) );
		wp_die(
			__( 'This plugin requires the Voxel theme to be active.', 'voxel-payment-gateways' ),
			__( 'Plugin Activation Error', 'voxel-payment-gateways' ),
			[ 'back_link' => true ]
		);
	}

	// Set default options
	if ( ! get_option( 'voxel_gateways_version' ) ) {
		update_option( 'voxel_gateways_version', VOXEL_GATEWAYS_VERSION );
	}
} );

/**
 * Plugin deactivation
 */
register_deactivation_hook( __FILE__, function() {
	// Cleanup if needed
} );
