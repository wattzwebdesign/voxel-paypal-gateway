<?php

namespace VoxelPayPal\Payment_Methods;

use VoxelPayPal\Paystack_Client;
use VoxelPayPal\Paystack_Connect_Client;

if ( ! defined('ABSPATH') ) {
	exit;
}

/**
 * Paystack Subscription Payment Method
 * Handles recurring payment processing using Paystack Plans and Subscriptions
 */
class Paystack_Subscription extends \Voxel\Product_Types\Payment_Methods\Base_Payment_Method {

	public function get_type(): string {
		return 'paystack_subscription';
	}

	public function get_label(): string {
		return _x( 'Paystack subscription', 'payment methods', 'voxel-payment-gateways' );
	}

	/**
	 * Process subscription - initializes transaction with plan
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

			// Get or create a Paystack plan for this subscription
			$plan_code = $this->get_or_create_plan( $first_item );

			if ( ! $plan_code ) {
				throw new \Exception( 'Failed to create or retrieve Paystack plan' );
			}

			// Build transaction data with plan
			$transaction_data = $this->build_transaction_data( $first_item, $plan_code );

			// Initialize transaction
			$response = Paystack_Client::initialize_transaction( $transaction_data );

			if ( ! $response['success'] ) {
				throw new \Exception( $response['error'] ?? 'Failed to initialize Paystack subscription' );
			}

			$transaction = $response['data'] ?? null;

			if ( ! $transaction || empty( $transaction['authorization_url'] ) ) {
				throw new \Exception( 'Paystack authorization URL not found' );
			}

			// Store subscription details
			$this->order->set_details( 'paystack.reference', $transaction['reference'] );
			$this->order->set_details( 'paystack.access_code', $transaction['access_code'] );
			$this->order->set_details( 'paystack.plan_code', $plan_code );
			$this->order->set_details( 'paystack.is_subscription', true );
			$this->order->set_details( 'paystack.status', 'PENDING' );

			// Store pricing
			$this->order->set_details( 'pricing.total', $first_item['amount'] );

			$this->order->save();

			return [
				'success' => true,
				'redirect_url' => $transaction['authorization_url'],
			];

		} catch ( \Exception $e ) {
			return [
				'success' => false,
				'message' => _x( 'Paystack subscription failed', 'checkout', 'voxel-payment-gateways' ),
				'debug' => [
					'type' => 'paystack_subscription_error',
					'message' => $e->getMessage(),
				],
			];
		}
	}

	/**
	 * Get or create a Paystack plan for the subscription
	 */
	protected function get_or_create_plan( array $line_item ): ?string {
		// Use currency from Paystack settings (must match Paystack account country)
		$currency = strtoupper( \Voxel\get( 'payments.paystack.currency', 'NGN' ) );
		$amount = Paystack_Client::to_paystack_amount( $line_item['amount'] );

		// Get subscription interval
		$interval = $line_item['subscription']['interval'] ?? 'month';
		$interval_count = $line_item['subscription']['interval_count'] ?? 1;

		// Map to Paystack interval
		$paystack_interval = $this->map_interval( $interval, $interval_count );

		if ( ! $paystack_interval ) {
			throw new \Exception( 'Unsupported subscription interval: ' . $interval );
		}

		$product_label = $line_item['product']['label'] ?? 'Subscription';

		// Generate a unique plan name based on product, amount, and interval
		$plan_key = sanitize_title( $product_label . '-' . $amount . '-' . $paystack_interval );

		// Check if we have a cached plan code for this configuration
		$cached_plans = get_option( 'paystack_plan_codes', [] );

		if ( ! empty( $cached_plans[ $plan_key ] ) ) {
			// Verify the plan still exists
			$verify = Paystack_Client::get_plan( $cached_plans[ $plan_key ] );
			if ( $verify['success'] ) {
				return $cached_plans[ $plan_key ];
			}
			// Plan no longer exists, remove from cache
			unset( $cached_plans[ $plan_key ] );
			update_option( 'paystack_plan_codes', $cached_plans );
		}

		// Create a new plan
		$plan_data = [
			'name' => mb_substr( $product_label, 0, 100 ),
			'amount' => $amount,
			'interval' => $paystack_interval,
			'currency' => $currency,
		];

		$response = Paystack_Client::create_plan( $plan_data );

		if ( ! $response['success'] ) {
			throw new \Exception( $response['error'] ?? 'Failed to create Paystack plan' );
		}

		$plan_code = $response['data']['plan_code'] ?? null;

		if ( $plan_code ) {
			// Cache the plan code
			$cached_plans[ $plan_key ] = $plan_code;
			update_option( 'paystack_plan_codes', $cached_plans );
		}

		return $plan_code;
	}

