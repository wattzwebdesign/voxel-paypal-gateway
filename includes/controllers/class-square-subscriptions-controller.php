<?php

namespace VoxelPayPal\Controllers;

use VoxelPayPal\Square_Client;

if ( ! defined('ABSPATH') ) {
	exit;
}

/**
 * Square Subscriptions Controller
 * Handles subscription success/cancel callbacks from Square
 */
class Square_Subscriptions_Controller extends \Voxel\Controllers\Base_Controller {

	protected function hooks() {
		$this->on( 'voxel_ajax_square.subscription.success', '@handle_subscription_success' );
		$this->on( 'voxel_ajax_square.subscription.cancel', '@handle_subscription_cancel' );
	}

	/**
	 * Handle successful subscription checkout
	 */
	protected function handle_subscription_success() {
		error_log( 'Square: Starting subscription success handler' );

		$order_id = $_REQUEST['order_id'] ?? null;
		if ( ! is_numeric( $order_id ) ) {
			error_log( 'Square: Invalid or missing order_id' );
			exit;
		}

		try {
			$order_id = absint( $order_id );
			error_log( 'Square: Order ID: ' . $order_id );

			$order = \Voxel\Product_Types\Orders\Order::find( [
				'id' => $order_id,
			] );

			if ( ! $order ) {
				error_log( 'Square: Order not found' );
				throw new \Exception( 'Order not found' );
			}

			error_log( 'Square: Order found, checking payment method' );

			$payment_method = $order->get_payment_method();
			if ( ! ( $payment_method instanceof \VoxelPayPal\Payment_Methods\Square_Subscription ) ) {
				throw new \Exception( 'Invalid payment method' );
			}

			// Get transaction details from callback
			$transaction_id = $_REQUEST['transactionId'] ?? $_REQUEST['transaction_id'] ?? null;

			error_log( 'Square: Transaction ID: ' . ( $transaction_id ?? 'none' ) );

			// Try to get payment details
			$payment = null;

			if ( $transaction_id ) {
				$response = Square_Client::get_payment( $transaction_id );
				if ( $response['success'] && ! empty( $response['data']['payment'] ) ) {
					$payment = $response['data']['payment'];
				}
			}

			// If we couldn't get payment, try listing recent payments
			if ( ! $payment ) {
				$list_response = Square_Client::list_payments( [
					'location_id' => Square_Client::get_location_id(),
					'sort_order' => 'DESC',
					'limit' => 10,
				] );

				if ( $list_response['success'] && ! empty( $list_response['data']['payments'] ) ) {
					foreach ( $list_response['data']['payments'] as $p ) {
						$note = $p['note'] ?? '';
						if ( strpos( $note, 'voxel_subscription_' . $order_id ) !== false ) {
							$payment = $p;
							break;
						}
					}
				}
			}

			// Process the initial subscription payment
			if ( $payment && isset( $payment['status'] ) && $payment['status'] === 'COMPLETED' ) {
				error_log( 'Square: Subscription initial payment completed' );
				$payment_method->handle_initial_payment_completed( $payment );
			} else {
				// Mark as pending - webhook will confirm payment
				error_log( 'Square: Payment pending, waiting for webhook confirmation' );
				$order->set_status( \Voxel\ORDER_PENDING_PAYMENT );
				$order->set_details( 'square.subscription_checkout_completed_at', \Voxel\utc()->format( 'Y-m-d H:i:s' ) );
				$order->save();
			}

			// Clear cart if needed
			if ( $order->get_details( 'cart.type' ) === 'customer_cart' ) {
				error_log( 'Square: Clearing customer cart...' );
				$customer = $order->get_customer();
				if ( $customer ) {
					$customer_cart = $customer->get_cart();
					$customer_cart->empty();
					$customer_cart->update();
				}
			}

			// Redirect to order success page
			$redirect_url = $order->get_link();
			error_log( 'Square: Redirecting to: ' . $redirect_url );
			wp_safe_redirect( $redirect_url );
			exit;

		} catch ( \Exception $e ) {
			error_log( 'Square: Error in subscription success: ' . $e->getMessage() );
			error_log( 'Square: Stack trace: ' . $e->getTraceAsString() );

			wp_die(
				esc_html( $e->getMessage() ),
				esc_html__( 'Subscription Error', 'voxel-payment-gateways' ),
				[ 'back_link' => true ]
			);
		}
	}

	/**
	 * Handle subscription cancellation
	 */
	protected function handle_subscription_cancel() {
		$order_id = $_REQUEST['order_id'] ?? null;
		if ( ! is_numeric( $order_id ) ) {
			exit;
		}

		$order_id = absint( $order_id );
		$order = \Voxel\Product_Types\Orders\Order::find( [
			'id' => $order_id,
		] );

		if ( $order ) {
			$order->set_status( \Voxel\ORDER_CANCELED );
			$order->set_details( 'square.subscription_canceled_at', \Voxel\utc()->format( 'Y-m-d H:i:s' ) );
			$order->save();
		}

		$redirect_to = ! empty( $_GET['redirect_to'] ) ? urldecode( $_GET['redirect_to'] ) : home_url('/');
		wp_safe_redirect( $redirect_to );
		exit;
	}
}
