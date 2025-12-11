<?php

namespace VoxelPayPal\Payment_Methods;

use VoxelPayPal\Paystack_Client;
use VoxelPayPal\Paystack_Connect_Client;

if ( ! defined('ABSPATH') ) {
	exit;
}

/**
 * Paystack Payment Method
 * Handles one-time payment processing for products and paid listings
 */
class Paystack_Payment extends \Voxel\Product_Types\Payment_Methods\Base_Payment_Method {

	public function get_type(): string {
		return 'paystack_payment';
	}

	public function get_label(): string {
		return _x( 'Paystack payment', 'payment methods', 'voxel-payment-gateways' );
	}

	/**
	 * Process payment - initializes Paystack transaction and redirects
	 */
	public function process_payment() {
		try {
			error_log( '=== PAYSTACK PAYMENT START ===' );
			error_log( 'Paystack: process_payment() called at ' . date('Y-m-d H:i:s') );
			error_log( 'Paystack: Order ID: ' . $this->order->get_id() );

			$customer = $this->order->get_customer();
			error_log( 'Paystack: Customer email: ' . ( $customer ? $customer->get_email() : 'NO CUSTOMER' ) );

			$line_items = $this->get_line_items();
			error_log( 'Paystack: Line items count: ' . count( $line_items ) );
			error_log( 'Paystack: Line items: ' . print_r( $line_items, true ) );

			// Check if this is a marketplace order
			$is_marketplace = Paystack_Connect_Client::is_marketplace_order( $this->order );
			$vendor_id = null;
			$subaccount_code = null;

			if ( $is_marketplace ) {
				$vendor_id = Paystack_Connect_Client::get_order_vendor_id( $this->order );

				if ( $vendor_id ) {
					$subaccount_code = Paystack_Connect_Client::get_vendor_subaccount_code( $vendor_id );
				}

				// If vendor not connected, fall back to direct payment
				if ( ! $subaccount_code ) {
					$is_marketplace = false;
				}
			}

			// Build transaction data
			$transaction_data = $this->build_transaction_data( $line_items, $is_marketplace, $vendor_id, $subaccount_code );
			error_log( 'Paystack: Transaction data: ' . print_r( $transaction_data, true ) );

			// Initialize Paystack transaction
			$response = Paystack_Client::initialize_transaction( $transaction_data );
			error_log( 'Paystack: API Response: ' . print_r( $response, true ) );

			if ( ! $response['success'] ) {
				error_log( 'Paystack: ERROR - ' . ( $response['error'] ?? 'Unknown error' ) );
				throw new \Exception( $response['error'] ?? 'Failed to initialize Paystack transaction' );
			}

			$transaction = $response['data'] ?? null;

			if ( ! $transaction || empty( $transaction['authorization_url'] ) ) {
				throw new \Exception( 'Paystack authorization URL not found in response' );
			}

			// Store transaction details in Voxel order
			$this->order->set_details( 'paystack.reference', $transaction['reference'] );
			$this->order->set_details( 'paystack.access_code', $transaction['access_code'] );
			$this->order->set_details( 'paystack.status', 'PENDING' );
			$this->order->set_details( 'paystack.capture_method', $this->get_capture_method() );

			// Calculate and store total
			$total = 0;
			foreach ( $line_items as $item ) {
				$total += $item['amount'] * $item['quantity'];
			}
			$this->order->set_details( 'pricing.total', $total );

			if ( $is_marketplace && $vendor_id ) {
				$this->order->set_details( 'paystack.is_marketplace', true );
				$this->order->set_details( 'paystack.vendor_id', $vendor_id );
				$this->order->set_details( 'paystack.subaccount_code', $subaccount_code );

				// Store fee information
				$earnings = Paystack_Connect_Client::calculate_vendor_earnings( (float) $total, $vendor_id );
				$this->order->set_details( 'marketplace.platform_fee', $earnings['platform_fee'] );
				$this->order->set_details( 'marketplace.vendor_earnings', $earnings['vendor_earnings'] );
			}

			$this->order->save();

			return [
				'success' => true,
				'redirect_url' => $transaction['authorization_url'],
			];

		} catch ( \Exception $e ) {
			return [
				'success' => false,
				'message' => _x( 'Paystack payment failed', 'checkout', 'voxel-payment-gateways' ),
				'debug' => [
					'type' => 'paystack_error',
					'message' => $e->getMessage(),
				],
			];
		}
	}

