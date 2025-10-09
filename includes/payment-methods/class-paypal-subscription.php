<?php

namespace VoxelPayPal\Payment_Methods;

use VoxelPayPal\PayPal_Client;

if ( ! defined('ABSPATH') ) {
	exit;
}

/**
 * PayPal Subscription Payment Method
 * Handles recurring subscription payments
 */
class PayPal_Subscription extends \Voxel\Product_Types\Payment_Methods\Base_Payment_Method {

	public function get_type(): string {
		return 'paypal_subscription';
	}

	public function get_label(): string {
		return _x( 'PayPal subscription', 'payment methods', 'voxel-paypal-gateway' );
	}

	/**
	 * Process subscription payment
	 */
	public function process_payment() {
		try {
			$customer = $this->order->get_customer();
			$line_items = $this->get_line_items();

			if ( empty( $line_items ) ) {
				throw new \Exception( 'No items in order' );
			}

			// For simplicity, we'll use the first item to create the plan
			// In production, you might need to handle multiple items differently
			$first_item = $line_items[0];
			$order_item = $first_item['order_item'];

			// Get subscription details
			$interval = $order_item->get_details('subscription.unit'); // 'MONTH', 'YEAR', 'DAY'
			$frequency = $order_item->get_details('subscription.frequency') ?: 1;
			$trial_days = $order_item->get_details('subscription.trial_days');

			// Create or get PayPal product and plan
			$plan_id = $this->get_or_create_plan( $first_item, $interval, $frequency );

			if ( ! $plan_id ) {
				throw new \Exception( 'Failed to create billing plan' );
			}

			// Build subscription data
			$subscription_data = [
				'plan_id' => $plan_id,
				'custom_id' => 'voxel_order_' . $this->order->get_id(),
				'subscriber' => [
					'name' => [
						'given_name' => $customer->get_display_name(),
					],
					'email_address' => $customer->get_email(),
				],
				'application_context' => [
					'brand_name' => \Voxel\get( 'payments.paypal.payments.brand_name', get_bloginfo( 'name' ) ),
					'locale' => 'en-US',
					'shipping_preference' => 'NO_SHIPPING',
					'user_action' => 'SUBSCRIBE_NOW',
					'payment_method' => [
						'payer_selected' => 'PAYPAL',
						'payee_preferred' => 'IMMEDIATE_PAYMENT_REQUIRED',
					],
					'return_url' => $this->get_return_url(),
					'cancel_url' => $this->get_cancel_url(),
				],
			];

			// Add trial period if specified
			if ( is_numeric( $trial_days ) && $trial_days > 0 ) {
				$subscription_data['plan'] = [
					'billing_cycles' => [
						[
							'frequency' => [
								'interval_unit' => 'DAY',
								'interval_count' => 1,
							],
							'tenure_type' => 'TRIAL',
							'sequence' => 1,
							'total_cycles' => absint( $trial_days ),
							'pricing_scheme' => [
								'fixed_price' => [
									'value' => '0',
									'currency_code' => $this->order->get_currency(),
								],
							],
						],
					],
				];
			}

			// Create subscription
			$response = PayPal_Client::create_subscription( $subscription_data );

			if ( ! $response['success'] ) {
				throw new \Exception( $response['error'] ?? 'Failed to create subscription' );
			}

			$subscription = $response['data'];

			// Store subscription details
			$this->order->set_details( 'paypal.subscription_id', $subscription['id'] );
			$this->order->set_details( 'paypal.plan_id', $plan_id );
			$this->order->set_details( 'paypal.status', $subscription['status'] );

			// Calculate total
			$total = 0;
			foreach ( $line_items as $item ) {
				$total += $item['amount'] * $item['quantity'];
			}
			$this->order->set_details( 'pricing.total', $total );

			$this->order->save();

			// Find approval URL
			$approval_url = null;
			foreach ( $subscription['links'] as $link ) {
				if ( $link['rel'] === 'approve' ) {
					$approval_url = $link['href'];
					break;
				}
			}

			if ( ! $approval_url ) {
				throw new \Exception( 'PayPal subscription approval URL not found' );
			}

			return [
				'success' => true,
				'redirect_url' => $approval_url,
			];

		} catch ( \Exception $e ) {
			return [
				'success' => false,
				'message' => _x( 'Subscription setup failed', 'checkout', 'voxel-paypal-gateway' ),
				'debug' => [
					'type' => 'paypal_subscription_error',
					'message' => $e->getMessage(),
				],
			];
		}
	}

