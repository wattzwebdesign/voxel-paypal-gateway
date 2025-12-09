<?php

namespace VoxelPayPal;

if ( ! defined('ABSPATH') ) {
	exit;
}

/**
 * Square API Client Handler
 */
class Square_Client {

	/**
	 * Get Square API base URL
	 */
	public static function get_api_base_url(): string {
		return self::is_test_mode()
			? 'https://connect.squareupsandbox.com'
			: 'https://connect.squareup.com';
	}

	/**
	 * Check if test mode is enabled
	 */
	public static function is_test_mode(): bool {
		$mode = \Voxel\get( 'payments.square.mode', 'sandbox' );
		return $mode === 'sandbox';
	}

	/**
	 * Get application ID
	 */
	public static function get_application_id(): ?string {
		$mode = self::is_test_mode() ? 'sandbox' : 'live';
		return \Voxel\get( "payments.square.{$mode}.application_id" );
	}

	/**
	 * Get access token
	 */
	public static function get_access_token(): ?string {
		$mode = self::is_test_mode() ? 'sandbox' : 'live';
		return \Voxel\get( "payments.square.{$mode}.access_token" );
	}

	/**
	 * Get location ID
	 */
	public static function get_location_id(): ?string {
		$mode = self::is_test_mode() ? 'sandbox' : 'live';
		return \Voxel\get( "payments.square.{$mode}.location_id" );
	}

	/**
	 * Get webhook signature key
	 */
	public static function get_webhook_signature_key(): ?string {
		$mode = self::is_test_mode() ? 'sandbox' : 'live';
		return \Voxel\get( "payments.square.{$mode}.webhook_signature_key" );
	}

	/**
	 * Make API request to Square
	 */
	public static function make_request( string $endpoint, array $args = [] ): array {
		$token = self::get_access_token();

		if ( ! $token ) {
			return [
				'success' => false,
				'error' => 'Unable to authenticate with Square. Please check your access token.',
			];
		}

		$method = $args['method'] ?? 'GET';
		$body = $args['body'] ?? [];

		$request_args = [
			'method' => $method,
			'headers' => [
				'Content-Type' => 'application/json',
				'Authorization' => 'Bearer ' . $token,
				'Square-Version' => '2024-01-17',
			],
			'timeout' => 30,
		];

		if ( ! empty( $body ) ) {
			$request_args['body'] = json_encode( $body );
		}

		$url = self::get_api_base_url() . $endpoint;
		$response = wp_remote_request( $url, $request_args );

		if ( is_wp_error( $response ) ) {
			return [
				'success' => false,
				'error' => $response->get_error_message(),
			];
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$response_body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $status_code >= 200 && $status_code < 300 ) {
			return [
				'success' => true,
				'data' => $response_body,
			];
		}

		// Extract error message from Square response
		$error_message = 'Unknown error';
		if ( ! empty( $response_body['errors'] ) && is_array( $response_body['errors'] ) ) {
			$error_message = $response_body['errors'][0]['detail'] ?? $response_body['errors'][0]['code'] ?? 'Unknown error';
		}

		return [
			'success' => false,
			'error' => $error_message,
			'details' => $response_body,
		];
	}

	/**
	 * Generate a unique idempotency key
	 */
	public static function generate_idempotency_key(): string {
		return wp_generate_uuid4();
	}

	/**
	 * Create a checkout link (Payment Links API)
	 */
	public static function create_checkout_link( array $checkout_data ): array {
		return self::make_request( '/v2/online-checkout/payment-links', [
			'method' => 'POST',
			'body' => $checkout_data,
		] );
	}

	/**
	 * Get payment link details
	 */
	public static function get_payment_link( string $link_id ): array {
		return self::make_request( "/v2/online-checkout/payment-links/{$link_id}" );
	}

	/**
	 * Delete payment link
	 */
	public static function delete_payment_link( string $link_id ): array {
		return self::make_request( "/v2/online-checkout/payment-links/{$link_id}", [
			'method' => 'DELETE',
		] );
	}

	/**
	 * Get payment details
	 */
	public static function get_payment( string $payment_id ): array {
		return self::make_request( "/v2/payments/{$payment_id}" );
	}

	/**
	 * List payments
	 */
	public static function list_payments( array $params = [] ): array {
		$query = ! empty( $params ) ? '?' . http_build_query( $params ) : '';
		return self::make_request( "/v2/payments{$query}" );
	}

	/**
	 * Cancel a payment
	 */
	public static function cancel_payment( string $payment_id ): array {
		return self::make_request( "/v2/payments/{$payment_id}/cancel", [
			'method' => 'POST',
		] );
	}

	/**
	 * Refund a payment
	 */
	public static function refund_payment( array $refund_data ): array {
		return self::make_request( '/v2/refunds', [
			'method' => 'POST',
			'body' => $refund_data,
		] );
	}

	/**
	 * Get refund details
	 */
	public static function get_refund( string $refund_id ): array {
		return self::make_request( "/v2/refunds/{$refund_id}" );
	}

	/**
	 * Get order details
	 */
	public static function get_order( string $order_id ): array {
		return self::make_request( "/v2/orders/{$order_id}" );
	}