	/**
	 * Map Voxel interval to Paystack interval
	 */
	protected function map_interval( string $interval, int $interval_count = 1 ): ?string {
		// Paystack supports: hourly, daily, weekly, monthly, quarterly, biannually, annually
		switch ( $interval ) {
			case 'hour':
				return 'hourly';
			case 'day':
				return 'daily';
			case 'week':
				return 'weekly';
			case 'month':
				if ( $interval_count === 1 ) {
					return 'monthly';
				} elseif ( $interval_count === 3 ) {
					return 'quarterly';
				} elseif ( $interval_count === 6 ) {
					return 'biannually';
				} elseif ( $interval_count === 12 ) {
					return 'annually';
				}
				return 'monthly'; // Default to monthly for other counts
			case 'year':
				return 'annually';
			default:
				return null;
		}
	}

	/**
	 * Build transaction data for subscription
	 */
	protected function build_transaction_data( array $line_item, string $plan_code ): array {
		$customer = $this->order->get_customer();
		// Use currency from Paystack settings (must match Paystack account country)
		$currency = strtoupper( \Voxel\get( 'payments.paystack.currency', 'NGN' ) );
		$amount = Paystack_Client::to_paystack_amount( $line_item['amount'] );

		$customer_email = $customer ? $customer->get_email() : '';

		if ( empty( $customer_email ) ) {
			throw new \Exception( 'Customer email is required for Paystack subscriptions' );
		}

		$reference = Paystack_Client::generate_reference( 'vxl_sub_' . $this->order->get_id() );

		$transaction_data = [
			'email' => $customer_email,
			'amount' => $amount,
			'currency' => $currency,
			'reference' => $reference,
			'plan' => $plan_code,
			'callback_url' => $this->get_return_url(),
			'metadata' => [
				'order_id' => $this->order->get_id(),
				'subscription' => true,
				'custom_fields' => [
					[
						'display_name' => 'Order ID',
						'variable_name' => 'order_id',
						'value' => (string) $this->order->get_id(),
					],
				],
			],
		];

		// Add payment channels if configured
		$channels = $this->get_payment_channels();
		if ( ! empty( $channels ) ) {
			$transaction_data['channels'] = $channels;
		}

		return apply_filters( 'voxel/paystack/subscription-transaction-data', $transaction_data, $this->order );
	}

	/**
	 * Get return URL after subscription authorization
	 */
	protected function get_return_url(): string {
		return add_query_arg( [
			'vx' => 1,
			'action' => 'paystack.subscription.callback',
			'order_id' => $this->order->get_id(),
		], home_url('/') );
	}

	/**
	 * Get configured payment channels
	 */
	protected function get_payment_channels(): array {
		$channels = \Voxel\get( 'payments.paystack.channels', [] );
		return apply_filters( 'voxel/paystack/channels', $channels, $this->order );
	}

