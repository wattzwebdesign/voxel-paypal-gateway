<?php

namespace VoxelPayPal\Controllers;

use VoxelPayPal\Paystack_Client;

if ( ! defined('ABSPATH') ) {
	exit;
}

/**
 * Paystack Webhooks Controller
 * Handles Paystack webhook events
 */
class Paystack_Webhooks_Controller extends \Voxel\Controllers\Base_Controller {

	protected function hooks(): void {
		$this->on( 'voxel_ajax_paystack.webhooks', '@handle_webhooks' );
		$this->on( 'voxel_ajax_nopriv_paystack.webhooks', '@handle_webhooks' );
	}

	/**
	 * Handle Paystack webhooks
	 */
	protected function handle_webhooks(): void {
		try {
			// Get raw POST data
			$raw_body = file_get_contents( 'php://input' );

			// Verify webhook signature
			$signature = $_SERVER['HTTP_X_PAYSTACK_SIGNATURE'] ?? '';

			if ( ! Paystack_Client::verify_webhook_signature( $raw_body, $signature ) ) {
				throw new \Exception( 'Invalid webhook signature' );
			}

			$event = json_decode( $raw_body, true );

			if ( json_last_error() !== JSON_ERROR_NONE ) {
				throw new \Exception( 'Invalid JSON payload' );
			}

			// Log webhook for debugging
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'Paystack Webhook: ' . ( $event['event'] ?? 'unknown' ) );
			}

			// Route based on event type
			$event_type = $event['event'] ?? '';