	/**
	 * Create an order
	 */
	public static function create_order( array $order_data ): array {
		return self::make_request( '/v2/orders', [
			'method' => 'POST',
			'body' => $order_data,
		] );
	}

	/**
	 * Create or retrieve a customer
	 */
	public static function create_customer( array $customer_data ): array {
		return self::make_request( '/v2/customers', [
			'method' => 'POST',
			'body' => $customer_data,
		] );
	}

	/**
	 * Get customer details
	 */
	public static function get_customer( string $customer_id ): array {
		return self::make_request( "/v2/customers/{$customer_id}" );
	}

	/**
	 * Search customers by email
	 */
	public static function search_customers( array $query ): array {
		return self::make_request( '/v2/customers/search', [
			'method' => 'POST',
			'body' => $query,
		] );
	}

	/**
	 * Create a catalog object (for subscription plans)
	 */
	public static function create_catalog_object( array $catalog_data ): array {
		return self::make_request( '/v2/catalog/object', [
			'method' => 'POST',
			'body' => $catalog_data,
		] );
	}

	/**
	 * Get catalog object
	 */
	public static function get_catalog_object( string $object_id ): array {
		return self::make_request( "/v2/catalog/object/{$object_id}" );
	}

	/**
	 * Search catalog objects
	 */
	public static function search_catalog_objects( array $query ): array {
		return self::make_request( '/v2/catalog/search', [
			'method' => 'POST',
			'body' => $query,
		] );
	}

	/**
	 * Create a subscription
	 */
	public static function create_subscription( array $subscription_data ): array {
		return self::make_request( '/v2/subscriptions', [
			'method' => 'POST',
			'body' => $subscription_data,
		] );
	}

	/**
	 * Get subscription details
	 */
	public static function get_subscription( string $subscription_id ): array {
		return self::make_request( "/v2/subscriptions/{$subscription_id}" );
	}

	/**
	 * Update subscription
	 */
	public static function update_subscription( string $subscription_id, array $update_data ): array {
		return self::make_request( "/v2/subscriptions/{$subscription_id}", [
			'method' => 'PUT',
			'body' => $update_data,
		] );
	}

	/**
	 * Cancel subscription
	 */
	public static function cancel_subscription( string $subscription_id ): array {
		return self::make_request( "/v2/subscriptions/{$subscription_id}/cancel", [
			'method' => 'POST',
		] );
	}

	/**
	 * Pause subscription
	 */
	public static function pause_subscription( string $subscription_id, array $pause_data = [] ): array {
		return self::make_request( "/v2/subscriptions/{$subscription_id}/pause", [
			'method' => 'POST',
			'body' => $pause_data,
		] );
	}

	/**
	 * Resume subscription
	 */
	public static function resume_subscription( string $subscription_id, array $resume_data = [] ): array {
		return self::make_request( "/v2/subscriptions/{$subscription_id}/resume", [
			'method' => 'POST',
			'body' => $resume_data,
		] );
	}

	/**
	 * List subscription events
	 */
	public static function list_subscription_events( string $subscription_id ): array {
		return self::make_request( "/v2/subscriptions/{$subscription_id}/events" );
	}

	/**
	 * Create a card on file for customer
	 */
	public static function create_card( array $card_data ): array {
		return self::make_request( '/v2/cards', [
			'method' => 'POST',
			'body' => $card_data,
		] );
	}

	/**
	 * Get card details
	 */
	public static function get_card( string $card_id ): array {
		return self::make_request( "/v2/cards/{$card_id}" );
	}

	/**
	 * List cards for a customer
	 */
	public static function list_cards( string $customer_id ): array {
		return self::make_request( "/v2/cards?customer_id={$customer_id}" );
	}

	/**
	 * Get location details
	 */
	public static function get_location( string $location_id ): array {
		return self::make_request( "/v2/locations/{$location_id}" );
	}

	/**
	 * List all locations
	 */
	public static function list_locations(): array {
		return self::make_request( '/v2/locations' );
	}

	/**
	 * Verify webhook signature
	 */
	public static function verify_webhook_signature( string $signature, string $webhook_url, string $body ): bool {
		$signature_key = self::get_webhook_signature_key();

		if ( empty( $signature_key ) ) {
			// If no signature key configured, skip verification (not recommended for production)
			return true;
		}

		// Square webhook signature verification
		// The signature is computed as: HMAC-SHA256(webhook_url + body, signature_key)
		$string_to_sign = $webhook_url . $body;
		$expected_signature = base64_encode( hash_hmac( 'sha256', $string_to_sign, $signature_key, true ) );

		return hash_equals( $expected_signature, $signature );
	}

	/**
	 * Check if credentials are configured
	 */
	public static function is_configured(): bool {
		return ! empty( self::get_access_token() ) && ! empty( self::get_location_id() );
	}

	/**
	 * Convert amount to Square format (cents)
	 */
	public static function to_square_amount( float $amount ): int {
		return (int) round( $amount * 100 );
	}

	/**
	 * Convert Square amount (cents) to decimal
	 */
	public static function from_square_amount( int $amount ): float {
		return $amount / 100;
	}
}