	/**
	 * Handle subscription creation webhook
	 */
	public function subscription_created( array $subscription ): void {
		$this->order->set_details( 'paystack.subscription', $subscription );
		$this->order->set_details( 'paystack.subscription_code', $subscription['subscription_code'] ?? null );
		$this->order->set_details( 'paystack.subscription_status', $subscription['status'] ?? 'active' );
		$this->order->set_details( 'paystack.email_token', $subscription['email_token'] ?? null );

		// Store next payment date
		if ( ! empty( $subscription['next_payment_date'] ) ) {
			$this->order->set_details( 'paystack.next_payment_date', $subscription['next_payment_date'] );
		}

		$status = $subscription['status'] ?? '';

		if ( $status === 'active' ) {
			$this->order->set_status( \Voxel\ORDER_COMPLETED );
		}

		$this->order->set_details( 'paystack.last_synced_at', \Voxel\utc()->format( 'Y-m-d H:i:s' ) );
		$this->order->save();
	}

	/**
	 * Handle subscription cancellation
	 */
	public function subscription_cancelled( array $data ): void {
		$this->order->set_details( 'paystack.subscription_status', 'cancelled' );
		$this->order->set_details( 'paystack.canceled_at', \Voxel\utc()->format( 'Y-m-d H:i:s' ) );
		$this->order->set_status( \Voxel\ORDER_CANCELED );
		$this->order->save();
	}

	/**
	 * Handle initial payment completed after subscription authorization
	 */
	public function handle_initial_payment_completed( array $transaction ): void {
		$this->order->set_details( 'paystack.initial_payment', $transaction );
		$this->order->set_details( 'paystack.transaction', $transaction );
		$this->order->set_details( 'paystack.status', $transaction['status'] ?? 'unknown' );

		if ( ! empty( $transaction['id'] ) ) {
			$this->order->set_transaction_id( (string) $transaction['id'] );
			$this->order->set_details( 'paystack.transaction_id', $transaction['id'] );
		}

		if ( ! empty( $transaction['reference'] ) ) {
			$this->order->set_details( 'paystack.reference', $transaction['reference'] );
		}

		// Store authorization for subscription
		if ( ! empty( $transaction['authorization'] ) ) {
			$this->order->set_details( 'paystack.authorization', $transaction['authorization'] );
		}

		// Get subscription code from authorization
		if ( ! empty( $transaction['authorization']['subscription_code'] ) ) {
			$this->order->set_details( 'paystack.subscription_code', $transaction['authorization']['subscription_code'] );
		}

		// Store customer info
		if ( ! empty( $transaction['customer'] ) ) {
			$this->order->set_details( 'paystack.customer', $transaction['customer'] );
			$this->order->set_details( 'paystack.customer_code', $transaction['customer']['customer_code'] ?? null );
		}

		$transaction_status = $transaction['status'] ?? '';

		if ( $transaction_status === 'success' ) {
			$this->order->set_status( \Voxel\ORDER_COMPLETED );
			$this->order->set_details( 'paystack.subscription_status', 'active' );
		} elseif ( in_array( $transaction_status, [ 'abandoned', 'failed' ], true ) ) {
			$this->order->set_status( \Voxel\ORDER_CANCELED );
		}

		$this->order->set_details( 'paystack.last_synced_at', \Voxel\utc()->format( 'Y-m-d H:i:s' ) );
		$this->order->save();
	}

	/**
	 * Handle subscription payment (recurring charge)
	 */
	public function handle_subscription_payment( array $transaction ): void {
		$this->order->set_details( 'paystack.last_payment', $transaction );
		$this->order->set_details( 'paystack.last_payment_id', $transaction['id'] ?? null );
		$this->order->set_details( 'paystack.last_payment_status', $transaction['status'] ?? 'unknown' );
		$this->order->set_details( 'paystack.last_payment_at', \Voxel\utc()->format( 'Y-m-d H:i:s' ) );

		$transaction_status = $transaction['status'] ?? '';

		if ( $transaction_status === 'success' ) {
			// Subscription payment successful - ensure order is active
			if ( $this->order->get_status() !== \Voxel\ORDER_COMPLETED ) {
				$this->order->set_status( \Voxel\ORDER_COMPLETED );
			}
			$this->order->set_details( 'paystack.subscription_status', 'active' );
		}

		$this->order->save();
	}

