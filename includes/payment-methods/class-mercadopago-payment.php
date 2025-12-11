<?php

namespace VoxelPayPal\Payment_Methods;

use VoxelPayPal\MercadoPago_Client;
use VoxelPayPal\MercadoPago_Connect_Client;

if ( ! defined('ABSPATH') ) {
	exit;
}

/**
 * Mercado Pago Payment Method
 * Handles one-time payment processing for products and paid listings
 */
class MercadoPago_Payment extends \Voxel\Product_Types\Payment_Methods\Base_Payment_Method {

	public function get_type(): string {
		return 'mercadopago_payment';
	}

	public function get_label(): string {
		return _x( 'Mercado Pago payment', 'payment methods', 'voxel-payment-gateways' );
	}

	/**
	 * Process payment - creates Mercado Pago preference and redirects
	 */
	public function process_payment() {
		// Early debug - this should ALWAYS appear
		error_log( '=== MP PAYMENT START ===' );
		error_log( 'MP: process_payment() called at ' . date('Y-m-d H:i:s') );

		try {
			$customer = $this->order->get_customer();
			$line_items = $this->get_line_items();

			// Debug: Log line items to understand pricing
			error_log( 'Mercado Pago Line Items: ' . print_r( $line_items, true ) );

			// Check if this is a marketplace order
			$is_marketplace = MercadoPago_Connect_Client::is_marketplace_order( $this->order );
			$vendor_id = null;
			$vendor_access_token = null;

			error_log( 'MP: is_marketplace_order result: ' . ( $is_marketplace ? 'YES' : 'NO' ) );

			if ( $is_marketplace ) {
				$vendor_id = MercadoPago_Connect_Client::get_order_vendor_id( $this->order );
				error_log( 'MP: vendor_id: ' . ( $vendor_id ?? 'NULL' ) );

				if ( $vendor_id ) {
					$vendor_access_token = MercadoPago_Connect_Client::get_vendor_access_token( $vendor_id );
					error_log( 'MP: vendor_access_token: ' . ( $vendor_access_token ? 'EXISTS (len=' . strlen($vendor_access_token) . ')' : 'NULL' ) );
				}

				// If vendor not connected, fall back to direct payment
				if ( ! $vendor_access_token ) {
					error_log( 'MP: No vendor token - falling back to direct payment' );
					$is_marketplace = false;
				}
			}

			// Build preference data
			$preference_data = $this->build_preference_data( $line_items, $is_marketplace, $vendor_id );

			// Create Mercado Pago preference
			$response = MercadoPago_Client::create_preference( $preference_data, $vendor_access_token );

			if ( ! $response['success'] ) {
				throw new \Exception( $response['error'] ?? 'Failed to create Mercado Pago checkout' );
			}

			$preference = $response['data'] ?? null;

			if ( ! $preference || empty( $preference['id'] ) ) {
				throw new \Exception( 'Mercado Pago preference not found in response' );
			}

			// Store preference details in Voxel order
			$this->order->set_details( 'mercadopago.preference_id', $preference['id'] );
			$this->order->set_details( 'mercadopago.status', 'PENDING' );
			$this->order->set_details( 'mercadopago.capture_method', $this->get_capture_method() );

			// Calculate total first
			$total = 0;
			foreach ( $line_items as $item ) {
				$total += $item['amount'] * $item['quantity'];
			}
			$this->order->set_details( 'pricing.total', $total );

			if ( $is_marketplace && $vendor_id ) {
				$this->order->set_details( 'mercadopago.is_marketplace', true );
				$this->order->set_details( 'mercadopago.vendor_id', $vendor_id );

				// Store fee information (now $total is available)
				$earnings = MercadoPago_Connect_Client::calculate_vendor_earnings( (float) $total, $vendor_id );
				$this->order->set_details( 'marketplace.platform_fee', $earnings['platform_fee'] );
				$this->order->set_details( 'marketplace.vendor_earnings', $earnings['vendor_earnings'] );
			}

			$this->order->save();

			// Get checkout URL - use sandbox or live init_point
			$checkout_url = MercadoPago_Client::is_test_mode()
				? ( $preference['sandbox_init_point'] ?? $preference['init_point'] )
				: $preference['init_point'];

			// Log the preference response for debugging
			error_log( 'Mercado Pago Preference Response: ' . print_r( $preference, true ) );
			error_log( 'Mercado Pago Checkout URL: ' . $checkout_url );

			if ( ! $checkout_url ) {
				throw new \Exception( 'Mercado Pago checkout URL not found' );
			}

			return [
				'success' => true,
				'redirect_url' => $checkout_url,
			];

		} catch ( \Exception $e ) {
			error_log( '=== MP PAYMENT ERROR ===' );
			error_log( 'MP Exception: ' . $e->getMessage() );
			error_log( 'MP Exception trace: ' . $e->getTraceAsString() );

			return [
				'success' => false,
				'message' => _x( 'Mercado Pago payment failed', 'checkout', 'voxel-payment-gateways' ),
				'debug' => [
					'type' => 'mercadopago_error',
					'message' => $e->getMessage(),
				],
			];
		}
	}

