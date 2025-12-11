<?php

namespace VoxelPayPal\Payment_Methods;

use VoxelPayPal\MercadoPago_Client;

if ( ! defined('ABSPATH') ) {
	exit;
}

/**
 * Mercado Pago Subscription Payment Method
 * Handles recurring payment processing using Mercado Pago Preapproval API
 */
class MercadoPago_Subscription extends \Voxel\Product_Types\Payment_Methods\Base_Payment_Method {

	public function get_type(): string {
		return 'mercadopago_subscription';
	}

	public function get_label(): string {
		return _x( 'Mercado Pago subscription', 'payment methods', 'voxel-payment-gateways' );
	}

	/**
	 * Process subscription - creates Mercado Pago preapproval
	 */
	public function process_payment() {
		try {
			$customer = $this->order->get_customer();
			$line_items = $this->get_line_items();

			// Get the first item for subscription details
			$first_item = reset( $line_items );
			if ( ! $first_item ) {
				throw new \Exception( 'No subscription item found' );
			}

			// Build preapproval data
			$preapproval_data = $this->build_preapproval_data( $first_item );

			// Create Mercado Pago preapproval
			$response = MercadoPago_Client::create_preapproval( $preapproval_data );

			if ( ! $response['success'] ) {
				throw new \Exception( $response['error'] ?? 'Failed to create Mercado Pago subscription' );
			}

			$preapproval = $response['data'] ?? null;

			if ( ! $preapproval || empty( $preapproval['id'] ) ) {
				throw new \Exception( 'Mercado Pago preapproval not found in response' );
			}

			// Store preapproval details in Voxel order
			$this->order->set_details( 'mercadopago.preapproval_id', $preapproval['id'] );
			$this->order->set_details( 'mercadopago.subscription_status', $preapproval['status'] ?? 'pending' );
			$this->order->set_details( 'mercadopago.is_subscription', true );

			// Store pricing
			$this->order->set_details( 'pricing.total', $first_item['amount'] );

			$this->order->save();

			// Get redirect URL
			$init_point = $preapproval['init_point'] ?? $preapproval['sandbox_init_point'] ?? null;

			if ( ! $init_point ) {
				throw new \Exception( 'Mercado Pago subscription URL not found' );
			}

			return [
				'success' => true,
				'redirect_url' => $init_point,
			];

		} catch ( \Exception $e ) {
			return [
				'success' => false,
				'message' => _x( 'Mercado Pago subscription failed', 'checkout', 'voxel-payment-gateways' ),
				'debug' => [
					'type' => 'mercadopago_subscription_error',
					'message' => $e->getMessage(),
				],
			];
		}
	}

	/**
	 * Build Mercado Pago preapproval data structure
	 */
	protected function build_preapproval_data( array $line_item ): array {
		$currency = $this->order->get_currency();
		$brand_name = \Voxel\get( 'payments.mercadopago.payments.brand_name', get_bloginfo( 'name' ) );

		// Get customer email
		$customer = $this->order->get_customer();
		$customer_email = '';
		if ( $customer && method_exists( $customer, 'get_email' ) ) {
			$customer_email = $customer->get_email();
		}

		// Get subscription interval from product
		$interval = $line_item['subscription']['interval'] ?? 'month';
		$interval_count = $line_item['subscription']['interval_count'] ?? 1;

		// Map Voxel intervals to Mercado Pago frequency types
		$frequency_type = 'months';
		$frequency = 1;

		switch ( $interval ) {
			case 'day':
				$frequency_type = 'days';
				$frequency = $interval_count;
				break;
			case 'week':
				$frequency_type = 'days';
				$frequency = $interval_count * 7;
				break;
			case 'month':
				$frequency_type = 'months';
				$frequency = $interval_count;
				break;
			case 'year':
				$frequency_type = 'months';
				$frequency = $interval_count * 12;
				break;
		}

		$product_label = $line_item['product']['label'] ?? 'Subscription';
		$unit_price = MercadoPago_Client::to_mercadopago_amount( $line_item['amount'] );

		$preapproval_data = [
			'reason' => mb_substr( $product_label . ' - ' . ( $brand_name ?: get_bloginfo( 'name' ) ), 0, 256 ),
			'external_reference' => 'voxel_subscription_' . $this->order->get_id(),
			'payer_email' => $customer_email,
			'auto_recurring' => [
				'frequency' => $frequency,
				'frequency_type' => $frequency_type,
				'transaction_amount' => $unit_price,
				'currency_id' => $currency,
			],
			'back_url' => $this->get_return_url(),
			'notification_url' => $this->get_webhook_url(),
		];

		return apply_filters( 'voxel/mercadopago/preapproval-data', $preapproval_data, $this->order );
	}

