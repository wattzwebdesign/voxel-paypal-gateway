<?php

namespace VoxelPayPal\Payment_Methods;

use VoxelPayPal\PayPal_Client;

if ( ! defined('ABSPATH') ) {
	exit;
}

/**
 * PayPal Payment Method
 * Handles the actual payment processing
 */
class PayPal_Payment extends \Voxel\Product_Types\Payment_Methods\Base_Payment_Method {

	public function get_type(): string {
		return 'paypal_payment';
	}

	public function get_label(): string {
		return _x( 'PayPal payment', 'payment methods', 'voxel-paypal-gateway' );
	}

	/**
	 * Process payment - creates PayPal order and redirects to PayPal
	 */
	public function process_payment() {
		try {
			$customer = $this->order->get_customer();
			$line_items = $this->get_line_items();

			// Build PayPal order data
			$paypal_order_data = $this->build_paypal_order_data( $line_items );

			// Create PayPal order
			$response = PayPal_Client::create_order( $paypal_order_data );

			if ( ! $response['success'] ) {
				throw new \Exception( $response['error'] ?? 'Failed to create PayPal order' );
			}

			$paypal_order = $response['data'];

			// Store PayPal order ID in Voxel order
			$this->order->set_details( 'paypal.order_id', $paypal_order['id'] );
			$this->order->set_details( 'paypal.status', $paypal_order['status'] );
			$this->order->set_details( 'paypal.capture_method', $this->get_capture_method() );

			// Calculate total
			$total = 0;
			foreach ( $line_items as $item ) {
				$total += $item['amount'] * $item['quantity'];
			}
			$this->order->set_details( 'pricing.total', $total );

			$this->order->save();

			// Find approval URL
			$approval_url = null;
			foreach ( $paypal_order['links'] as $link ) {
				if ( $link['rel'] === 'approve' ) {
					$approval_url = $link['href'];
					break;
				}
			}

			if ( ! $approval_url ) {
				throw new \Exception( 'PayPal approval URL not found' );
			}

			return [
				'success' => true,
				'redirect_url' => $approval_url,
			];

		} catch ( \Exception $e ) {
			return [
				'success' => false,
				'message' => _x( 'PayPal payment failed', 'checkout', 'voxel-paypal-gateway' ),
				'debug' => [
					'type' => 'paypal_error',
					'message' => $e->getMessage(),
				],
			];
		}
	}

	/**
	 * Build PayPal order data structure
	 */
	protected function build_paypal_order_data( array $line_items ): array {
		$currency = $this->order->get_currency();
		$brand_name = \Voxel\get( 'payments.paypal.payments.brand_name', get_bloginfo( 'name' ) );
		$landing_page = \Voxel\get( 'payments.paypal.payments.landing_page', 'NO_PREFERENCE' );

		// Calculate amounts
		$item_total = 0;
		$items = [];

		foreach ( $line_items as $line_item ) {
			$unit_amount = number_format( $line_item['amount'], 2, '.', '' );
			$item_total += floatval( $unit_amount ) * $line_item['quantity'];

			$items[] = [
				'name' => mb_substr( $line_item['product']['label'], 0, 127 ),
				'description' => ! empty( $line_item['product']['description'] )
					? mb_substr( $line_item['product']['description'], 0, 127 )
					: '',
				'unit_amount' => [
					'currency_code' => $currency,
					'value' => $unit_amount,
				],
				'quantity' => (string) $line_item['quantity'],
			];
		}

		$amount_value = number_format( $item_total, 2, '.', '' );

		$order_data = [
			'intent' => $this->get_capture_method() === 'manual' ? 'AUTHORIZE' : 'CAPTURE',
			'purchase_units' => [
				[
					'reference_id' => 'voxel_order_' . $this->order->get_id(),
					'custom_id' => 'voxel_order_' . $this->order->get_id(),
					'description' => sprintf( 'Order #%d', $this->order->get_id() ),
					'amount' => [
						'currency_code' => $currency,
						'value' => $amount_value,
						'breakdown' => [
							'item_total' => [
								'currency_code' => $currency,
								'value' => $amount_value,
							],
						],
					],
					'items' => $items,
				],
			],
			'application_context' => [
				'brand_name' => $brand_name,
				'landing_page' => $landing_page,
				'user_action' => 'PAY_NOW',
				'return_url' => $this->get_return_url(),
				'cancel_url' => $this->get_cancel_url(),
			],
		];

		return apply_filters( 'voxel/paypal/order-data', $order_data, $this->order );
	}

	/**
	 * Get return URL after PayPal approval
	 */
	protected function get_return_url(): string {
		return add_query_arg( [
			'vx' => 1,
			'action' => 'paypal.checkout.success',
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
			'action' => 'paypal.checkout.cancel',
			'order_id' => $this->order->get_id(),
			'redirect_to' => rawurlencode( $redirect_url ),
		], home_url('/') );
	}

	/**
	 * Get capture method
	 */
	public function get_capture_method(): string {
		$approval = \Voxel\get( 'payments.paypal.payments.order_approval', 'automatic' );
		return $approval === 'manual' ? 'manual' : 'automatic';
	}

