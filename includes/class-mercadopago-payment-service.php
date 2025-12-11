<?php

namespace VoxelPayPal;

if ( ! defined('ABSPATH') ) {
	exit;
}

/**
 * Mercado Pago Payment Service
 * Registers Mercado Pago as a payment provider in Voxel
 */
class MercadoPago_Payment_Service extends \Voxel\Product_Types\Payment_Services\Base_Payment_Service {

	public function get_key(): string {
		return 'mercadopago';
	}

	public function get_label(): string {
		return 'Mercado Pago';
	}

	public function get_description(): ?string {
		return 'Accept payments via Mercado Pago. Supports credit cards, debit cards, bank transfers, cash payments (boleto, OXXO), and digital wallets across Latin America.';
	}

	public function is_test_mode(): bool {
		return MercadoPago_Client::is_test_mode();
	}

	public function get_settings_schema(): \Voxel\Utils\Config_Schema\Data_Object {
		return \Voxel\Utils\Config_Schema\Schema::Object( [
			'mode' => \Voxel\Utils\Config_Schema\Schema::Enum( [ 'live', 'sandbox' ] )->default('sandbox'),
			'currency' => \Voxel\Utils\Config_Schema\Schema::String()->default('ARS'),

			'live' => \Voxel\Utils\Config_Schema\Schema::Object( [
				'access_token' => \Voxel\Utils\Config_Schema\Schema::String(),
				'public_key' => \Voxel\Utils\Config_Schema\Schema::String(),
				'application_id' => \Voxel\Utils\Config_Schema\Schema::String(),
				'client_secret' => \Voxel\Utils\Config_Schema\Schema::String(),
				'webhook_secret' => \Voxel\Utils\Config_Schema\Schema::String(),
			] ),

			'sandbox' => \Voxel\Utils\Config_Schema\Schema::Object( [
				'access_token' => \Voxel\Utils\Config_Schema\Schema::String(),
				'public_key' => \Voxel\Utils\Config_Schema\Schema::String(),
				'application_id' => \Voxel\Utils\Config_Schema\Schema::String(),
				'client_secret' => \Voxel\Utils\Config_Schema\Schema::String(),
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
			] ),
		] );
	}

	public function get_settings_component(): ?array {
		ob_start();
		require VOXEL_GATEWAYS_PATH . 'templates/mercadopago-settings.php';
		$template = ob_get_clean();

		$src = plugin_dir_url( VOXEL_GATEWAYS_FILE ) . 'assets/mercadopago-settings.esm.js';

		return [
			'src' => add_query_arg( 'v', VOXEL_GATEWAYS_VERSION, $src ),
			'template' => $template,
			'data' => [],
		];
	}

	public function get_payment_handler(): ?string {
		return 'mercadopago_payment';
	}

	public function get_subscription_handler(): ?string {
		return 'mercadopago_subscription';
	}

	public function get_primary_currency(): ?string {
		$currency = \Voxel\get( 'payments.mercadopago.currency', 'ARS' );
		if ( ! is_string( $currency ) || empty( $currency ) ) {
			return 'ARS';
		}

		return strtoupper( $currency );
	}

	public function get_supported_currencies(): array {
		// Mercado Pago supported currencies (Latin America only)
		return [
			'ARS', // Argentine Peso
			'BRL', // Brazilian Real
			'CLP', // Chilean Peso
			'COP', // Colombian Peso
			'MXN', // Mexican Peso
			'PEN', // Peruvian Sol
			'UYU', // Uruguayan Peso
		];
	}
}