	/**
	 * Build Paystack transaction data structure
	 */
	protected function build_transaction_data( array $line_items, bool $is_marketplace = false, ?int $vendor_id = null, ?string $subaccount_code = null ): array {
		$customer = $this->order->get_customer();

		// Use currency from Paystack settings (must match Paystack account country)
		$currency = strtoupper( \Voxel\get( 'payments.paystack.currency', 'NGN' ) );

		// Calculate total amount in kobo/pesewas (smallest currency unit)
		$total_amount = 0;
		foreach ( $line_items as $line_item ) {
			$total_amount += $line_item['amount'] * $line_item['quantity'];
		}

		// Convert to smallest currency unit
		$amount_in_kobo = Paystack_Client::to_paystack_amount( $total_amount );

		// Get customer email
		$customer_email = $customer ? $customer->get_email() : '';

		if ( empty( $customer_email ) ) {
			throw new \Exception( 'Customer email is required for Paystack payments' );
		}

		// Generate unique reference
		$reference = Paystack_Client::generate_reference( 'vxl_' . $this->order->get_id() );

		$transaction_data = [
			'email' => $customer_email,
			'amount' => $amount_in_kobo,
			'currency' => $currency,
			'reference' => $reference,
			'callback_url' => $this->get_return_url(),
			'metadata' => [
				'order_id' => $this->order->get_id(),
				'custom_fields' => [
					[
						'display_name' => 'Order ID',
						'variable_name' => 'order_id',
						'value' => (string) $this->order->get_id(),
					],
				],
			],
		];

		// Add line items to metadata
		$cart_items = [];
		foreach ( $line_items as $line_item ) {
			$cart_items[] = [
				'name' => $line_item['product']['label'] ?? 'Product',
				'quantity' => (int) $line_item['quantity'],
				'amount' => Paystack_Client::to_paystack_amount( $line_item['amount'] ),
			];
		}
		$transaction_data['metadata']['cart'] = $cart_items;

		// Add marketplace split if applicable
		if ( $is_marketplace && $subaccount_code && $vendor_id ) {
			$transaction_data['subaccount'] = $subaccount_code;

			// Calculate platform fee
			$platform_fee = Paystack_Connect_Client::get_application_fee( $total_amount, $vendor_id );
			$platform_fee_in_kobo = Paystack_Client::to_paystack_amount( $platform_fee );

			if ( $platform_fee_in_kobo > 0 ) {
				// transaction_charge is the flat fee that goes to the main account
				$transaction_data['transaction_charge'] = $platform_fee_in_kobo;
			}

			// Set fee bearer
			$fee_bearer = \Voxel\get( 'payments.paystack.marketplace.fee_bearer', 'account' );
			$transaction_data['bearer'] = $fee_bearer;
		}

		return apply_filters( 'voxel/paystack/transaction-data', $transaction_data, $this->order );
	}

	/**
	 * Get return URL after Paystack checkout
	 */
	protected function get_return_url(): string {
		return add_query_arg( [
			'vx' => 1,
			'action' => 'paystack.checkout.callback',
			'order_id' => $this->order->get_id(),
		], home_url('/') );
	}

	/**
	 * Get webhook URL
	 */
	protected function get_webhook_url(): string {
		return add_query_arg( [
			'vx' => 1,
			'action' => 'paystack.webhooks',
		], home_url('/') );
	}

	/**
	 * Get capture method
	 */
	public function get_capture_method(): string {
		$approval = \Voxel\get( 'payments.paystack.payments.order_approval', 'automatic' );
		return $approval === 'manual' ? 'manual' : 'automatic';
	}

	/**
	 * Handle Paystack payment completion
	 */
	public function handle_payment_completed( array $transaction ): void {
		$this->order->set_details( 'paystack.transaction', $transaction );
		$this->order->set_details( 'paystack.status', $transaction['status'] ?? 'unknown' );

		// Extract transaction details
		if ( ! empty( $transaction['id'] ) ) {
			$this->order->set_transaction_id( (string) $transaction['id'] );
			$this->order->set_details( 'paystack.transaction_id', $transaction['id'] );
		}

		if ( ! empty( $transaction['reference'] ) ) {
			$this->order->set_details( 'paystack.reference', $transaction['reference'] );
		}

		// Get amount (convert from kobo/pesewas)
		if ( ! empty( $transaction['amount'] ) ) {
			$amount = Paystack_Client::from_paystack_amount( (int) $transaction['amount'] );
			$this->order->set_details( 'pricing.total', $amount );
		}

		// Store authorization for future recurring charges
		if ( ! empty( $transaction['authorization'] ) ) {
			$this->order->set_details( 'paystack.authorization', $transaction['authorization'] );
		}

		// Store customer info
		if ( ! empty( $transaction['customer'] ) ) {
			$this->order->set_details( 'paystack.customer', $transaction['customer'] );
		}

		// Set order status based on transaction status
		$transaction_status = $transaction['status'] ?? '';

		switch ( $transaction_status ) {
			case 'success':
				if ( $this->get_capture_method() === 'manual' ) {
					$this->order->set_status( \Voxel\ORDER_PENDING_APPROVAL );
				} else {
					$this->order->set_status( \Voxel\ORDER_COMPLETED );
				}
				break;

			case 'abandoned':
			case 'failed':
				$this->order->set_status( \Voxel\ORDER_CANCELED );
				break;

			default:
				$this->order->set_status( \Voxel\ORDER_PENDING_PAYMENT );
				break;
		}

		$this->order->set_details( 'paystack.last_synced_at', \Voxel\utc()->format( 'Y-m-d H:i:s' ) );
		$this->order->save();
	}

