<?php

namespace VoxelPayPal\Payment_Methods;

use VoxelPayPal\PayPal_Connect_Client;

if ( ! defined('ABSPATH') ) {
	exit;
}

/**
 * PayPal Transfer Payment Method
 * Handles vendor payout transactions in marketplace mode
 */
class PayPal_Transfer extends \Voxel\Product_Types\Payment_Methods\Base_Payment_Method {

	public function get_type(): string {
		return 'paypal_transfer';
	}

	public function get_label(): string {
		return _x( 'PayPal Transfer (Vendor Payout)', 'payment methods', 'voxel-paypal-gateway' );
	}

	/**
	 * Process transfer - creates payout to vendor
	 */
	public function process_payment() {
		try {
			// Verify this is a vendor payout order
			$parent_order_id = $this->order->get_details( 'transfer.parent_order_id' );
			$vendor_id = $this->order->get_details( 'transfer.vendor_id' );
			$amount = $this->order->get_details( 'transfer.amount' );

			if ( ! $parent_order_id || ! $vendor_id || ! $amount ) {
				throw new \Exception( 'Invalid transfer order data' );
			}

			// Get vendor PayPal email
			$vendor_email = PayPal_Connect_Client::get_vendor_paypal_email( $vendor_id );
			if ( ! $vendor_email ) {
				throw new \Exception( 'Vendor PayPal email not found' );
			}

			// Prepare payout
			$payout_items = [
				[
					'recipient_email' => $vendor_email,
					'amount' => $amount,
					'currency' => $this->order->get_currency(),
					'note' => sprintf( 'Payment for order #%d', $parent_order_id ),
					'recipient_id' => $this->order->get_id(),
				],
			];

			$email_subject = sprintf( 'Payment from %s', get_bloginfo( 'name' ) );
			$email_message = sprintf(
				'You have received a payment of %s %s for order #%d.',
				$this->order->get_currency(),
				$amount,
				$parent_order_id
			);

			// Create payout
			$response = PayPal_Connect_Client::create_vendor_payout( $payout_items, $email_subject, $email_message );

			if ( ! $response['success'] ) {
				throw new \Exception( $response['error'] ?? 'Failed to create payout' );
			}

			$payout_data = $response['data'];

			// Store payout details
			$this->order->set_details( 'paypal.payout_batch_id', $payout_data['batch_header']['payout_batch_id'] ?? null );
			$this->order->set_details( 'paypal.payout_status', $payout_data['batch_header']['batch_status'] ?? 'PENDING' );

			if ( ! empty( $payout_data['items'][0]['payout_item_id'] ) ) {
				$this->order->set_transaction_id( $payout_data['items'][0]['payout_item_id'] );
				$this->order->set_details( 'paypal.payout_item_id', $payout_data['items'][0]['payout_item_id'] );
			}

			$this->order->set_details( 'pricing.total', $amount );
			$this->order->set_status( \Voxel\ORDER_PENDING_PAYMENT );
			$this->order->save();

			return [
				'success' => true,
				'message' => _x( 'Payout created successfully', 'checkout', 'voxel-paypal-gateway' ),
			];

		} catch ( \Exception $e ) {
			$this->order->set_status( \Voxel\ORDER_CANCELED );
			$this->order->save();

			return [
				'success' => false,
				'message' => _x( 'Payout failed', 'checkout', 'voxel-paypal-gateway' ),
				'debug' => [
					'type' => 'paypal_transfer_error',
					'message' => $e->getMessage(),
				],
			];
		}
	}

	/**
	 * Handle payout completion from webhook
	 */
	public function handle_payout_completed( array $payout_item ): void {
		$this->order->set_details( 'paypal.payout_item', $payout_item );
		$this->order->set_details( 'paypal.payout_status', 'SUCCESS' );
		$this->order->set_details( 'paypal.payout_completed_at', \Voxel\utc()->format( 'Y-m-d H:i:s' ) );

		// Update transaction amount if available
		if ( ! empty( $payout_item['payout_item']['amount']['value'] ) ) {
			$amount = floatval( $payout_item['payout_item']['amount']['value'] );
			$this->order->set_details( 'pricing.total', $amount );
		}

		$this->order->set_status( \Voxel\ORDER_COMPLETED );
		$this->order->save();

		do_action( 'voxel/paypal/vendor-payout-completed', $this->order, $payout_item );
	}

	/**
	 * Handle payout failure from webhook
	 */
	public function handle_payout_failed( array $payout_item ): void {
		$this->order->set_details( 'paypal.payout_item', $payout_item );
		$this->order->set_details( 'paypal.payout_status', 'FAILED' );
		$this->order->set_details( 'paypal.payout_failed_at', \Voxel\utc()->format( 'Y-m-d H:i:s' ) );

		$failure_reason = $payout_item['payout_item']['errors']['message'] ?? 'Unknown error';
		$this->order->set_details( 'paypal.failure_reason', $failure_reason );

		$this->order->set_status( \Voxel\ORDER_CANCELED );
		$this->order->save();

		do_action( 'voxel/paypal/vendor-payout-failed', $this->order, $payout_item );
	}