	/**
	 * Get return URL after subscription authorization
	 */
	protected function get_return_url(): string {
		return add_query_arg( [
			'vx' => 1,
			'action' => 'mercadopago.subscription.callback',
			'order_id' => $this->order->get_id(),
		], home_url('/') );
	}

	/**
	 * Get webhook URL
	 */
	protected function get_webhook_url(): string {
		return add_query_arg( [
			'vx' => 1,
			'action' => 'mercadopago.webhooks',
		], home_url('/') );
	}

	/**
	 * Handle subscription status update
	 */
	public function subscription_updated( array $preapproval ): void {
		$this->order->set_details( 'mercadopago.preapproval', $preapproval );
		$this->order->set_details( 'mercadopago.subscription_status', $preapproval['status'] ?? 'unknown' );

		// Store preapproval ID if not already set
		if ( ! empty( $preapproval['id'] ) && ! $this->order->get_details( 'mercadopago.preapproval_id' ) ) {
			$this->order->set_details( 'mercadopago.preapproval_id', $preapproval['id'] );
		}

		$status = $preapproval['status'] ?? '';

		switch ( $status ) {
			case 'authorized':
			case 'active':
				$this->order->set_status( \Voxel\ORDER_COMPLETED );
				break;

			case 'pending':
				$this->order->set_status( \Voxel\ORDER_PENDING_PAYMENT );
				break;

			case 'paused':
				// Keep current status but note it's paused
				$this->order->set_details( 'mercadopago.paused_at', \Voxel\utc()->format( 'Y-m-d H:i:s' ) );
				break;

			case 'cancelled':
				$this->order->set_status( \Voxel\ORDER_CANCELED );
				break;
		}

		$this->order->set_details( 'mercadopago.last_synced_at', \Voxel\utc()->format( 'Y-m-d H:i:s' ) );
		$this->order->save();
	}

	/**
	 * Handle subscription payment (recurring charge)
	 */
	public function handle_subscription_payment( array $payment ): void {
		$this->order->set_details( 'mercadopago.last_payment', $payment );
		$this->order->set_details( 'mercadopago.last_payment_id', $payment['id'] ?? null );
		$this->order->set_details( 'mercadopago.last_payment_status', $payment['status'] ?? 'unknown' );
		$this->order->set_details( 'mercadopago.last_payment_at', \Voxel\utc()->format( 'Y-m-d H:i:s' ) );

		$payment_status = $payment['status'] ?? '';

		if ( $payment_status === 'approved' ) {
			// Subscription payment successful - ensure order is active
			if ( $this->order->get_status() !== \Voxel\ORDER_COMPLETED ) {
				$this->order->set_status( \Voxel\ORDER_COMPLETED );
			}
		} elseif ( in_array( $payment_status, [ 'rejected', 'cancelled' ], true ) ) {
			// Payment failed - log but don't immediately cancel
			// Mercado Pago will retry according to their rules
			$this->order->set_details( 'mercadopago.payment_failed_at', \Voxel\utc()->format( 'Y-m-d H:i:s' ) );
		}

		$this->order->save();
	}

	/**
	 * Handle initial payment completed after subscription authorization
	 */
	public function handle_initial_payment_completed( array $payment ): void {
		$this->order->set_details( 'mercadopago.initial_payment', $payment );
		$this->order->set_details( 'mercadopago.initial_payment_id', $payment['id'] ?? null );

		if ( ! empty( $payment['id'] ) ) {
			$this->order->set_transaction_id( (string) $payment['id'] );
		}

		$payment_status = $payment['status'] ?? '';

		if ( $payment_status === 'approved' ) {
			$this->order->set_status( \Voxel\ORDER_COMPLETED );
		} elseif ( $payment_status === 'pending' || $payment_status === 'in_process' ) {
			$this->order->set_status( \Voxel\ORDER_PENDING_PAYMENT );
		} elseif ( in_array( $payment_status, [ 'rejected', 'cancelled' ], true ) ) {
			$this->order->set_status( \Voxel\ORDER_CANCELED );
		}

		$this->order->set_details( 'mercadopago.last_synced_at', \Voxel\utc()->format( 'Y-m-d H:i:s' ) );
		$this->order->save();
	}

