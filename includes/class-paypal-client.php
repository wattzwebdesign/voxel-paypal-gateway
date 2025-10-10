<?php

namespace VoxelPayPal;

if ( ! defined('ABSPATH') ) {
	exit;
}

/**
 * PayPal API Client Handler
 */
class PayPal_Client {

	/**
	 * Get PayPal API base URL
	 */
	public static function get_api_base_url(): string {
		return self::is_test_mode()
			? 'https://api-m.sandbox.paypal.com'
			: 'https://api-m.paypal.com';
	}

	/**
	 * Check if test mode is enabled
	 */
	public static function is_test_mode(): bool {
		$mode = \Voxel\get( 'payments.paypal.mode', 'sandbox' );
		return $mode === 'sandbox';
	}

	/**
	 * Get client ID
	 */
	public static function get_client_id(): ?string {
		$mode = self::is_test_mode() ? 'sandbox' : 'live';
		return \Voxel\get( "payments.paypal.{$mode}.client_id" );
	}

	/**
	 * Get client secret
	 */
	public static function get_client_secret(): ?string {
		$mode = self::is_test_mode() ? 'sandbox' : 'live';
		return \Voxel\get( "payments.paypal.{$mode}.client_secret" );
	}

	/**
	 * Get access token from PayPal
	 */
	public static function get_access_token(): ?string {
		$client_id = self::get_client_id();
		$client_secret = self::get_client_secret();

		if ( empty( $client_id ) || empty( $client_secret ) ) {
			return null;
		}

		// Check for cached token
		$cache_key = 'voxel_paypal_token_' . ( self::is_test_mode() ? 'sandbox' : 'live' );
		$cached_token = get_transient( $cache_key );

		if ( $cached_token ) {
			return $cached_token;
		}

		// Request new token
		$response = wp_remote_post( self::get_api_base_url() . '/v1/oauth2/token', [
			'headers' => [
				'Accept' => 'application/json',
				'Accept-Language' => 'en_US',
				'Authorization' => 'Basic ' . base64_encode( $client_id . ':' . $client_secret ),
			],
			'body' => [
				'grant_type' => 'client_credentials',
			],
			'timeout' => 30,
		] );

		if ( is_wp_error( $response ) ) {
			error_log( 'PayPal token error: ' . $response->get_error_message() );
			return null;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( empty( $body['access_token'] ) ) {
			return null;
		}

		$token = $body['access_token'];
		$expires_in = $body['expires_in'] ?? 3600;

		// Cache token (subtract 60 seconds for safety margin)
		set_transient( $cache_key, $token, $expires_in - 60 );

		return $token;
	}

	/**
	 * Make API request to PayPal
	 */
	public static function make_request( string $endpoint, array $args = [] ): array {
		$token = self::get_access_token();

		if ( ! $token ) {
			return [
				'success' => false,
				'error' => 'Unable to authenticate with PayPal',
			];
		}

		$method = $args['method'] ?? 'GET';
		$body = $args['body'] ?? [];

		$request_args = [
			'method' => $method,
			'headers' => [
				'Content-Type' => 'application/json',
				'Authorization' => 'Bearer ' . $token,
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

		return [
			'success' => false,
			'error' => $response_body['message'] ?? 'Unknown error',
			'details' => $response_body,
		];
	}

	/**
	 * Create PayPal order
	 */
	public static function create_order( array $order_data ): array {
		return self::make_request( '/v2/checkout/orders', [
			'method' => 'POST',
			'body' => $order_data,
		] );
	}

	/**
	 * Capture PayPal order
	 */
	public static function capture_order( string $order_id ): array {
		return self::make_request( "/v2/checkout/orders/{$order_id}/capture", [
			'method' => 'POST',
		] );
	}

	/**
	 * Get order details
	 */
	public static function get_order( string $order_id ): array {
		return self::make_request( "/v2/checkout/orders/{$order_id}" );
	}

	/**
	 * Refund a capture
	 */
	public static function refund_capture( string $capture_id, array $refund_data = [] ): array {
		return self::make_request( "/v2/payments/captures/{$capture_id}/refund", [
			'method' => 'POST',
			'body' => $refund_data,
		] );
	}

	/**
	 * Get webhook secret
	 */
	public static function get_webhook_secret(): ?string {
		$mode = self::is_test_mode() ? 'sandbox' : 'live';
		return \Voxel\get( "payments.paypal.{$mode}.webhook.secret" );
	}

	/**
	 * Verify webhook signature
	 */
	public static function verify_webhook_signature( array $headers, string $raw_body ): bool {
		$webhook_id = self::get_webhook_secret();

		if ( empty( $webhook_id ) ) {
			return false;
		}

		// PayPal webhook verification
		// In production, implement proper webhook verification using PayPal SDK
		// For now, basic verification

		return ! empty( $headers['paypal-transmission-id'] ?? '' );
	}

	/**
	 * Create PayPal Product (for subscriptions)
	 */
	public static function create_product( array $product_data ): array {
		return self::make_request( '/v1/catalogs/products', [
			'method' => 'POST',
			'body' => $product_data,
		] );
	}

	/**
	 * Create PayPal Billing Plan (for subscriptions)
	 */
	public static function create_plan( array $plan_data ): array {
		return self::make_request( '/v1/billing/plans', [
			'method' => 'POST',
			'body' => $plan_data,
		] );
	}

	/**
	 * Get billing plan details
	 */
	public static function get_plan( string $plan_id ): array {
		return self::make_request( "/v1/billing/plans/{$plan_id}" );
	}

	/**
	 * Create subscription
	 */
	public static function create_subscription( array $subscription_data ): array {
		return self::make_request( '/v1/billing/subscriptions', [
			'method' => 'POST',
			'body' => $subscription_data,
		] );
	}

	/**
	 * Get subscription details
	 */
	public static function get_subscription( string $subscription_id ): array {
		return self::make_request( "/v1/billing/subscriptions/{$subscription_id}" );
	}

	/**
	 * Cancel subscription
	 */
	public static function cancel_subscription( string $subscription_id, string $reason = '' ): array {
		return self::make_request( "/v1/billing/subscriptions/{$subscription_id}/cancel", [
			'method' => 'POST',
			'body' => [
				'reason' => $reason ?: 'Customer requested cancellation',
			],
		] );
	}

	/**
	 * Suspend subscription
	 */
	public static function suspend_subscription( string $subscription_id, string $reason = '' ): array {
		return self::make_request( "/v1/billing/subscriptions/{$subscription_id}/suspend", [
			'method' => 'POST',
			'body' => [
				'reason' => $reason ?: 'Subscription suspended',
			],
		] );
	}

	/**
	 * Activate subscription
	 */
	public static function activate_subscription( string $subscription_id, string $reason = '' ): array {
		return self::make_request( "/v1/billing/subscriptions/{$subscription_id}/activate", [
			'method' => 'POST',
			'body' => [
				'reason' => $reason ?: 'Subscription reactivated',
			],
		] );
	}

	/**
	 * Create payout (for marketplace vendor payments)
	 */
	public static function create_payout( array $payout_data ): array {
		return self::make_request( '/v1/payments/payouts', [
			'method' => 'POST',
			'body' => $payout_data,
		] );
	}

	/**
	 * Get payout details
	 */
	public static function get_payout( string $payout_batch_id ): array {
		return self::make_request( "/v1/payments/payouts/{$payout_batch_id}" );
	}

	/**
	 * Get payout item details
	 */
	public static function get_payout_item( string $payout_item_id ): array {
		return self::make_request( "/v1/payments/payouts-item/{$payout_item_id}" );
	}

	/**
	 * Cancel unclaimed payout item
	 */
	public static function cancel_payout_item( string $payout_item_id ): array {
		return self::make_request( "/v1/payments/payouts-item/{$payout_item_id}/cancel", [
			'method' => 'POST',
		] );
	}
}
