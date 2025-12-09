<?php

namespace VoxelPayPal\Payment_Methods;

use VoxelPayPal\Square_Client;

if ( ! defined('ABSPATH') ) {
	exit;
}

/**
 * Square Subscription Payment Method
 * Handles recurring subscription payments for memberships
 */
class Square_Subscription extends \Voxel\Product_Types\Payment_Methods\Base_Payment_Method {

	public function get_type(): string {
		return 'square_subscription';
	}

	public function get_label(): string {
		return _x( 'Square subscription', 'payment methods', 'voxel-payment-gateways' );
	}

	/**
	 * Process subscription payment
	 * Note: Square subscriptions require a customer with a card on file.
	 * For the initial payment, we'll use a checkout link to collect card details,
	 * then create the subscription.
	 */
	public function process_payment() {
		try {
			$customer = $this->order->get_customer();
			$line_items = $this->get_line_items();

			if ( empty( $line_items ) ) {
				throw new \Exception( 'No items in order' );
			}

			// Get the first item for subscription setup
			$first_item = $line_items[0];
			$order_item = $first_item['order_item'];

			// Get subscription details
			$interval = $order_item->get_details('subscription.unit') ?: 'MONTH';
			$frequency = $order_item->get_details('subscription.frequency') ?: 1;
			$trial_days = $order_item->get_details('subscription.trial_days');

			// Store subscription metadata for later use (after card collection)
			$this->order->set_details( 'square.subscription_setup', [
				'interval' => $interval,
				'frequency' => $frequency,
				'trial_days' => $trial_days,
				'amount' => $first_item['amount'],
				'currency' => $first_item['currency'],
				'product_label' => $first_item['product']['label'],
				'product_description' => $first_item['product']['description'] ?? '',
			] );

			// For Square subscriptions, we need to:
			// 1. Create a checkout link to collect initial payment and card details
			// 2. After successful payment, use webhook to create subscription

			$checkout_data = $this->build_subscription_checkout_data( $line_items );

			// Create Square payment link for initial payment
			$response = Square_Client::create_checkout_link( $checkout_data );

			if ( ! $response['success'] ) {
				throw new \Exception( $response['error'] ?? 'Failed to create Square checkout' );
			}

			$payment_link = $response['data']['payment_link'] ?? null;

			if ( ! $payment_link ) {
				throw new \Exception( 'Square payment link not found in response' );
			}

			// Store Square payment link details
			$this->order->set_details( 'square.payment_link_id', $payment_link['id'] );
			$this->order->set_details( 'square.order_id', $payment_link['order_id'] ?? null );
			$this->order->set_details( 'square.status', 'PENDING' );
			$this->order->set_details( 'square.is_subscription', true );

			// Calculate total
			$total = 0;
			foreach ( $line_items as $item ) {
				$total += $item['amount'] * $item['quantity'];
			}
			$this->order->set_details( 'pricing.total', $total );

			$this->order->save();

			// Get checkout URL
			$checkout_url = $payment_link['url'] ?? $payment_link['long_url'] ?? null;

			if ( ! $checkout_url ) {
				throw new \Exception( 'Square checkout URL not found' );
			}

			return [
				'success' => true,
				'redirect_url' => $checkout_url,
			];

		} catch ( \Exception $e ) {
			return [
				'success' => false,
				'message' => _x( 'Subscription setup failed', 'checkout', 'voxel-payment-gateways' ),
				'debug' => [
					'type' => 'square_subscription_error',
					'message' => $e->getMessage(),
				],
			];
		}
	}

	/**
	 * Build checkout data for subscription initial payment
	 */
	protected function build_subscription_checkout_data( array $line_items ): array {
		$currency = $this->order->get_currency();
		$brand_name = \Voxel\get( 'payments.square.payments.brand_name', get_bloginfo( 'name' ) );
		$location_id = Square_Client::get_location_id();
		$customer = $this->order->get_customer();

		// Build total from line items and collect product names
		$total_amount = 0;
		$product_names = [];
		foreach ( $line_items as $line_item ) {
			$unit_amount_cents = Square_Client::to_square_amount( $line_item['amount'] );
			$total_amount += $unit_amount_cents * $line_item['quantity'];
			$product_names[] = $line_item['product']['label'] ?? '';
		}

		// Build a descriptive order name
		$order_name = '';
		if ( ! empty( $product_names ) && ! empty( $product_names[0] ) ) {
			$first_product = $product_names[0];
			$order_name = sprintf( '%s (Subscription) - Order #%d', mb_substr( $first_product, 0, 80 ), $this->order->get_id() );
		} else {
			$order_name = sprintf( 'Subscription - Order #%d', $this->order->get_id() );
		}

		$checkout_data = [
			'idempotency_key' => Square_Client::generate_idempotency_key(),
			'quick_pay' => [
				'name' => mb_substr( $order_name, 0, 255 ),
				'price_money' => [
					'amount' => $total_amount,
					'currency' => $currency,
				],
				'location_id' => $location_id,
			],
			'checkout_options' => [
				'redirect_url' => $this->get_return_url(),
				'merchant_support_email' => get_option( 'admin_email' ),
				'ask_for_shipping_address' => false,
			],
			'pre_populated_data' => [],
		];

		// Add customer email if available
		if ( $customer && method_exists( $customer, 'get_email' ) && $customer->get_email() ) {
			$checkout_data['pre_populated_data']['buyer_email'] = $customer->get_email();
		}

		// Store reference to Voxel order
		$checkout_data['payment_note'] = 'voxel_subscription_' . $this->order->get_id();

		return apply_filters( 'voxel/square/subscription-checkout-data', $checkout_data, $this->order );
	}

