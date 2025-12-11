<?php

namespace VoxelPayPal\Controllers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Wallet Deposit Controller
 * Handles wallet top-up/deposit flow using configured payment gateway
 */
class Wallet_Deposit_Controller extends \Voxel\Controllers\Base_Controller {

	protected function hooks(): void {
		// Initiate deposit (create payment and redirect to gateway)
		$this->on( 'voxel_ajax_wallet.deposit.initiate', '@handle_initiate_deposit' );

		// Success callback from gateway
		$this->on( 'voxel_ajax_wallet.deposit.success', '@handle_deposit_success' );
		$this->on( 'voxel_ajax_nopriv_wallet.deposit.success', '@handle_deposit_success' );

		// Cancel callback from gateway
		$this->on( 'voxel_ajax_wallet.deposit.cancel', '@handle_deposit_cancel' );
		$this->on( 'voxel_ajax_nopriv_wallet.deposit.cancel', '@handle_deposit_cancel' );

		// Hook into payment completion events to credit wallet
		$this->on( 'voxel/paypal/payment-captured', '@maybe_credit_wallet_deposit', 10, 2 );
		$this->on( 'voxel/stripe/payment-completed', '@maybe_credit_wallet_deposit', 10, 2 );
		$this->on( 'voxel/paystack/payment-captured', '@maybe_credit_wallet_deposit', 10, 2 );
		$this->on( 'voxel/mercadopago/payment-captured', '@maybe_credit_wallet_deposit', 10, 2 );
	}