	/**
	 * Handle subscription payment failed
	 */
	public function subscription_payment_failed( array $data ): void {
		$this->order->set_details( 'paystack.payment_failed', $data );
		$this->order->set_details( 'paystack.payment_failed_at', \Voxel\utc()->format( 'Y-m-d H:i:s' ) );

		// Don't immediately cancel - Paystack will retry
		// Just log the failure
		$this->order->save();
	}

	/**
	 * Sync with Paystack
	 */
	public function should_sync(): bool {
		return ! $this->order->get_details( 'paystack.last_synced_at' );
	}

	public function sync(): void {
		$subscription_code = $this->order->get_details( 'paystack.subscription_code' );
		if ( $subscription_code ) {
			$response = Paystack_Client::get_subscription( $subscription_code );

			if ( $response['success'] && ! empty( $response['data'] ) ) {
				$this->order->set_details( 'paystack.subscription', $response['data'] );
				$this->order->set_details( 'paystack.subscription_status', $response['data']['status'] ?? 'unknown' );
				$this->order->set_details( 'paystack.last_synced_at', \Voxel\utc()->format( 'Y-m-d H:i:s' ) );
				$this->order->save();
				return;
			}
		}

		// Fallback to verifying by reference
		$reference = $this->order->get_details( 'paystack.reference' );
		if ( $reference ) {
			$response = Paystack_Client::verify_transaction( $reference );

			if ( $response['success'] && ! empty( $response['data'] ) ) {
				$this->handle_initial_payment_completed( $response['data'] );
			}
		}
	}

	/**
	 * Customer actions for subscription
	 */
	public function get_customer_actions(): array {
		$actions = [];
		$status = $this->order->get_status();
		$subscription_status = $this->order->get_details( 'paystack.subscription_status' );

		// Cancel subscription
		if ( $status === \Voxel\ORDER_COMPLETED && $subscription_status === 'active' ) {
			$actions[] = [
				'action' => 'customer.cancel_subscription',
				'label' => _x( 'Cancel subscription', 'order customer actions', 'voxel-payment-gateways' ),
				'handler' => function() {
					$subscription_code = $this->order->get_details( 'paystack.subscription_code' );
					$email_token = $this->order->get_details( 'paystack.email_token' );

					if ( $subscription_code && $email_token ) {
						$response = Paystack_Client::disable_subscription( $subscription_code, $email_token );

						if ( $response['success'] ) {
							$this->order->set_details( 'paystack.subscription_status', 'cancelled' );
							$this->order->set_details( 'paystack.canceled_at', \Voxel\utc()->format( 'Y-m-d H:i:s' ) );
							$this->order->set_details( 'paystack.canceled_by', get_current_user_id() );
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
						'message' => 'Subscription not found or missing token',
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
		$subscription_status = $this->order->get_details( 'paystack.subscription_status' );

		// Cancel subscription (vendor)
		if ( $status === \Voxel\ORDER_COMPLETED && $subscription_status === 'active' ) {
			$actions[] = [
				'action' => 'vendor.cancel_subscription',
				'label' => _x( 'Cancel subscription', 'order actions', 'voxel-payment-gateways' ),
				'handler' => function() {
					$subscription_code = $this->order->get_details( 'paystack.subscription_code' );
					$email_token = $this->order->get_details( 'paystack.email_token' );

					if ( $subscription_code && $email_token ) {
						$response = Paystack_Client::disable_subscription( $subscription_code, $email_token );

						if ( $response['success'] ) {
							$this->order->set_details( 'paystack.subscription_status', 'cancelled' );
							$this->order->set_details( 'paystack.canceled_at', \Voxel\utc()->format( 'Y-m-d H:i:s' ) );
							$this->order->set_details( 'paystack.canceled_by', get_current_user_id() );
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
						'message' => 'Subscription not found or missing token',
					] );
				},
			];
		}

		return $actions;
	}
}
