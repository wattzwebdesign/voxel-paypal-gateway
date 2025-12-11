<?php

namespace VoxelPayPal;

if ( ! defined('ABSPATH') ) {
	exit;
}

/**
 * Mercado Pago API Client Handler
 */
class MercadoPago_Client {

	/**
	 * Get Mercado Pago API base URL
	 * Note: Same URL for both sandbox and production, credentials determine mode
	 */
	public static function get_api_base_url(): string {
		return 'https://api.mercadopago.com';
	}

	/**
	 * Get OAuth base URL
	 */
	public static function get_auth_base_url(): string {
		return 'https://auth.mercadopago.com';
	}

	/**
	 * Check if test mode is enabled
	 */
	public static function is_test_mode(): bool {
		$mode = \Voxel\get( 'payments.mercadopago.mode', 'sandbox' );
		return $mode === 'sandbox';
	}

	/**
	 * Get application ID
	 */
	public static function get_application_id(): ?string {
		$mode = self::is_test_mode() ? 'sandbox' : 'live';
		return \Voxel\get( "payments.mercadopago.{$mode}.application_id" );
	}

	/**
	 * Get client secret (for OAuth)
	 */
	public static function get_client_secret(): ?string {
		$mode = self::is_test_mode() ? 'sandbox' : 'live';
		return \Voxel\get( "payments.mercadopago.{$mode}.client_secret" );
	}

	/**
	 * Get access token
	 */
	public static function get_access_token(): ?string {
		$mode = self::is_test_mode() ? 'sandbox' : 'live';
		return \Voxel\get( "payments.mercadopago.{$mode}.access_token" );
	}

	/**
	 * Get public key
	 */
	public static function get_public_key(): ?string {
		$mode = self::is_test_mode() ? 'sandbox' : 'live';
		return \Voxel\get( "payments.mercadopago.{$mode}.public_key" );
	}

	/**
	 * Get webhook secret signature
	 */
	public static function get_webhook_secret(): ?string {
		$mode = self::is_test_mode() ? 'sandbox' : 'live';
		return \Voxel\get( "payments.mercadopago.{$mode}.webhook_secret" );
	}

