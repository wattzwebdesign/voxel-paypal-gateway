<?php

namespace VoxelPayPal\Controllers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Wallet Ajax Controller
 * Handles AJAX endpoints for wallet operations
 */
class Wallet_Ajax_Controller extends \Voxel\Controllers\Base_Controller {

	protected function hooks(): void {
		error_log( 'Wallet Ajax Controller: Registering hooks' );

		// Get balance endpoint
		$this->on( 'voxel_ajax_wallet.get_balance', '@handle_get_balance' );

		// Get transactions endpoint
		$this->on( 'voxel_ajax_wallet.get_transactions', '@handle_get_transactions' );

		// Pay with wallet endpoint (for checkout)
		$this->on( 'voxel_ajax_wallet.pay_with_wallet', '@handle_pay_with_wallet' );

		// Wallet checkout endpoint (creates order and pays)
		$this->on( 'voxel_ajax_wallet.checkout', '@handle_wallet_checkout' );
		$this->on( 'voxel_ajax_nopriv_wallet.checkout', '@handle_wallet_checkout' );

		// Hook into Voxel's checkout to intercept wallet payments
		// Priority 5 to run before Voxel's handler (priority 10)
		$this->on( 'voxel_ajax_products.checkout', '@maybe_intercept_checkout', 5 );
	}