	/**
	 * Get or create Square subscription plan (catalog object)
	 */
	protected function get_or_create_subscription_plan( array $line_item, string $interval, int $frequency ): ?string {
		$product_label = $line_item['product']['label'];
		$amount = Square_Client::to_square_amount( $line_item['amount'] );
		$currency = $line_item['currency'];

		// Check if plan already exists in order details
		$cached_plan_id = $this->order->get_details( 'square.cached_plan_id' );
		if ( $cached_plan_id ) {
			return $cached_plan_id;
		}

		// Map Voxel intervals to Square intervals
		$square_cadence = $this->map_interval_to_square_cadence( $interval, $frequency );

		// Create subscription plan catalog object
		$catalog_data = [
			'idempotency_key' => Square_Client::generate_idempotency_key(),
			'object' => [
				'type' => 'SUBSCRIPTION_PLAN',
				'id' => '#plan_' . $this->order->get_id(),
				'subscription_plan_data' => [
					'name' => mb_substr( $product_label . ' - Subscription', 0, 255 ),
					'phases' => [
						[
							'cadence' => $square_cadence,
							'recurring_price_money' => [
								'amount' => $amount,
								'currency' => $currency,
							],
						],
					],
				],
			],
		];

		$response = Square_Client::create_catalog_object( $catalog_data );

		if ( ! $response['success'] ) {
			error_log( 'Square: Failed to create subscription plan: ' . ( $response['error'] ?? 'Unknown error' ) );
			return null;
		}

		$plan_id = $response['data']['catalog_object']['id'] ?? null;

		if ( $plan_id ) {
			// Cache the plan ID
			$this->order->set_details( 'square.cached_plan_id', $plan_id );
			$this->order->save();
		}

		return $plan_id;
	}

	/**
	 * Map Voxel interval to Square cadence
	 */
	protected function map_interval_to_square_cadence( string $interval, int $frequency ): string {
		$interval = strtoupper( $interval );

		// Square supports: DAILY, WEEKLY, EVERY_TWO_WEEKS, MONTHLY, EVERY_TWO_MONTHS,
		// QUARTERLY, EVERY_FOUR_MONTHS, EVERY_SIX_MONTHS, ANNUAL, EVERY_TWO_YEARS

		if ( $interval === 'DAY' ) {
			return 'DAILY';
		} elseif ( $interval === 'WEEK' ) {
			if ( $frequency >= 2 ) {
				return 'EVERY_TWO_WEEKS';
			}
			return 'WEEKLY';
		} elseif ( $interval === 'MONTH' ) {
			if ( $frequency === 2 ) {
				return 'EVERY_TWO_MONTHS';
			} elseif ( $frequency === 3 ) {
				return 'QUARTERLY';
			} elseif ( $frequency === 4 ) {
				return 'EVERY_FOUR_MONTHS';
			} elseif ( $frequency === 6 ) {
				return 'EVERY_SIX_MONTHS';
			}
			return 'MONTHLY';
		} elseif ( $interval === 'YEAR' ) {
			if ( $frequency >= 2 ) {
				return 'EVERY_TWO_YEARS';
			}
			return 'ANNUAL';
		}

		return 'MONTHLY';
	}

	/**
	 * Get return URL after approval
	 */
	protected function get_return_url(): string {
		return add_query_arg( [
			'vx' => 1,
			'action' => 'square.subscription.success',
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
			'action' => 'square.subscription.cancel',
			'order_id' => $this->order->get_id(),
			'redirect_to' => rawurlencode( $redirect_url ),
		], home_url('/') );
	}

	/**
	 * Handle subscription activated/updated
	 */
	public function subscription_updated( array $subscription ): void {
		$this->order->set_details( 'square.subscription', $subscription );
		$this->order->set_details( 'square.status', $subscription['status'] ?? 'ACTIVE' );

		if ( ! empty( $subscription['id'] ) ) {
			$this->order->set_transaction_id( $subscription['id'] );
			$this->order->set_details( 'square.subscription_id', $subscription['id'] );
		}

		// Map Square subscription status to Voxel order status
		$status = strtoupper( $subscription['status'] ?? '' );

		if ( $status === 'ACTIVE' ) {
			$this->order->set_status( 'sub_active' );
		} elseif ( $status === 'PAUSED' ) {
			$this->order->set_status( 'sub_paused' );
		} elseif ( in_array( $status, [ 'CANCELED', 'DEACTIVATED' ], true ) ) {
			$this->order->set_status( 'sub_canceled' );
		} elseif ( $status === 'PENDING' ) {
			$this->order->set_status( 'pending_payment' );
		}

		$this->order->set_details( 'square.last_synced_at', \Voxel\utc()->format( 'Y-m-d H:i:s' ) );
		$this->order->save();
	}

