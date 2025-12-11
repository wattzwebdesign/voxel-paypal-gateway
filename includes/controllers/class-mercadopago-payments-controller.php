<?php

namespace VoxelPayPal\Controllers;

use VoxelPayPal\MercadoPago_Client;

if ( ! defined('ABSPATH') ) {
	exit;
}

/**
 * Mercado Pago Payments Controller
 * Handles checkout return callbacks
 */
class MercadoPago_Payments_Controller extends \Voxel\Controllers\Base_Controller {

	protected function hooks(): void {
		// Checkout callbacks
		$this->on( 'voxel_ajax_mercadopago.checkout.success', '@handle_checkout_success' );
		$this->on( 'voxel_ajax_mercadopago.checkout.failure', '@handle_checkout_failure' );
		$this->on( 'voxel_ajax_mercadopago.checkout.pending', '@handle_checkout_pending' );

		// Subscription callback
		$this->on( 'voxel_ajax_mercadopago.subscription.callback', '@handle_subscription_callback' );

		// Allow non-logged in access (user might not be logged in on return)
		$this->on( 'voxel_ajax_nopriv_mercadopago.checkout.success', '@handle_checkout_success' );
		$this->on( 'voxel_ajax_nopriv_mercadopago.checkout.failure', '@handle_checkout_failure' );
		$this->on( 'voxel_ajax_nopriv_mercadopago.checkout.pending', '@handle_checkout_pending' );
		$this->on( 'voxel_ajax_nopriv_mercadopago.subscription.callback', '@handle_subscription_callback' );
	}

	/**
	 * Handle successful checkout return
	 */
	protected function handle_checkout_success(): void {
		try {
			$order_id = absint( $_GET['order_id'] ?? 0 );
			$payment_id = sanitize_text_field( $_GET['payment_id'] ?? '' );
			$collection_id = sanitize_text_field( $_GET['collection_id'] ?? '' );
			$collection_status = sanitize_text_field( $_GET['collection_status'] ?? '' );
			$external_reference = sanitize_text_field( $_GET['external_reference'] ?? '' );

			if ( ! $order_id ) {
				throw new \Exception( 'Invalid order ID' );
			}

			$order = \Voxel\Product_Types\Orders\Order::find( [ 'id' => $order_id ] );

			if ( ! $order ) {
				throw new \Exception( 'Order not found' );
			}

			$payment_method = $order->get_payment_method();

			// Get payment details from Mercado Pago
			$mp_payment_id = $payment_id ?: $collection_id;

			if ( $mp_payment_id ) {
				$response = MercadoPago_Client::get_payment( $mp_payment_id );

				if ( $response['success'] && ! empty( $response['data'] ) ) {
					if ( $payment_method instanceof \VoxelPayPal\Payment_Methods\MercadoPago_Payment ) {
						$payment_method->handle_payment_completed( $response['data'] );
					} elseif ( $payment_method instanceof \VoxelPayPal\Payment_Methods\MercadoPago_Subscription ) {
						$payment_method->handle_initial_payment_completed( $response['data'] );
					}
				}
			} else {
				// No payment ID yet - mark as pending (webhook will update)
				if ( $collection_status === 'approved' ) {
					$order->set_status( \Voxel\ORDER_COMPLETED );
				} else {
					$order->set_status( \Voxel\ORDER_PENDING_PAYMENT );
				}
				$order->save();
			}

			// Trigger success action
			do_action( 'voxel/mercadopago/checkout-success', $order );

			// Redirect to order confirmation or custom page
			$redirect_url = $this->get_success_redirect_url( $order );
			wp_redirect( $redirect_url );
			exit;

		} catch ( \Exception $e ) {
			error_log( 'Mercado Pago Checkout Success Error: ' . $e->getMessage() );

			wp_redirect( add_query_arg( [
				'payment_error' => urlencode( $e->getMessage() ),
			], home_url() ) );
			exit;
		}
	}