	/**
	 * Intercept Voxel's checkout when wallet payment is selected
	 * This runs BEFORE Voxel's checkout handler
	 */
	protected function maybe_intercept_checkout(): void {
		error_log( 'Wallet intercept: Hook fired!' );

		// Check if this is a wallet payment request
		$raw_input = file_get_contents( 'php://input' );
		error_log( 'Wallet intercept: Raw input: ' . substr( $raw_input, 0, 500 ) );

		$input = json_decode( $raw_input, true );

		// Also check $_REQUEST for the flag
		$use_wallet = ! empty( $input['use_wallet'] ) || ! empty( $_REQUEST['use_wallet'] );

		error_log( 'Wallet intercept: use_wallet from body=' . ( ! empty( $input['use_wallet'] ) ? 'true' : 'false' ) . ', from REQUEST=' . ( ! empty( $_REQUEST['use_wallet'] ) ? 'true' : 'false' ) );

		if ( ! $use_wallet ) {
			// Not a wallet payment, let Voxel handle it
			error_log( 'Wallet intercept: No wallet flag, passing to Voxel' );
			return;
		}

		error_log( 'Wallet intercept: Intercepting checkout for wallet payment' );
		error_log( 'Wallet intercept: Raw input: ' . $raw_input );

		// Check if wallet is enabled
		if ( ! \VoxelPayPal\Wallet_Client::is_enabled() ) {
			wp_send_json( [
				'success' => false,
				'message' => __( 'Wallet feature is not available', 'voxel-payment-gateways' ),
			] );
			return;
		}

		$user_id = get_current_user_id();

		if ( ! $user_id ) {
			wp_send_json( [
				'success' => false,
				'message' => __( 'Please log in to use your wallet', 'voxel-payment-gateways' ),
			] );
			return;
		}

		try {
			// Get cart items from Voxel's request format
			$source = $input['source'] ?? $_REQUEST['source'] ?? '';
			$items_json = $input['items'] ?? $_REQUEST['items'] ?? '';

			error_log( 'Wallet intercept: source=' . $source . ', items_json=' . $items_json );

			// Parse items - Voxel sends them as JSON string in direct_cart
			$items_config = [];
			if ( is_string( $items_json ) ) {
				$items_config = json_decode( wp_unslash( $items_json ), true );
			} elseif ( is_array( $items_json ) ) {
				$items_config = $items_json;
			}

			if ( ! is_array( $items_config ) || empty( $items_config ) ) {
				throw new \Exception( __( 'No items in cart', 'voxel-payment-gateways' ) );
			}

			error_log( 'Wallet intercept: Parsed items: ' . print_r( $items_config, true ) );

			// Create cart using Voxel's Direct_Cart
			$cart = new \Voxel\Product_Types\Cart\Direct_Cart();

			foreach ( $items_config as $item_config ) {
				if ( is_array( $item_config ) ) {
					$cart_item = \Voxel\Product_Types\Cart_Items\Cart_Item::create( $item_config );
					if ( $cart_item ) {
						$cart->add_item( $cart_item );
					}
				}
			}

			// Check if cart has items
			if ( empty( $cart->get_items() ) ) {
				throw new \Exception( __( 'Failed to create cart items', 'voxel-payment-gateways' ) );
			}

			// Calculate total from cart
			$total = 0;
			foreach ( $cart->get_items() as $item ) {
				$pricing_summary = $item->get_pricing_summary();
				if ( isset( $pricing_summary['total_amount'] ) ) {
					$total += $pricing_summary['total_amount'];
				}
			}

			error_log( 'Wallet intercept: Calculated total=' . $total );

			if ( $total <= 0 ) {
				throw new \Exception( __( 'Invalid order total', 'voxel-payment-gateways' ) );
			}

			// Check if user has sufficient balance
			if ( ! \VoxelPayPal\Wallet_Client::has_sufficient_balance( $user_id, $total ) ) {
				$balance_formatted = \VoxelPayPal\Wallet_Client::get_balance_formatted( $user_id );
				$total_formatted = \VoxelPayPal\Wallet_Client::format_amount( $total );

				throw new \Exception( sprintf(
					__( 'Insufficient wallet balance. You have %1$s but need %2$s', 'voxel-payment-gateways' ),
					$balance_formatted,
					$total_formatted
				) );
			}

			error_log( 'Wallet intercept: Balance sufficient, creating order' );

			// Create the order using Voxel's system
			$order = \Voxel\Product_Types\Orders\Order::create_from_cart( $cart, [
				'meta' => [
					'wallet_payment' => true,
				],
			] );

			if ( ! $order ) {
				throw new \Exception( __( 'Failed to create order', 'voxel-payment-gateways' ) );
			}

			$order_id = $order->get_id();
			error_log( 'Wallet intercept: Order created, id=' . $order_id );

			// Debit from wallet
			$result = \VoxelPayPal\Wallet_Client::debit( $user_id, $total, [
				'type' => 'purchase',
				'reference_type' => 'order',
				'reference_id' => $order_id,
				'description' => sprintf( __( 'Payment for Order #%d', 'voxel-payment-gateways' ), $order_id ),
			] );

			if ( ! $result['success'] ) {
				// Failed to debit - cancel the order
				$order->set_status( \Voxel\ORDER_CANCELED );
				$order->save();

				throw new \Exception( $result['error'] ?? __( 'Failed to process wallet payment', 'voxel-payment-gateways' ) );
			}

			error_log( 'Wallet intercept: Debited from wallet, transaction_id=' . $result['transaction_id'] );

			// Update order status to completed
			// Use offline_payment as payment method since wallet payments don't need external sync
			$order->set_payment_method( 'offline_payment' );
			$order->set_status( \Voxel\ORDER_COMPLETED );
			$order->set_transaction_id( 'wallet_' . $result['transaction_id'] );
			$order->set_details( 'wallet.paid', true );
			$order->set_details( 'wallet.transaction_id', $result['transaction_id'] );
			$order->set_details( 'wallet.paid_at', \Voxel\utc()->format( 'Y-m-d H:i:s' ) );
			$order->set_details( 'pricing.total', $total );
			$order->save();

			// Clear the cart
			$cart->empty();
			$cart->update();

			// Dispatch order completed event
			if ( class_exists( '\Voxel\Events\Products\Orders\Customer_Completed_Order_Event' ) ) {
				( new \Voxel\Events\Products\Orders\Customer_Completed_Order_Event() )->dispatch( $order_id );
			}

			// Get redirect URL using Voxel's built-in method
			$redirect_url = $order->get_success_redirect();

			error_log( 'Wallet intercept: Success, redirecting to ' . $redirect_url );

			wp_send_json( [
				'success' => true,
				'message' => __( 'Payment successful!', 'voxel-payment-gateways' ),
				'redirect_url' => $redirect_url,
				'order_id' => $order_id,
				'new_balance' => $result['new_balance'],
				'new_balance_formatted' => \VoxelPayPal\Wallet_Client::format_amount( $result['new_balance'] ),
			] );

		} catch ( \Exception $e ) {
			error_log( 'Wallet intercept error: ' . $e->getMessage() );
			wp_send_json( [
				'success' => false,
				'message' => $e->getMessage(),
			] );
		}
	}

