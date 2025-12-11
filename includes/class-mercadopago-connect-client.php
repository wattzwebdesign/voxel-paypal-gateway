<?php

namespace VoxelPayPal;

if ( ! defined('ABSPATH') ) {
	exit;
}

/**
 * Mercado Pago Connect Client
 * Handles marketplace OAuth operations and vendor management
 */
class MercadoPago_Connect_Client {

	/**
	 * User meta keys for storing vendor tokens
	 */
	const META_ACCESS_TOKEN = 'mercadopago_access_token';
	const META_REFRESH_TOKEN = 'mercadopago_refresh_token';
	const META_TOKEN_EXPIRES = 'mercadopago_token_expires';
	const META_USER_ID = 'mercadopago_user_id';
	const META_PUBLIC_KEY = 'mercadopago_public_key';

	/**
	 * Get OAuth authorization URL for vendor onboarding
	 *
	 * @param int $vendor_id WordPress user ID
	 * @param string $redirect_uri Callback URL after authorization
	 * @return string|null Authorization URL or null if not configured
	 */
	public static function get_authorization_url( int $vendor_id, string $redirect_uri ): ?string {
		$app_id = MercadoPago_Client::get_application_id();

		if ( empty( $app_id ) ) {
			return null;
		}

		// Generate unique state for CSRF protection
		$state = wp_generate_password( 32, false );
		set_transient( 'mercadopago_oauth_state_' . $vendor_id, $state, HOUR_IN_SECONDS );

		$params = [
			'client_id' => $app_id,
			'response_type' => 'code',
			'platform_id' => 'mp', // Platform type
			'redirect_uri' => $redirect_uri,
			'state' => $state,
		];

		return MercadoPago_Client::get_auth_base_url() . '/authorization?' . http_build_query( $params );
	}

	/**
	 * Exchange authorization code for access token
	 *
	 * @param string $code Authorization code from OAuth callback
	 * @param string $redirect_uri Same redirect URI used in authorization
	 * @return array Response with tokens or error
	 */
	public static function exchange_code_for_token( string $code, string $redirect_uri ): array {
		$app_id = MercadoPago_Client::get_application_id();
		$client_secret = MercadoPago_Client::get_client_secret();

		if ( empty( $app_id ) || empty( $client_secret ) ) {
			return [
				'success' => false,
				'error' => 'OAuth credentials not configured',
			];
		}

		$response = wp_remote_post( MercadoPago_Client::get_api_base_url() . '/oauth/token', [
			'headers' => [
				'Content-Type' => 'application/x-www-form-urlencoded',
				'Accept' => 'application/json',
			],
			'body' => [
				'client_id' => $app_id,
				'client_secret' => $client_secret,
				'code' => $code,
				'redirect_uri' => $redirect_uri,
				'grant_type' => 'authorization_code',
			],
			'timeout' => 30,
		] );

		if ( is_wp_error( $response ) ) {
			return [
				'success' => false,
				'error' => $response->get_error_message(),
			];
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $status_code >= 200 && $status_code < 300 && ! empty( $body['access_token'] ) ) {
			return [
				'success' => true,
				'data' => $body,
			];
		}

		$error_message = $body['message'] ?? $body['error'] ?? 'Failed to exchange authorization code';

		return [
			'success' => false,
			'error' => $error_message,
			'details' => $body,
		];
	}

	/**
	 * Refresh an expired access token
	 *
	 * @param string $refresh_token Refresh token
	 * @return array Response with new tokens or error
	 */
	public static function refresh_access_token( string $refresh_token ): array {
		$app_id = MercadoPago_Client::get_application_id();
		$client_secret = MercadoPago_Client::get_client_secret();

		if ( empty( $app_id ) || empty( $client_secret ) ) {
			return [
				'success' => false,
				'error' => 'OAuth credentials not configured',
			];
		}

		$response = wp_remote_post( MercadoPago_Client::get_api_base_url() . '/oauth/token', [
			'headers' => [
				'Content-Type' => 'application/x-www-form-urlencoded',
				'Accept' => 'application/json',
			],
			'body' => [
				'client_id' => $app_id,
				'client_secret' => $client_secret,
				'refresh_token' => $refresh_token,
				'grant_type' => 'refresh_token',
			],
			'timeout' => 30,
		] );

		if ( is_wp_error( $response ) ) {
			return [
				'success' => false,
				'error' => $response->get_error_message(),
			];
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $status_code >= 200 && $status_code < 300 && ! empty( $body['access_token'] ) ) {
			return [
				'success' => true,
				'data' => $body,
			];
		}

		$error_message = $body['message'] ?? $body['error'] ?? 'Failed to refresh access token';

		return [
			'success' => false,
			'error' => $error_message,
			'details' => $body,
		];
	}

