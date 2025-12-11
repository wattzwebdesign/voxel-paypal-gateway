<?php

namespace VoxelPayPal\Controllers;

if ( ! defined('ABSPATH') ) {
	exit;
}

/**
 * Paystack Controller
 * Main controller that initializes all Paystack functionality
 */
class Paystack_Controller extends \Voxel\Controllers\Base_Controller {

	protected function dependencies(): void {
		new Paystack_Connect_Controller();
		new Paystack_Payments_Controller();
		new Paystack_Subscriptions_Controller();
		new Paystack_Webhooks_Controller();
	}

	protected function hooks(): void {
		$this->filter( 'voxel/product-types/payment-methods', '@register_payment_methods' );
		$this->on( 'voxel/backend/screen:payments/paystack', '@render_webhook_notice' );
	}

	/**
	 * Register Paystack payment methods
	 */
	protected function register_payment_methods( array $payment_methods ): array {
		$payment_methods['paystack_payment'] = \VoxelPayPal\Payment_Methods\Paystack_Payment::class;
		$payment_methods['paystack_subscription'] = \VoxelPayPal\Payment_Methods\Paystack_Subscription::class;

		return $payment_methods;
	}

	/**
	 * Render webhook URL notice on admin payments page
	 */
	protected function render_webhook_notice(): void {
		$webhook_url = home_url( '/?vx=1&action=paystack.webhooks' );
		?>
		<div class="ts-group" style="margin-top: 20px;">
			<div class="ts-group-head">
				<h3><?php _e( 'Webhook Configuration', 'voxel-payment-gateways' ); ?></h3>
			</div>
			<div class="x-row">
				<div class="ts-form-group x-col-12">
					<p style="margin-bottom: 10px;">
						<?php _e( 'Configure this webhook URL in your Paystack Dashboard → Settings → API Keys & Webhooks:', 'voxel-payment-gateways' ); ?>
					</p>
					<input type="text" readonly value="<?php echo esc_attr( $webhook_url ); ?>" style="width: 100%; font-family: monospace;" onclick="this.select();">
				</div>
			</div>
		</div>
		<?php
	}
}
