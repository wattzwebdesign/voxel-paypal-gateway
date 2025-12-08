<?php

namespace VoxelPayPal;

if ( ! defined('ABSPATH') ) {
	exit;
}

/**
 * Offline Payment Service
 * Registers Offline/Cash on Delivery as a payment provider in Voxel
 */
class Offline_Payment_Service extends \Voxel\Product_Types\Payment_Services\Base_Payment_Service {

	public function get_key(): string {
		return 'offline';
	}

	public function get_label(): string {
		return 'Offline Payment';
	}

	public function get_description(): ?string {
		return 'Accept offline payments such as Cash on Delivery, Bank Transfer, or Pay at Pickup. Orders are placed immediately and marked as paid manually.';
	}

	public function is_test_mode(): bool {
		return false;
	}

	public function get_settings_schema(): \Voxel\Utils\Config_Schema\Data_Object {
		return \Voxel\Utils\Config_Schema\Schema::Object( [
			'label' => \Voxel\Utils\Config_Schema\Schema::String()->default('Pay Offline'),
			'instructions' => \Voxel\Utils\Config_Schema\Schema::String()->default(''),
			'order_status' => \Voxel\Utils\Config_Schema\Schema::Enum( [ 'pending_payment', 'pending_approval' ] )->default('pending_payment'),
		] );
	}

	public function get_settings_component(): ?array {
		ob_start();
		require VOXEL_GATEWAYS_PATH . 'templates/offline-settings.php';
		$template = ob_get_clean();

		$src = plugin_dir_url( VOXEL_GATEWAYS_FILE ) . 'assets/offline-settings.esm.js';

		return [
			'src' => add_query_arg( 'v', VOXEL_GATEWAYS_VERSION, $src ),
			'template' => $template,
			'data' => [],
		];
	}

	public function get_payment_handler(): ?string {
		return 'offline_payment';
	}

	public function get_subscription_handler(): ?string {
		return null;
	}

	public function get_primary_currency(): ?string {
		// Use the site's default currency or fall back to USD
		$currency = \Voxel\get( 'payments.offline.currency', \Voxel\get( 'settings.stripe.currency', 'USD' ) );
		if ( ! is_string( $currency ) || empty( $currency ) ) {
			return 'USD';
		}

		return strtoupper( $currency );
	}

	public function get_supported_currencies(): array {
		// Offline payments support all currencies
		return [
			'AED', 'AFN', 'ALL', 'AMD', 'ANG', 'AOA', 'ARS', 'AUD', 'AWG', 'AZN',
			'BAM', 'BBD', 'BDT', 'BGN', 'BHD', 'BIF', 'BMD', 'BND', 'BOB', 'BRL',
			'BSD', 'BTN', 'BWP', 'BYN', 'BZD', 'CAD', 'CDF', 'CHF', 'CLP', 'CNY',
			'COP', 'CRC', 'CUP', 'CVE', 'CZK', 'DJF', 'DKK', 'DOP', 'DZD', 'EGP',
			'ERN', 'ETB', 'EUR', 'FJD', 'FKP', 'GBP', 'GEL', 'GHS', 'GIP', 'GMD',
			'GNF', 'GTQ', 'GYD', 'HKD', 'HNL', 'HRK', 'HTG', 'HUF', 'IDR', 'ILS',
			'INR', 'IQD', 'IRR', 'ISK', 'JMD', 'JOD', 'JPY', 'KES', 'KGS', 'KHR',
			'KMF', 'KPW', 'KRW', 'KWD', 'KYD', 'KZT', 'LAK', 'LBP', 'LKR', 'LRD',
			'LSL', 'LYD', 'MAD', 'MDL', 'MGA', 'MKD', 'MMK', 'MNT', 'MOP', 'MRU',
			'MUR', 'MVR', 'MWK', 'MXN', 'MYR', 'MZN', 'NAD', 'NGN', 'NIO', 'NOK',
			'NPR', 'NZD', 'OMR', 'PAB', 'PEN', 'PGK', 'PHP', 'PKR', 'PLN', 'PYG',
			'QAR', 'RON', 'RSD', 'RUB', 'RWF', 'SAR', 'SBD', 'SCR', 'SDG', 'SEK',
			'SGD', 'SHP', 'SLL', 'SOS', 'SRD', 'SSP', 'STN', 'SYP', 'SZL', 'THB',
			'TJS', 'TMT', 'TND', 'TOP', 'TRY', 'TTD', 'TWD', 'TZS', 'UAH', 'UGX',
			'USD', 'UYU', 'UZS', 'VES', 'VND', 'VUV', 'WST', 'XAF', 'XCD', 'XOF',
			'XPF', 'YER', 'ZAR', 'ZMW', 'ZWL',
		];
	}
}