	/**
	 * Store vendor OAuth tokens
	 *
	 * @param int $vendor_id WordPress user ID
	 * @param array $token_data Token response data from OAuth
	 * @return bool Success status
	 */
	public static function store_vendor_tokens( int $vendor_id, array $token_data ): bool {
		if ( empty( $token_data['access_token'] ) ) {
			return false;
		}

		// Calculate expiration timestamp
		// Mercado Pago tokens are valid for 180 days (15552000 seconds)
		$expires_in = $token_data['expires_in'] ?? 15552000;
		$expires_at = time() + $expires_in;

		update_user_meta( $vendor_id, self::META_ACCESS_TOKEN, $token_data['access_token'] );
		update_user_meta( $vendor_id, self::META_TOKEN_EXPIRES, $expires_at );

		if ( ! empty( $token_data['refresh_token'] ) ) {
			update_user_meta( $vendor_id, self::META_REFRESH_TOKEN, $token_data['refresh_token'] );
		}

		if ( ! empty( $token_data['user_id'] ) ) {
			update_user_meta( $vendor_id, self::META_USER_ID, $token_data['user_id'] );
		}

		if ( ! empty( $token_data['public_key'] ) ) {
			update_user_meta( $vendor_id, self::META_PUBLIC_KEY, $token_data['public_key'] );
		}

		return true;
	}

	/**
	 * Get vendor access token (with automatic refresh if expired)
	 *
	 * @param int $vendor_id WordPress user ID
	 * @return string|null Access token or null if not connected
	 */
	public static function get_vendor_access_token( int $vendor_id ): ?string {
		$access_token = get_user_meta( $vendor_id, self::META_ACCESS_TOKEN, true );

		if ( empty( $access_token ) ) {
			return null;
		}

		// Check if token is expired or about to expire (1 hour margin)
		$expires_at = (int) get_user_meta( $vendor_id, self::META_TOKEN_EXPIRES, true );
		if ( $expires_at && $expires_at < ( time() + 3600 ) ) {
			// Try to refresh the token
			$refresh_token = get_user_meta( $vendor_id, self::META_REFRESH_TOKEN, true );
			if ( $refresh_token ) {
				$result = self::refresh_access_token( $refresh_token );
				if ( $result['success'] ) {
					self::store_vendor_tokens( $vendor_id, $result['data'] );
					return $result['data']['access_token'];
				}
			}
			// If refresh fails, return the potentially expired token
			// The API call will fail and handle the error
		}

		return $access_token;
	}

	/**
	 * Get vendor Mercado Pago user ID
	 */
	public static function get_vendor_mp_user_id( int $vendor_id ): ?string {
		$user_id = get_user_meta( $vendor_id, self::META_USER_ID, true );
		return ! empty( $user_id ) ? (string) $user_id : null;
	}

	/**
	 * Check if vendor is connected to Mercado Pago
	 *
	 * @param int $vendor_id WordPress user ID
	 * @return bool True if vendor has valid tokens
	 */
	public static function is_vendor_connected( int $vendor_id ): bool {
		$access_token = get_user_meta( $vendor_id, self::META_ACCESS_TOKEN, true );
		return ! empty( $access_token );
	}

