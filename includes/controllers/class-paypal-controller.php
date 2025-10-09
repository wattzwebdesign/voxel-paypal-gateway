<?php

namespace VoxelPayPal\Controllers;

use VoxelPayPal\PayPal_Payment_Service;
use VoxelPayPal\Payment_Methods\PayPal_Payment;

if ( ! defined('ABSPATH') ) {
	exit;
}

/**
 * Main PayPal Controller
 * Registers payment service and payment methods with Voxel
 */
class PayPal_Controller extends \Voxel\Controllers\Base_Controller {

	protected function dependencies() {
		new Frontend_Payments_Controller();
		new Frontend_Subscriptions_Controller();
		new Frontend_Webhooks_Controller();
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
		if ( $provider !== 'paypal' ) {
			return;
		}

		$webhook_url = home_url( '/?vx=1&action=paypal.webhooks' );
		?>
		<div class="notice notice-info">
			<p>
				<strong><?php _e( 'PayPal Webhook URL:', 'voxel-paypal-gateway' ); ?></strong><br>
				<code style="font-size: 13px;"><?php echo esc_html( $webhook_url ); ?></code><br>
				<small><?php _e( 'Add this URL to your PayPal webhook configuration in the PayPal Dashboard.', 'voxel-paypal-gateway' ); ?></small>
			</p>
		</div>
		<?php
	}

	/**
	 * Register PayPal payment service
	 */
	protected function register_payment_service( $payment_services ) {
		$payment_services['paypal'] = new PayPal_Payment_Service();
		return $payment_services;
	}

	/**
	 * Register PayPal payment methods
	 */
	protected function register_payment_methods( $payment_methods ) {
		$payment_methods['paypal_payment'] = PayPal_Payment::class;
		$payment_methods['paypal_subscription'] = \VoxelPayPal\Payment_Methods\PayPal_Subscription::class;
		return $payment_methods;
	}
}
