<?php

namespace VoxelPayPal;

if ( ! defined('ABSPATH') ) {
	exit;
}

/**
 * Paystack Connect Client
 * Handles marketplace operations, subaccounts, and vendor management
 */
class Paystack_Connect_Client {

	/**
	 * User meta keys for storing vendor subaccount info
	 */
	const META_SUBACCOUNT_CODE = 'paystack_subaccount_code';
	const META_BANK_CODE = 'paystack_bank_code';
	const META_ACCOUNT_NUMBER = 'paystack_account_number';
	const META_ACCOUNT_NAME = 'paystack_account_name';
	const META_BUSINESS_NAME = 'paystack_business_name';
	const META_PERCENTAGE_CHARGE = 'paystack_percentage_charge';

	/**
	 * Create a subaccount for a vendor
	 *
	 * @param int $vendor_id WordPress user ID
	 * @param array $bank_details Bank details (bank_code, account_number, business_name)
	 * @return array Response with subaccount data or error
	 */
	public static function create_subaccount( int $vendor_id, array $bank_details ): array {
		$user = get_userdata( $vendor_id );
		if ( ! $user ) {
			return [
				'success' => false,
				'error' => 'Invalid vendor ID',
			];
		}

		// Resolve account to verify bank details
		$resolve_response = self::resolve_bank_account(
			$bank_details['account_number'],
			$bank_details['bank_code']
		);

		if ( ! $resolve_response['success'] ) {
			return $resolve_response;
		}

		$account_name = $resolve_response['data']['account_name'] ?? '';

		// Get platform fee percentage (default 0 = no automatic split)
		// We'll use transaction_charge instead for flexibility
		$percentage_charge = 0;

		$subaccount_data = [
			'business_name' => $bank_details['business_name'] ?? $user->display_name,
			'bank_code' => $bank_details['bank_code'],
			'account_number' => $bank_details['account_number'],
			'percentage_charge' => $percentage_charge,
			'primary_contact_email' => $user->user_email,
			'primary_contact_name' => $user->display_name,
			'metadata' => [
				'vendor_id' => $vendor_id,
				'wp_user_id' => $vendor_id,
			],
		];

		$response = Paystack_Client::make_request( '/subaccount', [
			'method' => 'POST',
			'body' => $subaccount_data,
		] );

		if ( $response['success'] && ! empty( $response['data']['subaccount_code'] ) ) {
			// Store subaccount info in user meta
			self::store_vendor_subaccount( $vendor_id, [
				'subaccount_code' => $response['data']['subaccount_code'],
				'bank_code' => $bank_details['bank_code'],
				'account_number' => $bank_details['account_number'],
				'account_name' => $account_name,
				'business_name' => $bank_details['business_name'] ?? $user->display_name,
			] );
		}

		return $response;
	}

	/**
	 * Get subaccount details
	 *
	 * @param string $subaccount_code Subaccount code
	 * @return array Response with subaccount data or error
	 */
	public static function get_subaccount( string $subaccount_code ): array {
		return Paystack_Client::make_request( "/subaccount/{$subaccount_code}" );
	}

	/**
	 * Update a subaccount
	 *
	 * @param string $subaccount_code Subaccount code
	 * @param array $update_data Data to update
	 * @return array Response with subaccount data or error
	 */
	public static function update_subaccount( string $subaccount_code, array $update_data ): array {
		return Paystack_Client::make_request( "/subaccount/{$subaccount_code}", [
			'method' => 'PUT',
			'body' => $update_data,
		] );
	}

	/**
	 * List all banks for a country
	 *
	 * @param string $country Country code (e.g., 'nigeria', 'ghana', 'south_africa', 'kenya')
	 * @return array Response with banks list or error
	 */
	public static function list_banks( string $country = 'nigeria' ): array {
		$params = [ 'country' => $country ];
		return Paystack_Client::make_request( '/bank?' . http_build_query( $params ) );
	}

	/**
	 * Resolve bank account number to get account name
	 *
	 * @param string $account_number Bank account number
	 * @param string $bank_code Bank code
	 * @return array Response with account details or error
	 */
	public static function resolve_bank_account( string $account_number, string $bank_code ): array {
		$params = [
			'account_number' => $account_number,
			'bank_code' => $bank_code,
		];

		return Paystack_Client::make_request( '/bank/resolve?' . http_build_query( $params ) );
	}

	/**
	 * Store vendor subaccount info
	 *
	 * @param int $vendor_id WordPress user ID
	 * @param array $subaccount_data Subaccount data
	 * @return bool Success status
	 */
	public static function store_vendor_subaccount( int $vendor_id, array $subaccount_data ): bool {
		if ( empty( $subaccount_data['subaccount_code'] ) ) {
			return false;
		}

		update_user_meta( $vendor_id, self::META_SUBACCOUNT_CODE, $subaccount_data['subaccount_code'] );

		if ( ! empty( $subaccount_data['bank_code'] ) ) {
			update_user_meta( $vendor_id, self::META_BANK_CODE, $subaccount_data['bank_code'] );
		}

		if ( ! empty( $subaccount_data['account_number'] ) ) {
			update_user_meta( $vendor_id, self::META_ACCOUNT_NUMBER, $subaccount_data['account_number'] );
		}

		if ( ! empty( $subaccount_data['account_name'] ) ) {
			update_user_meta( $vendor_id, self::META_ACCOUNT_NAME, $subaccount_data['account_name'] );
		}

		if ( ! empty( $subaccount_data['business_name'] ) ) {
			update_user_meta( $vendor_id, self::META_BUSINESS_NAME, $subaccount_data['business_name'] );
		}

		return true;
	}

