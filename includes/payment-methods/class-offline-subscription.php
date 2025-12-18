<?php

namespace VoxelPayPal\Payment_Methods;

if ( ! defined('ABSPATH') ) {
	exit;
}

/**
 * Offline Subscription Payment Method
 * Handles recurring payment processing with manual payment tracking
 */
class Offline_Subscription extends \Voxel\Product_Types\Payment_Methods\Base_Payment_Method {

	public function get_type(): string {
		return 'offline_subscription';
	}

	public function get_label(): string {
		return _x( 'Offline subscription', 'payment methods', 'voxel-payment-gateways' );
	}

	public function is_subscription(): bool {
		return true;
	}

	/**
	 * Process subscription - creates order in pending state
	 */
	public function process_payment() {
		try {
			$line_items = $this->get_line_items();

			if ( empty( $line_items ) ) {
				throw new \Exception( 'No items in order' );
			}

			$first_item = reset( $line_items );
			$order_item = $first_item['order_item'] ?? null;

			// Calculate total
			$total = 0;
			foreach ( $line_items as $item ) {
				$total += $item['amount'] * $item['quantity'];
			}

			// Get subscription interval from order item
			$interval = 'month';
			$frequency = 1;

			if ( $order_item ) {
				$interval = $order_item->get_details('subscription.unit') ?: 'month';
				$frequency = $order_item->get_details('subscription.frequency') ?: 1;
			}

			// Normalize interval
			$interval = strtolower( $interval );

			// Generate unique subscription ID
			$subscription_id = 'offline_sub_' . $this->order->get_id() . '_' . time();
			$now = \Voxel\utc()->format( 'Y-m-d H:i:s' );

			// Store subscription details
			$this->order->set_details( 'offline.subscription_id', $subscription_id );
			$this->order->set_details( 'offline.is_subscription', true );
			$this->order->set_details( 'offline.subscription_status', 'pending' );
			$this->order->set_details( 'offline.created_at', $now );
			$this->order->set_details( 'offline.billing_interval', [
				'unit' => $interval,
				'frequency' => (int) $frequency,
			]);
			$this->order->set_details( 'offline.payment_history', [] );
			$this->order->set_details( 'pricing.total', $total );

			// Set transaction ID
			$this->order->set_transaction_id( $subscription_id );

			// Set order status based on settings (same as one-time offline orders)
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

			return [
				'success' => true,
				'redirect_url' => $this->get_order_confirmation_url(),
			];

		} catch ( \Exception $e ) {
			return [
				'success' => false,
				'message' => _x( 'Failed to create subscription', 'checkout', 'voxel-payment-gateways' ),
				'debug' => [
					'type' => 'offline_subscription_error',
					'message' => $e->getMessage(),
				],
			];
		}
	}

	/**
	 * Calculate next payment date based on billing interval
	 */
	protected function calculate_next_payment_date(): int {
		$interval = $this->get_billing_interval();
		$unit = $interval['unit'] ?? 'month';
		$frequency = $interval['frequency'] ?? 1;

		$now = time();

		switch ( $unit ) {
			case 'day':
				return strtotime( "+{$frequency} days", $now );
			case 'week':
				return strtotime( "+{$frequency} weeks", $now );
			case 'month':
				return strtotime( "+{$frequency} months", $now );
			case 'year':
				return strtotime( "+{$frequency} years", $now );
			default:
				return strtotime( '+1 month', $now );
		}
	}

