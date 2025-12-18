<?php

namespace VoxelPayPal;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Wallet Client
 * Handles all wallet balance operations and transaction logging
 */
class Wallet_Client {

	/**
	 * User meta key for wallet balance (stored in cents)
	 */
	const META_WALLET_BALANCE = 'voxel_wallet_balance';

	/**
	 * Option key for wallet settings
	 */
	const OPTION_ENABLED = 'voxel_wallet_enabled';

	/**
	 * Transaction table name (without prefix)
	 */
	const TABLE_NAME = 'voxel_wallet_transactions';

	/**
	 * Get all wallet settings from WordPress option
	 *
	 * @return array
	 */
	public static function get_settings(): array {
		$defaults = [
			'enabled' => false,
			'min_deposit' => 1,
			'max_deposit' => 10000,
			'preset_amounts' => [ 10, 25, 50, 100 ],
		];

		$settings = get_option( 'voxel_wallet_settings', [] );

		return wp_parse_args( $settings, $defaults );
	}

	/**
	 * Check if wallet feature is enabled by admin
	 * Also verifies a real payment gateway is available (not just offline)
	 *
	 * @return bool
	 */
	public static function is_enabled(): bool {
		$settings = self::get_settings();
		if ( ! (bool) $settings['enabled'] ) {
			return false;
		}

		// Wallet requires a real payment gateway for deposits
		return self::has_real_payment_gateway();
	}

