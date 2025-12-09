<?php

namespace VoxelPayPal\Controllers;

use VoxelPayPal\Square_Client;

if ( ! defined('ABSPATH') ) {
	exit;
}

/**
 * Square Payments Controller
 * Handles payment success/cancel callbacks from Square
 */
class Square_Payments_Controller extends \Voxel\Controllers\Base_Controller {

	protected function hooks() {
		$this->on( 'voxel_ajax_square.checkout.success', '@handle_checkout_success' );
		$this->on( 'voxel_ajax_square.checkout.cancel', '@handle_checkout_cancel' );
	}

	/**
	 * Handle successful checkout
	 */
	protected function handle_checkout_success() {
		error_log( 'Square: Starting checkout success handler' );

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
			if ( ! ( $payment_method instanceof \VoxelPayPal\Payment_Methods\Square_Payment ) ) {
				throw new \Exception( 'Invalid payment method' );
			}

			// Get Square order/payment details from the callback
			// Square redirects with transaction details in URL params
			$transaction_id = $_REQUEST['transactionId'] ?? $_REQUEST['transaction_id'] ?? null;
			$checkout_id = $_REQUEST['checkoutId'] ?? $_REQUEST['checkout_id'] ?? null;

			error_log( 'Square: Transaction ID: ' . ( $transaction_id ?? 'none' ) );
			error_log( 'Square: Checkout ID: ' . ( $checkout_id ?? 'none' ) );

			// Get stored order ID from our order
			$square_order_id = $order->get_details( 'square.order_id' );
			error_log( 'Square: Stored Square Order ID: ' . ( $square_order_id ?? 'none' ) );

			// Try to get payment details
			$payment = null;

			// Method 1: Get payment by transaction ID (most reliable)
			if ( $transaction_id ) {
				error_log( 'Square: Fetching payment by transaction ID...' );
				$response = Square_Client::get_payment( $transaction_id );
				error_log( 'Square: Get payment response: ' . json_encode( $response ) );
				if ( $response['success'] && ! empty( $response['data']['payment'] ) ) {
					$payment = $response['data']['payment'];
					error_log( 'Square: Payment found, status: ' . ( $payment['status'] ?? 'unknown' ) );
				}
			}

			// Method 2: Get order and check for payments
			if ( ! $payment && $square_order_id ) {
				error_log( 'Square: Fetching order to find payment...' );
				$order_response = Square_Client::get_order( $square_order_id );
				if ( $order_response['success'] && ! empty( $order_response['data']['order'] ) ) {
					$square_order = $order_response['data']['order'];
					$tenders = $square_order['tenders'] ?? [];
					if ( ! empty( $tenders ) ) {
						// Get the first tender's payment ID
						$tender_payment_id = $tenders[0]['payment_id'] ?? $tenders[0]['id'] ?? null;
						if ( $tender_payment_id ) {
							$pay_response = Square_Client::get_payment( $tender_payment_id );
							if ( $pay_response['success'] && ! empty( $pay_response['data']['payment'] ) ) {
								$payment = $pay_response['data']['payment'];
								error_log( 'Square: Payment found via order, status: ' . ( $payment['status'] ?? 'unknown' ) );
							}
						}
					}
				}
			}

			// Method 3: List recent payments and find one matching our order
			if ( ! $payment ) {
				error_log( 'Square: Listing recent payments to find match...' );
				$list_response = Square_Client::list_payments( [
					'location_id' => Square_Client::get_location_id(),
					'sort_order' => 'DESC',
					'limit' => 20,
				] );

				if ( $list_response['success'] && ! empty( $list_response['data']['payments'] ) ) {
					foreach ( $list_response['data']['payments'] as $p ) {
						// Check if payment is linked to our Square order
						$payment_order_id = $p['order_id'] ?? '';
						if ( $square_order_id && $payment_order_id === $square_order_id ) {
							$payment = $p;
							error_log( 'Square: Payment found by order ID match, status: ' . ( $payment['status'] ?? 'unknown' ) );
							break;
						}
						// Also check note for order reference
						$note = $p['note'] ?? '';
						if ( strpos( $note, 'voxel_order_' . $order_id ) !== false ) {
							$payment = $p;
							error_log( 'Square: Payment found by note match, status: ' . ( $payment['status'] ?? 'unknown' ) );
							break;
						}
					}
				}
			}

			// If we found a payment, process it
			if ( $payment && isset( $payment['status'] ) ) {
				$status = $payment['status'];
				error_log( 'Square: Payment found with status: ' . $status );

				if ( $status === 'COMPLETED' ) {
					error_log( 'Square: Payment COMPLETED - processing order' );
					$payment_method->handle_order_completed( $payment );
				} elseif ( $status === 'APPROVED' ) {
					// APPROVED means payment was successful but may need capture
					// For checkout links, this typically means it's done
					error_log( 'Square: Payment APPROVED - treating as completed' );
					$payment_method->handle_order_completed( $payment );
				} else {
					// Other statuses (PENDING, FAILED, etc.)
					error_log( 'Square: Payment status ' . $status . ' - setting pending' );
					$order->set_details( 'square.payment_id', $payment['id'] ?? null );
					$order->set_details( 'square.payment_status', $status );
					$order->set_details( 'square.checkout_completed_at', \Voxel\utc()->format( 'Y-m-d H:i:s' ) );
					$order->set_status( \Voxel\ORDER_PENDING_PAYMENT );
					$order->save();
				}
			} else {
				// No payment found via API - but user completed checkout
				// This can happen in sandbox or if there's API lag
				// Check the Square order status instead
				error_log( 'Square: No payment found, checking order status...' );

				$order_completed = false;
				if ( $square_order_id ) {
					$order_response = Square_Client::get_order( $square_order_id );
					error_log( 'Square: Order response: ' . json_encode( $order_response ) );

					if ( $order_response['success'] && ! empty( $order_response['data']['order'] ) ) {
						$sq_order = $order_response['data']['order'];
						$order_state = $sq_order['state'] ?? '';
						error_log( 'Square: Order state: ' . $order_state );

						// COMPLETED or OPEN with tenders means payment went through
						if ( $order_state === 'COMPLETED' || ( $order_state === 'OPEN' && ! empty( $sq_order['tenders'] ) ) ) {
							error_log( 'Square: Order has tenders/completed - marking as completed' );
							$order->set_status( \Voxel\ORDER_COMPLETED );
							$order->set_details( 'square.order_state', $order_state );
							$order->set_details( 'square.checkout_completed_at', \Voxel\utc()->format( 'Y-m-d H:i:s' ) );
							$order->save();
							$order_completed = true;
						}
					}
				}

				if ( ! $order_completed ) {
					// Still couldn't verify - mark pending for webhook
					error_log( 'Square: Could not verify payment - marking as pending for webhook' );
					$order->set_status( \Voxel\ORDER_PENDING_PAYMENT );
					$order->set_details( 'square.checkout_completed_at', \Voxel\utc()->format( 'Y-m-d H:i:s' ) );
					$order->save();
				}
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
			error_log( 'Square: Error in checkout success: ' . $e->getMessage() );
			error_log( 'Square: Stack trace: ' . $e->getTraceAsString() );

			wp_die(
				esc_html( $e->getMessage() ),
				esc_html__( 'Payment Error', 'voxel-payment-gateways' ),
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
			$order->set_details( 'square.canceled_at', \Voxel\utc()->format( 'Y-m-d H:i:s' ) );
			$order->save();
		}

		$redirect_to = ! empty( $_GET['redirect_to'] ) ? urldecode( $_GET['redirect_to'] ) : home_url('/');
		wp_safe_redirect( $redirect_to );
		exit;
	}
}
