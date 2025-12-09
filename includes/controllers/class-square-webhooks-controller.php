<?php

namespace VoxelPayPal\Controllers;

use VoxelPayPal\Square_Client;

if ( ! defined('ABSPATH') ) {
	exit;
}

/**
 * Square Webhooks Controller
 * Handles Square webhook events
 */
class Square_Webhooks_Controller extends \Voxel\Controllers\Base_Controller {

	protected function hooks() {
		$this->on( 'voxel_ajax_square.webhooks', '@handle_webhooks' );
	}

	/**
	 * Handle Square webhooks
	 */
	protected function handle_webhooks() {
		try {
			// Get raw POST data
			$raw_body = file_get_contents( 'php://input' );
			$event = json_decode( $raw_body, true );

			if ( json_last_error() !== JSON_ERROR_NONE ) {
				throw new \Exception( 'Invalid JSON payload' );
			}

			// Get webhook signature
			$signature = $_SERVER['HTTP_X_SQUARE_HMACSHA256_SIGNATURE'] ?? '';
			$webhook_url = home_url( '/?vx=1&action=square.webhooks' );

			// Verify webhook signature
			if ( ! Square_Client::verify_webhook_signature( $signature, $webhook_url, $raw_body ) ) {
				throw new \Exception( 'Invalid webhook signature' );
			}

			// Log webhook for debugging
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'Square Webhook: ' . ( $event['type'] ?? 'unknown' ) );
			}

			// Process event based on type
			$event_type = $event['type'] ?? '';

