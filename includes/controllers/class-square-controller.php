<?php

namespace VoxelPayPal\Controllers;

use VoxelPayPal\Square_Payment_Service;
use VoxelPayPal\Payment_Methods\Square_Payment;

if ( ! defined('ABSPATH') ) {
	exit;
}

/**
 * Main Square Controller
 * Registers payment service and payment methods with Voxel
 */
class Square_Controller extends \Voxel\Controllers\Base_Controller {

	protected function dependencies() {
		new Square_Payments_Controller();
		new Square_Subscriptions_Controller();
		new Square_Webhooks_Controller();
	}

	protected function hooks() {
		$this->filter( 'voxel/product-types/payment-services', '@register_payment_service' );
		$this->filter( 'voxel/product-types/payment-methods', '@register_payment_methods' );
		$this->on( 'admin_notices', '@show_webhook_notice' );
	}

	/**
	 * Show webhook URL notice on payments page
	 */
	protected function show_webhook_notice() {
		$screen = get_current_screen();
		if ( ! $screen || $screen->id !== 'voxel_page_voxel-payments' ) {
			return;
		}

		$provider = \Voxel\get( 'payments.provider' );
		if ( $provider !== 'square' ) {
			return;
		}

		$webhook_url = home_url( '/?vx=1&action=square.webhooks' );
		?>
		<div class="notice notice-info">
			<p>
				<strong><?php _e( 'Square Webhook URL:', 'voxel-payment-gateways' ); ?></strong><br>
				<code style="font-size: 13px;"><?php echo esc_html( $webhook_url ); ?></code><br>
				<small><?php _e( 'Add this URL to your Square webhook configuration in the Square Developer Dashboard.', 'voxel-payment-gateways' ); ?></small>
			</p>
		</div>
		<?php
	}

	/**
	 * Register Square payment service
	 */
	protected function register_payment_service( $payment_services ) {
		$payment_services['square'] = new Square_Payment_Service();
		return $payment_services;
	}

	/**
	 * Register Square payment methods
	 */
	protected function register_payment_methods( $payment_methods ) {
		$payment_methods['square_payment'] = Square_Payment::class;
		$payment_methods['square_subscription'] = \VoxelPayPal\Payment_Methods\Square_Subscription::class;
		return $payment_methods;
	}
}
