<?php

namespace VoxelPayPal\Controllers;

use VoxelPayPal\PayPal_Client;

if ( ! defined('ABSPATH') ) {
	exit;
}

/**
 * Frontend Payments Controller
 * Handles payment success/cancel callbacks from PayPal
 */
class Frontend_Payments_Controller extends \Voxel\Controllers\Base_Controller {

	protected function hooks() {
		$this->on( 'voxel_ajax_paypal.checkout.success', '@handle_checkout_success' );
		$this->on( 'voxel_ajax_paypal.checkout.cancel', '@handle_checkout_cancel' );
	}

	/**
	 * Handle successful checkout
	 */
	protected function handle_checkout_success() {
		error_log( 'PayPal: Starting checkout success handler' );

		$order_id = $_REQUEST['order_id'] ?? null;
		if ( ! is_numeric( $order_id ) ) {
			error_log( 'PayPal: Invalid or missing order_id' );
			exit;
		}

		try {
			$order_id = absint( $order_id );
			error_log( 'PayPal: Order ID: ' . $order_id );

			$order = \Voxel\Product_Types\Orders\Order::find( [
				'id' => $order_id,
			] );

			if ( ! $order ) {
				error_log( 'PayPal: Order not found' );
				throw new \Exception( 'Order not found' );
			}

			error_log( 'PayPal: Order found, checking payment method' );

			$payment_method = $order->get_payment_method();
			if ( ! ( $payment_method instanceof \VoxelPayPal\Payment_Methods\PayPal_Payment ) ) {
				throw new \Exception( 'Invalid payment method' );
			}

			// Get PayPal order ID
			$paypal_order_id = $order->get_details( 'paypal.order_id' );
			if ( ! $paypal_order_id ) {
				error_log( 'PayPal: PayPal order ID not found in order details' );
				throw new \Exception( 'PayPal order ID not found' );
			}

			error_log( 'PayPal: PayPal Order ID: ' . $paypal_order_id );

			// Capture the order if automatic
			$capture_method = $payment_method->get_capture_method();
			error_log( 'PayPal: Capture method: ' . $capture_method );

			if ( $capture_method === 'automatic' ) {
				error_log( 'PayPal: Capturing order...' );
				$response = PayPal_Client::capture_order( $paypal_order_id );

				if ( ! $response['success'] ) {
					error_log( 'PayPal: Capture failed: ' . ( $response['error'] ?? 'Unknown error' ) );
					throw new \Exception( $response['error'] ?? 'Failed to capture payment' );
				}

				error_log( 'PayPal: Capture successful' );
				$paypal_order = $response['data'];
			} else {
				// For manual capture, just get the order details
				error_log( 'PayPal: Getting order details (manual capture)...' );
				$response = PayPal_Client::get_order( $paypal_order_id );

				if ( ! $response['success'] ) {
					error_log( 'PayPal: Get order failed: ' . ( $response['error'] ?? 'Unknown error' ) );
					throw new \Exception( $response['error'] ?? 'Failed to get order details' );
				}

				$paypal_order = $response['data'];
			}

			// Update Voxel order
			error_log( 'PayPal: Updating Voxel order...' );
			$payment_method->handle_order_completed( $paypal_order );

			// Clear cart if needed
			if ( $order->get_details( 'cart.type' ) === 'customer_cart' ) {
				error_log( 'PayPal: Clearing customer cart...' );
				$customer = $order->get_customer();
				if ( $customer ) {
					$customer_cart = $customer->get_cart();
					$customer_cart->empty();
					$customer_cart->update();
				}
			}

			// Redirect to order success page
			$redirect_url = $order->get_link();
			error_log( 'PayPal: Redirecting to: ' . $redirect_url );
			wp_safe_redirect( $redirect_url );
			exit;

		} catch ( \Exception $e ) {
			error_log( 'PayPal: Error in checkout success: ' . $e->getMessage() );
			error_log( 'PayPal: Stack trace: ' . $e->getTraceAsString() );

			wp_die(
				esc_html( $e->getMessage() ),
				esc_html__( 'Payment Error', 'voxel-paypal-gateway' ),
				[ 'back_link' => true ]
			);
		}
	}

	/**
	 * Handle checkout cancellation
	 */
	protected function handle_checkout_cancel() {
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
			$order->save();
		}

		$redirect_to = ! empty( $_GET['redirect_to'] ) ? urldecode( $_GET['redirect_to'] ) : home_url('/');
		wp_safe_redirect( $redirect_to );
		exit;
	}
}