			switch ( $event_type ) {
				// Payment events
				case 'payment.completed':
					$this->handle_payment_completed( $event );
					break;

				case 'payment.updated':
					$this->handle_payment_updated( $event );
					break;

				case 'payment.created':
					$this->handle_payment_created( $event );
					break;

				// Refund events
				case 'refund.created':
					$this->handle_refund_created( $event );
					break;

				case 'refund.updated':
					$this->handle_refund_updated( $event );
					break;

				// Order events
				case 'order.created':
					$this->handle_order_created( $event );
					break;

				case 'order.updated':
					$this->handle_order_updated( $event );
					break;

				// Subscription events
				case 'subscription.created':
					$this->handle_subscription_created( $event );
					break;

				case 'subscription.updated':
					$this->handle_subscription_updated( $event );
					break;

				// Invoice events (for subscription payments)
				case 'invoice.payment_made':
					$this->handle_invoice_payment_made( $event );
					break;

				case 'invoice.canceled':
					$this->handle_invoice_canceled( $event );
					break;

				case 'invoice.scheduled_charge_failed':
					$this->handle_invoice_charge_failed( $event );
					break;

				default:
					// Log unknown event type
					if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
						error_log( 'Square: Unhandled webhook event: ' . $event_type );
					}
					break;
			}

			// Return success response to Square
			wp_send_json( [ 'success' => true ], 200 );

		} catch ( \Exception $e ) {
			// Log error
			error_log( 'Square Webhook Error: ' . $e->getMessage() );

			// Return error response
			wp_send_json( [
				'success' => false,
				'error' => $e->getMessage(),
			], 400 );
		}
	}

	/**
	 * Handle payment completed event
	 */
	protected function handle_payment_completed( array $event ) {
		$payment = $event['data']['object']['payment'] ?? null;
		if ( ! $payment ) {
			return;
		}

		$payment_id = $payment['id'] ?? null;
		$note = $payment['note'] ?? '';

		// Try to find order by payment ID or note
		$order = $this->find_order_by_payment_id( $payment_id );

		if ( ! $order && $note ) {
			$order = $this->find_order_by_note( $note );
		}

		if ( ! $order ) {
			return;
		}

		$payment_method = $order->get_payment_method();

		if ( $payment_method instanceof \VoxelPayPal\Payment_Methods\Square_Payment ) {
			$payment_method->handle_order_completed( $payment );
		} elseif ( $payment_method instanceof \VoxelPayPal\Payment_Methods\Square_Subscription ) {
			$payment_method->handle_initial_payment_completed( $payment );
		}

		do_action( 'voxel/square/payment-completed', $order, $event );
	}

	/**
	 * Handle payment updated event
	 */
	protected function handle_payment_updated( array $event ) {
		$payment = $event['data']['object']['payment'] ?? null;
		if ( ! $payment ) {
			return;
		}

		$payment_id = $payment['id'] ?? null;
		$status = $payment['status'] ?? '';

		$order = $this->find_order_by_payment_id( $payment_id );
		if ( ! $order ) {
			return;
		}

		// Update order based on payment status
		if ( $status === 'COMPLETED' ) {
			$payment_method = $order->get_payment_method();
			if ( $payment_method instanceof \VoxelPayPal\Payment_Methods\Square_Payment ) {
				$payment_method->handle_order_completed( $payment );
			}
		} elseif ( $status === 'FAILED' || $status === 'CANCELED' ) {
			$order->set_status( \Voxel\ORDER_CANCELED );
			$order->set_details( 'square.status', $status );
			$order->save();
		}

		do_action( 'voxel/square/payment-updated', $order, $event );
	}

	/**
	 * Handle payment created event
	 */
	protected function handle_payment_created( array $event ) {
		// Log for debugging, but typically no action needed
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'Square: Payment created webhook received' );
		}
	}

	/**
	 * Handle refund created event
	 */
	protected function handle_refund_created( array $event ) {
		$refund = $event['data']['object']['refund'] ?? null;
		if ( ! $refund ) {
			return;
		}

		$payment_id = $refund['payment_id'] ?? null;
		if ( ! $payment_id ) {
			return;
		}

		$order = $this->find_order_by_payment_id( $payment_id );
		if ( ! $order ) {
			return;
		}

		// Update order with refund details
		$order->set_details( 'square.refund_id', $refund['id'] ?? null );
		$order->set_details( 'square.refund_status', $refund['status'] ?? 'PENDING' );

		if ( $refund['status'] === 'COMPLETED' ) {
			$order->set_status( \Voxel\ORDER_REFUNDED );
		}

		$order->save();

		do_action( 'voxel/square/refund-created', $order, $event );
	}

	/**
	 * Handle refund updated event
	 */
	protected function handle_refund_updated( array $event ) {
		$refund = $event['data']['object']['refund'] ?? null;
		if ( ! $refund ) {
			return;
		}

		$payment_id = $refund['payment_id'] ?? null;
		if ( ! $payment_id ) {
			return;
		}

		$order = $this->find_order_by_payment_id( $payment_id );
		if ( ! $order ) {
			return;
		}

		$order->set_details( 'square.refund_status', $refund['status'] ?? null );

		if ( $refund['status'] === 'COMPLETED' ) {
			$order->set_status( \Voxel\ORDER_REFUNDED );
		}

		$order->save();

		do_action( 'voxel/square/refund-updated', $order, $event );
	}

	/**
	 * Handle order created event
	 */
	protected function handle_order_created( array $event ) {
		// Log for debugging
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'Square: Order created webhook received' );
		}
	}

	/**
	 * Handle order updated event
	 */
	protected function handle_order_updated( array $event ) {
		$square_order = $event['data']['object']['order'] ?? null;
		if ( ! $square_order ) {
			return;
		}

		$square_order_id = $square_order['id'] ?? null;
		if ( ! $square_order_id ) {
			return;
		}

		$order = $this->find_order_by_square_order_id( $square_order_id );
		if ( ! $order ) {
			return;
		}

		// Update order details
		$order->set_details( 'square.order_state', $square_order['state'] ?? null );
		$order->save();

		do_action( 'voxel/square/order-updated', $order, $event );
	}

	/**
	 * Handle subscription created event
	 */
	protected function handle_subscription_created( array $event ) {
		$subscription = $event['data']['object']['subscription'] ?? null;
		if ( ! $subscription ) {
			return;
		}

		$subscription_id = $subscription['id'] ?? null;

		// Try to find order by subscription ID or customer reference
		$order = $this->find_order_by_subscription_id( $subscription_id );
		if ( ! $order ) {
			return;
		}

		$payment_method = $order->get_payment_method();
		if ( $payment_method instanceof \VoxelPayPal\Payment_Methods\Square_Subscription ) {
			$payment_method->subscription_updated( $subscription );
		}

		do_action( 'voxel/square/subscription-created', $order, $event );
	}

	/**
	 * Handle subscription updated event
	 */
	protected function handle_subscription_updated( array $event ) {
		$subscription = $event['data']['object']['subscription'] ?? null;
		if ( ! $subscription ) {
			return;
		}

		$subscription_id = $subscription['id'] ?? null;

		$order = $this->find_order_by_subscription_id( $subscription_id );
		if ( ! $order ) {
			return;
		}

		$payment_method = $order->get_payment_method();
		if ( $payment_method instanceof \VoxelPayPal\Payment_Methods\Square_Subscription ) {
			$payment_method->subscription_updated( $subscription );
		}

		do_action( 'voxel/square/subscription-updated', $order, $event );
	}

	/**
	 * Handle invoice payment made event (subscription payment)
	 */
	protected function handle_invoice_payment_made( array $event ) {
		$invoice = $event['data']['object']['invoice'] ?? null;
		if ( ! $invoice ) {
			return;
		}

		$subscription_id = $invoice['subscription_id'] ?? null;
		if ( ! $subscription_id ) {
			return;
		}

		$order = $this->find_order_by_subscription_id( $subscription_id );
		if ( ! $order ) {
			return;
		}

		// Update last payment date
		$order->set_details( 'square.last_invoice_paid_at', \Voxel\utc()->format( 'Y-m-d H:i:s' ) );
		$order->set_details( 'square.last_invoice_id', $invoice['id'] ?? null );
		$order->save();

		do_action( 'voxel/square/invoice-payment-made', $order, $event );
	}

	/**
	 * Handle invoice canceled event
	 */
	protected function handle_invoice_canceled( array $event ) {
		$invoice = $event['data']['object']['invoice'] ?? null;
		if ( ! $invoice ) {
			return;
		}

		$subscription_id = $invoice['subscription_id'] ?? null;
		if ( ! $subscription_id ) {
			return;
		}

		$order = $this->find_order_by_subscription_id( $subscription_id );
		if ( ! $order ) {
			return;
		}

		// Log cancellation
		$order->set_details( 'square.last_invoice_canceled_at', \Voxel\utc()->format( 'Y-m-d H:i:s' ) );
		$order->save();

		do_action( 'voxel/square/invoice-canceled', $order, $event );
	}

	/**
	 * Handle invoice scheduled charge failed event
	 */
	protected function handle_invoice_charge_failed( array $event ) {
		$invoice = $event['data']['object']['invoice'] ?? null;
		if ( ! $invoice ) {
			return;
		}

		$subscription_id = $invoice['subscription_id'] ?? null;
		if ( ! $subscription_id ) {
			return;
		}

		$order = $this->find_order_by_subscription_id( $subscription_id );
		if ( ! $order ) {
			return;
		}

		// Log failed charge
		$order->set_details( 'square.last_charge_failed_at', \Voxel\utc()->format( 'Y-m-d H:i:s' ) );
		$order->save();

		do_action( 'voxel/square/invoice-charge-failed', $order, $event );
	}

	/**
	 * Find order by Square payment ID
	 */
	protected function find_order_by_payment_id( ?string $payment_id ) {
		if ( ! $payment_id ) {
			return null;
		}

		$orders = \Voxel\Product_Types\Orders\Order::query( [
			'limit' => 1,
			'meta_query' => [
				[
					'key' => 'square.payment_id',
					'value' => $payment_id,
				],
			],
		] );

		return ! empty( $orders ) ? $orders[0] : null;
	}

	/**
	 * Find order by payment note (contains voxel_order_ID)
	 */
	protected function find_order_by_note( string $note ) {
		// Extract order ID from note like "voxel_order_123" or "voxel_subscription_123"
		if ( preg_match( '/voxel_(?:order|subscription)_(\d+)/', $note, $matches ) ) {
			$order_id = absint( $matches[1] );

			return \Voxel\Product_Types\Orders\Order::find( [
				'id' => $order_id,
			] );
		}

		return null;
	}

	/**
	 * Find order by Square order ID
	 */
	protected function find_order_by_square_order_id( ?string $square_order_id ) {
		if ( ! $square_order_id ) {
			return null;
		}

		$orders = \Voxel\Product_Types\Orders\Order::query( [
			'limit' => 1,
			'meta_query' => [
				[
					'key' => 'square.order_id',
					'value' => $square_order_id,
				],
			],
		] );

		return ! empty( $orders ) ? $orders[0] : null;
	}

	/**
	 * Find order by Square subscription ID
	 */
	protected function find_order_by_subscription_id( ?string $subscription_id ) {
		if ( ! $subscription_id ) {
			return null;
		}

		$orders = \Voxel\Product_Types\Orders\Order::query( [
			'limit' => 1,
			'meta_query' => [
				[
					'key' => 'square.subscription_id',
					'value' => $subscription_id,
				],
			],
		] );

		return ! empty( $orders ) ? $orders[0] : null;
	}
}