	/**
	 * Handle failed checkout return
	 */
	protected function handle_checkout_failure(): void {
		try {
			$order_id = absint( $_GET['order_id'] ?? 0 );

			if ( $order_id ) {
				$order = \Voxel\Product_Types\Orders\Order::find( [ 'id' => $order_id ] );

				if ( $order ) {
					$order->set_status( \Voxel\ORDER_CANCELED );
					$order->set_details( 'mercadopago.status', 'failed' );
					$order->set_details( 'mercadopago.failed_at', \Voxel\utc()->format( 'Y-m-d H:i:s' ) );
					$order->save();

					do_action( 'voxel/mercadopago/checkout-failure', $order );
				}
			}

			// Redirect to failure page
			$redirect_url = $this->get_failure_redirect_url();
			wp_redirect( $redirect_url );
			exit;

		} catch ( \Exception $e ) {
			error_log( 'Mercado Pago Checkout Failure Error: ' . $e->getMessage() );
			wp_redirect( home_url() );
			exit;
		}
	}

	/**
	 * Handle pending checkout return
	 */
	protected function handle_checkout_pending(): void {
		try {
			$order_id = absint( $_GET['order_id'] ?? 0 );
			$payment_id = sanitize_text_field( $_GET['payment_id'] ?? '' );
			$collection_id = sanitize_text_field( $_GET['collection_id'] ?? '' );

			if ( $order_id ) {
				$order = \Voxel\Product_Types\Orders\Order::find( [ 'id' => $order_id ] );

				if ( $order ) {
					$order->set_status( \Voxel\ORDER_PENDING_PAYMENT );
					$order->set_details( 'mercadopago.status', 'pending' );

					$mp_payment_id = $payment_id ?: $collection_id;
					if ( $mp_payment_id ) {
						$order->set_details( 'mercadopago.payment_id', $mp_payment_id );
					}

					$order->save();

					do_action( 'voxel/mercadopago/checkout-pending', $order );

					// Redirect to pending page
					$redirect_url = $this->get_pending_redirect_url( $order );
					wp_redirect( $redirect_url );
					exit;
				}
			}

			wp_redirect( home_url() );
			exit;

		} catch ( \Exception $e ) {
			error_log( 'Mercado Pago Checkout Pending Error: ' . $e->getMessage() );
			wp_redirect( home_url() );
			exit;
		}
	}

	/**
	 * Handle subscription callback
	 */
	protected function handle_subscription_callback(): void {
		try {
			$order_id = absint( $_GET['order_id'] ?? 0 );
			$preapproval_id = sanitize_text_field( $_GET['preapproval_id'] ?? '' );

			if ( ! $order_id ) {
				throw new \Exception( 'Invalid order ID' );
			}

			$order = \Voxel\Product_Types\Orders\Order::find( [ 'id' => $order_id ] );

			if ( ! $order ) {
				throw new \Exception( 'Order not found' );
			}

			$payment_method = $order->get_payment_method();

			// Get preapproval details if ID provided
			if ( $preapproval_id && $payment_method instanceof \VoxelPayPal\Payment_Methods\MercadoPago_Subscription ) {
				$response = MercadoPago_Client::get_preapproval( $preapproval_id );

				if ( $response['success'] && ! empty( $response['data'] ) ) {
					$payment_method->subscription_updated( $response['data'] );
				}
			}

			// Trigger success action
			do_action( 'voxel/mercadopago/subscription-callback', $order );

			// Redirect to order confirmation
			$redirect_url = $this->get_success_redirect_url( $order );
			wp_redirect( $redirect_url );
			exit;

		} catch ( \Exception $e ) {
			error_log( 'Mercado Pago Subscription Callback Error: ' . $e->getMessage() );

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
		$url = apply_filters( 'voxel/mercadopago/success-redirect-url', null, $order );

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
		$url = apply_filters( 'voxel/mercadopago/failure-redirect-url', null );

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
		$url = apply_filters( 'voxel/mercadopago/pending-redirect-url', null, $order );

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