	/**
	 * Get current user's wallet balance
	 */
	protected function handle_get_balance(): void {
		// Check if wallet is enabled
		if ( ! \VoxelPayPal\Wallet_Client::is_enabled() ) {
			wp_send_json( [
				'success' => false,
				'message' => __( 'Wallet feature is not available', 'voxel-payment-gateways' ),
			] );
			return;
		}

		$user_id = get_current_user_id();

		if ( ! $user_id ) {
			wp_send_json( [
				'success' => false,
				'message' => __( 'Please log in to view your wallet', 'voxel-payment-gateways' ),
			] );
			return;
		}

		$balance = \VoxelPayPal\Wallet_Client::get_balance( $user_id );
		$balance_formatted = \VoxelPayPal\Wallet_Client::get_balance_formatted( $user_id );
		$currency = \VoxelPayPal\Wallet_Client::get_site_currency();

		wp_send_json( [
			'success' => true,
			'balance' => $balance,
			'balance_formatted' => $balance_formatted,
			'currency' => $currency,
		] );
	}

	/**
	 * Get current user's transaction history
	 */
	protected function handle_get_transactions(): void {
		// Check if wallet is enabled
		if ( ! \VoxelPayPal\Wallet_Client::is_enabled() ) {
			wp_send_json( [
				'success' => false,
				'message' => __( 'Wallet feature is not available', 'voxel-payment-gateways' ),
			] );
			return;
		}

		$user_id = get_current_user_id();

		if ( ! $user_id ) {
			wp_send_json( [
				'success' => false,
				'message' => __( 'Please log in to view your transactions', 'voxel-payment-gateways' ),
			] );
			return;
		}

		$limit = isset( $_GET['limit'] ) ? absint( $_GET['limit'] ) : 20;
		$offset = isset( $_GET['offset'] ) ? absint( $_GET['offset'] ) : 0;
		$type = isset( $_GET['type'] ) ? sanitize_text_field( $_GET['type'] ) : null;

		$transactions = \VoxelPayPal\Wallet_Client::get_transactions( $user_id, [
			'limit' => min( $limit, 100 ),
			'offset' => $offset,
			'type' => $type,
		] );

		$total = \VoxelPayPal\Wallet_Client::get_transaction_count( $user_id, $type );

		wp_send_json( [
			'success' => true,
			'transactions' => $transactions,
			'total' => $total,
			'has_more' => ( $offset + count( $transactions ) ) < $total,
		] );
	}

	/**
	 * Pay for an order using wallet balance
	 */
	protected function handle_pay_with_wallet(): void {
		// Check if wallet is enabled
		if ( ! \VoxelPayPal\Wallet_Client::is_enabled() ) {
			wp_send_json( [
				'success' => false,
				'message' => __( 'Wallet feature is not available', 'voxel-payment-gateways' ),
			] );
			return;
		}

		$user_id = get_current_user_id();

		if ( ! $user_id ) {
			wp_send_json( [
				'success' => false,
				'message' => __( 'Please log in to use your wallet', 'voxel-payment-gateways' ),
			] );
			return;
		}

		// Get JSON input
		$input = json_decode( file_get_contents( 'php://input' ), true );

		if ( ! is_array( $input ) || empty( $input['order_id'] ) ) {
			wp_send_json( [
				'success' => false,
				'message' => __( 'Invalid request', 'voxel-payment-gateways' ),
			] );
			return;
		}

		$order_id = absint( $input['order_id'] );

		// Get the order
		$order = \Voxel\Product_Types\Orders\Order::find( [ 'id' => $order_id ] );

		if ( ! $order ) {
			wp_send_json( [
				'success' => false,
				'message' => __( 'Order not found', 'voxel-payment-gateways' ),
			] );
			return;
		}

		// Verify the order belongs to this user
		$customer = $order->get_customer();
		if ( ! $customer || $customer->get_id() !== $user_id ) {
			wp_send_json( [
				'success' => false,
				'message' => __( 'You cannot pay for this order', 'voxel-payment-gateways' ),
			] );
			return;
		}

		// Verify order is pending payment
		$status = $order->get_status();
		if ( $status !== \Voxel\ORDER_PENDING_PAYMENT && $status !== \Voxel\ORDER_PENDING_APPROVAL ) {
			wp_send_json( [
				'success' => false,
				'message' => __( 'This order cannot be paid with wallet', 'voxel-payment-gateways' ),
			] );
			return;
		}

		// Calculate order total
		$total = $this->calculate_order_total( $order );

		if ( $total <= 0 ) {
			wp_send_json( [
				'success' => false,
				'message' => __( 'Invalid order total', 'voxel-payment-gateways' ),
			] );
			return;
		}

		// Check if user has sufficient balance
		if ( ! \VoxelPayPal\Wallet_Client::has_sufficient_balance( $user_id, $total ) ) {
			$balance_formatted = \VoxelPayPal\Wallet_Client::get_balance_formatted( $user_id );
			$total_formatted = \VoxelPayPal\Wallet_Client::format_amount( $total );

			wp_send_json( [
				'success' => false,
				'message' => sprintf(
					__( 'Insufficient wallet balance. You have %1$s but need %2$s', 'voxel-payment-gateways' ),
					$balance_formatted,
					$total_formatted
				),
			] );
			return;
		}

		// Debit from wallet
		$result = \VoxelPayPal\Wallet_Client::debit( $user_id, $total, [
			'type' => 'purchase',
			'reference_type' => 'order',
			'reference_id' => $order_id,
			'description' => sprintf( __( 'Payment for Order #%d', 'voxel-payment-gateways' ), $order_id ),
		] );

		if ( ! $result['success'] ) {
			wp_send_json( [
				'success' => false,
				'message' => $result['error'] ?? __( 'Failed to process wallet payment', 'voxel-payment-gateways' ),
			] );
			return;
		}

		// Update order status
		// Use offline_payment as payment method since wallet payments don't need external sync
		$order->set_payment_method( 'offline_payment' );
		$order->set_status( \Voxel\ORDER_COMPLETED );
		$order->set_transaction_id( 'wallet_' . $result['transaction_id'] );
		$order->set_details( 'wallet.paid', true );
		$order->set_details( 'wallet.transaction_id', $result['transaction_id'] );
		$order->set_details( 'wallet.paid_at', \Voxel\utc()->format( 'Y-m-d H:i:s' ) );
		$order->set_details( 'pricing.total', $total );
		$order->save();

		// Dispatch order completed event
		if ( class_exists( '\Voxel\Events\Products\Orders\Customer_Completed_Order_Event' ) ) {
			( new \Voxel\Events\Products\Orders\Customer_Completed_Order_Event() )->dispatch( $order_id );
		}

		// Get redirect URL using Voxel's built-in method
		$redirect_url = $order->get_success_redirect();

		wp_send_json( [
			'success' => true,
			'message' => __( 'Payment successful!', 'voxel-payment-gateways' ),
			'redirect_url' => $redirect_url,
			'new_balance' => $result['new_balance'],
			'new_balance_formatted' => \VoxelPayPal\Wallet_Client::format_amount( $result['new_balance'] ),
		] );
	}