	/**
	 * Disconnect vendor from Mercado Pago
	 *
	 * @param int $vendor_id WordPress user ID
	 * @return bool Success status
	 */
	public static function disconnect_vendor( int $vendor_id ): bool {
		delete_user_meta( $vendor_id, self::META_ACCESS_TOKEN );
		delete_user_meta( $vendor_id, self::META_REFRESH_TOKEN );
		delete_user_meta( $vendor_id, self::META_TOKEN_EXPIRES );
		delete_user_meta( $vendor_id, self::META_USER_ID );
		delete_user_meta( $vendor_id, self::META_PUBLIC_KEY );

		return true;
	}

	/**
	 * Verify OAuth state for CSRF protection
	 *
	 * @param int $vendor_id WordPress user ID
	 * @param string $state State value from callback
	 * @return bool True if state is valid
	 */
	public static function verify_oauth_state( int $vendor_id, string $state ): bool {
		$stored_state = get_transient( 'mercadopago_oauth_state_' . $vendor_id );
		if ( $stored_state && hash_equals( $stored_state, $state ) ) {
			delete_transient( 'mercadopago_oauth_state_' . $vendor_id );
			return true;
		}
		return false;
	}

	/**
	 * Check if marketplace mode is enabled
	 */
	public static function is_marketplace_enabled(): bool {
		return (bool) \Voxel\get( 'payments.mercadopago.marketplace.enabled', 0 );
	}

	/**
	 * Calculate vendor earnings from order
	 *
	 * @param float $order_total Total order amount
	 * @param int $vendor_id Vendor user ID
	 * @return array Array with platform_fee, vendor_earnings, fee_type
	 */
	public static function calculate_vendor_earnings( float $order_total, int $vendor_id = 0 ): array {
		if ( ! self::is_marketplace_enabled() ) {
			return [
				'platform_fee' => 0,
				'vendor_earnings' => $order_total,
				'fee_type' => 'none',
			];
		}

		$fee_type = \Voxel\get( 'payments.mercadopago.marketplace.fee_type', 'percentage' );
		$fee_value = floatval( \Voxel\get( 'payments.mercadopago.marketplace.fee_value', 0 ) );

		$platform_fee = 0;

		switch ( $fee_type ) {
			case 'fixed':
				$platform_fee = $fee_value;
				break;

			case 'percentage':
				$platform_fee = ( $order_total * $fee_value ) / 100;
				break;
		}

		// Ensure fees don't exceed order total
		$platform_fee = min( $platform_fee, $order_total );
		$vendor_earnings = $order_total - $platform_fee;

		return [
			'platform_fee' => round( $platform_fee, 2 ),
			'vendor_earnings' => round( $vendor_earnings, 2 ),
			'fee_type' => $fee_type,
		];
	}

	/**
	 * Get marketplace/application fee for a transaction
	 * This is the amount that goes to the platform
	 *
	 * @param float $order_total Total order amount
	 * @param int $vendor_id Vendor user ID
	 * @return float Platform fee amount
	 */
	public static function get_application_fee( float $order_total, int $vendor_id = 0 ): float {
		$earnings = self::calculate_vendor_earnings( $order_total, $vendor_id );
		return $earnings['platform_fee'];
	}

