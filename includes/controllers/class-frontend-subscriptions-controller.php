<?php

namespace VoxelPayPal\Controllers;

use VoxelPayPal\PayPal_Client;

if ( ! defined('ABSPATH') ) {
	exit;
}

/**
 * Frontend Subscriptions Controller
 * Handles subscription success/cancel callbacks from PayPal
 */
class Frontend_Subscriptions_Controller extends \Voxel\Controllers\Base_Controller {

	protected function hooks() {
		$this->on( 'voxel_ajax_paypal.subscription.success', '@handle_subscription_success' );
		$this->on( 'voxel_ajax_paypal.subscription.cancel', '@handle_subscription_cancel' );
	}

	/**
	 * Handle successful subscription approval
	 */
	protected function handle_subscription_success() {
		error_log( 'PayPal: Starting subscription success handler' );

		$order_id = $_REQUEST['order_id'] ?? null;
		$subscription_id = $_REQUEST['subscription_id'] ?? null;

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
			if ( ! ( $payment_method instanceof \VoxelPayPal\Payment_Methods\PayPal_Subscription ) ) {
				throw new \Exception( 'Invalid payment method' );
			}

			// Get PayPal subscription ID (either from URL or from order details)
			$paypal_subscription_id = $subscription_id ?: $order->get_details( 'paypal.subscription_id' );
			if ( ! $paypal_subscription_id ) {
				error_log( 'PayPal: PayPal subscription ID not found' );
				throw new \Exception( 'PayPal subscription ID not found' );
			}

			error_log( 'PayPal: PayPal Subscription ID: ' . $paypal_subscription_id );

			// Get subscription details from PayPal
			error_log( 'PayPal: Getting subscription details...' );
			$response = PayPal_Client::get_subscription( $paypal_subscription_id );

			if ( ! $response['success'] ) {
				error_log( 'PayPal: Get subscription failed: ' . ( $response['error'] ?? 'Unknown error' ) );
				throw new \Exception( $response['error'] ?? 'Failed to get subscription details' );
			}

			$paypal_subscription = $response['data'];
			error_log( 'PayPal: Subscription status: ' . $paypal_subscription['status'] );

			// Update Voxel order
			error_log( 'PayPal: Updating Voxel order...' );
			$payment_method->subscription_updated( $paypal_subscription );

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
			error_log( 'PayPal: Error in subscription success: ' . $e->getMessage() );
			error_log( 'PayPal: Stack trace: ' . $e->getTraceAsString() );

			wp_die(
				esc_html( $e->getMessage() ),
				esc_html__( 'Subscription Error', 'voxel-paypal-gateway' ),
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
			$order->save();
		}

		$redirect_to = ! empty( $_REQUEST['redirect_to'] ) ? urldecode( $_REQUEST['redirect_to'] ) : home_url('/');
		wp_safe_redirect( $redirect_to );
		exit;
	}
}