	/**
	 * Get vendor subaccount code
	 *
	 * @param int $vendor_id WordPress user ID
	 * @return string|null Subaccount code or null
	 */
	public static function get_vendor_subaccount_code( int $vendor_id ): ?string {
		$code = get_user_meta( $vendor_id, self::META_SUBACCOUNT_CODE, true );
		return ! empty( $code ) ? $code : null;
	}

	/**
	 * Get vendor bank info
	 *
	 * @param int $vendor_id WordPress user ID
	 * @return array Bank info array
	 */
	public static function get_vendor_bank_info( int $vendor_id ): array {
		return [
			'subaccount_code' => get_user_meta( $vendor_id, self::META_SUBACCOUNT_CODE, true ) ?: null,
			'bank_code' => get_user_meta( $vendor_id, self::META_BANK_CODE, true ) ?: null,
			'account_number' => get_user_meta( $vendor_id, self::META_ACCOUNT_NUMBER, true ) ?: null,
			'account_name' => get_user_meta( $vendor_id, self::META_ACCOUNT_NAME, true ) ?: null,
			'business_name' => get_user_meta( $vendor_id, self::META_BUSINESS_NAME, true ) ?: null,
		];
	}

	/**
	 * Check if vendor is connected (has subaccount)
	 *
	 * @param int $vendor_id WordPress user ID
	 * @return bool True if vendor has a subaccount
	 */
	public static function is_vendor_connected( int $vendor_id ): bool {
		$subaccount_code = get_user_meta( $vendor_id, self::META_SUBACCOUNT_CODE, true );
		return ! empty( $subaccount_code );
	}

	/**
	 * Disconnect vendor (remove subaccount link)
	 *
	 * @param int $vendor_id WordPress user ID
	 * @return bool Success status
	 */
	public static function disconnect_vendor( int $vendor_id ): bool {
		delete_user_meta( $vendor_id, self::META_SUBACCOUNT_CODE );
		delete_user_meta( $vendor_id, self::META_BANK_CODE );
		delete_user_meta( $vendor_id, self::META_ACCOUNT_NUMBER );
		delete_user_meta( $vendor_id, self::META_ACCOUNT_NAME );
		delete_user_meta( $vendor_id, self::META_BUSINESS_NAME );
		delete_user_meta( $vendor_id, self::META_PERCENTAGE_CHARGE );

		return true;
	}

	/**
	 * Check if marketplace mode is enabled
	 */
	public static function is_marketplace_enabled(): bool {
		return (bool) \Voxel\get( 'payments.paystack.marketplace.enabled', 0 );
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

		$fee_type = \Voxel\get( 'payments.paystack.marketplace.fee_type', 'percentage' );
		$fee_value = floatval( \Voxel\get( 'payments.paystack.marketplace.fee_value', 0 ) );

		$platform_fee = 0;

		switch ( $fee_type ) {
			case 'fixed':
				// Fixed fee in the main currency unit
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
		try {
			// Check if marketplace mode is enabled first
			if ( ! self::is_marketplace_enabled() ) {
				return false;
			}

			// Get items to determine product type
			$items = $order->get_items();

			if ( empty( $items ) ) {
				return false;
			}

			// Check the first item for post_id
			$first_item = reset( $items );
			$post_id = null;

			// Try get_post_id() method directly
			if ( method_exists( $first_item, 'get_post_id' ) ) {
				$post_id = $first_item->get_post_id();
			}

			// Fallback: Try get_details
			if ( ! $post_id ) {
				$post_id = $first_item->get_details( 'post_id' );
			}

			// Get vendor ID
			$vendor_id = null;
			if ( method_exists( $first_item, 'get_vendor_id' ) ) {
				$vendor_id = $first_item->get_vendor_id();
			}

			// If no vendor_id from item and we have post_id, get from post author
			if ( ! $vendor_id && $post_id ) {
				$vendor_id = get_post_field( 'post_author', $post_id );
			}

			// Get customer ID
			$customer = $order->get_customer();
			$customer_id = $customer ? $customer->get_id() : null;

			// Check if we have vendor and customer info
			if ( ! $vendor_id || ! $customer_id ) {
				return false;
			}

			// Check if vendor is connected (has subaccount)
			$vendor_connected = self::is_vendor_connected( $vendor_id );

			// It's a marketplace order if vendor is different from customer
			// AND vendor is connected to Paystack
			return $vendor_id != $customer_id && $vendor_connected;

		} catch ( \Exception $e ) {
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
	 * Get supported countries for Paystack
	 *
	 * @return array Countries with their bank endpoint codes
	 */
	public static function get_supported_countries(): array {
		return [
			'NG' => [
				'name' => 'Nigeria',
				'code' => 'nigeria',
				'currency' => 'NGN',
			],
			'GH' => [
				'name' => 'Ghana',
				'code' => 'ghana',
				'currency' => 'GHS',
			],
			'ZA' => [
				'name' => 'South Africa',
				'code' => 'south-africa',
				'currency' => 'ZAR',
			],
			'KE' => [
				'name' => 'Kenya',
				'code' => 'kenya',
				'currency' => 'KES',
			],
		];
	}

	/**
	 * Get fee bearer options
	 *
	 * @return array Fee bearer options
	 */
	public static function get_fee_bearer_options(): array {
		return [
			'account' => 'Platform bears all Paystack fees',
			'subaccount' => 'Vendor bears all Paystack fees',
			'all' => 'Fees split equally',
			'all_proportional' => 'Fees split proportionally',
		];
	}
}
