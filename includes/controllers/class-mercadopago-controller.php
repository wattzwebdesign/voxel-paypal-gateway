<?php

namespace VoxelPayPal\Controllers;

if ( ! defined('ABSPATH') ) {
	exit;
}

/**
 * Mercado Pago Controller
 * Main controller that initializes all Mercado Pago functionality
 */
class MercadoPago_Controller extends \Voxel\Controllers\Base_Controller {

	protected function dependencies(): void {
		new MercadoPago_Connect_Controller();
		new MercadoPago_Payments_Controller();
		new MercadoPago_Webhooks_Controller();
	}

	protected function hooks(): void {
		$this->filter( 'voxel/product-types/payment-methods', '@register_payment_methods' );
		$this->on( 'voxel/backend/screen:payments/mercadopago', '@render_webhook_notice' );
	}

	/**
	 * Register Mercado Pago payment methods
	 */
	protected function register_payment_methods( array $payment_methods ): array {
		$payment_methods['mercadopago_payment'] = \VoxelPayPal\Payment_Methods\MercadoPago_Payment::class;
		$payment_methods['mercadopago_subscription'] = \VoxelPayPal\Payment_Methods\MercadoPago_Subscription::class;

		return $payment_methods;
	}

	/**
	 * Render webhook URL notice on admin payments page
	 */
	protected function render_webhook_notice(): void {
		$webhook_url = home_url( '/?vx=1&action=mercadopago.webhooks' );
		?>
		<div class="ts-group" style="margin-top: 20px;">
			<div class="ts-group-head">
				<h3><?php _e( 'Webhook Configuration', 'voxel-payment-gateways' ); ?></h3>
			</div>
			<div class="x-row">
				<div class="ts-form-group x-col-12">
					<p style="margin-bottom: 10px;">
						<?php _e( 'Configure this webhook URL in your Mercado Pago application settings:', 'voxel-payment-gateways' ); ?>
					</p>
					<input type="text" readonly value="<?php echo esc_attr( $webhook_url ); ?>" style="width: 100%; font-family: monospace;" onclick="this.select();">
				</div>
			</div>
		</div>
		<?php
	}
}
