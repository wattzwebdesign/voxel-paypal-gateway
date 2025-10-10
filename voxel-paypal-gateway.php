<?php
/**
 * Plugin Name: Voxel PayPal Gateway
 * Plugin URI: https://your-site.com/voxel-paypal-gateway
 * Description: Seamless PayPal payment gateway integration for Voxel theme. Supports PayPal Checkout, subscriptions, and marketplace payments.
 * Version: 1.0.0
 * Author: Code Wattz
 * Author URI: https://codewattz.com
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
 * Initialize licensing system
 */
function init_licensing() {
	// Load licensing classes
	if ( ! class_exists( '\VoxelPayPal\FluentLicensing' ) ) {
		require_once VOXEL_PAYPAL_PATH . 'updater/FluentLicensing.php';
	}

	// Register licensing
	$licensing = new \VoxelPayPal\FluentLicensing();
	$licensing->register( [
		'version'  => VOXEL_PAYPAL_VERSION,
		'item_id'  => '462',
		'basename' => plugin_basename( __FILE__ ),
		'api_url'  => 'https://codewattz.com/',
	] );

	// Load license settings page
	if ( ! class_exists( '\VoxelPayPal\LicenseSettings' ) ) {
		require_once VOXEL_PAYPAL_PATH . 'updater/LicenseSettings.php';
	}

	// Initialize license settings page
	$license_settings = new \VoxelPayPal\LicenseSettings();
	$license_settings->register( $licensing )
		->setConfig( [
			'menu_title'   => 'Voxel PayPal License',
			'page_title'   => 'Voxel PayPal Gateway License',
			'title'        => 'Voxel PayPal Gateway License',
			'license_key'  => 'License Key',
			'purchase_url' => 'https://codewattz.com/voxel-paypal-gateway/',
			'account_url'  => 'https://codewattz.com/account/',
			'plugin_name'  => 'Voxel PayPal Gateway',
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
		admin_url( 'options-general.php?page=voxel-paypal-gateway-manage-license' ),
		__( 'License', 'voxel-paypal-gateway' )
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
	if ( isset( $_GET['page'] ) && $_GET['page'] === 'voxel-paypal-gateway-manage-license' ) {
		return;
	}

	try {
		$licensing = \VoxelPayPal\FluentLicensing::getInstance();
		$status = $licensing->getStatus();
		$license_url = admin_url( 'options-general.php?page=voxel-paypal-gateway-manage-license' );

		// License not activated
		if ( empty( $status['status'] ) || $status['status'] === 'unregistered' ) {
			?>
			<div class="notice notice-error">
				<p>
					<strong><?php _e( 'Voxel PayPal Gateway:', 'voxel-paypal-gateway' ); ?></strong>
					<?php _e( 'License activation required. The plugin will not function until you activate your license.', 'voxel-paypal-gateway' ); ?>
					<a href="<?php echo esc_url( $license_url ); ?>"><?php _e( 'Activate License', 'voxel-paypal-gateway' ); ?></a>
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
					<strong><?php _e( 'Voxel PayPal Gateway:', 'voxel-paypal-gateway' ); ?></strong>
					<?php _e( 'Your license is invalid. Please check your license key or contact support.', 'voxel-paypal-gateway' ); ?>
					<a href="<?php echo esc_url( $license_url ); ?>"><?php _e( 'Manage License', 'voxel-paypal-gateway' ); ?></a>
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
					<strong><?php _e( 'Voxel PayPal Gateway:', 'voxel-paypal-gateway' ); ?></strong>
					<?php _e( 'Your license has been disabled. Please contact support for assistance.', 'voxel-paypal-gateway' ); ?>
					<a href="https://codewattz.com/account/" target="_blank"><?php _e( 'Contact Support', 'voxel-paypal-gateway' ); ?></a>
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
						<strong><?php _e( 'Voxel PayPal Gateway:', 'voxel-paypal-gateway' ); ?></strong>
						<?php printf( __( 'Your license will expire in %d days. Renew now to continue receiving updates and support.', 'voxel-paypal-gateway' ), ceil( $days_until_expiry ) ); ?>
						<a href="<?php echo esc_url( $license_url ); ?>"><?php _e( 'Manage License', 'voxel-paypal-gateway' ); ?></a>
					</p>
				</div>
				<?php
			}

			// Show error if already expired
			if ( $days_until_expiry < 0 ) {
				?>
				<div class="notice notice-error">
					<p>
						<strong><?php _e( 'Voxel PayPal Gateway:', 'voxel-paypal-gateway' ); ?></strong>
						<?php _e( 'Your license has expired. Please renew to continue using the plugin.', 'voxel-paypal-gateway' ); ?>
						<a href="https://codewattz.com/voxel-paypal-gateway/" target="_blank"><?php _e( 'Renew License', 'voxel-paypal-gateway' ); ?></a>
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
				<strong><?php _e( 'Voxel PayPal Gateway:', 'voxel-paypal-gateway' ); ?></strong>
				<?php _e( 'License system not initialized. Please deactivate and reactivate the plugin.', 'voxel-paypal-gateway' ); ?>
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
	// Check license first - block everything if invalid
	if ( ! is_license_valid() ) {
		return;
	}

	if ( ! check_voxel_theme() ) {
		return;
	}

	// Load composer autoloader if exists (for PayPal SDK)
	if ( file_exists( VOXEL_PAYPAL_PATH . 'vendor/autoload.php' ) ) {
		require_once VOXEL_PAYPAL_PATH . 'vendor/autoload.php';
	}

	// Load core files
	require_once VOXEL_PAYPAL_PATH . 'includes/class-paypal-client.php';
	require_once VOXEL_PAYPAL_PATH . 'includes/class-paypal-connect-client.php';
	require_once VOXEL_PAYPAL_PATH . 'includes/class-paypal-payment-service.php';
	require_once VOXEL_PAYPAL_PATH . 'includes/payment-methods/class-paypal-payment.php';
	require_once VOXEL_PAYPAL_PATH . 'includes/payment-methods/class-paypal-subscription.php';
	require_once VOXEL_PAYPAL_PATH . 'includes/payment-methods/class-paypal-transfer.php';
	require_once VOXEL_PAYPAL_PATH . 'includes/controllers/class-paypal-controller.php';
	require_once VOXEL_PAYPAL_PATH . 'includes/controllers/class-paypal-connect-controller.php';
	require_once VOXEL_PAYPAL_PATH . 'includes/controllers/class-frontend-connect-controller.php';
	require_once VOXEL_PAYPAL_PATH . 'includes/controllers/class-frontend-payments-controller.php';
	require_once VOXEL_PAYPAL_PATH . 'includes/controllers/class-frontend-subscriptions-controller.php';
	require_once VOXEL_PAYPAL_PATH . 'includes/controllers/class-frontend-webhooks-controller.php';

	// Initialize controllers
	new Controllers\PayPal_Controller();
	new Controllers\PayPal_Connect_Controller();
	new Controllers\Frontend_Connect_Controller();
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
			border-radius: 5px;
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

		var attempts = 0;
		var maxAttempts = 100;
		var pollInterval = setInterval(function() {
			attempts++;
			if (addPayPalIcon() || attempts >= maxAttempts) {
				clearInterval(pollInterval);
			}
		}, 100);
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
			'name' => __( 'Vendor Orders', 'voxel-paypal-gateway' ),
			'singular_name' => __( 'Vendor Order', 'voxel-paypal-gateway' ),
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
 * Register Elementor widget
 */
add_action( 'elementor/widgets/register', function( $widgets_manager ) {
	require_once VOXEL_PAYPAL_PATH . 'includes/widgets/class-paypal-connect-widget.php';
	$widgets_manager->register( new \VoxelPayPal\Widgets\PayPal_Connect_Widget() );
} );

/**
 * Register Elementor widget category
 */
add_action( 'elementor/elements/categories_registered', function( $elements_manager ) {
	$elements_manager->add_category(
		'voxel',
		[
			'title' => __( 'Voxel', 'voxel-paypal-gateway' ),
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
		return '<p>' . __( 'Please log in to manage your PayPal settings.', 'voxel-paypal-gateway' ) . '</p>';
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
		<h3><?php _e( 'PayPal Payout Settings', 'voxel-paypal-gateway' ); ?></h3>
		<p><?php _e( 'Set your PayPal email to receive payments from your sales.', 'voxel-paypal-gateway' ); ?></p>

		<form id="paypal-vendor-email-form" method="post">
			<div class="form-group">
				<label for="paypal_vendor_email">
					<?php _e( 'PayPal Email', 'voxel-paypal-gateway' ); ?>
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
					<?php _e( 'If not set, payments will be sent to your account email.', 'voxel-paypal-gateway' ); ?>
				</small>
			</div>

			<button type="submit" class="btn btn-primary">
				<?php _e( 'Save PayPal Email', 'voxel-paypal-gateway' ); ?>
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
							messageDiv.textContent = '<?php _e( 'PayPal email saved successfully!', 'voxel-paypal-gateway' ); ?>';
						} else {
							messageDiv.style.display = 'block';
							messageDiv.style.color = 'red';
							messageDiv.textContent = data.error || '<?php _e( 'Failed to save email.', 'voxel-paypal-gateway' ); ?>';
						}
					} catch (error) {
						messageDiv.style.display = 'block';
						messageDiv.style.color = 'red';
						messageDiv.textContent = '<?php _e( 'An error occurred.', 'voxel-paypal-gateway' ); ?>';
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
