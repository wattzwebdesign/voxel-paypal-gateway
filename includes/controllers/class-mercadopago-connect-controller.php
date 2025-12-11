<?php

namespace VoxelPayPal\Controllers;

use VoxelPayPal\MercadoPago_Connect_Client;

if ( ! defined('ABSPATH') ) {
	exit;
}

/**
 * Mercado Pago Connect Controller
 * Handles OAuth vendor connection flow for marketplace
 */
class MercadoPago_Connect_Controller extends \Voxel\Controllers\Base_Controller {

	protected function hooks(): void {
		// OAuth flow endpoints
		$this->on( 'voxel_ajax_mercadopago.oauth.connect', '@handle_oauth_connect' );
		$this->on( 'voxel_ajax_mercadopago.oauth.callback', '@handle_oauth_callback' );
		$this->on( 'voxel_ajax_mercadopago.oauth.disconnect', '@handle_oauth_disconnect' );

		// Allow non-logged-in access for OAuth callback (will verify internally)
		$this->on( 'voxel_ajax_nopriv_mercadopago.oauth.callback', '@handle_oauth_callback' );
	}

	/**
	 * Initiate OAuth connection - redirect vendor to Mercado Pago
	 */
	protected function handle_oauth_connect(): void {
		try {
			$user_id = get_current_user_id();

			if ( ! $user_id ) {
				throw new \Exception( 'You must be logged in to connect your Mercado Pago account.' );
			}

			// Check if marketplace is enabled
			if ( ! MercadoPago_Connect_Client::is_marketplace_enabled() ) {
				throw new \Exception( 'Marketplace mode is not enabled.' );
			}

			// Get OAuth redirect URI
			$redirect_uri = MercadoPago_Connect_Client::get_oauth_redirect_uri();

			// Get authorization URL
			$auth_url = MercadoPago_Connect_Client::get_authorization_url( $user_id, $redirect_uri );

			if ( ! $auth_url ) {
				throw new \Exception( 'OAuth credentials not configured. Please contact the site administrator.' );
			}

			// Redirect to Mercado Pago authorization
			wp_redirect( $auth_url );
			exit;

		} catch ( \Exception $e ) {
			wp_die( esc_html( $e->getMessage() ), 'Connection Error', [ 'back_link' => true ] );
		}
	}

	/**
	 * Handle OAuth callback from Mercado Pago
	 */
	protected function handle_oauth_callback(): void {
		try {
			// Get authorization code from query string
			$code = sanitize_text_field( $_GET['code'] ?? '' );
			$state = sanitize_text_field( $_GET['state'] ?? '' );
			$error = sanitize_text_field( $_GET['error'] ?? '' );

			// Check for OAuth errors
			if ( $error ) {
				$error_description = sanitize_text_field( $_GET['error_description'] ?? 'Authorization was denied.' );
				throw new \Exception( $error_description );
			}

			if ( empty( $code ) ) {
				throw new \Exception( 'Authorization code not received.' );
			}

			// Get current user
			$user_id = get_current_user_id();

			if ( ! $user_id ) {
				// Try to get user from state (if we stored it)
				throw new \Exception( 'You must be logged in to complete the connection.' );
			}

			// Verify state for CSRF protection
			if ( ! MercadoPago_Connect_Client::verify_oauth_state( $user_id, $state ) ) {
				throw new \Exception( 'Invalid state parameter. Please try again.' );
			}

			// Get redirect URI (must match the one used in authorization)
			$redirect_uri = MercadoPago_Connect_Client::get_oauth_redirect_uri();

			// Exchange code for tokens
			$result = MercadoPago_Connect_Client::exchange_code_for_token( $code, $redirect_uri );

			if ( ! $result['success'] ) {
				throw new \Exception( $result['error'] ?? 'Failed to exchange authorization code.' );
			}

			// Store tokens
			$stored = MercadoPago_Connect_Client::store_vendor_tokens( $user_id, $result['data'] );

			if ( ! $stored ) {
				throw new \Exception( 'Failed to save connection details.' );
			}

			// Get return URL from query or default to account page
			$return_url = wp_get_referer() ?: home_url( '/account/' );

			// Add success message
			$return_url = add_query_arg( 'mercadopago_connected', '1', $return_url );

			wp_redirect( $return_url );
			exit;

		} catch ( \Exception $e ) {
			// Redirect back with error
			$return_url = wp_get_referer() ?: home_url();
			$return_url = add_query_arg( [
				'mercadopago_error' => urlencode( $e->getMessage() ),
			], $return_url );

			wp_redirect( $return_url );
			exit;
		}
	}

	/**
	 * Handle OAuth disconnect
	 */
	protected function handle_oauth_disconnect(): void {
		try {
			$user_id = get_current_user_id();

			if ( ! $user_id ) {
				throw new \Exception( 'You must be logged in.' );
			}

			// Verify nonce
			$nonce = sanitize_text_field( $_GET['_wpnonce'] ?? $_POST['_wpnonce'] ?? '' );
			if ( ! wp_verify_nonce( $nonce, 'mercadopago_disconnect_' . $user_id ) ) {
				throw new \Exception( 'Security verification failed.' );
			}

			// Disconnect vendor
			MercadoPago_Connect_Client::disconnect_vendor( $user_id );

			// Return success
			if ( wp_doing_ajax() ) {
				wp_send_json( [ 'success' => true ] );
			} else {
				$return_url = wp_get_referer() ?: home_url();
				$return_url = add_query_arg( 'mercadopago_disconnected', '1', $return_url );
				wp_redirect( $return_url );
				exit;
			}

		} catch ( \Exception $e ) {
			if ( wp_doing_ajax() ) {
				wp_send_json( [
					'success' => false,
					'error' => $e->getMessage(),
				] );
			} else {
				wp_die( esc_html( $e->getMessage() ), 'Disconnect Error', [ 'back_link' => true ] );
			}
		}
	}
}
