<?php

namespace VoxelPayPal\Controllers;

use VoxelPayPal\PayPal_Connect_Client;

if ( ! defined('ABSPATH') ) {
	exit;
}

/**
 * PayPal Connect Controller
 * Handles admin-side marketplace and payout management
 */
class PayPal_Connect_Controller extends \Voxel\Controllers\Base_Controller {

	protected function hooks() {
		$this->on( 'voxel/backend/screen:payments/paypal', '@render_marketplace_settings' );
		$this->on( 'voxel_ajax_paypal.admin.save_marketplace_settings', '@save_marketplace_settings' );
		$this->on( 'voxel_ajax_paypal.admin.get_payout_logs', '@get_payout_logs' );
		$this->on( 'voxel_ajax_paypal.admin.manual_payout', '@create_manual_payout' );
	}

	/**
	 * Render marketplace settings section
	 */
	protected function render_marketplace_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// This hook allows adding custom sections to the PayPal settings page
		// The actual settings are rendered via the settings schema in PayPal_Payment_Service
	}

	/**
	 * Save marketplace settings
	 */
	protected function save_marketplace_settings() {
		try {
			if ( ! current_user_can( 'manage_options' ) ) {
				throw new \Exception( 'Unauthorized' );
			}

			$settings = json_decode( file_get_contents( 'php://input' ), true );

			if ( ! is_array( $settings ) ) {
				throw new \Exception( 'Invalid settings data' );
			}

			// Validate and save marketplace settings
			$marketplace_settings = [
				'enabled' => ! empty( $settings['enabled'] ) ? 1 : 0,
				'fee_type' => in_array( $settings['fee_type'] ?? '', [ 'fixed', 'percentage' ] )
					? $settings['fee_type']
					: 'percentage',
				'fee_value' => floatval( $settings['fee_value'] ?? 10 ),
				'auto_payout' => ! empty( $settings['auto_payout'] ) ? 1 : 0,
				'payout_delay_days' => intval( $settings['payout_delay_days'] ?? 0 ),
				'shipping_responsibility' => in_array( $settings['shipping_responsibility'] ?? '', [ 'platform', 'vendor' ] )
					? $settings['shipping_responsibility']
					: 'vendor',
			];

			// Update settings
			$current_settings = \Voxel\get( 'payments.paypal', [] );
			$current_settings['marketplace'] = $marketplace_settings;
			update_option( 'voxel_payments_paypal', $current_settings );

			wp_send_json( [
				'success' => true,
				'message' => 'Marketplace settings saved successfully',
			] );

		} catch ( \Exception $e ) {
			wp_send_json( [
				'success' => false,
				'error' => $e->getMessage(),
			], 400 );
		}
	}

	/**
	 * Get payout logs for admin dashboard
	 */
	protected function get_payout_logs() {
		try {
			if ( ! current_user_can( 'manage_options' ) ) {
				throw new \Exception( 'Unauthorized' );
			}

			$limit = intval( $_GET['limit'] ?? 20 );
			$logs = PayPal_Connect_Client::get_payout_logs( $limit );

			wp_send_json( [
				'success' => true,
				'logs' => $logs,
			] );

		} catch ( \Exception $e ) {
			wp_send_json( [
				'success' => false,
				'error' => $e->getMessage(),
			], 400 );
		}
	}

	/**
	 * Create manual payout (admin-initiated)
	 */
	protected function create_manual_payout() {
		try {
			if ( ! current_user_can( 'manage_options' ) ) {
				throw new \Exception( 'Unauthorized' );
			}

			$data = json_decode( file_get_contents( 'php://input' ), true );

			if ( empty( $data['vendor_id'] ) || empty( $data['amount'] ) || empty( $data['currency'] ) ) {
				throw new \Exception( 'Missing required fields' );
			}

			$vendor_id = intval( $data['vendor_id'] );
			$amount = floatval( $data['amount'] );
			$currency = sanitize_text_field( $data['currency'] );
			$note = sanitize_text_field( $data['note'] ?? 'Manual payout' );

			// Get vendor PayPal email
			$vendor_email = PayPal_Connect_Client::get_vendor_paypal_email( $vendor_id );
			if ( ! $vendor_email ) {
				throw new \Exception( 'Vendor PayPal email not found' );
			}

			// Create payout
			$payout_items = [
				[
					'recipient_email' => $vendor_email,
					'amount' => $amount,
					'currency' => $currency,
					'note' => $note,
					'recipient_id' => 'manual_' . $vendor_id . '_' . time(),
				],
			];

			$result = PayPal_Connect_Client::create_vendor_payout(
				$payout_items,
				'Manual payout from ' . get_bloginfo( 'name' ),
				$note
			);

			if ( ! $result['success'] ) {
				throw new \Exception( $result['error'] ?? 'Failed to create payout' );
			}

			wp_send_json( [
				'success' => true,
				'message' => 'Manual payout created successfully',
				'payout_batch_id' => $result['data']['batch_header']['payout_batch_id'] ?? null,
			] );

		} catch ( \Exception $e ) {
			wp_send_json( [
				'success' => false,
				'error' => $e->getMessage(),
			], 400 );
		}
	}
}
