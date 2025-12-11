<?php

namespace VoxelPayPal;

if ( ! defined('ABSPATH') ) {
	exit;
}

/**
 * Paystack API Client Handler
 */
class Paystack_Client {

	/**
	 * Get Paystack API base URL
	 */
	public static function get_api_base_url(): string {
		return 'https://api.paystack.co';
	}

	/**
	 * Check if test mode is enabled
	 */
	public static function is_test_mode(): bool {
		$mode = \Voxel\get( 'payments.paystack.mode', 'sandbox' );
		return $mode === 'sandbox';
	}

	/**
	 * Get secret key
	 */
	public static function get_secret_key(): ?string {
		$mode = self::is_test_mode() ? 'sandbox' : 'live';
		return \Voxel\get( "payments.paystack.{$mode}.secret_key" );
	}

	/**
	 * Get public key
	 */
	public static function get_public_key(): ?string {
		$mode = self::is_test_mode() ? 'sandbox' : 'live';
		return \Voxel\get( "payments.paystack.{$mode}.public_key" );
	}

	/**
	 * Get webhook secret
	 */
	public static function get_webhook_secret(): ?string {
		$mode = self::is_test_mode() ? 'sandbox' : 'live';
		return \Voxel\get( "payments.paystack.{$mode}.webhook_secret" );
	}

