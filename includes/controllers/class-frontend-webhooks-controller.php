<?php

namespace VoxelPayPal\Controllers;

use VoxelPayPal\PayPal_Client;

if ( ! defined('ABSPATH') ) {
	exit;
}

/**
 * Frontend Webhooks Controller
 * Handles PayPal webhook events
 */
class Frontend_Webhooks_Controller extends \Voxel\Controllers\Base_Controller {

	protected function hooks() {
		$this->on( 'voxel_ajax_paypal.webhooks', '@handle_webhooks' );
	}

	/**
	 * Handle PayPal webhooks
	 */
	protected function handle_webhooks() {

		try {
			// Get raw POST data
			$raw_body = file_get_contents( 'php://input' );
			$event = json_decode( $raw_body, true );

			if ( json_last_error() !== JSON_ERROR_NONE ) {
				throw new \Exception( 'Invalid JSON payload' );
			}

			// Verify webhook signature
			$headers = getallheaders();
			if ( ! PayPal_Client::verify_webhook_signature( $headers, $raw_body ) ) {
				throw new \Exception( 'Invalid webhook signature' );
			}

			// Log webhook for debugging
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'PayPal Webhook: ' . $event['event_type'] ?? 'unknown' );
			}

			// Process event based on type
			$event_type = $event['event_type'] ?? '';