	/**
	 * Handle PayPal order completion
	 */
	public function handle_order_completed( array $paypal_order ): void {
		$this->order->set_details( 'paypal.order', $paypal_order );
		$this->order->set_details( 'paypal.status', $paypal_order['status'] );

		// Extract capture or authorization details
		if ( ! empty( $paypal_order['purchase_units'][0]['payments']['captures'] ) ) {
			$capture = $paypal_order['purchase_units'][0]['payments']['captures'][0];
			$this->order->set_transaction_id( $capture['id'] );
			$this->order->set_details( 'paypal.capture_id', $capture['id'] );

			$amount = floatval( $capture['amount']['value'] );
			$this->order->set_details( 'pricing.total', $amount );

			// Set order status
			if ( $capture['status'] === 'COMPLETED' ) {
				$this->order->set_status( \Voxel\ORDER_COMPLETED );

				// Process vendor payout if marketplace order
				$this->process_marketplace_payout();
			} elseif ( $capture['status'] === 'PENDING' ) {
				$this->order->set_status( \Voxel\ORDER_PENDING_PAYMENT );
			}
		} elseif ( ! empty( $paypal_order['purchase_units'][0]['payments']['authorizations'] ) ) {
			$authorization = $paypal_order['purchase_units'][0]['payments']['authorizations'][0];
			$this->order->set_transaction_id( $authorization['id'] );
			$this->order->set_details( 'paypal.authorization_id', $authorization['id'] );

			$amount = floatval( $authorization['amount']['value'] );
			$this->order->set_details( 'pricing.total', $amount );

			// Authorization requires manual capture
			$this->order->set_status( \Voxel\ORDER_PENDING_APPROVAL );
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
		$paypal_order_id = $this->order->get_details( 'paypal.order_id' );
		if ( ! $paypal_order_id ) {
			return;
		}

		$response = PayPal_Client::get_order( $paypal_order_id );

		if ( $response['success'] && ! empty( $response['data'] ) ) {
			$this->handle_order_completed( $response['data'] );
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
				'label' => _x( 'Approve', 'order actions', 'voxel-paypal-gateway' ),
				'type' => 'primary',
				'handler' => function() {
					$authorization_id = $this->order->get_details( 'paypal.authorization_id' );
					if ( ! $authorization_id ) {
						return wp_send_json( [
							'success' => false,
							'message' => 'Authorization ID not found',
						] );
					}

					// Capture the authorization
					$response = PayPal_Client::make_request( "/v2/payments/authorizations/{$authorization_id}/capture", [
						'method' => 'POST',
					] );

					if ( $response['success'] ) {
						$this->order->set_status( \Voxel\ORDER_COMPLETED );
						$this->order->set_details( 'paypal.capture_id', $response['data']['id'] );
						$this->order->save();

						( new \Voxel\Events\Products\Orders\Vendor_Approved_Order_Event )->dispatch( $this->order->get_id() );

						return wp_send_json( [
							'success' => true,
						] );
					}

					return wp_send_json( [
						'success' => false,
						'message' => $response['error'] ?? 'Failed to capture payment',
					] );
				},
			];

			$actions[] = [
				'action' => 'vendor.decline',
				'label' => _x( 'Decline', 'order actions', 'voxel-paypal-gateway' ),
				'handler' => function() {
					$this->order->set_status( \Voxel\ORDER_CANCELED );
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
				'label' => _x( 'Cancel order', 'order customer actions', 'voxel-paypal-gateway' ),
				'handler' => function() {
					$this->order->set_status( \Voxel\ORDER_CANCELED );
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

	/**
	 * Process marketplace payout to vendor
	 */
	protected function process_marketplace_payout(): void {
		// Check if this is a marketplace order
		$is_marketplace_order = \VoxelPayPal\PayPal_Connect_Client::is_marketplace_order( $this->order );

		if ( ! $is_marketplace_order ) {
			return;
		}

		// Check auto-payout setting
		$auto_payout = (bool) \Voxel\get( 'payments.paypal.marketplace.auto_payout', '1' );

		if ( ! $auto_payout ) {
			return;
		}

		// Check payout delay
		$payout_delay_days = intval( \Voxel\get( 'payments.paypal.marketplace.payout_delay_days', 0 ) );

		if ( $payout_delay_days > 0 ) {
			// Schedule payout for later
			$this->schedule_delayed_payout( $payout_delay_days );
			return;
		}

		// Process payout immediately
		$result = \VoxelPayPal\PayPal_Connect_Client::process_order_payout( $this->order );

		if ( ! $result['success'] ) {
			// Log error but don't fail the order
			error_log( sprintf(
				'PayPal Payout Error: Failed to create payout for order #%d: %s',
				$this->order->get_id(),
				$result['error'] ?? 'Unknown error'
			) );

			// Store error for admin review
			$this->order->set_details( 'marketplace.payout_error', $result['error'] ?? 'Unknown error' );
			$this->order->save();
		}
	}

	/**
	 * Schedule delayed payout
	 */
	protected function schedule_delayed_payout( int $delay_days ): void {
		$timestamp = time() + ( $delay_days * DAY_IN_SECONDS );

		wp_schedule_single_event( $timestamp, 'voxel/paypal/process-delayed-payout', [
			'order_id' => $this->order->get_id(),
		] );

		$this->order->set_details( 'marketplace.payout_scheduled_at', $timestamp );
		$this->order->save();
	}
}