	/**
	 * Make API request to Paystack
	 *
	 * @param string $endpoint API endpoint
	 * @param array $args Request arguments
	 */
	public static function make_request( string $endpoint, array $args = [] ): array {
		$secret_key = self::get_secret_key();

		if ( ! $secret_key ) {
			return [
				'success' => false,
				'error' => 'Unable to authenticate with Paystack. Please check your secret key.',
			];
		}

		$method = $args['method'] ?? 'GET';
		$body = $args['body'] ?? [];

		$request_args = [
			'method' => $method,
			'headers' => [
				'Content-Type' => 'application/json',
				'Authorization' => 'Bearer ' . $secret_key,
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

		if ( $status_code >= 200 && $status_code < 300 && ! empty( $response_body['status'] ) ) {
			return [
				'success' => true,
				'data' => $response_body['data'] ?? $response_body,
				'message' => $response_body['message'] ?? '',
			];
		}

		// Extract error message from Paystack response
		$error_message = $response_body['message'] ?? 'Unknown error';

		return [
			'success' => false,
			'error' => $error_message,
			'details' => $response_body,
			'status_code' => $status_code,
		];
	}

	/**
	 * Initialize a transaction
	 *
	 * @param array $transaction_data Transaction data
	 */
	public static function initialize_transaction( array $transaction_data ): array {
		return self::make_request( '/transaction/initialize', [
			'method' => 'POST',
			'body' => $transaction_data,
		] );
	}

	/**
	 * Verify a transaction
	 *
	 * @param string $reference Transaction reference
	 */
	public static function verify_transaction( string $reference ): array {
		return self::make_request( "/transaction/verify/{$reference}" );
	}

	/**
	 * Get transaction details
	 *
	 * @param string $transaction_id Transaction ID
	 */
	public static function get_transaction( string $transaction_id ): array {
		return self::make_request( "/transaction/{$transaction_id}" );
	}

	/**
	 * List transactions
	 *
	 * @param array $params Query parameters
	 */
	public static function list_transactions( array $params = [] ): array {
		$query = ! empty( $params ) ? '?' . http_build_query( $params ) : '';
		return self::make_request( "/transaction{$query}" );
	}

	/**
	 * Charge authorization (for recurring charges)
	 *
	 * @param array $charge_data Charge data including authorization_code, email, amount
	 */
	public static function charge_authorization( array $charge_data ): array {
		return self::make_request( '/transaction/charge_authorization', [
			'method' => 'POST',
			'body' => $charge_data,
		] );
	}

	/**
	 * Create a refund
	 *
	 * @param string $transaction_reference Transaction reference
	 * @param array $refund_data Optional refund data (amount for partial refund)
	 */
	public static function create_refund( string $transaction_reference, array $refund_data = [] ): array {
		$body = array_merge( [ 'transaction' => $transaction_reference ], $refund_data );
		return self::make_request( '/refund', [
			'method' => 'POST',
			'body' => $body,
		] );
	}

	/**
	 * Get refund details
	 *
	 * @param string $refund_id Refund ID
	 */
	public static function get_refund( string $refund_id ): array {
		return self::make_request( "/refund/{$refund_id}" );
	}

	/**
	 * Create a plan (for subscriptions)
	 *
	 * @param array $plan_data Plan data
	 */
	public static function create_plan( array $plan_data ): array {
		return self::make_request( '/plan', [
			'method' => 'POST',
			'body' => $plan_data,
		] );
	}

	/**
	 * Get plan details
	 *
	 * @param string $plan_id_or_code Plan ID or code
	 */
	public static function get_plan( string $plan_id_or_code ): array {
		return self::make_request( "/plan/{$plan_id_or_code}" );
	}

	/**
	 * List plans
	 *
	 * @param array $params Query parameters
	 */
	public static function list_plans( array $params = [] ): array {
		$query = ! empty( $params ) ? '?' . http_build_query( $params ) : '';
		return self::make_request( "/plan{$query}" );
	}

	/**
	 * Update a plan
	 *
	 * @param string $plan_id_or_code Plan ID or code
	 * @param array $plan_data Plan data to update
	 */
	public static function update_plan( string $plan_id_or_code, array $plan_data ): array {
		return self::make_request( "/plan/{$plan_id_or_code}", [
			'method' => 'PUT',
			'body' => $plan_data,
		] );
	}

	/**
	 * Create a subscription
	 *
	 * @param array $subscription_data Subscription data
	 */
	public static function create_subscription( array $subscription_data ): array {
		return self::make_request( '/subscription', [
			'method' => 'POST',
			'body' => $subscription_data,
		] );
	}

	/**
	 * Get subscription details
	 *
	 * @param string $subscription_id_or_code Subscription ID or code
	 */
	public static function get_subscription( string $subscription_id_or_code ): array {
		return self::make_request( "/subscription/{$subscription_id_or_code}" );
	}

	/**
	 * List subscriptions
	 *
	 * @param array $params Query parameters
	 */
	public static function list_subscriptions( array $params = [] ): array {
		$query = ! empty( $params ) ? '?' . http_build_query( $params ) : '';
		return self::make_request( "/subscription{$query}" );
	}

	/**
	 * Enable a subscription
	 *
	 * @param string $subscription_code Subscription code
	 * @param string $email_token Email token from subscription
	 */
	public static function enable_subscription( string $subscription_code, string $email_token ): array {
		return self::make_request( '/subscription/enable', [
			'method' => 'POST',
			'body' => [
				'code' => $subscription_code,
				'token' => $email_token,
			],
		] );
	}

	/**
	 * Disable a subscription
	 *
	 * @param string $subscription_code Subscription code
	 * @param string $email_token Email token from subscription
	 */
	public static function disable_subscription( string $subscription_code, string $email_token ): array {
		return self::make_request( '/subscription/disable', [
			'method' => 'POST',
			'body' => [
				'code' => $subscription_code,
				'token' => $email_token,
			],
		] );
	}

	/**
	 * Generate manage subscription link
	 *
	 * @param string $subscription_code Subscription code
	 */
	public static function get_subscription_manage_link( string $subscription_code ): array {
		return self::make_request( "/subscription/{$subscription_code}/manage/link" );
	}

	/**
	 * Create a customer
	 *
	 * @param array $customer_data Customer data
	 */
	public static function create_customer( array $customer_data ): array {
		return self::make_request( '/customer', [
			'method' => 'POST',
			'body' => $customer_data,
		] );
	}

	/**
	 * Get customer details
	 *
	 * @param string $email_or_code Customer email or code
	 */
	public static function get_customer( string $email_or_code ): array {
		return self::make_request( "/customer/{$email_or_code}" );
	}

	/**
	 * Update customer
	 *
	 * @param string $customer_code Customer code
	 * @param array $customer_data Customer data to update
	 */
	public static function update_customer( string $customer_code, array $customer_data ): array {
		return self::make_request( "/customer/{$customer_code}", [
			'method' => 'PUT',
			'body' => $customer_data,
		] );
	}

	/**
	 * Verify webhook signature
	 *
	 * @param string $payload Raw webhook payload
	 * @param string $signature Signature from x-paystack-signature header
	 * @return bool True if signature is valid
	 */
	public static function verify_webhook_signature( string $payload, string $signature ): bool {
		$secret = self::get_webhook_secret();

		if ( empty( $secret ) ) {
			// If no secret configured, skip verification (not recommended for production)
			return true;
		}

		// Paystack uses HMAC-SHA512
		$calculated_signature = hash_hmac( 'sha512', $payload, $secret );

		// Compare signatures using timing-safe comparison
		return hash_equals( $calculated_signature, $signature );
	}

	/**
	 * Check if credentials are configured
	 */
	public static function is_configured(): bool {
		return ! empty( self::get_secret_key() ) && ! empty( self::get_public_key() );
	}

	/**
	 * Convert amount to Paystack format (kobo/pesewas - smallest currency unit)
	 * Paystack expects amounts in the smallest currency unit (e.g., kobo for NGN, pesewas for GHS)
	 *
	 * @param float $amount Amount in main currency unit
	 * @return int Amount in smallest currency unit
	 */
	public static function to_paystack_amount( float $amount ): int {
		return (int) round( $amount * 100 );
	}

	/**
	 * Convert Paystack amount to main currency unit
	 *
	 * @param int $amount Amount in smallest currency unit
	 * @return float Amount in main currency unit
	 */
	public static function from_paystack_amount( int $amount ): float {
		return round( $amount / 100, 2 );
	}

	/**
	 * Generate a unique reference
	 *
	 * @param string $prefix Optional prefix
	 * @return string Unique reference
	 */
	public static function generate_reference( string $prefix = 'vxl' ): string {
		return $prefix . '_' . bin2hex( random_bytes( 12 ) );
	}

	/**
	 * Get supported currencies
	 */
	public static function get_supported_currencies(): array {
		return [ 'NGN', 'GHS', 'ZAR', 'USD', 'KES' ];
	}

	/**
	 * Map Paystack interval to standard interval name
	 *
	 * @param string $interval Paystack interval (hourly, daily, weekly, monthly, quarterly, biannually, annually)
	 * @return string|null Standard interval or null if invalid
	 */
	public static function normalize_interval( string $interval ): ?string {
		$map = [
			'hourly' => 'hour',
			'daily' => 'day',
			'weekly' => 'week',
			'monthly' => 'month',
			'quarterly' => 'quarter',
			'biannually' => 'biannual',
			'annually' => 'year',
		];

		return $map[ strtolower( $interval ) ] ?? null;
	}

	/**
	 * Convert standard interval to Paystack interval
	 *
	 * @param string $interval Standard interval
	 * @return string|null Paystack interval or null if invalid
	 */
	public static function to_paystack_interval( string $interval ): ?string {
		$map = [
			'hour' => 'hourly',
			'day' => 'daily',
			'week' => 'weekly',
			'month' => 'monthly',
			'quarter' => 'quarterly',
			'biannual' => 'biannually',
			'year' => 'annually',
		];

		return $map[ strtolower( $interval ) ] ?? null;
	}
}