			switch ( $event_type ) {
				// Payment events
				case 'charge.success':
					$this->handle_charge_success( $event );
					break;

				case 'charge.failed':
					$this->handle_charge_failed( $event );
					break;

				// Subscription events
				case 'subscription.create':
					$this->handle_subscription_create( $event );
					break;

				case 'subscription.disable':
					$this->handle_subscription_disable( $event );
					break;

				case 'subscription.not_renew':
					$this->handle_subscription_not_renew( $event );
					break;

				// Invoice events (subscription renewal)
				case 'invoice.create':
					$this->handle_invoice_create( $event );
					break;

				case 'invoice.update':
					$this->handle_invoice_update( $event );
					break;

				case 'invoice.payment_failed':
					$this->handle_invoice_payment_failed( $event );
					break;

				// Transfer events (marketplace)
				case 'transfer.success':
					$this->handle_transfer_success( $event );
					break;

				case 'transfer.failed':
					$this->handle_transfer_failed( $event );
					break;

				// Refund events
				case 'refund.processed':
					$this->handle_refund_processed( $event );
					break;

				case 'refund.failed':
					$this->handle_refund_failed( $event );
					break;

				default:
					// Log unknown event type
					if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
						error_log( 'Paystack: Unhandled webhook event: ' . $event_type );
					}
					break;
			}

			// Return success response
			wp_send_json( [ 'success' => true ], 200 );

		} catch ( \Exception $e ) {
			error_log( 'Paystack Webhook Error: ' . $e->getMessage() );

			wp_send_json( [
				'success' => false,
				'error' => $e->getMessage(),
			], 400 );
		}
	}

	/**
	 * Handle successful charge
	 */
	protected function handle_charge_success( array $event ): void {
		$data = $event['data'] ?? [];
		$reference = $data['reference'] ?? '';
		$metadata = $data['metadata'] ?? [];

		$order = $this->find_order_by_reference( $reference );

		if ( ! $order ) {
			// Try to find by metadata order_id
			$order_id = $metadata['order_id'] ?? null;
			if ( $order_id ) {
				$order = \Voxel\Product_Types\Orders\Order::find( [ 'id' => absint( $order_id ) ] );
			}
		}

		if ( ! $order ) {
			return;
		}

		$payment_method = $order->get_payment_method();

		if ( $payment_method instanceof \VoxelPayPal\Payment_Methods\Paystack_Payment ) {
			$payment_method->handle_payment_completed( $data );
		} elseif ( $payment_method instanceof \VoxelPayPal\Payment_Methods\Paystack_Subscription ) {
			// Check if this is the initial payment or a recurring one
			$subscription_code = $order->get_details( 'paystack.subscription_code' );
			if ( ! $subscription_code ) {
				$payment_method->handle_initial_payment_completed( $data );
			} else {
				$payment_method->handle_subscription_payment( $data );
			}
		}

		do_action( 'voxel/paystack/webhook-charge-success', $order, $data, $event );
	}

	/**
	 * Handle failed charge
	 */
	protected function handle_charge_failed( array $event ): void {
		$data = $event['data'] ?? [];
		$reference = $data['reference'] ?? '';

		$order = $this->find_order_by_reference( $reference );

		if ( ! $order ) {
			return;
		}

		$order->set_details( 'paystack.status', 'failed' );
		$order->set_details( 'paystack.failed_at', \Voxel\utc()->format( 'Y-m-d H:i:s' ) );
		$order->set_details( 'paystack.failure_reason', $data['gateway_response'] ?? 'Payment failed' );

		// Only update status if still pending
		if ( $order->get_status() === \Voxel\ORDER_PENDING_PAYMENT ) {
			$order->set_status( \Voxel\ORDER_CANCELED );
		}

		$order->save();

		do_action( 'voxel/paystack/webhook-charge-failed', $order, $data, $event );
	}

	/**
	 * Handle subscription creation
	 */
	protected function handle_subscription_create( array $event ): void {
		$data = $event['data'] ?? [];
		$subscription_code = $data['subscription_code'] ?? '';
		$customer = $data['customer'] ?? [];

		// Find order by subscription code or customer email
		$order = $this->find_order_by_subscription_code( $subscription_code );

		if ( ! $order && ! empty( $customer['email'] ) ) {
			// Try to find recent pending subscription for this customer
			$order = $this->find_recent_pending_subscription_order( $customer['email'] );
		}

		if ( ! $order ) {
			return;
		}

		$payment_method = $order->get_payment_method();

		if ( $payment_method instanceof \VoxelPayPal\Payment_Methods\Paystack_Subscription ) {
			$payment_method->subscription_created( $data );
		}

		do_action( 'voxel/paystack/webhook-subscription-create', $order, $data, $event );
	}

	/**
	 * Handle subscription disable (cancellation)
	 */
	protected function handle_subscription_disable( array $event ): void {
		$data = $event['data'] ?? [];
		$subscription_code = $data['subscription_code'] ?? '';

		$order = $this->find_order_by_subscription_code( $subscription_code );

		if ( ! $order ) {
			return;
		}

		$payment_method = $order->get_payment_method();

		if ( $payment_method instanceof \VoxelPayPal\Payment_Methods\Paystack_Subscription ) {
			$payment_method->subscription_cancelled( $data );
		}

		do_action( 'voxel/paystack/webhook-subscription-disable', $order, $data, $event );
	}

	/**
	 * Handle subscription not renew
	 */
	protected function handle_subscription_not_renew( array $event ): void {
		$data = $event['data'] ?? [];
		$subscription_code = $data['subscription_code'] ?? '';

		$order = $this->find_order_by_subscription_code( $subscription_code );

		if ( ! $order ) {
			return;
		}

		// Mark subscription as not renewing
		$order->set_details( 'paystack.will_not_renew', true );
		$order->set_details( 'paystack.not_renew_at', \Voxel\utc()->format( 'Y-m-d H:i:s' ) );
		$order->save();

		do_action( 'voxel/paystack/webhook-subscription-not-renew', $order, $data, $event );
	}

	/**
	 * Handle invoice creation (upcoming subscription charge)
	 */
	protected function handle_invoice_create( array $event ): void {
		$data = $event['data'] ?? [];
		$subscription = $data['subscription'] ?? [];
		$subscription_code = $subscription['subscription_code'] ?? '';

		$order = $this->find_order_by_subscription_code( $subscription_code );

		if ( ! $order ) {
			return;
		}

		// Log upcoming charge
		$order->set_details( 'paystack.upcoming_invoice', [
			'amount' => $data['amount'] ?? 0,
			'due_date' => $data['due_date'] ?? '',
			'created_at' => \Voxel\utc()->format( 'Y-m-d H:i:s' ),
		] );
		$order->save();

		do_action( 'voxel/paystack/webhook-invoice-create', $order, $data, $event );
	}

	/**
	 * Handle invoice update
	 */
	protected function handle_invoice_update( array $event ): void {
		$data = $event['data'] ?? [];
		$subscription = $data['subscription'] ?? [];
		$subscription_code = $subscription['subscription_code'] ?? '';

		$order = $this->find_order_by_subscription_code( $subscription_code );

		if ( ! $order ) {
			return;
		}

		// Update invoice status
		$order->set_details( 'paystack.invoice_status', $data['status'] ?? 'unknown' );
		$order->set_details( 'paystack.invoice_updated_at', \Voxel\utc()->format( 'Y-m-d H:i:s' ) );
		$order->save();

		do_action( 'voxel/paystack/webhook-invoice-update', $order, $data, $event );
	}

	/**
	 * Handle failed invoice payment (subscription renewal failed)
	 */
	protected function handle_invoice_payment_failed( array $event ): void {
		$data = $event['data'] ?? [];
		$subscription = $data['subscription'] ?? [];
		$subscription_code = $subscription['subscription_code'] ?? '';

		$order = $this->find_order_by_subscription_code( $subscription_code );

		if ( ! $order ) {
			return;
		}

		$payment_method = $order->get_payment_method();

		if ( $payment_method instanceof \VoxelPayPal\Payment_Methods\Paystack_Subscription ) {
			$payment_method->subscription_payment_failed( $data );
		}

		do_action( 'voxel/paystack/webhook-invoice-payment-failed', $order, $data, $event );
	}

	/**
	 * Handle successful transfer (marketplace payout)
	 */
	protected function handle_transfer_success( array $event ): void {
		$data = $event['data'] ?? [];
		$transfer_code = $data['transfer_code'] ?? '';
		$reference = $data['reference'] ?? '';

		// Find order by transfer reference
		$order = $this->find_order_by_transfer_reference( $reference );

		if ( ! $order ) {
			return;
		}

		$order->set_details( 'paystack.transfer_status', 'success' );
		$order->set_details( 'paystack.transfer_completed_at', \Voxel\utc()->format( 'Y-m-d H:i:s' ) );
		$order->save();

		do_action( 'voxel/paystack/webhook-transfer-success', $order, $data, $event );
	}

	/**
	 * Handle failed transfer
	 */
	protected function handle_transfer_failed( array $event ): void {
		$data = $event['data'] ?? [];
		$reference = $data['reference'] ?? '';

		$order = $this->find_order_by_transfer_reference( $reference );

		if ( ! $order ) {
			return;
		}

		$order->set_details( 'paystack.transfer_status', 'failed' );
		$order->set_details( 'paystack.transfer_failed_at', \Voxel\utc()->format( 'Y-m-d H:i:s' ) );
		$order->set_details( 'paystack.transfer_failure_reason', $data['reason'] ?? 'Transfer failed' );
		$order->save();

		do_action( 'voxel/paystack/webhook-transfer-failed', $order, $data, $event );
	}

	/**
	 * Handle processed refund
	 */
	protected function handle_refund_processed( array $event ): void {
		$data = $event['data'] ?? [];
		$transaction = $data['transaction'] ?? [];
		$reference = $transaction['reference'] ?? '';

		$order = $this->find_order_by_reference( $reference );

		if ( ! $order ) {
			return;
		}

		$order->set_details( 'paystack.refund_status', 'processed' );
		$order->set_details( 'paystack.refund_amount', $data['amount'] ?? 0 );
		$order->set_details( 'paystack.refunded_at', \Voxel\utc()->format( 'Y-m-d H:i:s' ) );
		$order->set_status( \Voxel\ORDER_REFUNDED );
		$order->save();

		do_action( 'voxel/paystack/webhook-refund-processed', $order, $data, $event );
	}

	/**
	 * Handle failed refund
	 */
	protected function handle_refund_failed( array $event ): void {
		$data = $event['data'] ?? [];
		$transaction = $data['transaction'] ?? [];
		$reference = $transaction['reference'] ?? '';

		$order = $this->find_order_by_reference( $reference );

		if ( ! $order ) {
			return;
		}

		$order->set_details( 'paystack.refund_status', 'failed' );
		$order->set_details( 'paystack.refund_failed_at', \Voxel\utc()->format( 'Y-m-d H:i:s' ) );
		$order->save();

		do_action( 'voxel/paystack/webhook-refund-failed', $order, $data, $event );
	}

	/**
	 * Find order by Paystack reference
	 */
	protected function find_order_by_reference( string $reference ): ?\Voxel\Product_Types\Orders\Order {
		if ( empty( $reference ) ) {
			return null;
		}

		// Try to extract order ID from reference (format: vxl_123_xxx)
		if ( preg_match( '/vxl_(\d+)_/', $reference, $matches ) ) {
			$order_id = absint( $matches[1] );
			return \Voxel\Product_Types\Orders\Order::find( [ 'id' => $order_id ] );
		}

		// Search by stored reference
		$orders = \Voxel\Product_Types\Orders\Order::query( [
			'limit' => 1,
			'meta_query' => [
				[
					'key' => 'paystack.reference',
					'value' => $reference,
				],
			],
		] );

		return ! empty( $orders ) ? $orders[0] : null;
	}

	/**
	 * Find order by subscription code
	 */
	protected function find_order_by_subscription_code( string $subscription_code ): ?\Voxel\Product_Types\Orders\Order {
		if ( empty( $subscription_code ) ) {
			return null;
		}

		$orders = \Voxel\Product_Types\Orders\Order::query( [
			'limit' => 1,
			'meta_query' => [
				[
					'key' => 'paystack.subscription_code',
					'value' => $subscription_code,
				],
			],
		] );

		return ! empty( $orders ) ? $orders[0] : null;
	}

	/**
	 * Find order by transfer reference
	 */
	protected function find_order_by_transfer_reference( string $reference ): ?\Voxel\Product_Types\Orders\Order {
		if ( empty( $reference ) ) {
			return null;
		}

		$orders = \Voxel\Product_Types\Orders\Order::query( [
			'limit' => 1,
			'meta_query' => [
				[
					'key' => 'paystack.transfer_reference',
					'value' => $reference,
				],
			],
		] );

		return ! empty( $orders ) ? $orders[0] : null;
	}

	/**
	 * Find recent pending subscription order by customer email
	 */
	protected function find_recent_pending_subscription_order( string $email ): ?\Voxel\Product_Types\Orders\Order {
		if ( empty( $email ) ) {
			return null;
		}

		// Find user by email
		$user = get_user_by( 'email', $email );
		if ( ! $user ) {
			return null;
		}

		// Find recent pending subscription orders for this user
		$orders = \Voxel\Product_Types\Orders\Order::query( [
			'limit' => 1,
			'customer_id' => $user->ID,
			'status' => \Voxel\ORDER_PENDING_PAYMENT,
			'payment_method' => 'paystack_subscription',
			'orderby' => 'created_at',
			'order' => 'DESC',
		] );

		return ! empty( $orders ) ? $orders[0] : null;
	}
}
