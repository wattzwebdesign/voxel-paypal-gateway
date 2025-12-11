<?php

namespace VoxelPayPal\Controllers;

use VoxelPayPal\Paystack_Connect_Client;

if ( ! defined('ABSPATH') ) {
	exit;
}

/**
 * Paystack Connect Controller
 * Handles vendor onboarding (bank account connection) for marketplace
 */
class Paystack_Connect_Controller extends \Voxel\Controllers\Base_Controller {

	protected function hooks(): void {
		// Vendor onboarding AJAX endpoints
		$this->on( 'wp_ajax_paystack.vendor.connect', '@handle_vendor_connect' );
		$this->on( 'wp_ajax_paystack.vendor.disconnect', '@handle_vendor_disconnect' );
		$this->on( 'wp_ajax_paystack.vendor.get_banks', '@handle_get_banks' );
		$this->on( 'wp_ajax_paystack.vendor.resolve_account', '@handle_resolve_account' );
		$this->on( 'wp_ajax_paystack.vendor.get_status', '@handle_get_status' );

		// Frontend AJAX for logged-in users
		$this->on( 'voxel_ajax_paystack.connect.banks', '@handle_get_banks_frontend' );
		$this->on( 'voxel_ajax_paystack.connect.resolve', '@handle_resolve_account_frontend' );
		$this->on( 'voxel_ajax_paystack.connect.submit', '@handle_submit_bank_details' );
		$this->on( 'voxel_ajax_paystack.connect.disconnect', '@handle_disconnect' );
		$this->on( 'voxel_ajax_paystack.connect.status', '@handle_connection_status' );
	}

	/**
	 * Handle vendor bank account connection
	 */
	protected function handle_vendor_connect(): void {
		try {
			if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'edit_posts' ) ) {
				throw new \Exception( 'Unauthorized' );
			}

			// Verify nonce
			check_ajax_referer( 'paystack_vendor_connect', 'nonce' );

			$vendor_id = absint( $_POST['vendor_id'] ?? get_current_user_id() );
			$bank_code = sanitize_text_field( $_POST['bank_code'] ?? '' );
			$account_number = sanitize_text_field( $_POST['account_number'] ?? '' );
			$business_name = sanitize_text_field( $_POST['business_name'] ?? '' );

			if ( empty( $bank_code ) || empty( $account_number ) ) {
				throw new \Exception( 'Bank code and account number are required' );
			}

			// Create subaccount
			$result = Paystack_Connect_Client::create_subaccount( $vendor_id, [
				'bank_code' => $bank_code,
				'account_number' => $account_number,
				'business_name' => $business_name,
			] );

			if ( ! $result['success'] ) {
				throw new \Exception( $result['error'] ?? 'Failed to create subaccount' );
			}

