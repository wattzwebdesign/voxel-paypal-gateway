<?php

namespace VoxelPayPal;

if ( ! defined('ABSPATH') ) {
	exit;
}

/**
 * PayPal Connect Client
 * Handles marketplace payout operations and vendor management
 */
class PayPal_Connect_Client {

	/**
	 * Create payout to vendor(s)
	 *
	 * @param array $payout_items Array of payout items with structure:
	 *   [
	 *     'recipient_email' => 'vendor@example.com',
	 *     'amount' => 100.00,
	 *     'currency' => 'USD',
	 *     'note' => 'Payment for order #123',
	 *     'recipient_id' => '123', // vendor user ID or order ID
	 *   ]
	 * @param string $email_subject Subject line for payout notification email
	 * @param string $email_message Message for payout notification email
	 * @return array Response with success status and payout details
	 */
	public static function create_vendor_payout( array $payout_items, string $email_subject = '', string $email_message = '' ): array {

		if ( empty( $payout_items ) ) {
			return [
				'success' => false,
				'error' => 'No payout items provided',
			];
		}

		// Validate all payout items
		foreach ( $payout_items as $item ) {
			if ( empty( $item['recipient_email'] ) || empty( $item['amount'] ) || empty( $item['currency'] ) ) {
				return [
					'success' => false,
					'error' => 'Invalid payout item: missing required fields',
				];
			}

			// Validate email
			if ( ! is_email( $item['recipient_email'] ) ) {
				return [
					'success' => false,
					'error' => 'Invalid recipient email: ' . $item['recipient_email'],
				];
			}

			// Validate amount (must be positive and at least 1.00 USD or equivalent)
			if ( $item['amount'] < 1.00 ) {
				return [
					'success' => false,
					'error' => 'Payout amount must be at least 1.00',
				];
			}
		}

		// Build payout batch
		$sender_batch_id = 'voxel_payout_' . time() . '_' . wp_generate_password( 8, false );

		$items = [];
		foreach ( $payout_items as $item ) {
			$items[] = [
				'amount' => [
					'value' => number_format( $item['amount'], 2, '.', '' ),
					'currency' => $item['currency'],
				],
				'receiver' => $item['recipient_email'],
				'note' => $item['note'] ?? 'Payment from ' . get_bloginfo( 'name' ),
				'sender_item_id' => (string) ( $item['recipient_id'] ?? wp_generate_password( 12, false ) ),
				'recipient_wallet' => 'PAYPAL',
			];
		}

		$payout_data = [
			'sender_batch_header' => [
				'sender_batch_id' => $sender_batch_id,
				'recipient_type' => 'EMAIL',
				'email_subject' => $email_subject ?: 'You have a payment',
				'email_message' => $email_message ?: 'You have received a payment. Thank you.',
			],
			'items' => $items,
		];

		// Create payout via PayPal API
		$response = PayPal_Client::create_payout( $payout_data );

		if ( $response['success'] ) {
			// Log payout for tracking
			self::log_payout( $response['data'], $payout_items );
		} else {
			// Log errors for troubleshooting
			error_log( 'PayPal Payout Error: ' . ( $response['error'] ?? 'Unknown error' ) );
			if ( isset( $response['details'] ) ) {
				error_log( 'PayPal Payout Error details: ' . json_encode( $response['details'], JSON_PRETTY_PRINT ) );
			}
		}

		return $response;
	}