	/**
	 * Sync with Paystack
	 */
	public function should_sync(): bool {
		return ! $this->order->get_details( 'paystack.last_synced_at' );
	}

	public function sync(): void {
		$reference = $this->order->get_details( 'paystack.reference' );
		if ( ! $reference ) {
			return;
		}

		$response = Paystack_Client::verify_transaction( $reference );

		if ( $response['success'] && ! empty( $response['data'] ) ) {
			$this->handle_payment_completed( $response['data'] );
		}
	}

	/**
	 * Vendor actions
	 */
	public function get_vendor_actions(): array {
		$actions = [];

		if ( $this->order->get_status() === \Voxel\ORDER_PENDING_APPROVAL ) {
			$actions[] = [
				'action' => 'vendor.approve',
				'label' => _x( 'Approve', 'order actions', 'voxel-payment-gateways' ),
				'type' => 'primary',
				'handler' => function() {
					// For Paystack, payment is already captured via checkout
					// Just update the order status
					$this->order->set_status( \Voxel\ORDER_COMPLETED );
					$this->order->set_details( 'paystack.approved_at', \Voxel\utc()->format( 'Y-m-d H:i:s' ) );
					$this->order->set_details( 'paystack.approved_by', get_current_user_id() );
					$this->order->save();

					( new \Voxel\Events\Products\Orders\Vendor_Approved_Order_Event )->dispatch( $this->order->get_id() );

					return wp_send_json( [
						'success' => true,
					] );
				},
			];

			$actions[] = [
				'action' => 'vendor.decline',
				'label' => _x( 'Decline', 'order actions', 'voxel-payment-gateways' ),
				'handler' => function() {
					// Attempt to refund the payment
					$reference = $this->order->get_details( 'paystack.reference' );

					if ( $reference ) {
						$refund_response = Paystack_Client::create_refund( $reference );

						if ( $refund_response['success'] ) {
							$this->order->set_details( 'paystack.refund_id', $refund_response['data']['id'] ?? null );
						}
					}

					$this->order->set_status( \Voxel\ORDER_CANCELED );
					$this->order->set_details( 'paystack.declined_at', \Voxel\utc()->format( 'Y-m-d H:i:s' ) );
					$this->order->set_details( 'paystack.declined_by', get_current_user_id() );
					$this->order->save();

					( new \Voxel\Events\Products\Orders\Vendor_Declined_Order_Event )->dispatch( $this->order->get_id() );

					return wp_send_json( [
						'success' => true,
					] );
				},
			];
		}

		return $actions;
	}

	/**
	 * Customer actions
	 */
	public function get_customer_actions(): array {
		$actions = [];

		if ( $this->order->get_status() === \Voxel\ORDER_PENDING_APPROVAL ) {
			$actions[] = [
				'action' => 'customer.cancel',
				'label' => _x( 'Cancel order', 'order customer actions', 'voxel-payment-gateways' ),
				'handler' => function() {
					// Attempt to refund the payment
					$reference = $this->order->get_details( 'paystack.reference' );

					if ( $reference ) {
						$refund_response = Paystack_Client::create_refund( $reference );

						if ( $refund_response['success'] ) {
							$this->order->set_details( 'paystack.refund_id', $refund_response['data']['id'] ?? null );
						}
					}

					$this->order->set_status( \Voxel\ORDER_CANCELED );
					$this->order->set_details( 'paystack.canceled_at', \Voxel\utc()->format( 'Y-m-d H:i:s' ) );
					$this->order->set_details( 'paystack.canceled_by', get_current_user_id() );
					$this->order->save();

					( new \Voxel\Events\Products\Orders\Customer_Canceled_Order_Event )->dispatch( $this->order->get_id() );

					return wp_send_json( [
						'success' => true,
					] );
				},
			];
		}

		return $actions;
	}
}
