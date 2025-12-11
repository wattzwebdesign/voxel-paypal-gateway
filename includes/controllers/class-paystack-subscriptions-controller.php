<?php

namespace VoxelPayPal\Controllers;

use VoxelPayPal\Paystack_Client;

if ( ! defined('ABSPATH') ) {
	exit;
}

/**
 * Paystack Subscriptions Controller
 * Handles subscription management operations
 */
class Paystack_Subscriptions_Controller extends \Voxel\Controllers\Base_Controller {

	protected function hooks(): void {
		// Admin AJAX actions for subscription management
		$this->on( 'wp_ajax_paystack_get_subscription', '@get_subscription_details' );
		$this->on( 'wp_ajax_paystack_cancel_subscription', '@cancel_subscription' );
		$this->on( 'wp_ajax_paystack_enable_subscription', '@enable_subscription' );

		// Subscription management link
		$this->on( 'voxel_ajax_paystack.subscription.manage', '@get_manage_link' );
		$this->on( 'voxel_ajax_nopriv_paystack.subscription.manage', '@get_manage_link' );
	}

	/**
	 * Get subscription details
	 */
	protected function get_subscription_details(): void {
		try {
			if ( ! current_user_can( 'manage_options' ) ) {
				throw new \Exception( 'Unauthorized' );
			}

			$subscription_code = sanitize_text_field( $_POST['subscription_code'] ?? '' );

			if ( empty( $subscription_code ) ) {
				throw new \Exception( 'Subscription code is required' );
			}

			$response = Paystack_Client::get_subscription( $subscription_code );

			if ( ! $response['success'] ) {
				throw new \Exception( $response['error'] ?? 'Failed to get subscription' );
			}

			wp_send_json( [
				'success' => true,
				'data' => $response['data'],
			] );

		} catch ( \Exception $e ) {
			wp_send_json( [
				'success' => false,
				'message' => $e->getMessage(),
			] );
		}
	}

	/**
	 * Cancel subscription
	 */
	protected function cancel_subscription(): void {
		try {
			if ( ! current_user_can( 'manage_options' ) ) {
				throw new \Exception( 'Unauthorized' );
			}

			$subscription_code = sanitize_text_field( $_POST['subscription_code'] ?? '' );
			$email_token = sanitize_text_field( $_POST['email_token'] ?? '' );

			if ( empty( $subscription_code ) || empty( $email_token ) ) {
				throw new \Exception( 'Subscription code and email token are required' );
			}

			$response = Paystack_Client::disable_subscription( $subscription_code, $email_token );

			if ( ! $response['success'] ) {
				throw new \Exception( $response['error'] ?? 'Failed to cancel subscription' );
			}

			wp_send_json( [
				'success' => true,
				'message' => 'Subscription cancelled successfully',
			] );

		} catch ( \Exception $e ) {
			wp_send_json( [
				'success' => false,
				'message' => $e->getMessage(),
			] );
		}
	}

	/**
	 * Enable subscription
	 */
	protected function enable_subscription(): void {
		try {
			if ( ! current_user_can( 'manage_options' ) ) {
				throw new \Exception( 'Unauthorized' );
			}

			$subscription_code = sanitize_text_field( $_POST['subscription_code'] ?? '' );
			$email_token = sanitize_text_field( $_POST['email_token'] ?? '' );

			if ( empty( $subscription_code ) || empty( $email_token ) ) {
				throw new \Exception( 'Subscription code and email token are required' );
			}

			$response = Paystack_Client::enable_subscription( $subscription_code, $email_token );

			if ( ! $response['success'] ) {
				throw new \Exception( $response['error'] ?? 'Failed to enable subscription' );
			}

			wp_send_json( [
				'success' => true,
				'message' => 'Subscription enabled successfully',
			] );

		} catch ( \Exception $e ) {
			wp_send_json( [
				'success' => false,
				'message' => $e->getMessage(),
			] );
		}
	}

	/**
	 * Get subscription management link
	 * Returns a link that allows customers to manage their subscription
	 */
	protected function get_manage_link(): void {
		try {
			$order_id = absint( $_GET['order_id'] ?? 0 );

			if ( ! $order_id ) {
				throw new \Exception( 'Invalid order ID' );
			}

			$order = \Voxel\Product_Types\Orders\Order::find( [ 'id' => $order_id ] );

			if ( ! $order ) {
				throw new \Exception( 'Order not found' );
			}

			// Verify the current user owns this order
			$customer = $order->get_customer();
			if ( ! $customer || $customer->get_id() !== get_current_user_id() ) {
				throw new \Exception( 'Unauthorized' );
			}

			$subscription_code = $order->get_details( 'paystack.subscription_code' );

			if ( ! $subscription_code ) {
				throw new \Exception( 'Subscription not found' );
			}

			$response = Paystack_Client::get_subscription_manage_link( $subscription_code );

			if ( ! $response['success'] ) {
				throw new \Exception( $response['error'] ?? 'Failed to get management link' );
			}

			$manage_link = $response['data']['link'] ?? null;

			if ( $manage_link ) {
				wp_redirect( $manage_link );
				exit;
			}

			throw new \Exception( 'Management link not available' );

		} catch ( \Exception $e ) {
			wp_redirect( add_query_arg( [
				'subscription_error' => urlencode( $e->getMessage() ),
			], home_url() ) );
			exit;
		}
	}
}
