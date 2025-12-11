<?php

namespace VoxelPayPal\Controllers;

use VoxelPayPal\Paystack_Client;

if ( ! defined('ABSPATH') ) {
	exit;
}

/**
 * Paystack Payments Controller
 * Handles checkout return callbacks
 */
class Paystack_Payments_Controller extends \Voxel\Controllers\Base_Controller {

	protected function hooks(): void {
		// Checkout callback (Paystack uses single callback URL)
		$this->on( 'voxel_ajax_paystack.checkout.callback', '@handle_checkout_callback' );

		// Allow non-logged in access (user might not be logged in on return)
		$this->on( 'voxel_ajax_nopriv_paystack.checkout.callback', '@handle_checkout_callback' );

		// Subscription callback
		$this->on( 'voxel_ajax_paystack.subscription.callback', '@handle_subscription_callback' );
		$this->on( 'voxel_ajax_nopriv_paystack.subscription.callback', '@handle_subscription_callback' );
	}

	/**
	 * Handle Paystack checkout callback
	 * Paystack redirects back with ?trxref=REFERENCE&reference=REFERENCE
	 */
	protected function handle_checkout_callback(): void {
		try {
			$order_id = absint( $_GET['order_id'] ?? 0 );
			$reference = sanitize_text_field( $_GET['reference'] ?? $_GET['trxref'] ?? '' );

			if ( ! $order_id ) {
				throw new \Exception( 'Invalid order ID' );
			}

			$order = \Voxel\Product_Types\Orders\Order::find( [ 'id' => $order_id ] );

			if ( ! $order ) {
				throw new \Exception( 'Order not found' );
			}

			$payment_method = $order->get_payment_method();

			// Verify the transaction with Paystack
			$stored_reference = $order->get_details( 'paystack.reference' );
			$verify_reference = $reference ?: $stored_reference;

			if ( $verify_reference ) {
				$response = Paystack_Client::verify_transaction( $verify_reference );

				if ( $response['success'] && ! empty( $response['data'] ) ) {
					$transaction = $response['data'];

					// Check transaction status
					if ( $transaction['status'] === 'success' ) {
						if ( $payment_method instanceof \VoxelPayPal\Payment_Methods\Paystack_Payment ) {
							$payment_method->handle_payment_completed( $transaction );
						} elseif ( $payment_method instanceof \VoxelPayPal\Payment_Methods\Paystack_Subscription ) {
							$payment_method->handle_initial_payment_completed( $transaction );
						}

						// Empty the cart after successful payment
						if ( $order->get_details( 'cart.type' ) === 'customer_cart' ) {
							$customer = $order->get_customer();
							if ( $customer ) {
								$customer_cart = $customer->get_cart();
								$customer_cart->empty();
								$customer_cart->update();
							}
						}

						// Trigger success action
						do_action( 'voxel/paystack/checkout-success', $order, $transaction );

						// Redirect to order page
						$redirect_url = $order->get_link();
						wp_safe_redirect( $redirect_url );
						exit;
					} elseif ( $transaction['status'] === 'abandoned' || $transaction['status'] === 'failed' ) {
						// Payment failed
						$order->set_status( \Voxel\ORDER_CANCELED );
						$order->set_details( 'paystack.status', $transaction['status'] );
						$order->set_details( 'paystack.failed_at', \Voxel\utc()->format( 'Y-m-d H:i:s' ) );
						$order->save();

						do_action( 'voxel/paystack/checkout-failure', $order, $transaction );

						$redirect_url = $this->get_failure_redirect_url();
						wp_redirect( $redirect_url );
						exit;
					} else {
						// Payment pending or other status
						$order->set_status( \Voxel\ORDER_PENDING_PAYMENT );
						$order->set_details( 'paystack.status', $transaction['status'] );
						$order->save();

						do_action( 'voxel/paystack/checkout-pending', $order, $transaction );

						$redirect_url = $this->get_pending_redirect_url( $order );
						wp_redirect( $redirect_url );
						exit;
					}
				} else {
					// Verification failed - might need to wait for webhook
					$order->set_status( \Voxel\ORDER_PENDING_PAYMENT );
					$order->set_details( 'paystack.verification_error', $response['error'] ?? 'Unknown error' );
					$order->save();

					$redirect_url = $this->get_pending_redirect_url( $order );
					wp_redirect( $redirect_url );
					exit;
				}
			} else {
				// No reference available
				throw new \Exception( 'Transaction reference not found' );
			}

		} catch ( \Exception $e ) {
			error_log( 'Paystack Checkout Callback Error: ' . $e->getMessage() );

			wp_redirect( add_query_arg( [
				'payment_error' => urlencode( $e->getMessage() ),
			], home_url() ) );
			exit;
		}
	}

