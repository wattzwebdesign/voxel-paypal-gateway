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
	 * Store pending deposit data
	 * We use transients during the payment flow, then create an order on success
	 */
	protected function store_pending_deposit( string $deposit_id, array $data ): void {
		set_transient( 'wallet_deposit_' . $deposit_id, $data, HOUR_IN_SECONDS );
	}

	/**
	 * Get pending deposit data
	 */
	protected function get_pending_deposit( string $deposit_id ): ?array {
		$data = get_transient( 'wallet_deposit_' . $deposit_id );
		return $data ?: null;
	}

	/**
	 * Update pending deposit data
	 */
	protected function update_pending_deposit( string $deposit_id, array $updates ): void {
		$data = $this->get_pending_deposit( $deposit_id );
		if ( $data ) {
			$data = array_merge( $data, $updates );
			$this->store_pending_deposit( $deposit_id, $data );
		}
	}

	/**
	 * Delete pending deposit data
	 */
	protected function delete_pending_deposit( string $deposit_id ): void {
		delete_transient( 'wallet_deposit_' . $deposit_id );
	}

	/**
	 * Create a completed wallet deposit order
	 * Called after payment is confirmed - creates order in completed state
	 * Orders are stored in Voxel's custom vx_orders table
	 */
	protected function create_completed_deposit_order( int $user_id, float $amount, string $currency, string $gateway, string $transaction_id ): ?int {
		global $wpdb;

		// Get or create system post for wallet deposits
		$wallet_post_id = $this->get_wallet_system_post_id();

		// Build order details
		$details = [
			'wallet' => [
				'is_deposit' => true,
				'deposit_amount' => $amount,
				'gateway' => $gateway,
				'gateway_transaction_id' => $transaction_id,
				'created_at' => \Voxel\utc()->format( 'Y-m-d H:i:s' ),
			],
			'pricing' => [
				'total' => $amount,
				'currency' => $currency,
			],
			'cart' => [
				'type' => 'direct_cart',
				'items' => [],
			],
			'meta' => [],
		];

		// Insert into Voxel's orders table
		$result = $wpdb->insert( $wpdb->prefix . 'vx_orders', [
			'customer_id' => $user_id,
			'vendor_id' => null,
			'status' => 'completed',
			'shipping_status' => null,
			'payment_method' => $gateway . '_payment',
			'transaction_id' => $transaction_id,
			'details' => wp_json_encode( $details ),
			'parent_id' => null,
			'testmode' => \Voxel\is_test_mode() ? 1 : 0,
			'created_at' => \Voxel\utc()->format( 'Y-m-d H:i:s' ),
		] );

		if ( $result === false ) {
			return null;
		}

		$order_id = $wpdb->insert_id;

		// Insert order item for display
		// 'type' => 'regular' is required for Voxel to load the item
		$item_details = [
			'type' => 'regular',
			'product' => [
				'label' => __( 'Wallet Reload', 'voxel-payment-gateways' ),
			],
			'currency' => $currency,
			'summary' => [
				'quantity' => 1,
				'amount_per_unit' => $amount,
				'total_amount' => $amount,
			],
		];

		$wpdb->insert( $wpdb->prefix . 'vx_order_items', [
			'order_id' => $order_id,
			'post_id' => $wallet_post_id,
			'product_type' => 'wallet_deposit',
			'field_key' => 'voxel:wallet',
			'details' => wp_json_encode( $item_details ),
		] );

		return $order_id;
	}

	/**
	 * Get or create the system post used for wallet deposit orders
	 */
	protected function get_wallet_system_post_id(): int {
		$post_id = get_option( 'voxel_wallet_system_post_id' );

		if ( $post_id && get_post( $post_id ) ) {
			return (int) $post_id;
		}

		// Create hidden system post
		$post_id = wp_insert_post( [
			'post_type' => 'page',
			'post_status' => 'private',
			'post_title' => __( 'Wallet Deposit', 'voxel-payment-gateways' ),
			'post_content' => '',
			'post_author' => 1,
		] );

		if ( $post_id && ! is_wp_error( $post_id ) ) {
			update_option( 'voxel_wallet_system_post_id', $post_id );
			return $post_id;
		}

		// Fallback to site homepage
		return (int) get_option( 'page_on_front', 1 );
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
		$deposit_id = wp_generate_uuid4();

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

			// Store deposit data in transient
			$this->store_pending_deposit( $deposit_id, [
				'user_id' => $user_id,
				'amount' => $amount,
				'currency' => $currency,
				'gateway' => 'stripe',
				'session_id' => $session->id,
				'return_url' => $return_url,
				'created_at' => time(),
			] );

			return [
				'success' => true,
				'redirect_url' => $session->url,
				'deposit_id' => $deposit_id,
			];

		} catch ( \Exception $e ) {
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
		// Generate unique deposit ID
		$deposit_id = wp_generate_uuid4();

		$amount_formatted = number_format( $amount, 2, '.', '' );

		$paypal_order_data = [
			'intent' => 'CAPTURE',
			'purchase_units' => [
				[
					'reference_id' => 'wallet_deposit_' . $deposit_id,
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

		$response = \VoxelPayPal\PayPal_Client::create_order( $paypal_order_data );

		if ( ! $response['success'] ) {
			return [
				'success' => false,
				'message' => $response['error'] ?? __( 'Failed to create PayPal order', 'voxel-payment-gateways' ),
			];
		}

		$paypal_order = $response['data'];

		// Find approval URL
		$approval_url = null;
		foreach ( $paypal_order['links'] as $link ) {
			if ( $link['rel'] === 'approve' ) {
				$approval_url = $link['href'];
				break;
			}
		}

		if ( ! $approval_url ) {
			return [
				'success' => false,
				'message' => __( 'PayPal approval URL not found', 'voxel-payment-gateways' ),
			];
		}

		// Store deposit data in transient
		$this->store_pending_deposit( $deposit_id, [
			'user_id' => $user_id,
			'amount' => $amount,
			'currency' => $currency,
			'gateway' => 'paypal',
			'paypal_order_id' => $paypal_order['id'],
			'return_url' => $return_url,
			'created_at' => time(),
		] );

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
		$user = get_userdata( $user_id );

		// Generate unique deposit ID
		$deposit_id = wp_generate_uuid4();

		// Paystack uses kobo (smallest currency unit)
		$amount_kobo = (int) round( $amount * 100 );

		// Generate reference using deposit ID
		$reference = 'wallet_deposit_' . $deposit_id;

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
			'reference' => $reference,
			'callback_url' => $callback_url,
			'metadata' => [
				'wallet_deposit' => true,
				'deposit_id' => $deposit_id,
				'user_id' => $user_id,
			],
		] );

		if ( ! $response['success'] ) {
			return [
				'success' => false,
				'message' => $response['error'] ?? __( 'Failed to initialize Paystack transaction', 'voxel-payment-gateways' ),
			];
		}

		// Store deposit data in transient
		$this->store_pending_deposit( $deposit_id, [
			'user_id' => $user_id,
			'amount' => $amount,
			'currency' => $currency,
			'gateway' => 'paystack',
			'paystack_reference' => $response['data']['reference'] ?? $reference,
			'paystack_access_code' => $response['data']['access_code'] ?? null,
			'return_url' => $return_url,
			'created_at' => time(),
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
		$user = get_userdata( $user_id );

		// Generate unique deposit ID
		$deposit_id = wp_generate_uuid4();

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
			'external_reference' => 'wallet_deposit_' . $deposit_id,
			'metadata' => [
				'wallet_deposit' => 'true',
				'deposit_id' => $deposit_id,
				'user_id' => $user_id,
			],
		];

		$response = \VoxelPayPal\MercadoPago_Client::create_preference( $preference_data );

		if ( ! $response['success'] ) {
			return [
				'success' => false,
				'message' => $response['error'] ?? __( 'Failed to create Mercado Pago preference', 'voxel-payment-gateways' ),
			];
		}

		// Store deposit data in transient
		$this->store_pending_deposit( $deposit_id, [
			'user_id' => $user_id,
			'amount' => $amount,
			'currency' => $currency,
			'gateway' => 'mercadopago',
			'mercadopago_preference_id' => $response['data']['id'] ?? null,
			'return_url' => $return_url,
			'created_at' => time(),
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

		// Get pending deposit from transient
		$deposit = $this->get_pending_deposit( $deposit_id );

		if ( ! $deposit ) {
			wp_die( __( 'Deposit not found or expired', 'voxel-payment-gateways' ) );
		}

		// Check if already processed
		if ( ! empty( $deposit['processed'] ) ) {
			$return_url = $deposit['return_url'] ?? '';
			$this->redirect_after_deposit( true, __( 'Funds already added to your wallet', 'voxel-payment-gateways' ), $return_url );
			return;
		}

		$user_id = $deposit['user_id'];
		$amount = floatval( $deposit['amount'] );
		$currency = $deposit['currency'];
		$return_url = $deposit['return_url'] ?? '';

		// Verify deposit belongs to current user (if logged in)
		$current_user_id = get_current_user_id();
		if ( $current_user_id && $current_user_id !== $user_id ) {
			wp_die( __( 'Permission denied', 'voxel-payment-gateways' ) );
		}

		try {
			// Verify payment with gateway
			$verification = $this->verify_gateway_payment( $gateway, $deposit );

			if ( ! $verification['success'] ) {
				$this->redirect_after_deposit( false, __( 'Payment verification failed', 'voxel-payment-gateways' ), $return_url );
				return;
			}

			$gateway_transaction_id = $verification['transaction_id'] ?? '';

			// Create the Voxel order now that payment is confirmed
			$order_id = $this->create_completed_deposit_order( $user_id, $amount, $currency, $gateway, $gateway_transaction_id );

			// Credit the wallet
			$result = \VoxelPayPal\Wallet_Client::credit( $user_id, $amount, [
				'type' => 'deposit',
				'reference_type' => 'order',
				'reference_id' => $order_id ?: 0,
				'gateway' => $gateway,
				'gateway_transaction_id' => $gateway_transaction_id,
				'description' => $order_id
					? sprintf( __( 'Wallet deposit via %s (Order #%d)', 'voxel-payment-gateways' ), ucfirst( $gateway ), $order_id )
					: sprintf( __( 'Wallet deposit via %s', 'voxel-payment-gateways' ), ucfirst( $gateway ) ),
			] );

			if ( ! $result['success'] ) {
				$this->redirect_after_deposit( false, $result['error'], $return_url );
				return;
			}

			// Update order with wallet transaction ID if order was created
			if ( $order_id ) {
				$order = \Voxel\Product_Types\Orders\Order::get( $order_id );
				if ( $order ) {
					$order->set_details( 'wallet.credited', true );
					$order->set_details( 'wallet.credited_at', \Voxel\utc()->format( 'Y-m-d H:i:s' ) );
					$order->set_details( 'wallet.transaction_id', $result['transaction_id'] );
					$order->save();
				}
			}

			// Mark deposit as processed and delete transient
			$this->update_pending_deposit( $deposit_id, [ 'processed' => true, 'order_id' => $order_id ] );
			$this->delete_pending_deposit( $deposit_id );

			// Fire action
			do_action( 'voxel/wallet/deposit_completed', $user_id, $order_id, $amount );

			$this->redirect_after_deposit( true, sprintf(
				__( '%s has been added to your wallet!', 'voxel-payment-gateways' ),
				\VoxelPayPal\Wallet_Client::format_amount( $amount )
			), $return_url );

		} catch ( \Exception $e ) {
			$this->redirect_after_deposit( false, $e->getMessage(), $return_url );
		}
	}

	/**
	 * Verify payment with gateway using transient deposit data
	 */
	private function verify_gateway_payment( string $gateway, array $deposit ): array {
		switch ( $gateway ) {
			case 'stripe':
				return $this->verify_stripe_payment( $deposit );

			case 'paypal':
				return $this->verify_paypal_payment( $deposit );

			case 'paystack':
				return $this->verify_paystack_payment( $deposit );

			case 'mercadopago':
				return $this->verify_mercadopago_payment( $deposit );

			default:
				return [ 'success' => false ];
		}
	}

	/**
	 * Verify Stripe payment
	 */
	private function verify_stripe_payment( array $deposit ): array {
		$session_id = $deposit['session_id'] ?? '';

		if ( empty( $session_id ) ) {
			return [ 'success' => false ];
		}

		try {
			$stripe = \Voxel\Modules\Stripe_Payments\Stripe_Client::getClient();
			$session = $stripe->checkout->sessions->retrieve( $session_id );

			if ( $session->payment_status !== 'paid' ) {
				return [ 'success' => false ];
			}

			return [
				'success' => true,
				'transaction_id' => $session->payment_intent,
			];

		} catch ( \Exception $e ) {
			return [ 'success' => false ];
		}
	}

	/**
	 * Verify PayPal payment
	 */
	private function verify_paypal_payment( array $deposit ): array {
		$paypal_order_id = $deposit['paypal_order_id'] ?? '';

		if ( empty( $paypal_order_id ) ) {
			return [ 'success' => false ];
		}

		// Capture the PayPal order
		$response = \VoxelPayPal\PayPal_Client::capture_order( $paypal_order_id );

		if ( ! $response['success'] ) {
			// Check if already captured
			$get_response = \VoxelPayPal\PayPal_Client::get_order( $paypal_order_id );
			if ( $get_response['success'] && $get_response['data']['status'] === 'COMPLETED' ) {
				return [
					'success' => true,
					'transaction_id' => $paypal_order_id,
				];
			}
			return [ 'success' => false ];
		}

		$paypal_order = $response['data'];

		if ( $paypal_order['status'] !== 'COMPLETED' ) {
			return [ 'success' => false ];
		}

		// Get capture ID
		$capture_id = $paypal_order_id;
		if ( ! empty( $paypal_order['purchase_units'][0]['payments']['captures'][0]['id'] ) ) {
			$capture_id = $paypal_order['purchase_units'][0]['payments']['captures'][0]['id'];
		}

		return [
			'success' => true,
			'transaction_id' => $capture_id,
		];
	}

	/**
	 * Verify Paystack payment
	 */
	private function verify_paystack_payment( array $deposit ): array {
		$reference = $_GET['reference'] ?? ( $deposit['paystack_reference'] ?? '' );

		if ( empty( $reference ) ) {
			return [ 'success' => false ];
		}

		$response = \VoxelPayPal\Paystack_Client::verify_transaction( $reference );

		if ( ! $response['success'] ) {
			return [ 'success' => false ];
		}

		if ( $response['data']['status'] !== 'success' ) {
			return [ 'success' => false ];
		}

		return [
			'success' => true,
			'transaction_id' => $response['data']['reference'],
		];
	}

	/**
	 * Verify Mercado Pago payment
	 */
	private function verify_mercadopago_payment( array $deposit ): array {
		$payment_id = $_GET['payment_id'] ?? null;
		$status = $_GET['status'] ?? null;

		if ( $status !== 'approved' ) {
			return [ 'success' => false ];
		}

		return [
			'success' => true,
			'transaction_id' => $payment_id ?: ( $deposit['mercadopago_preference_id'] ?? '' ),
		];
	}

	/**
	 * Handle deposit cancellation
	 */
	protected function handle_deposit_cancel(): void {
		$deposit_id = sanitize_text_field( $_GET['deposit_id'] ?? '' );
		$return_url = '';

		if ( $deposit_id ) {
			$deposit = $this->get_pending_deposit( $deposit_id );
			if ( $deposit ) {
				$return_url = $deposit['return_url'] ?? '';
				$this->delete_pending_deposit( $deposit_id );
			}
		}

		$this->redirect_after_deposit( false, __( 'Deposit was cancelled', 'voxel-payment-gateways' ), $return_url );
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
		$amount = floatval( $order->get_details( 'wallet.deposit_amount' ) ?: $order->get_details( 'pricing.total' ) );
		$gateway = $order->get_details( 'wallet.gateway' );

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
				ucfirst( $gateway ?: 'unknown' ),
				$order->get_id()
			),
		] );

		if ( $result['success'] ) {
			$order->set_status( \Voxel\ORDER_COMPLETED );
			$order->set_details( 'wallet.credited', true );
			$order->set_details( 'wallet.credited_at', \Voxel\utc()->format( 'Y-m-d H:i:s' ) );
			$order->set_details( 'wallet.transaction_id', $result['transaction_id'] );
			$order->save();

			do_action( 'voxel/wallet/deposit_completed', $user_id, $order->get_id(), $amount );
		}
	}
}
