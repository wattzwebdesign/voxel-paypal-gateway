<?php

namespace VoxelPayPal\Payment_Methods;

use VoxelPayPal\Square_Client;

if ( ! defined('ABSPATH') ) {
	exit;
}

/**
 * Square Payment Method
 * Handles one-time payment processing for products and paid listings
 */
class Square_Payment extends \Voxel\Product_Types\Payment_Methods\Base_Payment_Method {

	public function get_type(): string {
		return 'square_payment';
	}

	public function get_label(): string {
		return _x( 'Square payment', 'payment methods', 'voxel-payment-gateways' );
	}

	/**
	 * Process payment - creates Square checkout link and redirects
	 */
	public function process_payment() {
		try {
			$customer = $this->order->get_customer();
			$line_items = $this->get_line_items();

			// Build Square checkout data
			$checkout_data = $this->build_checkout_data( $line_items );

			// Create Square payment link
			$response = Square_Client::create_checkout_link( $checkout_data );

			if ( ! $response['success'] ) {
				throw new \Exception( $response['error'] ?? 'Failed to create Square checkout' );
			}

			$payment_link = $response['data']['payment_link'] ?? null;

			if ( ! $payment_link ) {
				throw new \Exception( 'Square payment link not found in response' );
			}

			// Store Square payment link details in Voxel order
			$this->order->set_details( 'square.payment_link_id', $payment_link['id'] );
			$this->order->set_details( 'square.order_id', $payment_link['order_id'] ?? null );
			$this->order->set_details( 'square.status', 'PENDING' );
			$this->order->set_details( 'square.capture_method', $this->get_capture_method() );

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
				'message' => _x( 'Square payment failed', 'checkout', 'voxel-payment-gateways' ),
				'debug' => [
					'type' => 'square_error',
					'message' => $e->getMessage(),
				],
			];
		}
	}

	/**
	 * Build Square checkout data structure
	 */
	protected function build_checkout_data( array $line_items ): array {
		$currency = $this->order->get_currency();
		$brand_name = \Voxel\get( 'payments.square.payments.brand_name', get_bloginfo( 'name' ) );
		$location_id = Square_Client::get_location_id();

		// Build line items for Square and collect product names
		$square_line_items = [];
		$total_amount = 0;
		$product_names = [];

		foreach ( $line_items as $line_item ) {
			$unit_amount_cents = Square_Client::to_square_amount( $line_item['amount'] );
			$item_total = $unit_amount_cents * $line_item['quantity'];
			$total_amount += $item_total;

			$product_label = $line_item['product']['label'] ?? '';
			$product_names[] = $product_label;

			$square_line_items[] = [
				'name' => mb_substr( $product_label, 0, 512 ),
				'quantity' => (string) $line_item['quantity'],
				'base_price_money' => [
					'amount' => $unit_amount_cents,
					'currency' => $currency,
				],
			];
		}

		// Build a descriptive order name
		$order_name = '';
		if ( ! empty( $product_names ) ) {
			// Use the first product name, truncated if needed
			$first_product = $product_names[0];
			if ( count( $product_names ) > 1 ) {
				$order_name = sprintf( '%s (+%d more) - Order #%d', mb_substr( $first_product, 0, 80 ), count( $product_names ) - 1, $this->order->get_id() );
			} else {
				$order_name = sprintf( '%s - Order #%d', mb_substr( $first_product, 0, 100 ), $this->order->get_id() );
			}
		} else {
			$order_name = sprintf( 'Order #%d', $this->order->get_id() );
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
		$customer = $this->order->get_customer();
		if ( $customer && method_exists( $customer, 'get_email' ) && $customer->get_email() ) {
			$checkout_data['pre_populated_data']['buyer_email'] = $customer->get_email();
		}

		// Add description if available
		if ( ! empty( $line_items[0]['product']['description'] ) ) {
			$checkout_data['description'] = mb_substr( $line_items[0]['product']['description'], 0, 60 );
		}

		// Store reference to Voxel order
		$checkout_data['payment_note'] = 'voxel_order_' . $this->order->get_id();

		return apply_filters( 'voxel/square/checkout-data', $checkout_data, $this->order );
	}

	/**
	 * Get return URL after Square checkout
	 */
	protected function get_return_url(): string {
		return add_query_arg( [
			'vx' => 1,
			'action' => 'square.checkout.success',
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
			'action' => 'square.checkout.cancel',
			'order_id' => $this->order->get_id(),
			'redirect_to' => rawurlencode( $redirect_url ),
		], home_url('/') );
	}

	/**
	 * Get capture method
	 */
	public function get_capture_method(): string {
		$approval = \Voxel\get( 'payments.square.payments.order_approval', 'automatic' );
		return $approval === 'manual' ? 'manual' : 'automatic';
	}

	/**
	 * Handle Square order completion
	 */
	public function handle_order_completed( array $square_payment ): void {
		$this->order->set_details( 'square.payment', $square_payment );
		$this->order->set_details( 'square.status', $square_payment['status'] ?? 'COMPLETED' );

		// Extract payment details
		if ( ! empty( $square_payment['id'] ) ) {
			$this->order->set_transaction_id( $square_payment['id'] );
			$this->order->set_details( 'square.payment_id', $square_payment['id'] );
		}

		// Get amount
		if ( ! empty( $square_payment['amount_money'] ) ) {
			$amount = Square_Client::from_square_amount( $square_payment['amount_money']['amount'] );
			$this->order->set_details( 'pricing.total', $amount );
		}

		// Set order status based on payment status
		$payment_status = $square_payment['status'] ?? '';

		if ( $payment_status === 'COMPLETED' ) {
			if ( $this->get_capture_method() === 'manual' ) {
				$this->order->set_status( \Voxel\ORDER_PENDING_APPROVAL );
			} else {
				$this->order->set_status( \Voxel\ORDER_COMPLETED );
			}
		} elseif ( $payment_status === 'APPROVED' ) {
			$this->order->set_status( \Voxel\ORDER_PENDING_APPROVAL );
		} elseif ( in_array( $payment_status, [ 'FAILED', 'CANCELED' ], true ) ) {
			$this->order->set_status( \Voxel\ORDER_CANCELED );
		} else {
			$this->order->set_status( \Voxel\ORDER_PENDING_PAYMENT );
		}

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
		$payment_id = $this->order->get_details( 'square.payment_id' );
		if ( ! $payment_id ) {
			return;
		}

		$response = Square_Client::get_payment( $payment_id );

		if ( $response['success'] && ! empty( $response['data']['payment'] ) ) {
			$this->handle_order_completed( $response['data']['payment'] );
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
					// For Square, payment is already captured via checkout
					// Just update the order status
					$this->order->set_status( \Voxel\ORDER_COMPLETED );
					$this->order->set_details( 'square.approved_at', \Voxel\utc()->format( 'Y-m-d H:i:s' ) );
					$this->order->set_details( 'square.approved_by', get_current_user_id() );
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
					$payment_id = $this->order->get_details( 'square.payment_id' );
					$amount = $this->order->get_details( 'pricing.total' );
					$currency = $this->order->get_currency();

					if ( $payment_id && $amount ) {
						$refund_response = Square_Client::refund_payment( [
							'idempotency_key' => Square_Client::generate_idempotency_key(),
							'payment_id' => $payment_id,
							'amount_money' => [
								'amount' => Square_Client::to_square_amount( $amount ),
								'currency' => $currency,
							],
							'reason' => 'Order declined by vendor',
						] );

						if ( $refund_response['success'] ) {
							$this->order->set_details( 'square.refund_id', $refund_response['data']['refund']['id'] ?? null );
						}
					}

					$this->order->set_status( \Voxel\ORDER_CANCELED );
					$this->order->set_details( 'square.declined_at', \Voxel\utc()->format( 'Y-m-d H:i:s' ) );
					$this->order->set_details( 'square.declined_by', get_current_user_id() );
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
					$payment_id = $this->order->get_details( 'square.payment_id' );
					$amount = $this->order->get_details( 'pricing.total' );
					$currency = $this->order->get_currency();

					if ( $payment_id && $amount ) {
						$refund_response = Square_Client::refund_payment( [
							'idempotency_key' => Square_Client::generate_idempotency_key(),
							'payment_id' => $payment_id,
							'amount_money' => [
								'amount' => Square_Client::to_square_amount( $amount ),
								'currency' => $currency,
							],
							'reason' => 'Customer requested cancellation',
						] );

						if ( $refund_response['success'] ) {
							$this->order->set_details( 'square.refund_id', $refund_response['data']['refund']['id'] ?? null );
						}
					}

					$this->order->set_status( \Voxel\ORDER_CANCELED );
					$this->order->set_details( 'square.canceled_at', \Voxel\utc()->format( 'Y-m-d H:i:s' ) );
					$this->order->set_details( 'square.canceled_by', get_current_user_id() );
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