	/**
	 * Get or create PayPal billing plan
	 */
	protected function get_or_create_plan( array $line_item, string $interval, int $frequency ): ?string {
		$product_label = $line_item['product']['label'];
		$amount = number_format( $line_item['amount'], 2, '.', '' );
		$currency = $line_item['currency'];

		// Check if plan already exists in order details
		$cached_plan_id = $this->order->get_details( 'paypal.cached_plan_id' );
		if ( $cached_plan_id ) {
			return $cached_plan_id;
		}

		// Create product first
		$product_data = [
			'name' => mb_substr( $product_label, 0, 127 ),
			'type' => 'SERVICE',
			'category' => 'SOFTWARE',
		];

		if ( ! empty( $line_item['product']['description'] ) ) {
			$product_data['description'] = mb_substr( $line_item['product']['description'], 0, 256 );
		}

		$product_response = PayPal_Client::create_product( $product_data );

		if ( ! $product_response['success'] ) {
			error_log( 'PayPal: Failed to create product: ' . ( $product_response['error'] ?? 'Unknown error' ) );
			return null;
		}

		$product_id = $product_response['data']['id'];

		// Map Voxel intervals to PayPal intervals
		$paypal_interval = strtoupper( $interval );
		if ( ! in_array( $paypal_interval, [ 'DAY', 'WEEK', 'MONTH', 'YEAR' ], true ) ) {
			$paypal_interval = 'MONTH';
		}

		// Create billing plan
		$plan_data = [
			'product_id' => $product_id,
			'name' => mb_substr( $product_label . ' - Subscription', 0, 127 ),
			'status' => 'ACTIVE',
			'billing_cycles' => [
				[
					'frequency' => [
						'interval_unit' => $paypal_interval,
						'interval_count' => $frequency,
					],
					'tenure_type' => 'REGULAR',
					'sequence' => 1,
					'total_cycles' => 0, // 0 means infinite
					'pricing_scheme' => [
						'fixed_price' => [
							'value' => $amount,
							'currency_code' => $currency,
						],
					],
				],
			],
			'payment_preferences' => [
				'auto_bill_outstanding' => true,
				'setup_fee_failure_action' => 'CONTINUE',
				'payment_failure_threshold' => 3,
			],
		];

		$plan_response = PayPal_Client::create_plan( $plan_data );

		if ( ! $plan_response['success'] ) {
			error_log( 'PayPal: Failed to create plan: ' . ( $plan_response['error'] ?? 'Unknown error' ) );
			return null;
		}

		$plan_id = $plan_response['data']['id'];

		// Cache the plan ID
		$this->order->set_details( 'paypal.cached_plan_id', $plan_id );
		$this->order->save();

		return $plan_id;
	}

	/**
	 * Get return URL after approval
	 */
	protected function get_return_url(): string {
		return add_query_arg( [
			'vx' => 1,
			'action' => 'paypal.subscription.success',
			'order_id' => $this->order->get_id(),
		], home_url('/') );
	}

	/**
	 * Get cancel URL
	 */
	protected function get_cancel_url(): string {
		$redirect_url = wp_get_referer() ?: home_url('/');
		$redirect_url = add_query_arg( 't', time(), $redirect_url );

		return add_query_arg( [
			'vx' => 1,
			'action' => 'paypal.subscription.cancel',
			'order_id' => $this->order->get_id(),
			'redirect_to' => rawurlencode( $redirect_url ),
		], home_url('/') );
	}

	/**
	 * Handle subscription activated
	 */
	public function subscription_updated( array $subscription ): void {
		$this->order->set_details( 'paypal.subscription', $subscription );
		$this->order->set_details( 'paypal.status', $subscription['status'] );
		$this->order->set_transaction_id( $subscription['id'] );

		// Map PayPal subscription status to Voxel order status
		// Voxel uses format: 'sub_{status}' (matching Stripe implementation)
		$status = strtolower( $subscription['status'] );
		if ( $status === 'active' ) {
			$this->order->set_status( 'sub_active' );
		} elseif ( $status === 'suspended' ) {
			$this->order->set_status( 'sub_paused' );
		} elseif ( in_array( $status, [ 'cancelled', 'expired' ], true ) ) {
			$this->order->set_status( 'sub_canceled' );
		} elseif ( $status === 'approval_pending' ) {
			$this->order->set_status( 'pending_payment' );
		}

		$this->order->set_details( 'paypal.last_synced_at', \Voxel\utc()->format( 'Y-m-d H:i:s' ) );
		$this->order->save();
	}

	/**
	 * Sync with PayPal
	 */
	public function should_sync(): bool {
		return ! $this->order->get_details( 'paypal.last_synced_at' );
	}

	public function sync(): void {
		$subscription_id = $this->order->get_details( 'paypal.subscription_id' );
		if ( ! $subscription_id ) {
			return;
		}

		$response = PayPal_Client::get_subscription( $subscription_id );

		if ( $response['success'] && ! empty( $response['data'] ) ) {
			$this->subscription_updated( $response['data'] );
		}
	}

	/**
	 * Is this a subscription?
	 */
	public function is_subscription(): bool {
		return true;
	}

	/**
	 * Get billing interval
	 */
	public function get_billing_interval(): ?array {
		$plan_id = $this->order->get_details( 'paypal.plan_id' );
		if ( ! $plan_id ) {
			return null;
		}

		// Get from order item details
		$items = $this->order->get_items();
		if ( empty( $items ) ) {
			return null;
		}

		$first_item = reset( $items );
		$unit = $first_item->get_details('subscription.unit') ?: 'month';
		$frequency = $first_item->get_details('subscription.frequency') ?: 1;

		return [
			'unit' => strtolower( $unit ),
			'frequency' => $frequency,
		];
	}

	/**
	 * Customer actions
	 */
	public function get_customer_actions(): array {
		$actions = [];
		$status = $this->order->get_status();

		if ( in_array( $status, [ 'sub_active', 'sub_paused' ], true ) ) {
			$actions[] = [
				'action' => 'customer.cancel_subscription',
				'label' => _x( 'Cancel subscription', 'order customer actions', 'voxel-paypal-gateway' ),
				'handler' => function() {
					$subscription_id = $this->order->get_details( 'paypal.subscription_id' );
					if ( ! $subscription_id ) {
						return wp_send_json( [
							'success' => false,
							'message' => 'Subscription ID not found',
						] );
					}

					$response = PayPal_Client::cancel_subscription( $subscription_id, 'Customer requested cancellation' );

					if ( $response['success'] ) {
						$this->order->set_status( 'sub_canceled' );
						$this->order->save();

						return wp_send_json( [
							'success' => true,
						] );
					}

					return wp_send_json( [
						'success' => false,
						'message' => $response['error'] ?? 'Failed to cancel subscription',
					] );
				},
			];
		}

		return $actions;
	}
}
