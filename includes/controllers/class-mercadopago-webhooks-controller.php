<?php

namespace VoxelPayPal\Controllers;

use VoxelPayPal\MercadoPago_Client;

if ( ! defined('ABSPATH') ) {
	exit;
}

/**
 * Mercado Pago Webhooks Controller
 * Handles Mercado Pago webhook events
 */
class MercadoPago_Webhooks_Controller extends \Voxel\Controllers\Base_Controller {

	protected function hooks(): void {
		$this->on( 'voxel_ajax_mercadopago.webhooks', '@handle_webhooks' );
		$this->on( 'voxel_ajax_nopriv_mercadopago.webhooks', '@handle_webhooks' );
	}

	/**
	 * Handle Mercado Pago webhooks
	 */
	protected function handle_webhooks(): void {
		try {
			// Get raw POST data
			$raw_body = file_get_contents( 'php://input' );
			$event = json_decode( $raw_body, true );

			if ( json_last_error() !== JSON_ERROR_NONE ) {
				throw new \Exception( 'Invalid JSON payload' );
			}

			// Get signature headers
			$x_signature = $_SERVER['HTTP_X_SIGNATURE'] ?? '';
			$x_request_id = $_SERVER['HTTP_X_REQUEST_ID'] ?? '';

			// Get data ID from query string or payload
			$data_id = sanitize_text_field( $_GET['data_id'] ?? $_GET['id'] ?? '' );
			if ( empty( $data_id ) && ! empty( $event['data']['id'] ) ) {
				$data_id = (string) $event['data']['id'];
			}

			// Verify webhook signature
			if ( ! empty( $x_signature ) && ! empty( $data_id ) ) {
				if ( ! MercadoPago_Client::verify_webhook_signature( $x_signature, $x_request_id, $data_id ) ) {
					// Debug logging for signature verification failures
					error_log( 'MP Webhook Signature Failed:' );
					error_log( '  - x_signature: ' . substr( $x_signature, 0, 80 ) . '...' );
					error_log( '  - x_request_id: ' . $x_request_id );
					error_log( '  - data_id: ' . $data_id );
					error_log( '  - webhook_secret_configured: ' . ( MercadoPago_Client::get_webhook_secret() ? 'yes' : 'no' ) );
					throw new \Exception( 'Invalid webhook signature' );
				}
			}

			// Log webhook for debugging
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'Mercado Pago Webhook: ' . ( $event['type'] ?? $event['topic'] ?? 'unknown' ) );
			}

			// Determine event type - Mercado Pago uses different formats
			$event_type = $event['type'] ?? '';
			$topic = $event['topic'] ?? '';
			$action = $event['action'] ?? '';