	/**
	 * Calculate order total from line items
	 */
	private function calculate_order_total( $order ): float {
		// Try to get stored total first
		$stored_total = $order->get_details( 'pricing.total' );
		if ( is_numeric( $stored_total ) && $stored_total > 0 ) {
			return floatval( $stored_total );
		}

		// Calculate from items
		$items = $order->get_items();
		$total = 0;

		foreach ( $items as $item ) {
			if ( isset( $item['amount'] ) && isset( $item['quantity'] ) ) {
				$total += floatval( $item['amount'] ) * intval( $item['quantity'] );
			}
		}

		return $total;
	}

	/**
	 * Handle wallet checkout - creates order and pays with wallet in one step
	 */
	protected function handle_wallet_checkout(): void {
		error_log( 'Wallet checkout: Starting - method=' . $_SERVER['REQUEST_METHOD'] );

		// Check if wallet is enabled
		if ( ! \VoxelPayPal\Wallet_Client::is_enabled() ) {
			error_log( 'Wallet checkout: Wallet not enabled' );
			wp_send_json( [
				'success' => false,
				'message' => __( 'Wallet feature is not available', 'voxel-payment-gateways' ),
			] );
			return;
		}

		error_log( 'Wallet checkout: Wallet is enabled' );

		$user_id = get_current_user_id();
		error_log( 'Wallet checkout: user_id=' . $user_id );

		if ( ! $user_id ) {
			wp_send_json( [
				'success' => false,
				'message' => __( 'Please log in to use your wallet', 'voxel-payment-gateways' ),
			] );
			return;
		}

		// Get JSON input
		$raw_input = file_get_contents( 'php://input' );
		error_log( 'Wallet checkout: raw_input=' . $raw_input );
		$input = json_decode( $raw_input, true );

		$provided_total = isset( $input['total'] ) ? floatval( $input['total'] ) : 0;
		$items_config = isset( $input['items'] ) ? $input['items'] : [];
		$source = isset( $input['source'] ) ? $input['source'] : '';

		error_log( 'Wallet checkout: total=' . $provided_total . ', source=' . $source . ', items=' . count( $items_config ) );

		if ( $provided_total <= 0 ) {
			wp_send_json( [
				'success' => false,
				'message' => __( 'Invalid order total', 'voxel-payment-gateways' ),
			] );
			return;
		}

		try {
			// Check if user has sufficient balance
			if ( ! \VoxelPayPal\Wallet_Client::has_sufficient_balance( $user_id, $provided_total ) ) {
				$balance_formatted = \VoxelPayPal\Wallet_Client::get_balance_formatted( $user_id );
				$total_formatted = \VoxelPayPal\Wallet_Client::format_amount( $provided_total );

				throw new \Exception( sprintf(
					__( 'Insufficient wallet balance. You have %1$s but need %2$s', 'voxel-payment-gateways' ),
					$balance_formatted,
					$total_formatted
				) );
			}

			error_log( 'Wallet checkout: Balance sufficient' );

			// Create a direct cart with the items
			$cart = new \Voxel\Product_Types\Cart\Direct_Cart();

			// If we have items config from Vue, use it
			if ( ! empty( $items_config ) && is_array( $items_config ) ) {
				foreach ( $items_config as $item_config ) {
					if ( is_array( $item_config ) ) {
						$cart_item = \Voxel\Product_Types\Cart_Items\Cart_Item::create( $item_config );
						if ( $cart_item ) {
							$cart->add_item( $cart_item );
						}
					}
				}
			}

			// Check if cart has items
			if ( empty( $cart->get_items() ) ) {
				error_log( 'Wallet checkout: No items in cart' );
				throw new \Exception( __( 'No items in cart', 'voxel-payment-gateways' ) );
			}

			error_log( 'Wallet checkout: Cart has ' . count( $cart->get_items() ) . ' items' );

			// Create the order using Voxel's system
			$order = \Voxel\Product_Types\Orders\Order::create_from_cart( $cart, [
				'meta' => [
					'wallet_payment' => true,
				],
			] );

			if ( ! $order ) {
				throw new \Exception( __( 'Failed to create order', 'voxel-payment-gateways' ) );
			}

			$order_id = $order->get_id();
			error_log( 'Wallet checkout: Order created, id=' . $order_id );

			// Debit from wallet
			$result = \VoxelPayPal\Wallet_Client::debit( $user_id, $provided_total, [
				'type' => 'purchase',
				'reference_type' => 'order',
				'reference_id' => $order_id,
				'description' => sprintf( __( 'Payment for Order #%d', 'voxel-payment-gateways' ), $order_id ),
			] );

			if ( ! $result['success'] ) {
				// Failed to debit - cancel the order
				$order->set_status( \Voxel\ORDER_CANCELED );
				$order->save();

				throw new \Exception( $result['error'] ?? __( 'Failed to process wallet payment', 'voxel-payment-gateways' ) );
			}

			error_log( 'Wallet checkout: Debited from wallet' );

			// Update order status to completed
			// Use offline_payment as payment method since wallet payments don't need external sync
			$order->set_payment_method( 'offline_payment' );
			$order->set_status( \Voxel\ORDER_COMPLETED );
			$order->set_transaction_id( 'wallet_' . $result['transaction_id'] );
			$order->set_details( 'wallet.paid', true );
			$order->set_details( 'wallet.transaction_id', $result['transaction_id'] );
			$order->set_details( 'wallet.paid_at', \Voxel\utc()->format( 'Y-m-d H:i:s' ) );
			$order->set_details( 'pricing.total', $provided_total );
			$order->save();

			// Clear the cart
			$cart->empty();
			$cart->update();

			// Dispatch order completed event
			if ( class_exists( '\Voxel\Events\Products\Orders\Customer_Completed_Order_Event' ) ) {
				( new \Voxel\Events\Products\Orders\Customer_Completed_Order_Event() )->dispatch( $order_id );
			}

			// Get redirect URL using Voxel's built-in method
			$redirect_url = $order->get_success_redirect();

			error_log( 'Wallet checkout: Success, redirecting to ' . $redirect_url );

			wp_send_json( [
				'success' => true,
				'message' => __( 'Payment successful!', 'voxel-payment-gateways' ),
				'redirect_url' => $redirect_url,
				'order_id' => $order_id,
				'new_balance' => $result['new_balance'],
				'new_balance_formatted' => \VoxelPayPal\Wallet_Client::format_amount( $result['new_balance'] ),
			] );

		} catch ( \Exception $e ) {
			error_log( 'Wallet checkout error: ' . $e->getMessage() );
			wp_send_json( [
				'success' => false,
				'message' => $e->getMessage(),
			] );
		}
	}
}