	/**
	 * Build Mercado Pago preference data structure
	 */
	protected function build_preference_data( array $line_items, bool $is_marketplace = false, ?int $vendor_id = null ): array {
		$currency = $this->order->get_currency();
		$brand_name = \Voxel\get( 'payments.mercadopago.payments.brand_name', get_bloginfo( 'name' ) );

		error_log( 'MP build_preference_data: currency from order = ' . $currency );
		error_log( 'MP build_preference_data: is_marketplace = ' . ( $is_marketplace ? 'YES' : 'NO' ) );

		// Build items for Mercado Pago
		$mp_items = [];
		$total_amount = 0;

		foreach ( $line_items as $line_item ) {
			$unit_price = MercadoPago_Client::to_mercadopago_amount( $line_item['amount'] );
			$item_total = $unit_price * $line_item['quantity'];
			$total_amount += $item_total;

			$product_label = $line_item['product']['label'] ?? 'Product';

			$mp_items[] = [
				'id' => (string) $this->order->get_id() . '_' . count( $mp_items ),
				'title' => mb_substr( $product_label, 0, 256 ),
				'description' => mb_substr( $line_item['product']['description'] ?? '', 0, 256 ),
				'quantity' => (int) $line_item['quantity'],
				'currency_id' => $currency,
				'unit_price' => $unit_price,
			];
		}

		$preference_data = [
			'items' => $mp_items,
			'back_urls' => [
				'success' => $this->get_return_url( 'success' ),
				'failure' => $this->get_return_url( 'failure' ),
				'pending' => $this->get_return_url( 'pending' ),
			],
			'auto_return' => 'approved',
			'external_reference' => 'voxel_order_' . $this->order->get_id(),
			'notification_url' => $this->get_webhook_url(),
			'statement_descriptor' => mb_substr( $brand_name ?: get_bloginfo( 'name' ), 0, 22 ),
			'payment_methods' => [
				'excluded_payment_types' => [],
				'excluded_payment_methods' => [],
				'installments' => 12,
			],
			'binary_mode' => false,
		];

		// Add marketplace fee if this is a marketplace order
		if ( $is_marketplace && $vendor_id ) {
			$platform_fee = MercadoPago_Connect_Client::get_application_fee( $total_amount, $vendor_id );

			// Debug logging to trace marketplace fee calculation
			error_log( 'MP Marketplace Debug:' );
			error_log( '  - is_marketplace: true' );
			error_log( '  - vendor_id: ' . $vendor_id );
			error_log( '  - total_amount: ' . $total_amount );
			error_log( '  - fee_type: ' . \Voxel\get('payments.mercadopago.marketplace.fee_type') );
			error_log( '  - fee_value: ' . \Voxel\get('payments.mercadopago.marketplace.fee_value') );
			error_log( '  - marketplace_enabled: ' . ( \Voxel\get('payments.mercadopago.marketplace.enabled') ? 'yes' : 'no' ) );
			error_log( '  - calculated platform_fee: ' . $platform_fee );

			if ( $platform_fee > 0 ) {
				$preference_data['marketplace_fee'] = $platform_fee;
			}
		}

		return apply_filters( 'voxel/mercadopago/preference-data', $preference_data, $this->order );
	}

