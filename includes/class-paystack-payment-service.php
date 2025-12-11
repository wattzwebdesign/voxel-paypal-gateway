<?php

namespace VoxelPayPal;

if ( ! defined('ABSPATH') ) {
	exit;
}

/**
 * Paystack Payment Service
 * Registers Paystack as a payment provider in Voxel
 */
class Paystack_Payment_Service extends \Voxel\Product_Types\Payment_Services\Base_Payment_Service {

	public function get_key(): string {
		return 'paystack';
	}

	public function get_label(): string {
		return 'Paystack';
	}

	public function get_description(): ?string {
		return 'Accept payments via Paystack. Supports cards, bank transfers, mobile money, USSD, and more across Africa.';
	}

	public function is_test_mode(): bool {
		return Paystack_Client::is_test_mode();
	}

	public function get_settings_schema(): \Voxel\Utils\Config_Schema\Data_Object {
		return \Voxel\Utils\Config_Schema\Schema::Object( [
			'mode' => \Voxel\Utils\Config_Schema\Schema::Enum( [ 'live', 'sandbox' ] )->default('sandbox'),
			'currency' => \Voxel\Utils\Config_Schema\Schema::String()->default('NGN'),

			'live' => \Voxel\Utils\Config_Schema\Schema::Object( [
				'secret_key' => \Voxel\Utils\Config_Schema\Schema::String(),
				'public_key' => \Voxel\Utils\Config_Schema\Schema::String(),
				'webhook_secret' => \Voxel\Utils\Config_Schema\Schema::String(),
			] ),

			'sandbox' => \Voxel\Utils\Config_Schema\Schema::Object( [
				'secret_key' => \Voxel\Utils\Config_Schema\Schema::String(),
				'public_key' => \Voxel\Utils\Config_Schema\Schema::String(),
				'webhook_secret' => \Voxel\Utils\Config_Schema\Schema::String(),
			] ),

			'payments' => \Voxel\Utils\Config_Schema\Schema::Object( [
				'order_approval' => \Voxel\Utils\Config_Schema\Schema::Enum( [ 'automatic', 'manual' ] )->default('automatic'),
				'brand_name' => \Voxel\Utils\Config_Schema\Schema::String()->default(''),
			] ),

			'marketplace' => \Voxel\Utils\Config_Schema\Schema::Object( [
				'enabled' => \Voxel\Utils\Config_Schema\Schema::String()->default('0'),
				'fee_type' => \Voxel\Utils\Config_Schema\Schema::Enum( [ 'fixed', 'percentage' ] )->default('percentage'),
				'fee_value' => \Voxel\Utils\Config_Schema\Schema::String()->default('10'),
				'fee_bearer' => \Voxel\Utils\Config_Schema\Schema::Enum( [ 'account', 'subaccount', 'all', 'all_proportional' ] )->default('account'),
			] ),
		] );
	}

	public function get_settings_component(): ?array {
		ob_start();
		require VOXEL_GATEWAYS_PATH . 'templates/paystack-settings.php';
		$template = ob_get_clean();

		$src = plugin_dir_url( VOXEL_GATEWAYS_FILE ) . 'assets/paystack-settings.esm.js';

		return [
			'src' => add_query_arg( 'v', VOXEL_GATEWAYS_VERSION, $src ),
			'template' => $template,
			'data' => [],
		];
	}

	public function get_payment_handler(): ?string {
		return 'paystack_payment';
	}

	public function get_subscription_handler(): ?string {
		return 'paystack_subscription';
	}

	public function get_primary_currency(): ?string {
		$currency = \Voxel\get( 'payments.paystack.currency', 'NGN' );
		if ( ! is_string( $currency ) || empty( $currency ) ) {
			return 'NGN';
		}

		return strtoupper( $currency );
	}

	public function get_supported_currencies(): array {
		// Paystack supported currencies
		return [
			'NGN', // Nigerian Naira
			'GHS', // Ghanaian Cedi
			'ZAR', // South African Rand
			'USD', // US Dollar
			'KES', // Kenyan Shilling
		];
	}
}