			switch ( $event_type ) {
				// One-time payment events
				case 'PAYMENT.CAPTURE.COMPLETED':
					$this->handle_payment_captured( $event );
					break;

				case 'PAYMENT.CAPTURE.DENIED':
				case 'PAYMENT.CAPTURE.DECLINED':
					$this->handle_payment_failed( $event );
					break;

				case 'PAYMENT.CAPTURE.REFUNDED':
					$this->handle_payment_refunded( $event );
					break;

				case 'CHECKOUT.ORDER.APPROVED':
					// Order approved by customer, waiting for capture
					break;

				case 'PAYMENT.AUTHORIZATION.CREATED':
					$this->handle_authorization_created( $event );
					break;

				case 'PAYMENT.AUTHORIZATION.VOIDED':
					$this->handle_authorization_voided( $event );
					break;

				// Subscription events
				case 'BILLING.SUBSCRIPTION.ACTIVATED':
					$this->handle_subscription_activated( $event );
					break;

				case 'BILLING.SUBSCRIPTION.CANCELLED':
				case 'BILLING.SUBSCRIPTION.EXPIRED':
					$this->handle_subscription_cancelled( $event );
					break;

				case 'BILLING.SUBSCRIPTION.SUSPENDED':
					$this->handle_subscription_suspended( $event );
					break;

				case 'BILLING.SUBSCRIPTION.UPDATED':
					$this->handle_subscription_updated( $event );
					break;

				case 'BILLING.SUBSCRIPTION.PAYMENT.FAILED':
					$this->handle_subscription_payment_failed( $event );
					break;

				default:
					// Log unknown event type
					if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
						error_log( 'PayPal: Unhandled webhook event: ' . $event_type );
					}
					break;
			}

			// Return success response to PayPal
			wp_send_json( [ 'success' => true ], 200 );

		} catch ( \Exception $e ) {
			// Log error
			error_log( 'PayPal Webhook Error: ' . $e->getMessage() );

			// Return error response
			wp_send_json( [
				'success' => false,
				'error' => $e->getMessage(),
			], 400 );
		}
	}

	/**
	 * Handle payment captured event
	 */
	protected function handle_payment_captured( array $event ) {
		$capture_id = $event['resource']['id'] ?? null;
		if ( ! $capture_id ) {
			return;
		}

		// Find order by capture ID
		$orders = \Voxel\Product_Types\Orders\Order::query( [
			'limit' => 1,
			'meta_query' => [
				[
					'key' => 'paypal.capture_id',
					'value' => $capture_id,
				],
			],
		] );

		if ( empty( $orders ) ) {
			return;
		}

		$order = $orders[0];
		$order->set_status( \Voxel\ORDER_COMPLETED );
		$order->save();

		do_action( 'voxel/paypal/payment-captured', $order, $event );
	}

	/**
	 * Handle payment failed event
	 */
	protected function handle_payment_failed( array $event ) {
		$capture_id = $event['resource']['id'] ?? null;
		if ( ! $capture_id ) {
			return;
		}

		$orders = \Voxel\Product_Types\Orders\Order::query( [
			'limit' => 1,
			'meta_query' => [
				[
					'key' => 'paypal.capture_id',
					'value' => $capture_id,
				],
			],
		] );

		if ( empty( $orders ) ) {
			return;
		}

		$order = $orders[0];
		$order->set_status( \Voxel\ORDER_CANCELED );
		$order->save();

		do_action( 'voxel/paypal/payment-failed', $order, $event );
	}

	/**
	 * Handle payment refunded event
	 */
	protected function handle_payment_refunded( array $event ) {
		$capture_id = $event['resource']['id'] ?? null;
		if ( ! $capture_id ) {
			return;
		}

		$orders = \Voxel\Product_Types\Orders\Order::query( [
			'limit' => 1,
			'meta_query' => [
				[
					'key' => 'paypal.capture_id',
					'value' => $capture_id,
				],
			],
		] );

		if ( empty( $orders ) ) {
			return;
		}

		$order = $orders[0];
		$order->set_status( \Voxel\ORDER_REFUNDED );
		$order->save();

		do_action( 'voxel/paypal/payment-refunded', $order, $event );
	}

	/**
	 * Handle authorization created event
	 */
	protected function handle_authorization_created( array $event ) {
		$authorization_id = $event['resource']['id'] ?? null;
		if ( ! $authorization_id ) {
			return;
		}

		$orders = \Voxel\Product_Types\Orders\Order::query( [
			'limit' => 1,
			'meta_query' => [
				[
					'key' => 'paypal.authorization_id',
					'value' => $authorization_id,
				],
			],
		] );

		if ( empty( $orders ) ) {
			return;
		}

		$order = $orders[0];
		$order->set_status( \Voxel\ORDER_PENDING_APPROVAL );
		$order->save();

		do_action( 'voxel/paypal/authorization-created', $order, $event );
	}

	/**
	 * Handle authorization voided event
	 */
	protected function handle_authorization_voided( array $event ) {
		$authorization_id = $event['resource']['id'] ?? null;
		if ( ! $authorization_id ) {
			return;
		}

		$orders = \Voxel\Product_Types\Orders\Order::query( [
			'limit' => 1,
			'meta_query' => [
				[
					'key' => 'paypal.authorization_id',
					'value' => $authorization_id,
				],
			],
		] );

		if ( empty( $orders ) ) {
			return;
		}

		$order = $orders[0];
		$order->set_status( \Voxel\ORDER_CANCELED );
		$order->save();

		do_action( 'voxel/paypal/authorization-voided', $order, $event );
	}

	/**
	 * Handle subscription activated event
	 */
	protected function handle_subscription_activated( array $event ) {
		$subscription_id = $event['resource']['id'] ?? null;
		if ( ! $subscription_id ) {
			return;
		}

		$order = $this->find_order_by_subscription_id( $subscription_id );
		if ( ! $order ) {
			return;
		}

		$payment_method = $order->get_payment_method();
		if ( $payment_method instanceof \VoxelPayPal\Payment_Methods\PayPal_Subscription ) {
			$payment_method->subscription_updated( $event['resource'] );
		}

		do_action( 'voxel/paypal/subscription-activated', $order, $event );
	}

	/**
	 * Handle subscription cancelled event
	 */
	protected function handle_subscription_cancelled( array $event ) {
		$subscription_id = $event['resource']['id'] ?? null;
		if ( ! $subscription_id ) {
			return;
		}

		$order = $this->find_order_by_subscription_id( $subscription_id );
		if ( ! $order ) {
			return;
		}

		$payment_method = $order->get_payment_method();
		if ( $payment_method instanceof \VoxelPayPal\Payment_Methods\PayPal_Subscription ) {
			$payment_method->subscription_updated( $event['resource'] );
		}

		do_action( 'voxel/paypal/subscription-cancelled', $order, $event );
	}

	/**
	 * Handle subscription suspended event
	 */
	protected function handle_subscription_suspended( array $event ) {
		$subscription_id = $event['resource']['id'] ?? null;
		if ( ! $subscription_id ) {
			return;
		}

		$order = $this->find_order_by_subscription_id( $subscription_id );
		if ( ! $order ) {
			return;
		}

		$payment_method = $order->get_payment_method();
		if ( $payment_method instanceof \VoxelPayPal\Payment_Methods\PayPal_Subscription ) {
			$payment_method->subscription_updated( $event['resource'] );
		}

		do_action( 'voxel/paypal/subscription-suspended', $order, $event );
	}

	/**
	 * Handle subscription updated event
	 */
	protected function handle_subscription_updated( array $event ) {
		$subscription_id = $event['resource']['id'] ?? null;
		if ( ! $subscription_id ) {
			return;
		}

		$order = $this->find_order_by_subscription_id( $subscription_id );
		if ( ! $order ) {
			return;
		}

		$payment_method = $order->get_payment_method();
		if ( $payment_method instanceof \VoxelPayPal\Payment_Methods\PayPal_Subscription ) {
			$payment_method->subscription_updated( $event['resource'] );
		}

		do_action( 'voxel/paypal/subscription-updated', $order, $event );
	}

	/**
	 * Handle subscription payment failed event
	 */
	protected function handle_subscription_payment_failed( array $event ) {
		$subscription_id = $event['resource']['id'] ?? null;
		if ( ! $subscription_id ) {
			return;
		}

		$order = $this->find_order_by_subscription_id( $subscription_id );
		if ( ! $order ) {
			return;
		}

		// Optionally mark order as past due or paused
		// This depends on your business logic

		do_action( 'voxel/paypal/subscription-payment-failed', $order, $event );
	}

	/**
	 * Find order by subscription ID
	 */
	protected function find_order_by_subscription_id( string $subscription_id ) {
		$orders = \Voxel\Product_Types\Orders\Order::query( [
			'limit' => 1,
			'meta_query' => [
				[
					'key' => 'paypal.subscription_id',
					'value' => $subscription_id,
				],
			],
		] );

		return ! empty( $orders ) ? $orders[0] : null;
	}
}