	/**
	 * Get order confirmation URL
	 */
	protected function get_order_confirmation_url(): string {
		$orders_page_id = \Voxel\get( 'templates.orders' );

		if ( $orders_page_id ) {
			return add_query_arg( [
				'order_id' => $this->order->get_id(),
			], get_permalink( $orders_page_id ) );
		}

		return add_query_arg( [
			'order_id' => $this->order->get_id(),
			'offline_subscription' => 'success',
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
	 * Handle subscription status update
	 */
	public function subscription_updated( array $subscription ): void {
		$status = $subscription['status'] ?? 'active';

		$this->order->set_details( 'offline.subscription_status', $status );

		if ( isset( $subscription['next_payment_date'] ) ) {
			$this->order->set_details( 'offline.next_payment_date', $subscription['next_payment_date'] );
		}

		// Map status to order status
		if ( $status === 'active' ) {
			$this->order->set_status( 'sub_active' );
		} elseif ( $status === 'canceled' ) {
			$this->order->set_status( 'sub_canceled' );
		}

		$this->order->set_details( 'offline.last_updated_at', \Voxel\utc()->format( 'Y-m-d H:i:s' ) );
		$this->order->save();
	}

	/**
	 * Get billing interval
	 */
	public function get_billing_interval(): ?array {
		$interval = $this->order->get_details( 'offline.billing_interval' );

		if ( $interval ) {
			return $interval;
		}

		// Fallback to order items
		$items = $this->order->get_items();
		if ( empty( $items ) ) {
			return [ 'unit' => 'month', 'frequency' => 1 ];
		}

		$first_item = reset( $items );
		$unit = $first_item->get_details('subscription.unit') ?: 'month';
		$frequency = $first_item->get_details('subscription.frequency') ?: 1;

		return [
			'unit' => strtolower( $unit ),
			'frequency' => (int) $frequency,
		];
	}

	/**
	 * No sync needed for offline subscriptions
	 */
	public function should_sync(): bool {
		return false;
	}

	public function sync(): void {
		// No-op for offline subscriptions
	}

	/**
	 * Vendor actions for subscription
	 */
	public function get_vendor_actions(): array {
		$actions = [];
		$status = $this->order->get_status();
		$subscription_status = $this->order->get_details( 'offline.subscription_status' );

		// For pending subscriptions - allow marking initial payment as received
		if ( $status === \Voxel\ORDER_PENDING_PAYMENT || $status === \Voxel\ORDER_PENDING_APPROVAL ) {
			$actions[] = [
				'action' => 'vendor.mark_paid',
				'label' => _x( 'Mark as Paid', 'order actions', 'voxel-payment-gateways' ),
				'type' => 'primary',
				'handler' => function() {
					$now = \Voxel\utc()->format( 'Y-m-d H:i:s' );
					$total = $this->order->get_details( 'pricing.total' ) ?: 0;

					// Calculate next payment date
					$next_payment = $this->calculate_next_payment_date();

					// Record initial payment
					$history = $this->order->get_details( 'offline.payment_history' ) ?: [];
					$history[] = [
						'date' => $now,
						'amount' => $total,
						'marked_by' => get_current_user_id(),
						'note' => 'Initial subscription payment',
						'type' => 'initial',
					];

					// Update order details
					$this->order->set_details( 'offline.payment_history', $history );
					$this->order->set_details( 'offline.last_payment_date', time() );
					$this->order->set_details( 'offline.next_payment_date', $next_payment );
					$this->order->set_details( 'offline.subscription_status', 'active' );
					$this->order->set_details( 'offline.paid_at', $now );
					$this->order->set_details( 'offline.marked_paid_by', get_current_user_id() );

					// Set subscription active status
					$this->order->set_status( 'sub_active' );
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

			// Cancel pending subscription
			$actions[] = [
				'action' => 'vendor.cancel',
				'label' => _x( 'Cancel Order', 'order actions', 'voxel-payment-gateways' ),
				'handler' => function() {
					$this->order->set_status( \Voxel\ORDER_CANCELED );
					$this->order->set_details( 'offline.subscription_status', 'canceled' );
					$this->order->set_details( 'offline.canceled_at', \Voxel\utc()->format( 'Y-m-d H:i:s' ) );
					$this->order->set_details( 'offline.canceled_by', get_current_user_id() );
					$this->order->save();

					if ( class_exists( '\Voxel\Events\Products\Orders\Vendor_Declined_Order_Event' ) ) {
						( new \Voxel\Events\Products\Orders\Vendor_Declined_Order_Event )->dispatch( $this->order->get_id() );
					}

					return wp_send_json( [
						'success' => true,
					] );
				},
			];
		}

		// For active subscriptions - allow recording renewal payments and cancellation
		if ( $status === 'sub_active' || $subscription_status === 'active' ) {
			// Record renewal payment
			$actions[] = [
				'action' => 'vendor.mark_renewal_paid',
				'label' => _x( 'Record Renewal Payment', 'order actions', 'voxel-payment-gateways' ),
				'type' => 'primary',
				'handler' => function() {
					$now = \Voxel\utc()->format( 'Y-m-d H:i:s' );
					$total = $this->order->get_details( 'pricing.total' ) ?: 0;

					// Calculate next payment date
					$next_payment = $this->calculate_next_payment_date();

					// Record renewal payment
					$history = $this->order->get_details( 'offline.payment_history' ) ?: [];
					$history[] = [
						'date' => $now,
						'amount' => $total,
						'marked_by' => get_current_user_id(),
						'note' => 'Renewal payment',
						'type' => 'renewal',
					];

					// Update order details
					$this->order->set_details( 'offline.payment_history', $history );
					$this->order->set_details( 'offline.last_payment_date', time() );
					$this->order->set_details( 'offline.next_payment_date', $next_payment );
					$this->order->set_details( 'offline.last_updated_at', $now );
					$this->order->save();

					return wp_send_json( [
						'success' => true,
						'message' => 'Renewal payment recorded',
					] );
				},
			];

			// Cancel active subscription
			$actions[] = [
				'action' => 'vendor.cancel_subscription',
				'label' => _x( 'Cancel Subscription', 'order actions', 'voxel-payment-gateways' ),
				'handler' => function() {
					$this->order->set_status( 'sub_canceled' );
					$this->order->set_details( 'offline.subscription_status', 'canceled' );
					$this->order->set_details( 'offline.canceled_at', \Voxel\utc()->format( 'Y-m-d H:i:s' ) );
					$this->order->set_details( 'offline.canceled_by', get_current_user_id() );
					$this->order->save();

					if ( class_exists( '\Voxel\Events\Products\Orders\Vendor_Declined_Order_Event' ) ) {
						( new \Voxel\Events\Products\Orders\Vendor_Declined_Order_Event )->dispatch( $this->order->get_id() );
					}

					return wp_send_json( [
						'success' => true,
					] );
				},
			];
		}

		// For canceled subscriptions - allow reactivation
		if ( $status === 'sub_canceled' || $subscription_status === 'canceled' ) {
			$actions[] = [
				'action' => 'vendor.reactivate_subscription',
				'label' => _x( 'Reactivate Subscription', 'order actions', 'voxel-payment-gateways' ),
				'type' => 'primary',
				'handler' => function() {
					$now = \Voxel\utc()->format( 'Y-m-d H:i:s' );

					// Calculate next payment date from now
					$next_payment = $this->calculate_next_payment_date();

					$this->order->set_status( 'sub_active' );
					$this->order->set_details( 'offline.subscription_status', 'active' );
					$this->order->set_details( 'offline.next_payment_date', $next_payment );
					$this->order->set_details( 'offline.reactivated_at', $now );
					$this->order->set_details( 'offline.reactivated_by', get_current_user_id() );
					$this->order->save();

					return wp_send_json( [
						'success' => true,
					] );
				},
			];
		}

		return $actions;
	}

	/**
	 * Customer actions for subscription
	 * Note: Cancel actions handled by vendor actions which show for both
	 */
	public function get_customer_actions(): array {
		return [];
	}

	/**
	 * Get notes to display to customer after order
	 */
	public function get_notes_to_customer(): ?string {
		$rendered = $this->get_rendered_instructions();

		if ( ! $rendered ) {
			return null;
		}

		// Add subscription info
		$interval = $this->get_billing_interval();
		$unit = $interval['unit'] ?? 'month';
		$frequency = $interval['frequency'] ?? 1;

		$interval_text = $frequency > 1 ? "{$frequency} {$unit}s" : $unit;
		$rendered .= "\n\n" . sprintf(
			__( 'Billing cycle: Every %s', 'voxel-payment-gateways' ),
			$interval_text
		);

		// Add next payment date if available
		$next_payment = $this->order->get_details( 'offline.next_payment_date' );
		if ( $next_payment ) {
			$next_date = date_i18n( get_option( 'date_format' ), $next_payment );
			$rendered .= "\n" . sprintf(
				__( 'Next payment due: %s', 'voxel-payment-gateways' ),
				$next_date
			);
		}

		$rendered = esc_html( $rendered );
		$rendered = links_add_target( make_clickable( $rendered ) );

		return $rendered;
	}
}