	/**
	 * Handle payout blocked from webhook
	 */
	public function handle_payout_blocked( array $payout_item ): void {
		$this->order->set_details( 'paypal.payout_item', $payout_item );
		$this->order->set_details( 'paypal.payout_status', 'BLOCKED' );
		$this->order->set_details( 'paypal.payout_blocked_at', \Voxel\utc()->format( 'Y-m-d H:i:s' ) );

		$this->order->set_status( \Voxel\ORDER_PENDING_APPROVAL );
		$this->order->save();

		do_action( 'voxel/paypal/vendor-payout-blocked', $this->order, $payout_item );
	}

	/**
	 * Handle payout refunded/returned from webhook
	 */
	public function handle_payout_refunded( array $payout_item ): void {
		$this->order->set_details( 'paypal.payout_item', $payout_item );
		$this->order->set_details( 'paypal.payout_status', 'RETURNED' );
		$this->order->set_details( 'paypal.payout_returned_at', \Voxel\utc()->format( 'Y-m-d H:i:s' ) );

		$this->order->set_status( \Voxel\ORDER_REFUNDED );
		$this->order->save();

		do_action( 'voxel/paypal/vendor-payout-returned', $this->order, $payout_item );
	}

	/**
	 * Sync payout status with PayPal
	 */
	public function should_sync(): bool {
		$status = $this->order->get_details( 'paypal.payout_status' );
		return in_array( $status, [ 'PENDING', 'PROCESSING' ] );
	}

	public function sync(): void {
		$payout_item_id = $this->order->get_details( 'paypal.payout_item_id' );
		if ( ! $payout_item_id ) {
			return;
		}

		$response = \VoxelPayPal\PayPal_Client::get_payout_item( $payout_item_id );

		if ( ! $response['success'] || empty( $response['data'] ) ) {
			return;
		}

		$payout_item = $response['data'];
		$status = $payout_item['transaction_status'] ?? null;

		switch ( $status ) {
			case 'SUCCESS':
				$this->handle_payout_completed( $payout_item );
				break;

			case 'FAILED':
			case 'UNCLAIMED':
				$this->handle_payout_failed( $payout_item );
				break;

			case 'BLOCKED':
			case 'ONHOLD':
				$this->handle_payout_blocked( $payout_item );
				break;

			case 'RETURNED':
			case 'REVERSED':
				$this->handle_payout_refunded( $payout_item );
				break;
		}
	}

	/**
	 * Admin actions for managing payouts
	 */
	public function get_vendor_actions(): array {
		$actions = [];

		// Allow canceling unclaimed payouts
		$status = $this->order->get_details( 'paypal.payout_status' );
		$payout_item_id = $this->order->get_details( 'paypal.payout_item_id' );

		if ( in_array( $status, [ 'PENDING', 'UNCLAIMED' ] ) && $payout_item_id ) {
			$actions[] = [
				'action' => 'admin.cancel_payout',
				'label' => _x( 'Cancel Payout', 'order actions', 'voxel-paypal-gateway' ),
				'handler' => function() use ( $payout_item_id ) {
					$response = \VoxelPayPal\PayPal_Client::cancel_payout_item( $payout_item_id );

					if ( $response['success'] ) {
						$this->order->set_status( \Voxel\ORDER_CANCELED );
						$this->order->set_details( 'paypal.payout_status', 'CANCELLED' );
						$this->order->save();

						return wp_send_json( [
							'success' => true,
							'message' => 'Payout cancelled successfully',
						] );
					}

					return wp_send_json( [
						'success' => false,
						'message' => $response['error'] ?? 'Failed to cancel payout',
					] );
				},
			];
		}

		// Allow retrying failed payouts
		if ( in_array( $status, [ 'FAILED', 'BLOCKED', 'RETURNED' ] ) ) {
			$actions[] = [
				'action' => 'admin.retry_payout',
				'label' => _x( 'Retry Payout', 'order actions', 'voxel-paypal-gateway' ),
				'type' => 'primary',
				'handler' => function() {
					// Reset order and retry
					$this->order->set_status( \Voxel\ORDER_PENDING_PAYMENT );
					$this->order->set_details( 'paypal.payout_status', null );
					$this->order->set_details( 'paypal.payout_item_id', null );
					$this->order->save();

					// Reprocess payment
					$result = $this->process_payment();

					return wp_send_json( $result );
				},
			];
		}

		return $actions;
	}
}