			wp_send_json( [
				'success' => true,
				'message' => 'Bank account connected successfully',
				'data' => [
					'subaccount_code' => $result['data']['subaccount_code'] ?? null,
				],
			] );

		} catch ( \Exception $e ) {
			wp_send_json( [
				'success' => false,
				'message' => $e->getMessage(),
			] );
		}
	}

	/**
	 * Handle vendor disconnect
	 */
	protected function handle_vendor_disconnect(): void {
		try {
			if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'edit_posts' ) ) {
				throw new \Exception( 'Unauthorized' );
			}

			// Verify nonce
			check_ajax_referer( 'paystack_vendor_disconnect', 'nonce' );

			$vendor_id = absint( $_POST['vendor_id'] ?? get_current_user_id() );

			Paystack_Connect_Client::disconnect_vendor( $vendor_id );

			wp_send_json( [
				'success' => true,
				'message' => 'Bank account disconnected',
			] );

		} catch ( \Exception $e ) {
			wp_send_json( [
				'success' => false,
				'message' => $e->getMessage(),
			] );
		}
	}

	/**
	 * Get list of banks
	 */
	protected function handle_get_banks(): void {
		try {
			$country = sanitize_text_field( $_GET['country'] ?? $_POST['country'] ?? 'nigeria' );

			$result = Paystack_Connect_Client::list_banks( $country );

			if ( ! $result['success'] ) {
				throw new \Exception( $result['error'] ?? 'Failed to fetch banks' );
			}

			wp_send_json( [
				'success' => true,
				'data' => $result['data'],
			] );

		} catch ( \Exception $e ) {
			wp_send_json( [
				'success' => false,
				'message' => $e->getMessage(),
			] );
		}
	}

	/**
	 * Resolve bank account number
	 */
	protected function handle_resolve_account(): void {
		try {
			$account_number = sanitize_text_field( $_POST['account_number'] ?? '' );
			$bank_code = sanitize_text_field( $_POST['bank_code'] ?? '' );

			if ( empty( $account_number ) || empty( $bank_code ) ) {
				throw new \Exception( 'Account number and bank code are required' );
			}

			$result = Paystack_Connect_Client::resolve_bank_account( $account_number, $bank_code );

			if ( ! $result['success'] ) {
				throw new \Exception( $result['error'] ?? 'Failed to verify account' );
			}

			wp_send_json( [
				'success' => true,
				'data' => [
					'account_name' => $result['data']['account_name'] ?? '',
					'account_number' => $result['data']['account_number'] ?? '',
				],
			] );

		} catch ( \Exception $e ) {
			wp_send_json( [
				'success' => false,
				'message' => $e->getMessage(),
			] );
		}
	}

	/**
	 * Get vendor connection status
	 */
	protected function handle_get_status(): void {
		try {
			$vendor_id = absint( $_GET['vendor_id'] ?? $_POST['vendor_id'] ?? get_current_user_id() );

			$is_connected = Paystack_Connect_Client::is_vendor_connected( $vendor_id );
			$bank_info = Paystack_Connect_Client::get_vendor_bank_info( $vendor_id );

			wp_send_json( [
				'success' => true,
				'data' => [
					'connected' => $is_connected,
					'bank_info' => $is_connected ? $bank_info : null,
				],
			] );

		} catch ( \Exception $e ) {
			wp_send_json( [
				'success' => false,
				'message' => $e->getMessage(),
			] );
		}
	}

	/**
	 * Frontend: Get banks list
	 */
	protected function handle_get_banks_frontend(): void {
		try {
			$user_id = get_current_user_id();

			if ( ! $user_id ) {
				throw new \Exception( 'You must be logged in' );
			}

			if ( ! Paystack_Connect_Client::is_marketplace_enabled() ) {
				throw new \Exception( 'Marketplace mode is not enabled' );
			}

			$country = sanitize_text_field( $_GET['country'] ?? 'nigeria' );

			$result = Paystack_Connect_Client::list_banks( $country );

			if ( ! $result['success'] ) {
				throw new \Exception( $result['error'] ?? 'Failed to fetch banks' );
			}

			// Format banks for select dropdown
			$banks = array_map( function( $bank ) {
				return [
					'code' => $bank['code'],
					'name' => $bank['name'],
				];
			}, $result['data'] ?? [] );

			// Sort alphabetically
			usort( $banks, function( $a, $b ) {
				return strcmp( $a['name'], $b['name'] );
			} );

			wp_send_json( [
				'success' => true,
				'banks' => $banks,
			] );

		} catch ( \Exception $e ) {
			wp_send_json( [
				'success' => false,
				'message' => $e->getMessage(),
			] );
		}
	}

	/**
	 * Frontend: Resolve account
	 */
	protected function handle_resolve_account_frontend(): void {
		try {
			$user_id = get_current_user_id();

			if ( ! $user_id ) {
				throw new \Exception( 'You must be logged in' );
			}

			$account_number = sanitize_text_field( $_POST['account_number'] ?? '' );
			$bank_code = sanitize_text_field( $_POST['bank_code'] ?? '' );

			if ( empty( $account_number ) || empty( $bank_code ) ) {
				throw new \Exception( 'Account number and bank are required' );
			}

			$result = Paystack_Connect_Client::resolve_bank_account( $account_number, $bank_code );

			if ( ! $result['success'] ) {
				throw new \Exception( $result['error'] ?? 'Could not verify account. Please check the details.' );
			}

			wp_send_json( [
				'success' => true,
				'account_name' => $result['data']['account_name'] ?? '',
			] );

		} catch ( \Exception $e ) {
			wp_send_json( [
				'success' => false,
				'message' => $e->getMessage(),
			] );
		}
	}

	/**
	 * Frontend: Submit bank details and create subaccount
	 */
	protected function handle_submit_bank_details(): void {
		try {
			$user_id = get_current_user_id();

			if ( ! $user_id ) {
				throw new \Exception( 'You must be logged in' );
			}

			if ( ! Paystack_Connect_Client::is_marketplace_enabled() ) {
				throw new \Exception( 'Marketplace mode is not enabled' );
			}

			// Verify nonce
			$nonce = sanitize_text_field( $_POST['_wpnonce'] ?? '' );
			if ( ! wp_verify_nonce( $nonce, 'paystack_connect_' . $user_id ) ) {
				throw new \Exception( 'Security verification failed' );
			}

			$bank_code = sanitize_text_field( $_POST['bank_code'] ?? '' );
			$account_number = sanitize_text_field( $_POST['account_number'] ?? '' );
			$business_name = sanitize_text_field( $_POST['business_name'] ?? '' );

			if ( empty( $bank_code ) || empty( $account_number ) ) {
				throw new \Exception( 'Bank and account number are required' );
			}

			// Check if already connected
			if ( Paystack_Connect_Client::is_vendor_connected( $user_id ) ) {
				throw new \Exception( 'You already have a connected bank account. Please disconnect first.' );
			}

			// Create subaccount
			$result = Paystack_Connect_Client::create_subaccount( $user_id, [
				'bank_code' => $bank_code,
				'account_number' => $account_number,
				'business_name' => $business_name,
			] );

			if ( ! $result['success'] ) {
				throw new \Exception( $result['error'] ?? 'Failed to connect bank account' );
			}

			wp_send_json( [
				'success' => true,
				'message' => 'Bank account connected successfully! You can now receive payments.',
			] );

		} catch ( \Exception $e ) {
			wp_send_json( [
				'success' => false,
				'message' => $e->getMessage(),
			] );
		}
	}

	/**
	 * Frontend: Disconnect bank account
	 */
	protected function handle_disconnect(): void {
		try {
			$user_id = get_current_user_id();

			if ( ! $user_id ) {
				throw new \Exception( 'You must be logged in' );
			}

			// Verify nonce
			$nonce = sanitize_text_field( $_POST['_wpnonce'] ?? $_GET['_wpnonce'] ?? '' );
			if ( ! wp_verify_nonce( $nonce, 'paystack_disconnect_' . $user_id ) ) {
				throw new \Exception( 'Security verification failed' );
			}

			Paystack_Connect_Client::disconnect_vendor( $user_id );

			if ( wp_doing_ajax() ) {
				wp_send_json( [
					'success' => true,
					'message' => 'Bank account disconnected',
				] );
			} else {
				$return_url = wp_get_referer() ?: home_url();
				$return_url = add_query_arg( 'paystack_disconnected', '1', $return_url );
				wp_redirect( $return_url );
				exit;
			}

		} catch ( \Exception $e ) {
			if ( wp_doing_ajax() ) {
				wp_send_json( [
					'success' => false,
					'message' => $e->getMessage(),
				] );
			} else {
				wp_die( esc_html( $e->getMessage() ), 'Disconnect Error', [ 'back_link' => true ] );
			}
		}
	}

	/**
	 * Frontend: Get connection status
	 */
	protected function handle_connection_status(): void {
		try {
			$user_id = get_current_user_id();

			if ( ! $user_id ) {
				wp_send_json( [
					'success' => true,
					'connected' => false,
					'marketplace_enabled' => Paystack_Connect_Client::is_marketplace_enabled(),
				] );
				return;
			}

			$is_connected = Paystack_Connect_Client::is_vendor_connected( $user_id );
			$bank_info = $is_connected ? Paystack_Connect_Client::get_vendor_bank_info( $user_id ) : null;

			// Mask account number for display
			if ( $bank_info && ! empty( $bank_info['account_number'] ) ) {
				$acc = $bank_info['account_number'];
				$bank_info['account_number_masked'] = substr( $acc, 0, 3 ) . '****' . substr( $acc, -3 );
			}

			wp_send_json( [
				'success' => true,
				'connected' => $is_connected,
				'marketplace_enabled' => Paystack_Connect_Client::is_marketplace_enabled(),
				'bank_info' => $bank_info,
				'disconnect_nonce' => wp_create_nonce( 'paystack_disconnect_' . $user_id ),
				'connect_nonce' => wp_create_nonce( 'paystack_connect_' . $user_id ),
			] );

		} catch ( \Exception $e ) {
			wp_send_json( [
				'success' => false,
				'message' => $e->getMessage(),
			] );
		}
	}
}