	/**
	 * Handle subscription callback
	 */
	protected function handle_subscription_callback(): void {
		try {
			$order_id = absint( $_GET['order_id'] ?? 0 );
			$reference = sanitize_text_field( $_GET['reference'] ?? $_GET['trxref'] ?? '' );

			if ( ! $order_id ) {
				throw new \Exception( 'Invalid order ID' );
			}

			$order = \Voxel\Product_Types\Orders\Order::find( [ 'id' => $order_id ] );

			if ( ! $order ) {
				throw new \Exception( 'Order not found' );
			}

			$payment_method = $order->get_payment_method();

			// Verify the transaction
			$stored_reference = $order->get_details( 'paystack.reference' );
			$verify_reference = $reference ?: $stored_reference;

			if ( $verify_reference && $payment_method instanceof \VoxelPayPal\Payment_Methods\Paystack_Subscription ) {
				$response = Paystack_Client::verify_transaction( $verify_reference );

				if ( $response['success'] && ! empty( $response['data'] ) ) {
					$transaction = $response['data'];
					$payment_method->handle_initial_payment_completed( $transaction );
				}
			}

			// Empty the cart after successful subscription
			if ( $order->get_details( 'cart.type' ) === 'customer_cart' ) {
				$customer = $order->get_customer();
				if ( $customer ) {
					$customer_cart = $customer->get_cart();
					$customer_cart->empty();
					$customer_cart->update();
				}
			}

			// Trigger success action
			do_action( 'voxel/paystack/subscription-callback', $order );

			// Redirect to order page
			$redirect_url = $order->get_link();
			wp_safe_redirect( $redirect_url );
			exit;

		} catch ( \Exception $e ) {
			error_log( 'Paystack Subscription Callback Error: ' . $e->getMessage() );

			wp_redirect( add_query_arg( [
				'subscription_error' => urlencode( $e->getMessage() ),
			], home_url() ) );
			exit;
		}
	}

	/**
	 * Get success redirect URL
	 */
	protected function get_success_redirect_url( \Voxel\Product_Types\Orders\Order $order ): string {
		// Try to get from filter
		$url = apply_filters( 'voxel/paystack/success-redirect-url', null, $order );

		if ( $url ) {
			return $url;
		}

		// Default to orders page with success message
		$orders_page = get_option( 'voxel_orders_page' );

		if ( $orders_page ) {
			return add_query_arg( [
				'order_id' => $order->get_id(),
				'payment_success' => '1',
			], get_permalink( $orders_page ) );
		}

		return add_query_arg( [
			'payment_success' => '1',
		], home_url() );
	}

	/**
	 * Get failure redirect URL
	 */
	protected function get_failure_redirect_url(): string {
		$url = apply_filters( 'voxel/paystack/failure-redirect-url', null );

		if ( $url ) {
			return $url;
		}

		return add_query_arg( [
			'payment_failed' => '1',
		], home_url() );
	}

	/**
	 * Get pending redirect URL
	 */
	protected function get_pending_redirect_url( \Voxel\Product_Types\Orders\Order $order ): string {
		$url = apply_filters( 'voxel/paystack/pending-redirect-url', null, $order );

		if ( $url ) {
			return $url;
		}

		$orders_page = get_option( 'voxel_orders_page' );

		if ( $orders_page ) {
			return add_query_arg( [
				'order_id' => $order->get_id(),
				'payment_pending' => '1',
			], get_permalink( $orders_page ) );
		}

		return add_query_arg( [
			'payment_pending' => '1',
		], home_url() );
	}
}