	/**
	 * Get return URL after Mercado Pago checkout
	 */
	protected function get_return_url( string $status = 'success' ): string {
		return add_query_arg( [
			'vx' => 1,
			'action' => 'mercadopago.checkout.' . $status,
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
	 * Get capture method
	 */
	public function get_capture_method(): string {
		$approval = \Voxel\get( 'payments.mercadopago.payments.order_approval', 'automatic' );
		return $approval === 'manual' ? 'manual' : 'automatic';
	}

	/**
	 * Handle Mercado Pago payment completion
	 */
	public function handle_payment_completed( array $payment ): void {
		$this->order->set_details( 'mercadopago.payment', $payment );
		$this->order->set_details( 'mercadopago.status', $payment['status'] ?? 'unknown' );

		// Extract payment details
		if ( ! empty( $payment['id'] ) ) {
			$this->order->set_transaction_id( (string) $payment['id'] );
			$this->order->set_details( 'mercadopago.payment_id', $payment['id'] );
		}

		// Get amount
		if ( ! empty( $payment['transaction_amount'] ) ) {
			$amount = MercadoPago_Client::from_mercadopago_amount( $payment['transaction_amount'] );
			$this->order->set_details( 'pricing.total', $amount );
		}

		// Set order status based on payment status
		$payment_status = $payment['status'] ?? '';

		switch ( $payment_status ) {
			case 'approved':
				if ( $this->get_capture_method() === 'manual' ) {
					$this->order->set_status( \Voxel\ORDER_PENDING_APPROVAL );
				} else {
					$this->order->set_status( \Voxel\ORDER_COMPLETED );
				}
				break;

			case 'authorized':
				$this->order->set_status( \Voxel\ORDER_PENDING_APPROVAL );
				break;

			case 'pending':
			case 'in_process':
				$this->order->set_status( \Voxel\ORDER_PENDING_PAYMENT );
				break;

			case 'rejected':
			case 'cancelled':
			case 'refunded':
			case 'charged_back':
				$this->order->set_status( \Voxel\ORDER_CANCELED );
				break;

			default:
				$this->order->set_status( \Voxel\ORDER_PENDING_PAYMENT );
				break;
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
		$payment_id = $this->order->get_details( 'mercadopago.payment_id' );
		if ( ! $payment_id ) {
			return;
		}

		$response = MercadoPago_Client::get_payment( $payment_id );

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
					// For Mercado Pago, payment is already captured via checkout
					// Just update the order status
					$this->order->set_status( \Voxel\ORDER_COMPLETED );
					$this->order->set_details( 'mercadopago.approved_at', \Voxel\utc()->format( 'Y-m-d H:i:s' ) );
					$this->order->set_details( 'mercadopago.approved_by', get_current_user_id() );
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
					$payment_id = $this->order->get_details( 'mercadopago.payment_id' );

					if ( $payment_id ) {
						$refund_response = MercadoPago_Client::refund_payment( $payment_id );

						if ( $refund_response['success'] ) {
							$this->order->set_details( 'mercadopago.refund_id', $refund_response['data']['id'] ?? null );
						}
					}

					$this->order->set_status( \Voxel\ORDER_CANCELED );
					$this->order->set_details( 'mercadopago.declined_at', \Voxel\utc()->format( 'Y-m-d H:i:s' ) );
					$this->order->set_details( 'mercadopago.declined_by', get_current_user_id() );
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
					$payment_id = $this->order->get_details( 'mercadopago.payment_id' );

					if ( $payment_id ) {
						$refund_response = MercadoPago_Client::refund_payment( $payment_id );

						if ( $refund_response['success'] ) {
							$this->order->set_details( 'mercadopago.refund_id', $refund_response['data']['id'] ?? null );
						}
					}

					$this->order->set_status( \Voxel\ORDER_CANCELED );
					$this->order->set_details( 'mercadopago.canceled_at', \Voxel\utc()->format( 'Y-m-d H:i:s' ) );
					$this->order->set_details( 'mercadopago.canceled_by', get_current_user_id() );
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