	/**
	 * Check if order is a marketplace order (vendor is different from platform)
	 *
	 * @param \Voxel\Product_Types\Orders\Order $order
	 * @return bool True if this is a marketplace order
	 */
	public static function is_marketplace_order( \Voxel\Product_Types\Orders\Order $order ): bool {
		error_log( '=== MP is_marketplace_order START ===' );

		try {
			// Check if marketplace mode is enabled first
			$enabled = self::is_marketplace_enabled();
			error_log( 'MP Connect: marketplace enabled = ' . ( $enabled ? 'YES' : 'NO' ) );

			if ( ! $enabled ) {
				return false;
			}

			// Get items to determine product type
			error_log( 'MP Connect: getting order items...' );
			$items = $order->get_items();
			error_log( 'MP Connect: got ' . count( $items ) . ' items' );

			if ( empty( $items ) ) {
				error_log( 'MP Connect: no items, returning false' );
				return false;
			}

			// Check the first item for post_id
			$first_item = reset( $items );
			$post_id = null;

			// Try get_post_id() method directly (available on Order_Item_Regular)
			if ( method_exists( $first_item, 'get_post_id' ) ) {
				$post_id = $first_item->get_post_id();
				error_log( 'MP Connect: got post_id from method: ' . $post_id );
			}

			// Fallback: Try get_details
			if ( ! $post_id ) {
				$post_id = $first_item->get_details( 'post_id' );
				error_log( 'MP Connect: got post_id from details: ' . $post_id );
			}

			// Get vendor ID - try item method first, then post author
			$vendor_id = null;
			if ( method_exists( $first_item, 'get_vendor_id' ) ) {
				$vendor_id = $first_item->get_vendor_id();
				error_log( 'MP Connect: got vendor_id from method: ' . $vendor_id );
			}

			// If no vendor_id from item and we have post_id, get from post author
			if ( ! $vendor_id && $post_id ) {
				$vendor_id = get_post_field( 'post_author', $post_id );
				error_log( 'MP Connect: got vendor_id from post author: ' . $vendor_id );
			}

			// Get customer ID
			$customer_id = $order->get_customer()->get_id();
			error_log( 'MP Connect: customer_id = ' . $customer_id );

			// Check if we have vendor and customer info
			if ( ! $vendor_id || ! $customer_id ) {
				error_log( 'MP Connect: missing vendor or customer, returning false' );
				return false;
			}

			// Check if vendor is connected
			$vendor_connected = self::is_vendor_connected( $vendor_id );
			error_log( 'MP Connect: vendor_connected = ' . ( $vendor_connected ? 'YES' : 'NO' ) );

			// It's a marketplace order if vendor is different from customer
			// AND vendor is connected to Mercado Pago
			$is_marketplace = $vendor_id != $customer_id && $vendor_connected;
			error_log( 'MP Connect: final result = ' . ( $is_marketplace ? 'YES' : 'NO' ) );
			error_log( '=== MP is_marketplace_order END ===' );

			return $is_marketplace;

		} catch ( \Exception $e ) {
			error_log( '=== MP is_marketplace_order EXCEPTION ===' );
			error_log( 'MP Connect Exception: ' . $e->getMessage() );
			error_log( 'MP Connect Trace: ' . $e->getTraceAsString() );
			return false;
		}
	}

	/**
	 * Get vendor ID from order
	 *
	 * @param \Voxel\Product_Types\Orders\Order $order
	 * @return int|null Vendor user ID or null
	 */
	public static function get_order_vendor_id( \Voxel\Product_Types\Orders\Order $order ): ?int {
		$items = $order->get_items();

		if ( empty( $items ) ) {
			return null;
		}

		$first_item = reset( $items );
		$post_id = null;

		if ( method_exists( $first_item, 'get_post_id' ) ) {
			$post_id = $first_item->get_post_id();
		}

		if ( ! $post_id ) {
			$post_id = $first_item->get_details( 'post_id' );
		}

		$vendor_id = null;
		if ( method_exists( $first_item, 'get_vendor_id' ) ) {
			$vendor_id = $first_item->get_vendor_id();
		}

		if ( ! $vendor_id && $post_id ) {
			$vendor_id = get_post_field( 'post_author', $post_id );
		}

		return $vendor_id ? (int) $vendor_id : null;
	}

	/**
	 * Get vendor Mercado Pago account info
	 *
	 * @param int $vendor_id WordPress user ID
	 * @return array|null Account info or null if not connected
	 */
	public static function get_vendor_account_info( int $vendor_id ): ?array {
		$access_token = self::get_vendor_access_token( $vendor_id );

		if ( ! $access_token ) {
			return null;
		}

		$result = MercadoPago_Client::get_user_info( $access_token );

		if ( $result['success'] && ! empty( $result['data'] ) ) {
			return $result['data'];
		}

		return null;
	}

	/**
	 * Get OAuth redirect URI for the site
	 *
	 * @return string Redirect URI
	 */
	public static function get_oauth_redirect_uri(): string {
		return add_query_arg( [
			'vx' => 1,
			'action' => 'mercadopago.oauth.callback',
		], home_url( '/' ) );
	}
}
