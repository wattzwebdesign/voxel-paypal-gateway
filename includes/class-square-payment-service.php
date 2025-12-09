<?php

namespace VoxelPayPal;

if ( ! defined('ABSPATH') ) {
	exit;
}

/**
 * Square Payment Service
 * Registers Square as a payment provider in Voxel
 */
class Square_Payment_Service extends \Voxel\Product_Types\Payment_Services\Base_Payment_Service {

	public function get_key(): string {
		return 'square';
	}

	public function get_label(): string {
		return 'Square';
	}

	public function get_description(): ?string {
		return 'Accept payments via Square. Supports cards, digital wallets (Apple Pay, Google Pay), Cash App, ACH bank transfers, and gift cards.';
	}

	public function is_test_mode(): bool {
		return Square_Client::is_test_mode();
	}

	public function get_settings_schema(): \Voxel\Utils\Config_Schema\Data_Object {
		return \Voxel\Utils\Config_Schema\Schema::Object( [
			'mode' => \Voxel\Utils\Config_Schema\Schema::Enum( [ 'live', 'sandbox' ] )->default('sandbox'),
			'currency' => \Voxel\Utils\Config_Schema\Schema::String()->default('USD'),

			'live' => \Voxel\Utils\Config_Schema\Schema::Object( [
				'application_id' => \Voxel\Utils\Config_Schema\Schema::String(),
				'access_token' => \Voxel\Utils\Config_Schema\Schema::String(),
				'location_id' => \Voxel\Utils\Config_Schema\Schema::String(),
				'webhook_signature_key' => \Voxel\Utils\Config_Schema\Schema::String(),
			] ),

			'sandbox' => \Voxel\Utils\Config_Schema\Schema::Object( [
				'application_id' => \Voxel\Utils\Config_Schema\Schema::String(),
				'access_token' => \Voxel\Utils\Config_Schema\Schema::String(),
				'location_id' => \Voxel\Utils\Config_Schema\Schema::String(),
				'webhook_signature_key' => \Voxel\Utils\Config_Schema\Schema::String(),
			] ),

			'payments' => \Voxel\Utils\Config_Schema\Schema::Object( [
				'order_approval' => \Voxel\Utils\Config_Schema\Schema::Enum( [ 'automatic', 'manual' ] )->default('automatic'),
				'brand_name' => \Voxel\Utils\Config_Schema\Schema::String()->default(''),
			] ),
		] );
	}

	public function get_settings_component(): ?array {
		ob_start();
		require VOXEL_GATEWAYS_PATH . 'templates/square-settings.php';
		$template = ob_get_clean();

		$src = plugin_dir_url( VOXEL_GATEWAYS_FILE ) . 'assets/square-settings.esm.js';

		return [
			'src' => add_query_arg( 'v', VOXEL_GATEWAYS_VERSION, $src ),
			'template' => $template,
			'data' => [],
		];
	}

	public function get_payment_handler(): ?string {
		return 'square_payment';
	}

	public function get_subscription_handler(): ?string {
		return 'square_subscription';
	}

	public function get_primary_currency(): ?string {
		$currency = \Voxel\get( 'payments.square.currency', 'USD' );
		if ( ! is_string( $currency ) || empty( $currency ) ) {
			return 'USD';
		}

		return strtoupper( $currency );
	}

	public function get_supported_currencies(): array {
		// Square supported currencies
		return [
			'USD', 'CAD', 'GBP', 'EUR', 'AUD', 'JPY',
		];
	}
}