	/**
	 * Sync with Mercado Pago
	 */
	public function should_sync(): bool {
		return ! $this->order->get_details( 'mercadopago.last_synced_at' );
	}

	public function sync(): void {
		$preapproval_id = $this->order->get_details( 'mercadopago.preapproval_id' );
		if ( ! $preapproval_id ) {
			return;
		}

		$response = MercadoPago_Client::get_preapproval( $preapproval_id );

		if ( $response['success'] && ! empty( $response['data'] ) ) {
			$this->subscription_updated( $response['data'] );
		}
	}

	/**
	 * Customer actions for subscription
	 */
	public function get_customer_actions(): array {
		$actions = [];
		$status = $this->order->get_status();
		$subscription_status = $this->order->get_details( 'mercadopago.subscription_status' );

		// Cancel subscription
		if ( $status === \Voxel\ORDER_COMPLETED && in_array( $subscription_status, [ 'authorized', 'active' ], true ) ) {
			$actions[] = [
				'action' => 'customer.cancel_subscription',
				'label' => _x( 'Cancel subscription', 'order customer actions', 'voxel-payment-gateways' ),
				'handler' => function() {
					$preapproval_id = $this->order->get_details( 'mercadopago.preapproval_id' );

					if ( $preapproval_id ) {
						$response = MercadoPago_Client::cancel_preapproval( $preapproval_id );

						if ( $response['success'] ) {
							$this->order->set_details( 'mercadopago.subscription_status', 'cancelled' );
							$this->order->set_details( 'mercadopago.canceled_at', \Voxel\utc()->format( 'Y-m-d H:i:s' ) );
							$this->order->set_details( 'mercadopago.canceled_by', get_current_user_id() );
							$this->order->set_status( \Voxel\ORDER_CANCELED );
							$this->order->save();

							return wp_send_json( [
								'success' => true,
							] );
						}

						return wp_send_json( [
							'success' => false,
							'message' => $response['error'] ?? 'Failed to cancel subscription',
						] );
					}

					return wp_send_json( [
						'success' => false,
						'message' => 'Subscription not found',
					] );
				},
			];
		}

		return $actions;
	}

	/**
	 * Vendor actions for subscription
	 */
	public function get_vendor_actions(): array {
		$actions = [];
		$status = $this->order->get_status();
		$subscription_status = $this->order->get_details( 'mercadopago.subscription_status' );

		// Cancel subscription (vendor)
		if ( $status === \Voxel\ORDER_COMPLETED && in_array( $subscription_status, [ 'authorized', 'active' ], true ) ) {
			$actions[] = [
				'action' => 'vendor.cancel_subscription',
				'label' => _x( 'Cancel subscription', 'order actions', 'voxel-payment-gateways' ),
				'handler' => function() {
					$preapproval_id = $this->order->get_details( 'mercadopago.preapproval_id' );

					if ( $preapproval_id ) {
						$response = MercadoPago_Client::cancel_preapproval( $preapproval_id );

						if ( $response['success'] ) {
							$this->order->set_details( 'mercadopago.subscription_status', 'cancelled' );
							$this->order->set_details( 'mercadopago.canceled_at', \Voxel\utc()->format( 'Y-m-d H:i:s' ) );
							$this->order->set_details( 'mercadopago.canceled_by', get_current_user_id() );
							$this->order->set_status( \Voxel\ORDER_CANCELED );
							$this->order->save();

							( new \Voxel\Events\Products\Orders\Vendor_Declined_Order_Event )->dispatch( $this->order->get_id() );

							return wp_send_json( [
								'success' => true,
							] );
						}

						return wp_send_json( [
							'success' => false,
							'message' => $response['error'] ?? 'Failed to cancel subscription',
						] );
					}

					return wp_send_json( [
						'success' => false,
						'message' => 'Subscription not found',
					] );
				},
			];
		}

		return $actions;
	}
}