			// Route based on type or topic
			if ( $topic === 'payment' || $event_type === 'payment' ) {
				$this->handle_payment_event( $event );
			} elseif ( $topic === 'subscription_preapproval' || strpos( $event_type, 'subscription_preapproval' ) !== false ) {
				$this->handle_preapproval_event( $event );
			} elseif ( $topic === 'subscription_authorized_payment' || strpos( $event_type, 'authorized_payment' ) !== false ) {
				$this->handle_authorized_payment_event( $event );
			} elseif ( $topic === 'merchant_order' || $event_type === 'topic_merchant_order_wh' ) {
				$this->handle_merchant_order_event( $event );
			} elseif ( $event_type === 'mp-connect' ) {
				$this->handle_mp_connect_event( $event );
			} else {
				// Log unknown event type
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( 'Mercado Pago: Unhandled webhook event: ' . json_encode( [ 'type' => $event_type, 'topic' => $topic ] ) );
				}
			}

			// Return success response
			wp_send_json( [ 'success' => true ], 200 );

		} catch ( \Exception $e ) {
			error_log( 'Mercado Pago Webhook Error: ' . $e->getMessage() );

			wp_send_json( [
				'success' => false,
				'error' => $e->getMessage(),
			], 400 );
		}
	}

	/**
	 * Handle payment events
	 */
	protected function handle_payment_event( array $event ): void {
		$payment_id = $event['data']['id'] ?? null;

		if ( ! $payment_id ) {
			// Try to get from resource URL
			if ( ! empty( $event['resource'] ) && preg_match( '/\/payments\/(\d+)/', $event['resource'], $matches ) ) {
				$payment_id = $matches[1];
			}
		}

		if ( ! $payment_id ) {
			return;
		}

		// Get payment details from API
		$response = MercadoPago_Client::get_payment( $payment_id );

		if ( ! $response['success'] || empty( $response['data'] ) ) {
			return;
		}

		$payment = $response['data'];
		$external_reference = $payment['external_reference'] ?? '';

		// Find order by external reference
		$order = $this->find_order_by_external_reference( $external_reference );

		if ( ! $order ) {
			// Try to find by payment ID
			$order = $this->find_order_by_payment_id( $payment_id );
		}

		if ( ! $order ) {
			return;
		}

		$payment_method = $order->get_payment_method();

		if ( $payment_method instanceof \VoxelPayPal\Payment_Methods\MercadoPago_Payment ) {
			$payment_method->handle_payment_completed( $payment );
		} elseif ( $payment_method instanceof \VoxelPayPal\Payment_Methods\MercadoPago_Subscription ) {
			// Check if this is the initial payment or a recurring one
			$initial_payment_id = $order->get_details( 'mercadopago.initial_payment_id' );
			if ( ! $initial_payment_id ) {
				$payment_method->handle_initial_payment_completed( $payment );
			} else {
				$payment_method->handle_subscription_payment( $payment );
			}
		}

		do_action( 'voxel/mercadopago/webhook-payment', $order, $payment, $event );
	}

	/**
	 * Handle preapproval (subscription) events
	 */
	protected function handle_preapproval_event( array $event ): void {
		$preapproval_id = $event['data']['id'] ?? null;

		if ( ! $preapproval_id ) {
			if ( ! empty( $event['resource'] ) && preg_match( '/\/preapproval\/([a-zA-Z0-9]+)/', $event['resource'], $matches ) ) {
				$preapproval_id = $matches[1];
			}
		}

		if ( ! $preapproval_id ) {
			return;
		}

		// Get preapproval details from API
		$response = MercadoPago_Client::get_preapproval( $preapproval_id );

		if ( ! $response['success'] || empty( $response['data'] ) ) {
			return;
		}

		$preapproval = $response['data'];
		$external_reference = $preapproval['external_reference'] ?? '';

		// Find order by external reference
		$order = $this->find_order_by_external_reference( $external_reference );

		if ( ! $order ) {
			// Try to find by preapproval ID
			$order = $this->find_order_by_preapproval_id( $preapproval_id );
		}

		if ( ! $order ) {
			return;
		}

		$payment_method = $order->get_payment_method();

		if ( $payment_method instanceof \VoxelPayPal\Payment_Methods\MercadoPago_Subscription ) {
			$payment_method->subscription_updated( $preapproval );
		}

		do_action( 'voxel/mercadopago/webhook-preapproval', $order, $preapproval, $event );
	}

	/**
	 * Handle authorized payment events (subscription recurring payments)
	 */
	protected function handle_authorized_payment_event( array $event ): void {
		$payment_id = $event['data']['id'] ?? null;

		if ( ! $payment_id ) {
			return;
		}

		// Get payment details from API
		$response = MercadoPago_Client::get_payment( $payment_id );

		if ( ! $response['success'] || empty( $response['data'] ) ) {
			return;
		}

		$payment = $response['data'];

		// Get preapproval ID from payment
		$preapproval_id = $payment['preapproval_id'] ?? null;

		if ( ! $preapproval_id ) {
			return;
		}

		// Find order by preapproval ID
		$order = $this->find_order_by_preapproval_id( $preapproval_id );

		if ( ! $order ) {
			return;
		}

		$payment_method = $order->get_payment_method();

		if ( $payment_method instanceof \VoxelPayPal\Payment_Methods\MercadoPago_Subscription ) {
			$payment_method->handle_subscription_payment( $payment );
		}

		do_action( 'voxel/mercadopago/webhook-authorized-payment', $order, $payment, $event );
	}

	/**
	 * Handle merchant order events (marketplace)
	 */
	protected function handle_merchant_order_event( array $event ): void {
		// Merchant orders are for marketplace - log details for debugging
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'Mercado Pago: Merchant order webhook received' );
			error_log( 'Mercado Pago: Merchant order data: ' . json_encode( $event ) );
		}

		// Try to get order ID from the event
		$merchant_order_id = $event['data']['id'] ?? null;

		if ( ! $merchant_order_id ) {
			return;
		}

		// Fetch merchant order details from API
		$response = MercadoPago_Client::make_request( "/merchant_orders/{$merchant_order_id}" );

		if ( ! $response['success'] || empty( $response['data'] ) ) {
			error_log( 'Mercado Pago: Failed to fetch merchant order details' );
			return;
		}

		$merchant_order = $response['data'];
		$external_reference = $merchant_order['external_reference'] ?? '';

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'Mercado Pago: Merchant order external_reference: ' . $external_reference );
			error_log( 'Mercado Pago: Merchant order status: ' . ( $merchant_order['status'] ?? 'unknown' ) );
		}

		// Find order by external reference and process if needed
		$order = $this->find_order_by_external_reference( $external_reference );

		if ( $order ) {
			// Check if all payments are approved
			$payments = $merchant_order['payments'] ?? [];
			$total_paid = 0;
			$all_approved = true;

			foreach ( $payments as $payment ) {
				if ( $payment['status'] === 'approved' ) {
					$total_paid += $payment['total_paid_amount'] ?? 0;
				} else {
					$all_approved = false;
				}
			}

			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'Mercado Pago: Merchant order total_paid: ' . $total_paid );
				error_log( 'Mercado Pago: Merchant order all_approved: ' . ( $all_approved ? 'yes' : 'no' ) );
			}
		}
	}

	/**
	 * Handle mp-connect events (OAuth connection notifications)
	 */
	protected function handle_mp_connect_event( array $event ): void {
		// OAuth connection events from marketplace
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'Mercado Pago: OAuth connect event received' );
			error_log( 'Mercado Pago: Connect event data: ' . json_encode( $event ) );
		}

		// These events notify when a seller connects/disconnects their account
		// Currently just logging - could be used to sync connection status
	}

	/**
	 * Find order by external reference
	 */
	protected function find_order_by_external_reference( string $reference ): ?\Voxel\Product_Types\Orders\Order {
		if ( empty( $reference ) ) {
			return null;
		}

		// Parse reference: voxel_order_123 or voxel_subscription_123
		if ( preg_match( '/voxel_(?:order|subscription)_(\d+)/', $reference, $matches ) ) {
			$order_id = absint( $matches[1] );

			return \Voxel\Product_Types\Orders\Order::find( [ 'id' => $order_id ] );
		}

		return null;
	}

	/**
	 * Find order by Mercado Pago payment ID
	 */
	protected function find_order_by_payment_id( string $payment_id ): ?\Voxel\Product_Types\Orders\Order {
		if ( empty( $payment_id ) ) {
			return null;
		}

		$orders = \Voxel\Product_Types\Orders\Order::query( [
			'limit' => 1,
			'meta_query' => [
				[
					'key' => 'mercadopago.payment_id',
					'value' => $payment_id,
				],
			],
		] );

		return ! empty( $orders ) ? $orders[0] : null;
	}

	/**
	 * Find order by Mercado Pago preapproval ID
	 */
	protected function find_order_by_preapproval_id( string $preapproval_id ): ?\Voxel\Product_Types\Orders\Order {
		if ( empty( $preapproval_id ) ) {
			return null;
		}

		$orders = \Voxel\Product_Types\Orders\Order::query( [
			'limit' => 1,
			'meta_query' => [
				[
					'key' => 'mercadopago.preapproval_id',
					'value' => $preapproval_id,
				],
			],
		] );

		return ! empty( $orders ) ? $orders[0] : null;
	}
}