	/**
	 * Calculate vendor earnings from order
	 *
	 * @param float $order_total Total order amount
	 * @param int $vendor_id Vendor user ID
	 * @return array Array with platform_fee, vendor_earnings, fee_type
	 */
	public static function calculate_vendor_earnings( float $order_total, int $vendor_id = 0 ): array {
		$marketplace_enabled = (bool) \Voxel\get( 'payments.paypal.marketplace.enabled', 0 );

		if ( ! $marketplace_enabled ) {
			return [
				'platform_fee' => 0,
				'vendor_earnings' => $order_total,
				'fee_type' => 'none',
			];
		}

		$fee_type = \Voxel\get( 'payments.paypal.marketplace.fee_type', 'percentage' );
		$fee_value = floatval( \Voxel\get( 'payments.paypal.marketplace.fee_value', 0 ) );

		$platform_fee = 0;

		switch ( $fee_type ) {
			case 'fixed':
				$platform_fee = $fee_value;
				break;

			case 'percentage':
				$platform_fee = ( $order_total * $fee_value ) / 100;
				break;

			case 'conditional':
				// Conditional fees based on vendor tier or order amount
				$conditions = \Voxel\get( 'payments.paypal.marketplace.fee_conditions', [] );
				$platform_fee = self::calculate_conditional_fee( $order_total, $vendor_id, $conditions );
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
	 * Calculate conditional fee based on rules
	 */
	protected static function calculate_conditional_fee( float $order_total, int $vendor_id, array $conditions ): float {
		$fee = 0;

		foreach ( $conditions as $condition ) {
			$applies = true;

			// Check order amount threshold
			if ( isset( $condition['min_amount'] ) && $order_total < $condition['min_amount'] ) {
				$applies = false;
			}

			if ( isset( $condition['max_amount'] ) && $order_total > $condition['max_amount'] ) {
				$applies = false;
			}

			// Check vendor tier
			if ( isset( $condition['vendor_tier'] ) && $vendor_id > 0 ) {
				$vendor_tier = get_user_meta( $vendor_id, 'vendor_tier', true );
				if ( $vendor_tier !== $condition['vendor_tier'] ) {
					$applies = false;
				}
			}

			if ( $applies ) {
				if ( $condition['type'] === 'fixed' ) {
					$fee = floatval( $condition['value'] );
				} else {
					$fee = ( $order_total * floatval( $condition['value'] ) ) / 100;
				}
				break; // Use first matching condition
			}
		}

		return $fee;
	}

	/**
	 * Get vendor PayPal email
	 */
	public static function get_vendor_paypal_email( int $vendor_id ): ?string {
		$email = get_user_meta( $vendor_id, 'paypal_email', true );

		if ( empty( $email ) || ! is_email( $email ) ) {
			// Fallback to user's primary email
			$user = get_userdata( $vendor_id );
			return $user ? $user->user_email : null;
		}

		return $email;
	}

	/**
	 * Set vendor PayPal email
	 */
	public static function set_vendor_paypal_email( int $vendor_id, string $email ): bool {
		if ( ! is_email( $email ) ) {
			return false;
		}

		return update_user_meta( $vendor_id, 'paypal_email', sanitize_email( $email ) );
	}

	/**
	 * Check if order has multiple vendors (marketplace order)
	 */
	public static function is_marketplace_order( \Voxel\Product_Types\Orders\Order $order ): bool {
		// Check if marketplace mode is enabled first
		$marketplace_enabled = (bool) \Voxel\get( 'payments.paypal.marketplace.enabled', '0' );

		if ( ! $marketplace_enabled ) {
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

		// Try get_post_id() method directly (available on Order_Item_Regular)
		if ( method_exists( $first_item, 'get_post_id' ) ) {
			$post_id = $first_item->get_post_id();
		}

		// Fallback: Try get_details
		if ( ! $post_id ) {
			$post_id = $first_item->get_details( 'post_id' );
		}

		// Get vendor ID - try item method first, then post author
		$vendor_id = null;
		if ( method_exists( $first_item, 'get_vendor_id' ) ) {
			$vendor_id = $first_item->get_vendor_id();
		}

		// If no vendor_id from item and we have post_id, get from post author
		if ( ! $vendor_id && $post_id ) {
			$vendor_id = get_post_field( 'post_author', $post_id );
		}

		// Get customer ID
		$customer_id = $order->get_customer()->get_id();

		// Check if we have vendor and customer info
		if ( ! $vendor_id || ! $customer_id ) {
			return false;
		}

		// It's a marketplace order if vendor is different from customer
		return $vendor_id && $vendor_id != $customer_id;
	}

	/**
	 * Create sub-order for vendor tracking
	 */
	public static function create_vendor_sub_order( \Voxel\Product_Types\Orders\Order $parent_order, int $vendor_id, float $amount ): ?int {
		global $wpdb;

		$vendor = get_userdata( $vendor_id );
		if ( ! $vendor ) {
			return null;
		}

		// Create a sub-order post
		$sub_order_data = [
			'post_type' => 'voxel_vendor_order',
			'post_title' => sprintf( 'Vendor Order for #%d', $parent_order->get_id() ),
			'post_status' => 'pending',
			'post_author' => $vendor_id,
		];

		$sub_order_id = wp_insert_post( $sub_order_data );

		if ( is_wp_error( $sub_order_id ) ) {
			return null;
		}

		// Store metadata
		update_post_meta( $sub_order_id, 'parent_order_id', $parent_order->get_id() );
		update_post_meta( $sub_order_id, 'vendor_id', $vendor_id );
		update_post_meta( $sub_order_id, 'vendor_amount', $amount );
		update_post_meta( $sub_order_id, 'payout_status', 'pending' );
		update_post_meta( $sub_order_id, 'created_at', current_time( 'mysql' ) );

		return $sub_order_id;
	}

	/**
	 * Update sub-order payout status
	 */
	public static function update_sub_order_payout_status( int $sub_order_id, string $status, ?string $payout_item_id = null ): bool {
		update_post_meta( $sub_order_id, 'payout_status', $status );
		update_post_meta( $sub_order_id, 'payout_updated_at', current_time( 'mysql' ) );

		if ( $payout_item_id ) {
			update_post_meta( $sub_order_id, 'payout_item_id', $payout_item_id );
		}

		// Update post status
		$post_status = 'pending';
		switch ( $status ) {
			case 'success':
			case 'completed':
				$post_status = 'completed';
				break;
			case 'failed':
			case 'blocked':
			case 'refunded':
				$post_status = 'failed';
				break;
		}

		wp_update_post( [
			'ID' => $sub_order_id,
			'post_status' => $post_status,
		] );

		return true;
	}

	/**
	 * Log payout for debugging and tracking
	 */
	protected static function log_payout( array $payout_data, array $items ): void {
		$log_entry = [
			'timestamp' => current_time( 'mysql' ),
			'batch_id' => $payout_data['batch_header']['payout_batch_id'] ?? null,
			'batch_status' => $payout_data['batch_header']['batch_status'] ?? null,
			'items_count' => count( $items ),
			'items' => $items,
		];

		// Store in options table for easy retrieval
		$logs = get_option( 'voxel_paypal_payout_logs', [] );
		$logs[] = $log_entry;

		// Keep only last 100 logs
		if ( count( $logs ) > 100 ) {
			$logs = array_slice( $logs, -100 );
		}

		update_option( 'voxel_paypal_payout_logs', $logs );

		// Also log to error log if debug mode is enabled
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'PayPal Payout Created: Batch ID ' . ( $payout_data['batch_header']['payout_batch_id'] ?? 'unknown' ) );
		}
	}

	/**
	 * Get payout logs
	 */
	public static function get_payout_logs( int $limit = 20 ): array {
		$logs = get_option( 'voxel_paypal_payout_logs', [] );
		return array_slice( array_reverse( $logs ), 0, $limit );
	}

	/**
	 * Process order completion and create vendor payout
	 */
	public static function process_order_payout( \Voxel\Product_Types\Orders\Order $order ): array {
		// Check if marketplace mode is enabled
		if ( ! self::is_marketplace_order( $order ) ) {
			return [
				'success' => false,
				'error' => 'Not a marketplace order',
			];
		}

		// Get post ID from order items
		$items = $order->get_items();
		if ( empty( $items ) ) {
			return [
				'success' => false,
				'error' => 'No items in order',
			];
		}

		$first_item = reset( $items );

		// Use get_post_id() method (same as is_marketplace_order)
		$post_id = null;
		if ( method_exists( $first_item, 'get_post_id' ) ) {
			$post_id = $first_item->get_post_id();
		}

		// Fallback to get_details
		if ( ! $post_id ) {
			$post_id = $first_item->get_details( 'post_id' );
		}

		// Get vendor ID - try item method first
		$vendor_id = null;
		if ( method_exists( $first_item, 'get_vendor_id' ) ) {
			$vendor_id = $first_item->get_vendor_id();
		}

		// Fallback: get from post author if we have post_id
		if ( ! $vendor_id && $post_id ) {
			$vendor_id = get_post_field( 'post_author', $post_id );
		}

		if ( ! $vendor_id ) {
			error_log( 'PayPal Payout Error: No vendor found for order #' . $order->get_id() );
			return [
				'success' => false,
				'error' => 'Vendor not found',
			];
		}

		$order_total = $order->get_details( 'pricing.total' );

		// Calculate vendor earnings
		$earnings = self::calculate_vendor_earnings( $order_total, $vendor_id );

		if ( $earnings['vendor_earnings'] <= 0 ) {
			error_log( 'PayPal Payout Error: Vendor earnings are zero or negative for order #' . $order->get_id() );
			return [
				'success' => false,
				'error' => 'Vendor earnings are zero or negative',
			];
		}

		// Get vendor PayPal email
		$vendor_email = self::get_vendor_paypal_email( $vendor_id );

		if ( ! $vendor_email ) {
			error_log( 'PayPal Payout Error: Vendor PayPal email not found for vendor #' . $vendor_id . ' (order #' . $order->get_id() . ')' );
			return [
				'success' => false,
				'error' => 'Vendor PayPal email not found',
			];
		}

		// Create sub-order for tracking
		$sub_order_id = self::create_vendor_sub_order( $order, $vendor_id, $earnings['vendor_earnings'] );

		// Prepare payout
		$payout_items = [
			[
				'recipient_email' => $vendor_email,
				'amount' => $earnings['vendor_earnings'],
				'currency' => $order->get_currency(),
				'note' => sprintf( 'Payment for order #%d', $order->get_id() ),
				'recipient_id' => $sub_order_id ?? $order->get_id(),
			],
		];

		$email_subject = sprintf( 'Payment from %s', get_bloginfo( 'name' ) );
		$email_message = sprintf(
			'You have received a payment of %s %s for order #%d.',
			$order->get_currency(),
			$earnings['vendor_earnings'],
			$order->get_id()
		);

		// Create payout
		$result = self::create_vendor_payout( $payout_items, $email_subject, $email_message );

		if ( $result['success'] && $sub_order_id ) {
			// Extract payout item ID from response
			$payout_item_id = null;
			if ( ! empty( $result['data']['items'][0]['payout_item_id'] ) ) {
				$payout_item_id = $result['data']['items'][0]['payout_item_id'];
			}

			// Update sub-order with payout details
			self::update_sub_order_payout_status( $sub_order_id, 'processing', $payout_item_id );

			// Store payout details in parent order
			$order->set_details( 'marketplace.vendor_payout_id', $result['data']['batch_header']['payout_batch_id'] ?? null );
			$order->set_details( 'marketplace.vendor_earnings', $earnings['vendor_earnings'] );
			$order->set_details( 'marketplace.platform_fee', $earnings['platform_fee'] );
			$order->set_details( 'marketplace.sub_order_id', $sub_order_id );
			$order->save();
		}

		return $result;
	}
}
