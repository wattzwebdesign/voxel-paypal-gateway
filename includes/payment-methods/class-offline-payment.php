<?php

namespace VoxelPayPal\Payment_Methods;

if ( ! defined('ABSPATH') ) {
	exit;
}

/**
 * Offline Payment Method
 * Handles offline payments like Cash on Delivery, Bank Transfer, etc.
 */
class Offline_Payment extends \Voxel\Product_Types\Payment_Methods\Base_Payment_Method {

	public function get_type(): string {
		return 'offline_payment';
	}

	public function get_label(): string {
		return _x( 'Offline payment', 'payment methods', 'voxel-payment-gateways' );
	}

	/**
	 * Process payment - sets order to pending and returns success immediately
	 */
	public function process_payment() {
		try {
			$line_items = $this->get_line_items();

			// Calculate total
			$total = 0;
			foreach ( $line_items as $item ) {
				$total += $item['amount'] * $item['quantity'];
			}

			// Store order details
			$this->order->set_details( 'offline.payment_method', 'offline' );
			$this->order->set_details( 'offline.created_at', \Voxel\utc()->format( 'Y-m-d H:i:s' ) );
			$this->order->set_details( 'pricing.total', $total );

			// Generate a transaction ID for tracking
			$transaction_id = 'offline_' . $this->order->get_id() . '_' . time();
			$this->order->set_transaction_id( $transaction_id );

			// Set order status based on settings
			$order_status = \Voxel\get( 'payments.offline.order_status', 'pending_payment' );

			if ( $order_status === 'pending_approval' ) {
				$this->order->set_status( \Voxel\ORDER_PENDING_APPROVAL );
			} else {
				$this->order->set_status( \Voxel\ORDER_PENDING_PAYMENT );
			}

			$this->order->save();

			// Get the order confirmation page URL
			$redirect_url = $this->get_order_confirmation_url();

			return [
				'success' => true,
				'redirect_url' => $redirect_url,
			];

		} catch ( \Exception $e ) {
			return [
				'success' => false,
				'message' => _x( 'Failed to place order', 'checkout', 'voxel-payment-gateways' ),
				'debug' => [
					'type' => 'offline_error',
					'message' => $e->getMessage(),
				],
			];
		}
	}

	/**
	 * Get order confirmation URL
	 */
	protected function get_order_confirmation_url(): string {
		// Try to get the orders page from Voxel settings
		$orders_page_id = \Voxel\get( 'templates.orders' );

		if ( $orders_page_id ) {
			return add_query_arg( [
				'order_id' => $this->order->get_id(),
			], get_permalink( $orders_page_id ) );
		}

		// Fallback to home with order parameter
		return add_query_arg( [
			'order_id' => $this->order->get_id(),
			'offline_payment' => 'success',
		], home_url('/') );
	}

	/**
	 * No sync needed for offline payments
	 */
	public function should_sync(): bool {
		return false;
	}

	public function sync(): void {
		// Nothing to sync for offline payments
	}

	/**
	 * Vendor actions
	 */
	public function get_vendor_actions(): array {
		$actions = [];
		$status = $this->order->get_status();

		// Show "Mark as Paid" action for pending payment orders
		if ( $status === \Voxel\ORDER_PENDING_PAYMENT || $status === \Voxel\ORDER_PENDING_APPROVAL ) {
			$actions[] = [
				'action' => 'vendor.mark_paid',
				'label' => _x( 'Mark as Paid', 'order actions', 'voxel-payment-gateways' ),
				'type' => 'primary',
				'handler' => function() {
					$this->order->set_status( \Voxel\ORDER_COMPLETED );
					$this->order->set_details( 'offline.paid_at', \Voxel\utc()->format( 'Y-m-d H:i:s' ) );
					$this->order->set_details( 'offline.marked_paid_by', get_current_user_id() );
					$this->order->save();

					// Dispatch event
					if ( class_exists( '\Voxel\Events\Products\Orders\Vendor_Approved_Order_Event' ) ) {
						( new \Voxel\Events\Products\Orders\Vendor_Approved_Order_Event )->dispatch( $this->order->get_id() );
					}

					return wp_send_json( [
						'success' => true,
					] );
				},
			];

			$actions[] = [
				'action' => 'vendor.cancel',
				'label' => _x( 'Cancel Order', 'order actions', 'voxel-payment-gateways' ),
				'handler' => function() {
					$this->order->set_status( \Voxel\ORDER_CANCELED );
					$this->order->set_details( 'offline.canceled_at', \Voxel\utc()->format( 'Y-m-d H:i:s' ) );
					$this->order->set_details( 'offline.canceled_by', get_current_user_id() );
					$this->order->save();

					// Dispatch event
					if ( class_exists( '\Voxel\Events\Products\Orders\Vendor_Declined_Order_Event' ) ) {
						( new \Voxel\Events\Products\Orders\Vendor_Declined_Order_Event )->dispatch( $this->order->get_id() );
					}

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
		$status = $this->order->get_status();

		// Allow customer to cancel unpaid orders
		if ( $status === \Voxel\ORDER_PENDING_PAYMENT || $status === \Voxel\ORDER_PENDING_APPROVAL ) {
			$actions[] = [
				'action' => 'customer.cancel',
				'label' => _x( 'Cancel order', 'order customer actions', 'voxel-payment-gateways' ),
				'handler' => function() {
					$this->order->set_status( \Voxel\ORDER_CANCELED );
					$this->order->set_details( 'offline.canceled_at', \Voxel\utc()->format( 'Y-m-d H:i:s' ) );
					$this->order->set_details( 'offline.canceled_by', get_current_user_id() );
					$this->order->save();

					// Dispatch event
					if ( class_exists( '\Voxel\Events\Products\Orders\Customer_Canceled_Order_Event' ) ) {
						( new \Voxel\Events\Products\Orders\Customer_Canceled_Order_Event )->dispatch( $this->order->get_id() );
					}

					return wp_send_json( [
						'success' => true,
					] );
				},
			];
		}

		return $actions;
	}

	/**
	 * Get notes to display to customer after order
	 * Returns the payment instructions configured in settings
	 */
	public function get_notes_to_customer(): ?string {
		$instructions = \Voxel\get( 'payments.offline.instructions' );

		if ( ! is_string( $instructions ) || empty( $instructions ) ) {
			return null;
		}

		// Render any dynamic tags if available
		if ( class_exists( '\Voxel\Dynamic_Data\Group' ) ) {
			$instructions = \Voxel\render( $instructions, [
				'customer' => \Voxel\Dynamic_Data\Group::User( $this->order->get_customer() ),
				'vendor' => \Voxel\Dynamic_Data\Group::User( $this->order->get_vendor() ),
				'site' => \Voxel\Dynamic_Data\Group::Site(),
			] );
		}

		$instructions = esc_html( $instructions );
		$instructions = links_add_target( make_clickable( $instructions ) );

		return $instructions;
	}
}