	/**
	 * Make API request to Mercado Pago
	 *
	 * @param string $endpoint API endpoint
	 * @param array $args Request arguments
	 * @param string|null $access_token Optional access token (for marketplace requests)
	 */
	public static function make_request( string $endpoint, array $args = [], ?string $access_token = null ): array {
		$token = $access_token ?? self::get_access_token();

		if ( ! $token ) {
			return [
				'success' => false,
				'error' => 'Unable to authenticate with Mercado Pago. Please check your access token.',
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

		// Extract error message from Mercado Pago response
		$error_message = 'Unknown error';
		if ( ! empty( $response_body['message'] ) ) {
			$error_message = $response_body['message'];
		} elseif ( ! empty( $response_body['error'] ) ) {
			$error_message = $response_body['error'];
		} elseif ( ! empty( $response_body['cause'] ) && is_array( $response_body['cause'] ) ) {
			$error_message = $response_body['cause'][0]['description'] ?? $response_body['cause'][0]['code'] ?? 'Unknown error';
		}

		return [
			'success' => false,
			'error' => $error_message,
			'details' => $response_body,
			'status_code' => $status_code,
		];
	}

	/**
	 * Create a checkout preference (one-time payment)
	 *
	 * @param array $preference_data Preference data
	 * @param string|null $access_token Optional vendor access token for marketplace
	 */
	public static function create_preference( array $preference_data, ?string $access_token = null ): array {
		return self::make_request( '/checkout/preferences', [
			'method' => 'POST',
			'body' => $preference_data,
		], $access_token );
	}

	/**
	 * Get preference details
	 */
	public static function get_preference( string $preference_id ): array {
		return self::make_request( "/checkout/preferences/{$preference_id}" );
	}

	/**
	 * Get payment details
	 */
	public static function get_payment( string $payment_id ): array {
		return self::make_request( "/v1/payments/{$payment_id}" );
	}

	/**
	 * Search payments
	 */
	public static function search_payments( array $params = [] ): array {
		$query = ! empty( $params ) ? '?' . http_build_query( $params ) : '';
		return self::make_request( "/v1/payments/search{$query}" );
	}

	/**
	 * Refund a payment
	 *
	 * @param string $payment_id Payment ID
	 * @param float|null $amount Optional partial refund amount (null for full refund)
	 */
	public static function refund_payment( string $payment_id, ?float $amount = null ): array {
		$body = [];
		if ( $amount !== null ) {
			$body['amount'] = $amount;
		}

		return self::make_request( "/v1/payments/{$payment_id}/refunds", [
			'method' => 'POST',
			'body' => $body,
		] );
	}

	/**
	 * Get refund details
	 */
	public static function get_refund( string $payment_id, string $refund_id ): array {
		return self::make_request( "/v1/payments/{$payment_id}/refunds/{$refund_id}" );
	}

	/**
	 * Create a preapproval (subscription)
	 */
	public static function create_preapproval( array $preapproval_data ): array {
		return self::make_request( '/preapproval', [
			'method' => 'POST',
			'body' => $preapproval_data,
		] );
	}

	/**
	 * Get preapproval details
	 */
	public static function get_preapproval( string $preapproval_id ): array {
		return self::make_request( "/preapproval/{$preapproval_id}" );
	}

	/**
	 * Update preapproval
	 */
	public static function update_preapproval( string $preapproval_id, array $update_data ): array {
		return self::make_request( "/preapproval/{$preapproval_id}", [
			'method' => 'PUT',
			'body' => $update_data,
		] );
	}

	/**
	 * Cancel preapproval (subscription)
	 */
	public static function cancel_preapproval( string $preapproval_id ): array {
		return self::update_preapproval( $preapproval_id, [
			'status' => 'cancelled',
		] );
	}

	/**
	 * Pause preapproval (subscription)
	 */
	public static function pause_preapproval( string $preapproval_id ): array {
		return self::update_preapproval( $preapproval_id, [
			'status' => 'paused',
		] );
	}

	/**
	 * Search preapprovals
	 */
	public static function search_preapprovals( array $params = [] ): array {
		$query = ! empty( $params ) ? '?' . http_build_query( $params ) : '';
		return self::make_request( "/preapproval/search{$query}" );
	}

	/**
	 * Get authorized payments for a preapproval
	 */
	public static function get_preapproval_payments( string $preapproval_id ): array {
		return self::make_request( "/preapproval/{$preapproval_id}/authorized_payments" );
	}

	/**
	 * Verify webhook signature
	 *
	 * @param string $x_signature The x-signature header value
	 * @param string $x_request_id The x-request-id header value
	 * @param string $data_id The data.id from the webhook payload
	 * @return bool True if signature is valid
	 */
	public static function verify_webhook_signature( string $x_signature, string $x_request_id, string $data_id ): bool {
		$secret = self::get_webhook_secret();

		if ( empty( $secret ) ) {
			// If no secret configured, skip verification (not recommended for production)
			return true;
		}

		// Parse x-signature header: ts=1704908010,v1=abc123...
		$parts = explode( ',', $x_signature );
		$ts = '';
		$v1 = '';

		foreach ( $parts as $part ) {
			$key_value = explode( '=', $part, 2 );
			if ( count( $key_value ) === 2 ) {
				$key = trim( $key_value[0] );
				$value = trim( $key_value[1] );
				if ( $key === 'ts' ) {
					$ts = $value;
				} elseif ( $key === 'v1' ) {
					$v1 = $value;
				}
			}
		}

		if ( empty( $ts ) || empty( $v1 ) ) {
			return false;
		}

		// Build signature template: "id:{data_id};request-id:{x_request_id};ts:{ts};"
		$signature_template = "id:{$data_id};request-id:{$x_request_id};ts:{$ts};";

		// Generate HMAC-SHA256 signature
		$calculated_signature = hash_hmac( 'sha256', $signature_template, $secret );

		// Compare signatures using timing-safe comparison
		return hash_equals( $calculated_signature, $v1 );
	}

	/**
	 * Check if credentials are configured
	 */
	public static function is_configured(): bool {
		return ! empty( self::get_access_token() ) && ! empty( self::get_public_key() );
	}

	/**
	 * Check if marketplace is configured
	 */
	public static function is_marketplace_configured(): bool {
		return self::is_configured()
			&& ! empty( self::get_application_id() )
			&& ! empty( self::get_client_secret() );
	}

	/**
	 * Convert amount to Mercado Pago format
	 * Note: Mercado Pago uses decimal format, not cents
	 */
	public static function to_mercadopago_amount( float $amount ): float {
		return round( $amount, 2 );
	}

	/**
	 * Convert Mercado Pago amount to decimal
	 */
	public static function from_mercadopago_amount( float $amount ): float {
		return round( $amount, 2 );
	}

	/**
	 * Get user/merchant info
	 */
	public static function get_user_info( ?string $access_token = null ): array {
		return self::make_request( '/users/me', [], $access_token );
	}

	/**
	 * Get supported currencies for the current country
	 */
	public static function get_supported_currencies(): array {
		return [ 'ARS', 'BRL', 'CLP', 'COP', 'MXN', 'PEN', 'UYU' ];
	}
}
