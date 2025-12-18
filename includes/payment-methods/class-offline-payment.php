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

			// Inject payment instructions into order_notes for @order(customer_notes) dynamic tag
			// Done after save so order data is fully populated
			$instructions = $this->get_rendered_instructions();
			if ( $instructions ) {
				$existing_notes = $this->order->get_details( 'order_notes' ) ?? '';
				$separator = ! empty( $existing_notes ) ? "\n\n---\n\n" : '';
				$this->order->set_details( 'order_notes', $existing_notes . $separator . $instructions );
				$this->order->save();
			}

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
	 * Get rendered payment instructions with dynamic tags processed
	 * Used to inject into order_notes for @order(customer_notes) dynamic tag
	 */
	protected function get_rendered_instructions(): ?string {
		$instructions = \Voxel\get( 'payments.offline.instructions' );

		if ( ! is_string( $instructions ) || empty( $instructions ) ) {
			return null;
		}

		// Render dynamic tags including order
		if ( class_exists( '\Voxel\Dynamic_Data\Group' ) ) {
			// Refresh order from database to ensure all data is loaded
			$order = \Voxel\Product_Types\Orders\Order::get( $this->order->get_id() );
			if ( ! $order ) {
				$order = $this->order;
			}

			// Get customer - fallback to current user if not set on order
			$customer = $order->get_customer();
			if ( ! $customer && is_user_logged_in() ) {
				$customer = \Voxel\User::get( get_current_user_id() );
			}

			// Get vendor - may be null for direct purchases
			$vendor = $order->get_vendor();

			$groups = [
				'order' => \Voxel\Dynamic_Data\Group::Order( $order ),
				'site' => \Voxel\Dynamic_Data\Group::Site(),
			];

			// Only add customer/vendor groups if they exist
			if ( $customer ) {
				$groups['customer'] = \Voxel\Dynamic_Data\Group::User( $customer );
			}
			if ( $vendor ) {
				$groups['vendor'] = \Voxel\Dynamic_Data\Group::User( $vendor );
			}

			$instructions = \Voxel\render( $instructions, $groups );
		}

		return $instructions;
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
	 * Note: Customer cancel handled by vendor.cancel action which shows for both
	 */
	public function get_customer_actions(): array {
		return [];
	}

	/**
	 * Get notes to display to customer after order
	 * Returns the payment instructions configured in settings
	 */
	public function get_notes_to_customer(): ?string {
		$rendered = $this->get_rendered_instructions();

		if ( ! $rendered ) {
			return null;
		}

		$rendered = esc_html( $rendered );
		$rendered = links_add_target( make_clickable( $rendered ) );

		return $rendered;
	}
}
