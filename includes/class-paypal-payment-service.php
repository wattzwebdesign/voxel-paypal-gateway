<?php

namespace VoxelPayPal;

if ( ! defined('ABSPATH') ) {
	exit;
}

/**
 * PayPal Payment Service
 * Registers PayPal as a payment provider in Voxel
 */
class PayPal_Payment_Service extends \Voxel\Product_Types\Payment_Services\Base_Payment_Service {

	public function get_key(): string {
		return 'paypal';
	}

	public function get_label(): string {
		return 'PayPal';
	}

	public function get_description(): ?string {
		return 'Accept payments via PayPal Checkout. Supports one-time payments, subscriptions, and marketplace transactions with vendor payouts.';
	}

	public function is_test_mode(): bool {
		return PayPal_Client::is_test_mode();
	}

	public function get_settings_schema(): \Voxel\Utils\Config_Schema\Data_Object {
		return \Voxel\Utils\Config_Schema\Schema::Object( [
			'mode' => \Voxel\Utils\Config_Schema\Schema::Enum( [ 'live', 'sandbox' ] )->default('sandbox'),
			'currency' => \Voxel\Utils\Config_Schema\Schema::String()->default('USD'),

			'live' => \Voxel\Utils\Config_Schema\Schema::Object( [
				'client_id' => \Voxel\Utils\Config_Schema\Schema::String(),
				'client_secret' => \Voxel\Utils\Config_Schema\Schema::String(),
				'webhook' => \Voxel\Utils\Config_Schema\Schema::Object( [
					'id' => \Voxel\Utils\Config_Schema\Schema::String(),
					'secret' => \Voxel\Utils\Config_Schema\Schema::String(),
				] ),
			] ),

			'sandbox' => \Voxel\Utils\Config_Schema\Schema::Object( [
				'client_id' => \Voxel\Utils\Config_Schema\Schema::String(),
				'client_secret' => \Voxel\Utils\Config_Schema\Schema::String(),
				'webhook' => \Voxel\Utils\Config_Schema\Schema::Object( [
					'id' => \Voxel\Utils\Config_Schema\Schema::String(),
					'secret' => \Voxel\Utils\Config_Schema\Schema::String(),
				] ),
			] ),

			'payments' => \Voxel\Utils\Config_Schema\Schema::Object( [
				'order_approval' => \Voxel\Utils\Config_Schema\Schema::Enum( [ 'automatic', 'manual' ] )->default('automatic'),
				'brand_name' => \Voxel\Utils\Config_Schema\Schema::String()->default(''),
				'landing_page' => \Voxel\Utils\Config_Schema\Schema::Enum( [ 'LOGIN', 'BILLING', 'NO_PREFERENCE' ] )->default('NO_PREFERENCE'),
			] ),

			'marketplace' => \Voxel\Utils\Config_Schema\Schema::Object( [
				'enabled' => \Voxel\Utils\Config_Schema\Schema::String()->default('0'),
				'fee_type' => \Voxel\Utils\Config_Schema\Schema::Enum( [ 'fixed', 'percentage' ] )->default('percentage'),
				'fee_value' => \Voxel\Utils\Config_Schema\Schema::String()->default('10'),
				'auto_payout' => \Voxel\Utils\Config_Schema\Schema::String()->default('1'),
				'payout_delay_days' => \Voxel\Utils\Config_Schema\Schema::String()->default('0'),
				'shipping_responsibility' => \Voxel\Utils\Config_Schema\Schema::Enum( [ 'platform', 'vendor' ] )->default('vendor'),
			] ),
		] );
	}

	public function get_settings_component(): ?array {
		ob_start();
		require VOXEL_PAYPAL_PATH . 'templates/paypal-settings.php';
		$template = ob_get_clean();

		$src = plugin_dir_url( VOXEL_PAYPAL_FILE ) . 'assets/paypal-settings.esm.js';

		return [
			'src' => add_query_arg( 'v', VOXEL_PAYPAL_VERSION, $src ),
			'template' => $template,
			'data' => [],
		];
	}

	public function get_payment_handler(): ?string {
		return 'paypal_payment';
	}

	public function get_subscription_handler(): ?string {
		return 'paypal_subscription';
	}

	public function get_primary_currency(): ?string {
		$currency = \Voxel\get( 'payments.paypal.currency', 'USD' );
		if ( ! is_string( $currency ) || empty( $currency ) ) {
			return 'USD';
		}

		return strtoupper( $currency );
	}

	public function get_supported_currencies(): array {
		// PayPal supported currencies
		return [
			'AUD', 'BRL', 'CAD', 'CNY', 'CZK', 'DKK', 'EUR', 'HKD', 'HUF',
			'ILS', 'JPY', 'MYR', 'MXN', 'TWD', 'NZD', 'NOK', 'PHP', 'PLN',
			'GBP', 'RUB', 'SGD', 'SEK', 'CHF', 'THB', 'USD',
		];
	}
}