	/**
	 * Check if a real payment gateway is available (not offline-only)
	 * Wallet deposits require Stripe, PayPal, Paystack, or Mercado Pago
	 *
	 * @return bool
	 */
	public static function has_real_payment_gateway(): bool {
		// Check Stripe
		$stripe_mode = \Voxel\get( 'payments.stripe.mode', 'sandbox' );
		$stripe_secret = ( $stripe_mode === 'live' )
			? \Voxel\get( 'payments.stripe.live.api_key' )
			: \Voxel\get( 'payments.stripe.sandbox.api_key' );

		if ( ! empty( $stripe_secret ) ) {
			return true;
		}

		// Check PayPal
		if ( (bool) \Voxel\get( 'payments.paypal.enabled', false ) ) {
			return true;
		}

		// Check Paystack
		if ( (bool) \Voxel\get( 'payments.paystack.enabled', false ) ) {
			return true;
		}

		// Check Mercado Pago
		if ( (bool) \Voxel\get( 'payments.mercadopago.enabled', false ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Get user's wallet balance in dollars/euros (human readable)
	 *
	 * @param int $user_id
	 * @return float
	 */
	public static function get_balance( int $user_id ): float {
		$balance_cents = self::get_balance_in_cents( $user_id );
		return $balance_cents / 100;
	}

	/**
	 * Get user's wallet balance in cents (stored value)
	 *
	 * @param int $user_id
	 * @return int
	 */
	public static function get_balance_in_cents( int $user_id ): int {
		$balance = get_user_meta( $user_id, self::META_WALLET_BALANCE, true );
		return absint( $balance );
	}

	/**
	 * Get formatted balance with currency symbol
	 *
	 * @param int $user_id
	 * @return string
	 */
	public static function get_balance_formatted( int $user_id ): string {
		$balance = self::get_balance( $user_id );
		return self::format_amount( $balance );
	}

	/**
	 * Format amount with currency symbol
	 *
	 * @param float $amount
	 * @param string|null $currency
	 * @return string
	 */
	public static function format_amount( float $amount, ?string $currency = null ): string {
		if ( $currency === null ) {
			$currency = self::get_site_currency();
		}

		// Get currency symbol
		$symbols = [
			'USD' => '$',
			'EUR' => '€',
			'GBP' => '£',
			'JPY' => '¥',
			'AUD' => 'A$',
			'CAD' => 'C$',
			'CHF' => 'CHF ',
			'CNY' => '¥',
			'INR' => '₹',
			'MXN' => 'MX$',
			'BRL' => 'R$',
			'NGN' => '₦',
			'ZAR' => 'R',
			'GHS' => 'GH₵',
			'KES' => 'KSh',
		];

		$symbol = $symbols[ $currency ] ?? $currency . ' ';

		return $symbol . number_format( $amount, 2 );
	}

	/**
	 * Get site's default currency from configured payment gateway
	 *
	 * @return string
	 */
	public static function get_site_currency(): string {
		// Try Stripe first (most common)
		$stripe_currency = \Voxel\get( 'settings.stripe.currency' );
		if ( ! empty( $stripe_currency ) ) {
			return strtoupper( $stripe_currency );
		}

		// Try PayPal
		$paypal_currency = \Voxel\get( 'payments.paypal.currency' );
		if ( ! empty( $paypal_currency ) ) {
			return strtoupper( $paypal_currency );
		}

		// Try Paystack
		$paystack_currency = \Voxel\get( 'payments.paystack.currency' );
		if ( ! empty( $paystack_currency ) ) {
			return strtoupper( $paystack_currency );
		}

		// Try Mercado Pago
		$mp_currency = \Voxel\get( 'payments.mercadopago.currency' );
		if ( ! empty( $mp_currency ) ) {
			return strtoupper( $mp_currency );
		}

		// Default to USD
		return 'USD';
	}

	/**
	 * Check if user has sufficient balance for an amount
	 *
	 * @param int $user_id
	 * @param float $amount Amount in dollars/euros
	 * @return bool
	 */
	public static function has_sufficient_balance( int $user_id, float $amount ): bool {
		$balance = self::get_balance( $user_id );
		return $balance >= $amount;
	}

	/**
	 * Credit (add) funds to user's wallet
	 *
	 * @param int $user_id
	 * @param float $amount Amount in dollars/euros
	 * @param array $meta Transaction metadata
	 * @return array ['success' => bool, 'transaction_id' => int|null, 'error' => string|null]
	 */
	public static function credit( int $user_id, float $amount, array $meta = [] ): array {
		if ( $amount <= 0 ) {
			return [
				'success' => false,
				'transaction_id' => null,
				'error' => __( 'Invalid amount', 'voxel-payment-gateways' ),
			];
		}

		$amount_cents = (int) round( $amount * 100 );
		$current_balance = self::get_balance_in_cents( $user_id );
		$new_balance = $current_balance + $amount_cents;

		// Update balance
		update_user_meta( $user_id, self::META_WALLET_BALANCE, $new_balance );

		// Log transaction
		$transaction_id = self::log_transaction( [
			'user_id' => $user_id,
			'transaction_type' => $meta['type'] ?? 'deposit',
			'amount' => $amount_cents,
			'balance_after' => $new_balance,
			'currency' => self::get_site_currency(),
			'reference_type' => $meta['reference_type'] ?? null,
			'reference_id' => $meta['reference_id'] ?? null,
			'gateway' => $meta['gateway'] ?? null,
			'gateway_transaction_id' => $meta['gateway_transaction_id'] ?? null,
			'description' => $meta['description'] ?? __( 'Wallet credit', 'voxel-payment-gateways' ),
			'status' => 'completed',
		] );

		// Fire action
		do_action( 'voxel/wallet/credited', $user_id, $amount, $transaction_id );

		return [
			'success' => true,
			'transaction_id' => $transaction_id,
			'new_balance' => $new_balance / 100,
			'error' => null,
		];
	}

	/**
	 * Debit (remove) funds from user's wallet
	 *
	 * @param int $user_id
	 * @param float $amount Amount in dollars/euros
	 * @param array $meta Transaction metadata
	 * @return array ['success' => bool, 'transaction_id' => int|null, 'error' => string|null]
	 */
	public static function debit( int $user_id, float $amount, array $meta = [] ): array {
		if ( $amount <= 0 ) {
			return [
				'success' => false,
				'transaction_id' => null,
				'error' => __( 'Invalid amount', 'voxel-payment-gateways' ),
			];
		}

		$amount_cents = (int) round( $amount * 100 );
		$current_balance = self::get_balance_in_cents( $user_id );

		if ( $current_balance < $amount_cents ) {
			return [
				'success' => false,
				'transaction_id' => null,
				'error' => __( 'Insufficient wallet balance', 'voxel-payment-gateways' ),
			];
		}

		$new_balance = $current_balance - $amount_cents;

		// Update balance
		update_user_meta( $user_id, self::META_WALLET_BALANCE, $new_balance );

		// Log transaction
		$transaction_id = self::log_transaction( [
			'user_id' => $user_id,
			'transaction_type' => $meta['type'] ?? 'purchase',
			'amount' => -$amount_cents, // Negative for debits
			'balance_after' => $new_balance,
			'currency' => self::get_site_currency(),
			'reference_type' => $meta['reference_type'] ?? null,
			'reference_id' => $meta['reference_id'] ?? null,
			'gateway' => 'wallet',
			'gateway_transaction_id' => null,
			'description' => $meta['description'] ?? __( 'Wallet payment', 'voxel-payment-gateways' ),
			'status' => 'completed',
		] );

		// Fire action
		do_action( 'voxel/wallet/debited', $user_id, $amount, $transaction_id );

		return [
			'success' => true,
			'transaction_id' => $transaction_id,
			'new_balance' => $new_balance / 100,
			'error' => null,
		];
	}

	/**
	 * Log a transaction to the database
	 *
	 * @param array $data
	 * @return int|false Transaction ID or false on failure
	 */
	public static function log_transaction( array $data ) {
		global $wpdb;

		$table_name = self::get_table_name();
		$now = current_time( 'mysql', true );

		$result = $wpdb->insert(
			$table_name,
			[
				'user_id' => $data['user_id'],
				'transaction_type' => $data['transaction_type'],
				'amount' => $data['amount'],
				'balance_after' => $data['balance_after'],
				'currency' => $data['currency'],
				'reference_type' => $data['reference_type'],
				'reference_id' => $data['reference_id'],
				'gateway' => $data['gateway'],
				'gateway_transaction_id' => $data['gateway_transaction_id'],
				'description' => $data['description'],
				'status' => $data['status'],
				'created_at' => $now,
				'updated_at' => $now,
			],
			[
				'%d', // user_id
				'%s', // transaction_type
				'%d', // amount
				'%d', // balance_after
				'%s', // currency
				'%s', // reference_type
				'%d', // reference_id
				'%s', // gateway
				'%s', // gateway_transaction_id
				'%s', // description
				'%s', // status
				'%s', // created_at
				'%s', // updated_at
			]
		);

		if ( $result === false ) {
			return false;
		}

		return $wpdb->insert_id;
	}

	/**
	 * Get user's transaction history
	 *
	 * @param int $user_id
	 * @param array $args Query arguments
	 * @return array
	 */
	public static function get_transactions( int $user_id, array $args = [] ): array {
		global $wpdb;

		$table_name = self::get_table_name();

		$defaults = [
			'limit' => 20,
			'offset' => 0,
			'type' => null, // 'deposit', 'purchase', 'refund'
			'order' => 'DESC',
		];

		$args = wp_parse_args( $args, $defaults );

		$where = $wpdb->prepare( 'WHERE user_id = %d', $user_id );

		if ( ! empty( $args['type'] ) ) {
			$where .= $wpdb->prepare( ' AND transaction_type = %s', $args['type'] );
		}

		$order = $args['order'] === 'ASC' ? 'ASC' : 'DESC';
		$limit = absint( $args['limit'] );
		$offset = absint( $args['offset'] );

		$query = "SELECT * FROM {$table_name} {$where} ORDER BY created_at {$order} LIMIT {$limit} OFFSET {$offset}";

		$results = $wpdb->get_results( $query, ARRAY_A );

		if ( empty( $results ) ) {
			return [];
		}

		// Format results
		return array_map( function( $row ) {
			return [
				'id' => (int) $row['id'],
				'type' => $row['transaction_type'],
				'amount' => (int) $row['amount'] / 100, // Convert to dollars
				'amount_formatted' => self::format_amount( abs( (int) $row['amount'] ) / 100, $row['currency'] ),
				'balance_after' => (int) $row['balance_after'] / 100,
				'balance_after_formatted' => self::format_amount( (int) $row['balance_after'] / 100, $row['currency'] ),
				'currency' => $row['currency'],
				'reference_type' => $row['reference_type'],
				'reference_id' => $row['reference_id'] ? (int) $row['reference_id'] : null,
				'gateway' => $row['gateway'],
				'description' => $row['description'],
				'status' => $row['status'],
				'created_at' => $row['created_at'],
				'is_credit' => (int) $row['amount'] > 0,
			];
		}, $results );
	}

	/**
	 * Get transaction count for user
	 *
	 * @param int $user_id
	 * @param string|null $type
	 * @return int
	 */
	public static function get_transaction_count( int $user_id, ?string $type = null ): int {
		global $wpdb;

		$table_name = self::get_table_name();

		$where = $wpdb->prepare( 'WHERE user_id = %d', $user_id );

		if ( ! empty( $type ) ) {
			$where .= $wpdb->prepare( ' AND transaction_type = %s', $type );
		}

		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name} {$where}" );
	}

	/**
	 * Get full table name with prefix
	 *
	 * @return string
	 */
	public static function get_table_name(): string {
		global $wpdb;
		return $wpdb->prefix . self::TABLE_NAME;
	}

	/**
	 * Create database tables
	 */
	public static function create_tables(): void {
		global $wpdb;

		$table_name = self::get_table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id BIGINT(20) UNSIGNED NOT NULL,
			transaction_type VARCHAR(50) NOT NULL,
			amount BIGINT(20) NOT NULL,
			balance_after BIGINT(20) NOT NULL,
			currency VARCHAR(3) NOT NULL,
			reference_type VARCHAR(50) NULL,
			reference_id BIGINT(20) NULL,
			gateway VARCHAR(50) NULL,
			gateway_transaction_id VARCHAR(255) NULL,
			description TEXT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'completed',
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			KEY user_id (user_id),
			KEY transaction_type (transaction_type),
			KEY reference_type_id (reference_type, reference_id),
			KEY created_at (created_at)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Check if database table exists
	 *
	 * @return bool
	 */
	public static function table_exists(): bool {
		global $wpdb;

		$table_name = self::get_table_name();
		$result = $wpdb->get_var( $wpdb->prepare(
			"SHOW TABLES LIKE %s",
			$table_name
		) );

		return $result === $table_name;
	}

	/**
	 * Get minimum deposit amount
	 *
	 * @return float
	 */
	public static function get_min_deposit(): float {
		$settings = self::get_settings();
		return (float) $settings['min_deposit'];
	}

	/**
	 * Get maximum deposit amount
	 *
	 * @return float
	 */
	public static function get_max_deposit(): float {
		$settings = self::get_settings();
		return (float) $settings['max_deposit'];
	}

	/**
	 * Get preset deposit amounts
	 *
	 * @return array
	 */
	public static function get_preset_amounts(): array {
		$settings = self::get_settings();
		$presets = $settings['preset_amounts'];

		if ( empty( $presets ) || ! is_array( $presets ) ) {
			return [ 10, 25, 50, 100 ];
		}

		return array_map( 'floatval', $presets );
	}

	/**
	 * Validate deposit amount
	 *
	 * @param float $amount
	 * @return array ['valid' => bool, 'error' => string|null]
	 */
	public static function validate_deposit_amount( float $amount ): array {
		$min = self::get_min_deposit();
		$max = self::get_max_deposit();

		if ( $amount < $min ) {
			return [
				'valid' => false,
				'error' => sprintf(
					__( 'Minimum deposit amount is %s', 'voxel-payment-gateways' ),
					self::format_amount( $min )
				),
			];
		}

		if ( $amount > $max ) {
			return [
				'valid' => false,
				'error' => sprintf(
					__( 'Maximum deposit amount is %s', 'voxel-payment-gateways' ),
					self::format_amount( $max )
				),
			];
		}

		return [
			'valid' => true,
			'error' => null,
		];
	}

	/**
	 * Refund to wallet (used when order is refunded)
	 *
	 * @param int $user_id
	 * @param float $amount
	 * @param int $order_id
	 * @return array
	 */
	public static function refund( int $user_id, float $amount, int $order_id ): array {
		return self::credit( $user_id, $amount, [
			'type' => 'refund',
			'reference_type' => 'order',
			'reference_id' => $order_id,
			'description' => sprintf( __( 'Refund for Order #%d', 'voxel-payment-gateways' ), $order_id ),
		] );
	}
}