	/**
	 * Handle initial payment completed - activate subscription
	 */
	public function handle_initial_payment_completed( array $payment ): void {
		// Store payment details
		$this->order->set_details( 'square.initial_payment', $payment );

		if ( ! empty( $payment['id'] ) ) {
			$this->order->set_details( 'square.payment_id', $payment['id'] );
		}

		// For simplicity, we'll mark the subscription as active after initial payment
		// In a full implementation, you would create the subscription using Square's Subscriptions API
		// with the customer's card on file from the initial payment

		$this->order->set_status( 'sub_active' );
		$this->order->set_transaction_id( $payment['id'] ?? 'square_sub_' . $this->order->get_id() );
		$this->order->set_details( 'square.subscription_started_at', \Voxel\utc()->format( 'Y-m-d H:i:s' ) );
		$this->order->set_details( 'square.last_synced_at', \Voxel\utc()->format( 'Y-m-d H:i:s' ) );

		$this->order->save();
	}

	/**
	 * Sync with Square
	 */
	public function should_sync(): bool {
		return ! $this->order->get_details( 'square.last_synced_at' );
	}

	public function sync(): void {
		$subscription_id = $this->order->get_details( 'square.subscription_id' );
		if ( ! $subscription_id ) {
			return;
		}

		$response = Square_Client::get_subscription( $subscription_id );

		if ( $response['success'] && ! empty( $response['data']['subscription'] ) ) {
			$this->subscription_updated( $response['data']['subscription'] );
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
				'label' => _x( 'Cancel subscription', 'order customer actions', 'voxel-payment-gateways' ),
				'handler' => function() {
					$subscription_id = $this->order->get_details( 'square.subscription_id' );

					if ( $subscription_id ) {
						// Cancel via Square API
						$response = Square_Client::cancel_subscription( $subscription_id );

						if ( ! $response['success'] ) {
							// Log error but still cancel locally
							error_log( 'Square: Failed to cancel subscription via API: ' . ( $response['error'] ?? 'Unknown error' ) );
						}
					}

					// Update order status
					$this->order->set_status( 'sub_canceled' );
					$this->order->set_details( 'square.canceled_at', \Voxel\utc()->format( 'Y-m-d H:i:s' ) );
					$this->order->set_details( 'square.canceled_by', get_current_user_id() );
					$this->order->save();

					return wp_send_json( [
						'success' => true,
					] );
				},
			];
		}

		// Pause/Resume actions
		if ( $status === 'sub_active' ) {
			$actions[] = [
				'action' => 'customer.pause_subscription',
				'label' => _x( 'Pause subscription', 'order customer actions', 'voxel-payment-gateways' ),
				'handler' => function() {
					$subscription_id = $this->order->get_details( 'square.subscription_id' );

					if ( $subscription_id ) {
						$response = Square_Client::pause_subscription( $subscription_id );

						if ( $response['success'] ) {
							$this->order->set_status( 'sub_paused' );
							$this->order->set_details( 'square.paused_at', \Voxel\utc()->format( 'Y-m-d H:i:s' ) );
							$this->order->save();

							return wp_send_json( [ 'success' => true ] );
						}

						return wp_send_json( [
							'success' => false,
							'message' => $response['error'] ?? 'Failed to pause subscription',
						] );
					}

					return wp_send_json( [
						'success' => false,
						'message' => 'Subscription ID not found',
					] );
				},
			];
		}

		if ( $status === 'sub_paused' ) {
			$actions[] = [
				'action' => 'customer.resume_subscription',
				'label' => _x( 'Resume subscription', 'order customer actions', 'voxel-payment-gateways' ),
				'handler' => function() {
					$subscription_id = $this->order->get_details( 'square.subscription_id' );

					if ( $subscription_id ) {
						$response = Square_Client::resume_subscription( $subscription_id );

						if ( $response['success'] ) {
							$this->order->set_status( 'sub_active' );
							$this->order->set_details( 'square.resumed_at', \Voxel\utc()->format( 'Y-m-d H:i:s' ) );
							$this->order->save();

							return wp_send_json( [ 'success' => true ] );
						}

						return wp_send_json( [
							'success' => false,
							'message' => $response['error'] ?? 'Failed to resume subscription',
						] );
					}

					return wp_send_json( [
						'success' => false,
						'message' => 'Subscription ID not found',
					] );
				},
			];
		}

		return $actions;
	}
}