	/**
	 * Initiate wallet deposit
	 */
	protected function handle_initiate_deposit(): void {
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
				'message' => __( 'Please log in to add funds', 'voxel-payment-gateways' ),
			] );
			return;
		}

		// Get JSON input
		$input = json_decode( file_get_contents( 'php://input' ), true );

		if ( ! is_array( $input ) || empty( $input['amount'] ) ) {
			wp_send_json( [
				'success' => false,
				'message' => __( 'Invalid request', 'voxel-payment-gateways' ),
			] );
			return;
		}

		$amount = floatval( $input['amount'] );
		$return_url = isset( $input['return_url'] ) ? esc_url_raw( $input['return_url'] ) : '';

		// Validate amount
		$validation = \VoxelPayPal\Wallet_Client::validate_deposit_amount( $amount );
		if ( ! $validation['valid'] ) {
			wp_send_json( [
				'success' => false,
				'message' => $validation['error'],
			] );
			return;
		}

		$currency = \VoxelPayPal\Wallet_Client::get_site_currency();

		// Determine which payment gateway to use
		$gateway = $this->get_active_payment_gateway();

		if ( ! $gateway ) {
			wp_send_json( [
				'success' => false,
				'message' => __( 'No payment gateway configured', 'voxel-payment-gateways' ),
			] );
			return;
		}

		try {
			// Create the deposit based on gateway type
			$result = $this->create_gateway_deposit( $gateway, $user_id, $amount, $currency, $return_url );

			if ( ! $result['success'] ) {
				wp_send_json( $result );
				return;
			}

			wp_send_json( [
				'success' => true,
				'redirect_url' => $result['redirect_url'],
				'deposit_id' => $result['deposit_id'] ?? null,
			] );

		} catch ( \Exception $e ) {
			wp_send_json( [
				'success' => false,
				'message' => $e->getMessage(),
			] );
		}
	}

	/**
	 * Get the active payment gateway
	 */
	private function get_active_payment_gateway(): ?string {
		// Check Stripe - Voxel stores keys at payments.stripe.sandbox.api_key and payments.stripe.live.api_key
		$stripe_mode = \Voxel\get( 'payments.stripe.mode', 'sandbox' );
		$stripe_secret = ( $stripe_mode === 'live' )
			? \Voxel\get( 'payments.stripe.live.api_key' )
			: \Voxel\get( 'payments.stripe.sandbox.api_key' );

		if ( ! empty( $stripe_secret ) ) {
			return 'stripe';
		}

		// Check PayPal
		$paypal_enabled = (bool) \Voxel\get( 'payments.paypal.enabled', false );
		if ( $paypal_enabled ) {
			return 'paypal';
		}

		// Check Paystack
		$paystack_enabled = (bool) \Voxel\get( 'payments.paystack.enabled', false );
		if ( $paystack_enabled ) {
			return 'paystack';
		}

		// Check Mercado Pago
		$mercadopago_enabled = (bool) \Voxel\get( 'payments.mercadopago.enabled', false );
		if ( $mercadopago_enabled ) {
			return 'mercadopago';
		}

		return null;
	}

	/**
	 * Create deposit payment via the appropriate gateway
	 */
	private function create_gateway_deposit( string $gateway, int $user_id, float $amount, string $currency, string $return_url = '' ): array {
		switch ( $gateway ) {
			case 'stripe':
				return $this->create_stripe_deposit( $user_id, $amount, $currency, $return_url );

			case 'paypal':
				return $this->create_paypal_deposit( $user_id, $amount, $currency, $return_url );

			case 'paystack':
				return $this->create_paystack_deposit( $user_id, $amount, $currency, $return_url );

			case 'mercadopago':
				return $this->create_mercadopago_deposit( $user_id, $amount, $currency, $return_url );

			default:
				return [
					'success' => false,
					'message' => __( 'Unsupported payment gateway', 'voxel-payment-gateways' ),
				];
		}
	}

	/**
	 * Create Stripe deposit checkout session
	 */
	private function create_stripe_deposit( int $user_id, float $amount, string $currency, string $return_url = '' ): array {
		$amount_cents = (int) round( $amount * 100 );
		$user = get_userdata( $user_id );

		// Generate unique deposit ID
		$deposit_id = 'wallet_deposit_' . $user_id . '_' . time() . '_' . wp_rand( 1000, 9999 );

		// Store pending deposit with return URL
		$this->store_pending_deposit( $deposit_id, [
			'user_id' => $user_id,
			'return_url' => $return_url,
			'amount' => $amount,
			'currency' => $currency,
			'gateway' => 'stripe',
			'created_at' => current_time( 'mysql', true ),
		] );

		// Create Stripe checkout session
		try {
			$stripe = \Voxel\Modules\Stripe_Payments\Stripe_Client::getClient();

			$session_data = [
				'payment_method_types' => [ 'card' ],
				'line_items' => [
					[
						'price_data' => [
							'currency' => strtolower( $currency ),
							'product_data' => [
								'name' => __( 'Wallet Deposit', 'voxel-payment-gateways' ),
								'description' => sprintf(
									__( 'Add %s to your wallet', 'voxel-payment-gateways' ),
									\VoxelPayPal\Wallet_Client::format_amount( $amount, $currency )
								),
							],
							'unit_amount' => $amount_cents,
						],
						'quantity' => 1,
					],
				],
				'mode' => 'payment',
				'success_url' => add_query_arg( [
					'vx' => 1,
					'action' => 'wallet.deposit.success',
					'deposit_id' => $deposit_id,
					'gateway' => 'stripe',
				], home_url( '/' ) ),
				'cancel_url' => add_query_arg( [
					'vx' => 1,
					'action' => 'wallet.deposit.cancel',
					'deposit_id' => $deposit_id,
				], home_url( '/' ) ),
				'metadata' => [
					'wallet_deposit' => 'true',
					'deposit_id' => $deposit_id,
					'user_id' => $user_id,
				],
			];

			if ( $user && $user->user_email ) {
				$session_data['customer_email'] = $user->user_email;
			}

			$session = $stripe->checkout->sessions->create( $session_data );

			// Update pending deposit with session ID
			$this->update_pending_deposit( $deposit_id, [
				'stripe_session_id' => $session->id,
			] );

			return [
				'success' => true,
				'redirect_url' => $session->url,
				'deposit_id' => $deposit_id,
			];

		} catch ( \Exception $e ) {
			$this->delete_pending_deposit( $deposit_id );
			return [
				'success' => false,
				'message' => $e->getMessage(),
			];
		}
	}

	/**
	 * Create PayPal deposit
	 */
	private function create_paypal_deposit( int $user_id, float $amount, string $currency, string $return_url = '' ): array {
		$deposit_id = 'wallet_deposit_' . $user_id . '_' . time() . '_' . wp_rand( 1000, 9999 );

		// Store pending deposit with return URL
		$this->store_pending_deposit( $deposit_id, [
			'user_id' => $user_id,
			'return_url' => $return_url,
			'amount' => $amount,
			'currency' => $currency,
			'gateway' => 'paypal',
			'created_at' => current_time( 'mysql', true ),
		] );

		$amount_formatted = number_format( $amount, 2, '.', '' );

		$order_data = [
			'intent' => 'CAPTURE',
			'purchase_units' => [
				[
					'reference_id' => $deposit_id,
					'custom_id' => $deposit_id,
					'description' => __( 'Wallet Deposit', 'voxel-payment-gateways' ),
					'amount' => [
						'currency_code' => $currency,
						'value' => $amount_formatted,
					],
				],
			],
			'application_context' => [
				'brand_name' => get_bloginfo( 'name' ),
				'landing_page' => 'NO_PREFERENCE',
				'user_action' => 'PAY_NOW',
				'return_url' => add_query_arg( [
					'vx' => 1,
					'action' => 'wallet.deposit.success',
					'deposit_id' => $deposit_id,
					'gateway' => 'paypal',
				], home_url( '/' ) ),
				'cancel_url' => add_query_arg( [
					'vx' => 1,
					'action' => 'wallet.deposit.cancel',
					'deposit_id' => $deposit_id,
				], home_url( '/' ) ),
			],
		];

		$response = \VoxelPayPal\PayPal_Client::create_order( $order_data );

		if ( ! $response['success'] ) {
			$this->delete_pending_deposit( $deposit_id );
			return [
				'success' => false,
				'message' => $response['error'] ?? __( 'Failed to create PayPal order', 'voxel-payment-gateways' ),
			];
		}

		$paypal_order = $response['data'];

		// Update pending deposit with PayPal order ID
		$this->update_pending_deposit( $deposit_id, [
			'paypal_order_id' => $paypal_order['id'],
		] );

		// Find approval URL
		$approval_url = null;
		foreach ( $paypal_order['links'] as $link ) {
			if ( $link['rel'] === 'approve' ) {
				$approval_url = $link['href'];
				break;
			}
		}

		if ( ! $approval_url ) {
			$this->delete_pending_deposit( $deposit_id );
			return [
				'success' => false,
				'message' => __( 'PayPal approval URL not found', 'voxel-payment-gateways' ),
			];
		}

		return [
			'success' => true,
			'redirect_url' => $approval_url,
			'deposit_id' => $deposit_id,
		];
	}

	/**
	 * Create Paystack deposit
	 */
	private function create_paystack_deposit( int $user_id, float $amount, string $currency, string $return_url = '' ): array {
		$deposit_id = 'wallet_deposit_' . $user_id . '_' . time() . '_' . wp_rand( 1000, 9999 );
		$user = get_userdata( $user_id );

		// Store pending deposit with return URL
		$this->store_pending_deposit( $deposit_id, [
			'user_id' => $user_id,
			'return_url' => $return_url,
			'amount' => $amount,
			'currency' => $currency,
			'gateway' => 'paystack',
			'created_at' => current_time( 'mysql', true ),
		] );

		// Paystack uses kobo (smallest currency unit)
		$amount_kobo = (int) round( $amount * 100 );

		$callback_url = add_query_arg( [
			'vx' => 1,
			'action' => 'wallet.deposit.success',
			'deposit_id' => $deposit_id,
			'gateway' => 'paystack',
		], home_url( '/' ) );

		$response = \VoxelPayPal\Paystack_Client::initialize_transaction( [
			'email' => $user->user_email,
			'amount' => $amount_kobo,
			'currency' => $currency,
			'reference' => $deposit_id,
			'callback_url' => $callback_url,
			'metadata' => [
				'wallet_deposit' => true,
				'deposit_id' => $deposit_id,
				'user_id' => $user_id,
			],
		] );

		if ( ! $response['success'] ) {
			$this->delete_pending_deposit( $deposit_id );
			return [
				'success' => false,
				'message' => $response['error'] ?? __( 'Failed to initialize Paystack transaction', 'voxel-payment-gateways' ),
			];
		}

		// Update pending deposit with reference
		$this->update_pending_deposit( $deposit_id, [
			'paystack_reference' => $response['data']['reference'] ?? $deposit_id,
			'paystack_access_code' => $response['data']['access_code'] ?? null,
		] );

		return [
			'success' => true,
			'redirect_url' => $response['data']['authorization_url'],
			'deposit_id' => $deposit_id,
		];
	}

	/**
	 * Create Mercado Pago deposit
	 */
	private function create_mercadopago_deposit( int $user_id, float $amount, string $currency, string $return_url = '' ): array {
		$deposit_id = 'wallet_deposit_' . $user_id . '_' . time() . '_' . wp_rand( 1000, 9999 );
		$user = get_userdata( $user_id );

		// Store pending deposit with return URL
		$this->store_pending_deposit( $deposit_id, [
			'user_id' => $user_id,
			'return_url' => $return_url,
			'amount' => $amount,
			'currency' => $currency,
			'gateway' => 'mercadopago',
			'created_at' => current_time( 'mysql', true ),
		] );

		$success_url = add_query_arg( [
			'vx' => 1,
			'action' => 'wallet.deposit.success',
			'deposit_id' => $deposit_id,
			'gateway' => 'mercadopago',
		], home_url( '/' ) );

		$cancel_url = add_query_arg( [
			'vx' => 1,
			'action' => 'wallet.deposit.cancel',
			'deposit_id' => $deposit_id,
		], home_url( '/' ) );

		$preference_data = [
			'items' => [
				[
					'title' => __( 'Wallet Deposit', 'voxel-payment-gateways' ),
					'quantity' => 1,
					'unit_price' => $amount,
					'currency_id' => $currency,
				],
			],
			'payer' => [
				'email' => $user->user_email,
			],
			'back_urls' => [
				'success' => $success_url,
				'failure' => $cancel_url,
				'pending' => $success_url,
			],
			'auto_return' => 'approved',
			'external_reference' => $deposit_id,
			'metadata' => [
				'wallet_deposit' => 'true',
				'deposit_id' => $deposit_id,
				'user_id' => $user_id,
			],
		];

		$response = \VoxelPayPal\MercadoPago_Client::create_preference( $preference_data );

		if ( ! $response['success'] ) {
			$this->delete_pending_deposit( $deposit_id );
			return [
				'success' => false,
				'message' => $response['error'] ?? __( 'Failed to create Mercado Pago preference', 'voxel-payment-gateways' ),
			];
		}

		// Update pending deposit
		$this->update_pending_deposit( $deposit_id, [
			'mercadopago_preference_id' => $response['data']['id'] ?? null,
		] );

		$checkout_url = \VoxelPayPal\MercadoPago_Client::is_test_mode()
			? $response['data']['sandbox_init_point']
			: $response['data']['init_point'];

		return [
			'success' => true,
			'redirect_url' => $checkout_url,
			'deposit_id' => $deposit_id,
		];
	}

	/**
	 * Handle deposit success callback
	 */
	protected function handle_deposit_success(): void {
		$deposit_id = sanitize_text_field( $_GET['deposit_id'] ?? '' );
		$gateway = sanitize_text_field( $_GET['gateway'] ?? '' );

		if ( empty( $deposit_id ) ) {
			wp_die( __( 'Invalid deposit', 'voxel-payment-gateways' ) );
		}

		$pending = $this->get_pending_deposit( $deposit_id );

		if ( ! $pending ) {
			wp_die( __( 'Deposit not found', 'voxel-payment-gateways' ) );
		}

		$return_url = $pending['return_url'] ?? '';

		// Check if already processed
		if ( ! empty( $pending['processed'] ) ) {
			$this->redirect_after_deposit( true, __( 'Funds already added to your wallet', 'voxel-payment-gateways' ), $return_url );
			return;
		}

		try {
			// Verify payment with gateway
			$verified = $this->verify_gateway_payment( $gateway, $pending );

			if ( ! $verified ) {
				$this->redirect_after_deposit( false, __( 'Payment verification failed', 'voxel-payment-gateways' ), $return_url );
				return;
			}

			// Credit the wallet
			$result = \VoxelPayPal\Wallet_Client::credit( $pending['user_id'], $pending['amount'], [
				'type' => 'deposit',
				'gateway' => $gateway,
				'gateway_transaction_id' => $pending['gateway_transaction_id'] ?? null,
				'description' => sprintf(
					__( 'Wallet deposit via %s', 'voxel-payment-gateways' ),
					ucfirst( $gateway )
				),
			] );

			if ( ! $result['success'] ) {
				$this->redirect_after_deposit( false, $result['error'], $return_url );
				return;
			}

			// Mark as processed
			$this->update_pending_deposit( $deposit_id, [
				'processed' => true,
				'processed_at' => current_time( 'mysql', true ),
				'wallet_transaction_id' => $result['transaction_id'],
			] );

			// Fire action
			do_action( 'voxel/wallet/deposit_completed', $pending['user_id'], $deposit_id, $pending['amount'] );

			$this->redirect_after_deposit( true, sprintf(
				__( '%s has been added to your wallet!', 'voxel-payment-gateways' ),
				\VoxelPayPal\Wallet_Client::format_amount( $pending['amount'] )
			), $return_url );

		} catch ( \Exception $e ) {
			$this->redirect_after_deposit( false, $e->getMessage(), $return_url );
		}
	}

	/**
	 * Verify payment with gateway
	 */
	private function verify_gateway_payment( string $gateway, array &$pending ): bool {
		switch ( $gateway ) {
			case 'stripe':
				return $this->verify_stripe_payment( $pending );

			case 'paypal':
				return $this->verify_paypal_payment( $pending );

			case 'paystack':
				return $this->verify_paystack_payment( $pending );

			case 'mercadopago':
				return $this->verify_mercadopago_payment( $pending );

			default:
				return false;
		}
	}

	/**
	 * Verify Stripe payment
	 */
	private function verify_stripe_payment( array &$pending ): bool {
		if ( empty( $pending['stripe_session_id'] ) ) {
			return false;
		}

		try {
			$stripe = \Voxel\Modules\Stripe_Payments\Stripe_Client::getClient();
			$session = $stripe->checkout->sessions->retrieve( $pending['stripe_session_id'] );

			if ( $session->payment_status !== 'paid' ) {
				return false;
			}

			$pending['gateway_transaction_id'] = $session->payment_intent;
			return true;

		} catch ( \Exception $e ) {
			return false;
		}
	}

	/**
	 * Verify PayPal payment
	 */
	private function verify_paypal_payment( array &$pending ): bool {
		if ( empty( $pending['paypal_order_id'] ) ) {
			return false;
		}

		// Capture the PayPal order
		$response = \VoxelPayPal\PayPal_Client::capture_order( $pending['paypal_order_id'] );

		if ( ! $response['success'] ) {
			// Check if already captured
			$get_response = \VoxelPayPal\PayPal_Client::get_order( $pending['paypal_order_id'] );
			if ( $get_response['success'] && $get_response['data']['status'] === 'COMPLETED' ) {
				$pending['gateway_transaction_id'] = $pending['paypal_order_id'];
				return true;
			}
			return false;
		}

		$paypal_order = $response['data'];

		if ( $paypal_order['status'] !== 'COMPLETED' ) {
			return false;
		}

		// Get capture ID
		if ( ! empty( $paypal_order['purchase_units'][0]['payments']['captures'][0]['id'] ) ) {
			$pending['gateway_transaction_id'] = $paypal_order['purchase_units'][0]['payments']['captures'][0]['id'];
		} else {
			$pending['gateway_transaction_id'] = $pending['paypal_order_id'];
		}

		return true;
	}

	/**
	 * Verify Paystack payment
	 */
	private function verify_paystack_payment( array &$pending ): bool {
		$reference = $_GET['reference'] ?? ( $pending['paystack_reference'] ?? null );

		if ( empty( $reference ) ) {
			return false;
		}

		$response = \VoxelPayPal\Paystack_Client::verify_transaction( $reference );

		if ( ! $response['success'] ) {
			return false;
		}

		if ( $response['data']['status'] !== 'success' ) {
			return false;
		}

		$pending['gateway_transaction_id'] = $response['data']['reference'];
		return true;
	}

	/**
	 * Verify Mercado Pago payment
	 */
	private function verify_mercadopago_payment( array &$pending ): bool {
		$payment_id = $_GET['payment_id'] ?? null;
		$status = $_GET['status'] ?? null;

		if ( $status !== 'approved' ) {
			return false;
		}

		if ( $payment_id ) {
			$pending['gateway_transaction_id'] = $payment_id;
		}

		return true;
	}

	/**
	 * Handle deposit cancellation
	 */
	protected function handle_deposit_cancel(): void {
		$deposit_id = sanitize_text_field( $_GET['deposit_id'] ?? '' );

		if ( ! empty( $deposit_id ) ) {
			$this->delete_pending_deposit( $deposit_id );
		}

		$this->redirect_after_deposit( false, __( 'Deposit was cancelled', 'voxel-payment-gateways' ) );
	}

	/**
	 * Redirect after deposit completion
	 */
	private function redirect_after_deposit( bool $success, string $message, string $return_url = '' ): void {
		// Use stored return URL if available
		if ( ! empty( $return_url ) ) {
			$redirect_url = $return_url;
		} else {
			// Try to get account page as fallback
			$account_page_id = \Voxel\get( 'templates.account' );
			if ( $account_page_id ) {
				$redirect_url = get_permalink( $account_page_id );
			} else {
				$redirect_url = home_url( '/' );
			}
		}

		$redirect_url = add_query_arg( [
			'wallet_deposit' => $success ? 'success' : 'failed',
			'wallet_message' => urlencode( $message ),
		], $redirect_url );

		wp_safe_redirect( $redirect_url );
		exit;
	}

	/**
	 * Maybe credit wallet for deposit order (webhook handler)
	 */
	protected function maybe_credit_wallet_deposit( $order, $event = null ): void {
		// Check if this is a wallet deposit order
		$is_deposit = $order->get_details( 'wallet.is_deposit' );

		if ( ! $is_deposit ) {
			return;
		}

		// Check if already credited
		$already_credited = $order->get_details( 'wallet.credited' );
		if ( $already_credited ) {
			return;
		}

		$user_id = $order->get_customer()->get_id();
		$amount = floatval( $order->get_details( 'pricing.total' ) );
		$gateway = $order->get_details( 'payment.gateway' );

		if ( $amount <= 0 || ! $user_id ) {
			return;
		}

		// Credit wallet
		$result = \VoxelPayPal\Wallet_Client::credit( $user_id, $amount, [
			'type' => 'deposit',
			'reference_type' => 'order',
			'reference_id' => $order->get_id(),
			'gateway' => $gateway,
			'description' => sprintf(
				__( 'Wallet deposit via %s (Order #%d)', 'voxel-payment-gateways' ),
				ucfirst( $gateway ),
				$order->get_id()
			),
		] );

		if ( $result['success'] ) {
			$order->set_details( 'wallet.credited', true );
			$order->set_details( 'wallet.credited_at', \Voxel\utc()->format( 'Y-m-d H:i:s' ) );
			$order->set_details( 'wallet.transaction_id', $result['transaction_id'] );
			$order->save();

			do_action( 'voxel/wallet/deposit_completed', $user_id, $order->get_id(), $amount );
		}
	}

	/**
	 * Store pending deposit in transient
	 */
	private function store_pending_deposit( string $deposit_id, array $data ): void {
		set_transient( 'wallet_deposit_' . $deposit_id, $data, HOUR_IN_SECONDS );
	}

	/**
	 * Get pending deposit from transient
	 */
	private function get_pending_deposit( string $deposit_id ): ?array {
		$data = get_transient( 'wallet_deposit_' . $deposit_id );
		return $data ?: null;
	}

	/**
	 * Update pending deposit
	 */
	private function update_pending_deposit( string $deposit_id, array $updates ): void {
		$data = $this->get_pending_deposit( $deposit_id );
		if ( $data ) {
			$data = array_merge( $data, $updates );
			$this->store_pending_deposit( $deposit_id, $data );
		}
	}

	/**
	 * Delete pending deposit
	 */
	private function delete_pending_deposit( string $deposit_id ): void {
		delete_transient( 'wallet_deposit_' . $deposit_id );
	}
}
